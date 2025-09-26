<?php
if (!defined('ABSPATH')) exit;

/**
 * Class: CHS_Centris_Mapping
 * 
 * Centralized field mapping for all Centris feed files.
 * Provides easy access to numeric-indexed mappings with meta_keys.
 *
 * Centralized field mapping between Centris feed columns and Houzez meta/taxonomies.
 * 
 * Each file is mapped using numeric indexes (0,1,2…), with comments for clarity.
 * Empty or unknown columns have unique placeholder keys to prevent data override.
 * 
 * Note: each new file fist key must be _centris_mls to make it unique
 *
 */


class CHS_Centris_Mapping
{

    /**
     * @var array Complete mapping array
     */
    private static $mapping = [

        'ADDENDA.TXT' => [
            '0' => '_centris_mls',                     // num_inscription
            '6' => '_houzez_property_des_fr',          // description_fr
            '1' => '_centris_addenda_1',
            '2' => '_centris_addenda_2',
            '3' => '_centris_addenda_3',
            '4' => '_centris_addenda_4',
            '5' => '_centris_addenda_5',
        ],

        'BUREAUX.TXT' => [
            '0' => '_centris_mls',                     // num_inscription
            '1' => '_houzez_property_broker_name',     // broker name
            '2' => '_houzez_property_broker_license',  // broker license
            '3' => '_houzez_property_address',         // street number
            '4' => '_houzez_property_address',         // street name
            '5' => '_houzez_property_city',            // city
            '6' => '_houzez_property_state',           // province
            '7' => '_houzez_property_zip',             // postal code
            '8' => '_houzez_property_country',         // country
            '9' => '_centris_bureaux_9',
            '10' => '_centris_bureaux_10',
            '11' => '_centris_bureaux_11',
            '12' => '_centris_bureaux_12',
            '13' => '_houzez_property_phone',           // phone
            '14' => '_houzez_property_email',           // email
            '15' => '_houzez_property_website',         // website
            '16' => '_centris_bureaux_16',
            '17' => '_houzez_property_source_id',       // source ID
            '18' => '_centris_bureaux_18',
        ],

        'CARACTERISTIQUES.TXT' => [
            '0' => '_centris_mls',
            '1' => 'houzez_property_features',
            '2' => 'houzez_property_features_1',
            '3' => 'houzez_property_features_2',
            '4' => 'houzez_property_features_3',
            '5' => '_centris_caracteristiques_5',
            '6' => '_centris_caracteristiques_6',
        ],

        'DEPENSES.TXT' => [
            '0' => '_centris_mls',
            '2' => '_houzez_property_price',
            '5' => '_houzez_property_price_postfix',
            '1' => '_centris_depenses_1',
            '3' => '_centris_depenses_3',
            '4' => '_centris_depenses_4',
            '6' => '_centris_depenses_6',
            '7' => '_centris_depenses_7',
        ],

        'FIRMES.TXT' => [
            '0' => '_centris_mls',
            '1' => '_houzez_property_broker_name',
            '2' => '_houzez_property_broker_license',
            '3' => '_centris_firmes_3',
            '4' => '_centris_firmes_4',
            '5' => '_centris_firmes_5',
            '6' => '_houzez_property_source_id',
        ],

        'INSCRIPTIONS.TXT' => [
            '0' => '_centris_mls',
            '2' => '_houzez_property_city',
            '3' => '_centris_mls_code',
            '4' => '_houzez_property_address',
            '5' => '_houzez_property_address',
            '9' => '_houzez_property_price',
            '11' => '_houzez_property_country',
            '14' => '_centris_inscriptions_14',
            '15' => '_centris_inscriptions_15',
            '19' => '_centris_inscriptions_19',
            '20' => '_centris_inscriptions_20',
            '21' => '_centris_inscriptions_21',
            '22' => '_centris_inscriptions_22',
            '23' => '_centris_inscriptions_23',
            '30' => '_houzez_property_bedrooms',
            '31' => '_houzez_property_bathrooms',
            '35' => '_centris_inscriptions_35',
            '36' => '_centris_inscriptions_36',
            '119' => '_centris_inscriptions_119',
            '156' => '_centris_inscriptions_156',
            '18141' => '_centris_inscriptions_18141',
            '8' => '_centris_inscriptions_8',
            '45' => '_houzez_property_map_lat',
            '46' => '_houzez_property_map_lng',
            '47' => '_centris_inscriptions_47',
            '48' => '_centris_inscriptions_48',
            '49' => '_centris_inscriptions_49',
            '50' => '_centris_inscriptions_50',
            '51' => '_centris_inscriptions_51',
        ],

        'LIENS_ADDITIONNELS.TXT' => [
            '0' => '_centris_mls',
            '3' => '_houzez_property_video_url',
            '4' => '_houzez_property_video_url_1',
            '1' => '_centris_liens_1',
            '2' => '_centris_liens_2',
        ],

        'MEMBRES.TXT' => [
            '0' => '_centris_mls',
            '1' => '_houzez_property_city',
            '4' => '_houzez_property_address',
            '5' => '_houzez_property_address',
            '8' => '_houzez_property_zip',
            // '1' => '_centris_membres_1',
            '2' => '_centris_membres_2',
            '3' => '_centris_membres_3',
            '6' => '_centris_membres_6',
            '7' => '_centris_membres_7',
            '9' => '_centris_membres_9',
            '10' => '_centris_membres_10',
            '11' => '_centris_membres_11',
            '12' => '_centris_membres_12',
            '13' => '_centris_membres_13',
            '14' => '_centris_membres_14',
            '15' => '_centris_membres_15',
            '16' => '_centris_membres_16',
            '17' => '_centris_membres_17',
            '18' => '_centris_membres_18',
        ],

        'MEMBRES_MEDIAS_SOCIAUX.TXT' => [
            '0' => '_centris_mls',
        ],

        'PHOTOS.TXT' => [
            '0' => '_centris_mls',
            '1' => '_houzez_media_order',
            '6' => '_houzez_property_images',
            '8' => '_houzez_media_timestamp',
            '2' => '_centris_photos_2',
            '3' => '_centris_photos_3',
            '4' => '_centris_photos_4',
            '5' => '_centris_photos_5',
            '7' => '_centris_photos_7',
            '9' => '_centris_photos_9',
        ],

        'PIECES_UNITES.TXT' => [
            '0' => '_centris_mls',
            '9' => '_houzez_property_size',
            '11' => '_houzez_property_size_unit',
            '1' => '_centris_pieces_1',
            '2' => '_centris_pieces_2',
            '3' => '_centris_pieces_3',
            '4' => '_centris_pieces_4',
            '5' => '_centris_pieces_5',
            '6' => '_centris_pieces_6',
            '7' => '_centris_pieces_7',
            '8' => '_centris_pieces_8',
            '10' => '_centris_pieces_10',
            '12' => '_centris_pieces_12',
            '13' => '_centris_pieces_13',
        ],

        'REMARQUES.TXT' => [
            '0' => '_centris_mls',
            '6' => '_houzez_property_des_fr',
            '1' => '_centris_remarques_1',
            '2' => '_centris_remarques_2',
            '3' => '_centris_remarques_3',
            '4' => '_centris_remarques_4',
            '5' => '_centris_remarques_5',
        ],

        'RENOVATIONS.TXT' => [
            '0' => '_centris_mls',
            '6' => '_houzez_property_des_fr',
            '1' => '_centris_renovations_1',
            '2' => '_centris_renovations_2',
            '3' => '_centris_renovations_3',
            '4' => '_centris_renovations_4',
            '5' => '_centris_renovations_5',
        ],

        'UNITES_DETAILLEES.TXT' => [
            '0' => '_centris_mls',
            '3' => '_houzez_property_bedrooms',
            '4' => '_houzez_property_bathrooms',
            '1' => '_centris_unites_detaillees_1',
            '2' => '_centris_unites_detaillees_2',
            '5' => '_centris_unites_detaillees_5',
            '6' => '_centris_unites_detaillees_6',
            '7' => '_centris_unites_detaillees_7',
            '8' => '_centris_unites_detaillees_8',
            '9' => '_centris_unites_detaillees_9',
            '10' => '_centris_unites_detaillees_10',
            '11' => '_centris_unites_detaillees_11',
            '12' => '_centris_unites_detaillees_12',
            '13' => '_centris_unites_detaillees_13',
            '14' => '_centris_unites_detaillees_14',
            '15' => '_centris_unites_detaillees_15',
            '16' => '_centris_unites_detaillees_16',
            '17' => '_centris_unites_detaillees_17',
            '18' => '_centris_unites_detaillees_18',
            '19' => '_centris_unites_detaillees_19',
            '20' => '_centris_unites_detaillees_20',
        ],

        'UNITES_SOMMAIRES.TXT' => [
            '0' => '_centris_mls',
        ],

        'VISITES_LIBRES.TXT' => [
            '0' => '_centris_mls',
        ],

    ];

    /**
     * Get mapping array for a specific file
     * 
     * @param string $file_name
     * @return array
     */
    public static function getMapping($file_name)
    {
        return isset(self::$mapping[$file_name]) ? self::$mapping[$file_name] : [];
    }

    /**
     * Get all mappings
     * 
     * @return array
     */
    public static function getAllMappings()
    {
        return self::$mapping;
    }

    /**
     * Map a single row of data based on file mapping
     * 
     * @param array $row       Associative array of data (column index => value)
     * @param string $filename Name of the source file (e.g. 'INSCRIPTIONS.TXT')
     * @return array           Mapped associative array (meta_key => value)
     */
    public static function getMappedData(array $row, string $filename): array
    {
        $result = [];

        try {
            if (!isset(self::$mapping[$filename])) {
                CHS_Logger::log("No mapping found for file: $filename");
                return $result;
            }

            foreach (self::$mapping[$filename] as $index => $meta_key) {
                // Support numeric and associative indexes

                // echo "<br>chhh Index: " . $index . " for meta_key: " . $meta_key . "<br> data:- ".$row[$index] ?? 'N/A';


                if (isset($row[$index])) {
                    $value = $row[$index];
                    $result[$meta_key] = $value;
                } else {
                    $result[$meta_key] = ''; // set empty if index not found    
                }
            }
        } catch (\Throwable $e) {
            CHS_Logger::logs("Error mapping data for file {$filename}: " . $e->getMessage());
        }

        return $result;
    }

    public static function getMappedDataSingle(array $row, string $filename): array
    {
        $result = [];
        try {
            if (!isset(self::$mapping[$filename])) {
                CHS_Logger::log("No mapping found for file: $filename");
                return $result;
            }

            foreach (self::$mapping[$filename] as $index => $meta_key) {
                // Support numeric and associative indexes

                // echo "<br>chhh Index: " . $index . " for meta_key: " . $meta_key . "<br> data:- ".$row[$index] ?? 'N/A';


                if (isset($row[$index])) {
                    $value = $row[$index];
                    $result[$meta_key] = $value;
                } else {
                    $result[$meta_key] = ''; // set empty if index not found    
                }
            }
        } catch (\Throwable $e) {
            CHS_Logger::logs("Error mapping data for file {$filename}: " . $e->getMessage());
        }

        return $result;
    }
}
