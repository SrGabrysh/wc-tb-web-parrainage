<?php
/**
 * Exemple : Usage Simple du Template Modal System
 * 
 * Guide pratique étape par étape pour intégrer rapidement
 * des modales d'aide dans n'importe quelle page
 * 
 * @since 2.14.1
 * @example Code minimal pour démarrer rapidement
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Exemple d'usage simple - 5 étapes seulement !
 */
class ExempleSimpleUsage {
    
    private $modal_manager;
    
    public function __construct() {
        
        // ÉTAPE 1 : Créer le gestionnaire de modales
        $this->modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
            null,           // Logger (optionnel)
            [],             // Configuration par défaut
            'simple'        // Namespace unique
        );
        
        // ÉTAPE 2 : Initialiser
        $this->modal_manager->init();
        
        // Hook pour afficher la page d'exemple
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'setup_content' ] );
    }
    
    public function add_menu() {
        add_menu_page(
            'Usage Simple',
            'Usage Simple',
            'manage_options',
            'usage-simple',
            [ $this, 'render_page' ],
            'dashicons-lightbulb',
            30
        );
    }
    
    public function setup_content() {
        
        // ÉTAPE 3 : Définir le contenu des modales
        $this->modal_manager->set_modal_content( 'exemple_simple', [
            'title' => 'Ma Première Modal',
            'definition' => 'Ceci est ma première modal créée avec le Template Modal System !',
            'tips' => [
                'Super facile à utiliser',
                'Design identique aux Analytics',
                'Réutilisable partout'
            ]
        ] );
        
        $this->modal_manager->set_modal_content( 'exemple_avance', [
            'title' => 'Modal Avancée',
            'definition' => 'Exemple avec contenu structuré complet.',
            'details' => [
                'Support de tous les types de contenu',
                'Formules mathématiques',
                'Exemples concrets',
                'Conseils d\'optimisation'
            ],
            'formula' => '(Simplicité + Performance) × Réutilisabilité = Template Modal System',
            'example' => 'En 5 minutes, vous avez des modales professionnelles !',
            'interpretation' => 'Ce système vous fait gagner des heures de développement.',
            'tips' => [
                'Commencez par cet exemple simple',
                'Personnalisez selon vos besoins',
                'Réutilisez sur tous vos projets'
            ]
        ] );
    }
    
    public function render_page() {
        
        // ÉTAPE 4 : Charger les assets CSS/JS
        $this->modal_manager->enqueue_modal_assets();
        
        ?>
        <div class="wrap">
            <h1>🚀 Usage Simple du Template Modal System</h1>
            
            <div class="notice notice-success">
                <p><strong>🎉 Félicitations !</strong> Vous venez de créer vos premières modales en 5 étapes seulement !</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
                
                <!-- Card simple -->
                <div class="tb-modal-card">
                    <h2 style="margin-top: 0;">
                        💡 Modal Simple
                        
                        <!-- ÉTAPE 5 : Ajouter l'icône d'aide -->
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [
                            'position' => 'float-right'
                        ] ); ?>
                    </h2>
                    
                    <p>Cliquez sur l'icône pour voir une modal d'aide simple avec contenu de base.</p>
                    
                    <code>
                        render_help_icon( 'exemple_simple' )
                    </code>
                </div>
                
                <!-- Card avancée -->
                <div class="tb-modal-card">
                    <h2 style="margin-top: 0;">
                        🔧 Modal Avancée
                        
                        <?php $this->modal_manager->render_help_icon( 'exemple_avance', [
                            'position' => 'float-right'
                        ] ); ?>
                    </h2>
                    
                    <p>Exemple avec formule, détails, exemples et conseils complets.</p>
                    
                    <code>
                        render_help_icon( 'exemple_avance' )
                    </code>
                </div>
                
            </div>
            
            <!-- Guide d'implémentation -->
            <div class="tb-modal-card" style="margin-top: 30px;">
                <h2 style="margin-top: 0;">📋 Code Source - 5 Étapes</h2>
                
                <h3>ÉTAPE 1 : Créer le gestionnaire</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
$modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
    null,        // Logger (optionnel)  
    [],          // Configuration par défaut
    "simple"     // Namespace unique
);
                '); ?></code></pre>
                
                <h3>ÉTAPE 2 : Initialiser</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
$modal_manager->init();
                '); ?></code></pre>
                
                <h3>ÉTAPE 3 : Définir le contenu</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
$modal_manager->set_modal_content( "ma_modal", [
    "title" => "Titre de ma modal",
    "definition" => "Description simple et claire",
    "tips" => [ "Conseil 1", "Conseil 2" ]
] );
                '); ?></code></pre>
                
                <h3>ÉTAPE 4 : Charger les assets</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
$modal_manager->enqueue_modal_assets();
                '); ?></code></pre>
                
                <h3>ÉTAPE 5 : Ajouter l'icône</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
// En PHP
$modal_manager->render_help_icon( "ma_modal" );

// Ou directement en HTML
<span class="tb-modal-simple-icon" 
      data-modal-key="ma_modal"
      data-namespace="simple">
    <i class="dashicons dashicons-info-outline"></i>
</span>
                '); ?></code></pre>
                
                <p style="background: #e8f5e8; padding: 15px; border-radius: 4px; border-left: 4px solid #4caf50; margin-top: 20px;">
                    <strong>🎯 C'est tout !</strong> Vous avez maintenant des modales d'aide avec le même design que les Analytics TB-Web Parrainage.
                </p>
            </div>
            
            <!-- Options avancées -->
            <div class="tb-modal-card" style="margin-top: 30px;">
                <h2 style="margin-top: 0;">⚙️ Options Avancées</h2>
                
                <h3>🎨 Positions d'icônes</h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    
                    <div style="padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <strong>Inline</strong>
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [
                            'position' => 'inline',
                            'size' => 'small'
                        ] ); ?>
                        <br><code>position: 'inline'</code>
                    </div>
                    
                    <div style="padding: 15px; background: #f8f9fa; border-radius: 4px; position: relative;">
                        <strong>Absolue</strong>
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [
                            'position' => 'absolute',
                            'size' => 'small'
                        ] ); ?>
                        <br><code>position: 'absolute'</code>
                    </div>
                    
                    <div style="padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <strong>Float Right</strong>
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [
                            'position' => 'float-right',
                            'size' => 'small'
                        ] ); ?>
                        <br><code>position: 'float-right'</code>
                    </div>
                    
                </div>
                
                <h3>📏 Tailles d'icônes</h3>
                <div style="display: flex; gap: 30px; align-items: center; margin-bottom: 20px;">
                    <div>
                        Petite 
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [ 'size' => 'small' ] ); ?>
                        <code>size: 'small'</code>
                    </div>
                    <div>
                        Normale 
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [ 'size' => 'normal' ] ); ?>
                        <code>size: 'normal'</code>
                    </div>
                    <div>
                        Grande 
                        <?php $this->modal_manager->render_help_icon( 'exemple_simple', [ 'size' => 'large' ] ); ?>
                        <code>size: 'large'</code>
                    </div>
                </div>
                
                <h3>🎛️ Configuration personnalisée</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
$config = [
    "modal_width" => 700,         // Largeur custom
    "modal_max_height" => 600,    // Hauteur custom  
    "enable_cache" => true,       // Cache activé
    "cache_duration" => 600       // 10 minutes
];

$modal_manager = new TemplateModalManager( $logger, $config, "mon_namespace" );
                '); ?></code></pre>
            </div>
            
            <!-- Next steps -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin-top: 30px;">
                <h2 style="margin-top: 0; color: white;">🚀 Prochaines Étapes</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <h4>📊 Dashboard Admin</h4>
                        <p>Créez un dashboard avec métriques et modales d'aide pour chaque indicateur.</p>
                    </div>
                    <div>
                        <h4>⚙️ Formulaires Config</h4>
                        <p>Ajoutez des explications détaillées à vos champs de configuration.</p>
                    </div>
                    <div>
                        <h4>📋 Listes/Tableaux</h4>
                        <p>Documentez chaque colonne de vos tableaux de données.</p>
                    </div>
                </div>
                
                <p style="margin-bottom: 0; opacity: 0.9;">
                    Consultez les autres exemples dans le dossier <code>/examples/</code> pour des implémentations complètes !
                </p>
            </div>
            
        </div>
        
        <style>
        .tb-modal-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .tb-modal-card h2 {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .tb-modal-card code {
            background: #f1f1f1;
            padding: 4px 8px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .tb-modal-card pre code {
            background: none;
            padding: 0;
        }
        </style>
        <?php
    }
}

// Initialiser l'exemple
if ( class_exists( 'TBWeb\WCParrainage\TemplateModalManager' ) ) {
    new ExempleSimpleUsage();
}
