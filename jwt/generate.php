<?php

// Load the Secret Key
$secretKey = file_get_contents(ROOT . '/jwt/secretkey.txt');

function generateJWTs($username) {

    global $secretKey;
    
    // Step 1: Define the Header
    $header = json_encode([
        "alg" => "HS256",
        "typ" => "JWT"
    ]);

    $accessTokenPayload = json_encode([
        "sub" => $username,
        "iat" => time(),
        "exp" => time() + 1800  // Access Token expires in 30 minutes
    ]);
    $refreshTokenPayload = json_encode([
        "sub" => $username,
        "iat" => time(),
        "exp" => time() + 604800  // Refresh Token expires in 1 week
    ]);

    // Function to Base64URL Encode
    function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    $base64Header = base64UrlEncode($header);
    $base64AccessTokenPayload = base64UrlEncode($accessTokenPayload);
    $base64RefreshTokenPayload = base64UrlEncode($refreshTokenPayload);

    // Create the Signature
    $accessTokenSignature = hash_hmac('sha256', $base64Header . "." . $base64AccessTokenPayload, $secretKey, true);
    $refreshTokenSignature = hash_hmac('sha256', $base64Header . "." . $base64RefreshTokenPayload, $secretKey, true);

    // Base64URL Encode the Signature
    $base64AccessTokenSignature = base64UrlEncode($accessTokenSignature);
    $base64RefreshTokenSignature = base64UrlEncode($refreshTokenSignature);

    // Concatenate Header, Payload, and Signature to Create JWT
    $accessToken = $base64Header . "." . $base64AccessTokenPayload . "." . $base64AccessTokenSignature;
    $refreshToken = $base64Header . "." . $base64RefreshTokenPayload . "." . $base64RefreshTokenSignature;

    // Return the Tokens as an Associative Array
    writeLog("[Success] Generated JWT tokens. Access token: " . $accessToken . " and refresh token: " . $refreshToken);
    return ['accessToken' => $accessToken, 'refreshToken' => $refreshToken];
}
?>