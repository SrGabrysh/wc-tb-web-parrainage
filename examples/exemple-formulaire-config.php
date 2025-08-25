<?php
/**
 * Exemple : Formulaire de Configuration avec Modales d'Aide
 * 
 * Démonstration d'un formulaire de paramètres utilisant le Template Modal System
 * pour ajouter des explications détaillées sur chaque champ
 * 
 * @since 2.14.1
 * @example Parfait pour pages de configuration, paramètres de plugins, etc.
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe d'exemple pour un formulaire de configuration avec modales
 */
class ExempleFormulaireConfig {
    
    private $modal_manager;
    private $logger;
    private $option_name = 'exemple_config_settings';
    
    public function __construct() {
        // Récupérer le logger du plugin TB-Web Parrainage
        global $wc_tb_parrainage_plugin;
        $this->logger = $wc_tb_parrainage_plugin ? $wc_tb_parrainage_plugin->get_logger() : null;
        
        // Créer l'instance avec config spécialisée pour formulaires
        $this->modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
            $this->logger,
            [
                'modal_width' => 500,              // Plus étroit pour formulaires
                'modal_max_height' => 450,         
                'enable_cache' => true,            
                'load_dashicons' => true,
                'enable_multilang' => false,       // Pas de multilingue pour cet exemple
            ],
            'config_form'                          // Namespace dédié
        );
        
        $this->modal_manager->init();
        
        // Hooks WordPress
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'init_modal_content' ] );
    }
    
    /**
     * Ajouter la page au menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Exemple Configuration',
            'Exemple Config',
            'manage_options',
            'exemple-config',
            [ $this, 'render_config_page' ]
        );
    }
    
    /**
     * Enregistrer les paramètres WordPress
     */
    public function register_settings() {
        register_setting( 'exemple_config_group', $this->option_name );
        
        // Section Email
        add_settings_section(
            'email_section',
            'Configuration Email',
            [ $this, 'section_email_callback' ],
            'exemple-config'
        );
        
        // Section Performance
        add_settings_section(
            'performance_section',
            'Optimisation Performance',
            [ $this, 'section_performance_callback' ],
            'exemple-config'
        );
        
        // Section Sécurité
        add_settings_section(
            'security_section',
            'Paramètres de Sécurité',
            [ $this, 'section_security_callback' ],
            'exemple-config'
        );
    }
    
    /**
     * Initialiser le contenu des modales d'aide
     */
    public function init_modal_content() {
        
        $modal_contents = [
            // Configuration Email
            'smtp_host' => [
                'title' => 'Serveur SMTP',
                'definition' => 'Adresse du serveur de messagerie sortante utilisé pour envoyer les emails depuis votre site.',
                'details' => [
                    'Format typique : smtp.votre-fournisseur.com',
                    'Obligatoire pour l\'envoi d\'emails via SMTP',
                    'Fourni par votre hébergeur ou service email'
                ],
                'example' => 'Exemples courants : smtp.gmail.com, smtp.ovh.net, mail.votre-domaine.com',
                'tips' => [
                    'Vérifiez auprès de votre hébergeur les paramètres corrects',
                    'Testez la connexion après configuration',
                    'Utilisez SSL/TLS pour plus de sécurité'
                ]
            ],
            
            'smtp_port' => [
                'title' => 'Port SMTP',
                'definition' => 'Numéro de port utilisé pour la connexion au serveur SMTP.',
                'details' => [
                    'Port 25 : Standard mais souvent bloqué par les hébergeurs',
                    'Port 587 : STARTTLS - Recommandé pour la plupart des cas',
                    'Port 465 : SSL/TLS - Alternative sécurisée'
                ],
                'interpretation' => 'Le choix du port dépend de la configuration de votre serveur SMTP et du niveau de sécurité souhaité.',
                'tips' => [
                    'Utilisez le port 587 si possible (plus compatible)',
                    'Activez toujours le chiffrement (SSL/TLS)',
                    'Contactez votre hébergeur en cas de doute'
                ]
            },
            
            'smtp_auth' => [
                'title' => 'Authentification SMTP',
                'definition' => 'Méthode d\'authentification utilisée pour se connecter au serveur SMTP.',
                'details' => [
                    'Login/Mot de passe : Méthode standard la plus répandue',
                    'OAuth2 : Méthode moderne et sécurisée (Gmail, Outlook)',
                    'Aucune : Serveurs locaux ou configurations spéciales'
                ],
                'tips' => [
                    'Utilisez toujours l\'authentification pour les serveurs externes',
                    'OAuth2 est plus sûr que login/mot de passe',
                    'Changez régulièrement vos mots de passe'
                ]
            ],
            
            // Performance
            'cache_enabled' => [
                'title' => 'Cache Activé',
                'definition' => 'Active la mise en cache des pages pour améliorer les temps de chargement.',
                'details' => [
                    'Stocke temporairement les pages générées',
                    'Réduit la charge serveur',
                    'Améliore l\'expérience utilisateur'
                ],
                'interpretation' => 'Le cache peut réduire les temps de chargement de 50-80% sur un site WordPress standard.',
                'precision' => 'Attention : peut causer des problèmes avec du contenu dynamique (panier, compte utilisateur).',
                'tips' => [
                    'Activez toujours sauf cas spéciaux',
                    'Configurez des exclusions pour les pages dynamiques',
                    'Videz le cache après modifications importantes'
                ]
            ],
            
            'cache_duration' => [
                'title' => 'Durée du Cache',
                'definition' => 'Temps pendant lequel les pages sont conservées en cache avant régénération.',
                'formula' => 'Durée en secondes (3600 = 1 heure)',
                'details' => [
                    '1 heure (3600s) : Sites mis à jour quotidiennement',
                    '6 heures (21600s) : Sites mis à jour hebdomadairement', 
                    '24 heures (86400s) : Sites statiques ou peu modifiés'
                ],
                'interpretation' => 'Plus la durée est longue, meilleures sont les performances, mais plus le contenu peut être obsolète.',
                'tips' => [
                    'Ajustez selon la fréquence de vos mises à jour',
                    'Commencez par 6 heures puis optimisez',
                    'Réduisez pour les sites e-commerce (stock, prix)'
                ]
            },
            
            'minify_css' => [
                'title' => 'Compression CSS',
                'definition' => 'Supprime les espaces et commentaires inutiles des fichiers CSS pour réduire leur taille.',
                'details' => [
                    'Réduit la taille des fichiers de 20-40%',
                    'Améliore les temps de chargement',
                    'N\'affecte pas le fonctionnement'
                ],
                'interpretation' => 'Optimisation sans risque qui améliore toujours les performances.',
                'tips' => [
                    'Activez toujours cette option',
                    'Testez votre site après activation',
                    'Désactivez temporairement si problème d\'affichage'
                ]
            ],
            
            // Sécurité
            'login_attempts' => [
                'title' => 'Tentatives de Connexion',
                'definition' => 'Nombre maximum de tentatives de connexion échouées autorisées avant blocage temporaire.',
                'details' => [
                    '3 tentatives : Sécurité élevée (peut gêner utilisateurs légitimes)',
                    '5 tentatives : Équilibre sécurité/convivialité (recommandé)',
                    '10 tentatives : Sécurité faible mais très tolérant'
                ],
                'interpretation' => 'Protection contre les attaques par force brute. Plus le nombre est bas, plus la sécurité est élevée.',
                'tips' => [
                    'Utilisez 5 tentatives pour la plupart des sites',
                    'Réduisez à 3 pour les sites sensibles',
                    'Informez vos utilisateurs de cette limite'
                ]
            ],
            
            'lockout_duration' => [
                'title' => 'Durée de Blocage',
                'definition' => 'Temps pendant lequel un utilisateur est bloqué après avoir dépassé le nombre de tentatives autorisées.',
                'formula' => 'Durée en minutes',
                'details' => [
                    '15 minutes : Blocage court, utilisateur peut réessayer rapidement',
                    '60 minutes : Équilibre entre sécurité et convivialité',
                    '24 heures : Blocage long pour maximum de sécurité'
                ],
                'interpretation' => 'Un blocage trop court réduit l\'efficacité, trop long peut frustruer les utilisateurs légitimes.',
                'tips' => [
                    'Commencez par 60 minutes',
                    'Réduisez si vous recevez des plaintes d\'utilisateurs',
                    'Augmentez si vous subissez beaucoup d\'attaques'
                ]
            ],
            
            'two_factor_auth' => [
                'title' => 'Authentification à Deux Facteurs',
                'definition' => 'Ajoute une couche de sécurité supplémentaire en demandant un code en plus du mot de passe.',
                'details' => [
                    'SMS : Code envoyé par message (simple mais moins sécurisé)',
                    'Authentificateur : Application mobile (Google Auth, Authy)',
                    'Email : Code envoyé par email (pratique mais vulnérable)'
                ],
                'interpretation' => 'Réduit drastiquement les risques de piratage même en cas de mot de passe compromis.',
                'precision' => 'Recommandé pour tous les administrateurs et utilisateurs privilégiés.',
                'tips' => [
                    'Activez au minimum pour les administrateurs',
                    'Utilisez une app authentificateur plutôt que SMS',
                    'Gardez des codes de récupération en sécurité'
                ]
            ]
        ];
        
        $this->modal_manager->set_batch_modal_content( $modal_contents );
    }
    
    /**
     * Callback pour section email
     */
    public function section_email_callback() {
        echo '<p>Configurez les paramètres d\'envoi d\'emails pour votre site.</p>';
    }
    
    /**
     * Callback pour section performance
     */
    public function section_performance_callback() {
        echo '<p>Optimisez les performances de votre site WordPress.</p>';
    }
    
    /**
     * Callback pour section sécurité
     */
    public function section_security_callback() {
        echo '<p>Renforcez la sécurité de votre site contre les attaques.</p>';
    }
    
    /**
     * Rendre la page de configuration
     */
    public function render_config_page() {
        
        // Charger les assets des modales
        $this->modal_manager->enqueue_modal_assets();
        
        // Traitement du formulaire
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'exemple_config_group-options' ) ) {
            // WordPress gère automatiquement la sauvegarde via register_setting()
            echo '<div class="notice notice-success"><p>Configuration sauvegardée avec succès !</p></div>';
        }
        
        // Récupérer les valeurs actuelles
        $options = get_option( $this->option_name, [] );
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                ⚙️ Exemple Configuration
                <span class="description" style="font-size: 14px; font-weight: normal; margin-left: 15px;">
                    Formulaire avec modales d'aide
                </span>
            </h1>
            
            <hr class="wp-header-end">
            
            <!-- Notice d'information -->
            <div class="notice notice-info">
                <p>
                    <strong>📋 Démonstration :</strong> 
                    Chaque champ dispose d'une icône d'aide <i class="dashicons dashicons-info-outline"></i> 
                    avec des explications détaillées dans des modales identiques aux Analytics !
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'exemple_config_group' );
                ?>
                
                <!-- Section Email -->
                <div class="tb-modal-card" style="margin-bottom: 30px;">
                    <h2 style="margin-top: 0;">📧 Configuration Email</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="smtp_host">
                                    Serveur SMTP
                                    <?php $this->modal_manager->render_help_icon( 'smtp_host', [
                                        'title' => 'Aide sur le serveur SMTP'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="smtp_host" 
                                       name="<?php echo $this->option_name; ?>[smtp_host]" 
                                       value="<?php echo esc_attr( $options['smtp_host'] ?? '' ); ?>" 
                                       class="regular-text" 
                                       placeholder="smtp.votre-fournisseur.com" />
                                <p class="description">Adresse du serveur SMTP pour l'envoi d'emails</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_port">
                                    Port SMTP
                                    <?php $this->modal_manager->render_help_icon( 'smtp_port', [
                                        'title' => 'Aide sur le port SMTP'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="smtp_port" 
                                       name="<?php echo $this->option_name; ?>[smtp_port]" 
                                       value="<?php echo esc_attr( $options['smtp_port'] ?? '587' ); ?>" 
                                       class="small-text" 
                                       min="1" max="65535" />
                                <p class="description">Port de connexion (587 recommandé)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_auth">
                                    Authentification
                                    <?php $this->modal_manager->render_help_icon( 'smtp_auth', [
                                        'title' => 'Aide sur l\'authentification SMTP'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="smtp_auth" name="<?php echo $this->option_name; ?>[smtp_auth]">
                                    <option value="login" <?php selected( $options['smtp_auth'] ?? 'login', 'login' ); ?>>
                                        Login/Mot de passe
                                    </option>
                                    <option value="oauth2" <?php selected( $options['smtp_auth'] ?? '', 'oauth2' ); ?>>
                                        OAuth2
                                    </option>
                                    <option value="none" <?php selected( $options['smtp_auth'] ?? '', 'none' ); ?>>
                                        Aucune
                                    </option>
                                </select>
                                <p class="description">Méthode d'authentification au serveur</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Section Performance -->
                <div class="tb-modal-card" style="margin-bottom: 30px;">
                    <h2 style="margin-top: 0;">⚡ Optimisation Performance</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cache_enabled">
                                    Cache
                                    <?php $this->modal_manager->render_help_icon( 'cache_enabled', [
                                        'title' => 'Aide sur le cache'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="cache_enabled" 
                                           name="<?php echo $this->option_name; ?>[cache_enabled]" 
                                           value="1" 
                                           <?php checked( $options['cache_enabled'] ?? false, 1 ); ?> />
                                    Activer la mise en cache des pages
                                </label>
                                <p class="description">Améliore significativement les temps de chargement</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cache_duration">
                                    Durée du cache
                                    <?php $this->modal_manager->render_help_icon( 'cache_duration', [
                                        'title' => 'Aide sur la durée du cache'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="cache_duration" name="<?php echo $this->option_name; ?>[cache_duration]">
                                    <option value="3600" <?php selected( $options['cache_duration'] ?? '21600', '3600' ); ?>>
                                        1 heure
                                    </option>
                                    <option value="21600" <?php selected( $options['cache_duration'] ?? '21600', '21600' ); ?>>
                                        6 heures (recommandé)
                                    </option>
                                    <option value="86400" <?php selected( $options['cache_duration'] ?? '', '86400' ); ?>>
                                        24 heures
                                    </option>
                                </select>
                                <p class="description">Temps de conservation du cache</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="minify_css">
                                    Compression CSS
                                    <?php $this->modal_manager->render_help_icon( 'minify_css', [
                                        'title' => 'Aide sur la compression CSS'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="minify_css" 
                                           name="<?php echo $this->option_name; ?>[minify_css]" 
                                           value="1" 
                                           <?php checked( $options['minify_css'] ?? true, 1 ); ?> />
                                    Compresser les fichiers CSS
                                </label>
                                <p class="description">Réduit la taille des fichiers pour un chargement plus rapide</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Section Sécurité -->
                <div class="tb-modal-card" style="margin-bottom: 30px;">
                    <h2 style="margin-top: 0;">🔒 Paramètres de Sécurité</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="login_attempts">
                                    Tentatives de connexion
                                    <?php $this->modal_manager->render_help_icon( 'login_attempts', [
                                        'title' => 'Aide sur les tentatives de connexion'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="login_attempts" 
                                       name="<?php echo $this->option_name; ?>[login_attempts]" 
                                       value="<?php echo esc_attr( $options['login_attempts'] ?? '5' ); ?>" 
                                       class="small-text" 
                                       min="3" max="20" />
                                <p class="description">Nombre maximum avant blocage temporaire</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="lockout_duration">
                                    Durée de blocage
                                    <?php $this->modal_manager->render_help_icon( 'lockout_duration', [
                                        'title' => 'Aide sur la durée de blocage'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="lockout_duration" name="<?php echo $this->option_name; ?>[lockout_duration]">
                                    <option value="15" <?php selected( $options['lockout_duration'] ?? '60', '15' ); ?>>
                                        15 minutes
                                    </option>
                                    <option value="60" <?php selected( $options['lockout_duration'] ?? '60', '60' ); ?>>
                                        1 heure (recommandé)
                                    </option>
                                    <option value="1440" <?php selected( $options['lockout_duration'] ?? '', '1440' ); ?>>
                                        24 heures
                                    </option>
                                </select>
                                <p class="description">Durée du blocage après dépassement</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="two_factor_auth">
                                    Authentification 2FA
                                    <?php $this->modal_manager->render_help_icon( 'two_factor_auth', [
                                        'title' => 'Aide sur l\'authentification à deux facteurs'
                                    ] ); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="two_factor_auth" 
                                           name="<?php echo $this->option_name; ?>[two_factor_auth]" 
                                           value="1" 
                                           <?php checked( $options['two_factor_auth'] ?? false, 1 ); ?> />
                                    Activer l'authentification à deux facteurs
                                </label>
                                <p class="description">Recommandé pour tous les administrateurs</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Boutons d'action -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1;">
                    <?php submit_button( 'Sauvegarder la Configuration', 'primary', 'submit', false ); ?>
                    
                    <button type="button" class="button" style="margin-left: 10px;" onclick="location.reload();">
                        Actualiser
                    </button>
                    
                    <p style="margin: 15px 0 0 0; color: #646970; font-style: italic;">
                        💡 Astuce : Testez vos modifications sur un environnement de développement avant la production.
                    </p>
                </div>
                
            </form>
            
        </div>
        
        <style>
        /* Styles pour cet exemple de formulaire */
        .tb-modal-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .tb-modal-card h2 {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-table th {
            position: relative;
            padding-right: 30px; /* Espace pour l'icône d'aide */
        }
        
        /* Animation des champs au focus */
        .form-table input:focus,
        .form-table select:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }
        
        /* Style pour les descriptions */
        .form-table .description {
            color: #646970;
            font-style: italic;
            margin-top: 5px;
        }
        </style>
        <?php
    }
}

// Initialiser l'exemple si TB-Web Parrainage est actif
if ( class_exists( 'TBWeb\WCParrainage\TemplateModalManager' ) ) {
    new ExempleFormulaireConfig();
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-warning"><p>Le plugin TB-Web Parrainage doit être actif pour utiliser cet exemple.</p></div>';
    });
}
