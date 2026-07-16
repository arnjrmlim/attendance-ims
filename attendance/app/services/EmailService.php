<?php

/**
 * EmailService
 *
 * Sends email via SMTP (raw socket with TLS/SSL support).
 * Does NOT require PHPMailer — uses PHP's built-in streams.
 * Queues failures to email_logs and supports retry.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class EmailService
{
    private SettingsService $cfg;

    public function __construct()
    {
        $this->cfg = new SettingsService();
    }

    /* ── Public API ─────────────────────────────────────────── */

    /**
     * Queue an email for delivery. Returns the email_log id.
     */
    public function queue(
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath = null,
        ?string $reportPeriod   = null
    ): string {
        $id   = uuid_v4();
        $stmt = Database::connection()->prepare(
            "INSERT INTO email_logs
             (id, recipient, subject, report_period, body_preview, attachment_path, status, retry_count, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, 'queued', 0, NOW())"
        );
        $stmt->execute([
            $id,
            $to,
            $subject,
            $reportPeriod,
            substr(strip_tags($body), 0, 500),
            $attachmentPath,
        ]);
        return $id;
    }

    /**
     * Send immediately and log the outcome. Returns true on success.
     */
    public function send(
        string  $to,
        string  $subject,
        string  $htmlBody,
        ?string $attachmentPath = null,
        ?string $reportPeriod   = null
    ): bool {
        $logId = $this->queue($to, $subject, $htmlBody, $attachmentPath, $reportPeriod);
        return $this->deliverLog($logId, $to, $subject, $htmlBody, $attachmentPath);
    }

    /**
     * Re-attempt all queued/failed emails that are due for retry.
     */
    public function retryPending(): int
    {
        $maxRetries = (int) $this->cfg->get('email_max_retries', 5);
        $stmt = Database::connection()->prepare(
            "SELECT * FROM email_logs
             WHERE status IN ('queued','failed','retrying')
               AND retry_count < ?
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY created_at ASC"
        );
        $stmt->execute([$maxRetries]);
        $rows = $stmt->fetchAll();

        $sent = 0;
        foreach ($rows as $row) {
            $ok = $this->deliverLog(
                $row['id'],
                $row['recipient'],
                $row['subject'],
                '', // body not stored in full — rebuild from attachment or skip
                $row['attachment_path']
            );
            if ($ok) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Manually resend a specific email_log entry.
     */
    public function resend(string $logId): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM email_logs WHERE id = ?');
        $stmt->execute([$logId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return $this->deliverLog(
            $row['id'],
            $row['recipient'],
            $row['subject'],
            $row['body_preview'] ?? '',
            $row['attachment_path']
        );
    }

    /**
     * Send a test email to verify SMTP settings.
     */
    public function sendTest(string $to): array
    {
        $subject = '[AMS] Test Email — ' . date('Y-m-d H:i:s');
        $body    = '<p>This is a test email from the Attendance Management System.</p>'
                 . '<p>If you received this, your SMTP settings are configured correctly.</p>';

        $ok = $this->deliverSmtp($to, $subject, $body, null);
        return ['success' => $ok['ok'], 'error' => $ok['error'] ?? ''];
    }

    /* ── Internal ────────────────────────────────────────────── */

    private function deliverLog(
        string  $logId,
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): bool {
        // Mark as retrying
        Database::connection()->prepare(
            "UPDATE email_logs SET status = 'retrying', updated_at = NOW() WHERE id = ?"
        )->execute([$logId]);

        $result = $this->deliverSmtp($to, $subject, $body, $attachmentPath);

        if ($result['ok']) {
            Database::connection()->prepare(
                "UPDATE email_logs
                 SET status = 'sent', sent_at = NOW(), last_error = NULL, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$logId]);

            // Notify admins of success if there were prior failures
            return true;
        }

        // Update retry info
        $interval = (int) $this->cfg->get('email_retry_interval', 60);
        $maxRetries = (int) $this->cfg->get('email_max_retries', 5);
        Database::connection()->prepare(
            "UPDATE email_logs
             SET status      = IF(retry_count + 1 >= ?, 'failed', 'queued'),
                 retry_count = retry_count + 1,
                 last_error  = ?,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 updated_at  = NOW()
             WHERE id = ?"
        )->execute([$maxRetries, $result['error'], $interval, $logId]);

        return false;
    }

    /**
     * Low-level SMTP delivery via PHP streams (TLS/SSL).
     * Returns ['ok' => bool, 'error' => string].
     */
    private function deliverSmtp(
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): array {
        $host       = (string) $this->cfg->get('smtp_host', '');
        $port       = (int)    $this->cfg->get('smtp_port', 587);
        $username   = (string) $this->cfg->get('smtp_username', '');
        $password   = $this->decryptPassword((string) $this->cfg->get('smtp_password', ''));
        $encryption = strtolower((string) $this->cfg->get('smtp_encryption', 'tls'));
        $fromName   = (string) $this->cfg->get('smtp_from_name', 'Attendance System');
        $fromEmail  = (string) $this->cfg->get('smtp_from_email', '');

        if (empty($host) || empty($fromEmail)) {
            return ['ok' => false, 'error' => 'SMTP not configured. Set smtp_host and smtp_from_email in System Settings.'];
        }

        try {
            $boundary  = '==Multipart_' . bin2hex(random_bytes(8));
            $messageId = '<' . uuid_v4() . '@ams>';
            $date      = date('r');
            $fromHeader = '"' . addslashes($fromName) . '" <' . $fromEmail . '>';

            // Build MIME message
            $hasAttachment = $attachmentPath && is_file($attachmentPath);
            $mime = $this->buildMime(
                $fromHeader, $to, $subject, $date, $messageId,
                $body, $attachmentPath, $boundary, $hasAttachment
            );

            // Open socket
            $context = stream_context_create();
            if ($encryption === 'ssl') {
                $conn = stream_socket_client(
                    "ssl://{$host}:{$port}", $errno, $errstr, 15,
                    STREAM_CLIENT_CONNECT, $context
                );
            } else {
                $conn = stream_socket_client(
                    "tcp://{$host}:{$port}", $errno, $errstr, 15
                );
            }

            if (!$conn) {
                return ['ok' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];
            }

            stream_set_timeout($conn, 15);

            $read = function () use ($conn): string {
                $response = '';
                while ($line = fgets($conn, 512)) {
                    $response .= $line;
                    if (isset($line[3]) && $line[3] === ' ') {
                        break;
                    }
                }
                return $response;
            };
            $write = function (string $cmd) use ($conn): void {
                fwrite($conn, $cmd . "\r\n");
            };

            $read(); // server greeting
            $write("EHLO {$host}");
            $ehlo = $read();

            if ($encryption === 'tls') {
                $write('STARTTLS');
                $read();
                stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO {$host}");
                $read();
            }

            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($username));
            $read();
            $write(base64_encode($password));
            $authResp = $read();
            if (!str_starts_with($authResp, '235')) {
                fclose($conn);
                return ['ok' => false, 'error' => 'SMTP authentication failed: ' . trim($authResp)];
            }

            $write("MAIL FROM:<{$fromEmail}>");
            $read();
            // Support multiple recipients
            foreach (array_map('trim', explode(',', $to)) as $recipient) {
                $write("RCPT TO:<{$recipient}>");
                $read();
            }

            $write('DATA');
            $read();
            fwrite($conn, $mime . "\r\n.\r\n");
            $dataResp = $read();

            $write('QUIT');
            fclose($conn);

            if (!str_starts_with($dataResp, '250')) {
                return ['ok' => false, 'error' => 'SMTP DATA rejected: ' . trim($dataResp)];
            }

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildMime(
        string  $from,
        string  $to,
        string  $subject,
        string  $date,
        string  $messageId,
        string  $body,
        ?string $attachmentPath,
        string  $boundary,
        bool    $hasAttachment
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        if ($hasAttachment) {
            $headers  = "From: {$from}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "Date: {$date}\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $mime  = $headers . "\r\n";
            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($body)) . "\r\n";

            $fileName    = basename((string) $attachmentPath);
            $fileContent = base64_encode((string) file_get_contents((string) $attachmentPath));
            $mimeType    = $this->mimeTypeFor($fileName);

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $mime .= chunk_split($fileContent) . "\r\n";
            $mime .= "--{$boundary}--";
        } else {
            $headers  = "From: {$from}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "Date: {$date}\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $mime     = $headers . "\r\n" . chunk_split(base64_encode($body));
        }

        return $mime;
    }

    private function mimeTypeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'csv'  => 'text/csv',
            'zip'  => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    /* ── Password encryption helpers ─────────────────────────── */

    public static function encryptPassword(string $plain): string
    {
        $key = self::encKey();
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    private function decryptPassword(string $stored): string
    {
        if (empty($stored)) {
            return '';
        }
        try {
            $key  = self::encKey();
            $raw  = base64_decode($stored);
            $iv   = substr($raw, 0, 16);
            $enc  = substr($raw, 16);
            $plain = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
            return $plain !== false ? $plain : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function encKey(): string
    {
        // Derive a 32-byte key from the app base_url (unique per installation).
        return hash('sha256', (string) config('base_url', 'ams-default-key'), true);
    }
}
