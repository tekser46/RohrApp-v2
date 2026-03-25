<?php
class SipgateSetting extends Model
{
    protected string $table = 'sipgate_settings';

    public function getByUser(int $userId): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function createForUser(int $userId): array
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db()->prepare("INSERT INTO {$this->table} (user_id, webhook_token) VALUES (?, ?)");
        $stmt->execute([$userId, $token]);
        return ['id' => (int) $this->db()->lastInsertId(), 'webhook_token' => $token];
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE webhook_token = ? AND is_enabled = 1");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function enable(int $userId, bool $enabled = true): void
    {
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET is_enabled = ? WHERE user_id = ?");
        $stmt->execute([$enabled ? 1 : 0, $userId]);
    }
}
