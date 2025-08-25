<?php
/**
 * SuspensionValidator - Validation éligibilité suspension remises parrain
 * 
 * Responsabilité unique : Valider toutes les conditions d'éligibilité
 * avant de procéder à une suspension de remise parrain
 * 
 * @package TBWeb\WCParrainage
 * @version 2.8.0-dev
 * @since 2.8.0
 */

declare( strict_types=1 );

namespace TBWeb\WCParrainage;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SuspensionValidator - Validation éligibilité suspension
 * 
 * Valide :
 * - Abonnements valides et statuts corrects
 * - Relation parrain-filleul existante
 * - Remise actuellement active
 * - Conditions business de suspension
 */
class SuspensionValidator {
    
    /**
     * @var Logger Instance du logger
     */
    private $logger;
    
    /**
     * Statuts filleul déclenchant une suspension
     */
    private const SUSPENSION_TRIGGER_STATUSES = array(
        'cancelled',
        'on-hold', 
        'expired',
        'pending-cancel'
    );
    
    /**
     * Statuts parrain acceptés pour suspension
     */
    private const VALID_PARRAIN_STATUSES = array(
        'active',
        'on-hold'
    );
    
    /**
     * Statuts remise suspendables
     */
    private const SUSPENDABLE_DISCOUNT_STATUSES = array(
        'active',
        'reactivated'
    );
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        
        $this->logger->debug(
            'SuspensionValidator initialisé',
            array(
                'suspension_triggers' => self::SUSPENSION_TRIGGER_STATUSES,
                'valid_parrain_statuses' => self::VALID_PARRAIN_STATUSES,
                'suspendable_statuses' => self::SUSPENDABLE_DISCOUNT_STATUSES
            ),
            'suspension-validator'
        );
    }
    
    /**
     * STEP 3.3 : Validation complète éligibilité suspension
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul 
     * @param string $filleul_new_status Nouveau statut filleul
     * @return array Résultat validation avec détails
     */
    public function validate_suspension_eligibility( int $parrain_subscription_id, int $filleul_subscription_id, string $filleul_new_status ): array {
        
        $start_time = microtime( true );
        $validation_id = wp_generate_uuid4();
        
        $context = array(
            'parrain_subscription_id' => $parrain_subscription_id,
            'filleul_subscription_id' => $filleul_subscription_id,
            'filleul_new_status' => $filleul_new_status,
            'validation_id' => $validation_id
        );
        
        $this->logger->info(
            'DÉBUT validation éligibilité suspension',
            $context,
            'suspension-validator'
        );
        
        $errors = array();
        $warnings = array();
        
        // STEP 3.3.1 : Validation du statut filleul déclencheur
        if ( ! $this->is_suspension_trigger_status( $filleul_new_status ) ) {
            $errors[] = array(
                'code' => 'invalid_trigger_status',
                'message' => "Statut filleul '{$filleul_new_status}' ne déclenche pas de suspension",
                'expected' => self::SUSPENSION_TRIGGER_STATUSES
            );
        }
        
        // STEP 3.3.2 : Validation abonnement filleul
        $filleul_validation = $this->validate_filleul_subscription( $filleul_subscription_id, $filleul_new_status );
        if ( ! $filleul_validation['is_valid'] ) {
            $errors = array_merge( $errors, $filleul_validation['errors'] );
        } else {
            $warnings = array_merge( $warnings, $filleul_validation['warnings'] );
        }
        
        // STEP 3.3.3 : Validation abonnement parrain
        $parrain_validation = $this->validate_parrain_subscription( $parrain_subscription_id );
        if ( ! $parrain_validation['is_valid'] ) {
            $errors = array_merge( $errors, $parrain_validation['errors'] );
        } else {
            $warnings = array_merge( $warnings, $parrain_validation['warnings'] );
        }
        
        // STEP 3.3.4 : Validation relation parrain-filleul
        if ( empty( $errors ) ) {
            $relation_validation = $this->validate_parrain_filleul_relation( 
                $parrain_subscription_id, 
                $filleul_subscription_id,
                $parrain_validation['parrain_data'] 
            );
            
            if ( ! $relation_validation['is_valid'] ) {
                $errors = array_merge( $errors, $relation_validation['errors'] );
            }
        }
        
        // STEP 3.3.5 : Validation statut remise actuel
        if ( empty( $errors ) && isset( $parrain_validation['parrain_data'] ) ) {
            $discount_validation = $this->validate_current_discount_status( $parrain_validation['parrain_data']['subscription'] );
            
            if ( ! $discount_validation['is_valid'] ) {
                $errors = array_merge( $errors, $discount_validation['errors'] );
            } else {
                $warnings = array_merge( $warnings, $discount_validation['warnings'] );
            }
        }
        
        $is_eligible = empty( $errors );
        $execution_time_ms = $this->get_execution_time_ms( $start_time );
        
        $result = array(
            'is_eligible' => $is_eligible,
            'errors' => $errors,
            'warnings' => $warnings,
            'validation_id' => $validation_id,
            'execution_time_ms' => $execution_time_ms
        );
        
        // Ajouter données parrain si validation réussie
        if ( $is_eligible && isset( $parrain_validation['parrain_data'] ) ) {
            $result['parrain_data'] = $parrain_validation['parrain_data'];
        }
        
        $log_level = $is_eligible ? 'info' : 'warning';
        $this->logger->$log_level(
            $is_eligible ? 'Validation suspension éligibilité RÉUSSIE' : 'Validation suspension éligibilité ÉCHOUÉE',
            array_merge( $context, $result ),
            'suspension-validator'
        );
        
        return $result;
    }
    
    /**
     * STEP 3.3.1 : Vérifier si le statut déclenche une suspension
     * 
     * @param string $status Statut à vérifier
     * @return bool True si déclencheur
     */
    private function is_suspension_trigger_status( string $status ): bool {
        return in_array( $status, self::SUSPENSION_TRIGGER_STATUSES, true );
    }
    
    /**
     * STEP 3.3.2 : Validation abonnement filleul
     * 
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param string $expected_status Statut attendu
     * @return array Résultat validation
     */
    private function validate_filleul_subscription( int $filleul_subscription_id, string $expected_status ): array {
        
        $errors = array();
        $warnings = array();
        
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            $errors[] = array(
                'code' => 'wcs_not_available',
                'message' => 'WooCommerce Subscriptions non disponible'
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        $subscription = wcs_get_subscription( $filleul_subscription_id );
        
        if ( ! $subscription ) {
            $errors[] = array(
                'code' => 'filleul_not_found',
                'message' => "Abonnement filleul #{$filleul_subscription_id} introuvable"
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        $current_status = $subscription->get_status();
        
        if ( $current_status !== $expected_status ) {
            $warnings[] = array(
                'code' => 'status_mismatch',
                'message' => "Statut filleul actuel '{$current_status}' != attendu '{$expected_status}'"
            );
        }
        
        $this->logger->debug(
            'Validation abonnement filleul terminée',
            array(
                'subscription_id' => $filleul_subscription_id,
                'current_status' => $current_status,
                'expected_status' => $expected_status,
                'is_valid' => true
            ),
            'suspension-validator'
        );
        
        return array(
            'is_valid' => true,
            'errors' => $errors,
            'warnings' => $warnings,
            'filleul_data' => array(
                'subscription' => $subscription,
                'current_status' => $current_status
            )
        );
    }
    
    /**
     * STEP 3.3.3 : Validation abonnement parrain
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @return array Résultat validation
     */
    private function validate_parrain_subscription( int $parrain_subscription_id ): array {
        
        $errors = array();
        $warnings = array();
        
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            $errors[] = array(
                'code' => 'wcs_not_available',
                'message' => 'WooCommerce Subscriptions non disponible'
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        $subscription = wcs_get_subscription( $parrain_subscription_id );
        
        if ( ! $subscription ) {
            $errors[] = array(
                'code' => 'parrain_not_found',
                'message' => "Abonnement parrain #{$parrain_subscription_id} introuvable"
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        $current_status = $subscription->get_status();
        
        if ( ! in_array( $current_status, self::VALID_PARRAIN_STATUSES, true ) ) {
            $errors[] = array(
                'code' => 'invalid_parrain_status',
                'message' => "Statut parrain '{$current_status}' non autorisé pour suspension",
                'allowed_statuses' => self::VALID_PARRAIN_STATUSES
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        // Vérifier présence des métadonnées nécessaires
        $original_price = $subscription->get_meta( '_parrain_original_price' );
        if ( empty( $original_price ) || ! is_numeric( $original_price ) ) {
            $warnings[] = array(
                'code' => 'missing_original_price',
                'message' => 'Prix original manquant ou invalide - pourrait affecter la suspension'
            );
        }
        
        $this->logger->debug(
            'Validation abonnement parrain terminée',
            array(
                'subscription_id' => $parrain_subscription_id,
                'current_status' => $current_status,
                'original_price' => $original_price,
                'is_valid' => true
            ),
            'suspension-validator'
        );
        
        return array(
            'is_valid' => true,
            'errors' => $errors,
            'warnings' => $warnings,
            'parrain_data' => array(
                'subscription' => $subscription,
                'current_status' => $current_status,
                'original_price' => $original_price
            )
        );
    }
    
    /**
     * STEP 3.3.4 : Validation relation parrain-filleul
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param array $parrain_data Données parrain
     * @return array Résultat validation
     */
    private function validate_parrain_filleul_relation( int $parrain_subscription_id, int $filleul_subscription_id, array $parrain_data ): array {
        
        $errors = array();
        $warnings = array();
        
        // Vérifier que les IDs sont différents (pas d'auto-parrainage)
        if ( $parrain_subscription_id === $filleul_subscription_id ) {
            $errors[] = array(
                'code' => 'self_referencing',
                'message' => 'Un abonnement ne peut pas être son propre parrain'
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        // Validation business : vérifier que la relation existe dans les métadonnées
        $existing_filleul_id = $parrain_data['subscription']->get_meta( '_parrain_suspension_filleul_id' );
        
        if ( ! empty( $existing_filleul_id ) && intval( $existing_filleul_id ) !== $filleul_subscription_id ) {
            $warnings[] = array(
                'code' => 'filleul_mismatch',
                'message' => "Filleul attendu #{$existing_filleul_id} != actuel #{$filleul_subscription_id}"
            );
        }
        
        $this->logger->debug(
            'Validation relation parrain-filleul terminée',
            array(
                'parrain_subscription_id' => $parrain_subscription_id,
                'filleul_subscription_id' => $filleul_subscription_id,
                'existing_filleul_id' => $existing_filleul_id,
                'is_valid' => true
            ),
            'suspension-validator'
        );
        
        return array(
            'is_valid' => true,
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * STEP 3.3.5 : Validation statut remise actuel
     * 
     * @param \WC_Subscription $subscription Abonnement parrain
     * @return array Résultat validation
     */
    private function validate_current_discount_status( \WC_Subscription $subscription ): array {
        
        $errors = array();
        $warnings = array();
        
        $current_discount_status = $subscription->get_meta( '_parrain_discount_status' );
        
        // Si pas de statut défini, considérer comme 'active' par défaut
        if ( empty( $current_discount_status ) ) {
            $current_discount_status = 'active';
            $warnings[] = array(
                'code' => 'no_discount_status',
                'message' => 'Aucun statut remise défini - assumé comme "active"'
            );
        }
        
        if ( ! in_array( $current_discount_status, self::SUSPENDABLE_DISCOUNT_STATUSES, true ) ) {
            $errors[] = array(
                'code' => 'not_suspendable_status',
                'message' => "Statut remise '{$current_discount_status}' non suspendable",
                'suspendable_statuses' => self::SUSPENDABLE_DISCOUNT_STATUSES
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        // Vérifier si déjà suspendu
        if ( $current_discount_status === 'suspended' ) {
            $errors[] = array(
                'code' => 'already_suspended',
                'message' => 'Remise parrain déjà suspendue'
            );
            return array( 'is_valid' => false, 'errors' => $errors, 'warnings' => $warnings );
        }
        
        $this->logger->debug(
            'Validation statut remise actuel terminée',
            array(
                'subscription_id' => $subscription->get_id(),
                'current_discount_status' => $current_discount_status,
                'is_suspendable' => true
            ),
            'suspension-validator'
        );
        
        return array(
            'is_valid' => true,
            'errors' => $errors,
            'warnings' => $warnings,
            'current_status' => $current_discount_status
        );
    }
    
    /**
     * Calculer temps d'exécution en millisecondes
     * 
     * @param float $start_time Temps début microtime(true)
     * @return int Temps en ms
     */
    private function get_execution_time_ms( float $start_time ): int {
        return (int) round( ( microtime( true ) - $start_time ) * 1000 );
    }
}
