<?php
set_time_limit(0);
ignore_user_abort(true);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$today = date('Y-m-d');
$nowTime = date('H:i:s');
$dayOfWeek = (int)date('N');
$isTradingDay = ($dayOfWeek >= 1 && $dayOfWeek <= 5);
$marketOpenTime = '09:00:00';
$marketCloseTime = '16:15:00';
$isPreOpen = $isTradingDay && ($nowTime < $marketOpenTime);
$isAfterClose = $isTradingDay && ($nowTime >= $marketCloseTime);
$log_file = __DIR__ . '/last_update_daily.txt';
$forceUpdate = isset($_GET['force']) && $_GET['force'] === '1';

function get_day_count($mysqli, $date) {
    $stmt = $mysqli->prepare('SELECT COUNT(*) as cnt FROM prices WHERE date = ?');
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

function get_latest_date($mysqli) {
    $res = $mysqli->query('SELECT MAX(date) as latest_date FROM prices');
    $row = $res ? $res->fetch_assoc() : null;
    return $row['latest_date'] ?? null;
}

$todayCountBefore = get_day_count($mysqli, $today);
$latestBefore = get_latest_date($mysqli);
$minCompleteRows = 800;

function get_placeholder_count($mysqli, $date) {
    $stmt = $mysqli->prepare('SELECT COUNT(*) as cnt FROM prices WHERE date = ? AND volume = 0 AND open = high AND high = low AND low = close');
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

// Sebelum market close, jangan lock "already_updated" agar data real tetap bisa mengganti placeholder pre-open.
if (!$forceUpdate && $isAfterClose && $todayCountBefore >= $minCompleteRows) {
    if (!file_exists($log_file)) {
        file_put_contents($log_file, $today);
    }
    touch($log_file);
    echo json_encode([
        'status' => 'already_updated',
        'today' => $today,
        'latest_date' => $latestBefore,
        'today_count' => $todayCountBefore,
        'is_fresh_today' => true,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_at_display' => date('d M Y H:i')
    ]);
    exit;
}

// Jika marker harian sudah ada tetapi data belum lengkap, tetap lanjutkan proses update.
// Ini penting agar update bisa mengejar target baris harian (tidak macet di status pending).

ob_start();
$GLOBALS['_ajax_update_caller'] = true;
require __DIR__ . '/update_daily_prices.php';
$output = ob_get_clean();

// Hapus kode warna terminal ANSI
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

$todayCountAfter = get_day_count($mysqli, $today);
$latestAfter = get_latest_date($mysqli);
$placeholderCountAfter = get_placeholder_count($mysqli, $today);
$isFreshToday = ($todayCountAfter >= $minCompleteRows && $latestAfter === $today && ($isPreOpen || $placeholderCountAfter === 0));

if ($todayCountAfter > 0 && $latestAfter === $today) {
    // Sinkronkan jam "Update Terakhir" di dashboard dengan eksekusi updater terbaru.
    touch($log_file);
}

if ($isPreOpen && $todayCountAfter >= $minCompleteRows && $latestAfter === $today) {
    // Pre-open dianggap siap baseline meskipun masih placeholder karena market belum buka.
    $isFreshToday = true;
}

    if ($isFreshToday) {
        file_put_contents($log_file, $today);
        touch($log_file); // Memaksa update mtime agar jam di UI berubah
        echo json_encode([
        'status' => 'success',
        'today' => $today,
        'latest_date' => $latestAfter,
        'today_count' => $todayCountAfter,
        'is_fresh_today' => true,
        'placeholder_count' => $placeholderCountAfter,
        'session' => $isPreOpen ? 'pre_open' : ($isAfterClose ? 'after_close' : 'intraday'),
        'forced' => $forceUpdate,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_at_display' => date('d M Y H:i'),
        'log' => $output
    ]);
} else {
    // Jangan tulis marker sukses jika data belum masuk/masih parsial
    echo json_encode([
        'status' => 'incomplete',
        'today' => $today,
        'latest_date' => $latestAfter,
        'today_count' => $todayCountAfter,
        'is_fresh_today' => false,
        'placeholder_count' => $placeholderCountAfter,
        'session' => $isPreOpen ? 'pre_open' : ($isAfterClose ? 'after_close' : 'intraday'),
        'forced' => $forceUpdate,
        'updated_at' => file_exists($log_file) ? date('Y-m-d H:i:s', filemtime($log_file)) : null,
        'updated_at_display' => file_exists($log_file) ? date('d M Y H:i', filemtime($log_file)) : 'Belum Pernah',
        'message' => 'Update dijalankan, tetapi data EOD hari ini belum lengkap.',
        'log' => $output
    ]);
}
?>
