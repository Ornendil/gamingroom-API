<?php
declare(strict_types=1);

/**
 * rateLimit.php
 *
 * File-based, tenant-aware, per-endpoint rate limiting.
 * - Skips OPTIONS (CORS preflight)
 * - Uses tenant slug (if available) to avoid cross-tenant interference
 * - Uses endpoint profiles: display / admin / auth
 * - Works behind proxies if you set trusted proxy rules (see getClientIp()).
 */

// ------------------------------------------------------------
// 0) Skip CORS preflight
// ------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    return;
}

// ------------------------------------------------------------
// 1) Helpers
// ------------------------------------------------------------
function rateLimitLog(string $msg): void {
    if (function_exists('writeLog')) {
        writeLog($msg);
    }
}

/**
 * Client IP detection.
 *
 * NOTE: Using X-Forwarded-For is only safe if you control the reverse proxy
 * (nginx) and strip/overwrite these headers from the outside.
 *
 * Minimal approach (works well on a single host nginx setup):
 * - If HTTP_X_FORWARDED_FOR exists, use its first IP
 * - Else use REMOTE_ADDR
 */
function getClientIp(): string {
    global $TENANT_CONFIG;
    // SAFE DEFAULT: do not trust X-Forwarded-For unless you *know* nginx overwrites it.
    $trustXff = false;

    // Optional: allow enabling via tenant.json or bootstrap
    if (isset($TENANT_CONFIG['trustXForwardedFor']) && $TENANT_CONFIG['trustXForwardedFor'] === true) {
        $trustXff = true;
    }
    if (isset($TRUST_X_FORWARDED_FOR) && $TRUST_X_FORWARDED_FOR === true) {
        $trustXff = true;
    }

    if ($trustXff) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            $parts = array_map('trim', explode(',', $xff));
            if (!empty($parts[0])) {
                return $parts[0];
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}


/**
 * Pick rate profile based on endpoint path.
 */
function pickRateProfile(string $endpoint): string {
    // Public display polling endpoints
    if (str_contains($endpoint, '/api/sessions')) {
        return 'display';
    }

    // Authentication-related endpoints
    if (
        str_contains($endpoint, '/api/login') ||
        str_contains($endpoint, '/api/refresh') ||
        str_contains($endpoint, '/api/logout')
    ) {
        return 'auth';
    }

    // Default for admin / write endpoints
    return 'admin';
}

// ------------------------------------------------------------
// 2) Profiles (tune as needed)
// ------------------------------------------------------------
$RATE_PROFILES = [
  'display' => [
    'maxRequests'      => 60,   // 60/min allows 5s polling + bursts
    'timeWindow'       => 60,
    'emergencyCap'     => 600,  // 600/5min = 120/min sustained (still far below nginx 1000/min)
    'emergencyWindow'  => 300,
  ],
  'admin' => [
    'maxRequests'      => 40,   // per 10s = 4 req/s bursts
    'timeWindow'       => 10,
    'emergencyCap'     => 300,  // per minute; generous for NAT + multiple admins
    'emergencyWindow'  => 60,
  ],
  'auth' => [
    'maxRequests'      => 8,
    'timeWindow'       => 60,
    'emergencyCap'     => 25,
    'emergencyWindow'  => 300,
  ],
];


// ------------------------------------------------------------
// 3) Compute key (tenant-aware)
// ------------------------------------------------------------
$ip = getClientIp();
$endpoint = $_SERVER['SCRIPT_NAME'] ?? 'unknown';

// Tenant slug should be set by bootstrap/config (recommended).
// Fallbacks: TENANT_CONFIG['slug'], or 'global'.
$tenantSlug = 'global';
if (isset($TENANT_SLUG) && is_string($TENANT_SLUG) && $TENANT_SLUG !== '') {
    $tenantSlug = $TENANT_SLUG;
} elseif (isset($TENANT_CONFIG['slug']) && is_string($TENANT_CONFIG['slug']) && $TENANT_CONFIG['slug'] !== '') {
    $tenantSlug = $TENANT_CONFIG['slug'];
}

$profileName = pickRateProfile($endpoint);
$profile = $RATE_PROFILES[$profileName] ?? $RATE_PROFILES['admin'];

$maxRequests     = (int)$profile['maxRequests'];
$timeWindow      = (int)$profile['timeWindow'];
$emergencyCap    = (int)$profile['emergencyCap'];
$emergencyWindow = (int)$profile['emergencyWindow'];

// Ensure tmp exists
$rateLimitDir = ROOT . '/tmp/';
if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0770, true);
}

// Use tenant|ip|endpoint as the bucket key
$rateLimitFile = $rateLimitDir . 'rate_limit_' . md5($tenantSlug . '|' . $ip . '|' . $endpoint) . '.json';

// ------------------------------------------------------------
// 4) Load timestamps
// ------------------------------------------------------------
$requestTimes = [];
if (file_exists($rateLimitFile)) {
    $decoded = json_decode((string)file_get_contents($rateLimitFile), true);
    if (is_array($decoded)) {
        $requestTimes = $decoded;
    }
}

$currentTime = time();

// Keep only entries within the emergency window (outer window)
$requestTimes = array_values(array_filter(
    $requestTimes,
    fn($ts) => is_int($ts) && ($currentTime - $ts) <= $emergencyWindow
));

// Primary window count
$recentRequests = array_filter(
    $requestTimes,
    fn($ts) => ($currentTime - $ts) <= $timeWindow
);

// ------------------------------------------------------------
// 5) Enforce limits
// ------------------------------------------------------------
if (count($recentRequests) >= $maxRequests) {
    rateLimitLog("[Error] Rate limit exceeded ($profileName) for $tenantSlug $endpoint from $ip. " .
                 "Requests in last {$timeWindow}s: " . count($recentRequests));
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded']);
    exit;
}

if (count($requestTimes) >= $emergencyCap) {
    rateLimitLog("[Error] Emergency rate limit exceeded ($profileName) for $tenantSlug $endpoint from $ip. " .
                 "Requests in last {$emergencyWindow}s: " . count($requestTimes));
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Emergency rate limit exceeded']);
    exit;
}

// ------------------------------------------------------------
// 6) Record current request
// ------------------------------------------------------------
$requestTimes[] = $currentTime;
$ok = @file_put_contents($rateLimitFile, json_encode($requestTimes), LOCK_EX);
if ($ok === false) {
    rateLimitLog("[Warn] Failed writing rate limit file: $rateLimitFile");
}

// ------------------------------------------------------------
// 7) Periodic cleanup (once per request, cheap enough at your scale)
// ------------------------------------------------------------
$expirationTime = 86400; // 24h
foreach (glob($rateLimitDir . "rate_limit_*.json") as $file) {
    if (is_file($file) && filemtime($file) < ($currentTime - $expirationTime)) {
        @unlink($file);
    }
}
