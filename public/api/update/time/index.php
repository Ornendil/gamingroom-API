<?php

// Update time slot and station for session

require_once __DIR__ . '/../../../../config.php';

//Logging
require_once ROOT . '/logging.php';
// writeLog("[Data fetch] Config loaded.");

// Import necessary headers, likely used for setting up API response headers like Content-Type or CORS.
require_once ROOT . '/apiHeaders.php';
// writeLog("[Data fetch] API headers loaded.");

// Include rate limit
require_once ROOT . '/rateLimit.php';
// writeLog("[Data fetch] Rate limit set.");

// Include CSRF validation
require_once ROOT . '/csrf/validate.php';
// writeLog("[Data fetch] CSRF validated.");

// Include JWT Authorization
require_once ROOT . '/auth.php';
// writeLog("[Data fetch] Authenticated.");

// Connect to SQLite database
$db = new SQLite3(ROOT . '/gamingrom.db');

if (!$db) {
    writeLog("[Error] Database connection failed on trying to update time and/or station for session.");
    http_response_code(500);  // Set a proper HTTP response code
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Check if the necessary POST data is present
if ( isset($_POST['id']) && isset($_POST['slot']) && isset($_POST['computer']) ) {
    $id = $_POST['id'];
    $slot = $_POST['slot'];
    $fra = $_POST['fra'];
    $til = $_POST['til'];
    $computer = $_POST['computer'];

    // writeLog("[Debug] Received POST data - ID: $id, Slot: $slot, Fra: $fra, Til: $til, Computer: $computer");

    // Prepare an SQL statement to update the status
    $stmt = $db->prepare('UPDATE gaming_sessions SET time_slot = :time_slot, fra = :fra, til = :til, computer = :computer WHERE id = :id');

    if (!$stmt) {
        writeLog("[Error] Failed to prepare SQL statement: " . $db->lastErrorMsg());
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL statement']));
    }

    // Bind values to placeholders
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':time_slot', $slot, SQLITE3_INTEGER);
    $stmt->bindValue(':fra', $fra, SQLITE3_TEXT);
    $stmt->bindValue(':til', $til, SQLITE3_TEXT);
    $stmt->bindValue(':computer', $computer, SQLITE3_TEXT);


    // Execute the prepared statement
    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['status' => 'success', 'id' => $id, 'slot' => $slot, 'computer' => $computer]);
    } else {
        writeLog("[Error] Failed to execute SQL statement: " . $db->lastErrorMsg());
        writeLog("[Error] Failed to update time and/or station for session.");
        echo json_encode(['status' => 'error', 'message' => 'Failed to update session.']);
    }
} else {
    writeLog("[Error] Couldn't update time and/or station for session. Missing required parameters.");
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
}

// Close the database
$db->close();
?>