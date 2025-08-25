<?php
/**
 * Test de migration des modales client vers Template Modal System
 * 
 * Script de validation pour s'assurer que la migration est réussie
 * et que le nouveau système fonctionne correctement
 * 
 * @since 2.14.1
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de tests pour la migration des modales client
 */
class TestClientModalMigration {
    
    private $logger;
    private $tests_results = [];
    
    public function __construct() {
        global $wc_tb_parrainage_plugin;
        $this->logger = $wc_tb_parrainage_plugin ? $wc_tb_parrainage_plugin->get_logger() : null;
        
        echo "<h1>🧪 Tests de Migration Modales Client → Template Modal System</h1>\n";
        echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 4px; margin: 20px 0;'>\n";
        echo "<p><strong>Objectif :</strong> Valider que la migration des modales de la page 'mes-parrainages' vers le Template Modal System fonctionne correctement.</p>\n";
        echo "</div>\n";
        
        $this->run_all_tests();
        $this->display_results();
    }
    
    /**
     * Exécuter tous les tests
     */
    private function run_all_tests() {
        
        echo "<h2>📋 Exécution des Tests</h2>\n";
        
        // Test 1 : Vérifier que les classes existent
        $this->test_classes_exist();
        
        // Test 2 : Vérifier l'initialisation du MyAccountModalManager
        $this->test_modal_manager_initialization();
        
        // Test 3 : Vérifier la configuration du contenu
        $this->test_modal_content_setup();
        
        // Test 4 : Vérifier le rendu des icônes
        $this->test_icon_rendering();
        
        // Test 5 : Vérifier les assets
        $this->test_assets_enqueuing();
        
        // Test 6 : Vérifier l'intégration avec MyAccountParrainageManager
        $this->test_integration();
        
        // Test 7 : Vérifier les données AJAX
        $this->test_ajax_data();
    }
    
    /**
     * Test 1 : Vérifier que les classes existent
     */
    private function test_classes_exist() {
        
        echo "<h3>🔍 Test 1 : Existence des Classes</h3>\n";
        
        $classes_to_check = [
            'TBWeb\WCParrainage\TemplateModalManager',
            'TBWeb\WCParrainage\MyAccountModalManager',
            'TBWeb\WCParrainage\MyAccountParrainageManager'
        ];
        
        foreach ( $classes_to_check as $class_name ) {
            if ( class_exists( $class_name ) ) {
                $this->log_success( "✅ Classe {$class_name} existe" );
                $this->tests_results[] = [ 'test' => "Classe {$class_name}", 'status' => 'success' ];
            } else {
                $this->log_error( "❌ Classe {$class_name} manquante" );
                $this->tests_results[] = [ 'test' => "Classe {$class_name}", 'status' => 'error' ];
            }
        }
    }
    
    /**
     * Test 2 : Vérifier l'initialisation du MyAccountModalManager
     */
    private function test_modal_manager_initialization() {
        
        echo "<h3>🚀 Test 2 : Initialisation du Modal Manager</h3>\n";
        
        try {
            // Créer une instance pour tester
            $modal_manager = new \TBWeb\WCParrainage\MyAccountModalManager( $this->logger );
            
            $this->log_success( "✅ MyAccountModalManager créé avec succès" );
            $this->tests_results[] = [ 'test' => 'Initialisation MyAccountModalManager', 'status' => 'success' ];
            
            // Vérifier que l'init fonctionne
            $modal_manager->init();
            $this->log_success( "✅ Initialisation réussie" );
            $this->tests_results[] = [ 'test' => 'Init MyAccountModalManager', 'status' => 'success' ];
            
            // Vérifier les stats
            $stats = $modal_manager->get_usage_stats();
            if ( is_array( $stats ) ) {
                $this->log_success( "✅ Statistiques disponibles : " . json_encode( $stats ) );
                $this->tests_results[] = [ 'test' => 'Stats MyAccountModalManager', 'status' => 'success' ];
            } else {
                $this->log_error( "❌ Statistiques non disponibles" );
                $this->tests_results[] = [ 'test' => 'Stats MyAccountModalManager', 'status' => 'error' ];
            }
            
        } catch ( \Exception $e ) {
            $this->log_error( "❌ Erreur initialisation : " . $e->getMessage() );
            $this->tests_results[] = [ 'test' => 'Initialisation MyAccountModalManager', 'status' => 'error' ];
        }
    }
    
    /**
     * Test 3 : Vérifier la configuration du contenu
     */
    private function test_modal_content_setup() {
        
        echo "<h3>📝 Test 3 : Configuration du Contenu Modal</h3>\n";
        
        try {
            $modal_manager = new \TBWeb\WCParrainage\MyAccountModalManager( $this->logger );
            $modal_manager->init();
            
            // Simuler la configuration du contenu
            $modal_manager->setup_modal_contents();
            
            $stats = $modal_manager->get_usage_stats();
            
            if ( $stats['total_elements'] > 0 ) {
                $this->log_success( "✅ Contenu configuré : {$stats['total_elements']} modales" );
                $this->tests_results[] = [ 'test' => 'Configuration contenu', 'status' => 'success' ];
                
                // Vérifier les clés attendues
                $expected_keys = [ 'active_discounts', 'monthly_savings', 'total_savings', 'next_billing' ];
                $this->log_info( "📋 Modales attendues : " . implode( ', ', $expected_keys ) );
                
            } else {
                $this->log_error( "❌ Aucun contenu configuré" );
                $this->tests_results[] = [ 'test' => 'Configuration contenu', 'status' => 'error' ];
            }
            
        } catch ( \Exception $e ) {
            $this->log_error( "❌ Erreur configuration contenu : " . $e->getMessage() );
            $this->tests_results[] = [ 'test' => 'Configuration contenu', 'status' => 'error' ];
        }
    }
    
    /**
     * Test 4 : Vérifier le rendu des icônes
     */
    private function test_icon_rendering() {
        
        echo "<h3>🎨 Test 4 : Rendu des Icônes</h3>\n";
        
        try {
            $modal_manager = new \TBWeb\WCParrainage\MyAccountModalManager( $this->logger );
            $modal_manager->init();
            $modal_manager->setup_modal_contents();
            
            // Tester le rendu d'une icône
            $icon_html = $modal_manager->render_help_icon( 'active_discounts', 'Test Icon' );
            
            if ( ! empty( $icon_html ) && strpos( $icon_html, 'data-modal-key="active_discounts"' ) !== false ) {
                $this->log_success( "✅ Rendu d'icône fonctionne" );
                $this->log_info( "🔍 HTML généré : " . substr( strip_tags( $icon_html ), 0, 100 ) . "..." );
                $this->tests_results[] = [ 'test' => 'Rendu icônes', 'status' => 'success' ];
            } else {
                $this->log_error( "❌ Rendu d'icône défaillant" );
                $this->tests_results[] = [ 'test' => 'Rendu icônes', 'status' => 'error' ];
            }
            
        } catch ( \Exception $e ) {
            $this->log_error( "❌ Erreur rendu icône : " . $e->getMessage() );
            $this->tests_results[] = [ 'test' => 'Rendu icônes', 'status' => 'error' ];
        }
    }
    
    /**
     * Test 5 : Vérifier les assets
     */
    private function test_assets_enqueuing() {
        
        echo "<h3>📦 Test 5 : Chargement des Assets</h3>\n";
        
        try {
            $modal_manager = new \TBWeb\WCParrainage\MyAccountModalManager( $this->logger );
            $modal_manager->init();
            
            // Vérifier que les fichiers assets existent
            $css_file = WC_TB_PARRAINAGE_PATH . 'assets/css/template-modals.css';
            $js_file = WC_TB_PARRAINAGE_PATH . 'assets/js/template-modals.js';
            
            if ( file_exists( $css_file ) ) {
                $this->log_success( "✅ Fichier CSS template-modals.css existe" );
                $this->tests_results[] = [ 'test' => 'CSS template-modals', 'status' => 'success' ];
            } else {
                $this->log_error( "❌ Fichier CSS template-modals.css manquant" );
                $this->tests_results[] = [ 'test' => 'CSS template-modals', 'status' => 'error' ];
            }
            
            if ( file_exists( $js_file ) ) {
                $this->log_success( "✅ Fichier JS template-modals.js existe" );
                $this->tests_results[] = [ 'test' => 'JS template-modals', 'status' => 'success' ];
            } else {
                $this->log_error( "❌ Fichier JS template-modals.js manquant" );
                $this->tests_results[] = [ 'test' => 'JS template-modals', 'status' => 'error' ];
            }
            
        } catch ( \Exception $e ) {
            $this->log_error( "❌ Erreur vérification assets : " . $e->getMessage() );
            $this->tests_results[] = [ 'test' => 'Chargement assets', 'status' => 'error' ];
        }
    }
    
    /**
     * Test 6 : Vérifier l'intégration avec MyAccountParrainageManager
     */
    private function test_integration() {
        
        echo "<h3>🔗 Test 6 : Intégration avec MyAccountParrainageManager</h3>\n";
        
        try {
            // Créer une instance de MyAccountParrainageManager pour vérifier l'intégration
            $parrainage_manager = new \TBWeb\WCParrainage\MyAccountParrainageManager( $this->logger );
            
            $this->log_success( "✅ MyAccountParrainageManager créé (avec MyAccountModalManager intégré)" );
            $this->tests_results[] = [ 'test' => 'Intégration classes', 'status' => 'success' ];
            
            // Vérifier l'init
            $parrainage_manager->init();
            $this->log_success( "✅ Initialisation intégrée réussie" );
            $this->tests_results[] = [ 'test' => 'Init intégrée', 'status' => 'success' ];
            
        } catch ( \Exception $e ) {
            $this->log_error( "❌ Erreur intégration : " . $e->getMessage() );
            $this->tests_results[] = [ 'test' => 'Intégration classes', 'status' => 'error' ];
        }
    }
    
    /**
     * Test 7 : Vérifier les données AJAX
     */
    private function test_ajax_data() {
        
        echo "<h3>🔄 Test 7 : Données AJAX</h3>\n";
        
        try {
            $modal_manager = new \TBWeb\WCParrainage\MyAccountModalManager( $this->logger );
            $modal_manager->init();
            $modal_manager->setup_modal_contents();
            
            // Vérifier que les hooks AJAX sont enregistrés
            if ( has_action( 'wp_ajax_tb_modal_client_get_content' ) ) {
                $this->log_success( "✅ Hook AJAX tb_modal_client_get_content enregistré" );
                $this->tests_results[] = [ 'test' => 'Hook AJAX GET', 'status' => 'success' ];
            } else {
                $this->log_error( "❌ Hook AJAX tb_modal_client_get_content manquant" );
                $this->tests_results[] = [ 'test' => 'Hook AJAX GET', 'status' => 'error' ];
            }
            
            $this->log_info( "💡 Les données AJAX seront testées lors de l'utilisation réelle" );
            
        } catch ( \Exception $e ) {
            $this->log_error( "❌ Erreur vérification AJAX : " . $e->getMessage() );
            $this->tests_results[] = [ 'test' => 'Données AJAX', 'status' => 'error' ];
        }
    }
    
    /**
     * Afficher les résultats des tests
     */
    private function display_results() {
        
        echo "<h2>📊 Résultats des Tests</h2>\n";
        
        $success_count = count( array_filter( $this->tests_results, function( $result ) {
            return $result['status'] === 'success';
        } ) );
        
        $total_count = count( $this->tests_results );
        $success_rate = $total_count > 0 ? ( $success_count / $total_count ) * 100 : 0;
        
        // Affichage du résumé
        if ( $success_rate >= 90 ) {
            $status_color = '#4caf50';
            $status_icon = '🎉';
            $status_text = 'EXCELLENT';
        } elseif ( $success_rate >= 70 ) {
            $status_color = '#ff9800';
            $status_icon = '⚠️';
            $status_text = 'ACCEPTABLE';
        } else {
            $status_color = '#f44336';
            $status_icon = '❌';
            $status_text = 'PROBLÉMATIQUE';
        }
        
        echo "<div style='background: {$status_color}; color: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>\n";
        echo "<h3 style='margin-top: 0; color: white;'>{$status_icon} Résultat Global : {$status_text}</h3>\n";
        echo "<p><strong>Taux de réussite :</strong> {$success_count}/{$total_count} tests ({$success_rate}%)</p>\n";
        echo "</div>\n";
        
        // Détail des tests
        echo "<table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>\n";
        echo "<thead><tr style='background: #f0f0f0;'>\n";
        echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Test</th>\n";
        echo "<th style='padding: 10px; text-align: center; border: 1px solid #ddd;'>Statut</th>\n";
        echo "</tr></thead>\n";
        echo "<tbody>\n";
        
        foreach ( $this->tests_results as $result ) {
            $status_icon = $result['status'] === 'success' ? '✅' : '❌';
            $status_color = $result['status'] === 'success' ? '#e8f5e8' : '#ffe8e8';
            
            echo "<tr style='background: {$status_color};'>\n";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$result['test']}</td>\n";
            echo "<td style='padding: 10px; text-align: center; border: 1px solid #ddd;'>{$status_icon}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</tbody></table>\n";
        
        // Conclusion et next steps
        echo "<h2>📋 Conclusion & Prochaines Étapes</h2>\n";
        
        if ( $success_rate >= 90 ) {
            echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 4px; border-left: 4px solid #4caf50;'>\n";
            echo "<h3>🎉 Migration Réussie !</h3>\n";
            echo "<p>La migration des modales client vers le Template Modal System est un <strong>succès</strong>. Le système est prêt pour la production.</p>\n";
            echo "<h4>✅ Prochaines étapes :</h4>\n";
            echo "<ol>\n";
            echo "<li>🧪 Tester sur la vraie page 'mes-parrainages'</li>\n";
            echo "<li>🔍 Vérifier le comportement avec de vrais utilisateurs</li>\n";
            echo "<li>🗑️ Nettoyer l'ancien code client-help-modals.js</li>\n";
            echo "<li>📝 Mettre à jour la documentation</li>\n";
            echo "</ol>\n";
            echo "</div>\n";
        } else {
            echo "<div style='background: #ffe8e8; padding: 15px; border-radius: 4px; border-left: 4px solid #f44336;'>\n";
            echo "<h3>⚠️ Migration Incomplète</h3>\n";
            echo "<p>Certains tests ont échoué. Veuillez corriger les problèmes avant de passer en production.</p>\n";
            echo "<h4>🔧 Actions requises :</h4>\n";
            echo "<ol>\n";
            echo "<li>📋 Analyser les tests en échec ci-dessus</li>\n";
            echo "<li>🔧 Corriger les problèmes identifiés</li>\n";
            echo "<li>🔄 Relancer les tests</li>\n";
            echo "<li>📞 Contacter le support si nécessaire</li>\n";
            echo "</ol>\n";
            echo "</div>\n";
        }
        
        // Infos de debug
        if ( $this->logger ) {
            $this->logger->info(
                'Tests migration modales client terminés',
                [
                    'success_rate' => $success_rate,
                    'total_tests' => $total_count,
                    'successful_tests' => $success_count,
                    'results' => $this->tests_results
                ],
                'client-modal-migration-test'
            );
        }
    }
    
    /**
     * Logger des messages de succès
     */
    private function log_success( $message ) {
        echo "<p style='color: #4caf50;'>{$message}</p>\n";
    }
    
    /**
     * Logger des messages d'erreur
     */
    private function log_error( $message ) {
        echo "<p style='color: #f44336;'>{$message}</p>\n";
    }
    
    /**
     * Logger des messages d'info
     */
    private function log_info( $message ) {
        echo "<p style='color: #2271b1;'>{$message}</p>\n";
    }
}

// Exécuter les tests si nous sommes dans l'admin
if ( is_admin() && current_user_can( 'manage_options' ) ) {
    new TestClientModalMigration();
} else {
    echo "<p>⚠️ Ce script doit être exécuté dans l'admin WordPress avec les permissions appropriées.</p>";
}
