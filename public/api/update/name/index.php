<?php

declare(strict_types=1);

// Update player name for session

require_once __DIR__ . '/../../../../config.php';

//Logging
require_once ROOT . '/logging.php';

// Import necessary headers, likely used for setting up API response headers like Content-Type or CORS.
require_once ROOT . '/apiHeaders.php';

// Include rate limit
require_once ROOT . '/rateLimit.php';

// Include CSRF validation
require_once ROOT . '/csrf/validate.php';

// Include JWT Authorization
require_once ROOT . '/auth.php';

// DB
if (!file_exists($DB_PATH)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database missing']);
    exit;
}

$db = new SQLite3($DB_PATH);
if (!$db) {
    writeLog("Database connection failed on update/name. DB_PATH=" . $DB_PATH, "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Read input (form or JSON)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = $json;
    }
}

$id   = $input['id'] ?? null;
$navn = $input['navn'] ?? null;

if ($id === null || $navn === null) {
    writeLog("update/name missing required parameters.", "Error");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    $db->close();
    exit;
}

if (filter_var($id, FILTER_VALIDATE_INT) === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
    $db->close();
    exit;
}
$id = (int)$id;

// Trim and lightly sanitize navn (keep it permissive)
$navn = trim((string)$navn);
if ($navn === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid navn']);
    $db->close();
    exit;
}
// Optional: cap length to keep DB tidy
if (mb_strlen($navn) > 50) {
    $navn = mb_substr($navn, 0, 50);
}

$stmt = $db->prepare('
    UPDATE gaming_sessions
    SET navn = :navn,
        last_updated = :last_updated
    WHERE id = :id
');

if (!$stmt) {
    writeLog("Failed to prepare SQL statement on update/name: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']);
    $db->close();
    exit;
}

$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':navn', $navn, SQLITE3_TEXT);
$stmt->bindValue(':last_updated', time(), SQLITE3_INTEGER);

$res = $stmt->execute();

if ($res) {
    if ($db->changes() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Session not found']);
    } else {
        echo json_encode(['status' => 'success', 'id' => $id, 'navn' => $navn], JSON_UNESCAPED_UNICODE);
    }
} else {
    writeLog("Failed to update name for session: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update session.', 'id' => $id]);
}

$db->close();