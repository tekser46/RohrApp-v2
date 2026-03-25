<?php
/**
 * LicenseController — License & package info
 */
class LicenseController extends Controller
{
    /**
     * GET /api/license
     * Get current user's active license with package details
     */
    public function current(array $params = []): void
    {
        $userId = Session::userId();
        $license = (new UserLicense())->getCurrentLicense($userId);

        if (!$license) {
            Response::error('Keine Lizenz gefunden', 404, 'NOT_FOUND');
        }

        // Parse features JSON
        if (isset($license['features']) && is_string($license['features'])) {
            $license['features'] = json_decode($license['features'], true);
        }

        Response::success($license);
    }

    /**
     * GET /api/license/packages
     * List all available packages
     */
    public function packages(array $params = []): void
    {
        $packages = (new Package())->getAllActive();

        // Parse features JSON for each package
        foreach ($packages as &$pkg) {
            if (isset($pkg['features']) && is_string($pkg['features'])) {
                $pkg['features'] = json_decode($pkg['features'], true);
            }
        }

        Response::success($packages);
    }
}
