<?php
if (!defined('ABSPATH')) exit;
require_once CHS_PLUGIN_DIR . 'inc/services/class-chs-file-detector.php';
class CHS_AdminDashboard {
    protected $sourcePath;
    protected $filePattern;
    protected $cronMorning;
    protected $cronEvening;
    protected $lastScan;
    protected $detectedFiles;
    protected $logFile;

    public function __construct() {
        $this->sourcePath   = get_option('chs_source_path', '/home/vitev704/centris');
        $this->filePattern  = get_option('chs_file_pattern', 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP');
        $this->cronMorning  = get_option('chs_cron_morning', '06:45');
        $this->cronEvening  = get_option('chs_cron_evening', '18:45');
        $this->lastScan     = get_option('chs_last_scan', 0);
        $this->logFile      = CHS_LOG_FILE;

        // $this->detectedFiles = CHS_FileDetector::detect($this->sourcePath, $this->filePattern, 5);
        // $this->renderHeader();
        // $this->renderSettings();
        // $this->renderActions();
        // $this->detectedSourceFiles($this->lastScan);
        // $this->renderLogs();
        // $this->footer();
    }

    public function chs_admin_dashboard() {
        $this->renderHeader();
        // $this->renderSettings();
        $this->renderActions();
        $this->detectedSourceFiles($this->lastScan);
  
        $this->renderLogs();
        $this->footer();
    }
  

    protected function renderActions():void {
        // Handle manual sync trigger
        // if(isset($_POST)) {
        //        if (!current_user_can('manage_options')) {
        //           wp_die('You do not have sufficient permissions to perform this action.');
        //       }
        // }
        if (isset($_POST['chs_manual_sync']) && check_admin_referer('chs_manual_sync_action', 'chs_manual_sync_nonce')) {
           if (!current_user_can('manage_options')) {
                  wp_die('You do not have sufficient permissions to perform this action.');
              }
            chs_sync_properties();
            echo '<div class="updated notice"><p><strong>Sync started. Check logs for details.</strong></p></div>';
        }

        // Handle Clear Logs
        if (isset($_POST['chs_clear_logs']) && check_admin_referer('chs_clear_logs_action', 'chs_clear_logs_nonce')) {
            if (!current_user_can('manage_options')) {
                  wp_die('You do not have sufficient permissions to perform this action.');
              }

            if (file_exists($this->logFile)) {
                file_put_contents($this->logFile, '');
            }
            echo '<div class="updated notice"><p><strong>Logs cleared.</strong></p></div>';
        }
        ?>
        <!-- Action Buttons Card -->
           <!-- Action Buttons -->
            <div class="chs-card">
              <h2><span class="dashicons dashicons-controls-play"></span> Actions</h2>
              <div class="chs-action-buttons">
                    <form method="post">
                      <?php
              // wp_nonce_field('chs_manual_sync_action', 'chs_manual_sync_nonce');
              // submit_button('Run Sync Now', 'primary', 'chs_manual_sync', false);

              // wp_nonce_field('chs_scan_now_action', 'chs_scan_now_nonce');
              // submit_button('Scan Now', 'secondary', 'chs_scan_now', false);

              // wp_nonce_field('chs_clear_logs_action', 'chs_clear_logs_nonce');
              // submit_button('Clear Logs', 'delete', 'chs_clear_logs', false);
                  // Run Sync Now
                  wp_nonce_field('chs_manual_sync_action', 'chs_manual_sync_nonce');
                  submit_button('▶ Run Sync Now', 'primary', 'chs_manual_sync', false);

                  // Scan Now
                  wp_nonce_field('chs_scan_now_action', 'chs_scan_now_nonce');
                  submit_button('🔍 Scan Now', 'secondary', 'chs_scan_now', false);

                  // Clear Logs
                  wp_nonce_field('chs_clear_logs_action', 'chs_clear_logs_nonce');
                  submit_button('🗑 Clear Logs', 'delete button-danger', 'chs_clear_logs', false);
              ?>
              </form>
              </div>
            </div>        
        <?php
    }
    protected function renderHeader() {
        ?>
            <div class="wrap chs-admin-page">
            <h1><span class="dashicons dashicons-update-alt"></span> Centris Houzez Sync</h1>


          <style>
              .chs-admin-page h1 {
                  display: flex;
                  align-items: center;
                  gap: 10px;
              }
          </style>


        <?php
    }

    protected function detectedSourceFiles($timestamp) {
        ?>
         <!-- Files -->
          <div class="chs-card">
            <h2><span class="dashicons dashicons-media-code"></span> Detected source files (last 5)</h2>
                <p><em>Timezone: America/Toronto</em></p>

            <?php
                if (isset($_POST['chs_scan_now']) && check_admin_referer('chs_scan_now_action', 'chs_scan_now_nonce')) {
                if (!current_user_can('manage_options')) {
                  wp_die('You do not have sufficient permissions to perform this action.');
              }
                  update_option('chs_last_scan', time()); // just to trigger refresh
                echo '<div class="updated notice"><p><strong>Scan executed. Table refreshed.</strong></p></div>';
            
            ?>
            <table class="widefat striped fixed">
              <thead>
                <tr><th>Path</th><th>Size (MB)</th><th>Modified</th></tr>
              </thead>
              <tbody>
                <!-- <tr><td>/example/file1.zip</td><td>12.3</td><td>2025-09-15 10:30</td></tr>
                <tr><td>/example/file2.txt</td><td>4.5</td><td>2025-09-14 21:00</td></tr> -->
        <?php
                    // Detected source files

            // $files = chs_detect_source_files($sourcePath, $filePattern, 5);/
            $files = CHS_FileDetector::detect($this->sourcePath, $this->filePattern, 5);

            if (!empty($files)) {
        //       echo '<table class="widefat"><thead><tr><th>Path</th><th>Size</th><th>MTime</th></tr></thead><tbody>';
                foreach ($files as $f) {
                    echo '<tr>';
                    echo '<td>' . esc_html($f['path']) . '</td>';
                    echo '<td>' . esc_html($f['size_mb']) . '</td>';
                    echo '<td>' . esc_html($f['mtime']) . '</td>';
                    echo '</tr>';
                }
          //     echo '</tbody></table>';
            } else {
                echo '<p><em>No files detected.</em></p>';
            }
            ?>

              </tbody>
            </table>
        <?php }?>

          </div>
        <?php
    }

    protected function renderLogs() {
        ?>
                  <!-- Logs -->
          <div class="chs-card">
            <h2><span class="dashicons dashicons-media-text"></span> Logs</h2>
            <pre class="chs-logs">

            <?php

          $logFile  = CHS_LOG_FILE; //$logDir . 'log-' . date('Y-m-d') . '.txt';

          if (file_exists($logFile)) {
            $logs = file($logFile, FILE_IGNORE_NEW_LINES);
            // $lastLogs = array_slice($logs, -50); // last 50 lines
            $output = implode("\n", $logs);  // convert array → string

            echo '<pre style="overflow:auto;">'
                . $output
          . '</pre>';
          } else {
            echo '<p><em>No log file found for today.</em></p>';
          }
          ?>

            </pre>
          </div>

        <?php
    }

    protected function footer() {
        echo '</div>'; // .wrap
    } 
}

