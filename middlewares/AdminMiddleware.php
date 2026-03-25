<?php
/**
 * AdminMiddleware — Requires admin role
 */
class AdminMiddleware extends Middleware
{
    public function handle(): void
    {
        if (!Session::isAuthenticated()) {
            Response::unauthorized();
        }
        if (!Session::isAdmin()) {
            Response::forbidden('Nur Administratoren haben Zugriff.');
        }
    }
}
