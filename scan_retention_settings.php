<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
$mysqli = db_connect();
require_subscription($mysqli);

if (!is_admin()) {
    http_response_code(403);
    die('Akses ditolak. Halaman ini khusus admin.');
}

$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';

function retention_ensure_settings_table($db) {
    $db->query("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function retention_get_int_setting($db, $key, $default) {
    $keyEsc = $db->real_escape_string($key);
    $res = $db->query("SELECT setting_value FROM app_settings WHERE setting_key = '{$keyEsc}' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $val = (int)$row['setting_value'];
        if ($val > 0) return $val;
    }
    return $default;
}

function retention_set_int_setting($db, $key, $value) {
    $keyEsc = $db->real_escape_string($key);
    $val = (int)$value;
    $db->query("INSERT INTO app_settings (setting_key, setting_value) VALUES ('{$keyEsc}', '{$val}') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
}

retention_ensure_settings_table($mysqli);

$detailDays = retention_get_int_setting($mysqli, 'scan_history_detail_days', 90);
$summaryDays = retention_get_int_setting($mysqli, 'scan_history_summary_days', 365);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $detailInput = isset($_POST['detail_days']) ? (int)$_POST['detail_days'] : 90;
    $summaryInput = isset($_POST['summary_days']) ? (int)$_POST['summary_days'] : 365;

    if ($detailInput < 7) $detailInput = 7;
    if ($summaryInput < 30) $summaryInput = 30;
    if ($summaryInput < $detailInput) $summaryInput = $detailInput;

    retention_set_int_setting($mysqli, 'scan_history_detail_days', $detailInput);
    retention_set_int_setting($mysqli, 'scan_history_summary_days', $summaryInput);

    $detailDays = $detailInput;
    $summaryDays = $summaryInput;
    $msg = 'Pengaturan retensi berhasil disimpan.';
}
?>
<?php if (!$isEmbedded) include 'header.php'; ?>
<style>
body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #f8fafc; }
.wrap { width: min(980px, 100%); box-sizing: border-box; padding: 16px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 14px; }
label { display: block; font-weight: 700; margin: 10px 0 6px; color: #334155; }
input[type=number] { width: 100%; max-width: 260px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }
button { background: #0f766e; color: #fff; border: 0; border-radius: 6px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
button:hover { background: #115e59; }
.note { font-size: 13px; color: #475569; line-height: 1.5; }
.ok { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
</style>
<div class="wrap">
    <div class="card">
        <h2 style="margin-top:0;">Pengaturan Retensi Riwayat Scan</h2>
        <p class="note">Atur lama penyimpanan data detail scan dan ringkasan sesi scan. Cleanup dijalankan lewat script cron harian.</p>
        <?php if ($msg): ?>
            <div class="ok"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="detail_days">Retensi Detail Scan (hari)</label>
            <input id="detail_days" name="detail_days" type="number" min="7" step="1" value="<?= (int)$detailDays ?>">

            <label for="summary_days">Retensi Ringkasan Scan (hari)</label>
            <input id="summary_days" name="summary_days" type="number" min="30" step="1" value="<?= (int)$summaryDays ?>">

            <div style="margin-top:14px;">
                <button type="submit">Simpan Pengaturan</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Cron Cleanup</h3>
        <p class="note" style="margin:0;">Jalankan script ini harian dari task scheduler/cron:</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; overflow:auto;">php cleanup_scan_history.php</pre>
    </div>
</div>
<?php if (!$isEmbedded) include 'footer.php'; ?>
