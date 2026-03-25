<?php
/**
 * Response — Standardized JSON responses
 */
class Response
{
    /**
     * Success response
     */
    public static function success($data = null, string $message = '', int $code = 200): void
    {
        http_response_code($code);
        $body = ['success' => true];
        if ($data !== null) $body['data'] = $data;
        if ($message)       $body['message'] = $message;
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Paginated list response
     */
    public static function paginated(array $data, int $total, int $page, int $perPage): void
    {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / max($perPage, 1)),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Error response
     */
    public static function error(string $message, int $code = 400, string $errorCode = 'ERROR', array $fields = []): void
    {
        http_response_code($code);
        $body = [
            'success' => false,
            'error'   => [
                'code'    => $errorCode,
                'message' => $message,
            ],
        ];
        if ($fields) $body['error']['fields'] = $fields;
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Nicht autorisiert'): void
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }

    /**
     * 403 Forbidden
     */
    public static function forbidden(string $message = 'Zugriff verweigert'): void
    {
        self::error($message, 403, 'FORBIDDEN');
    }

    /**
     * 404 Not Found
     */
    public static function notFound(string $message = 'Nicht gefunden'): void
    {
        self::error($message, 404, 'NOT_FOUND');
    }

    /**
     * 429 Too Many Requests
     */
    public static function tooManyRequests(string $message = 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.'): void
    {
        self::error($message, 429, 'RATE_LIMIT');
    }
}
