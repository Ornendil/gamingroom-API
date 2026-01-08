<?php
// Assumes session is already started in config.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    writeLog("CSRF validate: session not started", "Error");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server misconfiguration']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    writeLog("CSRF token validation failed for session ID: " . session_id(), "ERROR");
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
    exit;
}
