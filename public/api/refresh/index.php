<?php
declare(strict_types=1);

// Refresh (Public)

require_once __DIR__ . '/../../../config.php';
require_once ROOT . '/logging.php';
require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/rateLimit.php';
require_once ROOT . '/jwt/refresh.php';

// Only accept refresh token from HttpOnly cookie
$refreshToken = $_COOKIE['refreshToken'] ?? '';

if ($refreshToken === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No refresh token provided']);
    exit;
}

$validationResult = refreshAccessToken($refreshToken);

if (!is_array($validationResult) || ($validationResult['status'] ?? '') !== 'success') {
    // Keep logs non-sensitive
    writeLog("Refresh token validation failed for tenant " . ($TENANT_SLUG ?? 'unknown') . ": " . ($validationResult['message'] ?? 'unknown'), "Error");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => $validationResult['message'] ?? 'Invalid refresh token']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'accessToken' => $validationResult['accessToken']
], JSON_UNESCAPED_UNICODE);
