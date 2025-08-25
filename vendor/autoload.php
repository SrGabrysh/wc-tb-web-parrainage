<?php

// Autoloader simple pour TB-Web Parrainage Plugin
// Cette version simplifiée remplace temporairement Composer

spl_autoload_register(function ($class) {
    // Namespace du plugin
    $prefix = 'TBWeb\\WCParrainage\\';
    
    // Répertoire de base pour le namespace
    $base_dir = __DIR__ . '/../src/';
    
    // Vérifier si la classe utilise le namespace du plugin
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Non, passer au prochain autoloader
        return;
    }
    
    // Obtenir le nom de classe relatif
    $relative_class = substr($class, $len);
    
    // Remplacer le namespace par le chemin du fichier
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Si le fichier existe, l'inclure
    if (file_exists($file)) {
        require $file;
    }
}); 