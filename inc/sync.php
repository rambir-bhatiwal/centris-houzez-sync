<?php
if (!defined('ABSPATH')) exit;

/**
 * Parse a Centris SIIQ ZIP file and return structured arrays.
 *
 * Returns:
 * [
 *   'meta' => [ 'zip' => 'COMPANY12320230910.zip', 'source' => 'COMPANY123', 'run_id' => '...'],
 *   'listings' => [ [ 'ListingID' => '...', 'Price' => '...', ... ], ... ],
 *   'photos' => [ [ 'ListingID' => '...', 'PhotoURL' => '...', 'Order' => 1 ], ... ],
 *   'dictionaries' => [ 'TABLE_NAME' => [ ... ] ],
 *   'errors' => [ '...' ],
 * ]
 *
 * @param string $zip_path Absolute path to the SIIQ zip file on server.
 * @param bool $cleanup_after Whether to remove extracted temp folder after parsing.
 * @return array
 */

 function chs_sync_properties(): void {
    try {
            /// Clear previous extracted files and logs
            $extracted_dir = CHS_BASE_DIR . 'extracted/';
            CHS_Utils::clearFolder(  $extracted_dir );
            
            /// Clear previous logs
            if (file_exists(CHS_LOG_FILE) && CHS_CLEAR_LOGS_ON_SYNC) {
                file_put_contents(CHS_LOG_FILE, '');
            }

            $reader = new CHS_ZipReader();
            $reader->process_all_files();
            CHS_Utils::sendEmail();
            CHS_Logger::log("Sync process executed successfully.");
        } catch (\Throwable $e) {
            CHS_Logger::logs("Sync process failed: " . $e->getMessage());
        }
}
