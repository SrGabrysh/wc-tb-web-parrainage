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
     * Statuts de workflow supportés
     * @var array
     */
    private $workflow_statuses = array(
        'pending',
        'calculated', 
        'simulated',
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
     * Initialise le workflow asynchrone avec hooks WordPress
     * 
     * @return void
     */
    public function init() {
        // PHASE 1 : Marquage synchrone rapide au checkout
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_parrainage_order' ), 10, 1 );
        
        // PHASE 2 : Programmation asynchrone lors activation abonnement filleul
        add_action( 'woocommerce_subscription_status_active', array( $this, 'schedule_parrain_discount' ), 10, 1 );
        
        // PHASE 3 : Traitement différé via hook CRON personnalisé
        add_action( WC_TB_PARRAINAGE_QUEUE_HOOK, array( $this, 'process_parrain_discount_async' ), 10, 3 );
        
        // Gestion des retry automatiques
        add_action( 'tb_parrainage_retry_discount', array( $this, 'retry_failed_discount' ), 10, 4 );
        
        $this->logger->info(
            'Workflow asynchrone de remises parrain initialisé',
            array(
                'hooks_registered' => array(
                    'woocommerce_checkout_order_processed',
                    'woocommerce_subscription_status_active', 
                    WC_TB_PARRAINAGE_QUEUE_HOOK,
                    'tb_parrainage_retry_discount'
                )
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
            
        } catch ( Exception $e ) {
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
                // Programmation avec délai de sécurité
                $schedule_time = time() + WC_TB_PARRAINAGE_ASYNC_DELAY;
                
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
                    // Fallback pour problème CRON
                    $this->handle_cron_failure( $parent_order->get_id(), $subscription->get_id() );
                }
            }
            
        } catch ( Exception $e ) {
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
            
            foreach ( $product_ids as $product_id ) {
                $validation = $this->discount_validator->validate_discount_eligibility( 
                    $parrain_subscription_id, 
                    $order_id, 
                    $product_id 
                );
                
                if ( $validation['is_eligible'] ) {
                    $eligible_products[] = $product_id;
                }
            }
            
            if ( empty( $eligible_products ) ) {
                throw new Exception( 'Aucun produit éligible pour remise parrain' );
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
                // Stockage des résultats calculés (simulation uniquement en v2.6.0)
                $this->store_calculated_discount_results( $order, $discount_results );
                
                // Mise à jour du statut workflow
                $order->update_meta_data( '_parrainage_workflow_status', 'calculated' );
                $order->update_meta_data( '_tb_parrainage_calculated', current_time( 'mysql' ) );
                $order->delete_meta_data( '_pending_parrain_discount' );
                $order->save();
                
                $this->logger->info(
                    'Remise parrainage calculée avec succès (simulation v2.6.0)',
                    array(
                        'order_id' => $order_id,
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'discount_results' => $discount_results,
                        'attempt' => $attempt_number
                    ),
                    'discount-processor'
                );
                
                // Notification optionnelle
                do_action( 'tb_parrainage_discount_calculated', $order_id, $discount_results );
            } else {
                throw new Exception( 'Échec des calculs de remise' );
            }
            
        } catch ( Exception $e ) {
            $this->handle_processing_error( $order_id, $filleul_subscription_id, $attempt_number, $e );
        }
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
        if ( $order && $order->get_meta( '_tb_parrainage_calculated' ) ) {
            $this->logger->info(
                'Remise déjà calculée - abandon traitement',
                array( 'order_id' => $order_id ),
                'discount-processor'
            );
            return false;
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
}