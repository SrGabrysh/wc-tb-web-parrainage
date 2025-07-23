<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de validation d'accès aux fonctionnalités de parrainage côté client
 * 
 * Responsabilité unique : Validation de l'accès aux fonctionnalités de parrainage
 * pour les utilisateurs connectés avec abonnements WooCommerce Subscriptions actifs
 * 
 * @package TBWeb\WCParrainage
 * @since 1.3.0
 */
class MyAccountAccessValidator {
    
    // Constantes pour les types d'erreur (éviter magic numbers)
    const ERROR_NO_SUBSCRIPTION = 'no_subscription';
    const ERROR_INACTIVE_SUBSCRIPTION = 'inactive_subscription';
    const ERROR_NOT_LOGGED_IN = 'not_logged_in';
    
    // Statuts d'abonnement considérés comme actifs
    const ACTIVE_SUBSCRIPTION_STATUSES = ['active', 'pending-cancel'];
    
    /**
     * @var Logger Instance du système de logs
     */
    private $logger;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du système de logs
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Vérifie si un utilisateur peut accéder aux fonctionnalités de parrainage
     * 
     * @param int|null $user_id ID de l'utilisateur (null pour utilisateur connecté)
     * @return bool True si l'accès est autorisé, false sinon
     */
    public function user_can_access_parrainages( $user_id = null ) {
        // Utiliser l'utilisateur connecté si aucun ID fourni
        if ( $user_id === null ) {
            $user_id = \get_current_user_id();
        }
        
        // Vérifier si l'utilisateur est connecté
        if ( ! $user_id ) {
            $this->log_access_attempt( $user_id, false, self::ERROR_NOT_LOGGED_IN );
            return false;
        }
        
        // Vérifier si WooCommerce Subscriptions est actif
        if ( ! \class_exists( 'WC_Subscriptions' ) ) {
            $this->logger->error( 'WooCommerce Subscriptions requis pour l\'accès aux parrainages', array(
                'user_id' => $user_id
            ) );
            return false;
        }
        
        // Récupérer l'abonnement actif de l'utilisateur
        $subscription = $this->get_user_active_subscription( $user_id );
        
        if ( ! $subscription ) {
            $this->log_access_attempt( $user_id, false, self::ERROR_NO_SUBSCRIPTION );
            return false;
        }
        
        // Vérifier si l'abonnement est actif
        if ( ! $this->is_subscription_active( $subscription ) ) {
            $this->log_access_attempt( $user_id, false, self::ERROR_INACTIVE_SUBSCRIPTION );
            return false;
        }
        
        // Accès autorisé
        $this->log_access_attempt( $user_id, true );
        return true;
    }
    
    /**
     * Récupère l'abonnement actif d'un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @return \WC_Subscription|null L'abonnement actif ou null si aucun
     */
    public function get_user_active_subscription( $user_id ) {
        if ( ! \function_exists( 'wcs_get_users_subscriptions' ) ) {
            return null;
        }
        
        // Récupérer tous les abonnements de l'utilisateur
        $subscriptions = \wcs_get_users_subscriptions( $user_id );
        
        if ( empty( $subscriptions ) ) {
            return null;
        }
        
        // Chercher le premier abonnement actif
        foreach ( $subscriptions as $subscription ) {
            if ( $this->is_subscription_active( $subscription ) ) {
                return $subscription;
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si un abonnement est considéré comme actif
     * 
     * @param \WC_Subscription $subscription L'abonnement à vérifier
     * @return bool True si l'abonnement est actif, false sinon
     */
    public function is_subscription_active( $subscription ) {
        if ( ! $subscription || ! method_exists( $subscription, 'get_status' ) ) {
            return false;
        }
        
        $status = $subscription->get_status();
        
        return in_array( $status, self::ACTIVE_SUBSCRIPTION_STATUSES, true );
    }
    
    /**
     * Gère le refus d'accès selon le type d'erreur
     * 
     * @param string $error_type Type d'erreur (constante ERROR_*)
     * @return void
     */
    public function handle_access_denied( $error_type ) {
        switch ( $error_type ) {
            case self::ERROR_NOT_LOGGED_IN:
                $message = \__( 'Vous devez être connecté pour accéder à vos parrainages.', 'wc-tb-web-parrainage' );
                break;
                
            case self::ERROR_NO_SUBSCRIPTION:
                $message = \__( 'Vous devez avoir un abonnement pour accéder aux fonctionnalités de parrainage.', 'wc-tb-web-parrainage' );
                break;
                
            case self::ERROR_INACTIVE_SUBSCRIPTION:
                $message = \__( 'Votre abonnement doit être actif pour accéder aux fonctionnalités de parrainage.', 'wc-tb-web-parrainage' );
                break;
                
            default:
                $message = \__( 'Accès non autorisé aux fonctionnalités de parrainage.', 'wc-tb-web-parrainage' );
        }
        
        // Rediriger vers la page Mon Compte avec message d'erreur
        \wc_add_notice( $message, 'error' );
        \wp_safe_redirect( \wc_get_page_permalink( 'myaccount' ) );
        exit;
    }
    
    /**
     * Enregistre une tentative d'accès dans les logs
     * 
     * @param int|null $user_id ID de l'utilisateur
     * @param bool $result Résultat de la validation d'accès
     * @param string|null $error Type d'erreur si échec
     * @return void
     */
    private function log_access_attempt( $user_id, $result, $error = null ) {
        $message = $result ? 'Accès autorisé aux parrainages' : 'Accès refusé aux parrainages';
        $level = $result ? 'info' : 'warning';
        
        $context = array(
            'user_id' => $user_id,
            'result' => $result ? 'success' : 'denied',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
        
        if ( $error ) {
            $context['error_type'] = $error;
        }
        
        if ( $level === 'info' ) {
            $this->logger->info( $message, $context );
        } else {
            $this->logger->warning( $message, $context );
        }
    }
} 