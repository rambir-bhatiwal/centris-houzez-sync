<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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

}
