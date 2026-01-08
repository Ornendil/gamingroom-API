<?php
declare(strict_types=1);

header('Content-Type: application/json');

// Prevent caching
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ------------------- Tenant-aware CORS -------------------

// Expect bootstrap/config to have set $TENANT_CONFIG.
// Fall back to empty list (deny all cross-origin) if missing/misconfigured.
$allowedOrigins = [];
if (isset($TENANT_CONFIG) && is_array($TENANT_CONFIG) && isset($TENANT_CONFIG['allowedOrigins']) && is_array($TENANT_CONFIG['allowedOrigins'])) {
    $allowedOrigins = $TENANT_CONFIG['allowedOrigins'];
}

// Normalize list (trim)
$allowedOrigins = array_values(array_filter(array_map(
    fn($o) => is_string($o) ? trim($o) : '',
    $allowedOrigins
)));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// helper: log safely
$log = function(string $msg): void {
    if (function_exists('writeLog')) {
        writeLog($msg);
    }
};

// If no Origin header, this is not a CORS request (curl, same-origin navigation, server-to-server).
// We simply don't emit Access-Control-Allow-Origin, and let it work normally.
if ($origin === '') {
    // still helpful for some clients:
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    return;
}

$isAllowed = in_array($origin, $allowedOrigins, true);

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    if ($isAllowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        http_response_code(200);
        exit;
    }

    $log("[Fail] Unauthorized origin (preflight): " . $origin);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized origin']);
    exit;
}

// Actual request
if ($isAllowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    return;
}

$log("[Fail] Unauthorized origin: " . $origin);
http_response_code(403);
echo json_encode(['status' => 'error', 'message' => 'Unauthorized origin']);
exit;
