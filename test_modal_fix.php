<?php
/**
 * Test rapide pour vérifier le fix des modales
 * À ajouter temporairement dans functions.php ou exécuter dans l'admin
 */

add_action('wp_footer', function() {
    if (!is_wc_endpoint_url('mes-parrainages')) {
        return;
    }
    
    echo '<!-- TEST MODAL FIX -->';
    echo '<script>console.log("Page mes-parrainages détectée, test des modales...");</script>';
    
    // Vérifier si les assets sont chargés
    global $wp_scripts, $wp_styles;
    
    $modal_js_loaded = false;
    $modal_css_loaded = false;
    
    foreach ($wp_scripts->registered as $handle => $script) {
        if (strpos($handle, 'template-modal') !== false || strpos($handle, 'tb-modal') !== false) {
            echo '<script>console.log("JS Modal trouvé: ' . $handle . '");</script>';
            $modal_js_loaded = true;
        }
    }
    
    foreach ($wp_styles->registered as $handle => $style) {
        if (strpos($handle, 'template-modal') !== false || strpos($handle, 'tb-modal') !== false) {
            echo '<script>console.log("CSS Modal trouvé: ' . $handle . '");</script>';
            $modal_css_loaded = true;
        }
    }
    
    if (!$modal_js_loaded) {
        echo '<script>console.error("❌ Aucun JS modal chargé!");</script>';
    }
    
    if (!$modal_css_loaded) {
        echo '<script>console.error("❌ Aucun CSS modal chargé!");</script>';
    }
    
    // Test des icônes présentes
    echo '<script>
    setTimeout(function() {
        const icons = document.querySelectorAll(".tb-modal-client-icon, .tb-client-help-icon");
        console.log("Icônes trouvées: " + icons.length);
        icons.forEach(function(icon, index) {
            console.log("Icône " + index + ":", icon.classList.toString(), icon.dataset);
        });
        
        if (icons.length === 0) {
            console.error("❌ Aucune icône de modal trouvée sur la page!");
        }
    }, 1000);
    </script>';
});

// Test de diagnostic dans l'admin
add_action('admin_footer', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<script>
    // Fonction de diagnostic accessible via console
    window.testModalSystem = function() {
        console.log("=== DIAGNOSTIC MODAL SYSTEM ===");
        
        // Test existence des classes PHP
        const testData = ' . json_encode([
            'classes_exist' => [
                'TemplateModalManager' => class_exists('TBWeb\WCParrainage\TemplateModalManager'),
                'MyAccountModalManager' => class_exists('TBWeb\WCParrainage\MyAccountModalManager'),
                'MyAccountParrainageManager' => class_exists('TBWeb\WCParrainage\MyAccountParrainageManager')
            ],
            'plugin_active' => isset($GLOBALS['wc_tb_parrainage_plugin']),
            'functions_exist' => [
                'is_wc_endpoint_url' => function_exists('is_wc_endpoint_url'),
                'is_account_page' => function_exists('is_account_page')
            ]
        ]) . ';
        
        console.log("Classes PHP:", testData.classes_exist);
        console.log("Plugin actif:", testData.plugin_active);
        console.log("Fonctions WC:", testData.functions_exist);
        
        return testData;
    };
    
    console.log("💡 Tapez testModalSystem() dans la console pour diagnostic");
    </script>';
});
?>
