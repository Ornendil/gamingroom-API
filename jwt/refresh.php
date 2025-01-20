<?php

// Step 1: Load the Secret Key
$secretKey = file_get_contents(ROOT . '/jwt/secretkey.txt');

// Step 2: Function to Validate and Refresh Tokens
function refreshAccessToken($refreshToken) {
    global $secretKey;

    try {
        // Step 3: Split the JWT into Header, Payload, Signature
        list($encodedHeader, $encodedPayload, $encodedSignature) = explode('.', $refreshToken);
        
        // Step 4: Decode the Payload
        $decodedPayload = json_decode(base64_decode(strtr($encodedPayload, '-_', '+/')), true);

        // Step 5: Check if the Refresh Token has expired
        if (isset($decodedPayload['exp']) && time() > $decodedPayload['exp']) {
            writeLog("[Error] Couldn't refresh access token.");
            writeLog("[Error] JWT Refresh token " . $refreshToken . " has expired on " . $decodedPayload['exp']);
            return ['status' => 'error', 'message' => 'Refresh token has expired'];
        }

        // Step 6: Create a New Access Token Payload
        $accessTokenPayload = json_encode([
            "sub" => $decodedPayload['sub'],  // Carry over the subject from the refresh token
            "iat" => time(),
            "exp" => time() + 1800  // Access Token expires in 30 minutes
        ]);
        
        // Step 7: Function to Base64URL Encode Data
        function base64UrlEncode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        // Step 8: Base64URL Encode Header and Payload for New Access Token
        $base64AccessTokenPayload = base64UrlEncode($accessTokenPayload);

        // Step 9: Reuse the Header from the Refresh Token
        $base64Header = $encodedHeader;
        
        // Step 10: Create the New Access Token Signature
        $accessTokenSignature = hash_hmac('sha256', $base64Header . "." . $base64AccessTokenPayload, $secretKey, true);
        $base64AccessTokenSignature = base64UrlEncode($accessTokenSignature);

        // Step 11: Concatenate Header, Payload, and Signature to Create the New Access Token
        $newAccessToken = $base64Header . "." . $base64AccessTokenPayload . "." . $base64AccessTokenSignature;

        return ['status' => 'success', 'accessToken' => $newAccessToken];
    } catch (Exception $e) {
        writeLog("[Error] Couldn't refresh access token.");
        writeLog("[Error] Invalid refresh token: " . $refreshToken);
        return ['status' => 'error', 'message' => 'Invalid refresh token'];
    }
}

?>