<?php
if (!defined('ABSPATH')) exit;

class CHS_ColumnInspector {

    protected $baseDir;

    public function __construct() {
        $this->baseDir = CHS_BASE_DIR . 'extracted/';
        if (is_admin()) {
            // add_action('admin_menu', [$this, 'registerAdminPage']);
        }
    }

    /**
     * Register submenu page under Centris Sync
     */
    public function registerAdminPage() {
        add_submenu_page(
            'chs-admin', // parent menu slug
            __('Column Inspector', 'chs'),
            __('Column Inspector', 'chs'),
            'manage_options',
            'chs-inspector',
            [$this, 'renderInspectorPage']
        );
    }

    /**
     * Render the Inspector UI
     */
    public function renderInspectorPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'chs'));
        }

        $selectedLocation = isset($_POST['chs_location']) ? sanitize_text_field($_POST['chs_location']) : '';
        $selectedFile     = isset($_POST['chs_file']) ? sanitize_text_field($_POST['chs_file']) : '';

        echo '<div class="wrap"><h1>Column Inspector</h1>';
        echo '<form method="POST">';

        // Step 1: Location selector (dynamic from folders)
        echo '<h3>Select Folder (extracted ZIP files)</h3>';
        echo '<select name="chs_location" onchange="this.form.submit()">';
        echo '<option value="">-- Choose --</option>';
        $locations = $this->getDirFromLocation();
        foreach ($locations as $loc) {
            echo '<option value="' . esc_attr($loc) . '" ' . selected($selectedLocation, $loc, false) . '>' . esc_html(basename($loc)) . '</option>';
        }
        echo '</select>';

        // Step 2: File selector (always show dropdown, even if empty)
        if(isset($_POST['chs_location'])){
        echo '<h3>Select File</h3>';
        echo '<select name="chs_file">';
        echo '<option value="">-- Choose File --</option>';
        if ($_POST['chs_location']) {
            $files = $this->getFilesFromLocation($_POST['chs_location']);
            foreach ($files as $file) {
                echo '<option value="' . esc_attr($file) . '" ' . selected($selectedFile, $file, false) . '>' . esc_html(basename($file)) . '</option>';
            }
        }
        echo '</select>';

        }
      
        // Submit button
        submit_button('Inspect');

        echo '</form>';

        // Step 3: Inspector Output
        if ($selectedFile) {
            $this->renderInspectorOutput($selectedFile);
        }

        echo '</div>';
    }

    /**
     * Get files from selected folder
     */
    protected function getFilesFromLocation($location) {
        $dir = trailingslashit($this->baseDir . $location);
        // return ( glob($dir . '*.TXT')) ?: [];
        return array_merge(
                glob($dir . '*.txt') ?: [],
                glob($dir . '*.TXT') ?: []
            );
    }

    /**
     * Get sub-directories inside baseDir
     */
    protected function getDirFromLocation() {
        if (!is_dir($this->baseDir)) return [];
        $subDirs = glob($this->baseDir . '*', GLOB_ONLYDIR);
        if (!$subDirs) return [];
        rsort($subDirs); // newest first
        return array_map('basename', $subDirs); // return folder names only
    }

    /**
     * Render inspector output
     */
    protected function renderInspectorOutput($filePath) {
        $fileName = basename($filePath);
        echo "<h3>Inspector File:- {$fileName}</h3><pre>";

        if (!file_exists($filePath)) {
            echo "File not found: " . esc_html($filePath);
            echo '</pre>';
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line !== '') {
                    $columns = explode(',', $line); // pipe-separated
                    foreach ($columns as $index => $value) {
                        echo "[" . ($index) . "] => " . esc_html($value) . "\n";
                    }
                    break;
                }
            }
            fclose($handle);
        } else {
            echo "Unable to open file.";
        }

        echo '</pre>';
    }
}
