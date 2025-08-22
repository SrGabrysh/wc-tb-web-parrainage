/**
 * Gestion des modales d'aide côté client
 * Plugin TB-Web Parrainage
 * @since 2.14.1
 */

jQuery(document).ready(function ($) {
  "use strict";

  // Vérifier que les données sont disponibles
  if (typeof tbClientHelp === "undefined") {
    console.error("TB Parrainage: Données des modales non disponibles");
    return;
  }

  /**
   * Initialisation des modales
   */
  function initClientHelpModals() {
    // Créer le conteneur pour les modales s'il n'existe pas
    if (!$("#tb-client-modals-container").length) {
      $("body").append('<div id="tb-client-modals-container"></div>');
    }

    // Attacher les événements aux icônes d'aide
    bindHelpIconEvents();

    // Log pour debug
    console.log("TB Parrainage: Modales d'aide initialisées");
  }

  /**
   * Attacher les événements click aux icônes
   */
  function bindHelpIconEvents() {
    // Utiliser la délégation d'événements pour gérer les icônes ajoutées dynamiquement
    $(document).on("click", ".tb-client-help-icon", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $icon = $(this);
      var metricKey = $icon.data("metric");
      var modalTitle = $icon.data("title") || "Information";

      // Vérifier que nous avons le contenu pour cette métrique
      if (!tbClientHelp.modals || !tbClientHelp.modals[metricKey]) {
        console.error("TB Parrainage: Contenu non trouvé pour", metricKey);
        showErrorModal();
        return;
      }

      // Récupérer les données de la modal
      var modalData = tbClientHelp.modals[metricKey];

      // Afficher la modal
      showHelpModal(modalData.title || modalTitle, modalData.content);
    });
  }

  /**
   * Afficher une modal d'aide
   * @param {string} title - Titre de la modal
   * @param {string} content - Contenu HTML de la modal
   */
  function showHelpModal(title, content) {
    // Créer un ID unique pour cette modal
    var modalId = "tb-client-help-modal-" + Date.now();

    // Créer le HTML de la modal
    var modalHtml =
      '<div id="' +
      modalId +
      '" class="tb-client-help-modal">' +
      '<div class="tb-client-help-content">' +
      content +
      "</div>" +
      "</div>";

    // Ajouter la modal au conteneur
    $("#tb-client-modals-container").append(modalHtml);

    // Initialiser la modal jQuery UI
    var $modal = $("#" + modalId);

    $modal.dialog({
      title: title,
      modal: true,
      width: Math.min(600, $(window).width() * 0.9), // Max 600px ou 90% de la largeur écran
      maxHeight: Math.min(500, $(window).height() * 0.8), // Max 500px ou 80% de la hauteur écran
      resizable: true,
      draggable: true,
      closeText: "", // Vide pour ne pas afficher de texte
      dialogClass: "tb-client-modal-dialog",
      position: {
        my: "center",
        at: "center",
        of: window,
      },
      create: function () {
        // Personnaliser l'overlay
        $(".ui-widget-overlay").addClass("tb-client-overlay");

        // Ajouter une animation d'entrée
        $(this).parent().hide().fadeIn(300);

        // Accessibilité : focus sur le titre
        $(this).parent().find(".ui-dialog-title").attr("tabindex", "0").focus();
      },
      open: function () {
        // Focus sur le contenu pour l'accessibilité
        $(this).focus();

        // S'assurer que le contenu ne déborde pas
        $(this).css({
          "max-width": "100%",
          "overflow-x": "hidden",
        });
      },
      close: function () {
        // Détruire et supprimer la modal
        $(this).dialog("destroy").remove();
      },
      // PAS DE BOUTONS - Utiliser seulement la croix comme l'admin
      buttons: {},
    });

    // Gérer la touche Escape
    $(document).on("keydown.tbClientModal", function (e) {
      if (e.keyCode === 27) {
        // ESC
        $modal.dialog("close");
        $(document).off("keydown.tbClientModal");
      }
    });

    // Fermer en cliquant sur l'overlay
    $(".ui-widget-overlay").on("click.tbClientModal", function () {
      $modal.dialog("close");
      $(this).off("click.tbClientModal");
    });
  }

  /**
   * Afficher une modal d'erreur
   */
  function showErrorModal() {
    var errorContent =
      '<div class="help-definition" style="border-color: #f44336;">' +
      '<p style="color: #f44336;">Une erreur est survenue lors du chargement de l\'aide.</p>' +
      "</div>" +
      '<div class="help-section">' +
      "<p>Veuillez rafraîchir la page et réessayer. Si le problème persiste, " +
      "contactez notre support technique.</p>" +
      "</div>";

    showHelpModal("Erreur", errorContent);
  }

  /**
   * Gestion du responsive
   */
  function handleResponsive() {
    $(window).on("resize.tbClientModals", function () {
      $(".tb-client-modal-dialog").each(function () {
        var $dialog = $(this);
        var $modal = $dialog.find(".ui-dialog-content");

        if ($modal.dialog("isOpen")) {
          // Ajuster la largeur
          var newWidth = Math.min(600, $(window).width() * 0.9);
          $modal.dialog("option", "width", newWidth);

          // Ajuster la position sur mobile
          if ($(window).width() <= 768) {
            $modal.dialog("option", "position", {
              my: "center top+20",
              at: "center top+20",
              of: window,
            });
          } else {
            $modal.dialog("option", "position", {
              my: "center",
              at: "center",
              of: window,
            });
          }
        }
      });
    });
  }

  /**
   * Amélioration : Animation au survol des icônes
   */
  function enhanceIconInteractions() {
    $(document)
      .on("mouseenter", ".tb-client-help-icon", function () {
        $(this).addClass("hover-active");
      })
      .on("mouseleave", ".tb-client-help-icon", function () {
        $(this).removeClass("hover-active");
      });

    // Tooltip natif au survol
    $(".tb-client-help-icon").each(function () {
      var $icon = $(this);
      if (!$icon.attr("title")) {
        $icon.attr("title", "Cliquez pour en savoir plus");
      }
    });
  }

  /**
   * Initialisation au chargement de la page
   */
  $(function () {
    // Attendre que jQuery UI soit chargé
    if (typeof $.fn.dialog === "undefined") {
      console.error("TB Parrainage: jQuery UI Dialog non disponible");
      return;
    }

    // Initialiser les modales
    initClientHelpModals();

    // Gérer le responsive
    handleResponsive();

    // Améliorer les interactions
    enhanceIconInteractions();

    // Réinitialiser si contenu AJAX chargé (pour compatibilité avec certains thèmes)
    $(document).on("ajaxComplete", function () {
      setTimeout(function () {
        bindHelpIconEvents();
        enhanceIconInteractions();
      }, 100);
    });
  });

  /**
   * Debug mode (activable via console)
   */
  window.tbClientHelpDebug = function () {
    console.log("=== TB Client Help Debug ===");
    console.log("Modales disponibles:", Object.keys(tbClientHelp.modals || {}));
    console.log("Icônes trouvées:", $(".tb-client-help-icon").length);
    console.log("Conteneur modales:", $("#tb-client-modals-container").length);
    console.log("jQuery UI Dialog:", typeof $.fn.dialog !== "undefined");
    return true;
  };
});

/**
 * Polyfill pour les anciens navigateurs
 */
if (!String.prototype.includes) {
  String.prototype.includes = function (search, start) {
    "use strict";
    if (typeof start !== "number") {
      start = 0;
    }
    if (start + search.length > this.length) {
      return false;
    } else {
      return this.indexOf(search, start) !== -1;
    }
  };
}
