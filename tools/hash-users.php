<?php
declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php hash-users.php <tenant-slug>\n");
    exit(1);
}

$tenant = $argv[1];
$baseDir = realpath(__DIR__ . '/../tenants');

if ($baseDir === false) {
    fwrite(STDERR, "Cannot locate tenants directory\n");
    exit(1);
}

$tenantDir = $baseDir . DIRECTORY_SEPARATOR . $tenant;
$usersFile = $tenantDir . DIRECTORY_SEPARATOR . 'users.json';

if (!is_dir($tenantDir)) {
    fwrite(STDERR, "Tenant not found: $tenant\n");
    exit(1);
}

if (!file_exists($usersFile)) {
    fwrite(STDERR, "users.json not found for tenant: $tenant\n");
    exit(1);
}

$data = json_decode(file_get_contents($usersFile), true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON in $usersFile\n");
    exit(1);
}

$changed = false;

foreach ($data as $username => &$user) {
    if (!isset($user['password']) || !is_string($user['password'])) {
        continue;
    }

    // Skip if it already looks like a password_hash()
    if (str_starts_with($user['password'], '$2y$') || str_starts_with($user['password'], '$argon')) {
        continue;
    }

    echo "Hashing password for user: $username\n";
    $user['password'] = password_hash($user['password'], PASSWORD_DEFAULT);
    $changed = true;
}

unset($user);

if (!$changed) {
    echo "No plaintext passwords found. Nothing to do.\n";
    exit(0);
}

file_put_contents(
    $usersFile,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

echo "Updated passwords written to $usersFile\n";
