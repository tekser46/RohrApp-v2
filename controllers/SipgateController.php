<?php
class SipgateController extends Controller
{
    /**
     * GET /api/sipgate/settings — Get or create sipgate settings + webhook URL
     */
    public function getSettings(array $params = []): void
    {
        $userId = Session::userId();
        $model = new SipgateSetting();
        $settings = $model->getByUser($userId);

        if (!$settings) {
            $result = $model->createForUser($userId);
            $settings = $model->getByUser($userId);
        }

        // Build webhook URL — tek URL, token yok, numara bazlı eşleştirme
        $cfg = $this->config();
        $baseUrl = rtrim($cfg['url'] ?? 'https://rohrapp.de', '/');
        $webhookUrl = $baseUrl . '/webhook/sipgate';

        // Get numbers
        $numbers = (new SipgateNumber())->getBySettingsId($settings['id']);

        Response::success([
            'id' => $settings['id'],
            'is_enabled' => (bool) $settings['is_enabled'],
            'webhook_url' => $webhookUrl,
            'numbers' => $numbers,
        ]);
    }

    /**
     * PUT /api/sipgate/settings — Enable/disable sipgate
     */
    public function updateSettings(array $params = []): void
    {
        $userId = Session::userId();
        $isEnabled = (bool) Request::input('is_enabled', false);
        (new SipgateSetting())->enable($userId, $isEnabled);
        Response::success(null, $isEnabled ? 'Sipgate aktiviert.' : 'Sipgate deaktiviert.');
    }

    /**
     * POST /api/sipgate/numbers — Add a number
     */
    public function addNumber(array $params = []): void
    {
        $userId = Session::userId();
        $settings = (new SipgateSetting())->getByUser($userId);
        if (!$settings) Response::error('Sipgate nicht konfiguriert', 400);

        $number = trim(Request::input('number', ''));
        $label = trim(Request::input('label', ''));
        $blockName = trim(Request::input('block_name', ''));
        $isBlocked = (bool) Request::input('is_blocked', false);

        if (!$number) Response::error('Nummer ist erforderlich', 400);

        $id = (new SipgateNumber())->add($settings['id'], $number, $label, $blockName, $isBlocked);
        Response::success(['id' => $id], 'Nummer hinzugefügt.');
    }

    /**
     * DELETE /api/sipgate/numbers/:id — Remove a number
     */
    public function deleteNumber(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        $settings = (new SipgateSetting())->getByUser($userId);
        if (!$settings) Response::error('Sipgate nicht konfiguriert', 400);

        (new SipgateNumber())->remove($id, $settings['id']);
        Response::success(null, 'Nummer entfernt.');
    }

    /**
     * POST /api/sipgate/numbers/:id/block — Toggle block status
     */
    public function toggleBlock(array $params = []): void
    {
        $userId = Session::userId();
        $id = (int) ($params['id'] ?? 0);
        $settings = (new SipgateSetting())->getByUser($userId);
        if (!$settings) Response::error('Sipgate nicht konfiguriert', 400);

        (new SipgateNumber())->toggleBlock($id, $settings['id']);
        Response::success(null, 'Block-Status geändert.');
    }
}
