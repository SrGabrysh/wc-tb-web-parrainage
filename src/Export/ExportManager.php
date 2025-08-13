<?php
namespace TBWeb\WCParrainage\Export;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExportManager - Orchestration du module export
 * Responsabilité : Hooks, initialisation, coordination des exports
 * @since 2.7.8
 */
class ExportManager {
    
    // Constantes pour éviter magic numbers
    const AJAX_ACTION_EXPORT = 'tb_parrainage_export_logs';
    const AJAX_ACTION_PREVIEW = 'tb_parrainage_preview_export';
    const DEFAULT_CHUNK_SIZE = 100;
    const MAX_MINUTES = 30;
    
    private $logger;
    private $handler;
    private $validator;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
        $this->handler = new ExportHandler( $logger );
        $this->validator = new ExportValidator( $logger );
    }
    
    /**
     * Initialisation des hooks
     */
    public function init() {
        // Hooks AJAX pour les exports
        add_action( 'wp_ajax_' . self::AJAX_ACTION_EXPORT, array( $this, 'handle_ajax_export' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION_PREVIEW, array( $this, 'handle_ajax_preview' ) );
        
        $this->logger->debug( 
            'ExportManager initialisé',
            array( 'module' => 'export' ),
            'export-manager'
        );
    }
    
    /**
     * Handler AJAX pour l'export des logs
     */
    public function handle_ajax_export() {
        // Vérification de sécurité
        if ( ! $this->validator->validate_ajax_request() ) {
            return;
        }
        
        try {
            // Validation des paramètres
            $params = $this->validator->validate_export_params( $_POST );
            if ( ! $params ) {
                wp_send_json_error( array(
                    'message' => __( 'Paramètres d\'export invalides', 'wc-tb-web-parrainage' )
                ) );
                return;
            }
            
            // Exécution de l'export
            $result = $this->handler->export_logs( $params );
            
            if ( $result['success'] ) {
                // Log de l'action pour traçabilité
                $this->logger->info(
                    sprintf( 'Export logs effectué par %s', wp_get_current_user()->user_login ),
                    array( 
                        'params' => $params,
                        'file_size' => $result['file_size'],
                        'logs_count' => $result['logs_count']
                    ),
                    'admin-action'
                );
                
                wp_send_json_success( $result );
            } else {
                wp_send_json_error( array(
                    'message' => $result['message'] ?? __( 'Erreur lors de l\'export', 'wc-tb-web-parrainage' )
                ) );
            }
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur lors de l\'export des logs',
                array( 'error' => $e->getMessage() ),
                'export-manager'
            );
            
            wp_send_json_error( array(
                'message' => __( 'Erreur technique lors de l\'export', 'wc-tb-web-parrainage' )
            ) );
        }
    }
    
    /**
     * Handler AJAX pour preview des logs à exporter
     */
    public function handle_ajax_preview() {
        // Vérification de sécurité
        if ( ! $this->validator->validate_ajax_request() ) {
            return;
        }
        
        try {
            // Validation des paramètres
            $params = $this->validator->validate_export_params( $_POST );
            if ( ! $params ) {
                wp_send_json_error( array(
                    'message' => __( 'Paramètres de preview invalides', 'wc-tb-web-parrainage' )
                ) );
                return;
            }
            
            // Génération du preview
            $preview = $this->handler->get_export_preview( $params );
            
            wp_send_json_success( $preview );
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur lors du preview d\'export',
                array( 'error' => $e->getMessage() ),
                'export-manager'
            );
            
            wp_send_json_error( array(
                'message' => __( 'Erreur lors du preview', 'wc-tb-web-parrainage' )
            ) );
        }
    }
    
    /**
     * Génération des options de durée pour le sélecteur
     * @return array Options du menu déroulant
     */
    public function get_duration_options() {
        $options = array();
        
        // 1-10 minutes (minute par minute)
        for ( $i = 1; $i <= 10; $i++ ) {
            $options[$i] = sprintf( 
                _n( '%d minute', '%d minutes', $i, 'wc-tb-web-parrainage' ), 
                $i 
            );
        }
        
        // 15-30 minutes (par 5 minutes)
        for ( $i = 15; $i <= self::MAX_MINUTES; $i += 5 ) {
            $options[$i] = sprintf( 
                __( '%d minutes', 'wc-tb-web-parrainage' ), 
                $i 
            );
        }
        
        return $options;
    }
    
    /**
     * Génération des options de niveau de log
     * @return array Options des niveaux
     */
    public function get_level_options() {
        return array(
            'ALL' => __( 'Tous les niveaux', 'wc-tb-web-parrainage' ),
            'ERROR' => __( 'Erreurs uniquement', 'wc-tb-web-parrainage' ),
            'WARNING' => __( 'Avertissements et erreurs', 'wc-tb-web-parrainage' ),
            'INFO' => __( 'Info, avertissements et erreurs', 'wc-tb-web-parrainage' ),
            'DEBUG' => __( 'Tous (incluant debug)', 'wc-tb-web-parrainage' )
        );
    }
    
    /**
     * Génération des options de source
     * @return array Options des sources
     */
    public function get_source_options() {
        // Récupération dynamique des sources depuis la base
        global $wpdb;
        $table_name = $wpdb->prefix . 'tb_parrainage_logs';
        
        $sources = $wpdb->get_col(
            "SELECT DISTINCT source FROM {$table_name} WHERE source IS NOT NULL ORDER BY source"
        );
        
        $options = array(
            'ALL' => __( 'Toutes les sources', 'wc-tb-web-parrainage' )
        );
        
        foreach ( $sources as $source ) {
            $options[$source] = ucfirst( str_replace( '-', ' ', $source ) );
        }
        
        return $options;
    }
    
    /**
     * Rendu du modal d'export
     */
    public function render_export_modal() {
        $duration_options = $this->get_duration_options();
        $level_options = $this->get_level_options();
        $source_options = $this->get_source_options();
        
        ?>
        <div id="export-logs-modal" class="export-modal" style="display: none;">
            <div class="export-modal-content">
                <div class="export-modal-header">
                    <h2><?php esc_html_e( 'Exporter les Logs', 'wc-tb-web-parrainage' ); ?></h2>
                    <button type="button" class="export-modal-close">&times;</button>
                </div>
                
                <div class="export-modal-body">
                    <div class="export-tabs">
                        <button type="button" class="export-tab-btn active" data-tab="all">
                            <?php esc_html_e( 'Tous les logs', 'wc-tb-web-parrainage' ); ?>
                        </button>
                        <button type="button" class="export-tab-btn" data-tab="duration">
                            <?php esc_html_e( 'Par durée', 'wc-tb-web-parrainage' ); ?>
                        </button>
                    </div>
                    
                    <div class="export-tab-content">
                        <div id="export-tab-all" class="export-tab-panel active">
                            <p><?php esc_html_e( 'Exporter tous les logs disponibles dans la base de données.', 'wc-tb-web-parrainage' ); ?></p>
                        </div>
                        
                        <div id="export-tab-duration" class="export-tab-panel">
                            <label for="export-duration"><?php esc_html_e( 'Durée (minutes) :', 'wc-tb-web-parrainage' ); ?></label>
                            <select id="export-duration" name="duration">
                                <?php foreach ( $duration_options as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Logs des X dernières minutes', 'wc-tb-web-parrainage' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="export-filters">
                        <h3><?php esc_html_e( 'Filtres avancés', 'wc-tb-web-parrainage' ); ?></h3>
                        
                        <div class="export-filter-row">
                            <label for="export-level"><?php esc_html_e( 'Niveau de log :', 'wc-tb-web-parrainage' ); ?></label>
                            <select id="export-level" name="level">
                                <?php foreach ( $level_options as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="export-filter-row">
                            <label for="export-source"><?php esc_html_e( 'Source :', 'wc-tb-web-parrainage' ); ?></label>
                            <select id="export-source" name="source">
                                <?php foreach ( $source_options as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="export-modal-footer">
                    <button type="button" class="button button-secondary" id="export-cancel">
                        <?php esc_html_e( 'Annuler', 'wc-tb-web-parrainage' ); ?>
                    </button>
                    <button type="button" class="button button-primary" id="export-download">
                        <?php esc_html_e( 'Télécharger', 'wc-tb-web-parrainage' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
