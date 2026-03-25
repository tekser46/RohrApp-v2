<?php
/**
 * DashboardController — Dashboard summary data
 */
class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     */
    public function index(array $params = []): void
    {
        $userId = Session::userId();
        $db = $this->db();

        // Get license
        $license = (new UserLicense())->getCurrentLicense($userId);

        // Count unread messages
        $msgCount = $db->prepare("SELECT COUNT(*) FROM contact_messages WHERE user_id = ? AND status = 'unread'");
        $msgCount->execute([$userId]);
        $unreadMessages = (int) $msgCount->fetchColumn();

        // Count today's calls
        $callCount = $db->prepare("SELECT COUNT(*) FROM call_logs WHERE user_id = ? AND DATE(started_at) = CURDATE()");
        $callCount->execute([$userId]);
        $todayCalls = (int) $callCount->fetchColumn();

        // Count unread emails (from cache)
        $emailCount = $db->prepare("SELECT COUNT(*) FROM email_cache WHERE user_id = ? AND is_read = 0");
        $emailCount->execute([$userId]);
        $unreadEmails = (int) $emailCount->fetchColumn();

        // Recent calls (last 5)
        $recentCalls = $db->prepare("SELECT * FROM call_logs WHERE user_id = ? ORDER BY started_at DESC LIMIT 5");
        $recentCalls->execute([$userId]);

        Response::success([
            'license' => $license ? [
                'package_name'  => $license['package_name'],
                'package_slug'  => $license['package_slug'],
                'status'        => $license['status'],
                'license_key'   => $license['license_key'],
                'expires_at'    => $license['expires_at'],
            ] : null,
            'stats' => [
                'unread_messages' => $unreadMessages,
                'today_calls'     => $todayCalls,
                'unread_emails'   => $unreadEmails,
            ],
            'recent_calls' => $recentCalls->fetchAll(),
        ]);
    }
}
