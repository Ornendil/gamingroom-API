<?php

// Update status for session

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
if ( isset($_POST['id']) && isset($_POST['status']) ) {
    $id = $_POST['id'];
    $status = $_POST['status'];

    // Prepare an SQL statement to update the status
    $stmt = $db->prepare('UPDATE gaming_sessions SET status = :status WHERE id = :id');

    // Bind values to placeholders
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);

    // Execute the prepared statement
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'id' => $id, 'newStatus' => $status]);
    } else {
        writeLog("[Error] Failed to update status for session.");
        echo json_encode(['status' => 'error', 'message' => 'Failed to update session.']);
    }
} else {
    writeLog("[Error] Couldn't update status for session. Missing required parameters.");
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
}

// Close the database
$db->close();
?>