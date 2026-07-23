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
     * Extended queue that also stores report_date_from / report_date_to for retry regeneration.
     */
    private function queueWithPeriod(
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath,
        ?string $reportPeriod,
        ?string $dateFrom,
        ?string $dateTo
    ): string {
        $id  = uuid_v4();
        $db  = Database::connection();

        // Ensure the period columns exist (self-heal)
        foreach (['report_date_from', 'report_date_to'] as $col) {
            $exists = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'email_logs'
                    AND COLUMN_NAME  = '{$col}'"
            )->fetchColumn();
            if ($exists === 0) {
                $db->exec("ALTER TABLE `email_logs`
                            ADD COLUMN `{$col}` DATE DEFAULT NULL
                            AFTER `simulated_date`");
            }
        }

        $db->prepare(
            "INSERT INTO email_logs
               (id, recipient, subject, report_period, body_preview, attachment_path,
                status, retry_count, next_retry_at, report_date_from, report_date_to)
             VALUES (?, ?, ?, ?, ?, ?, 'queued', 0, NOW(), ?, ?)"
        )->execute([
            $id,
            $to,
            $subject,
            $reportPeriod,
            substr(strip_tags($body), 0, 500),
            $attachmentPath,
            $dateFrom,
            $dateTo,
        ]);
        return $id;
    }

    /**
     * Deliver an email with proper BCC envelope separation (P7).
     * headerTo / headerCc appear in the visible To: / Cc: headers.
     * envelopeRecipients contains all SMTP RCPT TO targets (including BCC).
     */
    private function deliverLogWithBcc(
        string  $logId,
        string  $headerTo,
        string  $headerCc,
        array   $envelopeRecipients,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): bool {
        Database::connection()->prepare(
            "UPDATE email_logs SET status = 'retrying', updated_at = NOW() WHERE id = ?"
        )->execute([$logId]);

        $result = $this->deliverSmtpWithBcc(
            $headerTo, $headerCc, $envelopeRecipients,
            $subject, $body, $attachmentPath
        );

        if ($result['ok']) {
            Database::connection()->prepare(
                "UPDATE email_logs
                 SET status = 'sent', sent_at = NOW(), last_error = NULL, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$logId]);
            return true;
        }

        $interval   = (int) $this->cfg->get('email_retry_interval', 60);
        $maxRetries = (int) $this->cfg->get('email_max_retries', 5);
        Database::connection()->prepare(
            "UPDATE email_logs
             SET status       = IF(retry_count + 1 >= ?, 'failed', 'queued'),
                 retry_count  = retry_count + 1,
                 last_error   = ?,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 updated_at   = NOW()
             WHERE id = ?"
        )->execute([$maxRetries, $result['error'], $interval, $logId]);
        return false;
    }

    /**
     * Send immediately and log the outcome. Returns true on success.
     * (Legacy entry point — does not store period dates or handle BCC separately.)
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
     * Send an attendance report email with proper To/CC/BCC separation and
     * period date storage for reliable retry attachment regeneration.
     *
     * Called by EmailScheduleService::executeReport().
     * This is the canonical send path for all scheduled reports.
     *
     * @param string      $to             Primary recipient(s) — comma-separated
     * @param string      $cc             CC recipients — comma-separated (shown in headers)
     * @param string      $bcc            BCC recipients — comma-separated (envelope only, hidden from headers)
     * @param string      $subject
     * @param string      $htmlBody
     * @param string|null $attachmentPath Path to .xlsx or .zip file
     * @param string|null $reportPeriod   Human-readable label e.g. "July 2026 (1–15)"
     * @param string|null $dateFrom       YYYY-MM-DD period start (stored for retry)
     * @param string|null $dateTo         YYYY-MM-DD period end   (stored for retry)
     * @return bool
     */
    public function sendWithPeriod(
        string  $to,
        string  $cc,
        string  $bcc,
        string  $subject,
        string  $htmlBody,
        ?string $attachmentPath,
        ?string $reportPeriod,
        ?string $dateFrom,
        ?string $dateTo
    ): bool {
        // All envelope recipients: To + CC + BCC
        $envelopeRecipients = array_filter(
            array_map('trim', explode(',', implode(',', [$to, $cc, $bcc])))
        );

        // Visible header recipients: To + CC only (BCC excluded)
        $headerTo = $to;
        $headerCc = $cc;

        // Create the log row with period dates stored for retry
        $logId = $this->queueWithPeriod(
            $to, $subject, $htmlBody, $attachmentPath,
            $reportPeriod, $dateFrom, $dateTo
        );

        $ok = $this->deliverLogWithBcc(
            $logId, $headerTo, $headerCc,
            $envelopeRecipients,
            $subject, $htmlBody, $attachmentPath
        );

        // Clean up attachment file after delivery attempt (success or first failure)
        // On retry, the file will be regenerated from stored period dates.
        if ($attachmentPath && is_file($attachmentPath)) {
            @unlink($attachmentPath);
        }

        return $ok;
    }

    /**
     * Manually resend a specific email_log entry.
     * If report_date_from / report_date_to are stored, the Excel attachment
     * is regenerated rather than relying on the original temp file (P5).
     */
    public function resend(string $logId): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM email_logs WHERE id = ?');
        $stmt->execute([$logId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        // Attempt to regenerate the Excel attachment from stored period dates (P5)
        $attachmentPath = null;
        $dateFrom = $row['report_date_from'] ?? null;
        $dateTo   = $row['report_date_to']   ?? null;
        if ($dateFrom && $dateTo) {
            try {
                $periodLabel    = $row['report_period'] ?? 'Report';
                $excelService   = new AttendanceExcelReportService();
                $attachmentPath = $excelService->generateForPeriod($dateFrom, $dateTo, $periodLabel);
            } catch (\Throwable $e) {
                error_log('Retry attachment regeneration failed: ' . $e->getMessage());
            }
        }

        // Fall back to original attachment path if regeneration failed and file still exists
        if (!$attachmentPath && !empty($row['attachment_path']) && is_file($row['attachment_path'])) {
            $attachmentPath = $row['attachment_path'];
        }

        $ok = $this->deliverLog(
            $row['id'],
            $row['recipient'],
            $row['subject'],
            $row['body_preview'] ?? '',
            $attachmentPath
        );

        // Clean up regenerated file after delivery attempt
        if ($attachmentPath && is_file($attachmentPath)) {
            @unlink($attachmentPath);
        }

        return $ok;
    }

    /**
     * Send a test email to verify SMTP settings.
     */
    public function sendTest(string $to): array
    {
        $companyName = (new SettingsService())->getCompanyName();
        $companyAbbreviation = (new SettingsService())->getCompanyAbbreviation();
        $subject = '[' . $companyAbbreviation . '] Test Email — ' . date('Y-m-d H:i:s');
        $body    = '<p>This is a test email from ' . e($companyName) . ' — Attendance Management Portal.</p>'
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
    /**
     * SMTP delivery with proper BCC support (P7).
     *
     * $headerTo / $headerCc  — appear in the visible To: / Cc: message headers.
     * $envelopeRecipients    — ALL SMTP RCPT TO targets, including BCC addresses.
     *                          BCC recipients receive the email but are NOT
     *                          listed in any visible header.
     *
     * Returns ['ok' => bool, 'error' => string].
     */
    private function deliverSmtpWithBcc(
        string  $headerTo,
        string  $headerCc,
        array   $envelopeRecipients,
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
            return ['ok' => false, 'error' => 'SMTP not configured.'];
        }
        if (empty($envelopeRecipients)) {
            return ['ok' => false, 'error' => 'No recipients specified.'];
        }

        try {
            $boundary   = '==Multipart_' . bin2hex(random_bytes(8));
            $companyAbbreviation = (new SettingsService())->getCompanyAbbreviation();
            $messageId  = '<' . uuid_v4() . '@' . strtolower($companyAbbreviation) . '>';
            $date       = date('r');
            $fromHeader = '"' . addslashes($fromName) . '" <' . $fromEmail . '>';

            // Build MIME — visible headers only contain To and Cc (not BCC)
            $hasAttachment = $attachmentPath && is_file($attachmentPath);
            $mime = $this->buildMimeWithCc(
                $fromHeader, $headerTo, $headerCc,
                $subject, $date, $messageId,
                $body, $attachmentPath, $boundary, $hasAttachment
            );

            // Open socket
            if ($encryption === 'ssl') {
                $conn = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
            } else {
                $conn = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
            }
            if (!$conn) {
                return ['ok' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];
            }
            stream_set_timeout($conn, 15);

            $read = function () use ($conn): string {
                $response = '';
                while ($line = fgets($conn, 512)) {
                    $response .= $line;
                    if (isset($line[3]) && $line[3] === ' ') break;
                }
                return $response;
            };
            $write = fn(string $cmd) => fwrite($conn, $cmd . "\r\n");

            $read(); // greeting
            $write("EHLO {$host}"); $read();

            if ($encryption === 'tls') {
                $write('STARTTLS'); $read();
                stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO {$host}"); $read();
            }

            $write('AUTH LOGIN');           $read();
            $write(base64_encode($username)); $read();
            $write(base64_encode($password));
            $authResp = $read();
            if (!str_starts_with($authResp, '235')) {
                fclose($conn);
                return ['ok' => false, 'error' => 'SMTP auth failed: ' . trim($authResp)];
            }

            $write("MAIL FROM:<{$fromEmail}>"); $read();

            // SMTP envelope: send to ALL recipients (To + CC + BCC)
            foreach ($envelopeRecipients as $r) {
                if (!empty($r)) {
                    $write("RCPT TO:<{$r}>"); $read();
                }
            }

            $write('DATA'); $read();
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

    /**
     * Build MIME message with separate visible To: and Cc: headers.
     * BCC is intentionally excluded from all headers.
     */
    private function buildMimeWithCc(
        string  $from,
        string  $to,
        string  $cc,
        string  $subject,
        string  $date,
        string  $messageId,
        string  $body,
        ?string $attachmentPath,
        string  $boundary,
        bool    $hasAttachment
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $ccHeader = !empty($cc) ? "Cc: {$cc}\r\n" : '';

        if ($hasAttachment) {
            $headers  = "From: {$from}\r\nTo: {$to}\r\n{$ccHeader}";
            $headers .= "Subject: {$encodedSubject}\r\nDate: {$date}\r\nMessage-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $mime  = $headers . "\r\n--{$boundary}\r\n";
            $mime .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($body)) . "\r\n";

            $fileName    = basename((string) $attachmentPath);
            $fileContent = base64_encode((string) file_get_contents((string) $attachmentPath));
            $mimeType    = $this->mimeTypeFor($fileName);

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $mime .= chunk_split($fileContent) . "\r\n--{$boundary}--";
        } else {
            $headers  = "From: {$from}\r\nTo: {$to}\r\n{$ccHeader}";
            $headers .= "Subject: {$encodedSubject}\r\nDate: {$date}\r\nMessage-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $mime     = $headers . "\r\n" . chunk_split(base64_encode($body));
        }
        return $mime;
    }

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
