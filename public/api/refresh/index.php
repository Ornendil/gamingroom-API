<?php

// Refresh (Public)

require_once __DIR__ . '/../../../config.php';

//Logging
require_once ROOT . '/logging.php';

require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/rateLimit.php';
require_once ROOT . '/jwt/refresh.php'; // Include the private logic script

// Step 1: Get Refresh Token from Request
$refreshToken = $_COOKIE['refreshToken'] ?? '';

writeLog("[Info] Refresh token received: " . ($refreshToken ? "YES" : "NO"));

writeLog("[Info] Cookies: " . json_encode($_COOKIE));

if (!$refreshToken) {
    writeLog("[Error] No refresh token provided in the request.");
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'No refresh token provided']);
    exit;
}

// Validate the refresh token and issue a new access token
$validationResult = refreshAccessToken($refreshToken);

if ($validationResult['status'] !== 'success') {
    writeLog("[Error] Refresh token validation failed: " . $validationResult['message']);
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => "test ".$validationResult['message']]);
    exit;
}

// If valid, return the new access token
writeLog("[Success] Refresh token validated successfully. Issuing new access token.");
echo json_encode([
    'status' => 'success',
    'accessToken' => $validationResult['accessToken']
]);

?>
