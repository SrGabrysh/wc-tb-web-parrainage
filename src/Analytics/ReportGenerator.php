<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Générateur de rapports et exports
 * 
 * Responsabilité unique : Génération rapports PDF/Excel et exports données
 * Principe SRP : Séparation génération vs affichage vs calculs
 * 
 * @since 2.12.0
 */
class ReportGenerator {
    
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
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'report-generator';
    
    /**
     * Répertoire uploads pour rapports
     */
    private $reports_dir;
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     * @param AnalyticsDataProvider $data_provider Provider données
     * @param ROICalculator $roi_calculator Calculateur ROI
     */
    public function __construct( $logger, AnalyticsDataProvider $data_provider, ROICalculator $roi_calculator ) {
        $this->logger = $logger;
        $this->data_provider = $data_provider;
        $this->roi_calculator = $roi_calculator;
        
        // Créer répertoire rapports
        $upload_dir = wp_upload_dir();
        $this->reports_dir = $upload_dir['basedir'] . '/tb-parrainage-reports/';
        
        if ( ! file_exists( $this->reports_dir ) ) {
            wp_mkdir_p( $this->reports_dir );
        }
        
        $this->logger->info(
            'ReportGenerator initialisé avec répertoire',
            array(
                'version' => '2.12.0',
                'reports_dir' => $this->reports_dir
            ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Générer rapport mensuel automatique
     * 
     * @return array Résultat génération
     */
    public function generate_monthly_report(): array {
        
        try {
            
            $current_month = date( 'Y-m' );
            $filename = 'rapport-mensuel-' . $current_month . '.pdf';
            $file_path = $this->reports_dir . $filename;
            
            // Récupérer données du mois
            $metrics = $this->roi_calculator->calculate_roi_metrics( 30 );
            $evolution = $this->data_provider->get_revenue_evolution( 30 );
            $top_performers = $this->data_provider->get_top_performers( 10 );
            $health = $this->roi_calculator->calculate_system_health_metrics();
            
            // Générer contenu PDF
            $pdf_content = $this->generate_monthly_pdf_content( array(
                'month' => $current_month,
                'metrics' => $metrics,
                'evolution' => $evolution,
                'top_performers' => $top_performers,
                'health' => $health
            ) );
            
            // Écrire fichier (simulation PDF simple en HTML)
            $html_content = $this->convert_to_html_report( $pdf_content );
            file_put_contents( str_replace( '.pdf', '.html', $file_path ), $html_content );
            
            $this->logger->info(
                'Rapport mensuel généré avec succès',
                array(
                    'filename' => $filename,
                    'month' => $current_month
                ),
                self::LOG_CHANNEL
            );
            
            return array(
                'success' => true,
                'filename' => $filename,
                'file_path' => $file_path
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur génération rapport mensuel',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Générer rapport custom
     * 
     * @param string $report_type Type de rapport
     * @param string $start_date Date début
     * @param string $end_date Date fin
     * @param string $format Format (pdf/excel)
     * @return array Résultat génération
     */
    public function generate_custom_report( 
        string $report_type, 
        string $start_date, 
        string $end_date, 
        string $format = 'pdf' 
    ): array {
        
        try {
            
            // Valider dates
            if ( empty( $start_date ) || empty( $end_date ) ) {
                throw new \Exception( 'Dates de début et fin requises' );
            }
            
            $filename = 'rapport-' . $report_type . '-' . $start_date . '-' . $end_date . '.' . $format;
            $file_path = $this->reports_dir . $filename;
            
            // Calculer période en jours
            $start = new \DateTime( $start_date );
            $end = new \DateTime( $end_date );
            $period_days = $end->diff( $start )->days;
            
            // Récupérer données selon type rapport
            $report_data = $this->get_report_data( $report_type, $period_days, $start_date, $end_date );
            
            // Générer selon format
            if ( $format === 'excel' ) {
                $content = $this->generate_excel_content( $report_data );
                $file_path = str_replace( '.excel', '.csv', $file_path ); // Simplification CSV
            } else {
                $content = $this->generate_pdf_content( $report_data );
                $file_path = str_replace( '.pdf', '.html', $file_path ); // Simplification HTML
            }
            
            // Écrire fichier
            file_put_contents( $file_path, $content );
            
            // URL téléchargement
            $upload_dir = wp_upload_dir();
            $download_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
            
            $this->logger->info(
                'Rapport custom généré avec succès',
                array(
                    'type' => $report_type,
                    'format' => $format,
                    'filename' => $filename
                ),
                self::LOG_CHANNEL
            );
            
            return array(
                'success' => true,
                'filename' => $filename,
                'file_path' => $file_path,
                'download_url' => $download_url
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur génération rapport custom',
                array(
                    'type' => $report_type,
                    'error' => $e->getMessage()
                ),
                self::LOG_CHANNEL
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Export données brutes
     * 
     * @param string $export_type Type d'export
     * @param string $format Format (csv/excel)
     * @return array Résultat export
     */
    public function export_raw_data( string $export_type, string $format = 'csv' ): array {
        
        try {
            
            $filename = 'export-' . $export_type . '-' . date( 'Y-m-d-H-i-s' ) . '.' . $format;
            $file_path = $this->reports_dir . $filename;
            
            // Récupérer données brutes
            $raw_data = $this->data_provider->get_raw_export_data( $export_type );
            
            if ( empty( $raw_data ) ) {
                throw new \Exception( 'Aucune donnée à exporter pour le type: ' . $export_type );
            }
            
            // Générer contenu selon format
            if ( $format === 'excel' || $format === 'csv' ) {
                $content = $this->convert_to_csv( $raw_data );
                $file_path = str_replace( '.excel', '.csv', $file_path );
            } else {
                $content = $this->convert_to_json( $raw_data );
                $file_path = str_replace( '.' . $format, '.json', $file_path );
            }
            
            // Écrire fichier
            file_put_contents( $file_path, $content );
            
            // URL téléchargement
            $upload_dir = wp_upload_dir();
            $download_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
            
            $this->logger->info(
                'Export données brutes généré',
                array(
                    'type' => $export_type,
                    'format' => $format,
                    'rows' => count( $raw_data ),
                    'filename' => $filename
                ),
                self::LOG_CHANNEL
            );
            
            return array(
                'success' => true,
                'filename' => $filename,
                'file_path' => $file_path,
                'download_url' => $download_url,
                'rows_exported' => count( $raw_data )
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur export données brutes',
                array(
                    'type' => $export_type,
                    'error' => $e->getMessage()
                ),
                self::LOG_CHANNEL
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Récupérer données selon type rapport
     * 
     * @param string $report_type Type de rapport
     * @param int $period_days Période en jours
     * @param string $start_date Date début
     * @param string $end_date Date fin
     * @return array Données rapport
     */
    private function get_report_data( 
        string $report_type, 
        int $period_days, 
        string $start_date, 
        string $end_date 
    ): array {
        
        switch ( $report_type ) {
            
            case 'monthly':
                return array(
                    'type' => 'monthly',
                    'period' => array( 'start' => $start_date, 'end' => $end_date ),
                    'metrics' => $this->roi_calculator->calculate_roi_metrics( $period_days ),
                    'evolution' => $this->data_provider->get_revenue_evolution( $period_days ),
                    'top_performers' => $this->data_provider->get_top_performers( 10 ),
                    'conversion_stats' => $this->data_provider->get_conversion_stats( $period_days ),
                    'health' => $this->roi_calculator->calculate_system_health_metrics()
                );
                
            case 'annual':
                return array(
                    'type' => 'annual',
                    'period' => array( 'start' => $start_date, 'end' => $end_date ),
                    'metrics' => $this->roi_calculator->calculate_roi_metrics( $period_days ),
                    'evolution' => $this->data_provider->get_revenue_evolution( $period_days ),
                    'comparison' => $this->get_year_over_year_comparison( $start_date, $end_date ),
                    'top_performers' => $this->data_provider->get_top_performers( 20 ),
                    'projections' => $this->calculate_annual_projections( $period_days )
                );
                
            case 'performance':
                return array(
                    'type' => 'performance',
                    'period' => array( 'start' => $start_date, 'end' => $end_date ),
                    'top_performers' => $this->data_provider->get_top_performers( 50 ),
                    'conversion_stats' => $this->data_provider->get_conversion_stats( $period_days ),
                    'efficiency_metrics' => $this->calculate_efficiency_metrics( $period_days )
                );
                
            default:
                return array(
                    'type' => 'basic',
                    'period' => array( 'start' => $start_date, 'end' => $end_date ),
                    'metrics' => $this->roi_calculator->calculate_roi_metrics( $period_days )
                );
        }
    }
    
    /**
     * Générer contenu PDF mensuel
     * 
     * @param array $data Données rapport
     * @return string Contenu PDF
     */
    private function generate_monthly_pdf_content( array $data ): string {
        
        $content = array();
        $content[] = "=== RAPPORT MENSUEL PARRAINAGE ===";
        $content[] = "Mois: " . $data['month'];
        $content[] = "Généré le: " . date( 'Y-m-d H:i:s' );
        $content[] = "";
        
        // Métriques ROI
        $content[] = "--- MÉTRIQUES ROI ---";
        $content[] = "Revenus mensuels: " . number_format( $data['metrics']['revenue']['monthly_revenue'], 2 ) . "€";
        $content[] = "Remises mensuelles: " . number_format( $data['metrics']['revenue']['monthly_discounts'], 2 ) . "€";
        $content[] = "Profit net: " . number_format( $data['metrics']['revenue']['net_profit'], 2 ) . "€";
        $content[] = "ROI: " . number_format( $data['metrics']['roi']['roi_percentage'], 2 ) . "%";
        $content[] = "";
        
        // Performance
        $content[] = "--- PERFORMANCE ---";
        $content[] = "Parrains actifs: " . $data['metrics']['counts']['active_parrains'];
        $content[] = "Filleuls actifs: " . $data['metrics']['counts']['active_filleuls'];
        $content[] = "Revenu moyen par filleul: " . number_format( $data['metrics']['performance']['avg_revenue_per_filleul'], 2 ) . "€";
        $content[] = "";
        
        // Santé système
        $content[] = "--- SANTÉ SYSTÈME ---";
        $content[] = "Score santé: " . $data['health']['health_score'] . "/100";
        $content[] = "Statut global: " . ucfirst( $data['health']['global_status'] );
        $content[] = "";
        
        // Top performers
        if ( ! empty( $data['top_performers'] ) ) {
            $content[] = "--- TOP PERFORMERS ---";
            foreach ( array_slice( $data['top_performers'], 0, 5 ) as $index => $performer ) {
                $content[] = ($index + 1) . ". Parrain " . $performer['parrain_id'] . 
                           " - " . $performer['nb_filleuls'] . " filleuls - " . 
                           number_format( $performer['revenue_generated'], 2 ) . "€";
            }
        }
        
        return implode( "\n", $content );
    }
    
    /**
     * Générer contenu PDF general
     * 
     * @param array $data Données rapport
     * @return string Contenu HTML
     */
    private function generate_pdf_content( array $data ): string {
        
        $content = array();
        $content[] = "<!DOCTYPE html>";
        $content[] = "<html><head><meta charset='UTF-8'><title>Rapport Parrainage</title></head><body>";
        $content[] = "<h1>Rapport " . ucfirst( $data['type'] ) . "</h1>";
        $content[] = "<p>Période: " . $data['period']['start'] . " au " . $data['period']['end'] . "</p>";
        $content[] = "<p>Généré le: " . date( 'Y-m-d H:i:s' ) . "</p>";
        
        if ( isset( $data['metrics'] ) ) {
            $content[] = "<h2>Métriques ROI</h2>";
            $content[] = "<ul>";
            $content[] = "<li>Revenus: " . number_format( $data['metrics']['revenue']['monthly_revenue'], 2 ) . "€</li>";
            $content[] = "<li>Remises: " . number_format( $data['metrics']['revenue']['monthly_discounts'], 2 ) . "€</li>";
            $content[] = "<li>ROI: " . number_format( $data['metrics']['roi']['roi_percentage'], 2 ) . "%</li>";
            $content[] = "</ul>";
        }
        
        $content[] = "</body></html>";
        
        return implode( "\n", $content );
    }
    
    /**
     * Générer contenu Excel/CSV
     * 
     * @param array $data Données rapport
     * @return string Contenu CSV
     */
    private function generate_excel_content( array $data ): string {
        
        $csv_lines = array();
        
        // Headers
        $csv_lines[] = "Type,Valeur,Unité,Période";
        
        // Métriques
        if ( isset( $data['metrics'] ) ) {
            $csv_lines[] = "Revenus mensuels," . $data['metrics']['revenue']['monthly_revenue'] . ",€," . $data['period']['start'];
            $csv_lines[] = "Remises mensuelles," . $data['metrics']['revenue']['monthly_discounts'] . ",€," . $data['period']['start'];
            $csv_lines[] = "ROI," . $data['metrics']['roi']['roi_percentage'] . ",%," . $data['period']['start'];
            $csv_lines[] = "Parrains actifs," . $data['metrics']['counts']['active_parrains'] . ",nombre," . $data['period']['start'];
            $csv_lines[] = "Filleuls actifs," . $data['metrics']['counts']['active_filleuls'] . ",nombre," . $data['period']['start'];
        }
        
        return implode( "\n", $csv_lines );
    }
    
    /**
     * Convertir données en CSV
     * 
     * @param array $data Données à convertir
     * @return string Contenu CSV
     */
    private function convert_to_csv( array $data ): string {
        
        if ( empty( $data ) ) {
            return '';
        }
        
        $csv_lines = array();
        
        // Headers depuis première ligne
        $headers = array_keys( $data[0] );
        $csv_lines[] = implode( ',', $headers );
        
        // Données
        foreach ( $data as $row ) {
            $csv_lines[] = implode( ',', array_map( function( $value ) {
                return '"' . str_replace( '"', '""', $value ) . '"';
            }, array_values( $row ) ) );
        }
        
        return implode( "\n", $csv_lines );
    }
    
    /**
     * Convertir données en JSON
     * 
     * @param array $data Données à convertir
     * @return string Contenu JSON
     */
    private function convert_to_json( array $data ): string {
        
        return json_encode( array(
            'export_date' => date( 'Y-m-d H:i:s' ),
            'total_rows' => count( $data ),
            'data' => $data
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }
    
    /**
     * Convertir rapport en HTML
     * 
     * @param string $content Contenu texte
     * @return string HTML
     */
    private function convert_to_html_report( string $content ): string {
        
        $html = array();
        $html[] = "<!DOCTYPE html>";
        $html[] = "<html><head>";
        $html[] = "<meta charset='UTF-8'>";
        $html[] = "<title>Rapport Parrainage</title>";
        $html[] = "<style>";
        $html[] = "body { font-family: Arial, sans-serif; margin: 20px; }";
        $html[] = "h1 { color: #2271b1; }";
        $html[] = "pre { background: #f1f1f1; padding: 15px; }";
        $html[] = "</style>";
        $html[] = "</head><body>";
        $html[] = "<h1>Rapport Parrainage TB-Web</h1>";
        $html[] = "<pre>" . esc_html( $content ) . "</pre>";
        $html[] = "</body></html>";
        
        return implode( "\n", $html );
    }
    
    /**
     * Comparaison année sur année
     * 
     * @param string $start_date Date début
     * @param string $end_date Date fin
     * @return array Comparaison
     */
    private function get_year_over_year_comparison( string $start_date, string $end_date ): array {
        
        // Calculer période précédente (année dernière)
        $current_start = new \DateTime( $start_date );
        $current_end = new \DateTime( $end_date );
        
        $previous_start = clone $current_start;
        $previous_start->modify( '-1 year' );
        $previous_end = clone $current_end;
        $previous_end->modify( '-1 year' );
        
        return $this->data_provider->get_period_comparison(
            $start_date,
            $end_date,
            $previous_start->format( 'Y-m-d' ),
            $previous_end->format( 'Y-m-d' )
        );
    }
    
    /**
     * Calculer projections annuelles
     * 
     * @param int $period_days Période actuelle
     * @return array Projections
     */
    private function calculate_annual_projections( int $period_days ): array {
        
        $current_metrics = $this->roi_calculator->calculate_roi_metrics( $period_days );
        
        // Projection simple basée sur période actuelle
        $daily_revenue = $current_metrics['revenue']['monthly_revenue'] / $period_days;
        $annual_projection = $daily_revenue * 365;
        
        return array(
            'annual_revenue_projection' => round( $annual_projection, 2 ),
            'growth_rate_needed' => 15, // Hypothèse 15% croissance
            'target_roi' => 200 // Objectif ROI 200%
        );
    }
    
    /**
     * Calculer métriques d'efficacité
     * 
     * @param int $period_days Période
     * @return array Métriques efficacité
     */
    private function calculate_efficiency_metrics( int $period_days ): array {
        
        $conversion_stats = $this->data_provider->get_conversion_stats( $period_days );
        
        return array(
            'conversion_rate' => $conversion_stats['conversion_rate'],
            'acquisition_efficiency' => min( 100, $conversion_stats['conversion_rate'] * 2 ),
            'retention_estimate' => 85 // Hypothèse rétention
        );
    }
}
