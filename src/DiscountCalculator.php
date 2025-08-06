<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculateur de remises parrain
 * 
 * Responsabilité unique : Calculer les montants de remise selon la configuration produit
 * Principe DRY : Centralise tous les calculs de remise
 * Principe KISS : Logique simple et directe de calcul
 * 
 * @since 2.5.0
 */
class DiscountCalculator {
    
    /**
     * Logger instance pour traçabilité
     * @var Logger
     */
    private $logger;
    
    /**
     * Cache des configurations produit pour performance
     * @var array
     */
    private $product_configs_cache = array();
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Calcule la remise parrain pour un produit donné
     * 
     * @param int $product_id ID du produit filleul
     * @param float $current_subscription_price Prix actuel de l'abonnement parrain
     * @param int $parrain_subscription_id ID de l'abonnement parrain (pour logs)
     * @return array|false Données de remise calculées ou false si erreur
     */
    public function calculate_parrain_discount( $product_id, $current_subscription_price, $parrain_subscription_id = 0 ) {
        try {
            // Validation des paramètres d'entrée
            if ( ! $this->validate_calculation_params( $product_id, $current_subscription_price ) ) {
                return false;
            }
            
            // Récupération de la configuration produit
            $product_config = $this->get_product_discount_config( $product_id );
            if ( ! $product_config ) {
                $this->logger->warning( 
                    'Configuration de remise non trouvée pour le produit', 
                    array( 'product_id' => $product_id ),
                    'discount-calculator'
                );
                return false;
            }
            
            // Calcul de la remise selon le type configuré
            $discount_data = $this->perform_discount_calculation( 
                $current_subscription_price, 
                $product_config,
                $parrain_subscription_id
            );
            
            // Log du succès du calcul
            $this->logger->info(
                'Calcul de remise parrain effectué',
                array(
                    'product_id' => $product_id,
                    'subscription_id' => $parrain_subscription_id,
                    'original_price' => $current_subscription_price,
                    'discount_amount' => $discount_data['discount_amount'],
                    'new_price' => $discount_data['new_price']
                ),
                'discount-calculator'
            );
            
            return $discount_data;
            
        } catch ( InvalidArgumentException $e ) {
            // Erreur de validation des paramètres - plus spécifique
            $this->logger->warning(
                'Paramètres invalides pour le calcul de remise parrain',
                array(
                    'product_id' => $product_id,
                    'subscription_id' => $parrain_subscription_id,
                    'validation_error' => $e->getMessage()
                ),
                'discount-calculator'
            );
            return false;
        } catch ( Exception $e ) {
            // Erreur système générale
            $this->logger->error(
                'Erreur système lors du calcul de remise parrain',
                array(
                    'product_id' => $product_id,
                    'subscription_id' => $parrain_subscription_id,
                    'system_error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'discount-calculator'
            );
            return false;
        }
    }
    
    /**
     * Récupère la configuration de remise pour un produit
     * 
     * Structure attendue de la configuration produit :
     * ```
     * [
     *   'product_id' => [
     *     'remise_parrain' => [
     *       'type' => 'percentage' | 'fixed',     // Type de remise
     *       'montant' => float,                   // Valeur de la remise
     *       'enabled' => bool                     // Remise activée
     *     ]
     *   ]
     * ]
     * ```
     * 
     * @param int $product_id ID du produit
     * @return array|false Configuration ou false si non trouvée
     */
    private function get_product_discount_config( $product_id ) {
        // Vérification cache pour performance
        if ( isset( $this->product_configs_cache[ $product_id ] ) ) {
            return $this->product_configs_cache[ $product_id ];
        }
        
        // Récupération depuis la configuration existante du plugin
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        
        if ( ! isset( $products_config[ $product_id ] ) ) {
            return false;
        }
        
        $config = $products_config[ $product_id ];
        
        // Validation de la structure de configuration
        if ( ! isset( $config['remise_parrain'] ) ) {
            return false;
        }
        
        // Normalisation de la configuration
        $normalized_config = array(
            'discount_type' => $config['remise_parrain']['type'] ?? 'percentage',
            'discount_value' => (float) ( $config['remise_parrain']['montant'] ?? WC_TB_PARRAINAGE_DEFAULT_DISCOUNT_RATE ),
            'currency' => $config['remise_parrain']['unite'] ?? 'EUR',
            'product_name' => $config['description'] ?? 'Produit inconnu'
        );
        
        // Mise en cache pour performance
        $this->product_configs_cache[ $product_id ] = $normalized_config;
        
        return $normalized_config;
    }
    
    /**
     * Effectue le calcul réel de la remise
     * 
     * @param float $current_price Prix actuel
     * @param array $config Configuration produit
     * @param int $subscription_id ID abonnement pour logs
     * @return array Données calculées
     */
    private function perform_discount_calculation( $current_price, $config, $subscription_id ) {
        $discount_amount = 0.0;
        
        switch ( $config['discount_type'] ) {
            case 'percentage':
                // Calcul en pourcentage avec validation de limite
                $rate = min( $config['discount_value'], WC_TB_PARRAINAGE_MAX_DISCOUNT_RATE );
                $discount_amount = $current_price * $rate;
                break;
                
            case 'fixed':
                // Remise fixe avec validation de montant
                $discount_amount = min( $config['discount_value'], $current_price - WC_TB_PARRAINAGE_MIN_SUBSCRIPTION_AMOUNT );
                break;
                
            default:
                throw new InvalidArgumentException( 'Type de remise non supporté : ' . $config['discount_type'] );
        }
        
        // Arrondir selon la précision définie
        $discount_amount = round( $discount_amount, WC_TB_PARRAINAGE_DISCOUNT_PRECISION );
        $new_price = round( $current_price - $discount_amount, WC_TB_PARRAINAGE_DISCOUNT_PRECISION );
        
        // S'assurer que le nouveau prix n'est jamais négatif
        $new_price = max( WC_TB_PARRAINAGE_MIN_SUBSCRIPTION_AMOUNT, $new_price );
        
        return array(
            'original_price' => $current_price,
            'discount_amount' => $discount_amount,
            'new_price' => $new_price,
            'discount_type' => $config['discount_type'],
            'discount_rate' => $config['discount_value'],
            'currency' => $config['currency'],
            'calculation_date' => current_time( 'mysql' ),
            'metadata' => array(
                'calculator_version' => '2.5.0',
                'subscription_id' => $subscription_id,
                'product_config' => $config
            )
        );
    }
    
    /**
     * Valide les paramètres de calcul
     * 
     * @param int $product_id ID produit
     * @param float $price Prix
     * @throws InvalidArgumentException Si les paramètres sont invalides
     * @return bool True si valide
     */
    private function validate_calculation_params( $product_id, $price ) {
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            $error_msg = 'ID produit invalide pour calcul remise : ' . $product_id;
            $this->logger->error( $error_msg, array( 'product_id' => $product_id ), 'discount-calculator' );
            throw new InvalidArgumentException( $error_msg );
        }
        
        if ( ! is_numeric( $price ) || $price < WC_TB_PARRAINAGE_MIN_SUBSCRIPTION_AMOUNT ) {
            $error_msg = 'Prix invalide pour calcul remise : ' . $price . ' (minimum: ' . WC_TB_PARRAINAGE_MIN_SUBSCRIPTION_AMOUNT . ')';
            $this->logger->error( $error_msg, array( 'price' => $price ), 'discount-calculator' );
            throw new InvalidArgumentException( $error_msg );
        }
        
        return true;
    }
    
    /**
     * Formate un montant pour affichage
     * 
     * @param float $amount Montant
     * @param string $currency Devise
     * @return string Montant formaté
     */
    public function format_discount_amount( $amount, $currency = 'EUR' ) {
        return number_format( $amount, WC_TB_PARRAINAGE_DISCOUNT_PRECISION, ',', '' ) . '€/' . __( 'mois', 'wc-tb-web-parrainage' );
    }
    
    /**
     * Vide le cache des configurations
     * Utile après modification de configuration
     */
    public function clear_config_cache() {
        $this->product_configs_cache = array();
        $this->logger->debug( 'Cache des configurations produit vidé', array(), 'discount-calculator' );
    }
}