/**
 * JavaScript générique pour Template Modal System
 * TB-Web Parrainage - Version réutilisable
 * @since 2.14.1
 * @version 1.0.0
 */

(function ($) {
  "use strict";

  // Fonction de création d'un gestionnaire de modales générique
  window.TBTemplateModals = function (config) {
    // Configuration par défaut
    const defaultConfig = {
      namespace: "generic",
      modalWidth: 600,
      modalMaxHeight: 500,
      animationDuration: 200,
      enableCache: true,
      cacheDuration: 300000, // 5 minutes en millisecondes
      enableKeyboardNav: true,
      enableMultilang: false,
      cssClasses: {
        icon: "tb-modal-generic-icon",
        modal: "tb-modal-generic-modal",
        content: "tb-modal-generic-content",
      },
      ajaxActions: {
        getContent: "tb_modal_generic_get_content",
        setLanguage: "tb_modal_generic_set_language",
      },
      strings: {
        loading: "Chargement...",
        error: "Erreur lors du chargement",
        close: "Fermer",
        language: "Langue",
        help: "Aide",
      },
    };

    // Fusionner la configuration
    this.config = $.extend(true, {}, defaultConfig, config);

    // Cache pour le contenu
    this.contentCache = {};

    // Langue actuelle
    this.currentLanguage = this.config.currentLanguage || "fr";

    // Statistiques d'utilisation
    this.stats = {
      modalsOpened: 0,
      cacheHits: 0,
      ajaxRequests: 0,
      errors: 0,
    };

    /**
     * Initialisation du gestionnaire
     */
    this.init = function () {
      this.bindEvents();

      if (this.config.enableKeyboardNav) {
        this.setupKeyboardNavigation();
      }

      this.log("Template Modal Manager initialisé", {
        namespace: this.config.namespace,
        config: this.config,
      });

      return this;
    };

    /**
     * Liaison des événements
     */
    this.bindEvents = function () {
      const self = this;

      // Clic sur les icônes d'aide (avec namespace)
      $(document).on(
        "click",
        `[data-namespace="${this.config.namespace}"] .${this.config.cssClasses.icon}, .${this.config.cssClasses.icon}[data-namespace="${this.config.namespace}"]`,
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleHelpIconClick($(this));
        }
      );

      // Changement de langue dans les modales (si multilingue activé)
      if (this.config.enableMultilang) {
        $(document).on(
          "change",
          ".tb-modal-language-selector select",
          function (e) {
            self.handleLanguageChange($(this));
          }
        );
      }

      // Fermeture par Échap
      if (this.config.enableKeyboardNav) {
        $(document).on("keydown", function (e) {
          self.handleKeydown(e);
        });
      }
    };

    /**
     * Configuration de la navigation clavier
     */
    this.setupKeyboardNavigation = function () {
      const self = this;
      const iconSelector = `[data-namespace="${this.config.namespace}"] .${this.config.cssClasses.icon}, .${this.config.cssClasses.icon}[data-namespace="${this.config.namespace}"]`;

      // Rendre les icônes d'aide focusables
      $(iconSelector).attr("tabindex", "0");

      // Activation par Entrée ou Espace
      $(document).on("keydown", iconSelector, function (e) {
        if (e.which === 13 || e.which === 32) {
          // Enter ou Space
          e.preventDefault();
          self.handleHelpIconClick($(this));
        }
      });
    };

    /**
     * Gestion du clic sur une icône d'aide
     */
    this.handleHelpIconClick = function ($icon) {
      const elementKey = $icon.data("modal-key") || $icon.data("element-key");
      const namespace = $icon.data("namespace");

      // Vérifier que le namespace correspond
      if (namespace !== this.config.namespace) {
        this.log(
          "Namespace incorrect",
          { expected: this.config.namespace, received: namespace },
          "warn"
        );
        return;
      }

      if (!elementKey) {
        this.log("Clé d'élément non trouvée", { icon: $icon }, "error");
        this.stats.errors++;
        return;
      }

      this.showModal(elementKey, $icon);
    };

    /**
     * Afficher une modale
     */
    this.showModal = function (elementKey, $triggerIcon) {
      const self = this;
      this.stats.modalsOpened++;

      // Créer la modale si elle n'existe pas
      let $modal = this.getOrCreateModal(elementKey);

      // Charger le contenu
      this.loadModalContent(elementKey, $modal);

      // Configuration responsive
      const windowWidth = $(window).width();
      const windowHeight = $(window).height();
      const modalWidth = Math.min(this.config.modalWidth, windowWidth * 0.9);
      const modalMaxHeight = Math.min(
        this.config.modalMaxHeight,
        windowHeight * 0.8
      );

      // Ouvrir la modale avec jQuery UI Dialog
      $modal.dialog({
        width: modalWidth,
        maxHeight: modalMaxHeight,
        modal: true,
        resizable: true,
        draggable: true,
        closeText: "", // Pas de texte sur le bouton fermer
        dialogClass: `${this.config.cssClasses.modal}-dialog tb-modal-${this.config.namespace}-dialog`,
        position: {
          my: "center",
          at: "center",
          of: window,
        },
        create: function () {
          // Personnaliser l'overlay
          $(".ui-widget-overlay").addClass(
            `tb-modal-${self.config.namespace}-overlay`
          );

          // Animation d'entrée
          $(this).parent().hide().fadeIn(self.config.animationDuration);
        },
        open: function () {
          // Focus pour l'accessibilité
          $(this).focus();

          // S'assurer que le contenu ne déborde pas
          $(this).css({
            "max-width": "100%",
            "overflow-x": "hidden",
          });

          self.log("Modal ouverte", { elementKey: elementKey });
        },
        close: function () {
          // Restaurer le focus sur l'icône déclencheuse
          if ($triggerIcon && $triggerIcon.length) {
            $triggerIcon.focus();
          }

          self.log("Modal fermée", { elementKey: elementKey });
        },
      });
    };

    /**
     * Obtenir ou créer une modale
     */
    this.getOrCreateModal = function (elementKey) {
      const modalId = `tb-modal-${this.config.namespace}-${elementKey}`;
      let $modal = $("#" + modalId);

      if ($modal.length === 0) {
        $modal = $("<div>", {
          id: modalId,
          class: `${this.config.cssClasses.modal} tb-modal-${this.config.namespace}-modal`,
          title: this.config.strings.loading,
        });

        $("body").append($modal);
      }

      return $modal;
    };

    /**
     * Charger le contenu d'une modale
     */
    this.loadModalContent = function (elementKey, $modal) {
      const self = this;

      // Vérifier le cache
      const cacheKey = `${elementKey}_${this.currentLanguage}`;
      if (this.config.enableCache && this.contentCache[cacheKey]) {
        const cached = this.contentCache[cacheKey];

        // Vérifier si le cache n'a pas expiré
        if (Date.now() - cached.timestamp < this.config.cacheDuration) {
          this.renderModalContent(cached.content, $modal);
          this.stats.cacheHits++;
          return;
        }
      }

      // Afficher l'état de chargement
      $modal.html(
        `<div class="tb-modal-loading">${this.config.strings.loading}</div>`
      );

      // Préparer les données AJAX
      const ajaxData = {
        action: this.config.ajaxActions.getContent,
        nonce: this.config.nonce,
        element_key: elementKey,
        namespace: this.config.namespace,
        language: this.currentLanguage,
      };

      // Requête AJAX
      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: ajaxData,
        timeout: 10000, // 10 secondes
        success: function (response) {
          self.stats.ajaxRequests++;

          if (response.success && response.data.content) {
            // Mettre en cache si activé
            if (self.config.enableCache) {
              self.contentCache[cacheKey] = {
                content: response.data.content,
                timestamp: Date.now(),
              };
            }

            // Afficher le contenu
            self.renderModalContent(response.data.content, $modal);

            // Mettre à jour le titre de la modale
            const title = response.data.content.title || elementKey;
            $modal.dialog("option", "title", title);

            self.log("Contenu chargé avec succès", {
              elementKey: elementKey,
              language: self.currentLanguage,
            });
          } else {
            const errorMsg = response.data || self.config.strings.error;
            self.showError($modal, errorMsg);
            self.stats.errors++;
          }
        },
        error: function (xhr, status, error) {
          self.stats.ajaxRequests++;
          self.stats.errors++;

          let errorMsg = self.config.strings.error;

          if (status === "timeout") {
            errorMsg = "Délai d'attente dépassé";
          } else if (status === "parsererror") {
            errorMsg = "Erreur de format de réponse";
          }

          self.showError($modal, errorMsg);
          self.log(
            "Erreur AJAX",
            {
              elementKey: elementKey,
              status: status,
              error: error,
            },
            "error"
          );
        },
      });
    };

    /**
     * Afficher le contenu d'une modale
     */
    this.renderModalContent = function (content, $modal) {
      let html = "";

      // Sélecteur de langue (si multilingue activé)
      if (this.config.enableMultilang) {
        html += this.renderLanguageSelector();
      }

      // Contenu principal avec padding pour une meilleure présentation
      html += `<div class="${this.config.cssClasses.content} tb-modal-${this.config.namespace}-content" style="padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">`;

      // Titre principal - SANS escapeHtml pour éviter corruption
      if (content.title) {
        html += `<h3 style="color: #2c3e50 !important; margin-bottom: 15px !important; margin-top: 0 !important; display: block !important; visibility: visible !important;">${content.title}</h3>`;
      }

      // Définition principale - SANS escapeHtml pour éviter corruption
      if (content.definition) {
        html += `<div class="modal-definition" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">
          <p style="font-size: 14px !important; line-height: 1.5 !important; margin-bottom: 10px !important; display: block !important; visibility: visible !important; color: #333 !important;">${content.definition}</p>
        </div>`;
      }

      // Contenu libre (priorité sur le contenu structuré)
      if (content.content) {
        html += content.content;
      } else {
        // Contenu structuré avec styles améliorés
        html += this.renderStructuredContent(content);
      }

      html += "</div>";

      $modal.html(html);

      // Correction CSS immédiate + forçage de visibilité TOTAL
      const self = this;
      setTimeout(() => {
        const modalElement = $modal[0];
        if (modalElement) {
          // Styles de base pour la modal
          modalElement.style.minHeight = "400px";
          modalElement.style.maxHeight = "800px";
          modalElement.style.height = "auto";
          modalElement.style.overflow = "visible";
          modalElement.style.overflowY = "auto";
          
          // FORCER la visibilité de TOUS les éléments enfants
          const allElements = modalElement.querySelectorAll('*');
          allElements.forEach(el => {
            if (el.tagName !== 'SCRIPT' && el.tagName !== 'STYLE') {
              // Affichage adapté selon le type d'élément
              if (el.tagName === 'LI') {
                el.style.display = 'list-item';
              } else if (el.tagName === 'UL') {
                el.style.display = 'block';
                el.style.listStyle = 'disc';
                el.style.paddingLeft = '25px';
              } else {
                el.style.display = 'block';
              }
              el.style.visibility = 'visible';
              el.style.opacity = '1';
              
              // Forcer la couleur du texte
              if (['P', 'LI', 'DIV', 'SPAN'].includes(el.tagName)) {
                el.style.color = '#333';
              }
            }
          });
          
          // Forcer le recalcul de la taille
          modalElement.offsetHeight;
          
          console.log(`[TB Modal ${self.config.namespace}] VISIBILITÉ FORCÉE - éléments traités: ${allElements.length}, hauteur: ${modalElement.scrollHeight}px`);
        }
      }, 100);
    };

    /**
     * Rendre le contenu structuré
     */
    this.renderStructuredContent = function (content) {
      let html = "";

      // Détails - SANS emoji ni escapeHtml
      if (content.details && Array.isArray(content.details)) {
        html += '<div class="modal-section" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">';
        html += '<h4 style="color: #34495e !important; margin-bottom: 10px !important; display: block !important; visibility: visible !important; font-weight: 600 !important;">Détails</h4>';
        html += '<ul style="margin-left: 20px !important; list-style: disc !important; display: block !important; visibility: visible !important;">';
        content.details.forEach(function (detail) {
          html += `<li style="margin-bottom: 5px !important; display: list-item !important; visibility: visible !important; color: #333 !important;">${detail}</li>`;
        }, this);
        html += "</ul>";
        html += "</div>";
      }

      // Interprétation - SANS emoji ni escapeHtml
      if (content.interpretation) {
        html += '<div class="modal-section" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">';
        html += '<h4 style="color: #34495e !important; margin-bottom: 10px !important; display: block !important; visibility: visible !important; font-weight: 600 !important;">Interprétation</h4>';
        if (Array.isArray(content.interpretation)) {
          html += '<ul style="margin-left: 20px !important; list-style: disc !important; display: block !important; visibility: visible !important;">';
          content.interpretation.forEach(function (item) {
            html += `<li style="margin-bottom: 5px !important; display: list-item !important; visibility: visible !important; color: #333 !important;">${item}</li>`;
          }, this);
          html += "</ul>";
        } else {
          html += `<p style="font-style: italic !important; background: #f8f9fa !important; padding: 10px !important; border-radius: 5px !important; display: block !important; visibility: visible !important; color: #333 !important;">${content.interpretation}</p>`;
        }
        html += "</div>";
      }

      // Formule (si présente) - SANS emoji ni escapeHtml
      if (content.formula) {
        html += `<div class="modal-example" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">
          <div style="background: #e3f2fd !important; padding: 15px !important; border-radius: 5px !important; border-left: 4px solid #2196f3 !important; display: block !important; visibility: visible !important;">
            <strong style="display: inline !important; visibility: visible !important; color: #333 !important;">Formule :</strong> <span style="display: inline !important; visibility: visible !important; color: #333 !important;">${content.formula}</span>
          </div>
        </div>`;
      }

      // Exemple (si présent) - SANS emoji ni escapeHtml
      if (content.example) {
        html += `<div class="modal-example" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">
          <div style="background: #e8f5e8 !important; padding: 15px !important; border-radius: 5px !important; border-left: 4px solid #27ae60 !important; display: block !important; visibility: visible !important;">
            <strong style="display: inline !important; visibility: visible !important; color: #333 !important;">Exemple :</strong> <span style="display: inline !important; visibility: visible !important; color: #333 !important;">${content.example}</span>
          </div>
        </div>`;
      }

      // Précision (si présente) - SANS emoji ni escapeHtml
      if (content.precision) {
        html += `<div class="modal-precision" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">
          <div style="background: #fff3cd !important; padding: 15px !important; border-radius: 5px !important; border-left: 4px solid #ffc107 !important; display: block !important; visibility: visible !important;">
            <strong style="display: inline !important; visibility: visible !important; color: #333 !important;">Précision :</strong> <span style="display: inline !important; visibility: visible !important; color: #333 !important;">${content.precision}</span>
          </div>
        </div>`;
      }

      // Conseils - SANS emoji ni escapeHtml
      if (content.tips && Array.isArray(content.tips)) {
        html += '<div class="modal-tips" style="margin-bottom: 15px !important; display: block !important; visibility: visible !important;">';
        html += '<h4 style="color: #34495e !important; margin-bottom: 10px !important; display: block !important; visibility: visible !important; font-weight: 600 !important;">Conseils</h4>';
        html += '<ul style="margin-left: 20px !important; list-style: disc !important; display: block !important; visibility: visible !important;">';
        content.tips.forEach(function (tip) {
          html += `<li style="margin-bottom: 5px !important; color: #2c3e50 !important; display: list-item !important; visibility: visible !important;">${tip}</li>`;
        }, this);
        html += "</ul>";
        html += "</div>";
      }

      return html;
    };

    /**
     * Rendre le sélecteur de langue
     */
    this.renderLanguageSelector = function () {
      const languages = {
        fr: "Français",
        en: "English",
        es: "Español",
        de: "Deutsch",
        it: "Italiano",
      };

      let html = '<div class="tb-modal-language-selector">';
      html += `<label>${this.config.strings.language} :</label>`;
      html += `<select data-namespace="${this.config.namespace}">`;

      Object.keys(languages).forEach((code) => {
        const selected = code === this.currentLanguage ? "selected" : "";
        html += `<option value="${code}" ${selected}>${languages[code]}</option>`;
      });

      html += "</select>";
      html += "</div>";
      html += '<div class="tb-modal-clearfix"></div>';

      return html;
    };

    /**
     * Afficher une erreur
     */
    this.showError = function ($modal, errorMessage) {
      const html = `<div class="tb-modal-error">${this.escapeHtml(
        errorMessage
      )}</div>`;
      $modal.html(html);
    };

    /**
     * Gestion du changement de langue
     */
    this.handleLanguageChange = function ($select) {
      const newLanguage = $select.val();
      const namespace = $select.data("namespace");

      // Vérifier que le namespace correspond
      if (namespace !== this.config.namespace) {
        return;
      }

      if (newLanguage !== this.currentLanguage) {
        this.currentLanguage = newLanguage;

        // Sauvegarder la préférence via AJAX (si multilingue activé)
        if (
          this.config.enableMultilang &&
          this.config.ajaxActions.setLanguage
        ) {
          this.saveLanguagePreference(newLanguage);
        }

        // Recharger le contenu de la modale ouverte
        this.reloadCurrentModal();
      }
    };

    /**
     * Sauvegarder la préférence de langue
     */
    this.saveLanguagePreference = function (language) {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: this.config.ajaxActions.setLanguage,
          nonce: this.config.nonce,
          language: language,
          namespace: this.config.namespace,
        },
        success: function (response) {
          if (response.success) {
            self.log("Langue sauvegardée", { language: language });
          }
        },
        error: function () {
          self.log("Erreur sauvegarde langue", { language: language }, "warn");
        },
      });
    };

    /**
     * Recharger la modale actuellement ouverte
     */
    this.reloadCurrentModal = function () {
      const $openModal = $(
        `.ui-dialog:visible .${this.config.cssClasses.modal}`
      );

      if ($openModal.length) {
        const modalId = $openModal.attr("id");
        const elementKey = modalId.replace(
          `tb-modal-${this.config.namespace}-`,
          ""
        );

        // Vider le cache pour cet élément
        const cacheKey = `${elementKey}_${this.currentLanguage}`;
        delete this.contentCache[cacheKey];

        // Recharger le contenu
        this.loadModalContent(elementKey, $openModal);
      }
    };

    /**
     * Gestion des touches clavier
     */
    this.handleKeydown = function (e) {
      // Fermer avec Échap
      if (e.which === 27) {
        // Échap
        const $openModal = $(
          `.ui-dialog:visible .${this.config.cssClasses.modal}`
        );
        if ($openModal.length) {
          $openModal.dialog("close");
        }
      }
    };

    /**
     * Échapper le HTML pour la sécurité
     */
    this.escapeHtml = function (text) {
      if (typeof text !== "string") {
        return text;
      }

      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      };

      return text.replace(/[&<>"']/g, function (m) {
        return map[m];
      });
    };

    /**
     * Logger simple
     */
    this.log = function (message, data, level) {
      level = level || "info";

      if (typeof console !== "undefined" && console[level]) {
        const prefix = `[TB Modal ${this.config.namespace}]`;
        console[level](prefix, message, data || "");
      }
    };

    /**
     * Obtenir les statistiques d'utilisation
     */
    this.getStats = function () {
      return {
        ...this.stats,
        cacheSize: Object.keys(this.contentCache).length,
        namespace: this.config.namespace,
      };
    };

    /**
     * Vider le cache
     */
    this.clearCache = function () {
      this.contentCache = {};
      this.log("Cache vidé");
    };

    /**
     * Méthodes publiques pour l'usage programmatique
     */

    /**
     * Ouvrir une modale par clé
     */
    this.openModal = function (elementKey) {
      this.showModal(elementKey, null);
    };

    /**
     * Définir du contenu de modale directement (bypass AJAX)
     */
    this.setModalContent = function (elementKey, content, language) {
      language = language || this.currentLanguage;
      const cacheKey = `${elementKey}_${language}`;

      this.contentCache[cacheKey] = {
        content: content,
        timestamp: Date.now(),
      };

      this.log("Contenu défini directement", {
        elementKey: elementKey,
        language: language,
      });
    };

    /**
     * Fermer toutes les modales
     */
    this.closeAllModals = function () {
      $(`.ui-dialog:visible .${this.config.cssClasses.modal}`).dialog("close");
    };

    // Auto-initialisation si pas de configuration custom
    if (!config || config.autoInit !== false) {
      this.init();
    }

    return this;
  };

  // Utilité globale pour créer rapidement un gestionnaire de modales
  window.createTBModalManager = function (namespace, customConfig) {
    customConfig = customConfig || {};
    customConfig.namespace = namespace;

    return new window.TBTemplateModals(customConfig);
  };
})(jQuery);
