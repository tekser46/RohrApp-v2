<?php
/**
 * MessageController — Contact messages from website forms
 */
class MessageController extends Controller
{
    /**
     * GET /api/messages — List messages
     */
    public function list(array $params = []): void
    {
        $userId  = Session::userId();
        $page    = Request::queryInt('page', 1);
        $perPage = Request::queryInt('per_page', 30);
        $status  = Request::query('status', ''); // '', 'unread', 'read', 'archived'
        $search  = Request::query('search', '');
        $sort    = Request::query('sort', 'date_desc');
        $offset  = ($page - 1) * $perPage;

        $model = new ContactMessage();
        $total  = $model->countByUser($userId, $status, $search);
        $msgs   = $model->getByUser($userId, $perPage, $offset, $status, $search, $sort);

        Response::paginated($msgs, $total, $page, $perPage);
    }

    /**
     * GET /api/messages/:id — Message detail
     */
    public function detail(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        if (!$id) Response::error('ID erforderlich', 400);

        $model = new ContactMessage();
        $msg = $model->getByIdAndUser($id, $userId);
        if (!$msg) Response::error('Nachricht nicht gefunden', 404);

        // Auto mark as read
        if ($msg['status'] === 'unread') {
            $model->setStatus($id, $userId, 'read');
            $msg['status'] = 'read';
        }

        Response::success($msg);
    }

    /**
     * PUT /api/messages/:id/read — Mark as read
     */
    public function markRead(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        (new ContactMessage())->setStatus($id, $userId, 'read');
        Response::success(null, 'Als gelesen markiert.');
    }

    /**
     * PUT /api/messages/:id/unread — Mark as unread
     */
    public function markUnread(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        (new ContactMessage())->setStatus($id, $userId, 'unread');
        Response::success(null, 'Als ungelesen markiert.');
    }

    /**
     * PUT /api/messages/:id/archive — Archive message
     */
    public function archive(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        (new ContactMessage())->setStatus($id, $userId, 'archived');
        Response::success(null, 'Archiviert.');
    }

    /**
     * DELETE /api/messages/:id — Delete message
     */
    public function delete(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        (new ContactMessage())->deleteByUser($id, $userId);
        Response::success(null, 'Nachricht gelöscht.');
    }

    /**
     * POST /api/messages/bulk-delete — Delete multiple messages
     */
    public function bulkDelete(array $params = []): void
    {
        $userId = Session::userId();
        $ids = Request::input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            Response::error('Keine Nachrichten ausgewählt', 400);
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $p = array_map('intval', $ids);
        $p[] = $userId;

        $stmt = $db->prepare("DELETE FROM contact_messages WHERE id IN ({$placeholders}) AND user_id = ?");
        $stmt->execute($p);

        Response::success(['deleted' => $stmt->rowCount()], $stmt->rowCount() . ' Nachricht(en) gelöscht.');
    }
}
