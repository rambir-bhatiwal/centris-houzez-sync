<?php
if (!defined('ABSPATH')) exit;

class CHS_FileDetector
{
    public static function detect($dir, $patternOpt = 'PIVOTELECOM*.TXT;PIVOTELECOM*.ZIP', $limit = 5)
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir) || !is_readable($dir)) {
            CHS_Logger::logs('Invalid directory: ' . $dir);
            return [];
        }

        $patternOpt = self::parsePatternsByExtWithPrefixSuffixCaseSensitive($patternOpt);
        $candidates = [];

        foreach (glob($dir . '/*', GLOB_NOSORT) ?: [] as $path) {

            if (!is_file($path)) continue;

            $filename = basename($path);
            $ext      = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
            $name     = pathinfo($filename, PATHINFO_FILENAME); // keep original case for matching

            // Skip if extension not in pattern rules
            if (!isset($patternOpt[$ext])) continue;

            $rules = $patternOpt[$ext];
            $matched = false;
            $matchedPrefix = null;

            // Check prefix
            foreach ($rules['prefix'] as $p) {
                if (str_starts_with($name, $p)) { // case-sensitive
                    $matched = true;
                    $matchedPrefix = $p;
                    break;
                }
            }

            // If no prefix matched, check suffix
            if (!$matched) {
                foreach ($rules['suffix'] as $s) {
                    if (str_ends_with($name, $s)) { // case-sensitive
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) continue;

            // Extract number part after prefix if prefix matched
            $number = null;
            if ($matchedPrefix !== null && preg_match('/^' . preg_quote($matchedPrefix, '/') . '(\d+)/', $name, $mm)) {
                $number = $mm[1];
            }

            $size = @filesize($path);
            if ($size === false || $size === 0) {
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

        // Sort by modification time (latest first)
        usort($candidates, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        $tz = new DateTimeZone('America/Toronto');
        $out = [];

        foreach (array_slice($candidates, 0, $limit) as $f) {
            $dt = new DateTime('@' . $f['mtime']);
            $dt->setTimezone($tz);

            $row = [
                'path'    => $f['path'],
                'size_mb' => self::formatSize($f['size']),
                'size'    => round($f['size'] / 1048576, 1),
                'mtime'   => $dt->format('Y-m-d H:i'),
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
        if (!is_numeric($bytes) || $bytes <= 0) {
            return '0 MB';
        }

        // Convert bytes to MB
        $sizeInMb = $bytes / 1048576;

        // Keep 2 decimal points
        return round($sizeInMb, 2) . ' MB';
    }

    public static function parsePatternsByExtWithPrefixSuffixCaseSensitive($patternString)
    {
        try {
            $patterns = array_map('trim', explode(';', $patternString));
            $results = [];

            foreach ($patterns as $pattern) {
                // Extract extension (keep original case)
                $extPos = strrpos($pattern, '.');
                if ($extPos !== false) {
                    $extension = substr($pattern, $extPos + 1);
                    $patternCore = substr($pattern, 0, $extPos);
                } else {
                    $extension = '';
                    $patternCore = $pattern;
                }

                // Convert extension to UPPERCASE for array key
                $extensionKey = strtoupper($extension);

                // Determine prefix or suffix
                $prefix = '';
                $suffix = '';
                if (str_starts_with($patternCore, '*') && str_ends_with($patternCore, '*')) {
                    $inner = trim($patternCore, '*');
                    $prefix = $inner;
                    $suffix = $inner;
                } elseif (str_starts_with($patternCore, '*')) {
                    $suffix = ltrim($patternCore, '*');
                } elseif (str_ends_with($patternCore, '*')) {
                    $prefix = rtrim($patternCore, '*');
                } else {
                    $prefix = $patternCore;
                }

                // Initialize extension arrays if not exists
                if (!isset($results[$extensionKey])) {
                    $results[$extensionKey] = [
                        'prefix' => [],
                        'suffix' => []
                    ];
                }

                // Store values (avoid duplicates)
                if ($prefix !== '' && !in_array($prefix, $results[$extensionKey]['prefix'], true)) {
                    $results[$extensionKey]['prefix'][] = $prefix;
                }
                if ($suffix !== '' && !in_array($suffix, $results[$extensionKey]['suffix'], true)) {
                    $results[$extensionKey]['suffix'][] = $suffix;
                }
            }

            return $results;
        } catch (\Throwable $e) {
            CHS_Logger::logs("Error detactor " . $e->getMessage());
        }
    }
}
