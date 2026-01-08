<?php
// Assumes session is already started in config.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    writeLog("CSRF generate: session not started", "ERROR");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server misconfiguration']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    writeLog("Generated CSRF token for session ID: " . session_id(), "SUCCESS");
}
