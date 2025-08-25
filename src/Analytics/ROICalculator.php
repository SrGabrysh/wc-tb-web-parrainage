<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculateur de ROI et métriques business
 * 
 * Responsabilité unique : Calculs ROI, métriques financières et KPI
 * Principe SRP : Séparation calculs métier vs accès données
 * 
 * @since 2.12.0
 */
class ROICalculator {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Data provider pour données
     * @var AnalyticsDataProvider
     */
    private $data_provider;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'roi-calculator';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     * @param AnalyticsDataProvider $data_provider Provider données
     */
    public function __construct( $logger, AnalyticsDataProvider $data_provider ) {
        $this->logger = $logger;
        $this->data_provider = $data_provider;
        
        $this->logger->info(
            'ROICalculator initialisé avec data provider',
            array( 'version' => '2.12.0' ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Calculer métriques ROI pour une période
     * 
     * @param int $period_days Nombre de jours
     * @return array Métriques ROI complètes
     */
    public function calculate_roi_metrics( int $period_days = 30 ): array {
        
        // Données de base
        $monthly_revenue = $this->data_provider->get_monthly_filleuls_revenue();
        $monthly_discounts = $this->data_provider->get_monthly_parrains_discounts();
        $active_parrains = $this->data_provider->count_active_parrains();
        $active_filleuls = $this->data_provider->count_active_filleuls();
        
        // Calculs ROI
        $net_profit = $monthly_revenue - $monthly_discounts;
        $roi_percentage = $monthly_discounts > 0 ? ( $net_profit / $monthly_discounts ) * 100 : 0;
        
        // Métriques avancées
        $avg_revenue_per_filleul = $active_filleuls > 0 ? $monthly_revenue / $active_filleuls : 0;
        $avg_discount_per_parrain = $active_parrains > 0 ? $monthly_discounts / $active_parrains : 0;
        $filleuls_per_parrain = $active_parrains > 0 ? $active_filleuls / $active_parrains : 0;
        
        // Coût d'acquisition approximatif (remise première année)
        $acquisition_cost = $monthly_discounts * 12; // Approximation année complète
        $customer_lifetime_value = $avg_revenue_per_filleul * 24; // CLV 2 ans
        $ltv_cac_ratio = $acquisition_cost > 0 ? $customer_lifetime_value / $acquisition_cost : 0;
        
        $metrics = array(
            'period_days' => $period_days,
            'revenue' => array(
                'monthly_revenue' => round( $monthly_revenue, 2 ),
                'monthly_discounts' => round( $monthly_discounts, 2 ),
                'net_profit' => round( $net_profit, 2 ),
                'annual_projection' => round( $net_profit * 12, 2 )
            ),
            'roi' => array(
                'roi_percentage' => round( $roi_percentage, 2 ),
                'ltv_cac_ratio' => round( $ltv_cac_ratio, 2 ),
                'payback_months' => $avg_revenue_per_filleul > 0 ? 
                    round( ( $avg_discount_per_parrain * 12 ) / $avg_revenue_per_filleul, 1 ) : 0
            ),
            'performance' => array(
                'avg_revenue_per_filleul' => round( $avg_revenue_per_filleul, 2 ),
                'avg_discount_per_parrain' => round( $avg_discount_per_parrain, 2 ),
                'filleuls_per_parrain' => round( $filleuls_per_parrain, 2 ),
                'conversion_efficiency' => $this->calculate_conversion_efficiency()
            ),
            'counts' => array(
                'active_parrains' => $active_parrains,
                'active_filleuls' => $active_filleuls,
                'total_relationships' => $active_parrains + $active_filleuls
            )
        );
        
        $this->logger->info(
            'Métriques ROI calculées',
            array(
                'period_days' => $period_days,
                'roi_percentage' => $metrics['roi']['roi_percentage'],
                'net_profit' => $metrics['revenue']['net_profit']
            ),
            self::LOG_CHANNEL
        );
        
        return $metrics;
    }
    
    /**
     * ROI du mois en cours
     * 
     * @return float ROI mois actuel
     */
    public function calculate_current_month_roi(): float {
        
        $monthly_revenue = $this->data_provider->get_monthly_filleuls_revenue();
        $monthly_discounts = $this->data_provider->get_monthly_parrains_discounts();
        
        if ( $monthly_discounts == 0 ) {
            return 0;
        }
        
        $net_profit = $monthly_revenue - $monthly_discounts;
        $roi = ( $net_profit / $monthly_discounts ) * 100;
        
        $this->logger->info(
            'ROI mois actuel calculé',
            array(
                'revenue' => $monthly_revenue,
                'discounts' => $monthly_discounts,
                'roi' => round( $roi, 2 )
            ),
            self::LOG_CHANNEL
        );
        
        return round( $roi, 2 );
    }
    
    /**
     * Revenus mensuels actuels
     * 
     * @return float Revenus mensuels
     */
    public function get_monthly_revenue(): float {
        
        return $this->data_provider->get_monthly_filleuls_revenue();
    }
    
    /**
     * Remises mensuelles actuelles
     * 
     * @return float Remises mensuelles
     */
    public function get_monthly_discounts(): float {
        
        return $this->data_provider->get_monthly_parrains_discounts();
    }
    
    /**
     * Calculer efficacité conversion
     * 
     * @return float Score efficacité 0-100
     */
    private function calculate_conversion_efficiency(): float {
        
        $active_parrains = $this->data_provider->count_active_parrains();
        $active_filleuls = $this->data_provider->count_active_filleuls();
        
        // Score basé sur ratio filleuls/parrains et activité
        if ( $active_parrains == 0 ) {
            return 0;
        }
        
        $ratio = $active_filleuls / $active_parrains;
        
        // Score optimal à 2 filleuls par parrain
        $efficiency_score = min( 100, ( $ratio / 2 ) * 100 );
        
        return round( $efficiency_score, 2 );
    }
    
    /**
     * Calculer valeur vie client (CLV)
     * 
     * @param float $monthly_revenue Revenu mensuel moyen
     * @param int $retention_months Mois de rétention moyenne
     * @return float CLV calculée
     */
    public function calculate_customer_lifetime_value( 
        float $monthly_revenue = 0, 
        int $retention_months = 24 
    ): float {
        
        if ( $monthly_revenue == 0 ) {
            $monthly_revenue = $this->get_avg_monthly_revenue_per_customer();
        }
        
        // CLV simple = Revenu mensuel × Mois de rétention
        $clv = $monthly_revenue * $retention_months;
        
        $this->logger->info(
            'CLV calculée',
            array(
                'monthly_revenue' => $monthly_revenue,
                'retention_months' => $retention_months,
                'clv' => round( $clv, 2 )
            ),
            self::LOG_CHANNEL
        );
        
        return round( $clv, 2 );
    }
    
    /**
     * Revenu mensuel moyen par client
     * 
     * @return float Revenu moyen
     */
    private function get_avg_monthly_revenue_per_customer(): float {
        
        $total_revenue = $this->data_provider->get_monthly_filleuls_revenue();
        $total_customers = $this->data_provider->count_active_filleuls();
        
        if ( $total_customers == 0 ) {
            return 0;
        }
        
        return $total_revenue / $total_customers;
    }
    
    /**
     * Calculer coût d'acquisition client (CAC)
     * 
     * @param float $monthly_discounts Remises mensuelles
     * @param int $acquisition_period_months Période acquisition en mois
     * @return float CAC moyen
     */
    public function calculate_customer_acquisition_cost( 
        float $monthly_discounts = 0, 
        int $acquisition_period_months = 12 
    ): float {
        
        if ( $monthly_discounts == 0 ) {
            $monthly_discounts = $this->data_provider->get_monthly_parrains_discounts();
        }
        
        // CAC = Remises totales sur période d'acquisition
        $cac = $monthly_discounts * $acquisition_period_months;
        
        $this->logger->info(
            'CAC calculé',
            array(
                'monthly_discounts' => $monthly_discounts,
                'acquisition_period' => $acquisition_period_months,
                'cac' => round( $cac, 2 )
            ),
            self::LOG_CHANNEL
        );
        
        return round( $cac, 2 );
    }
    
    /**
     * Métriques de performance comparative
     * 
     * @param array $current_metrics Métriques période actuelle
     * @param array $previous_metrics Métriques période précédente
     * @return array Comparaison performance
     */
    public function calculate_performance_comparison( 
        array $current_metrics, 
        array $previous_metrics 
    ): array {
        
        $comparison = array(
            'revenue_growth' => $this->calculate_growth_rate(
                $previous_metrics['revenue']['monthly_revenue'] ?? 0,
                $current_metrics['revenue']['monthly_revenue'] ?? 0
            ),
            'roi_improvement' => $this->calculate_growth_rate(
                $previous_metrics['roi']['roi_percentage'] ?? 0,
                $current_metrics['roi']['roi_percentage'] ?? 0
            ),
            'customers_growth' => $this->calculate_growth_rate(
                $previous_metrics['counts']['active_filleuls'] ?? 0,
                $current_metrics['counts']['active_filleuls'] ?? 0
            ),
            'efficiency_change' => $this->calculate_growth_rate(
                $previous_metrics['performance']['conversion_efficiency'] ?? 0,
                $current_metrics['performance']['conversion_efficiency'] ?? 0
            )
        );
        
        // Score global de performance
        $performance_scores = array_values( $comparison );
        $avg_performance = array_sum( $performance_scores ) / count( $performance_scores );
        
        $comparison['overall_performance'] = round( $avg_performance, 2 );
        
        $this->logger->info(
            'Comparaison performance calculée',
            array( 'overall_performance' => $comparison['overall_performance'] ),
            self::LOG_CHANNEL
        );
        
        return $comparison;
    }
    
    /**
     * Calculer taux de croissance
     * 
     * @param float $old_value Ancienne valeur
     * @param float $new_value Nouvelle valeur
     * @return float Taux croissance en %
     */
    private function calculate_growth_rate( float $old_value, float $new_value ): float {
        
        if ( $old_value == 0 ) {
            return $new_value > 0 ? 100 : 0;
        }
        
        return round( ( ( $new_value - $old_value ) / $old_value ) * 100, 2 );
    }
    
    /**
     * Métriques de santé du système parrainage
     * 
     * @return array Indicateurs santé
     */
    public function calculate_system_health_metrics(): array {
        
        $active_parrains = $this->data_provider->count_active_parrains();
        $active_filleuls = $this->data_provider->count_active_filleuls();
        $monthly_revenue = $this->data_provider->get_monthly_filleuls_revenue();
        $monthly_discounts = $this->data_provider->get_monthly_parrains_discounts();
        
        // Indicateurs santé
        $health_score = 0;
        $health_indicators = array();
        
        // 1. Activité générale (25%)
        if ( $active_parrains > 0 && $active_filleuls > 0 ) {
            $health_score += 25;
            $health_indicators['activity'] = array(
                'status' => 'good',
                'message' => 'Système actif avec parrains et filleuls'
            );
        } else {
            $health_indicators['activity'] = array(
                'status' => 'warning',
                'message' => 'Faible activité système'
            );
        }
        
        // 2. Ratio équilibré (25%)
        $ratio = $active_parrains > 0 ? $active_filleuls / $active_parrains : 0;
        if ( $ratio >= 1 && $ratio <= 3 ) {
            $health_score += 25;
            $health_indicators['ratio'] = array(
                'status' => 'good',
                'message' => 'Ratio parrain/filleul équilibré'
            );
        } else {
            $health_indicators['ratio'] = array(
                'status' => 'warning',
                'message' => 'Ratio parrain/filleul déséquilibré'
            );
        }
        
        // 3. Rentabilité (25%)
        if ( $monthly_revenue > $monthly_discounts ) {
            $health_score += 25;
            $health_indicators['profitability'] = array(
                'status' => 'good',
                'message' => 'Système rentable'
            );
        } else {
            $health_indicators['profitability'] = array(
                'status' => 'critical',
                'message' => 'Système non rentable'
            );
        }
        
        // 4. Croissance (25%)
        $conversion_stats = $this->data_provider->get_conversion_stats( 30 );
        if ( $conversion_stats['conversion_rate'] >= 10 ) {
            $health_score += 25;
            $health_indicators['growth'] = array(
                'status' => 'good',
                'message' => 'Bon taux de conversion'
            );
        } else {
            $health_indicators['growth'] = array(
                'status' => 'warning',
                'message' => 'Faible taux de conversion'
            );
        }
        
        // Statut global
        $global_status = 'critical';
        if ( $health_score >= 75 ) {
            $global_status = 'excellent';
        } elseif ( $health_score >= 50 ) {
            $global_status = 'good';
        } elseif ( $health_score >= 25 ) {
            $global_status = 'warning';
        }
        
        $health_metrics = array(
            'health_score' => $health_score,
            'global_status' => $global_status,
            'indicators' => $health_indicators,
            'recommendations' => $this->get_health_recommendations( $health_indicators )
        );
        
        $this->logger->info(
            'Métriques santé système calculées',
            array(
                'health_score' => $health_score,
                'global_status' => $global_status
            ),
            self::LOG_CHANNEL
        );
        
        return $health_metrics;
    }
    
    /**
     * Recommandations basées sur santé système
     * 
     * @param array $indicators Indicateurs santé
     * @return array Recommandations
     */
    private function get_health_recommendations( array $indicators ): array {
        
        $recommendations = array();
        
        foreach ( $indicators as $key => $indicator ) {
            if ( $indicator['status'] !== 'good' ) {
                switch ( $key ) {
                    case 'activity':
                        $recommendations[] = 'Augmenter promotion codes parrain';
                        break;
                    case 'ratio':
                        $recommendations[] = 'Optimiser stratégie acquisition filleuls';
                        break;
                    case 'profitability':
                        $recommendations[] = 'Revoir structure remises ou prix';
                        break;
                    case 'growth':
                        $recommendations[] = 'Améliorer taux conversion codes';
                        break;
                }
            }
        }
        
        if ( empty( $recommendations ) ) {
            $recommendations[] = 'Système performant, maintenir stratégie actuelle';
        }
        
        return $recommendations;
    }
}
