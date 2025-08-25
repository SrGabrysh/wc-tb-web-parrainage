<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubscriptionPricingManager {
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    public function init() {
        // Hook pour calculer les dates PENDANT la création de commande (avant webhook)
        \add_action( 'woocommerce_checkout_order_processed', array( $this, 'calculer_dates_simple' ), 10, 1 );
    }
    
    /**
     * Calcul ultra-simple des dates de tarification
     */
    public function calculer_dates_simple( $order_id ) {
        // Vérification de base
        if ( empty( $order_id ) ) {
            return;
        }
        
        // Vérifier si déjà traité
        if ( \get_post_meta( $order_id, '_parrainage_date_fin_remise', true ) ) {
            return; // Déjà calculé
        }
        
        // Vérifier si la commande contient un code parrain
        $code_parrain = \get_post_meta( $order_id, '_billing_parrain_code', true );
        if ( empty( $code_parrain ) ) {
            return;
        }
        
        // Calcul ultra-simple
        $date_debut = \current_time( 'Y-m-d' );
        $timestamp = \strtotime( $date_debut . ' +12 months +2 days' );
        $date_fin = \date( 'Y-m-d', $timestamp );
        
        // Stocker les métadonnées
        \update_post_meta( $order_id, '_parrainage_date_fin_remise', $date_fin );
        \update_post_meta( $order_id, '_parrainage_date_debut', $date_debut );
        \update_post_meta( $order_id, '_parrainage_jours_marge', 2 );
        
        // Log simple
        $this->logger->info( 
            \sprintf( 'Dates tarification calculées simplement pour commande #%d', $order_id ),
            array( 'order_id' => $order_id, 'date_debut' => $date_debut, 'date_fin' => $date_fin ),
            'subscription-pricing'
        );
    }
    
    /**
     * Obtenir les informations de tarification parrainage pour une commande
     */
    public function obtenir_infos_tarification_parrainage( $order_id ) {
        // Récupérer les dates stockées
        $date_fin_remise = \get_post_meta( $order_id, '_parrainage_date_fin_remise', true );
        
        if ( empty( $date_fin_remise ) ) {
            return null;
        }
        
        $date_debut = \get_post_meta( $order_id, '_parrainage_date_debut', true );
        $jours_marge = \get_post_meta( $order_id, '_parrainage_jours_marge', true );
        
        // Formatage simple DD-MM-YYYY
        $date_fin_formatted = '';
        $date_debut_formatted = '';
        
        if ( ! empty( $date_fin_remise ) ) {
            $timestamp_fin = \strtotime( $date_fin_remise );
            $date_fin_formatted = \date( 'd-m-Y', $timestamp_fin );
        }
        
        if ( ! empty( $date_debut ) ) {
            $timestamp_debut = \strtotime( $date_debut );
            $date_debut_formatted = \date( 'd-m-Y', $timestamp_debut );
        }
        
        return array(
            'date_fin_remise_parrainage' => $date_fin_remise,
            'date_debut_parrainage' => $date_debut,
            'date_fin_remise_parrainage_formatted' => $date_fin_formatted,
            'date_debut_parrainage_formatted' => $date_debut_formatted,
            'jours_marge_parrainage' => (int) $jours_marge,
            'periode_remise_mois' => 12
        );
    }
} 