<?php
if (!defined('ABSPATH')) exit;

class CHS_SummaryMail
{

    protected string $recipients;
    protected string $subjectPrefix;
    protected string $logFile = CHS_LOG_FILE;
    protected string $errorLogFile = CHS_ERROR_LOG_FILE;
    protected string $lastSummaryFile = '';

    public function __construct(string $recipients = '', string $subjectPrefix = '[Centris Sync]')
    {
        $this->recipients    = sanitize_text_field($recipients);
        $this->subjectPrefix = sanitize_text_field($subjectPrefix);
        CHS_Utils::handleSendEmail();
        $this->lastSummaryFile = get_option('chs_last_json_file', '');
    }

    /**
     * Send summary email
     *
     * @return bool True if sent successfully, false otherwise
     */
   public function send(): bool
{
    try {
        if (empty($this->recipients)) {
            CHS_Logger::logs("No recipients provided for summary email.");
            return false;
        }

    
        $subject = $this->subjectPrefix . ' Summary Report ';
        $body    = $this->email_body();

        // wp_mail allows headers as array
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Attach summary file if available
        $attachments = [];

            $chs_attach_log = get_option('chs_attach_log') ?? 'NO'; // default to 'always'
            if (( $chs_attach_log === 'yes' ||$chs_attach_log === 'YES') && file_exists($this->logFile)) {
                    if (!empty($this->lastSummaryFile) && file_exists($this->lastSummaryFile)) {
                        $attachments[] = $this->lastSummaryFile;
                    }
            }

        // Use wp_mail instead of raw mail()
        $sent = wp_mail($this->recipients, $subject, $body, $headers, $attachments);

        if (!$sent) {
            CHS_Logger::logs("wp_mail failed to send summary email.");
        }

        return $sent;
    } catch (\Throwable $e) {
        CHS_Logger::logs("Error sending summary email: " . $e->getMessage());
        return false;
    }
}


    protected function email_body()
    {
        if (empty($this->lastSummaryFile) || !file_exists($this->lastSummaryFile)) {
            CHS_Logger::logs("Summary data file not found: " . $this->lastSummaryFile);
            return "<p>No summary data file available. File not found: " . esc_html($this->lastSummaryFile) . "</p>";
        }

        $summary = json_decode(file_get_contents($this->lastSummaryFile), true);
        if (empty($summary)) {
            CHS_Logger::logs("Summary data is empty or invalid JSON in file: " . $this->lastSummaryFile);
            return '<p>No summary data available.</p>';
        }

        $lastErros =  CHS_Utils::getLastErrors(CHS_ERROR_LOG_FILE, $summary['started_at'], $summary['ended_at'], 10);

        ob_start();
?>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    color: #333;
                }

                h2 {
                    margin-top: 20px;
                    color: #444;
                }

                table {
                    border-collapse: collapse;
                    width: 100%;
                    margin-top: 10px;
                }

                th,
                td {
                    border: 1px solid #ddd;
                    padding: 6px;
                    text-align: left;
                }

                th {
                    background: #f5f5f5;
                }
            </style>
        </head>

        <body>

            <h2>Centris Sync Summary</h2>

            <p><strong>Run status:</strong> OK<br>
                <strong>Duration:</strong> <?= $summary['duration'] ?><br>
                <strong>Started at:</strong> <?= $summary['started_at'] ?><br>
                <strong>Ended at:</strong> <?= $summary['ended_at'] ?>
            </p>

            <hr>

            <h2>System Info</h2>
            <ul>
                <li><strong>Site URL:</strong> <?= $summary['system']['site_url'] ?></li>
                <li><strong>Environment:</strong> <?= $summary['system']['environment'] ?></li>
                <li><strong>Plugin Version:</strong> <?= $summary['system']['plugin_version'] ?></li>
            </ul>

            <hr>

            <h2>Listings</h2>
            <table>
                <tr>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Unpublished</th>
                    <th>Unchanged</th>
                    <th>Reactivated</th>
                </tr>
                <tr>
                    <td><?= $summary['changes_tracked']['created'] ?></td>
                    <td><?= $summary['changes_tracked']['updated'] ?></td>
                    <td><?= $summary['changes_tracked']['unpublished'] ?></td>
                    <td><?= $summary['changes_tracked']['unchanged'] ?></td>
                    <td><?= $summary['changes_tracked']['reactivated'] ?></td>
                </tr>
            </table>

            <hr>

            <h2>Photos</h2>
            <?php foreach ($summary['photos_counter_file_bases'] as $file => $counts): ?>
                <p><strong>File:</strong> <?= $file ?></p>
                <table>
                    <tr>
                        <th>Total Processed</th>
                        <th>Skipped</th>
                        <th>Downloaded New</th>
                        <th>Downloaded Updated</th>
                        <th>Removed</th>
                        <th>Failed</th>
                    </tr>
                    <tr>
                        <td><?= $counts['total_photos_processed'] ?></td>
                        <td><?= $counts['skipped_unchanged'] ?></td>
                        <td><?= $counts['downloaded_new'] ?></td>
                        <td><?= $counts['downloaded_updated'] ?></td>
                        <td><?= $counts['removed'] ?></td>
                        <td><?= $counts['total_photos_failed'] ?></td>
                    </tr>
                </table>
            <?php endforeach; ?>

            <hr>

            <h2>Files Processed</h2>
            <table>
                <tr>
                    <th>Path</th>
                    <th>Size (MB)</th>
                    <th>Modified</th>
                </tr>
                <?php foreach ($summary['detected_files'] as $file): ?>
                    <tr>
                        <td><?= $file['path'] ?></td>
                        <td><?= $file['size_mb'] ?></td>
                        <td><?= $file['mtime'] ?> (America/Toronto)</td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <hr>

            <h2>Errors</h2>
            <?php if (!empty($lastErros)): ?>
                <table>
                    <tr>
                        <th>Timestamp</th>
                        <th>Error Message</th>
                    </tr>
                    <?php foreach ($lastErros as $error):
                        $parts = explode(' - ', $error, 2);
                        $timestamp = $parts[0] ?? '';
                        $message = $parts[1] ?? $error;
                    ?>
                        <tr>
                            <td><?= esc_html($timestamp) ?></td>
                            <td><?= esc_html($message) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No errors reported in this run. (Show top 10 here if present)</p>
            <?php endif; ?>
            <hr>

            <h2>Links</h2>
            <ul>
                <li><a href="<?= site_url() . '/uploads/centris-sync/summaries/' . basename($this->lastSummaryFile); ?>" download="summary.json">Summary File</a></li>
            </ul>

        </body>

        </html>
<?php
        $emailBody = ob_get_clean();
        return $emailBody;
    }
}