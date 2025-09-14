<?php
if (!defined('ABSPATH')) exit;

class CHS_FileDetector
{
    public static function detect($dir, $patternOpt = 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP', $limit = 5)
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir) || !is_readable($dir)) {
            CHS_Logger::log('Invalid directory: ' . $dir);
            return [];
        }

        $patterns = array_filter(array_map('trim', explode(';', $patternOpt)));
        $candidates = [];

        // Example: PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP
        $patterns = array_filter(array_map('trim', explode(';', $patternOpt)));

        $prefixes = [];
        $extensions = [];

        foreach ($patterns as $pat) {
            // split into prefix and extension
            if (preg_match('/^([A-Z0-9_]+)\*\.([A-Z0-9]+)/i', $pat, $m)) {
                $prefixes[] = strtoupper($m[1]);
                $extensions[] = strtoupper($m[2]);
            }
        }

        foreach (glob($dir . '/*', GLOB_NOSORT) ?: [] as $path) {

            if (!is_file($path)) continue;

            // echo filesize($path);
            $filename = basename($path);
            $ext      = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
            $name     = strtoupper(pathinfo($filename, PATHINFO_FILENAME));

            // check extension
            if (!in_array($ext, $extensions, true)) continue;

            // check prefix
            $matchedPrefix = null;
            foreach ($prefixes as $p) {
                // use strpos for compatibility instead of str_starts_with
                if (strpos($name, $p) === 0) {
                    $matchedPrefix = $p;
                    break;
                }
            }
            if (!$matchedPrefix) continue;

            // extract number part after prefix
            $number = null;
            if (preg_match('/^' . preg_quote($matchedPrefix, '/') . '(\d+)/i', $name, $mm)) {
                $number = $mm[1];
            }

            $size = @filesize($path);
            if ($size === false || $size === 0) {
                // fallback with stat()
                $stat = @stat($path);
                $size = $stat ? $stat['size'] : 0;
            }

            $candidates[] = [
                'path'   => $path,
                'size'   => $size,
                'mtime'  => filemtime($path),
                'number' => $number,
            ];
        }


        usort($candidates, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        $tz = new DateTimeZone('America/Toronto');
        $out = [];

        foreach (array_slice($candidates, 0, $limit) as $f) {
            $dt = new DateTime('@' . $f['mtime']);
            $dt->setTimezone($tz);

            $row = [
                'path'  => $f['path'],
                'size_mb'  => self::formatSize($f['size']), // formatted size
                'size' => round($f['size'] / 1048576, 1), // size in MB for logging
                'mtime' => $dt->format('Y-m-d H:i'),
            ];

            CHS_Logger::log(sprintf(
                '[source] found %s size=%s mtime=%s',
                $row['path'],
                $row['size_mb'],
                $row['mtime']
            ));

            $out[] = $row;
        }
        return $out;
    }

    public static function formatSize($bytes)
    {
        if (!is_numeric($bytes)) {
            return '0 B';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}
