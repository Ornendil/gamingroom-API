<?php
$ip = $_SERVER['REMOTE_ADDR'];
$endpoint = $_SERVER['SCRIPT_NAME']; // Current endpoint being accessed
$rateLimitFile = ROOT . '/tmp/rate_limit_' . md5($ip . $endpoint);

// Parameters for rate limiting
$maxRequests = 30; // Maximum number of requests
$timeWindow = 10; // Time window in seconds

// Emergency cap to block excessive requests over a longer period
$emergencyCap = 60; // Maximum requests per minute
$emergencyWindow = 60; // Emergency cap time window in seconds

// Load the request log if it exists
$requestTimes = [];
if (file_exists($rateLimitFile)) {
    $requestTimes = json_decode(file_get_contents($rateLimitFile), true) ?: [];
}

// Current time
$currentTime = time();

// Remove outdated timestamps for the primary window
$requestTimes = array_filter($requestTimes, function ($timestamp) use ($currentTime, $emergencyWindow) {
    return ($currentTime - $timestamp) <= $emergencyWindow;
});

// Check if the current request exceeds the primary limit
$recentRequests = array_filter($requestTimes, function ($timestamp) use ($currentTime, $timeWindow) {
    return ($currentTime - $timestamp) <= $timeWindow;
});

if (count($recentRequests) >= $maxRequests) {
    writeLog("[Error] Rate limit exceeded for $endpoint. Requests in last $timeWindow seconds: " . count($recentRequests));
    http_response_code(429); // Too Many Requests
    die(json_encode(['status' => 'error', 'message' => 'Rate limit exceeded']));
}

// Check if the request exceeds the emergency cap
if (count($requestTimes) >= $emergencyCap) {
    writeLog("[Error] Emergency rate limit exceeded for $endpoint. Total requests in last $emergencyWindow seconds: " . count($requestTimes));
    http_response_code(429); // Too Many Requests
    die(json_encode(['status' => 'error', 'message' => 'Emergency rate limit exceeded']));
}

// Add the current request timestamp
$requestTimes[] = $currentTime;

// Save the updated log back to the file
file_put_contents($rateLimitFile, json_encode($requestTimes));

// Periodic cleanup for old rate limit files
$rateLimitDir = ROOT . '/tmp/';
$expirationTime = 86400; // 24 hours in seconds
foreach (glob($rateLimitDir . "rate_limit_*") as $file) {
    if (filemtime($file) < ($currentTime - $expirationTime)) {
        unlink($file);
    }
}
?>
