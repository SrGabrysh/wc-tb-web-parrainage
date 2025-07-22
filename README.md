# WC TB-Web Parrainage

**Version:** 1.0.0  
**Auteur:** TB-Web  
**Compatible:** WordPress 6.0+, PHP 8.1+, WooCommerce 3.0+

## Description

Plugin de parrainage WooCommerce avec webhooks enrichis. Ce plugin combine deux fonctionnalités principales :

1. **Système de code parrain au checkout** - Permet aux clients de saisir un code parrain lors de la commande avec validation en temps réel
2. **Webhooks enrichis** - Ajoute automatiquement les métadonnées d'abonnement WooCommerce Subscriptions dans les webhooks

## Fonctionnalités

### ✨ Système de Parrainage

- Champ "Code parrain" au checkout WooCommerce
- Validation en temps réel via AJAX (format et existence en BDD)
- Messages dynamiques selon les produits du panier
- Prévention de l'auto-parrainage
- Stockage complet des informations dans les commandes
- Affichage enrichi dans l'administration des commandes

### 🔗 Webhooks Enrichis

- Ajout automatique des métadonnées d'abonnement dans les webhooks
- Informations complètes : ID, statut, dates, articles, facturation
- Support WooCommerce Subscriptions
- Logs détaillés de tous les traitements

### 🎛️ Interface d'Administration

- Consultation en temps réel des logs (avec filtres et recherche)
- Statistiques de parrainage
- Paramètres configurables
- Nettoyage automatique des anciens logs

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
- **WooCommerce Subscriptions** (optionnel, pour le système de parrainage)

### Paramètres

Rendez-vous dans **Réglages > TB-Web Parrainage** pour configurer :

- ✅ **Activer les webhooks enrichis** - Ajoute les métadonnées d'abonnement
- ✅ **Activer le système de parrainage** - Affiche le champ code parrain au checkout
- 🕐 **Rétention des logs** - Durée de conservation (1-365 jours)

## Utilisation

### Codes Parrain

Les codes parrain correspondent aux **ID d'abonnements actifs** WooCommerce Subscriptions :

- Format : **4 chiffres** (ex: 4896)
- Validation automatique en base de données
- Affichage des informations du parrain lors de la validation

### Messages par Produit

Le plugin supporte des messages personnalisés par produit. Par défaut configuré pour :

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
  ]
}
```

## Développement

### Structure du Plugin

```
wc-tb-web-parrainage/
├── wc-tb-web-parrainage.php    # Fichier principal
├── composer.json               # Autoload PSR-4
├── src/
│   ├── Plugin.php             # Classe principale
│   ├── Logger.php             # Système de logs
│   ├── WebhookManager.php     # Gestion webhooks
│   └── ParrainageManager.php  # Système parrainage
├── assets/
│   ├── admin.css              # Styles administration
│   └── admin.js               # Scripts administration
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

### Version 1.0.0 (2024-01-XX)

- Version initiale
- Système de code parrain complet avec validation AJAX
- Webhooks enrichis avec métadonnées d'abonnement
- Interface d'administration avec logs et statistiques
- Support WooCommerce Subscriptions
