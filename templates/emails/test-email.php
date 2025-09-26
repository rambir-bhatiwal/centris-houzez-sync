<?php
if (!defined('ABSPATH')) exit;

class CHS_TestMail {

    protected string $recipients;
    protected string $subjectPrefix;

    public function __construct(string $recipients = '', string $subjectPrefix = '[Centris Sync]') {
        $this->recipients    = sanitize_text_field($recipients);
        $this->subjectPrefix = sanitize_text_field($subjectPrefix);
        CHS_Utils::handleSendEmail();
    }

    /**
     * Send test email
     *
     * @return bool True if sent successfully, false otherwise
     */
    public function send(): bool {
        try{
        if (empty($this->recipients)) {
            return false;
        }

        $subject = $this->subjectPrefix . ' Test Email';
        $body    = '<p>This is a test email to verify your Centris Sync settings.</p>';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

//        return wp_mail($this->recipients, $subject, $body, $headers);
        return mail($this->recipients, $subject, $body, $headers);

    }catch (\Throwable $e) {
            CHS_Logger::logs("Error: " . $e->getMessage());
            return false;
    }
    }
}
