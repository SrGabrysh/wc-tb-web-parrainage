/**
 * TB-Web Parrainage - Interface d'administration Parrainage JavaScript
 */

jQuery(document).ready(function ($) {
  // Variables globales
  var isFiltering = false;
  var isExporting = false;

  /**
   * Initialisation de l'interface parrainage
   */
  function initParrainageInterface() {
    if (!window.location.href.includes("tab=parrainage")) {
      return;
    }

    initFilters();
    initToolbar();
    initInlineEdit();
    initPagination();

    console.log("Interface parrainage initialisée");
  }

  /**
   * Initialiser les filtres
   */
  function initFilters() {
    // Changement automatique du nombre par page
    $("#per-page-selector").on("change", function () {
      var newPerPage = $(this).val();
      var currentUrl = new URL(window.location);
      currentUrl.searchParams.set("per_page", newPerPage);
      currentUrl.searchParams.set("paged", "1"); // Reset à la page 1
      window.location.href = currentUrl.toString();
    });

    // Soumission automatique après sélection dans les dropdowns
    $(".parrainage-filters select").on("change", function () {
      // Optionnel : soumission automatique ou attendre le clic sur Filtrer
      // $('#parrainage-filters-form').submit();
    });

    // Validation des dates
    $("#date_from, #date_to").on("change", function () {
      validateDateRange();
    });

    // Autocomplétion pour la recherche parrain (si besoin)
    initParrainAutocomplete();
  }

  /**
   * Valider la plage de dates
   */
  function validateDateRange() {
    var dateFrom = $("#date_from").val();
    var dateTo = $("#date_to").val();

    if (dateFrom && dateTo) {
      var from = new Date(dateFrom);
      var to = new Date(dateTo);

      if (from > to) {
        showMessage(
          "La date de début doit être antérieure à la date de fin.",
          "warning"
        );
        $("#date_to").val("");
      }
    }
  }

  /**
   * Initialiser l'autocomplétion pour les parrains
   */
  function initParrainAutocomplete() {
    $("#parrain_search").on(
      "input",
      debounce(function () {
        var search = $(this).val();

        if (search.length >= 2) {
          // Ici on pourrait ajouter une requête AJAX pour l'autocomplétion
          // Pour l'instant, on utilise la recherche simple
        }
      }, 300)
    );
  }

  /**
   * Initialiser la barre d'outils
   */
  function initToolbar() {
    // Export CSV
    $("#export-csv").on("click", function (e) {
      e.preventDefault();
      exportData("csv");
    });

    // Export Excel
    $("#export-excel").on("click", function (e) {
      e.preventDefault();
      exportData("excel");
    });

    // Actualiser les données
    $("#refresh-data").on("click", function (e) {
      e.preventDefault();
      refreshData();
    });
  }

  /**
   * Exporter les données
   */
  function exportData(format) {
    if (isExporting) {
      return;
    }

    isExporting = true;
    var $button = $("#export-" + format);
    var originalText = $button.text();

    $button.text("Export en cours...").prop("disabled", true);

    // Récupérer les filtres actuels
    var filters = getCurrentFilters();

    // Créer un formulaire temporaire pour l'export
    var $form = $("<form>", {
      method: "POST",
      action: tbParrainageData.ajaxurl,
      target: "_blank",
    });

    $form.append(
      $("<input>", {
        type: "hidden",
        name: "action",
        value: "tb_parrainage_export_data",
      })
    );

    $form.append(
      $("<input>", {
        type: "hidden",
        name: "nonce",
        value: tbParrainageData.nonce,
      })
    );

    $form.append(
      $("<input>", {
        type: "hidden",
        name: "format",
        value: format,
      })
    );

    $form.append(
      $("<input>", {
        type: "hidden",
        name: "filters",
        value: JSON.stringify(filters),
      })
    );

    $form.append(
      $("<input>", {
        type: "hidden",
        name: "limit",
        value: "10000",
      })
    );

    // Ajouter le formulaire au DOM et le soumettre
    $("body").append($form);
    $form.submit();
    $form.remove();

    // Restaurer le bouton après un délai
    setTimeout(function () {
      $button.text(originalText).prop("disabled", false);
      isExporting = false;
    }, 2000);

    showMessage(
      "Export " +
        format.toUpperCase() +
        " lancé. Le fichier va se télécharger.",
      "success"
    );
  }

  /**
   * Actualiser les données
   */
  function refreshData() {
    showMessage("Actualisation en cours...", "success");

    // Invalider le cache et recharger la page
    var currentUrl = new URL(window.location);
    currentUrl.searchParams.set("cache_bust", Date.now());
    window.location.href = currentUrl.toString();
  }

  /**
   * Initialiser l'édition inline
   */
  function initInlineEdit() {
    // Clic sur un avantage pour l'éditer
    $(document).on("click", ".avantage-display", function () {
      var $display = $(this);
      var $edit = $display.siblings(".avantage-edit");
      var $input = $edit.find(".avantage-input");

      $display.hide();
      $edit.show();
      $input.focus().select();
    });

    // Sauvegarder l'avantage
    $(document).on("click", ".save-avantage", function () {
      var $button = $(this);
      var $edit = $button.closest(".avantage-edit");
      var $input = $edit.find(".avantage-input");
      var $display = $edit.siblings(".avantage-display");
      var orderId = $input.data("order-id");
      var newAvantage = $input.val().trim();

      if (!newAvantage) {
        showMessage("L'avantage ne peut pas être vide.", "error");
        return;
      }

      $button.text("Sauvegarde...").prop("disabled", true);

      $.ajax({
        url: tbParrainageData.ajaxurl,
        type: "POST",
        data: {
          action: "tb_parrainage_inline_edit",
          nonce: wp.create_nonce("tb_parrainage_inline_edit"),
          order_id: orderId,
          avantage: newAvantage,
        },
        success: function (response) {
          if (response.success) {
            $display.text(newAvantage);
            $edit.hide();
            $display.show();
            showMessage("Avantage mis à jour avec succès.", "success");
          } else {
            showMessage(
              response.data.message || "Erreur lors de la mise à jour.",
              "error"
            );
          }
        },
        error: function () {
          showMessage("Erreur de communication avec le serveur.", "error");
        },
        complete: function () {
          $button.text("Sauver").prop("disabled", false);
        },
      });
    });

    // Annuler l'édition
    $(document).on("click", ".cancel-avantage", function () {
      var $button = $(this);
      var $edit = $button.closest(".avantage-edit");
      var $display = $edit.siblings(".avantage-display");
      var $input = $edit.find(".avantage-input");

      // Restaurer la valeur originale
      $input.val($display.text());

      $edit.hide();
      $display.show();
    });

    // Échapper pour annuler
    $(document).on("keydown", ".avantage-input", function (e) {
      if (e.key === "Escape") {
        $(this).siblings(".cancel-avantage").click();
      } else if (e.key === "Enter") {
        $(this).siblings(".save-avantage").click();
      }
    });
  }

  /**
   * Initialiser la pagination
   */
  function initPagination() {
    // Gestion des clics sur les liens de pagination
    $(document).on("click", ".pagination-links a", function (e) {
      var href = $(this).attr("href");

      if (href && href !== "#") {
        // Laisser le navigateur gérer la navigation
        return true;
      }

      e.preventDefault();
    });
  }

  /**
   * Récupérer les filtres actuels
   */
  function getCurrentFilters() {
    return {
      date_from: $("#date_from").val(),
      date_to: $("#date_to").val(),
      parrain_search: $("#parrain_search").val(),
      product_id: $("#product_id").val(),
      subscription_status: $("#subscription_status").val(),
    };
  }

  /**
   * Afficher un message
   */
  function showMessage(message, type) {
    var $existing = $(".parrainage-message");
    if ($existing.length) {
      $existing.remove();
    }

    var $message = $("<div>", {
      class: "parrainage-message " + type,
      text: message,
    });

    $(".parrainage-interface-container h2").after($message);

    // Faire disparaître le message après 5 secondes
    setTimeout(function () {
      $message.fadeOut(function () {
        $(this).remove();
      });
    }, 5000);
  }

  /**
   * Fonction debounce pour limiter les appels
   */
  function debounce(func, wait) {
    var timeout;
    return function executedFunction() {
      var context = this;
      var args = arguments;

      var later = function () {
        clearTimeout(timeout);
        func.apply(context, args);
      };

      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * Améliorer l'UX avec des animations
   */
  function enhanceUX() {
    // Animation au survol des lignes de tableau
    $(".parrainage-table tbody tr").hover(
      function () {
        $(this).addClass("fade-in");
      },
      function () {
        $(this).removeClass("fade-in");
      }
    );

    // Animation des boutons de la barre d'outils
    $(".toolbar-left .button")
      .on("mouseenter", function () {
        $(this).addClass("slide-in");
      })
      .on("mouseleave", function () {
        $(this).removeClass("slide-in");
      });
  }

  /**
   * Gestion des raccourcis clavier
   */
  function initKeyboardShortcuts() {
    $(document).on("keydown", function (e) {
      // Ne pas traiter si on est dans un champ de saisie
      if ($(e.target).is("input, textarea, select")) {
        return;
      }

      // Ctrl+F pour focus sur la recherche parrain
      if (e.ctrlKey && e.key === "f") {
        e.preventDefault();
        $("#parrain_search").focus();
      }

      // Ctrl+E pour export CSV
      if (e.ctrlKey && e.key === "e") {
        e.preventDefault();
        $("#export-csv").click();
      }

      // F5 ou Ctrl+R pour actualiser
      if (e.key === "F5" || (e.ctrlKey && e.key === "r")) {
        e.preventDefault();
        $("#refresh-data").click();
      }
    });
  }

  /**
   * Optimisation responsive
   */
  function handleResponsive() {
    function adjustTableForMobile() {
      var windowWidth = $(window).width();

      if (windowWidth < 768) {
        // Sur mobile, ajouter des attributs data pour les labels
        $(".parrainage-table tbody tr").each(function () {
          $(this)
            .find("td")
            .each(function (index) {
              var headerText = $(".parrainage-table thead th").eq(index).text();
              $(this).attr("data-label", headerText);
            });
        });
      }
    }

    // Ajuster au chargement et au redimensionnement
    adjustTableForMobile();
    $(window).on("resize", debounce(adjustTableForMobile, 250));
  }

  /**
   * Validation côté client des formulaires
   */
  function initFormValidation() {
    $("#parrainage-filters-form").on("submit", function (e) {
      var hasErrors = false;

      // Valider les dates
      var dateFrom = $("#date_from").val();
      var dateTo = $("#date_to").val();

      if (dateFrom && dateTo) {
        var from = new Date(dateFrom);
        var to = new Date(dateTo);

        if (from > to) {
          showMessage(
            "La date de début doit être antérieure à la date de fin.",
            "error"
          );
          hasErrors = true;
        }
      }

      // Valider la recherche parrain (longueur max)
      var parrainSearch = $("#parrain_search").val();
      if (parrainSearch && parrainSearch.length > 100) {
        showMessage(
          "La recherche parrain est limitée à 100 caractères.",
          "error"
        );
        hasErrors = true;
      }

      if (hasErrors) {
        e.preventDefault();
        return false;
      }
    });
  }

  /**
   * Gestion des états de chargement
   */
  function showLoading(element) {
    $(element).addClass("loading");
  }

  function hideLoading(element) {
    $(element).removeClass("loading");
  }

  /**
   * Initialisation complète
   */
  function init() {
    initParrainageInterface();
    enhanceUX();
    initKeyboardShortcuts();
    handleResponsive();
    initFormValidation();

    // Afficher un message de bienvenue si c'est la première visite
    if (sessionStorage.getItem("parrainage_first_visit") !== "false") {
      setTimeout(function () {
        showMessage(
          "Bienvenue dans l'interface de parrainage ! Utilisez les filtres pour affiner vos recherches.",
          "success"
        );
        sessionStorage.setItem("parrainage_first_visit", "false");
      }, 1000);
    }
  }

  // Lancer l'initialisation
  init();

  /**
   * Export des fonctions pour utilisation externe
   */
  window.tbParrainageAdmin = {
    showMessage: showMessage,
    refreshData: refreshData,
    exportData: exportData,
    getCurrentFilters: getCurrentFilters,
  };
});

/**
 * Fonctions WordPress hooks
 */
jQuery(document).ready(function ($) {
  // Hook pour les actions WordPress
  $(document).on("tb_parrainage_data_updated", function (e, data) {
    console.log("Données parrainage mises à jour:", data);

    // Ici on pourrait mettre à jour l'interface sans recharger la page
    if (data.success) {
      window.tbParrainageAdmin.showMessage("Données mises à jour.", "success");
    }
  });

  // Hook pour les erreurs
  $(document).on("tb_parrainage_error", function (e, error) {
    console.error("Erreur parrainage:", error);
    window.tbParrainageAdmin.showMessage(
      error.message || "Une erreur est survenue.",
      "error"
    );
  });
});
