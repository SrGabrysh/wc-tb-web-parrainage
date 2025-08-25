<?php
/**
 * Script de debug pour identifier le problème de chargement des modales
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "<h1>🔍 Debug Chargement Modales</h1>\n";

// Test 1 : Vérifier si les classes existent
echo "<h2>1. Test Existence des Classes</h2>\n";

$classes = [
    'TBWeb\WCParrainage\Logger',
    'TBWeb\WCParrainage\TemplateModalManager', 
    'TBWeb\WCParrainage\MyAccountModalManager',
    'TBWeb\WCParrainage\MyAccountParrainageManager'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ {$class} existe<br>\n";
    } else {
        echo "❌ {$class} MANQUANTE<br>\n";
    }
}

// Test 2 : Tenter de créer MyAccountModalManager
echo "<h2>2. Test Création MyAccountModalManager</h2>\n";

try {
    global $wc_tb_parrainage_plugin;
    $logger = $wc_tb_parrainage_plugin ? $wc_tb_parrainage_plugin->get_logger() : null;
    
    if (!$logger) {
        echo "⚠️ Logger non disponible, création d'un logger factice<br>\n";
        if (class_exists('TBWeb\WCParrainage\Logger')) {
            $logger = new TBWeb\WCParrainage\Logger();
        } else {
            echo "❌ Impossible de créer un logger<br>\n";
            $logger = null;
        }
    }
    
    if (class_exists('TBWeb\WCParrainage\MyAccountModalManager')) {
        $modal_manager = new TBWeb\WCParrainage\MyAccountModalManager($logger);
        echo "✅ MyAccountModalManager créé avec succès<br>\n";
        
        // Test init
        $modal_manager->init();
        echo "✅ Init réussi<br>\n";
        
        // Test stats
        $stats = $modal_manager->get_usage_stats();
        echo "✅ Stats récupérées : " . json_encode($stats) . "<br>\n";
        
    } else {
        echo "❌ Classe MyAccountModalManager non trouvée<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la création : " . $e->getMessage() . "<br>\n";
    echo "📍 Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "<br>\n";
} catch (Error $e) {
    echo "❌ Erreur fatale : " . $e->getMessage() . "<br>\n";
    echo "📍 Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "<br>\n";
}

// Test 3 : Vérifier MyAccountParrainageManager
echo "<h2>3. Test MyAccountParrainageManager</h2>\n";

try {
    global $wc_tb_parrainage_plugin;
    $logger = $wc_tb_parrainage_plugin ? $wc_tb_parrainage_plugin->get_logger() : null;
    
    if (class_exists('TBWeb\WCParrainage\MyAccountParrainageManager')) {
        $parrainage_manager = new TBWeb\WCParrainage\MyAccountParrainageManager($logger);
        echo "✅ MyAccountParrainageManager créé avec succès<br>\n";
        
    } else {
        echo "❌ Classe MyAccountParrainageManager non trouvée<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur MyAccountParrainageManager : " . $e->getMessage() . "<br>\n";
    echo "📍 Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "<br>\n";
} catch (Error $e) {
    echo "❌ Erreur fatale MyAccountParrainageManager : " . $e->getMessage() . "<br>\n";
    echo "📍 Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "<br>\n";
}

// Test 4 : Vérifier les fonctions WordPress
echo "<h2>4. Test Fonctions WordPress</h2>\n";

$functions = [
    'add_action',
    'is_wc_endpoint_url',
    'is_account_page',
    'wp_enqueue_style',
    'wp_enqueue_script'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ {$func} disponible<br>\n";
    } else {
        echo "❌ {$func} MANQUANTE<br>\n";
    }
}

// Test 5 : Vérifier les assets
echo "<h2>5. Test Assets</h2>\n";

$assets = [
    WC_TB_PARRAINAGE_PATH . 'assets/css/template-modals.css',
    WC_TB_PARRAINAGE_PATH . 'assets/js/template-modals.js'
];

foreach ($assets as $asset) {
    if (file_exists($asset)) {
        echo "✅ " . basename($asset) . " existe<br>\n";
    } else {
        echo "❌ " . basename($asset) . " MANQUANT dans " . $asset . "<br>\n";
    }
}

echo "<h2>6. Informations Système</h2>\n";
echo "🔹 WordPress chargé : " . (defined('ABSPATH') ? 'Oui' : 'Non') . "<br>\n";
echo "🔹 WooCommerce chargé : " . (class_exists('WooCommerce') ? 'Oui' : 'Non') . "<br>\n";
echo "🔹 Plugin TB-Web actif : " . (isset($wc_tb_parrainage_plugin) ? 'Oui' : 'Non') . "<br>\n";
echo "🔹 Hook actuel : " . (function_exists('current_action') ? current_action() : 'Indéterminé') . "<br>\n";

if (function_exists('wp_get_environment_type')) {
    echo "🔹 Environnement : " . wp_get_environment_type() . "<br>\n";
}

// Test bonus : Essayer de tester directement sur la page mes-parrainages
echo "<h2>7. Test Contexte Page</h2>\n";

if (function_exists('is_wc_endpoint_url')) {
    $is_parrainages_page = is_wc_endpoint_url('mes-parrainages');
    echo "🔹 Page mes-parrainages : " . ($is_parrainages_page ? 'Oui' : 'Non') . "<br>\n";
}

if (function_exists('is_account_page')) {
    $is_account_page = is_account_page();
    echo "🔹 Page compte : " . ($is_account_page ? 'Oui' : 'Non') . "<br>\n";
}

echo "<p style='background: #f0f8ff; padding: 15px; border-radius: 4px; margin-top: 20px;'>";
echo "<strong>💡 Instructions :</strong><br>";
echo "1. Copiez ce script dans functions.php temporairement<br>";
echo "2. Ou créez une page admin pour l'exécuter<br>";
echo "3. Naviguez vers /mon-compte/mes-parrainages/ puis exécutez ce script<br>";
echo "</p>";
?>
