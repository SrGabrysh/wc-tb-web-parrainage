<?php

namespace TBWeb\WCParrainage\ParrainPricing\Scheduler;

use TBWeb\WCParrainage\ParrainPricing\Constants\ParrainPricingConstants;
use TBWeb\WCParrainage\ParrainPricing\Storage\ParrainPricingStorage;
use TBWeb\WCParrainage\Logger;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Planificateur pour le système de réduction automatique
 * 
 * Responsabilité unique : programmer et appliquer les modifications de prix
 * Utilise les hooks WooCommerce Subscriptions natifs
 * 
 * @since 2.0.0
 */
class ParrainPricingScheduler {
    
    /** @var Logger Instance du logger */
    private Logger $logger;
    
    /** @var ParrainPricingStorage Instance du stockage */
    private ParrainPricingStorage $storage;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du logger
     * @param ParrainPricingStorage $storage Instance du stockage
     */
    public function __construct( Logger $logger, ParrainPricingStorage $storage ) {
        $this->logger = $logger;
        $this->storage = $storage;
    }
    
    /**
     * Initialise les hooks du planificateur
     */
    public function init(): void {
        // Hook principal : prélèvement programmé WCS
        add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'handle_scheduled_payment' ), 5, 1 );
        
        // Hook secondaire : paiement manuel réussi
        add_action( 'woocommerce_subscription_payment_complete', array( $this, 'handle_payment_complete' ), 5, 1 );
        
        // Cron pour retry des échecs
        add_action( 'wc_tb_parrainage_pricing_retry', array( $this, 'handle_retry_cron' ) );
        
        // Programmation du cron retry si pas déjà planifié
        if ( ! wp_next_scheduled( 'wc_tb_parrainage_pricing_retry' ) ) {
            wp_schedule_event( time(), 'hourly', 'wc_tb_parrainage_pricing_retry' );
        }
    }
    
    /**
     * Programme une modification de prix pour le prochain prélèvement
     * 
     * @param array $pricing_context Contexte de la modification
     * @param array $calculation_data Données de calcul
     * @return array Résultat de la programmation
     */
    public function schedule_pricing_update( array $pricing_context, array $calculation_data ): array {
        try {
            // Préparer les données de programmation
            $scheduling_data = $this->prepare_scheduling_data( $pricing_context, $calculation_data );
            
            // Stockage via le gestionnaire de stockage
            $storage_result = $this->storage->store_scheduled_pricing( $scheduling_data );
            
            if ( ! $storage_result['success'] ) {
                throw new \Exception( $storage_result['error'] );
            }
            
            $this->logger->info( 'Modification de prix programmée', [
                'component' => 'ParrainPricingScheduler',
                'action' => 'schedule_pricing_update',
                'parrain_subscription_id' => $pricing_context['parrain_subscription_id'],
                'scheduled_date' => $scheduling_data['scheduled_date'],
                'reduction_amount' => $calculation_data['reduction_amount'],
                'pricing_id' => $storage_result['pricing_id']
            ]);
            
            return [
                'success' => true,
                'pricing_id' => $storage_result['pricing_id'],
                'scheduled_date' => $scheduling_data['scheduled_date'],
                'message' => 'Modification programmée avec succès'
            ];
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'Erreur programmation modification prix', [
                'component' => 'ParrainPricingScheduler',
                'action' => 'schedule_pricing_update',
                'error' => $e->getMessage(),
                'context' => $pricing_context,
                'calculation' => $calculation_data
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gère le prélèvement programmé d'un abonnement
     * 
     * @param int $subscription_id ID de l'abonnement
     */
    public function handle_scheduled_payment( int $subscription_id ): void {
        // Vérifier s'il y a une modification programmée pour cet abonnement
        $pending_pricing = $this->storage->get_pending_pricing( $subscription_id );
        
        if ( ! $pending_pricing ) {
            return; // Pas de modification programmée
        }
        
        $this->logger->info( 'Traitement prélèvement avec modification programmée', [
            'component' => 'ParrainPricingScheduler',
            'subscription_id' => $subscription_id,
            'pricing_id' => $pending_pricing['id']
        ]);
        
        // Appliquer la modification
        $this->apply_scheduled_pricing( $pending_pricing );
    }
    
    /**
     * Gère la finalisation d'un paiement d'abonnement
     * 
     * @param int $subscription_id ID de l'abonnement
     */
    public function handle_payment_complete( int $subscription_id ): void {
        // Même logique que scheduled_payment pour couvrir les paiements manuels
        $this->handle_scheduled_payment( $subscription_id );
    }
    
    /**
     * Applique une modification de prix programmée
     * 
     * @param array $scheduled_pricing Données de la modification
     */
    private function apply_scheduled_pricing( array $scheduled_pricing ): void {
        try {
            // Récupérer l'abonnement WooCommerce
            $subscription = wcs_get_subscription( $scheduled_pricing['parrain_subscription_id'] );
            
            if ( ! $subscription || ! $subscription->has_status( 'active' ) ) {
                $this->handle_application_failure( $scheduled_pricing, 'Abonnement non actif ou introuvable' );
                return;
            }
            
            // Vérifier si le prix a changé entre temps
            $current_price = $this->get_subscription_ht_price( $subscription );
            if ( abs( $current_price - $scheduled_pricing['original_price'] ) > 0.01 ) {
                $this->handle_application_failure( 
                    $scheduled_pricing, 
                    "Prix original modifié : attendu {$scheduled_pricing['original_price']}, actuel $current_price" 
                );
                return;
            }
            
            // Appliquer le nouveau prix
            $this->apply_new_price_to_subscription( $subscription, $scheduled_pricing );
            
            // Marquer comme appliqué
            $this->storage->mark_as_applied( $scheduled_pricing['id'] );
            
            // Enregistrer dans l'historique
            $this->record_successful_application( $scheduled_pricing );
            
            $this->logger->info( 'Modification de prix appliquée avec succès', [
                'component' => 'ParrainPricingScheduler',
                'pricing_id' => $scheduled_pricing['id'],
                'subscription_id' => $scheduled_pricing['parrain_subscription_id'],
                'old_price' => $scheduled_pricing['original_price'],
                'new_price' => $scheduled_pricing['new_price']
            ]);
            
        } catch ( \Exception $e ) {
            $this->handle_application_failure( $scheduled_pricing, $e->getMessage() );
        }
    }
    
    /**
     * Applique le nouveau prix à l'abonnement WooCommerce
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @param array $scheduled_pricing Données de modification
     */
    private function apply_new_price_to_subscription( $subscription, array $scheduled_pricing ): void {
        // Récupérer tous les items de l'abonnement
        $items = $subscription->get_items();
        
        if ( empty( $items ) ) {
            throw new \Exception( 'Aucun item trouvé dans l\'abonnement' );
        }
        
        // Calculer le ratio de réduction pour tous les items
        $price_ratio = $scheduled_pricing['new_price'] / $scheduled_pricing['original_price'];
        
        foreach ( $items as $item_id => $item ) {
            // Nouveau prix unitaire
            $old_price = $item->get_subtotal();
            $new_price = round( $old_price * $price_ratio, ParrainPricingConstants::PRICING_CALCULATION_PRECISION );
            
            // Mettre à jour l'item
            $item->set_subtotal( $new_price );
            $item->set_total( $new_price );
            $item->save();
        }
        
        // Recalculer les totaux de l'abonnement
        $subscription->calculate_totals();
        $subscription->save();
        
        // Ajouter une note à l'abonnement
        $subscription->add_order_note( sprintf(
            'Réduction parrainage appliquée automatiquement : %.2f€ → %.2f€ (économie : %.2f€)',
            $scheduled_pricing['original_price'],
            $scheduled_pricing['new_price'],
            $scheduled_pricing['reduction_amount']
        ) );
    }
    
    /**
     * Gère l'échec d'application d'une modification
     * 
     * @param array $scheduled_pricing Données de modification
     * @param string $error_message Message d'erreur
     */
    private function handle_application_failure( array $scheduled_pricing, string $error_message ): void {
        // Marquer comme échoué et incrémenter retry
        $this->storage->mark_as_failed( $scheduled_pricing['id'], $error_message );
        
        // Enregistrer dans l'historique
        $this->storage->store_history_record([
            'parrain_subscription_id' => $scheduled_pricing['parrain_subscription_id'],
            'filleul_order_id' => $scheduled_pricing['filleul_order_id'],
            'action' => $scheduled_pricing['action'],
            'price_before' => $scheduled_pricing['original_price'],
            'price_after' => $scheduled_pricing['original_price'], // Pas de changement
            'reduction_amount' => 0,
            'execution_status' => 'failed',
            'execution_details' => [
                'error_message' => $error_message,
                'retry_count' => ($scheduled_pricing['retry_count'] ?? 0) + 1,
                'failed_at' => current_time( 'mysql' )
            ],
            'user_notified' => false
        ]);
        
        $this->logger->error( 'Échec application modification prix', [
            'component' => 'ParrainPricingScheduler',
            'pricing_id' => $scheduled_pricing['id'],
            'subscription_id' => $scheduled_pricing['parrain_subscription_id'],
            'error' => $error_message,
            'retry_count' => ($scheduled_pricing['retry_count'] ?? 0) + 1
        ]);
    }
    
    /**
     * Enregistre une application réussie dans l'historique
     * 
     * @param array $scheduled_pricing Données de modification
     */
    private function record_successful_application( array $scheduled_pricing ): void {
        $this->storage->store_history_record([
            'parrain_subscription_id' => $scheduled_pricing['parrain_subscription_id'],
            'filleul_order_id' => $scheduled_pricing['filleul_order_id'],
            'action' => $scheduled_pricing['action'],
            'price_before' => $scheduled_pricing['original_price'],
            'price_after' => $scheduled_pricing['new_price'],
            'reduction_amount' => $scheduled_pricing['reduction_amount'],
            'execution_status' => 'success',
            'execution_details' => [
                'applied_at' => current_time( 'mysql' ),
                'plugin_version' => WC_TB_PARRAINAGE_VERSION
            ],
            'user_notified' => false // TODO: implémenter notification utilisateur
        ]);
    }
    
    /**
     * Gère le cron de retry des modifications échouées
     */
    public function handle_retry_cron(): void {
        $pending_retries = $this->storage->get_pending_retries();
        
        if ( empty( $pending_retries ) ) {
            return;
        }
        
        $this->logger->info( 'Traitement cron retry modifications échouées', [
            'component' => 'ParrainPricingScheduler',
            'pending_count' => count( $pending_retries )
        ]);
        
        foreach ( $pending_retries as $retry_pricing ) {
            // Vérifier si assez de temps s'est écoulé depuis le dernier échec
            if ( $this->should_retry_now( $retry_pricing ) ) {
                $this->apply_scheduled_pricing( $retry_pricing );
            }
        }
    }
    
    /**
     * Détermine si une modification doit être retentée maintenant
     * 
     * @param array $pricing_data Données de modification
     * @return bool True si doit être retentée
     */
    private function should_retry_now( array $pricing_data ): bool {
        $retry_count = (int) $pricing_data['retry_count'];
        $delays = ParrainPricingConstants::get_retry_delays();
        
        if ( $retry_count <= 0 || $retry_count > count( $delays ) ) {
            return false;
        }
        
        $required_delay = $delays[ $retry_count - 1 ] ?? 0;
        $last_update = strtotime( $pricing_data['updated_at'] );
        $elapsed = time() - $last_update;
        
        return $elapsed >= $required_delay;
    }
    
    /**
     * Prépare les données de programmation
     * 
     * @param array $context Contexte de la modification
     * @param array $calculation Données de calcul
     * @return array Données de programmation
     */
    private function prepare_scheduling_data( array $context, array $calculation ): array {
        return [
            'parrain_subscription_id' => $context['parrain_subscription_id'],
            'filleul_order_id' => $context['filleul_order_id'],
            'action' => ParrainPricingConstants::ACTION_APPLY_REDUCTION,
            'original_price' => $calculation['original_price'],
            'new_price' => $calculation['new_price'],
            'reduction_amount' => $calculation['reduction_amount'],
            'filleul_contribution' => $calculation['filleul_contribution'],
            'reduction_percentage' => $calculation['reduction_percentage'],
            'scheduled_date' => $context['next_payment_date'],
            'metadata' => [
                'filleul_product_ids' => $context['filleul_product_ids'] ?? [],
                'calculation_metadata' => $calculation['calculation_metadata'],
                'scheduled_by' => 'automatic_system',
                'created_at' => current_time( 'mysql' )
            ]
        ];
    }
    
    /**
     * Récupère le prix HT d'un abonnement
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @return float Prix HT
     */
    private function get_subscription_ht_price( $subscription ): float {
        return (float) $subscription->get_subtotal();
    }
} 