<?php
if (!defined('ABSPATH')) exit;
require_once CHS_PLUGIN_DIR . 'inc/services/class-chs-file-detector.php';

function chs_admin_dashboard() {
    echo '<div class="wrap"><h1>Centris Houzez Sync</h1>';

    // Handle manual sync trigger
    if (isset($_POST['chs_manual_sync']) && check_admin_referer('chs_manual_sync_action', 'chs_manual_sync_nonce')) {
        chs_sync_properties();
        echo '<div class="updated notice"><p><strong>Sync started. Check logs for details.</strong></p></div>';
    }

    // Handle Clear Logs
    if (isset($_POST['chs_clear_logs']) && check_admin_referer('chs_clear_logs_action', 'chs_clear_logs_nonce')) {
        $logFile = CHS_LOG_FILE;
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        echo '<div class="updated notice"><p><strong>Logs cleared.</strong></p></div>';
    }

    // Settings: source path & filename pattern
    $sourcePath   = get_option('chs_source_path', '/home/vitev704/centris');
    $filePattern  = get_option('chs_file_pattern', 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP');
    $cronMorning  = get_option('chs_cron_morning', '06:45');
    $cronEvening  = get_option('chs_cron_evening', '18:45');

    echo '<h2>Settings</h2>';
    echo '<form method="post" action="options.php">';
    settings_fields('chs_settings_group');
    do_settings_sections('chs_settings_group');
    echo '<table class="form-table">';
    echo '<tr><th>Source Path</th><td><input type="text" name="chs_source_path" value="' . esc_attr($sourcePath) . '" size="60" /></td></tr>';
    echo '<tr><th>Filename Pattern</th><td><input type="text" name="chs_file_pattern" value="' . esc_attr($filePattern) . '" size="60" /></td></tr>';
    echo '<tr><th>Cron Morning</th><td><input type="time" name="chs_cron_morning" value="' . esc_attr($cronMorning) . '" /></td></tr>';
    echo '<tr><th>Cron Evening</th><td><input type="time" name="chs_cron_evening" value="' . esc_attr($cronEvening) . '" /></td></tr>';
    echo '</table>';
    submit_button('Save Settings');
    echo '</form>';

    // Action buttons
    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('chs_manual_sync_action', 'chs_manual_sync_nonce');
    submit_button('Run Sync Now', 'primary', 'chs_manual_sync', false);

    wp_nonce_field('chs_scan_now_action', 'chs_scan_now_nonce');
    submit_button('Scan Now', 'secondary', 'chs_scan_now', false);

    wp_nonce_field('chs_clear_logs_action', 'chs_clear_logs_nonce');
    submit_button('Clear Logs', 'delete', 'chs_clear_logs', false);
    echo '</form>';

        // Handle "Scan Now"
    if (isset($_POST['chs_scan_now']) && check_admin_referer('chs_scan_now_action', 'chs_scan_now_nonce')) {
        update_option('chs_last_scan', time()); // just to trigger refresh
        echo '<div class="updated notice"><p><strong>Scan executed. Table refreshed.</strong></p></div>';
    
    // Detected source files
    echo '<h2>Detected source files (last 5)</h2>';
    echo '<p><em>Timezone: America/Toronto</em></p>';
    // $files = chs_detect_source_files($sourcePath, $filePattern, 5);/
    $files = CHS_FileDetector::detect($sourcePath, $filePattern, 5);

    if (!empty($files)) {
        echo '<table class="widefat"><thead><tr><th>Path</th><th>Size</th><th>MTime</th></tr></thead><tbody>';
        foreach ($files as $f) {
            echo '<tr>';
            echo '<td>' . esc_html($f['path']) . '</td>';
            echo '<td>' . esc_html($f['size_mb']) . '</td>';
            echo '<td>' . esc_html($f['mtime']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p><em>No files detected.</em></p>';
    }
}

    // Logs viewer
echo '<h2>Logs</h2>';

$todayLog  = CHS_LOG_FILE; //$logDir . 'log-' . date('Y-m-d') . '.txt';

if (file_exists($todayLog)) {
    $logs = file($todayLog, FILE_IGNORE_NEW_LINES);
    $lastLogs = array_slice($logs, -50); // last 50 lines
    $output = implode("\n", $lastLogs);  // convert array → string

    echo '<pre style="background:#f9f9f9; padding:10px; max-height:400px; overflow:auto;">'
        . $output
 . '</pre>';
} else {
    echo '<p><em>No log file found for today.</em></p>';
}
    echo '</div>';
}
