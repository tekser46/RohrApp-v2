<?php
/**
 * RateLimitMiddleware — IP-based rate limiting via database
 * No file permissions needed — uses login_attempts table
 */
class RateLimitMiddleware extends Middleware
{
    private int $maxAttempts;
    private int $windowSeconds;
    private string $prefix;

    public function __construct(int $maxAttempts = 5, int $windowSeconds = 900, string $prefix = 'default')
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->prefix = $prefix;
    }

    public function handle(): void
    {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $key = $this->prefix . ':' . $ip;
            $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds);

            // Count recent attempts
            $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND email = ? AND last_attempt_at > ?");
            $stmt->execute([$ip, $key, $cutoff]);
            $count = (int) $stmt->fetchColumn();

            if ($count >= $this->maxAttempts) {
                Response::error('Zu viele Anfragen. Bitte warten Sie ' . ceil($this->windowSeconds / 60) . ' Minuten.', 429);
            }

            // Record attempt
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, last_attempt_at) VALUES (?, ?, NOW())");
            $stmt->execute([$ip, $key]);

            // Cleanup old entries (1% chance per request)
            if (mt_rand(1, 100) === 1) {
                $db->prepare("DELETE FROM login_attempts WHERE last_attempt_at < ?")->execute([$cutoff]);
            }
        } catch (Exception $e) {
            // If DB fails, skip rate limiting — don't block the request
        }
    }

    /**
     * Clear rate limit for an IP (e.g., after successful login)
     */
    public static function clear(string $prefix = 'default'): void
    {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $key = $prefix . ':' . $ip;
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND email = ?")->execute([$ip, $key]);
        } catch (Exception $e) {
            // Silently ignore
        }
    }
}
