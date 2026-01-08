<?php
declare(strict_types=1);

// Save session

require_once __DIR__ . '/../../../config.php';
require_once ROOT . '/logging.php';
require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/rateLimit.php';
require_once ROOT . '/csrf/validate.php';
require_once ROOT . '/auth.php';

// Connect to tenant SQLite database
if (!file_exists($DB_PATH)) {
    // If you create DB lazily, you can remove this check.
    // Keeping it makes misconfig obvious.
    writeLog("DB missing on save. DB_PATH=" . $DB_PATH, "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database missing']);
    exit;
}

$db = new SQLite3($DB_PATH);
if (!$db) {
    writeLog("DB connection failed on save. DB_PATH=" . $DB_PATH, "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$db->exec('PRAGMA journal_mode = WAL;');

// Create table if it doesn't exist (NO status column)
$db->exec("
CREATE TABLE IF NOT EXISTS gaming_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT,
    last_updated INTEGER,
    lnr TEXT,
    navn TEXT,
    computer TEXT,
    time_slot INTEGER,
    fra TEXT,
    til TEXT
)
");

// ---- Read input: accept both form POST and JSON --------------

$input = $_POST;

if (empty($input)) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = $json;
    }
}

$navn     = trim((string)($input['navn'] ?? ''));
$lnr      = trim((string)($input['lnr'] ?? '')); // optional
$computer = strtolower(trim((string)($input['computer'] ?? ''))); // device id
$timeSlot = $input['time_slot'] ?? null;
$fra      = trim((string)($input['fra'] ?? ''));
$til      = trim((string)($input['til'] ?? ''));

// Basic required fields
if ($navn === '' || $computer === '' || $fra === '' || $til === '' || $timeSlot === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    $db->close();
    exit;
}

// Cap lengths (avoid paste bombs)
if (mb_strlen($navn) > 50) $navn = mb_substr($navn, 0, 50);
if (mb_strlen($lnr) > 50)  $lnr  = mb_substr($lnr, 0, 50);

// Validate time_slot
if (filter_var($timeSlot, FILTER_VALIDATE_INT) === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid time_slot']);
    $db->close();
    exit;
}
$timeSlot = (int)$timeSlot;

// Validate device
if (!in_array($computer, $ALLOWED_DEVICE_IDS, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid device']);
    $db->close();
    exit;
}

// Validate time format HH:MM
$hhmm = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
if (!preg_match($hhmm, $fra) || !preg_match($hhmm, $til)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid time format']);
    $db->close();
    exit;
}

// Display name primary; keep lnr for compatibility but default it to navn.
if ($lnr === '') {
    $lnr = $navn;
}

$current_date = date('Y-m-d');
$now = time();

// Insert session
$stmt = $db->prepare('
    INSERT INTO gaming_sessions (date, last_updated, computer, time_slot, fra, til, navn, lnr)
    VALUES (:date, :last_updated, :computer, :time_slot, :fra, :til, :navn, :lnr)
');

if (!$stmt) {
    writeLog("Failed to prepare INSERT on save: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']);
    $db->close();
    exit;
}

$stmt->bindValue(':date', $current_date, SQLITE3_TEXT);
$stmt->bindValue(':last_updated', $now, SQLITE3_INTEGER);
$stmt->bindValue(':computer', $computer, SQLITE3_TEXT);
$stmt->bindValue(':time_slot', $timeSlot, SQLITE3_INTEGER);
$stmt->bindValue(':fra', $fra, SQLITE3_TEXT);
$stmt->bindValue(':til', $til, SQLITE3_TEXT);
$stmt->bindValue(':navn', $navn, SQLITE3_TEXT);
$stmt->bindValue(':lnr', $lnr, SQLITE3_TEXT);

$res = $stmt->execute();
if (!$res) {
    writeLog("Failed to save new session: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save new session.']);
    $db->close();
    exit;
}

// Fetch sessions for today
$current_sessions_stmt = $db->prepare("
    SELECT *
    FROM gaming_sessions
    WHERE date = :current_date
    ORDER BY computer, time_slot
");

if (!$current_sessions_stmt) {
    writeLog("Failed to prepare SELECT on save: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']);
    $db->close();
    exit;
}

$current_sessions_stmt->bindValue(':current_date', $current_date, SQLITE3_TEXT);
$r = $current_sessions_stmt->execute();

$sessions = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $sessions[] = $row;
}

// ---- Retention ---------------------------------------------

$retentionDays = 1;
if (isset($TENANT_CONFIG['retentionDays']) && filter_var($TENANT_CONFIG['retentionDays'], FILTER_VALIDATE_INT) !== false) {
    $retentionDays = max(1, (int)$TENANT_CONFIG['retentionDays']);
}

// If retentionDays=1 => delete dates < today
$delete_stmt = $db->prepare("DELETE FROM gaming_sessions WHERE date < strftime('%Y-%m-%d', 'now', :offset)");
if ($delete_stmt) {
    $delete_stmt->bindValue(':offset', '-' . ($retentionDays - 1) . ' day', SQLITE3_TEXT);
    $delete_stmt->execute();
} else {
    writeLog("Failed to prepare retention delete: " . $db->lastErrorMsg(), "Warn")
}

$db->close();

echo json_encode(['status' => 'success', 'sessions' => $sessions], JSON_UNESCAPED_UNICODE);
