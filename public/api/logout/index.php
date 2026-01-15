<?php
declare(strict_types=1);

// Logout

require_once __DIR__ . '/../../../config.php';
require_once ROOT . '/logging.php';
require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/rateLimit.php';
// require_once ROOT . '/csrf/validate.php';
// require_once ROOT . '/auth.php';

// Clear refresh token cookie (match original attributes!)
setcookie(
    "refreshToken",
    "",
    [
        'expires'  => time() - 3600,
        ...$cookieDefaults,
        // Do NOT set 'domain' unless you set it when creating the cookie
    ]
);

// Destroy session (config.php should already have started it)
$sessid = (session_status() === PHP_SESSION_ACTIVE) ? session_id() : '';
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    session_unset();
    session_destroy();
}

// Optionally clear the PHP session cookie in the browser too
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        "",
        [
            'expires'  => time() - 3600,
            ...$cookieDefaults,
        ]
    );
}

writeLog("Logout completed. Session ID was " . ($sessid ?: 'none'), "Success");

echo json_encode(['status' => 'success', 'message' => 'Logged out successfully'], JSON_UNESCAPED_UNICODE);
