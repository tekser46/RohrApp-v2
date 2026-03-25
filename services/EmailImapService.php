<?php
/**
 * EmailImapService — IMAP connection, mail fetching, caching
 */
class EmailImapService
{
    /**
     * Test IMAP connection
     * @throws Exception on failure
     */
    public static function testConnection(array $settings): bool
    {
        $mailbox = self::buildMailbox($settings);
        $password = $settings['imap_password'] ?? '';

        // If password is encrypted, decrypt
        if (isset($settings['imap_password_encrypted']) && $settings['imap_password_encrypted']) {
            $password = Encryption::decrypt($settings['imap_password_encrypted']);
        }

        if (!function_exists('imap_open')) {
            throw new Exception('IMAP-Erweiterung ist nicht aktiviert. Bitte aktivieren Sie php_imap in der PHP-Konfiguration.');
        }

        $conn = @imap_open($mailbox, $settings['imap_username'], $password, 0, 1);
        if (!$conn) {
            $err = imap_last_error();
            throw new Exception('IMAP-Verbindung fehlgeschlagen: ' . ($err ?: 'Unbekannter Fehler'));
        }

        imap_close($conn);
        return true;
    }

    /**
     * Fetch IMAP folder list for user
     * @return array List of folder names
     */
    public static function getFolders(int $userId): array
    {
        $settingModel = new EmailSetting();
        $settings = $settingModel->getByUserId($userId);
        if (!$settings) throw new Exception('Keine E-Mail-Einstellungen gefunden.');

        $password = Encryption::decrypt($settings['imap_password_encrypted']);
        $serverString = self::buildServerString($settings);

        if (!function_exists('imap_open')) {
            throw new Exception('IMAP-Erweiterung nicht aktiviert.');
        }

        $conn = @imap_open($serverString . 'INBOX', $settings['imap_username'], $password, 0, 1);
        if (!$conn) {
            throw new Exception('IMAP-Verbindung fehlgeschlagen: ' . (imap_last_error() ?: 'Unbekannt'));
        }

        $folders = [];
        try {
            $list = @imap_list($conn, $serverString, '*');
            if ($list) {
                foreach ($list as $folderPath) {
                    // Remove server string prefix to get folder name
                    $name = str_replace($serverString, '', $folderPath);
                    // Decode UTF-7 folder names
                    $name = mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP');
                    $folders[] = $name;
                }
            }
        } finally {
            imap_close($conn);
        }

        // Ensure INBOX is always first
        $folders = array_values(array_unique($folders));
        $inboxIdx = array_search('INBOX', $folders);
        if ($inboxIdx !== false && $inboxIdx !== 0) {
            unset($folders[$inboxIdx]);
            array_unshift($folders, 'INBOX');
            $folders = array_values($folders);
        } elseif ($inboxIdx === false) {
            array_unshift($folders, 'INBOX');
        }

        return $folders;
    }

    /**
     * Fetch emails from IMAP and cache them
     * @param string $folder IMAP folder to sync (default: INBOX)
     * @return int Number of new emails fetched
     */
    public static function syncEmails(int $userId, int $maxFetch = 50, string $folder = 'INBOX'): int
    {
        $settingModel = new EmailSetting();
        $settings = $settingModel->getByUserId($userId);
        if (!$settings) throw new Exception('Keine E-Mail-Einstellungen gefunden.');

        $serverString = self::buildServerString($settings);
        // Encode folder name for IMAP
        $encodedFolder = mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
        $mailbox = $serverString . $encodedFolder;
        $password = Encryption::decrypt($settings['imap_password_encrypted']);

        if (!function_exists('imap_open')) {
            throw new Exception('IMAP-Erweiterung nicht aktiviert.');
        }

        $conn = @imap_open($mailbox, $settings['imap_username'], $password, 0, 1);
        if (!$conn) {
            throw new Exception('IMAP-Verbindung fehlgeschlagen: ' . (imap_last_error() ?: 'Unbekannt'));
        }

        $cacheModel = new EmailCache();
        $fetched = 0;

        try {
            // Get total messages count
            $info = imap_check($conn);
            $totalMessages = $info->Nmsgs;

            if ($totalMessages === 0) {
                imap_close($conn);
                return 0;
            }

            // Fetch last N messages (newest first)
            $start = max(1, $totalMessages - $maxFetch + 1);
            $range = $start . ':' . $totalMessages;
            $overview = imap_fetch_overview($conn, $range, 0);

            if (!$overview) {
                imap_close($conn);
                return 0;
            }

            // Process newest first
            $overview = array_reverse($overview);

            foreach ($overview as $mail) {
                $uid = (string) $mail->uid;
                // Make UID unique per folder
                $folderUid = $folder . ':' . $uid;

                // Get message_id (RFC) for duplicate check
                $msgId = $mail->message_id ?? '';

                // Skip if already cached (check by message_id first, then uid)
                if ($cacheModel->exists($userId, $folderUid, $msgId)) continue;

                // Decode subject
                $subject = isset($mail->subject) ? self::decodeMime($mail->subject) : '(Kein Betreff)';

                // Decode from
                $fromRaw = isset($mail->from) ? self::decodeMime($mail->from) : '';
                $fromName = '';
                $fromAddr = $fromRaw;

                if (preg_match('/^(.+?)\s*<(.+?)>$/', $fromRaw, $m)) {
                    $fromName = trim($m[1], '" ');
                    $fromAddr = $m[2];
                }

                // Decode to
                $toRaw = isset($mail->to) ? self::decodeMime($mail->to) : '';

                // Get body preview (first 300 chars of plain text)
                $bodyPreview = '';
                try {
                    $body = imap_fetchbody($conn, $mail->msgno, '1'); // plain text part
                    if ($body) {
                        $bodyPreview = mb_substr(strip_tags(quoted_printable_decode($body)), 0, 300);
                        $bodyPreview = preg_replace('/\s+/', ' ', trim($bodyPreview));
                    }
                } catch (Exception $e) {
                    // Skip body if error
                }

                // Check attachments
                $structure = @imap_fetchstructure($conn, $mail->msgno);
                $hasAttachments = isset($structure->parts) && count($structure->parts) > 1;

                // Date
                $mailDate = isset($mail->date) ? date('Y-m-d H:i:s', strtotime($mail->date)) : date('Y-m-d H:i:s');

                // Is read
                $isRead = isset($mail->seen) && $mail->seen ? 1 : 0;

                $cacheModel->upsert($userId, $folderUid, [
                    'message_id'      => $mail->message_id ?? '',
                    'from_address'    => $fromAddr,
                    'from_name'       => $fromName,
                    'to_address'      => $toRaw,
                    'subject'         => mb_substr($subject, 0, 500),
                    'body_preview'    => mb_substr($bodyPreview, 0, 500),
                    'is_read'         => $isRead,
                    'is_starred'      => 0,
                    'has_attachments' => $hasAttachments ? 1 : 0,
                    'folder'          => $folder,
                    'mail_date'       => $mailDate,
                ]);

                $fetched++;
            }

            // Update last sync
            $settingModel->saveForUser($userId, [
                'last_sync_at'  => date('Y-m-d H:i:s'),
                'last_sync_uid' => $overview[0]->uid ?? null,
            ]);

        } finally {
            imap_close($conn);
        }

        return $fetched;
    }

    /**
     * Build IMAP server string (without folder name)
     */
    private static function buildServerString(array $settings): string
    {
        $host = $settings['imap_host'] ?? '';
        $port = $settings['imap_port'] ?? 993;
        $enc  = $settings['imap_encryption'] ?? 'ssl';

        $flags = '/imap';
        if ($enc === 'ssl') $flags .= '/ssl';
        elseif ($enc === 'tls') $flags .= '/tls';
        $flags .= '/novalidate-cert';

        return '{' . $host . ':' . $port . $flags . '}';
    }

    /**
     * Build IMAP mailbox string (with INBOX folder — for backward compat)
     */
    private static function buildMailbox(array $settings): string
    {
        return self::buildServerString($settings) . 'INBOX';
    }

    /**
     * Decode MIME encoded text
     */
    private static function decodeMime(string $text): string
    {
        $decoded = imap_mime_header_decode($text);
        $result = '';
        if ($decoded) {
            foreach ($decoded as $part) {
                $charset = strtolower($part->charset);
                if ($charset === 'default' || $charset === 'us-ascii') {
                    $result .= $part->text;
                } else {
                    $result .= mb_convert_encoding($part->text, 'UTF-8', $charset);
                }
            }
        }
        return $result ?: $text;
    }
}
