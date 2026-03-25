<?php
/**
 * UpgradeRequest Model
 */
class UpgradeRequest extends Model
{
    protected string $table = 'license_upgrade_requests';

    /**
     * Get requests for a user
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db()->prepare("
            SELECT r.*, cp.name AS current_package_name, rp.name AS requested_package_name
            FROM {$this->table} r
            JOIN packages cp ON cp.id = r.current_package_id
            JOIN packages rp ON rp.id = r.requested_package_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all requests (admin) with user info
     */
    public function getAllWithUser(string $status = ''): array
    {
        $sql = "
            SELECT r.*, u.email AS user_email,
                   CONCAT(p.first_name, ' ', p.last_name) AS user_name,
                   p.company_name AS user_company,
                   cp.name AS current_package_name, cp.slug AS current_package_slug,
                   rp.name AS requested_package_name, rp.slug AS requested_package_slug,
                   admin.email AS reviewer_email
            FROM {$this->table} r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN user_profiles p ON p.user_id = r.user_id
            JOIN packages cp ON cp.id = r.current_package_id
            JOIN packages rp ON rp.id = r.requested_package_id
            LEFT JOIN users admin ON admin.id = r.reviewed_by
        ";
        $params = [];
        if ($status) {
            $sql .= " WHERE r.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Check if user has pending request
     */
    public function hasPending(int $userId): bool
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
