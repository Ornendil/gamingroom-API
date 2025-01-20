<?php

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

// Connect to SQLite database
$db = new SQLite3(ROOT . '/gamingrom.db');

if (!$db) {
    die("Connection failed: " . $db->lastErrorMsg());
}

// Check if the necessary DELETE data is present
$id = $_POST['id'] ?? null;

if ($id !== null) {
    // Check if the session ID exists
    $checkStmt = $db->prepare('SELECT COUNT(*) FROM gaming_sessions WHERE id = :id');
    $checkStmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $countResult = $checkStmt->execute()->fetchArray();
    
    if ($countResult[0] == 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Session ID does not exist.', 
            'id' => $id
        ]);
        exit;
    }

    // Prepare an SQL statement to delete the session
    $stmt = $db->prepare('DELETE FROM gaming_sessions WHERE id = :id');

    // Bind the ID value to the placeholder
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

    // Execute the prepared statement
    $result = $stmt->execute();

    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Session deleted successfully.', 
            'id' => $id]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to delete session.', 
            'id' => $id,
            'errorInfo' => $db->lastErrorMsg()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required parameter: id.'
    ]);
}

// Close the database
$db->close();

?>
