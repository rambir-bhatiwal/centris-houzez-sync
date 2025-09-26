<?php
if (!defined('ABSPATH')) exit;

class CHS_SummaryMail
{

    protected string $recipients;
    protected string $subjectPrefix;
    protected string $logFile = CHS_LOG_FILE;

    public function __construct(string $recipients = '', string $subjectPrefix = '[Centris Sync]')
    {
        $this->recipients    = sanitize_text_field($recipients);
        $this->subjectPrefix = sanitize_text_field($subjectPrefix);
        CHS_Utils::handleSendEmail();
    }

    /**
     * Send test email
     *
     * @return bool True if sent successfully, false otherwise
     */
    public function send(): bool
    {
        try {
            if (empty($this->recipients)) {
                return false;
            }

                      $subject = $this->subjectPrefix . ' Logs Summary';
            $body    = $this->getLogFileContents();
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            return wp_mail($this->recipients, $subject, $body, $headers);
        } catch (\Throwable $e) {
            CHS_Logger::logs("Error: " . $e->getMessage());
            return false;
        }
    }


        protected function getLogFileContents(): string
    {
        if (!file_exists($this->logFile)) {
            CHS_Logger::logs("Log file not found: " . $this->logFile);
            return '<p>No logs available. File not found: ' . esc_html($this->logFile) . '</p>';
        }

        $logs = file_get_contents($this->logFile);

        if (empty($logs)) {
            return '<p>No logs available.</p>';
        }

        // Escape HTML but preserve formatting with <pre>
        return '<h3>Centris Sync Logs</h3><pre style="font-family: monospace; white-space: pre-wrap; background:#f7f7f7; padding:10px; border:1px solid #ddd;">' 
                . esc_html($logs) 
                . '</pre>';
    }
}
