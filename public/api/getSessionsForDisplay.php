<?php

require_once '../../apiHeaders.php';

// Connect to SQLite database
$db = new SQLite3('../../gamingrom.db');

if (!$db) {
    die("Connection failed: " . $db->lastErrorMsg());
}

$query = 'SELECT * FROM gaming_sessions WHERE date = :currentDate';

if (isset($_POST['last_checked']) && !empty($_POST['last_checked'])) {
    $lastCheckedTimestamp = $_POST['last_checked'];
    $query .= ' AND last_updated > :lastChecked';
} else {
    $lastCheckedTimestamp = 0;
}

// Filter by computer if it's set
if (isset($_POST['computer']) && !empty($_POST['computer'])) {
    $query .= ' AND computer = :computer';
}

// Filter by id if it's set
if (isset($_POST['id']) && !empty($_POST['id'])) {
    $query .= ' AND id = :id';
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
if (isset($_POST['computer']) && !empty($_POST['computer'])) {
    $stmt->bindValue(':computer', $_POST['computer'], SQLITE3_TEXT);
}
if (isset($_POST['id']) && !empty($_POST['id'])) {
    $stmt->bindValue(':id', $_POST['id'], SQLITE3_INTEGER);
}

$results = $stmt->execute();

if (!$results) {
    die("Query failed: " . $db->lastErrorMsg());
}

// Initialize empty array to hold the data
$data = array();

// Loop through each row in the database
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $data[] = array(
        'id' => $row['id'],
        'computer' => $row['computer'],
        // 'time_slot' => $row['time_slot'],
        'fra' => $row['fra'],
        'til' => $row['til'],
        // 'lnr' => $row['lnr'],
        'navn' => $row['navn'],
        // 'passord' => $row['passord'],
        'status' => $row['status'],
        'last_updated' => $row['last_updated']
    );
}

// Close the database
$db->close();

// Output the data as JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>