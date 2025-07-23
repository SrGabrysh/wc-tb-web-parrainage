<?php

namespace TBWeb\WCParrainage\ParrainPricing\Constants;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Constantes centralisées pour le système de réduction automatique du parrain
 * 
 * Centralise toutes les valeurs métier pour éviter les magic numbers
 * et servir de Single Source of Truth (SSOT) pour la configuration
 * 
 * @since 2.0.0
 */
class ParrainPricingConstants {
    
    // ==========================================
    // RÈGLES MÉTIER DE CALCUL
    // ==========================================
    
    /** @var int Pourcentage de contribution du filleul (25% du prix HT du filleul) */
    const FILLEUL_CONTRIBUTION_PERCENTAGE = 25;
    
    /** @var float Prix minimum du parrain (peut être gratuit) */
    const MIN_PARRAIN_PRICE = 0.00;
    
    /** @var int Nombre de décimales pour les calculs monétaires */
    const PRICING_CALCULATION_PRECISION = 2;
    
    // ==========================================
    // GESTION DE LA RÉSILIENCE
    // ==========================================
    
    /** @var int Nombre maximum de tentatives en cas d'échec */
    const RETRY_MAX_ATTEMPTS = 3;
    
    /** @var array Délais entre tentatives en secondes [1min, 5min, 15min] */
    const RETRY_DELAY_SECONDS = [60, 300, 900];
    
    // ==========================================
    // PERFORMANCE ET CACHE
    // ==========================================
    
    /** @var int Durée du cache en secondes (5 minutes) */
    const CACHE_DURATION_SECONDS = 300;
    
    /** @var int Timeout pour les opérations critiques en secondes */
    const OPERATION_TIMEOUT_SECONDS = 30;
    
    // ==========================================
    // STATUTS DE PROGRAMMATION
    // ==========================================
    
    /** @var string Statut : en attente d'application */
    const STATUS_PENDING = 'pending';
    
    /** @var string Statut : appliqué avec succès */
    const STATUS_APPLIED = 'applied';
    
    /** @var string Statut : échec d'application */
    const STATUS_FAILED = 'failed';
    
    /** @var string Statut : annulé (par exemple si abonnement résilié) */
    const STATUS_CANCELLED = 'cancelled';
    
    // ==========================================
    // ACTIONS DE PRICING
    // ==========================================
    
    /** @var string Action : appliquer une réduction */
    const ACTION_APPLY_REDUCTION = 'apply_reduction';
    
    /** @var string Action : supprimer une réduction */
    const ACTION_REMOVE_REDUCTION = 'remove_reduction';
    
    // ==========================================
    // CONFIGURATION NOTIFICATIONS
    // ==========================================
    
    /** @var bool Notifications activées par défaut */
    const NOTIFICATIONS_ENABLED_DEFAULT = true;
    
    /** @var string Template email pour réduction appliquée */
    const EMAIL_TEMPLATE_REDUCTION_APPLIED = 'parrain_reduction_applied';
    
    /** @var string Template email pour réduction supprimée */
    const EMAIL_TEMPLATE_REDUCTION_REMOVED = 'parrain_reduction_removed';
    
    // ==========================================
    // LIMITES ET SÉCURITÉ
    // ==========================================
    
    /** @var int Nombre maximum d'opérations par batch */
    const MAX_BATCH_SIZE = 50;
    
    /** @var int Délai maximum entre la programmation et l'application (en jours) */
    const MAX_PENDING_DAYS = 90;
    
    /** @var int Nombre maximum d'enregistrements d'historique par abonnement */
    const MAX_HISTORY_RECORDS_PER_SUBSCRIPTION = 100;
    
    // ==========================================
    // MÉTRIQUES ET MONITORING
    // ==========================================
    
    /** @var float Taux de succès minimum acceptable (99%) */
    const MIN_SUCCESS_RATE_PERCENT = 99.0;
    
    /** @var int Durée de rétention des métriques en jours */
    const METRICS_RETENTION_DAYS = 365;
    
    /** @var int Seuil d'alerte pour les tâches en attente */
    const PENDING_TASKS_ALERT_THRESHOLD = 100;
    
    // ==========================================
    // MÉTHODES UTILITAIRES
    // ==========================================
    
    /**
     * Retourne tous les statuts valides
     * 
     * @return array Liste des statuts
     */
    public static function get_valid_statuses(): array {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPLIED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED
        ];
    }
    
    /**
     * Retourne toutes les actions valides
     * 
     * @return array Liste des actions
     */
    public static function get_valid_actions(): array {
        return [
            self::ACTION_APPLY_REDUCTION,
            self::ACTION_REMOVE_REDUCTION
        ];
    }
    
    /**
     * Retourne les délais de retry configurés
     * 
     * @return array Délais en secondes
     */
    public static function get_retry_delays(): array {
        return self::RETRY_DELAY_SECONDS;
    }
    
    /**
     * Vérifie si un statut est valide
     * 
     * @param string $status Statut à vérifier
     * @return bool True si valide
     */
    public static function is_valid_status( string $status ): bool {
        return in_array( $status, self::get_valid_statuses(), true );
    }
    
    /**
     * Vérifie si une action est valide
     * 
     * @param string $action Action à vérifier  
     * @return bool True si valide
     */
    public static function is_valid_action( string $action ): bool {
        return in_array( $action, self::get_valid_actions(), true );
    }
    
    /**
     * Retourne la configuration par défaut du système
     * 
     * @return array Configuration par défaut
     */
    public static function get_default_config(): array {
        return [
            'filleul_contribution_percentage' => self::FILLEUL_CONTRIBUTION_PERCENTAGE,
            'min_parrain_price' => self::MIN_PARRAIN_PRICE,
            'calculation_precision' => self::PRICING_CALCULATION_PRECISION,
            'retry_max_attempts' => self::RETRY_MAX_ATTEMPTS,
            'retry_delays' => self::RETRY_DELAY_SECONDS,
            'cache_duration' => self::CACHE_DURATION_SECONDS,
            'notifications_enabled' => self::NOTIFICATIONS_ENABLED_DEFAULT,
            'max_batch_size' => self::MAX_BATCH_SIZE,
            'max_pending_days' => self::MAX_PENDING_DAYS
        ];
    }
} 