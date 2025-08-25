<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Collecteur de données pour analytics
 * 
 * Responsabilité unique : Collecte données de base (parrains, filleuls, revenus)
 * Principe SRP : Séparation collecte vs calculs avancés
 * 
 * @since 2.12.0
 */
class AnalyticsDataCollector {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'analytics-data-collector';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        
        $this->logger->info(
            'AnalyticsDataCollector initialisé',
            array( 'version' => '2.12.0' ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Compter parrains actifs
     * 
     * @return int Nombre de parrains avec remise active
     */
    public function count_active_parrains(): int {
        
        global $wpdb;
        
        $query = "
            SELECT COUNT(DISTINCT pm_status.post_id) as count
            FROM {$wpdb->postmeta} pm_status
            WHERE pm_status.meta_key = '_parrain_discount_status' 
            AND pm_status.meta_value = 'active'
        ";
        
        $result = $wpdb->get_var( $query );
        
        $this->logger->info(
            'Comptage parrains actifs',
            array( 'count' => $result ),
            self::LOG_CHANNEL
        );
        
        return intval( $result );
    }
    
    /**
     * Compter filleuls actifs
     * 
     * @return int Nombre de filleuls avec abonnement actif
     */
    public function count_active_filleuls(): int {
        
        global $wpdb;
        
        // Mise à jour: compter les filleuls actifs via le statut du post 'shop_subscription'
        // (les métas _subscription_status ne sont pas toujours présentes)
        $query = "
            SELECT COUNT(DISTINCT p.ID) as count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_filleul ON p.ID = pm_filleul.post_id
                AND pm_filleul.meta_key = '_billing_parrain_code'
                AND pm_filleul.meta_value <> ''
            JOIN {$wpdb->posts} sub ON sub.post_parent = p.ID 
                AND sub.post_type = 'shop_subscription' 
                AND sub.post_status = 'wc-active'
        ";
        
        $result = $wpdb->get_var( $query );
        
        $this->logger->info(
            'Comptage filleuls actifs',
            array( 'count' => $result ),
            self::LOG_CHANNEL
        );
        
        return intval( $result );
    }
    
    /**
     * Revenus mensuels des filleuls
     * 
     * @return float Revenus totaux mensuels générés par filleuls
     */
    public function get_monthly_filleuls_revenue(): float {
        
        global $wpdb;
        
        // Revenu mensuel HT généré par les filleuls (commandes du mois)
        $query = "
            SELECT COALESCE(SUM(
                CAST(pm_total.meta_value AS DECIMAL(10,2)) - 
                COALESCE(CAST(pm_tax.meta_value AS DECIMAL(10,2)), 0)
            ), 0) as revenue
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_code ON p.ID = pm_code.post_id 
                AND pm_code.meta_key = '_billing_parrain_code' 
                AND pm_code.meta_value <> ''
            JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id 
                AND pm_total.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} pm_tax ON p.ID = pm_tax.post_id 
                AND pm_tax.meta_key = '_order_tax'
            WHERE p.post_type = 'shop_order'
              AND p.post_status IN ('wc-completed','wc-processing')
              AND MONTH(p.post_date) = MONTH(NOW())
              AND YEAR(p.post_date) = YEAR(NOW())
        ";
        
        $result = $wpdb->get_var( $query );
        
        $this->logger->info(
            'Calcul revenus mensuels filleuls (HT, commandes mois courant)',
            array( 'revenue' => $result, 'sql' => $query ),
            self::LOG_CHANNEL
        );
        
        return floatval( $result );
    }
    
    /**
     * Remises mensuelles accordées aux parrains
     * 
     * @return float Total remises mensuelles
     */
    public function get_monthly_parrains_discounts(): float {
        
        global $wpdb;
        
        $query = "
            SELECT COALESCE(SUM(CAST(pm_amount.meta_value AS DECIMAL(10,2))), 0) as discounts
            FROM {$wpdb->postmeta} pm_amount
            JOIN {$wpdb->postmeta} pm_status ON pm_amount.post_id = pm_status.post_id 
            AND pm_status.meta_key = '_parrain_discount_status' 
            AND pm_status.meta_value = 'active'
            WHERE pm_amount.meta_key = '_tb_parrainage_discount_amount'
        ";
        
        $result = $wpdb->get_var( $query );
        
        $this->logger->info(
            'Calcul remises mensuelles parrains',
            array( 'discounts' => $result ),
            self::LOG_CHANNEL
        );
        
        return floatval( $result );
    }
    
    /**
     * Statistiques de conversion
     * 
     * @param int $days Période d'analyse
     * @return array Stats conversion codes parrain
     */
    public function get_conversion_stats( int $days = 30 ): array {
        
        global $wpdb;
        
        // Codes parrain générés (approximation via logs)
        $codes_generated = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}tb_parrainage_logs 
            WHERE datetime >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            AND message LIKE '%code parrain%'
            AND source = 'parrainage'
        " );
        
        // Codes utilisés
        $codes_used = $wpdb->get_var( "
            SELECT COUNT(DISTINCT pm.meta_value)
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->prefix}tb_parrainage_logs l ON l.datetime >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            WHERE pm.meta_key = '_billing_parrain_code'
            AND pm.meta_value != ''
        " );
        
        $conversion_rate = $codes_generated > 0 ? ( $codes_used / $codes_generated ) * 100 : 0;
        
        $stats = array(
            'codes_generated' => intval( $codes_generated ),
            'codes_used' => intval( $codes_used ),
            'conversion_rate' => round( $conversion_rate, 2 ),
            'period_days' => $days
        );
        
        $this->logger->info(
            'Statistiques conversion calculées',
            $stats,
            self::LOG_CHANNEL
        );
        
        return $stats;
    }
    
    /**
     * Activité récente
     * 
     * @param int $limit Nombre d'événements
     * @return array Événements récents
     */
    public function get_recent_activity( int $limit = 20 ): array {
        
        global $wpdb;
        
        $query = "
            SELECT 
                datetime,
                source,
                message,
                level
            FROM {$wpdb->prefix}tb_parrainage_logs 
            WHERE datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND (
                message LIKE '%filleul%' 
                OR message LIKE '%parrain%' 
                OR message LIKE '%discount%'
                OR message LIKE '%expir%'
            )
            ORDER BY datetime DESC
            LIMIT {$limit}
        ";
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        $activities = array();
        foreach ( $results as $row ) {
            $activities[] = array(
                'datetime' => $row['datetime'],
                'source' => $row['source'],
                'message' => $row['message'],
                'level' => $row['level']
            );
        }
        
        $this->logger->info(
            'Activité récente récupérée',
            array( 'limit' => $limit, 'found' => count( $activities ) ),
            self::LOG_CHANNEL
        );
        
        return $activities;
    }
}
