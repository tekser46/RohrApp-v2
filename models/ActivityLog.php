<?php
/**
 * ActivityLog Model
 */
class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /**
     * Log an action
     */
    public static function log(string $action, ?int $userId = null, ?string $targetType = null, ?int $targetId = null, ?array $details = null): void
    {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO activity_logs (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
           ->execute([
               $userId ?? Session::userId(),
               $action,
               $targetType,
               $targetId,
               $details ? json_encode($details) : null,
               Request::ip(),
           ]);
    }

    /**
     * Get recent logs (admin)
     */
    public function getRecent(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db()->prepare("
            SELECT a.*, u.email AS user_email
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
}
