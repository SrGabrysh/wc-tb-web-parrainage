<?php
/**
 * Script temporaire pour forcer le vidage du cache des données de parrainage
 * À exécuter UNE FOIS après mise à jour du plugin pour corriger les bugs
 * 
 * INSTRUCTIONS :
 * 1. Uploadez ce fichier dans le dossier du plugin
 * 2. Accédez à : https://votre-site.fr/wp-content/plugins/wc-tb-web-parrainage/force-cache-clear.php
 * 3. Supprimez ce fichier après utilisation
 */

// Sécurité de base
if ( ! defined( 'ABSPATH' ) ) {
    // Chargement minimal de WordPress si accès direct
    require_once( '../../../wp-load.php' );
}

// Vérifier les permissions admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé' );
}

echo '<h1>Nettoyage Cache Parrainage TB-Web</h1>';

// Vider tous les transients liés au parrainage
global $wpdb;

$deleted_parrainages = $wpdb->query( 
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tb_parrainage_user_%'"
);

$deleted_summaries = $wpdb->query( 
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tb_parrainage_user_summary_%'"
);

// Vider aussi les timeouts
$deleted_timeouts = $wpdb->query( 
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tb_parrainage_user_%'"
);

echo '<p style="color: green;">✅ Cache de données parrainage vidé : ' . $deleted_parrainages . ' entrées</p>';
echo '<p style="color: green;">✅ Cache de résumés vidé : ' . $deleted_summaries . ' entrées</p>';
echo '<p style="color: green;">✅ Timeouts vidés : ' . $deleted_timeouts . ' entrées</p>';

echo '<p style="background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;">
<strong>Étapes suivantes :</strong><br>
1. Actualisez votre page "Mes parrainages"<br>
2. Les données devraient maintenant afficher 15,00€/mois<br>
3. Supprimez ce fichier force-cache-clear.php pour la sécurité
</p>';

echo '<p><a href="/wp-admin/options-general.php?page=wc-tb-parrainage&tab=logs">📋 Voir les logs</a></p>';
echo '<p><a href="/mon-compte/mes-parrainages/">👥 Tester "Mes parrainages"</a></p>';

?>
