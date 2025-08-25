<?php
/**
 * Plugin Name: WC TB-Web Parrainage
 * Description: Plugin de parrainage WooCommerce avec webhooks enrichis - Gestion des codes parrain au checkout, calcul automatique des dates de fin de remise parrainage, masquage conditionnel des codes promo et ajout des métadonnées d'abonnement dans les webhooks.
 * Version: 2.18.0
 * Author: TB-Web
 * Text Domain: wc-tb-web-parrainage
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL v2 or later
 */

declare( strict_types=1 );

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes
define( 'WC_TB_PARRAINAGE_VERSION', '2.18.0' );
define( 'WC_TB_PARRAINAGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_TB_PARRAINAGE_URL', plugin_dir_url( __FILE__ ) );

// SUPPRIMÉE : Constante pour le pourcentage de réduction du parrain - Remplacée par configuration par produit

// Nouvelles constantes pour l'onglet Parrainage
define( 'WC_TB_PARRAINAGE_ADMIN_PER_PAGE', 50 );
define( 'WC_TB_PARRAINAGE_MAX_EXPORT', 10000 );
define( 'WC_TB_PARRAINAGE_CACHE_TIME', 300 );
define( 'WC_TB_PARRAINAGE_DATE_FORMAT', 'Y-m-d' );
define( 'WC_TB_PARRAINAGE_MAX_SEARCH_LENGTH', 100 );

// Nouvelles constantes pour l'onglet "Mes parrainages" côté client
define( 'WC_TB_PARRAINAGE_ENDPOINT_KEY', 'mes-parrainages' );
define( 'WC_TB_PARRAINAGE_ENDPOINT_LABEL', 'Mes parrainages' );
define( 'WC_TB_PARRAINAGE_LIMIT_DISPLAY', 10 );
define( 'WC_TB_PARRAINAGE_INVITATION_URL', 'https://tb-web.fr/parrainage/' );
define( 'WC_TB_PARRAINAGE_CACHE_USER_DATA', 300 );
define( 'WC_TB_PARRAINAGE_EMAIL_MASK_CHAR', '*' );

// AJOUT : Nouvelles constantes pour les classes techniques v2.5.0
define( 'WC_TB_PARRAINAGE_DISCOUNT_PRECISION', 2 ); // Précision décimale pour les calculs
define( 'WC_TB_PARRAINAGE_MIN_SUBSCRIPTION_AMOUNT', 1.00 ); // Montant minimum d'abonnement
define( 'WC_TB_PARRAINAGE_DEFAULT_DISCOUNT_RATE', 0.0 ); // Taux par défaut si non configuré
define( 'WC_TB_PARRAINAGE_MAX_DISCOUNT_RATE', 0.5 ); // Limite maximum 50% de remise

// AJOUT v2.6.0 : Constantes pour le workflow asynchrone
define( 'WC_TB_PARRAINAGE_ASYNC_DELAY', 60 ); // 1 minute de délai (réglable via filtre)
define( 'WC_TB_PARRAINAGE_MAX_RETRY', 3 ); // Nombre maximum de tentatives
define( 'WC_TB_PARRAINAGE_RETRY_DELAY', 600 ); // Délai entre retry (10 minutes)
define( 'WC_TB_PARRAINAGE_QUEUE_HOOK', 'tb_parrainage_process_discount' ); // Hook CRON personnalisé

// AJOUT v2.7.1 : Constantes d'activation application réelle et hooks complémentaires
if ( ! defined( 'WC_TB_PARRAINAGE_SIMULATION_MODE' ) ) {
    // false = mode PRODUCTION (application réelle)
    define( 'WC_TB_PARRAINAGE_SIMULATION_MODE', false );
}
if ( ! defined( 'WC_TB_PARRAINAGE_DISCOUNT_DURATION' ) ) {
    define( 'WC_TB_PARRAINAGE_DISCOUNT_DURATION', 12 ); // 12 mois
}
if ( ! defined( 'WC_TB_PARRAINAGE_DISCOUNT_GRACE_PERIOD' ) ) {
    define( 'WC_TB_PARRAINAGE_DISCOUNT_GRACE_PERIOD', 2 ); // 2 jours
}
if ( ! defined( 'WC_TB_PARRAINAGE_END_DISCOUNT_HOOK' ) ) {
    define( 'WC_TB_PARRAINAGE_END_DISCOUNT_HOOK', 'tb_parrainage_end_discount' );
}
if ( ! defined( 'WC_TB_PARRAINAGE_DAILY_CHECK_HOOK' ) ) {
    define( 'WC_TB_PARRAINAGE_DAILY_CHECK_HOOK', 'tb_parrainage_daily_check' );
}

// Autoload Composer
require_once WC_TB_PARRAINAGE_PATH . 'vendor/autoload.php';

// Hooks
register_activation_hook( __FILE__, 'wc_tb_parrainage_activate' );
register_deactivation_hook( __FILE__, 'wc_tb_parrainage_deactivate' );
add_action( 'plugins_loaded', 'wc_tb_parrainage_init' );

// Activation
function wc_tb_parrainage_activate() {
    if ( ! current_user_can( 'activate_plugins' ) ) return;
    
    if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'WordPress 6.0+ requis' );
    }
    
    // Vérifier WooCommerce
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'WooCommerce est requis pour ce plugin' );
    }
    
    // Créer options par défaut
    add_option( 'wc_tb_parrainage_version', WC_TB_PARRAINAGE_VERSION );
    add_option( 'wc_tb_parrainage_settings', array(
        'enable_webhooks' => true,
        'enable_parrainage' => true,
        'enable_coupon_hiding' => true,
        'log_retention_days' => 30
    ) );
    
    // NOUVEAU : Ajouter l'endpoint "Mes parrainages" AVANT le flush (ORDRE CRITIQUE)
    add_rewrite_endpoint( WC_TB_PARRAINAGE_ENDPOINT_KEY, EP_ROOT | EP_PAGES );
    
    // Flush les permaliens APRÈS l'ajout de l'endpoint
    flush_rewrite_rules();

    // Planifier la vérification quotidienne des remises expirées
    if ( ! wp_next_scheduled( WC_TB_PARRAINAGE_DAILY_CHECK_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', WC_TB_PARRAINAGE_DAILY_CHECK_HOOK );
    }
}

// Désactivation
function wc_tb_parrainage_deactivate() {
    flush_rewrite_rules();
    // Nettoyer les événements planifiés
    wp_clear_scheduled_hook( WC_TB_PARRAINAGE_DAILY_CHECK_HOOK );
}

// Initialisation
function wc_tb_parrainage_init() {
    // Vérifier les dépendances
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>WC TB-Web Parrainage nécessite WooCommerce pour fonctionner.</p></div>';
        });
        return;
    }
    
    load_plugin_textdomain( 'wc-tb-web-parrainage', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    // AJOUT v2.6.0 : Instance globale pour accès aux services
    global $wc_tb_parrainage_plugin;
    $wc_tb_parrainage_plugin = new TBWeb\WCParrainage\Plugin();
    
    // NOUVEAU v2.10.0 : Mise à jour version pour forcer reload
    update_option( 'wc_tb_parrainage_version', WC_TB_PARRAINAGE_VERSION );
    
    // Programmer le CRON d'expiration des remises filleul si pas déjà fait
    if ( ! wp_next_scheduled( 'tb_parrainage_check_filleul_expiration' ) ) {
        wp_schedule_event( time(), 'daily', 'tb_parrainage_check_filleul_expiration' );
    }
} 