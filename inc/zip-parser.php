<?php
if (! defined( 'ABSPATH' )) exit;
require_once CHS_PLUGIN_DIR . 'inc/constants.php';

class CHS_ZipReader {

    protected $base_dir;
    protected $zip_dir;
    protected $extract_dir;
    protected $archive_dir;
    protected $failed_dir;

    // Keep parsed data per run
    protected $parsed = [];

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->base_dir    = rtrim( $upload_dir['basedir'], '/\\' ) . '/centris-sync/';
        // $this->zip_dir     = $this->base_dir . 'zips/';
        $this->zip_dir     = CHS_SOURCE_PATH . '/';  // use source path directly
        $this->extract_dir = CHS_BASE_DIR . 'extracted/';
        $this->archive_dir = CHS_BASE_DIR . 'archived/';
        $this->failed_dir  = CHS_BASE_DIR . 'failed/';

        foreach ( [ $this->zip_dir, $this->extract_dir, $this->archive_dir, $this->failed_dir ] as $d ) {
            if ( ! file_exists( $d ) ) {
                wp_mkdir_p( $d );
            }
        }
    }

    /**
     * Process all ZIPs (keeps the rest of class behavior same)
     */
    public function process_all_zips() {
        $results = [];
        try {
            $zips = glob( $this->zip_dir . '*.{zip,ZIP}', GLOB_BRACE );
            if ( empty( $zips ) ) {
                CHS_Logger::log( "No ZIP files found in {$this->zip_dir}" );
                return $results;
            }

            CHS_Logger::log( "Found " . count( $zips ) . " ZIP(s) to process." );
            foreach ( $zips as $zip_file ) {
                $res = $this->process_single_zip( $zip_file );
                if ( $res !== null ) $results[] = $res;
            }
            CHS_Logger::log( "All ZIP processing finished." );
            return $results;
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error in process_all_zips: " . $e->getMessage() );
            return $results;
        }
    }

    /**
     * Process single zip (extract, scan, generic parse, archive)
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
            CHS_Logger::log( "Extracted ZIP: " . basename( $zip_file ) . " to {$run_dir}" );

            // Scan and log file summaries (counts + header preview)
            $scan = $this->scan_and_log_files( $run_dir );

            // GENERIC: parse every data file the same way, no hard-coded types
            $parsed = $this->generic_process_files( $run_dir, $run_id );

            // Archive original ZIP
            // $this->archive_zip( $zip_file );
            // Archive original ZIP (only if not in DEV mode)
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

            CHS_Logger::log( "Run {$run_id} completed: parsed files: " . implode( ', ', array_keys( $parsed ) ) );
            return $result;

        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error processing ZIP " . basename( $zip_file ) . ": " . $e->getMessage() );
            $this->move_to_failed( $zip_file );
            return null;
        }
    }

    /**
     * Scan directory: counts + header preview (keeps existing behavior)
     */
    protected function scan_and_log_files( $run_dir ) {
        $info = [];
        try {
            $files = glob( $run_dir . '*.{txt,TXT,csv,CSV,accdb,ACCDB}', GLOB_BRACE );
            CHS_Logger::log( "Run directory '{$run_dir}' contains " . count( $files ) . " data file(s)." );

            foreach ( $files as $file ) {
                $basename = basename( $file );
                $lines = $this->count_lines( $file );
                $first = $this->get_first_non_empty_line( $file );
                $delim = $first !== null ? $this->detect_delimiter( $first ) : ',';
                $header_fields = $first ? str_getcsv( $this->strip_bom( $first ), $delim ) : [];
                $header_preview = array_slice( array_map( 'trim', $header_fields ), 0, 12 );

                CHS_Logger::log( "Found file {$basename} — lines: {$lines} — header preview: " . ( empty( $header_preview ) ? '[none]' : implode( ' | ', $header_preview ) ) );

                $info[ $basename ] = [
                    'path' => $file,
                    'lines' => $lines,
                    'header_preview' => $header_preview,
                    'detected_delimiter' => $delim,
                ];
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error scanning run_dir {$run_dir}: " . $e->getMessage() );
        }

        return $info;
    }

    /**
     * GENERIC processing for all files (no hard-coded file type handling)
     * - parses each file into associative rows (header => value)
     * - logs file summary + up to first 2 data rows as preview
     * - stores parsed rows under $parsed[$basename]
     *
     * @param string $run_dir
     * @param string $run_id
     * @return array parsed results keyed by basename
     */
    protected function generic_process_files( $run_dir, $run_id ) {
        $results = [];

        try {
            $files = glob( $run_dir . '*.{txt,TXT,csv,CSV,accdb,ACCDB}', GLOB_BRACE );
            foreach ( $files as $file ) {
                $basename = basename( $file );

                // ACCDB: skip deep parsing (reference files), still record presence
                $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                if ( $ext === 'accdb' ) {
                    CHS_Logger::log( "Skipping ACCDB parsing (reference): {$basename}" );
                    $results[ $basename ] = [
                        'meta' => [ 'type' => 'accdb', 'path' => $file ],
                        'rows' => [],
                    ];
                    continue;
                }

                // Detect delimiter from first non-empty line
                $first = $this->get_first_non_empty_line( $file );
                $delim = $first !== null ? $this->detect_delimiter( $first ) : ',';

                // Parse generically (returns associative rows header=>value)
                $rows = $this->parse_generic_csv( $file, $delim );

                // Log summary + up to two sample rows
                $count = count( $rows );
                $sample = [];
                if ( $count > 0 ) {
                    $sample[] = $this->row_preview( $rows[0] );
                }
                if ( $count > 1 ) {
                    $sample[] = $this->row_preview( $rows[1] );
                }

                CHS_Logger::log( "Parsed {$basename}: rows={$count} — sample=" . ( empty( $sample ) ? '[none]' : implode(' || ', $sample) ) );

                // Store parsed
                $results[ $basename ] = [
                    'meta' => [
                        'path' => $file,
                        'delimiter' => $delim,
                        'lines' => $this->count_lines( $file ),
                    ],
                    'rows' => $rows,
                ];
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error in generic_process_files for {$run_dir}: " . $e->getMessage() );
        }

        // keep under this run id for later retrieval
        $this->parsed[ $run_id ] = $results;
        return $results;
    }

    /**
     * Parse a CSV/TXT generically and return associative rows (header => value)
     *
     * @param string $file
     * @param string $delimiter
     * @return array
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
                $headers = array_map( function( $h ) { return trim( preg_replace('/\s+/', '_', $h) ); }, $headers );
                break;
            }

            if ( empty( $headers ) ) {
                fclose( $handle );
                return $rows;
            }

            // read remaining rows using fgetcsv
            while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
                $all_empty = true;
                foreach ( $data as $c ) {
                    if ( trim( (string)$c ) !== '' ) { $all_empty = false; break; }
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

    /**
     * Produce a short preview string for a row (first few fields)
     */
    protected function row_preview( $row ) {
        if ( empty( $row ) || ! is_array( $row ) ) return '[empty]';
        // take first 6 fields
        $vals = array_slice( $row, 0, 6 );
        $pairs = [];
        $i = 0;
        foreach ( $vals as $k => $v ) {
            $v = is_string( $v ) ? preg_replace('/\s+/', ' ', trim( $v )) : $v;
            $v = (string) $v;
            if ( strlen( $v ) > 120 ) $v = substr( $v, 0, 117 ) . '...';
            $pairs[] = "{$k}=" . $v;
            $i++; if ($i >= 6) break;
        }
        return '[' . implode( ', ', $pairs ) . ']';
    }

    /* ----------------- Helpers ----------------- */

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

    protected function archive_zip( $zip_file ) {
        try {
            $dest = $this->archive_dir . basename( $zip_file );
            if ( rename( $zip_file, $dest ) ) {
                CHS_Logger::log( "Archived ZIP: " . basename( $zip_file ) );
            } else {
                CHS_Logger::log( "Failed to archive ZIP: " . basename( $zip_file ) );
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error archiving " . basename( $zip_file ) . ": " . $e->getMessage() );
        }
    }

    protected function move_to_failed( $zip_file ) {
        try {
            $dest = $this->failed_dir . basename( $zip_file );
            if ( rename( $zip_file, $dest ) ) {
                CHS_Logger::log( "Moved ZIP to failed: " . basename( $zip_file ) );
            } else {
                CHS_Logger::log( "Failed to move ZIP to failed: " . basename( $zip_file ) );
            }
        } catch ( \Throwable $e ) {
            CHS_Logger::log( "Error moving to failed " . basename( $zip_file ) . ": " . $e->getMessage() );
        }
    }

    /**
     * Return parsed data for external use
     */
    public function get_parsed() {
        return $this->parsed;
    }

    /**
     * Optional cleanup
     */
    protected function recursive_remove_dir( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $it as $item ) {
            if ( $item->isDir() ) @rmdir( $item->getRealPath() ); else @unlink( $item->getRealPath() );
        }
        @rmdir( $dir );
    }
}
