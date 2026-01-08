<?php

// Step 1: Extract the Authorization Header
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

// Step 2: Check if Authorization Header Exists
if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    writeLog("Authorization token not provided or invalid format.", "ERROR");
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Authorization token not provided or invalid format']);
    exit;
}

// Step 3: Extract the JWT from the Authorization Header
$jwt = $matches[1];

// Step 4: Validate the JWT
require_once ROOT . '/jwt/validate.php';
$validationResult = validateToken($jwt);

if ($validationResult['status'] !== 'success') {
    writeLog("Unauthorized JWT validation. " . $validationResult['message'], 'ERROR');
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => $validationResult['message']]);
    exit;
}

// Step 5: Authorization is successful, script can proceed
// The script that includes this will continue if validation is successful
?>