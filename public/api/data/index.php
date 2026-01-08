<?php
declare(strict_types=1);

// Data (admin)

require_once __DIR__ . '/../../../config.php';
require_once ROOT . '/logging.php';
require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/rateLimit.php';
require_once ROOT . '/auth.php';

// Connect to tenant SQLite database
$db = new SQLite3($DB_PATH);
if (!$db) {
    writeLog("Database connection failed on /api/data/. DB_PATH=" . $DB_PATH, "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$current_date = date('Y-m-d');

// Build query + params
$query = 'SELECT * FROM gaming_sessions WHERE date = :currentDate';
$params = [
    ':currentDate' => [$current_date, SQLITE3_TEXT],
];

// last_checked filter
if (isset($_GET['last_checked']) && $_GET['last_checked'] !== '') {
    $lastCheckedTimestamp = $_GET['last_checked'];
    if (filter_var($lastCheckedTimestamp, FILTER_VALIDATE_INT) !== false) {
        $query .= ' AND last_updated > :lastChecked';
        $params[':lastChecked'] = [(int)$lastCheckedTimestamp, SQLITE3_INTEGER];
    }
}

// computer filter (normalize to lowercase + validate)
if (isset($_GET['computer']) && $_GET['computer'] !== '') {
    $computer = strtolower(trim((string)$_GET['computer']));
    if (in_array($computer, $ALLOWED_DEVICE_IDS, true)) {
        $query .= ' AND computer = :computer';
        $params[':computer'] = [$computer, SQLITE3_TEXT];
    }
}

// id filter
if (isset($_GET['id']) && $_GET['id'] !== '' && filter_var($_GET['id'], FILTER_VALIDATE_INT) !== false) {
    $query .= ' AND id = :id';
    $params[':id'] = [(int)$_GET['id'], SQLITE3_INTEGER];
}

$query .= ' ORDER BY computer, time_slot';

// Prepare + bind
$stmt = $db->prepare($query);
if (!$stmt) {
    writeLog("Failed to prepare SQL on /api/data/: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']);
    $db->close();
    exit;
}

foreach ($params as $key => [$val, $type]) {
    $stmt->bindValue($key, $val, $type);
}

$results = $stmt->execute();
if (!$results) {
    writeLog("Query failed on /api/data/: " . $db->lastErrorMsg(), "Error")
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    $db->close();
    exit;
}

// Output shaping
$data = [];
// If you want this cap, consider deriving it from tenant config later.
// For now, keep your existing value.
$maxEntries = 500;
$count = 0;

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $data[] = [
        'id' => $row['id'],
        'computer' => $row['computer'],
        'time_slot' => $row['time_slot'],
        'navn' => $row['navn'],
        'lnr' => $row['lnr'],
        'last_updated' => $row['last_updated']
    ];
    if (++$count >= $maxEntries) break;
}

$db->close();

echo json_encode($data, JSON_UNESCAPED_UNICODE);
