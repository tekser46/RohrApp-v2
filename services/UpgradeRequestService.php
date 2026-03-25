<?php
/**
 * UpgradeRequestService — Upgrade request business logic
 */
class UpgradeRequestService
{
    private UpgradeRequest $requestModel;
    private UserLicense $licenseModel;
    private Package $packageModel;

    public function __construct()
    {
        $this->requestModel = new UpgradeRequest();
        $this->licenseModel = new UserLicense();
        $this->packageModel = new Package();
    }

    /**
     * Create upgrade request
     */
    public function createRequest(int $userId, int $requestedPackageId, string $message = ''): int
    {
        // Check pending
        if ($this->requestModel->hasPending($userId)) {
            throw new Exception('Sie haben bereits eine ausstehende Anfrage.');
        }

        // Get current license
        $license = $this->licenseModel->getCurrentLicense($userId);
        if (!$license) throw new Exception('Keine aktive Lizenz gefunden.');

        // Validate requested package
        $pkg = $this->packageModel->find($requestedPackageId);
        if (!$pkg || !$pkg['is_active']) throw new Exception('Ungültiges Paket.');

        // Can't downgrade or request same
        if ($pkg['sort_order'] <= ($license['sort_order'] ?? 0)) {
            throw new Exception('Sie können nur ein höheres Paket anfragen.');
        }

        $id = $this->requestModel->create([
            'user_id'              => $userId,
            'current_package_id'   => $license['package_id'],
            'requested_package_id' => $requestedPackageId,
            'user_message'         => $message ?: null,
        ]);

        ActivityLog::log('upgrade.request', $userId, 'upgrade_request', $id, [
            'from' => $license['package_slug'] ?? '',
            'to'   => $pkg['slug'],
        ]);

        return $id;
    }

    /**
     * Approve request (admin)
     */
    public function approve(int $requestId, int $adminId, string $note = ''): void
    {
        $db = Database::getInstance();
        $request = $this->requestModel->find($requestId);
        if (!$request) throw new Exception('Anfrage nicht gefunden.');
        if ($request['status'] !== 'pending') throw new Exception('Anfrage wurde bereits bearbeitet.');

        $db->beginTransaction();
        try {
            // Update request
            $this->requestModel->update($requestId, [
                'status'      => 'approved',
                'admin_note'  => $note ?: null,
                'reviewed_by' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            // Update user's license to new package
            $license = $this->licenseModel->getCurrentLicense($request['user_id']);
            if ($license) {
                $this->licenseModel->update($license['id'], [
                    'package_id' => $request['requested_package_id'],
                    'status'     => 'active',
                    'starts_at'  => date('Y-m-d H:i:s'),
                ]);
            }

            $db->commit();

            ActivityLog::log('upgrade.approved', $adminId, 'upgrade_request', $requestId);

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Reject request (admin)
     */
    public function reject(int $requestId, int $adminId, string $note = ''): void
    {
        $request = $this->requestModel->find($requestId);
        if (!$request) throw new Exception('Anfrage nicht gefunden.');
        if ($request['status'] !== 'pending') throw new Exception('Anfrage wurde bereits bearbeitet.');

        $this->requestModel->update($requestId, [
            'status'      => 'rejected',
            'admin_note'  => $note ?: null,
            'reviewed_by' => $adminId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::log('upgrade.rejected', $adminId, 'upgrade_request', $requestId);
    }
}
