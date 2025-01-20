<?php

// Logout

require_once __DIR__ . '/../../../config.php';

//Logging
require_once ROOT . '/logging.php';

// Import necessary headers, likely used for setting up API response headers like Content-Type or CORS.
require_once ROOT . '/apiHeaders.php';

// Clear the refresh token cookie by setting it to expire in the past
setcookie(
    "refreshToken",
    "",
    [
        'expires' => time() - 3600,   // Set expiry time in the past
        'path' => '/',                // Same path as original
        'secure' => true,             // Same security flags as the original
        'httponly' => true,
        'samesite' => 'None'          // SameSite should match original setting
    ]
);

// Log the logout action for debugging
writeLog("[Success] Refresh token cookie cleared during logout.");

// Clearing CSRF token
session_save_path(ROOT . '/tmp'); // Set your custom session path if needed
session_start();
$sessid = session_id();
session_unset();
session_destroy();
writeLog("[Success] Session destroyed during logout. Session ID was " . $sessid);


// Respond with a success message
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
?>
