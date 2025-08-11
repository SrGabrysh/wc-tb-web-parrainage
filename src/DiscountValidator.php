<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validateur de conditions d'éligibilité aux remises parrain
 * 
 * Responsabilité unique : Valider toutes les conditions d'application des remises
 * Principe SRP : Séparation claire de la validation vs calcul
 * Principe OCP : Extensible pour nouvelles règles de validation
 * 
 * @since 2.5.0
 */
class DiscountValidator {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Cache des validations pour éviter les re-calculs
     * @var array
     */
    private $validation_cache = array();
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Valide l'éligibilité complète d'une remise parrain
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_order_id ID commande filleul
     * @param int $product_id ID produit filleul
     * @return array Résultat de validation avec détails
     */
    public function validate_discount_eligibility( $parrain_subscription_id, $filleul_order_id, $product_id ) {
        $validation_key = sprintf( '%d_%d_%d', $parrain_subscription_id, $filleul_order_id, $product_id );
        
        // Vérification cache pour performance
        if ( isset( $this->validation_cache[ $validation_key ] ) ) {
            return $this->validation_cache[ $validation_key ];
        }
        
        $result = array(
            'is_eligible' => true,
            'errors' => array(),
            'warnings' => array(),
            'details' => array(),
            'validation_date' => \current_time( 'mysql' )
        );
        
        try {
            // Validation de l'abonnement parrain
            $parrain_validation = $this->validate_parrain_subscription( $parrain_subscription_id );
            if ( ! $parrain_validation['is_valid'] ) {
                $result['is_eligible'] = false;
                $result['errors'] = array_merge( $result['errors'], $parrain_validation['errors'] );
            }
            
            // Validation de la commande filleul
            $filleul_validation = $this->validate_filleul_order( $filleul_order_id );
            if ( ! $filleul_validation['is_valid'] ) {
                $result['is_eligible'] = false;
                $result['errors'] = array_merge( $result['errors'], $filleul_validation['errors'] );
            }
            
            // Validation de la configuration produit
            $product_validation = $this->validate_product_configuration( $product_id );
            if ( ! $product_validation['is_valid'] ) {
                $result['is_eligible'] = false;
                $result['errors'] = array_merge( $result['errors'], $product_validation['errors'] );
            }
            
            // Validation des règles métier spécifiques
            $business_rules_validation = $this->validate_business_rules( $parrain_subscription_id, $filleul_order_id );
            if ( ! $business_rules_validation['is_valid'] ) {
                $result['is_eligible'] = false;
                $result['errors'] = array_merge( $result['errors'], $business_rules_validation['errors'] );
            }
            
            // Compilation des détails
            $result['details'] = array_merge(
                $parrain_validation['details'] ?? array(),
                $filleul_validation['details'] ?? array(),
                $product_validation['details'] ?? array(),
                $business_rules_validation['details'] ?? array()
            );
            
            // Log du résultat de validation
            $this->logger->info(
                'Validation d\'éligibilité remise parrain',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_order_id' => $filleul_order_id,
                    'product_id' => $product_id,
                    'is_eligible' => $result['is_eligible'],
                    'errors_count' => count( $result['errors'] ),
                    'warnings_count' => count( $result['warnings'] )
                ),
                'discount-validator'
            );
            
        } catch ( \InvalidArgumentException $e ) {
            // Erreur de validation des paramètres d'entrée
            $result['is_eligible'] = false;
            $result['errors'][] = 'Paramètres de validation invalides : ' . $e->getMessage();
            
            $this->logger->warning(
                'Paramètres invalides pour la validation d\'éligibilité',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_order_id' => $filleul_order_id,
                    'product_id' => $product_id,
                    'validation_error' => $e->getMessage()
                ),
                'discount-validator'
            );
        } catch ( \Exception $e ) {
            // Erreur système générale
            $result['is_eligible'] = false;
            $result['errors'][] = 'Erreur système lors de la validation : ' . $e->getMessage();
            
            $this->logger->error(
                'Erreur système lors de la validation d\'éligibilité',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_order_id' => $filleul_order_id,
                    'product_id' => $product_id,
                    'system_error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'discount-validator'
            );
        }
        
        // Mise en cache pour performance
        $this->validation_cache[ $validation_key ] = $result;
        
        return $result;
    }
    
    /**
     * Valide l'abonnement parrain
     * 
     * @param int $subscription_id ID abonnement
     * @return array Résultat validation
     */
    private function validate_parrain_subscription( $subscription_id ) {
        $result = array(
            'is_valid' => true,
            'errors' => array(),
            'details' => array()
        );
        
        // Vérification existence abonnement
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'WooCommerce Subscriptions non disponible';
            return $result;
        }
        
        $subscription = \wcs_get_subscription( $subscription_id );
        if ( ! $subscription ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Abonnement parrain non trouvé : ' . $subscription_id;
            return $result;
        }
        
        // Vérification statut abonnement
        $status = $subscription->get_status();
        $valid_statuses = array( 'active', 'pending' );
        
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Statut d\'abonnement parrain invalide : ' . $status;
        }
        
        // Vérification qu'aucune remise n'est déjà appliquée
        $existing_discount = $subscription->get_meta( '_parrain_discount_applied' );
        if ( $existing_discount === 'yes' ) {
            $result['errors'][] = 'Une remise parrain est déjà appliquée sur cet abonnement';
            // Note: Ne pas marquer comme invalide, c'est plutôt un avertissement
        }
        
        $result['details']['subscription_status'] = $status;
        $result['details']['subscription_total'] = $subscription->get_total();
        $result['details']['existing_discount'] = $existing_discount;
        
        return $result;
    }
    
    /**
     * Valide la commande filleul
     * 
     * @param int $order_id ID commande
     * @return array Résultat validation
     */
    private function validate_filleul_order( $order_id ) {
        $result = array(
            'is_valid' => true,
            'errors' => array(),
            'details' => array()
        );
        
        // Vérification existence commande
        $order = \wc_get_order( $order_id );
        if ( ! $order ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Commande filleul non trouvée : ' . $order_id;
            return $result;
        }
        
        // Vérification statut commande
        $status = $order->get_status();
        $valid_statuses = array( 'processing', 'completed', 'active' );
        
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Statut de commande filleul invalide : ' . $status;
        }
        
        // Vérification présence code parrain
        // Clé utilisée dans tout le plugin: _billing_parrain_code
        $parrain_code = $order->get_meta( '_billing_parrain_code' );
        if ( empty( $parrain_code ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Code parrain manquant dans la commande filleul';
        }
        
        $result['details']['order_status'] = $status;
        $result['details']['parrain_code'] = $parrain_code;
        $result['details']['order_total'] = $order->get_total();
        
        return $result;
    }
    
    /**
     * Valide la configuration produit pour les remises parrain
     * 
     * Vérifie la structure et la validité de la configuration :
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
     * @param int $product_id ID produit
     * @return array Résultat validation
     */
    private function validate_product_configuration( $product_id ) {
        $result = array(
            'is_valid' => true,
            'errors' => array(),
            'details' => array()
        );
        
        // Récupération configuration
        $products_config = \get_option( 'wc_tb_parrainage_products_config', array() );
        
        if ( ! isset( $products_config[ $product_id ] ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Configuration produit non trouvée : ' . $product_id;
            return $result;
        }
        
        $config = $products_config[ $product_id ];
        
        // Validation configuration remise parrain
        if ( ! isset( $config['remise_parrain'] ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Configuration remise parrain manquante pour le produit : ' . $product_id;
            return $result;
        }
        
        $remise_config = $config['remise_parrain'];
        
        // Validation montant remise
        if ( ! isset( $remise_config['montant'] ) || ! is_numeric( $remise_config['montant'] ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Montant de remise parrain invalide pour le produit : ' . $product_id;
        }
        
        // Validation type remise
        $valid_types = array( 'percentage', 'fixed' );
        $type = $remise_config['type'] ?? 'percentage';
        if ( ! in_array( $type, $valid_types, true ) ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Type de remise invalide : ' . $type;
        }
        
        $result['details']['discount_amount'] = $remise_config['montant'];
        $result['details']['discount_type'] = $type;
        $result['details']['product_config'] = $config;
        
        return $result;
    }
    
    /**
     * Valide les règles métier spécifiques
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_order_id ID commande filleul
     * @return array Résultat validation
     */
    private function validate_business_rules( $parrain_subscription_id, $filleul_order_id ) {
        $result = array(
            'is_valid' => true,
            'errors' => array(),
            'details' => array()
        );
        
        // Règle : Vérifier que le parrain et le filleul ne sont pas la même personne
        $parrain_user_id = $this->get_subscription_user_id( $parrain_subscription_id );
        $filleul_user_id = $this->get_order_user_id( $filleul_order_id );
        
        if ( $parrain_user_id && $filleul_user_id && $parrain_user_id === $filleul_user_id ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Auto-parrainage non autorisé';
        }
        
        // Règle : Vérifier limite de remises par parrain (si applicable)
        $active_discounts_count = $this->count_active_discounts_for_parrain( $parrain_subscription_id );
        $max_discounts = \apply_filters( 'tb_parrainage_max_discounts_per_parrain', 5 );
        
        if ( $active_discounts_count >= $max_discounts ) {
            $result['is_valid'] = false;
            $result['errors'][] = 'Limite maximum de remises atteinte pour ce parrain';
        }
        
        $result['details']['parrain_user_id'] = $parrain_user_id;
        $result['details']['filleul_user_id'] = $filleul_user_id;
        $result['details']['active_discounts_count'] = $active_discounts_count;
        $result['details']['max_discounts_allowed'] = $max_discounts;
        
        return $result;
    }
    
    /**
     * Récupère l'ID utilisateur d'un abonnement
     * 
     * @param int $subscription_id ID abonnement
     * @return int|false ID utilisateur ou false
     */
    private function get_subscription_user_id( $subscription_id ) {
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            return false;
        }
        
        $subscription = \wcs_get_subscription( $subscription_id );
        return $subscription ? $subscription->get_user_id() : false;
    }
    
    /**
     * Récupère l'ID utilisateur d'une commande
     * 
     * @param int $order_id ID commande
     * @return int|false ID utilisateur ou false
     */
    private function get_order_user_id( $order_id ) {
        $order = \wc_get_order( $order_id );
        return $order ? $order->get_user_id() : false;
    }
    
    /**
     * Compte les remises actives pour un parrain
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @return int Nombre de remises actives
     */
    private function count_active_discounts_for_parrain( $parrain_subscription_id ) {
        global $wpdb;
        
        // Requête pour compter les remises actives
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_parrain_discount_applied' 
             AND meta_value = 'yes'
             AND post_id IN (
                 SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_parrain_subscription_id'
                 AND meta_value = %d
             )",
            $parrain_subscription_id
        ) );
        
        return (int) $count;
    }
    
    /**
     * Vide le cache de validation
     */
    public function clear_validation_cache() {
        $this->validation_cache = array();
        $this->logger->debug( 'Cache de validation vidé', array(), 'discount-validator' );
    }
}