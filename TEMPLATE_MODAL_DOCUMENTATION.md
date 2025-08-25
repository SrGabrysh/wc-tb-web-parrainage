# 🎭 Template Modal System - Documentation

**Version :** 1.0.0  
**Compatibilité :** WordPress 6.0+, jQuery UI Dialog  
**Auteur :** TB-Web

## 📋 Vue d'ensemble

Le **Template Modal System** est un framework modulaire qui permet de créer des modales d'aide visuellement **identiques** aux modales Analytics du plugin TB-Web Parrainage, partout sur votre site WordPress.

### 🎯 Objectifs

- ✅ **Réutilisabilité** : Un seul système pour toutes vos modales
- ✅ **Cohérence visuelle** : Design identique aux modales Analytics
- ✅ **Flexibilité** : Configurable selon vos besoins
- ✅ **Performance** : Cache intelligent et chargement optimisé
- ✅ **Accessibilité** : Navigation clavier et screen readers

---

## 🏗️ Architecture

### Composants principaux

1. **`TemplateModalManager.php`** - Gestionnaire backend (PHP)
2. **`template-modals.css`** - Styles génériques
3. **`template-modals.js`** - Logique frontend
4. **Documentation & exemples** - Ce fichier

### Principe de fonctionnement

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Icône d'aide  │───▶│  Clic utilisateur │───▶│  Modal ouverte  │
└─────────────────┘    └──────────────────┘    └─────────────────┘
        ▲                        │                       │
        │                        ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ CSS générique   │    │ Requête AJAX     │    │ Contenu affiché │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

---

## 🚀 Installation Rapide

### 1. Inclusion des fichiers

Les fichiers sont déjà inclus dans le plugin TB-Web Parrainage :

```
src/TemplateModalManager.php
assets/css/template-modals.css
assets/js/template-modals.js
```

### 2. Initialisation basique

```php
// Dans votre code PHP
use TBWeb\WCParrainage\TemplateModalManager;

// Créer une instance avec namespace unique
$modal_manager = new TemplateModalManager(
    $logger,                    // Instance du logger
    [],                        // Configuration (optionnel)
    'mon_namespace'            // Namespace unique
);

// Initialiser
$modal_manager->init();

// Charger les assets sur vos pages
$modal_manager->enqueue_modal_assets();
```

---

## ⚙️ Configuration

### Options disponibles

```php
$config = [
    // Dimensions
    'modal_width' => 600,              // Largeur maximale
    'modal_max_height' => 500,         // Hauteur maximale

    // Fonctionnalités
    'enable_multilang' => false,       // Support multilingue
    'default_language' => 'fr',        // Langue par défaut
    'enable_cache' => true,            // Cache des contenus
    'cache_duration' => 300,           // Durée cache (secondes)
    'enable_keyboard_nav' => true,     // Navigation clavier
    'load_dashicons' => true,          // Charger Dashicons

    // Personnalisation
    'ajax_action_prefix' => 'tb_modal_custom',
    'css_prefix' => 'tb-modal-custom',
    'storage_option' => 'tb_modal_content_custom'
];

$modal_manager = new TemplateModalManager( $logger, $config, 'custom' );
```

---

## 📝 Utilisation

### 1. Ajouter des icônes d'aide

#### Option A : Via PHP (recommandé)

```php
// Dans votre template/page
$modal_manager->render_help_icon( 'ma_metrique', [
    'icon' => 'dashicons-info-outline',    // Icône Dashicons
    'title' => 'Aide sur ma métrique',     // Tooltip
    'position' => 'inline',                // inline|absolute|float-right
    'size' => 'normal',                    // small|normal|large
    'custom_classes' => ['ma-classe']      // Classes CSS additionnelles
] );
```

#### Option B : Directement en HTML

```html
<span
  class="tb-modal-custom-icon tb-modal-custom-icon-inline"
  data-modal-key="ma_metrique"
  data-namespace="custom"
  title="Aide sur ma métrique"
  tabindex="0"
>
  <i class="dashicons dashicons-info-outline"></i>
</span>
```

### 2. Définir le contenu des modales

#### Contenu simple

```php
$modal_manager->set_modal_content( 'ma_metrique', [
    'title' => 'Ma Métrique',
    'content' => '<p>Explication de ma métrique...</p><ul><li>Point 1</li><li>Point 2</li></ul>'
] );
```

#### Contenu structuré (recommandé)

```php
$modal_manager->set_modal_content( 'ma_metrique', [
    'title' => 'Ma Métrique',
    'definition' => 'Description courte et claire de la métrique',
    'details' => [
        'Premier point de détail',
        'Deuxième point de détail',
        'Troisième point de détail'
    ],
    'interpretation' => 'Comment interpréter cette métrique',
    'formula' => '(A + B) / C × 100',           // Optionnel
    'example' => 'Exemple : avec A=10, B=5, C=3 → 500%',  // Optionnel
    'precision' => 'Note importante sur cette métrique',   // Optionnel
    'tips' => [
        'Conseil d\'optimisation 1',
        'Conseil d\'optimisation 2'
    ]
] );
```

#### Contenu en lot (batch)

```php
$batch_content = [
    'metrique_1' => [
        'title' => 'Première Métrique',
        'definition' => 'Description...'
    ],
    'metrique_2' => [
        'title' => 'Deuxième Métrique',
        'definition' => 'Description...'
    ]
];

$modal_manager->set_batch_modal_content( $batch_content );
```

### 3. Support multilingue (optionnel)

```php
// Activer le multilingue
$config = [ 'enable_multilang' => true ];
$modal_manager = new TemplateModalManager( $logger, $config, 'multilang' );

// Définir contenu en français
$modal_manager->set_modal_content( 'ma_metrique', [
    'title' => 'Ma Métrique',
    'definition' => 'Description en français'
], 'fr' );

// Définir contenu en anglais
$modal_manager->set_modal_content( 'ma_metrique', [
    'title' => 'My Metric',
    'definition' => 'Description in English'
], 'en' );
```

---

## 🎨 Personnalisation Visuelle

### 1. Classes CSS disponibles

Le système génère automatiquement des classes basées sur votre namespace :

```css
/* Si namespace = "custom" */
.tb-modal-custom-icon              /* Icône d'aide */
/* Icône d'aide */
.tb-modal-custom-modal             /* Conteneur modal */
.tb-modal-custom-content           /* Contenu modal */
.tb-modal-custom-dialog            /* Dialog jQuery UI */

/* Positions */
.tb-modal-custom-icon-inline       /* Position inline */
.tb-modal-custom-icon-absolute     /* Position absolue */
.tb-modal-custom-icon-float-right  /* Float right */

/* Tailles */
.tb-modal-custom-icon-small        /* Petite taille */
.tb-modal-custom-icon-normal       /* Taille normale */
.tb-modal-custom-icon-large; /* Grande taille */
```

### 2. Zones de contenu spécialisées

```css
.modal-definition    /* Zone de définition (gris clair) */
/* Zone de définition (gris clair) */
.modal-example      /* Zone d'exemple (jaune clair) */
.modal-precision    /* Zone de précision (bleu clair) */
.modal-tips         /* Zone de conseils (vert clair) */
.modal-warning      /* Zone d'alerte (orange) */
.modal-error; /* Zone d'erreur (rouge) */
```

### 3. Surcharger les styles

```css
/* Personnaliser vos icônes */
.tb-modal-custom-icon .dashicons {
  color: #your-color !important;
}

/* Personnaliser vos modales */
.tb-modal-custom-dialog .ui-dialog {
  border-color: #your-color !important;
}

/* Personnaliser le contenu */
.tb-modal-custom-content .modal-definition {
  background: #your-background !important;
  border-left-color: #your-accent !important;
}
```

---

## 🧩 Intégration avec des containers

### 1. Cards/Boîtes

```html
<div class="tb-modal-card">
  <h3>Titre de ma carte</h3>
  <p>Contenu de ma carte...</p>

  <!-- Icône en position absolue (coin supérieur droit) -->
  <?php $modal_manager->render_help_icon( 'ma_carte', [ 'position' => 'absolute'
  ] ); ?>
</div>
```

### 2. Tableaux

```html
<table class="wp-list-table">
  <thead>
    <tr>
      <th>
        Colonne 1
        <?php $modal_manager->render_help_icon( 'colonne_1' ); ?>
      </th>
      <th>
        Colonne 2
        <?php $modal_manager->render_help_icon( 'colonne_2' ); ?>
      </th>
    </tr>
  </thead>
  <!-- ... -->
</table>
```

### 3. Formulaires

```html
<form>
  <label>
    Mon champ
    <?php $modal_manager->render_help_icon( 'mon_champ', [ 'size' => 'small' ]
    ); ?>
  </label>
  <input type="text" name="mon_champ" />
</form>
```

---

## 🔧 API JavaScript

### Utilisation côté frontend

```javascript
// L'objet JavaScript est auto-généré selon votre namespace
// Si namespace = "custom" → tbModalCustom

// Ouvrir une modal programmatiquement
tbModalCustom.openModal("ma_metrique");

// Définir du contenu directement (bypass AJAX)
tbModalCustom.setModalContent("ma_metrique", {
  title: "Ma Métrique",
  definition: "Description...",
});

// Fermer toutes les modales
tbModalCustom.closeAllModals();

// Obtenir les statistiques d'utilisation
const stats = tbModalCustom.getStats();
console.log(stats);
// { modalsOpened: 5, cacheHits: 3, ajaxRequests: 2, errors: 0 }

// Vider le cache
tbModalCustom.clearCache();
```

### Configuration JavaScript custom

```javascript
// Créer un gestionnaire custom avec configuration avancée
const monModalManager = new TBTemplateModals({
  namespace: "mon_namespace",
  modalWidth: 800,
  modalMaxHeight: 600,
  enableCache: true,
  enableMultilang: true,
  ajaxUrl: ajaxurl,
  nonce: "mon_nonce",
  strings: {
    loading: "Chargement personnalisé...",
    error: "Erreur personnalisée",
  },
});
```

---

## 🎯 Exemples Concrets

### Exemple 1 : Dashboard admin avec métriques

```php
<?php
// Initialisation
$dashboard_modals = new TemplateModalManager( $logger, [], 'dashboard' );
$dashboard_modals->init();

// Définir les contenus
$dashboard_modals->set_batch_modal_content( [
    'users_total' => [
        'title' => 'Utilisateurs Total',
        'definition' => 'Nombre total d\'utilisateurs enregistrés',
        'interpretation' => 'Indicateur de croissance de votre audience',
        'tips' => [ 'Encouragez les inscriptions', 'Simplifiez le processus' ]
    ],
    'revenue_monthly' => [
        'title' => 'Revenus Mensuels',
        'definition' => 'Revenus générés ce mois-ci',
        'formula' => 'Somme des commandes validées',
        'tips' => [ 'Analysez les pics', 'Optimisez les promotions' ]
    ]
] );
?>

<div class="wrap">
    <h1>Dashboard Admin</h1>

    <div class="tb-modal-card">
        <h3>Utilisateurs Total</h3>
        <div class="metric-value">1,234</div>
        <?php $dashboard_modals->render_help_icon( 'users_total', [
            'position' => 'absolute'
        ] ); ?>
    </div>

    <div class="tb-modal-card">
        <h3>Revenus Mensuels</h3>
        <div class="metric-value">€5,678</div>
        <?php $dashboard_modals->render_help_icon( 'revenue_monthly', [
            'position' => 'absolute'
        ] ); ?>
    </div>
</div>

<?php
// Charger les assets
$dashboard_modals->enqueue_modal_assets();
?>
```

### Exemple 2 : Formulaire de configuration

```php
<?php
$form_modals = new TemplateModalManager( $logger, [], 'config_form' );
$form_modals->init();

$form_modals->set_batch_modal_content( [
    'smtp_host' => [
        'title' => 'Serveur SMTP',
        'definition' => 'Adresse du serveur de messagerie sortante',
        'example' => 'smtp.gmail.com ou mail.votre-domaine.com',
        'tips' => [ 'Vérifiez auprès de votre hébergeur', 'Testez la connexion' ]
    ],
    'smtp_port' => [
        'title' => 'Port SMTP',
        'definition' => 'Port de connexion au serveur SMTP',
        'details' => [ 'Port 25 : Standard (souvent bloqué)', 'Port 587 : STARTTLS (recommandé)', 'Port 465 : SSL/TLS' ]
    ]
] );
?>

<form method="post">
    <table class="form-table">
        <tr>
            <th scope="row">
                Serveur SMTP
                <?php $form_modals->render_help_icon( 'smtp_host' ); ?>
            </th>
            <td>
                <input type="text" name="smtp_host" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                Port SMTP
                <?php $form_modals->render_help_icon( 'smtp_port' ); ?>
            </th>
            <td>
                <input type="number" name="smtp_port" class="small-text" />
            </td>
        </tr>
    </table>
</form>

<?php $form_modals->enqueue_modal_assets(); ?>
```

### Exemple 3 : Liste de produits WooCommerce

```php
<?php
$products_modals = new TemplateModalManager( $logger, [], 'products' );
$products_modals->init();

$products_modals->set_batch_modal_content( [
    'stock_status' => [
        'title' => 'Statut Stock',
        'definition' => 'Disponibilité actuelle du produit',
        'details' => [ 'En stock : Disponible à la vente', 'Rupture : Temporairement indisponible', 'Sur commande : Délai de livraison' ]
    ],
    'sale_price' => [
        'title' => 'Prix Promo',
        'definition' => 'Prix réduit temporaire du produit',
        'interpretation' => 'Augmente l\'attractivité et les conversions',
        'tips' => [ 'Limitez dans le temps', 'Communiquez l\'économie réalisée' ]
    ]
] );
?>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Produit</th>
            <th>
                Statut Stock
                <?php $products_modals->render_help_icon( 'stock_status', [ 'size' => 'small' ] ); ?>
            </th>
            <th>
                Prix Promo
                <?php $products_modals->render_help_icon( 'sale_price', [ 'size' => 'small' ] ); ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <!-- Vos produits... -->
    </tbody>
</table>

<?php $products_modals->enqueue_modal_assets(); ?>
```

---

## 🔒 Sécurité

### Nonces et permissions

Le système gère automatiquement :

- ✅ **Nonces WordPress** pour toutes les requêtes AJAX
- ✅ **Vérification des permissions** utilisateur
- ✅ **Sanitisation** de tous les inputs
- ✅ **Échappement HTML** des contenus affichés

### Bonnes pratiques

```php
// ✅ Bon : Permissions vérifiées automatiquement
$modal_manager->set_modal_content( $key, $content );

// ✅ Bon : Contenu sanitisé automatiquement
$content = [
    'definition' => 'Contenu <script>alert("test")</script>',  // Script supprimé
    'tips' => [ 'Conseil <b>important</b>' ]                   // HTML autorisé préservé
];

// ❌ Éviter : Bypass de sécurité
echo '<div>' . $_POST['unsafe_content'] . '</div>';
```

---

## 📊 Performance

### Cache intelligent

- ✅ **Cache côté JavaScript** : Évite les requêtes AJAX répétées
- ✅ **Cache côté serveur** : Évite les requêtes base de données
- ✅ **Lazy loading** : Modales créées seulement si nécessaires
- ✅ **Assets conditionnels** : CSS/JS chargés seulement où nécessaire

### Optimisations

```php
// Configuration performance optimisée
$config = [
    'enable_cache' => true,
    'cache_duration' => 3600,      // 1 heure
    'load_dashicons' => false      // Si vous avez déjà Dashicons
];
```

### Statistiques d'utilisation

```php
// Obtenir les stats du gestionnaire
$stats = $modal_manager->get_usage_stats();
/*
[
    'total_elements' => 5,
    'languages' => ['fr', 'en'],
    'elements_count_by_language' => [
        'fr' => 5,
        'en' => 3
    ]
]
*/
```

---

## 🐛 Débogage

### Logs automatiques

Le système log automatiquement :

```php
// Logs côté serveur (via Logger du plugin)
$this->logger->info( 'Template Modal action', $data, 'template-modal-manager' );

// Logs côté client (console navigateur)
console.log('[TB Modal custom] Modal ouverte', { elementKey: 'ma_metrique' });
```

### Mode debug

```javascript
// Activer les logs détaillés
tbModalCustom.config.debug = true;

// Voir les statistiques
console.log(tbModalCustom.getStats());
```

### Problèmes fréquents

| Problème             | Cause probable      | Solution                          |
| -------------------- | ------------------- | --------------------------------- |
| Modal ne s'ouvre pas | Namespace incorrect | Vérifier `data-namespace`         |
| Contenu vide         | Clé inexistante     | Vérifier `set_modal_content()`    |
| Style cassé          | CSS non chargé      | Vérifier `enqueue_modal_assets()` |
| Erreur AJAX          | Nonce invalide      | Régénérer les assets              |

---

## 🔄 Migration depuis Analytics

Si vous avez des modales basées sur le système Analytics :

```php
// Ancien système (Analytics)
$help_modal_manager->render_help_icon( 'metric_key' );

// Nouveau système (Template)
$template_modal_manager->render_help_icon( 'metric_key' );
```

Les styles et comportements sont identiques, seule l'initialisation change.

---

## 📚 Référence API

### TemplateModalManager (PHP)

#### Constructeur

```php
__construct( Logger $logger, array $config = [], string $namespace = 'generic' )
```

#### Méthodes principales

```php
init(): void                                    // Initialiser les hooks
enqueue_modal_assets( string $hook = '' ): void // Charger CSS/JS
render_help_icon( string $key, array $options = [] ): void // Rendre icône
set_modal_content( string $key, array $content, string $lang = '' ): bool // Définir contenu
set_batch_modal_content( array $batch, string $lang = '' ): bool // Batch contenu
get_usage_stats(): array                       // Statistiques
cleanup_modal_data(): bool                     // Nettoyer données
```

### TBTemplateModals (JavaScript)

#### Méthodes publiques

```javascript
openModal(elementKey); // Ouvrir modal
setModalContent(elementKey, content, language); // Définir contenu
closeAllModals(); // Fermer toutes
getStats(); // Statistiques
clearCache(); // Vider cache
```

---

## 📞 Support

### Ressources

- **Documentation** : Ce fichier
- **Exemples** : Dossier `examples/` (voir section suivante)
- **Source** : Code source commenté dans `src/`

### Contact

- **Auteur** : TB-Web
- **Version** : 1.0.0
- **Compatibilité** : WordPress 6.0+, PHP 8.1+

---

**🎉 Votre système de modales est maintenant prêt à l'emploi !**

Consultez la section exemples ci-dessous pour des implémentations concrètes.
