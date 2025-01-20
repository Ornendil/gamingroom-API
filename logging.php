<?php
function writeLog($message) {
    $logFile = __DIR__ . '/../logs/gamingroom.log'; // Adjust path as needed to point to your logs directory
    
    // Create the log message with a timestamp
    $timestamp = date("Y-m-d H:i:s");
    $formattedMessage = "[{$timestamp}] {$message}" . PHP_EOL;

    // Append the message to the log file
    file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
}
?>