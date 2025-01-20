<?php

// Load the Secret Key
$secretKey = file_get_contents(ROOT . '/jwt/secretkey.txt');

function validateToken($jwt) {
    global $secretKey;

    if (!$jwt) {
        writeLog("[Error] JWT token is missing");
        return ['status' => 'error', 'message' => 'JWT token is missing'];
    }

    // Split the JWT into Header, Payload, Signature
    list($encodedHeader, $encodedPayload, $encodedSignature) = explode('.', $jwt);

    // Decode Header and Payload
    $header = json_decode(base64_decode(strtr($encodedHeader, '-_', '+/')), true);
    $payload = json_decode(base64_decode(strtr($encodedPayload, '-_', '+/')), true);

    // Recreate the Signature
    $data = $encodedHeader . '.' . $encodedPayload;
    $expectedSignature = hash_hmac('sha256', $data, $secretKey, true);

    // Base64URL Encode the expected signature
    $encodedExpectedSignature = rtrim(strtr(base64_encode($expectedSignature), '+/', '-_'), '=');

    // Validate the Signature
    if (hash_equals($encodedSignature, $encodedExpectedSignature)) {
        // Check Expiration
        if (isset($payload['exp']) && time() > $payload['exp']) {
            writeLog("[Error] JWT token " . $jwt . " has expired on: " . $payload['exp']);
            return ['status' => 'error', 'message' => 'Token has expired'];
        } else {
            return ['status' => 'success', 'message' => 'JWT is valid', 'payload' => $payload];
        }
    } else {
        writeLog("[Error] Invalid JWT token: " . $jwt);
        return ['status' => 'error', 'message' => 'Invalid token'];
    }
}

?>