<?php
/**
 * Plugin Name: Custom Centris Houzez Sync 
 * Description: Sync Centris SIIQ property listings into Houzez theme.
 * Version: 1.0.0
 * Author: Panch Ram
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants
define('CHS_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include core files/
require_once CHS_PLUGIN_DIR . 'inc/constants.php';
require_once CHS_PLUGIN_DIR . 'inc/sync.php';
require_once CHS_PLUGIN_DIR . 'inc/photos.php';
require_once CHS_PLUGIN_DIR . 'inc/mapping.php';
require_once CHS_PLUGIN_DIR . 'inc/admin.php';
require_once CHS_PLUGIN_DIR . 'inc/logger.php';
require_once CHS_PLUGIN_DIR . 'inc/zip-parser.php';
require_once CHS_PLUGIN_DIR . 'inc/cron/class-chs-cron.php';

// Load services
require_once CHS_PLUGIN_DIR . 'inc/services/class-chs-file-detector.php';
new CHS_Cron();

// Activation hook
function chs_activate() {
    // e.g. create upload/logs folder if not exists
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/centris-sync/logs';
    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }
}
register_activation_hook( __FILE__, 'chs_activate' );


function chs_register_settings() {
    register_setting('chs_settings_group', 'chs_source_path');
    register_setting('chs_settings_group', 'chs_file_pattern');
    register_setting('chs_settings_group', 'chs_cron_morning');
    register_setting('chs_settings_group', 'chs_cron_evening');
}
add_action('admin_init', 'chs_register_settings');

// Check Houzez theme is active (optional guard).

// register_activation_hook( __FILE__, function() {
//     if ( ! function_exists( 'houzez_get_property_id' ) ) {
//         deactivate_plugins( plugin_basename( __FILE__ ) );
//         wp_die('Houzez theme required for Centris-Houzez Sync plugin.');
//     }
// });





// Load admin CSS
function chs_admin_assets() {
    wp_enqueue_style('chs-admin-css', CHS_PLUGIN_URL . 'assets/admin.css');
}
add_action('admin_enqueue_scripts', 'chs_admin_assets');

// Admin menu hook
function chs_admin_menu() {
    add_menu_page(
        'Centris Sync',
        'Centris Sync',
        'manage_options',
        'chs-admin',
        'chs_admin_dashboard',
        'dashicons-admin-home',
        50
    );
}
add_action('admin_menu', 'chs_admin_menu');