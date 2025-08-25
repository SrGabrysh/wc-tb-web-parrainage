<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire principal du module Analytics
 * 
 * Responsabilité unique : Orchestration du module analytics et enregistrement hooks
 * Architecture : Manager pattern pour coordination des composants analytics
 * 
 * @since 2.12.0
 */
class AnalyticsManager {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Data provider pour requêtes
     * @var AnalyticsDataProvider
     */
    private $data_provider;
    
    /**
     * Calculateur ROI
     * @var ROICalculator
     */
    private $roi_calculator;
    
    /**
     * Générateur de rapports
     * @var ReportGenerator
     */
    private $report_generator;
    
    /**
     * Renderer dashboard
     * @var DashboardRenderer
     */
    private $dashboard_renderer;
    
    /**
     * Gestionnaire modales d'aide (migré vers TemplateModalManager)
     * @var AdminStatsModalAdapter
     */
    private $modal_adapter;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'analytics-manager';
    
    /**
     * Options analytics
     */
    const OPTION_ANALYTICS_SETTINGS = 'wc_tb_parrainage_analytics_settings';
    const OPTION_LAST_REPORT_RUN = 'wc_tb_parrainage_last_report_run';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        
        // Initialisation composants selon SRP
        $this->data_provider = new AnalyticsDataProvider( $logger );
        $this->roi_calculator = new ROICalculator( $logger, $this->data_provider );
        $this->report_generator = new ReportGenerator( $logger, $this->data_provider, $this->roi_calculator );
        $this->modal_adapter = new AdminStatsModalAdapter( $logger );
        $this->dashboard_renderer = new DashboardRenderer( $logger, $this->data_provider, $this->roi_calculator, $this->modal_adapter );
        
        $this->logger->info(
            'AnalyticsManager initialisé avec tous les composants',
            array(
                'version' => '2.18.0',
                'components' => array(
                    'data_provider',
                    'roi_calculator', 
                    'report_generator',
                    'dashboard_renderer',
                    'modal_adapter'
                )
            ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Initialisation des hooks WordPress
     * 
     * @return void
     */
    public function init(): void {
        
        // Hooks admin seulement - PAS de menu séparé, intégration dans page existante
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analytics_assets' ) );
        }
        
        // Initialiser l'adaptateur de modales unifié
        $this->modal_adapter->init();
        
        // Hooks AJAX pour dashboard
        add_action( 'wp_ajax_tb_analytics_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
        add_action( 'wp_ajax_tb_analytics_generate_report', array( $this, 'ajax_generate_report' ) );
        add_action( 'wp_ajax_tb_analytics_export_data', array( $this, 'ajax_export_data' ) );
        
        // Hook CRON pour rapports automatiques
        add_action( 'tb_parrainage_generate_monthly_report', array( $this, 'generate_monthly_report_cron' ) );
        
        // Programmer CRON mensuel si pas déjà fait
        if ( ! wp_next_scheduled( 'tb_parrainage_generate_monthly_report' ) ) {
            wp_schedule_event( time(), 'monthly', 'tb_parrainage_generate_monthly_report' );
        }
        
        $this->logger->info(
            'AnalyticsManager hooks enregistrés avec succès',
            array(
                'admin_hooks' => is_admin(),
                'ajax_hooks' => array(
                    'dashboard_data',
                    'generate_report',
                    'export_data'
                ),
                'cron_scheduled' => wp_next_scheduled( 'tb_parrainage_generate_monthly_report' )
            ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Rendu du contenu Analytics dans l'onglet stats existant
     * 
     * @return void
     */
    public function render_analytics_content(): void {
        
        // Vérifier si analytics activé
        $settings = get_option( 'wc_tb_parrainage_settings', array() );
        if ( empty( $settings['enable_analytics'] ) ) {
            echo '<div class="notice notice-info"><p>Les Analytics sont désactivés. Activez-les dans l\'onglet Paramètres.</p></div>';
            return;
        }
        
        // Rendre le contenu analytics
        $this->dashboard_renderer->render_analytics_page();
        
        $this->logger->info(
            'Contenu Analytics rendu dans onglet stats',
            array( 'tab' => 'stats' ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Charger assets JavaScript/CSS pour analytics
     * 
     * @param string $hook Hook admin actuel
     * @return void
     */
    public function enqueue_analytics_assets( string $hook ): void {
        
        // Charger seulement sur page TB Parrainage
        if ( strpos( $hook, 'wc-tb-parrainage' ) === false ) {
            return;
        }
        
        // Chart.js pour graphiques
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        // Script analytics custom
        wp_enqueue_script(
            'tb-analytics-dashboard',
            WC_TB_PARRAINAGE_URL . 'assets/js/analytics-dashboard.js',
            array( 'jquery', 'chartjs' ),
            WC_TB_PARRAINAGE_VERSION,
            true
        );
        
        // CSS analytics
        wp_enqueue_style(
            'tb-analytics-style',
            WC_TB_PARRAINAGE_URL . 'assets/css/analytics.css',
            array(),
            WC_TB_PARRAINAGE_VERSION
        );
        
        // Localisation AJAX
        wp_localize_script( 'tb-analytics-dashboard', 'tbAnalytics', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tb_analytics_nonce' ),
            'strings' => array(
                'loading' => __( 'Chargement...', 'wc-tb-web-parrainage' ),
                'error' => __( 'Erreur lors du chargement', 'wc-tb-web-parrainage' ),
                'no_data' => __( 'Aucune donnée disponible', 'wc-tb-web-parrainage' )
            )
        ) );
        
        $this->logger->info(
            'Assets analytics chargés pour dashboard',
            array( 'hook' => $hook ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * AJAX: Récupérer données dashboard
     * 
     * @return void
     */
    public function ajax_get_dashboard_data(): void {
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_analytics_nonce' ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        try {
            
            $period = sanitize_text_field( $_POST['period'] ?? '30' );
            $period_days = intval( $period );
            
            // Récupérer données via data provider
            $dashboard_data = array(
                'roi_metrics' => $this->roi_calculator->calculate_roi_metrics( $period_days ),
                'revenue_evolution' => $this->data_provider->get_revenue_evolution( $period_days ),
                'conversion_stats' => $this->data_provider->get_conversion_stats( $period_days ),
                'top_performers' => $this->data_provider->get_top_performers( 10 ),
                'recent_activity' => $this->data_provider->get_recent_activity( 20 )
            );
            
            wp_send_json_success( $dashboard_data );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur récupération données dashboard',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            wp_send_json_error( __( 'Erreur lors de la récupération des données', 'wc-tb-web-parrainage' ) );
        }
    }
    
    /**
     * AJAX: Générer rapport custom
     * 
     * @return void
     */
    public function ajax_generate_report(): void {
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_analytics_nonce' ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        try {
            
            $report_type = sanitize_text_field( $_POST['report_type'] ?? 'monthly' );
            $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
            $end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
            $format = sanitize_text_field( $_POST['format'] ?? 'pdf' );
            
            // Générer rapport
            $report_result = $this->report_generator->generate_custom_report(
                $report_type,
                $start_date,
                $end_date,
                $format
            );
            
            if ( $report_result['success'] ) {
                wp_send_json_success( array(
                    'download_url' => $report_result['download_url'],
                    'filename' => $report_result['filename']
                ) );
            } else {
                wp_send_json_error( $report_result['error'] );
            }
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur génération rapport custom',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            wp_send_json_error( __( 'Erreur lors de la génération du rapport', 'wc-tb-web-parrainage' ) );
        }
    }
    
    /**
     * AJAX: Export données brutes
     * 
     * @return void
     */
    public function ajax_export_data(): void {
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_analytics_nonce' ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        try {
            
            $export_type = sanitize_text_field( $_POST['export_type'] ?? 'all_data' );
            $format = sanitize_text_field( $_POST['format'] ?? 'excel' );
            
            // Export via report generator
            $export_result = $this->report_generator->export_raw_data( $export_type, $format );
            
            if ( $export_result['success'] ) {
                wp_send_json_success( array(
                    'download_url' => $export_result['download_url'],
                    'filename' => $export_result['filename']
                ) );
            } else {
                wp_send_json_error( $export_result['error'] );
            }
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur export données brutes',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            wp_send_json_error( __( 'Erreur lors de l\'export des données', 'wc-tb-web-parrainage' ) );
        }
    }
    
    /**
     * CRON: Génération rapport mensuel automatique
     * 
     * @return void
     */
    public function generate_monthly_report_cron(): void {
        
        try {
            
            // Générer rapport mensuel
            $result = $this->report_generator->generate_monthly_report();
            
            if ( $result['success'] ) {
                
                // Envoyer par email si configuré
                $this->send_monthly_report_email( $result['file_path'] );
                
                // Mettre à jour timestamp
                update_option( self::OPTION_LAST_REPORT_RUN, current_time( 'mysql' ) );
                
                $this->logger->info(
                    'Rapport mensuel automatique généré avec succès',
                    array( 'file' => $result['filename'] ),
                    self::LOG_CHANNEL
                );
                
            } else {
                
                $this->logger->error(
                    'Échec génération rapport mensuel automatique',
                    array( 'error' => $result['error'] ),
                    self::LOG_CHANNEL
                );
            }
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Exception lors génération rapport mensuel CRON',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Envoyer rapport mensuel par email
     * 
     * @param string $file_path Chemin du fichier rapport
     * @return void
     */
    private function send_monthly_report_email( string $file_path ): void {
        
        $settings = get_option( self::OPTION_ANALYTICS_SETTINGS, array() );
        $email_recipients = $settings['monthly_report_emails'] ?? array();
        
        if ( empty( $email_recipients ) ) {
            return;
        }
        
        $subject = sprintf(
            __( 'Rapport mensuel parrainage - %s', 'wc-tb-web-parrainage' ),
            date( 'F Y', strtotime( '-1 month' ) )
        );
        
        $message = __( 'Veuillez trouver en pièce jointe le rapport mensuel de performance du système de parrainage.', 'wc-tb-web-parrainage' );
        
        $attachments = array( $file_path );
        
        foreach ( $email_recipients as $email ) {
            wp_mail( $email, $subject, $message, '', $attachments );
        }
        
        $this->logger->info(
            'Rapport mensuel envoyé par email',
            array( 'recipients' => count( $email_recipients ) ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Obtenir statistiques rapides pour dashboard
     * 
     * @return array Statistiques rapides
     */
    public function get_quick_stats(): array {
        
        return array(
            'total_parrains' => $this->data_provider->count_active_parrains(),
            'total_filleuls' => $this->data_provider->count_active_filleuls(),
            'monthly_revenue' => $this->roi_calculator->get_monthly_revenue(), // Revenus parrainage
            'monthly_total_revenue' => $this->data_provider->get_monthly_total_revenue(), // CORRECTION: Revenus totaux
            'monthly_discounts' => $this->roi_calculator->get_monthly_discounts(),
            'roi_current_month' => $this->roi_calculator->calculate_current_month_roi()
        );
    }
}
