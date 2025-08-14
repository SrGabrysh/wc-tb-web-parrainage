<?php
/**
 * Gestionnaire de Réactivation des Remises Parrain
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration complète du workflow de réactivation automatique
 * des remises parrain quand le filleul retourne à un statut actif.
 * 
 * @package TBWeb\WCParrainage
 * @version 2.8.2
 * @since 2.8.2
 * @author TB-Web
 */

namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe ReactivationManager
 * 
 * Orchestre le processus complet de réactivation des remises parrain :
 * - Coordination entre validator et handler
 * - Gestion du workflow complet de réactivation
 * - Logging spécialisé pour debugging
 * - Intégration avec SubscriptionDiscountManager existant
 */
class ReactivationManager {

    /**
     * @var Logger Instance du logger pour traçabilité
     */
    private $logger;

    /**
     * @var SubscriptionDiscountManager Service de gestion des remises
     */
    private $subscription_discount_manager;

    /**
     * @var ReactivationHandler|null Handler lazy-loaded
     */
    private $reactivation_handler = null;

    /**
     * @var ReactivationValidator|null Validator lazy-loaded
     */
    private $reactivation_validator = null;

    /**
     * Canal de logs spécialisé pour réactivation
     */
    const LOG_CHANNEL = 'reactivation-manager';

    /**
     * Constructeur avec injection de dépendances
     * 
     * @param Logger $logger Instance du logger
     * @param SubscriptionDiscountManager $subscription_discount_manager Service remises
     */
    public function __construct( $logger, $subscription_discount_manager ) {
        $this->logger = $logger;
        $this->subscription_discount_manager = $subscription_discount_manager;

        $this->logger->info(
            'ReactivationManager initialisé avec succès',
            array(
                'version' => '2.8.2',
                'timestamp' => current_time( 'Y-m-d H:i:s' ),
                'workflow_step' => 'reactivation_manager_initialized'
            ),
            self::LOG_CHANNEL
        );
    }

    /**
     * Orchestration complète du workflow de réactivation
     * 
     * WORKFLOW RÉACTIVATION :
     * 1. Validation éligibilité réactivation
     * 2. Réactivation effective de la remise 
     * 3. Mise à jour métadonnées et statuts
     * 4. Logging complet pour traçabilité
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul réactivé
     * @param string $new_status Nouveau statut filleul (ex: 'active')
     * @return array Résultat détaillé du processus
     */
    public function orchestrate_reactivation( $parrain_subscription_id, $filleul_subscription_id, $new_status ) {
        $start_time = microtime( true );

        $this->logger->info(
            'DÉMARRAGE workflow réactivation v2.8.2',
            array(
                'parrain_subscription_id' => $parrain_subscription_id,
                'filleul_subscription_id' => $filleul_subscription_id,
                'new_status' => $new_status,
                'workflow_step' => 'reactivation_workflow_started',
                'timestamp' => current_time( 'Y-m-d H:i:s' )
            ),
            self::LOG_CHANNEL
        );

        try {
            // ÉTAPE 1 : Validation éligibilité réactivation
            $validator = $this->get_reactivation_validator();
            $validation_result = $validator->validate_reactivation_eligibility(
                $parrain_subscription_id,
                $filleul_subscription_id,
                $new_status
            );

            if ( ! $validation_result['is_eligible'] ) {
                $this->logger->warning(
                    'Réactivation NON ÉLIGIBLE - Arrêt du workflow',
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id,
                        'reason' => $validation_result['reason'],
                        'details' => $validation_result['details'],
                        'workflow_step' => 'reactivation_not_eligible'
                    ),
                    self::LOG_CHANNEL
                );

                return array(
                    'success' => false,
                    'reason' => 'not_eligible',
                    'message' => $validation_result['reason'],
                    'details' => $validation_result['details'],
                    'execution_time_ms' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
                );
            }

            $this->logger->info(
                'Validation réactivation RÉUSSIE - Procédure à la réactivation',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'validation_details' => $validation_result['details'],
                    'workflow_step' => 'reactivation_eligible_proceeding'
                ),
                self::LOG_CHANNEL
            );

            // ÉTAPE 2 : Réactivation effective de la remise
            $handler = $this->get_reactivation_handler();
            $reactivation_result = $handler->reactivate_parrain_discount(
                $parrain_subscription_id,
                $filleul_subscription_id,
                $validation_result['details']
            );

            if ( ! $reactivation_result['success'] ) {
                $this->logger->error(
                    'ÉCHEC réactivation de la remise parrain',
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id,
                        'error' => $reactivation_result['error'],
                        'details' => $reactivation_result['details'],
                        'workflow_step' => 'reactivation_failed'
                    ),
                    self::LOG_CHANNEL
                );

                throw new \RuntimeException( 
                    'Échec réactivation remise : ' . $reactivation_result['error']
                );
            }

            // ÉTAPE 3 : Workflow terminé avec succès
            $execution_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

            $this->logger->info(
                'WORKFLOW RÉACTIVATION TERMINÉ AVEC SUCCÈS v2.8.2',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'original_price_restored' => $reactivation_result['original_price_restored'],
                    'discount_reactivated' => $reactivation_result['discount_reactivated'],
                    'execution_time_ms' => $execution_time,
                    'workflow_step' => 'reactivation_workflow_completed',
                    'status' => 'success'
                ),
                self::LOG_CHANNEL
            );

            return array(
                'success' => true,
                'message' => 'Remise parrain réactivée avec succès',
                'details' => array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'original_price_restored' => $reactivation_result['original_price_restored'],
                    'discount_reactivated' => $reactivation_result['discount_reactivated'],
                    'execution_time_ms' => $execution_time
                )
            );

        } catch ( \Exception $e ) {
            $execution_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

            $this->logger->error(
                'ERREUR CRITIQUE workflow réactivation v2.8.2',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'execution_time_ms' => $execution_time,
                    'workflow_step' => 'reactivation_workflow_error'
                ),
                self::LOG_CHANNEL
            );

            return array(
                'success' => false,
                'reason' => 'technical_error',
                'message' => 'Erreur technique lors de la réactivation : ' . $e->getMessage(),
                'execution_time_ms' => $execution_time
            );
        }
    }

    /**
     * Récupération lazy du ReactivationValidator
     * 
     * @return ReactivationValidator Instance du validator
     * @throws \RuntimeException Si la classe n'est pas chargée
     */
    private function get_reactivation_validator() {
        if ( ! isset( $this->reactivation_validator ) ) {
            // SÉCURITÉ v2.8.2 : Vérifier que les classes de réactivation existent
            if ( ! class_exists( 'TBWeb\WCParrainage\ReactivationValidator' ) ) {
                $this->logger->error(
                    'ERREUR CRITIQUE : Classes de réactivation non trouvées',
                    array(
                        'missing_class' => 'ReactivationValidator',
                        'workflow_step' => 'reactivation_initialization_failed',
                        'solution' => 'Les classes doivent être chargées dans Plugin.php'
                    ),
                    self::LOG_CHANNEL
                );
                throw new \RuntimeException( 'Classes de réactivation non chargées - contactez le développeur' );
            }

            $this->reactivation_validator = new ReactivationValidator( 
                $this->logger, 
                $this->subscription_discount_manager 
            );
        }

        return $this->reactivation_validator;
    }

    /**
     * Récupération lazy du ReactivationHandler
     * 
     * @return ReactivationHandler Instance du handler
     * @throws \RuntimeException Si la classe n'est pas chargée
     */
    private function get_reactivation_handler() {
        if ( ! isset( $this->reactivation_handler ) ) {
            // SÉCURITÉ v2.8.2 : Vérifier que les classes de réactivation existent
            if ( ! class_exists( 'TBWeb\WCParrainage\ReactivationHandler' ) ) {
                $this->logger->error(
                    'ERREUR CRITIQUE : Classes de réactivation non trouvées',
                    array(
                        'missing_class' => 'ReactivationHandler',
                        'workflow_step' => 'reactivation_initialization_failed',
                        'solution' => 'Les classes doivent être chargées dans Plugin.php'
                    ),
                    self::LOG_CHANNEL
                );
                throw new \RuntimeException( 'Classes de réactivation non chargées - contactez le développeur' );
            }

            $this->reactivation_handler = new ReactivationHandler( 
                $this->logger, 
                $this->subscription_discount_manager 
            );
        }

        return $this->reactivation_handler;
    }
}
