<?php
set_time_limit(0);
ignore_user_abort(true);

$today = date('Y-m-d');
$log_file = __DIR__ . '/last_update_daily.txt';

if (file_exists($log_file)) {
    $last = trim(file_get_contents($log_file));
    if ($last === $today) {
        echo json_encode(['status' => 'already_updated']);
        exit;
    }
}

ob_start();
require __DIR__ . '/update_daily_prices.php';
$output = ob_get_clean();

// Hapus kode warna terminal ANSI
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

file_put_contents($log_file, $today);

echo json_encode(['status' => 'success', 'log' => $output]);
?>
