/**
 * JavaScript pour les interactions des nouvelles fonctionnalités de remise v2.4.0
 * Gestion des popups et des interactions utilisateur
 */

jQuery(document).ready(function ($) {
  /**
   * GESTION INTELLIGENTE DES POPUPS - Version corrigée
   */
  function initDiscountPopups() {
    var $activePopup = null;
    var hideTimeout = null;

    // Afficher popup avec positionnement intelligent
    function showPopup($badge) {
      // Fermer popup actif
      hideAllPopups();

      var $popup = $badge.siblings(".discount-popup");
      if (!$popup.length) return;

      // Afficher le popup
      $popup.show();
      $activePopup = $popup;

      // Positionnement intelligent après affichage
      setTimeout(function () {
        adjustPopupPosition($popup, $badge);
      }, 10);
    }

    // Cacher tous les popups
    function hideAllPopups() {
      $(".discount-popup").hide();
      $activePopup = null;
    }

    // Positionnement intelligent selon l'espace disponible
    function adjustPopupPosition($popup, $badge) {
      var badgeOffset = $badge.offset();
      var popupWidth = $popup.outerWidth();
      var windowWidth = $(window).width();

      // Éviter débordement à droite
      if (badgeOffset.left + popupWidth / 2 > windowWidth - 20) {
        $popup.css({
          left: "auto",
          right: "0",
          transform: "translateX(0)",
        });
      }
      // Éviter débordement à gauche
      else if (badgeOffset.left - popupWidth / 2 < 20) {
        $popup.css({
          left: "0",
          transform: "translateX(0)",
        });
      }
    }

    // ÉVÉNEMENTS
    $(document).on("mouseenter", ".discount-badge", function () {
      var $badge = $(this);
      clearTimeout(hideTimeout);
      showPopup($badge);
    });

    $(document).on("mouseleave", ".discount-badge", function () {
      hideTimeout = setTimeout(function () {
        if (!$(".discount-popup:hover").length) {
          hideAllPopups();
        }
      }, 300);
    });

    $(document).on("mouseenter", ".discount-popup", function () {
      clearTimeout(hideTimeout);
    });

    $(document).on("mouseleave", ".discount-popup", function () {
      hideTimeout = setTimeout(hideAllPopups, 300);
    });

    // Clic extérieur - Fermer popup
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".discount-badge, .discount-popup").length) {
        hideAllPopups();
      }
    });
  }

  /**
   * Animation des badges de statut
   */
  function initStatusAnimations() {
    // Animation de pulsation pour les statuts "pending"
    $(".discount-status-pending").each(function () {
      var $badge = $(this);
      setInterval(function () {
        $badge.animate({ opacity: 0.6 }, 1000).animate({ opacity: 1 }, 1000);
      }, 2000);
    });

    // Effet de hover pour tous les badges
    $(".discount-badge").hover(
      function () {
        $(this).css("transform", "scale(1.05)");
      },
      function () {
        $(this).css("transform", "scale(1)");
      }
    );
  }

  /**
   * Actualisation périodique des statuts pending
   */
  function initAutoRefresh() {
    // Actualiser les statuts "pending" toutes les 30 secondes
    if ($(".discount-status-pending").length > 0) {
      setInterval(function () {
        // Simuler un changement de statut pour la démo
        $(".discount-status-pending").each(function () {
          if (Math.random() > 0.7) {
            // 30% de chance
            $(this)
              .removeClass("discount-status-pending")
              .addClass("discount-status-active")
              .text("ACTIVE");

            // Notification
            showDiscountNotification("Remise appliquée avec succès !");
          }
        });
      }, 30000);
    }
  }

  /**
   * Affichage notifications
   */
  function showDiscountNotification(message) {
    var $notification = $(
      '<div class="discount-notification">' + message + "</div>"
    );
    $notification.css({
      position: "fixed",
      top: "20px",
      right: "20px",
      background: "#46b450",
      color: "white",
      padding: "10px 20px",
      "border-radius": "4px",
      "z-index": "9999",
      opacity: "0",
    });

    $("body").append($notification);

    $notification.animate({ opacity: 1 }, 300);

    setTimeout(function () {
      $notification.animate({ opacity: 0 }, 300, function () {
        $(this).remove();
      });
    }, 3000);
  }

  /**
   * Gestion responsive des popups
   */
  function handleResponsivePopups() {
    function adjustPopups() {
      if ($(window).width() < 768) {
        // Sur mobile, transformer les popups en modales
        $(".discount-popup").addClass("mobile-modal");
      } else {
        $(".discount-popup").removeClass("mobile-modal");
      }
    }

    adjustPopups();
    $(window).on("resize", adjustPopups);
  }

  /**
   * Améliorer l'accessibilité
   */
  function enhanceAccessibility() {
    // Ajouter des attributs ARIA
    $(".discount-badge").attr("aria-describedby", function () {
      var orderId = $(this).data("order-id");
      return orderId ? "discount-popup-" + orderId : null;
    });

    $(".discount-popup")
      .attr("id", function () {
        var orderId = $(this).data("order-id");
        return orderId ? "discount-popup-" + orderId : null;
      })
      .attr("role", "tooltip");

    // Navigation au clavier
    $(".discount-badge").on("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        $(this).siblings(".discount-popup").toggle();
      }
    });
  }

  /**
   * Animations d'entrée pour les nouveaux éléments
   */
  function animateNewElements() {
    $(".column-remise-appliquee, .column-statut-remise").each(function () {
      $(this).css({
        opacity: "0",
        transform: "translateX(20px)",
      });

      $(this).animate(
        {
          opacity: "1",
        },
        {
          duration: 500,
          step: function (now) {
            $(this).css("transform", "translateX(" + 20 * (1 - now) + "px)");
          },
        }
      );
    });
  }

  /**
   * Filtrage intelligent des colonnes remise
   */
  function initSmartFiltering() {
    // Ajouter un filtre rapide pour les statuts de remise
    if ($(".parrainage-filters").length) {
      var $quickFilter = $(
        '<div class="quick-filter-remises" style="margin-top: 10px;">' +
          "<label>Statuts remise : </label>" +
          '<button type="button" class="button filter-all" data-status="all">Tous</button>' +
          '<button type="button" class="button filter-active" data-status="active">Actives</button>' +
          '<button type="button" class="button filter-pending" data-status="pending">En attente</button>' +
          '<button type="button" class="button filter-failed" data-status="failed">Échec</button>' +
          "</div>"
      );

      $(".parrainage-filters").append($quickFilter);

      // Gérer les clics sur les filtres rapides
      $(".quick-filter-remises .button").on("click", function () {
        var status = $(this).data("status");
        var $rows = $(".parrainage-table tbody tr");

        // Réinitialiser les boutons
        $(".quick-filter-remises .button").removeClass("button-primary");
        $(this).addClass("button-primary");

        if (status === "all") {
          $rows.show();
        } else {
          $rows.hide();
          $rows
            .filter(function () {
              return $(this).find(".discount-status-" + status).length > 0;
            })
            .show();
        }
      });
    }
  }

  // Initialisation avec délai pour s'assurer que le DOM est complètement chargé
  setTimeout(function () {
    if ($(".parrainage-interface-container").length > 0) {
      initDiscountPopups();
      initStatusAnimations();
      initAutoRefresh();
      handleResponsivePopups();
      enhanceAccessibility();
      animateNewElements();
      initSmartFiltering();

      console.log("Interface remises parrain initialisée (mode mock v2.4.0)");
    }
  }, 500);

  /**
   * Export des fonctions pour utilisation externe
   */
  window.tbParrainageDiscounts = {
    showNotification: showDiscountNotification,
    refreshPopups: initDiscountPopups,
  };
});
