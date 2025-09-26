<?php
if (!defined('ABSPATH')) exit;

require_once CHS_PLUGIN_DIR . 'inc/constants.php';

class CHS_Logger
{

    private static $instance = null;
    private $log_file;
    private $log_dir;
    private $error_log_file;

    private function __construct()
    {

        $this->log_file = CHS_LOG_FILE;
        $this->log_dir  = CHS_LOG_DIR; /// dirname($this->log_file);
        $this->error_log_file = CHS_ERROR_LOG_FILE;
        // if (!file_exists($this->log_file)) {
        //     wp_mkdir_p(target: $this->log_dir);
        //         touch($this->error_log_file);

        //     @chmod($this->log_file, 0644);
        // }
        // if (!file_exists($this->error_log_file)) {
        //     wp_mkdir_p($this->error_log_file);
        //     touch($this->error_log_file);

        //     @chmod($this->error_log_file, 0644);

        // }

        // Ensure log directory exists
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            @chmod($this->log_dir, 0755);
        }

        // Ensure main log file exists
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
            @chmod($this->log_file, 0644);
        }

        // Ensure error log file exists
        if (!file_exists($this->error_log_file)) {
            touch($this->error_log_file);
            @chmod($this->error_log_file, 0644);
        }
    }

    /**
     * Singleton pattern - return only one instance
     */
    private static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Public static log function (use anywhere in project)
     */
    public static function log($message)
    {
        $logger = self::instance();
        $log_file = $logger->log_file;
        $formatted = date('Y-m-d H:i:s') . " - " . $message . "\n";
        file_put_contents($log_file, $formatted, FILE_APPEND);
    }

    /**
     * Clear all log files
     */

    public static function clear_logs()
    {
        $logger = self::instance();
        $file = glob($logger->log_file);
        unlink($file);
    }

    public static function logs($message)
    {
        self::log($message);
        self::error_log($message);
    }

    /**
     * Public static log error function (use anywhere in project)
     */

    public static function error_log($message)
    {
        $logger = self::instance();
        $error_log_file = $logger->error_log_file;
        $formatted = date('Y-m-d H:i:s') . " - " . $message . "\n";
        file_put_contents($error_log_file, $formatted, FILE_APPEND);
    }

    /**
     * Clear all error log files
     */

    public static function clear_error_logs()
    {
        $logger = self::instance();
        $file = glob($logger->error_log_file);
        unlink($file);
    }

    /**
     * Read all error
     */
    public function getErrors()
    {
        $file = $this->error_log_file;
        if (!file_exists($file)) return '';
        return file_get_contents($file);
    }
}
