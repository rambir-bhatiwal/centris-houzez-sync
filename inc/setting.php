<?php
if (!defined('ABSPATH')) exit;

class CHS_Admin_Settings {
    protected $sourcePath;
    protected $filePattern;
    protected $cronMorning;
    protected $cronEvening;
    protected $recipients;
    protected $subjectPrefix;
    protected $sendMode;
    protected $attachLog;

    public function __construct() {
        // Register submenu & settings
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);

        $this->sourcePath   = get_option('chs_source_path', '/home/vitev704/centris');
        $this->filePattern  = get_option('chs_file_pattern', 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP');
        $this->cronMorning  = get_option('chs_cron_morning', '06:45');
        $this->cronEvening  = get_option('chs_cron_evening', '18:45');
        // Load saved values
        $this->recipients     = sanitize_text_field(get_option('chs_recipients', ''));
        $this->subjectPrefix  = sanitize_text_field(get_option('chs_subject_prefix', '[Centris Sync]'));
        $this->sendMode       = sanitize_text_field(get_option('chs_send_mode', 'always'));
        $this->attachLog      = sanitize_text_field(get_option('chs_attach_log', 'no'));
    }

    /**
     * Register submenu under main plugin menu
     */
    public function registerMenu() {
        add_submenu_page(
            'chs-admin',                    // parent slug
            'Settings',                // page title
            'Settings',                // menu title
            'manage_options',               // capability
            'chs-admin-settings',      // menu slug
            [$this, 'render']               // callback
        );
    }

    /**
     * Register settings securely
     */
    // public function registerSettings() {
    //     register_setting('chs_settings_group_emails', 'chs_recipients', 'sanitize_text_field');
    //     register_setting('chs_settings_group_emails', 'chs_subject_prefix', 'sanitize_text_field');
    //     register_setting('chs_settings_group_emails', 'chs_send_mode', 'sanitize_text_field');
    //     register_setting('chs_settings_group_emails', 'chs_attach_log', 'sanitize_text_field');
    // }

    public function registerSettings() {
        register_setting('chs_settings_group_emails', 'chs_recipients', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('chs_settings_group_emails', 'chs_subject_prefix', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('chs_settings_group_emails', 'chs_send_mode', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('chs_settings_group_emails', 'chs_attach_log', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    /**
     * Render settings page
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }
        if (isset($_POST['chs_test_email']) && check_admin_referer('chs_test_email_action', 'chs_test_email_nonce')) {
            $this->sendTestEmail();
        }

        $this->renderHeader();
        $this->renderSettings();
        $this->renderEmailSettings();
        $this->footer();
    }

    /**
     * Page header
     */
    protected function renderHeader() {
        ?>
        <div class="wrap chs-admin-page">
            <h1><span class="dashicons dashicons-admin-generic"></span>Settings</h1>
            <style>
                .chs-admin-page h1 {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
            </style>
        <?php
    }

    /**
     * Plugin Settings form
     *
     */

       protected function renderSettings() {
        ?>
          <!-- Settings Card -->
            <div class="chs-card">
                <h2><span class="dashicons dashicons-admin-generic"></span> Settings</h2>

                <form method="post" action="options.php">
                <?= settings_fields('chs_settings_group'); ?>
                <?= do_settings_sections('chs_settings_group'); ?>

                <div class="chs-settings-grid">
                    <!-- Left Side: Source Path & File Pattern -->
                    <div class="chs-settings-box">
                    <table class="form-table">
                        <tr>
                        <th><label for="chs_source_path">📂 Source Path</label></th>
                        <td><input type="text" name="chs_source_path" id="chs_source_path" value="<?= esc_attr($this->sourcePath); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                        <th><label for="chs_file_pattern">📝 Filename Pattern</label></th>
                        <td><input type="text" name="chs_file_pattern" id="chs_file_pattern" value="<?= esc_attr($this->filePattern); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                    </div>

                    <!-- Right Side: Cron Settings -->
                    <div class="chs-settings-box">
                    <table class="form-table">
                        <tr>
                        <th><label for="chs_cron_morning">🌅 Cron Morning</label></th>
                        <td><input type="time" name="chs_cron_morning" id="chs_cron_morning" value="<?= esc_attr($this->cronMorning); ?>" /></td>
                        </tr>
                        <tr>
                        <th><label for="chs_cron_evening">🌙 Cron Evening</label></th>
                        <td><input type="time" name="chs_cron_evening" id="chs_cron_evening" value="<?= esc_attr($this->cronEvening); ?>" /></td>
                        </tr>
                    </table>
                    </div>
                </div>

                <?= submit_button('💾 Save Settings'); ?>
                </form>
            </div>
     <?php  
    }

    /**
     * Settings form
     */
    protected function renderEmailSettings() {
        ?>
        <div class="chs-card">
            <h2><span class="dashicons dashicons-admin-generic"></span> Email Configuration</h2>

            <form method="post" action="options.php">
                <?php 
                    settings_fields('chs_settings_group_emails'); 
                    do_settings_sections('chs_settings_group_emails'); 
                ?>

                <div class="chs-settings-grid">
                    <!-- Left: Recipients & Subject Prefix -->
                    <div class="chs-settings-box">
                        <table class="form-table">
                            <tr>
                                <th><label for="chs_recipients">📥 Recipients</label></th>
                                <td>
                                    <input type="text" name="chs_recipients" id="chs_recipients"
                                           value="<?= esc_attr($this->recipients); ?>" 
                                           class="regular-text" 
                                           placeholder="Comma-separated emails" />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="chs_subject_prefix">📝 Subject Prefix</label></th>
                                <td>
                                    <input type="text" name="chs_subject_prefix" id="chs_subject_prefix"
                                           value="<?= esc_attr($this->subjectPrefix); ?>" 
                                           class="regular-text" />
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Right: Send Mode & Attach Last Log -->
                    <div class="chs-settings-box">
                        <table class="form-table">
                            <tr>
                                <th><label for="chs_send_mode">⚡ Send Mode</label></th>
                                <td>
                                    <select name="chs_send_mode" id="chs_send_mode">
                                        <option value="disabled" <?= selected($this->sendMode, 'disabled'); ?>>disabled</option>
                                        <option value="always" <?= selected($this->sendMode, 'always'); ?>>Always</option>
                                        <option value="changes" <?= selected($this->sendMode, 'changes'); ?>>Only if changes</option>
                                        <option value="error" <?= selected($this->sendMode, 'error'); ?>>Only on error</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="chs_attach_log">📎 Attach Last Log</label></th>
                                <td>
                                    <select name="chs_attach_log" id="chs_attach_log">
                                        <option value="no" <?= selected($this->attachLog, 'no'); ?>>No</option>
                                        <option value="yes" <?= selected($this->attachLog, 'yes'); ?>>Yes</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?= submit_button('💾 Save Settings'); ?>
            </form>

            <!-- Test Email Button -->
            <form method="post" style="margin-top:15px;">
                <?php wp_nonce_field('chs_test_email_action', 'chs_test_email_nonce'); ?>
                <input type="hidden" name="chs_test_email" value="1" />
                <?php submit_button('✉ Send Test Email', 'secondary', 'chs_test_email_btn', false); ?>
            </form>

        </div>
        <?php
    }

    /**
     * Footer wrapper
     */
    protected function footer() {
        echo '</div>'; // .wrap
    }

        
    private function sendTestEmail() {
        if (isset($_POST['chs_test_email']) && check_admin_referer('chs_test_email_action', 'chs_test_email_nonce')) {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized user');
            }
                require_once CHS_PLUGIN_DIR . 'templates/emails/test-email.php';
                if (class_exists('CHS_TestMail')) {
                    $testMail = new CHS_TestMail(
                        get_option('chs_mail_recipients', ''),
                        get_option('chs_mail_subject_prefix', '[Centris Sync]')
                    );

                    $sent = $testMail->send();

                        if ($sent) {
                            echo '<div class="updated notice"><p><strong>Test email sent successfully!</strong></p></div>';
                        } else {
                            echo '<div class="error notice"><p><strong>Failed to send test email. Check recipients or server settings.</strong></p></div>';
                        }
                    } else {
                        echo '<div class="error notice"><p><strong>CHS_TestMail class not found!</strong></p></div>';
                    }

        }
    }
}
