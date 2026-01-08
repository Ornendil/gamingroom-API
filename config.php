<?php
declare(strict_types=1);

define('ROOT', __DIR__); // This will hold the absolute path to the `gamingroom-admin` directory.

require_once ROOT . '/bootstrap.php';

// CSRF Session config (once, globally)
session_save_path(ROOT . '/tmp');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 3600 * 24 * 7,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}