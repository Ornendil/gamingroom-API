<?php
session_save_path(ROOT . '/tmp'); // Set your custom session path if needed
session_start();
// writeLog("[Debug] Session started with ID: " . session_id());

// Extract the CSRF token from the request headers
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// Validate the CSRF token
if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
    writeLog("[Error] CSRF token validation failed for session ID: " . session_id());
    writeLog("[Error] CSRF token from request: " . $csrfToken);
    writeLog("[Error] CSRF token from session: " . ($_SESSION['csrf_token'] ?? 'not set'));
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
    exit;
}
?>
