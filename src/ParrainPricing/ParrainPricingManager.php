<?php

namespace TBWeb\WCParrainage\ParrainPricing;

use TBWeb\WCParrainage\ParrainPricing\Constants\ParrainPricingConstants;
use TBWeb\WCParrainage\ParrainPricing\Calculator\ParrainPricingCalculator;
use TBWeb\WCParrainage\ParrainPricing\Scheduler\ParrainPricingScheduler;
use TBWeb\WCParrainage\ParrainPricing\Storage\ParrainPricingStorage;
use TBWeb\WCParrainage\ParrainPricing\Notifier\ParrainPricingEmailNotifier;
use TBWeb\WCParrainage\Logger;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manager principal du système de réduction automatique du parrain
 * 
 * Orchestrateur utilisant la composition (principe 13)
 * Injection de dépendances (respect du DIP)
 * Responsabilité unique : coordination des composants (principe SRP)
 * 
 * @since 2.0.0
 */
class ParrainPricingManager {
    
    /** @var ParrainPricingCalculator Calculateur de réductions */
    private ParrainPricingCalculator $calculator;
    
    /** @var ParrainPricingScheduler Planificateur de modifications */
    private ParrainPricingScheduler $scheduler;
    
    /** @var ParrainPricingStorage Gestionnaire de stockage */
    private ParrainPricingStorage $storage;
    
    /** @var ParrainPricingEmailNotifier Gestionnaire de notifications */
    private ParrainPricingEmailNotifier $notifier;
    
    /** @var Logger Instance du logger */
    private Logger $logger;
    
    /**
     * Constructeur - Injection de toutes les dépendances (composition)
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        
        // Initialisation des composants par composition
        $this->storage = new ParrainPricingStorage( $this->logger );
        $this->calculator = new ParrainPricingCalculator( $this->logger );
        $this->scheduler = new ParrainPricingScheduler( $this->logger, $this->storage );
        $this->notifier = new ParrainPricingEmailNotifier( $this->logger );
    }
    
    /**
     * Initialise le système de pricing
     */
    public function init(): void {
        // Initialiser le planificateur (hooks WCS)
        $this->scheduler->init();
        
        // Hooks pour déclenchement automatique
        add_action( 'woocommerce_order_status_completed', array( $this, 'handle_completed_order' ), 20, 1 );
        add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'handle_subscription_cancelled' ), 10, 1 );
        add_action( 'woocommerce_subscription_status_expired', array( $this, 'handle_subscription_expired' ), 10, 1 );
        
        $this->logger->info( 'Système de réduction automatique initialisé', [
            'component' => 'ParrainPricingManager'
        ]);
    }
    
    /**
     * Hook principal : traitement commande validée
     * 
     * Responsabilité unique : orchestration (principe SRP)
     * 
     * @param int $order_id ID de la commande
     */
    public function handle_completed_order( int $order_id ): void {
        try {
            // Vérifier si le système est activé
            if ( ! $this->is_pricing_system_enabled() ) {
                return;
            }
            
            // Étape 1 : Validation (SoC - préoccupation séparée)
            $validation_result = $this->validate_order_for_pricing( $order_id );
            
            if ( ! $validation_result['is_valid'] ) {
                $this->logger->info( 'Commande non éligible pour pricing parrain', [
                    'component' => 'ParrainPricingManager',
                    'order_id' => $order_id,
                    'reason' => $validation_result['reason']
                ]);
                return; // Early return (principe KISS)
            }
            
            // Étape 2 : Construction contexte (encapsulation)
            $pricing_context = $this->build_pricing_context( $validation_result );
            
            // Étape 3 : Calcul (SoC - préoccupation séparée)
            $pricing_data = $this->calculator->calculate(
                $pricing_context['parrain_current_price'],
                $pricing_context['filleul_price']
            );
            
            // Étape 4 : Planification (SoC - préoccupation séparée)
            $scheduling_result = $this->scheduler->schedule_pricing_update( $pricing_context, $pricing_data );
            
            if ( $scheduling_result['success'] ) {
                $this->logger->info( 'Réduction automatique programmée avec succès', [
                    'component' => 'ParrainPricingManager',
                    'order_id' => $order_id,
                    'parrain_subscription_id' => $pricing_context['parrain_subscription_id'],
                    'pricing_id' => $scheduling_result['pricing_id'],
                    'reduction_amount' => $pricing_data['reduction_amount']
                ]);
            }
            
        } catch ( \Exception $e ) {
            // Gestion d'erreur centralisée (principe DRY)
            $this->handle_pricing_error( $e, 'handle_completed_order', ['order_id' => $order_id] );
        }
    }
    
    /**
     * Gère l'annulation d'un abonnement
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     */
    public function handle_subscription_cancelled( $subscription ): void {
        $this->handle_subscription_termination( $subscription, 'cancelled' );
    }
    
    /**
     * Gère l'expiration d'un abonnement
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     */
    public function handle_subscription_expired( $subscription ): void {
        $this->handle_subscription_termination( $subscription, 'expired' );
    }
    
    /**
     * Gère la terminaison d'un abonnement (annulation/expiration)
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @param string $reason Raison de la terminaison
     */
    private function handle_subscription_termination( $subscription, string $reason ): void {
        try {
            $subscription_id = $subscription->get_id();
            
            // Vérifier s'il y a une réduction en cours pour cet abonnement
            $pending_pricing = $this->storage->get_pending_pricing( $subscription_id );
            
            if ( $pending_pricing ) {
                // Annuler la réduction programmée
                $this->cancel_pending_pricing( $pending_pricing['id'], $reason );
                
                $this->logger->info( 'Réduction programmée annulée suite à terminaison abonnement', [
                    'component' => 'ParrainPricingManager',
                    'subscription_id' => $subscription_id,
                    'pricing_id' => $pending_pricing['id'],
                    'reason' => $reason
                ]);
            }
            
        } catch ( \Exception $e ) {
            $this->handle_pricing_error( $e, 'handle_subscription_termination', [
                'subscription_id' => $subscription->get_id(),
                'reason' => $reason
            ]);
        }
    }
    
    /**
     * Valide si une commande est éligible pour le pricing
     * 
     * @param int $order_id ID de la commande
     * @return array Résultat de validation
     */
    private function validate_order_for_pricing( int $order_id ): array {
        // Récupérer la commande
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return [
                'is_valid' => false,
                'reason' => 'Commande introuvable'
            ];
        }
        
        // Vérifier si la commande a un code parrain
        $parrain_code = $order->get_meta( '_parrain_code' );
        if ( empty( $parrain_code ) ) {
            return [
                'is_valid' => false,
                'reason' => 'Aucun code parrain'
            ];
        }
        
        // Valider le code parrain (doit être un ID d'abonnement actif)
        $parrain_subscription = wcs_get_subscription( $parrain_code );
        if ( ! $parrain_subscription || ! $parrain_subscription->has_status( 'active' ) ) {
            return [
                'is_valid' => false,
                'reason' => 'Code parrain invalide ou abonnement non actif'
            ];
        }
        
        // Vérifier que la commande contient des abonnements
        $subscription_ids = wcs_get_subscriptions_for_order( $order_id );
        if ( empty( $subscription_ids ) ) {
            return [
                'is_valid' => false,
                'reason' => 'Commande sans abonnement'
            ];
        }
        
        // Empêcher l'auto-parrainage
        $customer_id = $order->get_customer_id();
        $parrain_customer_id = $parrain_subscription->get_customer_id();
        if ( $customer_id === $parrain_customer_id ) {
            return [
                'is_valid' => false,
                'reason' => 'Auto-parrainage non autorisé'
            ];
        }
        
        return [
            'is_valid' => true,
            'order_id' => $order_id,
            'parrain_subscription_id' => $parrain_code,
            'parrain_subscription' => $parrain_subscription,
            'filleul_subscriptions' => $subscription_ids,
            'filleul_customer_id' => $customer_id
        ];
    }
    
    /**
     * Construction du contexte de pricing
     * 
     * Principe d'encapsulation : prépare toutes les données nécessaires
     * 
     * @param array $validation_result Résultat de validation
     * @return array Contexte
     */
    private function build_pricing_context( array $validation_result ): array {
        $parrain_subscription = $validation_result['parrain_subscription'];
        $filleul_subscription_ids = $validation_result['filleul_subscriptions'];
        
        // Calculer le prix total des abonnements filleul
        $total_filleul_price = 0;
        $filleul_product_ids = [];
        
        foreach ( $filleul_subscription_ids as $subscription_id ) {
            $subscription = wcs_get_subscription( $subscription_id );
            if ( $subscription ) {
                $total_filleul_price += (float) $subscription->get_subtotal();
                
                // Collecter les IDs produits pour métadonnées
                foreach ( $subscription->get_items() as $item ) {
                    $filleul_product_ids[] = $item->get_product_id();
                }
            }
        }
        
        return [
            'parrain_subscription_id' => $validation_result['parrain_subscription_id'],
            'filleul_order_id' => $validation_result['order_id'],
            'parrain_current_price' => (float) $parrain_subscription->get_subtotal(),
            'filleul_price' => $total_filleul_price,
            'next_payment_date' => $parrain_subscription->get_date( 'next_payment' ),
            'filleul_product_ids' => array_unique( $filleul_product_ids ),
            'parrain_user_id' => $parrain_subscription->get_customer_id(),
            'filleul_user_id' => $validation_result['filleul_customer_id']
        ];
    }
    
    /**
     * Annule une modification de prix programmée
     * 
     * @param int $pricing_id ID de la modification
     * @param string $reason Raison de l'annulation
     * @return bool Succès
     */
    private function cancel_pending_pricing( int $pricing_id, string $reason ): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => ParrainPricingConstants::STATUS_CANCELLED,
                'updated_at' => current_time( 'mysql' ),
                'metadata' => wp_json_encode([
                    'cancellation_reason' => $reason,
                    'cancelled_at' => current_time( 'mysql' )
                ])
            ],
            [ 'id' => $pricing_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    /**
     * Vérifie si le système de pricing est activé
     * 
     * @return bool True si activé
     */
    private function is_pricing_system_enabled(): bool {
        $settings = get_option( 'wc_tb_parrainage_settings', [] );
        return ! empty( $settings['enable_automatic_pricing'] );
    }
    
    /**
     * Gestion centralisée des erreurs
     * 
     * Principe DRY : évite duplication de la logique d'erreur
     * 
     * @param \Exception $exception Exception
     * @param string $operation Opération en cours
     * @param array $context Contexte additionnel
     */
    private function handle_pricing_error( \Exception $exception, string $operation, array $context = [] ): void {
        // Log structuré avec contexte complet
        $this->logger->error( 'Erreur système de réduction parrain', [
            'component' => 'ParrainPricingManager',
            'operation' => $operation,
            'exception_type' => get_class( $exception ),
            'message' => $exception->getMessage(),
            'context' => $context,
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
        
        // Notification admin pour erreurs critiques
        if ( $this->is_critical_error( $exception ) ) {
            $this->notifier->notify_pricing_error([
                'component' => 'ParrainPricingManager',
                'operation' => $operation,
                'error_message' => $exception->getMessage(),
                'context' => $context,
                'timestamp' => current_time( 'mysql' )
            ]);
        }
    }
    
    /**
     * Détermine si une erreur est critique
     * 
     * @param \Exception $exception Exception
     * @return bool True si critique
     */
    private function is_critical_error( \Exception $exception ): bool {
        // Erreurs de base de données = critiques
        if ( strpos( $exception->getMessage(), 'database' ) !== false ) {
            return true;
        }
        
        // Erreurs d'abonnement = critiques
        if ( strpos( $exception->getMessage(), 'subscription' ) !== false ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Méthode utilitaire pour obtenir les statistiques du système
     * 
     * @return array Statistiques
     */
    public function get_system_statistics(): array {
        global $wpdb;
        
        $schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        $history_table = $wpdb->prefix . 'tb_parrainage_pricing_history';
        
        return [
            'total_scheduled' => $wpdb->get_var( "SELECT COUNT(*) FROM $schedule_table" ),
            'pending' => $wpdb->get_var( "SELECT COUNT(*) FROM $schedule_table WHERE status = 'pending'" ),
            'applied' => $wpdb->get_var( "SELECT COUNT(*) FROM $schedule_table WHERE status = 'applied'" ),
            'failed' => $wpdb->get_var( "SELECT COUNT(*) FROM $schedule_table WHERE status = 'failed'" ),
            'total_savings' => $wpdb->get_var( "SELECT SUM(reduction_amount) FROM $schedule_table WHERE status = 'applied'" ),
            'success_rate' => $this->calculate_success_rate()
        ];
    }
    
    /**
     * Calcule le taux de succès du système
     * 
     * @return float Taux de succès en pourcentage
     */
    private function calculate_success_rate(): float {
        global $wpdb;
        
        $schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM $schedule_table WHERE status IN ('applied', 'failed')" );
        $success = $wpdb->get_var( "SELECT COUNT(*) FROM $schedule_table WHERE status = 'applied'" );
        
        if ( $total == 0 ) {
            return 100.0;
        }
        
        return round( ( $success / $total ) * 100, 2 );
    }
} 