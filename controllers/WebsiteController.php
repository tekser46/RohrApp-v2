<?php
/**
 * WebsiteController — Manage user's connected websites
 */
class WebsiteController extends Controller
{
    /**
     * GET /api/websites — List user's websites
     */
    public function list(array $params = []): void
    {
        $userId = Session::userId();
        $websites = (new Website())->getByUser($userId);
        Response::success($websites);
    }

    /**
     * POST /api/websites — Add a new website
     * Body: domain, name (optional)
     */
    public function create(array $params = []): void
    {
        $userId = Session::userId();
        $domain = trim(Request::input('domain', ''));
        $name   = trim(Request::input('name', ''));

        if (!$domain) {
            Response::error('Domain ist erforderlich', 400);
        }

        // Clean domain (remove protocol)
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        $result = (new Website())->createForUser($userId, $domain, $name);

        ActivityLog::log('website_added', $userId, 'websites', $result['id'], ['domain' => $domain]);

        Response::success($result, 'Website hinzugefügt.');
    }

    /**
     * DELETE /api/websites/:id — Remove a website
     */
    public function delete(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        (new Website())->deleteByUser($id, $userId);
        Response::success(null, 'Website entfernt.');
    }
}
