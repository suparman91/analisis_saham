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
$mysqli = db_connect();
require_subscription($mysqli); // Wajib langganan aktif

$user_id = get_user_id();
$robo_run_msg = '';
$saldo_msg = '';
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

// --- Hitung total investasi (modal yang sudah diinvestasikan ke saham/posisi aktif) ---
if (!isset($total_invested)) {
    $total_invested = 0;
    if (isset($open) && is_array($open)) {
        foreach ($open as $o) {
            $total_invested += (float)$o['buy_price'] * (int)$o['lots'] * 100;
        }
    }
}

// Portfolio Open (User spesifik)
$open = [];
$res_open = $mysqli->query("SELECT * FROM robo_trades WHERE status='OPEN' AND user_id = $user_id ORDER BY buy_date DESC");
if ($res_open) { while ($r = $res_open->fetch_assoc()) $open[] = $r; }

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
    if (strpos($analysis['signal_details'], 'Golden Cross') !== false) {
        $ai_signals[] = 'Teknikal: Golden Cross (SMA5 > SMA20) terkonfirmasi';
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
        }
    }
    // Alasan lain: sentimen, fundamental, skor AI
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
    $o['ai_signals'] = implode(' | ', $ai_signals);
    $o['other_reasons'] = implode(' | ', $other_reasons);
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
$TARGET_POSITIONS = 5;
$MAX_ALLOC = 10000000;
$ALLIN_SCORE = 90;

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

    $avgVol5 = array_sum(array_slice($vols, -5)) / 5;
    if (!($sma5[$i - 1] <= $sma20[$i - 1] && $sma5[$i] > $sma20[$i] && $vols[$i] > $avgVol5 * 1.5)) {
        continue;
    }

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
    $score = (int)max(0, min(99, round($score)));

    $pending_reco[] = [
        'symbol' => $sym,
        'price' => $currPrice,
        'score' => $score,
        'vol_ratio' => round($volRatio, 2),
        'sma_spread' => round($smaSpreadPct, 2),
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
    if ($balance < 1000000) {
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
    if ($cand['status'] === 'ANTRIAN') {
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
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    th { background: #f8fafc; color: #64748b; font-size: 13px; text-transform: uppercase; }
    tr:last-child td { border-bottom: none; }
    tr:hover { background: #f8fafc; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .bg-green { background: #dcfce7; color: #166534; }
    .bg-red { background: #fee2e2; color: #991b1b; }
    .bg-blue { background: #dbeafe; color: #1e40af; }
    .bg-gray { background: #f1f5f9; color: #475569; }
    .bg-orange { background: #ffedd5; color: #9a3412; }
    
    .text-green { color: #166534; font-weight: bold; }
    .text-red { color: #991b1b; font-weight: bold; }
  </style>

<div style="margin-bottom: 25px;">
    <h2 style="margin:0; color:#0f172a;">&#x1F916; AI Robo-Trader Simulator</h2>
    <span style="color:#64748b; font-size:14px; display:block; margin-top:5px;">
      Simulasi Systematic Auto-Trading (Paper Trading) secara otomatis memonitor sinyal <b>Golden Cross</b> dengan <b>Ledakan Volume</b>, membeli dan menjual saham tanpa intervensi manusia berdasarkan batasan rasio risiko bawaan (-3% SL, +5% TP).
    </span>
</div>

<div style="margin-bottom:20px; background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
  <div>
    <h3 style="margin:0 0 5px 0; font-size:15px; color:#0f172a;">⚙️ Pengaturan Modal Awal (Capital)</h3>
    <span style="color:#64748b; font-size:13px;">Tentukan modal virtual yang dikelola oleh AI Robo-Trader. <b>Peringatan:</b> Mengubah modal akan mereset/menghapus seluruh riwayat trading Anda.</span>
        <div style="margin-top:6px; color:#64748b; font-size:12px;">Butuh tambah dana tanpa reset? Gunakan form Top-up Saldo di kanan.</div>
  </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
        <form id="formTopup" method="POST" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
            <input type="number" name="tambah_saldo" min="100000" step="100000" placeholder="Top-up saldo (Rp)" style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; font-weight:bold; min-width:200px;">
            <button type="submit" style="padding:10px 16px; background:#16a34a; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold; white-space:nowrap;">Tambah Saldo</button>
        </form>

        <form id="formModal" method="POST" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
    <input type="number" name="modal_awal" min="1000000" step="100000" value="<?= $eq_capital ?>" style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; font-weight:bold; min-width:200px;">
    <button type="submit" onclick="return confirm('Apakah Anda yakin? Mengubah modal akan MENGHAPUS SEMUA DATA simulasi (history & portofolio) untuk akun ini dan memulai dari awal.')" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight:bold; white-space:nowrap;">Update & Reset Data</button>
        <a href="robo_run_now.php" style="padding:10px 20px; background:#2563eb; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold; white-space:nowrap; display:inline-flex; align-items:center;">Jalankan Robot Sekarang</a>
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
function updateRobotLastRun(msg) {
    var el = document.getElementById('robotLastRun');
    if (!el) return;
    var now = new Date();
    var timeStr = now.toLocaleTimeString('id-ID');
    el.textContent = 'Terakhir robot dijalankan: ' + timeStr + (msg ? ' — ' + msg : '');
}

// Jalankan saat halaman dimuat (ambil status awal)
document.addEventListener('DOMContentLoaded', function() {
    fetch('robo_run_api.php')
        .then(r => r.json())
        .then(data => {
            updateRobotLastRun(data.msg);
        });
});

// Update setiap kali robot auto-run
setInterval(function() {
    fetch('robo_run_api.php')
        .then(r => r.json())
        .then(data => {
            updateRobotLastRun(data.msg);
        });
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
    <div class="tbl-header" style="flex-wrap:wrap; gap:10px;">
        <span>Posisi Menggantung (OPEN TRADES)</span>
        <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
            <button id="btnRefreshPrice" onclick="fetchLivePrices(false)" style="padding:6px 14px; background:#0ea5e9; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:13px; font-weight:bold;">&#x21bb; Refresh Harga Live <span id="liveCountdown" style="font-size:11px; opacity:0.8;"></span></button>
            <span id="liveRefreshStatus" style="font-size:11px; color:#64748b;">Auto-refresh setiap 30 detik</span>
        </div>
    </div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        OPEN berarti order <b>sudah terbeli</b> oleh robot (mode simulasi/paper trading), bukan sekadar watchlist.
    </div>
    <table>
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
                    <td style="font-size:12px;">
                        <?= isset($o['ai_signals']) ? htmlspecialchars($o['ai_signals']) : '-' ?>
                    </td>
                    <td style="font-size:12px;">
                        <?= isset($o['other_reasons']) ? htmlspecialchars($o['other_reasons']) : '-' ?>
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
        Default hanya menampilkan transaksi pada tanggal yang dipilih. Riwayat ini mencakup BUY, SELL, SEROK, dan aksi robot lain pada hari tersebut. Total data: <b><?= number_format($ledger_total_rows, 0, ',', '.') ?></b> baris.
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
                    <td style="font-size:12px;"><?= htmlspecialchars((string)$row['reason']) ?></td>
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

<div class="tbl-container" style="border-top: 4px solid #3b82f6;">
    <div class="tbl-header">Rekomendasi Menunggu (Belum Dibeli)</div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        Kandidat ini lolos sinyal teknikal, namun belum dieksekusi karena prioritas antrian, batas slot, saldo, atau aturan alokasi robot.
    </div>
    <table>
        <thead>
            <tr>
                <th>Ticker</th>
                <th>Harga Monitor</th>
                <th>Score</th>
                <th>Estimasi Lot</th>
                <th>Status</th>
                <th>Sinyal AI</th>
                <th>Alasan Lain</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pending_reco) == 0): ?>
            <tr><td colspan="7" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada kandidat menunggu saat ini.</td></tr>
            <?php else: ?>
                <?php foreach ($pending_reco as $c): ?>
                <tr>
                    <td><b><a href="chart.php?symbol=<?= urlencode($c['symbol']) ?>" target="_blank" style="color:#0d6efd; text-decoration:none;"><?= htmlspecialchars($c['symbol']) ?></a></b></td>
                    <td>Rp <?= number_format($c['price'], 0, ',', '.') ?></td>
                    <td>
                        <span class="badge bg-blue"><?= (int)$c['score'] ?>/99</span>
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
var _liveCountdownSec = 30;

function fetchLivePrices(silent) {
    const rows = document.querySelectorAll('[data-symbol]');
    if (!rows.length) return;

    const btn = document.getElementById('btnRefreshPrice');
    const statusEl = document.getElementById('liveRefreshStatus');
    if (!silent && btn) { btn.disabled = true; }
    if (statusEl) statusEl.textContent = 'Memperbarui harga...';

    const symbols = Array.from(rows).map(r => r.dataset.symbol);

    fetch('robo_live_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ symbols })
    })
    .then(r => r.json())
    .then(data => {
        let totalEquity = <?= $balance ?>;
        rows.forEach(row => {
            const sym = row.dataset.symbol;
            const buyPrice = parseFloat(row.dataset.buy);
            const lots = parseInt(row.dataset.lots);
            const live = data[sym];

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

        // Update equity card
        const equityEl = document.getElementById('cardTotalEquity');
        if (equityEl) equityEl.textContent = 'Rp ' + totalEquity.toLocaleString('id-ID', {maximumFractionDigits:0});

        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID');
        if (statusEl) statusEl.textContent = 'Terakhir diperbarui: ' + timeStr;
        if (btn) { btn.disabled = false; }

        // Reset countdown
        _startCountdown();
    })
    .catch(() => {
        if (btn) { btn.disabled = false; }
        if (statusEl) statusEl.textContent = 'Gagal memperbarui harga.';
        _startCountdown();
    });
}

function _startCountdown() {
    clearInterval(_liveCountdownTimer);
    clearInterval(_liveRefreshTimer);
    _liveCountdownSec = 30;
    const statusEl = document.getElementById('liveRefreshStatus');

    _liveCountdownTimer = setInterval(function() {
        _liveCountdownSec--;
        if (statusEl && statusEl.textContent.indexOf('Terakhir') !== -1) {
            // append countdown
        }
        const cdEl = document.getElementById('liveCountdown');
        if (cdEl) cdEl.textContent = _liveCountdownSec + 'd';
        if (_liveCountdownSec <= 0) {
            clearInterval(_liveCountdownTimer);
        }
    }, 1000);

    _liveRefreshTimer = setTimeout(function() {
        fetchLivePrices(true);
    }, 30000);
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