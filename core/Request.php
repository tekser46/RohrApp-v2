<?php
/**
 * Request — HTTP request helper
 */
class Request
{
    private static ?array $bodyCache = null;

    /**
     * Get HTTP method
     */
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get JSON body (POST/PUT)
     */
    public static function body(): array
    {
        if (self::$bodyCache === null) {
            $raw = file_get_contents('php://input');
            self::$bodyCache = $raw ? (json_decode($raw, true) ?? []) : [];
        }
        return self::$bodyCache;
    }

    /**
     * Get a single body field with optional default
     */
    public static function input(string $key, $default = null)
    {
        return self::body()[$key] ?? $default;
    }

    /**
     * Get query parameter
     */
    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get integer query param
     */
    public static function queryInt(string $key, int $default = 0): int
    {
        return (int) ($_GET[$key] ?? $default);
    }

    /**
     * Get client IP address
     */
    public static function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /**
     * Get a request header
     */
    public static function header(string $name, $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    /**
     * Get bearer token from Authorization header
     */
    public static function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }
}
