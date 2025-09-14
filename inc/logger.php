<?php
if (!defined('ABSPATH')) exit;

require_once CHS_PLUGIN_DIR . 'inc/constants.php';

class CHS_Logger {

    private static $instance = null;
    private $log_file;
    private $log_dir;

    private function __construct() {
        
        $this->log_file = CHS_LOG_FILE;
        $this->log_dir  = CHS_LOG_DIR; /// dirname($this->log_file);

        if (!file_exists($this->log_file)) {
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
        $log_file = $logger->log_file;
        $formatted = date('Y-m-d H:i:s') . " - " . $message . "\n";
        file_put_contents($log_file, $formatted, FILE_APPEND);
    }

    /**
     * Clear all log files
     */

    public static function clear_logs() {
        $logger = self::instance();
        $file = glob($logger->log_file);
        unlink($file);
    }
}