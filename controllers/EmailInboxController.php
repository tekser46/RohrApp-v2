<?php
/**
 * EmailInboxController — Email listing from IMAP cache
 */
class EmailInboxController extends Controller
{
    /**
     * GET /api/emails — List cached emails
     * Query params: page, per_page, filter (unread|starred), folder (IMAP folder name)
     */
    public function list(array $params = []): void
    {
        $userId = Session::userId();
        $page    = Request::queryInt('page', 1);
        $perPage = Request::queryInt('per_page', 30);
        $filter  = Request::query('filter', ''); // '', 'unread', 'starred'
        $folder  = Request::query('folder', ''); // IMAP folder name or empty for all
        $search  = Request::query('search', ''); // search term
        $sort    = Request::query('sort', 'date_desc'); // date_desc, date_asc
        $offset  = ($page - 1) * $perPage;

        $model = new EmailCache();
        $total  = $model->countByUser($userId, $filter, $folder, $search);
        $emails = $model->getByUser($userId, $perPage, $offset, $filter, $folder, $search, $sort);

        Response::paginated($emails, $total, $page, $perPage);
    }

    /**
     * GET /api/emails/folders — Get IMAP folder list
     */
    public function folders(array $params = []): void
    {
        $userId = Session::userId();

        try {
            $folders = EmailImapService::getFolders($userId);
            Response::success($folders);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'IMAP_ERROR');
        }
    }

    /**
     * GET /api/emails/:id — Email detail
     */
    public function detail(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $model = new EmailCache();
        $email = $model->getDetail($id, $userId);
        if (!$email) Response::notFound('E-Mail nicht gefunden');

        // Mark as read
        $model->markRead($id, $userId);

        Response::success($email);
    }

    /**
     * POST /api/emails/refresh — Sync new emails from IMAP
     * Body param: folder (optional, defaults to INBOX)
     */
    public function refresh(array $params = []): void
    {
        // IMAP can be slow — extend timeout
        set_time_limit(120);

        $userId = Session::userId();
        $folder = Request::input('folder', 'INBOX');

        try {
            $count = EmailImapService::syncEmails($userId, 50, $folder);
            Response::success([
                'fetched' => $count,
            ], $count > 0 ? $count . ' neue E-Mails geladen.' : 'Keine neuen E-Mails.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'IMAP_ERROR');
        }
    }

    /**
     * POST /api/emails/:id/star — Toggle star on an email
     */
    public function star(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $model = new EmailCache();
        $email = $model->getDetail($id, $userId);
        if (!$email) Response::notFound('E-Mail nicht gefunden');

        $newValue = $model->toggleStar($id, $userId);
        Response::success(['is_starred' => $newValue], $newValue ? 'Als wichtig markiert.' : 'Markierung entfernt.');
    }

    /**
     * POST /api/emails/:id/read — Set read/unread status
     * Body param: is_read (0 or 1)
     */
    public function setRead(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $isRead = (int) Request::input('is_read', 1);

        $db = $this->db();
        $stmt = $db->prepare("UPDATE email_cache SET is_read = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$isRead, $id, $userId]);

        Response::success(['is_read' => $isRead], $isRead ? 'Als gelesen markiert.' : 'Als ungelesen markiert.');
    }

    /**
     * DELETE /api/emails/:id — Delete cached email
     */
    public function deleteEmail(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $model = new EmailCache();
        $email = $model->getDetail($id, $userId);
        if (!$email) Response::notFound('E-Mail nicht gefunden');

        $model->delete($id);
        Response::success(null, 'E-Mail gelöscht.');
    }

    /**
     * POST /api/emails/bulk-delete — Delete multiple emails
     * Body param: ids (array of email IDs)
     */
    public function bulkDelete(array $params = []): void
    {
        $userId = Session::userId();
        $ids = Request::input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            Response::error('Keine E-Mails ausgewählt', 400);
        }

        $db = $this->db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_map('intval', $ids);
        $params[] = $userId;

        $stmt = $db->prepare("DELETE FROM email_cache WHERE id IN ({$placeholders}) AND user_id = ?");
        $stmt->execute($params);
        $deleted = $stmt->rowCount();

        Response::success(['deleted' => $deleted], $deleted . ' E-Mail(s) gelöscht.');
    }

    /**
     * POST /api/emails/:id/color — Set color marking on an email
     * Body param: color (red|yellow|green|blue|null)
     */
    public function color(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $model = new EmailCache();
        $email = $model->getDetail($id, $userId);
        if (!$email) Response::notFound('E-Mail nicht gefunden');

        $color = Request::input('color', null);
        $model->setColor($id, $userId, $color);
        Response::success(['color' => $color], $color ? 'Farbe gesetzt.' : 'Farbe entfernt.');
    }

    /**
     * POST /api/emails/reply — Reply to an email (supports attachments via FormData)
     */
    public function reply(array $params = []): void
    {
        $userId = Session::userId();

        // Support both JSON and FormData
        $to        = trim($_POST['to']        ?? Request::input('to', ''));
        $subject   = trim($_POST['subject']   ?? Request::input('subject', ''));
        $body      = $_POST['body']            ?? Request::input('body', '');
        $inReplyTo = $_POST['in_reply_to']     ?? Request::input('in_reply_to', '');

        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::error('Ungültige Empfänger-Adresse', 400);
        }
        if (!$subject) $subject = '(Kein Betreff)';
        if (!$body) Response::error('Nachricht darf nicht leer sein', 400);

        // Collect uploaded attachments
        $attachments = self::collectAttachments();

        try {
            SmtpMailService::send($userId, $to, $subject, $body, $inReplyTo ?: null, $attachments);
            ActivityLog::log('email.reply', $userId, null, null, ['to' => $to, 'subject' => $subject]);
            Response::success(null, 'E-Mail wurde gesendet.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'SMTP_ERROR');
        } finally {
            self::cleanupAttachments($attachments);
        }
    }

    /**
     * POST /api/emails/forward — Forward an email (supports attachments via FormData)
     */
    public function forward(array $params = []): void
    {
        $userId   = Session::userId();

        // Support both JSON and FormData
        $to       = trim($_POST['to']      ?? Request::input('to', ''));
        $subject  = trim($_POST['subject'] ?? Request::input('subject', ''));
        $body     = $_POST['body']          ?? Request::input('body', '');

        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::error('Ungültige Empfänger-Adresse', 400);
        }
        if (!$subject) $subject = '(Kein Betreff)';
        if (!$body) Response::error('Nachricht darf nicht leer sein', 400);

        // Collect uploaded attachments
        $attachments = self::collectAttachments();

        try {
            SmtpMailService::send($userId, $to, $subject, $body, null, $attachments);
            ActivityLog::log('email.forward', $userId, null, null, ['to' => $to, 'subject' => $subject]);
            Response::success(null, 'E-Mail wurde weitergeleitet.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'SMTP_ERROR');
        } finally {
            self::cleanupAttachments($attachments);
        }
    }

    /**
     * Collect uploaded attachment files from $_FILES
     * @return array [{name, tmp_name, type, size}, ...]
     */
    private static function collectAttachments(): array
    {
        $attachments = [];
        if (!empty($_FILES['attachments'])) {
            $files = $_FILES['attachments'];
            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $attachments[] = [
                            'name'     => $files['name'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'type'     => $files['type'][$i] ?: 'application/octet-stream',
                            'size'     => $files['size'][$i],
                        ];
                    }
                }
            } elseif ($files['error'] === UPLOAD_ERR_OK) {
                $attachments[] = [
                    'name'     => $files['name'],
                    'tmp_name' => $files['tmp_name'],
                    'type'     => $files['type'] ?: 'application/octet-stream',
                    'size'     => $files['size'],
                ];
            }
        }
        return $attachments;
    }

    /**
     * Clean up temp attachment files
     */
    private static function cleanupAttachments(array $attachments): void
    {
        foreach ($attachments as $att) {
            if (isset($att['tmp_name']) && file_exists($att['tmp_name'])) {
                @unlink($att['tmp_name']);
            }
        }
    }
}
