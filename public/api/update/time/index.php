<?php

declare(strict_types=1);

// Update time slot and station for session

require_once __DIR__ . '/../../../../config.php';

//Logging
require_once ROOT . '/logging.php';
// writeLog("Config loaded.", "Data fetch");

// Import necessary headers, likely used for setting up API response headers like Content-Type or CORS.
require_once ROOT . '/apiHeaders.php';
// writeLog("API headers loaded.", "Data fetch");

// Include rate limit
require_once ROOT . '/rateLimit.php';
// writeLog("Rate limit set.", "Data fetch");

// Include CSRF validation
require_once ROOT . '/csrf/validate.php';
// writeLog("CSRF validated.", "Data fetch");

// Include JWT Authorization
require_once ROOT . '/auth.php';
// writeLog("Authenticated.", "Data fetch");

// Connect to SQLite database
if (!file_exists($DB_PATH)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database missing']);
    exit;
}

$db = new SQLite3($DB_PATH);

if (!$db) {
    writeLog("Database connection failed on data fetch for public screen.", "Error");
    http_response_code(500);  // Set a proper HTTP response code
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
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

$id       = $input['id'] ?? null;
$slot     = $input['slot'] ?? null;
$fra      = $input['fra'] ?? null;
$til      = $input['til'] ?? null;
$computer = $input['computer'] ?? null;

// Validate required fields
if ($id === null || $slot === null || $computer === null || $fra === null || $til === null) {
    writeLog("update/time missing required parameters.", "Error");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    $db->close();
    exit;
}

// Validate id + slot
if (filter_var($id, FILTER_VALIDATE_INT) === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
    $db->close();
    exit;
}
$id = (int)$id;

if (filter_var($slot, FILTER_VALIDATE_INT) === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid slot']);
    $db->close();
    exit;
}
$slot = (int)$slot;

// Normalize + validate device id
$computer = strtolower(trim((string)$computer));
if (!in_array($computer, $ALLOWED_DEVICE_IDS, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid device']);
    $db->close();
    exit;
}

// Normalize fra/til
$fra = trim((string)$fra);
$til = trim((string)$til);

// Optional: basic HH:MM check (keeps garbage out)
$hhmm = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
if (!preg_match($hhmm, $fra) || !preg_match($hhmm, $til)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid time format']);
    $db->close();
    exit;
}

// Update
$stmt = $db->prepare('
    UPDATE gaming_sessions
    SET time_slot = :time_slot,
        fra = :fra,
        til = :til,
        computer = :computer,
        last_updated = :last_updated
    WHERE id = :id
');

if (!$stmt) {
    writeLog("Failed to prepare SQL statement on update/time: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']);
    $db->close();
    exit;
}

$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':time_slot', $slot, SQLITE3_INTEGER);
$stmt->bindValue(':fra', $fra, SQLITE3_TEXT);
$stmt->bindValue(':til', $til, SQLITE3_TEXT);
$stmt->bindValue(':computer', $computer, SQLITE3_TEXT);
$stmt->bindValue(':last_updated', time(), SQLITE3_INTEGER);

$result = $stmt->execute();

if ($result) {
    // Optional: check if any row was actually updated
    if ($db->changes() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Session not found']);
    } else {
        echo json_encode(['status' => 'success', 'id' => $id, 'slot' => $slot, 'computer' => $computer]);
    }
} else {
    writeLog("Failed to execute SQL on update/time: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update session.']);
}

$db->close();