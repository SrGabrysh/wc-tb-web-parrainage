<?php
/**
 * Validateur de Réactivation des Remises Parrain
 * 
 * RESPONSABILITÉ UNIQUE : Validation complète de l'éligibilité à la réactivation
 * automatique des remises parrain quand le filleul retourne à un statut actif.
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
 * Classe ReactivationValidator
 * 
 * Valide tous les critères d'éligibilité pour la réactivation :
 * - Validité des abonnements parrain et filleul
 * - Existence d'une remise précédemment suspendue
 * - Statut approprié pour la réactivation
 * - Cohérence des données de lien parrain-filleul
 */
class ReactivationValidator {

    /**
     * @var Logger Instance du logger pour traçabilité
     */
    private $logger;

    /**
     * @var SubscriptionDiscountManager Service de gestion des remises
     */
    private $subscription_discount_manager;

    /**
     * Canal de logs spécialisé pour validation réactivation
     */
    const LOG_CHANNEL = 'reactivation-validator';

    /**
     * Statuts filleul éligibles pour la réactivation
     */
    const ELIGIBLE_REACTIVATION_STATUSES = array( 'active' );

    /**
     * Statuts de remise parrain éligibles pour la réactivation
     */
    const ELIGIBLE_DISCOUNT_STATUSES = array( 'suspended', 'paused' );

    /**
     * Constructeur avec injection de dépendances
     * 
     * @param Logger $logger Instance du logger
     * @param SubscriptionDiscountManager $subscription_discount_manager Service remises
     */
    public function __construct( $logger, $subscription_discount_manager ) {
        $this->logger = $logger;
        $this->subscription_discount_manager = $subscription_discount_manager;

        $this->logger->debug(
            'ReactivationValidator initialisé',
            array(
                'version' => '2.8.2',
                'eligible_filleul_statuses' => self::ELIGIBLE_REACTIVATION_STATUSES,
                'eligible_discount_statuses' => self::ELIGIBLE_DISCOUNT_STATUSES,
                'workflow_step' => 'reactivation_validator_initialized'
            ),
            self::LOG_CHANNEL
        );
    }

    /**
     * Validation complète de l'éligibilité à la réactivation
     * 
     * CRITÈRES DE VALIDATION :
     * 1. Statut filleul éligible pour réactivation (active)
     * 2. Abonnement parrain valide et actif
     * 3. Remise précédemment suspendue existante
     * 4. Cohérence lien parrain-filleul
     * 5. Données techniques nécessaires présentes
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param string $new_status Nouveau statut filleul
     * @return array Résultat validation avec détails
     */
    public function validate_reactivation_eligibility( $parrain_subscription_id, $filleul_subscription_id, $new_status ) {
        $this->logger->info(
            'DÉMARRAGE validation éligibilité réactivation',
            array(
                'parrain_subscription_id' => $parrain_subscription_id,
                'filleul_subscription_id' => $filleul_subscription_id,
                'new_status' => $new_status,
                'workflow_step' => 'reactivation_validation_started'
            ),
            self::LOG_CHANNEL
        );

        try {
            // VALIDATION 1 : Statut filleul éligible
            if ( ! in_array( $new_status, self::ELIGIBLE_REACTIVATION_STATUSES, true ) ) {
                return $this->create_validation_failure(
                    'statut_filleul_non_eligible',
                    "Statut filleul '{$new_status}' non éligible pour réactivation",
                    array(
                        'new_status' => $new_status,
                        'eligible_statuses' => self::ELIGIBLE_REACTIVATION_STATUSES,
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id
                    )
                );
            }

            // VALIDATION 2 : Abonnement parrain valide
            $parrain_subscription = $this->get_valid_subscription( $parrain_subscription_id, 'parrain' );
            if ( ! $parrain_subscription ) {
                return $this->create_validation_failure(
                    'abonnement_parrain_invalide',
                    'Abonnement parrain non trouvé ou invalide',
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id
                    )
                );
            }

            // VALIDATION 3 : Abonnement filleul valide
            $filleul_subscription = $this->get_valid_subscription( $filleul_subscription_id, 'filleul' );
            if ( ! $filleul_subscription ) {
                return $this->create_validation_failure(
                    'abonnement_filleul_invalide',
                    'Abonnement filleul non trouvé ou invalide',
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id
                    )
                );
            }

            // VALIDATION 4 : Vérifier si une remise suspendue existe
            $suspended_discount_data = $this->get_suspended_discount_data( $parrain_subscription );
            if ( ! $suspended_discount_data ) {
                return $this->create_validation_failure(
                    'aucune_remise_suspendue',
                    'Aucune remise suspendue trouvée pour ce parrain',
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id,
                        'parrain_status' => $parrain_subscription->get_status()
                    )
                );
            }

            // VALIDATION 5 : Vérifier cohérence lien parrain-filleul
            $link_validation = $this->validate_parrain_filleul_link( 
                $parrain_subscription, 
                $filleul_subscription,
                $suspended_discount_data
            );
            if ( ! $link_validation['is_valid'] ) {
                return $this->create_validation_failure(
                    'lien_parrain_filleul_invalide',
                    $link_validation['reason'],
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'filleul_subscription_id' => $filleul_subscription_id,
                        'validation_details' => $link_validation['details']
                    )
                );
            }

            // VALIDATION RÉUSSIE
            $validation_details = array(
                'parrain_subscription' => array(
                    'id' => $parrain_subscription_id,
                    'status' => $parrain_subscription->get_status(),
                    'user_id' => $parrain_subscription->get_user_id()
                ),
                'filleul_subscription' => array(
                    'id' => $filleul_subscription_id,
                    'status' => $filleul_subscription->get_status(),
                    'user_id' => $filleul_subscription->get_user_id()
                ),
                'suspended_discount' => $suspended_discount_data,
                'eligible_for_reactivation' => true
            );

            $this->logger->info(
                'VALIDATION RÉACTIVATION RÉUSSIE - Éligible pour réactivation',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'new_status' => $new_status,
                    'suspended_discount_amount' => $suspended_discount_data['original_discount_amount'],
                    'workflow_step' => 'reactivation_validation_success'
                ),
                self::LOG_CHANNEL
            );

            return array(
                'is_eligible' => true,
                'reason' => 'eligible_for_reactivation',
                'details' => $validation_details
            );

        } catch ( \Exception $e ) {
            $this->logger->error(
                'ERREUR lors de la validation réactivation',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'workflow_step' => 'reactivation_validation_error'
                ),
                self::LOG_CHANNEL
            );

            return $this->create_validation_failure(
                'erreur_technique_validation',
                'Erreur technique lors de la validation : ' . $e->getMessage(),
                array(
                    'exception' => $e->getMessage(),
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id
                )
            );
        }
    }

    /**
     * Récupération d'un abonnement valide avec vérifications
     * 
     * @param int $subscription_id ID de l'abonnement
     * @param string $type Type pour logs ('parrain' ou 'filleul')
     * @return \WC_Subscription|false Abonnement valide ou false
     */
    private function get_valid_subscription( $subscription_id, $type ) {
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            $this->logger->warning(
                'WooCommerce Subscriptions non disponible',
                array(
                    'subscription_id' => $subscription_id,
                    'type' => $type,
                    'workflow_step' => 'wcs_not_available'
                ),
                self::LOG_CHANNEL
            );
            return false;
        }

        $subscription = wcs_get_subscription( $subscription_id );
        
        if ( ! $subscription || is_wp_error( $subscription ) ) {
            $this->logger->warning(
                "Abonnement {$type} non trouvé",
                array(
                    'subscription_id' => $subscription_id,
                    'type' => $type,
                    'workflow_step' => 'subscription_not_found'
                ),
                self::LOG_CHANNEL
            );
            return false;
        }

        $this->logger->debug(
            "Abonnement {$type} trouvé et valide",
            array(
                'subscription_id' => $subscription_id,
                'type' => $type,
                'status' => $subscription->get_status(),
                'user_id' => $subscription->get_user_id(),
                'workflow_step' => 'subscription_validated'
            ),
            self::LOG_CHANNEL
        );

        return $subscription;
    }

    /**
     * Récupération des données de remise suspendue
     * 
     * @param \WC_Subscription $parrain_subscription Abonnement parrain
     * @return array|false Données de remise suspendue ou false
     */
    private function get_suspended_discount_data( $parrain_subscription ) {
        // Vérifier métadonnées de suspension
        $suspended_discount = $parrain_subscription->get_meta( '_tb_parrainage_suspended_discount' );
        $suspension_date = $parrain_subscription->get_meta( '_tb_parrainage_suspension_date' );
        $original_price = $parrain_subscription->get_meta( '_tb_parrainage_original_price_before_suspension' );

        if ( empty( $suspended_discount ) || empty( $suspension_date ) || empty( $original_price ) ) {
            $this->logger->warning(
                'Métadonnées de suspension incomplètes ou absentes',
                array(
                    'parrain_subscription_id' => $parrain_subscription->get_id(),
                    'has_suspended_discount' => ! empty( $suspended_discount ),
                    'has_suspension_date' => ! empty( $suspension_date ),
                    'has_original_price' => ! empty( $original_price ),
                    'workflow_step' => 'incomplete_suspension_metadata'
                ),
                self::LOG_CHANNEL
            );
            return false;
        }

        $this->logger->debug(
            'Données de suspension trouvées',
            array(
                'parrain_subscription_id' => $parrain_subscription->get_id(),
                'suspended_discount_amount' => $suspended_discount,
                'suspension_date' => $suspension_date,
                'original_price_before_suspension' => $original_price,
                'workflow_step' => 'suspension_data_found'
            ),
            self::LOG_CHANNEL
        );

        return array(
            'original_discount_amount' => floatval( $suspended_discount ),
            'suspension_date' => $suspension_date,
            'original_price_before_suspension' => floatval( $original_price ),
            'current_price' => floatval( $parrain_subscription->get_total() )
        );
    }

    /**
     * Validation du lien parrain-filleul
     * 
     * @param \WC_Subscription $parrain_subscription Abonnement parrain
     * @param \WC_Subscription $filleul_subscription Abonnement filleul
     * @param array $suspended_discount_data Données de suspension
     * @return array Résultat de validation du lien
     */
    private function validate_parrain_filleul_link( $parrain_subscription, $filleul_subscription, $suspended_discount_data ) {
        // Vérifier que le parrain est toujours actif
        if ( ! in_array( $parrain_subscription->get_status(), array( 'active' ), true ) ) {
            return array(
                'is_valid' => false,
                'reason' => 'Abonnement parrain non actif - réactivation impossible',
                'details' => array(
                    'parrain_status' => $parrain_subscription->get_status(),
                    'required_status' => 'active'
                )
            );
        }

        // Vérifier données de cohérence (prix actuel vs prix avant suspension)
        $current_total = floatval( $parrain_subscription->get_total() );
        $expected_total_without_discount = $suspended_discount_data['original_price_before_suspension'];

        // Tolérance de 0.01€ pour les arrondis
        if ( abs( $current_total - $expected_total_without_discount ) > 0.01 ) {
            $this->logger->warning(
                'Incohérence prix parrain - possible modification manuelle',
                array(
                    'parrain_subscription_id' => $parrain_subscription->get_id(),
                    'current_total' => $current_total,
                    'expected_total_without_discount' => $expected_total_without_discount,
                    'difference' => abs( $current_total - $expected_total_without_discount ),
                    'workflow_step' => 'price_inconsistency_detected'
                ),
                self::LOG_CHANNEL
            );
            // WARNING uniquement, pas de blocage - permettre la réactivation même avec modification manuelle
        }

        $this->logger->debug(
            'Lien parrain-filleul validé avec succès',
            array(
                'parrain_subscription_id' => $parrain_subscription->get_id(),
                'filleul_subscription_id' => $filleul_subscription->get_id(),
                'parrain_status' => $parrain_subscription->get_status(),
                'filleul_new_status' => $filleul_subscription->get_status(),
                'workflow_step' => 'parrain_filleul_link_validated'
            ),
            self::LOG_CHANNEL
        );

        return array(
            'is_valid' => true,
            'reason' => 'Lien parrain-filleul valide pour réactivation',
            'details' => array(
                'parrain_status' => $parrain_subscription->get_status(),
                'filleul_status' => $filleul_subscription->get_status(),
                'price_consistency_ok' => abs( $current_total - $expected_total_without_discount ) <= 0.01
            )
        );
    }

    /**
     * Création d'un résultat d'échec de validation standardisé
     * 
     * @param string $reason Code de raison
     * @param string $message Message explicatif
     * @param array $details Détails techniques
     * @return array Résultat d'échec standardisé
     */
    private function create_validation_failure( $reason, $message, $details = array() ) {
        $this->logger->warning(
            'VALIDATION RÉACTIVATION ÉCHOUÉE : ' . $message,
            array_merge( $details, array(
                'reason' => $reason,
                'workflow_step' => 'reactivation_validation_failed'
            ) ),
            self::LOG_CHANNEL
        );

        return array(
            'is_eligible' => false,
            'reason' => $message,
            'details' => array_merge( $details, array(
                'validation_failed_reason' => $reason,
                'timestamp' => current_time( 'Y-m-d H:i:s' )
            ) )
        );
    }
}
