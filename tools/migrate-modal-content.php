<?php
/**
 * Script de migration unique pour transférer le contenu des modales
 * À exécuter une seule fois via WP-CLI ou admin
 * 
 * @since 2.18.0
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
    exit;
}

class ModalContentMigrator {
    
    /**
     * Exécuter la migration
     */
    public static function run() {
        global $wpdb;
        
        echo "🚀 Début de la migration des modales Analytics vers TemplateModalManager...\n";
        
        // 1. Récupérer l'ancien contenu HelpModalManager
        $old_option = get_option( 'wc_tb_parrainage_help_content', [] );
        
        if ( empty( $old_option ) ) {
            echo "⚠️ Aucun contenu legacy à migrer\n";
            
            // Créer une option vide pour le nouveau système
            update_option( 'tb_modal_content_analytics', [] );
            update_option( 'tb_modal_migration_completed', time() );
            
            echo "✅ Nouveau système initialisé (migration vide)\n";
            return true;
        }
        
        echo "📦 " . count( $old_option ) . " éléments trouvés dans l'ancien système\n";
        
        // 2. Convertir et sauvegarder dans le nouveau format
        $new_option = [];
        $migrated_count = 0;
        
        foreach ( $old_option as $key => $content ) {
            // Extraire la langue du key si format: metric_fr
            if ( preg_match( '/^(.+)_([a-z]{2})$/', $key, $matches ) ) {
                $metric = $matches[1];
                $lang = $matches[2];
            } else {
                $metric = $key;
                $lang = 'fr';
            }
            
            // Créer la structure pour le nouveau système
            if ( ! isset( $new_option[ $metric ] ) ) {
                $new_option[ $metric ] = [];
            }
            
            // Conversion du format
            $converted_content = self::convert_content_format( $content );
            
            if ( ! empty( $converted_content ) ) {
                $new_option[ $metric ][ $lang ] = $converted_content;
                $migrated_count++;
                echo "  ✓ Migré: {$metric} ({$lang})\n";
            } else {
                echo "  ⚠️ Échec conversion: {$metric} ({$lang})\n";
            }
        }
        
        // 3. Sauvegarder dans la nouvelle option TemplateModalManager
        if ( $migrated_count > 0 ) {
            update_option( 'tb_modal_content_analytics', $new_option );
            
            // Backup de l'ancien système
            update_option( 'wc_tb_parrainage_help_content_backup', $old_option );
            
            // 4. Marquer la migration comme effectuée
            update_option( 'tb_modal_migration_completed', time() );
            
            echo "✅ Migration terminée avec succès!\n";
            echo "📊 Résumé: {$migrated_count} contenus migrés vers TemplateModalManager\n";
            echo "💾 Backup créé: wc_tb_parrainage_help_content_backup\n";
            
            return true;
        } else {
            echo "❌ Aucun contenu n'a pu être migré\n";
            return false;
        }
    }
    
    /**
     * Convertir le format legacy vers TemplateModalManager
     */
    private static function convert_content_format( array $old_content ): array {
        
        $new_content = [];
        
        // Champs obligatoires
        if ( ! empty( $old_content['title'] ) ) {
            $new_content['title'] = sanitize_text_field( $old_content['title'] );
        }
        
        if ( ! empty( $old_content['definition'] ) ) {
            $new_content['definition'] = sanitize_textarea_field( $old_content['definition'] );
        }
        
        // Champs optionnels standards
        $standard_fields = [ 'details', 'interpretation', 'tips', 'example', 'formula', 'precision' ];
        
        foreach ( $standard_fields as $field ) {
            if ( ! empty( $old_content[ $field ] ) ) {
                if ( is_array( $old_content[ $field ] ) ) {
                    $new_content[ $field ] = array_map( 'sanitize_text_field', $old_content[ $field ] );
                } else {
                    $new_content[ $field ] = sanitize_textarea_field( $old_content[ $field ] );
                }
            }
        }
        
        // Champs spécifiques système de santé
        if ( ! empty( $old_content['criteria'] ) && is_array( $old_content['criteria'] ) ) {
            // Fusionner dans details
            $existing_details = $new_content['details'] ?? [];
            if ( ! is_array( $existing_details ) ) {
                $existing_details = [ $existing_details ];
            }
            $new_content['details'] = array_merge( $existing_details, $old_content['criteria'] );
        }
        
        if ( ! empty( $old_content['levels'] ) && is_array( $old_content['levels'] ) ) {
            // Convertir en interprétation formatée
            $levels_text = implode( ' | ', $old_content['levels'] );
            $existing_interpretation = $new_content['interpretation'] ?? '';
            if ( ! empty( $existing_interpretation ) ) {
                $new_content['interpretation'] = $existing_interpretation . ' ' . $levels_text;
            } else {
                $new_content['interpretation'] = $levels_text;
            }
        }
        
        return $new_content;
    }
    
    /**
     * Rollback de la migration
     */
    public static function rollback() {
        
        echo "🔄 Début du rollback...\n";
        
        // 1. Restaurer l'ancienne option
        $backup = get_option( 'wc_tb_parrainage_help_content_backup' );
        if ( $backup ) {
            update_option( 'wc_tb_parrainage_help_content', $backup );
            echo "✅ Ancien système restauré depuis backup\n";
        } else {
            echo "⚠️ Aucun backup trouvé\n";
        }
        
        // 2. Supprimer les nouvelles options
        delete_option( 'tb_modal_content_analytics' );
        delete_option( 'tb_modal_migration_completed' );
        
        echo "🗑️ Nouvelles options supprimées\n";
        echo "⚠️ Rollback effectué - Ancien système HelpModalManager restauré\n";
        
        return true;
    }
    
    /**
     * Statistiques de migration
     */
    public static function get_migration_stats() {
        
        $old_content = get_option( 'wc_tb_parrainage_help_content', [] );
        $new_content = get_option( 'tb_modal_content_analytics', [] );
        $migration_date = get_option( 'tb_modal_migration_completed' );
        
        $stats = [
            'legacy_items' => count( $old_content ),
            'new_items' => count( $new_content ),
            'migration_completed' => ! empty( $migration_date ),
            'migration_date' => $migration_date ? date( 'Y-m-d H:i:s', $migration_date ) : null,
            'backup_exists' => ! empty( get_option( 'wc_tb_parrainage_help_content_backup' ) )
        ];
        
        return $stats;
    }
}

// Exécuter si appelé directement via WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    
    WP_CLI::add_command( 'tb-modal migrate', function() {
        $result = ModalContentMigrator::run();
        if ( $result ) {
            WP_CLI::success( 'Migration des modales terminée avec succès' );
        } else {
            WP_CLI::error( 'Échec de la migration des modales' );
        }
    });
    
    WP_CLI::add_command( 'tb-modal rollback', function() {
        $result = ModalContentMigrator::rollback();
        if ( $result ) {
            WP_CLI::success( 'Rollback terminé avec succès' );
        } else {
            WP_CLI::error( 'Échec du rollback' );
        }
    });
    
    WP_CLI::add_command( 'tb-modal stats', function() {
        $stats = ModalContentMigrator::get_migration_stats();
        WP_CLI::log( 'Statistiques de migration:' );
        WP_CLI::log( '- Éléments legacy: ' . $stats['legacy_items'] );
        WP_CLI::log( '- Éléments TemplateModalManager: ' . $stats['new_items'] );
        WP_CLI::log( '- Migration terminée: ' . ( $stats['migration_completed'] ? 'Oui' : 'Non' ) );
        if ( $stats['migration_date'] ) {
            WP_CLI::log( '- Date migration: ' . $stats['migration_date'] );
        }
        WP_CLI::log( '- Backup disponible: ' . ( $stats['backup_exists'] ? 'Oui' : 'Non' ) );
    });
}

// Fonction helper pour utilisation programmatique
function tb_migrate_modal_content() {
    return ModalContentMigrator::run();
}

function tb_rollback_modal_migration() {
    return ModalContentMigrator::rollback();
}
