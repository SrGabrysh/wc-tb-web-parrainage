<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CouponManager {
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    public function init() {
        // Hooks pour masquer les codes promo
        add_action( 'wp_head', array( $this, 'hide_cart_coupon_field' ) );
        add_action( 'wp_head', array( $this, 'hide_checkout_coupon_field' ) );
        add_action( 'wp_loaded', array( $this, 'hide_coupon_toggle_checkout' ) );
        add_filter( 'woocommerce_coupons_enabled', array( $this, 'prevent_coupon_application' ) );
        add_filter( 'woocommerce_update_cart_action_cart_updated', '__return_true' );
    }
    
    /**
     * Vérifier si le panier contient des produits configurés nécessitant le masquage des codes promo
     */
    public function panier_necessite_masquage_coupon() {
        $config = get_option( 'wc_tb_parrainage_products_config', array() );
        $product_ids = $this->obtenir_produits_panier();
        
        // Chercher si au moins un produit du panier est configuré (hors default)
        foreach ( $product_ids as $product_id ) {
            if ( isset( $config[ $product_id ] ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtenir les produits du panier
     */
    public function obtenir_produits_panier() {
        $product_ids = array();
        
        if ( is_admin() || ! WC()->cart || WC()->cart->is_empty() ) {
            return $product_ids;
        }
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_ids[] = $cart_item['product_id'];
            
            // Inclure aussi les variations si présentes
            if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 ) {
                $product_ids[] = $cart_item['variation_id'];
            }
        }
        
        return array_unique( $product_ids );
    }
    
    /**
     * Masquer le formulaire de coupon sur la page panier
     */
    public function hide_cart_coupon_field() {
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }
        
        if ( $this->panier_necessite_masquage_coupon() ) {
            // Log de l'action
            $this->logger->info( 
                'Masquage des codes promo activé - produits configurés détectés dans le panier',
                array( 'page' => is_cart() ? 'cart' : 'checkout', 'products' => $this->obtenir_produits_panier() ),
                'coupon-manager'
            );
            
            // Masquer via CSS
            echo '<style>
                .woocommerce-cart .coupon,
                .cart_totals .coupon,
                .wc-proceed-to-checkout .coupon,
                form.woocommerce-coupon-form,
                .woocommerce-form-coupon-toggle {
                    display: none !important;
                }
            </style>';
        }
    }
    
    /**
     * Masquer le formulaire de coupon sur la page de commande
     */
    public function hide_checkout_coupon_field() {
        if ( ! is_checkout() ) {
            return;
        }
        
        if ( $this->panier_necessite_masquage_coupon() ) {
            // Masquer via CSS
            echo '<style>
                .woocommerce-checkout .woocommerce-form-coupon-toggle,
                .woocommerce-checkout .woocommerce-form-coupon,
                .checkout_coupon,
                .woocommerce-checkout-coupon {
                    display: none !important;
                }
            </style>';
        }
    }
    
    /**
     * Masquer le lien "Vous avez un code promo ?" sur la page de commande
     */
    public function hide_coupon_toggle_checkout() {
        if ( $this->panier_necessite_masquage_coupon() ) {
            remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
        }
    }
    
    /**
     * Empêcher l'application de coupons via formulaire quand le produit est présent
     */
    public function prevent_coupon_application( $enabled ) {
        if ( $this->panier_necessite_masquage_coupon() ) {
            $this->logger->info( 
                'Application des codes promo désactivée - produits configurés dans le panier',
                array( 'products' => $this->obtenir_produits_panier() ),
                'coupon-manager'
            );
            return false;
        }
        return $enabled;
    }
} 