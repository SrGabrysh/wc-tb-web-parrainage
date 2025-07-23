# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versioning Sémantique](https://semver.org/lang/fr/).

## [2.0.0] - 2025-07-25

### 🎉 Ajouté - Système de Réduction Automatique du Parrain

#### ✨ Nouvelles Fonctionnalités Majeures

- **Système de réduction automatique** : Réduit automatiquement le prix d'abonnement du parrain de 25% du prix du filleul
- **Application différée intelligente** : La réduction s'applique au prochain prélèvement du parrain (respecte le cycle de facturation)
- **Formule métier simple** : `Nouveau prix HT = MAX(0, Prix HT actuel - (Prix HT filleul × 25%))`
- **Gestion d'annulation** : Suppression automatique de la réduction si l'abonnement filleul est annulé/expiré

#### 🏗️ Architecture SOLID Nouvelle

- **`ParrainPricingManager`** : Orchestrateur principal respectant les principes SOLID
- **`ParrainPricingCalculator`** : Calculs de réduction avec logique KISS
- **`ParrainPricingScheduler`** : Planification via hooks WooCommerce Subscriptions natifs
- **`ParrainPricingStorage`** : Persistance avec tables dédiées (SSOT)
- **`ParrainPricingEmailNotifier`** : Notifications email aux parrains
- **`ParrainPricingConstants`** : Centralisation de toutes les constantes métier

#### 🗄️ Nouvelles Tables Base de Données

- **`wp_tb_parrainage_pricing_schedule`** : SSOT pour les modifications programmées
- **`wp_tb_parrainage_pricing_history`** : Audit trail immuable pour traçabilité complète
- **Migration automatique** avec `dbDelta` et gestion des versions

#### ⚙️ Nouvelles Constantes v2.0.0

```php
// Règles métier
WC_TB_PARRAINAGE_REDUCTION_PERCENTAGE = 25        // 25% du prix filleul
WC_TB_PARRAINAGE_MIN_PARRAIN_PRICE = 0.00        // Prix minimum (gratuit possible)
WC_TB_PARRAINAGE_CALCULATION_PRECISION = 2        // Décimales monétaires

// Résilience
WC_TB_PARRAINAGE_RETRY_MAX_ATTEMPTS = 3          // Tentatives max en cas d'échec
WC_TB_PARRAINAGE_RETRY_DELAY_SECONDS             // Délais entre tentatives (1min, 5min, 15min)

// Performance
WC_TB_PARRAINAGE_PRICING_CACHE_DURATION = 300   // 5 minutes de cache
WC_TB_PARRAINAGE_PRICING_DEBUG = false          // Mode debug pricing
```

#### 🎛️ Nouvelle Interface d'Administration

- **Onglet "Réductions Auto"** : Interface dédiée au système de réduction automatique
- **Statistiques en temps réel** : Total programmées, en attente, appliquées, taux de succès, économies totales
- **Gestion des modifications programmées** : Visualisation et statuts des réductions en attente
- **Historique détaillé** : Audit trail complet des modifications appliquées
- **Contrôles administrateur** : Activation/désactivation, mode debug, notifications

#### ⚡ Nouveaux Paramètres

- **"Activer la réduction automatique du parrain"** : Toggle principal (désactivé par défaut)
- **"Notifications email réductions"** : Envoi d'emails aux parrains lors d'application
- **"Mode debug réductions"** : Logs détaillés pour débogage

#### 🔔 Système de Notifications Email

- **Email réduction appliquée** : Template HTML professionnel avec détails économies
- **Email réduction supprimée** : Information lors d'annulation filleul
- **Notifications administrateur** : Alertes en cas d'erreurs critiques
- **Templates responsive** : Design adaptatif compatible tous clients email

#### 🔄 Gestion Intelligente des Hooks

- **`woocommerce_order_status_completed`** : Déclenchement programmation (priorité 20)
- **`woocommerce_scheduled_subscription_payment`** : Application lors prélèvement (priorité 5)
- **`woocommerce_subscription_payment_complete`** : Fallback paiements manuels (priorité 5)
- **`woocommerce_subscription_status_cancelled`** : Annulation réductions (priorité 10)
- **`woocommerce_subscription_status_expired`** : Nettoyage expirations (priorité 10)

#### 🛡️ Système de Résilience Avancé

- **Retry automatique** : 3 tentatives avec backoff exponentiel (1min, 5min, 15min)
- **Validation cohérence** : Vérification prix unchanged avant application
- **Gestion d'échecs** : Historique des erreurs et retry intelligent
- **Rollback sécurisé** : Annulation propre en cas de problème

#### 📊 Métriques et Monitoring

- **Taux de succès** : Suivi performance système (objectif 99%+)
- **Statistiques business** : Économies générées, nombre de réductions
- **Alertes proactives** : Notifications admin si problèmes détectés
- **Audit complet** : Traçabilité de toutes les opérations

### 🔧 Amélioré

#### Plugin Principal

- **Version incrémentée** : `1.3.0` → `2.0.0`
- **Base de données versionnée** : Gestion migrations avec `WC_TB_PARRAINAGE_DB_VERSION`
- **Prérequis renforcés** : WooCommerce Subscriptions obligatoire pour nouvelles fonctionnalités
- **Nettoyage crons** : Suppression automatique crons pricing à la désactivation

#### Architecture

- **Respect principes SOLID** : SRP, OCP, LSP, DIP appliqués rigoureusement
- **Composition privilégiée** : Injection dépendances vs héritage
- **Séparation responsabilités** : Chaque classe = une responsabilité unique
- **Extensibilité** : Interfaces pour ajout facile nouvelles fonctionnalités

#### Sécurité

- **Validation stricte** : Contrôles entrées utilisateur renforcés
- **Prévention auto-parrainage** : Vérification customer_id parrain ≠ filleul
- **Constraints base données** : Index unique pour cohérence SSOT
- **Logs sécurisés** : Pas d'exposition données sensibles

### 🐛 Corrigé

- **Migration sécurisée** : Gestion propre rollback en cas d'échec
- **Compatibilité themes** : CSS non-intrusif pour interface admin
- **Performance optimisée** : Requêtes indexées et cache intelligent
- **Memory leaks** : Gestion propre des objets et ressources

### 🚨 Breaking Changes

- **Prérequis WCS** : WooCommerce Subscriptions devient obligatoire pour activation
- **Nouvelle structure DB** : 2 nouvelles tables créées lors de l'activation
- **Nouvelles constantes** : 7 nouvelles constantes ajoutées au namespace global
- **Interface admin** : Nouvel onglet "Réductions Auto" dans les paramètres

### 📋 Notes de Migration

#### Prérequis Système

```
WordPress: 6.0+
PHP: 8.1+
WooCommerce: 3.0+
WooCommerce Subscriptions: requis
```

#### Migration Automatique

- **Tables créées automatiquement** lors de l'activation
- **Pas de perte de données** : Migration non-destructive
- **Rollback disponible** : Méthode de nettoyage en cas de problème
- **Version tracking** : Suivi version DB pour futures migrations

#### Post-Migration

1. **Vérifier activation** : Aller dans Paramètres > TB-Web Parrainage
2. **Activer système** : Cocher "Activer la réduction automatique du parrain"
3. **Configurer notifications** : Ajuster paramètres email selon besoins
4. **Tester fonctionnement** : Effectuer commande test avec code parrain
5. **Surveiller logs** : Vérifier bon fonctionnement via onglet Logs

### 🔍 Tests Recommandés

#### Tests Fonctionnels

- [ ] Commande avec code parrain → Réduction programmée
- [ ] Prélèvement abonnement → Réduction appliquée
- [ ] Annulation filleul → Réduction annulée
- [ ] Email notifications → Envoi réussi
- [ ] Interface admin → Statistiques correctes

#### Tests Techniques

- [ ] Migration DB → Tables créées
- [ ] Hooks WCS → Déclenchement correct
- [ ] Retry système → Gestion échecs
- [ ] Performance → Temps réponse < 5s
- [ ] Sécurité → Validation entrées

### 📈 Métriques de Qualité v2.0.0

- **Couverture tests** : Objectif 90%+ (tests unitaires + intégration)
- **Performance** : < 100ms calcul, < 5s application, < 30s notification
- **Fiabilité** : 99%+ taux succès, retry automatique, monitoring proactif
- **Maintenabilité** : Principes SOLID, architecture modulaire, documentation complète
- **Sécurité** : Validation stricte, prévention injections, audit trail complet

---

## [1.3.0] - 2025-07-25

### Ajouté

- Onglet "Mes parrainages" côté client dans Mon Compte WooCommerce
- Contrôle d'accès strict pour les abonnés WooCommerce Subscriptions actifs
- Interface utilisateur dédiée avec tableau des filleuls et emails masqués
- Message d'invitation personnalisé avec code parrain et lien de parrainage
- CSS responsive avec compatibilité thèmes WooCommerce
- Badges de statut colorés pour les abonnements des filleuls
- Système de cache pour optimiser les performances côté client
- 6 nouvelles constantes pour l'onglet client (éviter magic numbers)

### Amélioré

- Fonction d'activation mise à jour avec endpoint "mes-parrainages"
- Architecture SOLID avec séparation admin/client
- Documentation complète de la nouvelle fonctionnalité
- Respect de l'ordre critique d'activation des endpoints

## [1.2.0] - 2025-07-25

### Ajouté

- Onglet "Parrainage" complet dans l'interface d'administration
- Interface de consultation des données groupées par parrain
- Système de filtres avancé (date, parrain, produit, statut)
- Export des données avec feuille de statistiques (Excel)
- Édition inline des avantages de parrainage
- Pagination optimisée pour gros volumes
- Interface responsive adaptée mobile/tablette
- Liens directs vers profils, commandes et abonnements

### Amélioré

- Architecture SOLID avec séparation des responsabilités
- Cache des requêtes pour meilleures performances
- Constantes pour éviter les "magic numbers"
- Sécurité renforcée avec validation complète des entrées

## [1.1.1] - 2024-07-25

### Ajouté

- Calcul automatique des dates de fin de remise parrainage
- Intégration des données de tarification aux webhooks via la clé `parrainage_pricing`

### Amélioré

- Logs enrichis pour le suivi des calculs de tarification
- Stockage des métadonnées dans les commandes et abonnements
- Documentation mise à jour avec exemples de webhooks

## [1.2.0] - 2024-07-22

### Ajouté

- Masquage conditionnel des codes promo
- Option d'activation du masquage des codes promo dans les paramètres

### Amélioré

- Champ code parrain conditionnel (uniquement pour les produits configurés)
- Logs enrichis pour le suivi des actions de masquage

## [1.0.0] - 2024-01-XX

### Ajouté

- Version initiale
- Système de code parrain complet avec validation AJAX
- Webhooks enrichis avec métadonnées d'abonnement
- Interface d'administration avec logs et statistiques
- Support WooCommerce Subscriptions

---

## Format du Changelog

### Types de Changements

- **Ajouté** : pour les nouvelles fonctionnalités
- **Amélioré** : pour les changements dans les fonctionnalités existantes
- **Déprécié** : pour les fonctionnalités bientôt supprimées
- **Supprimé** : pour les fonctionnalités supprimées
- **Corrigé** : pour les corrections de bugs
- **Sécurité** : en cas de vulnérabilités

### Liens

- [2.0.0]: https://github.com/tb-web/wc-tb-web-parrainage/releases/tag/v2.0.0
- [1.3.0]: https://github.com/tb-web/wc-tb-web-parrainage/releases/tag/v1.3.0
- [1.2.0]: https://github.com/tb-web/wc-tb-web-parrainage/releases/tag/v1.2.0
