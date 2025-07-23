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
    public function render_parrainage_interface( $deletion_manager = null ) {
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
            
            <!-- Actions de suppression -->
            <?php if ( $deletion_manager ) : ?>
            <div class="parrainage-bulk-actions" style="background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 2px solid #fc8181; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                    <div>
                        <h2 style="margin: 0; color: #c53030; font-size: 18px; display: flex; align-items: center;">
                            🛡️ Gestion Anti-Fraude des Parrainages
                        </h2>
                        <p style="margin: 5px 0 0 0; color: #744210; font-size: 14px;">
                            <strong>⚠️ ATTENTION :</strong> Supprimez les parrainages frauduleux ou de test. Les réductions automatiques seront annulées.
                        </p>
                    </div>
                    <div id="selection-summary" style="background: white; padding: 10px 15px; border-radius: 6px; border: 2px solid #fc8181; text-align: center; min-width: 140px;">
                        <div id="selection-count" style="font-size: 16px; font-weight: bold; color: #c53030;">0 sélectionné(s)</div>
                        <div style="font-size: 12px; color: #744210;">sur <span id="total-count">0</span> parrainages</div>
                    </div>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" id="select-all-parrainages" class="button" style="background: #3182ce; color: white; border-color: #3182ce; font-weight: 600; padding: 8px 16px;">
                            ☑️ Tout Sélectionner
                        </button>
                        <button type="button" id="unselect-all-parrainages" class="button" style="background: #718096; color: white; border-color: #718096; font-weight: 600; padding: 8px 16px;">
                            ☐ Tout Désélectionner
                        </button>
                        <button type="button" id="select-visible-parrainages" class="button" style="background: #38a169; color: white; border-color: #38a169; font-weight: 600; padding: 8px 16px;">
                            👁️ Sélectionner Page Actuelle
                        </button>
                    </div>
                    
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" id="delete-selected-parrainages" class="button" style="background: #e53e3e; color: white; border-color: #e53e3e; font-weight: bold; font-size: 14px; padding: 10px 20px; box-shadow: 0 2px 4px rgba(229,62,62,0.3);" disabled>
                            🗑️ SUPPRIMER LA SÉLECTION
                        </button>
                        <div id="delete-help" style="font-size: 11px; color: #744210; max-width: 200px; line-height: 1.3;">
                            💡 Cochez les cases puis cliquez sur "Supprimer"
                        </div>
                    </div>
                </div>
                
                <div id="deletion-status" style="margin-top: 15px;"></div>
                
                <!-- Zone d'aide rapide -->
                <details style="margin-top: 15px; background: rgba(255,255,255,0.7); padding: 10px; border-radius: 4px;">
                    <summary style="cursor: pointer; font-weight: 600; color: #744210;">📖 Comment utiliser cette fonction ?</summary>
                    <div style="margin-top: 10px; font-size: 13px; line-height: 1.5; color: #744210;">
                        <ol style="margin: 5px 0; padding-left: 20px;">
                            <li><strong>Cochez</strong> les parrainages à supprimer (cases à gauche du tableau)</li>
                            <li><strong>Vérifiez</strong> le nombre sélectionné dans l'encadré rouge</li>
                            <li><strong>Cliquez</strong> sur "SUPPRIMER LA SÉLECTION"</li>
                            <li><strong>Confirmez</strong> la suppression dans la popup</li>
                        </ol>
                        <p style="margin: 10px 0 0 0;"><strong>🛡️ Sécurité :</strong> Les réductions automatiques appliquées aux parrains seront automatiquement annulées.</p>
                    </div>
                </details>
            </div>
            <?php endif; ?>
            
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
                <?php $this->render_parrainage_table( $parrainage_data, $deletion_manager ); ?>
            </div>
            
            <!-- Pagination -->
            <?php $this->render_pagination( $pagination_data, $validated_pagination ); ?>
        </div>
        
        <!-- Nonces pour AJAX -->
        <script type="text/javascript">
            var tbParrainageData = {
                ajaxurl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                nonce: '<?php echo wp_create_nonce( 'tb_parrainage_admin_action' ); ?>',
                delete_nonce: '<?php echo wp_create_nonce( 'tb_parrainage_delete_action' ); ?>',
                current_filters: <?php echo wp_json_encode( $validated_filters ); ?>,
                current_pagination: <?php echo wp_json_encode( $validated_pagination ); ?>,
                has_deletion_manager: <?php echo $deletion_manager ? 'true' : 'false'; ?>
            };
            
            <?php if ( $deletion_manager ) : ?>
            // JavaScript pour la gestion des suppressions
            jQuery(document).ready(function($) {
                let selectedCount = 0;
                let totalCount = $('.parrainage-checkbox').length;
                
                // Initialisation
                updateTotalCount();
                updateSelectionCount();
                updateDeleteButton();
                
                // Gestion de la sélection
                $(document).on('change', '.parrainage-checkbox', function() {
                    updateSelectionCount();
                    updateDeleteButton();
                    updateHeaderCheckbox();
                });
                
                // Checkbox du header
                $('#select-all-header').on('change', function() {
                    let isChecked = $(this).is(':checked');
                    $('.parrainage-checkbox').prop('checked', isChecked);
                    updateSelectionCount();
                    updateDeleteButton();
                });
                
                // Tout sélectionner
                $('#select-all-parrainages').on('click', function() {
                    $('.parrainage-checkbox').prop('checked', true);
                    $('#select-all-header').prop('checked', true);
                    updateSelectionCount();
                    updateDeleteButton();
                    highlightSelection();
                });
                
                // Tout désélectionner
                $('#unselect-all-parrainages').on('click', function() {
                    $('.parrainage-checkbox').prop('checked', false);
                    $('#select-all-header').prop('checked', false);
                    updateSelectionCount();
                    updateDeleteButton();
                    removeHighlight();
                });
                
                // Sélectionner page actuelle
                $('#select-visible-parrainages').on('click', function() {
                    $('.parrainage-checkbox:visible').prop('checked', true);
                    updateSelectionCount();
                    updateDeleteButton();
                    updateHeaderCheckbox();
                    highlightSelection();
                });
                
                // Suppression de la sélection
                $('#delete-selected-parrainages').on('click', function() {
                    let selectedIds = getSelectedIds();
                    if (selectedIds.length === 0) {
                        alert('❌ Aucun parrainage sélectionné !\n\n💡 Cochez d\'abord les cases à gauche du tableau.');
                        return;
                    }
                    
                    // Dialogue de confirmation amélioré - avec détails filleuls
                    let selectedDetails = [];
                    $('.parrainage-checkbox:checked').each(function() {
                        selectedDetails.push({
                            parrainName: $(this).data('parrain-name'),
                            filleulName: $(this).data('filleul-name'),
                            orderId: $(this).val()
                        });
                    });
                    
                    let parrainagesList = selectedDetails.slice(0, 5).map(d => 
                        '• ' + d.filleulName + ' (parrainé par ' + d.parrainName + ')'
                    ).join('\n');
                    
                    if (selectedDetails.length > 5) {
                        parrainagesList += '\n• ... et ' + (selectedDetails.length - 5) + ' autres parrainages';
                    }
                    
                    let confirmMessage = '🛡️ SUPPRESSION ANTI-FRAUDE DE PARRAINAGES INDIVIDUELS\n\n' +
                                       'Vous allez supprimer ' + selectedIds.length + ' parrainage(s) :\n\n' +
                                       parrainagesList + '\n\n' +
                                       '⚠️ CONSÉQUENCES :\n' +
                                       '✓ Suppression définitive de ces parrainages\n' +
                                       '✓ Annulation automatique des réductions appliquées\n' +
                                       '✓ Restauration des prix originaux programmée\n\n' +
                                       '🔒 Cette action est irréversible !\n\n' +
                                       'Confirmez-vous la suppression ?';
                    
                    if (!confirm(confirmMessage)) {
                        return;
                    }
                    
                    deleteSelectedParrainages(selectedIds);
                });
                
                // Suppression individuelle
                $(document).on('click', '.delete-single-parrainage', function() {
                    let orderId = $(this).data('order-id');
                    let parrainName = $(this).data('parrain-name') || 'Parrain inconnu';
                    let filleulName = $(this).data('filleul-name') || 'Filleul inconnu';
                    
                    let confirmMessage = '🛡️ SUPPRESSION IMMÉDIATE DU PARRAINAGE\n\n' +
                                       'Supprimer le parrainage :\n' +
                                       '• Filleul : ' + filleulName + '\n' +
                                       '• Parrain : ' + parrainName + '\n' +
                                       '• Commande #' + orderId + '\n\n' +
                                       '⚠️ CONSÉQUENCES :\n' +
                                       '✓ Suppression immédiate de ce parrainage\n' +
                                       '✓ Annulation automatique des réductions\n' +
                                       '✓ Action irréversible\n\n' +
                                       'Confirmer la suppression ?';
                    
                    if (!confirm(confirmMessage)) {
                        return;
                    }
                    
                    deleteSingleParrainage(orderId, $(this));
                });
                
                function updateTotalCount() {
                    totalCount = $('.parrainage-checkbox').length;
                    $('#total-count').text(totalCount);
                }
                
                function updateSelectionCount() {
                    selectedCount = $('.parrainage-checkbox:checked').length;
                    $('#selection-count').html(selectedCount + ' sélectionné(s)');
                    
                    // Coloration selon la sélection
                    if (selectedCount === 0) {
                        $('#selection-summary').css({
                            'background': 'white',
                            'border-color': '#fc8181'
                        });
                    } else {
                        $('#selection-summary').css({
                            'background': '#fed7d7',
                            'border-color': '#e53e3e'
                        });
                    }
                }
                
                function updateDeleteButton() {
                    let button = $('#delete-selected-parrainages');
                    if (selectedCount === 0) {
                        button.prop('disabled', true);
                        button.html('🗑️ SUPPRIMER LA SÉLECTION');
                        $('#delete-help').html('💡 Cochez les cases puis cliquez sur "Supprimer"');
                    } else {
                        button.prop('disabled', false);
                        button.html('🗑️ SUPPRIMER ' + selectedCount + ' PARRAINAGE(S)');
                        $('#delete-help').html('⚠️ Attention : suppression définitive !');
                    }
                }
                
                function updateHeaderCheckbox() {
                    let totalVisible = $('.parrainage-checkbox:visible').length;
                    let selectedVisible = $('.parrainage-checkbox:visible:checked').length;
                    
                    if (selectedVisible === 0) {
                        $('#select-all-header').prop('indeterminate', false).prop('checked', false);
                    } else if (selectedVisible === totalVisible) {
                        $('#select-all-header').prop('indeterminate', false).prop('checked', true);
                    } else {
                        $('#select-all-header').prop('indeterminate', true);
                    }
                }
                
                function highlightSelection() {
                    $('.parrainage-checkbox:checked').closest('tr').css({
                        'background-color': '#fed7d7',
                        'border-left': '4px solid #e53e3e'
                    });
                    setTimeout(removeHighlight, 2000);
                }
                
                function removeHighlight() {
                    $('.parrainage-checkbox').closest('tr').css({
                        'background-color': '',
                        'border-left': ''
                    });
                }
                
                function getSelectedIds() {
                    let ids = [];
                    $('.parrainage-checkbox:checked').each(function() {
                        ids.push($(this).val());
                    });
                    return ids;
                }
                
                function deleteSelectedParrainages(orderIds) {
                    // Récupérer les détails des parrainages sélectionnés
                    let selectedDetails = [];
                    $('.parrainage-checkbox:checked').each(function() {
                        selectedDetails.push({
                            orderId: $(this).val(),
                            parrainName: $(this).data('parrain-name'),
                            filleulName: $(this).data('filleul-name')
                        });
                    });
                    
                    // Interface de progression
                    $('#deletion-status').html('<div style="background: #fff3cd; padding: 15px; border-radius: 6px; color: #856404; border: 2px solid #faca15; font-weight: bold; text-align: center;">' +
                                             '<div style="font-size: 14px;">🔄 SUPPRESSION EN COURS...</div>' +
                                             '<div style="margin-top: 8px; font-size: 12px;">Suppression de ' + orderIds.length + ' parrainage(s) individuels et annulation des réductions</div>' +
                                             '<div style="margin-top: 5px; font-size: 11px;">Parrainages : ' + selectedDetails.slice(0,3).map(d => d.filleulName).join(', ') + (selectedDetails.length > 3 ? '...' : '') + '</div>' +
                                             '<div style="background: #e2e8f0; height: 6px; border-radius: 3px; margin-top: 8px; overflow: hidden;">' +
                                             '<div id="progress-bar" style="background: #3182ce; height: 100%; width: 0%; transition: width 0.3s;"></div></div>' +
                                             '</div>');
                    
                    $('#delete-selected-parrainages').prop('disabled', true).html('🔄 Suppression...');
                    
                    // Animation de la barre de progression
                    $('#progress-bar').css('width', '30%');
                    
                    $.ajax({
                        url: tbParrainageData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tb_parrainage_delete_selected',
                            order_ids: orderIds,
                            nonce: tbParrainageData.delete_nonce
                        },
                        success: function(response) {
                            $('#progress-bar').css('width', '100%');
                            
                            if (response.success) {
                                $('#deletion-status').html('<div style="background: #d4edda; padding: 20px; border-radius: 8px; color: #155724; border: 2px solid #38a169; text-align: center; font-weight: bold;">' +
                                                         '<div style="font-size: 16px;">✅ SUPPRESSION RÉUSSIE !</div>' +
                                                         '<div style="margin-top: 10px; font-size: 13px;">' + response.data.message + '</div>' +
                                                         '<div style="margin-top: 15px; font-size: 12px; color: #2d3748;">🔄 Rechargement de la page dans 3 secondes...</div>' +
                                                         '</div>');
                                setTimeout(() => {
                                    location.reload();
                                }, 3000);
                            } else {
                                $('#deletion-status').html('<div style="background: #fed7d7; padding: 15px; border-radius: 6px; color: #c53030; border: 2px solid #e53e3e; text-align: center; font-weight: bold;">' +
                                                         '<div style="font-size: 14px;">❌ ÉCHEC DE LA SUPPRESSION</div>' +
                                                         '<div style="margin-top: 8px; font-size: 12px;">' + response.data.message + '</div>' +
                                                         '</div>');
                                $('#delete-selected-parrainages').prop('disabled', false).html('🗑️ SUPPRIMER LA SÉLECTION');
                            }
                        },
                        error: function() {
                            $('#deletion-status').html('<div style="background: #fed7d7; padding: 15px; border-radius: 6px; color: #c53030; border: 2px solid #e53e3e; text-align: center; font-weight: bold;">' +
                                                     '<div style="font-size: 14px;">❌ ERREUR DE COMMUNICATION</div>' +
                                                     '<div style="margin-top: 8px; font-size: 12px;">Impossible de contacter le serveur. Veuillez réessayer.</div>' +
                                                     '</div>');
                            $('#delete-selected-parrainages').prop('disabled', false).html('🗑️ SUPPRIMER LA SÉLECTION');
                        }
                    });
                }
                
                function deleteSingleParrainage(orderId, button) {
                    let originalText = button.html();
                    button.html('🔄').prop('disabled', true);
                    
                    $.ajax({
                        url: tbParrainageData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tb_parrainage_delete_single',
                            order_id: orderId,
                            nonce: tbParrainageData.delete_nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                button.closest('tr').fadeOut(500, function() {
                                    $(this).remove();
                                    updateSelectionCount();
                                    updateDeleteButton();
                                });
                            } else {
                                alert('❌ ' + response.data.message);
                                button.html(originalText).prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('❌ Erreur de communication');
                            button.html(originalText).prop('disabled', false);
                        }
                    });
                }
            });
            <?php endif; ?>
        </script>
        <?php
    }
    
    /**
     * Rendre le tableau de parrainage
     */
    private function render_parrainage_table( $data, $deletion_manager = null ) {
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
                    <?php if ( $deletion_manager ) : ?>
                    <th class="column-checkbox" style="width: 60px; background: #fed7d7; text-align: center;">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                            <input type="checkbox" id="select-all-header" style="margin: 0; transform: scale(1.2);" title="Sélectionner/Désélectionner tout">
                            <small style="font-size: 10px; color: #c53030; font-weight: bold;">SÉLECTION</small>
                        </div>
                    </th>
                    <?php endif; ?>
                    <th class="column-parrain"><?php esc_html_e( 'Parrain', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-filleuls"><?php esc_html_e( 'Filleul(s)', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-date"><?php esc_html_e( 'Date', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-produit"><?php esc_html_e( 'Produit', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-avantage"><?php esc_html_e( 'Avantage', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-statut"><?php esc_html_e( 'Statut Abonnement', 'wc-tb-web-parrainage' ); ?></th>
                    <th class="column-montant"><?php esc_html_e( 'Montant', 'wc-tb-web-parrainage' ); ?></th>
                    <?php if ( $deletion_manager ) : ?>
                    <th class="column-actions" style="width: 110px; background: #fed7d7; text-align: center;">
                        <small style="font-size: 10px; color: #c53030; font-weight: bold;">ACTIONS</small>
                    </th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data['parrains'] as $parrain_data ) : ?>
                    <?php $this->render_parrain_row( $parrain_data, $deletion_manager ); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Rendre une ligne de parrain avec ses filleuls
     */
    private function render_parrain_row( $parrain_data, $deletion_manager = null ) {
        $parrain = $parrain_data['parrain'];
        $filleuls = $parrain_data['filleuls'];
        $filleuls_count = count( $filleuls );
        
        foreach ( $filleuls as $index => $filleul ) {
            ?>
            <tr class="<?php echo $index === 0 ? 'parrain-row' : 'filleul-row'; ?>" data-order-id="<?php echo esc_attr( $filleul['order_id'] ); ?>">
                
                <!-- Colonne Checkbox (si gestionnaire de suppression) - UNE PAR FILLEUL -->
                <?php if ( $deletion_manager ) : ?>
                <td class="column-checkbox" style="text-align: center; vertical-align: middle; background: #fff5f5; border-left: 3px solid #fc8181;">
                    <div style="padding: 8px;">
                        <input type="checkbox" class="parrainage-checkbox" value="<?php echo esc_attr( $filleul['order_id'] ); ?>" data-parrain-name="<?php echo esc_attr( $parrain['nom'] ); ?>" data-filleul-name="<?php echo esc_attr( $filleul['nom'] ); ?>" style="transform: scale(1.3); cursor: pointer;" title="Sélectionner ce parrainage (<?php echo esc_attr( $filleul['nom'] ); ?>) pour suppression">
                        <br>
                        <small style="color: #c53030; font-size: 9px; font-weight: bold; margin-top: 3px; display: block;">À SUPPR</small>
                    </div>
                </td>
                <?php endif; ?>
                
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
                
                <!-- Colonne Actions (si gestionnaire de suppression) -->
                <?php if ( $deletion_manager ) : ?>
                <td class="column-actions" style="text-align: center; background: #fff5f5; border-right: 3px solid #fc8181;">
                    <?php 
                    $can_delete = $deletion_manager->can_delete_parrainage( $filleul['order_id'] );
                    if ( $can_delete['can_delete'] ) :
                        $has_reduction = $can_delete['has_automatic_reduction'] ?? false;
                        $button_title = $has_reduction ? 'Suppression immédiate + annulation réductions automatiques' : 'Suppression immédiate de ce parrainage';
                    ?>
                    <div style="padding: 5px;">
                                            <button type="button" 
                            class="button delete-single-parrainage" 
                            data-order-id="<?php echo esc_attr( $filleul['order_id'] ); ?>"
                            data-parrain-name="<?php echo esc_attr( $parrain['nom'] ); ?>"
                            data-filleul-name="<?php echo esc_attr( $filleul['nom'] ); ?>"
                            title="<?php echo esc_attr( $button_title ); ?>"
                            style="background: #e53e3e; color: white; border-color: #e53e3e; font-size: 10px; padding: 4px 8px; line-height: 1.2; font-weight: bold; border-radius: 4px; box-shadow: 0 1px 3px rgba(229,62,62,0.3);">
                        🗑️ SUPPR
                    </button>
                        <?php if ( $has_reduction ) : ?>
                        <br><small style="color: #e53e3e; font-size: 9px; font-weight: bold; margin-top: 2px; display: block;">⚠️ Réduction active</small>
                        <?php endif; ?>
                        <small style="color: #744210; font-size: 8px; display: block; margin-top: 2px;">Suppression immédiate</small>
                    </div>
                    <?php else : ?>
                    <div style="padding: 5px;">
                        <span style="color: #718096; font-size: 10px; display: block; text-align: center;"><?php echo esc_html( $can_delete['reason'] ); ?></span>
                    </div>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                
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