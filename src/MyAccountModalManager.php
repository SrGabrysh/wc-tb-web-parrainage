<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire des modales d'aide pour la page Mon Compte "mes-parrainages"
 * Migration vers le Template Modal System
 * 
 * @package TBWeb\WCParrainage
 * @since 2.14.1
 */
class MyAccountModalManager {
    
    /**
     * @var Logger Instance du système de logs
     */
    private $logger;
    
    /**
     * @var TemplateModalManager Gestionnaire de modales
     */
    private $modal_manager;
    
    /**
     * Namespace pour les modales client
     */
    const MODAL_NAMESPACE = 'client_account';
    
    /**
     * Constructeur
     * 
     * @param Logger $logger Instance du système de logs
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
        
        // Créer l'instance du Template Modal Manager pour client
        $this->modal_manager = new TemplateModalManager(
            $logger,
            [
                'modal_width' => 600,
                'modal_max_height' => 500,
                'enable_cache' => true,
                'cache_duration' => 1800, // 30 minutes pour client
                'enable_keyboard_nav' => true,
                'load_dashicons' => true,
                'css_prefix' => 'tb-modal-client',
                'ajax_action_prefix' => 'tb_modal_client',
                'storage_option' => 'tb_modal_content_client'
            ],
            self::MODAL_NAMESPACE
        );
        
        $this->logger->info(
            'MyAccountModalManager initialisé avec Template Modal System',
            [ 'namespace' => self::MODAL_NAMESPACE ],
            'my-account-modals'
        );
    }
    
    /**
     * Initialisation des hooks et du contenu
     * 
     * @return void
     */
    public function init(): void {
        
        // Initialiser le Template Modal Manager
        $this->modal_manager->init();
        
        // Charger le contenu immédiatement plutôt qu'en hook
        // pour éviter les problèmes de timing
        $this->setup_modal_contents();
        
        $this->logger->info(
            'MyAccountModalManager hooks enregistrés',
            [],
            'my-account-modals'
        );
    }
    
    /**
     * Charger les assets des modales sur les bonnes pages
     * 
     * @return void
     */
    public function enqueue_modal_assets(): void {
        
        // Charger seulement sur les pages Mon Compte
        if ( ! \is_wc_endpoint_url( 'mes-parrainages' ) && ! \is_account_page() ) {
            return;
        }
        
        // Charger les assets du Template Modal System
        $this->modal_manager->enqueue_modal_assets();
        
        $this->logger->info(
            'Assets modales client chargés',
            [ 'hook' => \current_action() ],
            'my-account-modals'
        );
    }
    
    /**
     * Configurer le contenu des modales
     * 
     * @return void
     */
    public function setup_modal_contents(): void {
        
        // Ne pas vérifier la page ici pour éviter les problèmes de timing
        // Le contenu sera chargé une seule fois au démarrage
        
        // Définir tout le contenu en une fois
        $modal_contents = [
            'active_discounts' => [
                'title' => \__( 'Vos remises actives', 'wc-tb-web-parrainage' ),
                'definition' => 'Le nombre de remises actuellement appliquées sur votre abonnement grâce à vos filleuls.',
                'details' => [
                    'Chaque filleul actif vous fait bénéficier d\'une remise',
                    'La remise correspond à 20% du montant TTC de l\'abonnement du filleul',
                    'Les remises restent actives tant que les filleuls conservent leur abonnement'
                ],
                'interpretation' => 'Plus vous avez de filleuls actifs, plus vos économies mensuelles sont importantes.',
                'example' => 'Si votre filleul paie 71,99€ TTC/mois, vous économisez 14,40€/mois (20% de 71,99€). Avec 2 filleuls à 71,99€ TTC chacun, vous économisez 28,80€/mois au total.',
                'tips' => [
                    'Partagez votre code avec vos proches pour augmenter vos remises',
                    'Accompagnez vos filleuls pour qu\'ils restent satisfaits de nos services',
                    'Vérifiez régulièrement que vos remises sont bien appliquées'
                ]
            ],
            
            'monthly_savings' => [
                'title' => \__( 'Votre économie mensuelle', 'wc-tb-web-parrainage' ),
                'definition' => 'Le montant total que vous économisez chaque mois grâce au parrainage.',
                'formula' => 'Somme de toutes vos remises actives = 20% du montant TTC de chacun de vos filleuls',
                'details' => [
                    'Calcul automatique basé sur les abonnements actifs de vos filleuls',
                    'Montant variable selon les formules d\'abonnement choisies',
                    'Mise à jour en temps réel selon les changements d\'abonnements'
                ],
                'example' => '• 1 filleul à 71,99€ TTC = 14,40€/mois d\'économie\n• 2 filleuls à 71,99€ TTC = 28,80€/mois d\'économie\n• 3 filleuls à 71,99€ TTC = 43,20€/mois d\'économie\n• 5 filleuls à 71,99€ TTC = 72€/mois d\'économie (abonnement gratuit !)',
                'interpretation' => 'Votre économie varie selon les montants d\'abonnement de vos filleuls. Plus ils paient, plus vous économisez !',
                'tips' => [
                    'Orientez vos filleuls vers les formules qui leur conviennent le mieux',
                    'Un filleul satisfait reste plus longtemps abonné',
                    'Suivez l\'évolution de vos économies dans cette section'
                ]
            ],
            
            'total_savings' => [
                'title' => \__( 'Vos économies depuis le début', 'wc-tb-web-parrainage' ),
                'definition' => 'Le montant total économisé depuis votre premier parrainage réussi.',
                'details' => [
                    'Additionnement de toutes les remises appliquées sur vos factures',
                    'Inclut les remises des filleuls actuels et passés',
                    'Historique complet de votre activité de parrain'
                ],
                'interpretation' => 'Ce montant représente l\'argent que vous avez économisé grâce à vos recommandations. C\'est aussi le signe que vous avez aidé plusieurs personnes à découvrir nos services !',
                'precision' => 'Les montants sont calculés sur la base des remises effectivement appliquées sur vos factures.',
                'tips' => [
                    'Plus vos filleuls restent longtemps abonnés, plus vos économies totales augmentent',
                    'Pensez à accompagner vos filleuls dans leur découverte de nos services',
                    'Partagez vos économies avec vos proches pour les motiver'
                ]
            ],
            
            'next_billing' => [
                'title' => \__( 'Votre prochaine facture', 'wc-tb-web-parrainage' ),
                'definition' => 'La date et le montant de votre prochaine facture après application de vos remises parrainage.',
                'details' => [
                    'Date : Le jour où votre prochaine facture sera émise',
                    'Montant réduit : Le prix que vous paierez après remises',
                    'Prix normal : Le prix sans les remises (barré pour comparaison)'
                ],
                'example' => 'Prix normal : 71,99€\nVotre prix : 56,99€ (avec 1 filleul actif)\nDate : 14 septembre 2025',
                'interpretation' => 'Ce montant peut varier si de nouveaux filleuls s\'inscrivent ou si certains résilient leur abonnement avant votre prochaine facturation.',
                'precision' => 'Les remises sont appliquées automatiquement lors de la génération de votre facture.',
                'tips' => [
                    'Vérifiez que vos remises sont bien prises en compte',
                    'Anticipez les changements si vous savez qu\'un filleul va résilier',
                    'Contactez le support en cas de question sur votre facturation'
                ]
            ]
        ];
        
        // Charger tout le contenu
        $success = $this->modal_manager->set_batch_modal_content( $modal_contents );
        
        if ( $success ) {
            $this->logger->info(
                'Contenu modales client configuré avec succès',
                [ 'modals_count' => count( $modal_contents ) ],
                'my-account-modals'
            );
        } else {
            $this->logger->error(
                'Échec configuration contenu modales client',
                [ 'modals_count' => count( $modal_contents ) ],
                'my-account-modals'
            );
        }
    }
    
    /**
     * Rendre une icône d'aide avec le nouveau système
     * 
     * @param string $metric_key Clé de la métrique
     * @param string $title Titre de la modal (optionnel)
     * @param array $options Options d'affichage
     * @return string HTML de l'icône
     */
    public function render_help_icon( string $metric_key, string $title = '', array $options = [] ): string {
        
        // Options par défaut pour les modales client
        $default_options = [
            'icon' => 'dashicons-editor-help', // Même icône que l'ancien système
            'title' => ! empty( $title ) ? $title : \__( 'Cliquez pour en savoir plus', 'wc-tb-web-parrainage' ),
            'position' => 'inline',
            'size' => 'normal'
        ];
        
        $final_options = \wp_parse_args( $options, $default_options );
        
        // Capturer le output
        ob_start();
        $this->modal_manager->render_help_icon( $metric_key, $final_options );
        $html = ob_get_clean();
        
        $this->logger->debug(
            'Icône d\'aide rendue',
            [ 'metric_key' => $metric_key, 'title' => $title ],
            'my-account-modals'
        );
        
        return $html;
    }
    
    /**
     * Obtenir les statistiques d'utilisation des modales
     * 
     * @return array Statistiques
     */
    public function get_usage_stats(): array {
        return $this->modal_manager->get_usage_stats();
    }
    
    /**
     * Vider le cache des modales (utile pour développement)
     * 
     * @return bool Succès de l'opération
     */
    public function clear_modal_cache(): bool {
        return $this->modal_manager->cleanup_modal_data();
    }
    
    /**
     * Méthode utilitaire pour tester le système
     * 
     * @return array État du système
     */
    public function get_system_status(): array {
        return [
            'namespace' => self::MODAL_NAMESPACE,
            'template_manager_initialized' => isset( $this->modal_manager ),
            'usage_stats' => $this->get_usage_stats(),
            'timestamp' => \current_time( 'Y-m-d H:i:s' )
        ];
    }
}
