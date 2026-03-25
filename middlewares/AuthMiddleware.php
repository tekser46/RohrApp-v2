<?php
/**
 * AuthMiddleware — Requires authenticated session
 */
class AuthMiddleware extends Middleware
{
    public function handle(): void
    {
        if (!Session::isAuthenticated()) {
            Response::unauthorized();
        }
    }
}
