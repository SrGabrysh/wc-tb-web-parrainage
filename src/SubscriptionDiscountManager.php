<?php
namespace TBWeb\WCParrainage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire des remises d'abonnement v2.7.0
 * 
 * Responsabilité unique : Application et retrait des remises sur abonnements WooCommerce
 * Principe SRP : Séparation claire entre calcul (DiscountCalculator) et application (cette classe)
 * Principe OCP : Extensible via hooks WordPress pour personnalisations
 * 
 * @since 2.7.0
 */
class SubscriptionDiscountManager {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Service de notifications
     * @var DiscountNotificationService
     */
    private $notification_service;
    
    /**
     * Constructor avec injection de dépendances
     * 
     * @param Logger $logger Instance du logger
     * @param DiscountNotificationService $notification_service Service notifications
     */
    public function __construct( Logger $logger, DiscountNotificationService $notification_service ) {
        $this->logger = $logger;
        $this->notification_service = $notification_service;
    }
    
    /**
     * Applique une remise à un abonnement parrain avec validation stricte
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param array $discount_data Données de remise calculées
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param int $filleul_order_id ID commande filleul
     * @return array Résultat de l'application
     * @throws InvalidArgumentException Si données invalides
     * @throws RuntimeException Si remise déjà active
     */
    public function apply_discount( $parrain_subscription_id, $discount_data, $filleul_subscription_id, $filleul_order_id ) {
        try {
            // Hook avant application pour extensibilité
            do_action( 'tb_parrainage_before_apply_discount', $parrain_subscription_id, $discount_data );
            
            $parrain_subscription = wcs_get_subscription( $parrain_subscription_id );
            if ( ! $parrain_subscription ) {
                throw new InvalidArgumentException( 'Abonnement parrain introuvable' );
            }
            
            // Validation critique : vérifier si une remise est déjà active
            $existing_discount = $parrain_subscription->get_meta( '_tb_parrainage_discount_active' );
            if ( $existing_discount ) {
                throw new RuntimeException( 'Une remise est déjà active sur cet abonnement' );
            }
            
            // Validation de la structure des données de remise
            if ( ! isset( $discount_data['discount_amount'] ) || $discount_data['discount_amount'] <= 0 ) {
                throw new InvalidArgumentException( 'Montant de remise invalide' );
            }
            
            // Capturer le prix actuel comme "original" uniquement si pas déjà sauvegardé
            $saved_original = $parrain_subscription->get_meta( '_tb_parrainage_original_price' );
            $current_price = $parrain_subscription->get_total();
            
            if ( ! $saved_original ) {
                // Validation de cohérence
                if ( $current_price <= 0 ) {
                    throw new InvalidArgumentException( 'Prix abonnement invalide pour application remise' );
                }
                
                // Sauvegarder avec timestamp pour audit
                $parrain_subscription->update_meta_data( '_tb_parrainage_original_price', $current_price );
                $parrain_subscription->update_meta_data( '_tb_parrainage_original_price_date', current_time( 'mysql' ) );
                
                $this->logger->info(
                    'Prix original sauvegardé avant application remise',
                    array(
                        'parrain_subscription' => $parrain_subscription_id,
                        'original_price' => $current_price,
                        'timestamp' => current_time( 'mysql' )
                    ),
                    'subscription-discount-manager'
                );
            } else {
                // Utiliser le prix original déjà sauvegardé
                $current_price = floatval( $saved_original );
                
                $this->logger->info(
                    'Utilisation prix original existant',
                    array(
                        'parrain_subscription' => $parrain_subscription_id,
                        'saved_original' => $current_price
                    ),
                    'subscription-discount-manager'
                );
            }
            
            // Calculer le nouveau prix avec protection contre prix négatifs
            $new_price = max( 0, $current_price - $discount_data['discount_amount'] );
            
            // Application de la remise avec gestion des cas complexes
            $this->update_subscription_price( $parrain_subscription, $new_price );
            
            // Métadonnées de traçabilité complètes
            $parrain_subscription->update_meta_data( '_tb_parrainage_discount_active', true );
            $parrain_subscription->update_meta_data( '_tb_parrainage_discount_amount', $discount_data['discount_amount'] );
            $parrain_subscription->update_meta_data( '_tb_parrainage_discount_start', current_time( 'mysql' ) );
            $parrain_subscription->update_meta_data( '_tb_parrainage_filleul_id', $filleul_subscription_id );
            $parrain_subscription->update_meta_data( '_tb_parrainage_filleul_order_id', $filleul_order_id );
            
            // Calculer et stocker la date de fin (12 mois + 2 jours de grâce)
            $duration_months = apply_filters( 'tb_parrainage_discount_duration', WC_TB_PARRAINAGE_DISCOUNT_DURATION );
            $grace_days = apply_filters( 'tb_parrainage_discount_grace_period', WC_TB_PARRAINAGE_DISCOUNT_GRACE_PERIOD );
            $end_date = date( 'Y-m-d H:i:s', strtotime( "+{$duration_months} months +{$grace_days} days" ) );
            
            $parrain_subscription->update_meta_data( '_tb_parrainage_discount_end_date', $end_date );
            $parrain_subscription->save();
            
            // Note d'abonnement pour traçabilité administrative
            $parrain_subscription->add_order_note( 
                sprintf( 
                    'Remise parrainage appliquée : -%s€/mois (Filleul #%d, Commande #%d) - Fin prévue : %s', 
                    $discount_data['discount_amount'], 
                    $filleul_subscription_id,
                    $filleul_order_id,
                    date( 'd/m/Y', strtotime( $end_date ) )
                )
            );
            
            $result = array(
                'success' => true,
                'original_price' => $current_price,
                'new_price' => $new_price,
                'discount_amount' => $discount_data['discount_amount'],
                'end_date' => $end_date
            );
            
            $this->logger->info(
                'Remise parrainage appliquée avec succès',
                array_merge( $result, array(
                    'parrain_subscription' => $parrain_subscription_id,
                    'filleul_subscription' => $filleul_subscription_id,
                    'filleul_order' => $filleul_order_id
                )),
                'subscription-discount-manager'
            );
            
            // Notification avec données enrichies
            $this->notification_service->send_discount_applied_notification(
                $parrain_subscription_id,
                array_merge( $discount_data, $result )
            );
            
            // Hook après application réussie
            do_action( 'tb_parrainage_after_apply_discount', $parrain_subscription_id, $result );
            
            return $result;
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Échec application remise parrainage',
                array( 
                    'parrain_subscription' => $parrain_subscription_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'subscription-discount-manager'
            );
            
            return array( 
                'success' => false, 
                'error' => $e->getMessage(),
                'error_type' => get_class( $e )
            );
        }
    }
    
    /**
     * Retire une remise après expiration (12 mois) avec restauration prix original
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @return array Résultat de l'opération
     * @throws InvalidArgumentException Si abonnement invalide
     * @throws RuntimeException Si prix original non trouvé
     */
    public function remove_discount( $parrain_subscription_id, $filleul_subscription_id ) {
        try {
            $parrain_subscription = wcs_get_subscription( $parrain_subscription_id );
            if ( ! $parrain_subscription ) {
                throw new InvalidArgumentException( 'Abonnement parrain introuvable pour retrait remise' );
            }
            
            // Vérifier qu'une remise est active
            $is_discount_active = $parrain_subscription->get_meta( '_tb_parrainage_discount_active' );
            if ( ! $is_discount_active ) {
                $this->logger->warning(
                    'Tentative de retrait remise sur abonnement sans remise active',
                    array( 'parrain_subscription' => $parrain_subscription_id ),
                    'subscription-discount-manager'
                );
                return array( 'success' => true, 'message' => 'Aucune remise active à retirer' );
            }
            
            // Récupérer le prix original avec validation
            $original_price = $parrain_subscription->get_meta( '_tb_parrainage_original_price' );
            if ( ! $original_price || $original_price <= 0 ) {
                throw new RuntimeException( 'Prix original non trouvé ou invalide' );
            }
            
            $original_price_float = floatval( $original_price );
            $current_discount = $parrain_subscription->get_meta( '_tb_parrainage_discount_amount' );
            
            // Restaurer le prix original
            $this->update_subscription_price( $parrain_subscription, $original_price_float );
            
            // Mise à jour des métadonnées de fin
            $parrain_subscription->delete_meta_data( '_tb_parrainage_discount_active' );
            $parrain_subscription->update_meta_data( '_tb_parrainage_discount_end', current_time( 'mysql' ) );
            $parrain_subscription->update_meta_data( '_tb_parrainage_discount_removed_reason', 'expired_12_months' );
            
            // Conserver l'historique pour audit (ne pas supprimer toutes les métadonnées)
            $parrain_subscription->save();
            
            // Note d'abonnement pour traçabilité
            $parrain_subscription->add_order_note( 
                sprintf( 
                    'Remise parrainage terminée après 12 mois (Filleul #%d) - Prix restauré : %s€/mois (était : %s€/mois)', 
                    $filleul_subscription_id,
                    $original_price_float,
                    $original_price_float - floatval( $current_discount )
                )
            );
            
            $result = array( 
                'success' => true,
                'restored_price' => $original_price_float,
                'previous_discount' => $current_discount,
                'removal_reason' => 'expired_12_months'
            );
            
            $this->logger->info(
                'Remise parrainage retirée après expiration',
                array_merge( $result, array(
                    'parrain_subscription' => $parrain_subscription_id,
                    'filleul_subscription' => $filleul_subscription_id
                )),
                'subscription-discount-manager'
            );
            
            // Hook pour notification de fin de remise
            do_action( 'tb_parrainage_discount_removed', $parrain_subscription_id, $filleul_subscription_id );
            
            return $result;
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Échec suppression remise parrainage',
                array( 
                    'parrain_subscription' => $parrain_subscription_id,
                    'filleul_subscription' => $filleul_subscription_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'subscription-discount-manager'
            );
            
            return array( 
                'success' => false, 
                'error' => $e->getMessage(),
                'error_type' => get_class( $e )
            );
        }
    }
    
    /**
     * Met à jour le prix d'un abonnement avec gestion des cas complexes
     * Compatible WooCommerce Subscriptions 2.0+ avec support multi-produits
     * 
     * @param WC_Subscription $subscription Instance d'abonnement
     * @param float $new_price Nouveau prix total
     * @throws RuntimeException Si version WCS non supportée
     */
    private function update_subscription_price( $subscription, $new_price ) {
        // Compatibilité WCS 2.0+
        if ( ! method_exists( $subscription, 'get_items' ) ) {
            throw new RuntimeException( 'Version WooCommerce Subscriptions non supportée' );
        }
        
        $items = $subscription->get_items();
        $item_count = count( $items );
        
        if ( $item_count === 0 ) {
            throw new RuntimeException( 'Aucun produit trouvé dans l\'abonnement' );
        }
        
        $this->logger->debug(
            'Mise à jour prix abonnement',
            array(
                'subscription_id' => $subscription->get_id(),
                'items_count' => $item_count,
                'new_total_price' => $new_price
            ),
            'subscription-discount-manager'
        );
        
        if ( $item_count === 1 ) {
            // Cas simple : un seul produit
            foreach ( $items as $item ) {
                $old_total = $item->get_total();
                $item->set_total( $new_price );
                $item->set_subtotal( $new_price );
                $item->save();
                
                $this->logger->debug(
                    'Prix item unique mis à jour',
                    array(
                        'item_id' => $item->get_id(),
                        'old_total' => $old_total,
                        'new_total' => $new_price
                    ),
                    'subscription-discount-manager'
                );
            }
        } else {
            // Cas complexe : répartir proportionnellement la remise
            $original_total = $subscription->get_total();
            
            if ( $original_total <= 0 ) {
                throw new RuntimeException( 'Total abonnement invalide pour répartition proportionnelle' );
            }
            
            $ratio = $new_price / $original_total;
            
            $this->logger->debug(
                'Répartition proportionnelle multi-produits',
                array(
                    'original_total' => $original_total,
                    'new_total' => $new_price,
                    'ratio' => $ratio
                ),
                'subscription-discount-manager'
            );
            
            foreach ( $items as $item ) {
                $old_item_total = $item->get_total();
                $item_new_price = round( $old_item_total * $ratio, 2 );
                
                $item->set_total( $item_new_price );
                $item->set_subtotal( $item_new_price );
                $item->save();
                
                $this->logger->debug(
                    'Prix item proportionnel mis à jour',
                    array(
                        'item_id' => $item->get_id(),
                        'old_total' => $old_item_total,
                        'new_total' => $item_new_price,
                        'ratio_applied' => $ratio
                    ),
                    'subscription-discount-manager'
                );
            }
        }
        
        // Recalcul sécurisé des totaux
        $subscription->calculate_totals();
        
        // Forcer la mise à jour du montant récurrent (compatibilité)
        $subscription_id = $subscription->get_id();
        update_post_meta( $subscription_id, '_order_total', $new_price );
        
        // Hook pour compatibilité et extensibilité
        do_action( 'tb_parrainage_subscription_price_updated', $subscription, $new_price );
        
        $this->logger->info(
            'Prix abonnement mis à jour avec succès',
            array(
                'subscription_id' => $subscription_id,
                'final_price' => $new_price,
                'items_updated' => $item_count
            ),
            'subscription-discount-manager'
        );
    }
    
    /**
     * Vérifie les remises expirées et les retire automatiquement
     * Méthode appelée par le CRON quotidien de vérification
     * 
     * @return array Statistiques de traitement
     */
    public function check_expired_discounts() {
        global $wpdb;
        
        $current_time = current_time( 'mysql' );
        
        // Rechercher les abonnements avec remises expirées
        $expired_subscriptions = $wpdb->get_results( $wpdb->prepare( "
            SELECT post_id, meta_value as end_date
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_tb_parrainage_discount_end_date'
            AND meta_value < %s
            AND post_id IN (
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_tb_parrainage_discount_active' 
                AND meta_value = '1'
            )
        ", $current_time ), ARRAY_A );
        
        $processed_count = 0;
        $error_count = 0;
        
        foreach ( $expired_subscriptions as $expired ) {
            $subscription_id = intval( $expired['post_id'] );
            $end_date = $expired['end_date'];
            
            try {
                $subscription = wcs_get_subscription( $subscription_id );
                if ( ! $subscription ) {
                    continue;
                }
                
                $filleul_id = $subscription->get_meta( '_tb_parrainage_filleul_id' );
                
                $result = $this->remove_discount( $subscription_id, $filleul_id );
                
                if ( $result['success'] ) {
                    $processed_count++;
                    
                    $this->logger->info(
                        'Remise expirée retirée automatiquement',
                        array(
                            'subscription_id' => $subscription_id,
                            'end_date' => $end_date,
                            'processed_at' => $current_time
                        ),
                        'subscription-discount-manager'
                    );
                } else {
                    $error_count++;
                }
                
            } catch ( Exception $e ) {
                $error_count++;
                
                $this->logger->error(
                    'Erreur lors du retrait automatique de remise expirée',
                    array(
                        'subscription_id' => $subscription_id,
                        'error' => $e->getMessage()
                    ),
                    'subscription-discount-manager'
                );
            }
        }
        
        $stats = array(
            'total_expired' => count( $expired_subscriptions ),
            'successfully_processed' => $processed_count,
            'errors' => $error_count,
            'check_time' => $current_time
        );
        
        $this->logger->info(
            'Vérification automatique remises expirées terminée',
            $stats,
            'subscription-discount-manager'
        );
        
        return $stats;
    }
    
    /**
     * Récupère les statistiques des remises actives
     * 
     * @return array Statistiques complètes
     */
    public function get_active_discounts_stats() {
        global $wpdb;
        
        // Remises actives
        $active_count = $wpdb->get_var( "
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_tb_parrainage_discount_active'
            AND meta_value = '1'
        " );
        
        // Montant total des remises
        $total_discount = $wpdb->get_var( "
            SELECT SUM(CAST(pm2.meta_value AS DECIMAL(10,2)))
            FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
            WHERE pm1.meta_key = '_tb_parrainage_discount_active' 
            AND pm1.meta_value = '1'
            AND pm2.meta_key = '_tb_parrainage_discount_amount'
        " );
        
        // Remises expirant dans les 30 prochains jours
        $expiring_soon = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
            WHERE pm1.meta_key = '_tb_parrainage_discount_active' 
            AND pm1.meta_value = '1'
            AND pm2.meta_key = '_tb_parrainage_discount_end_date'
            AND pm2.meta_value BETWEEN %s AND %s
        ", current_time( 'mysql' ), date( 'Y-m-d H:i:s', strtotime( '+30 days' ) ) ) );
        
        return array(
            'active_discounts' => intval( $active_count ),
            'total_monthly_discount' => floatval( $total_discount ?: 0 ),
            'expiring_within_30_days' => intval( $expiring_soon ),
            'generated_at' => current_time( 'mysql' )
        );
    }
}
