<?php
namespace TBWeb\WCParrainage\Analytics;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire des modales d'aide pour les métriques analytics
 * 
 * Responsabilité unique : Gestion des modales d'explication des métriques
 * Principe SRP : Séparation affichage aide vs logique métier analytics
 * 
 * @since 2.13.0
 */
class HelpModalManager {
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Canal de logs spécialisé
     */
    const LOG_CHANNEL = 'help-modal-manager';
    
    /**
     * Option WordPress pour stocker les contenus
     */
    const OPTION_HELP_CONTENT = 'wc_tb_parrainage_help_content';
    const OPTION_USER_LANGUAGE = 'wc_tb_parrainage_user_help_language';
    
    /**
     * Constructor
     * 
     * @param Logger $logger Instance du logger
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        
        $this->logger->info(
            'HelpModalManager initialisé',
            array( 'version' => '2.13.0' ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Initialisation des hooks WordPress
     * 
     * @return void
     */
    public function init(): void {
        
        // Hook AJAX pour récupérer le contenu des modales
        add_action( 'wp_ajax_tb_analytics_get_help_content', array( $this, 'ajax_get_help_content' ) );
        add_action( 'wp_ajax_tb_analytics_set_help_language', array( $this, 'ajax_set_help_language' ) );
        
        // Initialiser le contenu par défaut si nécessaire
        add_action( 'admin_init', array( $this, 'maybe_initialize_help_content' ) );
        
        $this->logger->info(
            'HelpModalManager hooks enregistrés',
            array( 'hooks' => array( 'ajax_get_help_content', 'ajax_set_help_language' ) ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Charger les assets JavaScript/CSS pour les modales d'aide
     * 
     * @param string $hook Hook admin actuel
     * @return void
     */
    public function enqueue_help_assets( string $hook ): void {
        
        // Charger seulement sur page TB Parrainage
        if ( strpos( $hook, 'wc-tb-parrainage' ) === false ) {
            return;
        }
        
        // CSS pour les modales d'aide
        wp_enqueue_style(
            'tb-help-modals',
            WC_TB_PARRAINAGE_URL . 'assets/css/help-modals.css',
            array(),
            WC_TB_PARRAINAGE_VERSION
        );
        
        // JavaScript pour les modales d'aide
        wp_enqueue_script(
            'tb-help-modals',
            WC_TB_PARRAINAGE_URL . 'assets/js/help-modals.js',
            array( 'jquery', 'jquery-ui-dialog' ),
            WC_TB_PARRAINAGE_VERSION,
            true
        );
        
        // Localisation pour AJAX et textes
        wp_localize_script( 'tb-help-modals', 'tbHelpModals', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tb_help_modals_nonce' ),
            'currentLanguage' => $this->get_user_language(),
            'strings' => array(
                'loading' => __( 'Chargement...', 'wc-tb-web-parrainage' ),
                'error' => __( 'Erreur lors du chargement', 'wc-tb-web-parrainage' ),
                'close' => __( 'Fermer', 'wc-tb-web-parrainage' ),
                'language' => __( 'Langue', 'wc-tb-web-parrainage' )
            )
        ) );
        
        $this->logger->info(
            'Assets modales d\'aide chargés',
            array( 'hook' => $hook ),
            self::LOG_CHANNEL
        );
    }
    
    /**
     * Rendre l'icône d'aide pour une métrique
     * 
     * @param string $metric_key Clé de la métrique
     * @return void
     */
    public function render_help_icon( string $metric_key ): void {
        ?>
        <span class="tb-help-icon" data-metric="<?php echo esc_attr( $metric_key ); ?>" title="<?php esc_attr_e( 'Aide sur cette métrique', 'wc-tb-web-parrainage' ); ?>">
            <i class="dashicons dashicons-info-outline"></i>
        </span>
        <?php
    }
    
    /**
     * AJAX: Récupérer le contenu d'aide pour une métrique
     * 
     * @return void
     */
    public function ajax_get_help_content(): void {
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_help_modals_nonce' ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        $metric_key = sanitize_text_field( $_POST['metric'] ?? '' );
        $language = sanitize_text_field( $_POST['language'] ?? $this->get_user_language() );
        
        if ( empty( $metric_key ) ) {
            wp_send_json_error( __( 'Métrique non spécifiée', 'wc-tb-web-parrainage' ) );
        }
        
        try {
            
            $content = $this->get_help_content( $metric_key, $language );
            
            if ( empty( $content ) ) {
                wp_send_json_error( __( 'Contenu d\'aide non trouvé', 'wc-tb-web-parrainage' ) );
            }
            
            wp_send_json_success( array(
                'content' => $content,
                'metric' => $metric_key,
                'language' => $language
            ) );
            
        } catch ( \Exception $e ) {
            
            $this->logger->error(
                'Erreur récupération contenu aide',
                array( 'metric' => $metric_key, 'error' => $e->getMessage() ),
                self::LOG_CHANNEL
            );
            
            wp_send_json_error( __( 'Erreur lors du chargement de l\'aide', 'wc-tb-web-parrainage' ) );
        }
    }
    
    /**
     * AJAX: Définir la langue préférée de l'utilisateur
     * 
     * @return void
     */
    public function ajax_set_help_language(): void {
        
        // Vérification sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_help_modals_nonce' ) ) {
            wp_die( __( 'Token de sécurité invalide', 'wc-tb-web-parrainage' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) );
        }
        
        $language = sanitize_text_field( $_POST['language'] ?? 'fr' );
        
        // Valider la langue
        if ( ! in_array( $language, array( 'fr', 'en' ), true ) ) {
            wp_send_json_error( __( 'Langue non supportée', 'wc-tb-web-parrainage' ) );
        }
        
        // Sauvegarder la préférence utilisateur
        update_user_meta( get_current_user_id(), self::OPTION_USER_LANGUAGE, $language );
        
        wp_send_json_success( array(
            'language' => $language,
            'message' => __( 'Langue mise à jour', 'wc-tb-web-parrainage' )
        ) );
    }
    
    /**
     * Obtenir la langue préférée de l'utilisateur
     * 
     * @return string Code langue (fr/en)
     */
    private function get_user_language(): string {
        
        $user_language = get_user_meta( get_current_user_id(), self::OPTION_USER_LANGUAGE, true );
        
        // Fallback vers français par défaut
        return in_array( $user_language, array( 'fr', 'en' ), true ) ? $user_language : 'fr';
    }
    
    /**
     * Obtenir le contenu d'aide pour une métrique
     * 
     * @param string $metric_key Clé de la métrique
     * @param string $language Langue demandée
     * @return array|null Contenu d'aide ou null si non trouvé
     */
    private function get_help_content( string $metric_key, string $language ): ?array {
        
        $all_content = get_option( self::OPTION_HELP_CONTENT, array() );
        
        // Récupérer le contenu pour cette métrique et langue
        $content = $all_content[ $metric_key ][ $language ] ?? null;
        
        // Fallback vers français si contenu anglais manquant
        if ( empty( $content ) && $language === 'en' ) {
            $content = $all_content[ $metric_key ]['fr'] ?? null;
        }
        
        return $content;
    }
    
    /**
     * Initialiser le contenu d'aide par défaut si nécessaire
     * 
     * @return void
     */
    public function maybe_initialize_help_content(): void {
        
        $existing_content = get_option( self::OPTION_HELP_CONTENT, false );
        
        if ( false === $existing_content ) {
            $this->initialize_default_help_content();
        }
    }
    
    /**
     * Initialiser le contenu d'aide par défaut
     * 
     * @return void
     */
    private function initialize_default_help_content(): void {
        
        $default_content = array(
            'total_parrains' => array(
                'fr' => array(
                    'title' => 'Parrains Actifs',
                    'definition' => 'Nombre de clients qui bénéficient actuellement d\'une remise grâce au parrainage.',
                    'details' => array(
                        'Compté en temps réel',
                        'Inclut uniquement les remises actives',
                        'Exclut les remises expirées ou suspendues'
                    ),
                    'interpretation' => 'Plus le nombre est élevé, plus votre système de parrainage récompense vos clients fidèles. Idéalement, ce nombre devrait croître progressivement.',
                    'tips' => array(
                        'Encouragez vos clients à parrainer',
                        'Vérifiez que les remises sont bien appliquées'
                    )
                ),
                'en' => array(
                    'title' => 'Active Sponsors',
                    'definition' => 'Number of customers currently receiving a discount through the referral program.',
                    'details' => array(
                        'Counted in real-time',
                        'Includes only active discounts',
                        'Excludes expired or suspended discounts'
                    ),
                    'interpretation' => 'The higher the number, the more your referral system rewards your loyal customers. Ideally, this number should grow gradually.',
                    'tips' => array(
                        'Encourage your customers to refer',
                        'Check that discounts are properly applied'
                    )
                )
            ),
            'total_filleuls' => array(
                'fr' => array(
                    'title' => 'Filleuls Actifs',
                    'definition' => 'Nombre de nouveaux clients avec un abonnement actif qui ont utilisé un code parrain.',
                    'details' => array(
                        'Clients avec code parrain ET abonnement en cours',
                        'Génère des revenus récurrents',
                        'Résultat direct du système de parrainage'
                    ),
                    'interpretation' => 'Mesure l\'efficacité de votre stratégie d\'acquisition. Plus élevé = plus de nouveaux clients grâce au parrainage.',
                    'tips' => array(
                        'Optimisez votre processus de parrainage',
                        'Suivez le taux de conversion des codes'
                    )
                ),
                'en' => array(
                    'title' => 'Active Referrals',
                    'definition' => 'Number of new customers with an active subscription who used a referral code.',
                    'details' => array(
                        'Customers with referral code AND active subscription',
                        'Generates recurring revenue',
                        'Direct result of the referral system'
                    ),
                    'interpretation' => 'Measures the effectiveness of your acquisition strategy. Higher = more new customers through referrals.',
                    'tips' => array(
                        'Optimize your referral process',
                        'Track code conversion rates'
                    )
                )
            ),
            'monthly_total_revenue' => array(
                'fr' => array(
                    'title' => 'Revenus Mensuels HT',
                    'definition' => 'Total des ventes du mois en cours, hors taxes (tous clients confondus).',
                    'details' => array(
                        'Calculé automatiquement : total TTC - taxes',
                        'Période : 1er au dernier jour du mois actuel',
                        'Inclut TOUTES les commandes et abonnements (avec ou sans parrainage)',
                        'Vision globale de votre chiffre d\'affaires mensuel'
                    ),
                    'interpretation' => 'Indicateur principal de performance commerciale globale. À comparer avec les mois précédents pour mesurer l\'impact du parrainage sur votre CA total.',
                    'precision' => 'Ce montant inclut tous vos clients, pas seulement ceux issus du parrainage.',
                    'tips' => array(
                        'Comparez avec les mois précédents',
                        'Analysez l\'impact du parrainage sur le CA global'
                    )
                ),
                'en' => array(
                    'title' => 'Monthly Revenue (Excl. Tax)',
                    'definition' => 'Total sales for the current month, excluding taxes (all customers combined).',
                    'details' => array(
                        'Automatically calculated: total incl. tax - taxes',
                        'Period: 1st to last day of current month',
                        'Includes ALL orders and subscriptions (with or without referral)',
                        'Global view of your monthly revenue'
                    ),
                    'interpretation' => 'Main indicator of overall business performance. Compare with previous months to measure referral impact on total revenue.',
                    'precision' => 'This amount includes all your customers, not just those from referrals.',
                    'tips' => array(
                        'Compare with previous months',
                        'Analyze referral impact on global revenue'
                    )
                )
            ),
            'monthly_discounts' => array(
                'fr' => array(
                    'title' => 'Remises Mensuelles',
                    'definition' => 'Montant total des remises parrain actuellement actives.',
                    'details' => array(
                        'Somme des remises en cours (pas forcément appliquées ce mois)',
                        'Représente votre "investissement" en fidélisation',
                        'Mise à jour en temps réel'
                    ),
                    'interpretation' => 'Coût de votre stratégie de parrainage. À mettre en balance avec les revenus générés.',
                    'tips' => array(
                        'Surveillez l\'équilibre avec les revenus',
                        'Ajustez les montants si nécessaire'
                    )
                ),
                'en' => array(
                    'title' => 'Monthly Discounts',
                    'definition' => 'Total amount of currently active sponsor discounts.',
                    'details' => array(
                        'Sum of ongoing discounts (not necessarily applied this month)',
                        'Represents your "investment" in loyalty',
                        'Updated in real-time'
                    ),
                    'interpretation' => 'Cost of your referral strategy. Should be balanced against generated revenue.',
                    'tips' => array(
                        'Monitor balance with revenue',
                        'Adjust amounts if necessary'
                    )
                )
            ),
            'roi_current_month' => array(
                'fr' => array(
                    'title' => 'ROI Mois Actuel',
                    'definition' => 'Rentabilité de votre système de parrainage basée sur les revenus générés par les filleuls.',
                    'formula' => '(Revenus Filleuls - Remises Parrain) ÷ Remises Parrain × 100',
                    'details' => array(
                        'Revenus : uniquement ceux générés par les clients ayant utilisé un code parrain ce mois',
                        'Remises : montants des remises parrain actuellement actives',
                        'Mesure la performance spécifique du système de parrainage'
                    ),
                    'example' => 'Avec 60€ de revenus filleuls et 15€ de remises parrain : Profit = 45€, ROI = 300%',
                    'interpretation' => array(
                        'Positif : le parrainage génère plus qu\'il ne coûte',
                        'Négatif : le parrainage coûte plus qu\'il ne rapporte',
                        '300% : excellent retour sur investissement parrainage'
                    ),
                    'precision' => 'Ce ROI se base uniquement sur l\'activité parrainage, pas sur votre chiffre d\'affaires global.',
                    'tips' => array(
                        'Visez un ROI positif et croissant',
                        'Optimisez les montants de remises si ROI négatif'
                    )
                ),
                'en' => array(
                    'title' => 'Current Month ROI',
                    'definition' => 'Profitability of your referral system based on revenue generated by referrals.',
                    'formula' => '(Referral Revenue - Sponsor Discounts) ÷ Sponsor Discounts × 100',
                    'details' => array(
                        'Revenue: only from customers who used a referral code this month',
                        'Discounts: amounts of currently active sponsor discounts',
                        'Measures specific performance of the referral system'
                    ),
                    'example' => 'With €60 referral revenue and €15 sponsor discounts: Profit = €45, ROI = 300%',
                    'interpretation' => array(
                        'Positive: referral generates more than it costs',
                        'Negative: referral costs more than it generates',
                        '300%: excellent referral return on investment'
                    ),
                    'precision' => 'This ROI is based only on referral activity, not your global revenue.',
                    'tips' => array(
                        'Aim for positive and growing ROI',
                        'Optimize discount amounts if ROI is negative'
                    )
                )
            ),
            'total_codes_used' => array(
                'fr' => array(
                    'title' => 'Codes Utilisés',
                    'definition' => 'Nombre total de codes parrain utilisés depuis le lancement.',
                    'details' => array(
                        'Compteur cumulatif',
                        'Inclut tous les codes valides saisis',
                        'Mesure l\'adoption globale du système'
                    ),
                    'interpretation' => 'Indicateur de popularité et d\'adoption de votre système de parrainage.',
                    'tips' => array(
                        'Encouragez le partage des codes',
                        'Facilitez la saisie des codes'
                    )
                ),
                'en' => array(
                    'title' => 'Codes Used',
                    'definition' => 'Total number of referral codes used since launch.',
                    'details' => array(
                        'Cumulative counter',
                        'Includes all valid codes entered',
                        'Measures overall system adoption'
                    ),
                    'interpretation' => 'Indicator of popularity and adoption of your referral system.',
                    'tips' => array(
                        'Encourage code sharing',
                        'Make code entry easier'
                    )
                )
            ),
            'monthly_events' => array(
                'fr' => array(
                    'title' => 'Événements ce mois',
                    'definition' => 'Activités liées au parrainage enregistrées ce mois.',
                    'details' => array(
                        'Nouveaux parrainages',
                        'Application de remises',
                        'Activations d\'abonnements',
                        'Autres actions système'
                    ),
                    'interpretation' => 'Mesure l\'activité générale du système. Plus élevé = système plus actif.',
                    'tips' => array(
                        'Surveillez les tendances mensuelles',
                        'Identifiez les pics d\'activité'
                    )
                ),
                'en' => array(
                    'title' => 'Events This Month',
                    'definition' => 'Referral-related activities recorded this month.',
                    'details' => array(
                        'New referrals',
                        'Discount applications',
                        'Subscription activations',
                        'Other system actions'
                    ),
                    'interpretation' => 'Measures overall system activity. Higher = more active system.',
                    'tips' => array(
                        'Monitor monthly trends',
                        'Identify activity peaks'
                    )
                )
            ),
            'webhooks_sent' => array(
                'fr' => array(
                    'title' => 'Webhooks Envoyés',
                    'definition' => 'Notifications automatiques envoyées à vos services externes.',
                    'details' => array(
                        'Intégrations avec autres plateformes',
                        'Synchronisation des données',
                        'Suivi technique du système'
                    ),
                    'interpretation' => 'Indicateur technique de bon fonctionnement des intégrations.',
                    'tips' => array(
                        'Vérifiez que vos intégrations fonctionnent',
                        'Surveillez les échecs d\'envoi'
                    )
                ),
                'en' => array(
                    'title' => 'Webhooks Sent',
                    'definition' => 'Automatic notifications sent to your external services.',
                    'details' => array(
                        'Integrations with other platforms',
                        'Data synchronization',
                        'Technical system monitoring'
                    ),
                    'interpretation' => 'Technical indicator of integration health.',
                    'tips' => array(
                        'Check that your integrations work',
                        'Monitor sending failures'
                    )
                )
            ),
            'system_health' => array(
                'fr' => array(
                    'title' => 'Santé du Système',
                    'definition' => 'Score global évaluant la performance de votre système de parrainage.',
                    'criteria' => array(
                        'Activité : présence de parrains et filleuls',
                        'Ratio : équilibre entre parrains et filleuls',
                        'Rentabilité : revenus vs remises',
                        'Croissance : taux de conversion des codes'
                    ),
                    'levels' => array(
                        'Excellent (75-100%) : système très performant',
                        'Bon (50-74%) : système fonctionnel',
                        'Attention (25-49%) : améliorations nécessaires',
                        'Critique (0-24%) : problèmes majeurs'
                    ),
                    'interpretation' => 'Score synthétique de performance. Surveillez les indicateurs en rouge pour identifier les axes d\'amélioration.',
                    'tips' => array(
                        'Corrigez les points en rouge en priorité',
                        'Suivez les recommandations affichées'
                    )
                ),
                'en' => array(
                    'title' => 'System Health',
                    'definition' => 'Global score evaluating your referral system performance.',
                    'criteria' => array(
                        'Activity: presence of sponsors and referrals',
                        'Ratio: balance between sponsors and referrals',
                        'Profitability: revenue vs discounts',
                        'Growth: code conversion rate'
                    ),
                    'levels' => array(
                        'Excellent (75-100%): very high performance system',
                        'Good (50-74%): functional system',
                        'Warning (25-49%): improvements needed',
                        'Critical (0-24%): major issues'
                    ),
                    'interpretation' => 'Synthetic performance score. Monitor red indicators to identify improvement areas.',
                    'tips' => array(
                        'Fix red points as priority',
                        'Follow displayed recommendations'
                    )
                )
            )
        );
        
        update_option( self::OPTION_HELP_CONTENT, $default_content );
        
        $this->logger->info(
            'Contenu d\'aide par défaut initialisé',
            array( 'metrics_count' => count( $default_content ) ),
            self::LOG_CHANNEL
        );
    }
}
