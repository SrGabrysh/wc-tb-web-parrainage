<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WebhookManager {
    
    private $logger;
    private $subscription_pricing_manager;
    
    public function __construct( $logger, $subscription_pricing_manager = null ) {
        $this->logger = $logger;
        $this->subscription_pricing_manager = $subscription_pricing_manager;
    }
    
    public function init() {
        \add_filter( 'woocommerce_webhook_payload', array( $this, 'ajouter_subscription_metadata_webhook' ), 10, 4 );
        \add_action( 'woocommerce_subscription_created_for_order', array( $this, 'stocker_subscription_id_commande' ), 10, 2 );
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
        $order = \wc_get_order( $resource_id );
        if ( ! $order ) {
            $this->logger->error( 
                'Commande introuvable pour webhook',
                array( 'webhook_id' => $webhook_id, 'resource_id' => $resource_id ),
                'webhook-subscriptions'
            );
            return $payload;
        }
        
        // Chercher les abonnements liés à cette commande
        $subscriptions = \wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
        
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
        
        // NOUVEAU : Ajouter l'objet parrainage unifié (v2.3.0 - source unique)
        $parrainage_unifie = $this->construire_objet_parrainage( $order, $payload );
        if ( $parrainage_unifie ) {
            $payload['parrainage'] = $parrainage_unifie;
            
            // Log pour la nouvelle structure sans doublons
            $this->logger->info( 
                sprintf( 'Webhook ordre %d : objet parrainage unifié ajouté (v2.3.0 - suppression doublons)', $resource_id ),
                array( 
                    'webhook_id' => $webhook_id,
                    'order_id' => $resource_id,
                    'version' => '2.3.0',
                    'doublons_supprimes' => true
                ),
                'webhook-parrainage-unifie'
            );
        }
        
        return $payload;
    }
    
    /**
     * Construit l'objet parrainage unifié pour le payload webhook
     * VERSION 2.3.0 : Source unique de vérité, suppression des doublons
     * 
     * @param \WC_Order $order Commande WooCommerce
     * @param array $payload Payload webhook actuel (pour récupérer subscription_metadata)
     * @return array|null Objet parrainage structuré ou null si pas de parrainage
     */
    private function construire_objet_parrainage( $order, $payload = array() ) {
        if ( ! $order ) {
            return null;
        }
        
        // Vérifier si la commande contient un code parrain
        $code_parrain = $order->get_meta( '_billing_parrain_code' );
        if ( empty( $code_parrain ) ) {
            return null;
        }
        
        // Construction de l'objet parrainage unifié
        $parrainage = array(
            'version' => '2.3.0', // Marquer la nouvelle version
            'actif' => true
        );
        
        // Section FILLEUL (côté réception)
        $parrainage['filleul'] = array(
            'code_parrain_saisi' => \sanitize_text_field( $code_parrain ),
            'avantage' => \sanitize_text_field( $order->get_meta( '_parrainage_avantage' ) ?: '' )
        );
        
        // Section PARRAIN (côté attribution)
        $parrainage['parrain'] = array(
            'user_id' => intval( $order->get_meta( '_parrain_user_id' ) ?: 0 ),
            'subscription_id' => \sanitize_text_field( $order->get_meta( '_parrain_subscription_id' ) ?: '' ),
            'email' => \sanitize_email( $order->get_meta( '_parrain_email' ) ?: '' ),
            'nom_complet' => \sanitize_text_field( $order->get_meta( '_parrain_nom_complet' ) ?: '' ),
            'prenom' => \sanitize_text_field( $order->get_meta( '_parrain_prenom' ) ?: '' )
        );
        
        // Section DATES (côté temporalité) - SOURCE UNIQUE
        $date_debut = $order->get_meta( '_parrainage_date_debut' );
        $date_fin = $order->get_meta( '_parrainage_date_fin_remise' );
        $jours_marge = intval( $order->get_meta( '_parrainage_jours_marge' ) ?: 2 );
        
        $parrainage['dates'] = array(
            'debut_parrainage' => $date_debut ?: '',
            'fin_remise_parrainage' => $date_fin ?: '',
            'debut_parrainage_formatted' => $date_debut ? date( 'd-m-Y', strtotime( $date_debut ) ) : '',
            'fin_remise_parrainage_formatted' => $date_fin ? date( 'd-m-Y', strtotime( $date_fin ) ) : '',
            'jours_marge' => $jours_marge,
            'periode_remise_mois' => 12
        );
        
        // Section TARIFICATION COMPLÈTE (prix, fréquence et remise) - SOURCE UNIQUE
        $tarification_info = $this->get_infos_tarification_configuree( $order->get_id() );
        
        $parrainage['tarification'] = array(
            'prix_avant_remise' => $tarification_info['prix_avant_remise'],
            'frequence_paiement' => $tarification_info['frequence_paiement'],
            'remise_parrain' => array(
                'montant' => $tarification_info['remise_parrain_montant'],
                'unite' => $tarification_info['remise_parrain_unite']
            )
        );
        
        // Section STATUT (état de la remise selon les abonnements)
        if ( isset( $payload['subscription_metadata'] ) && !empty( $payload['subscription_metadata'] ) ) {
            $first_subscription = $payload['subscription_metadata'][0];
            $is_active = in_array( $first_subscription['subscription_status'], array( 'active', 'wc-active' ) );
            
            $parrainage['statut'] = array(
                'remise_active' => $is_active,
                'subscription_concernee' => $first_subscription['subscription_id'],
                'message' => $is_active ? 'Remise parrain calculée et active' : 'Remise parrain en attente'
            );
        } else {
            $parrainage['statut'] = array(
                'remise_active' => false,
                'subscription_concernee' => null,
                'message' => 'Remise parrain en attente - abonnement pas encore actif'
            );
        }
        
        return $parrainage;
    }

    /**
     * Récupère les informations tarifaires complètes pour un produit donné
     * 
     * @param int $order_id ID de la commande
     * @return array Informations tarifaires enrichies
     */
    private function get_infos_tarification_configuree( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array(
                'remise_parrain_montant' => 0.00,
                'remise_parrain_unite' => 'EUR',
                'prix_avant_remise' => 0.00,
                'frequence_paiement' => 'mensuel'
            );
        }
        
        // Récupérer les produits de la commande
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        $config_trouvee = null;
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            // Vérifier si le produit a une configuration spécifique
            if ( isset( $products_config[ $product_id ] ) ) {
                $config_trouvee = $products_config[ $product_id ];
                break;
            }
        }
        
        // Si aucune config spécifique, utiliser la config par défaut
        if ( ! $config_trouvee && isset( $products_config['default'] ) ) {
            $config_trouvee = $products_config['default'];
        }
        
        // Valeurs par défaut si aucune configuration
        if ( ! $config_trouvee ) {
            $config_trouvee = array(
                'remise_parrain' => 0.00,
                'prix_standard' => 0.00,
                'frequence_paiement' => 'mensuel'
            );
        }
        
        return array(
            'remise_parrain_montant' => round( floatval( $config_trouvee['remise_parrain'] ?? 0.00 ), 2 ),
            'remise_parrain_unite' => 'EUR',
            'prix_avant_remise' => round( floatval( $config_trouvee['prix_standard'] ?? 0.00 ), 2 ),
            'frequence_paiement' => sanitize_text_field( $config_trouvee['frequence_paiement'] ?? 'mensuel' )
        );
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