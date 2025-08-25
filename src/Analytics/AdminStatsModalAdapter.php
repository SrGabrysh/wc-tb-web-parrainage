<?php
namespace TBWeb\WCParrainage\Analytics;

use TBWeb\WCParrainage\TemplateModalManager;
use TBWeb\WCParrainage\Logger;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adaptateur pour migrer les modales Analytics vers TemplateModalManager
 * 
 * Responsabilité unique : Pont entre l'ancien système HelpModalManager et TemplateModalManager unifié
 * Architecture : Adapter pattern pour migration progressive
 * 
 * @since 2.18.0
 */
class AdminStatsModalAdapter {
    
    const MODAL_NAMESPACE = 'analytics_admin';
    const LOG_CHANNEL = 'admin-stats-modal-adapter';
    
    private TemplateModalManager $modal_manager;
    private Logger $logger;
    private array $legacy_content = [];
    
    /**
     * Constructor
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        
        // Initialiser TemplateModalManager avec namespace analytics
        $config = [
            'modal_width' => 650,
            'modal_max_height' => 600,
            'enable_multilang' => true,
            'default_language' => 'fr',
            'ajax_action_prefix' => 'tb_modal_analytics',
            'css_prefix' => 'tb-modal-analytics',
            'storage_option' => 'tb_modal_content_analytics',
            'enable_keyboard_nav' => true,
            'enable_cache' => true,
            'cache_duration' => 3600
        ];
        
        $this->modal_manager = new TemplateModalManager(
            $logger,
            $config,
            self::MODAL_NAMESPACE
        );
        
        $this->logger->info(
            'AdminStatsModalAdapter initialisé',
            [],
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Initialiser les hooks WordPress
     */
    public function init(): void {
        // Initialiser le TemplateModalManager
        $this->modal_manager->init();
        
        // Migrer le contenu existant
        add_action( 'admin_init', [ $this, 'migrate_existing_content' ] );
        
        // Remplacer les hooks legacy si nécessaire
        $this->setup_legacy_compatibility();
        
        // Enregistrer les assets sur la page Analytics
        add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
        
        // Migration automatique au premier chargement
        if ( ! get_option( 'tb_modal_migration_completed' ) ) {
            $this->migrate_legacy_content();
        }
    }
    
    /**
     * Migrer le contenu des modales existantes
     */
    public function migrate_existing_content(): void {
        
        // Éviter la migration multiple
        if ( get_option( 'tb_modal_migration_completed' ) ) {
            return;
        }
        
        // Récupérer le contenu legacy depuis HelpModalManager
        $legacy_content = $this->get_legacy_modal_content();
        
        if ( empty( $legacy_content ) ) {
            $this->logger->warning(
                'Aucun contenu legacy à migrer',
                [],
                self::LOG_CHANNEL
            );
            return;
        }
        
        // Convertir au format TemplateModalManager
        $migrated_count = 0;
        foreach ( $legacy_content as $metric_key => $content ) {
            
            // Gérer format avec langue dans la clé (ex: metric_fr)
            if ( preg_match( '/^(.+)_([a-z]{2})$/', $metric_key, $matches ) ) {
                $actual_metric = $matches[1];
                $language = $matches[2];
                $converted = $this->convert_legacy_format( $actual_metric, $content );
            } else {
                $actual_metric = $metric_key;
                $language = 'fr';
                $converted = $this->convert_legacy_format( $metric_key, $content );
            }
            
            // Sauvegarder dans le nouveau système
            if ( ! empty( $converted ) ) {
                $this->modal_manager->set_modal_content(
                    $actual_metric,
                    $converted,
                    $language
                );
                $migrated_count++;
            }
        }
        
        // Marquer migration comme terminée
        update_option( 'tb_modal_migration_completed', time() );
        
        $this->logger->info(
            'Contenu modal migré avec succès',
            [ 'metrics_count' => $migrated_count ],
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Migration directe pour script one-time
     */
    public function migrate_legacy_content(): bool {
        
        try {
            // Récupérer l'ancien contenu
            $old_option = get_option( 'wc_tb_parrainage_help_content', [] );
            
            if ( empty( $old_option ) ) {
                $this->logger->info(
                    'Aucun contenu legacy trouvé pour migration',
                    [],
                    self::LOG_CHANNEL
                );
                return false;
            }
            
            $migrated_count = 0;
            
            // Convertir et sauvegarder dans le nouveau format
            foreach ( $old_option as $key => $content ) {
                // Extraire la langue du key si format: metric_fr
                if ( preg_match( '/^(.+)_([a-z]{2})$/', $key, $matches ) ) {
                    $metric = $matches[1];
                    $lang = $matches[2];
                } else {
                    $metric = $key;
                    $lang = 'fr';
                }
                
                // Convertir le contenu
                $converted = $this->convert_legacy_format( $metric, $content );
                
                if ( ! empty( $converted ) ) {
                    $this->modal_manager->set_modal_content( $metric, $converted, $lang );
                    $migrated_count++;
                }
            }
            
            // Marquer la migration comme effectuée
            update_option( 'tb_modal_migration_completed', time() );
            
            $this->logger->info(
                'Migration legacy terminée',
                [ 'migrated_count' => $migrated_count ],
                self::LOG_CHANNEL
            );
            
            return true;
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur durant migration legacy',
                [ 'error' => $e->getMessage() ],
                self::LOG_CHANNEL
            );
            return false;
        }
    }
    
    /**
     * Convertir le format legacy vers nouveau format
     */
    private function convert_legacy_format( string $metric_key, array $old_content ): array {
        
        $new_content = [
            'title' => $old_content['title'] ?? $this->get_metric_title( $metric_key ),
            'definition' => $old_content['definition'] ?? '',
        ];
        
        // Mapper les champs standards
        $field_mapping = [
            'details' => 'details',
            'formula' => 'formula', 
            'example' => 'example',
            'interpretation' => 'interpretation',
            'tips' => 'tips',
            'precision' => 'precision'
        ];
        
        foreach ( $field_mapping as $old_field => $new_field ) {
            if ( isset( $old_content[ $old_field ] ) ) {
                $new_content[ $new_field ] = $old_content[ $old_field ];
            }
        }
        
        // Champs spécifiques santé système
        if ( isset( $old_content['criteria'] ) ) {
            $new_content['details'] = array_merge(
                $new_content['details'] ?? [],
                $old_content['criteria']
            );
        }
        
        if ( isset( $old_content['levels'] ) ) {
            // Convertir les niveaux en interprétation
            $new_content['interpretation'] = implode( ' | ', $old_content['levels'] );
        }
        
        return $new_content;
    }
    
    /**
     * Obtenir le titre d'une métrique par défaut
     */
    private function get_metric_title( string $metric_key ): string {
        $titles = [
            'total_parrains' => 'Parrains Actifs',
            'total_filleuls' => 'Filleuls Actifs',
            'monthly_total_revenue' => 'Revenus Mensuels HT',
            'monthly_discounts' => 'Remises Mensuelles',
            'roi_current_month' => 'ROI Mois Actuel',
            'total_codes_used' => 'Codes Utilisés',
            'monthly_events' => 'Événements ce mois',
            'webhooks_sent' => 'Webhooks Envoyés',
            'system_health' => 'Santé du Système'
        ];
        
        return $titles[ $metric_key ] ?? ucfirst( str_replace( '_', ' ', $metric_key ) );
    }
    
    /**
     * Récupérer le contenu legacy
     */
    private function get_legacy_modal_content(): array {
        return get_option( 'wc_tb_parrainage_help_content', [] );
    }
    
    /**
     * Configuration compatibilité legacy
     */
    private function setup_legacy_compatibility(): void {
        // Pas besoin de hooks legacy spéciaux pour le moment
        // Le système unifié gère déjà tout
    }
    
    /**
     * Charger les assets si on est sur la page Analytics
     */
    public function maybe_enqueue_assets( string $hook ): void {
        
        // Vérifier qu'on est sur la page Analytics
        if ( 'toplevel_page_wc-tb-parrainage' !== $hook ) {
            return;
        }
        
        // Vérifier l'onglet
        $tab = $_GET['tab'] ?? 'dashboard';
        if ( ! in_array( $tab, [ 'stats', 'analytics', 'reports' ] ) ) {
            return;
        }
        
        // Charger les assets du TemplateModalManager
        $this->modal_manager->enqueue_modal_assets( $hook );
        
        // Script de compatibilité pour l'ancien code
        wp_add_inline_script( 'tb-modal-analytics-script', $this->get_compatibility_script() );
    }
    
    /**
     * Script de compatibilité pour l'ancien code
     */
    private function get_compatibility_script(): string {
        return "
        // Compatibilité avec l'ancien système HelpModalManager
        (function($) {
            // Rediriger les anciens sélecteurs vers le nouveau système
            $(document).on('click', '.tb-help-icon', function(e) {
                e.preventDefault();
                const metric = $(this).data('metric');
                
                // Utiliser le nouveau système TemplateModalManager
                if (window.tbModalAnalyticsAdmin && typeof window.tbModalAnalyticsAdmin.openModal === 'function') {
                    window.tbModalAnalyticsAdmin.openModal(metric);
                } else {
                    console.error('TemplateModalManager not initialized for Analytics Admin');
                }
            });
            
            // Alias pour compatibilité complète
            window.TBHelpModals = window.tbModalAnalyticsAdmin || {};
            
            // Auto-initialisation si l'objet existe
            if (window.tbModalAnalyticsAdmin && typeof window.tbModalAnalyticsAdmin.init === 'function') {
                $(document).ready(function() {
                    window.tbModalAnalyticsAdmin.init();
                });
            }
        })(jQuery);
        ";
    }
    
    /**
     * Rendre une icône d'aide (wrapper pour compatibilité)
     */
    public function render_help_icon( string $metric_key ): void {
        $this->modal_manager->render_help_icon( $metric_key, [
            'icon' => 'dashicons-info-outline',
            'position' => 'inline',
            'title' => sprintf( __( 'Aide sur %s', 'wc-tb-web-parrainage' ), $metric_key )
        ]);
    }
    
    /**
     * Obtenir l'instance du TemplateModalManager pour tests
     */
    public function get_modal_manager(): TemplateModalManager {
        return $this->modal_manager;
    }
    
    /**
     * Nettoyer les données (utilitaire pour développeurs)
     */
    public function cleanup_migration_data(): bool {
        
        $result = $this->modal_manager->cleanup_modal_data();
        delete_option( 'tb_modal_migration_completed' );
        
        if ( $result ) {
            $this->logger->info(
                'Données de migration nettoyées',
                [],
                self::LOG_CHANNEL
            );
        }
        
        return $result;
    }
}
