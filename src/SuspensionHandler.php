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
            
            // CORRECTION LOGIQUE : Récupérer le montant de remise appliqué
            $discount_amount = $subscription->get_meta( '_tb_parrainage_discount_amount' );
            if ( empty( $discount_amount ) || ! is_numeric( $discount_amount ) ) {
                $discount_amount = 0;
            } else {
                $discount_amount = floatval( $discount_amount );
            }
            
            $saved_data = array(
                'current_total_with_discount' => floatval( $current_total ),
                'discount_amount_to_remove' => $discount_amount,
                'new_total_without_discount' => floatval( $current_total ) + $discount_amount, // ADDITIONNER la remise
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
            
            if ( empty( $saved_data['new_total_without_discount'] ) || $saved_data['new_total_without_discount'] <= 0 ) {
                return array(
                    'success' => false,
                    'error' => 'Nouveau prix sans remise invalide ou manquant'
                );
            }
            
            $new_total = $saved_data['new_total_without_discount'];
            $discount_to_remove = $saved_data['discount_amount_to_remove'];
            
            // CORRECTION v2.8.2-fix9 : Logique Lambda - Modifier line_items ET totaux
            // Inspiré de votre fonction Lambda qui fonctionne parfaitement
            
            $subscription_id = $subscription->get_id();
            
            // ÉTAPE 1 : Modifier les line_items (comme votre Lambda)
            $line_items = $subscription->get_items();
            $line_item_updated = false;
            
            foreach ( $line_items as $item_id => $item ) {
                // CORRECTION v2.8.2-fix10 : Calculer le prix HT correct
                // Prix HT normal = (Prix TTC normal) / 1.20
                $prix_ttc_normal = $new_total; // 71.99€
                $prix_ht_normal = round( $prix_ttc_normal / 1.20, 2 ); // 59.99€
                
                $current_item_total = $item->get_total();
                
                // Mettre à jour le line_item avec le prix HT correct
                $item->set_total( $prix_ht_normal );
                $item->set_subtotal( $prix_ht_normal );
                $item->save();
                
                $this->logger->info(
                    'Line item mis à jour (prix HT correct)',
                    array(
                        'subscription_id' => $subscription_id,
                        'item_id' => $item_id,
                        'old_total_ht' => $current_item_total,
                        'new_total_ht' => $prix_ht_normal,
                        'prix_ttc_final' => $prix_ttc_normal,
                        'tva_rate' => '20%'
                    ),
                    'suspension-handler'
                );
                
                $line_item_updated = true;
                break; // Généralement un seul produit par abonnement
            }
            
            if ( ! $line_item_updated ) {
                return array(
                    'success' => false,
                    'error' => 'Aucun line_item trouvé pour mise à jour'
                );
            }
            
            // ÉTAPE 2 : Recalculer les totaux (comme votre Lambda)
            $subscription->calculate_totals();
            $subscription->save();
            
            // ÉTAPE 3 : Forcer les totaux si nécessaire (backup SQL)
            global $wpdb;
            
            $update_total = $wpdb->update(
                $wpdb->postmeta,
                array( 'meta_value' => $new_total ),
                array( 
                    'post_id' => $subscription_id,
                    'meta_key' => '_order_total'
                )
            );
            
            // Marquer la remise comme inactive
            $update_status = $wpdb->update(
                $wpdb->postmeta,
                array( 'meta_value' => '0' ),
                array( 
                    'post_id' => $subscription_id,
                    'meta_key' => '_tb_parrainage_discount_active'
                )
            );
            
            $this->logger->info(
                'Remise supprimée avec succès (méthode Lambda)',
                array(
                    'subscription_id' => $subscription_id,
                    'old_total' => $saved_data['current_total_with_discount'],
                    'new_total' => $new_total,
                    'discount_removed' => $discount_to_remove,
                    'method' => 'lambda_inspired',
                    'line_items_updated' => true,
                    'totals_recalculated' => true,
                    'sql_backup' => array(
                        'total_update' => $update_total,
                        'status_update' => $update_status
                    )
                ),
                'suspension-handler'
            );

            return array(
                'success' => true,
                'new_price' => $new_total,
                'price_difference' => $discount_to_remove
            );

        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur suppression remise',
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
