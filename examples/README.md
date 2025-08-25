# 📁 Exemples Template Modal System

Ce dossier contient des **exemples concrets et prêts à l'emploi** du Template Modal System pour créer des modales d'aide identiques à celles des Analytics TB-Web Parrainage.

## 📋 Liste des Exemples

### 🚀 [`exemple-simple-usage.php`](exemple-simple-usage.php)

**Perfect pour débuter !**

- ✅ **5 étapes seulement** pour créer vos premières modales
- ✅ **Code minimal** avec commentaires détaillés
- ✅ **Guide visuel** avec toutes les options disponibles
- ✅ **Exemples de positions** et tailles d'icônes

**Usage :** Copiez ce code dans votre plugin/thème pour démarrer rapidement.

### 📊 [`exemple-dashboard-admin.php`](exemple-dashboard-admin.php)

**Dashboard admin complet avec métriques**

- ✅ **6 métriques** avec modales d'aide détaillées
- ✅ **Design moderne** avec cards et animations
- ✅ **Données simulées** réalistes pour démonstration
- ✅ **Responsive design** adaptatif

**Parfait pour :** Pages d'analytics, tableaux de bord, rapports de performance.

### ⚙️ [`exemple-formulaire-config.php`](exemple-formulaire-config.php)

**Formulaire de configuration avec aide contextuelle**

- ✅ **3 sections** : Email, Performance, Sécurité
- ✅ **Explications détaillées** pour chaque champ
- ✅ **Validation intégrée** WordPress
- ✅ **Interface professionnelle** avec form-table

**Parfait pour :** Pages de paramètres, formulaires de configuration, options de plugins.

## 🎯 Comment utiliser les exemples

### 1. Installation

```bash
# Les exemples sont déjà inclus dans le plugin
wp-content/plugins/wc-tb-web-parrainage/examples/
```

### 2. Activation

```php
// Copiez le code de l'exemple choisi dans votre fichier PHP
// Ou incluez directement l'exemple :
require_once WC_TB_PARRAINAGE_PATH . 'examples/exemple-simple-usage.php';
```

### 3. Accès

- **Simple Usage** : Menu Admin → "Usage Simple"
- **Dashboard** : Menu Admin → Outils → "Exemple Dashboard"
- **Configuration** : Menu Admin → Réglages → "Exemple Config"

## 🔧 Personnalisation Rapide

### Changer le namespace

```php
// Dans l'exemple choisi, modifiez :
'mon_namespace'        // Au lieu de 'simple', 'dashboard_admin', etc.
```

### Adapter le contenu

```php
// Remplacez le contenu d'exemple par le vôtre :
$modal_manager->set_modal_content( 'ma_metrique', [
    'title' => 'Mon Titre',
    'definition' => 'Ma description...',
    'tips' => [ 'Mon conseil 1', 'Mon conseil 2' ]
] );
```

### Personnaliser l'apparence

```php
// Configuration custom :
$config = [
    'modal_width' => 800,           // Plus large
    'modal_max_height' => 600,      // Plus haut
    'enable_cache' => false         // Désactiver cache en dev
];
```

## 📚 Structure Type d'un Exemple

Tous les exemples suivent cette structure :

```php
class MonExemple {

    private $modal_manager;

    public function __construct() {
        // 1. Créer le gestionnaire
        $this->modal_manager = new TemplateModalManager( $logger, $config, 'namespace' );
        $this->modal_manager->init();

        // 2. Hooks WordPress
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'setup_content' ] );
    }

    public function setup_content() {
        // 3. Définir le contenu des modales
        $this->modal_manager->set_batch_modal_content( $contents );
    }

    public function render_page() {
        // 4. Charger les assets
        $this->modal_manager->enqueue_modal_assets();

        // 5. Rendre la page avec icônes d'aide
        $this->modal_manager->render_help_icon( 'ma_cle' );
    }
}
```

## 🎨 Galerie Visuelle

### Dashboard Admin

![Dashboard](https://via.placeholder.com/600x300/2271b1/ffffff?text=Dashboard+avec+6+m%C3%A9triques)
_6 cartes métriques avec icônes d'aide en position absolue_

### Formulaire Config

![Formulaire](https://via.placeholder.com/600x300/4caf50/ffffff?text=Formulaire+3+sections)
_Formulaire organisé en 3 sections avec aide sur chaque champ_

### Modal Ouverte

![Modal](https://via.placeholder.com/400x300/ff9800/ffffff?text=Modal+d%27aide+ouverte)
_Modal avec contenu structuré : définition, conseils, exemples_

## 💡 Cas d'Usage Recommandés

### 📊 **Analytics & Reporting**

- Tableaux de bord
- Rapports de performance
- Métriques business
- Indicateurs KPI

### ⚙️ **Configuration & Paramètres**

- Pages d'options
- Formulaires de réglages
- Configuration de plugins
- Paramètres utilisateur

### 📋 **Administration & Gestion**

- Listes de données
- Tableaux complexes
- Interfaces de gestion
- Outils d'administration

### 🛍️ **E-commerce**

- Configuration produits
- Paramètres boutique
- Analytics ventes
- Gestion commandes

## 🔍 Debug & Développement

### Mode Debug

```php
// Activez les logs détaillés
$config = [ 'debug' => true ];

// Ou en JavaScript
tbModalNamespace.config.debug = true;
```

### Cache en Développement

```php
// Désactivez le cache pendant le développement
$config = [ 'enable_cache' => false ];
```

### Vérifier les Assets

```php
// Forcez le rechargement des CSS/JS
wp_enqueue_script( 'handle', $url, [], time() );  // time() = pas de cache
```

## 📞 Support & Ressources

### Documentation Complète

- **[TEMPLATE_MODAL_DOCUMENTATION.md](../TEMPLATE_MODAL_DOCUMENTATION.md)** - Guide complet
- **[TemplateModalManager.php](../src/TemplateModalManager.php)** - Code source commenté

### Code Source

- **CSS :** `assets/css/template-modals.css`
- **JavaScript :** `assets/js/template-modals.js`
- **PHP :** `src/TemplateModalManager.php`

### Aide & Questions

1. Consultez d'abord la documentation complète
2. Analysez les exemples fournis
3. Vérifiez les logs de debug
4. Contactez le support TB-Web

---

**🎉 Prêt à créer vos modales ? Commencez par `exemple-simple-usage.php` !**
