<?php
/**
 * EmailSettingsController — IMAP/SMTP settings
 */
class EmailSettingsController extends Controller
{
    /**
     * GET /api/email-settings
     */
    public function get(array $params = []): void
    {
        $userId = Session::userId();
        $settings = (new EmailSetting())->getByUserId($userId);

        if ($settings) {
            // Don't send encrypted passwords to frontend
            unset($settings['imap_password_encrypted']);
            unset($settings['smtp_password_encrypted']);
            $settings['imap_password_set'] = true; // indicate password exists
        }

        Response::success($settings);
    }

    /**
     * PUT /api/email-settings
     */
    public function save(array $params = []): void
    {
        $userId = Session::userId();
        $body = Request::body();
        $model = new EmailSetting();

        $data = [];
        $allowed = ['email_address', 'imap_host', 'imap_port', 'imap_username', 'imap_encryption',
                     'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption'];

        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $data[$field] = trim($body[$field]);
            }
        }

        // Handle IMAP password (encrypt if provided)
        if (!empty($body['imap_password'])) {
            $data['imap_password_encrypted'] = Encryption::encrypt($body['imap_password']);
        }

        // Handle SMTP password
        if (!empty($body['smtp_password'])) {
            $data['smtp_password_encrypted'] = Encryption::encrypt($body['smtp_password']);
        }

        if (empty($data)) {
            Response::error('Keine Änderungen', 400);
        }

        $data['is_verified'] = 0; // Reset verification on change
        $model->saveForUser($userId, $data);

        ActivityLog::log('email_settings.update', $userId);
        Response::success(null, 'E-Mail-Einstellungen gespeichert.');
    }

    /**
     * POST /api/email-settings/test
     */
    public function test(array $params = []): void
    {
        $userId = Session::userId();
        $model = new EmailSetting();
        $settings = $model->getByUserId($userId);

        if (!$settings || !$settings['imap_host']) {
            Response::error('Bitte zuerst IMAP-Einstellungen speichern.', 400);
        }

        try {
            EmailImapService::testConnection($settings);
            $model->saveForUser($userId, ['is_verified' => 1]);
            Response::success(null, 'IMAP-Verbindung erfolgreich!');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'IMAP_ERROR');
        }
    }
}
