/**
 * TB-Web Parrainage - Interface d'administration JavaScript
 */

jQuery(document).ready(function ($) {
  // Variables globales
  var refreshTimeout;

  /**
   * Actualiser les logs
   */
  $("#refresh-logs").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var originalText = $button.text();

    $button.text("Chargement...").prop("disabled", true);

    // Simuler un rechargement de page pour les logs
    location.reload();
  });

  /**
   * Vider les logs
   */
  $("#clear-logs").on("click", function (e) {
    e.preventDefault();

    if (
      !confirm(
        "Êtes-vous sûr de vouloir supprimer tous les logs ? Cette action est irréversible."
      )
    ) {
      return;
    }

    var $button = $(this);
    var originalText = $button.text();

    $button.text("Suppression...").prop("disabled", true);

    $.ajax({
      url: tbParrainageAjax.ajaxurl,
      type: "POST",
      data: {
        action: "tb_parrainage_clear_logs",
        nonce: tbParrainageAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Vider le tbody des logs
          $("#logs-tbody").html(
            '<tr><td colspan="5">Aucun log disponible</td></tr>'
          );

          // Afficher un message de succès
          showNotice("Logs supprimés avec succès.", "success");
        } else {
          showNotice("Erreur lors de la suppression des logs.", "error");
        }
      },
      error: function () {
        showNotice("Erreur de communication avec le serveur.", "error");
      },
      complete: function () {
        $button.text(originalText).prop("disabled", false);
      },
    });
  });

  /**
   * Auto-actualisation des logs (optionnel)
   */
  function startAutoRefresh() {
    if ($("#auto-refresh-logs").is(":checked")) {
      refreshTimeout = setTimeout(function () {
        $("#refresh-logs").trigger("click");
      }, 30000); // 30 secondes
    }
  }

  /**
   * Filtre des logs par niveau
   */
  $("#log-level-filter").on("change", function () {
    var selectedLevel = $(this).val().toLowerCase();

    $("#logs-tbody tr").each(function () {
      var $row = $(this);
      var rowLevel = $row.find(".log-level").text().toLowerCase();

      if (selectedLevel === "" || rowLevel === selectedLevel) {
        $row.show();
      } else {
        $row.hide();
      }
    });
  });

  /**
   * Recherche dans les logs
   */
  $("#log-search").on("input", function () {
    var searchTerm = $(this).val().toLowerCase();

    $("#logs-tbody tr").each(function () {
      var $row = $(this);
      var rowText = $row.text().toLowerCase();

      if (searchTerm === "" || rowText.includes(searchTerm)) {
        $row.show();
      } else {
        $row.hide();
      }
    });
  });

  /**
   * Afficher une notice
   */
  function showNotice(message, type) {
    var noticeClass = "notice-" + type;
    var $notice = $(
      '<div class="notice ' +
        noticeClass +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );

    $(".wrap h1").after($notice);

    // Supprimer la notice après 5 secondes
    setTimeout(function () {
      $notice.fadeOut(function () {
        $(this).remove();
      });
    }, 5000);
  }

  /**
   * Mise en forme automatique des timestamps
   */
  function formatTimestamps() {
    $(".logs-container td:first-child").each(function () {
      var $cell = $(this);
      var timestamp = $cell.text().trim();

      if (timestamp) {
        var date = new Date(timestamp);
        var now = new Date();
        var diff = now - date;

        // Si c'est récent (moins d'une heure)
        if (diff < 3600000) {
          var minutes = Math.floor(diff / 60000);
          $cell
            .attr("title", timestamp)
            .html(
              '<span style="color: #0073aa;">Il y a ' + minutes + " min</span>"
            );
        }
        // Si c'est aujourd'hui
        else if (date.toDateString() === now.toDateString()) {
          $cell
            .attr("title", timestamp)
            .html(
              '<span style="color: #666;">Aujourd\'hui ' +
                date.toLocaleTimeString() +
                "</span>"
            );
        }
      }
    });
  }

  /**
   * Animation des statistiques
   */
  function animateStats() {
    $(".stat-number").each(function () {
      var $this = $(this);
      var target = parseInt($this.text());
      var current = 0;
      var increment = Math.ceil(target / 20);

      var timer = setInterval(function () {
        current += increment;
        if (current >= target) {
          current = target;
          clearInterval(timer);
        }
        $this.text(current);
      }, 50);
    });
  }

  /**
   * Gestion des onglets
   */
  $(".nav-tab").on("click", function (e) {
    var $tab = $(this);
    var target = $tab.attr("href").split("tab=")[1];

    // Mettre à jour l'URL sans recharger
    if (history.pushState) {
      var newurl =
        window.location.protocol +
        "//" +
        window.location.host +
        window.location.pathname +
        "?page=wc-tb-parrainage&tab=" +
        target;
      window.history.pushState({ path: newurl }, "", newurl);
    }
  });

  /**
   * Validation du formulaire de paramètres
   */
  $('form input[name="save_settings"]')
    .closest("form")
    .on("submit", function (e) {
      var retentionDays = parseInt($('input[name="log_retention_days"]').val());

      if (retentionDays < 1 || retentionDays > 365) {
        e.preventDefault();
        alert(
          "La durée de rétention des logs doit être comprise entre 1 et 365 jours."
        );
        return false;
      }
    });

  /**
   * Initialisation
   */
  function init() {
    // Formater les timestamps si on est sur l'onglet logs
    if (
      window.location.href.includes("tab=logs") ||
      !window.location.href.includes("tab=")
    ) {
      formatTimestamps();
    }

    // Animer les statistiques si on est sur l'onglet stats
    if (window.location.href.includes("tab=stats")) {
      setTimeout(animateStats, 500);
    }

    // Démarrer l'auto-actualisation si activée
    startAutoRefresh();
  }

  // Lancer l'initialisation
  init();

  /**
   * Gestion AJAX pour vider les logs
   */
  $(document).on("click", '[data-action="clear-logs"]', function (e) {
    e.preventDefault();
    $("#clear-logs").trigger("click");
  });

  /**
   * Copier un log dans le presse-papier
   */
  $(document).on("click", ".copy-log", function (e) {
    e.preventDefault();

    var $row = $(this).closest("tr");
    var logData = {
      datetime: $row.find("td:eq(0)").text(),
      level: $row.find("td:eq(1)").text(),
      source: $row.find("td:eq(2)").text(),
      message: $row.find("td:eq(3)").text(),
    };

    var logText = JSON.stringify(logData, null, 2);

    if (navigator.clipboard) {
      navigator.clipboard.writeText(logText).then(function () {
        showNotice("Log copié dans le presse-papier", "success");
      });
    } else {
      // Fallback pour les navigateurs plus anciens
      var $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(logText).select();
      document.execCommand("copy");
      $temp.remove();
      showNotice("Log copié dans le presse-papier", "success");
    }
  });
});

/**
 * Handler AJAX pour les actions d'administration
 */
jQuery(document).ajaxSuccess(function (event, xhr, settings) {
  if (settings.data && settings.data.includes("action=tb_parrainage_")) {
    // Traitement des réponses AJAX spécifiques au plugin
    console.log("TB-Parrainage AJAX success:", xhr.responseJSON);
  }
});

/**
 * Handler AJAX pour les erreurs
 */
jQuery(document).ajaxError(function (event, xhr, settings) {
  if (settings.data && settings.data.includes("action=tb_parrainage_")) {
    console.error("TB-Parrainage AJAX error:", xhr.responseJSON);
  }
});

/**
 * Gestion de l'interface de configuration des produits
 */
jQuery(document).ready(function($) {
    
    // Variables pour la gestion des produits
    var productIndex = $('.product-config-row').length;
    
    /**
     * Ajouter un nouveau produit
     */
    $('#add-product').on('click', function(e) {
        e.preventDefault();
        
        // Masquer le message "aucun produit" s'il existe
        $('.no-products').fadeOut();
        
        // Récupérer le template
        var template = $('#product-row-template').html();
        
        // Remplacer les placeholders
        template = template.replace(/\{\{INDEX\}\}/g, productIndex);
        template = template.replace(/\{\{PRODUCT_ID\}\}/g, '');
        
        // Ajouter la nouvelle ligne
        var $newRow = $(template);
        $newRow.addClass('adding');
        $('#products-container').append($newRow);
        
        // Animation d'apparition
        setTimeout(function() {
            $newRow.removeClass('adding');
        }, 300);
        
        // Focus sur le champ ID produit
        $newRow.find('.product-id-input').focus();
        
        productIndex++;
        
        // Mise à jour des numéros de produit affichés
        updateProductNumbers();
    });
    
    /**
     * Supprimer un produit
     */
    $(document).on('click', '.remove-product', function(e) {
        e.preventDefault();
        
        var $row = $(this).closest('.product-config-row');
        
        // Confirmation
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette configuration produit ?')) {
            return;
        }
        
        // Animation de suppression
        $row.addClass('removing');
        
        setTimeout(function() {
            $row.remove();
            
            // Vérifier s'il reste des produits
            if ($('.product-config-row').length === 0) {
                $('#products-container').html('<div class="no-products"><p>Aucun produit configuré. Cliquez sur "Ajouter un Produit" pour commencer.</p></div>');
            }
            
            // Mise à jour des numéros
            updateProductNumbers();
        }, 300);
    });
    
    /**
     * Mise à jour en temps réel du numéro de produit affiché
     */
    $(document).on('input', '.product-id-input', function() {
        var $input = $(this);
        var productId = $input.val();
        var $productNumber = $input.closest('.product-config-row').find('.product-number');
        
        if (productId && productId > 0) {
            $productNumber.text(productId);
        } else {
            $productNumber.text('Nouveau');
        }
        
        // Validation visuelle
        if (productId && productId > 0) {
            $input.removeClass('invalid').addClass('valid');
        } else if (productId !== '') {
            $input.removeClass('valid').addClass('invalid');
        } else {
            $input.removeClass('valid invalid');
        }
    });
    
    /**
     * Validation avant soumission
     */
    $('#products-config-form').on('submit', function(e) {
        var isValid = true;
        var productIds = [];
        var duplicates = [];
        
        // Vérifier que tous les ID produits sont valides et uniques
        $('.product-id-input').each(function() {
            var $input = $(this);
            var productId = parseInt($input.val());
            
            if (!productId || productId <= 0) {
                isValid = false;
                $input.addClass('invalid');
                showNotice('Tous les ID produits doivent être des nombres positifs.', 'error');
                return false;
            }
            
            if (productIds.includes(productId)) {
                duplicates.push(productId);
                isValid = false;
            } else {
                productIds.push(productId);
            }
            
            $input.removeClass('invalid');
        });
        
        if (duplicates.length > 0) {
            showNotice('Les ID produits suivants sont en double : ' + duplicates.join(', '), 'error');
            isValid = false;
        }
        
        // Vérifier que toutes les descriptions sont remplies
        $('.product-config-row textarea[name="description[]"]').each(function() {
            var $textarea = $(this);
            if (!$textarea.val().trim()) {
                isValid = false;
                $textarea.addClass('invalid');
                showNotice('Toutes les descriptions sont obligatoires.', 'error');
                return false;
            }
            $textarea.removeClass('invalid');
        });
        
        if (!isValid) {
            e.preventDefault();
            
            // Scroll vers le premier champ en erreur
            var $firstError = $('.invalid').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.closest('.product-config-row').offset().top - 100
                }, 500);
            }
        }
    });
    
    /**
     * Mise à jour des numéros de produits affichés
     */
    function updateProductNumbers() {
        $('.product-config-row').each(function(index) {
            var $row = $(this);
            var productId = $row.find('.product-id-input').val();
            var $productNumber = $row.find('.product-number');
            
            if (productId && productId > 0) {
                $productNumber.text(productId);
            } else {
                $productNumber.text('Nouveau');
            }
        });
    }
    
    /**
     * Auto-sauvegarde des données (optionnel)
     */
    var autoSaveTimeout;
    $(document).on('input', '.product-config-row input, .product-config-row textarea', function() {
        clearTimeout(autoSaveTimeout);
        
        // Afficher un indicateur de modification
        if (!$('.unsaved-changes').length) {
            $('.products-config-container').prepend(
                '<div class="unsaved-changes help-message">Des modifications non sauvegardées sont détectées. N\'oubliez pas de sauvegarder.</div>'
            );
        }
    });
    
    /**
     * Masquer l'indicateur de modifications après sauvegarde
     */
    $('#products-config-form').on('submit', function() {
        $('.unsaved-changes').remove();
    });
    
    /**
     * Raccourcis clavier
     */
    $(document).on('keydown', function(e) {
        // Ctrl+S pour sauvegarder
        if (e.ctrlKey && e.which === 83) {
            e.preventDefault();
            $('#products-config-form').submit();
        }
        
        // Escape pour annuler l'ajout en cours
        if (e.which === 27) {
            $('.adding .remove-product').click();
        }
    });
    
    /**
     * Initialisation
     */
    function initProductsInterface() {
        // Mise à jour des numéros au chargement
        updateProductNumbers();
        
        // Ajouter des tooltips si nécessaire
        if (typeof tippy !== 'undefined') {
            tippy('[data-tippy-content]');
        }
    }
    
    // Lancer l'initialisation si on est sur l'onglet produits
    if (window.location.href.includes('tab=products')) {
        initProductsInterface();
    }
    
});
