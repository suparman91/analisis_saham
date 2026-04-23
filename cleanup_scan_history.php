<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$mysqli = db_connect();

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Akses ditolak. Script ini khusus admin.');
    }
}

$mysqli->query("CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function cleanup_get_setting($db, $key, $default) {
    $keyEsc = $db->real_escape_string($key);
    $res = $db->query("SELECT setting_value FROM app_settings WHERE setting_key = '{$keyEsc}' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $v = (int)$row['setting_value'];
        if ($v > 0) return $v;
    }
    return $default;
}

$detailDays = cleanup_get_setting($mysqli, 'scan_history_detail_days', 90);
$summaryDays = cleanup_get_setting($mysqli, 'scan_history_summary_days', 365);
if ($summaryDays < $detailDays) {
    $summaryDays = $detailDays;
}

$deletedDetail = 0;
$deletedSummary = 0;

$detailSql = "DELETE FROM scan_history WHERE created_at < (NOW() - INTERVAL {$detailDays} DAY)";
if ($mysqli->query($detailSql)) {
    $deletedDetail = (int)$mysqli->affected_rows;
}

$hasSummaryTable = false;
$chkSummary = $mysqli->query("SHOW TABLES LIKE 'scan_history_summary'");
if ($chkSummary && $chkSummary->num_rows > 0) {
    $hasSummaryTable = true;
}

if ($hasSummaryTable) {
    $sumSql = "DELETE FROM scan_history_summary WHERE last_scan_at < (NOW() - INTERVAL {$summaryDays} DAY)";
    if ($mysqli->query($sumSql)) {
        $deletedSummary = (int)$mysqli->affected_rows;
    }
}

$result = [
    'status' => 'ok',
    'detail_days' => $detailDays,
    'summary_days' => $summaryDays,
    'deleted_detail_rows' => $deletedDetail,
    'deleted_summary_rows' => $deletedSummary,
    'ran_at' => date('Y-m-d H:i:s')
];

if ($isCli) {
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
}
