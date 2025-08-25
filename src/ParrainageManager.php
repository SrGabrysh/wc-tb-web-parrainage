<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParrainageManager {
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    public function init() {
        // Hooks pour le système de parrainage
        add_filter( 'woocommerce_checkout_fields', array( $this, 'ajouter_champ_parrain_checkout' ) );
        add_action( 'wp_head', array( $this, 'ajouter_style_description_parrain' ) );
        add_action( 'wp_ajax_valider_code_parrain', array( $this, 'ajax_valider_code_parrain' ) );
        add_action( 'wp_ajax_nopriv_valider_code_parrain', array( $this, 'ajax_valider_code_parrain' ) );
        add_action( 'wp_footer', array( $this, 'ajouter_validation_parrain_javascript' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'valider_champ_parrain_checkout' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'sauvegarder_champ_parrain_commande' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'afficher_parrain_admin_commande' ), 10, 1 );
    }
    
    /**
     * Configuration des messages de parrainage par produit
     * Récupère la configuration depuis la base de données (configurable via l'admin)
     */
    public function obtenir_configuration_messages_parrainage() {
        // Récupérer la configuration depuis la base de données
        $config_db = get_option( 'wc_tb_parrainage_products_config', array() );
        
        // Si aucune configuration en BDD, utiliser les valeurs par défaut
        if ( empty( $config_db ) ) {
            $config_db = $this->get_default_config_fallback();
        }
        
        // Permettre la surcharge via filtres pour les développeurs
        return apply_filters( 'tb_parrainage_messages_config', $config_db );
    }
    
    /**
     * Configuration par défaut (fallback si rien en BDD)
     */
    private function get_default_config_fallback() {
        return array(
            6713 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez d\'un mois gratuit supplémentaire',
                'avantage' => '1 mois gratuit supplémentaire'
            ),
            6524 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez d\'un mois gratuit supplémentaire',
                'avantage' => '1 mois gratuit supplémentaire'
            ),
            6519 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez d\'un mois gratuit supplémentaire',
                'avantage' => '1 mois gratuit supplémentaire'
            ),
            6354 => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓ - Vous bénéficierez de 10% de remise',
                'avantage' => '10% de remise'
            ),
            'default' => array(
                'description' => 'Vous êtes parrainé ? Saisissez votre code parrain à 4 chiffres, sans espace ni caractère spécial (exemple : 4896)',
                'message_validation' => 'Code parrain valide ✓',
                'avantage' => 'Avantage parrainage'
            )
        );
    }
    
    /**
     * Obtenir les produits du panier au checkout
     */
    public function obtenir_produits_panier_checkout() {
        $product_ids = array();
        
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product_ids[] = $cart_item['product_id'];
                
                // Inclure aussi les variations si présentes
                if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 ) {
                    $product_ids[] = $cart_item['variation_id'];
                }
            }
        }
        
        return array_unique( $product_ids );
    }
    
    /**
     * Vérifier si le panier contient des produits nécessitant un code parrain
     */
    public function panier_necessite_code_parrain() {
        $config = $this->obtenir_configuration_messages_parrainage();
        $product_ids = $this->obtenir_produits_panier_checkout();
        
        // Chercher si au moins un produit du panier est configuré (hors default)
        foreach ( $product_ids as $product_id ) {
            if ( isset( $config[ $product_id ] ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtenir le message de parrainage selon le produit
     */
    public function obtenir_message_parrainage( $type = 'description' ) {
        $config = $this->obtenir_configuration_messages_parrainage();
        $product_ids = $this->obtenir_produits_panier_checkout();
        
        // Chercher le premier produit qui a une configuration spécifique
        foreach ( $product_ids as $product_id ) {
            if ( isset( $config[ $product_id ] ) && isset( $config[ $product_id ][ $type ] ) ) {
                return wp_unslash( $config[ $product_id ][ $type ] );
            }
        }
        
        // Retourner le message par défaut
        return isset( $config['default'][ $type ] ) ? wp_unslash( $config['default'][ $type ] ) : '';
    }
    
    /**
     * Vérifier si un subscription_id existe
     */
    public function verifier_subscription_id_existe( $subscription_id ) {
        // Vérifier si WooCommerce Subscriptions est actif
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            return false;
        }
        
        // Utiliser l'API WooCommerce Subscriptions
        $subscription = wcs_get_subscription( $subscription_id );
        
        // Vérifier que l'abonnement existe et est actif
        if ( $subscription && $subscription->get_status() === 'active' ) {
            return $subscription;
        }
        
        return false;
    }
    
    /**
     * Obtenir les informations du parrain
     */
    public function obtenir_infos_parrain( $subscription_id ) {
        $subscription = $this->verifier_subscription_id_existe( $subscription_id );
        
        if ( ! $subscription ) {
            return false;
        }
        
        $user_id = $subscription->get_user_id();
        $infos_parrain = array(
            'subscription_id' => $subscription_id,
            'user_id' => $user_id,
            'email' => '',
            'nom' => '',
            'prenom' => ''
        );
        
        if ( $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if ( $user ) {
                $infos_parrain['email'] = $user->user_email;
                $infos_parrain['nom'] = get_user_meta( $user_id, 'last_name', true );
                $infos_parrain['prenom'] = get_user_meta( $user_id, 'first_name', true );
            }
        }
        
        return $infos_parrain;
    }
    
    /**
     * 1) Création du champ au checkout avec message dynamique (conditionnel)
     */
    public function ajouter_champ_parrain_checkout( $fields ) {
        // Vérifier si le panier contient des produits nécessitant un code parrain
        if ( ! $this->panier_necessite_code_parrain() ) {
            // Ne pas ajouter le champ si aucun produit configuré dans le panier
            return $fields;
        }
        
        // Obtenir le message dynamique selon le produit
        $description_dynamique = $this->obtenir_message_parrainage( 'description' );
        
        $fields['billing']['billing_parrain_code'] = array(
            'label'       => __( 'Code parrain', 'wc-tb-web-parrainage' ),
            'placeholder' => 'Ex : 4896',
            'required'    => true, // Obligatoire si affiché pour un produit configuré
            'class'       => array( 'form-row-wide' ),
            'clear'       => true,
            'priority'    => 131,
            'description' => $description_dynamique,
        );
        return $fields;
    }
    
    /**
     * 1.2) Style CSS pour la description du code parrain
     */
    public function ajouter_style_description_parrain() {
        if ( is_checkout() ) {
            ?>
            <style>
                .woocommerce-checkout #billing_parrain_code_field .description {
                    display: block;
                    margin-bottom: 4px;
                    clear: both;
                    line-height: 2em;
                    font-size: 0.85em;
                    color: #fff !important;       /* Même colorimétrie que le SIRET */
                }
            </style>
            <?php
        }
    }
    
    /**
     * 1.3) Endpoint AJAX pour validation temps réel
     */
    public function ajax_valider_code_parrain() {
        // Vérification du nonce pour la sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'], 'validation_parrain_nonce' ) ) {
            $this->logger->warning( 
                'Tentative de validation code parrain avec nonce invalide',
                array( 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
                'parrainage'
            );
            wp_send_json_error( array( 'message' => 'Erreur de sécurité' ) );
        }
        
        $code = sanitize_text_field( $_POST['code'] );
        $code = preg_replace( '/\s+/', '', $code );
        
        // Validation du format
        if ( empty( $code ) ) {
            wp_send_json_success( array( 
                'valid' => true, 
                'message' => '',
                'parrain_info' => null
            ) );
        }
        
        if ( ! preg_match( '/^\d{4}$/', $code ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Code parrain invalide (4 chiffres attendus)', 'wc-tb-web-parrainage' )
            ) );
        }
        
        // Validation de l'existence en BDD
        $subscription_id = intval( $code );
        $subscription = $this->verifier_subscription_id_existe( $subscription_id );
        
        if ( ! $subscription ) {
            $this->logger->info( 
                sprintf( 'Code parrain inexistant tenté: %s', $code ),
                array( 'code' => $code, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
                'parrainage'
            );
            wp_send_json_error( array( 
                'message' => __( 'Code parrain inexistant', 'wc-tb-web-parrainage' )
            ) );
        }
        
        // Validation anti-auto-parrainage
        $current_user_id = get_current_user_id();
        if ( $current_user_id ) {
            $subscription_user_id = $subscription->get_user_id();
            
            if ( $current_user_id === $subscription_user_id ) {
                $this->logger->warning( 
                    sprintf( 'Tentative auto-parrainage user %d avec code %s', $current_user_id, $code ),
                    array( 'user_id' => $current_user_id, 'code' => $code ),
                    'parrainage'
                );
                wp_send_json_error( array( 
                    'message' => __( 'Vous ne pouvez pas utiliser votre propre code parrain', 'wc-tb-web-parrainage' )
                ) );
            }
        }
        
        // Récupérer les infos du parrain pour affichage
        $infos_parrain = $this->obtenir_infos_parrain( $subscription_id );
        
        // Message de validation dynamique selon le produit
        $message_validation = $this->obtenir_message_parrainage( 'message_validation' );
        
        $this->logger->info( 
            sprintf( 'Code parrain validé avec succès: %s', $code ),
            array( 
                'code' => $code,
                'parrain_info' => $infos_parrain,
                'user_id' => $current_user_id
            ),
            'parrainage'
        );
        
        wp_send_json_success( array(
            'valid' => true,
            'message' => $message_validation,
            'parrain_info' => $infos_parrain
        ) );
    }
    
    /**
     * 1.4) Validation JavaScript temps réel avec AJAX
     */
    public function ajouter_validation_parrain_javascript() {
        if ( is_checkout() && ! is_wc_endpoint_url() ) {
            ?>
            <script type="text/javascript">
            jQuery(function($){
                
                var validation_timeout;
                var derniere_validation = '';
                
                /*  Validation : vide OU exactement 4 chiffres  */
                function validerFormatParrain(code) {
                    code = code.replace(/\s+/g, '');
                    return code === '' || /^\d{4}$/.test(code);
                }

                function afficherErreurParrain(msg) {
                    $('#billing_parrain_code_field')
                        .removeClass('woocommerce-validated')
                        .addClass('woocommerce-invalid');
                    $('#billing_parrain_code_field .woocommerce-input-wrapper')
                        .find('.error-parrain, .success-parrain, .info-parrain, .loading-parrain').remove()
                        .end()
                        .append('<span class="error-parrain" style="color:#e2401c;font-size:0.875em;margin-top:5px;display:block;">'+msg+'</span>');
                }

                function afficherSuccesParrain(msg, infos) {
                    $('#billing_parrain_code_field')
                        .removeClass('woocommerce-invalid')
                        .addClass('woocommerce-validated');
                    $('#billing_parrain_code_field .woocommerce-input-wrapper')
                        .find('.error-parrain, .success-parrain, .info-parrain, .loading-parrain').remove()
                        .end()
                        .append('<span class="success-parrain" style="color:#46b450;font-size:0.875em;margin-top:5px;display:block;font-weight:bold;">'+msg+'</span>');
                    
                    // Afficher les infos du parrain si disponibles
                    if (infos && infos.prenom && infos.nom) {
                        $('#billing_parrain_code_field .woocommerce-input-wrapper')
                            .append('<span class="info-parrain" style="color:#666;font-size:0.8em;margin-top:3px;display:block;">Parrain : '+infos.prenom+' '+infos.nom+'</span>');
                    }
                }

                function afficherChargementParrain() {
                    $('#billing_parrain_code_field')
                        .removeClass('woocommerce-invalid woocommerce-validated');
                    $('#billing_parrain_code_field .woocommerce-input-wrapper')
                        .find('.error-parrain, .success-parrain, .info-parrain, .loading-parrain').remove()
                        .end()
                        .append('<span class="loading-parrain" style="color:#0073aa;font-size:0.875em;margin-top:5px;display:block;">Vérification en cours...</span>');
                }

                function supprimerMessagesParrain() {
                    $('#billing_parrain_code_field')
                        .removeClass('woocommerce-invalid woocommerce-validated');
                    $('#billing_parrain_code_field .error-parrain, #billing_parrain_code_field .success-parrain, #billing_parrain_code_field .info-parrain, #billing_parrain_code_field .loading-parrain').remove();
                }

                function validerParrainAjax(code) {
                    code = code.replace(/\s+/g, '');
                    
                    // Éviter les appels répétés pour le même code
                    if (code === derniere_validation) {
                        return;
                    }
                    derniere_validation = code;
                    
                    if (code === '') {
                        supprimerMessagesParrain();
                        return;
                    }
                    
                    // Validation format d'abord
                    if (!validerFormatParrain(code)) {
                        afficherErreurParrain('Code parrain invalide (4 chiffres attendus)');
                        return;
                    }
                    
                    afficherChargementParrain();
                    
                    $.ajax({
                        url: wc_checkout_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'valider_code_parrain',
                            code: code,
                            nonce: '<?php echo wp_create_nonce( 'validation_parrain_nonce' ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                afficherSuccesParrain(response.data.message, response.data.parrain_info);
                            } else {
                                afficherErreurParrain(response.data.message);
                            }
                        },
                        error: function() {
                            afficherErreurParrain('Erreur de validation du code parrain');
                        }
                    });
                }

                /*  Validation en direct avec délai  */
                $(document).on('input', '#billing_parrain_code', function(){
                    var code = $(this).val();
                    
                    clearTimeout(validation_timeout);
                    
                    // Validation immédiate si le champ est vide
                    if (code === '') {
                        supprimerMessagesParrain();
                        return;
                    }
                    
                    // Validation immédiate du format
                    if (!validerFormatParrain(code)) {
                        afficherErreurParrain('Code parrain invalide (4 chiffres attendus)');
                        return;
                    }
                    
                    // Validation AJAX avec délai pour éviter trop d'appels
                    validation_timeout = setTimeout(function() {
                        validerParrainAjax(code);
                    }, 800);
                });

                /*  Validation à la perte du focus  */
                $(document).on('blur', '#billing_parrain_code', function(){
                    clearTimeout(validation_timeout);
                    var code = $(this).val();
                    if (code !== '') {
                        validerParrainAjax(code);
                    }
                });

                /*  Validation à la soumission du checkout  */
                $('form.checkout').on('checkout_place_order', function(){
                    var code = $('#billing_parrain_code').val().replace(/\s+/g, '');
                    
                    if (code !== '') {
                        // Vérifier si le champ est en erreur
                        if ($('#billing_parrain_code_field').hasClass('woocommerce-invalid')) {
                            $('html, body').animate({
                                scrollTop: $('#billing_parrain_code_field').offset().top - 100
                            }, 500);
                            return false;
                        }
                        
                        // Validation format final
                        if (!validerFormatParrain(code)) {
                            afficherErreurParrain('Code parrain invalide (4 chiffres attendus)');
                            $('html, body').animate({
                                scrollTop: $('#billing_parrain_code_field').offset().top - 100
                            }, 500);
                            return false;
                        }
                    }
                    
                    return true;
                });

            });
            </script>
            <?php
        }
    }
    
    /**
     * 2) Validation PHP côté serveur robuste avec vérification BDD (conditionnel)
     */
    public function valider_champ_parrain_checkout() {
        // Vérifier si le panier nécessite un code parrain
        $necessite_code_parrain = $this->panier_necessite_code_parrain();
        
        // Si le panier nécessite un code parrain mais qu'aucun n'est fourni
        if ( $necessite_code_parrain && ( ! isset($_POST['billing_parrain_code']) || $_POST['billing_parrain_code'] === '' ) ) {
            wc_add_notice(
                __( 'Le code parrain est obligatoire pour ce produit.', 'wc-tb-web-parrainage' ),
                'error'
            );
            return;
        }
        
        // Si un code parrain est fourni, le valider
        if ( isset($_POST['billing_parrain_code']) && $_POST['billing_parrain_code'] !== '' ) {
            
            $code = preg_replace( '/\s+/', '', sanitize_text_field( $_POST['billing_parrain_code'] ) );
            
            // 1) Validation du format (4 chiffres)
            if ( ! preg_match( '/^\d{4}$/', $code ) ) {
                wc_add_notice(
                    __( 'Code parrain invalide (format attendu : 4 chiffres, ex : 4896).', 'wc-tb-web-parrainage' ),
                    'error'
                );
                return;
            }
            
            // 2) Validation de l'existence en BDD
            $subscription_id = intval( $code );
            $subscription = $this->verifier_subscription_id_existe( $subscription_id );
            
            if ( ! $subscription ) {
                wc_add_notice(
                    __( 'Code parrain inexistant. Veuillez vérifier votre code auprès de votre parrain.', 'wc-tb-web-parrainage' ),
                    'error'
                );
                return;
            }
            
            // 3) Validation supplémentaire : empêcher l'auto-parrainage
            $current_user_id = get_current_user_id();
            if ( $current_user_id ) {
                $subscription_user_id = $subscription->get_user_id();
                
                if ( $current_user_id === $subscription_user_id ) {
                    wc_add_notice(
                        __( 'Vous ne pouvez pas utiliser votre propre code parrain.', 'wc-tb-web-parrainage' ),
                        'error'
                    );
                    return;
                }
            }
            
            // 4) Nettoyage et stockage temporaire
            $_POST['billing_parrain_code'] = $code;
            
            // Log pour debugging
            $this->logger->info( 
                sprintf( 'Code parrain validé côté serveur : %s pour la commande en cours', $code ),
                array( 'code' => $code, 'user_id' => $current_user_id ),
                'parrainage'
            );
        }
    }
    
    /**
     * 3) Sauvegarde enrichie dans la commande
     */
    public function sauvegarder_champ_parrain_commande( $order_id ) {
        if ( ! empty( $_POST['billing_parrain_code'] ) ) {
            $code = sanitize_text_field( $_POST['billing_parrain_code'] );
            
            // Sauvegarder le code parrain
            update_post_meta( $order_id, '_billing_parrain_code', $code );
            
            // Sauvegarder l'avantage du parrainage selon le produit
            $avantage = $this->obtenir_message_parrainage( 'avantage' );
            update_post_meta( $order_id, '_parrainage_avantage', $avantage );
            
            // Sauvegarder les infos du parrain pour référence future
            $infos_parrain = $this->obtenir_infos_parrain( intval( $code ) );
            if ( $infos_parrain ) {
                update_post_meta( $order_id, '_parrain_subscription_id', $infos_parrain['subscription_id'] );
                update_post_meta( $order_id, '_parrain_user_id', $infos_parrain['user_id'] );
                update_post_meta( $order_id, '_parrain_email', $infos_parrain['email'] );
                update_post_meta( $order_id, '_parrain_nom_complet', trim( $infos_parrain['prenom'] . ' ' . $infos_parrain['nom'] ) );
                // NOUVEAU v2.0.6 : Stocker le prénom séparément pour les webhooks
                update_post_meta( $order_id, '_parrain_prenom', $infos_parrain['prenom'] );
                update_post_meta( $order_id, '_parrain_nom', $infos_parrain['nom'] );
            }
            
            // Log pour tracking
            $this->logger->info( 
                sprintf( 'Parrainage enregistré - Commande: %d, Code: %s, Avantage: %s', $order_id, $code, $avantage ),
                array( 
                    'order_id' => $order_id,
                    'code' => $code,
                    'avantage' => $avantage,
                    'parrain_info' => $infos_parrain
                ),
                'parrainage'
            );
        }
    }
    
    /**
     * 4) Affichage enrichi du code parrain dans l'admin WooCommerce
     */
    public function afficher_parrain_admin_commande( $order ) {
        $code = get_post_meta( $order->get_id(), '_billing_parrain_code', true );
        
        if ( $code ) {
            $parrain_user_id = get_post_meta( $order->get_id(), '_parrain_user_id', true );
            $parrain_nom = get_post_meta( $order->get_id(), '_parrain_nom_complet', true );
            $parrain_email = get_post_meta( $order->get_id(), '_parrain_email', true );
            $avantage = get_post_meta( $order->get_id(), '_parrainage_avantage', true );
            
            echo '<div class="parrain-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #00a0d2;">';
            echo '<h4>🎁 ' . esc_html__( 'Informations Parrainage', 'wc-tb-web-parrainage' ) . '</h4>';
            echo '<p><strong>' . esc_html__( 'Code parrain :', 'wc-tb-web-parrainage' ) . '</strong> ' . esc_html( $code ) . '</p>';
            
            if ( $avantage ) {
                echo '<p><strong>' . esc_html__( 'Avantage accordé :', 'wc-tb-web-parrainage' ) . '</strong> <span style="color: #46b450; font-weight: bold;">' . esc_html( $avantage ) . '</span></p>';
            }
            
            if ( $parrain_nom ) {
                echo '<p><strong>' . esc_html__( 'Nom du parrain :', 'wc-tb-web-parrainage' ) . '</strong> ' . esc_html( $parrain_nom ) . '</p>';
            }
            
            if ( $parrain_email ) {
                echo '<p><strong>' . esc_html__( 'Email du parrain :', 'wc-tb-web-parrainage' ) . '</strong> ' . esc_html( $parrain_email ) . '</p>';
            }
            
            if ( $parrain_user_id ) {
                $user_link = admin_url( 'user-edit.php?user_id=' . $parrain_user_id );
                echo '<p><strong>' . esc_html__( 'Profil parrain :', 'wc-tb-web-parrainage' ) . '</strong> <a href="' . esc_url( $user_link ) . '" target="_blank">' . esc_html__( 'Voir le profil', 'wc-tb-web-parrainage' ) . '</a></p>';
            }
            
            echo '</div>';
        }
    }
} 