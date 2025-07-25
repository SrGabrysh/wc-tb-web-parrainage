<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de récupération des données de parrainage côté client
 * 
 * Responsabilité unique : Récupération et formatage des données de parrainage
 * pour l'affichage dans l'onglet "Mes parrainages" côté client
 * 
 * @package TBWeb\WCParrainage
 * @since 1.3.0
 */
class MyAccountDataProvider {
    
    // Constantes pour le cache (éviter magic numbers)
    const CACHE_KEY_PREFIX = 'tb_parrainage_user_';
    const CACHE_DURATION = 300; // 5 minutes
    
    // Constantes pour le formatage
    const EMAIL_MASK_CHAR = '*';
    const DATE_FORMAT_DISPLAY = 'd/m/Y';
    const AMOUNT_FORMAT = '%.2f€/mois';
    
    // Labels des statuts d'abonnement (v2.0.2 - HUMANISÉS)
    const STATUS_LABELS = [
        'active' => 'En cours',
        'wc-active' => 'En cours',
        'on-hold' => 'Suspendu',
        'wc-on-hold' => 'Suspendu',
        'cancelled' => 'Annulé',
        'wc-cancelled' => 'Annulé',
        'expired' => 'Expiré',
        'wc-expired' => 'Expiré',
        'pending-cancel' => 'Annulation programmée',
        'wc-pending-cancel' => 'Annulation programmée',
        'switched' => 'Modifié',
        'wc-switched' => 'Modifié'
    ];
    
    /**
     * @var Logger Instance du système de logs
     */
    private $logger;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du système de logs
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Récupère l'ID d'abonnement d'un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @return int|null ID de l'abonnement ou null si aucun
     */
    public function get_user_subscription_id( $user_id ) {
        if ( ! \function_exists( 'wcs_get_users_subscriptions' ) ) {
            return null;
        }
        
        $subscriptions = \wcs_get_users_subscriptions( $user_id );
        
        if ( empty( $subscriptions ) ) {
            return null;
        }
        
        // Retourner l'ID du premier abonnement actif trouvé
        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->get_status() === 'active' ) {
                return $subscription->get_id();
            }
        }
        
        // Si aucun actif, retourner le premier abonnement
        $first_subscription = reset( $subscriptions );
        return $first_subscription ? $first_subscription->get_id() : null;
    }
    
    /**
     * Récupère les données de parrainage d'un utilisateur
     * 
     * @param int $user_subscription_id ID de l'abonnement de l'utilisateur
     * @param int $limit Limite du nombre de parrainages à récupérer
     * @return array Tableau des parrainages formatés
     */
    public function get_user_parrainages( $user_subscription_id, $limit = WC_TB_PARRAINAGE_LIMIT_DISPLAY ) {
        // Vérifier le cache d'abord
        $cache_key = self::CACHE_KEY_PREFIX . $user_subscription_id;
        $cached_data = \get_transient( $cache_key );
        
        if ( $cached_data !== false ) {
            $this->logger->info( 'Données de parrainage récupérées depuis le cache', array(
                'subscription_id' => $user_subscription_id,
                'cache_key' => $cache_key
            ) );
            return $cached_data;
        }
        
        // Récupérer les données depuis la base
        $raw_data = $this->query_parrainages_data( $user_subscription_id, $limit );
        
        // Traiter et formater les données
        $formatted_data = array();
        foreach ( $raw_data as $row ) {
            $formatted_data[] = $this->process_parrainage_row( $row );
        }
        
        // Mettre en cache
        \set_transient( $cache_key, $formatted_data, self::CACHE_DURATION );
        
        $this->logger->info( 'Données de parrainage récupérées et mises en cache', array(
            'subscription_id' => $user_subscription_id,
            'count' => count( $formatted_data ),
            'cache_duration' => self::CACHE_DURATION
        ) );
        
        return $formatted_data;
    }
    
    /**
     * Formate un email pour le masquer partiellement
     * 
     * @param string $email Email à masquer
     * @return string Email masqué
     */
    public function format_filleul_email( $email ) {
        if ( empty( $email ) || ! \is_email( $email ) ) {
            return '';
        }
        
        $parts = explode( '@', $email );
        if ( count( $parts ) !== 2 ) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        // Masquer le nom d'utilisateur selon sa longueur
        $length = strlen( $username );
        if ( $length <= 2 ) {
            $masked_username = str_repeat( self::EMAIL_MASK_CHAR, $length );
        } else {
            $visible_chars = min( 2, floor( $length / 3 ) );
            $masked_chars = $length - $visible_chars;
            $masked_username = substr( $username, 0, $visible_chars ) . str_repeat( self::EMAIL_MASK_CHAR, $masked_chars );
        }
        
        return $masked_username . '@' . $domain;
    }
    
    /**
     * Récupère le label humanisé d'un statut d'abonnement (v2.0.2)
     * 
     * @param string $status Statut technique de l'abonnement
     * @return string Label affiché pour ce statut
     */
    private function get_subscription_status_label( $status ) {
        return self::STATUS_LABELS[ $status ] ?? ucfirst( $status );
    }
    
    /**
     * Formate une date de parrainage pour l'affichage
     * 
     * @param string $date Date au format MySQL
     * @return string Date formatée pour l'affichage
     */
    public function format_date_parrainage( $date ) {
        if ( empty( $date ) ) {
            return '';
        }
        
        $timestamp = \strtotime( $date );
        if ( ! $timestamp ) {
            return $date;
        }
        
        return \date( self::DATE_FORMAT_DISPLAY, $timestamp );
    }
    
    /**
     * Formate un montant HT pour l'affichage (v2.0.2)
     * 
     * @param float $amount_ht Montant HT à formater
     * @return string Montant formaté pour l'affichage
     */
    private function format_montant_ht( $amount_ht ) {
        if ( ! is_numeric( $amount_ht ) ) {
            return '';
        }
        return sprintf( '%.2f€ HT/mois', floatval( $amount_ht ) );
    }
    
    /**
     * Récupère le montant de la remise du parrain selon la configuration produit
     * 
     * @param int $order_id ID de la commande du filleul
     * @param string $subscription_status Statut de l'abonnement du filleul
     * @return string Montant de la remise formaté ou statut
     */
    private function get_parrain_reduction( $order_id, $subscription_status ) {
        // Vérifier si l'abonnement est actif
        if ( ! in_array( $subscription_status, ['active', 'wc-active'] ) ) {
            return 'Non applicable';
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return '0,00€';
        }
        
        // Récupérer la configuration des produits
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        $remise_montant = 0.00;
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            if ( isset( $products_config[ $product_id ]['remise_parrain'] ) ) {
                $remise_montant = floatval( $products_config[ $product_id ]['remise_parrain'] );
                break;
            }
        }
        
        // Si aucune remise spécifique, vérifier la config par défaut
        if ( $remise_montant == 0.00 && isset( $products_config['default']['remise_parrain'] ) ) {
            $remise_montant = floatval( $products_config['default']['remise_parrain'] );
        }
        
        // Formatage avec virgule française
        return number_format( $remise_montant, 2, ',', '' ) . '€';
    }
    
    /**
     * Formate un montant pour l'affichage
     * 
     * @param float $amount Montant à formater
     * @param string $currency Devise (non utilisée pour l'instant)
     * @return string Montant formaté
     */
    public function format_montant( $amount, $currency = 'EUR' ) {
        if ( ! is_numeric( $amount ) ) {
            return '';
        }
        
        return sprintf( self::AMOUNT_FORMAT, floatval( $amount ) );
    }
    
    /**
     * Exécute la requête SQL pour récupérer les données de parrainage
     * 
     * @param int $subscription_id ID de l'abonnement parrain
     * @param int $limit Limite du nombre de résultats
     * @return array Données brutes de la base de données
     */
    private function query_parrainages_data( $subscription_id, $limit ) {
        global $wpdb;
        
        $query = "
            SELECT 
                p.ID as order_id,
                p.post_date as date_parrainage,
                pm_code.meta_value as code_parrain,
                CONCAT(pm_first.meta_value, ' ', pm_last.meta_value) as filleul_nom,
                pm_email.meta_value as filleul_email,
                pm_avantage.meta_value as avantage,
                wci.order_item_name as produit_nom,
                sub.post_status as subscription_status,
                sub_meta.meta_value as subscription_total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_code ON p.ID = pm_code.post_id 
                AND pm_code.meta_key = '_billing_parrain_code'
            LEFT JOIN {$wpdb->postmeta} pm_first ON p.ID = pm_first.post_id 
                AND pm_first.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm_last ON p.ID = pm_last.post_id 
                AND pm_last.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_avantage ON p.ID = pm_avantage.post_id 
                AND pm_avantage.meta_key = '_parrainage_avantage'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items wci ON p.ID = wci.order_id 
                AND wci.order_item_type = 'line_item'
            LEFT JOIN {$wpdb->posts} sub ON sub.post_parent = p.ID 
                AND sub.post_type = 'shop_subscription'
            LEFT JOIN {$wpdb->postmeta} sub_meta ON sub.ID = sub_meta.post_id 
                AND sub_meta.meta_key = '_order_total'
            WHERE pm_code.meta_value = %s
                AND p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
            ORDER BY p.post_date DESC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results( $wpdb->prepare( $query, $subscription_id, $limit ) );
        
        if ( $wpdb->last_error ) {
            $this->logger->error( 'Erreur SQL lors de la récupération des parrainages', array(
                'subscription_id' => $subscription_id,
                'sql_error' => $wpdb->last_error,
                'query' => $query
            ) );
            return array();
        }
        
        return $results ?: array();
    }
    
    /**
     * Traite et formate une ligne de données de parrainage (v2.0.2)
     * 
     * @param object $row Ligne de données brutes
     * @return array Données formatées pour l'affichage
     */
    private function process_parrainage_row( $row ) {
        // Récupération sécurisée du prix HT réel
        $montant_ht = 0;
        if ( !empty( $row->order_id ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            try {
                $subscriptions = wcs_get_subscriptions_for_order( $row->order_id );
                if ( !empty( $subscriptions ) ) {
                    $subscription = reset( $subscriptions ); // Premier abonnement
                    if ( $subscription && $subscription->get_id() ) {
                        $montant_ht = (float) $subscription->get_subtotal(); // Prix HT officiel
                    }
                }
            } catch ( Exception $e ) {
                // Gestion d'erreur silencieuse avec logging
                $montant_ht = 0;
                if ( $this->logger ) {
                    $this->logger->error( 'Erreur récupération prix HT', array(
                        'order_id' => $row->order_id,
                        'error' => $e->getMessage()
                    ) );
                }
            }
        }
        
        return array(
            'order_id' => intval( $row->order_id ),
            'filleul_nom' => \sanitize_text_field( $row->filleul_nom ),
            'filleul_email' => $this->format_filleul_email( $row->filleul_email ),
            'date_parrainage' => $this->format_date_parrainage( $row->date_parrainage ),
            'date_parrainage_raw' => $row->date_parrainage,
            'produit_nom' => \sanitize_text_field( $row->produit_nom ),
            'subscription_status' => $row->subscription_status,
            'status_label' => $this->get_subscription_status_label( $row->subscription_status ),
            'avantage' => \sanitize_text_field( $row->avantage ?: 'Avantage parrainage' ),
            // Nouvelles données v2.0.2
            'abonnement_ht' => $this->format_montant_ht( $montant_ht ),
            'abonnement_ht_raw' => $montant_ht,
            'votre_remise' => $this->get_parrain_reduction( $row->order_id, $row->subscription_status ),
            // Anciennes données conservées pour compatibilité
            'montant' => $this->format_montant( $row->subscription_total ),
            'montant_raw' => floatval( $row->subscription_total )
        );
    }
} 