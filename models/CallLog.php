<?php
class CallLog extends Model
{
    protected string $table = 'call_logs';

    private function buildWhere(int $userId, string $direction, string $status, string $search, string $category = '', string $dateFrom = '', string $dateTo = '', bool $todayOnly = false): array
    {
        $sql = " WHERE user_id = ?";
        $params = [$userId];

        if ($direction) { $sql .= " AND direction = ?"; $params[] = $direction; }
        if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
        if ($category) { $sql .= " AND category = ?"; $params[] = $category; }
        if ($search) {
            $sql .= " AND (from_number LIKE ? OR to_number LIKE ? OR caller_name LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($dateFrom) { $sql .= " AND DATE(started_at) >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $sql .= " AND DATE(started_at) <= ?"; $params[] = $dateTo; }
        if ($todayOnly && !$search && !$dateFrom && !$dateTo) {
            $sql .= " AND DATE(started_at) = CURDATE()";
        }

        return [$sql, $params];
    }

    public function getByUser(int $userId, int $limit = 30, int $offset = 0, string $direction = '', string $status = '', string $search = '', string $sort = 'date_desc', string $category = '', string $dateFrom = '', string $dateTo = '', bool $todayOnly = false): array
    {
        [$where, $params] = $this->buildWhere($userId, $direction, $status, $search, $category, $dateFrom, $dateTo, $todayOnly);
        $orderDir = ($sort === 'date_asc') ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM {$this->table}" . $where . " ORDER BY started_at {$orderDir} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByUser(int $userId, string $direction = '', string $status = '', string $search = '', string $category = '', string $dateFrom = '', string $dateTo = '', bool $todayOnly = false): int
    {
        [$where, $params] = $this->buildWhere($userId, $direction, $status, $search, $category, $dateFrom, $dateTo, $todayOnly);
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM {$this->table}" . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function existsBySipgateCallId(string $callId): bool
    {
        $stmt = $this->db()->prepare("SELECT 1 FROM {$this->table} WHERE sipgate_call_id = ? LIMIT 1");
        $stmt->execute([$callId]);
        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            "INSERT INTO {$this->table} (user_id, sipgate_call_id, direction, from_number, to_number, caller_name, status, started_at, answered_at, ended_at, duration, matched_number_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['user_id'], $data['sipgate_call_id'], $data['direction'],
            $data['from_number'], $data['to_number'], $data['caller_name'] ?? '',
            $data['status'] ?? 'ringing',
            $data['started_at'] ?? null, $data['answered_at'] ?? null, $data['ended_at'] ?? null,
            $data['duration'] ?? 0, $data['matched_number_id'] ?? null,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function updateStatus(string $sipgateCallId, string $status, ?string $answeredAt = null, ?string $endedAt = null, int $duration = 0): void
    {
        $sql = "UPDATE {$this->table} SET status = ?";
        $params = [$status];

        if ($answeredAt) { $sql .= ", answered_at = ?"; $params[] = $answeredAt; }
        if ($endedAt) { $sql .= ", ended_at = ?"; $params[] = $endedAt; }
        if ($duration > 0) { $sql .= ", duration = ?"; $params[] = $duration; }

        $sql .= " WHERE sipgate_call_id = ?";
        $params[] = $sipgateCallId;

        $this->db()->prepare($sql)->execute($params);
    }
}
