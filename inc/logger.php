<?php
if (!defined('ABSPATH')) exit;

// function chs_log($message) {
//     $upload_dir = wp_upload_dir();
//     $log_dir = $upload_dir['basedir'] . '/centris-sync/logs/';

//     if (!file_exists($log_dir)) {
//         wp_mkdir_p($log_dir);
//     }

//     $log_file = $log_dir . 'log-' . date('Y-m-d') . '.txt';
//     file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
// }



class CHS_Logger {

    private static $instance = null;
    private $log_dir;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/centris-sync/logs/';

        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }

    /**
     * Singleton pattern - return only one instance
     */
    private static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Public static log function (use anywhere in project)
     */
    public static function log($message) {
        $logger = self::instance();
        $log_file = $logger->log_dir . 'log-' . date('Y-m-d') . '.txt';
        $formatted = date('Y-m-d H:i:s') . " - " . $message . "\n";
        file_put_contents($log_file, $formatted, FILE_APPEND);
    }
}