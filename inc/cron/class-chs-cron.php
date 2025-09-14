<?php
if (!defined('ABSPATH')) exit;
require_once CHS_PLUGIN_DIR . 'inc/constants.php';
class CHS_Cron {

    public function __construct() {
        // Hook to schedule cron on plugin load
        add_action('init', [$this, 'schedule_cron_events']);

        // Hook to our custom cron action
        add_action('chs_run_source_scan', [$this, 'run_source_scan']);
    }

    /**
     * Schedule cron events using the times saved in admin settings
     */
    public function schedule_cron_events() {
        $cronMorning = get_option('chs_cron_morning', '06:45');
        $cronEvening = get_option('chs_cron_evening', '18:45');

        $tz = new DateTimeZone('America/Toronto');

        // Schedule Morning cron
        if ($cronMorning) {
            $morningDT = DateTime::createFromFormat('H:i', $cronMorning, $tz);
            $morningTS = $morningDT->getTimestamp();
            if (!wp_next_scheduled('chs_run_source_scan_morning')) {
                wp_schedule_event($morningTS, 'daily', 'chs_run_source_scan_morning');
            }
            add_action('chs_run_source_scan_morning', [$this, 'run_source_scan']);
        }

        // Schedule Evening cron
        if ($cronEvening) {
            $eveningDT = DateTime::createFromFormat('H:i', $cronEvening, $tz);
            $eveningTS = $eveningDT->getTimestamp();
            if (!wp_next_scheduled('chs_run_source_scan_evening')) {
                wp_schedule_event($eveningTS, 'daily', 'chs_run_source_scan_evening');
            }
            add_action('chs_run_source_scan_evening', [$this, 'run_source_scan']);
        }
    }

    /**
     * The main function called by cron or manual scan
     */
    public function run_source_scan() {
        $sourcePath  = CHS_SOURCE_PATH;//get_option('chs_source_path', '/home/vitev704/centris');
        $filePattern = CHS_FILE_PATTERN; ///get_option('chs_file_pattern', 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP');

        // 1. Detect the latest files and log them
        $files = CHS_FileDetector::detect($sourcePath, $filePattern, 5);

        // 2. Call the main sync function for parsing / processing
        chs_sync_properties();

        // 3. Log summary
        CHS_Logger::log('[cron] Source scan & sync executed. Detected ' . count($files) . ' files.');
    }
}
