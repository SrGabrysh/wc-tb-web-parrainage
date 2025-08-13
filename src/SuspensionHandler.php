<?php
/**
 * SuspensionHandler - Logique métier de suspension des remises parrain
 * 
 * Responsabilité unique : Traiter la suspension effective des remises
 * (sauvegarde état actuel + modification prix abonnement + métadonnées)
 * 
 * @package TBWeb\WCParrainage
 * @version 2.8.0-dev
 * @since 2.8.0
 */

declare( strict_types=1 );

namespace TBWeb\WCParrainage;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SuspensionHandler - Traitement logique métier suspension
 * 
 * Gère :
 * - Sauvegarde de l'état actuel de la remise
 * - Modification du prix de l'abonnement parrain 
 * - Métadonnées de suspension
 * - Intégration avec SubscriptionDiscountManager existant
 */
class SuspensionHandler {
    
    /**
     * @var Logger Instance du logger
     */
    private $logger;
    
    /**
     * @var SubscriptionDiscountManager Manager remises existant
     */
    private $discount_manager;
    
    /**
     * Préfixes métadonnées pour éviter les conflits
     */
    private const META_PREFIX = '_parrain_suspension_';
    private const META_ORIGINAL_PRICE = '_parrain_suspension_original_price';
    private const META_SUSPENDED_DATE = '_parrain_suspension_date';
    private const META_SUSPENSION_CAUSE = '_parrain_suspension_cause';
    private const META_FILLEUL_ID = '_parrain_suspension_filleul_id';
    private const META_DISCOUNT_STATUS = '_parrain_discount_status';
    
    /**
     * Statuts de remise parrain
     */
    private const STATUS_ACTIVE = 'active';
    private const STATUS_SUSPENDED = 'suspended';
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du logger
     * @param SubscriptionDiscountManager $discount_manager Manager remises existant
     */
    public function __construct( Logger $logger, SubscriptionDiscountManager $discount_manager ) {
        $this->logger = $logger;
        $this->discount_manager = $discount_manager;
        
        $this->logger->debug(
            'SuspensionHandler initialisé',
            array(
                'meta_prefix' => self::META_PREFIX,
                'statuts_disponibles' => array( self::STATUS_ACTIVE, self::STATUS_SUSPENDED )
            ),
            'suspension-handler'
        );
    }
    
    /**
     * STEP 3.2 : Traitement complet de la suspension
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param string $filleul_new_status Nouveau statut filleul
     * @param array $parrain_data Données parrain validées
     * @return array Résultat traitement
     */
    public function process_suspension( int $parrain_subscription_id, int $filleul_subscription_id, string $filleul_new_status, array $parrain_data ): array {
        
        $start_time = microtime( true );
        $processing_id = wp_generate_uuid4();
        
        $context = array(
            'parrain_subscription_id' => $parrain_subscription_id,
            'filleul_subscription_id' => $filleul_subscription_id,
            'filleul_new_status' => $filleul_new_status,
            'processing_id' => $processing_id
        );
        
        $this->logger->info(
            'DÉBUT traitement suspension remise parrain',
            $context,
            'suspension-handler'
        );
        
        try {
            
            // STEP 3.2.1 : Récupération et validation abonnement parrain
            $parrain_subscription = $this->get_validated_subscription( $parrain_subscription_id );
            if ( ! $parrain_subscription ) {
                return $this->build_error_result( 'invalid_parrain_subscription', 'Abonnement parrain introuvable ou invalide', $context, $start_time );
            }
            
            // STEP 3.2.2 : Vérification statut remise actuel
            $current_discount_status = $this->get_current_discount_status( $parrain_subscription );
            if ( $current_discount_status !== self::STATUS_ACTIVE ) {
                return $this->build_error_result( 'discount_not_active', "Remise parrain déjà dans l'état: {$current_discount_status}", $context, $start_time );
            }
            
            // STEP 3.2.3 : Sauvegarde état actuel (prix avec remise)
            $current_state = $this->save_current_discount_state( $parrain_subscription );
            if ( ! $current_state['success'] ) {
                return $this->build_error_result( 'state_save_failed', 'Échec sauvegarde état actuel', array_merge( $context, $current_state ), $start_time );
            }
            
            // STEP 3.2.4 : Calcul et application du prix sans remise
            $price_restoration = $this->restore_original_price( $parrain_subscription, $current_state['saved_data'] );
            if ( ! $price_restoration['success'] ) {
                return $this->build_error_result( 'price_restoration_failed', 'Échec restauration prix original', array_merge( $context, $price_restoration ), $start_time );
            }
            
            // STEP 3.2.5 : Mise à jour métadonnées suspension
            $metadata_update = $this->update_suspension_metadata( 
                $parrain_subscription, 
                $filleul_subscription_id, 
                $filleul_new_status,
                $current_state['saved_data']
            );
            
            if ( ! $metadata_update['success'] ) {
                return $this->build_error_result( 'metadata_update_failed', 'Échec mise à jour métadonnées', array_merge( $context, $metadata_update ), $start_time );
            }
            
            // STEP 3.2.6 : Ajout note abonnement pour traçabilité
            $this->add_suspension_note( $parrain_subscription, $filleul_subscription_id, $filleul_new_status );
            
            $success_result = array(
                'success' => true,
                'suspension_details' => array(
                    'original_price' => $current_state['saved_data']['total_with_discount'],
                    'new_price' => $price_restoration['new_price'],
                    'discount_suspended' => $current_state['saved_data']['discount_amount'],
                    'suspension_date' => current_time( 'mysql' ),
                    'filleul_cause' => $filleul_new_status
                ),
                'execution_time_ms' => $this->get_execution_time_ms( $start_time ),
                'processing_id' => $processing_id
            );
            
            $this->logger->info(
                'Suspension remise parrain RÉUSSIE',
                array_merge( $context, $success_result ),
                'suspension-handler'
            );
            
            return $success_result;
            
        } catch ( \Exception $e ) {
            return $this->build_error_result( 'unexpected_exception', $e->getMessage(), array_merge( $context, array( 'trace' => $e->getTraceAsString() ) ), $start_time );
        }
    }
    
    /**
     * STEP 3.2.1 : Récupération et validation abonnement parrain
     * 
     * @param int $subscription_id ID abonnement
     * @return \WC_Subscription|false Abonnement validé ou false
     */
    private function get_validated_subscription( int $subscription_id ) {
        
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            $this->logger->error(
                'WooCommerce Subscriptions non disponible',
                array( 'subscription_id' => $subscription_id ),
                'suspension-handler'
            );
            return false;
        }
        
        $subscription = wcs_get_subscription( $subscription_id );
        
        if ( ! $subscription || ! $subscription->has_status( array( 'active', 'on-hold' ) ) ) {
            $this->logger->warning(
                'Abonnement parrain invalide ou statut incorrect',
                array(
                    'subscription_id' => $subscription_id,
                    'found' => ! ! $subscription,
                    'status' => $subscription ? $subscription->get_status() : 'N/A'
                ),
                'suspension-handler'
            );
            return false;
        }
        
        return $subscription;
    }
    
    /**
     * STEP 3.2.2 : Obtenir le statut actuel de la remise
     * 
     * @param \WC_Subscription $subscription Abonnement parrain
     * @return string Statut actuel
     */
    private function get_current_discount_status( \WC_Subscription $subscription ): string {
        $status = $subscription->get_meta( self::META_DISCOUNT_STATUS );
        return ! empty( $status ) ? $status : self::STATUS_ACTIVE; // Par défaut = active
    }
    
    /**
     * STEP 3.2.3 : Sauvegarde de l'état actuel de la remise
     * 
     * @param \WC_Subscription $subscription Abonnement parrain
     * @return array Résultat sauvegarde
     */
    private function save_current_discount_state( \WC_Subscription $subscription ): array {
        
        try {
            
            $current_total = $subscription->get_total();
            $original_price_meta = $subscription->get_meta( '_parrain_original_price' );
            
            // Calcul du montant de remise actuel
            $discount_amount = 0;
            if ( ! empty( $original_price_meta ) && is_numeric( $original_price_meta ) ) {
                $discount_amount = floatval( $original_price_meta ) - floatval( $current_total );
            }
            
            $saved_data = array(
                'total_with_discount' => floatval( $current_total ),
                'original_price' => floatval( $original_price_meta ),
                'discount_amount' => $discount_amount,
                'currency' => $subscription->get_currency(),
                'saved_at' => current_time( 'mysql' )
            );
            
            $this->logger->info(
                'État actuel remise sauvegardé',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'saved_data' => $saved_data
                ),
                'suspension-handler'
            );
            
            return array(
                'success' => true,
                'saved_data' => $saved_data
            );
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur sauvegarde état remise',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage()
                ),
                'suspension-handler'
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * STEP 3.2.4 : Restauration du prix original (sans remise)
     * 
     * @param \WC_Subscription $subscription Abonnement parrain
     * @param array $saved_data État sauvegardé
     * @return array Résultat restauration
     */
    private function restore_original_price( \WC_Subscription $subscription, array $saved_data ): array {
        
        try {
            
            if ( empty( $saved_data['original_price'] ) || $saved_data['original_price'] <= 0 ) {
                return array(
                    'success' => false,
                    'error' => 'Prix original invalide ou manquant'
                );
            }
            
            $original_price = $saved_data['original_price'];
            
            // Mise à jour du total de l'abonnement
            $subscription->set_total( $original_price );
            $subscription->save();
            
            $this->logger->info(
                'Prix original restauré avec succès',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'old_price' => $saved_data['total_with_discount'],
                    'new_price' => $original_price,
                    'difference' => $original_price - $saved_data['total_with_discount']
                ),
                'suspension-handler'
            );
            
            return array(
                'success' => true,
                'new_price' => $original_price,
                'price_difference' => $original_price - $saved_data['total_with_discount']
            );
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur restauration prix original',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage(),
                    'saved_data' => $saved_data
                ),
                'suspension-handler'
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * STEP 3.2.5 : Mise à jour des métadonnées de suspension
     * 
     * @param \WC_Subscription $subscription Abonnement parrain
     * @param int $filleul_id ID filleul
     * @param string $cause Cause suspension
     * @param array $saved_data État sauvegardé
     * @return array Résultat mise à jour
     */
    private function update_suspension_metadata( \WC_Subscription $subscription, int $filleul_id, string $cause, array $saved_data ): array {
        
        try {
            
            // Métadonnées principales
            $subscription->update_meta_data( self::META_DISCOUNT_STATUS, self::STATUS_SUSPENDED );
            $subscription->update_meta_data( self::META_SUSPENDED_DATE, current_time( 'mysql' ) );
            $subscription->update_meta_data( self::META_SUSPENSION_CAUSE, $cause );
            $subscription->update_meta_data( self::META_FILLEUL_ID, $filleul_id );
            $subscription->update_meta_data( self::META_ORIGINAL_PRICE, $saved_data['total_with_discount'] );
            
            // Métadonnées détaillées pour réactivation future
            $suspension_details = array(
                'suspended_at' => current_time( 'mysql' ),
                'filleul_id' => $filleul_id,
                'cause' => $cause,
                'original_state' => $saved_data,
                'version' => '2.8.0-dev'
            );
            
            $subscription->update_meta_data( self::META_PREFIX . 'details', wp_json_encode( $suspension_details ) );
            
            $subscription->save();
            
            $this->logger->info(
                'Métadonnées suspension mises à jour',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'new_status' => self::STATUS_SUSPENDED,
                    'suspension_details' => $suspension_details
                ),
                'suspension-handler'
            );
            
            return array( 'success' => true );
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur mise à jour métadonnées suspension',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage()
                ),
                'suspension-handler'
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * STEP 3.2.6 : Ajout note abonnement pour traçabilité
     * 
     * @param \WC_Subscription $subscription Abonnement parrain
     * @param int $filleul_id ID filleul
     * @param string $cause Cause suspension
     * @return void
     */
    private function add_suspension_note( \WC_Subscription $subscription, int $filleul_id, string $cause ): void {
        
        $note = sprintf(
            'Remise parrain suspendue automatiquement. Filleul #%d changé vers statut "%s". Prix restauré au montant original.',
            $filleul_id,
            $cause
        );
        
        $subscription->add_order_note( $note );
        
        $this->logger->debug(
            'Note suspension ajoutée à l\'abonnement',
            array(
                'subscription_id' => $subscription->get_id(),
                'note' => $note
            ),
            'suspension-handler'
        );
    }
    
    /**
     * Construire résultat d'erreur standardisé
     * 
     * @param string $error_code Code erreur
     * @param string $error_message Message erreur
     * @param array $context Contexte
     * @param float $start_time Temps début
     * @return array Résultat erreur
     */
    private function build_error_result( string $error_code, string $error_message, array $context, float $start_time ): array {
        
        $error_result = array(
            'success' => false,
            'error_code' => $error_code,
            'error_message' => $error_message,
            'execution_time_ms' => $this->get_execution_time_ms( $start_time )
        );
        
        $this->logger->error(
            "Erreur traitement suspension: {$error_message}",
            array_merge( $context, $error_result ),
            'suspension-handler'
        );
        
        return $error_result;
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
