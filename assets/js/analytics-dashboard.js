/**
 * JavaScript pour le Dashboard Analytics TB-Web Parrainage
 * Version: 2.12.0
 * Architecture modulaire KISS
 */

(function ($) {
  "use strict";

  /**
   * Module Dashboard Analytics
   * Responsabilité unique : Gestion interface dashboard
   */
  const tbAnalyticsDashboard = {
    // Propriétés
    charts: {},
    currentPeriod: 90,
    isLoading: false,

    /**
     * Initialisation du dashboard
     */
    init: function () {
      console.log("🚀 TB Analytics Dashboard v2.12.0 - Initialisation");

      this.bindEvents();
      this.loadInitialData();
      this.initCharts();
    },

    /**
     * Liaison des événements
     */
    bindEvents: function () {
      // Changement de période
      $("#tb-chart-period").on("change", (e) => {
        this.currentPeriod = parseInt(e.target.value);
        this.refreshCharts();
      });

      // Actualisation graphiques
      $("#tb-refresh-charts").on("click", () => {
        this.refreshCharts();
      });

      // Comparaison périodes
      $("#tb-compare-periods").on("click", () => {
        this.comparePeriods();
      });

      // Génération rapports
      $(".tb-generate-report").on("click", (e) => {
        const $btn = $(e.target);
        const reportType = $btn.data("report-type");
        const format = $btn.data("format");
        this.generateReport(reportType, format);
      });

      // Export données
      $(".tb-export-data").on("click", () => {
        const exportType = $("#tb-export-type").val();
        this.exportData(exportType);
      });

      // Actualisation activité
      $("#tb-refresh-activity").on("click", () => {
        this.refreshActivity();
      });

      console.log("✅ Événements dashboard liés");
    },

    /**
     * Chargement données initiales
     */
    loadInitialData: function () {
      this.showLoading();

      $.ajax({
        url: tbAnalytics.ajaxUrl,
        type: "POST",
        data: {
          action: "tb_analytics_get_dashboard_data",
          nonce: tbAnalytics.nonce,
          period: this.currentPeriod,
        },
        success: (response) => {
          if (response.success) {
            this.updateDashboard(response.data);
            console.log("✅ Données dashboard chargées", response.data);
          } else {
            this.showError("Erreur lors du chargement des données");
          }
        },
        error: () => {
          this.showError("Erreur de connexion");
        },
        complete: () => {
          this.hideLoading();
        },
      });
    },

    /**
     * Initialisation des graphiques Chart.js
     */
    initCharts: function () {
      // Configuration commune avec hauteur contrôlée
      const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        aspectRatio: 2, // CORRECTION: Ratio largeur/hauteur contrôlé
        plugins: {
          legend: {
            position: "bottom",
          },
        },
        scales: {
          y: {
            beginAtZero: true,
          },
        },
        layout: {
          padding: {
            top: 10,
            bottom: 10,
          },
        },
      };

      // Graphique revenus
      const revenueCtx = document.getElementById("tb-revenue-chart");
      if (revenueCtx) {
        this.charts.revenue = new Chart(revenueCtx, {
          type: "line",
          data: {
            labels: [],
            datasets: [
              {
                label: "Revenus €",
                data: [],
                borderColor: "#27ae60",
                backgroundColor: "rgba(39, 174, 96, 0.1)",
                fill: true,
              },
            ],
          },
          options: commonOptions,
        });
      }

      // Graphique filleuls
      const filleulesCtx = document.getElementById("tb-filleuls-chart");
      if (filleulesCtx) {
        this.charts.filleuls = new Chart(filleulesCtx, {
          type: "bar",
          data: {
            labels: [],
            datasets: [
              {
                label: "Nouveaux Filleuls",
                data: [],
                backgroundColor: "#9b59b6",
              },
            ],
          },
          options: commonOptions,
        });
      }

      // Graphique ROI
      const roiCtx = document.getElementById("tb-roi-chart");
      if (roiCtx) {
        this.charts.roi = new Chart(roiCtx, {
          type: "line",
          data: {
            labels: [],
            datasets: [
              {
                label: "ROI %",
                data: [],
                borderColor: "#e74c3c",
                backgroundColor: "rgba(231, 76, 60, 0.1)",
                fill: true,
              },
            ],
          },
          options: {
            ...commonOptions,
            aspectRatio: 2, // CORRECTION: Maintenir le ratio
            scales: {
              y: {
                beginAtZero: false,
              },
            },
          },
        });
      }

      // Graphique top performers
      const performersCtx = document.getElementById("tb-performers-chart");
      if (performersCtx) {
        this.charts.performers = new Chart(performersCtx, {
          type: "doughnut",
          data: {
            labels: [],
            datasets: [
              {
                data: [],
                backgroundColor: [
                  "#3498db",
                  "#27ae60",
                  "#e74c3c",
                  "#f39c12",
                  "#9b59b6",
                  "#1abc9c",
                ],
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            aspectRatio: 1.5, // CORRECTION: Ratio pour graphique circulaire
            plugins: {
              legend: {
                position: "right",
              },
            },
            layout: {
              padding: {
                top: 10,
                bottom: 10,
              },
            },
          },
        });
      }

      console.log("📊 Graphiques Chart.js initialisés");
    },

    /**
     * Mise à jour dashboard avec nouvelles données
     */
    updateDashboard: function (data) {
      // Mettre à jour graphiques
      if (data.revenue_evolution) {
        this.updateRevenueChart(data.revenue_evolution);
      }

      if (data.top_performers) {
        this.updatePerformersChart(data.top_performers);
      }

      // Activité récente
      if (data.recent_activity) {
        this.updateRecentActivity(data.recent_activity);
      }

      // Masquer les indicateurs de chargement
      $(".tb-chart-loading").hide();
    },

    /**
     * Mise à jour graphique revenus
     */
    updateRevenueChart: function (evolutionData) {
      if (!this.charts.revenue || !evolutionData.length) return;

      const labels = evolutionData.map((item) => item.month);
      const revenueData = evolutionData.map(
        (item) => item.new_filleuls * 56.99
      ); // Approximation

      this.charts.revenue.data.labels = labels;
      this.charts.revenue.data.datasets[0].data = revenueData;
      this.charts.revenue.update();

      // Mise à jour graphique filleuls
      if (this.charts.filleuls) {
        const filleulesData = evolutionData.map((item) => item.new_filleuls);
        this.charts.filleuls.data.labels = labels;
        this.charts.filleuls.data.datasets[0].data = filleulesData;
        this.charts.filleuls.update();
      }

      // Calcul ROI approximatif
      if (this.charts.roi) {
        const roiData = revenueData.map((revenue) => {
          const discounts = revenue * 0.15; // Approximation 15% remises
          return discounts > 0 ? ((revenue - discounts) / discounts) * 100 : 0;
        });
        this.charts.roi.data.labels = labels;
        this.charts.roi.data.datasets[0].data = roiData;
        this.charts.roi.update();
      }
    },

    /**
     * Mise à jour graphique top performers
     */
    updatePerformersChart: function (performersData) {
      if (!this.charts.performers || !performersData.length) return;

      const topPerformers = performersData.slice(0, 6); // Top 6

      // CORRECTION: Labels plus informatifs avec email et revenus
      const labels = topPerformers.map((p) => {
        const email =
          p.parrain_email && p.parrain_email !== "N/A"
            ? p.parrain_email.split("@")[0] // Première partie de l'email
            : `Parrain ${p.parrain_id}`;
        return `${email} (${p.nb_filleuls} filleul${
          p.nb_filleuls > 1 ? "s" : ""
        })`;
      });

      const data = topPerformers.map((p) => p.revenue_generated);

      this.charts.performers.data.labels = labels;
      this.charts.performers.data.datasets[0].data = data;
      this.charts.performers.update();
    },

    /**
     * Mise à jour activité récente
     */
    updateRecentActivity: function (activityData) {
      const $container = $("#tb-recent-activity");
      $container.empty();

      if (!activityData.length) {
        $container.html(
          '<div class="tb-activity-loading">Aucune activité récente</div>'
        );
        return;
      }

      activityData.forEach((activity) => {
        const levelClass = `tb-activity-level-${activity.level.toLowerCase()}`;
        const $item = $(`
                    <div class="tb-activity-item">
                        <div class="tb-activity-time">${this.formatDateTime(
                          activity.datetime
                        )}</div>
                        <div class="tb-activity-source">${activity.source}</div>
                        <div class="tb-activity-message ${levelClass}">
                            ${this.truncateMessage(activity.message, 80)}
                        </div>
                    </div>
                `);
        $container.append($item);
      });
    },

    /**
     * Actualisation des graphiques
     */
    refreshCharts: function () {
      console.log(
        `🔄 Actualisation graphiques - Période: ${this.currentPeriod} jours`
      );

      // Afficher indicateurs chargement
      $(".tb-chart-loading").show();

      this.loadInitialData();
    },

    /**
     * Actualisation activité récente
     */
    refreshActivity: function () {
      const $container = $("#tb-recent-activity");
      $container.html(
        '<div class="tb-activity-loading">Actualisation...</div>'
      );

      // Simuler rechargement (dans vrai AJAX)
      setTimeout(() => {
        this.loadInitialData();
      }, 500);
    },

    /**
     * Comparaison de périodes
     */
    comparePeriods: function () {
      const period1Start = $("#tb-period1-start").val();
      const period1End = $("#tb-period1-end").val();
      const period2Start = $("#tb-period2-start").val();
      const period2End = $("#tb-period2-end").val();

      if (!period1Start || !period1End || !period2Start || !period2End) {
        alert("Veuillez sélectionner toutes les dates");
        return;
      }

      const $results = $("#tb-comparison-results");
      $results
        .html('<div class="tb-activity-loading">Comparaison en cours...</div>')
        .show();

      // Simulation comparaison
      setTimeout(() => {
        $results.html(`
                    <h3>Résultats de Comparaison</h3>
                    <div class="tb-stats-grid">
                        <div class="tb-stat-card">
                            <div class="tb-stat-icon">📈</div>
                            <div class="tb-stat-content">
                                <div class="tb-stat-value positive">+15.2%</div>
                                <div class="tb-stat-label">Évolution Revenus</div>
                            </div>
                        </div>
                        <div class="tb-stat-card">
                            <div class="tb-stat-icon">👥</div>
                            <div class="tb-stat-content">
                                <div class="tb-stat-value positive">+8.5%</div>
                                <div class="tb-stat-label">Nouveaux Filleuls</div>
                            </div>
                        </div>
                    </div>
                `);
      }, 1000);
    },

    /**
     * Génération de rapport
     */
    generateReport: function (reportType, format) {
      console.log(`📄 Génération rapport: ${reportType} (${format})`);

      const $status = $("#tb-report-status");
      $status
        .removeClass("success error")
        .addClass("loading")
        .text("Génération du rapport en cours...")
        .show();

      $.ajax({
        url: tbAnalytics.ajaxUrl,
        type: "POST",
        data: {
          action: "tb_analytics_generate_report",
          nonce: tbAnalytics.nonce,
          report_type: reportType,
          format: format,
          start_date: this.getStartDate(),
          end_date: this.getEndDate(),
        },
        success: (response) => {
          if (response.success) {
            $status
              .removeClass("loading")
              .addClass("success")
              .html(
                `✅ Rapport généré: <a href="${response.data.download_url}" target="_blank">${response.data.filename}</a>`
              );
          } else {
            $status
              .removeClass("loading")
              .addClass("error")
              .text("❌ " + response.data);
          }
        },
        error: () => {
          $status
            .removeClass("loading")
            .addClass("error")
            .text("❌ Erreur lors de la génération");
        },
      });
    },

    /**
     * Export de données
     */
    exportData: function (exportType) {
      console.log(`💾 Export données: ${exportType}`);

      const $status = $("#tb-report-status");
      $status
        .removeClass("success error")
        .addClass("loading")
        .text("Export des données en cours...")
        .show();

      $.ajax({
        url: tbAnalytics.ajaxUrl,
        type: "POST",
        data: {
          action: "tb_analytics_export_data",
          nonce: tbAnalytics.nonce,
          export_type: exportType,
          format: "excel",
        },
        success: (response) => {
          if (response.success) {
            $status
              .removeClass("loading")
              .addClass("success")
              .html(
                `✅ Export terminé: <a href="${response.data.download_url}" target="_blank">${response.data.filename}</a>`
              );
          } else {
            $status
              .removeClass("loading")
              .addClass("error")
              .text("❌ " + response.data);
          }
        },
        error: () => {
          $status
            .removeClass("loading")
            .addClass("error")
            .text("❌ Erreur lors de l'export");
        },
      });
    },

    /**
     * Utilitaires
     */
    getStartDate: function () {
      const date = new Date();
      date.setDate(date.getDate() - this.currentPeriod);
      return date.toISOString().split("T")[0];
    },

    getEndDate: function () {
      return new Date().toISOString().split("T")[0];
    },

    formatDateTime: function (datetime) {
      const date = new Date(datetime);
      return date.toLocaleString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },

    truncateMessage: function (message, length) {
      return message.length > length
        ? message.substring(0, length) + "..."
        : message;
    },

    showLoading: function () {
      this.isLoading = true;
      $(".tb-analytics-section").addClass("loading");
    },

    hideLoading: function () {
      this.isLoading = false;
      $(".tb-analytics-section").removeClass("loading");
    },

    showError: function (message) {
      console.error("❌ Erreur Dashboard:", message);

      const $error = $(`
                <div class="notice notice-error">
                    <p>⚠️ ${message}</p>
                </div>
            `);

      $(".tb-analytics-dashboard").prepend($error);

      setTimeout(() => {
        $error.fadeOut(() => $error.remove());
      }, 5000);
    },
  };

  /**
   * Exposition globale pour accès externe
   */
  window.tbAnalyticsDashboard = tbAnalyticsDashboard;

  /**
   * Auto-initialisation si DOM prêt
   */
  $(document).ready(function () {
    // Vérifier si on est sur la page analytics
    if ($(".tb-analytics-dashboard").length > 0) {
      tbAnalyticsDashboard.init();
    }
  });

  console.log("📊 TB Analytics Dashboard Script chargé v2.12.0");
})(jQuery);
