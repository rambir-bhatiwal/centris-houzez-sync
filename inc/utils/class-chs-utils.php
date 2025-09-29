<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once CHS_PLUGIN_DIR . 'templates/emails/summary-email.php';
require_once CHS_PLUGIN_DIR . 'templates/emails/error-email.php';
class CHS_Utils {

    /**
     * Recursively remove all contents of a folder but keep the folder itself
     */
    public static function clearFolder( $dir ) {
        if ( ! is_dir( $dir ) ) return;

        $it = new DirectoryIterator( $dir );
        foreach ( $it as $item ) {
            if ( $item->isDot() ) continue;
            $path = $item->getRealPath();
            if ( $item->isDir() ) {
                self::recursiveRemoveDir( $path );
            } else {
                @unlink( $path );
            }
        }
    }

    /**
     * Recursive remove directory completely (helper)
     */
    public static function recursiveRemoveDir( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $it as $item ) {
            if ( $item->isDir() ) @rmdir( $item->getRealPath() ); else @unlink( $item->getRealPath() );
        }
        @rmdir( $dir );
    }

    /**
     * Email send option handler
     */

    public static function handleSendEmail(): bool {
        $sendMode = get_option('chs_send_mode', 'always'); // default to 'always'
        if ($sendMode === 'no' || $sendMode === 'disabled') {
            return false; // Do not send any emails
        }
        return true;
    }

    public static function sendEmail(): bool {
        $recipients     = sanitize_text_field(get_option('chs_recipients', ''));
        $subjectPrefix  = sanitize_text_field(get_option('chs_subject_prefix', '[Centris Sync]'));
        try{
        $CHS_SummaryMail = new CHS_SummaryMail($recipients, $subjectPrefix);
        $CHS_ErrorMail = new CHS_ErrorMail($recipients, $subjectPrefix);
        $sendMode = get_option('chs_send_mode', 'always'); // default to 'always'
        if ($sendMode === 'no' || $sendMode === 'disabled') {
            return false; // Do not send any emails
        }elseif ($sendMode === 'always') {
            # Send all emails
            $CHS_SummaryMail->send();
            $CHS_ErrorMail->send();
        }elseif ($sendMode === 'changes') {
            $CHS_SummaryMail->send();
            # Send only if there are changes - logic to be implemented
        }elseif ($sendMode === 'error') {
            # Send only on error - logic to be implemented
            $CHS_ErrorMail->send();
        }
        return true;
        }catch (\Throwable $e) {
                CHS_Logger::logs("Error: " . $e->getMessage());
                return false;
        }
    }

    public static function getPostIdByCentryMLS(string $mls): ?int
    {
        $posts = get_posts([
            'post_type'  => CHS_PROPERTY_POST_TYPE,
            'meta_key'   => '_centris_mls',
            'meta_value' => $mls,
            'fields'     => 'ids',
            'numberposts' => 1,
        ]);

        return !empty($posts) ? (int)$posts[0] : null;
    }

    public static function getCentrisSourceId(string $file)
    {
        $base = pathinfo($file, PATHINFO_FILENAME);
        preg_match('/(\d+)$/', $base, $matches);
        if (empty($matches)) {
            return 0;
        }
        if (isset($matches[1])) {
            $sourceId = $matches[1];
            return $sourceId;
        }
        return 0;
    }

    public static function createJsonFile()
    {
        $uploadDir = wp_upload_dir();
        $logDir = $uploadDir['basedir'] . '/centris-sync/summaries/';

        // Make sure directory exists
        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);
        }

        // Get last file from settings
        $lastFile = get_option('chs_last_json_file', '');

        // Delete last file if empty
        if ($lastFile && file_exists($lastFile) && filesize($lastFile) === 0) {
            unlink($lastFile);
        }

        // Create new file
        $newFile = $logDir . 'centris-sync-' . date('Ymd-Hi') . '.json';
        
        
        file_put_contents($newFile, json_encode([], JSON_PRETTY_PRINT));

        // Save path to settings
        update_option('chs_last_json_file', $newFile);

        return $newFile;
    }

      /**
     * Write data to the latest JSON file.
     * - Accepts string, int, bool, or array.
     * - Appends as key => value.
     */
    public static function writeJsonFile(string $key, $value)
    {
        $file = get_option('chs_last_json_file', '');

        if (!$file || !file_exists($file)) {
            // If no file exists, create one first
            $file = self::createJsonFile();
        }

        // Read existing JSON
        $content = json_decode(file_get_contents($file), true);

        if (!is_array($content)) {
            $content = [];
        }

        // Add or update data
        $content[$key] = $value;

        // Save back to file
        file_put_contents($file, json_encode($content, JSON_PRETTY_PRINT));
    }

    // create a function to write json system contents 
    public static function writeJsonFileSystemInfo(){
        $systemInfo = [
            'site_url'       => get_site_url(),
            'environment'    => defined('WP_ENV') ? WP_ENV : 'production',
            'plugin_version' => defined('CHS_PLUGIN_VERSION') ? CHS_PLUGIN_VERSION : '1.0.0',
        ];
        // Write to JSON
        self::writeJsonFile('system', $systemInfo);    
    }

    public static function getLastErrors($logFile, $startDate, $endDate, $limit = 10) {
        if (!file_exists($logFile)) return [];

        $errors = [];

        $handle = fopen($logFile, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if(count($errors) >= $limit) break;
                // Example line format: "2025-09-27 10:03:51 - Error ..."
                if (stripos($line, 'error') === false) continue; // skip non-error lines

                $timestamp = substr($line, 0, 19); // first 19 chars = "YYYY-MM-DD HH:MM:SS"
                
                if ($timestamp >= $startDate && $timestamp <= $endDate) {
                    $errors[] = trim($line);
                }
            }
            fclose($handle);
        }

        // return only the last $limit errors
        // return array_slice($errors, -$limit);
        return $errors;
    }

    public static function chs_store_or_get_json($data = null) {
        if ($data !== null && !is_array($data)) {
            return null; // Only accept arrays or null
        }
        // Define fixed file path (you can also store in a plugin option)
        $filePath = WP_CONTENT_DIR . '/uploads/centris-sync/lastrun/';
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filePath .= 'last_detected_files.json';

        // If data is provided → overwrite the file
        if ($data !== null) {
            file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
            return $data;
        }

        // If file exists → return decoded JSON
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            return json_decode($content, true);
        }

        // File doesn't exist and no data → return null
        return null;
    }

}
