<?php
/**
 * WebhookController — Public endpoints for external integrations
 * No auth required — uses API key / token for verification
 */
class WebhookController extends Controller
{
    /**
     * POST /webhook/contact — Receive contact form submission
     * Query param: token (website API key)
     * Body: name, email, phone, subject, message
     */
    public function contact(array $params = []): void
    {
        $token = Request::query('token', '');
        if (!$token) {
            Response::error('Token erforderlich', 401, 'MISSING_TOKEN');
        }

        // Find by website API key first, then by user license key
        $website = (new Website())->findByApiKey($token);
        $userId = null;
        $websiteId = null;

        if ($website) {
            $userId = (int) $website['user_id'];
            $websiteId = (int) $website['id'];
        } else {
            // Try license key
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT ul.user_id FROM user_licenses ul WHERE ul.license_key = ? AND ul.status = 'active' LIMIT 1");
            $stmt->execute([$token]);
            $lic = $stmt->fetch();
            if ($lic) {
                $userId = (int) $lic['user_id'];
                // Find or create default website for this user
                $sites = (new Website())->getByUser($userId);
                $websiteId = !empty($sites) ? (int) $sites[0]['id'] : 0;
            }
        }

        if (!$userId) {
            Response::error('Ungültiger Token', 401, 'INVALID_TOKEN');
        }

        // Validate required fields
        $name    = trim(Request::input('name', ''));
        $email   = trim(Request::input('email', ''));
        $phone   = trim(Request::input('phone', ''));
        $subject = trim(Request::input('subject', ''));
        $message = trim(Request::input('message', ''));

        if (!$name && !$email && !$message) {
            Response::error('Mindestens Name, E-Mail oder Nachricht ist erforderlich', 400);
        }

        // Rate limit: max 10 messages per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 10) {
            Response::error('Zu viele Anfragen. Bitte versuchen Sie es später.', 429, 'RATE_LIMIT');
        }

        // Create message
        $msgModel = new ContactMessage();
        $id = $msgModel->createFromWebhook([
            'website_id'   => $websiteId ?: 0,
            'user_id'      => $userId,
            'sender_name'  => mb_substr($name, 0, 200),
            'sender_email' => mb_substr($email, 0, 200),
            'sender_phone' => mb_substr($phone, 0, 50),
            'subject'      => mb_substr($subject, 0, 300),
            'message'      => mb_substr($message, 0, 5000),
            'ip_address'   => $ip,
        ]);

        // Log activity
        ActivityLog::log('message_received', $userId, 'contact_messages', $id,
            ['from' => ($name ?: $email ?: 'Unbekannt')]);

        Response::success(['id' => $id], 'Nachricht erfolgreich gesendet.');
    }

    /**
     * POST /webhook/sipgate — Receive Sipgate webhook events
     * NO TOKEN — single URL for all users
     * User matching via phone number in sipgate_numbers table
     *
     * Sipgate sends events: newCall, answer, hangup
     */
    public function sipgate(array $params = []): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rawPayload = file_get_contents('php://input');

        // Parse payload — Sipgate sends form-encoded OR JSON
        $data = json_decode($rawPayload, true);
        if (!$data) {
            parse_str($rawPayload, $data);
        }
        if (empty($data)) {
            $data = $_POST;
        }

        $eventType = $data['event'] ?? 'unknown';
        $logModel = new WebhookLog();

        // Extract Sipgate fields
        $callId     = $data['callId'] ?? null;
        $direction  = isset($data['direction']) ? ($data['direction'] === 'in' ? 'in' : 'out') : null;
        $fromNumber = $data['from'] ?? $data['fromNumber'] ?? '';
        $toNumber   = $data['to'] ?? $data['toNumber'] ?? '';
        $callerName = $data['fromName'] ?? $data['callerName'] ?? '';

        // Match number to user — eingehend: aranan numara, ausgehend: arayan numara
        $numberModel = new SipgateNumber();
        $matchNumber = ($direction === 'in') ? $toNumber : $fromNumber;
        $matched = $numberModel->findByNumber($matchNumber);

        $userId = $matched ? (int) $matched['user_id'] : null;

        // Duplicate check
        $callModel = new CallLog();
        if ($callId && $eventType === 'newCall' && $callModel->existsBySipgateCallId($callId)) {
            $logModel->log('sipgate', $eventType, $rawPayload, $ip, $userId, null, true);
            Response::success(null, 'Duplicate — already processed.');
            return;
        }

        // Check if blocked
        if ($matched && $matched['is_blocked']) {
            $logModel->log('sipgate', $eventType, $rawPayload, $ip, $userId, null, true);
            Response::success(null, 'Blocked number — ignored.');
            return;
        }

        $callLogId = null;

        switch ($eventType) {
            case 'newCall':
                // Create new call log
                $callLogId = $callModel->create([
                    'user_id'           => $userId,
                    'sipgate_call_id'   => $callId,
                    'direction'         => $direction,
                    'from_number'       => $fromNumber,
                    'to_number'         => $toNumber,
                    'caller_name'       => $callerName,
                    'status'            => 'ringing',
                    'started_at'        => date('Y-m-d H:i:s'),
                    'matched_number_id' => $matched ? $matched['id'] : null,
                ]);
                break;

            case 'answer':
                if ($callId) {
                    $callModel->updateStatus($callId, 'answered', date('Y-m-d H:i:s'));
                }
                break;

            case 'hangup':
                $cause = $data['cause'] ?? 'normalClearing';
                $status = 'hangup';
                if ($cause === 'cancel' || $cause === 'busy') {
                    $status = ($cause === 'busy') ? 'busy' : 'missed';
                } elseif (!isset($data['answerTime']) && $direction === 'in') {
                    $status = 'missed';
                }

                $duration = 0;
                if (isset($data['duration'])) {
                    $duration = (int) $data['duration'];
                }

                if ($callId) {
                    $callModel->updateStatus($callId, $status, null, date('Y-m-d H:i:s'), $duration);
                }
                break;
        }

        // Log webhook
        $logModel->log('sipgate', $eventType, $rawPayload, $ip, $userId, $callLogId, true);

        // Sipgate expects 200 OK with valid response
        Response::success(null, 'OK');
    }
}
