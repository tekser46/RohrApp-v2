<?php
/**
 * PasswordReset Model
 */
class PasswordReset extends Model
{
    protected string $table = 'password_resets';

    /**
     * Create a reset token (invalidate old ones first)
     */
    public function createToken(int $userId): string
    {
        $db = $this->db();

        // Invalidate old tokens
        $db->prepare("DELETE FROM {$this->table} WHERE user_id = ?")->execute([$userId]);

        // New token, valid 1 hour
        $token = bin2hex(random_bytes(32));
        $this->create([
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        return $token;
    }

    /**
     * Find valid (unused, not expired) token
     */
    public function findValidToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM {$this->table} WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Mark token as used
     */
    public function markUsed(int $id): void
    {
        $this->update($id, ['used_at' => date('Y-m-d H:i:s')]);
    }
}
