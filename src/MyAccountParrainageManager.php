<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire principal de l'onglet "Mes parrainages" côté client
 * 
 * Responsabilité unique : Gestion de l'endpoint WooCommerce Mon Compte
 * et orchestration de l'affichage des données de parrainage côté client
 * 
 * @package TBWeb\WCParrainage
 * @since 1.3.0
 */
class MyAccountParrainageManager {
    
    // Constantes pour éviter les magic numbers
    const ENDPOINT_KEY = 'mes-parrainages';
    const ENDPOINT_LABEL = 'Mes parrainages';
    const PARRAINAGES_LIMIT = 10;
    const PARRAINAGE_URL = 'https://tb-web.fr/parrainage/';
    
    /**
     * @var Logger Instance du système de logs
     */
    private $logger;
    
    /**
     * @var MyAccountDataProvider Fournisseur de données
     */
    private $data_provider;
    
    /**
     * @var MyAccountAccessValidator Validateur d'accès
     */
    private $access_validator;
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du système de logs
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        $this->data_provider = new MyAccountDataProvider( $logger );
        $this->access_validator = new MyAccountAccessValidator( $logger );
    }
    
    /**
     * Initialise le gestionnaire et ses hooks
     * 
     * @return void
     */
    public function init() {
        // Hooks WooCommerce standard pour l'endpoint
        \add_action( 'init', array( $this, 'register_endpoint' ) );
        \add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
        \add_action( 'woocommerce_account_' . self::ENDPOINT_KEY . '_endpoint', array( $this, 'render_endpoint_content' ) );
        \add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        
        $this->logger->info( 'MyAccountParrainageManager initialisé', array(
            'endpoint_key' => self::ENDPOINT_KEY,
            'endpoint_label' => self::ENDPOINT_LABEL
        ) );
    }
    
    /**
     * Enregistre l'endpoint WooCommerce
     * 
     * @return void
     */
    public function register_endpoint() {
        \add_rewrite_endpoint( self::ENDPOINT_KEY, \EP_ROOT | \EP_PAGES );
    }
    
    /**
     * Ajoute l'onglet au menu Mon Compte
     * 
     * @param array $items Éléments du menu existants
     * @return array Menu mis à jour
     */
    public function add_menu_item( $items ) {
        // Vérifier l'accès utilisateur avant d'afficher l'onglet
        if ( ! $this->access_validator->user_can_access_parrainages() ) {
            return $items;
        }
        
        // Retirer logout pour le remettre à la fin
        $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
        if ( $logout ) {
            unset( $items['customer-logout'] );
        }
        
        // Ajouter notre onglet
        $items[ self::ENDPOINT_KEY ] = \__( self::ENDPOINT_LABEL, 'wc-tb-web-parrainage' );
        
        // Remettre logout à la fin
        if ( $logout ) {
            $items['customer-logout'] = $logout;
        }
        
        return $items;
    }
    
    /**
     * Rend le contenu de l'endpoint
     * 
     * @return void
     */
    public function render_endpoint_content() {
        // DEBUG v2.14.0 : Log entrée dans la méthode
        $this->logger->info( '🚀 DEBUT render_endpoint_content - Mes parrainages', array(
            'user_id' => get_current_user_id(),
            'timestamp' => time(),
            'memory_usage' => memory_get_usage( true )
        ), 'mes-parrainages-debug' );
        
        try {
            // DEBUG v2.14.0 : Test access_validator
            $this->logger->info( '🔐 Test access_validator->user_can_access_parrainages()', array(), 'mes-parrainages-debug' );
            
            // Vérification d'accès obligatoire
            if ( ! $this->access_validator->user_can_access_parrainages() ) {
                $this->logger->warning( '❌ Accès refusé par access_validator', array(), 'mes-parrainages-debug' );
                $this->access_validator->handle_access_denied( MyAccountAccessValidator::ERROR_NO_SUBSCRIPTION );
                return;
            }
            
            $this->logger->info( '✅ Accès autorisé par access_validator', array(), 'mes-parrainages-debug' );
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans access_validator', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ), 'mes-parrainages-debug' );
            echo '<div class="error">Erreur d\'accès : ' . esc_html( $e->getMessage() ) . '</div>';
            return;
        }
        
        try {
            // DEBUG v2.14.0 : Récupération user_id
            $user_id = \get_current_user_id();
            $this->logger->info( '👤 User ID récupéré', array( 'user_id' => $user_id ), 'mes-parrainages-debug' );
            
            // DEBUG v2.14.0 : Test data_provider
            $this->logger->info( '📊 Test data_provider->get_user_subscription_id()', array(), 'mes-parrainages-debug' );
            $subscription_id = $this->data_provider->get_user_subscription_id( $user_id );
            $this->logger->info( '📋 Subscription ID récupéré', array( 'subscription_id' => $subscription_id ), 'mes-parrainages-debug' );
            
            if ( ! $subscription_id ) {
                $this->logger->warning( '❌ Aucun subscription_id trouvé', array( 'user_id' => $user_id ), 'mes-parrainages-debug' );
                $this->access_validator->handle_access_denied( MyAccountAccessValidator::ERROR_NO_SUBSCRIPTION );
                return;
            }
            
            // DEBUG v2.14.0 : Test get_user_parrainages
            $this->logger->info( '📈 Test data_provider->get_user_parrainages()', array(
                'subscription_id' => $subscription_id,
                'limit' => self::PARRAINAGES_LIMIT
            ), 'mes-parrainages-debug' );
            
            // Récupérer les données de parrainage
            $parrainages = $this->data_provider->get_user_parrainages( $subscription_id, self::PARRAINAGES_LIMIT );
            $this->logger->info( '📊 Parrainages récupérés', array( 
                'count' => count( $parrainages ),
                'subscription_id' => $subscription_id 
            ), 'mes-parrainages-debug' );
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans récupération données', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $user_id ?? 'UNKNOWN'
            ), 'mes-parrainages-debug' );
            echo '<div class="error">Erreur lors de la récupération des données : ' . esc_html( $e->getMessage() ) . '</div>';
            return;
        }
        
        // Logger l'affichage
        $this->logger->info( 'Affichage onglet Mes parrainages', array(
            'user_id' => $user_id,
            'subscription_id' => $subscription_id,
            'parrainages_count' => count( $parrainages )
        ) );
        
        try {
            // DEBUG v2.14.0 : Début rendu interface
            $this->logger->info( '🎨 DEBUT rendu interface', array(
                'parrainages_count' => count( $parrainages ),
                'is_empty' => empty( $parrainages )
            ), 'mes-parrainages-debug' );
            
            // Rendre l'interface
            echo '<div class="woocommerce-MyAccount-content">';
            echo '<h2>' . \esc_html__( 'Mes parrainages', 'wc-tb-web-parrainage' ) . '</h2>';
            
            if ( empty( $parrainages ) ) {
                $this->logger->info( '📝 Affichage message invitation', array( 'subscription_id' => $subscription_id ), 'mes-parrainages-debug' );
                $this->render_invitation_message( $subscription_id );
            } else {
                // CORRECTION v2.14.0 : Version sécurisée de render_savings_summary
                $this->logger->info( '📊 Test render_savings_summary() SÉCURISÉ', array( 'subscription_id' => $subscription_id ), 'mes-parrainages-debug' );
                
                try {
                    $this->render_savings_summary_safe( $subscription_id );
                    $this->logger->info( '✅ render_savings_summary_safe() TERMINÉ', array(), 'mes-parrainages-debug' );
                } catch ( Exception $e ) {
                    $this->logger->error( '💥 Erreur dans render_savings_summary_safe()', array(
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ), 'mes-parrainages-debug' );
                    
                    // Affichage fallback simple
                    echo '<div class="savings-summary-section">';
                    echo '<h3>📊 Résumé de vos remises</h3>';
                    echo '<p>Données temporairement indisponibles. Veuillez réessayer plus tard.</p>';
                    echo '</div>';
                }
                
                $this->logger->info( '📋 Test render_parrainages_table() - DÉSACTIVÉ TEMPORAIREMENT', array( 'parrainages_count' => count( $parrainages ) ), 'mes-parrainages-debug' );
                
                // CORRECTION v2.14.0 : Réactivation du tableau après correction erreur 500
                $this->logger->info( '📋 RÉACTIVATION render_parrainages_table()', array( 
                    'parrainages_count' => count( $parrainages ) 
                ), 'mes-parrainages-debug' );
                
                try {
                    $this->render_parrainages_table( $parrainages );
                    $this->logger->info( '✅ render_parrainages_table() RÉACTIVÉ AVEC SUCCÈS', array(), 'mes-parrainages-debug' );
                } catch ( \Throwable $e ) {
                    $this->logger->error( '💥 ERREUR render_parrainages_table()', array(
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ), 'mes-parrainages-debug' );
                    
                    // Fallback sécurisé
                    echo '<div class="parrainages-section">';
                    echo '<h3>📋 Vos parrainages</h3>';
                    echo '<p><strong>Vous avez ' . count( $parrainages ) . ' filleul(s)</strong></p>';
                    echo '<p><em>Erreur lors du chargement du tableau détaillé</em></p>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
            
            $this->logger->info( '✅ Interface rendue avec succès', array(), 'mes-parrainages-debug' );
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans rendu interface', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ), 'mes-parrainages-debug' );
            echo '<div class="error">Erreur lors de l\'affichage : ' . esc_html( $e->getMessage() ) . '</div>';
        }
    }
    
    /**
     * Charge les styles CSS pour l'onglet
     * 
     * @return void
     */
    public function enqueue_styles() {
        // Charger seulement sur les pages Mon Compte
        if ( ! \is_wc_endpoint_url( self::ENDPOINT_KEY ) && ! \is_account_page() ) {
            return;
        }
        
        \wp_enqueue_style(
            'wc-tb-parrainage-my-account',
            WC_TB_PARRAINAGE_URL . 'assets/my-account-parrainage.css',
            array(),
            WC_TB_PARRAINAGE_VERSION
        );
        
        // NOUVEAU v2.4.0 : Charger les assets JavaScript pour les interactions de remise côté client
        \wp_enqueue_script( 'jquery-ui-effects-highlight' ); // CORRECTION v2.14.0 : Ajouter jQuery UI
        \wp_enqueue_script(
            'wc-tb-parrainage-my-account-discount',
            WC_TB_PARRAINAGE_URL . 'assets/my-account-discount.js',
            array( 'jquery', 'jquery-ui-effects-highlight' ), // CORRECTION v2.14.0 : Ajouter dépendance
            WC_TB_PARRAINAGE_VERSION . '_' . time(), // FORCE CACHE REFRESH v2.9.3
            true
        );
    }
    
    /**
     * Rend le tableau des parrainages avec les nouvelles colonnes v2.0.2
     * 
     * @param array $parrainages Données des parrainages
     * @return void
     */
    private function render_parrainages_table( $parrainages ) {
        ?>
        <table class="parrainages-table woocommerce-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Filleul', 'wc-tb-web-parrainage' ); ?></th>
                    <th><?php esc_html_e( 'Date parrainage', 'wc-tb-web-parrainage' ); ?></th>
                    <th><?php esc_html_e( 'Abonnement HT de votre filleul', 'wc-tb-web-parrainage' ); ?></th>
                    <th><?php esc_html_e( 'Statut de son abonnement', 'wc-tb-web-parrainage' ); ?></th>
                    <th><?php esc_html_e( 'Avantage reçu par votre filleul', 'wc-tb-web-parrainage' ); ?></th>
                    <th><?php esc_html_e( 'Votre remise*', 'wc-tb-web-parrainage' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $parrainages as $parrainage ) : ?>
                    <tr>
                        <td>
                            <?php echo esc_html( $parrainage['filleul_nom'] ); ?>
                            <?php if ( !empty( $parrainage['filleul_email'] ) ) : ?>
                                <br><small class="email-masked"><?php echo esc_html( $parrainage['filleul_email'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $parrainage['date_parrainage'] ); ?></td>
                        <td><?php echo esc_html( $parrainage['abonnement_ht'] ); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr( $parrainage['subscription_status'] ); ?>">
                                <?php echo esc_html( $parrainage['status_label'] ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $parrainage['avantage'] ); ?></td>
                        <!-- MODIFIÉ v2.4.0 : Colonne "Votre remise" enrichie -->
                        <td class="column-votre-remise">
                            <?php if ( !empty( $parrainage['discount_client_info'] ) ) : ?>
                                <?php
                                $discount = $parrainage['discount_client_info'];
                                $status_class = 'status-' . $discount['discount_status'];
                                $icon = $discount['status_icon'];
                                ?>
                                <div class="remise-status-container">
                                    <span class="remise-amount"><?php echo esc_html( $discount['discount_amount_formatted'] ); ?></span>
                                    <span class="remise-status <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo $icon . ' ' . esc_html( $discount['discount_status_message'] ); ?>
                                    </span>
                                </div>
                            <?php else : ?>
                                <span class="remise-na">Non applicable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Explications pédagogiques v2.0.2 -->
        <div class="tb-parrainage-explanations" style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #0073aa;">
            <p><strong>* À propos de votre remise :</strong></p>
            <ul style="margin-left: 20px;">
                <li>La remise de <strong>25% s'applique sur le montant hors taxes (HT)</strong> de l'abonnement de votre filleul</li>
                <li>Elle est automatiquement déduite de vos mensualités <strong>après la souscription</strong> de votre filleul</li>
                <li>La remise n'est active que si <strong>l'abonnement de votre filleul reste en cours</strong></li>
                <li>En cas d'annulation de l'abonnement du filleul, votre remise sera automatiquement supprimée</li>
            </ul>
        </div>
        
        <?php if ( count( $parrainages ) >= self::PARRAINAGES_LIMIT ) : ?>
            <p class="parrainages-limit-notice">
                <em><?php 
                    printf( 
                        esc_html__( 'Affichage des %d derniers parrainages. Contactez-nous pour consulter l\'historique complet.', 'wc-tb-web-parrainage' ),
                        self::PARRAINAGES_LIMIT 
                    ); 
                ?></em>
            </p>
        <?php endif;
    }
    
    /**
     * Rend le message d'invitation si aucun parrainage
     * 
     * @param int $user_subscription_id ID de l'abonnement de l'utilisateur
     * @return void
     */
    private function render_invitation_message( $user_subscription_id ) {
        ?>
        <div class="parrainage-invitation">
            <h3>🎁 <?php esc_html_e( 'Vous n\'avez pas encore parrainé ?', 'wc-tb-web-parrainage' ); ?></h3>
            <p><?php esc_html_e( 'Pour cela rien de plus simple, envoyez ce lien à votre filleul et dites-lui de s\'inscrire avec ce code de parrainage :', 'wc-tb-web-parrainage' ); ?></p>
            
            <div class="parrainage-details">
                <p>
                    <strong>🔗 <?php esc_html_e( 'Lien :', 'wc-tb-web-parrainage' ); ?></strong> 
                    <a href="<?php echo esc_url( self::PARRAINAGE_URL ); ?>" target="_blank">
                        <?php echo esc_html( self::PARRAINAGE_URL ); ?>
                    </a>
                </p>
                <p>
                    <strong>📧 <?php esc_html_e( 'Votre code parrain :', 'wc-tb-web-parrainage' ); ?></strong> 
                    <code class="parrain-code"><?php echo esc_html( $user_subscription_id ); ?></code>
                </p>
            </div>
            
            <p><em><?php esc_html_e( 'Votre filleul bénéficiera d\'un avantage et vous aussi !', 'wc-tb-web-parrainage' ); ?></em></p>
        </div>
        <?php
    }
    
    /**
     * NOUVEAU v2.4.0 : Rendu de la section résumé des économies
     * 
     * @param int $user_subscription_id ID de l'abonnement utilisateur
     * @return void
     */
    private function render_savings_summary( $user_subscription_id ) {
        try {
            $this->logger->info( '🔍 DÉBUT render_savings_summary()', array( 'subscription_id' => $user_subscription_id ), 'mes-parrainages-debug' );
            
            $summary = $this->data_provider->get_savings_summary( $user_subscription_id );
            
            $this->logger->info( '📊 Summary récupéré', array( 
                'subscription_id' => $user_subscription_id,
                'summary_keys' => array_keys( $summary ?? array() ),
                'summary_type' => gettype( $summary )
            ), 'mes-parrainages-debug' );
            
            if ( empty( $summary ) ) {
                $this->logger->warning( '⚠️ Summary vide, affichage par défaut', array(), 'mes-parrainages-debug' );
                echo '<div class="savings-summary-section"><p>Données de résumé non disponibles.</p></div>';
                return;
            }
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans render_savings_summary()', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ), 'mes-parrainages-debug' );
            echo '<div class="error">Erreur dans le résumé des économies : ' . esc_html( $e->getMessage() ) . '</div>';
            return;
        }
        ?>
        <div class="savings-summary-section">
            <h3><?php esc_html_e( '📊 Résumé de vos remises', 'wc-tb-web-parrainage' ); ?></h3>
            <div class="savings-grid">
                <div class="savings-card">
                    <span class="savings-label">Remises actives :</span>
                    <span class="savings-value"><?php echo esc_html( $summary['active_discounts'] . ' sur ' . $summary['total_referrals'] . ' filleuls' ); ?></span>
                </div>
                <div class="savings-card">
                    <span class="savings-label">Économie mensuelle :</span>
                    <span class="savings-value"><?php echo esc_html( number_format( $summary['monthly_savings'], 2, ',', '' ) ); ?>€</span>
                </div>
                <div class="savings-card">
                    <span class="savings-label">Économies totales :</span>
                    <span class="savings-value"><?php 
                        // PROTECTION : Éviter l'affichage de timestamps comme montants
                        $total_savings = $summary['total_savings_to_date'] ?? 0;
                        
                        // Si c'est un nombre très grand (probable timestamp), afficher 0
                        if ( is_numeric( $total_savings ) && $total_savings > 100000 ) {
                            echo '0,00';
                        } else {
                            echo esc_html( $total_savings );
                        }
                    ?>€</span>
                </div>
                <div class="savings-card">
                    <span class="savings-label">Prochaine facturation :</span>
                    <span class="savings-value">
                        <?php 
                        // CORRECTION v2.8.2-fix13 : Affichage sécurisé de la date et montant
                        $billing_date = $summary['next_billing']['date'] ?? date('d-m-Y', strtotime('+1 month'));
                        $billing_amount = $summary['next_billing']['amount'] ?? '0,00';
                        
                        // DEBUG v2.9.3 : Log les valeurs reçues pour diagnostic
                        $logger = new \TBWeb\WCParrainage\Logger();
                        $logger->debug( 'AFFICHAGE NEXT_BILLING - Valeurs reçues', array(
                            'billing_date_raw' => $billing_date,
                            'billing_amount_raw' => $billing_amount,
                            'billing_amount_type' => gettype($billing_amount),
                            'summary_next_billing_complet' => $summary['next_billing'] ?? 'AUCUN'
                        ), 'mes-parrainages-debug' );
                        
                        // Vérifier que le montant n'est pas aberrant
                        $amount_clean = str_replace(',', '.', $billing_amount);
                        if (is_numeric($amount_clean) && floatval($amount_clean) > 10000) {
                            $billing_amount = '0,00';
                            $logger->warning( 'Montant aberrant détecté dans affichage, correction appliquée', array(
                                'amount_original' => $billing_amount,
                                'amount_clean' => $amount_clean,
                                'amount_corrected' => '0,00'
                            ), 'mes-parrainages-debug' );
                        }
                        
                        echo esc_html( $billing_date . ' (' . $billing_amount . '€)' );
                        
                        // DEBUG v2.9.3 : Log du HTML généré pour diagnostic JavaScript
                        $logger->debug( 'HTML GÉNÉRÉ FINAL', array(
                            'html_output' => $billing_date . ' (' . $billing_amount . '€)',
                            'billing_date' => $billing_date,
                            'billing_amount' => $billing_amount,
                            'billing_amount_length' => strlen($billing_amount)
                        ), 'mes-parrainages-debug' );
                        
                        // FORCE CACHE BUSTING v2.9.3
                        echo '<!-- Cache bust: ' . time() . ' -->'; 
                        ?>
                    </span>
                </div>
            </div>
            
            <?php if ( !empty( $summary['pending_actions'] ) ) : ?>
            <div class="pending-actions">
                <h4>⚠️ Actions en attente :</h4>
                <ul>
                    <?php foreach ( $summary['pending_actions'] as $action ) : ?>
                        <li><?php echo esc_html( $action['message'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * NOUVEAU v2.14.0 : Version sécurisée de render_savings_summary
     * 
     * @param int $user_subscription_id ID de l'abonnement utilisateur
     * @return void
     */
    private function render_savings_summary_safe( $user_subscription_id ) {
        try {
            $this->logger->info( '🔍 DÉBUT render_savings_summary_safe()', array( 'subscription_id' => $user_subscription_id ), 'mes-parrainages-debug' );
            
            // Récupération sécurisée du summary
            $summary = null;
            try {
                $summary = $this->data_provider->get_savings_summary( $user_subscription_id );
                $this->logger->info( '📊 Summary récupéré avec succès', array( 
                    'summary_type' => gettype( $summary ),
                    'summary_keys' => is_array( $summary ) ? array_keys( $summary ) : 'NOT_ARRAY'
                ), 'mes-parrainages-debug' );
            } catch ( Exception $e ) {
                $this->logger->error( '❌ Erreur get_savings_summary()', array( 'error' => $e->getMessage() ), 'mes-parrainages-debug' );
                $summary = null;
            }
            
            if ( empty( $summary ) || ! is_array( $summary ) ) {
                $this->logger->warning( '⚠️ Summary vide ou invalide - affichage basique', array(), 'mes-parrainages-debug' );
                $this->render_basic_summary( $user_subscription_id );
                return;
            }
            
            // Validation des données du summary
            $active_discounts = intval( $summary['active_discounts'] ?? 0 );
            $total_referrals = intval( $summary['total_referrals'] ?? 0 );
            $monthly_savings = floatval( $summary['monthly_savings'] ?? 0 );
            
            $this->logger->info( '✅ Données validées', array(
                'active_discounts' => $active_discounts,
                'total_referrals' => $total_referrals,
                'monthly_savings' => $monthly_savings
            ), 'mes-parrainages-debug' );
            
            ?>
            <div class="savings-summary-section">
                <h3><?php esc_html_e( '📊 Résumé de vos remises', 'wc-tb-web-parrainage' ); ?></h3>
                <div class="savings-grid">
                    <div class="savings-card">
                        <span class="savings-label">Remises actives :</span>
                        <span class="savings-value"><?php echo esc_html( $active_discounts . ' sur ' . $total_referrals . ' filleuls' ); ?></span>
                    </div>
                    <div class="savings-card">
                        <span class="savings-label">Économie mensuelle :</span>
                        <span class="savings-value"><?php echo esc_html( number_format( $monthly_savings, 2, ',', '' ) ); ?>€</span>
                    </div>
                    <div class="savings-card">
                        <span class="savings-label">Économies totales :</span>
                        <span class="savings-value"><?php 
                            $total_savings = $summary['total_savings_to_date'] ?? 0;
                            if ( is_numeric( $total_savings ) && $total_savings > 100000 ) {
                                echo '0,00';
                            } else {
                                echo esc_html( $total_savings );
                            }
                        ?>€</span>
                    </div>
                    <div class="savings-card">
                        <span class="savings-label">Prochaine facturation :</span>
                        <span class="savings-value">
                            <?php 
                            $billing_date = $summary['next_billing']['date'] ?? date('d-m-Y', strtotime('+1 month'));
                            $billing_amount = $summary['next_billing']['amount'] ?? '0,00';
                            echo esc_html( $billing_date . ' (' . $billing_amount . '€)' ); 
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php
            
            $this->logger->info( '✅ render_savings_summary_safe() terminé avec succès', array(), 'mes-parrainages-debug' );
            
        } catch ( Exception $e ) {
            $this->logger->error( '💥 ERREUR FATALE dans render_savings_summary_safe()', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ), 'mes-parrainages-debug' );
            
            // Fallback ultra-basique
            $this->render_basic_summary( $user_subscription_id );
        }
    }
    
    /**
     * NOUVEAU v2.14.0 : Affichage basique de secours
     * 
     * @param int $user_subscription_id ID de l'abonnement
     * @return void
     */
    private function render_basic_summary( $user_subscription_id ) {
        ?>
        <div class="savings-summary-section">
            <h3>📊 Résumé de vos remises</h3>
            <div class="savings-grid">
                <div class="savings-card">
                    <span class="savings-label">Statut :</span>
                    <span class="savings-value">Données en cours de chargement...</span>
                </div>
            </div>
            <p><em>Le calcul détaillé de vos économies sera disponible prochainement.</em></p>
        </div>
        <?php
    }
} 