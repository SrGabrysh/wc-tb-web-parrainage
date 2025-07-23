<?php

namespace TBWeb\WCParrainage\ParrainPricing\Migration;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Migration pour le système de réduction automatique
 * 
 * Crée et met à jour les tables de base de données
 * Gestion des versions et migrations sécurisées
 * 
 * @since 2.0.0
 */
class ParrainPricingMigration {
    
    /** @var string Version actuelle de la DB */
    const DB_VERSION = '2.0.0';
    
    /** @var string Clé d'option pour la version DB */
    const DB_VERSION_OPTION = 'wc_tb_parrainage_db_version';
    
    /**
     * Exécute la migration si nécessaire
     */
    public function migrate(): void {
        $current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
        
        // Pas de migration nécessaire si même version
        if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
            return;
        }
        
        try {
            // Exécuter les migrations nécessaires
            if ( version_compare( $current_version, '2.0.0', '<' ) ) {
                $this->migrate_to_2_0_0();
            }
            
            // Mettre à jour la version DB
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
            
            // Log de succès
            if ( class_exists( 'TBWeb\WCParrainage\Logger' ) ) {
                $logger = new \TBWeb\WCParrainage\Logger();
                $logger->info( 'Migration base de données réussie', [
                    'component' => 'ParrainPricingMigration',
                    'from_version' => $current_version,
                    'to_version' => self::DB_VERSION
                ]);
            }
            
        } catch ( \Exception $e ) {
            // En cas d'erreur, ne pas mettre à jour la version
            if ( class_exists( 'TBWeb\WCParrainage\Logger' ) ) {
                $logger = new \TBWeb\WCParrainage\Logger();
                $logger->error( 'Erreur migration base de données', [
                    'component' => 'ParrainPricingMigration',
                    'error' => $e->getMessage(),
                    'from_version' => $current_version,
                    'target_version' => self::DB_VERSION
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Migration vers la version 2.0.0
     * 
     * Crée les tables pour le système de réduction automatique
     */
    private function migrate_to_2_0_0(): void {
        global $wpdb;
        
        // Inclure les fonctions WordPress nécessaires
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        // Créer la table de programmation (SSOT)
        $this->create_pricing_schedule_table();
        
        // Créer la table d'historique (audit trail)
        $this->create_pricing_history_table();
        
        // Vérifier que les tables ont bien été créées
        $this->verify_tables_creation();
    }
    
    /**
     * Crée la table de programmation des modifications de prix
     * 
     * Table principale SSOT pour les réductions programmées
     */
    private function create_pricing_schedule_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            parrain_subscription_id bigint(20) NOT NULL COMMENT 'ID abonnement du parrain',
            filleul_order_id bigint(20) NOT NULL COMMENT 'ID commande du filleul déclencheur',
            action enum('apply_reduction', 'remove_reduction') NOT NULL COMMENT 'Type d\'action',
            
            -- Données de tarification (éviter magic numbers)
            original_price decimal(10,2) NOT NULL COMMENT 'Prix HT original du parrain',
            new_price decimal(10,2) NOT NULL COMMENT 'Nouveau prix HT calculé',
            reduction_amount decimal(10,2) NOT NULL COMMENT 'Montant de la réduction',
            filleul_contribution decimal(10,2) NOT NULL COMMENT 'Prix HT du filleul',
            reduction_percentage decimal(5,2) NOT NULL COMMENT 'Pourcentage appliqué',
            
            -- Planification et état
            scheduled_date datetime NOT NULL COMMENT 'Date programmée',
            applied_date datetime NULL COMMENT 'Date effective',
            status enum('pending', 'applied', 'failed', 'cancelled') DEFAULT 'pending',
            retry_count tinyint(3) DEFAULT 0 COMMENT 'Nombre de tentatives',
            
            -- Métadonnées et traçabilité
            metadata json COMMENT 'Contexte supplémentaire',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY unique_active_pricing (parrain_subscription_id, status) 
                COMMENT 'Un seul pricing actif par parrain (SSOT)',
            KEY idx_parrain_subscription (parrain_subscription_id),
            KEY idx_scheduled_date (scheduled_date),
            KEY idx_status_retry (status, retry_count),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='SSOT - Programmation modifications prix parrain';";
        
        dbDelta( $sql );
        
        // Vérifier la création
        if ( ! $this->table_exists( $table_name ) ) {
            throw new \Exception( "Échec création table $table_name" );
        }
    }
    
    /**
     * Crée la table d'historique des modifications
     * 
     * Table immuable pour traçabilité complète
     */
    private function create_pricing_history_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tb_parrainage_pricing_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            parrain_subscription_id bigint(20) NOT NULL,
            filleul_order_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            
            -- État avant/après (traçabilité)
            price_before decimal(10,2) NOT NULL,
            price_after decimal(10,2) NOT NULL,
            reduction_amount decimal(10,2) NOT NULL,
            
            -- Contexte d'exécution
            execution_status enum('success', 'failed', 'cancelled') NOT NULL,
            execution_details json COMMENT 'Détails techniques',
            user_notified boolean DEFAULT FALSE COMMENT 'Notification envoyée',
            
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY idx_parrain_history (parrain_subscription_id, created_at),
            KEY idx_execution_status (execution_status),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Audit trail - Historique modifications prix';";
        
        dbDelta( $sql );
        
        // Vérifier la création
        if ( ! $this->table_exists( $table_name ) ) {
            throw new \Exception( "Échec création table $table_name" );
        }
    }
    
    /**
     * Vérifie si une table existe
     * 
     * @param string $table_name Nom de la table
     * @return bool True si existe
     */
    private function table_exists( string $table_name ): bool {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        );
        
        return (bool) $wpdb->get_var( $query );
    }
    
    /**
     * Vérifie que toutes les tables ont été créées
     * 
     * @throws \Exception Si une table manque
     */
    private function verify_tables_creation(): void {
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'tb_parrainage_pricing_schedule',
            $wpdb->prefix . 'tb_parrainage_pricing_history'
        ];
        
        foreach ( $required_tables as $table ) {
            if ( ! $this->table_exists( $table ) ) {
                throw new \Exception( "Table manquante après migration : $table" );
            }
        }
    }
    
    /**
     * Nettoie les données de migration en cas d'erreur
     * 
     * Méthode de rollback pour maintenir la cohérence
     */
    public function rollback(): void {
        global $wpdb;
        
        try {
            // Supprimer les tables créées
            $tables = [
                $wpdb->prefix . 'tb_parrainage_pricing_schedule',
                $wpdb->prefix . 'tb_parrainage_pricing_history'
            ];
            
            foreach ( $tables as $table ) {
                $wpdb->query( "DROP TABLE IF EXISTS $table" );
            }
            
            // Remettre la version DB à 0
            delete_option( self::DB_VERSION_OPTION );
            
            if ( class_exists( 'TBWeb\WCParrainage\Logger' ) ) {
                $logger = new \TBWeb\WCParrainage\Logger();
                $logger->info( 'Rollback migration effectué', [
                    'component' => 'ParrainPricingMigration',
                    'tables_dropped' => $tables
                ]);
            }
            
        } catch ( \Exception $e ) {
            if ( class_exists( 'TBWeb\WCParrainage\Logger' ) ) {
                $logger = new \TBWeb\WCParrainage\Logger();
                $logger->error( 'Erreur lors du rollback', [
                    'component' => 'ParrainPricingMigration',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Retourne les informations de statut de migration
     * 
     * @return array Informations de statut
     */
    public function get_migration_status(): array {
        global $wpdb;
        
        $current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
        $target_version = self::DB_VERSION;
        
        $tables_status = [];
        $required_tables = [
            'pricing_schedule' => $wpdb->prefix . 'tb_parrainage_pricing_schedule',
            'pricing_history' => $wpdb->prefix . 'tb_parrainage_pricing_history'
        ];
        
        foreach ( $required_tables as $key => $table ) {
            $tables_status[$key] = [
                'name' => $table,
                'exists' => $this->table_exists( $table ),
                'row_count' => $this->table_exists( $table ) 
                    ? $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) 
                    : 0
            ];
        }
        
        return [
            'current_version' => $current_version,
            'target_version' => $target_version,
            'is_up_to_date' => version_compare( $current_version, $target_version, '>=' ),
            'needs_migration' => version_compare( $current_version, $target_version, '<' ),
            'tables' => $tables_status
        ];
    }
    
    /**
     * Vérifie l'intégrité des données après migration
     * 
     * @return array Rapport d'intégrité
     */
    public function check_data_integrity(): array {
        global $wpdb;
        
        $issues = [];
        
        // Vérifier les contraintes de la table schedule
        $schedule_table = $wpdb->prefix . 'tb_parrainage_pricing_schedule';
        if ( $this->table_exists( $schedule_table ) ) {
            
            // Vérifier qu'il n'y a pas plusieurs pending pour le même abonnement
            $duplicate_pending = $wpdb->get_var(
                "SELECT COUNT(*) FROM (
                    SELECT parrain_subscription_id, COUNT(*) as cnt 
                    FROM $schedule_table 
                    WHERE status = 'pending' 
                    GROUP BY parrain_subscription_id 
                    HAVING cnt > 1
                ) as duplicates"
            );
            
            if ( $duplicate_pending > 0 ) {
                $issues[] = "Abonnements avec plusieurs réductions pending : $duplicate_pending";
            }
            
            // Vérifier la cohérence des prix
            $invalid_prices = $wpdb->get_var(
                "SELECT COUNT(*) FROM $schedule_table 
                 WHERE original_price < 0 OR new_price < 0 OR reduction_amount < 0"
            );
            
            if ( $invalid_prices > 0 ) {
                $issues[] = "Enregistrements avec prix négatifs : $invalid_prices";
            }
        }
        
        return [
            'is_valid' => empty( $issues ),
            'issues' => $issues,
            'checked_at' => current_time( 'mysql' )
        ];
    }
} 