<?php
declare(strict_types=1);

// Tenant info (public)

require_once __DIR__ . '/../../../config.php';
require_once ROOT . '/logging.php';
require_once ROOT . '/apiHeaders.php';
require_once ROOT . '/rateLimit.php';

// Safety: tenant config must exist
if (!isset($TENANT_CONFIG) || !is_array($TENANT_CONFIG)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Tenant misconfiguration']);
    exit;
}

// Timezone (default Europe/Oslo)
$tz = 'Europe/Oslo';
if (isset($TENANT_CONFIG['timezone']) && is_string($TENANT_CONFIG['timezone']) && $TENANT_CONFIG['timezone'] !== '') {
    $tz = $TENANT_CONFIG['timezone'];
}
date_default_timezone_set($tz);

$now = new DateTimeImmutable('now', new DateTimeZone($tz));

// Day key in tenant.json
$dayKeys = [
    1 => 'monday',
    2 => 'tuesday',
    3 => 'wednesday',
    4 => 'thursday',
    5 => 'friday',
    6 => 'saturday',
    7 => 'sunday',
];
$dayKey = $dayKeys[(int)$now->format('N')];

// Opening hours (weekly + today)
$openingHours = $TENANT_CONFIG['openingHours'] ?? [];
$todayHours = $openingHours[$dayKey] ?? null;

// Normalize todayHours output shape
$today = [
    'dayKey' => $dayKey,
    'open' => null,
    'close' => null,
    'isClosedToday' => true,
    'isOpenNow' => false,
];

if (is_array($todayHours) && isset($todayHours['open'], $todayHours['close'])) {
    $open = (string)$todayHours['open'];
    $close = (string)$todayHours['close'];

    // Validate HH:MM
    $hhmm = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
    if (preg_match($hhmm, $open) && preg_match($hhmm, $close)) {
        $today['open'] = $open;
        $today['close'] = $close;
        $today['isClosedToday'] = false;

        // Determine isOpenNow (simple same-day window)
        $todayDate = $now->format('Y-m-d');
        $openDt  = new DateTimeImmutable($todayDate . ' ' . $open,  new DateTimeZone($tz));
        $closeDt = new DateTimeImmutable($todayDate . ' ' . $close, new DateTimeZone($tz));

        // If close is earlier than open, treat as overnight (rare, but safe)
        if ($closeDt <= $openDt) {
            $closeDt = $closeDt->modify('+1 day');
        }

        $today['isOpenNow'] = ($now >= $openDt && $now < $closeDt);
    }
}

// Devices
$devices = $TENANT_CONFIG['devices'] ?? [];
if (!is_array($devices)) $devices = [];

// Normalize device ids to lowercase (canonical)
$devicesOut = [];
foreach ($devices as $d) {
    if (!is_array($d)) continue;
    $id = strtolower(trim((string)($d['id'] ?? '')));
    if ($id === '') continue;

    $devicesOut[] = [
        'id' => $id,
        'label' => (string)($d['label'] ?? $id),
        'type' => (string)($d['type'] ?? 'unknown'),
    ];
}

// Build response
$response = [
    'status' => 'success',
    'tenant' => [
        'slug' => (string)($TENANT_CONFIG['slug'] ?? ''),
        'displayName' => (string)($TENANT_CONFIG['displayName'] ?? ''),
        'authMode' => (string)($TENANT_CONFIG['authMode'] ?? 'name'),
        'retentionDays' => (int)($TENANT_CONFIG['retentionDays'] ?? 1),
        'slotMinutes' => (int)($TENANT_CONFIG['slotMinutes'] ?? 15),
        'defaultSessionSlots' => (int)($TENANT_CONFIG['defaultSessionSlots'] ?? 2),
        'timezone' => $tz,
        'serverTime' => $now->format(DATE_ATOM),
        'openingHours' => $openingHours,
        'today' => $today,
        'devices' => $devicesOut,
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
