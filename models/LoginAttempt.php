<?php
/**
 * LoginAttempt Model — Brute force protection
 */
class LoginAttempt extends Model
{
    protected string $table = 'login_attempts';

    /**
     * Check if IP is locked out
     */
    public function isLocked(string $ip): bool
    {
        $stmt = $this->db()->prepare(
            "SELECT locked_until FROM {$this->table} WHERE ip_address = ? AND locked_until > NOW() LIMIT 1"
        );
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        return $row !== false;
    }

    /**
     * Record a failed attempt, lock if threshold reached
     * @return int Current attempt count
     */
    public function recordFailure(string $ip, string $email, int $maxAttempts = 5, int $lockoutSeconds = 900): int
    {
        $db = $this->db();

        $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        if ($row) {
            $count = $row['attempt_count'] + 1;
            $lockUntil = $count >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockoutSeconds) : null;

            $db->prepare("UPDATE {$this->table} SET attempt_count = ?, email = ?, locked_until = ?, last_attempt_at = NOW() WHERE id = ?")
               ->execute([$count, $email, $lockUntil, $row['id']]);

            return $count;
        } else {
            $this->create([
                'ip_address'      => $ip,
                'email'           => $email,
                'attempt_count'   => 1,
                'last_attempt_at' => date('Y-m-d H:i:s'),
            ]);
            return 1;
        }
    }

    /**
     * Clear attempts on successful login
     */
    public function clearAttempts(string $ip): void
    {
        $this->db()->prepare("DELETE FROM {$this->table} WHERE ip_address = ?")->execute([$ip]);
    }
}
