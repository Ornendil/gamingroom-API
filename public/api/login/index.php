<?php

// Login

require_once __DIR__ . '/../../../config.php';

//Logging
require_once ROOT . '/logging.php';

// Import necessary headers, likely used for setting up API response headers like Content-Type or CORS.
require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/jwt/generate.php'; // Include the JWT generation script

// Define the path to the users data file.
$userFile = ROOT . '/users.json';

// Read the user data from the file and decode it into an associative array. If decoding fails, use an empty array as a fallback.
$userData = json_decode(file_get_contents($userFile), true) ?? [];

// Check if the request method is POST, indicating that the script is handling a login attempt.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Retrieve the 'username' and 'password' from the POST request. If not provided, set them to empty strings.
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Verify if the provided username exists in the user data and the password matches using password_verify.
    if (isset($userData[$username]) && password_verify($password, $userData[$username]["password"])) {

        // Generate JWTs for the user
        $tokens = generateJWTs($username);
        
        // Set the Refresh Token in an HTTP-only cookie (Server-side)
        if (
            setcookie(
                "refreshToken",            // Cookie name
                $tokens['refreshToken'],   // Cookie value
                [
                    'expires' => time() + 604800,   // Expires in 1 week
                    'path' => '/',                  // Available throughout the domain
                    // 'domain' => 'localhost',        // Explicitly set the domain to 'localhost'
                    'secure' => true,               // Only send over HTTPS
                    'httponly' => true,             // Not accessible via JavaScript
                    'samesite' => 'Strict'          // Prevents sending with cross-site requests
                ]
            )
        ) {
            writeLog("[Success] Refresh token cookie set successfully for user: " . $username);
            // writeLog("HTTP Headers: " . print_r(headers_list(), true));
        } else {
            writeLog("[Error] Failed to set refresh token cookie for user: " . $username);
        }
        

        // Send the CSRF token to the client
        require_once ROOT . '/csrf/generate.php';

        // Send a JSON response indicating success and return the generated access token
        echo json_encode([
            'status' => 'success',
            'accessToken' => $tokens['accessToken'],
            'csrfToken' => $_SESSION['csrf_token']
        ]);



    } else {
        // If the credentials are invalid, respond with an HTTP 401 Unauthorized status code and an error message.
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
} else {
    // If the request method is not POST, respond with an HTTP 405 Method Not Allowed status code and an error message.
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>
