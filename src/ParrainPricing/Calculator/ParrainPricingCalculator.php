<?php

namespace TBWeb\WCParrainage\ParrainPricing\Calculator;

use TBWeb\WCParrainage\ParrainPricing\Constants\ParrainPricingConstants;
use TBWeb\WCParrainage\Logger;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculateur de réduction automatique du parrain
 * 
 * Responsabilité unique : calculer la nouvelle tarification du parrain
 * selon la règle métier simple : 
 * Nouveau prix HT = MAX(0, Prix HT actuel - (Prix HT filleul × 25%))
 * 
 * @since 2.0.0
 */
class ParrainPricingCalculator {
    
    /** @var Logger Instance du logger */
    private Logger $logger;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Calcule la nouvelle tarification du parrain selon la règle métier
     * 
     * Principe KISS : calcul simple et direct
     * Formule : max(0, prix_parrain - 25% * prix_filleul)
     * 
     * @param float $parrain_current_ht Prix HT actuel du parrain
     * @param float $filleul_ht_price Prix HT de l'abonnement du filleul
     * @return array Résultat de calcul structuré
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    public function calculate( float $parrain_current_ht, float $filleul_ht_price ): array {
        // Validation des entrées (principe d'encapsulation)
        $this->validate_calculation_inputs( $parrain_current_ht, $filleul_ht_price );
        
        // Calcul simple et direct (principe KISS)
        $reduction_amount = $this->calculate_reduction_amount( $filleul_ht_price );
        $new_price = $this->calculate_new_price( $parrain_current_ht, $reduction_amount );
        $reduction_percentage = $this->calculate_reduction_percentage( $parrain_current_ht, $reduction_amount );
        
        // Résultat structuré (principe Least Astonishment)
        $result = [
            'original_price' => $parrain_current_ht,
            'new_price' => $new_price,
            'reduction_amount' => min( $reduction_amount, $parrain_current_ht ), // Réduction réelle
            'theoretical_reduction' => $reduction_amount, // Réduction théorique
            'filleul_contribution' => $filleul_ht_price,
            'reduction_percentage' => $reduction_percentage,
            'is_free_subscription' => $new_price === 0.0,
            'calculation_metadata' => $this->build_calculation_metadata( $parrain_current_ht, $filleul_ht_price )
        ];
        
        // Log du calcul pour traçabilité
        $this->logger->info( 'Calcul réduction parrain effectué', [
            'component' => 'ParrainPricingCalculator',
            'parrain_price' => $parrain_current_ht,
            'filleul_price' => $filleul_ht_price,
            'result' => $result
        ]);
        
        return $result;
    }
    
    /**
     * Calcule le montant de réduction avec constante explicite
     * 
     * Évite magic number 0.25 dispersé dans le code (principe DRY)
     * 
     * @param float $filleul_price Prix HT du filleul
     * @return float Montant de la réduction
     */
    private function calculate_reduction_amount( float $filleul_price ): float {
        return round(
            $filleul_price * ( ParrainPricingConstants::FILLEUL_CONTRIBUTION_PERCENTAGE / 100 ),
            ParrainPricingConstants::PRICING_CALCULATION_PRECISION
        );
    }
    
    /**
     * Calcule le nouveau prix avec contrainte métier claire
     * 
     * Garantit que le prix ne soit jamais négatif (principe Least Astonishment)
     * 
     * @param float $current_price Prix actuel
     * @param float $reduction Montant de réduction
     * @return float Nouveau prix
     */
    private function calculate_new_price( float $current_price, float $reduction ): float {
        return max(
            ParrainPricingConstants::MIN_PARRAIN_PRICE,
            round( $current_price - $reduction, ParrainPricingConstants::PRICING_CALCULATION_PRECISION )
        );
    }
    
    /**
     * Calcule le pourcentage de réduction réelle
     * 
     * @param float $original_price Prix original
     * @param float $reduction_amount Montant de réduction
     * @return float Pourcentage de réduction
     */
    private function calculate_reduction_percentage( float $original_price, float $reduction_amount ): float {
        if ( $original_price <= 0 ) {
            return 0.0;
        }
        
        $actual_reduction = min( $reduction_amount, $original_price );
        return round( ( $actual_reduction / $original_price ) * 100, 1 );
    }
    
    /**
     * Validation des entrées (principe d'encapsulation)
     * 
     * Responsabilité unique : contrôler la validité des données
     * 
     * @param float $parrain_price Prix du parrain
     * @param float $filleul_price Prix du filleul
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    private function validate_calculation_inputs( float $parrain_price, float $filleul_price ): void {
        if ( $parrain_price < 0 ) {
            throw new \InvalidArgumentException( "Prix parrain invalide : $parrain_price" );
        }
        
        if ( $filleul_price <= 0 ) {
            throw new \InvalidArgumentException( "Prix filleul invalide : $filleul_price" );
        }
        
        // Validation cohérence métier
        if ( $parrain_price > 10000 || $filleul_price > 10000 ) {
            throw new \InvalidArgumentException( 'Prix suspicieusement élevé détecté' );
        }
    }
    
    /**
     * Construction des métadonnées de calcul
     * 
     * Encapsule le contexte de calcul pour debugging et audit
     * 
     * @param float $parrain_price Prix parrain
     * @param float $filleul_price Prix filleul
     * @return array Métadonnées
     */
    private function build_calculation_metadata( float $parrain_price, float $filleul_price ): array {
        return [
            'calculation_date' => current_time( 'Y-m-d H:i:s' ),
            'calculation_timestamp' => time(),
            'contribution_percentage' => ParrainPricingConstants::FILLEUL_CONTRIBUTION_PERCENTAGE,
            'precision_decimals' => ParrainPricingConstants::PRICING_CALCULATION_PRECISION,
            'min_price_constraint' => ParrainPricingConstants::MIN_PARRAIN_PRICE,
            'plugin_version' => WC_TB_PARRAINAGE_VERSION,
            'calculation_formula' => 'max(0, parrain_price - (filleul_price * 0.25))'
        ];
    }
    
    /**
     * Vérifie si un calcul est valide business-wise
     * 
     * @param array $calculation_result Résultat de calcul
     * @return bool True si valide
     */
    public function is_calculation_valid( array $calculation_result ): bool {
        // Vérifications de cohérence
        if ( ! isset( $calculation_result['new_price'], $calculation_result['original_price'] ) ) {
            return false;
        }
        
        // Le nouveau prix ne peut pas être supérieur à l'original
        if ( $calculation_result['new_price'] > $calculation_result['original_price'] ) {
            return false;
        }
        
        // Le nouveau prix ne peut pas être négatif
        if ( $calculation_result['new_price'] < 0 ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Simule un calcul sans l'exécuter (dry-run)
     * 
     * Utile pour les prévisualisations et validations
     * 
     * @param float $parrain_price Prix parrain
     * @param float $filleul_price Prix filleul
     * @return array Simulation du calcul
     */
    public function simulate_calculation( float $parrain_price, float $filleul_price ): array {
        try {
            $result = $this->calculate( $parrain_price, $filleul_price );
            $result['is_simulation'] = true;
            $result['simulation_date'] = current_time( 'Y-m-d H:i:s' );
            
            return $result;
            
        } catch ( \InvalidArgumentException $e ) {
            return [
                'is_simulation' => true,
                'error' => true,
                'error_message' => $e->getMessage(),
                'simulation_date' => current_time( 'Y-m-d H:i:s' )
            ];
        }
    }
} 