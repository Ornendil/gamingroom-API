<?php
declare(strict_types=1);

/**
 * Tenant bootstrap
 * - Resolves tenant from subdomain
 * - Loads tenant.json
 * - Exposes paths + config
 */

// ---- Paths -------------------------------------------------

$ROOT_DIR    = realpath(__DIR__);
$TENANTS_DIR = $ROOT_DIR . '/tenants';

// ---- Resolve tenant slug ----------------------------------

$host = $_SERVER['HTTP_HOST'] ?? '';
$host = strtolower($host);
$host = explode(':', $host)[0]; // strip port

// Expected: <tenant>.ourdomain.no
$parts = explode('.', $host);
$tenantSlug = $parts[0] ?? '';

// Fallbacks (dev / safety)
if ($tenantSlug === '' || $tenantSlug === 'localhost') {
    $tenantSlug = 'biblioteket';
}

// Whitelist slug characters
if (!preg_match('/^[a-z0-9-]+$/', $tenantSlug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tenant']);
    exit;
}

// ---- Validate tenant exists --------------------------------

$TENANT_DIR = $TENANTS_DIR . '/' . $tenantSlug;

if (!is_dir($TENANT_DIR)) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown tenant']);
    exit;
}

// ---- Load tenant config ------------------------------------

$TENANT_CONFIG_PATH = $TENANT_DIR . '/tenant.json';

if (!file_exists($TENANT_CONFIG_PATH)) {
    http_response_code(500);
    echo json_encode(['error' => 'Tenant misconfigured']);
    exit;
}

$TENANT_CONFIG = json_decode(
    file_get_contents($TENANT_CONFIG_PATH),
    true,
    flags: JSON_THROW_ON_ERROR
);

// ---- Devices ------------------------------------------------

$ALLOWED_DEVICE_IDS = [];
$DEVICES_BY_ID = [];

if (isset($TENANT_CONFIG['devices']) && is_array($TENANT_CONFIG['devices'])) {
    foreach ($TENANT_CONFIG['devices'] as $device) {
        if (!is_array($device)) continue;

        $id = $device['id'] ?? null;
        if (!is_string($id)) continue;

        // normalize + validate
        $id = strtolower($id);
        if (!preg_match('/^[a-z0-9-]+$/', $id)) continue;

        $ALLOWED_DEVICE_IDS[] = $id;
        $DEVICES_BY_ID[$id] = $device;
    }
}

// Ensure uniqueness
$ALLOWED_DEVICE_IDS = array_values(array_unique($ALLOWED_DEVICE_IDS));


// ---- Expose canonical paths --------------------------------

$TENANT_SLUG = $tenantSlug;
$DB_PATH     = $TENANT_DIR . '/gamingrom.db';
$JWT_SECRET  = $TENANT_DIR . '/jwt_secret.txt';
$USERS_PATH  = $TENANT_DIR . '/users.json';

// (Do not open DB here — let endpoints decide when)