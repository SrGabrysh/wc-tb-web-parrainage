<?php
/**
 * SuspensionManager - Orchestration du workflow de suspension des remises parrain
 * 
 * Responsabilité unique : Orchestrer la suspension automatique des remises
 * quand un abonnement filleul devient inactif (cancelled, on-hold, expired)
 * 
 * @package TBWeb\WCParrainage
 * @version 2.8.0-dev
 * @since 2.8.0
 */

declare( strict_types=1 );

namespace TBWeb\WCParrainage;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SuspensionManager - Orchestration suspension remises parrain
 * 
 * Architecture modulaire :
 * - Manager = Orchestration + coordination
 * - Handler = Logique métier suspension
 * - Validator = Validation éligibilité
 */
class SuspensionManager {
    
    /**
     * @var Logger Instance du logger
     */
    private $logger;
    
    /**
     * @var SuspensionHandler Handler logique métier
     */
    private $suspension_handler;
    
    /**
     * @var SuspensionValidator Validator éligibilité
     */
    private $suspension_validator;
    
    /**
     * @var SubscriptionDiscountManager Manager remises existant
     */
    private $discount_manager;
    
    /**
     * @var array Statistiques de session
     */
    private $session_stats = array(
        'suspensions_attempted' => 0,
        'suspensions_successful' => 0,
        'suspensions_failed' => 0,
        'validation_failures' => 0
    );
    
    /**
     * Constructeur - Injection des dépendances
     * 
     * @param Logger $logger Instance du logger
     * @param SubscriptionDiscountManager $discount_manager Manager remises existant
     */
    public function __construct( Logger $logger, SubscriptionDiscountManager $discount_manager ) {
        $this->logger = $logger;
        $this->discount_manager = $discount_manager;
        
        // Initialisation des modules spécialisés
        $this->suspension_handler = new SuspensionHandler( $logger, $discount_manager );
        $this->suspension_validator = new SuspensionValidator( $logger );
        
        $this->logger->info(
            'SuspensionManager initialisé avec succès',
            array(
                'handler' => get_class( $this->suspension_handler ),
                'validator' => get_class( $this->suspension_validator ),
                'discount_manager' => get_class( $this->discount_manager )
            ),
            'suspension-manager'
        );
    }
    
    /**
     * NOUVEAU v2.10.0 : Initialisation des hooks WordPress pour suspension automatique
     * 
     * Enregistre les hooks pour détecter les changements de statut des abonnements filleuls
     * et déclencher automatiquement la suspension des remises parrain.
     * 
     * @return void
     */
    public function init(): void {
        // Hook principal : détecter tous les changements de statut vers inactivité
        add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'handle_subscription_status_change' ), 10, 1 );
        add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'handle_subscription_status_change' ), 10, 1 );
        add_action( 'woocommerce_subscription_status_expired', array( $this, 'handle_subscription_status_change' ), 10, 1 );
        add_action( 'woocommerce_subscription_status_pending-cancel', array( $this, 'handle_subscription_status_change' ), 10, 1 );
        
        $this->logger->info(
            'SuspensionManager hooks enregistrés avec succès',
            array(
                'hooks_registered' => array(
                    'woocommerce_subscription_status_cancelled',
                    'woocommerce_subscription_status_on-hold', 
                    'woocommerce_subscription_status_expired',
                    'woocommerce_subscription_status_pending-cancel'
                )
            ),
            'suspension-manager'
        );
    }
    
    /**
     * NOUVEAU v2.10.0 : Handler unifié pour tous les changements de statut vers suspension
     * 
     * Méthode appelée automatiquement par les hooks WordPress quand un abonnement
     * filleul change vers un statut inactif.
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement filleul
     * @return void
     */
    public function handle_subscription_status_change( $subscription ): void {
        if ( ! $subscription instanceof \WC_Subscription ) {
            $this->logger->warning(
                'Type d\'objet invalide reçu dans handle_subscription_status_change',
                array( 'received_type' => get_class( $subscription ) ),
                'suspension-manager'
            );
            return;
        }
        
        $filleul_subscription_id = $subscription->get_id();
        $new_status = $subscription->get_status();
        
        $this->logger->info(
            'Changement statut abonnement détecté - analyse parrainage',
            array(
                'filleul_subscription_id' => $filleul_subscription_id,
                'new_status' => $new_status,
                'trigger_hook' => current_filter()
            ),
            'suspension-manager'
        );
        
        // Rechercher si cet abonnement filleul a un parrain
        $parrain_info = $this->find_parrain_for_filleul( $filleul_subscription_id );
        
        if ( ! $parrain_info ) {
            $this->logger->debug(
                'Aucun parrain trouvé pour cet abonnement - aucune action nécessaire',
                array( 'filleul_subscription_id' => $filleul_subscription_id ),
                'suspension-manager'
            );
            return;
        }
        
        // Déclencher la suspension de la remise parrain
        $result = $this->orchestrate_suspension(
            $parrain_info['parrain_subscription_id'],
            $filleul_subscription_id,
            $new_status
        );
        
        $this->logger->info(
            'Suspension automatique déclenchée par changement statut',
            array(
                'filleul_subscription_id' => $filleul_subscription_id,
                'parrain_subscription_id' => $parrain_info['parrain_subscription_id'],
                'suspension_result' => $result['success'] ? 'SUCCESS' : 'FAILED',
                'execution_time_ms' => $result['execution_time_ms'] ?? 0
            ),
            'suspension-manager'
        );
    }
    
    /**
     * NOUVEAU v2.10.0 : Rechercher le parrain associé à un abonnement filleul
     * 
     * CORRIGÉ v2.10.0 : Utilise la méthode correcte basée sur les métadonnées réelles
     * 
     * @param int $filleul_subscription_id ID de l'abonnement filleul
     * @return array|false Informations du parrain ou false si non trouvé
     */
    private function find_parrain_for_filleul( int $filleul_subscription_id ) {
        global $wpdb;
        
        $this->logger->debug(
            'Recherche parrain pour filleul',
            array( 'filleul_subscription_id' => $filleul_subscription_id ),
            'suspension-manager'
        );
        
        // MÉTHODE 1: Chercher directement dans les métadonnées de l'abonnement filleul
        $parrain_code = get_post_meta( $filleul_subscription_id, '_billing_parrain_code', true );
        
        if ( $parrain_code ) {
            $this->logger->debug(
                'Parrain trouvé via _billing_parrain_code',
                array( 
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'parrain_code' => $parrain_code
                ),
                'suspension-manager'
            );
            
            return array(
                'parrain_subscription_id' => (int) $parrain_code,
                'order_id' => null // Pas besoin de l'order_id pour cette méthode
            );
        }
        
        // MÉTHODE 2: Chercher via _pending_parrain_discount (fallback)
        $pending_parrain = get_post_meta( $filleul_subscription_id, '_pending_parrain_discount', true );
        
        if ( $pending_parrain ) {
            $this->logger->debug(
                'Parrain trouvé via _pending_parrain_discount',
                array( 
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'pending_parrain' => $pending_parrain
                ),
                'suspension-manager'
            );
            
            return array(
                'parrain_subscription_id' => (int) $pending_parrain,
                'order_id' => null
            );
        }
        
        // MÉTHODE 3: Rechercher via les métadonnées Gabriel (inverse)
        $query = $wpdb->prepare( "
            SELECT post_id as parrain_subscription_id, meta_value as filleul_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_parrain_suspension_filleul_id'
            AND meta_value = %d
            LIMIT 1
        ", $filleul_subscription_id );
        
        $result = $wpdb->get_row( $query, ARRAY_A );
        
        if ( $result ) {
            $this->logger->debug(
                'Parrain trouvé via _parrain_suspension_filleul_id',
                array( 
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'parrain_subscription_id' => $result['parrain_subscription_id']
                ),
                'suspension-manager'
            );
            
            return array(
                'parrain_subscription_id' => (int) $result['parrain_subscription_id'],
                'order_id' => null
            );
        }
        
        $this->logger->warning(
            'Aucun parrain trouvé pour filleul',
            array( 'filleul_subscription_id' => $filleul_subscription_id ),
            'suspension-manager'
        );
        
        return false;
    }
    
    /**
     * STEP 3.1 : Orchestration complète de la suspension
     * 
     * Point d'entrée principal appelé par AutomaticDiscountProcessor
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param string $filleul_new_status Nouveau statut filleul
     * @return array Résultat orchestration avec détails
     */
    public function orchestrate_suspension( int $parrain_subscription_id, int $filleul_subscription_id, string $filleul_new_status ): array {
        
        $start_time = microtime( true );
        $this->session_stats['suspensions_attempted']++;
        
        $context = array(
            'parrain_subscription_id' => $parrain_subscription_id,
            'filleul_subscription_id' => $filleul_subscription_id,
            'filleul_new_status' => $filleul_new_status,
            'orchestration_id' => wp_generate_uuid4()
        );
        
        $this->logger->info(
            'DÉBUT orchestration suspension remise parrain',
            $context,
            'suspension-manager'
        );
        
        try {
            
            // STEP 3.1.1 : Validation éligibilité suspension
            $validation_result = $this->suspension_validator->validate_suspension_eligibility(
                $parrain_subscription_id,
                $filleul_subscription_id,
                $filleul_new_status
            );
            
            if ( ! $validation_result['is_eligible'] ) {
                $this->session_stats['validation_failures']++;
                
                $result = array(
                    'success' => false,
                    'step_reached' => 'validation',
                    'reason' => 'validation_failed',
                    'validation_errors' => $validation_result['errors'],
                    'execution_time_ms' => $this->get_execution_time_ms( $start_time )
                );
                
                $this->logger->warning(
                    'Suspension non éligible - validation échouée',
                    array_merge( $context, $result ),
                    'suspension-manager'
                );
                
                return $result;
            }
            
            // STEP 3.1.2 : Délégation à SuspensionHandler pour logique métier
            $suspension_result = $this->suspension_handler->process_suspension(
                $parrain_subscription_id,
                $filleul_subscription_id,
                $filleul_new_status,
                $validation_result['parrain_data']
            );
            
            if ( $suspension_result['success'] ) {
                $this->session_stats['suspensions_successful']++;
                
                $this->logger->info(
                    'Orchestration suspension terminée avec SUCCÈS',
                    array_merge( $context, array(
                        'suspension_details' => $suspension_result,
                        'execution_time_ms' => $this->get_execution_time_ms( $start_time )
                    ) ),
                    'suspension-manager'
                );
                
            } else {
                $this->session_stats['suspensions_failed']++;
                
                $this->logger->error(
                    'Orchestration suspension ÉCHOUÉE',
                    array_merge( $context, array(
                        'suspension_errors' => $suspension_result['errors'],
                        'execution_time_ms' => $this->get_execution_time_ms( $start_time )
                    ) ),
                    'suspension-manager'
                );
            }
            
            return array_merge( $suspension_result, array(
                'step_reached' => $suspension_result['success'] ? 'completed' : 'suspension_processing',
                'execution_time_ms' => $this->get_execution_time_ms( $start_time ),
                'orchestration_id' => $context['orchestration_id']
            ) );
            
        } catch ( \Exception $e ) {
            $this->session_stats['suspensions_failed']++;
            
            $error_result = array(
                'success' => false,
                'step_reached' => 'exception',
                'reason' => 'unexpected_error',
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => $this->get_execution_time_ms( $start_time )
            );
            
            $this->logger->error(
                'EXCEPTION durant orchestration suspension',
                array_merge( $context, $error_result ),
                'suspension-manager'
            );
            
            return $error_result;
        }
    }
    
    /**
     * Obtenir les statistiques de session pour monitoring
     * 
     * @return array Statistiques détaillées
     */
    public function get_session_statistics(): array {
        return array_merge( $this->session_stats, array(
            'success_rate' => $this->calculate_success_rate(),
            'session_start' => $this->get_session_start_time()
        ) );
    }
    
    /**
     * Calculer le taux de succès des suspensions
     * 
     * @return float Taux de succès (0.0 à 1.0)
     */
    private function calculate_success_rate(): float {
        if ( $this->session_stats['suspensions_attempted'] === 0 ) {
            return 0.0;
        }
        
        return round(
            $this->session_stats['suspensions_successful'] / $this->session_stats['suspensions_attempted'],
            3
        );
    }
    
    /**
     * Calculer le temps d'exécution en millisecondes
     * 
     * @param float $start_time Temps de début microtime(true)
     * @return int Temps en millisecondes
     */
    private function get_execution_time_ms( float $start_time ): int {
        return (int) round( ( microtime( true ) - $start_time ) * 1000 );
    }
    
    /**
     * Obtenir le timestamp de début de session
     * 
     * @return string Timestamp formaté
     */
    private function get_session_start_time(): string {
        return current_time( 'mysql' );
    }
    
    /**
     * Reset des statistiques de session (pour tests)
     * 
     * @return void
     */
    public function reset_session_statistics(): void {
        $this->session_stats = array(
            'suspensions_attempted' => 0,
            'suspensions_successful' => 0,
            'suspensions_failed' => 0,
            'validation_failures' => 0
        );
        
        $this->logger->debug(
            'Statistiques de session réinitialisées',
            array(),
            'suspension-manager'
        );
    }
}
