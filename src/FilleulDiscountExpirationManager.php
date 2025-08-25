<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire d'expiration des remises filleul après 12 mensualités
 * 
 * Responsabilité unique : Gérer la fin de remise des filleuls après 12 facturation
 * Principe SRP : Séparation claire fin de remise vs autres fonctionnalités
 * Principe OCP : Extensible pour nouvelles règles d'expiration
 * 
 * @since 2.11.0
 */
class FilleulDiscountExpirationManager {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'filleul-discount-expiration';
    
    /**
     * Métadonnées pour le tracking
     */
    const META_PRIX_STANDARD_HISTORIQUE = '_filleul_prix_standard_historique';
    const META_DATE_PREMIERE_FACTURATION = '_filleul_date_premiere_facturation';
    const META_FACTURATION_COUNT = '_filleul_facturation_count';
    const META_REMISE_EXPIREE = '_filleul_remise_expiree';
    const META_DATE_EXPIRATION_REMISE = '_filleul_date_expiration_remise';
    
    /**
     * Hook CRON pour vérification quotidienne
     */
    const CRON_HOOK = 'tb_parrainage_check_filleul_expiration';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        
        $this->logger->info(
            'FilleulDiscountExpirationManager initialisé avec succès',
            array(
                'version' => '2.11.0',
                'timestamp' => current_time( 'Y-m-d H:i:s' )
            ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Initialisation des hooks WordPress
     * 
     * @return void
     */
    public function init(): void {
        
        // Hook pour stocker le prix standard lors de la souscription
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'stocker_prix_standard_filleul' ), 15, 1 );
        
        // Hook pour tracker les facturations des filleuls
        add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'tracker_facturation_filleul' ), 10, 1 );
        
        // Hook pour vérification quotidienne via CRON
        add_action( self::CRON_HOOK, array( $this, 'verifier_expirations_filleuls' ) );
        
        // Programmer le CRON si pas déjà fait
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
        
        $this->logger->info(
            'FilleulDiscountExpirationManager hooks enregistrés avec succès',
            array(
                'hooks_registered' => array(
                    'woocommerce_checkout_order_processed',
                    'woocommerce_subscription_renewal_payment_complete',
                    self::CRON_HOOK
                ),
                'cron_scheduled' => wp_next_scheduled( self::CRON_HOOK )
            ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Stocker le prix standard du produit au moment de la souscription du filleul
     * 
     * ÉTAPE 1 : Sauvegarde prix de référence pour retour après 12 mois
     * 
     * @param int $order_id ID de la commande
     * @return void
     */
    public function stocker_prix_standard_filleul( int $order_id ): void {
        
        // Vérifier si c'est une commande avec code parrain (= filleul)
        $code_parrain = get_post_meta( $order_id, '_billing_parrain_code', true );
        if ( empty( $code_parrain ) ) {
            return; // Pas un filleul
        }
        
        // Vérifier si déjà traité
        if ( get_post_meta( $order_id, self::META_PRIX_STANDARD_HISTORIQUE, true ) ) {
            return; // Déjà stocké
        }
        
        try {
            
            // Récupérer l'abonnement associé à cette commande
            $subscriptions = wcs_get_subscriptions_for_order( $order_id );
            if ( empty( $subscriptions ) ) {
                $this->logger->warning(
                    'Aucun abonnement trouvé pour commande filleul',
                    array( 'order_id' => $order_id ),
                    self::LOG_CHANNEL
                );
                return;
            }
            
            $subscription = reset( $subscriptions ); // Prendre le premier abonnement
            $subscription_id = $subscription->get_id();
            
            // Récupérer le prix standard de la configuration produit ACTUELLE
            $prix_standard = $this->get_prix_standard_from_config( $order_id );
            
            if ( $prix_standard <= 0 ) {
                // Utiliser le prix actuel de l'abonnement comme fallback
                $prix_standard = floatval( $subscription->get_total() );
                
                $this->logger->warning(
                    'Prix standard non configuré, utilisation prix abonnement actuel',
                    array( 
                        'order_id' => $order_id,
                        'subscription_id' => $subscription_id,
                        'prix_fallback' => $prix_standard
                    ),
                    self::LOG_CHANNEL
                );
            }
            
            // Stocker dans la commande ET l'abonnement pour double sécurité
            update_post_meta( $order_id, self::META_PRIX_STANDARD_HISTORIQUE, $prix_standard );
            $subscription->update_meta_data( self::META_PRIX_STANDARD_HISTORIQUE, $prix_standard );
            $subscription->update_meta_data( self::META_DATE_PREMIERE_FACTURATION, current_time( 'mysql' ) );
            $subscription->update_meta_data( self::META_FACTURATION_COUNT, 0 ); // Première facturation pas encore passée
            
            $subscription->save();
            
            $this->logger->info(
                'Prix standard filleul stocké avec succès',
                array(
                    'order_id' => $order_id,
                    'subscription_id' => $subscription_id,
                    'prix_standard_stocke' => $prix_standard,
                    'code_parrain' => $code_parrain
                ),
                self::LOG_CHANNEL
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur stockage prix standard filleul',
                array(
                    'order_id' => $order_id,
                    'error' => $e->getMessage()
                ),
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Tracker les facturations des filleuls pour compter les mensualités
     * 
     * ÉTAPE 2 : Décompte des 12 mensualités
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement filleul
     * @return void
     */
    public function tracker_facturation_filleul( \WC_Subscription $subscription ): void {
        
        if ( ! $subscription ) {
            return;
        }
        
        $subscription_id = $subscription->get_id();
        
        // Vérifier si c'est un filleul (a un code parrain)
        $code_parrain = $this->get_code_parrain_from_subscription( $subscription );
        if ( empty( $code_parrain ) ) {
            return; // Pas un filleul
        }
        
        // Vérifier si la remise n'a pas déjà expiré
        $remise_expiree = $subscription->get_meta( self::META_REMISE_EXPIREE );
        if ( $remise_expiree === 'yes' ) {
            return; // Remise déjà expirée
        }
        
        try {
            
            // Récupérer le compteur actuel
            $count_actuel = intval( $subscription->get_meta( self::META_FACTURATION_COUNT ) );
            $nouveau_count = $count_actuel + 1;
            
            // Mettre à jour le compteur
            $subscription->update_meta_data( self::META_FACTURATION_COUNT, $nouveau_count );
            $subscription->save();
            
            $this->logger->info(
                'Facturation filleul trackée',
                array(
                    'subscription_id' => $subscription_id,
                    'facturation_count' => $nouveau_count,
                    'code_parrain' => $code_parrain
                ),
                self::LOG_CHANNEL
            );
            
            // Vérifier si on a atteint 12 facturations
            if ( $nouveau_count >= 12 ) {
                $this->expirer_remise_filleul( $subscription, $nouveau_count );
            }
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur tracking facturation filleul',
                array(
                    'subscription_id' => $subscription_id,
                    'error' => $e->getMessage()
                ),
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Expirer la remise du filleul et restaurer le prix standard
     * 
     * ÉTAPE 3 : Application du prix standard au 13ème mois
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement filleul
     * @param int $facturation_count Nombre de facturations
     * @return void
     */
    private function expirer_remise_filleul( \WC_Subscription $subscription, int $facturation_count ): void {
        
        $subscription_id = $subscription->get_id();
        
        try {
            
            // Récupérer le prix standard historique
            $prix_standard = floatval( $subscription->get_meta( self::META_PRIX_STANDARD_HISTORIQUE ) );
            
            if ( $prix_standard <= 0 ) {
                // Fallback : utiliser configuration actuelle
                $prix_standard = $this->get_prix_standard_from_subscription( $subscription );
                
                $this->logger->warning(
                    'Prix standard historique introuvable, utilisation configuration actuelle',
                    array(
                        'subscription_id' => $subscription_id,
                        'prix_standard_fallback' => $prix_standard
                    ),
                    self::LOG_CHANNEL
                );
            }
            
            if ( $prix_standard <= 0 ) {
                throw new \Exception( 'Prix standard introuvable pour expiration remise' );
            }
            
            // Sauvegarder prix actuel avant changement
            $prix_avant_expiration = floatval( $subscription->get_total() );
            
            // Appliquer le prix standard
            $this->update_subscription_price( $subscription, $prix_standard );
            
            // Marquer comme expiré
            $subscription->update_meta_data( self::META_REMISE_EXPIREE, 'yes' );
            $subscription->update_meta_data( self::META_DATE_EXPIRATION_REMISE, current_time( 'mysql' ) );
            
            $subscription->save();
            
            // Ajouter note à l'abonnement
            $subscription->add_order_note(
                sprintf(
                    'Remise filleul expirée après %d facturations. Prix modifié : %.2f€ → %.2f€',
                    $facturation_count,
                    $prix_avant_expiration,
                    $prix_standard
                )
            );
            
            $this->logger->info(
                'Remise filleul expirée avec succès',
                array(
                    'subscription_id' => $subscription_id,
                    'facturation_count' => $facturation_count,
                    'prix_avant' => $prix_avant_expiration,
                    'prix_apres' => $prix_standard,
                    'date_expiration' => current_time( 'mysql' )
                ),
                self::LOG_CHANNEL
            );
            
            // Déclencher webhook si configuré
            $this->trigger_expiration_webhook( $subscription, $prix_avant_expiration, $prix_standard );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur expiration remise filleul',
                array(
                    'subscription_id' => $subscription_id,
                    'facturation_count' => $facturation_count,
                    'error' => $e->getMessage()
                ),
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Vérification quotidienne des expirations via CRON
     * 
     * ÉTAPE 4 : Surveillance automatique quotidienne
     * 
     * @return void
     */
    public function verifier_expirations_filleuls(): void {
        
        $this->logger->info(
            'Démarrage vérification quotidienne expirations filleuls',
            array( 'timestamp' => current_time( 'mysql' ) ),
            self::LOG_CHANNEL
        );
        
        try {
            
            // Récupérer tous les abonnements filleuls actifs non expirés
            $subscriptions = wcs_get_subscriptions( array(
                'subscription_status' => array( 'active' ),
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => self::META_PRIX_STANDARD_HISTORIQUE,
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => self::META_REMISE_EXPIREE,
                        'value' => 'yes',
                        'compare' => '!='
                    )
                )
            ) );
            
            $total_verifies = 0;
            $total_expires = 0;
            
            foreach ( $subscriptions as $subscription ) {
                
                $subscription_id = $subscription->get_id();
                $facturation_count = intval( $subscription->get_meta( self::META_FACTURATION_COUNT ) );
                
                $total_verifies++;
                
                // Vérifier si doit expirer
                if ( $facturation_count >= 12 ) {
                    $this->expirer_remise_filleul( $subscription, $facturation_count );
                    $total_expires++;
                }
            }
            
            $this->logger->info(
                'Vérification quotidienne terminée',
                array(
                    'total_subscriptions_verifiees' => $total_verifies,
                    'total_remises_expirees' => $total_expires
                ),
                self::LOG_CHANNEL
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur vérification quotidienne expirations',
                array( 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
        }
    }
    
    /**
     * Récupérer le prix standard depuis la configuration produit
     * 
     * @param int $order_id ID de la commande
     * @return float Prix standard ou 0 si non trouvé
     */
    private function get_prix_standard_from_config( int $order_id ): float {
        
        // Récupérer la configuration des produits
        $config = get_option( 'wc_tb_parrainage_products_config', array() );
        if ( empty( $config ) ) {
            return 0.0;
        }
        
        // Récupérer les produits de la commande
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return 0.0;
        }
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            if ( isset( $config[ $product_id ] ) && isset( $config[ $product_id ]['prix_standard'] ) ) {
                return floatval( $config[ $product_id ]['prix_standard'] );
            }
        }
        
        return 0.0;
    }
    
    /**
     * Récupérer le prix standard depuis la configuration pour un abonnement
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @return float Prix standard ou 0 si non trouvé
     */
    private function get_prix_standard_from_subscription( \WC_Subscription $subscription ): float {
        
        // Récupérer la configuration des produits
        $config = get_option( 'wc_tb_parrainage_products_config', array() );
        if ( empty( $config ) ) {
            return 0.0;
        }
        
        foreach ( $subscription->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            if ( isset( $config[ $product_id ] ) && isset( $config[ $product_id ]['prix_standard'] ) ) {
                return floatval( $config[ $product_id ]['prix_standard'] );
            }
        }
        
        return 0.0;
    }
    
    /**
     * Récupérer le code parrain depuis un abonnement
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @return string Code parrain ou vide
     */
    private function get_code_parrain_from_subscription( \WC_Subscription $subscription ): string {
        
        // Chercher dans l'abonnement lui-même
        $code = $subscription->get_meta( '_billing_parrain_code' );
        if ( ! empty( $code ) ) {
            return $code;
        }
        
        // Chercher dans la commande parente
        $parent_order = $subscription->get_parent();
        if ( $parent_order ) {
            $code = $parent_order->get_meta( '_billing_parrain_code' );
            if ( ! empty( $code ) ) {
                return $code;
            }
        }
        
        return '';
    }
    
    /**
     * Mettre à jour le prix d'un abonnement
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @param float $new_price Nouveau prix
     * @return void
     */
    private function update_subscription_price( \WC_Subscription $subscription, float $new_price ): void {
        
        // Mettre à jour tous les articles de l'abonnement
        foreach ( $subscription->get_items() as $item ) {
            $item->set_subtotal( $new_price );
            $item->set_total( $new_price );
            $item->save();
        }
        
        // Recalculer les totaux
        $subscription->calculate_totals();
        
        // NOUVEAU v2.11.0 : Force synchronisation _order_total (comme v2.10.0)
        $subscription->update_meta_data( '_order_total', $subscription->get_total() );
        $subscription->save();
    }
    
    /**
     * Déclencher webhook d'expiration de remise
     * 
     * @param \WC_Subscription $subscription Instance de l'abonnement
     * @param float $prix_avant Prix avant expiration
     * @param float $prix_apres Prix après expiration
     * @return void
     */
    private function trigger_expiration_webhook( \WC_Subscription $subscription, float $prix_avant, float $prix_apres ): void {
        
        try {
            
            $webhook_data = array(
                'event' => 'filleul_discount_expired',
                'subscription_id' => $subscription->get_id(),
                'customer_id' => $subscription->get_customer_id(),
                'prix_avant_expiration' => $prix_avant,
                'prix_apres_expiration' => $prix_apres,
                'date_expiration' => current_time( 'mysql' ),
                'facturation_count' => intval( $subscription->get_meta( self::META_FACTURATION_COUNT ) ),
                'code_parrain' => $this->get_code_parrain_from_subscription( $subscription )
            );
            
            // Hook WordPress pour déclencher webhooks
            do_action( 'tb_parrainage_filleul_discount_expired', $webhook_data );
            
            $this->logger->info(
                'Webhook expiration remise filleul déclenché',
                $webhook_data,
                self::LOG_CHANNEL
            );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur déclenchement webhook expiration',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage()
                ),
                self::LOG_CHANNEL
            );
        }
    }
}
