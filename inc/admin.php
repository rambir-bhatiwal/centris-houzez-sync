<?php
if (!defined('ABSPATH')) exit;

function chs_admin_dashboard() {
    echo '<div class="wrap"><h1>Centris Houzez Sync</h1>';

    // Handle manual sync trigger
    if (isset($_POST['chs_manual_sync']) && check_admin_referer('chs_manual_sync_action', 'chs_manual_sync_nonce')) {
        chs_sync_properties();
        echo '<div class="updated notice"><p><strong>Sync started. Check logs for details.</strong></p></div>';
    }

    // Handle Clear Logs trigger
    if (isset($_POST['chs_clear_log']) && check_admin_referer('chs_clear_log_action', 'chs_clear_log_nonce')) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/centris-sync/logs/log-' . date('Y-m-d') . '.txt';
        if (file_exists($log_file)) {
            unlink($log_file);
            echo '<div class="updated notice"><p><strong>Today\'s log cleared successfully.</strong></p></div>';
        }
    }

    // Run Sync Now Button
    echo '<form method="post" style="margin-bottom:20px;">';
    wp_nonce_field('chs_manual_sync_action', 'chs_manual_sync_nonce');
    submit_button('Run Sync Now', 'primary', 'chs_manual_sync');
    echo '</form>';

    // Clear Log Button
    echo '<form method="post" style="margin-bottom:20px;">';
    wp_nonce_field('chs_clear_log_action', 'chs_clear_log_nonce');
    submit_button('Clear Today\'s Log', 'secondary', 'chs_clear_log');
    echo '</form>';

    // Display Log
    $upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/centris-sync/logs/';
$log_file = $log_dir . 'log-' . date('Y-m-d') . '.txt';



$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/centris-sync/logs/';
$log_file = $log_dir . 'log-' . date('Y-m-d') . '.txt';

$upload_dir = wp_upload_dir();
$log_dir    = $upload_dir['basedir'] . '/centris-sync/logs/';
$log_file   = $log_dir . 'log-' . date('Y-m-d') . '.txt';

echo '<h2>Log Preview</h2>';

if (file_exists($log_file) && is_readable($log_file)) {
    echo '<pre style="white-space:pre-wrap; word-wrap:break-word;">';
    echo file_get_contents($log_file); // Display raw content as-is
    echo '</pre>';
} else {
    echo '<em>No log file found or not readable.</em>';
}

echo '</div>';
}
