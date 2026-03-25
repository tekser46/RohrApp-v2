<?php
/**
 * Validator — Input validation helpers
 */
class Validator
{
    public static function email(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function phone(string $phone): bool
    {
        // Allow: +49 641 12345, 0641-12345, etc.
        $clean = preg_replace('/[\s\-\(\)\/]/', '', $phone);
        return (bool) preg_match('/^\+?[0-9]{6,20}$/', $clean);
    }

    public static function url(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    public static function domain(string $domain): bool
    {
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$/', $domain);
    }

    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen(trim($value)) >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen(trim($value)) <= $max;
    }

    public static function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name);
    }
}
