<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Detect local environment
$localDomains = ['localhost', '127.0.0.1', '::1', 'dev.yoursite.com']; // Add your dev domains

// if (in_array($_SERVER['SERVER_NAME'], $localDomains)) {
//     define('CHS_DEV_MODE', true);
// } else {
//     define('CHS_DEV_MODE', false);
// }
define('CHS_DEV_MODE', true); //TODO this is hardcoded due to development process otherwise it will be removed and above code will be enabled/uncommented 

// define plugin version
define('CHS_PLUGIN_VERSION', '1.0.0');

// define('CHS_PLUGIN_DIR', plugin_dir_path(__FILE__) . '../');
define('CHS_PLUGIN_URL', plugin_dir_url(__FILE__) . '../');

// Get WordPress uploads directory dynamically
$upload_dir = wp_upload_dir(); // returns array with 'basedir' and 'baseurl'

// Base directory for plugin files inside uploads
define('CHS_BASE_DIR', rtrim($upload_dir['basedir'], '/\\') . '/centris-sync/');
define('CHS_BASE_URL', rtrim($upload_dir['baseurl'], '/\\') . '/centris-sync/');

// Subdirectories
define('CHS_ZIP_DIR', CHS_BASE_DIR . 'zips/');
define('CHS_LOG_DIR', CHS_BASE_DIR . 'logs/');

// Default log file
define('CHS_LOG_FILE', CHS_LOG_DIR . 'centris-sync.log');
define('CHS_ERROR_LOG_FILE', CHS_LOG_DIR . 'centris-sync-error.log');

// Source path (default)
define('CHS_SOURCE_PATH', get_option('chs_source_path', '/home/vitev704/centris'));
define('CHS_FILE_PATTERN', get_option('chs_file_pattern', 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP'));

// Toronto timezone
date_default_timezone_set('America/Toronto');
define('CHS_TIMEZONE', 'America/Toronto');

// Clear logs on each sync (can be disabled for debugging)
define('CHS_CLEAR_LOGS_ON_SYNC', true); //TODO this is hardcoded due to development process otherwise it will be removed and above code will be enabled/uncommented 

// post type for properties
define('CHS_PROPERTY_POST_TYPE', 'property');

// Houzez meta keys for photos
define('CHS_HOUZEZ_PHOTO_META_KEY', '_centris_image_signatures');

// lock file to prevent concurrent syncs
define('CHS_LOCK_FILE', CHS_BASE_DIR . 'sync.lock');