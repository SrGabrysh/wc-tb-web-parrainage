<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Processeur automatique de remises parrain avec workflow asynchrone
 * 
 * Responsabilité unique : Orchestrer le processus complet d'application des remises
 * Principe SRP : Séparation claire des phases (marquage, programmation, traitement)
 * Principe OCP : Extensible via hooks WordPress pour personnalisations
 * 
 * @since 2.6.0
 */
class AutomaticDiscountProcessor {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Service de calcul des remises
     * @var DiscountCalculator
     */
    private $discount_calculator;
    
    /**
     * Service de validation des remises
     * @var DiscountValidator
     */
    private $discount_validator;
    
    /**
     * Service de notifications
     * @var DiscountNotificationService
     */
    private $notification_service;
    
    /**
     * NOUVEAU v2.7.0 : Gestionnaire d'application des remises
     * @var SubscriptionDiscountManager|null
     */
    private $subscription_discount_manager = null;
    
    /**
     * Statuts de workflow supportés
     * @var array
     */
    private $workflow_statuses = array(
        'pending',
        'calculated', 
        'simulated',
        // v2.7.x
        'applied',
        'application_failed',
        'active',
        'error',
        'retry'
    );
    
    /**
     * Constructor avec injection de dépendances
     * 
     * @param Logger $logger Instance du logger
     * @param DiscountCalculator $discount_calculator Service de calcul
     * @param DiscountValidator $discount_validator Service de validation
     * @param DiscountNotificationService $notification_service Service notifications
     */
    public function __construct( 
        Logger $logger, 
        DiscountCalculator $discount_calculator, 
        DiscountValidator $discount_validator, 
        DiscountNotificationService $notification_service 
    ) {
        $this->logger = $logger;
        $this->discount_calculator = $discount_calculator;
        $this->discount_validator = $discount_validator;
        $this->notification_service = $notification_service;
    }

    /**
     * v2.7.1: injection du gestionnaire d'application réelle des remises
     */
    public function set_subscription_discount_manager( SubscriptionDiscountManager $manager ) {
        $this->subscription_discount_manager = $manager;
    }
    
    /**
     * Initialise le workflow asynchrone avec hooks WordPress
     * 
     * @return void
     */
    public function init() {
        // PHASE 1 : Marquage synchrone rapide au checkout
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_parrainage_order' ), 10, 1 );
        
        // PHASE 2 : Programmation asynchrone lors activation abonnement filleul
        add_action( 'woocommerce_subscription_status_active', array( $this, 'schedule_parrain_discount' ), 10, 1 );
        
        // NOUVEAU v2.8.0 : Surveillance changements statut filleul vers inactivité
        add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'handle_filleul_suspension' ), 10, 1 );
        add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'handle_filleul_suspension' ), 10, 1 );
        add_action( 'woocommerce_subscription_status_expired', array( $this, 'handle_filleul_suspension' ), 10, 1 );
        
        // PHASE 3 : Traitement différé via hook CRON personnalisé
        add_action( WC_TB_PARRAINAGE_QUEUE_HOOK, array( $this, 'process_parrain_discount_async' ), 10, 3 );
        
        // v2.7.1 : Fin de remise programmée
        add_action( WC_TB_PARRAINAGE_END_DISCOUNT_HOOK, array( $this, 'end_parrain_discount' ), 10, 2 );
        
        // Gestion des retry automatiques
        add_action( 'tb_parrainage_retry_discount', array( $this, 'retry_failed_discount' ), 10, 4 );
        
        $this->logger->info(
            'Workflow asynchrone de remises parrain initialisé',
            array(
                'hooks_registered' => array(
                    'woocommerce_checkout_order_processed',
                    'woocommerce_subscription_status_active',
                    'woocommerce_subscription_status_cancelled',  // NOUVEAU v2.8.0
                    'woocommerce_subscription_status_on-hold',    // NOUVEAU v2.8.0
                    'woocommerce_subscription_status_expired',    // NOUVEAU v2.8.0
                    WC_TB_PARRAINAGE_QUEUE_HOOK,
                    'tb_parrainage_retry_discount'
                ),
                'version' => '2.8.0-dev'
            ),
            'discount-processor'
        );
    }
    
    /**
     * PHASE 1 : Marquage synchrone des commandes avec parrainage
     * Exécution < 50ms pour maintenir fluidité du checkout
     * 
     * @param int $order_id ID de la commande
     * @return void
     */
    public function mark_parrainage_order( $order_id ) {
        try {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            
            $code_parrain = $order->get_meta( '_billing_parrain_code' );
            
            if ( $code_parrain && ! empty( trim( $code_parrain ) ) ) {
                // Validation rapide du format code parrain
                if ( $this->validate_parrain_code_format( $code_parrain ) ) {
                    // Marquage pour traitement différé
                    $order->update_meta_data( '_pending_parrain_discount', $code_parrain );
                    $order->update_meta_data( '_parrainage_workflow_status', 'pending' );
                    $order->update_meta_data( '_parrainage_marked_date', current_time( 'mysql' ) );
                    $order->save();
                    
                    $this->logger->info(
                        'Commande marquée pour remise parrainage différée',
                        array(
                            'order_id' => $order_id,
                            'code_parrain' => $code_parrain,
                            'marked_at' => current_time( 'mysql' )
                        ),
                        'discount-processor'
                    );
                }
            }
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur lors du marquage de commande parrainage',
                array(
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'discount-processor'
            );
        }
    }
    
    /**
     * PHASE 2 : Programmation asynchrone lors activation abonnement filleul
     * 
     * @param WC_Subscription $subscription Instance de l'abonnement activé
     * @return void
     */
    public function schedule_parrain_discount( $subscription ) {
        try {
            $parent_order = $subscription->get_parent();
            if ( ! $parent_order ) {
                return;
            }
            
            $pending_discount = $parent_order->get_meta( '_pending_parrain_discount' );
            
            if ( $pending_discount && $parent_order->get_meta( '_parrainage_workflow_status' ) === 'pending' ) {
                // Programmation avec délai de sécurité (filtrable)
                $delay = (int) \apply_filters( 'tb_parrainage_async_delay', WC_TB_PARRAINAGE_ASYNC_DELAY );
                $schedule_time = time() + max( 0, $delay );
                
                $scheduled = wp_schedule_single_event(
                    $schedule_time,
                    WC_TB_PARRAINAGE_QUEUE_HOOK,
                    array(
                        $parent_order->get_id(),
                        $subscription->get_id(),
                        1 // Tentative #1
                    )
                );
                
                if ( $scheduled ) {
                    // Mise à jour des métadonnées
                    $parent_order->update_meta_data( '_parrainage_scheduled_time', $schedule_time );
                    $parent_order->update_meta_data( '_parrainage_workflow_status', 'scheduled' );
                    $parent_order->save();
                    
                    $this->logger->info(
                        'Remise parrainage programmée avec succès',
                        array(
                            'filleul_order_id' => $parent_order->get_id(),
                            'filleul_subscription_id' => $subscription->get_id(),
                            'scheduled_time' => date( 'Y-m-d H:i:s', $schedule_time ),
                            'delay_minutes' => WC_TB_PARRAINAGE_ASYNC_DELAY / 60
                        ),
                        'discount-processor'
                    );
                } else {
                    // Fallback pour problème CRON: exécuter immédiatement
                    $this->handle_cron_failure( $parent_order->get_id(), $subscription->get_id() );
                    $this->logger->warning(
                        'CRON non planifié - exécution immédiate du traitement asynchrone',
                        array(
                            'order_id' => $parent_order->get_id(),
                            'subscription_id' => $subscription->get_id()
                        ),
                        'discount-processor'
                    );
                    $this->process_parrain_discount_async( $parent_order->get_id(), $subscription->get_id(), 1 );
                }
            }
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur lors de la programmation de remise asynchrone',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'discount-processor'
            );
        }
    }
    
    /**
     * PHASE 3 : Traitement différé robuste avec calculs réels
     * 
     * @param int $order_id ID commande filleul
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param int $attempt_number Numéro de tentative
     * @return void
     */
    public function process_parrain_discount_async( $order_id, $filleul_subscription_id, $attempt_number = 1 ) {
        // Marquer l'exécution CRON pour monitoring
        update_option( 'tb_parrainage_last_cron_run', time() );
        
        $this->logger->info(
            'DÉBUT traitement asynchrone remise parrainage',
            array(
                'order_id' => $order_id,
                'subscription_id' => $filleul_subscription_id,
                'attempt' => $attempt_number
            ),
            'discount-processor'
        );
        
        try {
            // Vérifications préalables de sécurité
            if ( ! $this->validate_processing_conditions( $order_id, $filleul_subscription_id ) ) {
                return;
            }
            
            $order = wc_get_order( $order_id );
            $code_parrain = $order->get_meta( '_billing_parrain_code' );
            $parrain_subscription_id = intval( $code_parrain );
            
            // Validation complète de l'éligibilité
            $product_ids = $this->get_order_product_ids( $order );
            $eligible_products = array();
            
            $this->logger->debug(
                'Début validation éligibilité produits',
                array(
                    'order_id' => $order_id,
                    'product_ids' => $product_ids,
                    'parrain_subscription_id' => $parrain_subscription_id
                ),
                'discount-processor'
            );
            
            foreach ( $product_ids as $product_id ) {
                $validation = $this->discount_validator->validate_discount_eligibility( 
                    $parrain_subscription_id, 
                    $order_id, 
                    $product_id 
                );
                
                $this->logger->debug(
                    'Validation produit individuel',
                    array(
                        'product_id' => $product_id,
                        'is_eligible' => $validation['is_eligible'],
                        'errors' => $validation['errors'] ?? array(),
                        'details' => $validation['details'] ?? array()
                    ),
                    'discount-processor'
                );
                
                if ( $validation['is_eligible'] ) {
                    $eligible_products[] = $product_id;
                }
            }
            
            if ( empty( $eligible_products ) ) {
                $this->logger->error(
                    'Aucun produit éligible pour remise parrain - détails complets',
                    array(
                        'order_id' => $order_id,
                        'product_ids' => $product_ids,
                        'eligible_products' => $eligible_products,
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'code_parrain' => $code_parrain
                    ),
                    'discount-processor'
                );
                throw new \InvalidArgumentException( 'Aucun produit éligible pour remise parrain' );
            }
            
            // Calculs réels des remises
            $discount_results = array();
            $parrain_subscription = wcs_get_subscription( $parrain_subscription_id );
            $current_price = $parrain_subscription ? $parrain_subscription->get_total() : 0;
            
            foreach ( $eligible_products as $product_id ) {
                $discount_data = $this->discount_calculator->calculate_parrain_discount( 
                    $product_id, 
                    $current_price, 
                    $parrain_subscription_id 
                );
                
                if ( $discount_data ) {
                    $discount_results[] = $discount_data;
                }
            }
            
            if ( ! empty( $discount_results ) ) {
                // v2.7.1 : bascule selon mode simulation/production
                $simulation_mode = defined( 'WC_TB_PARRAINAGE_SIMULATION_MODE' ) ? WC_TB_PARRAINAGE_SIMULATION_MODE : true;
                $status = 'calculated';
                
                // Lisibilité accrue: simulation explicite uniquement si true
                if ( $simulation_mode === true ) {
                    // Mode simulation (comportement v2.6.0)
                    $this->store_calculated_discount_results( $order, $discount_results );
                    $order->update_meta_data( '_tb_parrainage_calculated', current_time( 'mysql' ) );
                    $status = 'simulated';
                } else {
                    // Application réelle requiert le gestionnaire
                    if ( ! $this->subscription_discount_manager ) {
                        throw new \RuntimeException( 'SubscriptionDiscountManager non disponible' );
                    }
                    $application_result = $this->subscription_discount_manager->apply_discount(
                        $parrain_subscription_id,
                        $discount_results[0],
                        $filleul_subscription_id,
                        $order_id
                    );
                    if ( ! empty( $application_result['success'] ) ) {
                        // Programmer la fin de remise
                        $months = defined( 'WC_TB_PARRAINAGE_DISCOUNT_DURATION' ) ? WC_TB_PARRAINAGE_DISCOUNT_DURATION : 12;
                        $grace = defined( 'WC_TB_PARRAINAGE_DISCOUNT_GRACE_PERIOD' ) ? WC_TB_PARRAINAGE_DISCOUNT_GRACE_PERIOD : 2;
                        $end_time = strtotime( "+{$months} months +{$grace} days" );
                        wp_schedule_single_event( $end_time, WC_TB_PARRAINAGE_END_DISCOUNT_HOOK, array( $parrain_subscription_id, $filleul_subscription_id ) );
                        $order->update_meta_data( '_tb_parrainage_applied', current_time( 'mysql' ) );
                        $order->update_meta_data( '_tb_parrainage_end_scheduled', date( 'Y-m-d H:i:s', $end_time ) );
                        $status = 'applied';
                    } else {
                        $status = 'application_failed';
                        $order->update_meta_data( '_tb_parrainage_application_error', $application_result['error'] ?? 'unknown' );
                    }
                }
                
                // Mise à jour statut workflow
                $order->update_meta_data( '_parrainage_workflow_status', $status );
                $order->update_meta_data( '_tb_parrainage_processed', current_time( 'mysql' ) );
                $order->save();
                
                // Nettoyage métadonnées temporaires
                $this->cleanup_temporary_metadata( $order );
                
                // Notification optionnelle
                do_action( 'tb_parrainage_discount_processed', $order_id, $discount_results, $status );
            } else {
                throw new \RuntimeException( 'Échec des calculs de remise' );
            }
            
        } catch ( \Exception $e ) {
            $this->handle_processing_error( $order_id, $filleul_subscription_id, $attempt_number, $e );
        }
    }

    /**
     * v2.7.1 : Fin de la remise programmée
     */
    public function end_parrain_discount( $parrain_subscription_id, $filleul_subscription_id ) {
        if ( ! $this->subscription_discount_manager ) {
            $this->logger->error(
                'Fin remise: SubscriptionDiscountManager non disponible',
                array(
                    'parrain_subscription' => $parrain_subscription_id,
                    'filleul_subscription' => $filleul_subscription_id
                ),
                'discount-processor'
            );
            return;
        }
        $this->subscription_discount_manager->remove_discount( $parrain_subscription_id, $filleul_subscription_id );
    }
    
    /**
     * Retry automatique d'un traitement qui a échoué
     * 
     * @param int $order_id ID commande
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param int $attempt_number Numéro de tentative
     * @param string $previous_error Erreur précédente
     * @return void
     */
    public function retry_failed_discount( $order_id, $filleul_subscription_id, $attempt_number, $previous_error ) {
        $this->logger->info(
            'RETRY traitement remise parrainage',
            array(
                'order_id' => $order_id,
                'subscription_id' => $filleul_subscription_id,
                'attempt' => $attempt_number,
                'previous_error' => $previous_error
            ),
            'discount-processor'
        );
        
        // Relancer le traitement principal
        $this->process_parrain_discount_async( $order_id, $filleul_subscription_id, $attempt_number );
    }
    
    /**
     * Validation des conditions de traitement
     * 
     * @param int $order_id ID commande
     * @param int $filleul_subscription_id ID abonnement filleul
     * @return bool Conditions validées
     */
    private function validate_processing_conditions( $order_id, $filleul_subscription_id ) {
        // Vérification existence et statut abonnement filleul
        $filleul_subscription = wcs_get_subscription( $filleul_subscription_id );
        if ( ! $filleul_subscription || $filleul_subscription->get_status() !== 'active' ) {
            $this->logger->warning(
                'Abonnement filleul non actif - abandon traitement',
                array(
                    'subscription_id' => $filleul_subscription_id,
                    'status' => $filleul_subscription ? $filleul_subscription->get_status() : 'not_found'
                ),
                'discount-processor'
            );
            return false;
        }
        
        // Vérification anti-doublon
        $order = wc_get_order( $order_id );
        if ( $order ) {
            if ( $order->get_meta( '_tb_parrainage_processed' ) || $order->get_meta( '_tb_parrainage_calculated' ) || $order->get_meta( '_tb_parrainage_applied' ) ) {
                $this->logger->info(
                    'Remise déjà traitée - abandon traitement',
                    array( 'order_id' => $order_id ),
                    'discount-processor'
                );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Stockage des résultats de calcul pour simulation
     * 
     * @param WC_Order $order Instance de commande
     * @param array $discount_results Résultats de calculs
     * @return void
     */
    private function store_calculated_discount_results( $order, $discount_results ) {
        $order->update_meta_data( '_parrainage_calculated_discounts', $discount_results );
        $order->update_meta_data( '_parrainage_calculation_date', current_time( 'mysql' ) );
        
        // Stockage pour affichage dans les interfaces
        foreach ( $discount_results as $result ) {
            $order->add_order_note(
                sprintf(
                    'Remise parrain calculée : %s %s (simulation v2.6.0)',
                    $result['discount_amount'],
                    $result['currency']
                )
            );
        }
    }
    
    /**
     * Gestion des erreurs de traitement avec retry automatique
     * 
     * @param int $order_id ID commande
     * @param int $filleul_subscription_id ID abonnement filleul  
     * @param int $attempt_number Numéro tentative
     * @param Exception $exception Exception capturée
     * @return void
     */
    private function handle_processing_error( $order_id, $filleul_subscription_id, $attempt_number, $exception ) {
        $this->logger->error(
            'Erreur lors du traitement asynchrone de remise',
            array(
                'order_id' => $order_id,
                'subscription_id' => $filleul_subscription_id,
                'attempt' => $attempt_number,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ),
            'discount-processor'
        );
        
        if ( $attempt_number < WC_TB_PARRAINAGE_MAX_RETRY ) {
            // Programmer un retry
            $retry_time = time() + WC_TB_PARRAINAGE_RETRY_DELAY;
            
            wp_schedule_single_event(
                $retry_time,
                'tb_parrainage_retry_discount',
                array(
                    $order_id,
                    $filleul_subscription_id,
                    $attempt_number + 1,
                    $exception->getMessage()
                )
            );
            
            $this->logger->info(
                'Retry programmé pour remise parrainage',
                array(
                    'order_id' => $order_id,
                    'retry_attempt' => $attempt_number + 1,
                    'retry_time' => date( 'Y-m-d H:i:s', $retry_time )
                ),
                'discount-processor'
            );
        } else {
            // Échec définitif
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->update_meta_data( '_parrainage_workflow_status', 'error' );
                $order->update_meta_data( '_parrainage_final_error', $exception->getMessage() );
                $order->save();
            }
            
            // Alerte administrative
            do_action( 'tb_parrainage_processing_failed', $order_id, $exception->getMessage() );
        }
    }
    
    /**
     * Gestion des échecs de programmation CRON
     * 
     * @param int $order_id ID commande
     * @param int $subscription_id ID abonnement
     * @return void
     */
    private function handle_cron_failure( $order_id, $subscription_id ) {
        $this->logger->error(
            'ÉCHEC PROGRAMMATION CRON - CRON WordPress potentiellement désactivé',
            array(
                'order_id' => $order_id,
                'subscription_id' => $subscription_id,
                'recommendation' => 'Vérifier la configuration CRON du serveur'
            ),
            'discount-processor'
        );
        
        // Marquer l'ordre pour surveillance manuelle
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->update_meta_data( '_parrainage_workflow_status', 'cron_failed' );
            $order->update_meta_data( '_parrainage_cron_failure_date', current_time( 'mysql' ) );
            $order->save();
        }
        
        // Hook pour notification administrative
        do_action( 'tb_parrainage_cron_failure', $order_id, $subscription_id );
    }
    
    /**
     * Validation du format du code parrain
     * 
     * @param string $code_parrain Code à valider
     * @return bool Code valide
     */
    private function validate_parrain_code_format( $code_parrain ) {
        return is_numeric( $code_parrain ) && strlen( trim( $code_parrain ) ) === 4;
    }
    
    /**
     * Récupération des IDs produits d'une commande
     * 
     * @param WC_Order $order Instance de commande
     * @return array IDs des produits
     */
    private function get_order_product_ids( $order ) {
        $product_ids = array();
        
        foreach ( $order->get_items() as $item ) {
            $product_ids[] = $item->get_product_id();
        }
        
        return $product_ids;
    }
    
    /**
     * NOUVEAU v2.6.0 : Vérification de la santé du système CRON
     * 
     * @return array Statut de santé avec recommandations
     */
    public function check_cron_health() {
        $health_status = array(
            'cron_enabled' => defined( 'DISABLE_WP_CRON' ) ? ! DISABLE_WP_CRON : true,
            'pending_events' => \wp_get_scheduled_event( WC_TB_PARRAINAGE_QUEUE_HOOK ),
            'last_run' => \get_option( 'tb_parrainage_last_cron_run', false ),
            'failed_orders' => $this->get_failed_orders_count(),
            'recommendations' => array()
        );
        
        // Analyses et recommandations
        if ( ! $health_status['cron_enabled'] ) {
            $health_status['recommendations'][] = 'CRON WordPress désactivé - Activer WP_CRON ou configurer cron serveur';
        }
        
        if ( $health_status['failed_orders'] > 0 ) {
            $health_status['recommendations'][] = sprintf( 
                '%d commandes en échec nécessitent une intervention manuelle',
                $health_status['failed_orders']
            );
        }
        
        $last_run = $health_status['last_run'];
        if ( $last_run && ( time() - $last_run ) > 3600 ) {
            $health_status['recommendations'][] = 'Aucune exécution CRON depuis plus d\'1 heure - Vérifier configuration';
        }
        
        return $health_status;
    }
    
    /**
     * NOUVEAU v2.6.0 : Comptage des commandes en échec
     * 
     * @return int Nombre de commandes avec workflow en erreur
     */
    private function get_failed_orders_count() {
        global $wpdb;
        
        $count = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_parrainage_workflow_status'
            AND meta_value IN (%s, %s)
        ", 'error', 'cron_failed' ) );
        
        return intval( $count );
    }
    
    /**
     * NOUVEAU v2.6.0 : Nettoyage des métadonnées temporaires
     * 
     * @param WC_Order $order Instance de commande
     * @return void
     */
    public function cleanup_temporary_metadata( $order ) {
        // Nettoyer les métadonnées temporaires après traitement réussi
        $order->delete_meta_data( '_pending_parrain_discount' );
        $order->delete_meta_data( '_parrainage_scheduled_time' );
        
        // Marquer la date de nettoyage pour audit
        $order->update_meta_data( '_parrainage_cleanup_date', current_time( 'mysql' ) );
        $order->save();
        
        $this->logger->debug(
            'Métadonnées temporaires nettoyées après traitement réussi',
            array( 'order_id' => $order->get_id() ),
            'discount-processor'
        );
    }
    
    /**
     * NOUVEAU v2.6.0 : Validation complète du système pour tests
     * 
     * @return array Résultat de validation avec recommandations
     */
    public function validate_system_readiness() {
        $validation_result = array(
            'is_ready' => true,
            'checks' => array(),
            'errors' => array(),
            'warnings' => array(),
            'recommendations' => array()
        );
        
        // Vérification des services WordPress
        $validation_result['checks']['wordpress'] = get_bloginfo( 'version' );
        $validation_result['checks']['woocommerce'] = class_exists( 'WooCommerce' );
        $validation_result['checks']['subscriptions'] = class_exists( 'WC_Subscriptions' );
        $validation_result['checks']['cron_enabled'] = ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
        
        // Vérification des services techniques
        $validation_result['checks']['calculator_loaded'] = ! is_null( $this->discount_calculator );
        $validation_result['checks']['validator_loaded'] = ! is_null( $this->discount_validator );
        $validation_result['checks']['notification_loaded'] = ! is_null( $this->notification_service );
        
        // Analyse des erreurs critiques
        if ( ! $validation_result['checks']['woocommerce'] ) {
            $validation_result['errors'][] = 'WooCommerce non activé';
            $validation_result['is_ready'] = false;
        }
        
        if ( ! $validation_result['checks']['subscriptions'] ) {
            $validation_result['errors'][] = 'WooCommerce Subscriptions non activé';
            $validation_result['is_ready'] = false;
        }
        
        if ( ! $validation_result['checks']['cron_enabled'] ) {
            $validation_result['warnings'][] = 'CRON WordPress désactivé - Le workflow asynchrone ne fonctionnera pas';
            $validation_result['recommendations'][] = 'Activer WP_CRON ou configurer un CRON serveur';
        }
        
        // Vérification de la santé CRON
        $cron_health = $this->check_cron_health();
        if ( isset( $cron_health['failed_orders'] ) && $cron_health['failed_orders'] > 0 ) {
            $validation_result['warnings'][] = sprintf( 
                '%d commandes en échec nécessitent une intervention', 
                $cron_health['failed_orders'] 
            );
        }
        
        // Recommandations générales
        if ( $validation_result['is_ready'] ) {
            $validation_result['recommendations'][] = 'Système prêt - Tester avec une commande de test';
            $validation_result['recommendations'][] = 'Surveiller les logs canal "discount-processor"';
        }
        
        return $validation_result;
    }
    
    /**
     * NOUVEAU v2.6.0 : Génération d'un rapport de diagnostic complet
     * 
     * @return array Rapport détaillé pour audit et debug
     */
    public function generate_diagnostic_report() {
        return array(
            'timestamp' => current_time( 'mysql' ),
            'version' => WC_TB_PARRAINAGE_VERSION,
            'system_validation' => $this->validate_system_readiness(),
            'cron_health' => $this->check_cron_health(),
            'workflow_statistics' => $this->get_workflow_statistics(),
            'configuration' => array(
                'async_delay' => WC_TB_PARRAINAGE_ASYNC_DELAY . ' seconds',
                'max_retry' => WC_TB_PARRAINAGE_MAX_RETRY,
                'retry_delay' => WC_TB_PARRAINAGE_RETRY_DELAY . ' seconds',
                'queue_hook' => WC_TB_PARRAINAGE_QUEUE_HOOK
            )
        );
    }
    
    /**
     * NOUVEAU v2.6.0 : Statistiques du workflow pour monitoring
     * 
     * @return array Métriques de performance du workflow
     */
    private function get_workflow_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Comptage par statut de workflow
        $status_counts = $wpdb->get_results( $wpdb->prepare( "
            SELECT meta_value as status, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
            GROUP BY meta_value
        ", '_parrainage_workflow_status' ), ARRAY_A );
        
        foreach ( $status_counts as $status_count ) {
            $stats['by_status'][ $status_count['status'] ] = intval( $status_count['count'] );
        }
        
        // Commandes traitées dans les dernières 24h
        $stats['processed_24h'] = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
            AND p.post_date > %s
        ", '_tb_parrainage_processed', date( 'Y-m-d H:i:s', time() - 86400 ) ) );
        
        return $stats;
    }
    
    /**
     * NOUVEAU v2.8.0 : Gestion de la suspension des remises quand filleul devient inactif
     * 
     * @param \WC_Subscription $subscription L'abonnement filleul qui devient inactif
     * @return void
     */
    public function handle_filleul_suspension( $subscription ) {
        
        $start_time = microtime( true );
        $filleul_subscription_id = $subscription->get_id();
        $new_status = $subscription->get_status();
        
        // Log détaillé de détection
        $this->logger->info(
            'TRIGGER v2.8.0 : Détection changement statut filleul vers inactivité',
            array(
                'filleul_subscription_id' => $filleul_subscription_id,
                'new_status' => $new_status,
                'trigger_time' => current_time( 'mysql' ),
                'user_id' => $subscription->get_user_id(),
                'customer_email' => $subscription->get_billing_email()
            ),
            'filleul-suspension'
        );
        
        try {
            // Rechercher le parrain associé à ce filleul
            $parrain_data = $this->find_parrain_for_filleul( $filleul_subscription_id );
            
            if ( ! $parrain_data ) {
                $this->logger->info(
                    'Aucun parrain trouvé pour ce filleul - aucune action requise',
                    array(
                        'filleul_subscription_id' => $filleul_subscription_id,
                        'search_duration' => round( ( microtime( true ) - $start_time ) * 1000, 2 ) . 'ms'
                    ),
                    'filleul-suspension'
                );
                return;
            }
            
            // Parrain trouvé - logger les détails complets
            $this->logger->info(
                'PARRAIN IDENTIFIÉ pour filleul inactif - suspension requise',
                array(
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'filleul_status' => $new_status,
                    'parrain_subscription_id' => $parrain_data['subscription_id'],
                    'parrain_user_id' => $parrain_data['user_id'],
                    'parrain_email' => $parrain_data['email'],
                    'ordre_filleul_id' => $parrain_data['ordre_filleul_id'],
                    'detection_duration' => round( ( microtime( true ) - $start_time ) * 1000, 2 ) . 'ms'
                ),
                'filleul-suspension'
            );
            
            // TODO v2.8.0 : Ici sera programmée la suspension asynchrone
            $this->logger->warning(
                'SUSPENSION PROGRAMMÉE (non implémentée) - Phase suivante du développement',
                array(
                    'parrain_subscription_id' => $parrain_data['subscription_id'],
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'reason' => 'Filleul statut : ' . $new_status,
                    'next_step' => 'Implémenter SubscriptionLifecycleManager::suspend_discount()'
                ),
                'filleul-suspension'
            );
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'ERREUR lors de la gestion suspension filleul',
                array(
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                    'execution_time' => round( ( microtime( true ) - $start_time ) * 1000, 2 ) . 'ms'
                ),
                'filleul-suspension'
            );
        }
    }
    
    /**
     * NOUVEAU v2.8.0 : Rechercher le parrain associé à un filleul
     * 
     * @param int $filleul_subscription_id ID de l'abonnement filleul
     * @return array|null Données du parrain trouvé ou null
     */
    private function find_parrain_for_filleul( $filleul_subscription_id ) {
        
        global $wpdb;
        
        $search_start = microtime( true );
        
        // Rechercher dans les métadonnées d'ordre : _billing_parrain_code = $filleul_subscription_id
        $query = $wpdb->prepare( "
            SELECT DISTINCT
                pm_parrain.post_id as ordre_filleul_id,
                pm_parrain.meta_value as parrain_subscription_id,
                pm_user.meta_value as parrain_user_id,
                pm_email.meta_value as parrain_email
            FROM {$wpdb->postmeta} pm_parrain
            LEFT JOIN {$wpdb->postmeta} pm_user 
                ON pm_parrain.post_id = pm_user.post_id 
                AND pm_user.meta_key = '_parrain_user_id'
            LEFT JOIN {$wpdb->postmeta} pm_email 
                ON pm_parrain.post_id = pm_email.post_id 
                AND pm_email.meta_key = '_parrain_email'
            WHERE pm_parrain.meta_key = '_billing_parrain_code'
                AND pm_parrain.meta_value = %s
            LIMIT 1
        ", $filleul_subscription_id );
        
        $result = $wpdb->get_row( $query, ARRAY_A );
        
        $search_duration = round( ( microtime( true ) - $search_start ) * 1000, 2 );
        
        // Log détaillé de la recherche
        $this->logger->debug(
            'Recherche parrain pour filleul - résultats',
            array(
                'filleul_subscription_id' => $filleul_subscription_id,
                'sql_query' => $query,
                'search_duration' => $search_duration . 'ms',
                'result_found' => ! empty( $result ),
                'wpdb_last_error' => $wpdb->last_error ?: 'aucune erreur'
            ),
            'filleul-suspension'
        );
        
        if ( empty( $result ) ) {
            return null;
        }
        
        // Valider que l'abonnement parrain existe toujours
        $parrain_subscription = wcs_get_subscription( $result['parrain_subscription_id'] );
        if ( ! $parrain_subscription ) {
            $this->logger->warning(
                'Parrain trouvé mais abonnement parrain inexistant',
                array(
                    'parrain_subscription_id' => $result['parrain_subscription_id'],
                    'ordre_filleul_id' => $result['ordre_filleul_id']
                ),
                'filleul-suspension'
            );
            return null;
        }
        
        // Retourner les données complètes du parrain
        return array(
            'subscription_id' => intval( $result['parrain_subscription_id'] ),
            'user_id' => intval( $result['parrain_user_id'] ),
            'email' => $result['parrain_email'],
            'ordre_filleul_id' => intval( $result['ordre_filleul_id'] ),
            'subscription_object' => $parrain_subscription,
            'current_status' => $parrain_subscription->get_status(),
            'current_total' => $parrain_subscription->get_total()
        );
    }
}