<?php
/**
 * Controller — Base controller class
 * All controllers extend this.
 */
abstract class Controller
{
    protected function db(): PDO
    {
        return Database::getInstance();
    }

    protected function config(): array
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__) . '/config/app.php';
        }
        return $config;
    }
}
