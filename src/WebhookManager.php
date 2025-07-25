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
            
            // Ajouter les informations de tarification parrainage si disponibles
            if ( $this->subscription_pricing_manager ) {
                $infos_tarification = $this->subscription_pricing_manager->obtenir_infos_tarification_parrainage( $resource_id );
                if ( $infos_tarification ) {
                    $payload['parrainage_pricing'] = $infos_tarification;
                    
                    // Log spécifique pour les données de tarification
                    $this->logger->info( 
                        sprintf( 'Webhook ordre %d : données de tarification parrainage ajoutées (avec abonnements)', $resource_id ),
                        array( 
                            'webhook_id' => $webhook_id,
                            'order_id' => $resource_id,
                            'pricing_data' => $infos_tarification
                        ),
                        'webhook-subscriptions'
                    );
                }
            }
            
            // NOUVEAU : Ajouter les informations de remise parrain si abonnements actifs
            if ( isset( $payload['parrainage_pricing'] ) && isset( $payload['subscription_metadata'] ) && !empty( $payload['subscription_metadata'] ) ) {
                // Traiter tous les abonnements présents dans la commande
                foreach ( $payload['subscription_metadata'] as $subscription_index => $subscription_data ) {
                    // Vérifier si l'abonnement est actif et possède un montant HT
                    if ( isset( $subscription_data['subscription_status'] ) && 
                         in_array( $subscription_data['subscription_status'], array( 'active', 'wc-active' ) ) && 
                         isset( $subscription_data['subscription_items'][0]['subtotal'] ) ) {
                        
                        // Récupérer le montant HT du premier article de l'abonnement
                        $montant_ht = floatval( $subscription_data['subscription_items'][0]['subtotal'] );
                        
                        // Calculer la remise parrain
                        $remise_info = $this->calculer_remise_parrain( $montant_ht );
                        
                        // Ajouter ces informations au payload dans la section parrainage_pricing
                        $payload['parrainage_pricing']['remise_parrain_montant'] = $remise_info['montant'];
                        $payload['parrainage_pricing']['remise_parrain_pourcentage'] = $remise_info['pourcentage'];
                        $payload['parrainage_pricing']['remise_parrain_base_ht'] = $remise_info['base_ht'];
                        $payload['parrainage_pricing']['remise_parrain_unite'] = $remise_info['unite'];
                        
                        // Si plusieurs abonnements, préciser l'ID de l'abonnement concerné
                        if ( count( $payload['subscription_metadata'] ) > 1 ) {
                            $payload['parrainage_pricing']['remise_parrain_subscription_id'] = $subscription_data['subscription_id'];
                        }
                        
                        // Log spécifique pour cette nouvelle fonctionnalité
                        $this->logger->info( 
                            sprintf( 'Webhook ordre %d : remise parrain calculée (%.2f€)', $resource_id, $remise_info['montant'] ),
                            array( 
                                'webhook_id' => $webhook_id,
                                'order_id' => $resource_id,
                                'subscription_id' => $subscription_data['subscription_id'],
                                'reduction_amount' => $remise_info['montant'],
                                'reduction_percentage' => $remise_info['pourcentage'],
                                'montant_ht' => $remise_info['base_ht']
                            ),
                            'webhook-parrain-remise'
                        );
                        
                        // On sort de la boucle après avoir traité le premier abonnement actif
                        break;
                    }
                }
            }
            
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
        
        // Ajouter les informations de tarification parrainage même sans abonnements
        // (car l'abonnement peut être créé après le webhook de commande)
        if ( $this->subscription_pricing_manager ) {
            $infos_tarification = $this->subscription_pricing_manager->obtenir_infos_tarification_parrainage( $resource_id );
            if ( $infos_tarification ) {
                // ✅ CORRECTION : Merge au lieu d'écrasement pour conserver les enrichissements de remise parrain
                if ( !isset( $payload['parrainage_pricing'] ) ) {
                    $payload['parrainage_pricing'] = $infos_tarification;
                } else {
                    $payload['parrainage_pricing'] = array_merge( $infos_tarification, $payload['parrainage_pricing'] );
                }
                
                // NOUVEAU : Ajouter l'indication que la remise sera calculée ultérieurement
                if ( !isset( $payload['subscription_metadata'] ) || empty( $payload['subscription_metadata'] ) ) {
                    // Indiquer que la remise sera calculée ultérieurement quand l'abonnement sera actif
                    $payload['parrainage_pricing']['remise_parrain_status'] = 'pending';
                    $payload['parrainage_pricing']['remise_parrain_message'] = 'La remise sera calculée lorsque l\'abonnement du filleul sera actif';
                    
                    // Log pour traçabilité
                    $this->logger->info( 
                        sprintf( 'Webhook ordre %d : remise parrain en attente (abonnement non encore actif)', $resource_id ),
                        array( 
                            'webhook_id' => $webhook_id,
                            'order_id' => $resource_id,
                        ),
                        'webhook-parrain-remise'
                    );
                }
                
                // Log spécifique pour les données de tarification
                $this->logger->info( 
                    sprintf( 'Webhook ordre %d : données de tarification parrainage ajoutées (sans abonnements)', $resource_id ),
                    array( 
                        'webhook_id' => $webhook_id,
                        'order_id' => $resource_id,
                        'pricing_data' => $infos_tarification
                    ),
                    'webhook-subscriptions'
                );
            }
        }
        
        return $payload;
    }
    
    /**
     * Calcule le montant de la remise parrain pour un abonnement donné
     * 
     * @param float $montant_ht Montant HT de l'abonnement du filleul
     * @return array Informations de remise (montant, pourcentage, base HT)
     */
    private function calculer_remise_parrain( $montant_ht ) {
        // Utiliser la constante définie pour le pourcentage de remise
        $reduction_percentage = defined( 'WC_TB_PARRAINAGE_REDUCTION_PERCENTAGE' ) 
            ? WC_TB_PARRAINAGE_REDUCTION_PERCENTAGE 
            : 25; // Valeur par défaut si la constante n'est pas définie
        
        // Calculer le montant de la remise (25% du montant HT)
        $reduction_amount = ( $montant_ht * $reduction_percentage ) / 100;
        
        // Arrondir à 2 décimales pour une précision monétaire standard
        $reduction_amount = round( $reduction_amount, 2 );
        $montant_ht = round( $montant_ht, 2 );
        
        return array(
            'montant' => $reduction_amount,
            'pourcentage' => $reduction_percentage,
            'base_ht' => $montant_ht,
            'unite' => 'EUR'
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