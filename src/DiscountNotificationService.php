<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service de gestion des notifications pour les remises parrain
 * 
 * Responsabilité unique : Gérer toutes les notifications liées aux remises
 * Principe SRP : Séparation notifications vs calcul vs validation
 * Principe OCP : Extensible pour nouveaux types de notifications
 * 
 * @since 2.5.0
 */
class DiscountNotificationService {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Types de notifications supportés
     * @var array
     */
    private $notification_types = array(
        'discount_applied',
        'discount_failed',
        'discount_suspended',
        'discount_reactivated',
        'admin_alert'
    );
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Envoie une notification de remise appliquée avec succès
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param array $discount_data Données de la remise appliquée
     * @return bool Succès de l'envoi
     */
    public function send_discount_applied_notification( $parrain_subscription_id, $discount_data ) {
        try {
            // Validation des paramètres d'entrée
            if ( ! is_numeric( $parrain_subscription_id ) || $parrain_subscription_id <= 0 ) {
                throw new InvalidArgumentException( 'ID abonnement parrain invalide : ' . $parrain_subscription_id );
            }
            
            if ( ! is_array( $discount_data ) || empty( $discount_data['discount_amount'] ) ) {
                throw new InvalidArgumentException( 'Données de remise invalides ou incomplètes' );
            }
            
            // Récupération des informations parrain
            $parrain_info = $this->get_parrain_info( $parrain_subscription_id );
            if ( ! $parrain_info ) {
                throw new InvalidArgumentException( 'Informations parrain introuvables pour l\'ID : ' . $parrain_subscription_id );
            }
            
            // Préparation du message
            $message_data = array(
                'type' => 'discount_applied',
                'parrain_name' => $parrain_info['name'],
                'parrain_email' => $parrain_info['email'],
                'discount_amount' => $discount_data['discount_amount'],
                'new_price' => $discount_data['new_price'],
                'original_price' => $discount_data['original_price'],
                'currency' => $discount_data['currency'] ?? 'EUR',
                'notification_date' => current_time( 'mysql' )
            );
            
            // Envoi selon préférences utilisateur
            $notification_sent = $this->send_notification( $message_data );
            
            // Log du résultat
            $this->logger->info(
                'Notification remise appliquée envoyée',
                array(
                    'subscription_id' => $parrain_subscription_id,
                    'notification_sent' => $notification_sent,
                    'discount_amount' => $discount_data['discount_amount']
                ),
                'discount-notifications'
            );
            
            return $notification_sent;
            
        } catch ( InvalidArgumentException $e ) {
            // Paramètres invalides pour la notification
            $this->logger->warning(
                'Paramètres invalides pour notification remise appliquée',
                array(
                    'subscription_id' => $parrain_subscription_id,
                    'validation_error' => $e->getMessage()
                ),
                'discount-notifications'
            );
            return false;
        } catch ( Exception $e ) {
            // Erreur système lors de l'envoi
            $this->logger->error(
                'Erreur système envoi notification remise appliquée',
                array(
                    'subscription_id' => $parrain_subscription_id,
                    'system_error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'discount-notifications'
            );
            return false;
        }
    }
    
    /**
     * Envoie une notification d'échec d'application de remise
     * 
     * @param int $parrain_subscription_id ID abonnement parrain
     * @param array $error_details Détails de l'erreur
     * @return bool Succès de l'envoi
     */
    public function send_discount_failed_notification( $parrain_subscription_id, $error_details ) {
        try {
            $parrain_info = $this->get_parrain_info( $parrain_subscription_id );
            if ( ! $parrain_info ) {
                return false;
            }
            
            $message_data = array(
                'type' => 'discount_failed',
                'parrain_name' => $parrain_info['name'],
                'parrain_email' => $parrain_info['email'],
                'error_reason' => $error_details['reason'] ?? 'Erreur inconnue',
                'error_details' => $error_details,
                'notification_date' => current_time( 'mysql' )
            );
            
            $notification_sent = $this->send_notification( $message_data );
            
            $this->logger->info(
                'Notification échec remise envoyée',
                array(
                    'subscription_id' => $parrain_subscription_id,
                    'notification_sent' => $notification_sent,
                    'error_reason' => $error_details['reason'] ?? 'Inconnue'
                ),
                'discount-notifications'
            );
            
            return $notification_sent;
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Erreur envoi notification échec remise',
                array(
                    'subscription_id' => $parrain_subscription_id,
                    'error' => $e->getMessage()
                ),
                'discount-notifications'
            );
            return false;
        }
    }
    
    /**
     * Envoie une alerte administrative
     * 
     * @param string $alert_type Type d'alerte
     * @param array $alert_data Données de l'alerte
     * @return bool Succès de l'envoi
     */
    public function send_admin_alert( $alert_type, $alert_data ) {
        try {
            // Récupération des emails administrateurs
            $admin_emails = $this->get_admin_notification_emails();
            if ( empty( $admin_emails ) ) {
                return false;
            }
            
            $message_data = array(
                'type' => 'admin_alert',
                'alert_type' => $alert_type,
                'alert_data' => $alert_data,
                'admin_emails' => $admin_emails,
                'notification_date' => current_time( 'mysql' ),
                'site_url' => get_site_url()
            );
            
            $notification_sent = $this->send_admin_notification( $message_data );
            
            $this->logger->info(
                'Alerte administrative envoyée',
                array(
                    'alert_type' => $alert_type,
                    'notification_sent' => $notification_sent,
                    'admin_count' => count( $admin_emails )
                ),
                'discount-notifications'
            );
            
            return $notification_sent;
            
        } catch ( Exception $e ) {
            $this->logger->error(
                'Erreur envoi alerte administrative',
                array(
                    'alert_type' => $alert_type,
                    'error' => $e->getMessage()
                ),
                'discount-notifications'
            );
            return false;
        }
    }
    
    /**
     * Récupère les informations du parrain
     * 
     * @param int $subscription_id ID abonnement
     * @return array|false Informations parrain ou false
     */
    private function get_parrain_info( $subscription_id ) {
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            return false;
        }
        
        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return false;
        }
        
        $user_id = $subscription->get_user_id();
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }
        
        return array(
            'user_id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'subscription_id' => $subscription_id
        );
    }
    
    /**
     * Envoie une notification selon le type
     * 
     * @param array $message_data Données du message
     * @return bool Succès de l'envoi
     */
    private function send_notification( $message_data ) {
        // Vérification des préférences utilisateur pour les notifications
        $notifications_enabled = get_option( 'wc_tb_parrainage_notifications_enabled', true );
        if ( ! $notifications_enabled ) {
            return false;
        }
        
        switch ( $message_data['type'] ) {
            case 'discount_applied':
                return $this->send_email_notification(
                    $message_data['parrain_email'],
                    __( 'Votre remise parrain a été appliquée', 'wc-tb-web-parrainage' ),
                    $this->build_discount_applied_email_content( $message_data )
                );
                
            case 'discount_failed':
                return $this->send_email_notification(
                    $message_data['parrain_email'],
                    __( 'Problème avec votre remise parrain', 'wc-tb-web-parrainage' ),
                    $this->build_discount_failed_email_content( $message_data )
                );
                
            default:
                return false;
        }
    }
    
    /**
     * Construit le contenu email pour remise appliquée
     * 
     * @param array $data Données du message
     * @return string Contenu HTML
     */
    private function build_discount_applied_email_content( $data ) {
        $template = '
        <h2>Bonne nouvelle, %s !</h2>
        <p>Votre remise parrain de <strong>%s</strong> a été appliquée avec succès à votre abonnement.</p>
        <h3>Détails de votre remise :</h3>
        <ul>
            <li>Prix original : <strong>%s</strong></li>
            <li>Remise appliquée : <strong>%s</strong></li>
            <li>Nouveau prix : <strong>%s</strong></li>
        </ul>
        <p>Cette remise sera automatiquement appliquée à vos prochaines facturations.</p>
        <p>Merci de faire confiance à nos services !</p>
        ';
        
        return sprintf(
            $template,
            esc_html( $data['parrain_name'] ),
            esc_html( number_format( $data['discount_amount'], 2, ',', '' ) . '€' ),
            esc_html( number_format( $data['original_price'], 2, ',', '' ) . '€' ),
            esc_html( number_format( $data['discount_amount'], 2, ',', '' ) . '€' ),
            esc_html( number_format( $data['new_price'], 2, ',', '' ) . '€' )
        );
    }
    
    /**
     * Construit le contenu email pour échec de remise
     * 
     * @param array $data Données du message
     * @return string Contenu HTML
     */
    private function build_discount_failed_email_content( $data ) {
        $template = '
        <h2>Information concernant votre remise parrain</h2>
        <p>Bonjour %s,</p>
        <p>Nous rencontrons actuellement une difficulté pour appliquer votre remise parrain.</p>
        <p><strong>Raison :</strong> %s</p>
        <p>Notre équipe technique a été automatiquement notifiée et travaille à résoudre ce problème dans les plus brefs délais.</p>
        <p>Vous recevrez une nouvelle notification dès que votre remise sera appliquée.</p>
        <p>Merci de votre compréhension.</p>
        ';
        
        return sprintf(
            $template,
            esc_html( $data['parrain_name'] ),
            esc_html( $data['error_reason'] )
        );
    }
    
    /**
     * Envoie un email via WordPress
     * 
     * @param string $to Email destinataire
     * @param string $subject Sujet
     * @param string $content Contenu HTML
     * @return bool Succès de l'envoi
     */
    private function send_email_notification( $to, $subject, $content ) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>'
        );
        
        return wp_mail( $to, $subject, $content, $headers );
    }
    
    /**
     * Récupère les emails administrateurs pour alertes
     * 
     * @return array Liste des emails administrateurs
     */
    private function get_admin_notification_emails() {
        $admin_emails = get_option( 'wc_tb_parrainage_admin_notification_emails', array() );
        
        if ( empty( $admin_emails ) ) {
            // Email admin par défaut si rien configuré
            $admin_emails = array( get_option( 'admin_email' ) );
        }
        
        return array_filter( $admin_emails );
    }
    
    /**
     * Envoie une notification administrative
     * 
     * @param array $message_data Données du message
     * @return bool Succès de l'envoi
     */
    private function send_admin_notification( $message_data ) {
        $subject = sprintf(
            '[%s] Alerte système parrainage : %s',
            get_bloginfo( 'name' ),
            $message_data['alert_type']
        );
        
        $content = sprintf(
            '<h2>Alerte système parrainage</h2>
             <p><strong>Type :</strong> %s</p>
             <p><strong>Date :</strong> %s</p>
             <p><strong>Détails :</strong></p>
             <pre>%s</pre>',
            esc_html( $message_data['alert_type'] ),
            esc_html( $message_data['notification_date'] ),
            esc_html( print_r( $message_data['alert_data'], true ) )
        );
        
        $success = true;
        foreach ( $message_data['admin_emails'] as $admin_email ) {
            if ( ! $this->send_email_notification( $admin_email, $subject, $content ) ) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Teste l'envoi de notification
     * 
     * @param string $email Email de test
     * @return bool Succès du test
     */
    public function test_notification( $email ) {
        return $this->send_email_notification(
            $email,
            'Test notification système parrainage',
            '<p>Ceci est un email de test du système de notifications parrainage.</p>'
        );
    }
}