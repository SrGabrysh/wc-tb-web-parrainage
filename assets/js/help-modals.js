/**
 * JavaScript pour les modales d'aide Analytics TB-Web Parrainage
 * @since 2.13.0
 */

(function ($) {
  "use strict";

  // Objet principal pour les modales d'aide
  const TBHelpModals = {
    // Configuration
    config: {
      modalWidth: 600,
      modalMaxHeight: 500,
      animationDuration: 200,
    },

    // Cache pour le contenu
    contentCache: {},

    // Langue actuelle
    currentLanguage: "fr",

    /**
     * Initialisation
     */
    init: function () {
      this.currentLanguage = tbHelpModals.currentLanguage || "fr";
      this.bindEvents();
      this.setupKeyboardNavigation();

      console.log("TB Help Modals initialized");
    },

    /**
     * Liaison des événements
     */
    bindEvents: function () {
      // Clic sur les icônes d'aide
      $(document).on(
        "click",
        ".tb-help-icon",
        this.handleHelpIconClick.bind(this)
      );

      // Changement de langue dans les modales
      $(document).on(
        "change",
        ".tb-language-selector select",
        this.handleLanguageChange.bind(this)
      );

      // Fermeture par Échap
      $(document).on("keydown", this.handleKeydown.bind(this));
    },

    /**
     * Navigation clavier
     */
    setupKeyboardNavigation: function () {
      // Rendre les icônes d'aide focusables
      $(".tb-help-icon").attr("tabindex", "0");

      // Activation par Entrée ou Espace
      $(document).on("keydown", ".tb-help-icon", function (e) {
        if (e.which === 13 || e.which === 32) {
          // Enter ou Space
          e.preventDefault();
          $(this).click();
        }
      });
    },

    /**
     * Gestion du clic sur une icône d'aide
     */
    handleHelpIconClick: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $icon = $(e.currentTarget);
      const metricKey = $icon.data("metric");

      if (!metricKey) {
        console.error("Metric key not found");
        return;
      }

      this.showHelpModal(metricKey);
    },

    /**
     * Afficher la modale d'aide
     */
    showHelpModal: function (metricKey) {
      // Créer la modale si elle n'existe pas
      let $modal = $("#tb-help-modal-" + metricKey);
      if ($modal.length === 0) {
        $modal = this.createModal(metricKey);
      }

      // Charger le contenu
      this.loadHelpContent(metricKey, $modal);

      // Ouvrir la modale avec les bonnes dimensions
      $modal.dialog({
        width: Math.min(600, $(window).width() * 0.9), // Max 600px ou 90% de la largeur écran
        maxHeight: Math.min(500, $(window).height() * 0.8), // Max 500px ou 80% de la hauteur écran
        modal: true,
        resizable: true,
        draggable: true,
        closeText: "", // Vide pour ne pas afficher de texte
        dialogClass: "tb-help-modal-dialog",
        position: {
          my: "center",
          at: "center",
          of: window,
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
          // Restaurer le focus sur l'icône d'aide
          $('.tb-help-icon[data-metric="' + metricKey + '"]').focus();
        },
      });
    },

    /**
     * Créer une modale
     */
    createModal: function (metricKey) {
      const $modal = $("<div>", {
        id: "tb-help-modal-" + metricKey,
        class: "tb-help-modal",
        title: tbHelpModals.strings.loading,
      });

      $("body").append($modal);
      return $modal;
    },

    /**
     * Charger le contenu d'aide
     */
    loadHelpContent: function (metricKey, $modal) {
      // Vérifier le cache
      const cacheKey = metricKey + "_" + this.currentLanguage;
      if (this.contentCache[cacheKey]) {
        this.renderHelpContent(this.contentCache[cacheKey], $modal);
        return;
      }

      // Afficher le chargement
      $modal.html(
        '<div class="tb-help-loading">' +
          tbHelpModals.strings.loading +
          "</div>"
      );

      // Requête AJAX
      $.ajax({
        url: tbHelpModals.ajaxUrl,
        type: "POST",
        data: {
          action: "tb_analytics_get_help_content",
          nonce: tbHelpModals.nonce,
          metric: metricKey,
          language: this.currentLanguage,
        },
        success: (response) => {
          if (response.success && response.data.content) {
            // Mettre en cache
            this.contentCache[cacheKey] = response.data.content;

            // Afficher le contenu
            this.renderHelpContent(response.data.content, $modal);

            // Mettre à jour le titre de la modale
            $modal.dialog(
              "option",
              "title",
              response.data.content.title || metricKey
            );
          } else {
            this.showError($modal, response.data || tbHelpModals.strings.error);
          }
        },
        error: () => {
          this.showError($modal, tbHelpModals.strings.error);
        },
      });
    },

    /**
     * Afficher le contenu d'aide
     */
    renderHelpContent: function (content, $modal) {
      let html = "";

      // Sélecteur de langue
      html += this.renderLanguageSelector();

      // Contenu principal
      html += '<div class="tb-help-content">';

      // Définition
      if (content.definition) {
        html +=
          '<div class="help-definition"><p>' +
          this.escapeHtml(content.definition) +
          "</p></div>";
      }

      // Détails
      if (content.details && content.details.length > 0) {
        html += '<div class="help-section">';
        html += "<h4>Détails</h4>";
        html += "<ul>";
        content.details.forEach((detail) => {
          html += "<li>" + this.escapeHtml(detail) + "</li>";
        });
        html += "</ul>";
        html += "</div>";
      }

      // Formule (pour ROI)
      if (content.formula) {
        html += '<div class="help-section">';
        html += "<h4>Calcul</h4>";
        html +=
          "<p><strong>" + this.escapeHtml(content.formula) + "</strong></p>";
        html += "</div>";
      }

      // Exemple
      if (content.example) {
        html += '<div class="help-example">';
        html +=
          "<strong>Exemple :</strong> " + this.escapeHtml(content.example);
        html += "</div>";
      }

      // Interprétation
      if (content.interpretation) {
        html += '<div class="help-section">';
        html += "<h4>Interprétation</h4>";
        if (Array.isArray(content.interpretation)) {
          html += "<ul>";
          content.interpretation.forEach((item) => {
            html += "<li>" + this.escapeHtml(item) + "</li>";
          });
          html += "</ul>";
        } else {
          html += "<p>" + this.escapeHtml(content.interpretation) + "</p>";
        }
        html += "</div>";
      }

      // Critères (pour santé système)
      if (content.criteria && content.criteria.length > 0) {
        html += '<div class="help-section">';
        html += "<h4>Critères d'évaluation</h4>";
        html += "<ul>";
        content.criteria.forEach((criterion) => {
          html += "<li>" + this.escapeHtml(criterion) + "</li>";
        });
        html += "</ul>";
        html += "</div>";
      }

      // Niveaux (pour santé système)
      if (content.levels && content.levels.length > 0) {
        html += '<div class="help-section">';
        html += "<h4>Niveaux</h4>";
        html += '<div class="health-levels">';
        content.levels.forEach((level, index) => {
          const classes = ["excellent", "good", "warning", "critical"];
          html +=
            '<div class="health-level ' + (classes[index] || "good") + '">';
          html += this.escapeHtml(level);
          html += "</div>";
        });
        html += "</div>";
        html += "</div>";
      }

      // Précision
      if (content.precision) {
        html += '<div class="help-precision">';
        html +=
          "<strong>Précision importante :</strong> " +
          this.escapeHtml(content.precision);
        html += "</div>";
      }

      // Conseils
      if (content.tips && content.tips.length > 0) {
        html += '<div class="help-tips">';
        html += "<h4>💡 Conseils</h4>";
        html += "<ul>";
        content.tips.forEach((tip) => {
          html += "<li>" + this.escapeHtml(tip) + "</li>";
        });
        html += "</ul>";
        html += "</div>";
      }

      html += "</div>"; // fin tb-help-content

      $modal.html(html);
    },

    /**
     * Afficher un sélecteur de langue
     */
    renderLanguageSelector: function () {
      return (
        '<div class="tb-language-selector">' +
        "<label>" +
        tbHelpModals.strings.language +
        " :</label>" +
        "<select>" +
        '<option value="fr"' +
        (this.currentLanguage === "fr" ? " selected" : "") +
        ">Français</option>" +
        '<option value="en"' +
        (this.currentLanguage === "en" ? " selected" : "") +
        ">English</option>" +
        "</select>" +
        "</div>"
      );
    },

    /**
     * Gestion du changement de langue
     */
    handleLanguageChange: function (e) {
      const newLanguage = $(e.target).val();

      if (newLanguage !== this.currentLanguage) {
        this.currentLanguage = newLanguage;

        // Sauvegarder la préférence
        $.ajax({
          url: tbHelpModals.ajaxUrl,
          type: "POST",
          data: {
            action: "tb_analytics_set_help_language",
            nonce: tbHelpModals.nonce,
            language: newLanguage,
          },
        });

        // Recharger le contenu de la modale active
        const $modal = $(e.target).closest(".tb-help-modal");
        if ($modal.length > 0) {
          const metricKey = $modal.attr("id").replace("tb-help-modal-", "");
          this.loadHelpContent(metricKey, $modal);
        }
      }
    },

    /**
     * Gestion des touches clavier
     */
    handleKeydown: function (e) {
      // Fermeture par Échap
      if (e.which === 27) {
        // Escape
        $(".tb-help-modal").dialog("close");
      }
    },

    /**
     * Afficher une erreur
     */
    showError: function ($modal, message) {
      $modal.html(
        '<div class="tb-help-error">' + this.escapeHtml(message) + "</div>"
      );
    },

    /**
     * Échapper le HTML pour la sécurité
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialisation quand le DOM est prêt
  $(document).ready(function () {
    // Vérifier que les dépendances sont disponibles
    if (typeof tbHelpModals === "undefined") {
      console.error("tbHelpModals configuration not found");
      return;
    }

    if (typeof $.fn.dialog === "undefined") {
      console.error("jQuery UI Dialog not loaded");
      return;
    }

    // Initialiser le système de modales
    TBHelpModals.init();
  });

  // Exposer l'objet globalement pour les tests/debug
  window.TBHelpModals = TBHelpModals;
})(jQuery);
