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
        $CHS_SummaryMail = new CHS_SummaryMail();
        $CHS_ErrorMail = new CHS_ErrorMail();
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

}
