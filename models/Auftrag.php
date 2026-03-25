<?php
class Auftrag extends Model
{
    protected string $table = 'auftraege';

    /**
     * Get today's aufträge for user
     */
    public function getToday(int $userId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT a.*, cl.from_number, cl.to_number, cl.caller_name, cl.direction
             FROM {$this->table} a
             LEFT JOIN call_logs cl ON a.call_log_id = cl.id
             WHERE a.user_id = ? AND DATE(a.created_at) = CURDATE()
             ORDER BY a.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Search aufträge (for past dates)
     */
    public function search(int $userId, string $search = '', string $dateFrom = '', string $dateTo = '', int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT a.*, cl.from_number, cl.to_number, cl.caller_name
                FROM {$this->table} a
                LEFT JOIN call_logs cl ON a.call_log_id = cl.id
                WHERE a.user_id = ?";
        $params = [$userId];

        if ($search) {
            $sql .= " AND (a.customer_name LIKE ? OR a.customer_phone LIKE ? OR a.customer_city LIKE ? OR cl.from_number LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($dateFrom) {
            $sql .= " AND DATE(a.created_at) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND DATE(a.created_at) <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countSearch(int $userId, string $search = '', string $dateFrom = '', string $dateTo = ''): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} a
                LEFT JOIN call_logs cl ON a.call_log_id = cl.id
                WHERE a.user_id = ?";
        $params = [$userId];

        if ($search) {
            $sql .= " AND (a.customer_name LIKE ? OR a.customer_phone LIKE ? OR a.customer_city LIKE ? OR cl.from_number LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($dateFrom) { $sql .= " AND DATE(a.created_at) >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $sql .= " AND DATE(a.created_at) <= ?"; $params[] = $dateTo; }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function createFromCall(int $userId, int $callLogId, array $data): int
    {
        $stmt = $this->db()->prepare(
            "INSERT INTO {$this->table} (user_id, call_log_id, customer_name, customer_address, customer_plz, customer_city, customer_phone, job_type, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId, $callLogId,
            $data['customer_name'] ?? '',
            $data['customer_address'] ?? '',
            $data['customer_plz'] ?? '',
            $data['customer_city'] ?? '',
            $data['customer_phone'] ?? '',
            $data['job_type'],
            $data['notes'] ?? null,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function updateStatus(int $id, int $userId, string $status): bool
    {
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByUser(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }
}
