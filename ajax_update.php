<?php
set_time_limit(0);
ignore_user_abort(true);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$today = date('Y-m-d');
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

// Jika data hari ini sudah cukup lengkap (dan bukan paksa update), langsung return already_updated
if (!$forceUpdate && $todayCountBefore >= $minCompleteRows) {
    if (file_exists($log_file)) {
        $last = trim(file_get_contents($log_file));
        if ($last !== $today) {
            file_put_contents($log_file, $today);
        }
    } else {
        file_put_contents($log_file, $today);
    }
    echo json_encode([
        'status' => 'already_updated',
        'today' => $today,
        'latest_date' => $latestBefore,
        'today_count' => $todayCountBefore,
        'is_fresh_today' => true
    ]);
    exit;
}

if (!$forceUpdate && file_exists($log_file)) {
    $last = trim(file_get_contents($log_file));
    if ($last === $today) {
        // Marker ada, tapi verifikasi tetap ke DB. Jika belum fresh, jangan dianggap sukses.
        echo json_encode([
            'status' => 'pending_data',
            'today' => $today,
            'latest_date' => $latestBefore,
            'today_count' => $todayCountBefore,
            'is_fresh_today' => false,
            'message' => 'Marker harian sudah ada, tetapi data hari ini belum lengkap.'
        ]);
        exit;
    }
}

ob_start();
require __DIR__ . '/update_daily_prices.php';
$output = ob_get_clean();

// Hapus kode warna terminal ANSI
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

$todayCountAfter = get_day_count($mysqli, $today);
$latestAfter = get_latest_date($mysqli);
$isFreshToday = ($todayCountAfter >= $minCompleteRows && $latestAfter === $today);

    if ($isFreshToday) {
        file_put_contents($log_file, $today);
        touch($log_file); // Memaksa update mtime agar jam di UI berubah
        echo json_encode([
        'status' => 'success',
        'today' => $today,
        'latest_date' => $latestAfter,
        'today_count' => $todayCountAfter,
        'is_fresh_today' => true,
        'forced' => $forceUpdate,
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
        'forced' => $forceUpdate,
        'message' => 'Update dijalankan, tetapi data EOD hari ini belum lengkap.',
        'log' => $output
    ]);
}
?>
