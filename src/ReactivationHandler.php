<?php
/**
 * Gestionnaire de Réactivation des Remises Parrain
 * 
 * RESPONSABILITÉ UNIQUE : Traitement concret de la réactivation des remises parrain
 * - Restauration du prix avec remise
 * - Mise à jour des métadonnées d'abonnement
 * - Gestion de l'historique et traçabilité
 * 
 * @package TBWeb\WCParrainage
 * @version 2.8.2
 * @since 2.8.2
 * @author TB-Web
 */

namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe ReactivationHandler
 * 
 * Traite la réactivation concrète des remises parrain :
 * - Calcul et application du nouveau prix avec remise
 * - Mise à jour des métadonnées d'abonnement
 * - Ajout de notes d'historique
 * - Nettoyage des données de suspension
 */
class ReactivationHandler {

    /**
     * @var Logger Instance du logger pour traçabilité
     */
    private $logger;

    /**
     * @var SubscriptionDiscountManager Service de gestion des remises
     */
    private $subscription_discount_manager;

    /**
     * Canal de logs spécialisé pour traitement réactivation
     */
    const LOG_CHANNEL = 'reactivation-handler';

    /**
     * Constructeur avec injection de dépendances
     * 
     * @param Logger $logger Instance du logger
     * @param SubscriptionDiscountManager $subscription_discount_manager Service remises
     */
    public function __construct( $logger, $subscription_discount_manager ) {
        $this->logger = $logger;
        $this->subscription_discount_manager = $subscription_discount_manager;

        $this->logger->debug(
            'ReactivationHandler initialisé',
            array(
                'version' => '2.8.2',
                'workflow_step' => 'reactivation_handler_initialized'
            ),
            self::LOG_CHANNEL
        );
    }

    /**
     * Réactivation concrète de la remise parrain
     * 
     * PROCESSUS DE RÉACTIVATION :
     * 1. Récupération des données de suspension
     * 2. Calcul du nouveau prix avec remise restaurée
     * 3. Application du nouveau prix à l'abonnement
     * 4. Mise à jour des métadonnées de statut
     * 5. Ajout de note d'historique
     * 6. Nettoyage des données de suspension
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul réactivé
     * @param array $validation_details Détails de validation
     * @return array Résultat détaillé de la réactivation
     */
    public function reactivate_parrain_discount( $parrain_subscription_id, $filleul_subscription_id, $validation_details ) {
        $this->logger->info(
            'DÉMARRAGE réactivation concrète de la remise parrain',
            array(
                'parrain_subscription_id' => $parrain_subscription_id,
                'filleul_subscription_id' => $filleul_subscription_id,
                'workflow_step' => 'reactivation_handler_started'
            ),
            self::LOG_CHANNEL
        );

        try {
            // ÉTAPE 1 : Récupération de l'abonnement parrain
            if ( ! function_exists( 'wcs_get_subscription' ) ) {
                throw new \RuntimeException( 'WooCommerce Subscriptions non disponible' );
            }

            $parrain_subscription = wcs_get_subscription( $parrain_subscription_id );
            if ( ! $parrain_subscription ) {
                throw new \RuntimeException( "Abonnement parrain {$parrain_subscription_id} non trouvé" );
            }

            // ÉTAPE 2 : Récupération des données de suspension
            $suspended_discount_data = $validation_details['suspended_discount'];
            $original_discount_amount = $suspended_discount_data['original_discount_amount'];
            $original_price_before_suspension = $suspended_discount_data['original_price_before_suspension'];

            $this->logger->info(
                'Données de suspension récupérées',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'original_discount_amount' => $original_discount_amount,
                    'original_price_before_suspension' => $original_price_before_suspension,
                    'current_price' => $parrain_subscription->get_total(),
                    'workflow_step' => 'suspension_data_retrieved'
                ),
                self::LOG_CHANNEL
            );

            // ÉTAPE 3 : Calcul du nouveau prix avec remise
            $new_price_with_discount = $original_price_before_suspension - $original_discount_amount;

            // Sécurité : prix minimum de 0
            if ( $new_price_with_discount < 0 ) {
                $this->logger->warning(
                    'Prix calculé négatif - ajustement à 0',
                    array(
                        'parrain_subscription_id' => $parrain_subscription_id,
                        'calculated_price' => $new_price_with_discount,
                        'original_price' => $original_price_before_suspension,
                        'discount_amount' => $original_discount_amount,
                        'workflow_step' => 'negative_price_adjustment'
                    ),
                    self::LOG_CHANNEL
                );
                $new_price_with_discount = 0;
            }

            // ÉTAPE 4 : Application du nouveau prix
            $this->update_subscription_price( $parrain_subscription, $new_price_with_discount );

            $this->logger->info(
                'Prix de l\'abonnement parrain mis à jour avec remise réactivée',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'previous_price' => $suspended_discount_data['current_price'],
                    'new_price_with_discount' => $new_price_with_discount,
                    'discount_amount_restored' => $original_discount_amount,
                    'workflow_step' => 'subscription_price_updated'
                ),
                self::LOG_CHANNEL
            );

            // ÉTAPE 5 : Mise à jour des métadonnées de statut
            $this->update_reactivation_metadata( 
                $parrain_subscription, 
                $filleul_subscription_id,
                $original_discount_amount,
                $suspended_discount_data
            );

            // ÉTAPE 6 : Ajout de note d'historique
            $this->add_reactivation_note( 
                $parrain_subscription, 
                $filleul_subscription_id,
                $original_discount_amount,
                $new_price_with_discount
            );

            // ÉTAPE 7 : Nettoyage des données de suspension
            $this->cleanup_suspension_metadata( $parrain_subscription );

            // SUCCÈS COMPLET
            $this->logger->info(
                'RÉACTIVATION REMISE PARRAIN TERMINÉE AVEC SUCCÈS',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'discount_amount_restored' => $original_discount_amount,
                    'final_price' => $new_price_with_discount,
                    'workflow_step' => 'reactivation_handler_completed'
                ),
                self::LOG_CHANNEL
            );

            return array(
                'success' => true,
                'original_price_restored' => $original_price_before_suspension,
                'discount_reactivated' => $original_discount_amount,
                'final_price' => $new_price_with_discount,
                'details' => array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'reactivation_date' => current_time( 'Y-m-d H:i:s' )
                )
            );

        } catch ( \Exception $e ) {
            $this->logger->error(
                'ERREUR lors de la réactivation concrète',
                array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'workflow_step' => 'reactivation_handler_error'
                ),
                self::LOG_CHANNEL
            );

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'details' => array(
                    'parrain_subscription_id' => $parrain_subscription_id,
                    'filleul_subscription_id' => $filleul_subscription_id,
                    'error_context' => 'reactivation_handler'
                )
            );
        }
    }

    /**
     * Mise à jour du prix de l'abonnement
     * 
     * @param \WC_Subscription $subscription Abonnement à modifier
     * @param float $new_price Nouveau prix
     * @throws \RuntimeException Si la mise à jour échoue
     */
    private function update_subscription_price( $subscription, $new_price ) {
        try {
            // Mise à jour via SubscriptionDiscountManager existant
            $update_result = $this->subscription_discount_manager->update_subscription_total( 
                $subscription->get_id(), 
                $new_price 
            );

            if ( ! $update_result ) {
                throw new \RuntimeException( 'Échec mise à jour prix via SubscriptionDiscountManager' );
            }

            $this->logger->debug(
                'Prix abonnement mis à jour via SubscriptionDiscountManager',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'new_price' => $new_price,
                    'workflow_step' => 'price_updated_via_manager'
                ),
                self::LOG_CHANNEL
            );

        } catch ( \Exception $e ) {
            $this->logger->warning(
                'SubscriptionDiscountManager non disponible - mise à jour directe',
                array(
                    'subscription_id' => $subscription->get_id(),
                    'error' => $e->getMessage(),
                    'workflow_step' => 'fallback_direct_update'
                ),
                self::LOG_CHANNEL
            );

            // Fallback : mise à jour directe
            foreach ( $subscription->get_items() as $item ) {
                $item->set_total( $new_price );
                $item->save();
            }

            $subscription->calculate_totals();
            $subscription->save();
        }
    }

    /**
     * Mise à jour des métadonnées de réactivation
     * 
     * @param \WC_Subscription $parrain_subscription Abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param float $discount_amount Montant remise réactivée
     * @param array $suspended_discount_data Données de suspension
     */
    private function update_reactivation_metadata( $parrain_subscription, $filleul_subscription_id, $discount_amount, $suspended_discount_data ) {
        $reactivation_date = current_time( 'Y-m-d H:i:s' );

        // Marquer comme réactivé
        $parrain_subscription->update_meta_data( '_tb_parrainage_discount_status', 'reactivated' );
        $parrain_subscription->update_meta_data( '_tb_parrainage_reactivation_date', $reactivation_date );
        $parrain_subscription->update_meta_data( '_tb_parrainage_discount_amount', $discount_amount );
        
        // Conserver historique de suspension
        $parrain_subscription->update_meta_data( '_tb_parrainage_last_suspension_date', $suspended_discount_data['suspension_date'] );
        $parrain_subscription->update_meta_data( '_tb_parrainage_reactivated_from_filleul', $filleul_subscription_id );

        $parrain_subscription->save();

        $this->logger->debug(
            'Métadonnées de réactivation mises à jour',
            array(
                'parrain_subscription_id' => $parrain_subscription->get_id(),
                'reactivation_date' => $reactivation_date,
                'discount_amount' => $discount_amount,
                'filleul_subscription_id' => $filleul_subscription_id,
                'workflow_step' => 'reactivation_metadata_updated'
            ),
            self::LOG_CHANNEL
        );
    }

    /**
     * Ajout de note d'historique de réactivation
     * 
     * @param \WC_Subscription $parrain_subscription Abonnement parrain
     * @param int $filleul_subscription_id ID abonnement filleul
     * @param float $discount_amount Montant remise réactivée
     * @param float $new_price Nouveau prix final
     */
    private function add_reactivation_note( $parrain_subscription, $filleul_subscription_id, $discount_amount, $new_price ) {
        $note = sprintf(
            '🟢 [TB-Parrainage v2.8.2] Remise parrain RÉACTIVÉE automatiquement suite à la réactivation du filleul #%d. Remise de %s€/mois restaurée. Nouveau prix : %s€/mois.',
            $filleul_subscription_id,
            number_format( $discount_amount, 2, ',', ' ' ),
            number_format( $new_price, 2, ',', ' ' )
        );

        $parrain_subscription->add_order_note( $note );

        $this->logger->debug(
            'Note de réactivation ajoutée à l\'abonnement parrain',
            array(
                'parrain_subscription_id' => $parrain_subscription->get_id(),
                'note' => $note,
                'workflow_step' => 'reactivation_note_added'
            ),
            self::LOG_CHANNEL
        );
    }

    /**
     * Nettoyage des métadonnées de suspension
     * 
     * @param \WC_Subscription $parrain_subscription Abonnement parrain
     */
    private function cleanup_suspension_metadata( $parrain_subscription ) {
        // Supprimer les métadonnées temporaires de suspension
        $parrain_subscription->delete_meta_data( '_tb_parrainage_suspended_discount' );
        $parrain_subscription->delete_meta_data( '_tb_parrainage_suspension_date' );
        $parrain_subscription->delete_meta_data( '_tb_parrainage_original_price_before_suspension' );

        $parrain_subscription->save();

        $this->logger->debug(
            'Métadonnées de suspension nettoyées',
            array(
                'parrain_subscription_id' => $parrain_subscription->get_id(),
                'workflow_step' => 'suspension_metadata_cleaned'
            ),
            self::LOG_CHANNEL
        );
    }
}
