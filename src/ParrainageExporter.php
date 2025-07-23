<?php
namespace TBWeb\WCParrainage;

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParrainageExporter {
    
    // Constantes
    const EXPORT_FORMAT_CSV = 'csv';
    const EXPORT_FORMAT_EXCEL = 'excel';
    const CSV_DELIMITER = ',';
    const CSV_ENCLOSURE = '"';
    const CSV_ESCAPE = '"';
    
    private $logger;
    
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Exporter vers CSV
     */
    public function export_to_csv( $data, $filename = null ) {
        if ( empty( $data['parrains'] ) ) {
            return new \WP_Error( 'no_data', 'Aucune donnée à exporter' );
        }
        
        $filename = $filename ?: $this->generate_filename( self::EXPORT_FORMAT_CSV );
        
        // Headers pour le téléchargement
        $this->set_download_headers( $filename, 'text/csv' );
        
        // Ouvrir le flux de sortie
        $output = fopen( 'php://output', 'w' );
        
        // Headers CSV
        $headers = array(
            'Parrain (Nom)',
            'Parrain (Email)', 
            'Parrain (ID Abonnement)',
            'Filleul (Nom)',
            'Filleul (Email)',
            'Date Parrainage',
            'Produit(s)',
            'Avantage',
            'Statut Abonnement',
            'Montant',
            'Devise'
        );
        
        fputcsv( $output, $headers, self::CSV_DELIMITER, self::CSV_ENCLOSURE, self::CSV_ESCAPE );
        
        // Données
        $export_count = 0;
        foreach ( $data['parrains'] as $parrain_data ) {
            foreach ( $parrain_data['filleuls'] as $filleul ) {
                $row = $this->format_csv_row( $parrain_data['parrain'], $filleul );
                fputcsv( $output, $row, self::CSV_DELIMITER, self::CSV_ENCLOSURE, self::CSV_ESCAPE );
                $export_count++;
                
                // Limite de sécurité
                if ( $export_count >= WC_TB_PARRAINAGE_MAX_EXPORT ) {
                    break 2;
                }
            }
        }
        
        fclose( $output );
        
        // Log de l'export
        $this->logger->info( 
            sprintf( 'Export CSV généré - %d lignes exportées', $export_count ),
            array( 
                'filename' => $filename,
                'format' => self::EXPORT_FORMAT_CSV,
                'rows_exported' => $export_count
            ),
            'parrainage-exporter'
        );
        
        return true;
    }
    
    /**
     * Exporter vers Excel (format CSV amélioré)
     */
    public function export_to_excel( $data, $filename = null ) {
        if ( empty( $data['parrains'] ) ) {
            return new \WP_Error( 'no_data', 'Aucune donnée à exporter' );
        }
        
        $filename = $filename ?: $this->generate_filename( self::EXPORT_FORMAT_EXCEL );
        
        // Headers pour le téléchargement Excel
        $this->set_download_headers( $filename, 'application/vnd.ms-excel' );
        
        // Commencer le document Excel (format HTML basique compatible Excel)
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Worksheet ss:Name="Parrainages">' . "\n";
        echo '<Table>' . "\n";
        
        // Headers avec style
        echo '<Row ss:StyleID="Header">' . "\n";
        $headers = array(
            'Parrain (Nom)',
            'Parrain (Email)', 
            'Parrain (ID Abonnement)',
            'Filleul (Nom)',
            'Filleul (Email)',
            'Date Parrainage',
            'Produit(s)',
            'Avantage',
            'Statut Abonnement',
            'Montant',
            'Devise'
        );
        
        foreach ( $headers as $header ) {
            echo '<Cell><Data ss:Type="String">' . esc_html( $header ) . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
        
        // Données
        $export_count = 0;
        foreach ( $data['parrains'] as $parrain_data ) {
            foreach ( $parrain_data['filleuls'] as $filleul ) {
                echo '<Row>' . "\n";
                $row = $this->format_excel_row( $parrain_data['parrain'], $filleul );
                
                foreach ( $row as $cell ) {
                    $type = is_numeric( $cell ) ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="' . $type . '">' . esc_html( $cell ) . '</Data></Cell>' . "\n";
                }
                
                echo '</Row>' . "\n";
                $export_count++;
                
                // Limite de sécurité
                if ( $export_count >= WC_TB_PARRAINAGE_MAX_EXPORT ) {
                    break 2;
                }
            }
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        
        // Feuille de statistiques
        $this->add_stats_worksheet( $data );
        
        echo '</Workbook>' . "\n";
        
        // Log de l'export
        $this->logger->info( 
            sprintf( 'Export Excel généré - %d lignes exportées', $export_count ),
            array( 
                'filename' => $filename,
                'format' => self::EXPORT_FORMAT_EXCEL,
                'rows_exported' => $export_count
            ),
            'parrainage-exporter'
        );
        
        return true;
    }
    
    /**
     * Formater une ligne CSV
     */
    private function format_csv_row( $parrain, $filleul ) {
        $produits = array();
        if ( ! empty( $filleul['produit_info'] ) ) {
            foreach ( $filleul['produit_info'] as $produit ) {
                $produits[] = $produit['name'] . ' (x' . $produit['quantity'] . ')';
            }
        }
        
        return array(
            $parrain['nom'],
            $parrain['email'],
            $parrain['subscription_id'],
            $filleul['nom'],
            $filleul['email'],
            $filleul['date_parrainage_formatted'],
            implode( '; ', $produits ),
            $filleul['avantage'],
            $filleul['subscription_info']['status_label'] ?? 'Non défini',
            $filleul['montant'],
            $filleul['devise']
        );
    }
    
    /**
     * Formater une ligne Excel
     */
    private function format_excel_row( $parrain, $filleul ) {
        $produits = array();
        if ( ! empty( $filleul['produit_info'] ) ) {
            foreach ( $filleul['produit_info'] as $produit ) {
                $produits[] = $produit['name'] . ' (x' . $produit['quantity'] . ')';
            }
        }
        
        return array(
            $parrain['nom'],
            $parrain['email'],
            $parrain['subscription_id'],
            $filleul['nom'],
            $filleul['email'],
            $filleul['date_parrainage_formatted'],
            implode( '; ', $produits ),
            $filleul['avantage'],
            $filleul['subscription_info']['status_label'] ?? 'Non défini',
            $filleul['montant'],
            $filleul['devise']
        );
    }
    
    /**
     * Ajouter une feuille de statistiques pour Excel
     */
    private function add_stats_worksheet( $data ) {
        echo '<Worksheet ss:Name="Statistiques">' . "\n";
        echo '<Table>' . "\n";
        
        // Calculer les statistiques
        $stats = $this->calculate_stats( $data );
        
        // Headers
        echo '<Row ss:StyleID="Header">' . "\n";
        echo '<Cell><Data ss:Type="String">Statistique</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">Valeur</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
        
        // Données statistiques
        foreach ( $stats as $label => $value ) {
            echo '<Row>' . "\n";
            echo '<Cell><Data ss:Type="String">' . esc_html( $label ) . '</Data></Cell>' . "\n";
            echo '<Cell><Data ss:Type="String">' . esc_html( $value ) . '</Data></Cell>' . "\n";
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
    }
    
    /**
     * Calculer les statistiques des données
     */
    private function calculate_stats( $data ) {
        $total_parrains = count( $data['parrains'] );
        $total_filleuls = 0;
        $total_montant = 0;
        $statuts = array();
        
        foreach ( $data['parrains'] as $parrain_data ) {
            $filleuls_count = count( $parrain_data['filleuls'] );
            $total_filleuls += $filleuls_count;
            
            foreach ( $parrain_data['filleuls'] as $filleul ) {
                $total_montant += $filleul['montant'];
                
                $statut = $filleul['subscription_info']['status'] ?? 'non-defini';
                $statuts[ $statut ] = ( $statuts[ $statut ] ?? 0 ) + 1;
            }
        }
        
        $stats = array(
            'Nombre total de parrains' => $total_parrains,
            'Nombre total de filleuls' => $total_filleuls,
            'Moyenne filleuls par parrain' => $total_parrains > 0 ? round( $total_filleuls / $total_parrains, 2 ) : 0,
            'Montant total des commandes' => wc_price( $total_montant ),
            'Montant moyen par filleul' => $total_filleuls > 0 ? wc_price( $total_montant / $total_filleuls ) : wc_price( 0 )
        );
        
        // Ajouter la répartition par statut
        foreach ( $statuts as $statut => $count ) {
            $stats[ 'Abonnements ' . $statut ] = $count;
        }
        
        return $stats;
    }
    
    /**
     * Définir les headers HTTP pour le téléchargement
     */
    private function set_download_headers( $filename, $content_type ) {
        // Nettoyer les buffers de sortie
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        
        // Headers de téléchargement
        header( 'Content-Type: ' . $content_type . '; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Pragma: public' );
        header( 'Expires: 0' );
        
        // BOM UTF-8 pour Excel
        if ( $content_type === 'text/csv' ) {
            echo "\xEF\xBB\xBF";
        }
    }
    
    /**
     * Générer un nom de fichier unique
     */
    private function generate_filename( $format ) {
        $date = current_time( 'Y-m-d_H-i-s' );
        $extension = ( $format === self::EXPORT_FORMAT_CSV ) ? 'csv' : 'xls';
        
        return sprintf( 'parrainages_%s.%s', $date, $extension );
    }
    
    /**
     * Sanitiser les données pour l'export
     */
    private function sanitize_export_data( $data ) {
        // Supprimer les données sensibles ou inutiles pour l'export
        $sanitized = $data;
        
        foreach ( $sanitized['parrains'] as &$parrain_data ) {
            // Supprimer les liens admin (inutiles dans l'export)
            unset( $parrain_data['parrain']['user_link'] );
            unset( $parrain_data['parrain']['subscription_link'] );
            
            foreach ( $parrain_data['filleuls'] as &$filleul ) {
                unset( $filleul['user_link'] );
                unset( $filleul['order_link'] );
                
                if ( isset( $filleul['subscription_info'] ) ) {
                    unset( $filleul['subscription_info']['link'] );
                }
                
                if ( isset( $filleul['produit_info'] ) ) {
                    foreach ( $filleul['produit_info'] as &$produit ) {
                        unset( $produit['link'] );
                    }
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Valider les données avant export
     */
    private function validate_export_data( $data ) {
        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'invalid_data_format', 'Format de données invalide' );
        }
        
        if ( empty( $data['parrains'] ) ) {
            return new \WP_Error( 'no_parrains', 'Aucun parrain trouvé dans les données' );
        }
        
        return true;
    }
    
    /**
     * Compter le nombre total de lignes à exporter
     */
    public function count_export_rows( $data ) {
        $total = 0;
        
        if ( ! empty( $data['parrains'] ) ) {
            foreach ( $data['parrains'] as $parrain_data ) {
                $total += count( $parrain_data['filleuls'] );
            }
        }
        
        return $total;
    }
    
    /**
     * Exporter avec validation et gestion d'erreurs
     */
    public function safe_export( $data, $format, $filename = null ) {
        // Validation
        $validation = $this->validate_export_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Vérifier la limite d'export
        $row_count = $this->count_export_rows( $data );
        if ( $row_count > WC_TB_PARRAINAGE_MAX_EXPORT ) {
            return new \WP_Error( 
                'export_limit_exceeded', 
                sprintf( 'Trop d\'enregistrements à exporter (%d). Limite : %d', $row_count, WC_TB_PARRAINAGE_MAX_EXPORT )
            );
        }
        
        // Sanitiser les données
        $sanitized_data = $this->sanitize_export_data( $data );
        
        // Exporter selon le format
        try {
            switch ( $format ) {
                case self::EXPORT_FORMAT_CSV:
                    return $this->export_to_csv( $sanitized_data, $filename );
                
                case self::EXPORT_FORMAT_EXCEL:
                    return $this->export_to_excel( $sanitized_data, $filename );
                    
                default:
                    return new \WP_Error( 'unsupported_format', 'Format d\'export non supporté' );
            }
        } catch ( \Exception $e ) {
            $this->logger->error( 
                'Erreur lors de l\'export',
                array( 'error' => $e->getMessage(), 'format' => $format ),
                'parrainage-exporter'
            );
            
            return new \WP_Error( 'export_failed', 'Erreur lors de l\'export : ' . $e->getMessage() );
        }
    }
} 