<?php
function writeLog(
    string $message,
    string $level = 'INFO',
    array $context = []
): void {
    $logFile = __DIR__ . '/../logs/gamingroom.log';

    $timestamp = date("Y-m-d H:i:s");

    // Basic context enrichment
    $tenant = $context['tenant'] ?? ($GLOBALS['TENANT_SLUG'] ?? 'global');
    $endpoint = $_SERVER['SCRIPT_NAME'] ?? '-';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';

    $contextStr = '';
    if (!empty($context)) {
        $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    $line = sprintf(
        "[%s] [%s] tenant=%s ip=%s endpoint=%s %s%s\n",
        $timestamp,
        strtoupper($level),
        $tenant,
        $ip,
        $endpoint,
        $message,
        $contextStr
    );

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
