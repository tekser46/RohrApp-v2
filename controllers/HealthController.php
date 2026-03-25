<?php
/**
 * HealthController — System health & version check
 */
class HealthController extends Controller
{
    /**
     * GET /api/health
     * Basic health check — DB connection test
     */
    public function index(array $params = []): void
    {
        try {
            $db = $this->db();
            $db->query('SELECT 1');
            $dbStatus = 'connected';
        } catch (Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        $config = $this->config();

        Response::success([
            'status'   => 'ok',
            'app'      => $config['name'],
            'version'  => $config['version'],
            'database' => $dbStatus,
            'php'      => PHP_VERSION,
            'time'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /api/version
     */
    public function version(array $params = []): void
    {
        $config = $this->config();
        Response::success([
            'version' => $config['version'],
            'name'    => $config['name'],
        ]);
    }
}
