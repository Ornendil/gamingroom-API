<?php
declare(strict_types=1);

// Load the Secret Key (tenant-specific)
$secretKey = file_get_contents($JWT_SECRET);

function base64UrlDecode(string $data): string|false {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function refreshAccessToken(string $refreshToken): array {
    global $secretKey;

    try {
        // Split token
        $parts = explode('.', $refreshToken);
        if (count($parts) !== 3) {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // Decode header/payload
        $headerJson = base64UrlDecode($encodedHeader);
        $payloadJson = base64UrlDecode($encodedPayload);

        if ($headerJson === false || $payloadJson === false) {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        // Verify alg (defensive)
        if (($header['alg'] ?? '') !== 'HS256') {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        // Verify signature
        $data = $encodedHeader . '.' . $encodedPayload;
        $expectedSignature = hash_hmac('sha256', $data, $secretKey, true);
        $encodedExpectedSignature = base64UrlEncode($expectedSignature);

        if (!hash_equals($encodedSignature, $encodedExpectedSignature)) {
            writeLog("Refresh token signature invalid", "Error");
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        // Verify exp
        if (!isset($payload['exp']) || !is_int($payload['exp'])) {
            // If json_decode makes it float, accept numeric too:
            if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
                return ['status' => 'error', 'message' => 'Invalid refresh token'];
            }
            $payload['exp'] = (int)$payload['exp'];
        }

        if (time() > $payload['exp']) {
            return ['status' => 'error', 'message' => 'Refresh token has expired'];
        }

        // Verify subject
        $sub = $payload['sub'] ?? null;
        if (!is_string($sub) || $sub === '') {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        // Optional but recommended: enforce that this is a refresh token
        // If you add "token_use":"refresh" when generating refresh tokens, enforce it here:
        if (($payload['token_use'] ?? '') !== 'refresh') {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        // Create new access token (consider setting token_use='access')
        $newHeader = $encodedHeader; // reuse header
        $newPayload = json_encode([
            'sub' => $sub,
            'iat' => time(),
            'exp' => time() + 1800,
            'token_use' => 'access'
        ], JSON_UNESCAPED_UNICODE);

        $encodedNewPayload = base64UrlEncode($newPayload);
        $newSig = hash_hmac('sha256', $newHeader . '.' . $encodedNewPayload, $secretKey, true);
        $encodedNewSig = base64UrlEncode($newSig);

        $newAccessToken = $newHeader . '.' . $encodedNewPayload . '.' . $encodedNewSig;

        return ['status' => 'success', 'accessToken' => $newAccessToken];

    } catch (Throwable $e) {
        writeLog("Refresh access token exception: " . $e->getMessage(), "Error");
        return ['status' => 'error', 'message' => 'Invalid refresh token'];
    }
}
