<?php
/**
 * Script de test pour vérifier la restauration des modales
 * À exécuter dans l'admin WordPress ou ajouter temporairement dans functions.php
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook de test pour vérifier le système sur la page mes-parrainages
add_action( 'wp_footer', 'test_modal_restoration_debug' );

function test_modal_restoration_debug() {
    // Vérifier qu'on est sur la bonne page
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'mes-parrainages' ) ) {
        return;
    }
    
    ?>
    <!-- DEBUT TEST MODAL RESTORATION -->
    <script>
    console.log('🔍 TEST RESTORATION MODALES - mes-parrainages détectée');
    
    // Test 1 : Vérifier les icônes présentes
    setTimeout(function() {
        const icons = document.querySelectorAll('.tb-client-help-icon');
        console.log('📊 Icônes d\'aide trouvées:', icons.length);
        
        if (icons.length > 0) {
            console.log('✅ SUCCÈS: Icônes d\'aide présentes');
            icons.forEach(function(icon, index) {
                console.log(`Icône ${index + 1}:`, {
                    metric: icon.dataset.metric,
                    title: icon.dataset.title,
                    classes: icon.className
                });
            });
        } else {
            console.error('❌ ÉCHEC: Aucune icône d\'aide trouvée');
        }
        
        // Test 2 : Vérifier le système JS
        if (typeof tbClientHelp !== 'undefined') {
            console.log('✅ SUCCÈS: tbClientHelp chargé');
            console.log('📋 Modales disponibles:', Object.keys(tbClientHelp.modals || {}));
        } else {
            console.error('❌ ÉCHEC: tbClientHelp non chargé');
        }
        
        // Test 3 : Vérifier jQuery UI
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.dialog !== 'undefined') {
            console.log('✅ SUCCÈS: jQuery UI Dialog disponible');
        } else {
            console.error('❌ ÉCHEC: jQuery UI Dialog non disponible');
        }
        
        // Test 4 : Simuler un clic sur la première icône
        if (icons.length > 0) {
            console.log('🧪 TEST: Simulation clic sur première icône...');
            const firstIcon = icons[0];
            
            // Créer un événement de clic
            const clickEvent = new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
                view: window
            });
            
            // Déclencher le clic
            firstIcon.dispatchEvent(clickEvent);
            
            // Vérifier si une modal s'ouvre
            setTimeout(function() {
                const modals = document.querySelectorAll('.ui-dialog:visible, .tb-client-help-modal:visible');
                if (modals.length > 0) {
                    console.log('🎉 SUCCÈS TOTAL: Modal ouverte par simulation!');
                    
                    // Fermer la modal après test
                    jQuery('.ui-dialog:visible').dialog('close');
                    
                } else {
                    console.warn('⚠️ PROBLÈME: Clic simulé mais aucune modal visible');
                }
            }, 500);
        }
        
        // Résumé final
        const success = icons.length > 0 && typeof tbClientHelp !== 'undefined';
        if (success) {
            console.log('%c🎉 RESTAURATION RÉUSSIE! Les modales devraient fonctionner.', 'color: green; font-weight: bold; font-size: 16px;');
        } else {
            console.log('%c❌ RESTAURATION ÉCHOUÉE! Problème persistant.', 'color: red; font-weight: bold; font-size: 16px;');
        }
        
    }, 1000);
    
    // Fonction utilitaire pour tests manuels
    window.testModalClick = function(metricKey) {
        const icon = document.querySelector(`[data-metric="${metricKey}"]`);
        if (icon) {
            icon.click();
            console.log(`Test clic sur modale: ${metricKey}`);
        } else {
            console.error(`Icône non trouvée pour: ${metricKey}`);
        }
    };
    
    console.log('💡 Commandes disponibles:');
    console.log('- testModalClick("active_discounts") // Test modal remises actives');
    console.log('- testModalClick("monthly_savings")  // Test modal économies mensuelles');
    console.log('- testModalClick("total_savings")    // Test modal économies totales');
    console.log('- testModalClick("next_billing")     // Test modal prochaine facture');
    
    </script>
    <!-- FIN TEST MODAL RESTORATION -->
    <?php
}

// Si on est dans l'admin, afficher un status
if ( is_admin() && current_user_can( 'manage_options' ) ) {
    add_action( 'admin_notices', 'test_modal_admin_notice' );
}

function test_modal_admin_notice() {
    ?>
    <div class="notice notice-info">
        <p>
            <strong>🧪 Test Modales Actif</strong> - 
            Allez sur <a href="<?php echo home_url('/mon-compte/mes-parrainages/'); ?>" target="_blank">/mon-compte/mes-parrainages/</a> 
            et ouvrez la console navigateur (F12) pour voir les résultats du test.
        </p>
    </div>
    <?php
}

// Log dans l'admin pour diagnostic
if ( is_admin() ) {
    add_action( 'admin_init', function() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Test des classes disponibles
        $classes_test = [
            'TemplateModalManager' => class_exists( 'TBWeb\WCParrainage\TemplateModalManager' ),
            'MyAccountModalManager' => class_exists( 'TBWeb\WCParrainage\MyAccountModalManager' ),
            'MyAccountParrainageManager' => class_exists( 'TBWeb\WCParrainage\MyAccountParrainageManager' )
        ];
        
        error_log( 'TEST MODALES - Classes disponibles: ' . json_encode( $classes_test ) );
        
        // Test du plugin principal
        global $wc_tb_parrainage_plugin;
        $plugin_active = isset( $wc_tb_parrainage_plugin ) && is_object( $wc_tb_parrainage_plugin );
        error_log( 'TEST MODALES - Plugin actif: ' . ( $plugin_active ? 'OUI' : 'NON' ) );
    });
}
?>
