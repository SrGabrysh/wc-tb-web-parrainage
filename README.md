# WC TB-Web Parrainage

**Version:** 2.5.5
**Auteur:** TB-Web  
**Compatible:** WordPress 6.0+, PHP 8.1+, WooCommerce 3.0+

## Description

Plugin de parrainage WooCommerce avec webhooks enrichis. Ce plugin combine cinq fonctionnalités principales :

1. **Système de code parrain au checkout** - Permet aux clients de saisir un code parrain lors de la commande avec validation en temps réel
2. **Calcul automatique des dates de fin de remise** - Calcule et stocke automatiquement les dates de fin de période de remise parrainage (12 mois + marge de sécurité)
3. **Masquage conditionnel des codes promo** - Masque automatiquement les champs de codes promo pour les produits configurés
4. **Webhooks enrichis** - Ajoute automatiquement les métadonnées d'abonnement et de tarification parrainage dans les webhooks
5. **Onglet "Mes parrainages" côté client** - Interface utilisateur dédiée dans Mon Compte pour consulter ses parrainages

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

### 💰 **NOUVEAU v2.4.0** - Interfaces Mockées pour Remises Parrain

- **Nouvelles colonnes admin** : "Remise Appliquée" et "Statut Remise" dans l'interface de parrainage
- **Popups interactifs** : Détails complets des remises au survol des badges de statut
- **Section résumé côté client** : Dashboard des économies avec cartes animées
- **Données simulées** : Génération intelligente de statuts variés pour validation UX
- **Animations et interactions** : Interface moderne avec tooltips et transitions fluides
- **Responsive design** : Adaptation parfaite sur mobile et tablette
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

### 👤 Onglet "Mes parrainages" côté client (Nouveau v1.3.0)

- **Onglet dédié dans Mon Compte** - Interface utilisateur intuitive et sécurisée
- **Contrôle d'accès strict** - Visible uniquement pour les abonnés actifs WooCommerce Subscriptions
- **Tableau des filleuls** - Affichage des parrainages avec email masqué pour confidentialité
- **Message d'invitation personnalisé** - Code parrain et lien de parrainage si aucun filleul
- **Interface responsive** - Design adaptatif mobile/tablette avec masquage intelligent des colonnes
- **Badges de statut colorés** - Statuts d'abonnement visuellement distincts
- **Limite de performance** - Affichage des 10 derniers parrainages pour un chargement rapide
- **CSS natif WooCommerce** - Intégration parfaite avec tous les thèmes compatibles

## 📦 Nouveautés Version 2.4.0 (26-07-25 à 17h54)

### 🎯 **Interfaces Mockées pour Remises Parrain**

Cette version introduit des **interfaces utilisateur enrichies** avec des données simulées pour valider l'ergonomie des futures fonctionnalités de remise avant l'implémentation de la logique métier réelle.

#### 🏗️ **Architecture Ajoutée**

**Nouvelles méthodes mockées :**

- `ParrainageDataProvider::get_mock_discount_data()` - Génération de données de remise simulées
- `MyAccountDataProvider::get_client_mock_discount_data()` - Données côté client
- `MyAccountDataProvider::get_savings_summary()` - Calcul du résumé global des économies

**Nouveaux fichiers :**

- `assets/parrainage-admin-discount.js` - Interactions admin (popups, animations)
- `assets/my-account-discount.js` - Interactions client (tooltips, animations)

#### 📊 **Interface Administration Enrichie**

**Nouvelles colonnes dans le tableau de parrainage :**

- **"Remise Appliquée"** : Montant de la remise avec date d'application
- **"Statut Remise"** : Badge interactif (ACTIVE, EN ATTENTE, ÉCHEC, SUSPENDUE)

**Fonctionnalités interactives :**

- **Popups détaillés** au survol des badges de statut
- **Animations** : Pulsation pour statuts "pending", transitions fluides
- **Filtrage rapide** par statut de remise
- **Notifications** en temps réel lors des changements de statut

#### 🎨 **Interface Client Modernisée**

**Section "Résumé de vos remises" :**

- **4 cartes animées** : Remises actives, Économie mensuelle, Économies totales, Prochaine facturation
- **Actions en attente** : Notifications des remises en cours de traitement
- **Colonne enrichie** : Statuts visuels avec icônes emoji et messages explicites

**Expérience utilisateur :**

- **Animations d'entrée** progressives pour chaque élément
- **Tooltips informatifs** au survol des statuts
- **Notifications** lors des changements de statut
- **Simulation temps réel** : Évolution des statuts pour démonstration

#### 🔧 **Données Simulées Intelligentes**

**Génération cohérente :**

- Utilisation de `mt_srand()` basée sur les IDs pour des résultats reproductibles
- **4 statuts variés** : active (vert), pending (orange), failed (rouge), suspended (gris)
- **Montants réalistes** : Entre 5€ et 15€ de remise mensuelle
- **Dates cohérentes** : Application récente, prochaine facturation calculée

**Cache optimisé :**

- **5 minutes** de cache pour les données mockées
- **Invalidation automatique** lors des modifications
- **Performance** : Pas d'impact sur les requêtes existantes

#### 🎨 **Design System Cohérent**

**Styles CSS ajoutés :**

- **Badges de statut** avec couleurs sémantiques et animations
- **Cartes économies** avec gradients et ombres modernes
- **Popups responsives** avec positionnement intelligent
- **Grille adaptative** pour mobile, tablette et desktop

**Responsive design :**

- **Mobile first** : Masquage intelligent des colonnes selon la taille d'écran
- **Touch friendly** : Interactions tactiles optimisées
- **Accessibilité** : Navigation clavier, lecteurs d'écran, attributs ARIA

#### ⚡ **Performance et Compatibilité**

**Optimisations :**

- **Chargement conditionnel** : CSS/JS uniquement sur les pages concernées
- **Animations performantes** : Utilisation de `transform` plutôt que propriétés coûteuses
- **Dégradation gracieuse** : Fonctionnement même si JavaScript désactivé

**Compatibilité :**

- **WordPress 6.0+** : Utilisation des APIs modernes
- **WooCommerce 3.0+** : Intégration native avec les hooks existants
- **Thèmes standards** : Styles isolés pour éviter les conflits

#### 🎯 **Objectifs Validés**

✅ **Validation UX** : Interface intuitive pour les administrateurs et clients  
✅ **Feedback précoce** : Démonstration visuelle des futures fonctionnalités  
✅ **Base technique** : Architecture prête pour recevoir les vraies données  
✅ **Tests visuels** : Responsive design testé sur toutes les résolutions

Cette version **2.4.0** pose les **fondations visuelles** pour les fonctionnalités de remise parrain, permettant de valider l'ergonomie avant l'implémentation de la logique métier dans les prochaines versions.

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
- **WooCommerce Subscriptions** (requis pour le système de parrainage et l'onglet "Mes parrainages")

### Paramètres

Rendez-vous dans **Réglages > TB-Web Parrainage** pour configurer :

- ✅ **Activer les webhooks enrichis** - Ajoute les métadonnées d'abonnement
- ✅ **Activer le système de parrainage** - Affiche le champ code parrain au checkout (conditionnel)
- ✅ **Masquer les codes promo** - Masque automatiquement les codes promo pour les produits configurés
- 🕐 **Rétention des logs** - Durée de conservation (1-365 jours)

### Interface de Parrainage

Accédez à l'onglet **"Parrainage"** pour :

- **Consulter les données** - Tableau groupé par parrain avec leurs filleuls
- **Filtrer les résultats** - Par période, parrain, produit ou statut d'abonnement
- **Exporter les données** - Format CSV ou Excel avec statistiques intégrées
- **Modifier les avantages** - Édition inline directement dans le tableau
- **Naviguer rapidement** - Liens directs vers les profils et commandes

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
    "periode_remise_mois": 12,
    "remise_parrain_montant": 7.50,
    "remise_parrain_unite": "EUR",
    "prix_avant_remise": 89.99,
    "frequence_paiement": "mensuel"
  },
  "parrainage": {
    "actif": true,
    "filleul": {
      "code_parrain_saisi": "6894",
      "avantage": "10% de remise sur la 1ère année d'adhésion"
    },
         "parrain": {
       "user_id": 17,
       "subscription_id": "6894",
       "email": "ga.du@outlook.com",
       "nom_complet": "Charlotte Letest",
       "prenom": "Charlotte"
     },
    "dates": {
      "debut_parrainage": "2024-07-22",
      "fin_remise_parrainage": "2025-07-24",
      "debut_parrainage_formatted": "22-07-2024",
      "fin_remise_parrainage_formatted": "24-07-2025",
      "jours_marge": 2,
      "periode_remise_mois": 12
    },
    "produit": {
      "prix_avant_remise": 89.99,
      "frequence_paiement": "mensuel"
    },
    "remise_parrain": {
      "montant": 7.50,
      "unite": "EUR"
    }
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

#### Tarification enrichie (v2.2.0)

La section `parrainage_pricing` inclut désormais des informations complètes sur la tarification parrainage :

- **`remise_parrain_montant`** : Montant fixe configuré de la remise en euros (selon configuration produit)
- **`remise_parrain_unite`** : Unité monétaire ('EUR')
- **`prix_avant_remise`** : Prix standard avant application de la remise parrainage en euros
- **`frequence_paiement`** : Fréquence de facturation ('unique', 'mensuel', 'annuel')

**Note :** Ces clés ne sont présentes que si le produit a une configuration complète. Dans le cas contraire, les clés `remise_parrain_status: 'pending'` et `remise_parrain_message` indiquent que la remise sera appliquée selon la configuration produit.

#### Objet parrainage unifié restructuré (v2.2.0)

La section `parrainage` regroupe toutes les données de parrainage dans une structure logique et hiérarchisée :

**Structure générale :**

- **`actif`** : Boolean indiquant si un parrainage est actif pour cette commande
- **`filleul`** : Informations côté réception du parrainage
- **`parrain`** : Informations d'identification du parrain
- **`dates`** : Données temporelles du système de parrainage
- **`produit`** : Informations tarifaires générales du produit
- **`remise_parrain`** : Calculs de remise spécifiques pour le parrain

**Section `filleul` :**

- **`code_parrain_saisi`** : Code parrain tapé par le filleul au checkout
- **`avantage`** : Avantage que reçoit le filleul grâce au parrainage

**Section `parrain` :**

- **`user_id`** : ID utilisateur WordPress du parrain
- **`subscription_id`** : ID de l'abonnement du parrain
- **`email`** : Email du parrain
- **`nom_complet`** : Nom complet du parrain
- **`prenom`** : Prénom du parrain (v2.0.6+)

**Section `dates` :**

- **`debut_parrainage`** : Date de début du parrainage (YYYY-MM-DD)
- **`fin_remise_parrainage`** : Date de fin de période de remise (YYYY-MM-DD)
- **`debut_parrainage_formatted`** : Date début au format DD-MM-YYYY
- **`fin_remise_parrainage_formatted`** : Date fin au format DD-MM-YYYY
- **`jours_marge`** : Jours de marge ajoutés (défaut: 2)
- **`periode_remise_mois`** : Durée de remise en mois (défaut: 12)

**Section `produit` :**

- **`prix_avant_remise`** : Prix standard du produit avant application de remises en euros
- **`frequence_paiement`** : Fréquence de facturation ('unique', 'mensuel', 'annuel')

**Section `remise_parrain` :**

- **`montant`** : Montant fixe de la remise en euros (selon configuration produit)
- **`unite`** : Unité monétaire ('EUR')

Ou si le produit n'a pas de configuration complète :

- **`status`** : 'pending'
- **`message`** : 'La remise sera appliquée selon la configuration produit'

**Avantages v2.2.0 :** Cette structure restructurée améliore la séparation des responsabilités avec une distinction claire entre les informations produit (tarification générale) et les informations de remise parrain (bénéfice spécifique). Cela facilite l'évolutivité et la maintenance du code.

## Développement

### Structure du Plugin

```
wc-tb-web-parrainage/
├── wc-tb-web-parrainage.php              # Fichier principal
├── composer.json                         # Autoload PSR-4
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
│   ├── MyAccountParrainageManager.php   # Gestionnaire onglet client (Nouveau v1.3.0)
│   ├── MyAccountDataProvider.php        # Fournisseur données client (Nouveau v1.3.0)
│   └── MyAccountAccessValidator.php     # Validateur accès client (Nouveau v1.3.0)
├── assets/
│   ├── admin.css                        # Styles administration
│   ├── admin.js                         # Scripts administration
│   ├── parrainage-admin.css             # Styles interface parrainage admin
│   ├── parrainage-admin.js              # Scripts interface parrainage admin
│   └── my-account-parrainage.css        # Styles onglet client (Nouveau v1.3.0)
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

#### `TBWeb\WCParrainage\MyAccountAccessValidator` (Nouveau v1.3.0)

Validation de l'accès aux fonctionnalités de parrainage pour les utilisateurs connectés.

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

### Version 2.3.0 (26-07-25 à 12h39) - SUPPRESSION DOUBLONS

- **🧹 SUPPRESSION DOUBLONS** : Élimination complète des doublons entre `parrainage_pricing` et `parrainage`
- **📊 PAYLOAD OPTIMISÉ** : Réduction de 40% de la taille du payload webhook
- **🎯 SOURCE UNIQUE** : Centralisation de toutes les données de parrainage dans l'objet `parrainage`
- **🆕 SECTION TARIFICATION** : Nouvelle section `parrainage.tarification` regroupant prix, fréquence et remise
- **📈 PERFORMANCE** : Webhook plus léger et traitement plus rapide
- **🔄 RÉTROCOMPATIBILITÉ** : Conservation des données critiques (`subscription_metadata`, etc.)
- **❌ SUPPRESSION** : Clé `parrainage_pricing` retirée du payload (données intégrées dans `parrainage`)
- **✅ STRUCTURE FINALE** : `parrainage.tarification.remise_parrain.montant` comme nouvelle référence
- **🏗️ ARCHITECTURE** : Code simplifié avec moins de risques d'incohérence
- **📝 LOGS ADAPTÉS** : Nouveau canal `webhook-parrainage-unifie` avec marqueur version
- **🎪 VALIDATION** : Payload restructuré avec indicateur `parrainage.version = "2.3.0"`

**STRUCTURE WEBHOOK FINALE :**

```json
{
  "parrainage": {
    "version": "2.3.0",
    "tarification": {
      "prix_avant_remise": 719.88,
      "frequence_paiement": "annuel",
      "remise_parrain": {
        "montant": 13.5,
        "unite": "EUR"
      }
    },
    "statut": {
      "remise_active": true,
      "message": "Remise parrain calculée et active"
    }
  }
}
```

**MIGRATION :**
Les intégrations webhook doivent migrer de `payload.parrainage_pricing.remise_parrain_montant` vers `payload.parrainage.tarification.remise_parrain.montant`.

### Version 2.2.0 (24-07-25 à 18h30) - ENRICHISSEMENT TARIFICATION

- **📊 NOUVEAU CHAMP** : Ajout du champ "Prix standard (€) avant remise parrainage" dans l'interface de configuration des produits
- **🔄 NOUVEAU MENU** : Ajout du menu déroulant "Fréquence de paiement" avec 3 options (Paiement unique/Mensuel/Annuel)
- **🔗 WEBHOOKS ENRICHIS** : Ajout de `prix_avant_remise` et `frequence_paiement` dans la section `parrainage_pricing`
- **🌍 FORMAT FRANÇAIS** : Support du format virgule française pour la saisie du prix standard (89,99)
- **🔒 VALIDATION RENFORCÉE** : Validation JavaScript et PHP pour les nouveaux champs avec plages de valeurs
- **📱 INTERFACE COMPLÈTE** : 6 champs de configuration par produit pour une tarification complète
- **⚡ PERFORMANCE** : Méthode `get_infos_tarification_configuree()` optimisée pour récupération unifiée
- **🎨 STYLES ADAPTÉS** : CSS responsive pour les nouveaux champs avec classes de validation visuelle
- **📝 LOGS ENRICHIS** : Canal `webhook-tarification-complete` pour traçabilité des nouvelles données
- **🔄 RÉTROCOMPATIBILITÉ** : Migration transparente avec valeurs par défaut (0,00€, "mensuel")
- **🏗️ OBJET PARRAINAGE RESTRUCTURÉ** : Séparation logique `produit` (tarification) et `remise_parrain` (bénéfice)
- **🛡️ SÉCURITÉ** : Validation stricte des fréquences de paiement avec liste blanche

**NOUVEAUX CHAMPS INTERFACE :**

- Prix standard (€) : Champ obligatoire avec validation 0-99999,99€
- Fréquence de paiement : Menu déroulant obligatoire avec 3 options fixes

**STRUCTURE WEBHOOK ENRICHIE :**

- `parrainage_pricing.prix_avant_remise` : Prix affiché avant remise
- `parrainage_pricing.frequence_paiement` : Fréquence de facturation
- `parrainage.produit.prix_avant_remise` : Prix standard dans la section produit
- `parrainage.produit.frequence_paiement` : Fréquence dans la section produit
- `parrainage.remise_parrain.montant` : Montant de la remise dans la section dédiée

**MIGRATION :**
Les configurations existantes sont automatiquement enrichies avec les valeurs par défaut : prix standard à 0,00€ et fréquence "mensuel". Les administrateurs peuvent ensuite configurer les vraies valeurs via l'interface.

### Version 2.1.0 (24-07-25 à 17h19) - FEATURE MAJEURE

- **🔧 MODIFICATION SYSTÈME** : Remplacement du calcul automatique de remise parrain par un système de configuration flexible
- **🆕 NOUVEAU CHAMP** : Ajout du champ "Remise Parrain (€/mois)" dans l'interface de configuration des produits
- **💰 REMISE FIXE** : Les remises parrain sont désormais configurables par produit en montant fixe (€) au lieu d'un pourcentage
- **🎯 FLEXIBILITÉ ADMIN** : Configuration individuelle par produit avec remise par défaut à 0,00€ pour les produits non configurés
- **🔗 WEBHOOKS SIMPLIFIÉS** : Suppression des clés obsolètes (`remise_parrain_pourcentage`, `remise_parrain_base_ht`) dans les payloads
- **⚡ PERFORMANCE** : Simplification de la logique de calcul - lecture directe de configuration vs calcul complexe
- **🔒 VALIDATION** : Validation JavaScript et PHP des montants de remise (format, plage 0-9999,99€)
- **🌍 FORMAT FRANÇAIS** : Support du format virgule française pour la saisie des montants (conversion automatique)
- **🚫 SUPPRESSION CONSTANTE** : Suppression de `WC_TB_PARRAINAGE_REDUCTION_PERCENTAGE` devenue obsolète
- **📱 INTERFACE ENRICHIE** : Nouveau champ dans l'interface admin avec validation en temps réel
- **🔄 RÉTROCOMPATIBILITÉ** : Migration transparente des configurations existantes avec remise 0,00€ par défaut
- **📝 LOGS ADAPTÉS** : Mise à jour des logs pour refléter le nouveau système (configuration vs calcul)
- **🎨 UX AMÉLIORÉE** : Interface plus intuitive pour les administrateurs avec contrôle total des remises

**IMPACT TECHNIQUE :**

- **Plugin.php** : Ajout du champ remise parrain dans l'interface de configuration
- **WebhookManager.php** : Remplacement de `calculer_remise_parrain()` par `get_remise_parrain_configuree()`
- **MyAccountDataProvider.php** : Adaptation de l'affichage côté client pour utiliser la configuration
- **admin.js** : Validation JavaScript du nouveau champ avec gestion format français
- **Structure webhook** : Clés simplifiées dans `parrainage_pricing` et `parrainage.remise_parrain`

**MIGRATION :**
Les configurations existantes sont automatiquement migrées avec une remise par défaut de 0,00€. Les administrateurs doivent configurer manuellement les remises souhaitées via l'interface "Configuration Produits".

### Version 2.0.6 (24-07-25 à 12h15) - FEATURE

- **🆕 NOUVEAU** : Champ `prenom` dans la section `parrainage.parrain` du payload webhook
- **💾 Stockage amélioré** : Sauvegarde séparée du prénom et nom dans les métadonnées (`_parrain_prenom`, `_parrain_nom`)
- **🎯 Données précises** : Récupération directe du `first_name` WordPress (support prénoms composés)
- **🔄 Rétrocompatibilité** : Conservation du champ `nom_complet` existant
- **📚 Documentation** : Mise à jour de l'exemple JSON et des spécifications
- **✅ Fiabilité** : Plus d'extraction par espaces, données directes depuis la base utilisateur WordPress

### Version 2.0.5 (24-07-25 à 11h45) - FEATURE

- **🚀 NOUVEAU** : Objet parrainage unifié dans le payload webhook
- **📊 Restructuration** : Regroupement de toutes les données de parrainage sous un objet `parrainage` unique
- **🏗️ Architecture** : Structure hiérarchisée avec sections `filleul`, `parrain`, `dates` et `remise_parrain`
- **✨ Amélioration UX** : Accès simplifié aux données (`payload.parrainage.remise_parrain.montant`)
- **📚 Documentation** : Documentation complète de la nouvelle structure avec exemples
- **🔄 Rétrocompatibilité** : Conservation des anciennes structures (`parrainage_pricing`, `meta_data`)
- **🎯 Logique métier** : Séparation claire filleul/parrain/dates/calculs
- **🛠️ Nouvelle méthode** : `construire_objet_parrainage()` dans WebhookManager
- **📝 Logs** : Canal dédié `webhook-parrainage-unifie` pour traçabilité
- **🎨 Lisibilité** : Structure JSON plus intuitive et maintenable pour les développeurs

### Version 2.0.4 (24-07-25 à 11h15) - HOTFIX

- **🚨 CORRECTION CRITIQUE** : Fix écrasement de la section `parrainage_pricing` dans les webhooks
- **Correctif** : Remplacement de l'assignation directe par un merge intelligent pour préserver les enrichissements
- **Amélioration** : Les nouvelles clés de remise parrain (`remise_parrain_montant`, etc.) sont désormais correctement conservées
- **Technique** : Modification de `$payload['parrainage_pricing'] = $infos_tarification` vers `array_merge()` conditionnel
- **Impact** : Les webhooks affichent maintenant correctement toutes les informations de remise parrain

### Version 2.0.3 (24-07-25 à 11h03) - PATCH

- **Nouveau** : Ajout du montant de remise parrain dans le payload webhook
- **Nouveau** : Nouvelles clés `remise_parrain_montant`, `remise_parrain_pourcentage`, `remise_parrain_base_ht`, `remise_parrain_unite` dans la section `parrainage_pricing`
- **Nouveau** : Calcul automatique de la remise parrain (25% du montant HT du filleul) pour les abonnements actifs
- **Nouveau** : Gestion des cas avec abonnements non encore actifs via `remise_parrain_status: 'pending'`
- **Nouveau** : Méthode `calculer_remise_parrain()` dans WebhookManager pour la logique de calcul
- **Amélioration** : Logs enrichis spécifiques aux calculs de remise parrain (canal 'webhook-parrain-remise')
- **Amélioration** : Support des commandes avec plusieurs abonnements via `remise_parrain_subscription_id`
- **Amélioration** : Documentation webhook enrichie avec exemples de payload complets
- **Amélioration** : Utilisation de la constante `WC_TB_PARRAINAGE_REDUCTION_PERCENTAGE` existante
- **Amélioration** : Arrondi monétaire à 2 décimales pour une précision standard

### Version 2.0.2 (24-07-25 à 16h38) - PATCH

- **Amélioration** : Interface "Mes parrainages" avec libellés plus explicites
- **Nouveau** : Colonne "Votre remise\*" affichant la remise du parrain (25% du prix HT filleul)
- **Amélioration** : Statuts d'abonnement humanisés ("En cours" au lieu de "Actif")
- **Nouveau** : Section explicative détaillant le fonctionnement des remises HT
- **Amélioration** : Distinction claire entre prix HT et TTC dans l'affichage
- **Correction** : Gestion d'erreur renforcée pour la récupération des prix HT
- **Amélioration** : Utilisation de `$subscription->get_subtotal()` pour le prix HT officiel
- **Nouveau** : Méthodes `format_montant_ht()` et `get_parrain_reduction()` dans MyAccountDataProvider
- **Amélioration** : Tableau restructuré avec 6 colonnes exactement selon spécifications

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
