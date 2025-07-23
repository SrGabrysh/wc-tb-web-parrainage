<?php

namespace TBWeb\WCParrainage\ParrainPricing\Storage;

use TBWeb\WCParrainage\ParrainPricing\Constants\ParrainPricingConstants;
use TBWeb\WCParrainage\Logger;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire de stockage pour le système de réduction automatique
 * 
 * Responsabilité unique : persistance des données de pricing
 * SSOT (Single Source of Truth) pour les modifications programmées
 * 
 * @since 2.0.0
 */
class ParrainPricingStorage {
    
    /** @var Logger Instance du logger */
    private Logger $logger;
    
    /** @var string Nom de la table principale */
    private string $schedule_table;
    
    /** @var string Nom de la table d'historique */
    private string $history_table;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        global $wpdb;
        
        $this->logger = $logger;
        $this->schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        $this->history_table = $wpdb->prefix . 'tb_parrainage_pricing_history';
    }
    
    /**
     * Stocke une modification de prix programmée
     * 
     * @param array $scheduling_data Données de programmation
     * @return array Résultat du stockage
     */
    public function store_scheduled_pricing( array $scheduling_data ): array {
        global $wpdb;
        
        try {
            // Validation des données requises
            $this->validate_scheduling_data( $scheduling_data );
            
            // Vérifier qu'il n'y a pas déjà une modification en cours pour cet abonnement
            $existing = $this->get_pending_pricing( $scheduling_data['parrain_subscription_id'] );
            if ( $existing ) {
                return [
                    'success' => false,
                    'error' => 'Une modification de prix est déjà programmée pour cet abonnement',
                    'existing_id' => $existing['id']
                ];
            }
            
            // Préparer les données pour insertion
            $insert_data = [
                'parrain_subscription_id' => $scheduling_data['parrain_subscription_id'],
                'filleul_order_id' => $scheduling_data['filleul_order_id'],
                'action' => $scheduling_data['action'],
                'original_price' => $scheduling_data['original_price'],
                'new_price' => $scheduling_data['new_price'],
                'reduction_amount' => $scheduling_data['reduction_amount'],
                'filleul_contribution' => $scheduling_data['filleul_contribution'],
                'reduction_percentage' => $scheduling_data['reduction_percentage'],
                'scheduled_date' => $scheduling_data['scheduled_date'],
                'status' => ParrainPricingConstants::STATUS_PENDING,
                'retry_count' => 0,
                'metadata' => wp_json_encode( $scheduling_data['metadata'] ?? [] ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ];
            
            // Insertion en base
            $result = $wpdb->insert(
                $this->schedule_table,
                $insert_data,
                [
                    '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', 
                    '%d', '%s', '%s', '%s'
                ]
            );
            
            if ( $result === false ) {
                throw new \Exception( 'Erreur lors de l\'insertion en base : ' . $wpdb->last_error );
            }
            
            $inserted_id = $wpdb->insert_id;
            
            // Log de succès
            $this->logger->info( 'Modification de prix programmée stockée', [
                'component' => 'ParrainPricingStorage',
                'action' => 'store_scheduled_pricing',
                'pricing_id' => $inserted_id,
                'parrain_subscription_id' => $scheduling_data['parrain_subscription_id'],
                'scheduled_date' => $scheduling_data['scheduled_date']
            ]);
            
            return [
                'success' => true,
                'pricing_id' => $inserted_id,
                'message' => 'Modification programmée avec succès'
            ];
            
        } catch ( \Exception $e ) {
            // Log d'erreur
            $this->logger->error( 'Erreur stockage modification programmée', [
                'component' => 'ParrainPricingStorage',
                'action' => 'store_scheduled_pricing',
                'error' => $e->getMessage(),
                'data' => $scheduling_data
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère une modification en attente pour un abonnement
     * 
     * @param int $subscription_id ID de l'abonnement
     * @return array|null Données de la modification ou null
     */
    public function get_pending_pricing( int $subscription_id ): ?array {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->schedule_table} 
             WHERE parrain_subscription_id = %d 
             AND status = %s 
             ORDER BY created_at DESC 
             LIMIT 1",
            $subscription_id,
            ParrainPricingConstants::STATUS_PENDING
        );
        
        $result = $wpdb->get_row( $query, ARRAY_A );
        
        if ( $result && isset( $result['metadata'] ) ) {
            $result['metadata'] = json_decode( $result['metadata'], true ) ?: [];
        }
        
        return $result;
    }
    
    /**
     * Marque une modification comme appliquée
     * 
     * @param int $pricing_id ID de la modification
     * @return bool Succès de l'opération
     */
    public function mark_as_applied( int $pricing_id ): bool {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->schedule_table,
            [
                'status' => ParrainPricingConstants::STATUS_APPLIED,
                'applied_date' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $pricing_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            $this->logger->info( 'Modification marquée comme appliquée', [
                'component' => 'ParrainPricingStorage',
                'pricing_id' => $pricing_id
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Marque une modification comme échouée et incrémente le compteur de retry
     * 
     * @param int $pricing_id ID de la modification
     * @param string $error_message Message d'erreur
     * @return bool Succès de l'opération
     */
    public function mark_as_failed( int $pricing_id, string $error_message = '' ): bool {
        global $wpdb;
        
        // Récupérer le nombre de tentatives actuel
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT retry_count, metadata FROM {$this->schedule_table} WHERE id = %d",
                $pricing_id
            ),
            ARRAY_A
        );
        
        if ( ! $current ) {
            return false;
        }
        
        $retry_count = (int) $current['retry_count'] + 1;
        $metadata = json_decode( $current['metadata'], true ) ?: [];
        
        // Ajouter l'erreur aux métadonnées
        if ( $error_message ) {
            $metadata['last_error'] = $error_message;
            $metadata['last_error_date'] = current_time( 'mysql' );
        }
        
        // Déterminer le statut final
        $final_status = $retry_count >= ParrainPricingConstants::RETRY_MAX_ATTEMPTS 
            ? ParrainPricingConstants::STATUS_FAILED 
            : ParrainPricingConstants::STATUS_PENDING;
        
        $result = $wpdb->update(
            $this->schedule_table,
            [
                'status' => $final_status,
                'retry_count' => $retry_count,
                'metadata' => wp_json_encode( $metadata ),
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $pricing_id ],
            [ '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            $this->logger->warning( 'Modification marquée comme échouée', [
                'component' => 'ParrainPricingStorage',
                'pricing_id' => $pricing_id,
                'retry_count' => $retry_count,
                'final_status' => $final_status,
                'error' => $error_message
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Stocke un enregistrement dans l'historique
     * 
     * @param array $history_data Données d'historique
     * @return bool Succès de l'opération
     */
    public function store_history_record( array $history_data ): bool {
        global $wpdb;
        
        try {
            $insert_data = [
                'parrain_subscription_id' => $history_data['parrain_subscription_id'],
                'filleul_order_id' => $history_data['filleul_order_id'],
                'action' => $history_data['action'],
                'price_before' => $history_data['price_before'],
                'price_after' => $history_data['price_after'],
                'reduction_amount' => $history_data['reduction_amount'],
                'execution_status' => $history_data['execution_status'],
                'execution_details' => wp_json_encode( $history_data['execution_details'] ?? [] ),
                'user_notified' => $history_data['user_notified'] ?? false,
                'created_at' => current_time( 'mysql' )
            ];
            
            $result = $wpdb->insert(
                $this->history_table,
                $insert_data,
                [ '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%s' ]
            );
            
            // Nettoyer l'historique si trop d'enregistrements
            $this->cleanup_history_for_subscription( $history_data['parrain_subscription_id'] );
            
            return $result !== false;
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'Erreur stockage historique', [
                'component' => 'ParrainPricingStorage',
                'error' => $e->getMessage(),
                'data' => $history_data
            ]);
            
            return false;
        }
    }
    
    /**
     * Récupère l'historique pour un abonnement
     * 
     * @param int $subscription_id ID de l'abonnement
     * @param int $limit Nombre maximum d'enregistrements
     * @return array Historique
     */
    public function get_pricing_history( int $subscription_id, int $limit = 20 ): array {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->history_table} 
             WHERE parrain_subscription_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $subscription_id,
            $limit
        );
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        // Décoder les détails JSON
        foreach ( $results as &$row ) {
            if ( isset( $row['execution_details'] ) ) {
                $row['execution_details'] = json_decode( $row['execution_details'], true ) ?: [];
            }
        }
        
        return $results;
    }
    
    /**
     * Récupère les modifications en attente de retry
     * 
     * @return array Liste des modifications à retenter
     */
    public function get_pending_retries(): array {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->schedule_table} 
             WHERE status = %s 
             AND retry_count > 0 
             AND retry_count < %d 
             ORDER BY updated_at ASC",
            ParrainPricingConstants::STATUS_PENDING,
            ParrainPricingConstants::RETRY_MAX_ATTEMPTS
        );
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        foreach ( $results as &$row ) {
            if ( isset( $row['metadata'] ) ) {
                $row['metadata'] = json_decode( $row['metadata'], true ) ?: [];
            }
        }
        
        return $results;
    }
    
    /**
     * Validation des données de programmation
     * 
     * @param array $data Données à valider
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    private function validate_scheduling_data( array $data ): void {
        $required_fields = [
            'parrain_subscription_id',
            'filleul_order_id',
            'action',
            'original_price',
            'new_price',
            'reduction_amount',
            'filleul_contribution',
            'reduction_percentage',
            'scheduled_date'
        ];
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[$field] ) ) {
                throw new \InvalidArgumentException( "Champ requis manquant : $field" );
            }
        }
        
        // Validation des types et valeurs
        if ( ! is_numeric( $data['parrain_subscription_id'] ) || $data['parrain_subscription_id'] <= 0 ) {
            throw new \InvalidArgumentException( 'ID abonnement parrain invalide' );
        }
        
        if ( ! ParrainPricingConstants::is_valid_action( $data['action'] ) ) {
            throw new \InvalidArgumentException( 'Action invalide : ' . $data['action'] );
        }
        
        if ( $data['original_price'] < 0 || $data['new_price'] < 0 ) {
            throw new \InvalidArgumentException( 'Prix négatifs non autorisés' );
        }
    }
    
    /**
     * Nettoie l'historique ancien pour un abonnement
     * 
     * @param int $subscription_id ID de l'abonnement
     */
    private function cleanup_history_for_subscription( int $subscription_id ): void {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->history_table} WHERE parrain_subscription_id = %d",
                $subscription_id
            )
        );
        
        if ( $count > ParrainPricingConstants::MAX_HISTORY_RECORDS_PER_SUBSCRIPTION ) {
            $excess = $count - ParrainPricingConstants::MAX_HISTORY_RECORDS_PER_SUBSCRIPTION;
            
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->history_table} 
                     WHERE parrain_subscription_id = %d 
                     ORDER BY created_at ASC 
                     LIMIT %d",
                    $subscription_id,
                    $excess
                )
            );
        }
    }
} 