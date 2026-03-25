<?php
/**
 * UpgradeRequestController — User upgrade requests
 */
class UpgradeRequestController extends Controller
{
    /**
     * POST /api/upgrade-requests — Create request
     */
    public function create(array $params = []): void
    {
        $userId    = Session::userId();
        $packageId = (int) Request::input('package_id', 0);
        $message   = trim(Request::input('message', ''));

        try {
            $service = new UpgradeRequestService();
            $id = $service->createRequest($userId, $packageId, $message);
            Response::success(['id' => $id], 'Upgrade-Anfrage wurde gesendet.', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'VALIDATION_ERROR');
        }
    }

    /**
     * GET /api/upgrade-requests — List user's requests
     */
    public function list(array $params = []): void
    {
        $userId = Session::userId();
        $requests = (new UpgradeRequest())->getByUser($userId);
        Response::success($requests);
    }
}
