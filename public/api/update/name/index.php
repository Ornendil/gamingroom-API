<?php

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

// Connect to SQLite database
$db = new SQLite3(ROOT . '/gamingrom.db');

// Check if the necessary POST data is present
if ( isset($_POST['id']) && isset($_POST['navn']) ) {
    $id = $_POST['id'];
    $navn = $_POST['navn'];

    // Prepare an SQL statement to update the navn
    $stmt = $db->prepare('UPDATE gaming_sessions SET navn = :navn WHERE id = :id');

    // Bind values to placeholders
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':navn', $navn, SQLITE3_TEXT);

    // Execute the prepared statement
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'id' => $id, 'navn' => $navn]);
    } else {
        writeLog("[Error] Failed to update name for session.");
        echo json_encode(['status' => 'error', 'message' => 'Failed to update session.', 'id' => $id, 'navn' => $navn]);
    }
} else {
    writeLog("[Error] Couldn't update name for session. Missing required parameters.");
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
}

// Close the database
$db->close();
?>