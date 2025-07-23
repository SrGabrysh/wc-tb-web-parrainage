<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParrainageValidator {
    
    // Constantes (éviter magic numbers)
    const DATE_FORMAT = 'Y-m-d';
    const MAX_SEARCH_LENGTH = 100;
    const ALLOWED_STATUSES = ['active', 'suspended', 'cancelled', 'expired', 'on-hold', 'pending', 'pending-cancel'];
    const ALLOWED_ORDER_BY = ['parrain_nom', 'date_parrainage', 'montant', 'statut'];
    const ALLOWED_ORDER = ['ASC', 'DESC'];
    const MAX_PER_PAGE = 200;
    const MIN_PER_PAGE = 10;
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Valider les filtres de recherche
     */
    public function validate_filters( $filters ) {
        $validated = array();
        
        // Date de début
        if ( ! empty( $filters['date_from'] ) ) {
            if ( $this->validate_date( $filters['date_from'] ) ) {
                $validated['date_from'] = sanitize_text_field( $filters['date_from'] );
            } else {
                $this->logger->warning( 
                    'Date de début invalide ignorée', 
                    array( 'date' => $filters['date_from'] ),
                    'parrainage-validator'
                );
            }
        }
        
        // Date de fin
        if ( ! empty( $filters['date_to'] ) ) {
            if ( $this->validate_date( $filters['date_to'] ) ) {
                $validated['date_to'] = sanitize_text_field( $filters['date_to'] );
            } else {
                $this->logger->warning( 
                    'Date de fin invalide ignorée', 
                    array( 'date' => $filters['date_to'] ),
                    'parrainage-validator'
                );
            }
        }
        
        // Validation de la cohérence des dates
        if ( ! empty( $validated['date_from'] ) && ! empty( $validated['date_to'] ) ) {
            if ( ! $this->validate_date_range( $validated['date_from'], $validated['date_to'] ) ) {
                unset( $validated['date_from'], $validated['date_to'] );
                $this->logger->warning( 
                    'Plage de dates incohérente ignorée', 
                    array( 
                        'date_from' => $filters['date_from'], 
                        'date_to' => $filters['date_to'] 
                    ),
                    'parrainage-validator'
                );
            }
        }
        
        // Recherche parrain
        if ( ! empty( $filters['parrain_search'] ) ) {
            $search = $this->sanitize_search_term( $filters['parrain_search'] );
            if ( ! empty( $search ) ) {
                $validated['parrain_search'] = $search;
            }
        }
        
        // ID produit
        if ( ! empty( $filters['product_id'] ) ) {
            $product_id = absint( $filters['product_id'] );
            if ( $product_id > 0 ) {
                $validated['product_id'] = $product_id;
            }
        }
        
        // Statut abonnement
        if ( ! empty( $filters['subscription_status'] ) ) {
            if ( $this->is_valid_status( $filters['subscription_status'] ) ) {
                $validated['subscription_status'] = sanitize_text_field( $filters['subscription_status'] );
            } else {
                $this->logger->warning( 
                    'Statut abonnement invalide ignoré', 
                    array( 'status' => $filters['subscription_status'] ),
                    'parrainage-validator'
                );
            }
        }
        
        return $validated;
    }
    
    /**
     * Valider les paramètres de pagination
     */
    public function validate_pagination( $pagination ) {
        $validated = array();
        
        // Page courante
        $page = isset( $pagination['page'] ) ? absint( $pagination['page'] ) : 1;
        $validated['page'] = max( 1, $page );
        
        // Nombre par page
        $per_page = isset( $pagination['per_page'] ) ? absint( $pagination['per_page'] ) : WC_TB_PARRAINAGE_ADMIN_PER_PAGE;
        $validated['per_page'] = max( self::MIN_PER_PAGE, min( self::MAX_PER_PAGE, $per_page ) );
        
        // Tri
        $order_by = isset( $pagination['order_by'] ) ? sanitize_text_field( $pagination['order_by'] ) : 'parrain_nom';
        if ( in_array( $order_by, self::ALLOWED_ORDER_BY, true ) ) {
            $validated['order_by'] = $order_by;
        } else {
            $validated['order_by'] = 'parrain_nom';
        }
        
        // Ordre
        $order = isset( $pagination['order'] ) ? strtoupper( sanitize_text_field( $pagination['order'] ) ) : 'ASC';
        if ( in_array( $order, self::ALLOWED_ORDER, true ) ) {
            $validated['order'] = $order;
        } else {
            $validated['order'] = 'ASC';
        }
        
        return $validated;
    }
    
    /**
     * Sanitiser un terme de recherche
     */
    public function sanitize_search_term( $term ) {
        $term = sanitize_text_field( wp_unslash( $term ) );
        
        // Limiter la longueur
        if ( strlen( $term ) > self::MAX_SEARCH_LENGTH ) {
            $term = substr( $term, 0, self::MAX_SEARCH_LENGTH );
        }
        
        // Supprimer les caractères indésirables pour la recherche SQL
        $term = preg_replace( '/[%_]/', '', $term );
        
        return trim( $term );
    }
    
    /**
     * Valider une plage de dates
     */
    public function validate_date_range( $date_from, $date_to ) {
        $from = strtotime( $date_from );
        $to = strtotime( $date_to );
        
        if ( $from === false || $to === false ) {
            return false;
        }
        
        // Date de début doit être antérieure à date de fin
        return $from <= $to;
    }
    
    /**
     * Valider le format d'une date
     */
    private function validate_date( $date ) {
        $parsed = \DateTime::createFromFormat( self::DATE_FORMAT, $date );
        return $parsed && $parsed->format( self::DATE_FORMAT ) === $date;
    }
    
    /**
     * Vérifier si un statut est valide
     */
    private function is_valid_status( $status ) {
        return in_array( $status, self::ALLOWED_STATUSES, true );
    }
    
    /**
     * Valider les paramètres d'export
     */
    public function validate_export_params( $params ) {
        $validated = array();
        
        // Format d'export
        $format = isset( $params['format'] ) ? sanitize_text_field( $params['format'] ) : 'csv';
        if ( in_array( $format, array( 'csv', 'excel' ), true ) ) {
            $validated['format'] = $format;
        } else {
            $validated['format'] = 'csv';
        }
        
        // Limiter le nombre d'enregistrements à exporter
        $limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : WC_TB_PARRAINAGE_MAX_EXPORT;
        $validated['limit'] = min( $limit, WC_TB_PARRAINAGE_MAX_EXPORT );
        
        // Inclure les filtres validés
        if ( ! empty( $params['filters'] ) ) {
            $validated['filters'] = $this->validate_filters( $params['filters'] );
        }
        
        return $validated;
    }
    
    /**
     * Valider les paramètres d'édition inline
     */
    public function validate_inline_edit( $params ) {
        if ( ! wp_verify_nonce( $params['nonce'] ?? '', 'tb_parrainage_inline_edit' ) ) {
            return new \WP_Error( 'invalid_nonce', 'Token de sécurité invalide' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'insufficient_permissions', 'Permissions insuffisantes' );
        }
        
        $order_id = absint( $params['order_id'] ?? 0 );
        if ( $order_id <= 0 ) {
            return new \WP_Error( 'invalid_order_id', 'ID de commande invalide' );
        }
        
        $new_avantage = sanitize_text_field( wp_unslash( $params['avantage'] ?? '' ) );
        if ( empty( $new_avantage ) ) {
            return new \WP_Error( 'empty_avantage', 'L\'avantage ne peut pas être vide' );
        }
        
        if ( strlen( $new_avantage ) > 200 ) {
            return new \WP_Error( 'avantage_too_long', 'L\'avantage est trop long (200 caractères max)' );
        }
        
        return array(
            'order_id' => $order_id,
            'avantage' => $new_avantage
        );
    }
} 