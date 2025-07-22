<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WebhookManager {
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    public function init() {
        add_filter( 'woocommerce_webhook_payload', array( $this, 'ajouter_subscription_metadata_webhook' ), 10, 4 );
        add_action( 'woocommerce_subscription_created_for_order', array( $this, 'stocker_subscription_id_commande' ), 10, 2 );
    }
    
    /**
     * Ajouter les informations complètes d'abonnement dans les webhooks WooCommerce
     * 
     * @param array $payload Données du webhook
     * @param string $resource Type de ressource (order, customer, etc.)
     * @param int $resource_id ID de la ressource
     * @param int $webhook_id ID du webhook
     * @return array Payload modifiée
     */
    public function ajouter_subscription_metadata_webhook( $payload, $resource, $resource_id, $webhook_id ) {
        
        // Ne traiter que les webhooks de type "order"
        if ( $resource !== 'order' ) {
            return $payload;
        }
        
        // Vérifier que WooCommerce Subscriptions est actif
        if ( ! class_exists( 'WC_Subscriptions' ) || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $this->logger->warning( 
                'WooCommerce Subscriptions non disponible pour webhook',
                array( 'webhook_id' => $webhook_id, 'resource_id' => $resource_id ),
                'webhook-subscriptions'
            );
            return $payload;
        }
        
        // Récupérer la commande
        $order = wc_get_order( $resource_id );
        if ( ! $order ) {
            $this->logger->error( 
                'Commande introuvable pour webhook',
                array( 'webhook_id' => $webhook_id, 'resource_id' => $resource_id ),
                'webhook-subscriptions'
            );
            return $payload;
        }
        
        // Chercher les abonnements liés à cette commande
        $subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
        
        if ( ! empty( $subscriptions ) ) {
            
            // Initialiser le tableau des métadonnées d'abonnement
            $subscription_metadata = array();
            
            foreach ( $subscriptions as $subscription ) {
                $subscription_data = array(
                    'subscription_id' => $subscription->get_id(),
                    'subscription_status' => $subscription->get_status(),
                    'subscription_start_date' => $subscription->get_date( 'start' ),
                    'subscription_next_payment' => $subscription->get_date( 'next_payment' ),
                    'subscription_trial_end' => $subscription->get_date( 'trial_end' ),
                    'subscription_end_date' => $subscription->get_date( 'end' ),
                    'subscription_billing_period' => $subscription->get_billing_period(),
                    'subscription_billing_interval' => $subscription->get_billing_interval(),
                    'subscription_total' => $subscription->get_total(),
                    'subscription_currency' => $subscription->get_currency(),
                );
                
                // Ajouter les informations produit de l'abonnement
                $subscription_items = array();
                foreach ( $subscription->get_items() as $item_id => $item ) {
                    $subscription_items[] = array(
                        'product_id' => $item->get_product_id(),
                        'variation_id' => $item->get_variation_id(),
                        'product_name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'subtotal' => $item->get_subtotal(),
                        'total' => $item->get_total(),
                    );
                }
                $subscription_data['subscription_items'] = $subscription_items;
                
                $subscription_metadata[] = $subscription_data;
            }
            
            // Ajouter les métadonnées d'abonnement au payload
            $payload['subscription_metadata'] = $subscription_metadata;
            
            // Ajouter aussi les IDs des abonnements directement pour un accès facile
            $payload['subscription_ids'] = wp_list_pluck( $subscriptions, 'id' );
            
            // Marquer que cette commande contient des abonnements
            $payload['has_subscriptions'] = true;
            $payload['subscriptions_count'] = count( $subscriptions );
            
            // Log pour debugging
            $this->logger->info( 
                sprintf( 
                    'Webhook ordre %d : %d abonnement(s) ajouté(s) - IDs: %s', 
                    $resource_id, 
                    count( $subscriptions ),
                    implode( ', ', wp_list_pluck( $subscriptions, 'id' ) )
                ),
                array( 
                    'webhook_id' => $webhook_id,
                    'order_id' => $resource_id,
                    'subscription_ids' => wp_list_pluck( $subscriptions, 'id' ),
                    'subscriptions_count' => count( $subscriptions )
                ),
                'webhook-subscriptions'
            );
            
        } else {
            // Aucun abonnement trouvé
            $payload['has_subscriptions'] = false;
            $payload['subscriptions_count'] = 0;
            
            $this->logger->debug( 
                sprintf( 'Webhook ordre %d : aucun abonnement trouvé', $resource_id ),
                array( 'webhook_id' => $webhook_id, 'order_id' => $resource_id ),
                'webhook-subscriptions'
            );
        }
        
        return $payload;
    }
    
    /**
     * Hook pour stocker les informations d'abonnement dans les métadonnées de la commande
     * (stockage pour référence future)
     */
    public function stocker_subscription_id_commande( $subscription, $order ) {
        
        if ( ! $subscription || ! $order ) {
            $this->logger->error( 
                'Paramètres invalides pour stocker subscription_id',
                array( 'subscription' => ! ! $subscription, 'order' => ! ! $order ),
                'webhook-subscriptions'
            );
            return;
        }
        
        try {
            // Stocker l'ID de l'abonnement dans les métadonnées de la commande
            $order->update_meta_data( '_subscription_id', $subscription->get_id() );
            $order->update_meta_data( '_subscription_status', $subscription->get_status() );
            $order->update_meta_data( '_has_subscription', 'yes' );
            
            // Sauvegarder les métadonnées
            $order->save_meta_data();
            
            // Log
            $this->logger->info( 
                sprintf( 'Commande %d : metadata subscription_id %d stockée', $order->get_id(), $subscription->get_id() ),
                array( 
                    'order_id' => $order->get_id(),
                    'subscription_id' => $subscription->get_id(),
                    'subscription_status' => $subscription->get_status()
                ),
                'webhook-subscriptions'
            );
            
        } catch ( \Exception $e ) {
            $this->logger->error( 
                sprintf( 'Erreur lors du stockage metadata subscription pour commande %d : %s', $order->get_id(), $e->getMessage() ),
                array( 
                    'order_id' => $order->get_id(),
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage()
                ),
                'webhook-subscriptions'
            );
        }
    }
    
    /**
     * Fonction utilitaire pour récupérer le subscription_id d'une commande
     * @param int $order_id ID de la commande
     * @return int|false subscription_id ou false si aucun
     */
    public function obtenir_subscription_id_commande( $order_id ) {
        
        // Méthode 1 : Via les métadonnées stockées
        $subscription_id = get_post_meta( $order_id, '_subscription_id', true );
        if ( $subscription_id ) {
            return intval( $subscription_id );
        }
        
        // Méthode 2 : Via WooCommerce Subscriptions
        if ( class_exists( 'WC_Subscriptions' ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $subscriptions = wcs_get_subscriptions_for_order( $order );
                if ( ! empty( $subscriptions ) ) {
                    $first_subscription = reset( $subscriptions );
                    return $first_subscription->get_id();
                }
            }
        }
        
        return false;
    }
} 