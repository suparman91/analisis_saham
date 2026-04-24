<?php
require_once 'auth.php'; // Panggil session
require_login();         // Wajib masuk

$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';
$portfolioUrl = static function (string $path) use ($isEmbedded): string {
    if (!$isEmbedded) {
        return $path;
    }
    return $path . (strpos($path, '?') === false ? '?' : '&') . 'embed=1';
};

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
require_once __DIR__ . '/robo_runtime.php';
$mysqli = db_connect();
require_subscription($mysqli); // Wajib langganan aktif

$user_id = get_user_id();
$roboSettings = robo_get_user_settings($mysqli, $user_id);
$marketContext = robo_get_market_context();
$runtimeConfig = robo_build_runtime_config($roboSettings, $marketContext);
$robo_run_msg = '';
$saldo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['strategy_profile'])) {
    $profile = strtolower(trim((string)$_POST['strategy_profile']));
    $marketAdaptive = isset($_POST['market_adaptive']) ? 1 : 0;
    $allowedProfiles = ['conservative', 'balanced', 'aggressive'];

    if (!in_array($profile, $allowedProfiles, true)) {
        header("Location: " . $portfolioUrl("portfolio.php?saldo_action=err&msg=" . urlencode("Profil strategi robo tidak valid.")));
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO robo_settings (user_id, strategy_profile, market_adaptive) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE strategy_profile = VALUES(strategy_profile), market_adaptive = VALUES(market_adaptive)");
    $stmt->bind_param("isi", $user_id, $profile, $marketAdaptive);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        header("Location: " . $portfolioUrl("portfolio.php?saldo_action=ok&msg=" . urlencode("Mode robo diperbarui ke " . ucfirst($profile) . ($marketAdaptive ? " dengan adaptasi pasar aktif." : " tanpa adaptasi pasar."))));
        exit;
    }

    header("Location: " . $portfolioUrl("portfolio.php?saldo_action=err&msg=" . urlencode("Perubahan mode robo gagal disimpan.")));
    exit;
}

if (isset($_GET['robo_run'])) {
    if ($_GET['robo_run'] === 'ok') {
        $robo_run_msg = isset($_GET['msg']) ? $_GET['msg'] : 'Robo berhasil dijalankan.';
    } elseif ($_GET['robo_run'] === 'err') {
        $robo_run_msg = isset($_GET['msg']) ? $_GET['msg'] : 'Robo gagal dijalankan.';
    }
}
if (isset($_GET['saldo_action'])) {
    if ($_GET['saldo_action'] === 'ok') {
        $saldo_msg = isset($_GET['msg']) ? $_GET['msg'] : 'Saldo berhasil ditambahkan.';
    } elseif ($_GET['saldo_action'] === 'err') {
        $saldo_msg = isset($_GET['msg']) ? $_GET['msg'] : 'Gagal menambahkan saldo.';
    }
}

// Handle setting Modal Awal Robot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_awal'])) {
    $new_modal = (float)$_POST['modal_awal'];
    if ($new_modal >= 1000000) { // Minimal 1 Juta
        // Reset saldo & bersihkan riwayat trade user ini saja
        $stmt = $mysqli->prepare("UPDATE users SET robo_capital = ?, robo_balance = ? WHERE id = ?");
        $stmt->bind_param("ddi", $new_modal, $new_modal, $user_id);
        $stmt->execute();
        
        $mysqli->query("DELETE FROM robo_trades WHERE user_id = $user_id");
        
        // Return JSON response if requested via AJAX
        if(isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        }
        header("Location: " . $portfolioUrl("portfolio.php"));
        exit;
    }
}

// Handle top-up saldo robot tanpa reset histori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_saldo'])) {
    $topup = (float)$_POST['tambah_saldo'];
    if ($topup > 0) {
        $stmt = $mysqli->prepare("UPDATE users SET robo_balance = robo_balance + ?, robo_capital = robo_capital + ? WHERE id = ?");
        $stmt->bind_param("ddi", $topup, $topup, $user_id);
        $ok = $stmt->execute();

        if ($ok) {
            header("Location: " . $portfolioUrl("portfolio.php?saldo_action=ok&msg=" . urlencode("Saldo robot bertambah Rp " . number_format($topup, 0, ',', '.'))));
            exit;
        }

        header("Location: " . $portfolioUrl("portfolio.php?saldo_action=err&msg=" . urlencode("Update saldo gagal diproses.")));
        exit;
    }

    header("Location: " . $portfolioUrl("portfolio.php?saldo_action=err&msg=" . urlencode("Nominal top-up harus lebih dari 0.")));
    exit;
}

// Fetch Data Modal & Saldo User
$res_bal = $mysqli->query("SELECT robo_capital, robo_balance FROM users WHERE id = $user_id LIMIT 1");
if ($res_bal && $res_bal->num_rows > 0) {
    $uData = $res_bal->fetch_assoc();
    $eq_capital = (float)$uData['robo_capital'];
    $balance = (float)$uData['robo_balance'];
} else {
    $eq_capital = 100000000;
    $balance = 100000000;
}

// --- AUTO RUN ROBOT: jalankan robot setiap kali halaman dibuka (hanya untuk user login & berlangganan) ---
if (!isset($_GET['robo_run']) && !isset($_GET['no_auto_robo'])) {
    // Cegah infinite loop jika robo_run_now.php redirect ke sini
    ob_start();
    include_once __DIR__ . '/robo_run_now.php';
    ob_end_clean();
    // Setelah robot dijalankan, reload halaman dengan pesan
    header('Location: ' . $portfolioUrl('portfolio.php?robo_run=ok&msg=Auto robot dijalankan.'));
    exit;
}

// Portfolio Open (User spesifik)
// Gabungkan posisi per simbol agar tampilan menyerupai akun sekuritas (lot ditotal, harga jadi rata-rata tertimbang).
$open = [];
$res_open = $mysqli->query("SELECT symbol, MIN(buy_date) AS buy_date, SUM(lots) AS lots, (SUM(buy_price * lots) / NULLIF(SUM(lots), 0)) AS buy_price, GROUP_CONCAT(buy_reason SEPARATOR ' | ') AS buy_reason FROM robo_trades WHERE status='OPEN' AND user_id = $user_id GROUP BY symbol ORDER BY buy_date DESC");
if ($res_open) { while ($r = $res_open->fetch_assoc()) $open[] = $r; }

// Hitung total investasi sesudah data posisi OPEN tersedia.
$total_invested = 0;
foreach ($open as $o) {
    $total_invested += (float)$o['buy_price'] * (int)$o['lots'] * 100;
}

// Ambil harga terbaru untuk setiap posisi OPEN lalu hitung floating P/L
$latest_prices = [];
if (count($open) > 0) {
    $symbols = [];
    foreach ($open as $o) {
        if (!empty($o['symbol'])) {
            $symbols[] = "'" . $mysqli->real_escape_string($o['symbol']) . "'";
        }
    }

    if (count($symbols) > 0) {
        $in = implode(',', array_unique($symbols));
        $sql_latest = "
            SELECT p.symbol, p.close
            FROM prices p
            INNER JOIN (
                SELECT symbol, MAX(date) AS max_date
                FROM prices
                WHERE symbol IN ($in)
                GROUP BY symbol
            ) x ON x.symbol = p.symbol AND x.max_date = p.date
        ";
        $res_latest = $mysqli->query($sql_latest);
        if ($res_latest) {
            while ($r = $res_latest->fetch_assoc()) {
                $latest_prices[$r['symbol']] = (float)$r['close'];
            }
        }
    }
}

foreach ($open as &$o) {
    $buy_price = (float)$o['buy_price'];
    $lots = (int)$o['lots'];
    $qty = $lots * 100;
    $latest = isset($latest_prices[$o['symbol']]) ? (float)$latest_prices[$o['symbol']] : $buy_price;
    $market_value = $latest * $qty;
    $cost_value = $buy_price * $qty;
    $pl_rp = $market_value - $cost_value;
    $pl_pct = $cost_value > 0 ? ($pl_rp / $cost_value) * 100 : 0;

    $o['latest_price'] = $latest;
    $o['market_value'] = $market_value;
    $o['floating_pl_rp'] = $pl_rp;
    $o['floating_pl_pct'] = $pl_pct;

    // Tambahkan alasan analisa lengkap (teknikal, fundamental, sentimen) dengan format terpisah
    $analysis = analyze_symbol($mysqli, $o['symbol']);
    $ai_signals = [];
    $other_reasons = [];
    $thought_steps = [];
    if (strpos($analysis['signal_details'], 'Golden Cross') !== false) {
        $ai_signals[] = 'Teknikal: Golden Cross (SMA5 > SMA20) terkonfirmasi';
        $thought_steps[] = 'Langkah 1 - Tren: Golden Cross terdeteksi, momentum naik dianggap valid.';
    } else {
        $thought_steps[] = 'Langkah 1 - Tren: Belum ada Golden Cross baru, posisi dipantau lebih ketat.';
    }
    // Volume breakout
    $avgVol5 = 0;
    $volBreakout = false;
    $prices = fetch_prices($mysqli, $o['symbol'], 30);
    $vols = array_column($prices, 'volume');
    $i = count($vols) - 1;
    if ($i >= 4) {
        $avgVol5 = array_sum(array_slice($vols, -5)) / 5;
        if ($vols[$i] > $avgVol5 * 1.5) {
            $volBreakout = true;
            $ai_signals[] = 'Volume Breakout: Volume hari ini (' . number_format($vols[$i]) . ') > 1.5x rata-rata 5 hari (' . number_format($avgVol5) . ')';
            $thought_steps[] = 'Langkah 2 - Volume: Ada konfirmasi volume breakout, probabilitas kelanjutan tren meningkat.';
        } else {
            $thought_steps[] = 'Langkah 2 - Volume: Belum breakout signifikan, posisi dijaga tanpa agresif tambah lot.';
        }
    } else {
        $thought_steps[] = 'Langkah 2 - Volume: Data volume historis belum cukup untuk konfirmasi breakout.';
    }
    // Alasan lain: sentimen, fundamental, skor AI
    if (!empty($analysis['global_sentiment'])) {
        $other_reasons[] = 'Sentimen: ' . $analysis['global_sentiment'] . (!empty($analysis['global_sentiment_details']) ? ' (' . $analysis['global_sentiment_details'] . ')' : '');
        $sentimentLabel = strtoupper((string)$analysis['global_sentiment']);
        if (strpos($sentimentLabel, 'NEG') !== false) {
            $thought_steps[] = 'Langkah 3 - Sentimen: Sentimen eksternal cenderung negatif, AI menurunkan agresivitas.';
        } elseif (strpos($sentimentLabel, 'POS') !== false) {
            $thought_steps[] = 'Langkah 3 - Sentimen: Sentimen eksternal positif, sinyal teknikal mendapat dukungan.';
        } else {
            $thought_steps[] = 'Langkah 3 - Sentimen: Sentimen netral, keputusan lebih ditopang data harga/volume.';
        }
    } else {
        $thought_steps[] = 'Langkah 3 - Sentimen: Data sentimen tidak tersedia, AI fokus pada teknikal dan risiko.';
    }
    if (!empty($analysis['fundamental'])) {
        $f = $analysis['fundamental'];
        $other_reasons[] = 'Fundamental: PE ' . ($f['pe'] ?? '-') . ', PBV ' . ($f['pbv'] ?? '-') . ', ROE ' . ($f['roe'] ?? '-') . ', EPS ' . ($f['eps'] ?? '-');
        $thought_steps[] = 'Langkah 4 - Fundamental: Kualitas emiten dicek via PE/PBV/ROE/EPS untuk validasi posisi menengah.';
    } else {
        $thought_steps[] = 'Langkah 4 - Fundamental: Data fundamental minim, bobot keputusan dialihkan ke momentum dan manajemen risiko.';
    }
    if (isset($analysis['fund_score'])) {
        $other_reasons[] = 'Skor AI: ' . ($analysis['fund_score'] ?? '-') . '/99 (Fundamental: ' . ($analysis['fund_score'] ?? '-') . ')';
        $scoreVal = (int)$analysis['fund_score'];
        if ($scoreVal >= 75) {
            $thought_steps[] = 'Langkah 5 - Skor: Skor AI tinggi (' . $scoreVal . '/99), posisi dipertahankan selama risk limit aman.';
        } elseif ($scoreVal >= 50) {
            $thought_steps[] = 'Langkah 5 - Skor: Skor AI menengah (' . $scoreVal . '/99), posisi tetap valid namun perlu monitoring rapat.';
        } else {
            $thought_steps[] = 'Langkah 5 - Skor: Skor AI rendah (' . $scoreVal . '/99), AI cenderung defensif dan siap evaluasi keluar.';
        }
    } else {
        $thought_steps[] = 'Langkah 5 - Skor: Skor AI tidak tersedia, keputusan ditentukan oleh kombinasi sinyal yang ada.';
    }

    if ($pl_pct <= -3) {
        $thought_steps[] = 'Langkah 6 - Risiko: Floating P/L sudah menembus area stop loss, prioritas utama proteksi modal.';
    } elseif ($pl_pct >= 5) {
        $thought_steps[] = 'Langkah 6 - Risiko: Floating P/L sudah di area target profit, AI siaga mengunci keuntungan.';
    } else {
        $thought_steps[] = 'Langkah 6 - Risiko: Floating P/L masih dalam zona normal, strategi utama adalah hold terukur.';
    }

    $signalLabel = strtoupper((string)($analysis['signal'] ?? 'HOLD'));
    $thought_steps[] = 'Kesimpulan AI: Sinyal saat ini ' . $signalLabel . ', keputusan tetap dinamis mengikuti update harga berikutnya.';

    $o['ai_signals'] = implode(' | ', $ai_signals);
    $o['other_reasons'] = implode(' | ', $other_reasons);
    $o['ai_thought_steps'] = $thought_steps;
}
unset($o);

// Portfolio Closed (History) (User spesifik)
$closed = [];
$res_closed = $mysqli->query("SELECT * FROM robo_trades WHERE status='CLOSED' AND user_id = $user_id ORDER BY sell_date DESC LIMIT 50");
if ($res_closed) { while ($r = $res_closed->fetch_assoc()) $closed[] = $r; }

$ledger_date = isset($_GET['ledger_date']) ? trim((string)$_GET['ledger_date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ledger_date)) {
    $ledger_date = date('Y-m-d');
}

$ledger_action = isset($_GET['ledger_action']) ? strtoupper(trim((string)$_GET['ledger_action'])) : 'ALL';
$allowed_ledger_actions = ['ALL', 'BUY', 'SELL', 'SEROK', 'SKIP', 'SKIP_SELL'];
if (!in_array($ledger_action, $allowed_ledger_actions, true)) {
    $ledger_action = 'ALL';
}

$ledger_symbol = isset($_GET['ledger_symbol']) ? strtoupper(trim((string)$_GET['ledger_symbol'])) : '';
if ($ledger_symbol !== '' && !preg_match('/^[A-Z0-9\.]+$/', $ledger_symbol)) {
    $ledger_symbol = '';
}

function buildSyntheticLedgerTimestamp(string $date, string $action, int $sequence = 0): string {
    $safeDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    $sequence = max(0, $sequence);
    $action = strtoupper(trim($action));

    if ($action === 'SELL') {
        $base = strtotime($safeDate . ' 15:14:50');
        return date('Y-m-d H:i:s', $base - min($sequence, 599));
    }

    if ($action === 'SEROK') {
        $base = strtotime($safeDate . ' 13:45:10');
        return date('Y-m-d H:i:s', $base + min($sequence, 599));
    }

    $base = strtotime($safeDate . ' 09:15:10');
    return date('Y-m-d H:i:s', $base + min($sequence, 599));
}

$ledger_date_esc = $mysqli->real_escape_string($ledger_date);
$ledger_action_sql = '';
if ($ledger_action !== 'ALL') {
    if ($ledger_action === 'SKIP') {
        $ledger_action_sql = " AND action LIKE 'SKIP%' ";
    } else {
        $ledger_action_esc = $mysqli->real_escape_string($ledger_action);
        $ledger_action_sql = " AND action = '{$ledger_action_esc}' ";
    }
}
$ledger_symbol_sql = '';
if ($ledger_symbol !== '') {
        $ledger_symbol_esc = $mysqli->real_escape_string($ledger_symbol);
        $ledger_symbol_sql = " AND symbol = '{$ledger_symbol_esc}' ";
}

$ledger_where_sql = "
    WHERE user_id = {$user_id}
      AND DATE(created_at) = '{$ledger_date_esc}'
      {$ledger_action_sql}
      {$ledger_symbol_sql}
";

$ledger_page = isset($_GET['ledger_page']) ? (int)$_GET['ledger_page'] : 1;
if ($ledger_page < 1) {
    $ledger_page = 1;
}
$ledger_per_page = 50;
$ledger_total_rows = 0;
$ledger_count_sql = "SELECT COUNT(*) AS total FROM robo_audit_log {$ledger_where_sql}";
$res_ledger_count = $mysqli->query($ledger_count_sql);
if ($res_ledger_count) {
    $ledger_total_rows = (int)$res_ledger_count->fetch_assoc()['total'];
}

$ledger_total_pages = max(1, (int)ceil($ledger_total_rows / $ledger_per_page));
if ($ledger_page > $ledger_total_pages) {
    $ledger_page = $ledger_total_pages;
}
$ledger_offset = ($ledger_page - 1) * $ledger_per_page;

$ledger_rows = [];
$ledger_sql = "
    SELECT created_at, symbol, action, price, lots, reason
    FROM robo_audit_log
    {$ledger_where_sql}
    ORDER BY created_at DESC
    LIMIT {$ledger_per_page} OFFSET {$ledger_offset}
";
$res_ledger = $mysqli->query($ledger_sql);
if ($res_ledger) {
    while ($r = $res_ledger->fetch_assoc()) {
        $ledger_rows[] = $r;
    }
}

// Fallback/source-of-truth untuk penutupan posisi: langsung dari tabel robo_trades
// Ini memastikan event SELL tetap terlihat walau audit log lama belum lengkap.
$closed_history_rows = [];
if ($ledger_action === 'ALL' || $ledger_action === 'SELL') {
    $closed_symbol_sql = '';
    if ($ledger_symbol !== '') {
        $ledger_symbol_esc2 = $mysqli->real_escape_string($ledger_symbol);
        $closed_symbol_sql = " AND symbol = '{$ledger_symbol_esc2}' ";
    }

    $closed_history_sql = "
        SELECT sell_date, symbol, sell_price, lots, sell_reason, profit_loss_rp, profit_loss_pct
        FROM robo_trades
        WHERE user_id = {$user_id}
          AND status = 'CLOSED'
          AND sell_date = '{$ledger_date_esc}'
          {$closed_symbol_sql}
        ORDER BY sell_date DESC, id DESC
        LIMIT 200
    ";

    $res_closed_history = $mysqli->query($closed_history_sql);
    if ($res_closed_history) {
        while ($r = $res_closed_history->fetch_assoc()) {
            $closed_history_rows[] = $r;
        }
    }
}

$ledger_export_rows = [];
$ledger_export_sql = "
    SELECT created_at, symbol, action, price, lots, reason
    FROM robo_audit_log
    {$ledger_where_sql}
    ORDER BY created_at DESC
";
$res_ledger_export = $mysqli->query($ledger_export_sql);
if ($res_ledger_export) {
    while ($r = $res_ledger_export->fetch_assoc()) {
        $ledger_export_rows[] = $r;
    }
}

// Sinkronisasi transaksi dari tabel utama jika belum tercatat di audit log.
$ledger_fallback_added_sell = 0;
$ledger_fallback_added_buy = 0;
$ledger_fallback_added_serok = 0;
$ledger_fallback_added = 0;
if (($ledger_action === 'ALL' || $ledger_action === 'SELL') && count($closed_history_rows) > 0) {
    $auditSellIndex = [];
    foreach ($ledger_export_rows as $row) {
        $rowAction = strtoupper((string)($row['action'] ?? ''));
        if ($rowAction !== 'SELL') {
            continue;
        }
        $rowSymbol = strtoupper((string)($row['symbol'] ?? ''));
        $rowPrice = isset($row['price']) ? (float)$row['price'] : 0;
        $rowLots = isset($row['lots']) ? (int)$row['lots'] : 0;
        $key = $rowSymbol . '|' . number_format($rowPrice, 4, '.', '') . '|' . $rowLots;
        $auditSellIndex[$key] = true;
    }

    foreach ($closed_history_rows as $closedIndex => $closedRow) {
        $symbol = strtoupper((string)($closedRow['symbol'] ?? ''));
        $sellPrice = isset($closedRow['sell_price']) ? (float)$closedRow['sell_price'] : 0;
        $lots = isset($closedRow['lots']) ? (int)$closedRow['lots'] : 0;
        if ($symbol === '' || $sellPrice <= 0 || $lots <= 0) {
            continue;
        }

        $key = $symbol . '|' . number_format($sellPrice, 4, '.', '') . '|' . $lots;
        if (isset($auditSellIndex[$key])) {
            continue;
        }

        $sellDate = (string)($closedRow['sell_date'] ?? $ledger_date);
        $reason = '[SYNC robo_trades] ' . (string)($closedRow['sell_reason'] ?? 'Sell tercatat di transaksi utama');
        $synthetic = [
            'created_at' => buildSyntheticLedgerTimestamp($sellDate, 'SELL', (int)$closedIndex),
            'symbol' => $symbol,
            'action' => 'SELL',
            'price' => $sellPrice,
            'lots' => $lots,
            'reason' => $reason,
        ];

        $ledger_rows[] = $synthetic;
        $ledger_export_rows[] = $synthetic;
        $auditSellIndex[$key] = true;
        $ledger_fallback_added_sell++;
    }

    if ($ledger_fallback_added_sell > 0) {
        usort($ledger_rows, function ($a, $b) {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });
        usort($ledger_export_rows, function ($a, $b) {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });
    }
}

// Sinkronisasi BUY/SEROK dari tabel transaksi utama jika audit log tidak lengkap.
if ($ledger_action === 'ALL' || $ledger_action === 'BUY' || $ledger_action === 'SEROK') {
    $buy_symbol_sql = '';
    if ($ledger_symbol !== '') {
        $ledger_symbol_esc3 = $mysqli->real_escape_string($ledger_symbol);
        $buy_symbol_sql = " AND symbol = '{$ledger_symbol_esc3}' ";
    }

    $buy_history_rows = [];
    $buy_history_sql = "
        SELECT buy_date, symbol, buy_price, lots, buy_reason
        FROM robo_trades
        WHERE user_id = {$user_id}
          AND buy_date = '{$ledger_date_esc}'
          {$buy_symbol_sql}
        ORDER BY buy_date DESC, id DESC
        LIMIT 300
    ";
    $res_buy_history = $mysqli->query($buy_history_sql);
    if ($res_buy_history) {
        while ($r = $res_buy_history->fetch_assoc()) {
            $buy_history_rows[] = $r;
        }
    }

    if (count($buy_history_rows) > 0) {
        $auditActionCount = [];
        foreach ($ledger_export_rows as $row) {
            $rowAction = strtoupper((string)($row['action'] ?? ''));
            if ($rowAction !== 'BUY' && $rowAction !== 'SEROK') {
                continue;
            }
            $rowSymbol = strtoupper((string)($row['symbol'] ?? ''));
            $rowPrice = isset($row['price']) ? (float)$row['price'] : 0;
            $rowLots = isset($row['lots']) ? (int)$row['lots'] : 0;
            if ($rowSymbol === '' || $rowPrice <= 0 || $rowLots <= 0) {
                continue;
            }
            $key = $rowAction . '|' . $rowSymbol . '|' . number_format($rowPrice, 4, '.', '') . '|' . $rowLots;
            $auditActionCount[$key] = ($auditActionCount[$key] ?? 0) + 1;
        }

        foreach ($buy_history_rows as $buyIndex => $buyRow) {
            $symbol = strtoupper((string)($buyRow['symbol'] ?? ''));
            $buyPrice = isset($buyRow['buy_price']) ? (float)$buyRow['buy_price'] : 0;
            $lots = isset($buyRow['lots']) ? (int)$buyRow['lots'] : 0;
            if ($symbol === '' || $buyPrice <= 0 || $lots <= 0) {
                continue;
            }

            $buyReasonRaw = (string)($buyRow['buy_reason'] ?? '');
            $buyReasonUpper = strtoupper($buyReasonRaw);
            $inferredAction = (strpos($buyReasonUpper, 'SEROK') !== false)
                ? 'SEROK'
                : 'BUY';

            if ($ledger_action !== 'ALL' && $ledger_action !== $inferredAction) {
                continue;
            }

            $key = $inferredAction . '|' . $symbol . '|' . number_format($buyPrice, 4, '.', '') . '|' . $lots;
            if (!empty($auditActionCount[$key])) {
                $auditActionCount[$key]--;
                continue;
            }

            $buyDate = (string)($buyRow['buy_date'] ?? $ledger_date);
            $reason = '[SYNC robo_trades] ' . ($buyReasonRaw !== '' ? $buyReasonRaw : 'Pembelian tercatat di transaksi utama');
            $synthetic = [
                'created_at' => buildSyntheticLedgerTimestamp($buyDate, $inferredAction, (int)$buyIndex),
                'symbol' => $symbol,
                'action' => $inferredAction,
                'price' => $buyPrice,
                'lots' => $lots,
                'reason' => $reason,
            ];

            $ledger_rows[] = $synthetic;
            $ledger_export_rows[] = $synthetic;

            if ($inferredAction === 'SEROK') {
                $ledger_fallback_added_serok++;
            } else {
                $ledger_fallback_added_buy++;
            }
        }

        if ($ledger_fallback_added_buy > 0 || $ledger_fallback_added_serok > 0) {
            usort($ledger_rows, function ($a, $b) {
                return strcmp((string)$b['created_at'], (string)$a['created_at']);
            });
            usort($ledger_export_rows, function ($a, $b) {
                return strcmp((string)$b['created_at'], (string)$a['created_at']);
            });
        }
    }
}

$ledger_fallback_added = $ledger_fallback_added_sell + $ledger_fallback_added_buy + $ledger_fallback_added_serok;

$ledger_summary = [
    'BUY' => ['count' => 0, 'value' => 0],
    'SELL' => ['count' => 0, 'value' => 0],
    'SEROK' => ['count' => 0, 'value' => 0],
    'SKIP' => ['count' => 0, 'value' => 0],
    'NET' => 0,
];
foreach ($ledger_export_rows as $row) {
    $action = strtoupper((string)$row['action']);
    $price = isset($row['price']) ? (float)$row['price'] : 0;
    $lots = isset($row['lots']) ? (int)$row['lots'] : 0;
    $value = ($price > 0 && $lots > 0) ? $price * $lots * 100 : 0;

    if ($action === 'BUY' || $action === 'SELL' || $action === 'SEROK') {
        $ledger_summary[$action]['count']++;
        $ledger_summary[$action]['value'] += $value;
        if ($action === 'SELL') {
            $ledger_summary['NET'] += $value;
        } else {
            $ledger_summary['NET'] -= $value;
        }
    } elseif (strpos($action, 'SKIP') === 0) {
        $ledger_summary['SKIP']['count']++;
        $ledger_summary['SKIP']['value'] += $value;
    }
}

if (isset($_GET['export_ledger']) && $_GET['export_ledger'] === '1') {
    $csvDate = preg_replace('/[^0-9\-]/', '', $ledger_date);
    $csvAction = $ledger_action !== 'ALL' ? $ledger_action : 'ALL';
    $csvSymbol = $ledger_symbol !== '' ? $ledger_symbol : 'ALL';
    $csvFilename = 'ledger_robo_' . $csvDate . '_' . $csvAction . '_' . preg_replace('/[^A-Z0-9\.]/', '', $csvSymbol) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $csvFilename);

    $output = fopen('php://output', 'w');
    if ($output) {
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Tanggal', 'Waktu', 'Ticker', 'Aksi', 'Harga', 'Lot', 'Value', 'Keterangan']);
        foreach ($ledger_export_rows as $row) {
            $price = isset($row['price']) ? (float)$row['price'] : 0;
            $lots = isset($row['lots']) ? (int)$row['lots'] : 0;
            $value = ($price > 0 && $lots > 0) ? $price * $lots * 100 : 0;
            fputcsv($output, [
                date('Y-m-d', strtotime($row['created_at'])),
                date('H:i:s', strtotime($row['created_at'])),
                $row['symbol'],
                strtoupper((string)$row['action']),
                $price,
                $lots,
                $value,
                (string)$row['reason'],
            ]);
        }
        fclose($output);
    }
    exit;
}

$total_pl = 0;
$win = 0;
$loss = 0;
$res_stats = $mysqli->query("SELECT profit_loss_rp FROM robo_trades WHERE status='CLOSED' AND user_id = $user_id");
if ($res_stats) {
    while ($r = $res_stats->fetch_assoc()) {
        $total_pl += $r['profit_loss_rp'];
        if ($r['profit_loss_rp'] > 0) $win++;
        else $loss++;
    }
}

$total_trades = $win + $loss;
$win_rate = $total_trades > 0 ? round(($win / $total_trades) * 100, 1) : 0;
$total_equity = $balance; // Saldo Cash

// Tambahkan nilai saham yang masih nyangkut di OPEN (Floating Value)
foreach ($open as $o) {
    $total_equity += (isset($o['market_value']) ? (float)$o['market_value'] : ((float)$o['buy_price'] * (int)$o['lots'] * 100));
}
// Floating P/L (Open Trades) = total unrealized profit/loss semua posisi open (standar sekuritas)
$floating_pl = 0;
if (isset($open) && is_array($open)) {
    foreach ($open as $o) {
        $floating_pl += isset($o['floating_pl_rp']) ? $o['floating_pl_rp'] : 0;
    }
}
$floating_pl_pct = $eq_capital > 0 ? ($floating_pl / $eq_capital) * 100 : 0;

// Tambahkan deklarasi $total_pl_pct agar tidak error notice
$total_pl_pct = $eq_capital > 0 ? ($total_pl / $eq_capital) * 100 : 0;

// Kandidat rekomendasi yang belum dieksekusi robot
$pending_reco = [];
$MAX_OPEN_POSITIONS = 10;
$MAX_BUY_PER_RUN = 2;
$MIN_CASH_TO_BUY = 1000000;
$TARGET_POSITIONS = (int)$runtimeConfig['target_positions'];
$MAX_ALLOC = (int)$runtimeConfig['max_alloc'];
$ALLIN_SCORE = (int)$runtimeConfig['allin_score'];
$MIN_ENTRY_SCORE = (int)$runtimeConfig['min_entry_score'];
$ALLOW_NEW_BUYS = !empty($runtimeConfig['allow_new_buys']);

$open_count = count($open);
$open_symbols = [];
foreach ($open as $o) {
    $open_symbols[$o['symbol']] = true;
}

$symbols = [];
$res_sym = $mysqli->query("SELECT DISTINCT symbol FROM prices ORDER BY symbol ASC LIMIT 400");
if ($res_sym) {
    while ($r = $res_sym->fetch_assoc()) {
        if (!empty($r['symbol'])) {
            $symbols[] = $r['symbol'];
        }
    }
}

foreach ($symbols as $sym) {
    if (isset($open_symbols[$sym])) {
        continue;
    }

    $prices = fetch_prices($mysqli, $sym, 30);
    if (count($prices) < 25) {
        continue;
    }

    $closes = array_column($prices, 'close');
    $vols = array_column($prices, 'volume');
    $i = count($closes) - 1;
    $currPrice = (float)$closes[$i];
    if ($currPrice < 50 || (int)$vols[$i] < 50000) {
        continue;
    }

    $sma5 = sma($closes, 5);
    $sma20 = sma($closes, 20);
    if (!isset($sma5[$i], $sma20[$i], $sma5[$i - 1], $sma20[$i - 1])) {
        continue;
    }

    // Screening utama: hanya saham dengan sinyal AI 'BUY' atau 'STRONG BUY'
    $analysis = analyze_symbol($mysqli, $sym);
    if (!isset($analysis['signal']) || ($analysis['signal'] !== 'BUY' && $analysis['signal'] !== 'STRONG BUY')) {
        continue;
    }

    // Cek SMA5 masih di atas SMA20 (sinyal aktif saat ini)
    if (!($sma5[$i] > $sma20[$i])) {
        continue;
    }
    // Cari hari Golden Cross dalam 3 hari terakhir
    $crossDay = -1;
    for ($d = 0; $d <= 2; $d++) {
        $prevIdx = $i - $d - 1;
        if ($prevIdx >= 0 && isset($sma5[$prevIdx], $sma20[$prevIdx]) && $sma5[$prevIdx] <= $sma20[$prevIdx]) {
            $crossDay = $i - $d;
            break;
        }
    }
    if ($crossDay < 0) {
        continue; // Tidak ada Golden Cross dalam 3 hari terakhir
    }
    // Cek volume breakout pada hari crossover
    $avgVol5ForCross = $crossDay >= 5 ? array_sum(array_slice($vols, $crossDay - 5, 5)) / 5 : 0;
    if ($avgVol5ForCross > 0 && !($vols[$crossDay] > $avgVol5ForCross * 1.5)) {
        continue; // Volume breakout tidak terpenuhi saat crossover
    }
    $crossDaysAgo = $i - $crossDay;
    $avgVol5 = array_sum(array_slice($vols, -5)) / 5;

    $volRatio = $avgVol5 > 0 ? ($vols[$i] / $avgVol5) : 1;
    $smaSpreadPct = $sma20[$i] > 0 ? (($sma5[$i] - $sma20[$i]) / $sma20[$i]) * 100 : 0;
    $ret5 = 0;
    if ($i >= 5 && $closes[$i - 5] > 0) {
        $ret5 = (($currPrice - $closes[$i - 5]) / $closes[$i - 5]) * 100;
    }

    $score = 55;
    $score += min(25, max(0, ($volRatio - 1.5) * 20));
    $score += min(10, max(0, $smaSpreadPct * 2));
    $score += min(10, max(0, $ret5));
    if ((string)$analysis['signal'] === 'STRONG BUY') {
        $score += 5;
    }
    $score = (int)max(0, min(99, round($score)));

    if ($score < $MIN_ENTRY_SCORE) {
        continue;
    }

    $pending_reco[] = [
        'symbol' => $sym,
        'price' => $currPrice,
        'score' => $score,
        'vol_ratio' => round($volRatio, 2),
        'sma_spread' => round($smaSpreadPct, 2),
        'cross_days_ago' => $crossDaysAgo,
    ];
}

usort($pending_reco, function ($a, $b) {
    return $b['score'] <=> $a['score'];
});

// --- Pembagian modal otomatis sesuai score ---
$total_score = 0;
foreach ($pending_reco as $c) { $total_score += $c['score']; }
$max_alloc = min($balance, 10000000); // Batas maksimal alokasi modal (misal 10jt atau sisa cash)
$min_cash = 1000000; // Sisakan cash minimal 1jt
$used_alloc = 0;
foreach ($pending_reco as &$cand) {
    // Hitung alokasi modal proporsional & estimasi lot lebih dulu agar $cand['lots'] selalu ada
    $portion = $total_score > 0 ? ($cand['score'] / $total_score) : 0;
    $alloc = floor($portion * ($max_alloc - $min_cash));
    $lot = 0;
    if ($cand['price'] > 0) {
        $lot = floor($alloc / ($cand['price'] * 100));
    }
    $cand['lots'] = max(1, $lot); // Minimal 1 lot jika memungkinkan
    // Status logic
    $status = '-';
    if (!$ALLOW_NEW_BUYS) {
        $status = 'MONITOR ONLY';
    } elseif ($balance < 1000000) {
        $status = 'SALDO TIDAK CUKUP';
    } elseif ($open_count >= $MAX_OPEN_POSITIONS) {
        $status = 'ANTRIAN';
    } elseif ($cand['lots'] * $cand['price'] * 100 > $balance) {
        $status = 'SALDO TIDAK CUKUP';
    } else {
        $status = 'SIAP BELI';
    }
    $cand['status'] = $status;
    $symbol = $cand['symbol'];
    $analysis = analyze_symbol($mysqli, $symbol);
    // Hitung alokasi modal proporsional
    $portion = $total_score > 0 ? ($cand['score'] / $total_score) : 0;
    $alloc = floor($portion * ($max_alloc - $min_cash));
    // Estimasi lot (kelipatan 100, harga saham)
    $lot = 0;
    if ($cand['price'] > 0) {
        $lot = floor($alloc / ($cand['price'] * 100));
    }
    $cand['lots'] = max(1, $lot); // Minimal 1 lot jika memungkinkan
    $used_alloc += $cand['lots'] * $cand['price'] * 100;
    // ...existing alasan logic...
    $ai_signals = [];
    $other_reasons = [];
    // 1. Sinyal Golden Cross
    if (strpos($analysis['signal_details'], 'Golden Cross') !== false) {
        $ai_signals[] = 'Teknikal: Golden Cross (SMA5 > SMA20) terkonfirmasi';
    }
    // 2. Volume Breakout
    $avgVol5 = 0;
    $volBreakout = false;
    $prices = fetch_prices($mysqli, $symbol, 30);
    $vols = array_column($prices, 'volume');
    $i = count($vols) - 1;
    if ($i >= 4) {
        $avgVol5 = array_sum(array_slice($vols, -5)) / 5;
        if ($vols[$i] > $avgVol5 * 1.5) {
            $volBreakout = true;
            $ai_signals[] = 'Volume Breakout: Volume hari ini (' . number_format($vols[$i]) . ') > 1.5x rata-rata 5 hari (' . number_format($avgVol5) . ')';
        }
    }
    // 3. Alasan status prioritas
    if ($cand['status'] === 'MONITOR ONLY') {
        $other_reasons[] = 'Belum masuk: robo sedang monitor-only karena sesi pasar atau sentimen saat ini.';
    } elseif ($cand['status'] === 'ANTRIAN') {
        $other_reasons[] = 'Belum masuk: slot posisi penuh, menunggu slot kosong.';
    } elseif ($cand['status'] === 'SALDO TIDAK CUKUP') {
        $other_reasons[] = 'Belum masuk: saldo tidak cukup untuk beli lot minimal.';
    } elseif ($cand['status'] === 'SIAP BELI') {
        $other_reasons[] = 'Prioritas masuk: siap dieksekusi robot pada run berikutnya.';
    } else {
        $other_reasons[] = 'Status: ' . $cand['status'];
    }
    // Tambahkan alasan lain dari AI jika ada
    if (!empty($analysis['global_sentiment'])) {
        $other_reasons[] = 'Sentimen: ' . $analysis['global_sentiment'] . (!empty($analysis['global_sentiment_details']) ? ' (' . $analysis['global_sentiment_details'] . ')' : '');
    }
    if (!empty($analysis['fundamental'])) {
        $f = $analysis['fundamental'];
        $other_reasons[] = 'Fundamental: PE ' . ($f['pe'] ?? '-') . ', PBV ' . ($f['pbv'] ?? '-') . ', ROE ' . ($f['roe'] ?? '-') . ', EPS ' . ($f['eps'] ?? '-');
    }
    if (isset($analysis['fund_score'])) {
        $other_reasons[] = 'Skor AI: ' . ($analysis['fund_score'] ?? '-') . '/99 (Fundamental: ' . ($analysis['fund_score'] ?? '-') . ')';
    }
    $cand['ai_signals'] = implode(' | ', $ai_signals);
    $cand['other_reasons'] = implode(' | ', $other_reasons);
    $cand['ai_reason'] = 'Sinyal AI: ' . $cand['ai_signals'] . ' || Alasan Lain: ' . $cand['other_reasons'];
}
unset($cand);

if (count($pending_reco) > 15) {
    $pending_reco = array_slice($pending_reco, 0, 15);
}

?>
<?php
$pageTitle = 'Robo-Trader Simulator | Analisis Saham';
?>
<?php include 'header.php'; ?>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      padding: 15px;
      width: 100%;
      max-width: 100%;
      margin: 0;
      background: #f8fafc;
      box-sizing: border-box;
    }
    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); flex-wrap: wrap; }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; white-space: nowrap; }
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }
    
    .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .card-stat { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-top: 4px solid #3b82f6; }
    .card-stat h3 { margin: 0 0 10px 0; color: #64748b; font-size: 14px; text-transform: uppercase; }
    .card-stat .value { font-size: 28px; font-weight: bold; color: #0f172a; margin-bottom: 5px; }
    .card-stat .sub { font-size: 13px; color: #94a3b8; }
    
    .tbl-container { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 30px; }
        .tbl-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #1e293b; display: flex; justify-content: space-between; }
        .open-trades-header { flex-wrap: wrap; gap: 10px; align-items: center; }
        .open-trades-actions { display:flex; flex-direction:column; gap:4px; align-items:flex-end; margin-left:auto; }
        .btn-refresh-live { padding:6px 14px; background:#0ea5e9; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:13px; font-weight:bold; max-width:100%; white-space:nowrap; }
        .live-status { font-size:11px; color:#64748b; text-align:right; }
        .live-status-indicator {
            display:inline-block;
            width:8px;
            height:8px;
            border-radius:999px;
            margin-right:6px;
            background:#94a3b8;
            vertical-align:middle;
            box-shadow:0 0 0 2px rgba(148,163,184,0.25);
        }
        .live-status-indicator.ok {
            background:#16a34a;
            box-shadow:0 0 0 2px rgba(22,163,74,0.22);
        }
        .live-status-indicator.warn {
            background:#d97706;
            box-shadow:0 0 0 2px rgba(217,119,6,0.22);
        }
        .live-status-indicator.err {
            background:#dc2626;
            box-shadow:0 0 0 2px rgba(220,38,38,0.22);
        }
        @media (max-width: 900px) {
            .open-trades-actions { width:100%; align-items:flex-start; margin-left:0; }
            .btn-refresh-live { width:100%; white-space:normal; }
            .live-status { text-align:left; }
        }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    th { background: #f8fafc; color: #64748b; font-size: 13px; text-transform: uppercase; }
    tr:last-child td { border-bottom: none; }
    tr:hover { background: #f8fafc; }

    .open-trades-table { table-layout: fixed; }
    .open-trades-table th, .open-trades-table td { vertical-align: top; }
    .open-trades-table th:nth-child(1) { width: 9%; }
    .open-trades-table th:nth-child(2) { width: 10%; }
    .open-trades-table th:nth-child(3) { width: 11%; }
    .open-trades-table th:nth-child(4) { width: 9%; }
    .open-trades-table th:nth-child(5) { width: 9%; }
    .open-trades-table th:nth-child(6) { width: 11%; }
    .open-trades-table th:nth-child(7) { width: 20%; }
    .open-trades-table th:nth-child(8) { width: 21%; }
    .open-trades-table .ai-cell,
    .open-trades-table .reason-cell {
        font-size: 12px;
        line-height: 1.45;
        color: #0f172a;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .open-trades-table .ot-preview {
        display: block;
    }
    .open-trades-table .ot-full {
        display: none;
    }
    .open-trades-table tr.open-row-expanded .ot-preview {
        display: none;
    }
    .open-trades-table tr.open-row-expanded .ot-full {
        display: block;
    }
    .btn-open-detail {
        margin-top: 8px;
        padding: 4px 8px;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
    }
    .btn-open-detail:hover {
        background: #dbeafe;
    }
    .ot-thought {
        margin-top: 8px;
        padding: 8px 10px;
        border: 1px solid #dbeafe;
        border-radius: 8px;
        background: #f8fbff;
    }
    .ot-thought-title {
        margin-bottom: 5px;
        font-size: 11px;
        font-weight: 700;
        color: #1d4ed8;
        text-transform: uppercase;
        letter-spacing: .2px;
    }
    .ot-thought-list {
        margin: 0;
        padding-left: 16px;
        font-size: 12px;
        color: #1e293b;
    }
    .ot-thought-list li {
        margin-bottom: 3px;
    }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .bg-green { background: #dcfce7; color: #166534; }
    .bg-red { background: #fee2e2; color: #991b1b; }
    .bg-blue { background: #dbeafe; color: #1e40af; }
    .bg-gray { background: #f1f5f9; color: #475569; }
    .bg-orange { background: #ffedd5; color: #9a3412; }
    .bg-teal { background: #ccfbf1; color: #115e59; }
    
    .text-green { color: #166534; font-weight: bold; }
    .text-red { color: #991b1b; font-weight: bold; }
  </style>

<style>
.robo-loading-inline {
    display: none;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #1e3a8a;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 6px 10px;
    white-space: nowrap;
}
.robo-loading-inline.active {
    display: inline-flex;
}
.robo-loading-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid #bfdbfe;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: roboSpin 0.8s linear infinite;
}
@keyframes roboSpin {
    to { transform: rotate(360deg); }
}
</style>

<div style="margin-bottom: 25px;">
    <h2 style="margin:0; color:#0f172a;">&#x1F916; AI Robo-Trader Simulator</h2>
    <span style="color:#64748b; font-size:14px; display:block; margin-top:5px;">
      Simulasi Systematic Auto-Trading (Paper Trading) secara otomatis memonitor sinyal <b>Golden Cross</b> dengan <b>Ledakan Volume</b>, membeli dan menjual saham tanpa intervensi manusia berdasarkan batasan rasio risiko bawaan (-3% SL, +5% TP).
    </span>
</div>

<div style="margin-bottom:20px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; padding:14px 16px; border-radius:8px; font-size:13px; line-height:1.6;">
        <b>Mode Robo Aktif:</b> <?= security_escape($runtimeConfig['profile_label']) ?>
        | <b>Sesi Pasar:</b> <?= security_escape($marketContext['session']) ?>
        | <b>Sentimen:</b> <?= security_escape($marketContext['sentiment_label']) ?> (score <?= (int)$marketContext['sentiment_score'] ?>)
        | <b>Entry Minimum:</b> Score <?= (int)$runtimeConfig['min_entry_score'] ?>/99
        | <b>Maks Buy per Run:</b> <?= (int)$runtimeConfig['max_buy_per_run'] ?>
        <br>
        <?= security_escape($runtimeConfig['status_note']) ?>
</div>

    <div style="margin-bottom:20px; background:#fff; padding:18px 20px; border-radius:8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        <form method="POST" style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
            <div>
                <label for="strategyProfile" style="display:block; font-size:13px; font-weight:600; color:#0f172a; margin-bottom:6px;">Profil strategi</label>
                <select id="strategyProfile" name="strategy_profile" style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; min-width:210px; background:#fff;">
                    <option value="conservative" <?= $roboSettings['strategy_profile'] === 'conservative' ? 'selected' : '' ?>>Conservative</option>
                    <option value="balanced" <?= $roboSettings['strategy_profile'] === 'balanced' ? 'selected' : '' ?>>Balanced</option>
                    <option value="aggressive" <?= $roboSettings['strategy_profile'] === 'aggressive' ? 'selected' : '' ?>>Aggressive</option>
                </select>
            </div>
            <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #dbeafe; background:#f8fbff; border-radius:6px; color:#1e3a8a; font-size:13px;">
                <input type="checkbox" name="market_adaptive" value="1" <?= !empty($roboSettings['market_adaptive']) ? 'checked' : '' ?>>
                Adaptif terhadap sentimen dan sesi pasar
            </label>
            <button type="submit" style="padding:10px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold; white-space:nowrap;">Simpan Mode Robo</button>
            <div style="font-size:12px; color:#64748b; max-width:520px; line-height:1.5;">
                Conservative lebih selektif, Balanced default, Aggressive lebih cepat masuk. Saat adaptif aktif, robo bisa menahan entry baru ketika sentimen atau sesi pasar tidak mendukung.
            </div>
        </form>
    </div>

<div style="margin-bottom:20px; background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
  <div>
    <h3 style="margin:0 0 5px 0; font-size:15px; color:#0f172a;">⚙️ Pengaturan Modal Awal (Capital)</h3>
    <span style="color:#64748b; font-size:13px;">Tentukan modal virtual yang dikelola oleh AI Robo-Trader. <b>Peringatan:</b> Mengubah modal akan mereset/menghapus seluruh riwayat trading Anda.</span>
        <div style="margin-top:6px; color:#64748b; font-size:12px;">Butuh tambah dana tanpa reset? Gunakan form Top-up Saldo di kanan.</div>
  </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
        <form id="formTopup" method="POST" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
            <input type="number" name="tambah_saldo" min="100000" step="100000" placeholder="Top-up saldo (Rp)" style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; font-weight:bold; min-width:200px;">
            <button type="submit" style="padding:10px 16px; background:#16a34a; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold; white-space:nowrap;">Tambah Saldo</button>
        </form>

        <form id="formModal" method="POST" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
    <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
    <input type="number" name="modal_awal" min="1000000" step="100000" value="<?= $eq_capital ?>" style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; font-weight:bold; min-width:200px;">
    <button type="submit" onclick="return confirm('Apakah Anda yakin? Mengubah modal akan MENGHAPUS SEMUA DATA simulasi (history & portofolio) untuk akun ini dan memulai dari awal.')" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight:bold; white-space:nowrap;">Update & Reset Data</button>
        <a id="btnRunRobotNow" href="<?= htmlspecialchars($portfolioUrl('robo_run_now.php'), ENT_QUOTES, 'UTF-8') ?>" style="padding:10px 20px; background:#2563eb; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold; white-space:nowrap; display:inline-flex; align-items:center;">Jalankan Robot Sekarang</a>
        <span id="roboLoadingInline" class="robo-loading-inline" aria-live="polite">
            <span class="robo-loading-spinner"></span>
            <span id="roboLoadingText">Robo AI sedang memproses data...</span>
        </span>
        </form>
    </div>
</div>

<?php if ($saldo_msg !== ''): ?>
<div style="margin-bottom:20px; background:#ecfdf3; border:1px solid #86efac; color:#14532d; padding:12px 14px; border-radius:8px; font-size:13px;">
        <?= htmlspecialchars($saldo_msg) ?>
</div>
<?php endif; ?>

<?php if ($robo_run_msg !== ''): ?>
<div style="margin-bottom:20px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; padding:12px 14px; border-radius:8px; font-size:13px;">
    <?= htmlspecialchars($robo_run_msg) ?>
</div>
<?php endif; ?>


<div id="robotLastRun" style="margin:10px 0 20px 0; color:#64748b; font-size:13px; font-style:italic;"></div>
<script>
function setRoboLoading(isLoading, text) {
    var inline = document.getElementById('roboLoadingInline');
    var label = document.getElementById('roboLoadingText');
    var runNowBtn = document.getElementById('btnRunRobotNow');
    if (!inline) return;
    if (label && text) {
        label.textContent = text;
    }
    inline.classList.toggle('active', !!isLoading);
    if (runNowBtn) {
        runNowBtn.style.pointerEvents = isLoading ? 'none' : 'auto';
        runNowBtn.style.opacity = isLoading ? '0.7' : '1';
    }
}

function updateRobotLastRun(msg) {
    var el = document.getElementById('robotLastRun');
    if (!el) return;
    var now = new Date();
    var timeStr = now.toLocaleTimeString('id-ID');
    el.textContent = 'Terakhir robot dijalankan: ' + timeStr + (msg ? ' — ' + msg : '');
}

function runRoboAuto() {
    setRoboLoading(true, 'Robo AI otomatis sedang berjalan...');
    return fetch('robo_run_api.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var extra = '';
            if (data.runtime && data.runtime.profile) {
                extra = ' [' + data.runtime.profile + ' | ' + ((data.runtime.market && data.runtime.market.sentiment_label) ? data.runtime.market.sentiment_label : 'NETRAL') + ']';
            }
            updateRobotLastRun((data.msg || 'Selesai.') + extra);
        })
        .catch(function () {
            updateRobotLastRun('Gagal mengambil status robo otomatis.');
        })
        .finally(function () {
            setRoboLoading(false);
        });
}

function toggleOpenTradeDetail(button) {
    var row = button ? button.closest('tr') : null;
    if (!row) return;
    var expanded = row.classList.toggle('open-row-expanded');
    button.textContent = expanded ? 'Sembunyikan Detail' : 'Lihat Detail';
}

// Jalankan saat halaman dimuat (ambil status awal)
document.addEventListener('DOMContentLoaded', function() {
    var runNowBtn = document.getElementById('btnRunRobotNow');
    if (runNowBtn) {
        runNowBtn.addEventListener('click', function () {
            setRoboLoading(true, 'Robo AI manual sedang dijalankan...');
        });
    }

    runRoboAuto();
});

// Update setiap kali robot auto-run
setInterval(function() {
    runRoboAuto();
}, 60000);
</script>

<style>
.dashboard-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 30px;
}
.card-stat {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  border: 1px solid #e2e8f0;
  padding: 18px 22px 16px 22px;
  min-width: 220px;
  flex: 1 1 220px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  margin: 0;
}
.card-stat h3 {
  margin: 0 0 8px 0;
  font-size: 16px;
  color: #0f172a;
  font-weight: 600;
}
.card-stat .value {
  font-size: 22px;
  font-weight: bold;
  margin-bottom: 4px;
}
.card-stat .sub {
  font-size: 13px;
  color: #64748b;
}
.text-green { color: #16a34a; }
.text-red { color: #dc2626; }
.stat-row {
  display: flex;
  flex-direction: row;
  gap: 32px;
  width: 100%;
  align-items: flex-start;
}
.stat-row .card-stat { min-width: 0; flex: 1 1 0; }
</style>

<div class="dashboard-cards" id="roboDashboard">
    <div class="card-stat">
        <h3>Modal Awal</h3>
        <div class="value">Rp <?= number_format($eq_capital, 0, ',', '.') ?></div>
        <div class="sub">Investasi awal simulasi</div>
    </div>
    <div class="card-stat">
        <h3>Total Investasi</h3>
        <div class="value">Rp <?= number_format($total_invested, 0, ',', '.') ?></div>
        <div class="sub">Sudah dibelikan saham</div>
    </div>
    <div class="card-stat">
        <h3>Sisa Modal / Cash</h3>
        <div class="value">Rp <?= number_format($balance, 0, ',', '.') ?></div>
        <div class="sub">Cash available</div>
    </div>
    <div class="card-stat">
        <h3>Total Equity</h3>
        <div class="value">Rp <?= number_format($total_equity, 0, ',', '.') ?></div>
        <div class="sub">Cash + Market Value</div>
    </div>
</div>
<div class="dashboard-cards stat-row" style="margin-bottom:30px;">
    <div class="card-stat">
      <h3>Profit / Loss (All Time)</h3>
      <div class="value <?= $total_pl >= 0 ? 'text-green' : 'text-red' ?>">
          <?= $total_pl > 0 ? '+' : '' ?><?= number_format($total_pl, 0, ',', '.') ?>
      </div>
      <div class="sub">Return (Closed): <b><?= round($total_pl_pct, 2) ?>%</b> vs Modal Rp <?= number_format($eq_capital, 0, ',', '.') ?></div>
    </div>
    <div class="card-stat">
      <h3>Floating P/L (Open Trades)</h3>
      <div class="value <?= $floating_pl >= 0 ? 'text-green' : 'text-red' ?>">
          <?= $floating_pl > 0 ? '+' : '' ?><?= number_format($floating_pl, 0, ',', '.') ?>
      </div>
      <div class="sub">Return (Floating): <?= number_format($floating_pl_pct, 2) ?>% dari modal berjalan</div>
    </div>
</div>

<div class="dashboard-cards" style="margin-bottom:30px;">
    <div class="card-stat">
        <h3>Win Rate Accuracy</h3>
        <div class="value <?= $win_rate >= 60 ? 'text-green' : ($win_rate > 0 ? 'text-orange' : '') ?>"><?= $win_rate ?>%</div>
        <div class="sub"><?= $win ?> Win / <?= $loss ?> Loss (<?= $total_trades ?> Trades)</div>
    </div>
    <div class="card-stat">
        <h3>Open Positions</h3>
        <div class="value text-blue"><?= count($open) ?> Saham</div>
        <div class="sub">Max Limit: 10 Emiten</div>
    </div>
</div>

<div class="tbl-container">
    <div class="tbl-header open-trades-header">
        <span>Posisi Menggantung (OPEN TRADES)</span>
        <div class="open-trades-actions">
            <button id="btnRefreshPrice" class="btn-refresh-live" onclick="fetchLivePrices(false)">&#x21bb; Refresh Harga Live <span id="liveCountdown" style="font-size:11px; opacity:0.8;"></span></button>
            <span id="liveRefreshStatus" class="live-status"><span id="liveStatusDot" class="live-status-indicator"></span>Auto-refresh setiap 30 detik</span>
        </div>
    </div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        OPEN berarti order <b>sudah terbeli</b> oleh robot (mode simulasi/paper trading), bukan sekadar watchlist.
    </div>
    <table class="open-trades-table">
        <thead>
            <tr>
                <th>Ticker</th>
                <th>Tanggal Beli</th>
                <th>Lot / Value</th>
                <th>Harga Rata-Rata</th>
                <th>Harga Terbaru</th>
                <th>Profit / Loss</th>
                <th>Sinyal AI</th>
                <th>Alasan Lain</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($open) == 0): ?>
            <tr><td colspan="8" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada saham yang sedang di-hold. Menunggu sinyal market.</td></tr>
            <?php else: ?>
                <?php foreach ($open as $o): ?>
                <?php
                    $aiFullText = isset($o['ai_signals']) ? trim((string)$o['ai_signals']) : '';
                    $reasonFullText = isset($o['other_reasons']) ? trim((string)$o['other_reasons']) : '';
                    $thoughtSteps = isset($o['ai_thought_steps']) && is_array($o['ai_thought_steps']) ? $o['ai_thought_steps'] : [];
                    if ($aiFullText === '') {
                        $aiFullText = '-';
                    }
                    if ($reasonFullText === '') {
                        $reasonFullText = '-';
                    }

                    $aiPreviewText = $aiFullText;
                    $reasonPreviewText = $reasonFullText;
                    if (strlen($aiPreviewText) > 220) {
                        $aiPreviewText = substr($aiPreviewText, 0, 220) . '...';
                    }
                    if (strlen($reasonPreviewText) > 260) {
                        $reasonPreviewText = substr($reasonPreviewText, 0, 260) . '...';
                    }
                    $hasExpandableDetail = ($aiPreviewText !== $aiFullText) || ($reasonPreviewText !== $reasonFullText) || count($thoughtSteps) > 0;
                ?>
                <tr data-symbol="<?= htmlspecialchars($o['symbol']) ?>" data-buy="<?= (float)$o['buy_price'] ?>" data-lots="<?= (int)$o['lots'] ?>">
                    <td><b><a href="chart.php?symbol=<?= $o['symbol'] ?>" target="_blank" style="color:#0d6efd; text-decoration:none;"><?= $o['symbol'] ?></a></b></td>
                    <td><?= $o['buy_date'] ?></td>
                    <td><?= $o['lots'] ?> / Rp <?= number_format($o['lots']*100*$o['buy_price'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($o['buy_price'], 0, ',', '.') ?></td>
                    <td class="cell-latest">Rp <?= number_format((float)$o['latest_price'], 0, ',', '.') ?></td>
                    <td class="cell-pl <?= ((float)$o['floating_pl_rp']) >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= ((float)$o['floating_pl_rp']) > 0 ? '+' : '' ?>Rp <?= number_format((float)$o['floating_pl_rp'], 0, ',', '.') ?>
                        (<?= round((float)$o['floating_pl_pct'], 2) ?>%)
                    </td>
                    <td class="ai-cell" title="<?= htmlspecialchars($aiFullText, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="ot-preview"><?= htmlspecialchars($aiPreviewText) ?></div>
                        <div class="ot-full"><?= htmlspecialchars($aiFullText) ?></div>
                    </td>
                    <td class="reason-cell" title="<?= htmlspecialchars($reasonFullText, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="ot-preview"><?= htmlspecialchars($reasonPreviewText) ?></div>
                        <div class="ot-full">
                            <?= htmlspecialchars($reasonFullText) ?>
                            <?php if (count($thoughtSteps) > 0): ?>
                            <div class="ot-thought">
                                <div class="ot-thought-title">Thought Process AI (Ringkas)</div>
                                <ol class="ot-thought-list">
                                    <?php foreach ($thoughtSteps as $step): ?>
                                        <li><?= htmlspecialchars((string)$step) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasExpandableDetail): ?>
                            <button type="button" class="btn-open-detail" onclick="toggleOpenTradeDetail(this)">Lihat Detail</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tbl-container" style="border-top: 4px solid #94a3b8;">
    <div class="tbl-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <span>Riwayat Transaksi (LEDGER HISTORY)</span>
        <form method="get" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0;">
            <input type="hidden" name="robo_run" value="ok">
            <input type="hidden" name="msg" value="Filter riwayat transaksi diterapkan.">
            <label for="ledger_date" style="font-size:12px; color:#475569;">Tanggal:</label>
            <input id="ledger_date" name="ledger_date" type="date" value="<?= htmlspecialchars($ledger_date) ?>" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
            <label for="ledger_action" style="font-size:12px; color:#475569;">Aksi:</label>
            <select id="ledger_action" name="ledger_action" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                <option value="ALL" <?= $ledger_action === 'ALL' ? 'selected' : '' ?>>Semua</option>
                <option value="BUY" <?= $ledger_action === 'BUY' ? 'selected' : '' ?>>BUY</option>
                <option value="SELL" <?= $ledger_action === 'SELL' ? 'selected' : '' ?>>SELL</option>
                <option value="SEROK" <?= $ledger_action === 'SEROK' ? 'selected' : '' ?>>SEROK</option>
                <option value="SKIP" <?= $ledger_action === 'SKIP' ? 'selected' : '' ?>>SKIP</option>
            </select>
            <label for="ledger_symbol" style="font-size:12px; color:#475569;">Ticker:</label>
            <input id="ledger_symbol" name="ledger_symbol" type="text" value="<?= htmlspecialchars($ledger_symbol) ?>" placeholder="Mis: PPRE.JK" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; width:110px; text-transform:uppercase;">
            <button type="submit" style="padding:6px 12px; background:#0d6efd; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:12px; font-weight:bold;">Tampilkan</button>
            <button type="submit" name="export_ledger" value="1" style="padding:6px 12px; background:#16a34a; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:12px; font-weight:bold;">Export CSV</button>
            <a href="portfolio.php?robo_run=ok&msg=Filter%20ledger%20direset.&ledger_date=<?= urlencode(date('Y-m-d')) ?>" style="padding:6px 12px; background:#e2e8f0; color:#334155; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold;">Reset Filter</a>
        </form>
    </div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        Default hanya menampilkan transaksi pada tanggal yang dipilih. Riwayat ini mencakup BUY, SELL, SEROK, dan aksi robot lain pada hari tersebut. Total data audit: <b><?= number_format($ledger_total_rows, 0, ',', '.') ?></b> baris.
        <?php if ($ledger_fallback_added > 0): ?>
            <br><span style="color:#0f766e;">Tambahan sinkronisasi dari transaksi utama: <b><?= number_format($ledger_fallback_added, 0, ',', '.') ?></b> baris (BUY: <?= number_format($ledger_fallback_added_buy, 0, ',', '.') ?>, SEROK: <?= number_format($ledger_fallback_added_serok, 0, ',', '.') ?>, SELL: <?= number_format($ledger_fallback_added_sell, 0, ',', '.') ?>).</span>
        <?php endif; ?>
        <?php if ($ledger_action === 'ALL' || $ledger_action === 'SELL'): ?>
            <br>Penutupan posisi dari sumber transaksi robo: <b><?= number_format(count($closed_history_rows), 0, ',', '.') ?></b> baris.
        <?php endif; ?>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; padding:12px 20px; border-bottom:1px solid #e2e8f0; background:#fff;">
        <div style="border:1px solid #dbeafe; background:#eff6ff; border-radius:8px; padding:10px 12px;">
            <div style="font-size:11px; color:#475569;">BUY</div>
            <div style="font-weight:bold; color:#1d4ed8;"><?= $ledger_summary['BUY']['count'] ?> transaksi</div>
            <div style="font-size:12px; color:#334155;">Rp <?= number_format($ledger_summary['BUY']['value'], 0, ',', '.') ?></div>
        </div>
        <div style="border:1px solid #dcfce7; background:#f0fdf4; border-radius:8px; padding:10px 12px;">
            <div style="font-size:11px; color:#475569;">SELL</div>
            <div style="font-weight:bold; color:#15803d;"><?= $ledger_summary['SELL']['count'] ?> transaksi</div>
            <div style="font-size:12px; color:#334155;">Rp <?= number_format($ledger_summary['SELL']['value'], 0, ',', '.') ?></div>
        </div>
        <div style="border:1px solid #fef3c7; background:#fffbeb; border-radius:8px; padding:10px 12px;">
            <div style="font-size:11px; color:#475569;">SEROK</div>
            <div style="font-weight:bold; color:#b45309;"><?= $ledger_summary['SEROK']['count'] ?> transaksi</div>
            <div style="font-size:12px; color:#334155;">Rp <?= number_format($ledger_summary['SEROK']['value'], 0, ',', '.') ?></div>
        </div>
        <div style="border:1px solid #e5e7eb; background:#f8fafc; border-radius:8px; padding:10px 12px;">
            <div style="font-size:11px; color:#475569;">SKIP</div>
            <div style="font-weight:bold; color:#475569;"><?= $ledger_summary['SKIP']['count'] ?> aksi</div>
            <div style="font-size:12px; color:#334155;">Rp <?= number_format($ledger_summary['SKIP']['value'], 0, ',', '.') ?></div>
        </div>
        <div style="border:1px solid <?= $ledger_summary['NET'] >= 0 ? '#dcfce7' : '#fee2e2' ?>; background:<?= $ledger_summary['NET'] >= 0 ? '#f0fdf4' : '#fef2f2' ?>; border-radius:8px; padding:10px 12px;">
            <div style="font-size:11px; color:#475569;">NET BUY/SELL</div>
            <div style="font-weight:bold; color:<?= $ledger_summary['NET'] >= 0 ? '#15803d' : '#b91c1c' ?>;"><?= $ledger_summary['NET'] >= 0 ? 'NET SELL' : 'NET BUY' ?></div>
            <div style="font-size:12px; color:#334155;">Rp <?= number_format(abs($ledger_summary['NET']), 0, ',', '.') ?></div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Ticker</th>
                <th>Aksi</th>
                <th>Harga</th>
                <th>Lot</th>
                <th>Value</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ledger_rows) == 0): ?>
            <tr><td colspan="7" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada riwayat transaksi pada tanggal <?= htmlspecialchars($ledger_date) ?>.</td></tr>
            <?php else: ?>
                <?php foreach ($ledger_rows as $row): ?>
                <?php
                    $action = strtoupper((string)$row['action']);
                    $price = isset($row['price']) ? (float)$row['price'] : 0;
                    $lots = isset($row['lots']) ? (int)$row['lots'] : 0;
                    $value = $price > 0 && $lots > 0 ? $price * $lots * 100 : 0;
                    $reasonText = (string)($row['reason'] ?? '');
                    $isSyncRow = stripos($reasonText, '[SYNC robo_trades]') === 0;
                    $reasonDisplay = $isSyncRow
                        ? preg_replace('/^\[SYNC robo_trades\]\s*/i', '', $reasonText)
                        : $reasonText;
                    $badge_cls = 'bg-gray';
                    if ($action === 'BUY') $badge_cls = 'bg-blue';
                    elseif ($action === 'SEROK') $badge_cls = 'bg-orange';
                    elseif ($action === 'SELL') $badge_cls = 'bg-green';
                    elseif ($action === 'SKIP_SELL') $badge_cls = 'bg-red';
                    elseif (strpos($action, 'SKIP') !== false) $badge_cls = 'bg-gray';
                ?>
                <tr>
                    <td><?= htmlspecialchars(date('H:i:s', strtotime($row['created_at']))) ?></td>
                    <td><b><?= htmlspecialchars($row['symbol']) ?></b></td>
                    <td><span class="badge <?= $badge_cls ?>"><?= htmlspecialchars($action) ?></span></td>
                    <td><?= $price > 0 ? 'Rp ' . number_format($price, 0, ',', '.') : '-' ?></td>
                    <td><?= $lots > 0 ? number_format($lots, 0, ',', '.') : '-' ?></td>
                    <td><?= $value > 0 ? 'Rp ' . number_format($value, 0, ',', '.') : '-' ?></td>
                    <td style="font-size:12px;">
                        <?php if ($isSyncRow): ?>
                            <span class="badge bg-teal" style="margin-right:6px;">SYNC</span>
                        <?php endif; ?>
                        <?= htmlspecialchars((string)$reasonDisplay) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($ledger_total_pages > 1): ?>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:12px 20px 16px 20px; border-top:1px solid #e2e8f0; background:#fff;">
        <div style="font-size:12px; color:#475569;">
            Halaman <b><?= $ledger_page ?></b> dari <b><?= $ledger_total_pages ?></b>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($ledger_page > 1): ?>
                <a href="portfolio.php?robo_run=ok&msg=Paging%20ledger%20diterapkan.&ledger_date=<?= urlencode($ledger_date) ?>&ledger_action=<?= urlencode($ledger_action) ?>&ledger_symbol=<?= urlencode($ledger_symbol) ?>&ledger_page=<?= $ledger_page - 1 ?>" style="padding:6px 12px; background:#e2e8f0; color:#334155; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold;">&laquo; Prev</a>
            <?php endif; ?>
            <?php if ($ledger_page < $ledger_total_pages): ?>
                <a href="portfolio.php?robo_run=ok&msg=Paging%20ledger%20diterapkan.&ledger_date=<?= urlencode($ledger_date) ?>&ledger_action=<?= urlencode($ledger_action) ?>&ledger_symbol=<?= urlencode($ledger_symbol) ?>&ledger_page=<?= $ledger_page + 1 ?>" style="padding:6px 12px; background:#0d6efd; color:#fff; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold;">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($ledger_action === 'ALL' || $ledger_action === 'SELL'): ?>
<div class="tbl-container" style="border-top: 4px solid #64748b;">
    <div class="tbl-header">Riwayat Penutupan Posisi (Sumber: robo_trades)</div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        Bagian ini menampilkan posisi yang benar-benar sudah ditutup pada tanggal filter, walau audit log tidak lengkap.
    </div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Ticker</th>
                <th>Harga Jual</th>
                <th>Lot</th>
                <th>Value</th>
                <th>P/L</th>
                <th>Alasan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($closed_history_rows) == 0): ?>
            <tr><td colspan="7" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada posisi CLOSED pada tanggal <?= htmlspecialchars($ledger_date) ?>.</td></tr>
            <?php else: ?>
                <?php foreach ($closed_history_rows as $row): ?>
                <?php
                    $sellPrice = isset($row['sell_price']) ? (float)$row['sell_price'] : 0;
                    $lots = isset($row['lots']) ? (int)$row['lots'] : 0;
                    $value = ($sellPrice > 0 && $lots > 0) ? ($sellPrice * $lots * 100) : 0;
                    $plRp = isset($row['profit_loss_rp']) ? (float)$row['profit_loss_rp'] : 0;
                    $plPct = isset($row['profit_loss_pct']) ? (float)$row['profit_loss_pct'] : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['sell_date']) ?></td>
                    <td><b><?= htmlspecialchars((string)$row['symbol']) ?></b></td>
                    <td><?= $sellPrice > 0 ? 'Rp ' . number_format($sellPrice, 0, ',', '.') : '-' ?></td>
                    <td><?= $lots > 0 ? number_format($lots, 0, ',', '.') : '-' ?></td>
                    <td><?= $value > 0 ? 'Rp ' . number_format($value, 0, ',', '.') : '-' ?></td>
                    <td class="<?= $plRp >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $plRp >= 0 ? '+' : '-' ?>Rp <?= number_format(abs($plRp), 0, ',', '.') ?>
                        (<?= $plPct >= 0 ? '+' : '' ?><?= number_format($plPct, 2) ?>%)
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars((string)$row['sell_reason']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="tbl-container" style="border-top: 4px solid #3b82f6;">
    <div class="tbl-header">Rekomendasi Menunggu (Belum Dibeli)</div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        Kandidat dengan Golden Cross dalam <b>3 hari terakhir</b> + sinyal AI BUY yang masih aktif. Badge <span style="background:#16a34a;color:#fff;padding:1px 5px;border-radius:3px;font-size:11px;">BARU</span> = crossover hari ini, <span style="background:#2563eb;color:#fff;padding:1px 5px;border-radius:3px;font-size:11px;">D+n</span> = crossover n hari lalu.
    </div>
    <table>
        <thead>
            <tr>
                <th>Ticker</th>
                <th>Harga Monitor</th>
                <th>Score</th>
                <th>Signal</th>
                <th>Estimasi Lot</th>
                <th>Status</th>
                <th>Sinyal AI</th>
                <th>Alasan Lain</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pending_reco) == 0): ?>
            <tr><td colspan="8" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada kandidat menunggu saat ini. (Tidak ada saham dengan Golden Cross 3 hari terakhir + sinyal AI BUY + volume breakout yang memenuhi threshold.)</td></tr>
            <?php else: ?>
                <?php foreach ($pending_reco as $c): ?>
                <tr>
                    <td><b><a href="chart.php?symbol=<?= urlencode($c['symbol']) ?>" target="_blank" style="color:#0d6efd; text-decoration:none;"><?= htmlspecialchars($c['symbol']) ?></a></b></td>
                    <td>Rp <?= number_format($c['price'], 0, ',', '.') ?></td>
                    <td>
                        <span class="badge bg-blue"><?= (int)$c['score'] ?>/99</span>
                    </td>
                    <td>
                        <?php
                            $cda = isset($c['cross_days_ago']) ? (int)$c['cross_days_ago'] : 0;
                            if ($cda === 0) {
                                echo '<span style="background:#16a34a;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:600;">BARU</span>';
                            } else {
                                echo '<span style="background:#2563eb;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:600;">D+' . $cda . '</span>';
                            }
                        ?>
                    </td>
                    <td><?= isset($c['lots']) ? (int)$c['lots'] : 0 ?> lot</td>
                    <td>
                        <?php
                            $status = isset($c['status']) ? $c['status'] : '-';
                            $cls = 'bg-gray';
                            if (strpos($status, 'SIAP') !== false) $cls = 'bg-green';
                            elseif (strpos($status, 'SALDO') !== false || strpos($status, 'TIDAK CUKUP') !== false) $cls = 'bg-red';
                            elseif (strpos($status, 'ANTRIAN') !== false || strpos($status, 'SLOT') !== false) $cls = 'bg-orange';
                        ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars($status) ?></span>
                    </td>
                    <td style="font-size:12px;">
                        <?= isset($c['ai_signals']) ? htmlspecialchars($c['ai_signals']) : '-' ?>
                    </td>
                    <td style="font-size:12px;">
                        <?= isset($c['other_reasons']) ? htmlspecialchars($c['other_reasons']) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<script>
var _liveRefreshTimer = null;
var _liveCountdownTimer = null;
var _liveRefreshIntervalMs = 30000;
var _liveCountdownSec = _liveRefreshIntervalMs / 1000;
var _liveRefreshInFlight = false;
var _liveLastUpdated = '';

function _setLiveStatus(text, state) {
    const statusEl = document.getElementById('liveRefreshStatus');
    const dotEl = document.getElementById('liveStatusDot');
    if (statusEl) {
        statusEl.innerHTML = '<span id="liveStatusDot" class="live-status-indicator"></span>' + text;
    }
    const realDot = document.getElementById('liveStatusDot') || dotEl;
    if (realDot) {
        realDot.classList.remove('ok', 'warn', 'err');
        if (state === 'ok') realDot.classList.add('ok');
        else if (state === 'warn') realDot.classList.add('warn');
        else if (state === 'err') realDot.classList.add('err');
    }
}

function fetchLivePrices(silent) {
    const rows = document.querySelectorAll('[data-symbol]');
    if (!rows.length || _liveRefreshInFlight) return;

    const btn = document.getElementById('btnRefreshPrice');
    if (!silent && btn) { btn.disabled = true; }
    _setLiveStatus('Memperbarui harga...', 'warn');

    const symbols = Array.from(rows).map(r => r.dataset.symbol);
    _liveRefreshInFlight = true;

    fetch('fetch_realtime.php?symbols=' + encodeURIComponent(symbols.join(',')))
    .then(r => r.json())
    .then(data => {
        let totalEquity = <?= $balance ?>;
        rows.forEach(row => {
            const sym = row.dataset.symbol;
            const buyPrice = parseFloat(row.dataset.buy);
            const lots = parseInt(row.dataset.lots);
            const live = data && data.data && data.data[sym] && !data.data[sym].error
                ? Number(data.data[sym].price)
                : null;

            if (!live || live <= 0) {
                totalEquity += buyPrice * lots * 100;
                return;
            }

            const qty = lots * 100;
            const plRp = (live - buyPrice) * qty;
            const plPct = buyPrice > 0 ? (plRp / (buyPrice * qty)) * 100 : 0;
            totalEquity += live * qty;

            const cellLatest = row.querySelector('.cell-latest');
            const cellPl = row.querySelector('.cell-pl');

            if (cellLatest) {
                cellLatest.textContent = 'Rp ' + live.toLocaleString('id-ID', {maximumFractionDigits:0});
                cellLatest.style.transition = 'background 0.4s';
                cellLatest.style.background = '#fef9c3';
                setTimeout(() => { cellLatest.style.background = ''; }, 1200);
            }
            if (cellPl) {
                const sign = plRp >= 0 ? '+' : '';
                cellPl.textContent = sign + 'Rp ' + Math.abs(plRp).toLocaleString('id-ID', {maximumFractionDigits:0}) + ' (' + (plRp >= 0 ? '+' : '') + plPct.toFixed(2) + '%)';
                cellPl.className = 'cell-pl ' + (plRp >= 0 ? 'text-green' : 'text-red');
            }
        });

        const equityEl = document.getElementById('cardTotalEquity');
        if (equityEl) equityEl.textContent = 'Rp ' + totalEquity.toLocaleString('id-ID', {maximumFractionDigits:0});

        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID');
        _liveLastUpdated = timeStr;
        _setLiveStatus('Terakhir diperbarui: ' + timeStr + ' • refresh lagi ' + _liveCountdownSec + ' detik', 'ok');
    })
    .catch(() => {
        _setLiveStatus('Gagal memperbarui harga • coba lagi otomatis ' + _liveCountdownSec + ' detik.', 'err');
    })
    .finally(() => {
        _liveRefreshInFlight = false;
        if (btn) { btn.disabled = false; }
        _startCountdown();
    });
}

function _startCountdown() {
    clearInterval(_liveCountdownTimer);
    clearInterval(_liveRefreshTimer);
    _liveCountdownSec = _liveRefreshIntervalMs / 1000;
    _liveCountdownTimer = setInterval(function() {
        _liveCountdownSec--;
        if (_liveLastUpdated) {
            _setLiveStatus('Terakhir diperbarui: ' + _liveLastUpdated + ' • refresh lagi ' + _liveCountdownSec + ' detik', 'ok');
        } else {
            _setLiveStatus('Auto-refresh setiap 30 detik • berikutnya ' + _liveCountdownSec + ' detik', 'warn');
        }
        const cdEl = document.getElementById('liveCountdown');
        if (cdEl) cdEl.textContent = _liveCountdownSec + 's';
        if (_liveCountdownSec <= 0) {
            clearInterval(_liveCountdownTimer);
        }
    }, 1000);

    _liveRefreshTimer = setTimeout(function() {
        fetchLivePrices(true);
    }, _liveRefreshIntervalMs);
}

// Auto-start on page load if there are open positions
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('[data-symbol]');
    if (rows.length > 0) {
        fetchLivePrices(true);
    }
});
</script>


<?php include 'footer.php'; ?>