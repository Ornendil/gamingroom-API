<?php
declare(strict_types=1);

// Load the Secret Key
$secretKey = file_get_contents($JWT_SECRET);

function base64UrlDecode(string $data): string|false {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function validateToken(string $jwt): array {
    global $secretKey;

    if ($jwt === '') {
        writeLog("JWT token is missing", "Error");
        return ['status' => 'error', 'message' => 'JWT token is missing'];
    }

    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        writeLog("Invalid JWT format", "Error");
        return ['status' => 'error', 'message' => 'Invalid token'];
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

    $headerJson = base64UrlDecode($encodedHeader);
    $payloadJson = base64UrlDecode($encodedPayload);

    if ($headerJson === false || $payloadJson === false) {
        writeLog("Invalid JWT encoding", "Error");
        return ['status' => 'error', 'message' => 'Invalid token'];
    }

    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);

    if (!is_array($header) || !is_array($payload)) {
        writeLog("Invalid JWT JSON", "Error");
        return ['status' => 'error', 'message' => 'Invalid token'];
    }

    // Defensive: only accept HS256
    if (($header['alg'] ?? '') !== 'HS256') {
        writeLog("Invalid JWT alg", "Error");
        return ['status' => 'error', 'message' => 'Invalid token'];
    }

    // Enforce token type
    if (($payload['token_use'] ?? '') !== 'access') {
        writeLog("JWT token_use invalid", "Error");
        return ['status' => 'error', 'message' => 'Invalid token'];
    }

    // Recreate signature
    $data = $encodedHeader . '.' . $encodedPayload;
    $expectedSignature = hash_hmac('sha256', $data, $secretKey, true);
    $encodedExpectedSignature = rtrim(strtr(base64_encode($expectedSignature), '+/', '-_'), '=');

    if (!hash_equals($encodedSignature, $encodedExpectedSignature)) {
        writeLog("Invalid JWT signature", "Error");
        return ['status' => 'error', 'message' => 'Invalid token'];
    }

    // Check expiration
    if (isset($payload['exp']) && is_numeric($payload['exp']) && time() > (int)$payload['exp']) {
        writeLog("JWT token expired at: " . $payload['exp'], "Error");
        return ['status' => 'error', 'message' => 'Token has expired'];
    }

    return ['status' => 'success', 'message' => 'JWT is valid', 'payload' => $payload];
}
?>
