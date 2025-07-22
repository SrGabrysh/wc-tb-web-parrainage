<?php
/**
 * Plugin Name: WC TB-Web Parrainage
 * Description: Plugin de parrainage WooCommerce avec webhooks enrichis - Gestion des codes parrain au checkout, masquage conditionnel des codes promo et ajout des métadonnées d'abonnement dans les webhooks.
 * Version: 1.2.0
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
define( 'WC_TB_PARRAINAGE_VERSION', '1.2.0' );
define( 'WC_TB_PARRAINAGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_TB_PARRAINAGE_URL', plugin_dir_url( __FILE__ ) );

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
    
    flush_rewrite_rules();
}

// Désactivation
function wc_tb_parrainage_deactivate() {
    flush_rewrite_rules();
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
    new TBWeb\WCParrainage\Plugin();
} 