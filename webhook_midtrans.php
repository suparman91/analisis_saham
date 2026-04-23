<?php
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$logFile = $logDir . '/payment_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Midtrans webhook ignored because automatic payments are temporarily disabled\n", FILE_APPEND | LOCK_EX);

http_response_code(503);
header('Retry-After: 3600');
echo 'Automatic payments are temporarily disabled.';
?>