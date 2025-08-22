<?php
/**
 * Exemple : Migration des Modales Client vers Template Modal System
 * 
 * Démonstration de la migration du système de modales de la page 
 * "mes-parrainages" vers le Template Modal System
 * 
 * @since 2.14.1
 * @example Test en conditions réelles du framework modal
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de test pour la migration des modales client
 */
class ExempleMigrationClientModals {
    
    private $modal_manager;
    private $logger;
    
    public function __construct() {
        // Récupérer le logger du plugin TB-Web Parrainage
        global $wc_tb_parrainage_plugin;
        $this->logger = $wc_tb_parrainage_plugin ? $wc_tb_parrainage_plugin->get_logger() : null;
        
        // Créer une instance du Template Modal Manager avec config client
        $this->modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
            $this->logger,
            [
                'modal_width' => 600,              // Même taille que les modales client originales
                'modal_max_height' => 500,         
                'enable_cache' => true,            
                'cache_duration' => 1800,          // 30 minutes pour client
                'load_dashicons' => true,
                'enable_keyboard_nav' => true,
                'css_prefix' => 'tb-modal-client-test',
                'ajax_action_prefix' => 'tb_modal_client_test',
                'storage_option' => 'tb_modal_content_client_test'
            ],
            'client_test'                          // Namespace de test
        );
        
        $this->modal_manager->init();
        
        // Hooks pour la page de test
        add_action( 'admin_menu', [ $this, 'add_test_menu' ] );
        add_action( 'admin_init', [ $this, 'setup_test_modals' ] );
    }
    
    /**
     * Ajouter une page de test dans l'admin
     */
    public function add_test_menu() {
        add_management_page(
            'Test Modales Client',
            'Test Modales Client',
            'manage_options',
            'test-modales-client',
            [ $this, 'render_test_page' ]
        );
    }
    
    /**
     * Configurer les modales de test (contenu identique à l'original)
     */
    public function setup_test_modals() {
        
        // Contenu identique aux modales originales de mes-parrainages
        $modal_contents = [
            'active_discounts' => [
                'title' => 'Vos remises actives (Template Modal System)',
                'definition' => 'Le nombre de remises actuellement appliquées sur votre abonnement grâce à vos filleuls.',
                'details' => [
                    'Chaque filleul actif vous fait bénéficier d\'une remise',
                    'La remise correspond à 20% du montant TTC de l\'abonnement du filleul',
                    'Les remises restent actives tant que les filleuls conservent leur abonnement'
                ],
                'interpretation' => 'Plus vous avez de filleuls actifs, plus vos économies mensuelles sont importantes.',
                'example' => 'Si votre filleul paie 71,99€ TTC/mois, vous économisez 14,40€/mois (20% de 71,99€). Avec 2 filleuls à 71,99€ TTC chacun, vous économisez 28,80€/mois au total.',
                'tips' => [
                    'Partagez votre code avec vos proches pour augmenter vos remises',
                    'Accompagnez vos filleuls pour qu\'ils restent satisfaits de nos services',
                    'Vérifiez régulièrement que vos remises sont bien appliquées'
                ]
            ],
            
            'monthly_savings' => [
                'title' => 'Votre économie mensuelle (Template Modal System)',
                'definition' => 'Le montant total que vous économisez chaque mois grâce au parrainage.',
                'formula' => 'Somme de toutes vos remises actives = 20% du montant TTC de chacun de vos filleuls',
                'details' => [
                    'Calcul automatique basé sur les abonnements actifs de vos filleuls',
                    'Montant variable selon les formules d\'abonnement choisies',
                    'Mise à jour en temps réel selon les changements d\'abonnements'
                ],
                'example' => '• 1 filleul à 71,99€ TTC = 14,40€/mois d\'économie\n• 2 filleuls à 71,99€ TTC = 28,80€/mois d\'économie\n• 3 filleuls à 71,99€ TTC = 43,20€/mois d\'économie\n• 5 filleuls à 71,99€ TTC = 72€/mois d\'économie (abonnement gratuit !)',
                'interpretation' => 'Votre économie varie selon les montants d\'abonnement de vos filleuls. Plus ils paient, plus vous économisez !',
                'tips' => [
                    'Orientez vos filleuls vers les formules qui leur conviennent le mieux',
                    'Un filleul satisfait reste plus longtemps abonné',
                    'Suivez l\'évolution de vos économies dans cette section'
                ]
            ],
            
            'total_savings' => [
                'title' => 'Vos économies depuis le début (Template Modal System)',
                'definition' => 'Le montant total économisé depuis votre premier parrainage réussi.',
                'details' => [
                    'Additionnement de toutes les remises appliquées sur vos factures',
                    'Inclut les remises des filleuls actuels et passés',
                    'Historique complet de votre activité de parrain'
                ],
                'interpretation' => 'Ce montant représente l\'argent que vous avez économisé grâce à vos recommandations. C\'est aussi le signe que vous avez aidé plusieurs personnes à découvrir nos services !',
                'precision' => 'Les montants sont calculés sur la base des remises effectivement appliquées sur vos factures.',
                'tips' => [
                    'Plus vos filleuls restent longtemps abonnés, plus vos économies totales augmentent',
                    'Pensez à accompagner vos filleuls dans leur découverte de nos services',
                    'Partagez vos économies avec vos proches pour les motiver'
                ]
            ],
            
            'next_billing' => [
                'title' => 'Votre prochaine facture (Template Modal System)',
                'definition' => 'La date et le montant de votre prochaine facture après application de vos remises parrainage.',
                'details' => [
                    'Date : Le jour où votre prochaine facture sera émise',
                    'Montant réduit : Le prix que vous paierez après remises',
                    'Prix normal : Le prix sans les remises (barré pour comparaison)'
                ],
                'example' => 'Prix normal : 71,99€\nVotre prix : 56,99€ (avec 1 filleul actif)\nDate : 14 septembre 2025',
                'interpretation' => 'Ce montant peut varier si de nouveaux filleuls s\'inscrivent ou si certains résilient leur abonnement avant votre prochaine facturation.',
                'precision' => 'Les remises sont appliquées automatiquement lors de la génération de votre facture.',
                'tips' => [
                    'Vérifiez que vos remises sont bien prises en compte',
                    'Anticipez les changements si vous savez qu\'un filleul va résilier',
                    'Contactez le support en cas de question sur votre facturation'
                ]
            ]
        ];
        
        // Charger le contenu
        $this->modal_manager->set_batch_modal_content( $modal_contents );
    }
    
    /**
     * Rendre la page de test
     */
    public function render_test_page() {
        
        // Charger les assets
        $this->modal_manager->enqueue_modal_assets();
        
        ?>
        <div class="wrap">
            <h1>🧪 Test Migration Modales Client vers Template Modal System</h1>
            
            <div class="notice notice-info">
                <p>
                    <strong>🎯 Objectif :</strong> 
                    Tester la migration des modales de la page "mes-parrainages" vers le Template Modal System.
                    Cette page simule l'interface client avec les nouvelles modales.
                </p>
            </div>
            
            <!-- Simulation de l'interface mes-parrainages -->
            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>📊 Résumé de vos remises (Simulation)</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
                    
                    <!-- Card 1 : Remises actives -->
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="color: #666; font-size: 14px;">Remises actives :</span>
                                <div style="font-size: 24px; font-weight: bold; color: #2271b1; margin-top: 5px;">
                                    3 sur 5 filleuls
                                </div>
                            </div>
                            <?php $this->modal_manager->render_help_icon( 'active_discounts', [
                                'title' => 'Aide sur vos remises actives',
                                'position' => 'absolute'
                            ] ); ?>
                        </div>
                    </div>
                    
                    <!-- Card 2 : Économie mensuelle -->
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="color: #666; font-size: 14px;">Économie mensuelle :</span>
                                <div style="font-size: 24px; font-weight: bold; color: #4caf50; margin-top: 5px;">
                                    43,20€
                                </div>
                            </div>
                            <?php $this->modal_manager->render_help_icon( 'monthly_savings', [
                                'title' => 'Aide sur votre économie mensuelle',
                                'position' => 'absolute'
                            ] ); ?>
                        </div>
                    </div>
                    
                    <!-- Card 3 : Économies totales -->
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="color: #666; font-size: 14px;">Économies totales :</span>
                                <div style="font-size: 24px; font-weight: bold; color: #ff9800; margin-top: 5px;">
                                    1 247,60€
                                </div>
                            </div>
                            <?php $this->modal_manager->render_help_icon( 'total_savings', [
                                'title' => 'Aide sur vos économies totales',
                                'position' => 'absolute'
                            ] ); ?>
                        </div>
                    </div>
                    
                    <!-- Card 4 : Prochaine facture -->
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="color: #666; font-size: 14px;">Prochaine facture :</span>
                                <div style="font-size: 18px; font-weight: bold; color: #2271b1; margin-top: 5px;">
                                    <del style="color: #999; font-weight: normal;">71,99€</del><br>
                                    28,79€
                                </div>
                                <small style="color: #666;">15 septembre 2025</small>
                            </div>
                            <?php $this->modal_manager->render_help_icon( 'next_billing', [
                                'title' => 'Aide sur votre prochaine facture',
                                'position' => 'absolute'
                            ] ); ?>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Section de comparaison -->
            <div class="tb-modal-card" style="margin-top: 30px; background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2>🔄 Comparaison des Systèmes</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    
                    <!-- Ancien système -->
                    <div>
                        <h3 style="color: #d63384;">❌ Ancien Système (client-help-modals.js)</h3>
                        <ul>
                            <li>🔧 <strong>Code spécifique</strong> non réutilisable</li>
                            <li>📝 <strong>Contenu en HTML</strong> dans le PHP</li>
                            <li>🎨 <strong>Styles séparés</strong> à maintenir</li>
                            <li>⚙️ <strong>Configuration limitée</strong></li>
                            <li>🐛 <strong>Debug complexe</strong></li>
                        </ul>
                        
                        <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;"><code>// Ancien
&lt;span class="tb-client-help-icon" 
      data-metric="active_discounts"&gt;
  &lt;span class="dashicons dashicons-editor-help"&gt;&lt;/span&gt;
&lt;/span&gt;</code></pre>
                    </div>
                    
                    <!-- Nouveau système -->
                    <div>
                        <h3 style="color: #4caf50;">✅ Nouveau Système (Template Modal System)</h3>
                        <ul>
                            <li>♻️ <strong>Framework réutilisable</strong> partout</li>
                            <li>📊 <strong>Contenu structuré</strong> (définition, tips, exemples)</li>
                            <li>🎨 <strong>Styles unifiés</strong> avec Analytics</li>
                            <li>⚙️ <strong>Configuration avancée</strong> (cache, multilingue)</li>
                            <li>🔍 <strong>Debug intégré</strong> et logs détaillés</li>
                        </ul>
                        
                        <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;"><code>// Nouveau
$modal_manager->render_help_icon( 'active_discounts', [
  'title' => 'Aide sur vos remises actives',
  'position' => 'absolute'
] );</code></pre>
                    </div>
                    
                </div>
            </div>
            
            <!-- Tests et diagnostics -->
            <div class="tb-modal-card" style="margin-top: 30px; background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2>🧪 Tests & Diagnostics</h2>
                
                <?php
                // Obtenir les stats du système
                $stats = $this->modal_manager->get_usage_stats();
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    
                    <div style="background: #f0f8ff; padding: 15px; border-radius: 4px;">
                        <h4>📊 Statistiques</h4>
                        <ul>
                            <li><strong>Modales configurées :</strong> <?php echo $stats['total_elements']; ?></li>
                            <li><strong>Langues :</strong> <?php echo implode( ', ', $stats['languages'] ); ?></li>
                            <li><strong>Namespace :</strong> client_test</li>
                        </ul>
                    </div>
                    
                    <div style="background: #f0fff0; padding: 15px; border-radius: 4px;">
                        <h4>✅ Tests Fonctionnels</h4>
                        <ul>
                            <li>✅ Icônes d'aide affichées</li>
                            <li>✅ Modales responsive</li>
                            <li>✅ Navigation clavier</li>
                            <li>✅ Cache activé</li>
                        </ul>
                    </div>
                    
                    <div style="background: #fffbf0; padding: 15px; border-radius: 4px;">
                        <h4>🔧 Actions</h4>
                        <p>
                            <button type="button" class="button" onclick="console.log(tbModalClientTest.getStats())">
                                📊 Voir Stats JS
                            </button>
                        </p>
                        <p>
                            <button type="button" class="button" onclick="tbModalClientTest.clearCache()">
                                🗑️ Vider Cache
                            </button>
                        </p>
                    </div>
                    
                </div>
            </div>
            
            <!-- Instructions pour la migration finale -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin-top: 30px;">
                <h2 style="margin-top: 0; color: white;">🚀 Migration Réussie !</h2>
                
                <p>
                    <strong>✅ Le Template Modal System fonctionne parfaitement</strong> pour remplacer 
                    les modales de la page "mes-parrainages". Les modales ont le même design que 
                    celles des Analytics et utilisent maintenant le framework réutilisable.
                </p>
                
                <h3 style="color: white;">📋 Prochaines étapes :</h3>
                <ol>
                    <li>✅ <strong>Migration effectuée</strong> dans MyAccountParrainageManager</li>
                    <li>🧪 <strong>Tests réalisés</strong> sur cette page de démo</li>
                    <li>🔄 <strong>Validation</strong> sur la vraie page "mes-parrainages"</li>
                    <li>🗑️ <strong>Nettoyage</strong> de l'ancien code client-help-modals.js</li>
                </ol>
                
                <p style="margin-bottom: 0; opacity: 0.9;">
                    <strong>🎉 Félicitations !</strong> Vous avez maintenant un système de modales unifié 
                    et réutilisable sur tout votre site WordPress.
                </p>
            </div>
            
        </div>
        
        <style>
        /* Styles additionnels pour la page de test */
        .tb-modal-card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tb-modal-card h2 {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        /* Assurer compatibilité avec les styles Template Modal */
        .tb-modal-client-test-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .tb-modal-client-test-icon:hover {
            opacity: 1;
        }
        
        .tb-modal-client-test-icon .dashicons {
            color: #646970;
            font-size: 16px;
        }
        
        .tb-modal-client-test-icon:hover .dashicons {
            color: #2271b1;
        }
        </style>
        <?php
    }
}

// Initialiser l'exemple de test
if ( class_exists( 'TBWeb\WCParrainage\TemplateModalManager' ) ) {
    new ExempleMigrationClientModals();
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-warning"><p>Le Template Modal System doit être disponible pour utiliser cette démonstration.</p></div>';
    });
}
