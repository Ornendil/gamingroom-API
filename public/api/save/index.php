<?php

// Save session

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

// require_once ROOT . '/passwordFunction.php';

// Connect to SQLite database
$db = new SQLite3(ROOT . '/gamingrom.db');



// Check if the necessary POST data is present
if ( isset($_POST['lnr'])
    && isset($_POST['navn'])
    && isset($_POST['computer'])
    && isset($_POST['time_slot'])
    && isset($_POST['fra'])
    && isset($_POST['til'])
) {
    $lnr = $_POST['lnr'];
    $navn = $_POST['navn'];
    $computer = $_POST['computer'];
    $time_slot = $_POST['time_slot'];
    $fra = $_POST['fra'];
    $til = $_POST['til'];

    // Create table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS gaming_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, date TEXT, last_updated INTEGER, lnr TEXT, navn TEXT, computer TEXT, time_slot INTEGER, fra TEXT, til TEXT)");
    $db->exec('PRAGMA journal_mode = WAL;');

    // Get JSON data from POST request
    $json_data = file_get_contents('php://input');
    $decoded_data = json_decode($json_data, true);

    // Extract 'data' from the decoded JSON
    $data = $decoded_data['data'] ?? [];

    // Current date
    $current_date = date('Y-m-d');

    // Prepare an SQL statement with placeholders for inserting new sessions
    $stmt = $db->prepare('INSERT INTO gaming_sessions (date, last_updated, computer, time_slot, fra, til, navn, lnr) VALUES (:date, :last_updated, :computer, :time_slot, :fra, :til, :navn, :lnr)');

    // Bind values to placeholders
    $stmt->bindValue(':date', $current_date, SQLITE3_TEXT);
    $stmt->bindValue(':last_updated', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':computer', $computer, SQLITE3_TEXT);
    $stmt->bindValue(':time_slot', $time_slot, SQLITE3_INTEGER);
    $stmt->bindValue(':fra', $fra, SQLITE3_TEXT);
    $stmt->bindValue(':til', $til, SQLITE3_TEXT);
    $stmt->bindValue(':navn', $navn, SQLITE3_TEXT);
    $stmt->bindValue(':lnr', isset($lnr) && $lnr ? $lnr : $navn, SQLITE3_TEXT);


    // Execute the prepared statement
    if ($stmt->execute()) {

        // Fetch the sessions for the current date after inserting new ones
        $result = $db->query("SELECT * FROM gaming_sessions WHERE date = :current_date");
        $current_sessions_stmt = $db->prepare("SELECT * FROM gaming_sessions WHERE date = :current_date");
        $current_sessions_stmt->bindValue(':current_date', $current_date, SQLITE3_TEXT);
        $result = $current_sessions_stmt->execute();

        $sessions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $sessions[] = $row;
        }

        echo json_encode(['status' => 'success', 'sessions' => $sessions]);
    } else {
        writeLog("[Error] Failed to save new session.");
        echo json_encode(['status' => 'error', 'message' => 'Failed to save new session.', 'id' => $id]);
    }


} else {
    writeLog("[Error] Couldn't save new session. Missing required parameters.");
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
}


// Delete old records (older than 7 days)
$delete_query = "DELETE FROM gaming_sessions WHERE date < strftime('%Y-%m-%d', 'now', '-7 day')";
$db->exec($delete_query);

// Close the database
$db->close();

?>