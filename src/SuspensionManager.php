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
