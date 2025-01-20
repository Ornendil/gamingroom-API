<?php
$rootUrl = 'https://biblioteket.globalgathering.no';
header('Content-Type: application/json');
// error_log(print_r($_COOKIE, true)); // Log all cookies

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Prevent Caching
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Pragma: no-cache');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$allowedOrigins = [
    $rootUrl,
    // If you're testing locally, you'll need to allow localhost too
    // 'http://127.0.0.1:3000',
    // 'http://localhost:3000',
    // 'http://127.0.0.1:3001',
    // 'http://localhost:3001',
    // 'http://localhost:3002',
    // 'https://127.0.0.1:3000',
    // 'https://localhost:3000',
    // 'https://127.0.0.1:3001',
    // 'https://localhost:3001',
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


// Handle OPTIONS request (CORS Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (in_array($origin, $allowedOrigins)) {
        // Allow from the trusted origin for preflight requests
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

        http_response_code(200);
    } else {
        writeLog("[Fail] Unauthorized origin: " . $origin);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized origin: ' . $origin]);
    }
    exit;
}

// Handle actual requests
if ($origin === '' || in_array($origin, $allowedOrigins)) {
    // Allow same-origin or if the origin is in the allowed list
    header('Access-Control-Allow-Origin: ' . ($origin === '' ? $rootUrl : $origin));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
} else {
    writeLog("[Fail] Unauthorized origin: " . $origin);
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized origin: ' . $origin]));
}

?>