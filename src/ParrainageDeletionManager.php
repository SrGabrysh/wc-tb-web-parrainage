<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire de suppression des parrainages
 * 
 * Responsabilité unique : Gestion de la suppression manuelle des parrainages
 * avec annulation automatique des réductions de tarification appliquées
 * 
 * @package TBWeb\WCParrainage
 * @since 2.0.0
 */
class ParrainageDeletionManager {
    
    /**
     * @var Logger Instance du système de logs
     */
    private $logger;
    
    /**
     * @var ParrainPricing\ParrainPricingManager Instance du gestionnaire de pricing
     */
    private $pricing_manager;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du système de logs
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        
        // Initialiser le gestionnaire de pricing si disponible
        if ( class_exists( 'TBWeb\WCParrainage\ParrainPricing\ParrainPricingManager' ) ) {
            $this->pricing_manager = new ParrainPricing\ParrainPricingManager( $logger );
        }
    }
    
    /**
     * Initialise les hooks AJAX
     * 
     * @return void
     */
    public function init() {
        add_action( 'wp_ajax_tb_parrainage_delete_selected', array( $this, 'handle_delete_selected_ajax' ) );
        add_action( 'wp_ajax_tb_parrainage_delete_single', array( $this, 'handle_delete_single_ajax' ) );
        
        $this->logger->info( 'ParrainageDeletionManager initialisé', array(
            'pricing_manager_available' => ! ! $this->pricing_manager
        ) );
    }
    
    /**
     * Gestionnaire AJAX pour suppression multiple
     */
    public function handle_delete_selected_ajax() {
        try {
            // Vérifications de sécurité
            $this->verify_ajax_security();
            
            // Récupérer les IDs des commandes à supprimer
            $order_ids = $this->get_selected_order_ids();
            
            if ( empty( $order_ids ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Aucun parrainage sélectionné.', 'wc-tb-web-parrainage' )
                ) );
            }
            
            // Supprimer les parrainages sélectionnés
            $results = $this->delete_multiple_parrainages( $order_ids );
            
            // Préparer la réponse
            $response = array(
                'message' => sprintf(
                    __( '%d parrainages supprimés avec succès. %d réductions annulées.', 'wc-tb-web-parrainage' ),
                    $results['deleted_count'],
                    $results['cancelled_reductions']
                ),
                'deleted_count' => $results['deleted_count'],
                'cancelled_reductions' => $results['cancelled_reductions'],
                'errors' => $results['errors']
            );
            
            if ( ! empty( $results['errors'] ) ) {
                $response['message'] .= ' Certaines suppressions ont échoué.';
            }
            
            wp_send_json_success( $response );
            
        } catch ( Exception $e ) {
            $this->logger->error( 'Erreur suppression multiple parrainages', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ) );
            
            wp_send_json_error( array(
                'message' => __( 'Erreur lors de la suppression. Vérifiez les logs.', 'wc-tb-web-parrainage' )
            ) );
        }
    }
    
    /**
     * Gestionnaire AJAX pour suppression unique
     */
    public function handle_delete_single_ajax() {
        try {
            // Vérifications de sécurité
            $this->verify_ajax_security();
            
            // Récupérer l'ID de la commande
            $order_id = intval( $_POST['order_id'] ?? 0 );
            
            if ( ! $order_id ) {
                wp_send_json_error( array(
                    'message' => __( 'ID de commande invalide.', 'wc-tb-web-parrainage' )
                ) );
            }
            
            // Supprimer le parrainage
            $result = $this->delete_single_parrainage( $order_id );
            
            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message' => __( 'Parrainage supprimé avec succès.', 'wc-tb-web-parrainage' ),
                    'reduction_cancelled' => $result['reduction_cancelled']
                ) );
            } else {
                wp_send_json_error( array(
                    'message' => $result['error'] ?? __( 'Erreur lors de la suppression.', 'wc-tb-web-parrainage' )
                ) );
            }
            
        } catch ( Exception $e ) {
            $this->logger->error( 'Erreur suppression parrainage unique', array(
                'error' => $e->getMessage(),
                'order_id' => $_POST['order_id'] ?? 'unknown',
                'user_id' => get_current_user_id()
            ) );
            
            wp_send_json_error( array(
                'message' => __( 'Erreur lors de la suppression. Vérifiez les logs.', 'wc-tb-web-parrainage' )
            ) );
        }
    }
    
    /**
     * Supprime plusieurs parrainages
     * 
     * @param array $order_ids IDs des commandes
     * @return array Résultats de suppression
     */
    private function delete_multiple_parrainages( array $order_ids ): array {
        $results = array(
            'deleted_count' => 0,
            'cancelled_reductions' => 0,
            'errors' => array()
        );
        
        foreach ( $order_ids as $order_id ) {
            $result = $this->delete_single_parrainage( $order_id );
            
            if ( $result['success'] ) {
                $results['deleted_count']++;
                if ( $result['reduction_cancelled'] ) {
                    $results['cancelled_reductions']++;
                }
            } else {
                $results['errors'][] = sprintf(
                    __( 'Commande #%d: %s', 'wc-tb-web-parrainage' ),
                    $order_id,
                    $result['error']
                );
            }
        }
        
        // Log du résultat global
        $this->logger->info( 'Suppression multiple de parrainages terminée', array(
            'total_requested' => count( $order_ids ),
            'deleted_count' => $results['deleted_count'],
            'cancelled_reductions' => $results['cancelled_reductions'],
            'errors_count' => count( $results['errors'] )
        ) );
        
        return $results;
    }
    
    /**
     * Supprime un parrainage unique
     * 
     * @param int $order_id ID de la commande
     * @return array Résultat de suppression
     */
    private function delete_single_parrainage( int $order_id ): array {
        try {
            // Vérifier que la commande existe et a un parrainage
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return array(
                    'success' => false,
                    'error' => __( 'Commande introuvable.', 'wc-tb-web-parrainage' )
                );
            }
            
            $parrain_code = $order->get_meta( '_billing_parrain_code' );
            if ( empty( $parrain_code ) ) {
                return array(
                    'success' => false,
                    'error' => __( 'Cette commande n\'a pas de parrainage.', 'wc-tb-web-parrainage' )
                );
            }
            
            // Récupérer les infos avant suppression pour logging
            $parrain_info = array(
                'parrain_code' => $parrain_code,
                'parrain_subscription_id' => $order->get_meta( '_parrain_subscription_id' ),
                'parrain_email' => $order->get_meta( '_parrain_email' ),
                'parrain_nom' => $order->get_meta( '_parrain_nom_complet' ),
                'avantage' => $order->get_meta( '_parrainage_avantage' ),
                'order_total' => $order->get_total(),
                'order_date' => $order->get_date_created()
            );
            
            // Annuler les réductions automatiques si elles existent
            $reduction_cancelled = $this->cancel_automatic_reductions( $order_id, $parrain_code );
            
            // Supprimer toutes les métadonnées de parrainage
            $this->delete_parrainage_metadata( $order );
            
            // Log de la suppression
            $this->logger->warning( 'Parrainage supprimé manuellement', array(
                'order_id' => $order_id,
                'parrain_info' => $parrain_info,
                'reduction_cancelled' => $reduction_cancelled,
                'admin_user_id' => get_current_user_id()
            ) );
            
            return array(
                'success' => true,
                'reduction_cancelled' => $reduction_cancelled
            );
            
        } catch ( Exception $e ) {
            $this->logger->error( 'Erreur lors de la suppression de parrainage', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ) );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Annule les réductions automatiques liées à un parrainage
     * 
     * @param int $order_id ID de la commande
     * @param string $parrain_code Code du parrain
     * @return bool True si des réductions ont été annulées
     */
    private function cancel_automatic_reductions( int $order_id, string $parrain_code ): bool {
        if ( ! $this->pricing_manager ) {
            return false; // Système de pricing non disponible
        }
        
        try {
            global $wpdb;
            
            $parrain_subscription_id = intval( $parrain_code );
            
            // Vérifier s'il y a des réductions programmées pour ce parrain
            $schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
            $scheduled_reductions = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$schedule_table} 
                 WHERE filleul_order_id = %d 
                 AND parrain_subscription_id = %d 
                 AND status IN ('pending', 'applied')",
                $order_id,
                $parrain_subscription_id
            ) );
            
            if ( empty( $scheduled_reductions ) ) {
                return false; // Pas de réductions à annuler
            }
            
            $cancelled_count = 0;
            
            foreach ( $scheduled_reductions as $reduction ) {
                if ( $reduction->status === 'pending' ) {
                    // Annuler la réduction programmée
                    $wpdb->update(
                        $schedule_table,
                        array(
                            'status' => 'cancelled',
                            'updated_at' => current_time( 'mysql' )
                        ),
                        array( 'id' => $reduction->id ),
                        array( '%s', '%s' ),
                        array( '%d' )
                    );
                    
                    $cancelled_count++;
                    
                } elseif ( $reduction->status === 'applied' ) {
                    // Programmer la restauration du prix original
                    $this->schedule_price_restoration( $reduction );
                    $cancelled_count++;
                }
                
                // Ajouter à l'historique
                $this->add_cancellation_to_history( $reduction, $order_id );
            }
            
            if ( $cancelled_count > 0 ) {
                $this->logger->info( 'Réductions automatiques annulées', array(
                    'order_id' => $order_id,
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'cancelled_count' => $cancelled_count
                ) );
            }
            
            return $cancelled_count > 0;
            
        } catch ( Exception $e ) {
            $this->logger->error( 'Erreur lors de l\'annulation des réductions automatiques', array(
                'order_id' => $order_id,
                'parrain_code' => $parrain_code,
                'error' => $e->getMessage()
            ) );
            
            return false;
        }
    }
    
    /**
     * Programme la restauration du prix original
     * 
     * @param object $reduction Données de la réduction appliquée
     */
    private function schedule_price_restoration( $reduction ) {
        global $wpdb;
        
        // Récupérer l'abonnement parrain pour la prochaine date de paiement
        $subscription = wcs_get_subscription( $reduction->parrain_subscription_id );
        if ( ! $subscription ) {
            return;
        }
        
        $next_payment = $subscription->get_date( 'next_payment' );
        if ( ! $next_payment ) {
            return;
        }
        
        // Insérer une nouvelle entrée pour restaurer le prix
        $schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        $wpdb->insert(
            $schedule_table,
            array(
                'parrain_subscription_id' => $reduction->parrain_subscription_id,
                'filleul_order_id' => $reduction->filleul_order_id,
                'action' => 'remove_reduction',
                'original_price' => $reduction->new_price, // Prix réduit actuel
                'new_price' => $reduction->original_price, // Prix original à restaurer
                'reduction_amount' => -$reduction->reduction_amount, // Montant négatif = restauration
                'filleul_contribution' => $reduction->filleul_contribution,
                'reduction_percentage' => -$reduction->reduction_percentage,
                'scheduled_date' => $next_payment,
                'status' => 'pending',
                'metadata' => wp_json_encode( array(
                    'reason' => 'parrainage_deleted',
                    'original_reduction_id' => $reduction->id,
                    'deleted_by_admin' => get_current_user_id()
                ) )
            ),
            array( '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
        );
        
        $this->logger->info( 'Restauration de prix programmée', array(
            'parrain_subscription_id' => $reduction->parrain_subscription_id,
            'restore_to_price' => $reduction->original_price,
            'scheduled_date' => $next_payment
        ) );
    }
    
    /**
     * Ajoute l'annulation à l'historique
     * 
     * @param object $reduction Données de la réduction
     * @param int $order_id ID de la commande supprimée
     */
    private function add_cancellation_to_history( $reduction, int $order_id ) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'tb_parrainage_pricing_history';
        $wpdb->insert(
            $history_table,
            array(
                'parrain_subscription_id' => $reduction->parrain_subscription_id,
                'filleul_order_id' => $order_id,
                'action' => 'reduction_cancelled',
                'price_before' => $reduction->new_price,
                'price_after' => $reduction->original_price,
                'reduction_amount' => $reduction->reduction_amount,
                'execution_status' => 'cancelled',
                'execution_details' => wp_json_encode( array(
                    'reason' => 'parrainage_deleted',
                    'deleted_by_admin' => get_current_user_id(),
                    'original_reduction_id' => $reduction->id
                ) ),
                'user_notified' => false
            ),
            array( '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%d' )
        );
    }
    
    /**
     * Supprime toutes les métadonnées de parrainage d'une commande
     * 
     * @param WC_Order $order Instance de commande
     */
    private function delete_parrainage_metadata( $order ) {
        $parrainage_meta_keys = array(
            '_billing_parrain_code',
            '_parrain_subscription_id',
            '_parrain_user_id',
            '_parrain_email',
            '_parrain_nom_complet',
            '_parrainage_avantage'
        );
        
        foreach ( $parrainage_meta_keys as $meta_key ) {
            $order->delete_meta_data( $meta_key );
        }
        
        $order->save_meta_data();
    }
    
    /**
     * Vérifie la sécurité des requêtes AJAX
     * 
     * @throws Exception Si la vérification échoue
     */
    private function verify_ajax_security() {
        // Vérifier les permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( __( 'Permissions insuffisantes.', 'wc-tb-web-parrainage' ) );
        }
        
        // Vérifier le nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_parrainage_delete_action' ) ) {
            throw new Exception( __( 'Nonce invalide.', 'wc-tb-web-parrainage' ) );
        }
        
        // Vérifier la méthode POST
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            throw new Exception( __( 'Méthode de requête invalide.', 'wc-tb-web-parrainage' ) );
        }
    }
    
    /**
     * Récupère les IDs des commandes sélectionnées
     * 
     * @return array IDs des commandes
     * @throws Exception Si aucun ID valide
     */
    private function get_selected_order_ids(): array {
        $order_ids = $_POST['order_ids'] ?? array();
        
        if ( ! is_array( $order_ids ) ) {
            $order_ids = array( $order_ids );
        }
        
        // Nettoyer et valider les IDs
        $order_ids = array_map( 'intval', $order_ids );
        $order_ids = array_filter( $order_ids, function( $id ) {
            return $id > 0;
        } );
        
        return array_unique( $order_ids );
    }
    
    /**
     * Vérifie si un parrainage peut être supprimé
     * 
     * @param int $order_id ID de la commande
     * @return array Résultat de vérification
     */
    public function can_delete_parrainage( int $order_id ): array {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return array(
                'can_delete' => false,
                'reason' => __( 'Commande introuvable.', 'wc-tb-web-parrainage' )
            );
        }
        
        $parrain_code = $order->get_meta( '_billing_parrain_code' );
        if ( empty( $parrain_code ) ) {
            return array(
                'can_delete' => false,
                'reason' => __( 'Cette commande n\'a pas de parrainage.', 'wc-tb-web-parrainage' )
            );
        }
        
        return array(
            'can_delete' => true,
            'has_automatic_reduction' => $this->has_active_reductions( $order_id, $parrain_code )
        );
    }
    
    /**
     * Vérifie si un parrainage a des réductions automatiques actives
     * 
     * @param int $order_id ID de la commande
     * @param string $parrain_code Code du parrain
     * @return bool True si des réductions sont actives
     */
    private function has_active_reductions( int $order_id, string $parrain_code ): bool {
        if ( ! $this->pricing_manager ) {
            return false;
        }
        
        global $wpdb;
        
        $schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$schedule_table} 
             WHERE filleul_order_id = %d 
             AND parrain_subscription_id = %d 
             AND status IN ('pending', 'applied')",
            $order_id,
            intval( $parrain_code )
        ) );
        
        return $count > 0;
    }
} 