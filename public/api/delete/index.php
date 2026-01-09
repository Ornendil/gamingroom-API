<?php

declare(strict_types=1);

// Delete session

require_once __DIR__ . '/../../../config.php';

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
    writeLog("DB connection failed on delete. DB_PATH=" . $DB_PATH, "Error");
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

$id = $input['id'] ?? null;

if ($id === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameter: id.']);
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

// Recommended safety: only delete today's sessions
$current_date = date('Y-m-d');

$stmt = $db->prepare('DELETE FROM gaming_sessions WHERE id = :id');
if (!$stmt) {
    writeLog("Failed to prepare delete statement: " . $db->lastErrorMsg(), "Error");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']);
    $db->close();
    exit;
}

$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':date', $current_date, SQLITE3_TEXT);

$res = $stmt->execute();

if ($res) {
    if ($db->changes() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Session not found', 'id' => $id]);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Session deleted successfully.', 'id' => $id]);
    }
} else {
    writeLog("Failed to delete session id=$id: " . $db->lastErrorMsg(), "Error");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete session.', 'id' => $id]);
}

$db->close();