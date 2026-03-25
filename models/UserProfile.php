<?php
/**
 * UserProfile Model
 */
class UserProfile extends Model
{
    protected string $table = 'user_profiles';

    /**
     * Get profile by user ID
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /**
     * Update profile by user ID (not by profile ID)
     */
    public function updateByUserId(int $userId, array $data): bool
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET {$set} WHERE user_id = ?");
        return $stmt->execute([...array_values($data), $userId]);
    }
}
