<?php
session_save_path(ROOT . '/tmp'); // Set your custom session path if needed

session_set_cookie_params([
    'lifetime' => 3600 * 24 * 7,
    'path' => '/',
    'secure' => true, // Only set over HTTPS
    'httponly' => true,
    'samesite' => 'Strict' // Allow cross-origin cookie use
]);

session_start();
// writeLog("[Debug] Session started with ID: " . session_id());

// Generate a CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    writeLog("[Success] Generated CSRF token: " . $_SESSION['csrf_token']);
    writeLog("[Success] Session ID on generating CSRF token: " . session_id());
}
?>