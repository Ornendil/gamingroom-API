<?php

// File path to your JSON file
$jsonFilePath = 'users.json';

// Read JSON file
$jsonData = file_get_contents($jsonFilePath);
$userData = json_decode($jsonData, true);

// Check if the decoding was successful
if ($userData === null) {
    die("Error decoding JSON");
}

// Iterate over each user and hash their password
foreach ($userData as $username => $details) {
    if (isset($details['password'])) {
        // Hash the password
        $hashedPassword = password_hash($details['password'], PASSWORD_DEFAULT);
        // Update the password in the array
        echo $hashedPassword . "\n";
        $userData[$username]['password'] = $hashedPassword;
    }
}

// Encode the array back into JSON
$newJsonData = json_encode($userData, JSON_PRETTY_PRINT);

// Save the new JSON data back to the file
file_put_contents($jsonFilePath, $newJsonData);

echo "Passwords have been hashed and updated in the JSON file.";

?>
