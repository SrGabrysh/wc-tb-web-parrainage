# 🚀 Template Modal System - Démarrage Rapide

**✨ Créez des modales identiques aux Analytics en 5 minutes !**

## 🎯 Objectif

Vous voulez ajouter des modales d'aide **visuellement identiques** à celles des Analytics TB-Web Parrainage partout sur votre site ? Ce guide vous y amène en **5 étapes simples**.

## ⚡ Démarrage Ultra-Rapide

### Étape 1 : Créer le gestionnaire

```php
$modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
    null,           // Logger (optionnel)
    [],             // Configuration par défaut
    'mon_namespace' // Namespace unique pour éviter les conflits
);
```

### Étape 2 : Initialiser

```php
$modal_manager->init();
```

### Étape 3 : Définir le contenu

```php
$modal_manager->set_modal_content( 'ma_metrique', [
    'title' => 'Ma Métrique',
    'definition' => 'Description claire et simple de ma métrique',
    'tips' => [
        'Premier conseil d\'optimisation',
        'Deuxième conseil pratique'
    ]
] );
```

### Étape 4 : Charger les assets

```php
$modal_manager->enqueue_modal_assets();
```

### Étape 5 : Ajouter l'icône d'aide

```php
$modal_manager->render_help_icon( 'ma_metrique' );
```

**🎉 C'est terminé !** Vous avez maintenant une modale avec le même design que les Analytics.

---

## 📋 Exemple Concret Complet

```php
<?php
// Dans votre plugin ou thème

class MonDashboard {

    private $modal_manager;

    public function __construct() {
        // Créer et initialiser
        $this->modal_manager = new \TBWeb\WCParrainage\TemplateModalManager(
            null, [], 'dashboard'
        );
        $this->modal_manager->init();

        // Hook WordPress
        add_action( 'admin_init', [ $this, 'setup_modals' ] );
    }

    public function setup_modals() {
        // Définir tout le contenu
        $this->modal_manager->set_batch_modal_content( [
            'users_total' => [
                'title' => 'Utilisateurs Total',
                'definition' => 'Nombre total d\'utilisateurs enregistrés',
                'interpretation' => 'Indicateur de croissance de votre audience',
                'tips' => [ 'Encouragez les inscriptions', 'Simplifiez le processus' ]
            ],
            'revenue_monthly' => [
                'title' => 'Revenus Mensuels',
                'definition' => 'Total des revenus générés ce mois',
                'formula' => 'Somme des commandes validées',
                'tips' => [ 'Analysez les pics', 'Optimisez les promotions' ]
            ]
        ] );
    }

    public function render_dashboard() {
        // Charger les assets
        $this->modal_manager->enqueue_modal_assets();

        ?>
        <div class="wrap">
            <h1>Mon Dashboard</h1>

            <!-- Métrique 1 -->
            <div class="metric-card">
                <h3>
                    Utilisateurs Total
                    <?php $this->modal_manager->render_help_icon( 'users_total' ); ?>
                </h3>
                <div class="metric-value">1,234</div>
            </div>

            <!-- Métrique 2 -->
            <div class="metric-card">
                <h3>
                    Revenus Mensuels
                    <?php $this->modal_manager->render_help_icon( 'revenue_monthly' ); ?>
                </h3>
                <div class="metric-value">€5,678</div>
            </div>
        </div>
        <?php
    }
}

// Initialiser
new MonDashboard();
?>
```

---

## 🎨 Options Visuelles

### Positions d'icônes

```php
// Inline (par défaut)
$modal_manager->render_help_icon( 'ma_cle' );

// Position absolue (coin supérieur droit)
$modal_manager->render_help_icon( 'ma_cle', [ 'position' => 'absolute' ] );

// Float à droite
$modal_manager->render_help_icon( 'ma_cle', [ 'position' => 'float-right' ] );
```

### Tailles d'icônes

```php
// Petite
$modal_manager->render_help_icon( 'ma_cle', [ 'size' => 'small' ] );

// Normale (par défaut)
$modal_manager->render_help_icon( 'ma_cle', [ 'size' => 'normal' ] );

// Grande
$modal_manager->render_help_icon( 'ma_cle', [ 'size' => 'large' ] );
```

### Icônes personnalisées

```php
$modal_manager->render_help_icon( 'ma_cle', [
    'icon' => 'dashicons-admin-settings',  // Autre icône Dashicons
    'title' => 'Mon tooltip personnalisé'
] );
```

---

## 🔧 Configuration Avancée

### Dimensions personnalisées

```php
$config = [
    'modal_width' => 700,           // Plus large
    'modal_max_height' => 600,      // Plus haut
];

$modal_manager = new TemplateModalManager( null, $config, 'custom' );
```

### Performance optimisée

```php
$config = [
    'enable_cache' => true,         // Cache activé
    'cache_duration' => 3600,       // 1 heure
    'load_dashicons' => false       // Si déjà chargés ailleurs
];
```

### Support multilingue

```php
$config = [
    'enable_multilang' => true,     // Activer FR/EN/ES/DE/IT
    'default_language' => 'fr'
];

// Définir contenu par langue
$modal_manager->set_modal_content( 'ma_cle', [
    'title' => 'Ma Métrique',
    'definition' => 'Description en français'
], 'fr' );

$modal_manager->set_modal_content( 'ma_cle', [
    'title' => 'My Metric',
    'definition' => 'Description in English'
], 'en' );
```

---

## 📊 Types de Contenu Disponibles

### Contenu simple

```php
[
    'title' => 'Mon Titre',
    'content' => '<p>Mon contenu HTML libre...</p>'
]
```

### Contenu structuré (recommandé)

```php
[
    'title' => 'Ma Métrique',
    'definition' => 'Description simple et claire',
    'details' => [
        'Point de détail 1',
        'Point de détail 2'
    ],
    'formula' => '(A + B) / C × 100',
    'example' => 'Avec A=10, B=5, C=3 → Résultat = 500%',
    'interpretation' => 'Comment lire cette métrique',
    'precision' => 'Note importante à retenir',
    'tips' => [
        'Conseil d\'optimisation 1',
        'Conseil d\'optimisation 2'
    ]
]
```

---

## 🎯 Cas d'Usage Courants

### Dashboard Analytics

```php
// Parfait pour des métriques comme :
'total_users', 'monthly_revenue', 'conversion_rate', 'average_order'
```

### Formulaires de Configuration

```php
// Idéal pour expliquer :
'smtp_host', 'cache_duration', 'security_level', 'api_settings'
```

### Tableaux de Données

```php
// Documenter les colonnes :
'product_name', 'stock_status', 'sale_price', 'last_modified'
```

---

## 🚀 Exemples Prêts à l'Emploi

Le plugin inclut **3 exemples complets** dans `/examples/` :

1. **📋 `exemple-simple-usage.php`** - Guide débutant avec toutes les options
2. **📊 `exemple-dashboard-admin.php`** - Dashboard avec 6 métriques
3. **⚙️ `exemple-formulaire-config.php`** - Formulaire avec aide contextuelle

**💡 Conseil :** Commencez par copier `exemple-simple-usage.php` et adaptez-le !

---

## 🔍 Debug & Dépannage

### Problèmes fréquents

| **Problème**         | **Cause**           | **Solution**                      |
| -------------------- | ------------------- | --------------------------------- |
| Modal ne s'ouvre pas | Namespace incorrect | Vérifier `data-namespace`         |
| Contenu vide         | Clé inexistante     | Vérifier `set_modal_content()`    |
| Styles cassés        | Assets non chargés  | Vérifier `enqueue_modal_assets()` |
| Erreur AJAX          | Nonce invalide      | Régénérer les assets              |

### Mode Debug

```php
// Activer les logs détaillés
$config = [ 'debug' => true ];

// Voir les stats d'utilisation
$stats = $modal_manager->get_usage_stats();
var_dump( $stats );
```

---

## 📚 Ressources Complètes

- **📖 [Documentation complète](TEMPLATE_MODAL_DOCUMENTATION.md)** - Guide exhaustif
- **📁 [Dossier examples/](examples/)** - 3 exemples prêts à l'emploi
- **💻 [Code source](src/TemplateModalManager.php)** - Implementation complète

---

## ✨ Résultat Final

Avec ce système, vous obtenez :

- ✅ **Design identique** aux modales Analytics
- ✅ **Performance optimisée** avec cache intelligent
- ✅ **Accessibilité complète** (navigation clavier + screen readers)
- ✅ **Responsive natif** pour mobile/tablette/desktop
- ✅ **Réutilisabilité totale** avec namespaces
- ✅ **API simple** en 5 étapes seulement

**🎉 Commencez maintenant et créez vos premières modales en 5 minutes !**
