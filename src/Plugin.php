<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    
    private $logger;
    private $webhook_manager;
    private $parrainage_manager;
    private $coupon_manager;
    private $subscription_pricing_manager;
    private $parrainage_stats_manager;
    private $my_account_parrainage_manager;
    
    // AJOUT v2.5.0 : Classes techniques fondamentales
    private $discount_calculator;
    private $discount_validator;
    private $discount_notification_service;
    
    // AJOUT v2.6.0 : Processeur automatique de workflow asynchrone
    private $automatic_discount_processor;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->init_managers();
        $this->load_discount_classes();
        $this->init_hooks();
    }
    
    private function init_managers() {
        // Version minimaliste pour test progressif
        $this->subscription_pricing_manager = new SubscriptionPricingManager( $this->logger );
        $this->webhook_manager = new WebhookManager( $this->logger, $this->subscription_pricing_manager );
        $this->parrainage_manager = new ParrainageManager( $this->logger );
        $this->coupon_manager = new CouponManager( $this->logger );
        $this->parrainage_stats_manager = new ParrainageStatsManager( $this->logger );
        $this->my_account_parrainage_manager = new MyAccountParrainageManager( $this->logger );
    }
    
    /**
     * NOUVEAU v2.5.0 : Chargement des classes techniques
     * Chargement conditionnel des classes uniquement si nécessaire
     */
    private function load_discount_classes() {
        $this->discount_calculator = new DiscountCalculator( $this->logger );
        $this->discount_validator = new DiscountValidator( $this->logger );
        $this->discount_notification_service = new DiscountNotificationService( $this->logger );
        
        // NOUVEAU v2.6.0 : Chargement du processeur de workflow asynchrone
        $this->automatic_discount_processor = new AutomaticDiscountProcessor( 
            $this->logger, 
            $this->discount_calculator, 
            $this->discount_validator, 
            $this->discount_notification_service 
        );
    }
    
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        
        // Initialiser les modules si activés
        $settings = get_option( 'wc_tb_parrainage_settings', array() );
        
        if ( ! empty( $settings['enable_webhooks'] ) ) {
            $this->webhook_manager->init();
        }
        
        if ( ! empty( $settings['enable_parrainage'] ) ) {
            $this->parrainage_manager->init();
        }
        
        // Initialiser le gestionnaire de coupons si activé
        if ( ! empty( $settings['enable_coupon_hiding'] ) ) {
            $this->coupon_manager->init();
        }
        
        // Initialiser le gestionnaire de tarification d'abonnements si le parrainage est activé (version simple)
        if ( ! empty( $settings['enable_parrainage'] ) ) {
            $this->subscription_pricing_manager->init();
        }
        
        // Initialiser le gestionnaire des statistiques de parrainage
        if ( ! empty( $settings['enable_parrainage'] ) ) {
            $this->parrainage_stats_manager->init();
        }
        
        // Initialiser le gestionnaire de l'onglet "Mes parrainages" côté client
        if ( ! empty( $settings['enable_parrainage'] ) ) {
            $this->my_account_parrainage_manager->init();
        }
        
        // MODIFICATION v2.6.0 : Initialisation directe des services de remise
        $this->init_discount_services();
        
        // Nettoyage automatique des logs
        add_action( 'wp_scheduled_delete', array( $this, 'cleanup_old_logs' ) );
    }
    
    public function add_admin_menu() {
        add_options_page(
            __( 'TB-Web Parrainage', 'wc-tb-web-parrainage' ),
            __( 'TB-Web Parrainage', 'wc-tb-web-parrainage' ),
            'manage_options',
            'wc-tb-parrainage',
            array( $this, 'admin_page' )
        );
    }
    
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès non autorisé', 'wc-tb-web-parrainage' ) );
        }
        
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'logs';
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TB-Web Parrainage', 'wc-tb-web-parrainage' ); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wc-tb-parrainage&tab=logs" class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Logs', 'wc-tb-web-parrainage' ); ?>
                </a>
                <a href="?page=wc-tb-parrainage&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Paramètres', 'wc-tb-web-parrainage' ); ?>
                </a>
                <a href="?page=wc-tb-parrainage&tab=products" class="nav-tab <?php echo $current_tab === 'products' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Configuration Produits', 'wc-tb-web-parrainage' ); ?>
                </a>
                <a href="?page=wc-tb-parrainage&tab=stats" class="nav-tab <?php echo $current_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Statistiques', 'wc-tb-web-parrainage' ); ?>
                </a>
                <a href="?page=wc-tb-parrainage&tab=parrainage" class="nav-tab <?php echo $current_tab === 'parrainage' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Parrainage', 'wc-tb-web-parrainage' ); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'products':
                        $this->render_products_tab();
                        break;
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    case 'parrainage':
                        $this->parrainage_stats_manager->render_parrainage_interface();
                        break;
                    case 'logs':
                    default:
                        $this->render_logs_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_logs_tab() {
        $logs = $this->logger->get_recent_logs( 100 );
        ?>
        <div class="logs-container">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="button" class="button" id="refresh-logs">
                        <?php esc_html_e( 'Actualiser', 'wc-tb-web-parrainage' ); ?>
                    </button>
                    <button type="button" class="button" id="clear-logs">
                        <?php esc_html_e( 'Vider les logs', 'wc-tb-web-parrainage' ); ?>
                    </button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date/Heure', 'wc-tb-web-parrainage' ); ?></th>
                        <th><?php esc_html_e( 'Niveau', 'wc-tb-web-parrainage' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'wc-tb-web-parrainage' ); ?></th>
                        <th><?php esc_html_e( 'Message', 'wc-tb-web-parrainage' ); ?></th>
                        <th><?php esc_html_e( 'Contexte', 'wc-tb-web-parrainage' ); ?></th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <?php if ( empty( $logs ) ) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e( 'Aucun log disponible', 'wc-tb-web-parrainage' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr class="log-level-<?php echo esc_attr( strtolower( $log['level'] ) ); ?>">
                                <td><?php echo esc_html( $log['datetime'] ); ?></td>
                                <td>
                                    <span class="log-level log-level-<?php echo esc_attr( strtolower( $log['level'] ) ); ?>">
                                        <?php echo esc_html( $log['level'] ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log['source'] ?? 'general' ); ?></td>
                                <td><?php echo esc_html( $log['message'] ); ?></td>
                                <td>
                                    <?php if ( ! empty( $log['context'] ) ) : ?>
                                        <details>
                                            <summary><?php esc_html_e( 'Voir détails', 'wc-tb-web-parrainage' ); ?></summary>
                                            <pre><?php echo esc_html( json_encode( $log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_settings_tab() {
        if ( isset( $_POST['save_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'save_tb_parrainage_settings' ) ) {
            $settings = array(
                'enable_webhooks' => isset( $_POST['enable_webhooks'] ),
                'enable_parrainage' => isset( $_POST['enable_parrainage'] ),
                'enable_coupon_hiding' => isset( $_POST['enable_coupon_hiding'] ),
                'log_retention_days' => absint( $_POST['log_retention_days'] )
            );
            
            update_option( 'wc_tb_parrainage_settings', $settings );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Paramètres sauvegardés.', 'wc-tb-web-parrainage' ) . '</p></div>';
        }
        
        $settings = get_option( 'wc_tb_parrainage_settings', array() );
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'save_tb_parrainage_settings' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Activer les webhooks enrichis', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_webhooks" value="1" <?php checked( ! empty( $settings['enable_webhooks'] ) ); ?>>
                            <?php esc_html_e( 'Ajouter les métadonnées d\'abonnement dans les webhooks WooCommerce', 'wc-tb-web-parrainage' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Activer le système de parrainage', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_parrainage" value="1" <?php checked( ! empty( $settings['enable_parrainage'] ) ); ?>>
                            <?php esc_html_e( 'Ajouter le champ code parrain au checkout', 'wc-tb-web-parrainage' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Masquer les codes promo', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_coupon_hiding" value="1" <?php checked( ! empty( $settings['enable_coupon_hiding'] ) ); ?>>
                            <?php esc_html_e( 'Masquer automatiquement les champs code promo pour les produits configurés', 'wc-tb-web-parrainage' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Les codes promo seront masqués au panier et checkout pour les produits configurés dans l\'onglet "Configuration Produits"', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Rétention des logs (jours)', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <input type="number" name="log_retention_days" value="<?php echo esc_attr( $settings['log_retention_days'] ?? 30 ); ?>" min="1" max="365">
                        <p class="description"><?php esc_html_e( 'Nombre de jours de conservation des logs', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button( __( 'Sauvegarder les paramètres', 'wc-tb-web-parrainage' ), 'primary', 'save_settings' ); ?>
        </form>
        <?php
    }
    
    private function render_stats_tab() {
        $stats = $this->get_parrainage_stats();
        ?>
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php esc_html_e( 'Codes parrain utilisés', 'wc-tb-web-parrainage' ); ?></h3>
                    <p class="stat-number"><?php echo esc_html( $stats['total_parrainage'] ); ?></p>
                </div>
                <div class="stat-card">
                    <h3><?php esc_html_e( 'Ce mois', 'wc-tb-web-parrainage' ); ?></h3>
                    <p class="stat-number"><?php echo esc_html( $stats['parrainage_ce_mois'] ); ?></p>
                </div>
                <div class="stat-card">
                    <h3><?php esc_html_e( 'Webhooks envoyés', 'wc-tb-web-parrainage' ); ?></h3>
                    <p class="stat-number"><?php echo esc_html( $stats['webhooks_envoyes'] ); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_parrainage_stats() {
        global $wpdb;
        
        $stats = array(
            'total_parrainage' => 0,
            'parrainage_ce_mois' => 0,
            'webhooks_envoyes' => 0
        );
        
        // Compter les commandes avec code parrain
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_billing_parrain_code' AND meta_value != ''"
        );
        $stats['total_parrainage'] = intval( $total );
        
        // Compter les parrainages ce mois
        $ce_mois = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_billing_parrain_code' 
             AND pm.meta_value != '' 
             AND p.post_date >= %s",
            date( 'Y-m-01' )
        ) );
        $stats['parrainage_ce_mois'] = intval( $ce_mois );
        
        // Compter les webhooks (approximation via logs)
        $webhooks = $this->logger->count_logs_by_source( 'webhook-subscriptions' );
        $stats['webhooks_envoyes'] = $webhooks;
        
        return $stats;
    }
    
    public function admin_assets( $hook ) {
        if ( strpos( $hook, 'wc-tb-parrainage' ) === false ) return;
        
        wp_enqueue_style(
            'wc-tb-parrainage-admin',
            WC_TB_PARRAINAGE_URL . 'assets/admin.css',
            array(),
            WC_TB_PARRAINAGE_VERSION
        );
        
        wp_enqueue_script(
            'wc-tb-parrainage-admin',
            WC_TB_PARRAINAGE_URL . 'assets/admin.js',
            array( 'jquery' ),
            WC_TB_PARRAINAGE_VERSION,
            true
        );
        
        // Charger les assets spécifiques à l'onglet parrainage
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'logs';
        if ( $current_tab === 'parrainage' ) {
            wp_enqueue_style(
                'wc-tb-parrainage-admin-parrainage',
                WC_TB_PARRAINAGE_URL . 'assets/parrainage-admin.css',
                array( 'wc-tb-parrainage-admin' ),
                WC_TB_PARRAINAGE_VERSION
            );
            
            wp_enqueue_script(
                'wc-tb-parrainage-admin-parrainage',
                WC_TB_PARRAINAGE_URL . 'assets/parrainage-admin.js',
                array( 'jquery', 'wc-tb-parrainage-admin' ),
                WC_TB_PARRAINAGE_VERSION,
                true
            );
        }
        
        wp_localize_script( 'wc-tb-parrainage-admin', 'tbParrainageAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tb_parrainage_admin_action' )
        ) );
    }
    
    public function cleanup_old_logs() {
        $settings = get_option( 'wc_tb_parrainage_settings', array() );
        $retention_days = $settings['log_retention_days'] ?? 30;
        
        $this->logger->cleanup_old_logs( $retention_days );
    }
    
    private function render_products_tab() {
        // Traitement de la sauvegarde
        if ( isset( $_POST['save_products'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'save_tb_parrainage_products' ) ) {
            $products_config = array();
            
            // Récupérer les données du formulaire
            if ( isset( $_POST['product_id'] ) && is_array( $_POST['product_id'] ) ) {
                for ( $i = 0; $i < count( $_POST['product_id'] ); $i++ ) {
                    $product_id = absint( $_POST['product_id'][$i] );
                    $description = sanitize_textarea_field( $_POST['description'][$i] ?? '' );
                    $message_validation = sanitize_textarea_field( $_POST['message_validation'][$i] ?? '' );
                    $avantage = sanitize_text_field( $_POST['avantage'][$i] ?? '' );
                    $remise_parrain = sanitize_text_field( $_POST['remise_parrain'][$i] ?? '' );
                    $remise_parrain = str_replace( ',', '.', $remise_parrain ); // Conversion virgule -> point
                    $prix_standard = sanitize_text_field( $_POST['prix_standard'][$i] ?? '' );
                    $prix_standard = str_replace( ',', '.', $prix_standard ); // Conversion virgule -> point
                    $frequence_paiement = sanitize_text_field( $_POST['frequence_paiement'][$i] ?? '' );
                    
                    // Validation prix standard
                    $prix_standard_float = floatval( $prix_standard );
                    if ( $prix_standard_float <= 0 ) {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Le prix standard doit être un montant positif.', 'wc-tb-web-parrainage' ) . '</p></div>';
                        return;
                    }
                    
                    // Validation fréquence paiement
                    $frequences_valides = array( 'unique', 'mensuel', 'annuel' );
                    if ( ! in_array( $frequence_paiement, $frequences_valides, true ) ) {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Fréquence de paiement invalide.', 'wc-tb-web-parrainage' ) . '</p></div>';
                        return;
                    }
                    
                    if ( $product_id > 0 && ! empty( $description ) ) {
                        $products_config[$product_id] = array(
                            'description' => $description,
                            'message_validation' => $message_validation,
                            'avantage' => $avantage,
                            'remise_parrain' => floatval( $remise_parrain ),
                            'prix_standard' => $prix_standard_float,
                            'frequence_paiement' => $frequence_paiement
                        );
                    }
                }
            }
            
                            // Ajouter la configuration par défaut
            if ( isset( $_POST['default_description'] ) ) {
                $default_remise_parrain = sanitize_text_field( $_POST['default_remise_parrain'] ?? '' );
                $default_remise_parrain = str_replace( ',', '.', $default_remise_parrain );
                $default_prix_standard = sanitize_text_field( $_POST['default_prix_standard'] ?? '' );
                $default_prix_standard = str_replace( ',', '.', $default_prix_standard );
                $default_frequence_paiement = sanitize_text_field( $_POST['default_frequence_paiement'] ?? '' );
                
                $products_config['default'] = array(
                    'description' => sanitize_textarea_field( $_POST['default_description'] ),
                    'message_validation' => sanitize_textarea_field( $_POST['default_message_validation'] ?? '' ),
                    'avantage' => sanitize_text_field( $_POST['default_avantage'] ?? '' ),
                    'remise_parrain' => floatval( $default_remise_parrain ),
                    'prix_standard' => floatval( $default_prix_standard ),
                    'frequence_paiement' => $default_frequence_paiement ?: 'mensuel'
                );
            }
            
            // Sauvegarder la configuration
            update_option( 'wc_tb_parrainage_products_config', $products_config );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Configuration produits sauvegardée.', 'wc-tb-web-parrainage' ) . '</p></div>';
            
            // Log de la modification
            $this->logger->info( 
                sprintf( 'Configuration produits mise à jour - %d produit(s) configuré(s)', count( $products_config ) ),
                array( 'products_count' => count( $products_config ), 'products' => array_keys( $products_config ) ),
                'admin-config'
            );
        }
        
        // Récupérer la configuration actuelle
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        
        // Migration depuis l'ancien système si aucune configuration
        if ( empty( $products_config ) ) {
            $products_config = $this->get_default_products_config();
        }
        
        ?>
        <div class="products-config-container">
            <h2><?php esc_html_e( 'Configuration des Messages de Parrainage par Produit', 'wc-tb-web-parrainage' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Configurez les messages de parrainage spécifiques à chaque produit. Les messages seront affichés au checkout selon le produit dans le panier.', 'wc-tb-web-parrainage' ); ?>
            </p>
            
            <form method="post" action="" id="products-config-form">
                <?php wp_nonce_field( 'save_tb_parrainage_products' ); ?>
                
                <div class="products-list">
                    <div class="products-header">
                        <h3><?php esc_html_e( 'Produits Configurés', 'wc-tb-web-parrainage' ); ?></h3>
                        <button type="button" class="button button-secondary" id="add-product">
                            <?php esc_html_e( 'Ajouter un Produit', 'wc-tb-web-parrainage' ); ?>
                        </button>
                    </div>
                    
                    <div id="products-container">
                        <?php if ( empty( $products_config ) ) : ?>
                            <div class="no-products">
                                <p><?php esc_html_e( 'Aucun produit configuré. Cliquez sur "Ajouter un Produit" pour commencer.', 'wc-tb-web-parrainage' ); ?></p>
                            </div>
                        <?php else : ?>
                            <?php $index = 0; foreach ( $products_config as $product_id => $config ) : ?>
                                <?php if ( $product_id !== 'default' ) : ?>
                                    <?php $this->render_product_config_row( $index, $product_id, $config ); ?>
                                    <?php $index++; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="default-config-section">
                    <h3><?php esc_html_e( 'Configuration par Défaut', 'wc-tb-web-parrainage' ); ?></h3>
                    <p class="description">
                        <?php esc_html_e( 'Ces messages seront utilisés pour tous les produits non configurés spécifiquement.', 'wc-tb-web-parrainage' ); ?>
                    </p>
                    
                    <?php 
                    $default_config = $products_config['default'] ?? array(
                        'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                        'message_validation' => 'Code parrain valide ✓',
                        'avantage' => 'Avantage parrainage',
                        'remise_parrain' => 0.00,
                        'prix_standard' => 0.00,
                        'frequence_paiement' => 'mensuel'
                    );
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Description du champ', 'wc-tb-web-parrainage' ); ?></th>
                            <td>
                                <textarea name="default_description" rows="3" class="large-text"><?php echo esc_textarea( wp_unslash( $default_config['description'] ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Texte affiché sous le champ code parrain au checkout', 'wc-tb-web-parrainage' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Message de validation', 'wc-tb-web-parrainage' ); ?></th>
                            <td>
                                <textarea name="default_message_validation" rows="2" class="large-text"><?php echo esc_textarea( wp_unslash( $default_config['message_validation'] ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Message affiché quand le code parrain est valide', 'wc-tb-web-parrainage' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Avantage accordé', 'wc-tb-web-parrainage' ); ?></th>
                            <td>
                                <input type="text" name="default_avantage" value="<?php echo esc_attr( wp_unslash( $default_config['avantage'] ) ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'Description courte de l\'avantage (affiché dans l\'admin commande)', 'wc-tb-web-parrainage' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Remise Parrain (€/mois)', 'wc-tb-web-parrainage' ); ?></th>
                            <td>
                                <input type="text" name="default_remise_parrain" value="<?php echo esc_attr( wp_unslash( $default_config['remise_parrain'] ) ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'Montant de la remise parrain (ex: 10.50)', 'wc-tb-web-parrainage' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Prix standard (€) avant remise parrainage', 'wc-tb-web-parrainage' ); ?></th>
                            <td>
                                <input type="text" 
                                       name="default_prix_standard" 
                                       value="<?php echo esc_attr( wp_unslash( $default_config['prix_standard'] ?? '' ) ); ?>" 
                                       class="regular-text prix-standard-input" 
                                       placeholder="89,99">
                                <p class="description"><?php esc_html_e( 'Prix standard par défaut avant remise parrainage', 'wc-tb-web-parrainage' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Fréquence de paiement par défaut', 'wc-tb-web-parrainage' ); ?></th>
                            <td>
                                <select name="default_frequence_paiement" class="regular-text">
                                    <option value="unique" <?php selected( $default_config['frequence_paiement'] ?? 'mensuel', 'unique' ); ?>>
                                        <?php esc_html_e( 'Paiement unique', 'wc-tb-web-parrainage' ); ?>
                                    </option>
                                    <option value="mensuel" <?php selected( $default_config['frequence_paiement'] ?? 'mensuel', 'mensuel' ); ?>>
                                        <?php esc_html_e( 'Mensuel', 'wc-tb-web-parrainage' ); ?>
                                    </option>
                                    <option value="annuel" <?php selected( $default_config['frequence_paiement'] ?? 'mensuel', 'annuel' ); ?>>
                                        <?php esc_html_e( 'Annuel', 'wc-tb-web-parrainage' ); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button( __( 'Sauvegarder la Configuration', 'wc-tb-web-parrainage' ), 'primary', 'save_products' ); ?>
            </form>
        </div>
        
        <!-- Template pour nouveau produit -->
        <script type="text/template" id="product-row-template">
            <?php $this->render_product_config_row( '{{INDEX}}', '{{PRODUCT_ID}}', array() ); ?>
        </script>
        <?php
    }
    
    private function render_product_config_row( $index, $product_id = '', $config = array() ) {
        $config = wp_parse_args( $config, array(
            'description' => '',
            'message_validation' => '',
            'avantage' => '',
            'remise_parrain' => '',
            'prix_standard' => '',
            'frequence_paiement' => ''
        ) );
        ?>
        <div class="product-config-row" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="product-header">
                <h4><?php esc_html_e( 'Produit', 'wc-tb-web-parrainage' ); ?> #<span class="product-number"><?php echo esc_html( $product_id ?: '{{PRODUCT_ID}}' ); ?></span></h4>
                <button type="button" class="button button-link-delete remove-product">
                    <?php esc_html_e( 'Supprimer', 'wc-tb-web-parrainage' ); ?>
                </button>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'ID Produit WooCommerce', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <input type="number" name="product_id[]" value="<?php echo esc_attr( $product_id ); ?>" min="1" class="small-text product-id-input" required>
                        <p class="description"><?php esc_html_e( 'ID du produit WooCommerce (visible dans Produits > Tous les produits)', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Description du champ', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <textarea name="description[]" rows="3" class="large-text" required><?php echo esc_textarea( wp_unslash( $config['description'] ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Texte affiché sous le champ code parrain au checkout', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Message de validation', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <textarea name="message_validation[]" rows="2" class="large-text"><?php echo esc_textarea( wp_unslash( $config['message_validation'] ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Message affiché quand le code parrain est valide', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Avantage accordé', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <input type="text" name="avantage[]" value="<?php echo esc_attr( wp_unslash( $config['avantage'] ) ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Description courte de l\'avantage (affiché dans l\'admin commande)', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Remise Parrain (€/mois)', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <input type="text" name="remise_parrain[]" value="<?php echo esc_attr( wp_unslash( $config['remise_parrain'] ) ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Montant de la remise parrain (ex: 10.50)', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Prix standard (€) avant remise parrainage', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <input type="text" 
                               name="prix_standard[]" 
                               value="<?php echo esc_attr( wp_unslash( $config['prix_standard'] ?? '' ) ); ?>" 
                               class="regular-text prix-standard-input" 
                               placeholder="89,99"
                               required>
                        <p class="description"><?php esc_html_e( 'Prix affiché avant application de la remise parrainage (format : 89,99)', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Fréquence de paiement', 'wc-tb-web-parrainage' ); ?></th>
                    <td>
                        <select name="frequence_paiement[]" class="regular-text" required>
                            <option value=""><?php esc_html_e( 'Choisir une fréquence...', 'wc-tb-web-parrainage' ); ?></option>
                            <option value="unique" <?php selected( $config['frequence_paiement'] ?? '', 'unique' ); ?>>
                                <?php esc_html_e( 'Paiement unique', 'wc-tb-web-parrainage' ); ?>
                            </option>
                            <option value="mensuel" <?php selected( $config['frequence_paiement'] ?? '', 'mensuel' ); ?>>
                                <?php esc_html_e( 'Mensuel', 'wc-tb-web-parrainage' ); ?>
                            </option>
                            <option value="annuel" <?php selected( $config['frequence_paiement'] ?? '', 'annuel' ); ?>>
                                <?php esc_html_e( 'Annuel', 'wc-tb-web-parrainage' ); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Fréquence de facturation du produit', 'wc-tb-web-parrainage' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function get_default_products_config() {
        // Configuration par défaut basée sur l'ancien code en dur
        return array(
            6713 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez d\'un mois gratuit supplémentaire',
                'avantage' => '1 mois gratuit supplémentaire',
                'remise_parrain' => 0.00,
                'prix_standard' => 0.00,
                'frequence_paiement' => 'mensuel'
            ),
            6524 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez d\'un mois gratuit supplémentaire',
                'avantage' => '1 mois gratuit supplémentaire',
                'remise_parrain' => 0.00,
                'prix_standard' => 0.00,
                'frequence_paiement' => 'mensuel'
            ),
            6519 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez d\'un mois gratuit supplémentaire',
                'avantage' => '1 mois gratuit supplémentaire',
                'remise_parrain' => 0.00,
                'prix_standard' => 0.00,
                'frequence_paiement' => 'mensuel'
            ),
            6354 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez de 10% de remise',
                'avantage' => '10% de remise',
                'remise_parrain' => 0.00,
                'prix_standard' => 0.00,
                'frequence_paiement' => 'mensuel'
            ),
            'default' => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓',
                'avantage' => 'Avantage parrainage',
                'remise_parrain' => 0.00,
                'prix_standard' => 0.00,
                'frequence_paiement' => 'mensuel'
            )
        );
    }
    
    /**
     * NOUVEAU v2.5.0 : Initialisation des services de remise
     */
    public function init_discount_services() {
        if ( $this->discount_calculator && $this->discount_validator ) {
            // MODIFICATION v2.6.0 : Initialisation du workflow asynchrone
            if ( $this->automatic_discount_processor ) {
                $this->automatic_discount_processor->init();
            }
            
            do_action( 'tb_parrainage_discount_services_loaded', $this );
        }
    }
    
    /**
     * NOUVEAU v2.5.0 : Getters pour l'accès aux services depuis l'extérieur
     */
    public function get_discount_calculator() {
        return $this->discount_calculator;
    }
    
    public function get_discount_validator() {
        return $this->discount_validator;
    }
    
    public function get_discount_notification_service() {
        return $this->discount_notification_service;
    }
    
    /**
     * NOUVEAU v2.6.0 : Getter pour accès au processeur automatique
     */
    public function get_automatic_discount_processor() {
        return $this->automatic_discount_processor;
    }
    
    /**
     * NOUVEAU v2.6.0 : Vérification de la santé du workflow asynchrone
     * 
     * @return array Statut de santé avec recommandations
     */
    public function get_workflow_health_status() {
        if ( $this->automatic_discount_processor ) {
            return $this->automatic_discount_processor->check_cron_health();
        }
        
        return array(
            'error' => 'Processeur automatique non initialisé',
            'recommendations' => array( 'Vérifier l\'initialisation du plugin' )
        );
    }
} 