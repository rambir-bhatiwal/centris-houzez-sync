<?php

/**
 * Plugin Name: Custom Centris Houzez Sync 
 * Description: Sync Centris SIIQ property listings into Houzez theme.
 * Version: 1.0.0
 * Author: Panch Ram
 */

// Exit if accessed directly
if (! defined('ABSPATH')) exit;

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
require_once CHS_PLUGIN_DIR . 'inc/utils/class-chs-utils.php';
require_once CHS_PLUGIN_DIR . 'inc/setting.php';
// Load services
require_once CHS_PLUGIN_DIR . 'inc/services/class-chs-file-detector.php';
require_once CHS_PLUGIN_DIR . 'inc/services/chs-column-inspector.php';
require_once CHS_PLUGIN_DIR . 'inc/services/chs-property-creator.php';
new CHS_ColumnInspector();
new CHS_Cron();

// Activation hook
function chs_activate()
{
    // e.g. create upload/logs folder if not exists
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/centris-sync/logs';
    if (! file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
}
register_activation_hook(__FILE__, 'chs_activate');


function chs_register_settings()
{
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
function chs_admin_assets()
{
    wp_enqueue_style('chs-admin-css', CHS_PLUGIN_URL . 'assets/admin.css');
}
add_action('admin_enqueue_scripts', 'chs_admin_assets');



/**
 * ✅ Admin menu wrapper (class-based conversion only)
 */
class CHS_Admin_Menu
{
    private $CHS_AdminDashboard;

    public function __construct()
    {
        $this->CHS_AdminDashboard = new CHS_AdminDashboard();

        add_action('admin_menu', [$this, 'chs_admin_menu']);

        add_filter('upload_mimes', function ($mimes) {
            $mimes['ashx'] = 'application/octet-stream'; // Allow .ashx
            $mimes['bin'] = 'application/octet-stream';
            $mimes['I'] = 'image/jpeg'; // if Centris uses `.I` as image type

            return $mimes;
        });

        add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
            // if (defined('DOING_CHS_IMPORT') && DOING_CHS_IMPORT === true) {
            return [
                'ext'             => pathinfo($filename, PATHINFO_EXTENSION),
                'type'            => 'application/octet-stream',
                'proper_filename' => $filename,
            ];
            // }

            // return $data;
        }, 10, 4);

        add_filter('wp_handle_upload_prefilter', function ($file) {
            $forbidden = ['php', 'php5', 'exe', 'sh', 'bat', 'js', 'pl'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $forbidden)) {
                return ['error' => 'This file type is not allowed for security reasons.'];
            }

            return $file;
        });
    }

    public function chs_admin_menu()
    {
        add_menu_page(
            'Centris Sync',
            'Centris Sync',
            'manage_options',
            'chs-admin',
            [$this->CHS_AdminDashboard, 'chs_admin_dashboard'],
            'dashicons-admin-home',
            50
        );
    }
}

new CHS_Admin_Menu();
if (is_admin()) {
    new CHS_Admin_Settings();
}
