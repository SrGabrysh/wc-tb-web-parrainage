/**
 * Auto-initialisation du Template Modal System
 * @since 2.16.3
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Rechercher tous les objets de configuration tbModal*
    for (let key in window) {
      if (key.startsWith("tbModal") && key !== "TBTemplateModals") {
        const config = window[key];
        if (config && config.namespace) {
          console.log(
            "Initialisation Template Modal System pour:",
            config.namespace
          );

          // Créer l'instance du gestionnaire
          const manager = new window.TBTemplateModals({
            namespace: config.namespace,
            modalWidth: config.config ? config.config.modalWidth : 600,
            modalMaxHeight: config.config ? config.config.modalMaxHeight : 500,
            enableCache: config.config ? config.config.enableCache : true,
            cacheDuration: config.config ? config.config.cacheDuration : 300000,
            ajaxUrl: config.ajaxUrl,
            nonce: config.nonce,
            ajaxActions: config.ajaxActions,
            cssClasses: config.cssClasses,
            currentLanguage: config.currentLanguage,
            strings: config.strings || {
              loading: "Chargement...",
              error: "Erreur lors du chargement",
              close: "Fermer",
            },
          });

          // Stocker l'instance pour usage global
          window[key + "Instance"] = manager;
        }
      }
    }
  });
})(jQuery);
