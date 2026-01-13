<?php
declare(strict_types=1);

define('ROOT', __DIR__); // This will hold the absolute path to the `gamingroom-admin` directory.

require_once ROOT . '/bootstrap.php';

$cookieDefaults = [
    'path'     => '/',              // Available throughout the domain
    // 'domain' => 'localhost',        // Explicitly set the domain to 'localhost'
    'secure'   => true,             // Only send over HTTPS
    'httponly' => true,             // Not accessible via JavaScript
    'samesite' => 'Strict',         // Prevents sending with cross-site requests
];

// CSRF Session config (once, globally)
session_save_path(ROOT . '/tmp');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 3600 * 24 * 7,
        ...$cookieDefaults,
    ]);
    session_start();
}