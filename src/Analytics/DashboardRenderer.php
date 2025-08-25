<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renderer pour dashboard analytics
 * 
 * Responsabilité unique : Affichage et rendu interface dashboard
 * Principe SRP : Séparation affichage vs logique métier
 * 
 * @since 2.12.0
 */
class DashboardRenderer {
    
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
     * Calculateur ROI
     * @var ROICalculator
     */
    private $roi_calculator;
    
    /**
     * Gestionnaire modales d'aide (migré vers TemplateModalManager)
     * @var AdminStatsModalAdapter
     */
    private $modal_adapter;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'dashboard-renderer';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     * @param AnalyticsDataProvider $data_provider Provider données
     * @param ROICalculator $roi_calculator Calculateur ROI
     * @param AdminStatsModalAdapter $modal_adapter Adaptateur modales unifié
     */
    public function __construct( $logger, AnalyticsDataProvider $data_provider, ROICalculator $roi_calculator, AdminStatsModalAdapter $modal_adapter ) {
        $this->logger = $logger;
        $this->data_provider = $data_provider;
        $this->roi_calculator = $roi_calculator;
        $this->modal_adapter = $modal_adapter;
        
        $this->logger->info(
            'DashboardRenderer initialisé avec dépendances',
            array( 'version' => '2.18.0' ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Rendre page analytics complète
     * 
     * @return void
     */
    public function render_analytics_page(): void {
        
        // Vérifier permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes pour accéder aux analytics', 'wc-tb-web-parrainage' ) );
        }
        
        try {
            
            // Récupérer données de base
            $quick_stats = $this->get_dashboard_quick_stats();
            $health_metrics = $this->roi_calculator->calculate_system_health_metrics();
            
            ?>
            <div class="wrap tb-analytics-dashboard">
                <h1 class="wp-heading-inline">
                    <?php esc_html_e( 'Analytics Parrainage', 'wc-tb-web-parrainage' ); ?>
                    <span class="tb-version">v2.12.0</span>
                </h1>
                
                <hr class="wp-header-end">
                
                <!-- Section métriques rapides -->
                <?php $this->render_quick_stats_section( $quick_stats ); ?>
                
                <!-- Section santé système -->
                <?php $this->render_health_section( $health_metrics ); ?>
                
                <!-- Section graphiques -->
                <?php $this->render_charts_section(); ?>
                
                <!-- Section comparaison périodes -->
                <?php $this->render_period_comparison_section(); ?>
                
                <!-- Section exports et rapports -->
                <?php $this->render_reports_section(); ?>
                
                <!-- Section activité récente -->
                <?php $this->render_recent_activity_section(); ?>
                
            </div>
            
            <!-- Scripts Dashboard -->
            <script type="text/javascript">
                // Initialiser dashboard après chargement page
                jQuery(document).ready(function($) {
                    if (typeof tbAnalyticsDashboard !== 'undefined') {
                        tbAnalyticsDashboard.init();
                    }
                });
            </script>
            <?php
            
            $this->logger->info(
                'Page analytics rendue avec succès',
                array( 'user_id' => get_current_user_id() ),
                self::LOG_CHANNEL
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur rendu page analytics',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__( 'Erreur lors du chargement des analytics', 'wc-tb-web-parrainage' ) . 
                 '</p></div>';
        }
    }
    
    /**
     * Section métriques rapides (Analytics + Stats basiques intégrées)
     * 
     * @param array $stats Statistiques rapides
     * @return void
     */
    private function render_quick_stats_section( array $stats ): void {
        
        // Récupérer aussi les stats basiques du plugin
        global $wpdb;
        $basic_stats = array(
            'total_parrainage' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_billing_parrain_code'" ),
            'parrainage_ce_mois' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tb_parrainage_logs WHERE datetime >= DATE_FORMAT(NOW(), '%Y-%m-01') AND message LIKE '%parrain%'" ),
            'webhooks_envoyes' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tb_parrainage_logs WHERE source LIKE '%webhook%'" )
        );
        
        ?>
        <div class="tb-analytics-section tb-quick-stats">
            <h2><?php esc_html_e( 'Vue d\'ensemble Complète', 'wc-tb-web-parrainage' ); ?></h2>
            
            <div class="tb-stats-grid">
                
                <!-- Stats Analytics avancées -->
                <div class="tb-stat-card tb-card-parrains">
                    <?php $this->modal_adapter->render_help_icon( 'total_parrains' ); ?>
                    <div class="tb-stat-icon">👥</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( $stats['total_parrains'] ); ?></div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Parrains Actifs', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <div class="tb-stat-card tb-card-filleuls">
                    <?php $this->modal_adapter->render_help_icon( 'total_filleuls' ); ?>
                    <div class="tb-stat-icon">👤</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( $stats['total_filleuls'] ); ?></div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Filleuls Actifs', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <div class="tb-stat-card tb-card-revenue">
                    <?php $this->modal_adapter->render_help_icon( 'monthly_total_revenue' ); ?>
                    <div class="tb-stat-icon">💰</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( number_format( $stats['monthly_total_revenue'], 2 ) ); ?>€ HT</div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Revenus Mensuels HT', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <div class="tb-stat-card tb-card-discounts">
                    <?php $this->modal_adapter->render_help_icon( 'monthly_discounts' ); ?>
                    <div class="tb-stat-icon">🎁</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( number_format( $stats['monthly_discounts'], 2 ) ); ?>€</div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Remises Mensuelles', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <div class="tb-stat-card tb-card-roi">
                    <?php $this->modal_adapter->render_help_icon( 'roi_current_month' ); ?>
                    <div class="tb-stat-icon">📈</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value <?php echo $stats['roi_current_month'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo esc_html( number_format( $stats['roi_current_month'], 1 ) ); ?>%
                        </div>
                        <div class="tb-stat-label"><?php esc_html_e( 'ROI Mois Actuel', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <!-- Stats basiques intégrées harmonieusement -->
                <div class="tb-stat-card tb-card-basic">
                    <?php $this->modal_adapter->render_help_icon( 'total_codes_used' ); ?>
                    <div class="tb-stat-icon">📝</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( $basic_stats['total_parrainage'] ?: 0 ); ?></div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Codes Utilisés', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <div class="tb-stat-card tb-card-basic">
                    <?php $this->modal_adapter->render_help_icon( 'monthly_events' ); ?>
                    <div class="tb-stat-icon">📅</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( $basic_stats['parrainage_ce_mois'] ?: 0 ); ?></div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Événements ce mois', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
                <div class="tb-stat-card tb-card-basic">
                    <?php $this->modal_adapter->render_help_icon( 'webhooks_sent' ); ?>
                    <div class="tb-stat-icon">🔗</div>
                    <div class="tb-stat-content">
                        <div class="tb-stat-value"><?php echo esc_html( $basic_stats['webhooks_envoyes'] ?: 0 ); ?></div>
                        <div class="tb-stat-label"><?php esc_html_e( 'Webhooks Envoyés', 'wc-tb-web-parrainage' ); ?></div>
                    </div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Section santé système
     * 
     * @param array $health_metrics Métriques santé
     * @return void
     */
    private function render_health_section( array $health_metrics ): void {
        
        $status_colors = array(
            'excellent' => 'green',
            'good' => 'blue',
            'warning' => 'orange',
            'critical' => 'red'
        );
        
        $status_color = $status_colors[ $health_metrics['global_status'] ] ?? 'gray';
        
        ?>
        <div class="tb-analytics-section tb-health-section">
            <h2>
                <?php esc_html_e( 'Santé du Système', 'wc-tb-web-parrainage' ); ?>
                <?php $this->modal_adapter->render_help_icon( 'system_health' ); ?>
            </h2>
            
            <div class="tb-health-overview">
                <div class="tb-health-score">
                    <div class="tb-health-circle tb-health-<?php echo esc_attr( $status_color ); ?>">
                        <span class="tb-health-percentage"><?php echo esc_html( $health_metrics['health_score'] ); ?>%</span>
                    </div>
                    <div class="tb-health-status">
                        <?php
                        switch ( $health_metrics['global_status'] ) {
                            case 'excellent':
                                esc_html_e( 'Excellent', 'wc-tb-web-parrainage' );
                                break;
                            case 'good':
                                esc_html_e( 'Bon', 'wc-tb-web-parrainage' );
                                break;
                            case 'warning':
                                esc_html_e( 'Attention', 'wc-tb-web-parrainage' );
                                break;
                            case 'critical':
                                esc_html_e( 'Critique', 'wc-tb-web-parrainage' );
                                break;
                        }
                        ?>
                    </div>
                </div>
                
                <div class="tb-health-indicators">
                    <?php foreach ( $health_metrics['indicators'] as $key => $indicator ): ?>
                        <div class="tb-health-indicator tb-indicator-<?php echo esc_attr( $indicator['status'] ); ?>">
                            <span class="tb-indicator-icon">
                                <?php echo $indicator['status'] === 'good' ? '✅' : ($indicator['status'] === 'warning' ? '⚠️' : '❌'); ?>
                            </span>
                            <span class="tb-indicator-message"><?php echo esc_html( $indicator['message'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ( ! empty( $health_metrics['recommendations'] ) ): ?>
                <div class="tb-health-recommendations">
                    <h3><?php esc_html_e( 'Recommandations', 'wc-tb-web-parrainage' ); ?></h3>
                    <ul>
                        <?php foreach ( $health_metrics['recommendations'] as $recommendation ): ?>
                            <li><?php echo esc_html( $recommendation ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Section graphiques
     * 
     * @return void
     */
    private function render_charts_section(): void {
        ?>
        <div class="tb-analytics-section tb-charts-section">
            <h2><?php esc_html_e( 'Évolution et Performance', 'wc-tb-web-parrainage' ); ?></h2>
            
            <div class="tb-charts-controls">
                <select id="tb-chart-period" class="tb-period-selector">
                    <option value="30"><?php esc_html_e( '30 derniers jours', 'wc-tb-web-parrainage' ); ?></option>
                    <option value="90" selected><?php esc_html_e( '3 derniers mois', 'wc-tb-web-parrainage' ); ?></option>
                    <option value="180"><?php esc_html_e( '6 derniers mois', 'wc-tb-web-parrainage' ); ?></option>
                    <option value="365"><?php esc_html_e( '12 derniers mois', 'wc-tb-web-parrainage' ); ?></option>
                </select>
                
                <button type="button" id="tb-refresh-charts" class="button">
                    <?php esc_html_e( 'Actualiser', 'wc-tb-web-parrainage' ); ?>
                </button>
            </div>
            
            <div class="tb-charts-grid">
                
                <div class="tb-chart-container">
                    <h3><?php esc_html_e( 'Évolution Revenus', 'wc-tb-web-parrainage' ); ?></h3>
                    <canvas id="tb-revenue-chart" width="400" height="200"></canvas>
                    <div class="tb-chart-loading"><?php esc_html_e( 'Chargement...', 'wc-tb-web-parrainage' ); ?></div>
                </div>
                
                <div class="tb-chart-container">
                    <h3><?php esc_html_e( 'Nouveaux Filleuls', 'wc-tb-web-parrainage' ); ?></h3>
                    <canvas id="tb-filleuls-chart" width="400" height="200"></canvas>
                    <div class="tb-chart-loading"><?php esc_html_e( 'Chargement...', 'wc-tb-web-parrainage' ); ?></div>
                </div>
                
                <div class="tb-chart-container">
                    <h3><?php esc_html_e( 'ROI par Mois', 'wc-tb-web-parrainage' ); ?></h3>
                    <canvas id="tb-roi-chart" width="400" height="200"></canvas>
                    <div class="tb-chart-loading"><?php esc_html_e( 'Chargement...', 'wc-tb-web-parrainage' ); ?></div>
                </div>
                
                <div class="tb-chart-container">
                    <h3><?php esc_html_e( 'Top Performers', 'wc-tb-web-parrainage' ); ?></h3>
                    <canvas id="tb-performers-chart" width="400" height="200"></canvas>
                    <div class="tb-chart-loading"><?php esc_html_e( 'Chargement...', 'wc-tb-web-parrainage' ); ?></div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Section comparaison périodes
     * 
     * @return void
     */
    private function render_period_comparison_section(): void {
        ?>
        <div class="tb-analytics-section tb-comparison-section">
            <h2><?php esc_html_e( 'Comparaison de Périodes', 'wc-tb-web-parrainage' ); ?></h2>
            
            <div class="tb-comparison-controls">
                <div class="tb-period-selector-group">
                    <label><?php esc_html_e( 'Période 1:', 'wc-tb-web-parrainage' ); ?></label>
                    <input type="date" id="tb-period1-start" class="tb-date-input">
                    <span><?php esc_html_e( 'à', 'wc-tb-web-parrainage' ); ?></span>
                    <input type="date" id="tb-period1-end" class="tb-date-input">
                </div>
                
                <div class="tb-period-selector-group">
                    <label><?php esc_html_e( 'Période 2:', 'wc-tb-web-parrainage' ); ?></label>
                    <input type="date" id="tb-period2-start" class="tb-date-input">
                    <span><?php esc_html_e( 'à', 'wc-tb-web-parrainage' ); ?></span>
                    <input type="date" id="tb-period2-end" class="tb-date-input">
                </div>
                
                <button type="button" id="tb-compare-periods" class="button button-primary">
                    <?php esc_html_e( 'Comparer', 'wc-tb-web-parrainage' ); ?>
                </button>
            </div>
            
            <div id="tb-comparison-results" class="tb-comparison-results" style="display: none;">
                <!-- Résultats comparaison chargés via AJAX -->
            </div>
        </div>
        <?php
    }
    
    /**
     * Section exports et rapports
     * 
     * @return void
     */
    private function render_reports_section(): void {
        ?>
        <div class="tb-analytics-section tb-reports-section">
            <h2><?php esc_html_e( 'Rapports et Exports', 'wc-tb-web-parrainage' ); ?></h2>
            
            <div class="tb-reports-grid">
                
                <div class="tb-report-card">
                    <h3><?php esc_html_e( 'Rapport Mensuel', 'wc-tb-web-parrainage' ); ?></h3>
                    <p><?php esc_html_e( 'Export complet des performances du mois', 'wc-tb-web-parrainage' ); ?></p>
                    <div class="tb-report-actions">
                        <button type="button" class="button tb-generate-report" 
                                data-report-type="monthly" data-format="pdf">
                            <?php esc_html_e( 'PDF', 'wc-tb-web-parrainage' ); ?>
                        </button>
                        <button type="button" class="button tb-generate-report" 
                                data-report-type="monthly" data-format="excel">
                            <?php esc_html_e( 'Excel', 'wc-tb-web-parrainage' ); ?>
                        </button>
                    </div>
                </div>
                
                <div class="tb-report-card">
                    <h3><?php esc_html_e( 'Rapport Annuel', 'wc-tb-web-parrainage' ); ?></h3>
                    <p><?php esc_html_e( 'Synthèse annuelle et évolution', 'wc-tb-web-parrainage' ); ?></p>
                    <div class="tb-report-actions">
                        <button type="button" class="button tb-generate-report" 
                                data-report-type="annual" data-format="pdf">
                            <?php esc_html_e( 'PDF', 'wc-tb-web-parrainage' ); ?>
                        </button>
                        <button type="button" class="button tb-generate-report" 
                                data-report-type="annual" data-format="excel">
                            <?php esc_html_e( 'Excel', 'wc-tb-web-parrainage' ); ?>
                        </button>
                    </div>
                </div>
                
                <div class="tb-report-card">
                    <h3><?php esc_html_e( 'Export Données Brutes', 'wc-tb-web-parrainage' ); ?></h3>
                    <p><?php esc_html_e( 'Données complètes pour analyse externe', 'wc-tb-web-parrainage' ); ?></p>
                    <div class="tb-export-options">
                        <select id="tb-export-type">
                            <option value="filleuls"><?php esc_html_e( 'Filleuls', 'wc-tb-web-parrainage' ); ?></option>
                            <option value="parrains"><?php esc_html_e( 'Parrains', 'wc-tb-web-parrainage' ); ?></option>
                            <option value="logs"><?php esc_html_e( 'Logs', 'wc-tb-web-parrainage' ); ?></option>
                        </select>
                        <button type="button" class="button tb-export-data">
                            <?php esc_html_e( 'Exporter', 'wc-tb-web-parrainage' ); ?>
                        </button>
                    </div>
                </div>
                
            </div>
            
            <div id="tb-report-status" class="tb-report-status"></div>
        </div>
        <?php
    }
    
    /**
     * Section activité récente
     * 
     * @return void
     */
    private function render_recent_activity_section(): void {
        ?>
        <div class="tb-analytics-section tb-activity-section">
            <h2><?php esc_html_e( 'Activité Récente', 'wc-tb-web-parrainage' ); ?></h2>
            
            <div id="tb-recent-activity" class="tb-activity-list">
                <div class="tb-activity-loading">
                    <?php esc_html_e( 'Chargement de l\'activité...', 'wc-tb-web-parrainage' ); ?>
                </div>
            </div>
            
            <div class="tb-activity-actions">
                <button type="button" id="tb-refresh-activity" class="button">
                    <?php esc_html_e( 'Actualiser', 'wc-tb-web-parrainage' ); ?>
                </button>
                <button type="button" id="tb-view-all-logs" class="button">
                    <?php esc_html_e( 'Voir tous les logs', 'wc-tb-web-parrainage' ); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Récupérer statistiques rapides pour dashboard
     * 
     * @return array Stats rapides
     */
    private function get_dashboard_quick_stats(): array {
        
        try {
            
            return array(
                'total_parrains' => $this->data_provider->count_active_parrains(),
                'total_filleuls' => $this->data_provider->count_active_filleuls(),
                'monthly_revenue' => $this->roi_calculator->get_monthly_revenue(),
                'monthly_total_revenue' => $this->data_provider->get_monthly_total_revenue(),
                'monthly_discounts' => $this->roi_calculator->get_monthly_discounts(),
                'roi_current_month' => $this->roi_calculator->calculate_current_month_roi()
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur récupération stats rapides',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            // Retourner valeurs par défaut en cas d'erreur
            return array(
                'total_parrains' => 0,
                'total_filleuls' => 0,
                'monthly_revenue' => 0.0,
                'monthly_discounts' => 0.0,
                'roi_current_month' => 0.0
            );
        }
    }
}
