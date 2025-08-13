<?php
namespace TBWeb\WCParrainage\Export;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExportValidator - Validation des paramètres d'export
 * Responsabilité : Validation sécurité, paramètres, permissions
 * @since 2.7.8
 */
class ExportValidator {
    
    // Constantes pour éviter magic numbers
    const MAX_DURATION = 30;
    const MIN_DURATION = 1;
    const VALID_TYPES = array( 'all', 'duration' );
    const VALID_LEVELS = array( 'ALL', 'ERROR', 'WARNING', 'INFO', 'DEBUG' );
    const NONCE_ACTION = 'tb_parrainage_admin_action';
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Validation complète d'une requête AJAX
     * @return bool Requête valide
     */
    public function validate_ajax_request() {
        // Vérification du nonce de sécurité
        if ( ! $this->validate_nonce() ) {
            wp_send_json_error( array( 
                'message' => __( 'Erreur de sécurité : token invalide', 'wc-tb-web-parrainage' ) 
            ) );
            return false;
        }
        
        // Vérification des permissions
        if ( ! $this->validate_permissions() ) {
            wp_send_json_error( array( 
                'message' => __( 'Permissions insuffisantes', 'wc-tb-web-parrainage' ) 
            ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation du nonce de sécurité
     * @return bool Nonce valide
     */
    private function validate_nonce() {
        if ( ! isset( $_POST['nonce'] ) ) {
            $this->logger->warning(
                'Tentative d\'export sans nonce',
                array( 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
                'export-security'
            );
            return false;
        }
        
        if ( ! wp_verify_nonce( $_POST['nonce'], self::NONCE_ACTION ) ) {
            $this->logger->warning(
                'Tentative d\'export avec nonce invalide',
                array( 
                    'nonce' => $_POST['nonce'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown' 
                ),
                'export-security'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation des permissions utilisateur
     * @return bool Permissions valides
     */
    private function validate_permissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            $this->logger->warning(
                'Tentative d\'export sans permissions',
                array( 
                    'user_id' => get_current_user_id(),
                    'user_login' => wp_get_current_user()->user_login ?? 'unknown'
                ),
                'export-security'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation des paramètres d'export
     * @param array $post_data Données POST
     * @return array|false Paramètres validés ou false
     */
    public function validate_export_params( $post_data ) {
        try {
            // Initialisation des paramètres par défaut
            $params = array(
                'type' => 'all',
                'duration' => 0,
                'level' => 'ALL',
                'source' => 'ALL'
            );
            
            // Validation du type d'export
            if ( isset( $post_data['type'] ) ) {
                $type = sanitize_text_field( $post_data['type'] );
                if ( ! $this->validate_export_type( $type ) ) {
                    $this->log_validation_error( 'Type d\'export invalide', array( 'type' => $type ) );
                    return false;
                }
                $params['type'] = $type;
            }
            
            // Validation de la durée si nécessaire
            if ( $params['type'] === 'duration' ) {
                if ( ! isset( $post_data['duration'] ) ) {
                    $this->log_validation_error( 'Durée manquante pour export par durée', array() );
                    return false;
                }
                
                $duration = $this->validate_duration( $post_data['duration'] );
                if ( $duration === false ) {
                    return false;
                }
                $params['duration'] = $duration;
            }
            
            // Validation du niveau de log
            if ( isset( $post_data['level'] ) ) {
                $level = sanitize_text_field( $post_data['level'] );
                if ( ! $this->validate_log_level( $level ) ) {
                    $this->log_validation_error( 'Niveau de log invalide', array( 'level' => $level ) );
                    return false;
                }
                $params['level'] = $level;
            }
            
            // Validation de la source
            if ( isset( $post_data['source'] ) ) {
                $source = sanitize_text_field( $post_data['source'] );
                if ( ! $this->validate_source( $source ) ) {
                    $this->log_validation_error( 'Source invalide', array( 'source' => $source ) );
                    return false;
                }
                $params['source'] = $source;
            }
            
            // Log de validation réussie
            $this->logger->debug(
                'Paramètres d\'export validés',
                $params,
                'export-validator'
            );
            
            return $params;
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur lors de la validation des paramètres',
                array( 'error' => $e->getMessage(), 'post_data' => $post_data ),
                'export-validator'
            );
            return false;
        }
    }
    
    /**
     * Validation du type d'export
     * @param string $type Type à valider
     * @return bool Type valide
     */
    private function validate_export_type( $type ) {
        return in_array( $type, self::VALID_TYPES, true );
    }
    
    /**
     * Validation de la durée
     * @param mixed $duration Durée à valider
     * @return int|false Durée validée ou false
     */
    private function validate_duration( $duration ) {
        // Conversion en entier
        $duration = intval( $duration );
        
        // Vérification des limites
        if ( $duration < self::MIN_DURATION || $duration > self::MAX_DURATION ) {
            $this->log_validation_error(
                'Durée hors limites',
                array( 
                    'duration' => $duration,
                    'min' => self::MIN_DURATION,
                    'max' => self::MAX_DURATION
                )
            );
            return false;
        }
        
        return $duration;
    }
    
    /**
     * Validation du niveau de log
     * @param string $level Niveau à valider
     * @return bool Niveau valide
     */
    private function validate_log_level( $level ) {
        return in_array( $level, self::VALID_LEVELS, true );
    }
    
    /**
     * Validation de la source
     * @param string $source Source à valider
     * @return bool Source valide
     */
    private function validate_source( $source ) {
        // 'ALL' est toujours valide
        if ( $source === 'ALL' ) {
            return true;
        }
        
        // Vérification que la source existe dans la base
        global $wpdb;
        $table_name = $wpdb->prefix . 'tb_parrainage_logs';
        
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE source = %s LIMIT 1",
            $source
        ) );
        
        if ( ! $exists ) {
            $this->log_validation_error(
                'Source inexistante dans la base',
                array( 'source' => $source )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation des limites de performance
     * @param array $params Paramètres validés
     * @return array|false Résultat de validation
     */
    public function validate_performance_limits( $params ) {
        // Estimation du nombre de logs
        $estimated_count = $this->estimate_log_count( $params );
        
        // Pas de limite stricte selon les spécifications
        // Mais retour d'informations pour l'utilisateur
        return array(
            'estimated_count' => $estimated_count,
            'needs_pagination' => $estimated_count > 1000,
            'warnings' => $this->generate_performance_warnings( $estimated_count )
        );
    }
    
    /**
     * Estimation du nombre de logs
     * @param array $params Paramètres
     * @return int Nombre estimé
     */
    private function estimate_log_count( $params ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tb_parrainage_logs';
        
        $where_conditions = array();
        $where_values = array();
        
        // Filtre par durée
        if ( $params['type'] === 'duration' && $params['duration'] > 0 ) {
            $minutes_ago = date( 'Y-m-d H:i:s', strtotime( "-{$params['duration']} minutes" ) );
            $where_conditions[] = 'datetime >= %s';
            $where_values[] = $minutes_ago;
        }
        
        // Filtre par niveau (simplification pour estimation)
        if ( $params['level'] !== 'ALL' ) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $params['level'];
        }
        
        // Filtre par source
        if ( $params['source'] !== 'ALL' ) {
            $where_conditions[] = 'source = %s';
            $where_values[] = $params['source'];
        }
        
        $where_clause = '';
        if ( ! empty( $where_conditions ) ) {
            $where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
        }
        
        $sql = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
        
        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }
        
        return intval( $wpdb->get_var( $sql ) );
    }
    
    /**
     * Génération d'avertissements de performance
     * @param int $estimated_count Nombre estimé
     * @return array Avertissements
     */
    private function generate_performance_warnings( $estimated_count ) {
        $warnings = array();
        
        if ( $estimated_count > 10000 ) {
            $warnings[] = __( 'Export volumineux : le téléchargement peut prendre plusieurs minutes', 'wc-tb-web-parrainage' );
        } elseif ( $estimated_count > 5000 ) {
            $warnings[] = __( 'Export important : temps de traitement élevé possible', 'wc-tb-web-parrainage' );
        }
        
        if ( $estimated_count === 0 ) {
            $warnings[] = __( 'Aucun log ne correspond à ces critères', 'wc-tb-web-parrainage' );
        }
        
        return $warnings;
    }
    
    /**
     * Log d'une erreur de validation
     * @param string $message Message d'erreur
     * @param array $context Contexte
     */
    private function log_validation_error( $message, $context ) {
        $this->logger->warning(
            "Validation export échouée : {$message}",
            array_merge( $context, array(
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ) ),
            'export-validator'
        );
    }
}
