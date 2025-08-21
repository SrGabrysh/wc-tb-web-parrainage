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
        $this->logger->info( '🚀 DÉBUT get_user_parrainages()', array(
            'user_subscription_id' => $user_subscription_id,
            'limit' => $limit
        ), 'mes-parrainages-debug' );
        
        try {
            // FORCE CACHE CLEAR v2.7.10 - Vider systématiquement pour tests
            $cache_key = self::CACHE_KEY_PREFIX . $user_subscription_id;
            \delete_transient( $cache_key );
            $cached_data = false;
            
            $this->logger->info( '🗑️ Cache vidé', array(
                'subscription_id' => $user_subscription_id,
                'cache_key' => $cache_key
            ), 'mes-parrainages-debug' );
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR dans cache clear', array(
                'error' => $e->getMessage()
            ), 'mes-parrainages-debug' );
        }
        
        if ( $cached_data !== false ) {
            $this->logger->info( 'Données de parrainage récupérées depuis le cache', array(
                'subscription_id' => $user_subscription_id,
                'cache_key' => $cache_key
            ) );
            return $cached_data;
        }
        
        try {
            // Récupérer les données depuis la base
            $this->logger->info( '🔍 Appel query_parrainages_data()', array(
                'subscription_id' => $user_subscription_id,
                'limit' => $limit
            ), 'mes-parrainages-debug' );
            
            $raw_data = $this->query_parrainages_data( $user_subscription_id, $limit );
            
            $this->logger->info( '📊 Raw data récupérée', array(
                'raw_data_count' => count( $raw_data ),
                'raw_data_type' => gettype( $raw_data )
            ), 'mes-parrainages-debug' );
            
            // Traiter et formater les données
            $formatted_data = array();
            foreach ( $raw_data as $index => $row ) {
                $this->logger->info( "🔄 Processing row {$index}", array(
                    'order_id' => $row->order_id ?? 'MISSING'
                ), 'mes-parrainages-debug' );
                
                try {
                    $formatted_row = $this->process_parrainage_row( $row );
                    $formatted_data[] = $formatted_row;
                    $this->logger->info( "✅ Row {$index} processed", array(), 'mes-parrainages-debug' );
                } catch ( Exception $e ) {
                    $this->logger->error( "💥 ERREUR processing row {$index}", array(
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ), 'mes-parrainages-debug' );
                    // Continuer avec les autres lignes
                }
            }
            
            $this->logger->info( '📦 Formatage terminé', array(
                'formatted_count' => count( $formatted_data )
            ), 'mes-parrainages-debug' );
            
            // Mettre en cache
            \set_transient( $cache_key, $formatted_data, self::CACHE_DURATION );
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans get_user_parrainages()', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ), 'mes-parrainages-debug' );
            return array(); // Retourner tableau vide en cas d'erreur
        }
        
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
        // DEBUG : Logs pour identifier le problème
        $this->logger->debug( 'get_parrain_reduction appelé', array(
            'order_id' => $order_id,
            'subscription_status' => $subscription_status
        ), 'account-data-provider' );
        
        // Vérifier si l'abonnement est actif
        if ( ! in_array( $subscription_status, ['active', 'wc-active'] ) ) {
            $this->logger->debug( 'Abonnement non actif', array(
                'subscription_status' => $subscription_status
            ), 'account-data-provider' );
            return 'Non applicable';
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->logger->warning( 'Commande non trouvée', array( 'order_id' => $order_id ), 'account-data-provider' );
            return '0,00€';
        }
        
        // Récupérer la configuration des produits
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        $remise_montant = 0.00;
        
        $this->logger->debug( 'Configuration produits récupérée', array(
            'config_count' => count( $products_config ),
            'config_keys' => array_keys( $products_config )
        ), 'account-data-provider' );
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            $this->logger->debug( 'Vérification produit', array(
                'product_id' => $product_id,
                'has_config' => isset( $products_config[ $product_id ]['remise_parrain'] ),
                'config_value' => $products_config[ $product_id ]['remise_parrain'] ?? 'NOT_SET'
            ), 'account-data-provider' );
            
            if ( isset( $products_config[ $product_id ]['remise_parrain'] ) ) {
                $remise = $products_config[ $product_id ]['remise_parrain'];
                
                // Gérer les formats de configuration uniformément avec DiscountCalculator
                if ( is_array( $remise ) && isset( $remise['montant'] ) ) {
                    $remise_montant = floatval( $remise['montant'] );
                } else {
                    $remise_montant = floatval( $remise );
                }
                
                $this->logger->debug( 'Remise trouvée', array(
                    'product_id' => $product_id,
                    'remise_raw' => $remise,
                    'remise_montant' => $remise_montant
                ), 'account-data-provider' );
                break;
            }
        }
        
        // Si aucune remise spécifique, vérifier la config par défaut
        if ( $remise_montant == 0.00 && isset( $products_config['default']['remise_parrain'] ) ) {
            $remise_montant = floatval( $products_config['default']['remise_parrain'] );
            $this->logger->debug( 'Remise par défaut utilisée', array( 'remise_montant' => $remise_montant ), 'account-data-provider' );
        }
        
        $result = number_format( $remise_montant, 2, ',', '' ) . '€/mois';
        
        $this->logger->info( 'Remise parrain calculée', array(
            'order_id' => $order_id,
            'remise_montant' => $remise_montant,
            'result' => $result
        ), 'account-data-provider' );
        
        return $result;
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
        $this->logger->info( '🔄 DÉBUT process_parrainage_row()', array(
            'order_id' => $row->order_id ?? 'MISSING',
            'filleul_nom' => $row->filleul_nom ?? 'MISSING'
        ), 'mes-parrainages-debug' );
        
        try {
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
        
            $this->logger->info( '💰 Montant HT calculé', array(
                'order_id' => $row->order_id,
                'montant_ht' => $montant_ht
            ), 'mes-parrainages-debug' );
            
            // NOUVEAU v2.4.0 : Données remise mockées côté client
            $this->logger->info( '🎭 Appel get_real_client_discount_data_safe()', array(
                'order_id' => $row->order_id
            ), 'mes-parrainages-debug' );
            
            $discount_data = $this->get_real_client_discount_data_safe( $row->order_id );
            
            $this->logger->info( '✅ Discount data récupérée', array(
                'order_id' => $row->order_id,
                'discount_data_type' => gettype( $discount_data )
            ), 'mes-parrainages-debug' );
            
            $result = array(
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
            'montant_raw' => floatval( $row->subscription_total ),
            // MODIFICATION v2.6.0 : Données remise réelles côté client
            'discount_client_info' => $discount_data
        );
        
        $this->logger->info( '✅ process_parrainage_row() TERMINÉ', array(
            'order_id' => $row->order_id,
            'result_keys' => array_keys( $result )
        ), 'mes-parrainages-debug' );
        
        return $result;
        
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans process_parrainage_row()', array(
                'order_id' => $row->order_id ?? 'MISSING',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ), 'mes-parrainages-debug' );
            
            // Retourner des données minimales en cas d'erreur
            return array(
                'order_id' => intval( $row->order_id ?? 0 ),
                'filleul_nom' => 'Erreur de traitement',
                'filleul_email' => '',
                'date_parrainage' => '',
                'subscription_status' => '',
                'status_label' => 'Erreur',
                'avantage' => '',
                'abonnement_ht' => '',
                'votre_remise' => 'Erreur',
                'discount_client_info' => array()
            );
        }
    }
    
    /**
     * NOUVEAU v2.4.0 : Génération de données mockées côté client
     * 
     * @param int $order_id ID de la commande
     * @return array Données mockées pour l'interface client
     */
    private function get_client_mock_discount_data( $order_id ) {
        $statuses = ['active', 'pending', 'failed', 'suspended'];
        
        // Utiliser l'ID de commande pour des résultats cohérents
        mt_srand( intval( $order_id ) );
        $status = $statuses[mt_rand( 0, count( $statuses ) - 1 )];
        
        $discount_amount = mt_rand( 500, 1500 ) / 100; // Entre 5€ et 15€
        $total_monthly_savings = mt_rand( 700, 2500 ) / 100; // Entre 7€ et 25€ (1-3 filleuls)
        $total_savings_to_date = mt_rand( 50, 300 ); // Entre 50€ et 300€ (montant simulé)
        
        return array(
            'discount_status' => $status,
            'discount_status_message' => $this->get_client_status_message( $status ),
            'discount_amount' => $discount_amount,
            'discount_amount_formatted' => number_format( $discount_amount, 2, ',', '' ) . '€/mois',
            'status_icon' => $this->get_status_icon( $status ),
            'status_color' => $this->get_status_color( $status ),
            'total_monthly_savings' => $total_monthly_savings,
            'total_savings_to_date' => $total_savings_to_date,
            'next_billing_preview' => array(
                'date' => date( 'd/m/Y', strtotime( '+1 month' ) ),
                'amount_before' => 89.99,
                'amount_after' => 89.99 - $discount_amount,
                'savings' => $discount_amount
            )
        );
    }
    
    /**
     * NOUVEAU v2.4.0 : Messages de statut pour les clients
     * 
     * @param string $status Statut technique
     * @return string Message affiché au client
     */
    private function get_client_status_message( $status ) {
        $messages = array(
            'active' => 'ACTIVE - Appliquée depuis le ' . date( 'd/m/Y', strtotime( '-' . mt_rand( 1, 30 ) . ' days' ) ),
            'pending' => 'EN ATTENTE - Application en cours...',
            'failed' => 'PROBLÈME - Contactez le support',
            'suspended' => 'SUSPENDUE - Filleul a suspendu son abonnement'
        );
        return $messages[$status] ?? 'Statut inconnu';
    }
    
    /**
     * NOUVEAU v2.4.0 : Icônes pour les statuts côté client
     * 
     * @param string $status Statut technique
     * @return string Icône emoji
     */
    private function get_status_icon( $status ) {
        $icons = array(
            'active' => '🟢',
            'pending' => '🟡',
            'failed' => '🔴',
            'suspended' => '⏸️'
        );
        return $icons[$status] ?? '⚪';
    }
    
    /**
     * NOUVEAU v2.4.0 : Couleurs pour les statuts côté client
     * 
     * @param string $status Statut technique
     * @return string Classe couleur CSS
     */
    private function get_status_color( $status ) {
        $colors = array(
            'active' => 'status-success',
            'pending' => 'status-warning',
            'failed' => 'status-error',
            'suspended' => 'status-neutral'
        );
        return $colors[$status] ?? 'status-default';
    }
    
    /**
     * NOUVEAU v2.4.0 : Calcul du résumé global des économies
     * 
     * @param int $user_subscription_id ID de l'abonnement utilisateur
     * @return array Résumé des économies
     */
    public function get_savings_summary( $user_subscription_id ) {
        // DEBUG v2.14.0 : FORCE LOG - TOUJOURS APPELÉ
        $this->logger->info( 
            '🚀 ENTRÉE get_savings_summary - DÉBUT COMPLET',
            array(
                'user_subscription_id' => $user_subscription_id,
                'timestamp' => time(),
                'called_from' => debug_backtrace()[1]['function'] ?? 'UNKNOWN',
                'memory_usage' => memory_get_usage( true )
            ),
            'mes-parrainages-debug'
        );
        
        try {
        
        // CORRECTIF v2.7.6 : Vider le cache du résumé si contient timestamp
        $cache_key = self::CACHE_KEY_PREFIX . 'summary_' . $user_subscription_id;
        $cached_summary = \get_transient( $cache_key );
        
        if ( $cached_summary !== false ) {
            // Vérifier si le résumé contient un timestamp au lieu d'un montant
            $total_savings = $cached_summary['total_savings_to_date'] ?? 0;
            if ( is_numeric( $total_savings ) && $total_savings > 100000 ) {
                \delete_transient( $cache_key );
                $cached_summary = false;
                
                $this->logger->info( 'Cache résumé invalidé - timestamp détecté', array(
                    'subscription_id' => $user_subscription_id,
                    'timestamp_detected' => $total_savings
                ), 'account-data-provider' );
            }
        }
        
        // DEBUG v2.9.0 : DÉSACTIVER CACHE TEMPORAIREMENT
        if ( false && $cached_summary !== false ) {
            return $cached_summary;
        }
        
        // MODIFICATION v2.6.0 : Calcul réel du résumé des économies
        try {
            $this->logger->info( '🔍 APPEL get_real_referrals_data()', array(
                'user_subscription_id' => $user_subscription_id
            ), 'mes-parrainages-debug' );
            
            $real_referrals = $this->get_real_referrals_data( $user_subscription_id );
            
            $this->logger->info( '✅ get_real_referrals_data() TERMINÉ', array(
                'real_referrals_type' => gettype( $real_referrals ),
                'real_referrals_is_array' => is_array( $real_referrals ),
                'real_referrals_count' => is_array( $real_referrals ) ? count( $real_referrals ) : 'NOT_ARRAY'
            ), 'mes-parrainages-debug' );
            
            // PROTECTION v2.14.0 : Vérifier que $real_referrals est un tableau
            if ( ! is_array( $real_referrals ) ) {
                $this->logger->error( '💥 ERREUR FATALE: $real_referrals n\'est pas un tableau', array(
                    'type' => gettype( $real_referrals ),
                    'value' => $real_referrals
                ), 'mes-parrainages-debug' );
                $real_referrals = array(); // Fallback vers tableau vide
            }
            
            // DEBUG v2.9.0 : Log détaillé pour debugging
            $this->logger->info( 
                'DÉBUT COMPTAGE - Données récupérées depuis get_real_referrals_data',
                array(
                    'user_subscription_id' => $user_subscription_id,
                    'total_referrals_raw' => count( $real_referrals ),
                    'referrals_structure' => array_map( function( $ref ) {
                        // CORRECTION v2.14.0 : Utiliser les bonnes clés de données
                        return array(
                            'order_id' => $ref['order_id'] ?? 'MISSING',
                            'nom' => $ref['nom'] ?? $ref['filleul_nom'] ?? 'MISSING', // Support des 2 formats
                            'has_discount_info' => isset( $ref['discount_info'] ),
                            'discount_info_keys' => isset( $ref['discount_info'] ) ? array_keys( $ref['discount_info'] ) : 'NONE'
                        );
                    }, array_slice( $real_referrals, 0, 3 ) ) // 3 premiers pour éviter logs trop longs
                ),
                'mes-parrainages-debug'
            );
            
            $active_discounts = 0;
            $total_monthly_savings = 0;
            $total_referrals = count( $real_referrals );
            
            foreach ( $real_referrals as $index => $referral ) {
                // CORRECTION v2.9.0 : Utiliser 'discount_info' (clé de ParrainageDataProvider)
                $status = $referral['discount_info']['discount_status'] ?? '';
                $amount = floatval( $referral['discount_info']['discount_amount'] ?? 0 );
                
                // DEBUG v2.9.0 : Log chaque filleul individuellement - SIMPLIFIÉ
                $this->logger->info( 
                    "FILLEUL #{$index} - STATUS: {$status}",
                    array(
                        'order_id' => $referral['order_id'] ?? 'MISSING',
                        'discount_status' => $status,
                        'discount_amount' => $amount
                    ),
                    'mes-parrainages-debug'
                );
                
                $this->logger->info( 
                    "FILLEUL #{$index} - WILL_COUNT: " . ( $amount > 0 && in_array( $status, array( 'calculated', 'applied', 'active', 'scheduled' ), true ) ? 'YES' : 'NO' ),
                    array(
                        'amount_gt_0' => ( $amount > 0 ),
                        'status_allowed' => in_array( $status, array( 'calculated', 'applied', 'active', 'scheduled' ), true ),
                        'allowed_statuses' => array( 'calculated', 'applied', 'active', 'scheduled' )
                    ),
                    'mes-parrainages-debug'
                );
                
                if ( isset( $referral['discount_info'] ) ) {
                    $this->logger->info( 
                        "FILLEUL #{$index} - DISCOUNT_INFO",
                        $referral['discount_info'],
                        'mes-parrainages-debug'
                    );
                }
                
                // CORRECTION v2.9.1 : Statut 'application_failed' ajouté car filleul actif = remise due
                // Ne pas compter uniquement sur le statut plugin, mais sur l'état réel du filleul
                if ( $amount > 0 && in_array( $status, array( 'calculated', 'applied', 'active', 'scheduled', 'application_failed' ), true ) ) {
                    $active_discounts++;
                    $total_monthly_savings += $amount;
                    
                    $this->logger->info( 
                        "FILLEUL #{$index} - COMPTÉ !",
                        array(
                            'order_id' => $referral['order_id'] ?? 'MISSING',
                            'active_discounts_now' => $active_discounts,
                            'total_monthly_savings_now' => $total_monthly_savings
                        ),
                        'mes-parrainages-debug'
                    );
                }
            }
            
            // DEBUG v2.9.0 : Log résultat final
            $this->logger->info( 
                'RÉSULTAT FINAL - Comptage terminé',
                array(
                    'user_subscription_id' => $user_subscription_id,
                    'total_referrals' => $total_referrals,
                    'active_discounts' => $active_discounts,
                    'total_monthly_savings' => $total_monthly_savings
                ),
                'mes-parrainages-debug'
            );
            
            $subscription = wcs_get_subscription( $user_subscription_id );
            $original_amount = $subscription ? floatval( $subscription->get_total() ) : 89.99;
            
            // DEBUG v2.9.3 : TRACE COMPLÈTE AVANT CALCUL NEXT_BILLING
            $this->logger->debug( 'DEBUG BEFORE NEXT_BILLING - Variables source', array(
                'user_subscription_id' => $user_subscription_id,
                'subscription_exists' => $subscription ? 'OUI' : 'NON',
                'subscription_get_total_brut' => $subscription ? $subscription->get_total() : 'N/A',
                'original_amount' => $original_amount,
                'original_amount_type' => gettype($original_amount),
                'total_monthly_savings' => $total_monthly_savings,
                'total_monthly_savings_type' => gettype($total_monthly_savings)
            ), 'mes-parrainages-debug' );
            
            // PROTECTION v2.9.2 : Si montant aberrant (timestamp), utiliser valeur par défaut
            if ( $original_amount > 100000 ) {
                $this->logger->warning( 'Montant aberrant détecté, utilisation valeur par défaut', array(
                    'user_subscription_id' => $user_subscription_id,
                    'montant_aberrant' => $original_amount
                ), 'account-data-provider' );
                $original_amount = 71.99; // Prix normal avec remise suspendue
            }
            
            $this->logger->info( '📈 DÉBUT calcul économies totales', array(
                'user_subscription_id' => $user_subscription_id,
                'real_referrals_count' => count( $real_referrals )
            ), 'mes-parrainages-debug' );
            
            // Calculer les économies totales depuis le début (estimation basée sur la durée des parrainages actifs)
            $total_savings_to_date = 0;
            foreach ( $real_referrals as $index => $referral ) {
                $this->logger->info( "💰 Calcul économies referral {$index}", array(
                    'referral_keys' => array_keys( $referral ),
                    'discount_info_exists' => isset( $referral['discount_info'] )
                ), 'mes-parrainages-debug' );
                try {
                    // CORRECTION v2.9.0 : Utiliser 'discount_info' (clé de ParrainageDataProvider)
                    $status = $referral['discount_info']['discount_status'] ?? '';
                    
                    $this->logger->info( "🔍 Status referral {$index}", array(
                        'status' => $status,
                        'discount_info_type' => gettype( $referral['discount_info'] ?? null )
                    ), 'mes-parrainages-debug' );
                    
                    if ( in_array( $status, array( 'calculated', 'applied', 'active', 'scheduled' ), true ) ) {
                        $discount_amount = floatval( $referral['discount_info']['discount_amount'] ?? 0 );
                        $parrainage_date = strtotime( $referral['date_parrainage_raw'] ?? 'now' );
                        
                        $this->logger->info( "📅 Calcul dates referral {$index}", array(
                            'discount_amount' => $discount_amount,
                            'parrainage_date_raw' => $referral['date_parrainage_raw'] ?? 'MISSING',
                            'parrainage_date_timestamp' => $parrainage_date
                        ), 'mes-parrainages-debug' );
                        
                        // CORRECTION v2.9.3 : Protection contre timestamps aberrants dans le calcul
                        if ( $parrainage_date === false || $parrainage_date < strtotime( '2020-01-01' ) ) {
                            $parrainage_date = time(); // Utiliser maintenant si date invalide
                        }
                        
                        $months_active = max( 1, floor( ( time() - $parrainage_date ) / ( 30 * 24 * 3600 ) ) );
                        
                        // CORRECTION v2.9.3 : Limiter les mois actifs à une valeur raisonnable
                        $months_active = min( $months_active, 24 ); // Max 2 ans
                        
                        $contribution = $discount_amount * $months_active;
                        $total_savings_to_date += $contribution;
                        
                        $this->logger->info( "✅ Contribution referral {$index}", array(
                            'months_active' => $months_active,
                            'contribution' => $contribution,
                            'total_savings_to_date' => $total_savings_to_date
                        ), 'mes-parrainages-debug' );
                    }
                    
                } catch ( Exception $e ) {
                    $this->logger->error( "💥 ERREUR calcul économies referral {$index}", array(
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ), 'mes-parrainages-debug' );
                }
            }

            $this->logger->info( '🏗️ DÉBUT création summary', array(
                'active_discounts' => $active_discounts,
                'total_referrals' => $total_referrals,
                'total_monthly_savings' => $total_monthly_savings,
                'total_savings_to_date' => $total_savings_to_date,
                'original_amount' => $original_amount
            ), 'mes-parrainages-debug' );
            
            try {
                $this->logger->info( '📅 Appel get_real_next_billing_date()', array(
                    'user_subscription_id' => $user_subscription_id
                ), 'mes-parrainages-debug' );
                
                $next_billing_date = $this->get_real_next_billing_date( $user_subscription_id );
                
                $this->logger->info( '✅ Next billing date récupérée', array(
                    'next_billing_date' => $next_billing_date
                ), 'mes-parrainages-debug' );
                
                $this->logger->info( '📋 Appel get_real_pending_actions()', array(
                    'real_referrals_count' => count( $real_referrals )
                ), 'mes-parrainages-debug' );
                
                $pending_actions = $this->get_real_pending_actions( $real_referrals );
                
                $this->logger->info( '✅ Pending actions récupérées', array(
                    'pending_actions_count' => count( $pending_actions )
                ), 'mes-parrainages-debug' );
                
                $summary = array(
                    'active_discounts' => $active_discounts,
                    'total_referrals' => $total_referrals,
                    'monthly_savings' => $total_monthly_savings, // CORRECTION v2.8.2-fix13 : Pas de formatage ici
                    'yearly_projection' => number_format( $total_monthly_savings * 12, 2 ),
                    'total_savings_to_date' => max( 0, $total_savings_to_date ), // CORRECTION : Éviter valeurs négatives
                    'currency' => get_woocommerce_currency_symbol(),
                    'next_billing' => array(
                        'date' => $next_billing_date, // CORRECTION v2.9.3 : Vraie date WooCommerce
                        'amount' => number_format( round( $original_amount - $total_monthly_savings, 2 ), 2, ',', '' ) . ' HT',
                        'original_amount' => number_format( $original_amount, 2, ',', '' )
                    ),
                    'pending_actions' => $pending_actions
                );
                
                $this->logger->info( '🎯 Summary créé avec succès', array(
                    'summary_keys' => array_keys( $summary )
                ), 'mes-parrainages-debug' );
                
            } catch ( Exception $e ) {
                $this->logger->error( '💥 ERREUR création summary', array(
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ), 'mes-parrainages-debug' );
                
                // Fallback summary en cas d'erreur
                $summary = array(
                    'active_discounts' => 0,
                    'total_referrals' => 0,
                    'monthly_savings' => 0,
                    'yearly_projection' => '0,00',
                    'total_savings_to_date' => 0,
                    'currency' => '€',
                    'next_billing' => array(
                        'date' => 'N/A',
                        'amount' => 'Erreur',
                        'original_amount' => 'Erreur'
                    ),
                    'pending_actions' => array()
                );
            }
            
            $this->logger->info( '🎉 get_savings_summary() TERMINÉ AVEC SUCCÈS', array(
                'user_subscription_id' => $user_subscription_id,
                'summary_size' => count( $summary ),
                'summary_keys' => array_keys( $summary )
            ), 'mes-parrainages-debug' );
            
            // DEBUG v2.9.3 : TRACE FINALE NEXT_BILLING CALCULÉ
            $next_billing_date_calc = $this->get_real_next_billing_date( $user_subscription_id );
            $next_billing_amount_calc = round( $original_amount - $total_monthly_savings, 2 );
            $next_billing_amount_formatted = number_format( $next_billing_amount_calc, 2, ',', '' ) . ' HT';
            
            $this->logger->debug( 'DEBUG AFTER NEXT_BILLING - Résultat final calculé', array(
                'user_subscription_id' => $user_subscription_id,
                'next_billing_date_raw' => $next_billing_date_calc,
                'calcul_soustraction' => $original_amount . ' - ' . $total_monthly_savings . ' = ' . $next_billing_amount_calc,
                'next_billing_amount_formatted' => $next_billing_amount_formatted,
                'summary_next_billing_complet' => $summary['next_billing']
            ), 'mes-parrainages-debug' );
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Erreur lors du calcul du résumé des économies réelles - fallback vers mockées',
                array(
                    'user_subscription_id' => $user_subscription_id,
                    'error' => $e->getMessage()
                ),
                'account-data-provider'
            );
            
            // Fallback vers données mockées en cas d'erreur
            return $this->get_mock_savings_summary( $user_subscription_id );
        }
        
        // PROTECTION : Détecter et corriger les timestamps dans total_savings_to_date
        if ( isset( $summary['total_savings_to_date'] ) ) {
            $total_savings_raw = $summary['total_savings_to_date'];
            
            // Si c'est un timestamp (nombre > 100000), corriger
            if ( is_numeric( $total_savings_raw ) && $total_savings_raw > 100000 ) {
                $this->logger->warning(
                    'TIMESTAMP DÉTECTÉ dans total_savings_to_date - correction appliquée',
                    array(
                        'user_subscription_id' => $user_subscription_id,
                        'timestamp_detected' => $total_savings_raw,
                        'converted_date' => date( 'Y-m-d H:i:s', $total_savings_raw )
                    ),
                    'account-data-provider'
                );
                
                // Remplacer par un montant par défaut
                $summary['total_savings_to_date'] = '0,00';
            }
        }
        
        // Mettre en cache
        \set_transient( $cache_key, $summary, self::CACHE_DURATION );
        
        $this->logger->info( '✅ get_savings_summary TERMINÉ AVEC SUCCÈS', array(
            'user_subscription_id' => $user_subscription_id,
            'summary_keys' => array_keys( $summary ),
            'active_discounts' => $summary['active_discounts'] ?? 'MISSING',
            'monthly_savings' => $summary['monthly_savings'] ?? 'MISSING'
        ), 'mes-parrainages-debug' );
        
        return $summary;
        
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans get_savings_summary', array(
                'user_subscription_id' => $user_subscription_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ), 'mes-parrainages-debug' );
            
            // Fallback vers données mockées en cas d'erreur
            return $this->get_mock_savings_summary( $user_subscription_id );
        }
    }
    
    /**
     * NOUVEAU v2.4.0 : Actions en attente mockées
     * 
     * @param int $active_discounts Nombre de remises actives
     * @param int $total_referrals Nombre total de filleuls
     * @return array Liste des actions en attente
     */
    private function get_mock_pending_actions( $active_discounts, $total_referrals ) {
        $actions = array();
        
        // Si il y a des filleuls sans remise active
        if ( $total_referrals > $active_discounts ) {
            $pending_count = $total_referrals - $active_discounts;
            
            if ( $pending_count === 1 ) {
                $actions[] = array( 'message' => 'Marie Dupont : Remise en attente (normal, sous 10 min)' );
            } else {
                $actions[] = array( 'message' => 'Paul Martin : Abonnement suspendu, remise en pause' );
            }
        }
        
        return $actions;
    }
    
    /**
     * WRAPPER SAFE pour get_real_client_discount_data avec gestion d'erreurs
     */
    private function get_real_client_discount_data_safe( $order_id ) {
        $this->logger->info( '🛡️ ENTRÉE get_real_client_discount_data_safe()', array(
            'order_id' => $order_id
        ), 'mes-parrainages-debug' );
        
        try {
            $this->logger->info( '🚀 Appel get_real_client_discount_data()', array(
                'order_id' => $order_id
            ), 'mes-parrainages-debug' );
            
            $result = $this->get_real_client_discount_data( $order_id );
            
            $this->logger->info( '✅ get_real_client_discount_data() OK', array(
                'order_id' => $order_id,
                'result_type' => gettype( $result ),
                'result_keys' => is_array( $result ) ? array_keys( $result ) : 'NOT_ARRAY'
            ), 'mes-parrainages-debug' );
            
            return $result;
            
        } catch ( \Exception $e ) {
            $this->logger->error( '💥 ERREUR dans get_real_client_discount_data_safe()', array(
                'order_id' => $order_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ), 'mes-parrainages-debug' );
            
            // Fallback vers données mockées en cas d'erreur
            $this->logger->info( '🎭 FALLBACK vers get_client_mock_discount_data()', array(
                'order_id' => $order_id
            ), 'mes-parrainages-debug' );
            
            return $this->get_client_mock_discount_data( $order_id );
        }
    }

    /**
     * NOUVEAU v2.6.0 : Récupération des vraies données client de remise
     * 
     * @param int $order_id ID de la commande
     * @return array Données de remise réelles côté client
     */
    private function get_real_client_discount_data( $order_id ) {
        $this->logger->info( '🚀 DEBUT get_real_client_discount_data', array(
            'order_id' => $order_id,
            'method' => 'get_real_client_discount_data'
        ), 'account-data-provider' );
        try {
            // Récupération de l'instance du plugin pour accès aux services
            $this->logger->info( '🔧 Récupération instance plugin', array(
                'order_id' => $order_id
            ), 'account-data-provider' );
            
            $plugin_instance = $this->get_plugin_instance();
            if ( ! $plugin_instance ) {
                $this->logger->warning( '❌ ÉCHEC - Instance plugin non trouvée !', array(
                    'order_id' => $order_id
                ), 'account-data-provider' );
                return $this->get_client_mock_discount_data( $order_id );
            }
            
            $this->logger->info( '✅ Instance plugin OK - récupération calculator', array(
                'order_id' => $order_id
            ), 'account-data-provider' );
            
            $calculator = $plugin_instance->get_discount_calculator();
            if ( ! $calculator ) {
                $this->logger->warning( '❌ ÉCHEC - Calculator non trouvé !', array(
                    'order_id' => $order_id
                ), 'account-data-provider' );
                return $this->get_client_mock_discount_data( $order_id );
            }
            
            $this->logger->info( '✅ Calculator OK - récupération commande', array(
                'order_id' => $order_id
            ), 'account-data-provider' );
            
            // Récupération des informations de la commande
            $this->logger->info( '📦 Récupération commande WooCommerce', array(
                'order_id' => $order_id
            ), 'account-data-provider' );
            
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                $this->logger->error( '❌ ÉCHEC - Commande WooCommerce non trouvée !', array(
                    'order_id' => $order_id,
                    'wc_get_order_result' => 'false'
                ), 'account-data-provider' );
                return array();
            }
            
            $this->logger->info( '✅ Commande OK - vérification workflow status', array(
                'order_id' => $order_id,
                'order_status' => $order->get_status()
            ), 'account-data-provider' );
            
            // Vérification si remise calculée (simulation) ou appliquée (réel)
            $calculated_discounts = $order->get_meta( '_parrainage_calculated_discounts' );
            $workflow_status = $order->get_meta( '_parrainage_workflow_status' );
            
            $this->logger->info( '🔍 Récupération métadonnées workflow', array(
                'order_id' => $order_id,
                'workflow_status' => $workflow_status,
                'has_calculated_discounts' => !empty($calculated_discounts)
            ), 'account-data-provider' );

            // 1) Mode simulation (v2.6.0)
            if ( $calculated_discounts && $workflow_status === 'calculated' ) {
                $discount_data = is_array( $calculated_discounts ) ? $calculated_discounts[0] : $calculated_discounts;
                return array(
                    'discount_status' => 'calculated',
                    'discount_status_label' => 'CALCULÉ (v2.6.0)',
                    'discount_amount' => $discount_data['discount_amount'] ?? 0,
                    'discount_amount_formatted' => number_format( $discount_data['discount_amount'] ?? 0, 2, ',', '' ) . '€/mois',
                    'calculation_date' => $order->get_meta( '_parrainage_calculation_date' ),
                    'is_simulation' => true
                );
            }

            // 2) Mode réel (v2.7.x) : workflow 'applied' ou 'active' → lire la méta sur la souscription du parrain
            if ( in_array( $workflow_status, array( 'applied', 'active' ), true ) ) {
                $parrain_subscription_id = (int) $order->get_meta( '_parrain_subscription_id' );
                if ( $parrain_subscription_id && \function_exists( 'wcs_get_subscription' ) ) {
                    $parrain_subscription = \wcs_get_subscription( $parrain_subscription_id );
                    if ( $parrain_subscription ) {
                        $amount = (float) $parrain_subscription->get_meta( '_tb_parrainage_discount_amount' );
                        if ( $amount > 0 ) {
                            return array(
                                'discount_status' => 'active',
                                'discount_status_label' => 'Remise active',
                                'discount_amount' => $amount,
                                'discount_amount_formatted' => number_format( $amount, 2, ',', '' ) . '€/mois',
                                'is_simulation' => false
                            );
                        }
                    }
                }
                // Si rien trouvé, afficher 0 mais conserver le statut
                return array(
                    'discount_status' => $workflow_status,
                    'discount_status_label' => $this->get_workflow_status_label_client( $workflow_status ),
                    'discount_amount' => 0,
                    'discount_amount_formatted' => '0,00€/mois',
                    'is_simulation' => false
                );
            }

            // 3) Statut 'application_failed' → CORRECTION v2.8.2-fix12 - Vérifier l'état du FILLEUL
            if ( $workflow_status === 'application_failed' ) {
                // CORRECTION : Vérifier l'état de l'abonnement du FILLEUL, pas du parrain !
                $filleul_status = $this->get_filleul_subscription_status( $order_id );
                $configured_amount = $this->get_configured_discount_amount( $order_id );
                
                if ( $filleul_status['is_active'] ) {
                    // Filleul actif → Parrain reçoit la remise
                    $remise_amount = $configured_amount;
                    $status_label = 'Active';
                    $discount_status = 'active';
                } else {
                    // Filleul inactif → Pas de remise pour le parrain
                    $remise_amount = 0;
                    $status_label = 'Filleul ' . $filleul_status['status_label'];
                    $discount_status = 'suspended';
                }
                
                $this->logger->info( 'GESTION application_failed - remise selon état du FILLEUL', array(
                    'order_id' => $order_id,
                    'workflow_status' => $workflow_status,
                    'filleul_status' => $filleul_status,
                    'configured_amount' => $configured_amount,
                    'displayed_amount' => $remise_amount
                ), 'account-data-provider' );
                
                return array(
                    'discount_status' => $discount_status,
                    'discount_status_label' => $status_label,
                    'discount_amount' => $remise_amount,
                    'discount_amount_formatted' => number_format( $remise_amount, 2, ',', '' ) . '€/mois',
                    'is_simulation' => false
                );
            }

            // 4) Autres statuts → 0 par défaut
            if ( $workflow_status ) {
                return array(
                    'discount_status' => $workflow_status,
                    'discount_status_label' => $this->get_workflow_status_label_client( $workflow_status ),
                    'discount_amount' => 0,
                    'discount_amount_formatted' => '0,00€/mois',
                    'is_simulation' => true
                );
            }
            
            // Fallback vers données mockées
            return $this->get_client_mock_discount_data( $order_id );
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur lors de la récupération des données client réelles',
                array(
                    'order_id' => $order_id,
                    'error' => $e->getMessage()
                ),
                'account-data-provider'
            );
            
            return $this->get_client_mock_discount_data( $order_id );
        }
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération des vraies données de filleuls pour calculs
     * 
     * @param int $user_subscription_id ID de l'abonnement parrain
     * @return array Données des filleuls avec calculs réels
     */
    private function get_real_referrals_data( $user_subscription_id ) {
        // Utiliser la même logique que get_parrainage_data mais pour un seul parrain
        $plugin_instance = $this->get_plugin_instance();
        if ( ! $plugin_instance ) {
            return array();
        }
        
        // Récupération via ParrainageDataProvider pour cohérence
        $parrainage_data_provider = new ParrainageDataProvider( $this->logger );
        
        $filters = array(
            'parrain_subscription_id' => $user_subscription_id
        );
        
        // CORRECTION v2.14.0 : Appel compatible avec toutes les versions
        try {
            // CORRECTION v2.9.0 : Spécifier pagination pour éviter les valeurs par défaut
            $pagination = array(
                'page' => 1,
                'per_page' => 100, // Suffisant pour récupérer tous les filleuls d'un parrain
                'order_by' => 'p.post_date',
                'order' => 'DESC'
            );
            
            // Tentative avec pagination (version récente)
            $data = $parrainage_data_provider->get_parrainage_data( $filters, $pagination );
            
        } catch ( ArgumentCountError $e ) {
            // Fallback sans pagination (version ancienne)
            $this->logger->info( 
                'ParrainageDataProvider version ancienne détectée - fallback sans pagination',
                array(
                    'user_subscription_id' => $user_subscription_id,
                    'error' => $e->getMessage()
                ),
                'account-data-provider'
            );
            $data = $parrainage_data_provider->get_parrainage_data( $filters );
            
        } catch ( Exception $e ) {
            // Fallback complet en cas d'autre erreur
            $this->logger->error(
                'Erreur lors de la récupération des données de parrainage',
                array(
                    'user_subscription_id' => $user_subscription_id,
                    'error' => $e->getMessage()
                ),
                'account-data-provider'
            );
            return array();
        }
        
        // Extraire les filleuls pour ce parrain spécifique
        if ( isset( $data['parrains'] ) ) {
            // DEBUG v2.9.0 : Log structure complète des parrains
            $this->logger->info( 
                'ANALYSE STRUCTURE PARRAINS',
                array(
                    'user_subscription_id_recherche' => $user_subscription_id,
                    'parrains_count' => count( $data['parrains'] ),
                    'parrains_details' => array_map( function( $parrain ) {
                        return array(
                            'subscription_id' => $parrain['parrain']['subscription_id'] ?? 'MISSING',
                            'filleuls_count' => count( $parrain['filleuls'] ?? array() ),
                            'filleuls_sample' => array_slice( $parrain['filleuls'] ?? array(), 0, 1 )
                        );
                    }, $data['parrains'] )
                ),
                'mes-parrainages-debug'
            );
            
            foreach ( $data['parrains'] as $parrain ) {
                $parrain_subscription_id = $parrain['parrain']['subscription_id'] ?? 'MISSING';
                
                // DEBUG v2.9.0 : Log chaque comparaison
                $this->logger->info( 
                    'COMPARAISON PARRAIN',
                    array(
                        'recherche' => intval( $user_subscription_id ),
                        'trouve' => intval( $parrain_subscription_id ),
                        'match' => ( intval( $parrain_subscription_id ) === intval( $user_subscription_id ) ),
                        'filleuls_count' => count( $parrain['filleuls'] ?? array() )
                    ),
                    'mes-parrainages-debug'
                );
                
                $this->logger->info( '🚀 APRÈS COMPARAISON PARRAIN - DÉBUT TRAITEMENT', array(
                    'user_subscription_id' => $user_subscription_id,
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'match_found' => ( intval( $parrain_subscription_id ) === intval( $user_subscription_id ) )
                ), 'mes-parrainages-debug' );
                
                if ( intval( $parrain_subscription_id ) === intval( $user_subscription_id ) ) {
                    $filleuls = $parrain['filleuls'] ?? array();
                    
                    $this->logger->info( '✅ MATCH TROUVÉ - Retour filleuls', array(
                        'filleuls_count' => count( $filleuls ),
                        'filleuls_sample' => array_slice( $filleuls, 0, 1 )  // Premier filleul pour debug
                    ), 'mes-parrainages-debug' );
                    
                    return $filleuls;
                }
            }
        }
        
        // DEBUG v2.9.0 : Aucun parrain trouvé
        $this->logger->warning( 
            'AUCUN PARRAIN TROUVÉ',
            array(
                'user_subscription_id' => $user_subscription_id,
                'data_structure' => array(
                    'has_parrains' => isset( $data['parrains'] ),
                    'parrains_count' => isset( $data['parrains'] ) ? count( $data['parrains'] ) : 0,
                    'data_keys' => array_keys( $data )
                )
            ),
            'mes-parrainages-debug'
        );
        
        return array();
    }
    
    /**
     * NOUVEAU v2.6.0 : Actions en attente basées sur données réelles
     * 
     * @param array $real_referrals Données réelles des filleuls
     * @return array Actions en attente
     */
    private function get_real_pending_actions( $real_referrals ) {
        $actions = array();
        
        foreach ( $real_referrals as $referral ) {
            // CORRECTION v2.9.0 : Utiliser 'discount_info' (clé de ParrainageDataProvider)
            $status = $referral['discount_info']['discount_status'] ?? 'unknown';
            
            switch ( $status ) {
                case 'pending':
                    $actions[] = array( 
                        'type' => 'pending',
                        'message' => sprintf( '%s : Remise en cours de calcul', $referral['nom'] ?? 'Filleul' )
                    );
                    break;
                case 'error':
                    $actions[] = array( 
                        'type' => 'error',
                        'message' => sprintf( '%s : Erreur de calcul, contactez le support', $referral['nom'] ?? 'Filleul' )
                    );
                    break;
                case 'cron_failed':
                    $actions[] = array( 
                        'type' => 'warning',
                        'message' => sprintf( '%s : Calcul en attente (problème technique)', $referral['nom'] ?? 'Filleul' )
                    );
                    break;
            }
        }
        
        return $actions;
    }
    
    /**
     * NOUVEAU v2.6.0 : Fallback vers données mockées pour get_savings_summary
     * 
     * @param int $user_subscription_id ID de l'abonnement
     * @return array Résumé mocké
     */
    private function get_mock_savings_summary( $user_subscription_id ) {
        mt_srand( intval( $user_subscription_id ) );
        
        $active_discounts = mt_rand( 1, 4 );
        $total_referrals = $active_discounts + mt_rand( 0, 2 );
        $monthly_savings = $active_discounts * ( mt_rand( 500, 1500 ) / 100 );
        
        return array(
            'active_discounts' => $active_discounts,
            'total_referrals' => $total_referrals,
            'monthly_savings' => round( $monthly_savings, 2 ),
            'yearly_projection' => round( $monthly_savings * 12, 2 ),
            'total_savings_to_date' => round( $monthly_savings * mt_rand( 6, 24 ), 2 ), // 6-24 mois d'économies simulées
            'currency' => get_woocommerce_currency_symbol(),
            'next_billing' => array(
                'date' => date( 'd/m/Y', strtotime( '+1 month' ) ),
                'amount' => round( 89.99 - $monthly_savings, 2 ),
                'original_amount' => 89.99
            ),
            'pending_actions' => $this->get_mock_pending_actions( $active_discounts, $total_referrals )
        );
    }
    
    /**
     * NOUVEAU v2.9.3 : Récupérer la vraie date de prochaine facturation WooCommerce
     * 
     * @param int $user_subscription_id ID de l'abonnement utilisateur
     * @return string Date formatée DD-MM-YYYY
     */
    private function get_real_next_billing_date( $user_subscription_id ) {
        try {
            $subscription = wcs_get_subscription( $user_subscription_id );
            if ( ! $subscription ) {
                return date( 'd-m-Y', strtotime( '+1 month' ) );
            }
            
            // Récupérer la vraie date de prochaine facturation
            $next_payment_timestamp = $subscription->get_time( 'next_payment' );
            if ( $next_payment_timestamp ) {
                return date( 'd-m-Y', $next_payment_timestamp );
            }
            
            // Fallback : chercher dans les métadonnées
            $next_payment_meta = get_post_meta( $user_subscription_id, '_schedule_next_payment', true );
            if ( $next_payment_meta ) {
                return date( 'd-m-Y', strtotime( $next_payment_meta ) );
            }
            
            // Dernier fallback
            return date( 'd-m-Y', strtotime( '+1 month' ) );
            
        } catch ( Exception $e ) {
            $this->logger->warning( 'Erreur récupération date prochaine facturation', array(
                'user_subscription_id' => $user_subscription_id,
                'error' => $e->getMessage()
            ), 'account-data-provider' );
            
            return date( 'd-m-Y', strtotime( '+1 month' ) );
        }
    }
    
    /**
     * NOUVEAU v2.6.0 : Labels des statuts workflow côté client
     * 
     * @param string $status Statut workflow
     * @return string Label pour affichage client
     */
    private function get_workflow_status_label_client( $status ) {
        $labels = array(
            // NOUVEAU v2.7.0 : Labels pour application réelle côté client
            'applied' => 'Remise active',
            'application_failed' => 'Échec activation',
            'active' => 'Remise en cours',
            
            // Labels existants conservés
            'calculated' => 'Calculé (Test v2.6.0)',
            'simulated' => 'Simulé (Test v2.6.0)',
            'pending' => 'En cours de calcul',
            'scheduled' => 'Programmé (activation prochaine)',
            'error' => 'Erreur de calcul',
            'cron_failed' => 'En attente technique'
        );
        return $labels[$status] ?? 'Statut inconnu';
    }
    
    /**
     * NOUVEAU v2.7.5 : Récupération du montant de remise configuré pour une commande
     * 
     * @param int $order_id ID de la commande
     * @return float Montant de la remise configurée
     */
    private function get_configured_discount_amount( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return 0.0;
        }
        
        // Récupérer la configuration des produits
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            if ( isset( $products_config[ $product_id ]['remise_parrain'] ) ) {
                $remise = $products_config[ $product_id ]['remise_parrain'];
                
                // Gérer les formats de configuration uniformément 
                if ( is_array( $remise ) && isset( $remise['montant'] ) ) {
                    return floatval( $remise['montant'] );
                } else {
                    return floatval( $remise );
                }
            }
        }
        
        // Si aucune remise spécifique, vérifier la config par défaut
        if ( isset( $products_config['default']['remise_parrain'] ) ) {
            $remise = $products_config['default']['remise_parrain'];
            if ( is_array( $remise ) && isset( $remise['montant'] ) ) {
                return floatval( $remise['montant'] );
            } else {
                return floatval( $remise );
            }
        }
        
        return 0.0;
    }

    /**
     * NOUVEAU v2.8.2-fix12 : Vérification de l'état du filleul pour calculer la remise parrain
     * 
     * @param int $order_id ID de la commande du filleul
     * @return array État de l'abonnement du filleul
     */
    private function get_filleul_subscription_status( $order_id ) {
        global $wpdb;
        
        // Trouver l'abonnement associé à cette commande
        $subscription_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT ID 
            FROM wp_posts 
            WHERE post_parent = %d 
            AND post_type = 'shop_subscription'
            LIMIT 1
        ", $order_id ) );
        
        if ( ! $subscription_id ) {
            return array(
                'is_active' => false,
                'status' => 'no_subscription',
                'status_label' => 'pas d\'abonnement'
            );
        }
        
        // Récupérer le statut de l'abonnement
        $subscription_status = $wpdb->get_var( $wpdb->prepare( "
            SELECT post_status 
            FROM wp_posts 
            WHERE ID = %d
        ", $subscription_id ) );
        
        $is_active = in_array( $subscription_status, array( 'wc-active', 'wc-on-hold' ), true );
        
        $status_labels = array(
            'wc-active' => 'actif',
            'wc-cancelled' => 'annulé',
            'wc-on-hold' => 'suspendu',
            'wc-expired' => 'expiré'
        );
        
        $status_label = $status_labels[ $subscription_status ] ?? $subscription_status;
        
        $this->logger->info( 'État filleul vérifié pour remise parrain', array(
            'order_id' => $order_id,
            'subscription_id' => $subscription_id,
            'status' => $subscription_status,
            'is_active' => $is_active,
            'status_label' => $status_label
        ), 'account-data-provider' );
        
        return array(
            'is_active' => $is_active,
            'status' => $subscription_status,
            'status_label' => $status_label,
            'subscription_id' => $subscription_id
        );
    }

    /**
     * LEGACY v2.8.2-fix11 : Vérification de l'état RÉEL de la remise parrain
     * 
     * @return array État réel de la remise
     */
    private function get_real_parrain_discount_status() {
        $user_id = get_current_user_id();
        $subscription_id = $this->get_user_subscription_id( $user_id );
        
        if ( ! $subscription_id ) {
            return array(
                'is_active' => false,
                'reason' => 'Aucun abonnement trouvé',
                'amount' => 0
            );
        }
        
        global $wpdb;
        
        // Vérifier l'état réel de la remise dans la base
        $result = $wpdb->get_row( $wpdb->prepare( "
            SELECT 
                pm_active.meta_value as remise_active,
                pm_amount.meta_value as montant_remise,
                pm_status.meta_value as statut_remise
            FROM wp_postmeta pm_active
            LEFT JOIN wp_postmeta pm_amount ON pm_active.post_id = pm_amount.post_id AND pm_amount.meta_key = '_tb_parrainage_discount_amount'
            LEFT JOIN wp_postmeta pm_status ON pm_active.post_id = pm_status.post_id AND pm_status.meta_key = '_parrain_discount_status'
            WHERE pm_active.post_id = %d 
            AND pm_active.meta_key = '_tb_parrainage_discount_active'
        ", $subscription_id ), ARRAY_A );
        
        if ( ! $result ) {
            return array(
                'is_active' => false,
                'reason' => 'Pas de données de remise',
                'amount' => 0
            );
        }
        
        $is_active = ( $result['remise_active'] === '1' );
        $status = $result['statut_remise'] ?? 'unknown';
        $amount = floatval( $result['montant_remise'] ?? 0 );
        
        $reason = '';
        if ( ! $is_active ) {
            $reason = $status === 'suspended' ? 'Remise suspendue' : 'Remise inactive';
        }
        
        $this->logger->info( 'État réel remise parrain vérifié', array(
            'subscription_id' => $subscription_id,
            'is_active' => $is_active,
            'status' => $status,
            'amount' => $amount,
            'reason' => $reason
        ), 'account-data-provider' );
        
        return array(
            'is_active' => $is_active,
            'reason' => $reason,
            'amount' => $amount,
            'status' => $status
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
} 