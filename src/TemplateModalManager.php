<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Template générique pour modales d'aide
 * 
 * Système modulaire pour créer des modales visuellement identiques
 * aux modales Analytics dans n'importe quelle partie du site
 * 
 * @since 2.14.1
 * @version 1.0.0
 */
class TemplateModalManager {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Configuration par défaut
     * @var array
     */
    private $config;
    
    /**
     * Namespace unique pour éviter les conflits
     * @var string
     */
    private $namespace;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'template-modal-manager';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     * @param array $config Configuration personnalisée
     * @param string $namespace Namespace unique pour éviter conflits
     */
    public function __construct( $logger, array $config = [], string $namespace = 'generic' ) {
        $this->logger = $logger;
        $this->namespace = sanitize_key( $namespace );
        
        // Configuration par défaut fusionnée avec config personnalisée
        $this->config = wp_parse_args( $config, [
            'modal_width' => 600,
            'modal_max_height' => 800,
            'enable_multilang' => false,
            'default_language' => 'fr',
            'ajax_action_prefix' => 'tb_modal_' . $this->namespace,
            'css_prefix' => 'tb-modal-' . $this->namespace,
            'storage_option' => 'tb_modal_content_' . $this->namespace,
            'cache_duration' => 300, // 5 minutes
            'load_dashicons' => true,
            'enable_keyboard_nav' => true,
            'enable_cache' => true
        ] );
        
        $this->logger->info(
            'TemplateModalManager initialisé',
            [
                'namespace' => $this->namespace,
                'config' => $this->config
            ],
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Initialisation des hooks WordPress
     * 
     * @return void
     */
    public function init(): void {
        
        // Hooks AJAX avec namespace
        $ajax_action = $this->config['ajax_action_prefix'] . '_get_content';
        add_action( 'wp_ajax_' . $ajax_action, [ $this, 'ajax_get_modal_content' ] );
        
        if ( $this->config['enable_multilang'] ) {
            $lang_action = $this->config['ajax_action_prefix'] . '_set_language';
            add_action( 'wp_ajax_' . $lang_action, [ $this, 'ajax_set_language' ] );
        }
        
        // Initialiser le contenu par défaut si nécessaire
        add_action( 'admin_init', [ $this, 'maybe_initialize_default_content' ] );
        
        $this->logger->info(
            'TemplateModalManager hooks enregistrés',
            [
                'namespace' => $this->namespace,
                'ajax_action' => $ajax_action
            ],
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Charger les assets CSS/JS pour les modales
     * 
     * @param string $hook Hook admin actuel
     * @param array $dependencies Dépendances additionnelles
     * @return void
     */
    public function enqueue_modal_assets( string $hook = '', array $dependencies = [] ): void {
        
        $css_handle = $this->config['css_prefix'] . '-style';
        $js_handle = $this->config['css_prefix'] . '-script';
        
        // CSS générique pour modales
        wp_enqueue_style(
            $css_handle,
            WC_TB_PARRAINAGE_URL . 'assets/css/template-modals.css',
            [],
            WC_TB_PARRAINAGE_VERSION
        );
        
        // Charger Dashicons si demandé
        if ( $this->config['load_dashicons'] ) {
            wp_enqueue_style( 'dashicons' );
        }
        
        // JavaScript générique pour modales
        $js_dependencies = array_merge( [ 'jquery', 'jquery-ui-dialog' ], $dependencies );
        wp_enqueue_script(
            $js_handle,
            WC_TB_PARRAINAGE_URL . 'assets/js/template-modals.js',
            $js_dependencies,
            WC_TB_PARRAINAGE_VERSION,
            true
        );
        
        // NOUVEAU : Script d'auto-initialisation
        wp_enqueue_script(
            $js_handle . '-init',
            WC_TB_PARRAINAGE_URL . 'assets/js/template-modals-init.js',
            [ $js_handle ],
            WC_TB_PARRAINAGE_VERSION,
            true
        );
        
        // Localisation pour AJAX et configuration
        wp_localize_script( $js_handle, $this->get_js_object_name(), [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( $this->get_nonce_action() ),
            'namespace' => $this->namespace,
            'config' => [
                'modalWidth' => $this->config['modal_width'],
                'modalMaxHeight' => $this->config['modal_max_height'],
                'enableCache' => $this->config['enable_cache'],
                'cacheDuration' => $this->config['cache_duration']
            ],
            'ajaxActions' => [
                'getContent' => $this->config['ajax_action_prefix'] . '_get_content',
                'setLanguage' => $this->config['ajax_action_prefix'] . '_set_language'
            ],
            'cssClasses' => [
                'icon' => $this->config['css_prefix'] . '-icon',
                'modal' => $this->config['css_prefix'] . '-modal',
                'content' => $this->config['css_prefix'] . '-content'
            ],
            'currentLanguage' => $this->get_user_language(),
            'strings' => $this->get_localized_strings()
        ] );
        
        if ( $this->logger ) {
            $this->logger->info(
                'Assets modales chargés avec auto-init',
                [
                    'namespace' => $this->namespace,
                    'js_object' => $this->get_js_object_name()
                ],
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Rendre l'icône d'aide pour un élément
     * 
     * @param string $element_key Clé de l'élément
     * @param array $options Options d'affichage
     * @return void
     */
    public function render_help_icon( string $element_key, array $options = [] ): void {
        
        $options = wp_parse_args( $options, [
            'icon' => 'dashicons-info-outline',
            'title' => __( 'Aide sur cet élément', 'wc-tb-web-parrainage' ),
            'position' => 'inline', // inline, absolute, float-right
            'size' => 'normal', // small, normal, large
            'custom_classes' => []
        ] );
        
        $css_classes = [
            $this->config['css_prefix'] . '-icon',
            $this->config['css_prefix'] . '-icon-' . $options['position'],
            $this->config['css_prefix'] . '-icon-' . $options['size']
        ];
        
        if ( ! empty( $options['custom_classes'] ) ) {
            $css_classes = array_merge( $css_classes, (array) $options['custom_classes'] );
        }
        
        ?>
        <span class="<?php echo esc_attr( implode( ' ', $css_classes ) ); ?>" 
              data-modal-key="<?php echo esc_attr( $element_key ); ?>"
              data-namespace="<?php echo esc_attr( $this->namespace ); ?>"
              title="<?php echo esc_attr( $options['title'] ); ?>"
              tabindex="0"
              role="button"
              aria-label="<?php echo esc_attr( $options['title'] ); ?>">
            <i class="dashicons <?php echo esc_attr( $options['icon'] ); ?>"></i>
        </span>
        <?php
    }
    
    /**
     * AJAX: Récupérer le contenu d'une modale
     * 
     * @return void
     */
    public function ajax_get_modal_content(): void {
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', $this->get_nonce_action() ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        $element_key = sanitize_text_field( $_POST['element_key'] ?? '' );
        $language = sanitize_text_field( $_POST['language'] ?? $this->get_user_language() );
        $namespace = sanitize_text_field( $_POST['namespace'] ?? '' );
        
        // Vérifier que le namespace correspond
        if ( $namespace !== $this->namespace ) {
            wp_send_json_error( __( 'Namespace incorrect', 'wc-tb-web-parrainage' ) );
        }
        
        if ( empty( $element_key ) ) {
            wp_send_json_error( __( 'Élément non spécifié', 'wc-tb-web-parrainage' ) );
        }
        
        try {
            
            $content = $this->get_modal_content( $element_key, $language );
            
            if ( empty( $content ) ) {
                wp_send_json_error( __( 'Contenu non trouvé', 'wc-tb-web-parrainage' ) );
            }
            
            wp_send_json_success( [
                'content' => $content,
                'element_key' => $element_key,
                'language' => $language,
                'namespace' => $this->namespace
            ] );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur récupération contenu modal',
                [
                    'namespace' => $this->namespace,
                    'element_key' => $element_key,
                    'error' => $e->getMessage()
                ],
                self::LOG_CHANNEL
            );
            
            wp_send_json_error( __( 'Erreur lors du chargement', 'wc-tb-web-parrainage' ) );
        }
    }
    
    /**
     * AJAX: Définir la langue préférée (si multilingue activé)
     * 
     * @return void
     */
    public function ajax_set_language(): void {
        
        if ( ! $this->config['enable_multilang'] ) {
            wp_send_json_error( __( 'Fonctionnalité multilingue désactivée', 'wc-tb-web-parrainage' ) );
        }
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', $this->get_nonce_action() ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        $language = sanitize_text_field( $_POST['language'] ?? $this->config['default_language'] );
        
        // Valider la langue (vous pouvez étendre cette liste)
        $supported_languages = [ 'fr', 'en', 'es', 'de', 'it' ];
        if ( ! in_array( $language, $supported_languages, true ) ) {
            wp_send_json_error( __( 'Langue non supportée', 'wc-tb-web-parrainage' ) );
        }
        
        // Sauvegarder la préférence utilisateur
        $user_meta_key = 'tb_modal_language_' . $this->namespace;
        update_user_meta( get_current_user_id(), $user_meta_key, $language );
        
        wp_send_json_success( [
            'language' => $language,
            'namespace' => $this->namespace,
            'message' => __( 'Langue mise à jour', 'wc-tb-web-parrainage' )
        ] );
    }
    
    /**
     * Définir le contenu d'un élément modal
     * 
     * @param string $element_key Clé de l'élément
     * @param array $content_data Données de contenu
     * @param string $language Langue (optionnel si multilingue désactivé)
     * @return bool Succès de l'opération
     */
    public function set_modal_content( string $element_key, array $content_data, string $language = '' ): bool {
        
        if ( ! $this->config['enable_multilang'] ) {
            $language = $this->config['default_language'];
        } elseif ( empty( $language ) ) {
            $language = $this->get_user_language();
        }
        
        $all_content = get_option( $this->config['storage_option'], [] );
        
        // Valider et nettoyer les données
        $content_data = $this->validate_content_data( $content_data );
        
        if ( empty( $content_data ) ) {
            return false;
        }
        
        // Stocker le contenu
        $all_content[ $element_key ][ $language ] = $content_data;
        
        $result = update_option( $this->config['storage_option'], $all_content );
        
        if ( $result ) {
            $this->logger->info(
                'Contenu modal défini',
                [
                    'namespace' => $this->namespace,
                    'element_key' => $element_key,
                    'language' => $language
                ],
                self::LOG_CHANNEL
            );
        }
        
        return $result;
    }
    
    /**
     * Définir plusieurs contenus en batch
     * VERSION CORRIGÉE avec gestion d'erreur
     * 
     * @param array $batch_content Tableau associatif [element_key => content_data]
     * @param string $language Langue
     * @return bool Succès de l'opération
     */
    public function set_batch_modal_content( array $batch_content, string $language = '' ): bool {
        
        if ( empty( $batch_content ) ) {
            if ( $this->logger ) {
                $this->logger->error(
                    'Batch content vide fourni',
                    [],
                    self::LOG_CHANNEL
                );
            }
            return false;
        }
        
        if ( ! $this->config['enable_multilang'] ) {
            $language = $this->config['default_language'];
        } elseif ( empty( $language ) ) {
            $language = $this->get_user_language();
        }
        
        $all_content = get_option( $this->config['storage_option'], [] );
        $updated_count = 0;
        
        foreach ( $batch_content as $element_key => $content_data ) {
            
            $validated_content = $this->validate_content_data( $content_data );
            
            if ( ! empty( $validated_content ) ) {
                $all_content[ $element_key ][ $language ] = $validated_content;
                $updated_count++;
            }
        }
        
        if ( $updated_count > 0 ) {
            $result = update_option( $this->config['storage_option'], $all_content );
            
            if ( $this->logger ) {
                $this->logger->info(
                    'Batch modal content défini',
                    [
                        'namespace' => $this->namespace,
                        'count' => $updated_count,
                        'language' => $language
                    ],
                    self::LOG_CHANNEL
                );
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Obtenir le contenu d'un élément modal
     * 
     * @param string $element_key Clé de l'élément
     * @param string $language Langue demandée
     * @return array|null Contenu ou null si non trouvé
     */
    private function get_modal_content( string $element_key, string $language ): ?array {
        
        $all_content = get_option( $this->config['storage_option'], [] );
        
        // Récupérer le contenu pour cette clé et langue
        $content = $all_content[ $element_key ][ $language ] ?? null;
        
        // Fallback vers langue par défaut si contenu manquant
        if ( empty( $content ) && $language !== $this->config['default_language'] ) {
            $content = $all_content[ $element_key ][ $this->config['default_language'] ] ?? null;
        }
        
        return $content;
    }
    
    /**
     * Obtenir la langue préférée de l'utilisateur
     * 
     * @return string Code langue
     */
    private function get_user_language(): string {
        
        if ( ! $this->config['enable_multilang'] ) {
            return $this->config['default_language'];
        }
        
        $user_meta_key = 'tb_modal_language_' . $this->namespace;
        $user_language = get_user_meta( get_current_user_id(), $user_meta_key, true );
        
        return ! empty( $user_language ) ? $user_language : $this->config['default_language'];
    }
    
    /**
     * Valider et nettoyer les données de contenu
     * 
     * @param array $content_data Données brutes
     * @return array Données validées
     */
    private function validate_content_data( array $content_data ): array {
        
        $validated = [];
        
        // Champs obligatoires
        if ( ! empty( $content_data['title'] ) ) {
            $validated['title'] = sanitize_text_field( $content_data['title'] );
        }
        
        if ( ! empty( $content_data['content'] ) ) {
            $validated['content'] = wp_kses_post( $content_data['content'] );
        }
        
        // Champs optionnels
        $optional_fields = [ 'definition', 'details', 'interpretation', 'tips', 'example', 'formula', 'precision' ];
        
        foreach ( $optional_fields as $field ) {
            if ( ! empty( $content_data[ $field ] ) ) {
                if ( is_array( $content_data[ $field ] ) ) {
                    $validated[ $field ] = array_map( 'sanitize_text_field', $content_data[ $field ] );
                } else {
                    $validated[ $field ] = sanitize_textarea_field( $content_data[ $field ] );
                }
            }
        }
        
        return $validated;
    }
    
    /**
     * Initialiser le contenu par défaut si nécessaire
     * 
     * @return void
     */
    public function maybe_initialize_default_content(): void {
        
        $existing_content = get_option( $this->config['storage_option'], false );
        
        if ( false === $existing_content ) {
            // Créer une option vide plutôt qu'un contenu par défaut
            update_option( $this->config['storage_option'], [] );
            
            $this->logger->info(
                'Stockage contenu modal initialisé',
                [ 'namespace' => $this->namespace ],
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Obtenir le nom de l'objet JavaScript
     * VERSION CORRIGÉE - Gestion correcte des underscores
     * 
     * @return string
     */
    private function get_js_object_name(): string {
        // Convertir client_account en ClientAccount pour avoir tbModalClientAccount
        $parts = explode('_', $this->namespace);
        $camelCase = implode('', array_map('ucfirst', $parts));
        return 'tbModal' . $camelCase;
    }
    
    /**
     * Obtenir l'action pour le nonce
     * 
     * @return string
     */
    private function get_nonce_action(): string {
        return 'tb_modal_nonce_' . $this->namespace;
    }
    
    /**
     * Obtenir les chaînes localisées
     * 
     * @return array
     */
    private function get_localized_strings(): array {
        return [
            'loading' => __( 'Chargement...', 'wc-tb-web-parrainage' ),
            'error' => __( 'Erreur lors du chargement', 'wc-tb-web-parrainage' ),
            'close' => __( 'Fermer', 'wc-tb-web-parrainage' ),
            'language' => __( 'Langue', 'wc-tb-web-parrainage' ),
            'help' => __( 'Aide', 'wc-tb-web-parrainage' )
        ];
    }
    
    /**
     * Nettoyer les données (utilitaire pour développeurs)
     * 
     * @return bool Succès de l'opération
     */
    public function cleanup_modal_data(): bool {
        
        $result = delete_option( $this->config['storage_option'] );
        
        if ( $result ) {
            $this->logger->info(
                'Données modales nettoyées',
                [ 'namespace' => $this->namespace ],
                self::LOG_CHANNEL
            );
        }
        
        return $result;
    }
    
    /**
     * Obtenir les statistiques d'utilisation
     * 
     * @return array Statistiques
     */
    public function get_usage_stats(): array {
        
        $all_content = get_option( $this->config['storage_option'], [] );
        
        $stats = [
            'total_elements' => count( $all_content ),
            'languages' => [],
            'elements_count_by_language' => []
        ];
        
        foreach ( $all_content as $element_key => $element_content ) {
            foreach ( $element_content as $language => $content ) {
                if ( ! in_array( $language, $stats['languages'] ) ) {
                    $stats['languages'][] = $language;
                }
                
                if ( ! isset( $stats['elements_count_by_language'][ $language ] ) ) {
                    $stats['elements_count_by_language'][ $language ] = 0;
                }
                
                $stats['elements_count_by_language'][ $language ]++;
            }
        }
        
        return $stats;
    }
}
