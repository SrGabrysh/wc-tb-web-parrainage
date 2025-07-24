# WC TB-Web Parrainage

**Version:** 2.0.1  
**Auteur:** TB-Web  
**Compatible:** WordPress 6.0+, PHP 8.1+, WooCommerce 3.0+, WooCommerce Subscriptions (requis v2.0.0+)

## Description

Plugin de parrainage WooCommerce avec webhooks enrichis et **système de réduction automatique du parrain**. Ce plugin combine six fonctionnalités principales :

1. **Système de code parrain au checkout** - Permet aux clients de saisir un code parrain lors de la commande avec validation en temps réel
2. **Calcul automatique des dates de fin de remise** - Calcule et stocke automatiquement les dates de fin de période de remise parrainage (12 mois + marge de sécurité)
3. **Masquage conditionnel des codes promo** - Masque automatiquement les champs de codes promo pour les produits configurés
4. **Webhooks enrichis** - Ajoute automatiquement les métadonnées d'abonnement et de tarification parrainage dans les webhooks
5. **Onglet "Mes parrainages" côté client** - Interface utilisateur dédiée dans Mon Compte pour consulter ses parrainages
6. **🎉 Système de réduction automatique du parrain (v2.0.0)** - Réduit automatiquement le prix d'abonnement du parrain de 25% du prix du filleul au prochain prélèvement

## Fonctionnalités

### ✨ Système de Parrainage

- Champ "Code parrain" au checkout WooCommerce (conditionnel selon produits configurés)
- Validation en temps réel via AJAX (format et existence en BDD)
- Messages dynamiques selon les produits du panier
- Prévention de l'auto-parrainage
- Stockage complet des informations dans les commandes
- Affichage enrichi dans l'administration des commandes

### 📅 Calcul Automatique des Dates de Fin de Remise

- Calcul automatique de la date de fin de période de remise parrainage (12 mois + 2 jours de marge)
- Stockage des dates dans les métadonnées des commandes et abonnements
- Intégration aux webhooks avec la clé `parrainage_pricing`
- Logs de traçabilité pour toutes les opérations de calcul

### 🚫 Masquage Conditionnel des Codes Promo

- Masquage automatique des champs codes promo au panier et checkout
- Activation selon les produits configurés dans l'interface d'administration
- Désactivation complète des fonctionnalités de coupons pour les produits concernés
- Logs des actions de masquage pour le suivi

### 🔗 Webhooks Enrichis

- Ajout automatique des métadonnées d'abonnement dans les webhooks
- **Nouvelles données de tarification parrainage** via la clé `parrainage_pricing`
- Informations complètes : ID, statut, dates, articles, facturation
- Support WooCommerce Subscriptions
- Logs détaillés de tous les traitements

### 🎛️ Interface d'Administration

- **Nouvel onglet "Parrainage"** - Interface complète de consultation des données de parrainage
- Consultation en temps réel des logs (avec filtres et recherche)
- Statistiques de parrainage
- Paramètres configurables
- Configuration des produits par interface graphique
- Nettoyage automatique des anciens logs

### 📊 Interface de Parrainage (Admin)

- **Tableau groupé par parrain** - Visualisation claire des parrains et leurs filleuls
- **Système de filtres avancé** - Filtrage par date, parrain, produit, statut d'abonnement
- **Export CSV et Excel** - Export complet des données avec statistiques
- **Édition inline** - Modification des avantages directement dans le tableau
- **Pagination optimisée** - Gestion performante de gros volumes de données
- **Interface responsive** - Adaptée mobile et tablette
- **Liens directs** - Accès rapide aux profils utilisateurs, commandes et abonnements

### 👤 Onglet "Mes parrainages" côté client (v1.3.0)

- **Onglet dédié dans Mon Compte** - Interface utilisateur intuitive et sécurisée
- **Contrôle d'accès strict** - Visible uniquement pour les abonnés actifs WooCommerce Subscriptions
- **Tableau des filleuls** - Affichage des parrainages avec email masqué pour confidentialité
- **Message d'invitation personnalisé** - Code parrain et lien de parrainage si aucun filleul
- **Interface responsive** - Design adaptatif mobile/tablette avec masquage intelligent des colonnes
- **Badges de statut colorés** - Statuts d'abonnement visuellement distincts
- **Limite de performance** - Affichage des 10 derniers parrainages pour un chargement rapide
- **CSS natif WooCommerce** - Intégration parfaite avec tous les thèmes compatibles

### 🎉 Système de Réduction Automatique du Parrain (Nouveau v2.0.0)

- **Réduction automatique intelligente** - 25% du prix HT du filleul déduit du prix HT du parrain
- **Application différée** - La réduction s'applique au prochain prélèvement du parrain (respecte les cycles de facturation)
- **Formule métier simple** : `Nouveau prix HT = MAX(0, Prix HT actuel - (Prix HT filleul × 25%))`
- **Gestion des annulations** - Suppression automatique de la réduction si l'abonnement filleul est annulé/expiré
- **Interface d'administration dédiée** - Onglet "Réductions Auto" avec statistiques temps réel et gestion des modifications
- **Système de retry intelligent** - 3 tentatives automatiques avec backoff exponentiel en cas d'échec
- **Notifications email automatiques** - Templates HTML professionnels envoyés aux parrains
- **Audit trail complet** - Historique immuable de toutes les modifications pour traçabilité
- **Architecture SOLID** - Code maintenable respectant les principes de développement SOLID
- **Tables dédiées** - Base de données SSOT avec `pricing_schedule` et `pricing_history`

## Installation

### 1. Installation manuelle

1. Téléchargez le plugin
2. Uploadez le dossier `wc-tb-web-parrainage` dans `/wp-content/plugins/`
3. Activez le plugin via l'interface WordPress

### 2. Via l'interface WordPress

1. Allez dans **Extensions > Ajouter**
2. Uploadez le fichier ZIP du plugin
3. Activez le plugin

## Configuration

### Prérequis

- **WordPress** 6.0 ou supérieur
- **PHP** 8.1 ou supérieur
- **WooCommerce** installé et activé
- **WooCommerce Subscriptions** (requis pour le système de parrainage, l'onglet "Mes parrainages" et le système de réduction automatique v2.0.0)

### Paramètres

Rendez-vous dans **Réglages > TB-Web Parrainage** pour configurer :

- ✅ **Activer les webhooks enrichis** - Ajoute les métadonnées d'abonnement
- ✅ **Activer le système de parrainage** - Affiche le champ code parrain au checkout (conditionnel)
- ✅ **Masquer les codes promo** - Masque automatiquement les codes promo pour les produits configurés
- 🎉 **Activer la réduction automatique du parrain** - **[NOUVEAU v2.0.0]** Système de réduction automatique (désactivé par défaut)
- 📧 **Notifications email réductions** - Envoi d'emails aux parrains lors d'application de réductions
- 🐛 **Mode debug réductions** - Logs détaillés pour débogage du système de réduction automatique
- 🕐 **Rétention des logs** - Durée de conservation (1-365 jours)

### Interface de Parrainage

Accédez à l'onglet **"Parrainage"** pour :

- **Consulter les données** - Tableau groupé par parrain avec leurs filleuls
- **Filtrer les résultats** - Par période, parrain, produit ou statut d'abonnement
- **Exporter les données** - Format CSV ou Excel avec statistiques intégrées
- **Modifier les avantages** - Édition inline directement dans le tableau
- **Naviguer rapidement** - Liens directs vers les profils et commandes

### Interface de Réduction Automatique (Nouveau v2.0.0)

Accédez à l'onglet **"Réductions Auto"** pour :

- **Voir les statistiques** - Total programmées, en attente, appliquées, taux de succès, économies totales
- **Gérer les modifications programmées** - Visualisation des réductions en attente avec statuts et tentatives
- **Consulter l'historique** - Audit trail complet des modifications appliquées avec détails d'exécution
- **Surveiller la performance** - Taux de succès, retry automatiques, alertes en cas de problème
- **Accès direct aux abonnements** - Liens vers les abonnements parrain concernés

## Utilisation

### Codes Parrain

Les codes parrain correspondent aux **ID d'abonnements actifs** WooCommerce Subscriptions :

- Format : **4 chiffres** (ex: 4896)
- Validation automatique en base de données
- Affichage des informations du parrain lors de la validation

### Configuration par Produit

Le plugin utilise une interface d'administration pour configurer les produits. Les fonctionnalités suivantes s'appliquent **uniquement aux produits configurés** :

- **Champ "Code parrain"** : Visible et obligatoire seulement pour les produits configurés
- **Masquage codes promo** : Les codes promo sont masqués automatiquement
- **Messages personnalisés** : Descriptions et avantages spécifiques par produit

Par défaut configuré pour :

- **Produits 6713, 6524, 6519** : "1 mois gratuit supplémentaire"
- **Produit 6354** : "10% de remise"
- **Autres produits** : "Avantage parrainage"

### Webhooks

Les webhooks WooCommerce de type "order" sont automatiquement enrichis avec :

```json
{
  "has_subscriptions": true,
  "subscriptions_count": 1,
  "subscription_ids": [4896],
  "subscription_metadata": [
    {
      "subscription_id": 4896,
      "subscription_status": "active",
      "subscription_start_date": "2024-01-01",
      "subscription_next_payment": "2024-02-01",
      "subscription_total": "29.99",
      "subscription_currency": "EUR",
      "subscription_items": [...]
    }
  ],
  "parrainage_pricing": {
    "date_fin_remise_parrainage": "2025-07-24",
    "date_debut_parrainage": "2024-07-22",
    "date_fin_remise_parrainage_formatted": "24-07-2025",
    "date_debut_parrainage_formatted": "22-07-2024",
    "jours_marge_parrainage": 2,
    "periode_remise_mois": 12
  }
}
```

#### Clé `parrainage_pricing`

Cette nouvelle clé n'apparaît que si la commande contient un code parrain valide :

- **`date_fin_remise_parrainage`** : Date calculée de fin de période de remise au format YYYY-MM-DD
- **`date_debut_parrainage`** : Date de début de l'abonnement avec parrainage au format YYYY-MM-DD
- **`date_fin_remise_parrainage_formatted`** : Date de fin de remise au format DD-MM-YYYY
- **`date_debut_parrainage_formatted`** : Date de début au format DD-MM-YYYY
- **`jours_marge_parrainage`** : Nombre de jours de marge ajoutés (défaut : 2)
- **`periode_remise_mois`** : Durée de la période de remise en mois (12)

## Développement

### Structure du Plugin

```
wc-tb-web-parrainage/
├── wc-tb-web-parrainage.php              # Fichier principal
├── composer.json                         # Autoload PSR-4
├── CHANGELOG.md                          # Historique des versions (Nouveau v2.0.0)
├── src/
│   ├── Plugin.php                       # Classe principale
│   ├── Logger.php                       # Système de logs
│   ├── WebhookManager.php               # Gestion webhooks
│   ├── ParrainageManager.php            # Système parrainage
│   ├── CouponManager.php                # Masquage codes promo
│   ├── SubscriptionPricingManager.php   # Calcul dates tarification
│   ├── ParrainageStatsManager.php       # Interface parrainage admin
│   ├── ParrainageDataProvider.php       # Fournisseur données admin
│   ├── ParrainageExporter.php           # Export données
│   ├── ParrainageValidator.php          # Validation données
│   ├── MyAccountParrainageManager.php   # Gestionnaire onglet client (v1.3.0)
│   ├── MyAccountDataProvider.php        # Fournisseur données client (v1.3.0)
│   ├── MyAccountAccessValidator.php     # Validateur accès client (v1.3.0)
│   └── ParrainPricing/                  # Nouveau v2.0.0 - Système réduction automatique
│       ├── ParrainPricingManager.php    # Orchestrateur principal (composition SOLID)
│       ├── Constants/
│       │   └── ParrainPricingConstants.php # Constantes métier centralisées
│       ├── Calculator/
│       │   └── ParrainPricingCalculator.php # Calculs de réduction (KISS)
│       ├── Scheduler/
│       │   └── ParrainPricingScheduler.php # Planification via hooks WCS
│       ├── Storage/
│       │   └── ParrainPricingStorage.php # Persistance DB (SSOT)
│       ├── Notifier/
│       │   └── ParrainPricingEmailNotifier.php # Notifications email
│       └── Migration/
│           └── ParrainPricingMigration.php # Migration DB sécurisée
├── assets/
│   ├── admin.css                        # Styles administration
│   ├── admin.js                         # Scripts administration
│   ├── parrainage-admin.css             # Styles interface parrainage admin
│   ├── parrainage-admin.js              # Scripts interface parrainage admin
│   └── my-account-parrainage.css        # Styles onglet client (v1.3.0)
└── README.md
```

### Hooks Disponibles

```php
// Personnaliser les messages de parrainage
add_filter( 'tb_parrainage_messages_config', 'custom_parrainage_messages' );

function custom_parrainage_messages( $config ) {
    $config[123] = array(
        'description' => 'Message personnalisé...',
        'message_validation' => 'Code valide ✓ - Avantage spécial',
        'avantage' => 'Avantage spécial'
    );
    return $config;
}
```

### Classes Principales

#### `TBWeb\WCParrainage\Plugin`

Classe principale qui orchestre le plugin.

#### `TBWeb\WCParrainage\Logger`

Système de logs avec stockage en base de données.

#### `TBWeb\WCParrainage\WebhookManager`

Gestion des webhooks WooCommerce enrichis.

#### `TBWeb\WCParrainage\ParrainageManager`

Système complet de gestion des codes parrain.

#### `TBWeb\WCParrainage\SubscriptionPricingManager`

Calcul et gestion des dates de modification tarifaire pour les abonnements avec parrainage.

#### `TBWeb\WCParrainage\CouponManager`

Gestion du masquage conditionnel des codes promo.

#### `TBWeb\WCParrainage\ParrainageStatsManager` (Nouveau)

Orchestration de l'interface d'administration des données de parrainage.

#### `TBWeb\WCParrainage\ParrainageDataProvider` (Nouveau)

Récupération et traitement des données de parrainage depuis la base de données.

#### `TBWeb\WCParrainage\ParrainageExporter` (Nouveau)

Export des données de parrainage vers différents formats (CSV, Excel).

#### `TBWeb\WCParrainage\ParrainageValidator` (Nouveau)

Validation des données d'entrée et paramètres de l'interface de parrainage.

#### `TBWeb\WCParrainage\MyAccountParrainageManager` (Nouveau v1.3.0)

Gestionnaire principal de l'onglet "Mes parrainages" côté client avec endpoint WooCommerce.

#### `TBWeb\WCParrainage\MyAccountDataProvider` (Nouveau v1.3.0)

Récupération et formatage des données de parrainage pour l'affichage côté client.

#### `TBWeb\WCParrainage\MyAccountAccessValidator` (v1.3.0)

Validation de l'accès aux fonctionnalités de parrainage pour les utilisateurs connectés.

#### `TBWeb\WCParrainage\ParrainPricing\ParrainPricingManager` (Nouveau v2.0.0)

Orchestrateur principal du système de réduction automatique utilisant la composition SOLID.

#### `TBWeb\WCParrainage\ParrainPricing\Calculator\ParrainPricingCalculator` (Nouveau v2.0.0)

Calculateur de réductions appliquant la formule métier simple (principe KISS).

#### `TBWeb\WCParrainage\ParrainPricing\Scheduler\ParrainPricingScheduler` (Nouveau v2.0.0)

Planificateur utilisant les hooks WooCommerce Subscriptions natifs pour l'application différée.

#### `TBWeb\WCParrainage\ParrainPricing\Storage\ParrainPricingStorage` (Nouveau v2.0.0)

Gestionnaire de persistance avec tables dédiées servant de Single Source of Truth (SSOT).

#### `TBWeb\WCParrainage\ParrainPricing\Notifier\ParrainPricingEmailNotifier` (Nouveau v2.0.0)

Système de notifications email avec templates HTML professionnels pour les parrains.

#### `TBWeb\WCParrainage\ParrainPricing\Migration\ParrainPricingMigration` (Nouveau v2.0.0)

Gestionnaire de migrations de base de données avec rollback automatique sécurisé.

## Logs et Debugging

### Consultation des Logs

Allez dans **Réglages > TB-Web Parrainage > Onglet Logs** pour :

- Consulter tous les logs en temps réel
- Filtrer par niveau (INFO, WARNING, ERROR, DEBUG)
- Rechercher dans les messages
- Vider les logs

### Types de Logs

- **webhook-subscriptions** : Traitement des webhooks
- **parrainage** : Validation et enregistrement des codes parrain
- **maintenance** : Nettoyage et maintenance automatique

### Debug WordPress

Si `WP_DEBUG` est activé, les logs sont aussi envoyés vers le système WordPress.

## FAQ

### Comment personnaliser les messages de parrainage ?

Utilisez le filtre `tb_parrainage_messages_config` (voir section Développement).

### Les webhooks ne contiennent pas les métadonnées d'abonnement

Vérifiez que :

- WooCommerce Subscriptions est installé et actif
- L'option "Webhooks enrichis" est activée dans les paramètres
- La commande contient bien des abonnements

### Le code parrain n'est pas validé

Vérifiez que :

- Le code correspond à un ID d'abonnement actif
- WooCommerce Subscriptions est installé
- L'utilisateur n'utilise pas son propre code

### Problèmes de performance

Le plugin est optimisé pour la performance :

- Cache des validations AJAX
- Nettoyage automatique des logs anciens
- Requêtes optimisées

## Support

Pour toute question ou problème :

1. Consultez les logs dans l'interface d'administration
2. Vérifiez la configuration des prérequis
3. Contactez TB-Web pour le support

## Licence

GPL v2 or later

## Changelog

### Version 2.0.1 (2025-07-24 à 11h09) - MINEURE

- **Amélioration** : Interface "Mes parrainages" côté client avec nouveaux labels plus explicites
- **Nouveau** : Colonne "Votre remise\*" dans le tableau des parrainages pour afficher la remise du parrain
- **Amélioration** : Labels de colonnes plus clairs ("Abonnement de votre filleul", "Statut de son abonnement", etc.)
- **Amélioration** : Statuts d'abonnement améliorés ("En cours" au lieu de "Actif", "Suspendu" au lieu de "En attente")
- **Nouveau** : Section d'explications sous le tableau détaillant le fonctionnement des remises
- **Amélioration** : Intégration avec la table `tb_parrainage_pricing_schedule` pour afficher les remises réelles
- **Amélioration** : Gestion intelligente de l'affichage des remises selon le statut d'abonnement du filleul
- **Documentation** : Explications détaillées sur l'application des remises HT et conditions d'activation

### Version 2.0.0 (2025-07-25) - MAJEURE

- **🎉 Nouveau** : Système de réduction automatique du parrain (25% du prix filleul déduit du prix parrain)
- **Nouveau** : Architecture SOLID avec composition et injection de dépendances
- **Nouveau** : Tables de base de données dédiées (`pricing_schedule`, `pricing_history`)
- **Nouveau** : Interface d'administration "Réductions Auto" avec statistiques temps réel
- **Nouveau** : Système de retry intelligent avec backoff exponentiel (3 tentatives)
- **Nouveau** : Notifications email automatiques aux parrains avec templates HTML
- **Nouveau** : Audit trail complet pour traçabilité des modifications
- **Nouveau** : Migration de base de données automatique avec rollback sécurisé
- **Nouveau** : 7 nouvelles constantes métier centralisées (éviter magic numbers)
- **Nouveau** : Gestion intelligente des hooks WooCommerce Subscriptions
- **Amélioration** : Prérequis WooCommerce Subscriptions obligatoire
- **Amélioration** : Versioning de base de données avec `WC_TB_PARRAINAGE_DB_VERSION`
- **Breaking Change** : Nouvelles tables créées automatiquement à l'activation

### Version 1.3.0 (2025-07-25)

- **Nouveau** : Onglet "Mes parrainages" côté client dans Mon Compte WooCommerce
- **Nouveau** : Classe `MyAccountParrainageManager` pour la gestion de l'endpoint client
- **Nouveau** : Classe `MyAccountDataProvider` pour la récupération des données côté client
- **Nouveau** : Classe `MyAccountAccessValidator` pour la validation d'accès aux abonnements
- **Nouveau** : Interface utilisateur dédiée avec tableau des filleuls et emails masqués
- **Nouveau** : Message d'invitation personnalisé avec code parrain et lien de parrainage
- **Nouveau** : CSS `my-account-parrainage.css` responsive avec compatibilité thèmes WooCommerce
- **Nouveau** : Contrôle d'accès strict pour les abonnés WooCommerce Subscriptions actifs
- **Nouveau** : Badges de statut colorés pour les abonnements des filleuls
- **Nouveau** : Système de cache pour optimiser les performances côté client
- **Nouveau** : 6 nouvelles constantes pour l'onglet client (éviter magic numbers)
- **Amélioration** : Fonction d'activation mise à jour avec endpoint "mes-parrainages"
- **Amélioration** : Architecture SOLID avec séparation admin/client
- **Amélioration** : Documentation complète de la nouvelle fonctionnalité
- **Amélioration** : Respect de l'ordre critique d'activation des endpoints

### Version 1.2.0 (2025-07-25)

- **Nouveau** : Onglet "Parrainage" complet dans l'interface d'administration
- **Nouveau** : Classe `ParrainageStatsManager` pour l'orchestration de l'interface parrainage
- **Nouveau** : Classe `ParrainageDataProvider` pour la récupération optimisée des données
- **Nouveau** : Classe `ParrainageExporter` pour l'export CSV et Excel avec statistiques
- **Nouveau** : Classe `ParrainageValidator` pour la validation sécurisée des données
- **Nouveau** : Interface de consultation des données groupées par parrain
- **Nouveau** : Système de filtres avancé (date, parrain, produit, statut)
- **Nouveau** : Export des données avec feuille de statistiques (Excel)
- **Nouveau** : Édition inline des avantages de parrainage
- **Nouveau** : Pagination optimisée pour gros volumes
- **Nouveau** : Assets CSS/JS dédiés à l'interface parrainage
- **Nouveau** : Interface responsive adaptée mobile/tablette
- **Nouveau** : Liens directs vers profils, commandes et abonnements
- **Amélioration** : Architecture SOLID avec séparation des responsabilités
- **Amélioration** : Cache des requêtes pour meilleures performances
- **Amélioration** : Constantes pour éviter les "magic numbers"
- **Amélioration** : Sécurité renforcée avec validation complète des entrées
- **Amélioration** : Documentation technique enrichie

### Version 1.1.1 (2024-07-25)

- **Nouveau** : Calcul automatique des dates de fin de remise parrainage
- **Nouveau** : Classe `SubscriptionPricingManager` pour la gestion des dates tarifaires
- **Nouveau** : Intégration des données de tarification aux webhooks via la clé `parrainage_pricing`
- **Amélioration** : Logs enrichis pour le suivi des calculs de tarification
- **Amélioration** : Stockage des métadonnées dans les commandes et abonnements
- **Amélioration** : Documentation mise à jour avec exemples de webhooks

### Version 1.2.0 (2024-07-22)

- **Nouveau** : Masquage conditionnel des codes promo
- **Nouveau** : Option d'activation du masquage des codes promo dans les paramètres
- **Amélioration** : Champ code parrain conditionnel (uniquement pour les produits configurés)
- **Amélioration** : Logs enrichis pour le suivi des actions de masquage
- **Amélioration** : Documentation mise à jour

### Version 1.1.0 (2024-01-XX)

- Améliorations diverses et corrections de bugs

### Version 1.0.0 (2024-01-XX)

- Version initiale
- Système de code parrain complet avec validation AJAX
- Webhooks enrichis avec métadonnées d'abonnement
- Interface d'administration avec logs et statistiques
- Support WooCommerce Subscriptions
