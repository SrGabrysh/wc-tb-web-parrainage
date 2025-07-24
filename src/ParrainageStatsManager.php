<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParrainageStatsManager {
    
    // Constantes (éviter magic numbers)
    const DEFAULT_PER_PAGE = 50;
    const MAX_EXPORT_RECORDS = 10000;
    const CACHE_DURATION = 300; // 5 minutes
    const AJAX_ACTION_FILTER = 'tb_parrainage_filter_data';
    const AJAX_ACTION_EXPORT = 'tb_parrainage_export_data';
    const AJAX_ACTION_INLINE_EDIT = 'tb_parrainage_inline_edit';
    
    private $logger;
    private $data_provider;
    private $exporter;
    private $validator;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
        $this->data_provider = new ParrainageDataProvider( $logger );
        $this->exporter = new ParrainageExporter( $logger );
        $this->validator = new ParrainageValidator( $logger );
    }
    
    /**
     * Initialiser les hooks
     */
    public function init() {
        // Actions AJAX pour l'interface parrainage
        add_action( 'wp_ajax_' . self::AJAX_ACTION_FILTER, array( $this, 'handle_ajax_filter' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION_EXPORT, array( $this, 'handle_ajax_export' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION_INLINE_EDIT, array( $this, 'handle_ajax_inline_edit' ) );
        
        $this->logger->debug( 
            'ParrainageStatsManager initialisé',
            array(),
            'parrainage-stats-manager'
        );
    }
    
    /**
     * Rendre l'interface de parrainage
     */
    public function render_parrainage_interface() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès non autorisé', 'wc-tb-web-parrainage' ) );
        }
        
        // Récupérer les paramètres depuis l'URL
        $current_filters = $this->get_current_filters();
        $current_pagination = $this->get_current_pagination();
        
        // Valider les paramètres
        $validated_filters = $this->validator->validate_filters( $current_filters );
        $validated_pagination = $this->validator->validate_pagination( $current_pagination );
        
        // Récupérer les données
        $parrainage_data = $this->data_provider->get_parrainage_data( $validated_filters, $validated_pagination );
        $total_count = $this->data_provider->count_total_parrainages( $validated_filters );
        
        // Données pour l'interface
        $parrain_list = $this->data_provider->get_parrain_list();
        $product_list = $this->data_provider->get_product_list();
        $status_list = $this->data_provider->get_subscription_statuses();
        
        // Calculer la pagination
        $pagination_data = $this->calculate_pagination( $total_count, $validated_pagination );
        
        ?>
        <div class="parrainage-interface-container">
            <h2><?php esc_html_e( 'Données de Parrainage', 'wc-tb-web-parrainage' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Consultation des parrainages regroupés par parrain avec leurs filleuls.', 'wc-tb-web-parrainage' ); ?>
            </p>
            
            <!-- Barre de filtres -->
            <div class="parrainage-filters">
                <form method="get" id="parrainage-filters-form">
                    <input type="hidden" name="page" value="wc-tb-parrainage">
                    <input type="hidden" name="tab" value="parrainage">
                    
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="date_from"><?php esc_html_e( 'Du', 'wc-tb-web-parrainage' ); ?></label>
                            <input type="date" 
                                   id="date_from" 
                                   name="date_from" 
                                   value="<?php echo esc_attr( $validated_filters['date_from'] ?? '' ); ?>" 
                                   class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to"><?php esc_html_e( 'Au', 'wc-tb-web-parrainage' ); ?></label>
                            <input type="date" 
                                   id="date_to" 
                                   name="date_to" 
                                   value="<?php echo esc_attr( $validated_filters['date_to'] ?? '' ); ?>" 
                                   class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="parrain_search"><?php esc_html_e( 'Parrain', 'wc-tb-web-parrainage' ); ?></label>
                            <input type="text" 
                                   id="parrain_search" 
                                   name="parrain_search" 
                                   value="<?php echo esc_attr( $validated_filters['parrain_search'] ?? '' ); ?>" 
                                   placeholder="<?php esc_attr_e( 'Nom ou email...', 'wc-tb-web-parrainage' ); ?>"
                                   class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="product_id"><?php esc_html_e( 'Produit', 'wc-tb-web-parrainage' ); ?></label>
                            <select id="product_id" name="product_id" class="filter-input">
                                <option value=""><?php esc_html_e( 'Tous les produits', 'wc-tb-web-parrainage' ); ?></option>
                                <?php foreach ( $product_list as $product ) : ?>
                                    <option value="<?php echo esc_attr( $product['id'] ); ?>" 
                                            <?php selected( $validated_filters['product_id'] ?? '', $product['id'] ); ?>>
                                        <?php echo esc_html( $product['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="subscription_status"><?php esc_html_e( 'Statut', 'wc-tb-web-parrainage' ); ?></label>
                            <select id="subscription_status" name="subscription_status" class="filter-input">
                                <option value=""><?php esc_html_e( 'Tous les statuts', 'wc-tb-web-parrainage' ); ?></option>
                                <?php foreach ( $status_list as $status_key => $status_label ) : ?>
                                    <option value="<?php echo esc_attr( $status_key ); ?>" 
                                            <?php selected( $validated_filters['subscription_status'] ?? '', $status_key ); ?>>
                                        <?php echo esc_html( $status_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Filtrer', 'wc-tb-web-parrainage' ); ?>
                        </button>
                        <a href="?page=wc-tb-parrainage&tab=parrainage" class="button">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e( 'Réinitialiser', 'wc-tb-web-parrainage' ); ?>
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Barre d'outils -->
            <div class="parrainage-toolbar">
                <div class="toolbar-left">
                    <button type="button" id="export-csv" class="button" data-format="csv">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e( 'Export CSV', 'wc-tb-web-parrainage' ); ?>
                    </button>
                    <button type="button" id="export-excel" class="button" data-format="excel">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e( 'Export Excel', 'wc-tb-web-parrainage' ); ?>
                    </button>
                    <button type="button" id="refresh-data" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Actualiser', 'wc-tb-web-parrainage' ); ?>
                    </button>
                </div>
                
                <div class="toolbar-right">
                    <label for="per-page-selector"><?php esc_html_e( 'Affichage :', 'wc-tb-web-parrainage' ); ?></label>
                    <select id="per-page-selector" name="per_page">
                        <option value="25" <?php selected( $validated_pagination['per_page'], 25 ); ?>>25 par page</option>
                        <option value="50" <?php selected( $validated_pagination['per_page'], 50 ); ?>>50 par page</option>
                        <option value="100" <?php selected( $validated_pagination['per_page'], 100 ); ?>>100 par page</option>
                        <option value="200" <?php selected( $validated_pagination['per_page'], 200 ); ?>>200 par page</option>
                    </select>
                </div>
            </div>
            
            <!-- Résultats et pagination -->
            <div class="parrainage-results-info">
                <?php 
                $start = ( $validated_pagination['page'] - 1 ) * $validated_pagination['per_page'] + 1;
                $end = min( $start + count( $parrainage_data['parrains'] ) - 1, $total_count );
                ?>
                <p>
                    <?php 
                    printf( 
                        esc_html__( 'Affichage de %d à %d sur %d parrainages', 'wc-tb-web-parrainage' ),
                        $start,
                        $end,
                        $total_count
                    ); 
                    ?>
                </p>
            </div>
            
            <!-- Tableau principal -->
            <div class="parrainage-table-container">
                <?php $this->render_parrainage_table( $parrainage_data ); ?>
            </div>
            
            <!-- Pagination -->
            <?php $this->render_pagination( $pagination_data, $validated_pagination ); ?>
        </div>
        
        <!-- Nonces pour AJAX -->
        <script type="text/javascript">
            var tbParrainageData = {
                ajaxurl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                nonce: '<?php echo wp_create_nonce( 'tb_parrainage_admin_action' ); ?>',
                current_filters: <?php echo wp_json_encode( $validated_filters ); ?>,
                current_pagination: <?php echo wp_json_encode( $validated_pagination ); ?>
            };
        </script>
        <?php
    }
    
    /**
     * Rendre le tableau de parrainage
     */
    private function render_parrainage_table( $data ) {
        if ( empty( $data['parrains'] ) ) {
            ?>
            <div class="no-parrainage-data">
                <p><?php esc_html_e( 'Aucun parrainage trouvé avec les filtres appliqués.', 'wc-tb-web-parrainage' ); ?></p>
            </div>
            <?php
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped parrainage-table">
            <thead>
                <tr>
                    <th class="column-parrain"><?php esc_html_e( 'Parrain', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-filleuls"><?php esc_html_e( 'Filleul(s)', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-date"><?php esc_html_e( 'Date', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-produit"><?php esc_html_e( 'Produit', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-avantage"><?php esc_html_e( 'Avantage', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-statut"><?php esc_html_e( 'Statut Abonnement', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-montant"><?php esc_html_e( 'Montant', 'wc-tb-web-parrainage' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data['parrains'] as $parrain_data ) : ?>
                    <?php $this->render_parrain_row( $parrain_data ); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Rendre une ligne de parrain avec ses filleuls
     */
    private function render_parrain_row( $parrain_data ) {
        $parrain = $parrain_data['parrain'];
        $filleuls = $parrain_data['filleuls'];
        $filleuls_count = count( $filleuls );
        
        foreach ( $filleuls as $index => $filleul ) {
            ?>
            <tr class="<?php echo $index === 0 ? 'parrain-row' : 'filleul-row'; ?>">
                <?php if ( $index === 0 ) : ?>
                    <!-- Cellule parrain avec rowspan -->
                    <td class="column-parrain" rowspan="<?php echo esc_attr( $filleuls_count ); ?>">
                        <div class="parrain-info">
                            <strong class="parrain-nom">
                                <?php if ( ! empty( $parrain['user_link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $parrain['user_link'] ); ?>" target="_blank">
                                        <?php echo esc_html( $parrain['nom'] ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html( $parrain['nom'] ); ?>
                                <?php endif; ?>
                            </strong>
                            <br>
                            <span class="parrain-email"><?php echo esc_html( $parrain['email'] ); ?></span>
                            <br>
                            <span class="parrain-id">
                                <?php esc_html_e( 'ID:', 'wc-tb-web-parrainage' ); ?> 
                                <?php if ( ! empty( $parrain['subscription_link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $parrain['subscription_link'] ); ?>" target="_blank">
                                        <?php echo esc_html( $parrain['subscription_id'] ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html( $parrain['subscription_id'] ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </td>
                <?php endif; ?>
                
                <!-- Cellule filleul -->
                <td class="column-filleuls">
                    <div class="filleul-info">
                        <strong class="filleul-nom">
                            <?php if ( ! empty( $filleul['user_link'] ) ) : ?>
                                <a href="<?php echo esc_url( $filleul['user_link'] ); ?>" target="_blank">
                                    <?php echo esc_html( $filleul['nom'] ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $filleul['nom'] ); ?>
                            <?php endif; ?>
                        </strong>
                        <br>
                        <span class="filleul-email"><?php echo esc_html( $filleul['email'] ); ?></span>
                    </div>
                </td>
                
                <!-- Date -->
                <td class="column-date">
                    <?php if ( ! empty( $filleul['order_link'] ) ) : ?>
                        <a href="<?php echo esc_url( $filleul['order_link'] ); ?>" target="_blank">
                            <?php echo esc_html( $filleul['date_parrainage_formatted'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html( $filleul['date_parrainage_formatted'] ); ?>
                    <?php endif; ?>
                </td>
                
                <!-- Produit -->
                <td class="column-produit">
                    <?php if ( ! empty( $filleul['produit_info'] ) ) : ?>
                        <?php foreach ( $filleul['produit_info'] as $produit ) : ?>
                            <div class="produit-item">
                                <?php if ( ! empty( $produit['link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $produit['link'] ); ?>" target="_blank">
                                        <?php echo esc_html( $produit['name'] ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html( $produit['name'] ); ?>
                                <?php endif; ?>
                                <span class="produit-quantity">(x<?php echo esc_html( $produit['quantity'] ); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <em><?php esc_html_e( 'Non défini', 'wc-tb-web-parrainage' ); ?></em>
                    <?php endif; ?>
                </td>
                
                <!-- Avantage (éditable inline) -->
                <td class="column-avantage">
                    <span class="avantage-display" data-order-id="<?php echo esc_attr( $filleul['order_id'] ); ?>">
                        <?php echo esc_html( $filleul['avantage'] ); ?>
                    </span>
                    <div class="avantage-edit" style="display: none;">
                        <input type="text" 
                               class="avantage-input" 
                               value="<?php echo esc_attr( $filleul['avantage'] ); ?>"
                               data-order-id="<?php echo esc_attr( $filleul['order_id'] ); ?>">
                        <button type="button" class="button button-small save-avantage">
                            <?php esc_html_e( 'Sauver', 'wc-tb-web-parrainage' ); ?>
                        </button>
                        <button type="button" class="button button-small cancel-avantage">
                            <?php esc_html_e( 'Annuler', 'wc-tb-web-parrainage' ); ?>
                        </button>
                    </div>
                </td>
                
                <!-- Statut abonnement -->
                <td class="column-statut">
                    <?php if ( ! empty( $filleul['subscription_info'] ) ) : ?>
                        <span class="status-badge <?php echo esc_attr( $filleul['subscription_info']['status_badge_class'] ); ?>">
                            <?php if ( ! empty( $filleul['subscription_info']['link'] ) ) : ?>
                                <a href="<?php echo esc_url( $filleul['subscription_info']['link'] ); ?>" target="_blank">
                                    <?php echo esc_html( $filleul['subscription_info']['status_label'] ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $filleul['subscription_info']['status_label'] ); ?>
                            <?php endif; ?>
                        </span>
                    <?php else : ?>
                        <span class="status-badge status-default">
                            <?php esc_html_e( 'Non défini', 'wc-tb-web-parrainage' ); ?>
                        </span>
                    <?php endif; ?>
                </td>
                
                <!-- Montant -->
                <td class="column-montant">
                    <?php echo wp_kses_post( $filleul['montant_formatted'] ); ?>
                </td>
            </tr>
            <?php
        }
    }
    
    /**
     * Rendre la pagination
     */
    private function render_pagination( $pagination_data, $current_pagination ) {
        if ( $pagination_data['total_pages'] <= 1 ) {
            return;
        }
        
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php 
                    printf( 
                        esc_html__( '%d éléments', 'wc-tb-web-parrainage' ),
                        $pagination_data['total_items']
                    ); 
                    ?>
                </span>
                
                <span class="pagination-links">
                    <?php if ( $current_pagination['page'] > 1 ) : ?>
                        <a class="first-page button" href="<?php echo esc_url( $this->get_pagination_url( 1 ) ); ?>">
                            <span class="screen-reader-text"><?php esc_html_e( 'Première page', 'wc-tb-web-parrainage' ); ?></span>
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                        <a class="prev-page button" href="<?php echo esc_url( $this->get_pagination_url( $current_pagination['page'] - 1 ) ); ?>">
                            <span class="screen-reader-text"><?php esc_html_e( 'Page précédente', 'wc-tb-web-parrainage' ); ?></span>
                            <span aria-hidden="true">&lsaquo;</span>
                        </a>
                    <?php else : ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                    <?php endif; ?>
                    
                    <span class="screen-reader-text"><?php esc_html_e( 'Page courante', 'wc-tb-web-parrainage' ); ?></span>
                    <span id="table-paging" class="paging-input">
                        <span class="tablenav-paging-text">
                            <?php 
                            printf( 
                                esc_html__( '%1$s sur %2$s', 'wc-tb-web-parrainage' ),
                                '<span class="current-page">' . esc_html( $current_pagination['page'] ) . '</span>',
                                '<span class="total-pages">' . esc_html( $pagination_data['total_pages'] ) . '</span>'
                            ); 
                            ?>
                        </span>
                    </span>
                    
                    <?php if ( $current_pagination['page'] < $pagination_data['total_pages'] ) : ?>
                        <a class="next-page button" href="<?php echo esc_url( $this->get_pagination_url( $current_pagination['page'] + 1 ) ); ?>">
                            <span class="screen-reader-text"><?php esc_html_e( 'Page suivante', 'wc-tb-web-parrainage' ); ?></span>
                            <span aria-hidden="true">&rsaquo;</span>
                        </a>
                        <a class="last-page button" href="<?php echo esc_url( $this->get_pagination_url( $pagination_data['total_pages'] ) ); ?>">
                            <span class="screen-reader-text"><?php esc_html_e( 'Dernière page', 'wc-tb-web-parrainage' ); ?></span>
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    <?php else : ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Gérer les requêtes AJAX de filtrage
     */
    public function handle_ajax_filter() {
        // Sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_parrainage_admin_action' ) ) {
            wp_die( 'Nonce invalide' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permissions insuffisantes' );
        }
        
        // Récupérer et valider les paramètres
        $filters = $_POST['filters'] ?? array();
        $pagination = $_POST['pagination'] ?? array();
        
        $validated_filters = $this->validator->validate_filters( $filters );
        $validated_pagination = $this->validator->validate_pagination( $pagination );
        
        // Récupérer les données
        try {
            $data = $this->data_provider->get_parrainage_data( $validated_filters, $validated_pagination );
            $total_count = $this->data_provider->count_total_parrainages( $validated_filters );
            
            wp_send_json_success( array(
                'data' => $data,
                'total_count' => $total_count,
                'filters_applied' => $validated_filters,
                'pagination' => $validated_pagination
            ) );
            
        } catch ( \Exception $e ) {
            $this->logger->error( 
                'Erreur lors du filtrage AJAX',
                array( 'error' => $e->getMessage(), 'filters' => $filters ),
                'parrainage-stats-manager'
            );
            
            wp_send_json_error( array(
                'message' => 'Erreur lors de la récupération des données'
            ) );
        }
    }
    
    /**
     * Gérer les requêtes AJAX d'export
     */
    public function handle_ajax_export() {
        // Sécurité
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tb_parrainage_admin_action' ) ) {
            wp_die( 'Nonce invalide' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permissions insuffisantes' );
        }
        
        // Récupérer les paramètres
        $export_params = array(
            'format' => sanitize_text_field( $_POST['format'] ?? 'csv' ),
            'filters' => $_POST['filters'] ?? array(),
            'limit' => absint( $_POST['limit'] ?? self::MAX_EXPORT_RECORDS )
        );
        
        $validated_params = $this->validator->validate_export_params( $export_params );
        
        // Récupérer toutes les données pour l'export (sans pagination)
        $all_data = $this->data_provider->get_parrainage_data( 
            $validated_params['filters'], 
            array( 'page' => 1, 'per_page' => $validated_params['limit'] )
        );
        
        // Exporter
        $result = $this->exporter->safe_export( $all_data, $validated_params['format'] );
        
        if ( is_wp_error( $result ) ) {
            $this->logger->error( 
                'Erreur lors de l\'export',
                array( 'error' => $result->get_error_message(), 'params' => $validated_params ),
                'parrainage-stats-manager'
            );
            
            wp_send_json_error( array(
                'message' => $result->get_error_message()
            ) );
        }
        
        // L'export a été envoyé directement, arrêter l'exécution
        exit;
    }
    
    /**
     * Gérer l'édition inline AJAX
     */
    public function handle_ajax_inline_edit() {
        $validation = $this->validator->validate_inline_edit( $_POST );
        
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array(
                'message' => $validation->get_error_message()
            ) );
        }
        
        // Mettre à jour l'avantage dans les métadonnées de la commande
        $success = update_post_meta( $validation['order_id'], '_parrainage_avantage', $validation['avantage'] );
        
        if ( $success ) {
            $this->logger->info( 
                'Avantage parrainage modifié',
                array( 
                    'order_id' => $validation['order_id'],
                    'new_avantage' => $validation['avantage']
                ),
                'parrainage-stats-manager'
            );
            
            // Invalider le cache
            wp_cache_flush_group( ParrainageDataProvider::CACHE_GROUP );
            
            wp_send_json_success( array(
                'message' => 'Avantage mis à jour avec succès',
                'new_avantage' => $validation['avantage']
            ) );
        } else {
            wp_send_json_error( array(
                'message' => 'Erreur lors de la mise à jour'
            ) );
        }
    }
    
    /**
     * Récupérer les filtres actuels depuis l'URL
     */
    private function get_current_filters() {
        return array(
            'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to' => sanitize_text_field( $_GET['date_to'] ?? '' ),
            'parrain_search' => sanitize_text_field( $_GET['parrain_search'] ?? '' ),
            'product_id' => absint( $_GET['product_id'] ?? 0 ),
            'subscription_status' => sanitize_text_field( $_GET['subscription_status'] ?? '' )
        );
    }
    
    /**
     * Récupérer la pagination actuelle depuis l'URL
     */
    private function get_current_pagination() {
        return array(
            'page' => absint( $_GET['paged'] ?? 1 ),
            'per_page' => absint( $_GET['per_page'] ?? self::DEFAULT_PER_PAGE ),
            'order_by' => sanitize_text_field( $_GET['order_by'] ?? 'parrain_nom' ),
            'order' => sanitize_text_field( $_GET['order'] ?? 'ASC' )
        );
    }
    
    /**
     * Calculer les données de pagination
     */
    private function calculate_pagination( $total_count, $pagination ) {
        $total_pages = ceil( $total_count / $pagination['per_page'] );
        
        return array(
            'total_items' => $total_count,
            'total_pages' => $total_pages,
            'current_page' => $pagination['page'],
            'per_page' => $pagination['per_page']
        );
    }
    
    /**
     * Générer une URL de pagination
     */
    private function get_pagination_url( $page ) {
        $current_filters = $this->get_current_filters();
        $current_pagination = $this->get_current_pagination();
        
        $params = array_merge( 
            array( 'page' => 'wc-tb-parrainage', 'tab' => 'parrainage', 'paged' => $page ),
            array_filter( $current_filters ),
            array( 'per_page' => $current_pagination['per_page'] )
        );
        
        return admin_url( 'options-general.php?' . http_build_query( $params ) );
    }
} 