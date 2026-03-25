<?php
/**
 * CsrfMiddleware — CSRF token verification for POST/PUT/DELETE
 */
class CsrfMiddleware extends Middleware
{
    public function handle(): void
    {
        $method = Request::method();
        if (!in_array($method, ['POST', 'PUT', 'DELETE'])) return;

        $token = Request::header('X-CSRF-Token');
        $sessionToken = Session::get('csrf_token');

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            Response::error('Ungültiger CSRF-Token', 403, 'CSRF_ERROR');
        }
    }
}
