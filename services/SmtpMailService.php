<?php
/**
 * SmtpMailService — Send emails via user's SMTP settings
 * Uses fsockopen for real SMTP connection (no external library)
 * Supports file attachments via multipart MIME
 */
class SmtpMailService
{
    /**
     * Send email using user's SMTP settings
     * @param array $attachments Optional [{name, tmp_name, type, size}, ...]
     */
    public static function send(int $userId, string $to, string $subject, string $htmlBody, ?string $inReplyTo = null, array $attachments = []): bool
    {
        $settingModel = new EmailSetting();
        $settings = $settingModel->getByUserId($userId);
        if (!$settings) throw new Exception('Keine E-Mail-Einstellungen gefunden.');

        $from     = $settings['email_address'];
        $smtpHost = $settings['smtp_host'] ?: $settings['imap_host'];
        $smtpPort = (int) ($settings['smtp_port'] ?: 587);
        $smtpUser = $settings['smtp_username'] ?: $settings['imap_username'];
        $smtpEnc  = $settings['smtp_encryption'] ?: 'tls';

        // Decrypt password
        $password = '';
        if (!empty($settings['smtp_password_encrypted'])) {
            $password = Encryption::decrypt($settings['smtp_password_encrypted']);
        } elseif (!empty($settings['imap_password_encrypted'])) {
            $password = Encryption::decrypt($settings['imap_password_encrypted']);
        }

        if (!$smtpHost || !$smtpUser || !$password) {
            throw new Exception('SMTP-Einstellungen unvollständig.');
        }

        // Build MIME message
        $message = self::buildMimeMessage($from, $to, $subject, $htmlBody, $inReplyTo, $attachments);

        // Send via SMTP
        self::smtpSend($smtpHost, $smtpPort, $smtpEnc, $smtpUser, $password, $from, $to, $message);

        return true;
    }

    /**
     * Build complete MIME message (headers + body)
     */
    private static function buildMimeMessage(string $from, string $to, string $subject, string $htmlBody, ?string $inReplyTo, array $attachments): string
    {
        $hasAttachments = !empty($attachments);
        $mixedBoundary = md5(uniqid('mixed_' . time()));
        $altBoundary   = md5(uniqid('alt_' . time()));

        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // Headers
        $msg  = "From: {$from}\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: {$subject}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Reply-To: {$from}\r\n";
        $msg .= "X-Mailer: RohrApp+\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "Message-ID: <" . uniqid('rohrapp_') . "@" . explode('@', $from)[1] . ">\r\n";

        if ($inReplyTo) {
            $msg .= "In-Reply-To: {$inReplyTo}\r\n";
            $msg .= "References: {$inReplyTo}\r\n";
        }

        if ($hasAttachments) {
            $msg .= "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"\r\n";
        } else {
            $msg .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n";
        }

        $msg .= "\r\n"; // End of headers

        // Plain text
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');

        if ($hasAttachments) {
            $msg .= "--{$mixedBoundary}\r\n";
            $msg .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        }

        $msg .= "--{$altBoundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($plainText)) . "\r\n";

        $msg .= "--{$altBoundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $msg .= "--{$altBoundary}--\r\n";

        if ($hasAttachments) {
            foreach ($attachments as $att) {
                if (!file_exists($att['tmp_name'])) continue;
                $fileData = file_get_contents($att['tmp_name']);
                if ($fileData === false) continue;

                $fileName = '=?UTF-8?B?' . base64_encode($att['name']) . '?=';
                $mimeType = $att['type'] ?: 'application/octet-stream';

                $msg .= "--{$mixedBoundary}\r\n";
                $msg .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
                $msg .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
                $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $msg .= chunk_split(base64_encode($fileData)) . "\r\n";
            }
            $msg .= "--{$mixedBoundary}--\r\n";
        }

        return $msg;
    }

    /**
     * Send email via SMTP using fsockopen
     */
    private static function smtpSend(string $host, int $port, string $encryption, string $username, string $password, string $from, string $to, string $message): void
    {
        // Connect
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);

        if (!$socket) {
            throw new Exception("SMTP-Verbindung fehlgeschlagen: {$errstr} ({$errno})");
        }

        // Set timeout
        stream_set_timeout($socket, 30);

        try {
            self::smtpRead($socket, 220); // Greeting

            self::smtpWrite($socket, "EHLO " . gethostname());
            self::smtpRead($socket, 250);

            // STARTTLS for TLS
            if ($encryption === 'tls') {
                self::smtpWrite($socket, "STARTTLS");
                self::smtpRead($socket, 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('STARTTLS fehlgeschlagen');
                }
                self::smtpWrite($socket, "EHLO " . gethostname());
                self::smtpRead($socket, 250);
            }

            // AUTH LOGIN
            self::smtpWrite($socket, "AUTH LOGIN");
            self::smtpRead($socket, 334);
            self::smtpWrite($socket, base64_encode($username));
            self::smtpRead($socket, 334);
            self::smtpWrite($socket, base64_encode($password));
            $authResponse = self::smtpRead($socket, 235);

            // MAIL FROM
            self::smtpWrite($socket, "MAIL FROM:<{$from}>");
            self::smtpRead($socket, 250);

            // RCPT TO
            self::smtpWrite($socket, "RCPT TO:<{$to}>");
            self::smtpRead($socket, 250);

            // DATA
            self::smtpWrite($socket, "DATA");
            self::smtpRead($socket, 354);

            // Send message body (dot-stuffing)
            $lines = explode("\r\n", $message);
            foreach ($lines as $line) {
                if (isset($line[0]) && $line[0] === '.') {
                    $line = '.' . $line; // Dot-stuffing
                }
                fwrite($socket, $line . "\r\n");
            }
            fwrite($socket, ".\r\n");
            self::smtpRead($socket, 250);

            // QUIT
            self::smtpWrite($socket, "QUIT");

        } catch (Exception $e) {
            @fclose($socket);
            throw $e;
        }

        @fclose($socket);
    }

    /**
     * Write command to SMTP socket
     */
    private static function smtpWrite($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * Read response from SMTP socket, check expected code
     */
    private static function smtpRead($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // Last line of response: code + space (not code + dash)
            if (isset($line[3]) && $line[3] === ' ') break;
            if (isset($line[3]) && $line[3] !== '-') break;
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP-Fehler ({$code}): " . trim($response));
        }

        return $response;
    }
}
