<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestionnaire des modales d'aide pour la page Mon Compte "mes-parrainages"
 * Version CORRIGÉE avec Template Modal System fonctionnel
 * 
 * @package TBWeb\WCParrainage
 * @since 2.14.1
 * @version 2.16.2
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
                'cache_duration' => 1800,
                'enable_keyboard_nav' => true,
                'load_dashicons' => true,
                'css_prefix' => 'tb-modal-client',
                'ajax_action_prefix' => 'tb_modal_client_account',
                'storage_option' => 'tb_modal_content_client_account'
            ],
            self::MODAL_NAMESPACE
        );
        
        if ( $this->logger ) {
            $this->logger->info(
                'MyAccountModalManager initialisé avec Template Modal System',
                [ 'namespace' => self::MODAL_NAMESPACE ],
                'my-account-modals'
            );
        }
    }
    
    /**
     * Initialisation des hooks et du contenu
     * 
     * @return void
     */
    public function init(): void {
        
        // Initialiser le Template Modal Manager
        $this->modal_manager->init();
        
        // Charger le contenu des modales
        $this->setup_modal_contents();
        
        // Hook pour charger les assets au bon moment
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ], 100 );
        
        if ( $this->logger ) {
            $this->logger->info(
                'MyAccountModalManager hooks enregistrés et contenu configuré',
                [],
                'my-account-modals'
            );
        }
    }
    
    /**
     * Vérifier et charger les assets si nécessaire
     */
    public function maybe_enqueue_assets(): void {
        if ( \is_wc_endpoint_url( 'mes-parrainages' ) || \is_account_page() ) {
            $this->enqueue_modal_assets();
        }
    }
    
    /**
     * Charger les assets des modales
     * 
     * @return void
     */
    public function enqueue_modal_assets(): void {
        
        // Charger les assets du Template Modal System
        $this->modal_manager->enqueue_modal_assets();
        
        // Ajouter un script adaptateur pour la compatibilité
        $this->add_compatibility_script();
        
        if ( $this->logger ) {
            $this->logger->info(
                'Assets Template Modal System chargés pour la page client',
                [],
                'my-account-modals'
            );
        }
    }
    
    /**
     * Ajouter un script de compatibilité pour l'ancien format
     */
    private function add_compatibility_script(): void {
        $script = "
        jQuery(document).ready(function($) {
            // Créer l'objet global pour le namespace client_account
            if (typeof window.tbModalClient_account === 'undefined' && typeof window.TBTemplateModals !== 'undefined') {
                window.tbModalClient_account = new window.TBTemplateModals({
                    namespace: 'client_account',
                    modalWidth: 600,
                    modalMaxHeight: 500,
                    enableCache: true,
                    ajaxUrl: '" . admin_url('admin-ajax.php') . "',
                    nonce: '" . wp_create_nonce('tb_modal_client_account_nonce') . "',
                    ajaxActions: {
                        getContent: 'tb_modal_client_account_get_content'
                    },
                    cssClasses: {
                        icon: 'tb-modal-client-icon',
                        modal: 'tb-modal-client-modal',
                        content: 'tb-modal-client-content'
                    }
                });
            }
            
            // Adapter les anciens sélecteurs si nécessaire
            $('.tb-client-help-icon').each(function() {
                var metric = $(this).data('metric');
                if (metric) {
                    $(this).addClass('tb-modal-client-icon')
                           .attr('data-modal-key', metric)
                           .attr('data-namespace', 'client_account');
                }
            });
        });
        ";
        
        wp_add_inline_script( 'tb-template-modals-' . self::MODAL_NAMESPACE, $script );
    }
    
    /**
     * Configurer le contenu des modales
     * 
     * @return void
     */
    public function setup_modal_contents(): void {
        
        $modal_contents = [
            'active_discounts' => [
                'title' => 'Vos remises actives',
                'definition' => 'Le nombre de remises actuellement appliquées sur votre abonnement grâce à vos filleuls actifs.',
                'details' => [
                    'Chaque filleul actif vous donne droit à une remise',
                    'Les remises sont cumulables jusqu\'à la limite définie',
                    'Une remise est active tant que le filleul maintient son abonnement'
                ],
                'interpretation' => 'Plus ce nombre est élevé, plus votre remise mensuelle est importante.',
                'example' => 'Exemple : Si vous avez 3 remises actives de 10€ chacune, vous économisez 30€ par mois.',
                'tips' => [
                    'Parrainez régulièrement pour maintenir vos remises',
                    'Vérifiez que vos filleuls restent actifs',
                    'Consultez les conditions de cumul des remises'
                ]
            ],
            'monthly_savings' => [
                'title' => 'Votre économie mensuelle',
                'definition' => 'Le montant total que vous économisez chaque mois grâce à vos parrainages actifs.',
                'formula' => 'Économie = Nombre de remises × Montant unitaire',
                'interpretation' => 'Cette économie est automatiquement déduite de votre prochaine facture.',
                'example' => 'Avec 5 filleuls actifs et une remise de 8€ par filleul, vous économisez 40€/mois.',
                'tips' => [
                    'Maximisez vos économies en parrainant de nouveaux clients',
                    'Suivez l\'évolution de vos économies mois par mois'
                ]
            ],
            'total_savings' => [
                'title' => 'Économies depuis le début',
                'definition' => 'Le montant total cumulé de toutes vos économies depuis votre premier parrainage.',
                'details' => [
                    'Inclut toutes les remises appliquées depuis le début',
                    'Prend en compte les remises expirées',
                    'Représente votre gain total grâce au programme de parrainage'
                ],
                'interpretation' => 'Ce montant représente l\'argent réel économisé sur vos factures.',
                'tips' => [
                    'Célébrez vos économies importantes',
                    'Partagez votre succès pour motiver vos futurs filleuls'
                ]
            ],
            'next_billing' => [
                'title' => 'Votre prochaine facture',
                'definition' => 'La date et le montant estimé de votre prochaine facture après application des remises.',
                'details' => [
                    'Date basée sur votre cycle de facturation',
                    'Montant incluant les remises actives',
                    'Estimation susceptible de changer si vos remises évoluent'
                ],
                'interpretation' => 'Ce montant peut diminuer si vous parrainez de nouveaux clients avant la date de facturation.',
                'example' => 'Facture normale : 50€, Avec 3 remises de 10€ : 20€ à payer.',
                'precision' => 'Les remises sont appliquées automatiquement lors de la génération de votre facture.',
                'tips' => [
                    'Vérifiez que vos remises sont bien prises en compte',
                    'Anticipez les changements si vous savez qu\'un filleul va résilier'
                ]
            ]
        ];
        
        // Charger tout le contenu dans le Template Modal System
        $success = $this->modal_manager->set_batch_modal_content( $modal_contents );
        
        if ( $this->logger ) {
            if ( $success ) {
                $this->logger->info(
                    'Contenu des modales client configuré avec succès',
                    [ 'modals_count' => count( $modal_contents ) ],
                    'my-account-modals'
                );
            } else {
                $this->logger->error(
                    'Échec configuration contenu modales',
                    [],
                    'my-account-modals'
                );
            }
        }
    }
    
    /**
     * Rendre une icône d'aide
     * 
     * @param string $metric_key Clé de la métrique
     * @param string $title Titre de la modal
     * @return string HTML de l'icône
     */
    public function render_help_icon( string $metric_key, string $title = '' ): string {
        
        ob_start();
        $this->modal_manager->render_help_icon( $metric_key, [
            'icon' => 'dashicons-editor-help',
            'title' => $title ?: __( 'Cliquez pour en savoir plus', 'wc-tb-web-parrainage' ),
            'position' => 'inline',
            'size' => 'normal'
        ] );
        return ob_get_clean();
    }
    
    /**
     * Obtenir les statistiques d'utilisation
     */
    public function get_usage_stats(): array {
        return $this->modal_manager->get_usage_stats();
    }
}
