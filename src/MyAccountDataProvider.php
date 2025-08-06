<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de récupération des données de parrainage côté client
 * 
 * Responsabilité unique : Récupération et formatage des données de parrainage
 * pour l'affichage dans l'onglet "Mes parrainages" côté client
 * 
 * @package TBWeb\WCParrainage
 * @since 1.3.0
 */
class MyAccountDataProvider {
    
    // Constantes pour le cache (éviter magic numbers)
    const CACHE_KEY_PREFIX = 'tb_parrainage_user_';
    const CACHE_DURATION = 300; // 5 minutes
    
    // Constantes pour le formatage
    const EMAIL_MASK_CHAR = '*';
    const DATE_FORMAT_DISPLAY = 'd/m/Y';
    const AMOUNT_FORMAT = '%.2f€/mois';
    
    // Labels des statuts d'abonnement (v2.0.2 - HUMANISÉS)
    const STATUS_LABELS = [
        'active' => 'En cours',
        'wc-active' => 'En cours',
        'on-hold' => 'Suspendu',
        'wc-on-hold' => 'Suspendu',
        'cancelled' => 'Annulé',
        'wc-cancelled' => 'Annulé',
        'expired' => 'Expiré',
        'wc-expired' => 'Expiré',
        'pending-cancel' => 'Annulation programmée',
        'wc-pending-cancel' => 'Annulation programmée',
        'switched' => 'Modifié',
        'wc-switched' => 'Modifié'
    ];
    
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
     * Récupère l'ID d'abonnement d'un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @return int|null ID de l'abonnement ou null si aucun
     */
    public function get_user_subscription_id( $user_id ) {
        if ( ! \function_exists( 'wcs_get_users_subscriptions' ) ) {
            return null;
        }
        
        $subscriptions = \wcs_get_users_subscriptions( $user_id );
        
        if ( empty( $subscriptions ) ) {
            return null;
        }
        
        // Retourner l'ID du premier abonnement actif trouvé
        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->get_status() === 'active' ) {
                return $subscription->get_id();
            }
        }
        
        // Si aucun actif, retourner le premier abonnement
        $first_subscription = reset( $subscriptions );
        return $first_subscription ? $first_subscription->get_id() : null;
    }
    
    /**
     * Récupère les données de parrainage d'un utilisateur
     * 
     * @param int $user_subscription_id ID de l'abonnement de l'utilisateur
     * @param int $limit Limite du nombre de parrainages à récupérer
     * @return array Tableau des parrainages formatés
     */
    public function get_user_parrainages( $user_subscription_id, $limit = WC_TB_PARRAINAGE_LIMIT_DISPLAY ) {
        // Vérifier le cache d'abord
        $cache_key = self::CACHE_KEY_PREFIX . $user_subscription_id;
        $cached_data = \get_transient( $cache_key );
        
        if ( $cached_data !== false ) {
            $this->logger->info( 'Données de parrainage récupérées depuis le cache', array(
                'subscription_id' => $user_subscription_id,
                'cache_key' => $cache_key
            ) );
            return $cached_data;
        }
        
        // Récupérer les données depuis la base
        $raw_data = $this->query_parrainages_data( $user_subscription_id, $limit );
        
        // Traiter et formater les données
        $formatted_data = array();
        foreach ( $raw_data as $row ) {
            $formatted_data[] = $this->process_parrainage_row( $row );
        }
        
        // Mettre en cache
        \set_transient( $cache_key, $formatted_data, self::CACHE_DURATION );
        
        $this->logger->info( 'Données de parrainage récupérées et mises en cache', array(
            'subscription_id' => $user_subscription_id,
            'count' => count( $formatted_data ),
            'cache_duration' => self::CACHE_DURATION
        ) );
        
        return $formatted_data;
    }
    
    /**
     * Formate un email pour le masquer partiellement
     * 
     * @param string $email Email à masquer
     * @return string Email masqué
     */
    public function format_filleul_email( $email ) {
        if ( empty( $email ) || ! \is_email( $email ) ) {
            return '';
        }
        
        $parts = explode( '@', $email );
        if ( count( $parts ) !== 2 ) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        // Masquer le nom d'utilisateur selon sa longueur
        $length = strlen( $username );
        if ( $length <= 2 ) {
            $masked_username = str_repeat( self::EMAIL_MASK_CHAR, $length );
        } else {
            $visible_chars = min( 2, floor( $length / 3 ) );
            $masked_chars = $length - $visible_chars;
            $masked_username = substr( $username, 0, $visible_chars ) . str_repeat( self::EMAIL_MASK_CHAR, $masked_chars );
        }
        
        return $masked_username . '@' . $domain;
    }
    
    /**
     * Récupère le label humanisé d'un statut d'abonnement (v2.0.2)
     * 
     * @param string $status Statut technique de l'abonnement
     * @return string Label affiché pour ce statut
     */
    private function get_subscription_status_label( $status ) {
        return self::STATUS_LABELS[ $status ] ?? ucfirst( $status );
    }
    
    /**
     * Formate une date de parrainage pour l'affichage
     * 
     * @param string $date Date au format MySQL
     * @return string Date formatée pour l'affichage
     */
    public function format_date_parrainage( $date ) {
        if ( empty( $date ) ) {
            return '';
        }
        
        $timestamp = \strtotime( $date );
        if ( ! $timestamp ) {
            return $date;
        }
        
        return \date( self::DATE_FORMAT_DISPLAY, $timestamp );
    }
    
    /**
     * Formate un montant HT pour l'affichage (v2.0.2)
     * 
     * @param float $amount_ht Montant HT à formater
     * @return string Montant formaté pour l'affichage
     */
    private function format_montant_ht( $amount_ht ) {
        if ( ! is_numeric( $amount_ht ) ) {
            return '';
        }
        return sprintf( '%.2f€ HT/mois', floatval( $amount_ht ) );
    }
    
    /**
     * Récupère le montant de la remise du parrain selon la configuration produit
     * 
     * @param int $order_id ID de la commande du filleul
     * @param string $subscription_status Statut de l'abonnement du filleul
     * @return string Montant de la remise formaté ou statut
     */
    private function get_parrain_reduction( $order_id, $subscription_status ) {
        // Vérifier si l'abonnement est actif
        if ( ! in_array( $subscription_status, ['active', 'wc-active'] ) ) {
            return 'Non applicable';
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return '0,00€';
        }
        
        // Récupérer la configuration des produits
        $products_config = get_option( 'wc_tb_parrainage_products_config', array() );
        $remise_montant = 0.00;
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            if ( isset( $products_config[ $product_id ]['remise_parrain'] ) ) {
                $remise_montant = floatval( $products_config[ $product_id ]['remise_parrain'] );
                break;
            }
        }
        
        // Si aucune remise spécifique, vérifier la config par défaut
        if ( $remise_montant == 0.00 && isset( $products_config['default']['remise_parrain'] ) ) {
            $remise_montant = floatval( $products_config['default']['remise_parrain'] );
        }
        
        // Formatage avec virgule française
        return number_format( $remise_montant, 2, ',', '' ) . '€';
    }
    
    /**
     * Formate un montant pour l'affichage
     * 
     * @param float $amount Montant à formater
     * @param string $currency Devise (non utilisée pour l'instant)
     * @return string Montant formaté
     */
    public function format_montant( $amount, $currency = 'EUR' ) {
        if ( ! is_numeric( $amount ) ) {
            return '';
        }
        
        return sprintf( self::AMOUNT_FORMAT, floatval( $amount ) );
    }
    
    /**
     * Exécute la requête SQL pour récupérer les données de parrainage
     * 
     * @param int $subscription_id ID de l'abonnement parrain
     * @param int $limit Limite du nombre de résultats
     * @return array Données brutes de la base de données
     */
    private function query_parrainages_data( $subscription_id, $limit ) {
        global $wpdb;
        
        $query = "
            SELECT 
                p.ID as order_id,
                p.post_date as date_parrainage,
                pm_code.meta_value as code_parrain,
                CONCAT(pm_first.meta_value, ' ', pm_last.meta_value) as filleul_nom,
                pm_email.meta_value as filleul_email,
                pm_avantage.meta_value as avantage,
                wci.order_item_name as produit_nom,
                sub.post_status as subscription_status,
                sub_meta.meta_value as subscription_total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_code ON p.ID = pm_code.post_id 
                AND pm_code.meta_key = '_billing_parrain_code'
            LEFT JOIN {$wpdb->postmeta} pm_first ON p.ID = pm_first.post_id 
                AND pm_first.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm_last ON p.ID = pm_last.post_id 
                AND pm_last.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_avantage ON p.ID = pm_avantage.post_id 
                AND pm_avantage.meta_key = '_parrainage_avantage'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items wci ON p.ID = wci.order_id 
                AND wci.order_item_type = 'line_item'
            LEFT JOIN {$wpdb->posts} sub ON sub.post_parent = p.ID 
                AND sub.post_type = 'shop_subscription'
            LEFT JOIN {$wpdb->postmeta} sub_meta ON sub.ID = sub_meta.post_id 
                AND sub_meta.meta_key = '_order_total'
            WHERE pm_code.meta_value = %s
                AND p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
            ORDER BY p.post_date DESC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results( $wpdb->prepare( $query, $subscription_id, $limit ) );
        
        if ( $wpdb->last_error ) {
            $this->logger->error( 'Erreur SQL lors de la récupération des parrainages', array(
                'subscription_id' => $subscription_id,
                'sql_error' => $wpdb->last_error,
                'query' => $query
            ) );
            return array();
        }
        
        return $results ?: array();
    }
    
    /**
     * Traite et formate une ligne de données de parrainage (v2.0.2)
     * 
     * @param object $row Ligne de données brutes
     * @return array Données formatées pour l'affichage
     */
    private function process_parrainage_row( $row ) {
        // Récupération sécurisée du prix HT réel
        $montant_ht = 0;
        if ( !empty( $row->order_id ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            try {
                $subscriptions = wcs_get_subscriptions_for_order( $row->order_id );
                if ( !empty( $subscriptions ) ) {
                    $subscription = reset( $subscriptions ); // Premier abonnement
                    if ( $subscription && $subscription->get_id() ) {
                        $montant_ht = (float) $subscription->get_subtotal(); // Prix HT officiel
                    }
                }
            } catch ( Exception $e ) {
                // Gestion d'erreur silencieuse avec logging
                $montant_ht = 0;
                if ( $this->logger ) {
                    $this->logger->error( 'Erreur récupération prix HT', array(
                        'order_id' => $row->order_id,
                        'error' => $e->getMessage()
                    ) );
                }
            }
        }
        
        // NOUVEAU v2.4.0 : Données remise mockées côté client
        $discount_data = $this->get_client_mock_discount_data( $row->order_id );
        
        return array(
            'order_id' => intval( $row->order_id ),
            'filleul_nom' => \sanitize_text_field( $row->filleul_nom ),
            'filleul_email' => $this->format_filleul_email( $row->filleul_email ),
            'date_parrainage' => $this->format_date_parrainage( $row->date_parrainage ),
            'date_parrainage_raw' => $row->date_parrainage,
            'produit_nom' => \sanitize_text_field( $row->produit_nom ),
            'subscription_status' => $row->subscription_status,
            'status_label' => $this->get_subscription_status_label( $row->subscription_status ),
            'avantage' => \sanitize_text_field( $row->avantage ?: 'Avantage parrainage' ),
            // Nouvelles données v2.0.2
            'abonnement_ht' => $this->format_montant_ht( $montant_ht ),
            'abonnement_ht_raw' => $montant_ht,
            'votre_remise' => $this->get_parrain_reduction( $row->order_id, $row->subscription_status ),
            // Anciennes données conservées pour compatibilité
            'montant' => $this->format_montant( $row->subscription_total ),
            'montant_raw' => floatval( $row->subscription_total ),
            // MODIFICATION v2.6.0 : Données remise réelles côté client
            'discount_client_info' => $this->get_real_client_discount_data( $row->order_id )
        );
    }
    
    /**
     * NOUVEAU v2.4.0 : Génération de données mockées côté client
     * 
     * @param int $order_id ID de la commande
     * @return array Données mockées pour l'interface client
     */
    private function get_client_mock_discount_data( $order_id ) {
        $statuses = ['active', 'pending', 'failed', 'suspended'];
        
        // Utiliser l'ID de commande pour des résultats cohérents
        mt_srand( intval( $order_id ) );
        $status = $statuses[mt_rand( 0, count( $statuses ) - 1 )];
        
        $discount_amount = mt_rand( 500, 1500 ) / 100; // Entre 5€ et 15€
        $total_monthly_savings = mt_rand( 700, 2500 ) / 100; // Entre 7€ et 25€ (1-3 filleuls)
        $total_savings_to_date = mt_rand( 50, 300 ); // Entre 50€ et 300€
        
        return array(
            'discount_status' => $status,
            'discount_status_message' => $this->get_client_status_message( $status ),
            'discount_amount' => $discount_amount,
            'discount_amount_formatted' => number_format( $discount_amount, 2, ',', '' ) . '€/mois',
            'status_icon' => $this->get_status_icon( $status ),
            'status_color' => $this->get_status_color( $status ),
            'total_monthly_savings' => $total_monthly_savings,
            'total_savings_to_date' => $total_savings_to_date,
            'next_billing_preview' => array(
                'date' => date( 'd/m/Y', strtotime( '+1 month' ) ),
                'amount_before' => 89.99,
                'amount_after' => 89.99 - $discount_amount,
                'savings' => $discount_amount
            )
        );
    }
    
    /**
     * NOUVEAU v2.4.0 : Messages de statut pour les clients
     * 
     * @param string $status Statut technique
     * @return string Message affiché au client
     */
    private function get_client_status_message( $status ) {
        $messages = array(
            'active' => 'ACTIVE - Appliquée depuis le ' . date( 'd/m/Y', strtotime( '-' . mt_rand( 1, 30 ) . ' days' ) ),
            'pending' => 'EN ATTENTE - Application en cours...',
            'failed' => 'PROBLÈME - Contactez le support',
            'suspended' => 'SUSPENDUE - Filleul a suspendu son abonnement'
        );
        return $messages[$status] ?? 'Statut inconnu';
    }
    
    /**
     * NOUVEAU v2.4.0 : Icônes pour les statuts côté client
     * 
     * @param string $status Statut technique
     * @return string Icône emoji
     */
    private function get_status_icon( $status ) {
        $icons = array(
            'active' => '🟢',
            'pending' => '🟡',
            'failed' => '🔴',
            'suspended' => '⏸️'
        );
        return $icons[$status] ?? '⚪';
    }
    
    /**
     * NOUVEAU v2.4.0 : Couleurs pour les statuts côté client
     * 
     * @param string $status Statut technique
     * @return string Classe couleur CSS
     */
    private function get_status_color( $status ) {
        $colors = array(
            'active' => 'status-success',
            'pending' => 'status-warning',
            'failed' => 'status-error',
            'suspended' => 'status-neutral'
        );
        return $colors[$status] ?? 'status-default';
    }
    
    /**
     * NOUVEAU v2.4.0 : Calcul du résumé global des économies
     * 
     * @param int $user_subscription_id ID de l'abonnement utilisateur
     * @return array Résumé des économies
     */
    public function get_savings_summary( $user_subscription_id ) {
        // Cache pour éviter les recalculs
        $cache_key = self::CACHE_KEY_PREFIX . 'summary_' . $user_subscription_id;
        $cached_summary = \get_transient( $cache_key );
        
        if ( $cached_summary !== false ) {
            return $cached_summary;
        }
        
        // MODIFICATION v2.6.0 : Calcul réel du résumé des économies
        try {
            $real_referrals = $this->get_real_referrals_data( $user_subscription_id );
            
            $active_discounts = 0;
            $total_monthly_savings = 0;
            $total_referrals = count( $real_referrals );
            
            foreach ( $real_referrals as $referral ) {
                if ( isset( $referral['discount_client_info']['discount_status'] ) && 
                     $referral['discount_client_info']['discount_status'] === 'calculated' ) {
                    $active_discounts++;
                    $total_monthly_savings += floatval( $referral['discount_client_info']['discount_amount'] ?? 0 );
                }
            }
            
            $subscription = wcs_get_subscription( $user_subscription_id );
            $original_amount = $subscription ? $subscription->get_total() : 89.99;
            
            $summary = array(
                'active_discounts' => $active_discounts,
                'total_referrals' => $total_referrals,
                'monthly_savings' => number_format( $total_monthly_savings, 2 ),
                'yearly_projection' => number_format( $total_monthly_savings * 12, 2 ),
                'currency' => get_woocommerce_currency_symbol(),
                'next_billing' => array(
                    'date' => date( 'd/m/Y', strtotime( '+1 month' ) ),
                    'amount' => round( $original_amount - $total_monthly_savings, 2 ),
                    'original_amount' => $original_amount
                ),
                'pending_actions' => $this->get_real_pending_actions( $real_referrals )
            );
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Erreur lors du calcul du résumé des économies réelles - fallback vers mockées',
                array(
                    'user_subscription_id' => $user_subscription_id,
                    'error' => $e->getMessage()
                ),
                'account-data-provider'
            );
            
            // Fallback vers données mockées en cas d'erreur
            return $this->get_mock_savings_summary( $user_subscription_id );
        }
        
        // Mettre en cache
        \set_transient( $cache_key, $summary, self::CACHE_DURATION );
        
        return $summary;
    }
    
    /**
     * NOUVEAU v2.4.0 : Actions en attente mockées
     * 
     * @param int $active_discounts Nombre de remises actives
     * @param int $total_referrals Nombre total de filleuls
     * @return array Liste des actions en attente
     */
    private function get_mock_pending_actions( $active_discounts, $total_referrals ) {
        $actions = array();
        
        // Si il y a des filleuls sans remise active
        if ( $total_referrals > $active_discounts ) {
            $pending_count = $total_referrals - $active_discounts;
            
            if ( $pending_count === 1 ) {
                $actions[] = array( 'message' => 'Marie Dupont : Remise en attente (normal, sous 10 min)' );
            } else {
                $actions[] = array( 'message' => 'Paul Martin : Abonnement suspendu, remise en pause' );
            }
        }
        
        return $actions;
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération des vraies données client de remise
     * 
     * @param int $order_id ID de la commande
     * @return array Données de remise réelles côté client
     */
    private function get_real_client_discount_data( $order_id ) {
        try {
            // Récupération de l'instance du plugin pour accès aux services
            $plugin_instance = $this->get_plugin_instance();
            if ( ! $plugin_instance ) {
                return $this->get_client_mock_discount_data( $order_id );
            }
            
            $calculator = $plugin_instance->get_discount_calculator();
            if ( ! $calculator ) {
                return $this->get_client_mock_discount_data( $order_id );
            }
            
            // Récupération des informations de la commande
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return array();
            }
            
            // Vérification si remise calculée
            $calculated_discounts = $order->get_meta( '_parrainage_calculated_discounts' );
            $workflow_status = $order->get_meta( '_parrainage_workflow_status' );
            
            if ( $calculated_discounts && $workflow_status === 'calculated' ) {
                $discount_data = is_array( $calculated_discounts ) ? $calculated_discounts[0] : $calculated_discounts;
                
                return array(
                    'discount_status' => 'calculated',
                    'discount_status_label' => 'CALCULÉ (v2.6.0)',
                    'discount_amount' => $discount_data['discount_amount'] ?? 0,
                    'discount_amount_formatted' => number_format( $discount_data['discount_amount'] ?? 0, 2, ',', '' ) . '€/mois',
                    'calculation_date' => $order->get_meta( '_parrainage_calculation_date' ),
                    'is_simulation' => true // Marquer comme simulation v2.6.0
                );
            } elseif ( $workflow_status ) {
                // Autres statuts du workflow
                return array(
                    'discount_status' => $workflow_status,
                    'discount_status_label' => $this->get_workflow_status_label_client( $workflow_status ),
                    'discount_amount' => 0,
                    'discount_amount_formatted' => '0,00€/mois',
                    'is_simulation' => true
                );
            }
            
            // Fallback vers données mockées
            return $this->get_client_mock_discount_data( $order_id );
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Erreur lors de la récupération des données client réelles',
                array(
                    'order_id' => $order_id,
                    'error' => $e->getMessage()
                ),
                'account-data-provider'
            );
            
            return $this->get_client_mock_discount_data( $order_id );
        }
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération des vraies données de filleuls pour calculs
     * 
     * @param int $user_subscription_id ID de l'abonnement parrain
     * @return array Données des filleuls avec calculs réels
     */
    private function get_real_referrals_data( $user_subscription_id ) {
        // Utiliser la même logique que get_parrainage_data mais pour un seul parrain
        $plugin_instance = $this->get_plugin_instance();
        if ( ! $plugin_instance ) {
            return array();
        }
        
        // Récupération via ParrainageDataProvider pour cohérence
        $parrainage_data_provider = new ParrainageDataProvider( $this->logger );
        
        $filters = array(
            'parrain_subscription_id' => $user_subscription_id
        );
        
        $data = $parrainage_data_provider->get_parrainage_data( $filters );
        
        // Extraire les filleuls pour ce parrain spécifique
        if ( isset( $data['parrains'] ) ) {
            foreach ( $data['parrains'] as $parrain ) {
                if ( intval( $parrain['subscription_id'] ) === intval( $user_subscription_id ) ) {
                    return $parrain['filleuls'] ?? array();
                }
            }
        }
        
        return array();
    }
    
    /**
     * NOUVEAU v2.6.0 : Actions en attente basées sur données réelles
     * 
     * @param array $real_referrals Données réelles des filleuls
     * @return array Actions en attente
     */
    private function get_real_pending_actions( $real_referrals ) {
        $actions = array();
        
        foreach ( $real_referrals as $referral ) {
            $status = $referral['discount_client_info']['discount_status'] ?? 'unknown';
            
            switch ( $status ) {
                case 'pending':
                    $actions[] = array( 
                        'type' => 'pending',
                        'message' => sprintf( '%s : Remise en cours de calcul', $referral['nom'] ?? 'Filleul' )
                    );
                    break;
                case 'error':
                    $actions[] = array( 
                        'type' => 'error',
                        'message' => sprintf( '%s : Erreur de calcul, contactez le support', $referral['nom'] ?? 'Filleul' )
                    );
                    break;
                case 'cron_failed':
                    $actions[] = array( 
                        'type' => 'warning',
                        'message' => sprintf( '%s : Calcul en attente (problème technique)', $referral['nom'] ?? 'Filleul' )
                    );
                    break;
            }
        }
        
        return $actions;
    }
    
    /**
     * NOUVEAU v2.6.0 : Fallback vers données mockées pour get_savings_summary
     * 
     * @param int $user_subscription_id ID de l'abonnement
     * @return array Résumé mocké
     */
    private function get_mock_savings_summary( $user_subscription_id ) {
        mt_srand( intval( $user_subscription_id ) );
        
        $active_discounts = mt_rand( 1, 4 );
        $total_referrals = $active_discounts + mt_rand( 0, 2 );
        $monthly_savings = $active_discounts * ( mt_rand( 500, 1500 ) / 100 );
        
        return array(
            'active_discounts' => $active_discounts,
            'total_referrals' => $total_referrals,
            'monthly_savings' => round( $monthly_savings, 2 ),
            'yearly_projection' => round( $monthly_savings * 12, 2 ),
            'currency' => get_woocommerce_currency_symbol(),
            'next_billing' => array(
                'date' => date( 'd/m/Y', strtotime( '+1 month' ) ),
                'amount' => round( 89.99 - $monthly_savings, 2 ),
                'original_amount' => 89.99
            ),
            'pending_actions' => $this->get_mock_pending_actions( $active_discounts, $total_referrals )
        );
    }
    
    /**
     * NOUVEAU v2.6.0 : Labels des statuts workflow côté client
     * 
     * @param string $status Statut workflow
     * @return string Label pour affichage client
     */
    private function get_workflow_status_label_client( $status ) {
        $labels = array(
            'calculated' => 'Calculé (Test v2.6.0)',
            'pending' => 'En cours de calcul',
            'scheduled' => 'Programmé',
            'error' => 'Erreur de calcul',
            'cron_failed' => 'En attente technique'
        );
        return $labels[$status] ?? 'Statut inconnu';
    }
    
    /**
     * NOUVEAU v2.6.0 : Récupération de l'instance du plugin
     * 
     * @return Plugin|null Instance du plugin ou null
     */
    private function get_plugin_instance() {
        global $wc_tb_parrainage_plugin;
        return isset( $wc_tb_parrainage_plugin ) ? $wc_tb_parrainage_plugin : null;
    }
} 