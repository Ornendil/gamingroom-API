<?php

// Get all sessions for the day

require_once __DIR__ . '/../../../config.php';
require_once ROOT . '/logging.php';
// writeLog("[Data fetch] Config loaded.");
require_once ROOT . '/apiHeaders.php';
// writeLog("[Data fetch] API headers loaded.");
require_once ROOT . '/rateLimit.php';
// writeLog("[Data fetch] Rate limit set.");

// Connect to SQLite database
$db = new SQLite3(ROOT . '/gamingrom.db');
if (!$db) {
    writeLog("[Error] Database connection failed on data fetch for public screen.");
    http_response_code(500);  // Set a proper HTTP response code
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

$query = 'SELECT * FROM gaming_sessions WHERE date = :currentDate';

if (isset($_GET['last_checked']) && !empty($_GET['last_checked'])) {
    $lastCheckedTimestamp = $_GET['last_checked'];
    if (filter_var($lastCheckedTimestamp, FILTER_VALIDATE_INT)) {
        $query .= ' AND last_updated > :lastChecked';
        $stmt->bindValue(':lastChecked', $lastCheckedTimestamp, SQLITE3_INTEGER);
    }
} else {
    $lastCheckedTimestamp = 0;
}


$allowedComputers = ["PC1", "PC2", "PC3", "PC4", "XBOX1", "XBOX2"];
if (isset($_GET['computer']) && !empty($_GET['computer']) && in_array($_GET['computer'], $allowedComputers)) {
    $query .= ' AND computer = :computer';
    $stmt->bindValue(':computer', $_GET['computer'], SQLITE3_TEXT);
}

if (isset($_GET['id']) && !empty($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $query .= ' AND id = :id';
    $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
}

$query .= ' ORDER BY computer, time_slot';

// Prepare the query
$current_date = date('Y-m-d');
$stmt = $db->prepare($query);
$stmt->bindValue(':currentDate', $current_date, SQLITE3_TEXT);

// Bind values based on the presence of POST parameters
if ($lastCheckedTimestamp != 0) {
    $stmt->bindValue(':lastChecked', $lastCheckedTimestamp, SQLITE3_INTEGER);
}
if (isset($_GET['computer']) && !empty($_GET['computer'])) {
    $stmt->bindValue(':computer', $_GET['computer'], SQLITE3_TEXT);
}
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
}

$results = $stmt->execute();

if (!$results) {
    writeLog("[Error] Database query failed on data fetch for public screen.");
    http_response_code(500);  // Internal server error
    die(json_encode(['status' => 'error', 'message' => 'Query failed']));
}

// Initialize empty array to hold the data
$data = array();
$maxEntries = 48;
$count = 0;

// Loop through each row in the database
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $data[] = array(
        'id' => $row['id'],
        'computer' => $row['computer'],
        'fra' => $row['fra'],
        'til' => $row['til'],
        'navn' => $row['navn'],
        'status' => $row['status'],
        'last_updated' => $row['last_updated']
    );
    $count++;
    if ($count >= $maxEntries) {
        break;
    }
}

// Close the database
$db->close();

// Output the data as JSON
// echo '{"data":'.json_encode($data, JSON_UNESCAPED_UNICODE).',"version":"0.5"}';
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>