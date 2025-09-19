<?php
/**
 * CHS_ZipReader
 *
 * Responsibilities:
 * - Parse TXT files directly or ZIP -> extract -> parse inner TXT(s)
 * - Use a file-based run lock to avoid overlapping runs
 * - Archive processed files into archived/YYYY-MM-DD/ (Toronto date)
 * - Log each extracted TXT with path/size/mtime before parsing
 * - Timezone: America/Toronto (used for timestamps & archive folders)
 *
 * Notes:
 * - This class uses CHS_Logger::log(...) for logs (keep your existing logger).
 * - Security/post-meta: four meta keys to be used later:
 *   centrismlls, centrisurl_detaillee, centrissource_id, centrisimage_signatures
 *
 * Copy-paste into: inc/class-chs-zip-reader.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CHS_PLUGIN_DIR . 'inc/constants.php';

class CHS_ZipReader {

    /* ---------------- Paths & settings ---------------- */
    protected $base_dir;
    protected $zip_dir;       // input dir (CHS_SOURCE_PATH)
    protected $extract_dir;   // base extracted folder
    protected $archive_dir;   // base archive folder
    protected $failed_dir;    // base failed folder
    protected $lock_file;     // run lock path
    protected $lockTtl = 3600; // seconds to consider lock stale (1 hour)

    /* Timezone */
    protected $tzName = CHS_TIMEZONE;
    protected $tz;

    /* Parsed result storage */
    protected $parsed = [];

    public function __construct() {
        $this->tz = new DateTimeZone( $this->tzName );

        $upload_dir = wp_upload_dir();
        $this->base_dir    = rtrim( $upload_dir['basedir'], '/\\' ) . '/centris-sync/';
        $this->zip_dir     = CHS_SOURCE_PATH . '/';    // existing pattern: source path
        $this->extract_dir = $this->base_dir . 'extracted/';
        $this->archive_dir = $this->base_dir . 'archived/';
        $this->failed_dir  = $this->base_dir . 'failed/';
        $this->lock_file   = $this->base_dir . 'chs_run.lock';

        foreach ( [ $this->zip_dir, $this->extract_dir, $this->archive_dir, $this->failed_dir ] as $d ) {
            if ( ! file_exists( $d ) ) {
                wp_mkdir_p( $d );
            }
        }
    }

    /* ---------------- Time helpers (Toronto) ---------------- */

    protected function now( $format = 'Y-m-d H:i:s' ) {
        $dt = new DateTime( 'now', $this->tz );
        return $dt->format( $format );
    }

    protected function nowTimestamp() {
        return (int) ( new DateTime( 'now', $this->tz ) )->getTimestamp();
    }

    protected function formatTimestamp( $timestamp, $format = 'Y-m-d H:i:s' ) {
        if ( empty( $timestamp ) ) return '';
        $dt = new DateTime( '@' . (int) $timestamp );
        $dt->setTimezone( $this->tz );
        return $dt->format( $format );
    }

    /* ---------------- Locking ---------------- */

    /**
     * Try to acquire the run lock.
     * Returns true if lock acquired, false if another run is active.
     */
    protected function acquireLock() {
        // If lock present, check TTL
        if ( file_exists( $this->lock_file ) ) {
            $age = $this->nowTimestamp() - @filemtime( $this->lock_file );
            if ( $age > $this->lockTtl ) {
                CHS_Logger::log( "Stale lock detected (age {$age}s). Removing stale lock: {$this->lock_file}" );
                @unlink( $this->lock_file );
            } else {
                $contents = @file_get_contents( $this->lock_file );
                CHS_Logger::log( "Lock present (age {$age}s). Skipping run. Lock contents: " . ( $contents ?: '[empty]' ) );
                return false;
            }
        }

        $meta = [
            'locked_at' => $this->now(),
            'pid'       => function_exists( 'getmypid' ) ? getmypid() : 0,
            'tz'        => $this->tzName,
        ];
        @file_put_contents( $this->lock_file, wp_json_encode( $meta ) );
        CHS_Logger::log( "Acquired lock: {$this->lock_file} (time: {$meta['locked_at']}, tz: {$this->tzName})" );

        return true;
    }

    /**
     * Release the run lock (safe delete).
     */
    protected function releaseLock() {
        if ( file_exists( $this->lock_file ) ) {
            @unlink( $this->lock_file );
            CHS_Logger::log( "Released lock: {$this->lock_file} (time: " . $this->now() . ")" );
        } else {
            CHS_Logger::log( "Release requested but lock not found: {$this->lock_file}" );
        }
    }

    /* ---------------- Public API ---------------- */

    /**
     * Main entry — process all ZIPs and standalone TXT/CSV.
     * This method uses locking to prevent overlap.
     */
    public function process_all_files() {
        $results = [];

        if (!$this->acquireLock()) {
            CHS_Logger::log("Lock active - another run in progress, skipping.");
            return $results;
        }

        try {
            // === ZIP Handling ===
            $zips = glob($this->zip_dir . '*.{zip,ZIP}', GLOB_BRACE);
            if (!empty($zips)) {
                CHS_Logger::log("Found " . count($zips) . " ZIP(s) to process.");
                foreach ($zips as $zipFile) {
                    $res = $this->process_single_zip($zipFile);
                    if ($res !== null) $results[] = $res;
                }
            }

            // === Standalone TXT/CSV Handling ===
            $textFiles = glob($this->zip_dir . '*.{txt,TXT,csv,CSV}', GLOB_BRACE);
            if (!empty($textFiles)) {
                CHS_Logger::log("Found " . count($textFiles) . " standalone TXT/CSV file(s) to process.");
                foreach ($textFiles as $txtFile) {
                    $runId = 'standalone_' . time();
                    // ✅ Pass original TXT file as $sourceFile
                    $res = $this->generic_process_files($this->zip_dir, $runId, $txtFile);

                    // Archive original ZIP (timezone-dated folder) unless dev mode
                    if ( ! CHS_DEV_MODE ) {
                        $this->archive_zip( $txtFile );
                    } else {
                        CHS_Logger::log( "DEV_MODE active - ZIP not archived: " . basename( $txtFile ) );
                    }
                    CHS_Logger::log("Processed standalone file: " . basename($txtFile));
                    if ($res !== null) $results[] = $res;
                }
            }

            CHS_Logger::log("All file processing finished.");
        } catch (\Throwable $e) {
            CHS_Logger::log("Error in process_all_files: " . $e->getMessage());
        } finally {
            $this->releaseLock();
        }

        return $results;
    }
    /* ---------------- Single ZIP flow ---------------- */

    /**
     * Process a single zip file:
     * - extract to a unique run folder under extracted/
     * - scan and log extracted files (path/size/mtime)
     * - generic parse
     * - archive original zip into archived/YYYY-MM-DD/
     */
    protected function process_single_zip( $zip_file ) {
        $run_id  = uniqid( 'chs_run_', true );
        $run_dir = $this->extract_dir . $run_id . '/';

        try {
            wp_mkdir_p( $run_dir );
            $zip = new ZipArchive();
            $open_res = $zip->open( $zip_file );
            if ( $open_res !== true ) {
                CHS_Logger::log( "Failed to open ZIP: " . basename( $zip_file ) . " (code {$open_res})" );
                $this->move_to_failed( $zip_file );
                return null;
            }

            if ( $zip->extractTo( $run_dir ) === false ) {
                CHS_Logger::log( "Failed to extract ZIP: " . basename( $zip_file ) );
                $zip->close();
                $this->move_to_failed( $zip_file );
                return null;
            }
            $zip->close();

            CHS_Logger::log( "Extracted ZIP: " . basename( $zip_file ) . " to {$run_dir} at " . $this->now() );

            // Scan and log (including path/size/mtime)
            $scan = $this->scan_and_log_files( $run_dir );

            // Generic parse
            $parsed = $this->generic_process_files( $run_dir, $run_id, $zip_file );

            // Archive original ZIP (timezone-dated folder) unless dev mode
            if ( ! CHS_DEV_MODE ) {
                $this->archive_zip( $zip_file );
            } else {
                CHS_Logger::log( "DEV_MODE active - ZIP not archived: " . basename( $zip_file ) );
            }

            $result = [
                'run_id' => $run_id,
                'zip'    => basename( $zip_file ),
                'scan'   => $scan,
                'parsed' => array_keys( $parsed ),
            ];

            $this->parsed[ $run_id ] = $parsed;

            CHS_Logger::log( "Run {$run_id} completed at " . $this->now() . ": parsed files: " . implode( ', ', array_keys( $parsed ) ) );
            return $result;

        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error processing ZIP " . basename( $zip_file ) . ": " . $e->getMessage() );
            $this->move_to_failed( $zip_file );
            return null;
        }
    }

    /**
     * Scan directory and log each data file with path/size/mtime (Toronto).
     * Returns file info metadata.
     */
    protected function scan_and_log_files( $run_dir ) {
        $info = [];
        try {
            $files = glob( $run_dir . '*.{txt,TXT,csv,CSV,accdb,ACCDB}', GLOB_BRACE );
            CHS_Logger::log( "Run directory '{$run_dir}' contains " . count( $files ) . " data file(s)." );

            foreach ( $files as $file ) {
                $basename = basename( $file );
                $size = @filesize( $file );
                $mtime = @filemtime( $file );
                $mtime_fmt = $this->formatTimestamp( $mtime );
                CHS_Logger::log( sprintf( "[scan] file=%s path=%s size=%d modified=%s", $basename, $file, $size, $mtime_fmt ) );

                // header preview + counts (keeps behavior)
                $lines = $this->count_lines( $file );
                $first = $this->get_first_non_empty_line( $file );
                $delim = $first !== null ? $this->detect_delimiter( $first ) : ',';
                $header_fields = $first ? str_getcsv( $this->strip_bom( $first ), $delim ) : [];
                $header_preview = array_slice( array_map( 'trim', $header_fields ), 0, 12 );

                CHS_Logger::log( "Found file {$basename} — size: {$size} bytes — modified: {$mtime_fmt} — lines: {$lines} — header preview: " . ( empty( $header_preview ) ? '[none]' : implode( ' | ', $header_preview ) ) );

                $info[ $basename ] = [
                    'path' => $file,
                    'lines' => $lines,
                    'header_preview' => $header_preview,
                    'detected_delimiter' => $delim,
                    'size' => $size,
                    'modified' => $mtime_fmt,
                ];
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error scanning run_dir {$run_dir}: " . $e->getMessage() );
        }

        return $info;
    }

    /* ---------------- Generic parsing ---------------- */

    /**
     * Generic processing for all files in $run_dir (TXT/CSV).
     * Logs parse start & end lines with duration.
     */
    protected function generic_process_files( $run_dir, $run_id, $sourceFile = null ) {
        $results = [];

        try {
            $files = glob( $run_dir . '*.{txt,TXT,csv,CSV,accdb,ACCDB}', GLOB_BRACE );
            foreach ( $files as $file ) {
                $basename = basename( $file );

                // For accdb skip
                $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                if ( $ext === 'accdb' ) {
                    CHS_Logger::log( "Skipping ACCDB parsing (reference): {$basename}" );
                    $results[ $basename ] = [ 'meta' => [ 'type' => 'accdb', 'path' => $file ], 'rows' => [] ];
                    continue;
                }

                // Log parse start line as requested
                // CHS_Logger::log("[parse] start sourceFile file=" . $sourceFile);
                // CHS_Logger::log( "[parse] start file={$file}" );
                if ($sourceFile && !in_array(strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION)), ['txt', 'csv'])) {
                    CHS_Logger::log("[parse] start source file={$sourceFile}");
                }

                // if ($sourceFile) {
                //     CHS_Logger::log("[parse] start source file={$sourceFile}");
                // }
                CHS_Logger::log("[parse] parsing file={$file}");

                $start = microtime( true );

                // delimiter detection & parse
                $first = $this->get_first_non_empty_line( $file );
                $delim = $first !== null ? $this->detect_delimiter( $first ) : ',';
                $rows = $this->parse_generic_csv( $file, $delim );

                $duration = microtime( true ) - $start;
                $duration_s = round( $duration, 2 );

                CHS_Logger::log( "[parse] records=" . count( $rows ) . " duration={$duration_s}s" );

                // sample preview logs already in parse
                $results[ $basename ] = [
                    'meta' => [
                        'path' => $file,
                        'delimiter' => $delim,
                        'lines' => $this->count_lines( $file ),
                        'size' => @filesize( $file ),
                        'modified' => $this->formatTimestamp( @filemtime( $file ) ),
                    ],
                    'rows' => $rows,
                ];
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error in generic_process_files for {$run_dir}: " . $e->getMessage() );
        }

        $this->parsed[ $run_id ] = $results;
        return $results;
    }

    /**
     * Parse CSV/TXT generically and return associative rows (header => value)
     * Keeps existing robust behavior and ensures UTF-8 normalization.
     */
    protected function parse_generic_csv( $file, $delimiter = ',' ) {
        $rows = [];
        try {
            $handle = fopen( $file, 'r' );
            if ( ! $handle ) {
                CHS_Logger::log( "Cannot open file for parsing: " . basename( $file ) );
                return $rows;
            }

            // find header (first non-empty line)
            $headers = [];
            while ( ( $line = fgets( $handle ) ) !== false ) {
                $line = trim( $line );
                if ( $line === '' ) continue;
                $line = $this->strip_bom( $line );
                $headers = str_getcsv( $line, $delimiter );
                $headers = array_map( function( $h ) {
                    $h = $this->to_utf8( $h );
                    return trim( preg_replace( '/\s+/', '_', $h ) );
                }, $headers );
                break;
            }

            if ( empty( $headers ) ) {
                fclose( $handle );
                return $rows;
            }

            // read remaining rows
            while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
                $all_empty = true;
                foreach ( $data as $c ) {
                    if ( trim( (string) $c ) !== '' ) { $all_empty = false; break; }
                }
                if ( $all_empty ) continue;

                $assoc = [];
                for ( $i = 0; $i < count( $headers ); $i++ ) {
                    $key = $headers[$i] ?? 'col_'.$i;
                    $val = isset( $data[$i] ) ? $this->to_utf8( $data[$i] ) : null;
                    $assoc[ $key ] = $val;
                }
                $rows[] = $assoc;
            }

            fclose( $handle );
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error parsing file " . basename( $file ) . ": " . $e->getMessage() );
        }

        return $rows;
    }

    /* ---------------- Helpers (existing) ---------------- */

    protected function row_preview( $row ) {
        if ( empty( $row ) || ! is_array( $row ) ) return '[empty]';
        $vals = array_slice( $row, 0, 6 );
        $pairs = [];
        $i = 0;
        foreach ( $vals as $k => $v ) {
            $v = is_string( $v ) ? preg_replace( '/\s+/', ' ', trim( $v ) ) : $v;
            $v = (string) $v;
            if ( strlen( $v ) > 120 ) $v = substr( $v, 0, 117 ) . '...';
            $pairs[] = "{$k}=" . $v;
            $i++; if ( $i >= 6 ) break;
        }
        return '[' . implode( ', ', $pairs ) . ']';
    }

    protected function count_lines( $file ) {
        try {
            $obj = new SplFileObject( $file, 'r' );
            $obj->seek( PHP_INT_MAX );
            return $obj->key() + 1;
        } catch ( \Throwable $e ) {
            $count = 0;
            if ( ( $h = @fopen( $file, 'r' ) ) !== false ) {
                while ( fgets( $h ) !== false ) { $count++; }
                fclose( $h );
            }
            return $count;
        }
    }

    protected function get_first_non_empty_line( $file ) {
        try {
            $h = fopen( $file, 'r' );
            if ( ! $h ) return null;
            while ( ( $line = fgets( $h ) ) !== false ) {
                $line = trim( $line );
                if ( $line !== '' ) {
                    fclose( $h );
                    return $this->strip_bom( $line );
                }
            }
            fclose( $h );
            return null;
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    protected function detect_delimiter( $line ) {
        $candidates = [ ',', ';', "\t", '|' ];
        $best = ',';
        $best_count = 0;
        foreach ( $candidates as $d ) {
            $cnt = substr_count( $line, $d );
            if ( $cnt > $best_count ) { $best_count = $cnt; $best = $d; }
        }
        return $best;
    }

    protected function to_utf8( $value ) {
        if ( $value === null ) return null;
        if ( mb_check_encoding( $value, 'UTF-8' ) ) return $value;
        $encs = [ 'CP1252', 'ISO-8859-1', 'Windows-1252', 'ASCII' ];
        foreach ( $encs as $e ) {
            $out = @mb_convert_encoding( $value, 'UTF-8', $e );
            if ( mb_check_encoding( $out, 'UTF-8' ) ) return $out;
        }
        return mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
    }

    protected function strip_bom( $s ) {
        if ( substr( $s, 0, 3 ) === "\xEF\xBB\xBF" ) return substr( $s, 3 );
        return $s;
    }

    /* ---------------- Archiving / failed moves ---------------- */

    protected function archive_zip( $zip_file ) {
        try {
            $dateDir = $this->archive_dir . $this->now( 'Y-m-d' ) . '/';
            if ( ! file_exists( $dateDir ) ) wp_mkdir_p( $dateDir );
            $dest = $dateDir . basename( $zip_file );
            if ( rename( $zip_file, $dest ) ) {
                CHS_Logger::log( "Archived ZIP: " . basename( $zip_file ) . " -> {$dateDir}" );
            } else {
                CHS_Logger::log( "Failed to archive ZIP: " . basename( $zip_file ) );
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error archiving " . basename( $zip_file ) . ": " . $e->getMessage() );
        }
    }

    protected function archive_specific_file( $file ) {
        try {
            $dateDir = $this->archive_dir . $this->now( 'Y-m-d' ) . '/';
            if ( ! file_exists( $dateDir ) ) wp_mkdir_p( $dateDir );
            $dest = $dateDir . basename( $file );
            if ( rename( $file, $dest ) ) {
                CHS_Logger::log( "Archived file: " . basename( $file ) . " -> {$dateDir}" );
            } else {
                CHS_Logger::log( "Failed to archive file: " . basename( $file ) );
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error archiving file " . basename( $file ) . ": " . $e->getMessage() );
        }
    }

    protected function move_to_failed( $file ) {
        try {
            $dateDir = $this->failed_dir . $this->now( 'Y-m-d' ) . '/';
            if ( ! file_exists( $dateDir ) ) wp_mkdir_p( $dateDir );
            $dest = $dateDir . basename( $file );
            if ( rename( $file, $dest ) ) {
                CHS_Logger::log( "Moved to failed: " . basename( $file ) . " -> {$dateDir}" );
            } else {
                CHS_Logger::log( "Failed to move to failed: " . basename( $file ) );
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error moving to failed " . basename( $file ) . ": " . $e->getMessage() );
        }
    }

    /* ---------------- Utility ---------------- */

    public function get_parsed() {
        return $this->parsed;
    }

    protected function recursive_remove_dir( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $it as $item ) {
            if ( $item->isDir() ) @rmdir( $item->getRealPath() ); else @unlink( $item->getRealPath() );
        }
        @rmdir( $dir );
    }
}
