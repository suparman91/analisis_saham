<?php
require_once 'auth.php'; // Panggil session
require_login();         // Wajib masuk

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
        header("Location: portfolio.php");
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
            header("Location: portfolio.php?saldo_action=ok&msg=" . urlencode("Saldo robot bertambah Rp " . number_format($topup, 0, ',', '.')));
            exit;
        }

        header("Location: portfolio.php?saldo_action=err&msg=" . urlencode("Update saldo gagal diproses."));
        exit;
    }

    header("Location: portfolio.php?saldo_action=err&msg=" . urlencode("Nominal top-up harus lebih dari 0."));
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
}
unset($o);

// Portfolio Closed (History) (User spesifik)
$closed = [];
$res_closed = $mysqli->query("SELECT * FROM robo_trades WHERE status='CLOSED' AND user_id = $user_id ORDER BY sell_date DESC LIMIT 50");
if ($res_closed) { while ($r = $res_closed->fetch_assoc()) $closed[] = $r; }

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
$floating_open_pl = 0;
$total_invest = 0;
$market_value_open = 0;

// Tambahkan nilai saham yang masih nyangkut di OPEN (Floating Value)
foreach ($open as $o) {
    $position_market_value = isset($o['market_value']) ? (float)$o['market_value'] : ((float)$o['buy_price'] * (int)$o['lots'] * 100);
    $total_equity += $position_market_value;
    $market_value_open += $position_market_value;
    $floating_open_pl += isset($o['floating_pl_rp']) ? (float)$o['floating_pl_rp'] : 0;
    $total_invest += ((float)$o['buy_price'] * (int)$o['lots'] * 100);
}
$overall_pl = $total_equity - $eq_capital;
$overall_pl_pct = $eq_capital > 0 ? ($overall_pl / $eq_capital) * 100 : 0;
$realized_pl_pct = $eq_capital > 0 ? ($total_pl / $eq_capital) * 100 : 0;
$floating_open_pl_pct = $eq_capital > 0 ? ($floating_open_pl / $eq_capital) * 100 : 0;

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

// Meta saham untuk filter sektor/tag pada rekomendasi
$stock_meta = [];
$sector_col = '';
$candidate_sector_cols = ['sector', 'industry', 'subsector', 'notation'];
$col_res = $mysqli->query("SHOW COLUMNS FROM stocks");
if ($col_res) {
    $stock_cols = [];
    while ($c = $col_res->fetch_assoc()) {
        $stock_cols[] = strtolower((string)$c['Field']);
    }
    foreach ($candidate_sector_cols as $candCol) {
        if (in_array($candCol, $stock_cols, true)) {
            $sector_col = $candCol;
            break;
        }
    }
}

if ($sector_col !== '') {
    $res_meta = $mysqli->query("SELECT symbol, name, `{$sector_col}` AS sector_tag FROM stocks");
} else {
    $res_meta = $mysqli->query("SELECT symbol, name, notation AS sector_tag FROM stocks");
}
if ($res_meta) {
    while ($m = $res_meta->fetch_assoc()) {
        if (!empty($m['symbol'])) {
            $stock_meta[$m['symbol']] = [
                'name' => (string)($m['name'] ?? ''),
                'sector' => (string)($m['sector_tag'] ?? '-'),
            ];
        }
    }
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

    $avgVol5 = array_sum(array_slice($vols, -5)) / 5;
    if (!($sma5[$i - 1] <= $sma20[$i - 1] && $sma5[$i] > $sma20[$i] && $vols[$i] > $avgVol5 * 1.5)) {
        continue;
    }

    $volRatio = $avgVol5 > 0 ? ($vols[$i] / $avgVol5) : 1;
    $smaSpreadPct = $sma20[$i] > 0 ? (($sma5[$i] - $sma20[$i]) / $sma20[$i]) * 100 : 0;
    $avgTurnover = $avgVol5 * $currPrice * 100;
    $liquidityLabel = 'LOW';
    if ($avgTurnover >= 10000000000) {
        $liquidityLabel = 'HIGH';
    } elseif ($avgTurnover >= 3000000000) {
        $liquidityLabel = 'MEDIUM';
    }
    $ret5 = 0;
    if ($i >= 5 && $closes[$i - 5] > 0) {
        $ret5 = (($currPrice - $closes[$i - 5]) / $closes[$i - 5]) * 100;
    }

    $meta = isset($stock_meta[$sym]) ? $stock_meta[$sym] : ['name' => '', 'sector' => '-'];

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
        'ret5' => round($ret5, 2),
        'name' => $meta['name'],
        'sector' => $meta['sector'] !== '' ? $meta['sector'] : '-',
        'liquidity_label' => $liquidityLabel,
        'avg_turnover' => $avgTurnover,
        'ai_reason' => 'Golden Cross + volume breakout',
        'ai_detail' => 'Vol x' . round($volRatio, 2) . ' | SMA spread ' . round($smaSpreadPct, 2) . '% | Return 5D ' . round($ret5, 2) . '% | Avg turnover Rp ' . number_format($avgTurnover, 0, ',', '.'),
    ];
}

usort($pending_reco, function ($a, $b) {
    return $b['score'] <=> $a['score'];
});

foreach ($pending_reco as $idx => &$cand) {
    $status = 'SIAP EKSEKUSI';
    $reason = 'Masuk prioritas eksekusi run berikutnya.';
    $estLots = 0;

    if ($open_count >= $MAX_OPEN_POSITIONS) {
        $status = 'MENUNGGU SLOT';
        $reason = 'Jumlah posisi OPEN sudah penuh (maks 10 emiten).';
    } elseif ($balance < $MIN_CASH_TO_BUY) {
        $status = 'SALDO KURANG';
        $reason = 'Saldo kas di bawah minimal pembelian Rp 1.000.000.';
    } else {
        $currentOpenCount = $open_count;
        $remainingSlots = max(1, $TARGET_POSITIONS - $currentOpenCount);
        $equalAlloc = (float)floor($balance / $remainingSlots);
        $isAllIn = ($cand['score'] >= $ALLIN_SCORE) && ($currentOpenCount === 0);
        $alloc = $isAllIn ? $balance : min($MAX_ALLOC, $equalAlloc);

        if ($alloc < $MIN_CASH_TO_BUY) {
            $status = 'SALDO KURANG';
            $reason = 'Alokasi per posisi kurang dari batas minimal beli.';
        } else {
            $estLots = (int)floor($alloc / ($cand['price'] * 100));
            if ($estLots <= 0) {
                $status = 'ALOKASI TIDAK CUKUP';
                $reason = 'Harga saham terlalu tinggi untuk alokasi saat ini.';
            } elseif ($idx >= $MAX_BUY_PER_RUN) {
                $status = 'MENUNGGU ANTRIAN';
                $reason = 'Batas eksekusi harian maksimal 2 saham per run.';
            } elseif ($isAllIn) {
                $status = 'SIAP EKSEKUSI (ALL-IN)';
                $reason = 'Skor sangat tinggi, robot boleh all-in pada posisi pertama.';
            }
        }
    }

    $cand['status'] = $status;
    $cand['reason'] = $reason;
    $cand['lots'] = $estLots;
}
unset($cand);

if (count($pending_reco) > 15) {
    $pending_reco = array_slice($pending_reco, 0, 15);
}

$reco_sector_options = [];
foreach ($pending_reco as $c) {
    $sec = trim((string)($c['sector'] ?? '-'));
    if ($sec !== '') {
        $reco_sector_options[$sec] = true;
    }
}
$reco_sector_options = array_keys($reco_sector_options);
sort($reco_sector_options);

// Ambil audit log terbaru untuk user ini
$audit_logs = [];
@$mysqli->query("CREATE TABLE IF NOT EXISTS robo_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    run_type VARCHAR(20) NOT NULL,
    action_summary VARCHAR(255) NOT NULL,
    decision_detail TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$res_audit = $mysqli->query("SELECT run_type, action_summary, decision_detail, created_at FROM robo_audit_logs WHERE user_id = {$user_id} ORDER BY id DESC LIMIT 20");
if ($res_audit) {
    while ($row = $res_audit->fetch_assoc()) {
        $audit_logs[] = $row;
    }
}

?>
<?php
$pageTitle = 'Robo-Trader Simulator | Analisis Saham';
?>
<?php include 'header.php'; ?>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f8fafc; }
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

<div class="dashboard-cards" id="roboDashboard">
    <div class="card-stat">
        <h3>Net Asset Value</h3>
        <div class="value" id="cardTotalEquity">Rp <?= number_format($total_equity, 0, ',', '.') ?></div>
        <div class="sub" id="cardNavSub">Total return: <b><?= round($overall_pl_pct, 2) ?>%</b> vs modal Rp <?= number_format($eq_capital, 0, ',', '.') ?></div>
    </div>

    <div class="card-stat">
        <h3>Market Value</h3>
        <div class="value text-blue" id="cardMarketValue">Rp <?= number_format($market_value_open, 0, ',', '.') ?></div>
        <div class="sub">Nilai pasar posisi OPEN saat ini</div>
    </div>

    <div class="card-stat">
        <h3>Cash Balance</h3>
        <div class="value text-blue" id="cardCashBalance">Rp <?= number_format($balance, 0, ',', '.') ?></div>
        <div class="sub">Saldo tunai siap dipakai beli</div>
    </div>

    <div class="card-stat">
        <h3>Cost Basis</h3>
        <div class="value text-blue" id="cardTotalInvest">Rp <?= number_format($total_invest, 0, ',', '.') ?></div>
        <div class="sub">Total modal beli posisi OPEN</div>
    </div>
    
    <div class="card-stat">
        <h3>Unrealized P / L</h3>
        <div class="value <?= $floating_open_pl >= 0 ? 'text-green' : 'text-red' ?>" id="cardFloatingPl">
            <?= $floating_open_pl > 0 ? '+' : '' ?>Rp <?= number_format($floating_open_pl, 0, ',', '.') ?>
        </div>
        <div class="sub" id="cardFloatingPlSub">Posisi OPEN: <b><?= round($floating_open_pl_pct, 2) ?>%</b> dari modal</div>
    </div>

    <div class="card-stat">
        <h3>Realized P / L</h3>
        <div class="value <?= $total_pl >= 0 ? 'text-green' : 'text-red' ?>">
            <?= $total_pl > 0 ? '+' : '' ?>Rp <?= number_format($total_pl, 0, ',', '.') ?>
        </div>
        <div class="sub">Dari posisi CLOSED: <b><?= round($realized_pl_pct, 2) ?>%</b> vs Modal Rp <?= number_format($eq_capital, 0, ',', '.') ?></div>
    </div>

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
                <th>Alasan Beli (Sinyal AI)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($open) == 0): ?>
            <tr><td colspan="7" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada saham yang sedang di-hold. Menunggu sinyal market.</td></tr>
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
                    <td><span class="badge bg-blue"><?= htmlspecialchars($o['buy_reason']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tbl-container" style="border-top: 4px solid #94a3b8;">
    <div class="tbl-header">Riwayat Transaksi (LEDGER HISTORY)</div>
    <table>
        <thead>
            <tr>
                <th>Ticker</th>
                <th>Tgl Beli</th>
                <th>Tgl Jual</th>
                <th>Avg. Buy</th>
                <th>Avg. Sell</th>
                <th>Status (Rules)</th>
                <th>P/L Realized (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($closed) == 0): ?>
            <tr><td colspan="7" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada history jual.</td></tr>
            <?php else: ?>
                <?php foreach ($closed as $o): ?>
                <tr>
                    <td><b><?= $o['symbol'] ?></b></td>
                    <td><?= $o['buy_date'] ?></td>
                    <td><?= $o['sell_date'] ?></td>
                    <td>Rp <?= number_format($o['buy_price'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($o['sell_price'], 0, ',', '.') ?></td>
                    <td><span class="badge <?= strpos($o['sell_reason'], 'Profit') !== false ? 'bg-green' : 'bg-red' ?>"><?= htmlspecialchars($o['sell_reason']) ?></span></td>
                    <td class="<?= $o['profit_loss_rp'] >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $o['profit_loss_rp'] > 0 ? '+' : '' ?>Rp <?= number_format($o['profit_loss_rp'], 0, ',', '.') ?> 
                        (<?= $o['profit_loss_pct'] ?>%)
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tbl-container" style="border-top: 4px solid #3b82f6;">
    <div class="tbl-header">Rekomendasi Menunggu (Belum Dibeli)</div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        Kandidat ini lolos sinyal teknikal, namun belum dieksekusi karena prioritas antrian, batas slot, saldo, atau aturan alokasi robot.
    </div>
    <div style="padding:10px 20px; border-bottom:1px solid #e2e8f0; background:#fff; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input id="recoKeyword" type="text" placeholder="Cari ticker/nama" style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; min-width:180px; font-size:12px;">
        <select id="recoSector" style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; min-width:170px;">
            <option value="">Semua Sektor/Tag</option>
            <?php foreach ($reco_sector_options as $sec): ?>
                <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="recoLiq" style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; min-width:150px;">
            <option value="">Semua Likuiditas</option>
            <option value="HIGH">Likuiditas Tinggi</option>
            <option value="MEDIUM">Likuiditas Sedang</option>
            <option value="LOW">Likuiditas Rendah</option>
        </select>
        <span id="recoCount" style="font-size:12px; color:#64748b;"></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Ticker</th>
                <th>Sektor/Tag</th>
                <th>Likuiditas</th>
                <th>Harga Monitor</th>
                <th>Score</th>
                <th>Alasan Sinyal AI</th>
                <th>Estimasi Lot</th>
                <th>Status</th>
                <th>Alasan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pending_reco) == 0): ?>
            <tr><td colspan="9" style="text-align:center; padding: 20px; color:#94a3b8;">Belum ada kandidat menunggu saat ini.</td></tr>
            <?php else: ?>
                <?php foreach ($pending_reco as $c): ?>
                <tr class="reco-row" data-sector="<?= htmlspecialchars($c['sector']) ?>" data-liq="<?= htmlspecialchars($c['liquidity_label']) ?>" data-keyword="<?= htmlspecialchars(strtolower($c['symbol'] . ' ' . ($c['name'] ?? ''))) ?>">
                    <td>
                        <b><a href="chart.php?symbol=<?= urlencode($c['symbol']) ?>" target="_blank" style="color:#0d6efd; text-decoration:none;"><?= htmlspecialchars($c['symbol']) ?></a></b>
                        <?php if (!empty($c['name'])): ?>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;"><?= htmlspecialchars($c['name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c['sector']) ?></td>
                    <td>
                        <span class="badge <?= $c['liquidity_label'] === 'HIGH' ? 'bg-green' : ($c['liquidity_label'] === 'MEDIUM' ? 'bg-orange' : 'bg-gray') ?>">
                            <?= htmlspecialchars($c['liquidity_label']) ?>
                        </span>
                    </td>
                    <td>Rp <?= number_format($c['price'], 0, ',', '.') ?></td>
                    <td>
                        <span class="badge bg-blue"><?= (int)$c['score'] ?>/99</span>
                    </td>
                    <td style="font-size:12px;" title="<?= htmlspecialchars($c['ai_detail']) ?>">
                        <?= htmlspecialchars($c['ai_reason']) ?>
                        <div style="font-size:11px; color:#64748b; margin-top:2px;"><?= htmlspecialchars($c['ai_detail']) ?></div>
                    </td>
                    <td><?= (int)$c['lots'] ?> lot</td>
                    <td>
                        <?php
                            $cls = 'bg-gray';
                            if (strpos($c['status'], 'SIAP') !== false) $cls = 'bg-green';
                            elseif (strpos($c['status'], 'SALDO') !== false || strpos($c['status'], 'TIDAK CUKUP') !== false) $cls = 'bg-red';
                            elseif (strpos($c['status'], 'ANTRIAN') !== false || strpos($c['status'], 'SLOT') !== false) $cls = 'bg-orange';
                        ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars($c['status']) ?></span>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($c['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tbl-container" style="border-top: 4px solid #0ea5e9;">
    <div class="tbl-header">Audit Keputusan Robot (Per Run)</div>
    <div style="padding:10px 20px; font-size:12px; color:#475569; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
        Menampilkan jejak keputusan robot: BUY/SELL/HOLD beserta ringkasan alasan pada setiap eksekusi run.
    </div>
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Sumber Run</th>
                <th>Ringkasan Aksi</th>
                <th>Detail Keputusan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($audit_logs) === 0): ?>
                <tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">Belum ada log audit run robot.</td></tr>
            <?php else: ?>
                <?php foreach ($audit_logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                        <td><span class="badge bg-gray"><?= htmlspecialchars(strtoupper($log['run_type'])) ?></span></td>
                        <td><?= htmlspecialchars($log['action_summary']) ?></td>
                        <td style="font-size:12px; color:#475569;"><?= htmlspecialchars((string)$log['decision_detail']) ?></td>
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
        let totalFloatingPl = 0;
        let marketValue = 0;
        rows.forEach(row => {
            const sym = row.dataset.symbol;
            const buyPrice = parseFloat(row.dataset.buy);
            const lots = parseInt(row.dataset.lots);
            const live = data[sym];

            if (!live || live <= 0) {
                const fallbackValue = buyPrice * lots * 100;
                totalEquity += fallbackValue;
                marketValue += fallbackValue;
                return;
            }

            const qty = lots * 100;
            const plRp = (live - buyPrice) * qty;
            const plPct = buyPrice > 0 ? (plRp / (buyPrice * qty)) * 100 : 0;
            const positionValue = live * qty;
            totalEquity += positionValue;
            marketValue += positionValue;
            totalFloatingPl += plRp;

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
        const navSubEl = document.getElementById('cardNavSub');
        const marketValueEl = document.getElementById('cardMarketValue');
        const cashBalanceEl = document.getElementById('cardCashBalance');
        if (equityEl) equityEl.textContent = 'Rp ' + totalEquity.toLocaleString('id-ID', {maximumFractionDigits:0});
        if (marketValueEl) marketValueEl.textContent = 'Rp ' + marketValue.toLocaleString('id-ID', {maximumFractionDigits:0});
        if (cashBalanceEl) cashBalanceEl.textContent = 'Rp ' + <?= (float)$balance ?>.toLocaleString('id-ID', {maximumFractionDigits:0});

        const floatingEl = document.getElementById('cardFloatingPl');
        const floatingSubEl = document.getElementById('cardFloatingPlSub');
        const investEl = document.getElementById('cardTotalInvest');
        const floatingPct = <?= $eq_capital > 0 ? '((totalFloatingPl / ' . (float)$eq_capital . ') * 100)' : '0' ?>;
        const overallPct = <?= $eq_capital > 0 ? '(((totalEquity - ' . (float)$eq_capital . ') / ' . (float)$eq_capital . ') * 100)' : '0' ?>;
        const totalInvest = <?= (float)$total_invest ?>;
        if (floatingEl) {
            const sign = totalFloatingPl >= 0 ? '+' : '-';
            floatingEl.textContent = sign + 'Rp ' + Math.abs(totalFloatingPl).toLocaleString('id-ID', {maximumFractionDigits:0});
            floatingEl.className = 'value ' + (totalFloatingPl >= 0 ? 'text-green' : 'text-red');
        }
        if (floatingSubEl) {
            floatingSubEl.innerHTML = 'Posisi OPEN: <b>' + floatingPct.toFixed(2) + '%</b> dari modal';
        }
        if (investEl) {
            investEl.textContent = 'Rp ' + totalInvest.toLocaleString('id-ID', {maximumFractionDigits:0});
        }
        if (navSubEl) {
            navSubEl.innerHTML = 'Total return: <b>' + overallPct.toFixed(2) + '%</b> vs modal Rp ' + <?= (float)$eq_capital ?>.toLocaleString('id-ID', {maximumFractionDigits:0});
        }

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

    const keyEl = document.getElementById('recoKeyword');
    const secEl = document.getElementById('recoSector');
    const liqEl = document.getElementById('recoLiq');
    const recoRows = document.querySelectorAll('.reco-row');
    const recoCount = document.getElementById('recoCount');

    function applyRecoFilter() {
        const keyword = keyEl ? keyEl.value.toLowerCase().trim() : '';
        const sector = secEl ? secEl.value : '';
        const liq = liqEl ? liqEl.value : '';
        let visible = 0;

        recoRows.forEach(function(row) {
            const rowKeyword = (row.dataset.keyword || '').toLowerCase();
            const okKeyword = keyword === '' || rowKeyword.indexOf(keyword) !== -1;
            const okSector = sector === '' || row.dataset.sector === sector;
            const okLiq = liq === '' || row.dataset.liq === liq;
            const show = okKeyword && okSector && okLiq;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (recoCount) {
            recoCount.textContent = visible + ' kandidat tampil';
        }
    }

    if (keyEl) keyEl.addEventListener('input', applyRecoFilter);
    if (secEl) secEl.addEventListener('change', applyRecoFilter);
    if (liqEl) liqEl.addEventListener('change', applyRecoFilter);
    if (recoRows.length > 0) applyRecoFilter();
});
</script>
<?php include 'footer.php'; ?>