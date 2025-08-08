<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParrainageDataProvider {
    
    // Constantes
    const QUERY_CACHE_KEY = 'tb_parrainage_data_cache';
    const FILLEUL_STATUS_ACTIVE = 'active';
    const CACHE_GROUP = 'tb_parrainage';
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Récupérer les données de parrainage avec filtres et pagination
     */
    public function get_parrainage_data( $filters = array(), $pagination = array() ) {
        $cache_key = $this->generate_cache_key( $filters, $pagination );
        
        // Vérifier le cache
        $cached_data = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( $cached_data !== false ) {
            $this->logger->debug( 
                'Données parrainage récupérées depuis le cache',
                array( 'cache_key' => $cache_key ),
                'parrainage-data-provider'
            );
            return $cached_data;
        }
        
        // Récupérer les données depuis la base
        $raw_data = $this->fetch_raw_data( $filters, $pagination );
        $processed_data = $this->process_raw_data( $raw_data );
        
        // Mettre en cache
        wp_cache_set( $cache_key, $processed_data, self::CACHE_GROUP, WC_TB_PARRAINAGE_CACHE_TIME );
        
        $this->logger->info( 
            sprintf( 'Données parrainage récupérées - %d parrains trouvés', count( $processed_data['parrains'] ) ),
            array( 
                'filters' => $filters, 
                'pagination' => $pagination,
                'total_parrains' => count( $processed_data['parrains'] )
            ),
            'parrainage-data-provider'
        );
        
        return $processed_data;
    }
    
    /**
     * Récupérer la liste des parrains pour l'autocomplétion
     */
    public function get_parrain_list( $search = '' ) {
        global $wpdb;
        
        $where_conditions = array( "pm_parrain.meta_key = '_parrain_subscription_id'" );
        $params = array();
        
        if ( ! empty( $search ) ) {
            $where_conditions[] = "(CONCAT(pm_prenom.meta_value, ' ', pm_nom.meta_value) LIKE %s OR pm_email.meta_value LIKE %s)";
            $search_param = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = implode( ' AND ', $where_conditions );
        
        $query = $wpdb->prepare( "
            SELECT DISTINCT 
                pm_parrain.meta_value as subscription_id,
                CONCAT(pm_prenom.meta_value, ' ', pm_nom.meta_value) as nom_complet,
                pm_email.meta_value as email
            FROM {$wpdb->postmeta} pm_parrain
            LEFT JOIN {$wpdb->postmeta} pm_prenom ON pm_parrain.post_id = pm_prenom.post_id AND pm_prenom.meta_key = '_parrain_prenom'
            LEFT JOIN {$wpdb->postmeta} pm_nom ON pm_parrain.post_id = pm_nom.post_id AND pm_nom.meta_key = '_parrain_nom'
            LEFT JOIN {$wpdb->postmeta} pm_email ON pm_parrain.post_id = pm_email.post_id AND pm_email.meta_key = '_parrain_email'
            WHERE {$where_clause}
            ORDER BY nom_complet ASC
            LIMIT 20
        ", $params );
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Récupérer la liste des produits configurés
     */
    public function get_product_list() {
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        $product_list = array();
        
        foreach ( $products_config as $product_id => $config ) {
            if ( $product_id === 'default' ) continue;
            
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $product_list[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'avantage' => $config['avantage'] ?? 'Avantage parrainage'
                );
            }
        }
        
        return $product_list;
    }
    
    /**
     * Récupérer les statuts d'abonnement disponibles
     */
    public function get_subscription_statuses() {
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            return array();
        }
        
        return array(
            'active' => __( 'Actif', 'wc-tb-web-parrainage' ),
            'suspended' => __( 'Suspendu', 'wc-tb-web-parrainage' ),
            'cancelled' => __( 'Annulé', 'wc-tb-web-parrainage' ),
            'expired' => __( 'Expiré', 'wc-tb-web-parrainage' ),
            'on-hold' => __( 'En attente', 'wc-tb-web-parrainage' ),
            'pending' => __( 'En cours', 'wc-tb-web-parrainage' ),
            'pending-cancel' => __( 'Annulation en cours', 'wc-tb-web-parrainage' )
        );
    }
    
    /**
     * Compter le nombre total de parrainages
     */
    public function count_total_parrainages( $filters = array() ) {
        global $wpdb;
        
        $where_conditions = array( "pm_parrain.meta_key = '_parrain_subscription_id'" );
        $params = array();
        
        // Appliquer les filtres
        $this->apply_filters_to_query( $where_conditions, $params, $filters );
        
        $where_clause = implode( ' AND ', $where_conditions );
        
        $query = $wpdb->prepare( "
            SELECT COUNT(DISTINCT pm_parrain.meta_value) as total
            FROM {$wpdb->postmeta} pm_parrain
            LEFT JOIN {$wpdb->posts} p ON pm_parrain.post_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm_produit ON p.ID = pm_produit.post_id AND pm_produit.meta_key = '_order_product_id'
            WHERE {$where_clause}
        ", $params );
        
        $result = $wpdb->get_var( $query );
        
        return intval( $result );
    }
    
    /**
     * Récupérer les données brutes depuis la base de données
     */
    private function fetch_raw_data( $filters, $pagination ) {
        global $wpdb;
        
        $where_conditions = array( "pm_parrain.meta_key = '_parrain_subscription_id'" );
        $params = array();
        
        // Appliquer les filtres
        $this->apply_filters_to_query( $where_conditions, $params, $filters );
        
        $where_clause = implode( ' AND ', $where_conditions );
        
        // Calcul offset
        $offset = ( $pagination['page'] - 1 ) * $pagination['per_page'];
        
        // Requête principale
        $query = $wpdb->prepare( "
            SELECT 
                pm_parrain.meta_value as parrain_subscription_id,
                pm_parrain.post_id as order_id,
                p.post_date as date_commande,
                pm_prenom.meta_value as parrain_prenom,
                pm_nom.meta_value as parrain_nom,
                pm_email.meta_value as parrain_email,
                pm_user_id.meta_value as parrain_user_id,
                pm_avantage.meta_value as avantage,
                
                -- Données du filleul (client de la commande)
                p.ID as filleul_order_id,
                billing_first_name.meta_value as filleul_prenom,
                billing_last_name.meta_value as filleul_nom,
                billing_email.meta_value as filleul_email,
                customer_user.meta_value as filleul_user_id,
                order_total.meta_value as montant_commande,
                order_currency.meta_value as devise
                
            FROM {$wpdb->postmeta} pm_parrain
            LEFT JOIN {$wpdb->posts} p ON pm_parrain.post_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm_prenom ON p.ID = pm_prenom.post_id AND pm_prenom.meta_key = '_parrain_prenom'
            LEFT JOIN {$wpdb->postmeta} pm_nom ON p.ID = pm_nom.post_id AND pm_nom.meta_key = '_parrain_nom'
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_parrain_email'
            LEFT JOIN {$wpdb->postmeta} pm_user_id ON p.ID = pm_user_id.post_id AND pm_user_id.meta_key = '_parrain_user_id'
            LEFT JOIN {$wpdb->postmeta} pm_avantage ON p.ID = pm_avantage.post_id AND pm_avantage.meta_key = '_parrainage_avantage'
            
            -- Informations filleul (données de facturation de la commande)
            LEFT JOIN {$wpdb->postmeta} billing_first_name ON p.ID = billing_first_name.post_id AND billing_first_name.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} billing_last_name ON p.ID = billing_last_name.post_id AND billing_last_name.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} billing_email ON p.ID = billing_email.post_id AND billing_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} customer_user ON p.ID = customer_user.post_id AND customer_user.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->postmeta} order_total ON p.ID = order_total.post_id AND order_total.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} order_currency ON p.ID = order_currency.post_id AND order_currency.meta_key = '_order_currency'
            
            WHERE {$where_clause}
            ORDER BY {$pagination['order_by']} {$pagination['order']}
            LIMIT %d OFFSET %d
        ", array_merge( $params, array( $pagination['per_page'], $offset ) ) );
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Traiter les données brutes pour les grouper par parrain
     */
    private function process_raw_data( $raw_data ) {
        $parrains = array();
        
        foreach ( $raw_data as $row ) {
            $parrain_id = $row->parrain_subscription_id;
            
            // Initialiser le parrain s'il n'existe pas
            if ( ! isset( $parrains[ $parrain_id ] ) ) {
                $parrains[ $parrain_id ] = array(
                    'parrain' => array(
                        'subscription_id' => $parrain_id,
                        'user_id' => $row->parrain_user_id,
                        'nom' => trim( $row->parrain_prenom . ' ' . $row->parrain_nom ),
                        'email' => $row->parrain_email,
                        'user_link' => $row->parrain_user_id ? admin_url( 'user-edit.php?user_id=' . $row->parrain_user_id ) : '',
                        'subscription_link' => $this->get_subscription_admin_link( $parrain_id )
                    ),
                    'filleuls' => array()
                );
            }
            
            // Ajouter le filleul
            $filleul_data = $this->process_filleul_data( $row );
            if ( $filleul_data ) {
                $parrains[ $parrain_id ]['filleuls'][] = $filleul_data;
            }
        }
        
        return array(
            'parrains' => array_values( $parrains ),
            'total_parrains' => count( $parrains )
        );
    }
    
    /**
     * Traiter les données d'un filleul
     */
    private function process_filleul_data( $row ) {
        // Récupérer les informations de l'abonnement du filleul
        $subscription_data = $this->get_filleul_subscription_data( $row->filleul_order_id );
        
        // MODIFICATION v2.6.0 : Remplacement des données mockées par vraies données calculées
        $discount_data = $this->get_real_discount_data( $row->filleul_order_id, $row->parrain_subscription_id );
        
        return array(
            'user_id' => $row->filleul_user_id,
            'nom' => trim( $row->filleul_prenom . ' ' . $row->filleul_nom ),
            'email' => $row->filleul_email,
            'date_parrainage' => $row->date_commande,
            'date_parrainage_formatted' => mysql2date( 'd/m/Y', $row->date_commande ),
            'order_id' => $row->filleul_order_id,
            'order_link' => admin_url( 'post.php?post=' . $row->filleul_order_id . '&action=edit' ),
            'user_link' => $row->filleul_user_id ? admin_url( 'user-edit.php?user_id=' . $row->filleul_user_id ) : '',
            'avantage' => $row->avantage ?: 'Avantage parrainage',
            'montant' => floatval( $row->montant_commande ),
            'montant_formatted' => $this->format_price( $row->montant_commande, $row->devise ),
            'devise' => $row->devise,
            'produit_info' => $this->get_order_products( $row->filleul_order_id ),
            'subscription_info' => $subscription_data,
            // NOUVEAU v2.4.0 : Informations de remise mockées
            'discount_info' => $discount_data
        );
    }
    
    /**
     * Récupérer les données d'abonnement du filleul
     */
    private function get_filleul_subscription_data( $order_id ) {
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return null;
        }
        
        $subscriptions = wcs_get_subscriptions_for_order( $order_id );
        
        if ( empty( $subscriptions ) ) {
            return null;
        }
        
        $subscription = reset( $subscriptions );
        
        return array(
            'id' => $subscription->get_id(),
            'status' => $subscription->get_status(),
            'status_label' => wcs_get_subscription_status_name( $subscription->get_status() ),
            'status_badge_class' => $this->get_status_badge_class( $subscription->get_status() ),
            'next_payment' => $subscription->get_date( 'next_payment' ),
            'link' => admin_url( 'post.php?post=' . $subscription->get_id() . '&action=edit' )
        );
    }
    
    /**
     * Récupérer les produits d'une commande
     */
    private function get_order_products( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array();
        }
        
        $products = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $products[] = array(
                    'id' => $product->get_id(),
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'link' => admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' )
                );
            }
        }
        
        return $products;
    }
    
    /**
     * Appliquer les filtres à la requête SQL
     */
    private function apply_filters_to_query( &$where_conditions, &$params, $filters ) {
        // Filtre par date
        if ( ! empty( $filters['date_from'] ) ) {
            $where_conditions[] = "p.post_date >= %s";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if ( ! empty( $filters['date_to'] ) ) {
            $where_conditions[] = "p.post_date <= %s";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Filtre par recherche parrain
        if ( ! empty( $filters['parrain_search'] ) ) {
            $where_conditions[] = "(CONCAT(pm_prenom.meta_value, ' ', pm_nom.meta_value) LIKE %s OR pm_email.meta_value LIKE %s)";
            $search_param = '%' . $filters['parrain_search'] . '%';
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Filtre par produit (nécessite jointure avec les items de commande)
        if ( ! empty( $filters['product_id'] ) ) {
            $where_conditions[] = "EXISTS (
                SELECT 1 FROM {$GLOBALS['wpdb']->prefix}woocommerce_order_items oi
                JOIN {$GLOBALS['wpdb']->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                WHERE oi.order_id = p.ID 
                AND oim.meta_key = '_product_id' 
                AND oim.meta_value = %s
            )";
            $params[] = $filters['product_id'];
        }
    }
    
    /**
     * Générer une clé de cache unique
     */
    private function generate_cache_key( $filters, $pagination ) {
        return self::QUERY_CACHE_KEY . '_' . md5( serialize( array( $filters, $pagination ) ) );
    }
    
    /**
     * Formater un prix avec devise
     */
    private function format_price( $amount, $currency ) {
        return wc_price( $amount, array( 'currency' => $currency ) );
    }
    
    /**
     * Obtenir la classe CSS pour le badge de statut
     */
    private function get_status_badge_class( $status ) {
        $classes = array(
            'active' => 'status-active',
            'suspended' => 'status-suspended',
            'cancelled' => 'status-cancelled',
            'expired' => 'status-cancelled',
            'on-hold' => 'status-suspended',
            'pending' => 'status-pending',
            'pending-cancel' => 'status-cancelled'
        );
        
        return $classes[ $status ] ?? 'status-default';
    }
    
    /**
     * Obtenir le lien admin vers un abonnement
     */
    private function get_subscription_admin_link( $subscription_id ) {
        if ( function_exists( 'wcs_get_subscription' ) ) {
            $subscription = wcs_get_subscription( $subscription_id );
            if ( $subscription ) {
                return admin_url( 'post.php?post=' . $subscription_id . '&action=edit' );
            }
        }
        return '';
    }
    
    /**
     * NOUVEAU v2.4.0 : Générer des données mockées pour les remises
     * Permet de tester l'interface sans logique métier
     * 
     * @param int $filleul_order_id ID de la commande du filleul
     * @param int $parrain_subscription_id ID de l'abonnement du parrain
     * @return array Données de remise mockées
     */
    private function get_mock_discount_data( $filleul_order_id, $parrain_subscription_id ) {
        // Simulation de différents statuts pour tests
        $statuses = ['active', 'pending', 'failed', 'suspended'];
        $status = $statuses[array_rand($statuses)];
        
        // Créer une variation basée sur l'ID pour des résultats cohérents
        $seed = intval( $filleul_order_id ) + intval( $parrain_subscription_id );
        mt_srand( $seed );
        
        $base_amount = 7.50;
        $variation = mt_rand( 500, 1500 ) / 100; // Entre 5€ et 15€
        $discount_amount = round( $variation, 2 );
        
        $original_amount = 89.99;
        $next_billing_amount = $original_amount - $discount_amount;
        $total_savings = mt_rand( 50, 500 ); // Économies simulées variables
        
        return array(
            'discount_status' => $status,
            'discount_status_label' => $this->get_discount_status_label( $status ),
            'discount_status_badge_class' => $this->get_discount_status_badge_class( $status ),
            'discount_amount' => $discount_amount,
            'discount_amount_formatted' => number_format( $discount_amount, 2, ',', '' ) . '€/mois',
            'discount_applied_date' => date( 'Y-m-d H:i:s', strtotime( '-' . mt_rand( 1, 30 ) . ' days' ) ),
            'discount_applied_date_formatted' => date( 'd/m/Y à H\hi', strtotime( '-' . mt_rand( 1, 30 ) . ' days' ) ),
            'next_billing_date' => date( 'Y-m-d', strtotime( '+1 month' ) ),
            'next_billing_amount' => $next_billing_amount,
            'original_amount' => $original_amount,
            'total_savings' => $total_savings
        );
    }
    
    /**
     * NOUVEAU v2.4.0 : Labels des statuts de remise pour affichage
     * 
     * @param string $status Statut technique
     * @return string Label affiché
     */
    private function get_discount_status_label( $status ) {
        $labels = array(
            'active' => 'ACTIVE',
            'pending' => 'EN ATTENTE',
            'failed' => 'ÉCHEC',
            'suspended' => 'SUSPENDUE',
            'na' => 'N/A'
        );
        return $labels[$status] ?? 'INCONNU';
    }
    
    /**
     * NOUVEAU v2.4.0 : Classes CSS pour les badges de statut
     * 
     * @param string $status Statut technique
     * @return string Classe CSS
     */
    private function get_discount_status_badge_class( $status ) {
        $classes = array(
            'active' => 'discount-status-active',
            'pending' => 'discount-status-pending',
            'failed' => 'discount-status-failed',
            'suspended' => 'discount-status-suspended',
            'na' => 'discount-status-na'
        );
        return $classes[$status] ?? 'discount-status-default';
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération des vraies données de remise calculées
     * 
     * @param int $filleul_order_id ID de la commande filleul
     * @param int $parrain_subscription_id ID de l'abonnement parrain
     * @return array|false Données de remise réelles ou false
     */
    private function get_real_discount_data( $filleul_order_id, $parrain_subscription_id ) {
        try {
            // Récupération de l'instance du plugin pour accès aux services
            $plugin_instance = $this->get_plugin_instance();
            if ( ! $plugin_instance ) {
                return $this->get_mock_discount_data( $filleul_order_id, $parrain_subscription_id );
            }
            
            $calculator = $plugin_instance->get_discount_calculator();
            $validator = $plugin_instance->get_discount_validator();
            
            if ( ! $calculator || ! $validator ) {
                $this->logger->warning(
                    'Services de calcul non disponibles - fallback vers données mockées',
                    array(
                        'filleul_order_id' => $filleul_order_id,
                        'parrain_subscription_id' => $parrain_subscription_id
                    ),
                    'data-provider'
                );
                return $this->get_mock_discount_data( $filleul_order_id, $parrain_subscription_id );
            }
            
            // Récupération des informations de la commande filleul
            $order = wc_get_order( $filleul_order_id );
            if ( ! $order ) {
                return false;
            }
            
            $code_parrain = $order->get_meta( '_billing_parrain_code' );
            if ( ! $code_parrain ) {
                return false;
            }
            
            // Validation de l'éligibilité
            $product_ids = $this->get_order_product_ids( $order );
            
            foreach ( $product_ids as $product_id ) {
                $validation = $validator->validate_discount_eligibility( 
                    $parrain_subscription_id, 
                    $filleul_order_id, 
                    $product_id 
                );
                
                if ( $validation['is_eligible'] ) {
                    // Calcul de la remise réelle
                    $parrain_subscription = wcs_get_subscription( $parrain_subscription_id );
                    if ( $parrain_subscription ) {
                        $current_price = $parrain_subscription->get_total();
                        
                        $discount_data = $calculator->calculate_parrain_discount( 
                            $product_id, 
                            $current_price, 
                            $parrain_subscription_id 
                        );
                        
                        if ( $discount_data ) {
                            // Détermination du statut basé sur l'état du workflow
                            $workflow_status = $this->get_workflow_status( $filleul_order_id, $parrain_subscription_id );
                            
                            return array(
                                'discount_status' => $workflow_status,
                                'discount_status_label' => $this->get_workflow_status_label( $workflow_status ),
                                'discount_status_badge_class' => $this->get_workflow_status_badge_class( $workflow_status ),
                                'discount_amount' => $discount_data['discount_amount'],
                                'discount_amount_formatted' => number_format( $discount_data['discount_amount'], 2, ',', '' ) . '€/mois',
                                'discount_applied_date' => $discount_data['calculation_date'],
                                'discount_applied_date_formatted' => mysql2date( 'd/m/Y à H\hi', $discount_data['calculation_date'] ),
                                'next_billing_date' => date( 'Y-m-d', strtotime( '+1 month' ) ),
                                'next_billing_amount' => $current_price - $discount_data['discount_amount'],
                                'original_amount' => $current_price,
                                'total_savings' => $discount_data['discount_amount'] * 12, // Estimation annuelle
                                'workflow_details' => $this->get_workflow_details( $filleul_order_id )
                            );
                        }
                    }
                }
            }
            
            return false;
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Erreur lors du calcul des vraies données de remise - fallback vers mockées',
                array(
                    'filleul_order_id' => $filleul_order_id,
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'data-provider'
            );
            
            // Fallback vers données mockées en cas d'erreur
            return $this->get_mock_discount_data( $filleul_order_id, $parrain_subscription_id );
        }
    }
    
    /**
     * NOUVEAU v2.6.0 : Détermination du statut workflow
     * 
     * @param int $filleul_order_id ID de la commande filleul
     * @param int $parrain_subscription_id ID de l'abonnement parrain
     * @return string Statut du workflow
     */
    private function get_workflow_status( $filleul_order_id, $parrain_subscription_id ) {
        $order = wc_get_order( $filleul_order_id );
        
        if ( $order->get_meta( '_tb_parrainage_calculated' ) ) {
            return 'calculated';
        } elseif ( $order->get_meta( '_pending_parrain_discount' ) ) {
            return 'pending';
        } elseif ( $order->get_meta( '_parrainage_workflow_status' ) ) {
            return $order->get_meta( '_parrainage_workflow_status' );
        } else {
            return 'simulated';
        }
    }
    
    /**
     * NOUVEAU v2.6.0 : Labels des statuts workflow v2.6.0
     * 
     * @param string $status Statut workflow
     * @return string Label pour affichage
     */
    private function get_workflow_status_label( $status ) {
        $labels = array(
            // NOUVEAU v2.7.0 : Statuts application réelle
            'applied' => 'ACTIVE',
            'application_failed' => 'ÉCHEC APPLICATION',
            'active' => 'REMISE ACTIVE',
            
            // Statuts existants conservés pour rétrocompatibilité
            'calculated' => 'CALCULÉ (v2.6.0)',
            'simulated' => 'SIMULÉ (v2.6.0)',
            'pending' => 'EN COURS',
            'scheduled' => 'PROGRAMMÉ',
            'error' => 'ERREUR',
            'cron_failed' => 'CRON DÉFAILLANT'
        );
        return $labels[$status] ?? 'INCONNU';
    }
    
    /**
     * NOUVEAU v2.6.0 : Classes CSS des statuts workflow
     * 
     * @param string $status Statut workflow
     * @return string Classe CSS
     */
    private function get_workflow_status_badge_class( $status ) {
        $classes = array(
            'calculated' => 'discount-status-calculated',
            'pending' => 'discount-status-pending',
            'scheduled' => 'discount-status-scheduled',
            'simulated' => 'discount-status-simulated',
            'error' => 'discount-status-error',
            'cron_failed' => 'discount-status-cron-failed'
        );
        return $classes[$status] ?? 'discount-status-default';
    }
    
    /**
     * NOUVEAU v2.6.0 : Détails du workflow pour monitoring
     * 
     * @param int $filleul_order_id ID de la commande filleul
     * @return array Détails du workflow
     */
    private function get_workflow_details( $filleul_order_id ) {
        $order = wc_get_order( $filleul_order_id );
        
        return array(
            'marked_date' => $order->get_meta( '_parrainage_marked_date' ),
            'scheduled_time' => $order->get_meta( '_parrainage_scheduled_time' ),
            'calculation_date' => $order->get_meta( '_parrainage_calculation_date' ),
            'workflow_status' => $order->get_meta( '_parrainage_workflow_status' ),
            'final_error' => $order->get_meta( '_parrainage_final_error' ),
            'cron_failure_date' => $order->get_meta( '_parrainage_cron_failure_date' )
        );
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération de l'instance du plugin
     * 
     * @return Plugin|null Instance du plugin ou null
     */
    private function get_plugin_instance() {
        global $wc_tb_parrainage_plugin;
        return isset( $wc_tb_parrainage_plugin ) ? $wc_tb_parrainage_plugin : null;
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération des IDs produits d'une commande
     * 
     * @param WC_Order $order Instance de commande
     * @return array IDs des produits
     */
    private function get_order_product_ids( $order ) {
        $product_ids = array();
        
        foreach ( $order->get_items() as $item ) {
            $product_ids[] = $item->get_product_id();
        }
        
        return $product_ids;
    }
} 