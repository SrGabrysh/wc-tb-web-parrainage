<?php
namespace TBWeb\WCParrainage\Export;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExportHandler - Logique métier d'export des logs
 * Responsabilité : Génération fichiers, requêtes BDD, formatage JSON
 * @since 2.7.8
 */
class ExportHandler {
    
    // Constantes pour éviter magic numbers
    const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    const CHUNK_SIZE = 100;
    const MAX_MEMORY_LOGS = 1000;
    const TEMP_FILE_PREFIX = 'tb_parrainage_export_';
    
    private $logger;
    private $table_name;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tb_parrainage_logs';
    }
    
    /**
     * Export principal des logs
     * @param array $params Paramètres d'export validés
     * @return array Résultat de l'export
     */
    public function export_logs( $params ) {
        try {
            // Génération de la requête SQL
            $sql_data = $this->build_export_query( $params );
            
            // Comptage total pour optimisation
            $total_logs = $this->count_logs( $sql_data['where_clause'], $sql_data['where_values'] );
            
            if ( $total_logs === 0 ) {
                return array(
                    'success' => false,
                    'message' => __( 'Aucun log trouvé avec ces critères', 'wc-tb-web-parrainage' )
                );
            }
            
            // Génération du nom de fichier
            $filename = $this->generate_filename( $params, $total_logs );
            
            // Export selon la taille
            if ( $total_logs <= self::MAX_MEMORY_LOGS ) {
                return $this->export_small_dataset( $sql_data, $filename, $total_logs );
            } else {
                return $this->export_large_dataset( $sql_data, $filename, $total_logs );
            }
            
        } catch ( \Exception $e ) {
            $this->logger->error(
                'Erreur dans export_logs',
                array( 'error' => $e->getMessage(), 'params' => $params ),
                'export-handler'
            );
            
            return array(
                'success' => false,
                'message' => __( 'Erreur technique lors de l\'export', 'wc-tb-web-parrainage' )
            );
        }
    }
    
    /**
     * Construction de la requête d'export
     * @param array $params Paramètres validés
     * @return array SQL data
     */
    private function build_export_query( $params ) {
        $where_conditions = array();
        $where_values = array();
        
        // Filtre par durée
        if ( $params['type'] === 'duration' && $params['duration'] > 0 ) {
            $minutes_ago = date( 'Y-m-d H:i:s', strtotime( "-{$params['duration']} minutes" ) );
            $where_conditions[] = 'datetime >= %s';
            $where_values[] = $minutes_ago;
        }
        
        // Filtre par niveau
        if ( $params['level'] !== 'ALL' ) {
            $levels = $this->get_levels_for_filter( $params['level'] );
            $placeholders = implode( ',', array_fill( 0, count( $levels ), '%s' ) );
            $where_conditions[] = "level IN ({$placeholders})";
            $where_values = array_merge( $where_values, $levels );
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
        
        return array(
            'where_clause' => $where_clause,
            'where_values' => $where_values
        );
    }
    
    /**
     * Obtenir les niveaux de log pour un filtre
     * @param string $filter Filtre sélectionné
     * @return array Niveaux de log
     */
    private function get_levels_for_filter( $filter ) {
        switch ( $filter ) {
            case 'ERROR':
                return array( 'ERROR' );
            case 'WARNING':
                return array( 'ERROR', 'WARNING' );
            case 'INFO':
                return array( 'ERROR', 'WARNING', 'INFO' );
            case 'DEBUG':
                return array( 'ERROR', 'WARNING', 'INFO', 'DEBUG' );
            default:
                return array( 'ERROR', 'WARNING', 'INFO', 'DEBUG' );
        }
    }
    
    /**
     * Compter les logs correspondants
     * @param string $where_clause Clause WHERE
     * @param array $where_values Valeurs pour la clause
     * @return int Nombre de logs
     */
    private function count_logs( $where_clause, $where_values ) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        
        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }
        
        return intval( $wpdb->get_var( $sql ) );
    }
    
    /**
     * Export pour petit dataset (en mémoire)
     * @param array $sql_data Données SQL
     * @param string $filename Nom du fichier
     * @param int $total_logs Nombre total de logs
     * @return array Résultat
     */
    private function export_small_dataset( $sql_data, $filename, $total_logs ) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table_name} {$sql_data['where_clause']} ORDER BY datetime DESC";
        
        if ( ! empty( $sql_data['where_values'] ) ) {
            $sql = $wpdb->prepare( $sql, $sql_data['where_values'] );
        }
        
        $logs = $wpdb->get_results( $sql, ARRAY_A );
        
        // Formatage pour LLM
        $formatted_logs = $this->format_logs_for_llm( $logs );
        
        // Génération du JSON
        $json_content = json_encode( $formatted_logs, self::JSON_OPTIONS );
        
        // Création du fichier temporaire
        $temp_file = $this->create_temp_file( $filename, $json_content );
        
        return array(
            'success' => true,
            'download_url' => $temp_file['url'],
            'filename' => $filename,
            'logs_count' => $total_logs,
            'file_size' => $temp_file['size']
        );
    }
    
    /**
     * Export pour large dataset (par chunks)
     * @param array $sql_data Données SQL
     * @param string $filename Nom du fichier
     * @param int $total_logs Nombre total de logs
     * @return array Résultat
     */
    private function export_large_dataset( $sql_data, $filename, $total_logs ) {
        global $wpdb;
        
        // Créer le fichier temporaire
        $temp_path = wp_upload_dir()['basedir'] . '/' . self::TEMP_FILE_PREFIX . uniqid() . '.json';
        $handle = fopen( $temp_path, 'w' );
        
        if ( ! $handle ) {
            throw new \Exception( 'Impossible de créer le fichier temporaire' );
        }
        
        // Écriture du début du JSON
        fwrite( $handle, "[\n" );
        
        $offset = 0;
        $first_chunk = true;
        
        while ( $offset < $total_logs ) {
            $sql = "SELECT * FROM {$this->table_name} {$sql_data['where_clause']} 
                   ORDER BY datetime DESC LIMIT %d OFFSET %d";
            
            $values = array_merge( $sql_data['where_values'], array( self::CHUNK_SIZE, $offset ) );
            $prepared_sql = $wpdb->prepare( $sql, $values );
            
            $chunk_logs = $wpdb->get_results( $prepared_sql, ARRAY_A );
            
            if ( empty( $chunk_logs ) ) {
                break;
            }
            
            // Formatage du chunk
            $formatted_chunk = $this->format_logs_for_llm( $chunk_logs );
            
            // Écriture du chunk
            foreach ( $formatted_chunk as $index => $log ) {
                if ( ! $first_chunk || $index > 0 ) {
                    fwrite( $handle, ",\n" );
                }
                fwrite( $handle, json_encode( $log, self::JSON_OPTIONS ) );
                $first_chunk = false;
            }
            
            $offset += self::CHUNK_SIZE;
        }
        
        // Fin du JSON
        fwrite( $handle, "\n]" );
        fclose( $handle );
        
        // Génération de l'URL de téléchargement
        $upload_dir = wp_upload_dir();
        $file_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $temp_path );
        
        return array(
            'success' => true,
            'download_url' => $file_url,
            'filename' => $filename,
            'logs_count' => $total_logs,
            'file_size' => filesize( $temp_path )
        );
    }
    
    /**
     * Formatage des logs pour LLM (structure optimisée)
     * @param array $logs Logs bruts
     * @return array Logs formatés
     */
    private function format_logs_for_llm( $logs ) {
        $formatted = array();
        
        foreach ( $logs as $log ) {
            // Décoder le contexte JSON s'il existe
            $context = null;
            if ( ! empty( $log['context'] ) ) {
                $decoded_context = json_decode( $log['context'], true );
                $context = $decoded_context ?: $log['context'];
            }
            
            // Structure optimisée pour LLM : une ligne par log avec propriétés structurées
            $formatted[] = array(
                'timestamp' => $log['datetime'],
                'level' => $log['level'],
                'source' => $log['source'] ?? 'general',
                'message' => $log['message'],
                'context' => $context,
                'user_id' => $log['user_id'] ? intval( $log['user_id'] ) : null
            );
        }
        
        return $formatted;
    }
    
    /**
     * Génération du nom de fichier selon les spécifications
     * @param array $params Paramètres d'export
     * @param int $total_logs Nombre de logs
     * @return string Nom du fichier
     */
    private function generate_filename( $params, $total_logs ) {
        $date = current_time( 'd-m-Y-H-i' );
        
        // Base du nom
        $filename = "tb-parrainage-logs-{$date}";
        
        // Ajout d'informations sur la plage temporelle
        if ( $params['type'] === 'duration' ) {
            $filename .= "-{$params['duration']}min";
        } else {
            $filename .= "-all";
        }
        
        // Ajout des filtres
        if ( $params['level'] !== 'ALL' ) {
            $filename .= "-" . strtolower( $params['level'] );
        }
        
        if ( $params['source'] !== 'ALL' ) {
            $filename .= "-" . sanitize_file_name( $params['source'] );
        }
        
        // Ajout du nombre de logs
        $filename .= "-{$total_logs}logs.json";
        
        return $filename;
    }
    
    /**
     * Création d'un fichier temporaire
     * @param string $filename Nom du fichier
     * @param string $content Contenu
     * @return array Informations du fichier
     */
    private function create_temp_file( $filename, $content ) {
        $upload_dir = wp_upload_dir();
        $temp_filename = self::TEMP_FILE_PREFIX . uniqid() . '.json';
        $temp_path = $upload_dir['basedir'] . '/' . $temp_filename;
        
        if ( file_put_contents( $temp_path, $content ) === false ) {
            throw new \Exception( 'Impossible de créer le fichier temporaire' );
        }
        
        $file_url = $upload_dir['baseurl'] . '/' . $temp_filename;
        
        return array(
            'path' => $temp_path,
            'url' => $file_url,
            'size' => filesize( $temp_path )
        );
    }
    
    /**
     * Preview des logs à exporter
     * @param array $params Paramètres validés
     * @return array Preview data
     */
    public function get_export_preview( $params ) {
        $sql_data = $this->build_export_query( $params );
        $total_logs = $this->count_logs( $sql_data['where_clause'], $sql_data['where_values'] );
        
        return array(
            'total_logs' => $total_logs,
            'estimated_size' => $this->estimate_file_size( $total_logs ),
            'time_range' => $this->get_time_range_info( $params )
        );
    }
    
    /**
     * Estimation de la taille du fichier
     * @param int $log_count Nombre de logs
     * @return string Taille estimée
     */
    private function estimate_file_size( $log_count ) {
        // Estimation basée sur ~500 bytes par log en moyenne
        $bytes = $log_count * 500;
        
        if ( $bytes < 1024 ) {
            return $bytes . ' B';
        } elseif ( $bytes < 1048576 ) {
            return round( $bytes / 1024, 1 ) . ' KB';
        } else {
            return round( $bytes / 1048576, 1 ) . ' MB';
        }
    }
    
    /**
     * Informations sur la plage temporelle
     * @param array $params Paramètres
     * @return string Info temporelle
     */
    private function get_time_range_info( $params ) {
        if ( $params['type'] === 'duration' ) {
            return sprintf( 
                __( '%d dernières minutes', 'wc-tb-web-parrainage' ), 
                $params['duration'] 
            );
        } else {
            return __( 'Tous les logs disponibles', 'wc-tb-web-parrainage' );
        }
    }
}
