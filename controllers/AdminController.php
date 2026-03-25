<?php
/**
 * AdminController — Admin panel endpoints
 */
class AdminController extends Controller
{
    /**
     * GET /api/admin/users — List all users with profiles + licenses
     */
    public function users(array $params = []): void
    {
        $db = $this->db();
        $users = $db->query("
            SELECT u.id, u.email, u.role, u.is_active, u.last_login_at, u.created_at,
                   p.first_name, p.last_name, p.company_name, p.phone,
                   pkg.name AS package_name, pkg.slug AS package_slug,
                   ul.status AS license_status, ul.license_key
            FROM users u
            LEFT JOIN user_profiles p ON p.user_id = u.id
            LEFT JOIN user_licenses ul ON ul.user_id = u.id
            LEFT JOIN packages pkg ON pkg.id = ul.package_id
            ORDER BY u.created_at DESC
        ")->fetchAll();

        Response::success($users);
    }

    /**
     * GET /api/admin/user/:id — Single user detail
     */
    public function userDetail(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $db = $this->db();
        $stmt = $db->prepare("
            SELECT u.*, p.*,
                   pkg.name AS package_name, pkg.slug AS package_slug,
                   ul.license_key, ul.status AS license_status, ul.starts_at, ul.expires_at
            FROM users u
            LEFT JOIN user_profiles p ON p.user_id = u.id
            LEFT JOIN user_licenses ul ON ul.user_id = u.id
            LEFT JOIN packages pkg ON pkg.id = ul.package_id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('Benutzer nicht gefunden');

        // Remove password hash
        unset($user['password_hash']);

        Response::success($user);
    }

    /**
     * GET /api/admin/upgrade-requests — All upgrade requests
     */
    public function upgradeRequests(array $params = []): void
    {
        $status = Request::query('status', '');
        $requests = (new UpgradeRequest())->getAllWithUser($status);
        Response::success($requests);
    }

    /**
     * PUT /api/admin/upgrade-requests/:id — Approve/Reject
     */
    public function handleRequest(array $params = []): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $action = Request::input('action', ''); // 'approve' or 'reject'
        $note   = trim(Request::input('note', ''));
        $adminId = Session::userId();

        if (!$id) Response::error('ID erforderlich', 400);

        try {
            $service = new UpgradeRequestService();
            if ($action === 'approve') {
                $service->approve($id, $adminId, $note);
                Response::success(null, 'Anfrage genehmigt. Lizenz wurde aktualisiert.');
            } elseif ($action === 'reject') {
                $service->reject($id, $adminId, $note);
                Response::success(null, 'Anfrage abgelehnt.');
            } else {
                Response::error('Ungültige Aktion. Verwenden Sie "approve" oder "reject".', 400);
            }
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'VALIDATION_ERROR');
        }
    }

    /**
     * PUT /api/admin/user/:id/license — Manually update license
     */
    public function updateLicense(array $params = []): void
    {
        $userId    = (int) ($params['id'] ?? 0);
        $packageId = (int) Request::input('package_id', 0);
        $status    = Request::input('status', '');

        if (!$userId) Response::error('User ID erforderlich', 400);

        $db = $this->db();
        $license = (new UserLicense())->getCurrentLicense($userId);
        if (!$license) Response::error('Keine Lizenz gefunden', 404);

        $updates = [];
        if ($packageId) $updates['package_id'] = $packageId;
        if ($status && in_array($status, ['active', 'expired', 'suspended', 'trial'])) $updates['status'] = $status;

        if (empty($updates)) Response::error('Keine Änderungen', 400);

        (new UserLicense())->update($license['id'], $updates);
        ActivityLog::log('admin.license.update', Session::userId(), 'user', $userId, $updates);

        Response::success(null, 'Lizenz aktualisiert.');
    }

    /**
     * GET /api/admin/logs — Activity logs
     */
    public function logs(array $params = []): void
    {
        $limit  = Request::queryInt('limit', 50);
        $offset = Request::queryInt('offset', 0);
        $logs = (new ActivityLog())->getRecent($limit, $offset);
        Response::success($logs);
    }
}
