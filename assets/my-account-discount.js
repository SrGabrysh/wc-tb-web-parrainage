/**
 * JavaScript pour l'interface client des remises v2.4.0
 * Interactions et animations côté Mon Compte
 */

jQuery(document).ready(function ($) {
  /**
   * Animation du résumé des économies
   */
  function animateSavings() {
    $(".savings-value").each(function () {
      var $this = $(this);
      var text = $this.text();

      // Animer les montants numériques - CORRECTION v2.9.3
      if (text.includes("€")) {
        // CORRECTION : Ignorer les montants avec date ou "HT" qui ne sont pas des montants simples
        if (text.includes("-") || text.includes("HT") || text.length > 20) {
          console.log("DEBUG v2.9.3: Animation ignorée pour:", text);
          return; // Ignorer ce type de texte
        }

        var amount = parseFloat(
          text.replace(/[^0-9.,]/g, "").replace(",", ".")
        );

        // CORRECTION : Vérifier que le montant est dans une plage normale
        if (!isNaN(amount) && amount > 0 && amount < 10000) {
          $this.prop("Counter", 0).animate(
            {
              Counter: amount,
            },
            {
              duration: 1500,
              easing: "swing",
              step: function (now) {
                $this.text(Math.ceil(now).toLocaleString("fr-FR") + "€");
              },
            }
          );
        } else {
          console.log(
            "DEBUG v2.9.3: Montant aberrant ignoré:",
            amount,
            "pour le texte:",
            text
          );
        }
      }
    });
  }

  /**
   * Gestion des statuts de remise interactifs
   */
  function initRemiseStatusInteractions() {
    // Tooltip au survol des statuts
    $(".remise-status").hover(
      function () {
        var status = $(this).text();
        var tooltip = getStatusTooltip(status);

        if (tooltip) {
          var $tooltip = $('<div class="status-tooltip">' + tooltip + "</div>");
          $tooltip.css({
            position: "absolute",
            background: "#333",
            color: "white",
            padding: "8px 12px",
            "border-radius": "4px",
            "font-size": "12px",
            "z-index": "1000",
            "white-space": "nowrap",
          });

          $(this).append($tooltip);
        }
      },
      function () {
        $(this).find(".status-tooltip").remove();
      }
    );
  }

  /**
   * Obtenir le tooltip selon le statut
   */
  function getStatusTooltip(status) {
    var tooltips = {
      ACTIVE:
        "Votre remise est appliquée et sera déduite de votre prochaine facture",
      "EN ATTENTE":
        "Votre remise est en cours d'application (délai normal: 5-10 minutes)",
      PROBLÈME: "Un problème est survenu. Notre équipe a été notifiée.",
      SUSPENDUE:
        "La remise est suspendue car votre filleul a suspendu son abonnement",
    };

    for (var key in tooltips) {
      if (status.includes(key)) {
        return tooltips[key];
      }
    }
    return null;
  }

  /**
   * Actualisation automatique des données mockées
   */
  function simulateDataUpdate() {
    // Simuler évolution des statuts pour la démo
    setInterval(function () {
      $(".remise-status.status-pending").each(function () {
        if (Math.random() > 0.8) {
          // 20% de chance
          $(this)
            .removeClass("status-pending")
            .addClass("status-active")
            .html(
              "🟢 ACTIVE - Appliquée depuis le " +
                new Date().toLocaleDateString("fr-FR")
            );

          // Mettre à jour le montant des économies
          updateSavingsSummary();

          // Notification utilisateur
          showClientNotification(
            "Votre remise a été appliquée avec succès !",
            "success"
          );
        }
      });
    }, 45000); // Toutes les 45 secondes
  }

  /**
   * Mise à jour du résumé des économies
   */
  function updateSavingsSummary() {
    var activeCount = $(".status-active").length;
    var $activeDisplay = $(".savings-value").first();

    if ($activeDisplay.length) {
      $activeDisplay.text(activeCount + " sur 4 filleuls");

      // Animation de mise à jour
      $activeDisplay
        .css("color", "#46b450")
        .animate(
          {
            fontSize: "18px",
          },
          200
        )
        .animate(
          {
            fontSize: "16px",
          },
          200
        );
    }

    // Recalculer économie mensuelle
    var monthlyAmount = activeCount * 7.5;
    var $monthlyDisplay = $(".savings-value").eq(1);
    if ($monthlyDisplay.length) {
      $monthlyDisplay.text(monthlyAmount.toFixed(2).replace(".", ",") + "€");
    }
  }

  /**
   * Affichage des notifications client
   */
  function showClientNotification(message, type) {
    var bgColor = type === "success" ? "#46b450" : "#dc3232";
    var $notification = $(
      '<div class="client-notification">' + message + "</div>"
    );

    $notification.css({
      position: "fixed",
      top: "20px",
      right: "20px",
      background: bgColor,
      color: "white",
      padding: "12px 20px",
      "border-radius": "6px",
      "box-shadow": "0 4px 12px rgba(0,0,0,0.15)",
      "z-index": "9999",
      opacity: "0",
      "max-width": "300px",
    });

    $("body").append($notification);

    $notification.animate({ opacity: 1 }, 400);

    setTimeout(function () {
      $notification.animate({ opacity: 0 }, 400, function () {
        $(this).remove();
      });
    }, 4000);
  }

  /**
   * Gestion responsive des cartes économies
   */
  function handleResponsiveCards() {
    function adjustCards() {
      if ($(window).width() < 768) {
        $(".savings-grid").addClass("mobile-layout");
        $(".savings-card").css("margin-bottom", "10px");
      } else {
        $(".savings-grid").removeClass("mobile-layout");
        $(".savings-card").css("margin-bottom", "0");
      }
    }

    adjustCards();
    $(window).resize(adjustCards);
  }

  /**
   * Animation d'apparition des éléments
   */
  function animateElementsEntrance() {
    // Animation des cartes d'économies
    $(".savings-card").each(function (index) {
      $(this)
        .css({
          opacity: "0",
          transform: "translateY(20px)",
        })
        .delay(index * 100)
        .animate(
          {
            opacity: "1",
          },
          {
            duration: 600,
            step: function (now) {
              $(this).css("transform", "translateY(" + 20 * (1 - now) + "px)");
            },
          }
        );
    });

    // Animation de la section en attente
    $(".pending-actions")
      .css({
        opacity: "0",
        transform: "scale(0.9)",
      })
      .delay(800)
      .animate(
        {
          opacity: "1",
        },
        {
          duration: 400,
          step: function (now) {
            $(this).css("transform", "scale(" + (0.9 + 0.1 * now) + ")");
          },
        }
      );
  }

  /**
   * Interactions tactiles pour mobile
   */
  function initTouchInteractions() {
    // Améliorer les interactions tactiles sur les éléments interactifs
    $(".remise-status, .savings-card")
      .on("touchstart", function () {
        $(this).addClass("touch-feedback");
      })
      .on("touchend touchcancel", function () {
        var $this = $(this);
        setTimeout(function () {
          $this.removeClass("touch-feedback");
        }, 150);
      });
  }

  /**
   * Amélioration de l'accessibilité
   */
  function enhanceAccessibility() {
    // Ajouter des labels aria pour les lecteurs d'écran
    $(".savings-value").attr("aria-label", function () {
      var label = $(this).siblings(".savings-label").text();
      var value = $(this).text();
      return label + " " + value;
    });

    // Rendre les statuts focusables au clavier
    $(".remise-status")
      .attr("tabindex", "0")
      .on("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          $(this).trigger("mouseenter");
          setTimeout(() => {
            $(this).trigger("mouseleave");
          }, 3000);
        }
      });
  }

  /**
   * Détection de changement de statut en temps réel (simulation)
   */
  function detectStatusChanges() {
    var initialStatuses = {};

    // Enregistrer les statuts initiaux
    $(".remise-status").each(function (index) {
      initialStatuses[index] = $(this).hasClass("status-active");
    });

    // Vérifier périodiquement les changements
    setInterval(function () {
      $(".remise-status").each(function (index) {
        var isActive = $(this).hasClass("status-active");
        var wasActive = initialStatuses[index];

        if (isActive && !wasActive) {
          // Nouvelle remise activée
          $(this).effect("highlight", { color: "#d4edda" }, 2000);
          initialStatuses[index] = true;
        }
      });
    }, 5000);
  }

  /**
   * Ajout d'effets visuels supplémentaires
   */
  function addVisualEffects() {
    // Effet de brillance sur les montants positifs
    $(".savings-value").each(function () {
      if ($(this).text().includes("€") && !$(this).text().includes("0,00")) {
        $(this).addClass("positive-amount");
      }
    });

    // Effet de pulsation douce sur les éléments en attente
    $(".status-pending").addClass("pulse-animation");
  }

  // Initialisation avec délai pour laisser le temps au DOM
  setTimeout(function () {
    if ($(".savings-summary-section").length > 0) {
      console.log("DEBUG v2.9.3: animateSavings() DÉSACTIVÉE définitivement");
      // animateSavings(); // DÉSACTIVÉ v2.9.3 - causait des valeurs aberrantes
      handleResponsiveCards();
      animateElementsEntrance();
      addVisualEffects();
    }

    if ($(".parrainages-table").length > 0) {
      initRemiseStatusInteractions();
      simulateDataUpdate();
      detectStatusChanges();
    }

    initTouchInteractions();
    enhanceAccessibility();

    console.log("Interface client remises initialisée (mode mock v2.4.0)");
  }, 500);

  /**
   * Export des fonctions pour utilisation externe
   */
  window.tbParrainageClient = {
    showNotification: showClientNotification,
    updateSummary: updateSavingsSummary,
    refreshAnimations: animateSavings,
  };
});
