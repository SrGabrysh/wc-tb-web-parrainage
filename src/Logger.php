<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tb_parrainage_logs';
        $this->maybe_create_table();
    }
    
    private function maybe_create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            datetime datetime NOT NULL,
            level varchar(10) NOT NULL,
            source varchar(50) NOT NULL DEFAULT 'general',
            message text NOT NULL,
            context longtext,
            user_id bigint(20),
            PRIMARY KEY (id),
            KEY datetime (datetime),
            KEY level (level),
            KEY source (source)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    public function info( $message, $context = array(), $source = 'general' ) {
        $this->log( 'INFO', $message, $context, $source );
    }
    
    public function error( $message, $context = array(), $source = 'general' ) {
        $this->log( 'ERROR', $message, $context, $source );
    }
    
    public function warning( $message, $context = array(), $source = 'general' ) {
        $this->log( 'WARNING', $message, $context, $source );
    }
    
    public function debug( $message, $context = array(), $source = 'general' ) {
        $this->log( 'DEBUG', $message, $context, $source );
    }
    
    private function log( $level, $message, $context, $source ) {
        global $wpdb;
        
        $data = array(
            'datetime' => current_time( 'mysql' ),
            'level' => $level,
            'source' => $source,
            'message' => $message,
            'context' => is_array( $context ) ? json_encode( $context ) : $context,
            'user_id' => get_current_user_id()
        );
        
        $wpdb->insert( $this->table_name, $data );
        
        // Aussi logger dans le système WordPress pour le debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[TB-Parrainage] [%s] [%s] %s', $level, $source, $message ) );
        }
    }
    
    public function get_recent_logs( $limit = 50, $level = null, $source = null ) {
        global $wpdb;
        
        $where_conditions = array();
        $where_values = array();
        
        if ( $level ) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $level;
        }
        
        if ( $source ) {
            $where_conditions[] = 'source = %s';
            $where_values[] = $source;
        }
        
        $where_clause = '';
        if ( ! empty( $where_conditions ) ) {
            $where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
        }
        
        $sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY datetime DESC LIMIT %d";
        $where_values[] = $limit;
        
        $prepared_sql = $wpdb->prepare( $sql, $where_values );
        $results = $wpdb->get_results( $prepared_sql, ARRAY_A );
        
        // Décoder le contexte JSON
        foreach ( $results as &$result ) {
            if ( ! empty( $result['context'] ) ) {
                $decoded_context = json_decode( $result['context'], true );
                $result['context'] = $decoded_context ?: $result['context'];
            }
        }
        
        return $results;
    }
    
    public function count_logs_by_source( $source, $days = 30 ) {
        global $wpdb;
        
        $date_limit = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE source = %s AND datetime >= %s",
            $source,
            $date_limit
        ) );
        
        return intval( $count );
    }
    
    public function cleanup_old_logs( $retention_days = 30 ) {
        global $wpdb;
        
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
        
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE datetime < %s",
            $cutoff_date
        ) );
        
        if ( $deleted ) {
            $this->info( 
                sprintf( 'Nettoyage automatique: %d logs supprimés', $deleted ), 
                array( 'cutoff_date' => $cutoff_date ),
                'maintenance'
            );
        }
        
        return $deleted;
    }
    
    public function clear_all_logs() {
        global $wpdb;
        
        $deleted = $wpdb->query( "DELETE FROM {$this->table_name}" );
        
        $this->info( 
            sprintf( 'Tous les logs ont été supprimés (%d entrées)', $deleted ),
            array(),
            'maintenance'
        );
        
        return $deleted;
    }
    
    public function get_logs_stats( $days = 30 ) {
        global $wpdb;
        
        $date_limit = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT level, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE datetime >= %s 
             GROUP BY level",
            $date_limit
        ), ARRAY_A );
        
        $result = array();
        foreach ( $stats as $stat ) {
            $result[ $stat['level'] ] = intval( $stat['count'] );
        }
        
        return $result;
    }
} 