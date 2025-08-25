<?php
/**
 * Exemple : Dashboard Admin avec Modales d'Aide
 * 
 * Démonstration complète d'un dashboard admin utilisant le Template Modal System
 * pour ajouter des modales d'aide identiques à celles des Analytics
 * 
 * @since 2.14.1
 * @example Pour tester : Copiez ce code dans un fichier de votre thème ou plugin
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe d'exemple pour un dashboard admin avec modales
 */
class ExempleDashboardAdmin {
    
    private $modal_manager;
    private $logger;
    
    public function __construct() {
        // Récupérer le logger du plugin TB-Web Parrainage
        global $wc_tb_parrainage_plugin;
        $this->logger = $wc_tb_parrainage_plugin ? $wc_tb_parrainage_plugin->get_logger() : null;
        
        // Créer l'instance du gestionnaire de modales avec namespace unique
        $this->modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
            $this->logger,
            [
                'modal_width' => 700,              // Plus large pour cet exemple
                'modal_max_height' => 550,         // Plus haut pour cet exemple
                'enable_cache' => true,            // Cache activé
                'cache_duration' => 600,           // 10 minutes
                'load_dashicons' => true,          // Dashicons nécessaires
            ],
            'dashboard_admin'                      // Namespace unique
        );
        
        // Initialiser
        $this->modal_manager->init();
        
        // Hooks WordPress
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'init_modal_content' ] );
    }
    
    /**
     * Ajouter la page au menu admin
     */
    public function add_admin_menu() {
        add_management_page(
            'Exemple Dashboard',
            'Exemple Dashboard', 
            'manage_options',
            'exemple-dashboard',
            [ $this, 'render_dashboard_page' ]
        );
    }
    
    /**
     * Initialiser le contenu des modales
     */
    public function init_modal_content() {
        
        // Définir le contenu de toutes les modales en une seule fois
        $modal_contents = [
            'total_users' => [
                'title' => 'Utilisateurs Total',
                'definition' => 'Nombre total d\'utilisateurs enregistrés sur votre site WordPress.',
                'details' => [
                    'Inclut tous les rôles : administrateurs, éditeurs, auteurs, etc.',
                    'Met à jour en temps réel à chaque nouvel enregistrement',
                    'N\'inclut pas les utilisateurs supprimés'
                ],
                'interpretation' => 'Indicateur de croissance de votre audience. Une augmentation constante est signe d\'un site attractif.',
                'tips' => [
                    'Encouragez les inscriptions avec des contenus exclusifs',
                    'Simplifiez le processus d\'inscription',
                    'Proposez des incitations (newsletters, téléchargements gratuits)'
                ]
            ],
            
            'monthly_revenue' => [
                'title' => 'Revenus Mensuels',
                'definition' => 'Total des revenus générés durant le mois en cours.',
                'formula' => 'Somme de toutes les commandes validées ce mois',
                'details' => [
                    'Période : 1er au dernier jour du mois actuel',
                    'Statuts inclus : completed, processing',
                    'Calcul en temps réel à chaque nouvelle commande'
                ],
                'example' => 'Si vous avez 100 commandes à 50€ en moyenne = 5 000€ de revenus',
                'interpretation' => 'Performance commerciale mensuelle. Comparez avec les mois précédents pour identifier les tendances.',
                'tips' => [
                    'Analysez les pics de ventes pour identifier les actions efficaces',
                    'Optimisez les promotions selon les périodes les plus profitables',
                    'Surveillez l\'évolution par rapport à l\'objectif mensuel'
                ]
            ],
            
            'conversion_rate' => [
                'title' => 'Taux de Conversion',
                'definition' => 'Pourcentage de visiteurs qui effectuent un achat sur votre site.',
                'formula' => '(Nombre de commandes ÷ Nombre de visiteurs) × 100',
                'details' => [
                    'Période de calcul : 30 derniers jours',
                    'Visiteurs uniques comptabilisés',
                    'Commandes de tous statuts incluses'
                ],
                'example' => 'Avec 1000 visiteurs et 25 commandes → Taux = 2,5%',
                'interpretation' => [
                    'Moins de 1% : Optimisation urgente nécessaire',
                    '1-3% : Performance correcte pour la plupart des secteurs',
                    'Plus de 3% : Très bonne performance'
                ],
                'precision' => 'Le taux moyen en e-commerce varie entre 1% et 4% selon le secteur.',
                'tips' => [
                    'Optimisez vos pages produits avec de meilleures descriptions',
                    'Réduisez les étapes du tunnel de commande',
                    'Améliorez la confiance avec des avis clients',
                    'Testez différentes versions de vos pages (A/B testing)'
                ]
            ],
            
            'average_order' => [
                'title' => 'Panier Moyen',
                'definition' => 'Montant moyen dépensé par commande sur votre site.',
                'formula' => 'Chiffre d\'affaires total ÷ Nombre de commandes',
                'details' => [
                    'Calcul sur les 30 derniers jours',
                    'Tous les produits et services inclus',
                    'Frais de port non inclus'
                ],
                'interpretation' => 'Indicateur de la valeur que vos clients accordent à vos produits. Plus il est élevé, meilleure est votre rentabilité.',
                'tips' => [
                    'Proposez des produits complémentaires (cross-selling)',
                    'Encouragez l\'achat de gammes supérieures (upselling)',
                    'Créez des offres bundles attractives',
                    'Fixez un seuil de livraison gratuite stratégique'
                ]
            ],
            
            'products_sold' => [
                'title' => 'Produits Vendus',
                'definition' => 'Nombre total d\'articles vendus ce mois.',
                'details' => [
                    'Compte chaque exemplaire vendu individuellement',
                    'Inclut tous les types de produits (physiques, numériques)',
                    'Période : mois en cours'
                ],
                'interpretation' => 'Volume d\'activité de votre boutique. Corrélé avec le chiffre d\'affaires mais pas toujours proportionnel.',
                'tips' => [
                    'Analysez quels produits se vendent le mieux',
                    'Ajustez vos stocks selon les tendances',
                    'Mettez en avant vos best-sellers'
                ]
            ],
            
            'system_health' => [
                'title' => 'Santé du Système',
                'definition' => 'Score global évaluant la performance technique et commerciale de votre site.',
                'details' => [
                    'Vitesse de chargement des pages',
                    'Taux d\'erreur des transactions',
                    'Disponibilité du site',
                    'Performance des plugins'
                ],
                'interpretation' => 'Indicateur synthétique de bon fonctionnement. Un score bas nécessite une attention technique immédiate.',
                'tips' => [
                    'Surveillez régulièrement ce score',
                    'Corrigez immédiatement les points en rouge',
                    'Planifiez une maintenance préventive mensuelle'
                ]
            ]
        ];
        
        // Charger tout le contenu en une seule opération
        $this->modal_manager->set_batch_modal_content( $modal_contents );
    }
    
    /**
     * Rendre la page dashboard
     */
    public function render_dashboard_page() {
        
        // Charger les assets des modales
        $this->modal_manager->enqueue_modal_assets();
        
        // Simuler des données (en production, récupérez vos vraies données)
        $metrics = $this->get_sample_metrics();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                🎛️ Exemple Dashboard Admin
                <span class="description" style="font-size: 14px; font-weight: normal; margin-left: 15px;">
                    Démonstration du Template Modal System
                </span>
            </h1>
            
            <hr class="wp-header-end">
            
            <!-- Notice d'information -->
            <div class="notice notice-info">
                <p>
                    <strong>📋 Démonstration :</strong> 
                    Cliquez sur les icônes d'aide <i class="dashicons dashicons-info-outline"></i> 
                    pour voir les modales identiques à celles des Analytics !
                </p>
            </div>
            
            <!-- Grid des métriques -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <!-- Métrique 1 : Utilisateurs -->
                <div class="tb-modal-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #646970; font-size: 14px; text-transform: uppercase;">
                            Utilisateurs Total
                        </h3>
                        <?php $this->modal_manager->render_help_icon( 'total_users', [
                            'position' => 'absolute',
                            'title' => 'Aide sur les utilisateurs total'
                        ] ); ?>
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #1d2327; margin-bottom: 5px;">
                        <?php echo number_format( $metrics['total_users'] ); ?>
                    </div>
                    <div style="color: #4caf50; font-size: 12px;">
                        <i class="dashicons dashicons-arrow-up-alt" style="font-size: 12px;"></i>
                        +12% ce mois
                    </div>
                </div>
                
                <!-- Métrique 2 : Revenus -->
                <div class="tb-modal-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #646970; font-size: 14px; text-transform: uppercase;">
                            Revenus Mensuels
                        </h3>
                        <?php $this->modal_manager->render_help_icon( 'monthly_revenue', [
                            'position' => 'absolute',
                            'title' => 'Aide sur les revenus mensuels'
                        ] ); ?>
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #1d2327; margin-bottom: 5px;">
                        <?php echo number_format( $metrics['monthly_revenue'], 0, ',', ' ' ); ?>€
                    </div>
                    <div style="color: #4caf50; font-size: 12px;">
                        <i class="dashicons dashicons-arrow-up-alt" style="font-size: 12px;"></i>
                        +8% vs mois dernier
                    </div>
                </div>
                
                <!-- Métrique 3 : Conversion -->
                <div class="tb-modal-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #646970; font-size: 14px; text-transform: uppercase;">
                            Taux de Conversion
                        </h3>
                        <?php $this->modal_manager->render_help_icon( 'conversion_rate', [
                            'position' => 'absolute',
                            'title' => 'Aide sur le taux de conversion'
                        ] ); ?>
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #1d2327; margin-bottom: 5px;">
                        <?php echo number_format( $metrics['conversion_rate'], 1 ); ?>%
                    </div>
                    <div style="color: #ff9800; font-size: 12px;">
                        <i class="dashicons dashicons-minus" style="font-size: 12px;"></i>
                        Stable
                    </div>
                </div>
                
                <!-- Métrique 4 : Panier moyen -->
                <div class="tb-modal-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #646970; font-size: 14px; text-transform: uppercase;">
                            Panier Moyen
                        </h3>
                        <?php $this->modal_manager->render_help_icon( 'average_order', [
                            'position' => 'absolute',
                            'title' => 'Aide sur le panier moyen'
                        ] ); ?>
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #1d2327; margin-bottom: 5px;">
                        <?php echo number_format( $metrics['average_order'], 0, ',', ' ' ); ?>€
                    </div>
                    <div style="color: #4caf50; font-size: 12px;">
                        <i class="dashicons dashicons-arrow-up-alt" style="font-size: 12px;"></i>
                        +5% ce mois
                    </div>
                </div>
                
                <!-- Métrique 5 : Produits vendus -->
                <div class="tb-modal-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #646970; font-size: 14px; text-transform: uppercase;">
                            Produits Vendus
                        </h3>
                        <?php $this->modal_manager->render_help_icon( 'products_sold', [
                            'position' => 'absolute',
                            'title' => 'Aide sur les produits vendus'
                        ] ); ?>
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #1d2327; margin-bottom: 5px;">
                        <?php echo number_format( $metrics['products_sold'] ); ?>
                    </div>
                    <div style="color: #4caf50; font-size: 12px;">
                        <i class="dashicons dashicons-arrow-up-alt" style="font-size: 12px;"></i>
                        +15% ce mois
                    </div>
                </div>
                
                <!-- Métrique 6 : Santé système -->
                <div class="tb-modal-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #646970; font-size: 14px; text-transform: uppercase;">
                            Santé du Système
                        </h3>
                        <?php $this->modal_manager->render_help_icon( 'system_health', [
                            'position' => 'absolute',
                            'title' => 'Aide sur la santé du système'
                        ] ); ?>
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #4caf50; margin-bottom: 5px;">
                        <?php echo $metrics['system_health']; ?>%
                    </div>
                    <div style="color: #4caf50; font-size: 12px;">
                        <i class="dashicons dashicons-yes-alt" style="font-size: 12px;"></i>
                        Excellent
                    </div>
                </div>
                
            </div>
            
            <!-- Section informative -->
            <div class="tb-modal-card" style="margin-top: 30px; background: #f8f9fa; border-left: 4px solid #2271b1;">
                <h3>💡 À propos de cet exemple</h3>
                <p>
                    Cette page démontre l'utilisation du <strong>Template Modal System</strong> 
                    pour créer des modales d'aide identiques à celles du module Analytics.
                </p>
                
                <h4>🔧 Code utilisé :</h4>
                <pre style="background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;"><code><?php echo esc_html('
// 1. Création du gestionnaire
$modal_manager = new TemplateModalManager( $logger, $config, "dashboard_admin" );

// 2. Définition du contenu
$modal_manager->set_modal_content( "total_users", [
    "title" => "Utilisateurs Total",
    "definition" => "Nombre total d\'utilisateurs...",
    "tips" => [ "Conseil 1", "Conseil 2" ]
] );

// 3. Rendu de l\'icône
$modal_manager->render_help_icon( "total_users", [
    "position" => "absolute"
] );

// 4. Chargement des assets
$modal_manager->enqueue_modal_assets();
                '); ?></code></pre>
                
                <p>
                    <strong>🎯 Résultat :</strong> Des modales avec exactement le même design, 
                    les mêmes animations et la même accessibilité que les modales Analytics !
                </p>
            </div>
            
        </div>
        
        <style>
        /* Styles additionnels pour cet exemple */
        .tb-modal-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease;
        }
        
        .tb-modal-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .tb-modal-card h3 {
            margin-top: 0;
        }
        
        /* Animation pour les valeurs */
        .tb-modal-card > div:nth-child(2) {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        </style>
        <?php
    }
    
    /**
     * Obtenir des métriques d'exemple
     * En production, remplacez par vos vraies données
     */
    private function get_sample_metrics() {
        return [
            'total_users' => 1847,
            'monthly_revenue' => 24567,
            'conversion_rate' => 2.8,
            'average_order' => 89,
            'products_sold' => 456,
            'system_health' => 94
        ];
    }
}

// Initialiser l'exemple (seulement si TB-Web Parrainage est actif)
if ( class_exists( 'TBWeb\WCParrainage\TemplateModalManager' ) ) {
    new ExempleDashboardAdmin();
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-warning"><p>Le plugin TB-Web Parrainage doit être actif pour utiliser cet exemple.</p></div>';
    });
}
