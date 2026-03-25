<?php
class WebhookLog extends Model
{
    protected string $table = 'webhook_logs';

    public function log(string $token, string $eventType, string $payload, string $ip, ?int $userId = null, ?int $callLogId = null, bool $processed = false): int
    {
        $stmt = $this->db()->prepare(
            "INSERT INTO {$this->table} (webhook_token, event_type, payload, ip_address, matched_user_id, call_log_id, processed)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$token, $eventType, $payload, $ip, $userId, $callLogId, $processed ? 1 : 0]);
        return (int) $this->db()->lastInsertId();
    }

    public function getRecent(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
}
