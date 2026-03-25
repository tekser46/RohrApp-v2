<?php
/**
 * Session — Secure session management
 */
class Session
{
    private static bool $started = false;

    /**
     * Start session with secure settings
     */
    public static function start(): void
    {
        if (self::$started) return;

        $config = require dirname(__DIR__) . '/config/app.php';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        // Secure cookie only on HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        session_name($config['session_name'] ?? 'rohrapp_session');
        session_start();

        self::$started = true;
    }

    /**
     * Get session value
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session completely
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$started = false;
    }

    /**
     * Regenerate session ID (call after login for fixation protection)
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Get current authenticated user ID, or null
     */
    public static function userId(): ?int
    {
        $id = self::get('user_id');
        return $id ? (int) $id : null;
    }

    /**
     * Get current user role
     */
    public static function userRole(): ?string
    {
        return self::get('user_role');
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool
    {
        return self::userId() !== null;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool
    {
        return self::userRole() === 'admin';
    }

    /**
     * Get or generate CSRF token
     */
    public static function csrfToken(): string
    {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }
}
