<?php
/**
 * Middleware — Base interface
 * Each middleware implements handle().
 * If the check fails, call Response::error() which exits.
 */
abstract class Middleware
{
    /**
     * Run the middleware check.
     * Must call Response::unauthorized() / Response::forbidden() and exit on failure.
     */
    abstract public function handle(): void;
}
