<?php
class CallController extends Controller
{
    /**
     * GET /api/calls — List call logs (default: today only, unless search params given)
     */
    public function list(array $params = []): void
    {
        $userId    = Session::userId();
        $page      = Request::queryInt('page', 1);
        $perPage   = Request::queryInt('per_page', 50);
        $direction = Request::query('direction', '');
        $status    = Request::query('status', '');
        $search    = Request::query('search', '');
        $sort      = Request::query('sort', 'date_desc');
        $category  = Request::query('category', ''); // none, falsch, auftrag
        $dateFrom  = Request::query('date_from', '');
        $dateTo    = Request::query('date_to', '');
        $todayOnly = Request::query('today', '1'); // default: only today
        $offset    = ($page - 1) * $perPage;

        $model = new CallLog();
        $total = $model->countByUser($userId, $direction, $status, $search, $category, $dateFrom, $dateTo, $todayOnly === '1');
        $calls = $model->getByUser($userId, $perPage, $offset, $direction, $status, $search, $sort, $category, $dateFrom, $dateTo, $todayOnly === '1');

        Response::paginated($calls, $total, $page, $perPage);
    }

    /**
     * GET /api/calls/:id — Call detail
     */
    public function detail(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db()->prepare("SELECT * FROM call_logs WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $call = $stmt->fetch();
        if (!$call) Response::notFound('Anruf nicht gefunden');

        Response::success($call);
    }

    /**
     * POST /api/calls/:id/categorize — Mark as falsch or auftrag
     */
    public function categorize(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        $category = Request::input('category', '');

        if (!in_array($category, ['falsch', 'auftrag', 'none'])) {
            Response::error('Ungültige Kategorie', 400);
        }

        $stmt = $this->db()->prepare("UPDATE call_logs SET category = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$category, $id, $userId]);

        // If reverting to 'none', also delete linked auftrag
        if ($category === 'none') {
            $this->db()->prepare("DELETE FROM auftraege WHERE call_log_id = ? AND user_id = ?")->execute([$id, $userId]);
        }

        Response::success(['category' => $category], 'Kategorie aktualisiert.');
    }

    /**
     * GET /api/calls/falsch — List false calls
     */
    public function falschList(array $params = []): void
    {
        $userId = Session::userId();
        $search = Request::query('search', '');

        $model = new CallLog();
        $calls = $model->getByUser($userId, 100, 0, '', '', $search, 'date_desc', 'falsch', '', '', false);

        Response::success($calls);
    }

    // ── Auftrag endpoints ──

    /**
     * GET /api/auftraege — Today's aufträge (or search)
     */
    public function auftragList(array $params = []): void
    {
        $userId = Session::userId();
        $search   = Request::query('search', '');
        $dateFrom = Request::query('date_from', '');
        $dateTo   = Request::query('date_to', '');
        $callLogId = Request::query('call_log_id', '');

        $model = new Auftrag();

        // Filter by call_log_id (for undo check)
        if ($callLogId) {
            $stmt = $this->db()->prepare("SELECT * FROM auftraege WHERE call_log_id = ? AND user_id = ?");
            $stmt->execute([(int)$callLogId, $userId]);
            Response::success($stmt->fetchAll());
            return;
        }

        if ($search || $dateFrom || $dateTo) {
            $total = $model->countSearch($userId, $search, $dateFrom, $dateTo);
            $data  = $model->search($userId, $search, $dateFrom, $dateTo);
            Response::paginated($data, $total, 1, 50);
        } else {
            $data = $model->getToday($userId);
            Response::success($data);
        }
    }

    /**
     * POST /api/auftraege — Create auftrag from call
     */
    public function auftragCreate(array $params = []): void
    {
        $userId = Session::userId();
        $callLogId     = (int) Request::input('call_log_id', 0);
        $customerName  = trim(Request::input('customer_name', ''));
        $customerAddr  = trim(Request::input('customer_address', ''));
        $customerPlz   = trim(Request::input('customer_plz', ''));
        $customerCity  = trim(Request::input('customer_city', ''));
        $customerPhone = trim(Request::input('customer_phone', ''));
        $jobType       = Request::input('job_type', '');
        $notes         = trim(Request::input('notes', ''));

        if (!in_array($jobType, ['Hauptleitung', 'Küche', 'Bad', 'Keller', 'Toilette'])) {
            Response::error('Bitte wählen Sie einen Arbeitstyp', 400);
        }

        // Mark call as auftrag
        if ($callLogId > 0) {
            $stmt = $this->db()->prepare("UPDATE call_logs SET category = 'auftrag' WHERE id = ? AND user_id = ?");
            $stmt->execute([$callLogId, $userId]);
        }

        $model = new Auftrag();
        $id = $model->createFromCall($userId, $callLogId ?: null, [
            'customer_name'    => $customerName,
            'customer_address' => $customerAddr,
            'customer_plz'     => $customerPlz,
            'customer_city'    => $customerCity,
            'customer_phone'   => $customerPhone,
            'job_type'         => $jobType,
            'notes'            => $notes,
        ]);

        Response::success(['id' => $id], 'Auftrag erstellt.');
    }

    /**
     * PUT /api/auftraege/:id — Full update auftrag
     */
    public function auftragUpdate(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);

        $fields = [];
        $values = [];
        foreach (['customer_name', 'customer_phone', 'customer_address', 'customer_plz', 'customer_city', 'job_type', 'status', 'notes'] as $f) {
            $v = Request::input($f);
            if ($v !== null) {
                $fields[] = "{$f} = ?";
                $values[] = $v;
            }
        }
        if (empty($fields)) {
            Response::error('Keine Daten', 400);
        }
        $values[] = $id;
        $values[] = $userId;

        $sql = "UPDATE auftraege SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
        $this->db()->prepare($sql)->execute($values);

        Response::success(null, 'Auftrag aktualisiert.');
    }

    /**
     * PUT /api/auftraege/:id/status — Update auftrag status
     */
    public function auftragStatus(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        $status = Request::input('status', '');

        if (!in_array($status, ['offen', 'in_bearbeitung', 'erledigt', 'storniert'])) {
            Response::error('Ungültiger Status', 400);
        }

        (new Auftrag())->updateStatus($id, $userId, $status);
        Response::success(null, 'Status aktualisiert.');
    }

    /**
     * DELETE /api/auftraege/:id
     */
    public function auftragDelete(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        (new Auftrag())->deleteByUser($id, $userId);
        Response::success(null, 'Auftrag gelöscht.');
    }
}
