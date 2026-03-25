<?php
class SipgateNumber extends Model
{
    protected string $table = 'sipgate_numbers';

    public function getBySettingsId(int $settingsId): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE sipgate_settings_id = ? ORDER BY created_at DESC");
        $stmt->execute([$settingsId]);
        return $stmt->fetchAll();
    }

    public function add(int $settingsId, string $number, string $label = '', string $blockName = '', bool $isBlocked = false): int
    {
        $number = preg_replace('/[^0-9+]/', '', $number);
        $stmt = $this->db()->prepare("INSERT INTO {$this->table} (sipgate_settings_id, number, label, block_name, is_blocked) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$settingsId, $number, $label, $blockName, $isBlocked ? 1 : 0]);
        return (int) $this->db()->lastInsertId();
    }

    public function remove(int $id, int $settingsId): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE id = ? AND sipgate_settings_id = ?");
        $stmt->execute([$id, $settingsId]);
        return $stmt->rowCount() > 0;
    }

    public function toggleBlock(int $id, int $settingsId): bool
    {
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET is_blocked = NOT is_blocked WHERE id = ? AND sipgate_settings_id = ?");
        $stmt->execute([$id, $settingsId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Find matching number across all users — for webhook call matching
     * Returns sipgate_settings.user_id if matched
     */
    public function findByNumber(string $number): ?array
    {
        // Normalize: remove spaces, dashes, keep only digits and +
        $normalized = preg_replace('/[^0-9+]/', '', $number);

        // Try exact match first
        $stmt = $this->db()->prepare(
            "SELECT sn.*, ss.user_id FROM {$this->table} sn
             JOIN sipgate_settings ss ON sn.sipgate_settings_id = ss.id
             WHERE sn.number = ? AND sn.is_active = 1 AND ss.is_enabled = 1 LIMIT 1"
        );
        $stmt->execute([$normalized]);
        $result = $stmt->fetch();
        if ($result) return $result;

        // Try suffix match (last 8-10 digits) for international format differences
        if (strlen($normalized) >= 8) {
            $suffix = substr($normalized, -10);
            $stmt = $this->db()->prepare(
                "SELECT sn.*, ss.user_id FROM {$this->table} sn
                 JOIN sipgate_settings ss ON sn.sipgate_settings_id = ss.id
                 WHERE sn.number LIKE ? AND sn.is_active = 1 AND ss.is_enabled = 1 LIMIT 1"
            );
            $stmt->execute(['%' . $suffix]);
            return $stmt->fetch() ?: null;
        }

        return null;
    }
}
