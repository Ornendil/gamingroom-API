<?php
declare(strict_types=1);

// Load the Secret Key
$secretKey = file_get_contents($JWT_SECRET);

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateJWTs(string $username): array {
    global $secretKey;

    $header = json_encode([
        "alg" => "HS256",
        "typ" => "JWT"
    ], JSON_UNESCAPED_UNICODE);

    $now = time();

    $accessTokenPayload = json_encode([
        "sub" => $username,
        "iat" => $now,
        "exp" => $now + 1800,
        "token_use" => "access"
    ], JSON_UNESCAPED_UNICODE);

    $refreshTokenPayload = json_encode([
        "sub" => $username,
        "iat" => $now,
        "exp" => $now + 604800,
        "token_use" => "refresh"
    ], JSON_UNESCAPED_UNICODE);

    $base64Header = base64UrlEncode($header);
    $base64AccessTokenPayload = base64UrlEncode($accessTokenPayload);
    $base64RefreshTokenPayload = base64UrlEncode($refreshTokenPayload);

    $accessTokenSignature = hash_hmac('sha256', $base64Header . "." . $base64AccessTokenPayload, $secretKey, true);
    $refreshTokenSignature = hash_hmac('sha256', $base64Header . "." . $base64RefreshTokenPayload, $secretKey, true);

    $base64AccessTokenSignature = base64UrlEncode($accessTokenSignature);
    $base64RefreshTokenSignature = base64UrlEncode($refreshTokenSignature);

    $accessToken = $base64Header . "." . $base64AccessTokenPayload . "." . $base64AccessTokenSignature;
    $refreshToken = $base64Header . "." . $base64RefreshTokenPayload . "." . $base64RefreshTokenSignature;

    writeLog("Generated JWT tokens for user: " . $username, "Success");

    return ['accessToken' => $accessToken, 'refreshToken' => $refreshToken];
}
