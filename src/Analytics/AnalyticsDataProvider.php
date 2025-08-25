<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fournisseur de données pour analytics (version allégée)
 * 
 * Responsabilité unique : Requêtes SQL avancées pour évolution et comparaisons
 * Principe SRP : Séparation données de base vs données évolution/comparaison
 * 
 * @since 2.12.0
 */
class AnalyticsDataProvider {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Collecteur données de base
     * @var AnalyticsDataCollector
     */
    private $data_collector;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'analytics-data-provider';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        $this->data_collector = new AnalyticsDataCollector( $logger );
        
        $this->logger->info(
            'AnalyticsDataProvider initialisé avec collecteur',
            array( 'version' => '2.12.0' ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Délégation méthodes collecteur
     */
    public function count_active_parrains(): int {
        return $this->data_collector->count_active_parrains();
    }
    
    public function count_active_filleuls(): int {
        return $this->data_collector->count_active_filleuls();
    }
    
    public function get_monthly_filleuls_revenue(): float {
        return $this->data_collector->get_monthly_filleuls_revenue();
    }
    
    /**
     * Revenus mensuels TOTAUX HT (Hors Taxes)
     * 
     * @return float Revenus HT mensuels
     */
    public function get_monthly_total_revenue(): float {
        global $wpdb;
        
        // CORRECTION: Calcul revenus HT mensuels (TTC - Taxes)
        $query = "
            SELECT COALESCE(SUM(
                CAST(pm_total.meta_value AS DECIMAL(10,2)) - 
                COALESCE(CAST(pm_tax.meta_value AS DECIMAL(10,2)), 0)
            ), 0) as revenue_ht
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id 
            AND pm_total.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} pm_tax ON p.ID = pm_tax.post_id 
            AND pm_tax.meta_key = '_order_tax'
            WHERE p.post_type IN ('shop_order', 'shop_subscription')
            AND p.post_status IN ('wc-completed', 'wc-active', 'wc-processing')
            AND MONTH(p.post_date) = MONTH(NOW())
            AND YEAR(p.post_date) = YEAR(NOW())
        ";
        
        $result = $wpdb->get_var( $query );
        
        // Logs de diagnostic détaillés
        $posts_total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
        $orders_this_month = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type IN ('shop_order','shop_subscription')
            AND post_status IN ('wc-completed','wc-active','wc-processing')
            AND MONTH(post_date) = MONTH(NOW())
            AND YEAR(post_date) = YEAR(NOW())
        " );
        
        $this->logger->info(
            'DEBUG monthly_total_revenue',
            array(
                'prefix' => $wpdb->prefix,
                'posts_total' => intval( $posts_total ),
                'orders_this_month' => intval( $orders_this_month ),
                'sql' => $query,
                'result_raw' => $result,
                'last_error' => $wpdb->last_error,
            ),
            self::LOG_CHANNEL
        );
        
        // Fallback si la requête SQL retourne 0 dans le contexte WordPress
        $numeric_result = floatval( $result );
        if ( $numeric_result <= 0 ) {
            $fallback_total = 0.0;
            
            // Utiliser WooCommerce API pour sommer (Total - Taxes) des commandes du mois
            if ( function_exists( 'wc_get_orders' ) ) {
                $start = date( 'Y-m-01 00:00:00' );
                $end   = date( 'Y-m-t 23:59:59' );
                
                $order_ids = wc_get_orders( array(
                    'status' => array( 'completed', 'processing' ),
                    'date_created' => $start . '...' . $end,
                    'return' => 'ids',
                    'limit' => -1,
                ) );
                
                foreach ( $order_ids as $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $fallback_total += (float) $order->get_total() - (float) $order->get_total_tax();
                    }
                }
            }
            
            $this->logger->info(
                'Fallback WooCommerce utilisé pour revenus mensuels HT',
                array( 'fallback_total' => $fallback_total, 'orders_count' => isset( $order_ids ) ? count( $order_ids ) : 0 ),
                self::LOG_CHANNEL
            );
            
            $numeric_result = $fallback_total;
        }
        
        $this->logger->info(
            'Calcul revenus mensuels HT Analytics',
            array( 'revenue_ht' => $numeric_result, 'formula' => 'TTC - Taxes (SQL) ou Fallback WooCommerce' ),
            self::LOG_CHANNEL
        );
        
        return $numeric_result;
    }
    
    public function get_monthly_parrains_discounts(): float {
        return $this->data_collector->get_monthly_parrains_discounts();
    }
    
    public function get_conversion_stats( int $days = 30 ): array {
        return $this->data_collector->get_conversion_stats( $days );
    }
    
    public function get_recent_activity( int $limit = 20 ): array {
        return $this->data_collector->get_recent_activity( $limit );
    }
    
    /**
     * Évolution des revenus sur période
     * 
     * @param int $days Nombre de jours à analyser
     * @return array Données évolution par mois
     */
    public function get_revenue_evolution( int $days = 180 ): array {
        
        global $wpdb;
        
        $query = "
            SELECT 
                DATE_FORMAT(l.datetime, '%Y-%m') as month,
                COUNT(CASE WHEN l.message LIKE '%filleul%' THEN 1 END) as new_filleuls,
                COUNT(CASE WHEN l.message LIKE '%parrain%' AND l.message LIKE '%discount%' THEN 1 END) as new_parrains
            FROM {$wpdb->prefix}tb_parrainage_logs l
            WHERE l.datetime >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            AND (l.message LIKE '%filleul%' OR l.message LIKE '%parrain%')
            GROUP BY DATE_FORMAT(l.datetime, '%Y-%m')
            ORDER BY month ASC
        ";
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        $evolution_data = array();
        foreach ( $results as $row ) {
            $evolution_data[] = array(
                'month' => $row['month'],
                'new_filleuls' => intval( $row['new_filleuls'] ),
                'new_parrains' => intval( $row['new_parrains'] )
            );
        }
        
        $this->logger->info(
            'Évolution revenus calculée',
            array( 'period_days' => $days, 'months' => count( $evolution_data ) ),
            self::LOG_CHANNEL
        );
        
        return $evolution_data;
    }
    
    /**
     * Top performers parrains
     * 
     * @param int $limit Nombre de parrains à retourner
     * @return array Top parrains par performance
     */
    public function get_top_performers( int $limit = 10 ): array {
        
        global $wpdb;
        
        $query = "
            SELECT 
                pm_parrain.meta_value as parrain_id,
                pm_email.meta_value as parrain_email,
                pm_nom.meta_value as parrain_nom,
                COUNT(pm_filleul.post_id) as nb_filleuls,
                COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))), 0) as revenue_generated
            FROM {$wpdb->postmeta} pm_filleul
            JOIN {$wpdb->postmeta} pm_parrain ON pm_filleul.post_id = pm_parrain.post_id 
            AND pm_parrain.meta_key = '_parrain_user_id'
            LEFT JOIN {$wpdb->postmeta} pm_email ON pm_filleul.post_id = pm_email.post_id 
            AND pm_email.meta_key = '_parrain_email'
            LEFT JOIN {$wpdb->postmeta} pm_nom ON pm_filleul.post_id = pm_nom.post_id 
            AND pm_nom.meta_key = '_parrain_nom_complet'
            LEFT JOIN {$wpdb->postmeta} pm_total ON pm_filleul.post_id = pm_total.post_id 
            AND pm_total.meta_key = '_order_total'
            WHERE pm_filleul.meta_key = '_billing_parrain_code'
            GROUP BY pm_parrain.meta_value, pm_email.meta_value, pm_nom.meta_value
            ORDER BY revenue_generated DESC, nb_filleuls DESC
            LIMIT {$limit}
        ";
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        $performers = array();
        foreach ( $results as $row ) {
            $performers[] = array(
                'parrain_id' => intval( $row['parrain_id'] ),
                'parrain_email' => $row['parrain_email'] ?: 'N/A',
                'parrain_nom' => $row['parrain_nom'] ?: 'N/A',
                'nb_filleuls' => intval( $row['nb_filleuls'] ),
                'revenue_generated' => floatval( $row['revenue_generated'] )
            );
        }
        
        $this->logger->info(
            'Top performers calculés',
            array( 'limit' => $limit, 'found' => count( $performers ) ),
            self::LOG_CHANNEL
        );
        
        return $performers;
    }
    
    /**
     * Données comparaison périodes
     * 
     * @param string $start_date Date début (Y-m-d)
     * @param string $end_date Date fin (Y-m-d)
     * @param string $compare_start_date Date début comparaison (Y-m-d)
     * @param string $compare_end_date Date fin comparaison (Y-m-d)
     * @return array Comparaison périodes
     */
    public function get_period_comparison( 
        string $start_date, 
        string $end_date, 
        string $compare_start_date, 
        string $compare_end_date 
    ): array {
        
        // Période actuelle
        $current_stats = $this->get_period_stats( $start_date, $end_date );
        
        // Période de comparaison
        $compare_stats = $this->get_period_stats( $compare_start_date, $compare_end_date );
        
        // Calcul évolutions
        $comparison = array(
            'current_period' => array(
                'start_date' => $start_date,
                'end_date' => $end_date,
                'stats' => $current_stats
            ),
            'compare_period' => array(
                'start_date' => $compare_start_date,
                'end_date' => $compare_end_date,
                'stats' => $compare_stats
            ),
            'evolution' => array(
                'filleuls_change' => $this->calculate_change_percentage( 
                    $compare_stats['filleuls'], 
                    $current_stats['filleuls'] 
                ),
                'parrains_change' => $this->calculate_change_percentage( 
                    $compare_stats['parrains'], 
                    $current_stats['parrains'] 
                ),
                'revenue_change' => $this->calculate_change_percentage( 
                    $compare_stats['revenue'], 
                    $current_stats['revenue'] 
                )
            )
        );
        
        $this->logger->info(
            'Comparaison périodes calculée',
            array(
                'current_period' => $start_date . ' → ' . $end_date,
                'compare_period' => $compare_start_date . ' → ' . $compare_end_date
            ),
            self::LOG_CHANNEL
        );
        
        return $comparison;
    }
    
    /**
     * Données pour export brut
     * 
     * @param string $export_type Type d'export (filleuls, parrains, logs)
     * @return array Données brutes
     */
    public function get_raw_export_data( string $export_type ): array {
        
        global $wpdb;
        
        switch ( $export_type ) {
            
            case 'filleuls':
                $query = "
                    SELECT 
                        pm_filleul.post_id as filleul_id,
                        pm_filleul.meta_value as code_parrain,
                        pm_total.meta_value as montant_mensuel,
                        pm_status.meta_value as status,
                        pm_email.meta_value as parrain_email
                    FROM {$wpdb->postmeta} pm_filleul
                    LEFT JOIN {$wpdb->postmeta} pm_total ON pm_filleul.post_id = pm_total.post_id 
                    AND pm_total.meta_key = '_order_total'
                    LEFT JOIN {$wpdb->postmeta} pm_status ON pm_filleul.post_id = pm_status.post_id 
                    AND pm_status.meta_key = '_subscription_status'
                    LEFT JOIN {$wpdb->postmeta} pm_email ON pm_filleul.post_id = pm_email.post_id 
                    AND pm_email.meta_key = '_parrain_email'
                    WHERE pm_filleul.meta_key = '_billing_parrain_code'
                ";
                break;
                
            case 'parrains':
                $query = "
                    SELECT 
                        pm_status.post_id as parrain_id,
                        pm_status.meta_value as discount_status,
                        pm_amount.meta_value as discount_amount,
                        pm_start.meta_value as discount_start,
                        pm_end.meta_value as discount_end
                    FROM {$wpdb->postmeta} pm_status
                    LEFT JOIN {$wpdb->postmeta} pm_amount ON pm_status.post_id = pm_amount.post_id 
                    AND pm_amount.meta_key = '_tb_parrainage_discount_amount'
                    LEFT JOIN {$wpdb->postmeta} pm_start ON pm_status.post_id = pm_start.post_id 
                    AND pm_start.meta_key = '_tb_parrainage_discount_start'
                    LEFT JOIN {$wpdb->postmeta} pm_end ON pm_status.post_id = pm_end.post_id 
                    AND pm_end.meta_key = '_tb_parrainage_discount_end_date'
                    WHERE pm_status.meta_key = '_parrain_discount_status'
                ";
                break;
                
            case 'logs':
                $query = "
                    SELECT 
                        datetime,
                        level,
                        source,
                        message
                    FROM {$wpdb->prefix}tb_parrainage_logs 
                    WHERE datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY datetime DESC
                ";
                break;
                
            default:
                return array();
        }
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        $this->logger->info(
            'Données export brutes récupérées',
            array( 'type' => $export_type, 'rows' => count( $results ) ),
            self::LOG_CHANNEL
        );
        
        return $results;
    }
    
    /**
     * Statistiques pour une période donnée
     * 
     * @param string $start_date Date début
     * @param string $end_date Date fin
     * @return array Stats période
     */
    private function get_period_stats( string $start_date, string $end_date ): array {
        
        global $wpdb;
        
        // Nouveaux filleuls
        $filleuls = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}tb_parrainage_logs 
            WHERE datetime BETWEEN '{$start_date}' AND '{$end_date}'
            AND message LIKE '%filleul%'
            AND source LIKE '%parrainage%'
        " );
        
        // Nouveaux parrains
        $parrains = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}tb_parrainage_logs 
            WHERE datetime BETWEEN '{$start_date}' AND '{$end_date}'
            AND message LIKE '%parrain%'
            AND message LIKE '%discount%'
        " );
        
        // Approximation revenus (basée sur activité logs)
        $revenue = $wpdb->get_var( "
            SELECT COUNT(*) * 56.99
            FROM {$wpdb->prefix}tb_parrainage_logs 
            WHERE datetime BETWEEN '{$start_date}' AND '{$end_date}'
            AND message LIKE '%filleul%'
        " );
        
        return array(
            'filleuls' => intval( $filleuls ),
            'parrains' => intval( $parrains ),
            'revenue' => floatval( $revenue )
        );
    }
    
    /**
     * Calculer pourcentage de changement
     * 
     * @param float $old_value Ancienne valeur
     * @param float $new_value Nouvelle valeur
     * @return float Pourcentage changement
     */
    private function calculate_change_percentage( float $old_value, float $new_value ): float {
        
        if ( $old_value == 0 ) {
            return $new_value > 0 ? 100 : 0;
        }
        
        return round( ( ( $new_value - $old_value ) / $old_value ) * 100, 2 );
    }
}