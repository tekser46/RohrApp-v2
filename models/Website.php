<?php
/**
 * Website Model — User's connected websites/domains
 */
class Website extends Model
{
    protected string $table = 'websites';

    /**
     * Get all websites for user
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Find by API key (for webhook auth)
     */
    public function findByApiKey(string $apiKey): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE api_key = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$apiKey]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create website
     */
    public function createForUser(int $userId, string $domain, string $name = ''): array
    {
        $apiKey = bin2hex(random_bytes(32)); // 64 char unique key
        $stmt = $this->db()->prepare("INSERT INTO {$this->table} (user_id, domain, name, api_key) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $domain, $name, $apiKey]);
        return [
            'id' => (int) $this->db()->lastInsertId(),
            'api_key' => $apiKey,
        ];
    }

    /**
     * Delete website (owned by user)
     */
    public function deleteByUser(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle active status
     */
    public function toggleActive(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }
}
