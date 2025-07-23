<?php

namespace TBWeb\WCParrainage\ParrainPricing\Notifier;

use TBWeb\WCParrainage\ParrainPricing\Constants\ParrainPricingConstants;
use TBWeb\WCParrainage\Logger;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire de notifications email pour le système de réduction
 * 
 * Responsabilité unique : communication par email avec les parrains
 * Respecte LSP : peut remplacer l'interface sans changer le comportement
 * 
 * @since 2.0.0
 */
class ParrainPricingEmailNotifier {
    
    /** @var Logger Instance du logger */
    private Logger $logger;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Notifie l'application d'une réduction
     * 
     * Comportement prévisible (principe Least Astonishment)
     * 
     * @param int $user_id ID de l'utilisateur parrain
     * @param array $notification_data Données de notification
     * @return array Résultat de notification
     */
    public function notify_reduction_applied( int $user_id, array $notification_data ): array {
        try {
            // Validation des données
            $this->validate_notification_data( $notification_data );
            
            // Récupérer les informations utilisateur
            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                throw new \Exception( "Utilisateur introuvable : $user_id" );
            }
            
            // Construction du contenu email
            $email_content = $this->build_reduction_email_content( $notification_data );
            $email_subject = $this->build_reduction_email_subject( $notification_data );
            
            // Envoi via système WordPress
            $success = $this->send_wordpress_email(
                $user->user_email,
                $email_subject,
                $email_content,
                $this->get_email_headers()
            );
            
            if ( $success ) {
                $this->logger->info( 'Notification réduction envoyée', [
                    'component' => 'ParrainPricingEmailNotifier',
                    'user_id' => $user_id,
                    'user_email' => $user->user_email,
                    'reduction_amount' => $notification_data['reduction_amount']
                ]);
            }
            
            return [
                'success' => $success,
                'message' => $success ? 'Email envoyé avec succès' : 'Échec envoi email',
                'email_sent_to' => $user->user_email
            ];
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'Erreur notification réduction', [
                'component' => 'ParrainPricingEmailNotifier',
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'data' => $notification_data
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Notifie la suppression d'une réduction
     * 
     * @param int $user_id ID de l'utilisateur parrain
     * @param array $notification_data Données de notification
     * @return array Résultat de notification
     */
    public function notify_reduction_removed( int $user_id, array $notification_data ): array {
        try {
            // Validation des données
            $this->validate_notification_data( $notification_data );
            
            // Récupérer les informations utilisateur
            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                throw new \Exception( "Utilisateur introuvable : $user_id" );
            }
            
            // Construction du contenu email
            $email_content = $this->build_removal_email_content( $notification_data );
            $email_subject = $this->build_removal_email_subject( $notification_data );
            
            // Envoi via système WordPress
            $success = $this->send_wordpress_email(
                $user->user_email,
                $email_subject,
                $email_content,
                $this->get_email_headers()
            );
            
            if ( $success ) {
                $this->logger->info( 'Notification suppression réduction envoyée', [
                    'component' => 'ParrainPricingEmailNotifier',
                    'user_id' => $user_id,
                    'user_email' => $user->user_email,
                    'action' => 'removal'
                ]);
            }
            
            return [
                'success' => $success,
                'message' => $success ? 'Email envoyé avec succès' : 'Échec envoi email',
                'email_sent_to' => $user->user_email
            ];
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'Erreur notification suppression', [
                'component' => 'ParrainPricingEmailNotifier',
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'data' => $notification_data
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Notifie une erreur de pricing à l'administrateur
     * 
     * @param array $error_data Données d'erreur
     * @return array Résultat de notification
     */
    public function notify_pricing_error( array $error_data ): array {
        try {
            // Email administrateur
            $admin_email = get_option( 'admin_email' );
            
            $email_subject = '🚨 Erreur système de réduction parrainage - ' . get_bloginfo( 'name' );
            $email_content = $this->build_error_email_content( $error_data );
            
            $success = $this->send_wordpress_email(
                $admin_email,
                $email_subject,
                $email_content,
                $this->get_email_headers()
            );
            
            if ( $success ) {
                $this->logger->info( 'Notification erreur envoyée à l\'admin', [
                    'component' => 'ParrainPricingEmailNotifier',
                    'admin_email' => $admin_email
                ]);
            }
            
            return [
                'success' => $success,
                'message' => $success ? 'Notification admin envoyée' : 'Échec notification admin'
            ];
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'Erreur notification admin', [
                'component' => 'ParrainPricingEmailNotifier',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Construction du contenu email pour réduction appliquée
     * 
     * Responsabilité unique : formatage du contenu
     * 
     * @param array $data Données de notification
     * @return string Contenu HTML
     */
    private function build_reduction_email_content( array $data ): string {
        $template = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
            <div style="background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #27ae60; margin: 0; font-size: 28px;">🎉 Félicitations !</h1>
                    <p style="color: #666; font-size: 16px; margin: 10px 0 0 0;">Votre parrainage vous fait économiser</p>
                </div>
                
                <div style="background-color: #e8f5e8; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
                    <h2 style="color: #27ae60; margin: 0 0 15px 0; font-size: 20px;">💰 Détails de votre réduction</h2>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px 0; font-weight: bold; color: #333;">Ancien prix mensuel :</td>
                            <td style="padding: 8px 0; text-align: right; color: #666;">%.2f€ HT</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px 0; font-weight: bold; color: #333;">Nouveau prix mensuel :</td>
                            <td style="padding: 8px 0; text-align: right; color: #27ae60; font-weight: bold; font-size: 18px;">%.2f€ HT</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold; color: #333;">Économie mensuelle :</td>
                            <td style="padding: 8px 0; text-align: right; color: #e74c3c; font-weight: bold; font-size: 18px;">-%.2f€</td>
                        </tr>
                    </table>
                </div>
                
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107; margin-bottom: 25px;">
                    <p style="margin: 0; color: #856404; font-size: 14px;">
                        <strong>📅 Application automatique :</strong><br>
                        Cette réduction a été appliquée automatiquement à votre abonnement et sera effective dès votre prochain prélèvement.
                    </p>
                </div>
                
                <div style="text-align: center; margin-bottom: 25px;">
                    <p style="color: #333; font-size: 16px; margin: 0 0 15px 0;">
                        <strong>Merci de nous aider à grandir ! 🚀</strong>
                    </p>
                    <p style="color: #666; font-size: 14px; margin: 0;">
                        Votre parrainage permet à de nouveaux entrepreneurs de découvrir nos formations.
                    </p>
                </div>
                
                <div style="border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
                    <p style="color: #999; font-size: 12px; margin: 0;">
                        TB Formation - Système de parrainage automatique<br>
                        Si vous avez des questions, contactez notre support.
                    </p>
                </div>
                
            </div>
        </div>';
        
        return sprintf(
            $template,
            $data['original_price'],
            $data['new_price'],
            $data['reduction_amount']
        );
    }
    
    /**
     * Construction du sujet email pour réduction
     * 
     * @param array $data Données de notification
     * @return string Sujet
     */
    private function build_reduction_email_subject( array $data ): string {
        return sprintf(
            '🎉 Réduction parrainage : %.2f€ d\'économie sur votre abonnement !',
            $data['reduction_amount']
        );
    }
    
    /**
     * Construction du contenu email pour suppression de réduction
     * 
     * @param array $data Données de notification
     * @return string Contenu HTML
     */
    private function build_removal_email_content( array $data ): string {
        $template = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
            <div style="background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #3498db; margin: 0; font-size: 28px;">ℹ️ Information importante</h1>
                    <p style="color: #666; font-size: 16px; margin: 10px 0 0 0;">Concernant votre réduction parrainage</p>
                </div>
                
                <div style="background-color: #e3f2fd; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
                    <h2 style="color: #1976d2; margin: 0 0 15px 0; font-size: 20px;">📋 Modification de votre tarification</h2>
                    <p style="color: #333; margin: 0; font-size: 16px;">
                        Votre réduction parrainage a été supprimée et votre abonnement reprend son tarif normal.
                        Cette modification sera effective dès votre prochain prélèvement.
                    </p>
                </div>
                
                <div style="text-align: center; margin-bottom: 25px;">
                    <p style="color: #333; font-size: 16px; margin: 0 0 15px 0;">
                        <strong>Questions ? Nous sommes là pour vous aider ! 💬</strong>
                    </p>
                    <p style="color: #666; font-size: 14px; margin: 0;">
                        N\'hésitez pas à contacter notre équipe support pour plus d\'informations.
                    </p>
                </div>
                
                <div style="border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
                    <p style="color: #999; font-size: 12px; margin: 0;">
                        TB Formation - Système de parrainage automatique<br>
                        Email envoyé automatiquement suite à une modification tarifaire.
                    </p>
                </div>
                
            </div>
        </div>';
        
        return $template;
    }
    
    /**
     * Construction du sujet email pour suppression
     * 
     * @param array $data Données de notification
     * @return string Sujet
     */
    private function build_removal_email_subject( array $data ): string {
        return 'ℹ️ Modification de votre réduction parrainage - ' . get_bloginfo( 'name' );
    }
    
    /**
     * Construction du contenu email d'erreur pour admin
     * 
     * @param array $error_data Données d'erreur
     * @return string Contenu texte
     */
    private function build_error_email_content( array $error_data ): string {
        $content = "⚠️ ERREUR SYSTÈME DE RÉDUCTION PARRAINAGE\n\n";
        $content .= "Site : " . get_bloginfo( 'name' ) . " (" . get_bloginfo( 'url' ) . ")\n";
        $content .= "Date : " . current_time( 'Y-m-d H:i:s' ) . "\n\n";
        
        $content .= "DÉTAILS DE L'ERREUR :\n";
        $content .= "- Composant : " . ($error_data['component'] ?? 'Non spécifié') . "\n";
        $content .= "- Message : " . ($error_data['error_message'] ?? 'Aucun message') . "\n";
        $content .= "- Abonnement : " . ($error_data['subscription_id'] ?? 'N/A') . "\n";
        $content .= "- Utilisateur : " . ($error_data['user_id'] ?? 'N/A') . "\n\n";
        
        if ( isset( $error_data['context'] ) ) {
            $content .= "CONTEXTE :\n";
            $content .= print_r( $error_data['context'], true ) . "\n\n";
        }
        
        $content .= "ACTION REQUISE :\n";
        $content .= "- Vérifiez les logs du plugin dans l'administration WordPress\n";
        $content .= "- Contrôlez l'état des abonnements concernés\n";
        $content .= "- Contactez le support technique si nécessaire\n\n";
        
        $content .= "Cet email a été généré automatiquement par le système de parrainage TB-Web.";
        
        return $content;
    }
    
    /**
     * Envoie un email via WordPress
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $content Contenu
     * @param array $headers Headers
     * @return bool Succès
     */
    private function send_wordpress_email( string $to, string $subject, string $content, array $headers ): bool {
        // Utiliser wp_mail pour compatibilité maximale
        return wp_mail( $to, $subject, $content, $headers );
    }
    
    /**
     * Retourne les headers email standards
     * 
     * @return array Headers
     */
    private function get_email_headers(): array {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>'
        ];
    }
    
    /**
     * Validation des données de notification
     * 
     * @param array $data Données à valider
     * @throws \InvalidArgumentException Si invalides
     */
    private function validate_notification_data( array $data ): void {
        $required_fields = ['original_price', 'new_price', 'reduction_amount'];
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[$field] ) ) {
                throw new \InvalidArgumentException( "Champ requis manquant : $field" );
            }
        }
        
        if ( $data['original_price'] < 0 || $data['new_price'] < 0 || $data['reduction_amount'] < 0 ) {
            throw new \InvalidArgumentException( 'Valeurs négatives non autorisées' );
        }
    }
} 