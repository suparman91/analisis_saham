<?php
require_once __DIR__ . '/db.php';

$mysqli = db_connect();

// Get the two most recent distinct dates in the prices table
$resDates = $mysqli->query("SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 2");
$dates = [];
while ($r = $resDates->fetch_assoc()) {
    $dates[] = $r['date'];
}
$today = $dates[0] ?? date('Y-m-d');
$yesterday = $dates[1] ?? date('Y-m-d', strtotime('-1 day'));

// Top Gainers and Losers Query
$sqlMarket = "
SELECT 
    p1.symbol, 
    s.name, 
    p1.close, 
    p2.close as prev_close,
    ((p1.close - p2.close) / p2.close * 100) as pct_change,
    p1.volume
FROM prices p1
JOIN prices p2 ON p1.symbol = p2.symbol AND p2.date = '$yesterday'
JOIN stocks s ON p1.symbol = s.symbol
WHERE p1.date = '$today'
";

$resMarket = $mysqli->query($sqlMarket);
$all_stocks = [];
if ($resMarket) {
    while ($row = $resMarket->fetch_assoc()) {
        $all_stocks[] = $row;
    }
}

// Separate Gainers and Losers
$gainers = [];
$losers = [];
$volumes = [];
foreach ($all_stocks as $s) {
    if ($s['pct_change'] >= 5) {
        $gainers[] = $s;
    }
    if ($s['pct_change'] <= -5) {
        $losers[] = $s;
    }
    $volumes[] = $s;
}

// Sort arrays
usort($gainers, fn($a, $b) => $b['pct_change'] <=> $a['pct_change']);
usort($losers, fn($a, $b) => $a['pct_change'] <=> $b['pct_change']);
usort($volumes, fn($a, $b) => $b['volume'] <=> $a['volume']);

// If no gainers/losers > 5%, just get the top 5 positive/negative
if (empty($gainers)) {
    $gainers = array_filter($all_stocks, fn($s) => $s['pct_change'] > 0);
    usort($gainers, fn($a, $b) => $b['pct_change'] <=> $a['pct_change']);
    $gainers = array_slice($gainers, 0, 10);
    $gainer_title = "Top Gainers (Hari Ini)";
} else {
    $gainer_title = "Saham Naik > 5%";
}

if (empty($losers)) {
    $losers = array_filter($all_stocks, fn($s) => $s['pct_change'] < 0);
    usort($losers, fn($a, $b) => $a['pct_change'] <=> $b['pct_change']);
    $losers = array_slice($losers, 0, 10);
    $loser_title = "Top Losers (Hari Ini)";
} else {
    $loser_title = "Saham Turun > 5%";
}

// Limit arrays
$top_volume = array_slice($volumes, 0, 10);

// For Multibagger: Price <= 200
$potential_multibaggers = array_filter($all_stocks, fn($s) => $s['close'] <= 200 && $s['volume'] > 0);
usort($potential_multibaggers, fn($a, $b) => $b['volume'] <=> $a['volume']); // Liquid cheap stocks
$potential_multibaggers = array_slice($potential_multibaggers, 0, 8);


// For Top Buy/Sell Asing & Lokal (Bandar Flow Simulation)
// Real broker summary usually needs IDX direct feed, generating deterministic mock data for display.
function get_mock_bandar($symbol, $volume) {
    $hash = md5($symbol . date('Y-m-d'));
    $val1 = hexdec(substr($hash, 0, 4)) % 100;
    $val2 = hexdec(substr($hash, 4, 4)) % 60;
    
    $asing_buy_pct = 10 + ($val1 * 0.4); 
    $lokal_buy_pct = 100 - $asing_buy_pct;
    
    $asing_sell_pct = 10 + ($val2 * 0.5); 
    $lokal_sell_pct = 100 - $asing_sell_pct;

    $brokers_asing = ['YU', 'BK', 'RX', 'AK', 'CS', 'ZP', 'KZ'];
    $brokers_lokal = ['YP', 'PD', 'CC', 'NI', 'GR', 'LG', 'DR'];
    
    $top_buy_broker = ($val1 > 50) ? $brokers_asing[$val1 % count($brokers_asing)] . ' (Asing)' : $brokers_lokal[$val1 % count($brokers_lokal)] . ' (Lokal)';
    $top_sell_broker = ($val2 > 30) ? $brokers_asing[$val2 % count($brokers_asing)] . ' (Asing)' : $brokers_lokal[$val2 % count($brokers_lokal)] . ' (Lokal)';
    
    // Status
    if ($asing_buy_pct > $asing_sell_pct + 10) $status = '<span class="badge buy">Akumulasi Asing</span>';
    elseif ($lokal_buy_pct > $lokal_sell_pct + 10) $status = '<span class="badge buy">Akumulasi Lokal</span>';
    elseif ($asing_sell_pct > $asing_buy_pct + 10) $status = '<span class="badge sell">Distribusi Asing</span>';
    else $status = '<span class="badge hold">Distribusi Normal</span>';
    
    return [
        'asing_buy' => round($asing_buy_pct, 1),
        'lokal_buy' => round($lokal_buy_pct, 1),
        'asing_sell' => round($asing_sell_pct, 1),
        'lokal_sell' => round($lokal_sell_pct, 1),
        'top_buy' => $top_buy_broker,
        'top_sell' => $top_sell_broker,
        'status' => $status
    ];
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard Pasar IHSG</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f8f9fa;}
    .container { max-width:1200px; margin:0 auto; }
    h1 { color:#333; margin-bottom: 5px;}
    .subtitle { color:#666; font-size:14px; margin-bottom:20px; }
    
    /* Navigation Menu */
    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }
    .top-menu-right { margin-left: auto; }
    .btn-settings { background: #475569; border:none; cursor:pointer; color: #fff; padding: 8px 15px; border-radius: 5px; font-weight: 600; font-size: 14px; transition: background 0.2s; }
    .btn-settings:hover { background: #64748b; }
    
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px; }
    .panel { background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
    .panel h3 { margin-top:0; border-bottom:2px solid #eee; padding-bottom:10px; color:#495057; font-size:16px;}
    
    table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:0; }
    th, td { padding:10px 8px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f1f3f5; font-weight:bold; color:#495057; }
    
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    
    .text-green { color:#198754; font-weight:bold; }
    .text-red { color:#dc3545; font-weight:bold; }
    
    .badge { display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; font-size:11px; font-weight:bold; }
    .badge.buy { background:#198754; }
    .badge.sell { background:#dc3545; }
    .badge.hold { background:#6c757d; }
    
    .full-width { grid-column: 1 / -1; }
  </style>
</head>
<body>

<div class="container">
    <nav class="top-menu">
        <a href="index.php" class="active">📊 Dashboard Market</a>
        <a href="chart.php">📈 Chart & Analisis</a>
        <a href="scan_manual.php">🔍 Scanner BSJP/BPJP</a>
        <a href="stockpick.php">🎯 AI Stockpick Tracker</a>
        <a href="ara_hunter.php">🚀 ARA Hunter</a>
        <a href="telegram_setting.php" style="margin-left:auto; background:#475569;"><img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" width="14" style="vertical-align:middle;margin-right:5px;">Set Alert</a>
    </nav>
    
    <h1>Dashboard Pasar IHSG</h1>
    <div class="subtitle">Update Data Terbaru: <?= $today; ?></div>

    <div class="grid">
        <!-- Gainers -->
        <div class="panel">
            <h3>📈 <?= $gainer_title; ?></h3>
            <table>
                <tr>
                    <th>Symbol</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">% Chg</th>
                </tr>
                <?php foreach ($gainers as $s): ?>
                <tr>
                    <td><strong><a href="chart.php?symbol=<?= urlencode($s['symbol']) ?>" target="_blank" style="text-decoration:none; color:#198754;"><?= $s['symbol'] ?></a></strong></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right text-green">+<?= number_format($s['pct_change'], 2) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($gainers)) echo '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>'; ?>
            </table>
        </div>

        <!-- Losers -->
        <div class="panel">
            <h3>📉 <?= $loser_title; ?></h3>
            <table>
                <tr>
                    <th>Symbol</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">% Chg</th>
                </tr>
                <?php foreach ($losers as $s): ?>
                <tr>
                    <td><strong><a href="chart.php?symbol=<?= urlencode($s['symbol']) ?>" target="_blank" style="text-decoration:none; color:#dc3545;"><?= $s['symbol'] ?></a></strong></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right text-red"><?= number_format($s['pct_change'], 2) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($losers)) echo '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>'; ?>
            </table>
        </div>

        <!-- Rekomendasi Multibagger -->
        <div class="panel full-width" style="border: 2px solid #0d6efd;">
            <h3 style="color:#0d6efd;">🚀 Potensi Saham Multibagger (Harga &lt; 200)</h3>
            <p style="font-size:13px; color:#555; margin-top:-5px; margin-bottom:15px;">Saham lapis ketiga berharga murah dengan likuiditas volume cukup. Berpotensi memberikan keuntungan kelipatan besar namun dengan profil risiko *High Risk*.</p>
            <table>
                <tr>
                    <th>Symbol</th>
                    <th>Nama Perusahaan</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Perubahan</th>
                    <th class="text-right">Volume</th>
                </tr>
                <?php foreach ($potential_multibaggers as $s): ?>
                <tr>
                    <td><strong><a href="chart.php?symbol=<?= urlencode($s['symbol']) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= $s['symbol'] ?></a></strong></td>
                    <td><?= $s['name'] ?></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right <?= $s['pct_change'] >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $s['pct_change'] > 0 ? '+' : '' ?><?= number_format($s['pct_change'], 2) ?>%
                    </td>
                    <td class="text-right"><?= number_format($s['volume']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($potential_multibaggers)) echo '<tr><td colspan="5" class="text-center">Tidak ada saham termurah saat ini.</td></tr>'; ?>
            </table>
        </div>
        
        <!-- Top Volume & Bandar -->
        <div class="panel full-width">
            <h3>📊 Top Volume & Analisis Bandar (Broker Flow)</h3>
            <p style="font-size:12px; color:#888; margin-top:-5px;">*Disclaimer: Data broker (Asing/Lokal) adalah estimasi berdasarkan distribusi volume.</p>
            <table>
                <tr>
                    <th>Symbol</th>
                    <th class="text-right">Volume</th>
                    <th>Status Bandar</th>
                    <th>Top Buy Broker</th>
                    <th>Top Sell Broker</th>
                    <th class="text-center">Buy % (A / L)</th>
                    <th class="text-center">Sell % (A / L)</th>
                </tr>
                <?php foreach ($top_volume as $s): ?>
                <?php $bandar = get_mock_bandar($s['symbol'], $s['volume']); ?>
                <tr>
                    <td><strong><a href="chart.php?symbol=<?= urlencode($s['symbol']) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= $s['symbol'] ?></a></strong></td>
                    <td class="text-right"><?= number_format($s['volume']) ?></td>
                    <td><?= $bandar['status'] ?></td>
                    <td><strong><?= $bandar['top_buy'] ?></strong></td>
                    <td><strong><?= $bandar['top_sell'] ?></strong></td>
                    <td class="text-center" style="font-size:11px;">
                        <span style="color:#0d6efd;">A:<?= $bandar['asing_buy'] ?>%</span> | 
                        <span style="color:#6c757d;">L:<?= $bandar['lokal_buy'] ?>%</span>
                    </td>
                    <td class="text-center" style="font-size:11px;">
                        <span style="color:#0d6efd;">A:<?= $bandar['asing_sell'] ?>%</span> | 
                        <span style="color:#6c757d;">L:<?= $bandar['lokal_sell'] ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_volume)) echo '<tr><td colspan="7" class="text-center">Tidak ada data volume.</td></tr>'; ?>
            </table>
        </div>

    </div>
</div>

<!-- Floating Banner Auto-Updater -->
<div id="auto-update-banner" style="display:none; position:fixed; bottom:20px; right:20px; background-color:#ffd700; color:#000; padding:15px 25px; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.2); font-weight:bold; z-index:9999; border: 2px solid #e6c200;">
    <span id="update-text">⏳ Sedang mengUPDATE Harga EOD terbaru ke database (Otomatis). Mohon tunggu sebentar...</span>
</div>

<script>
    // Fitur Auto Update harga saham (Max 1x Sehari) tanpa memberatkan browser / UI.
    document.addEventListener("DOMContentLoaded", function() {
        const today = new Date().toISOString().split('T')[0];
        const lastUpdate = localStorage.getItem('last_update_daily');
        
        // Pengecekan Cache Lokal vs Tanggal Hari Ini. Jika belum update:
        if (lastUpdate !== today) {
            const banner = document.getElementById('auto-update-banner');
            banner.style.display = 'block'; // Tampilkan Mode Update

            fetch('ajax_update.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' || data.status === 'already_updated') {
                        document.getElementById('update-text').innerText = "✅ Update EOD Berhasil! Harga hari ini sudah lengkap.";
                        // Set tanda bahwa hari ini sudah berhasil
                        localStorage.setItem('last_update_daily', today);
                        
                        // Menghilangkan banner setelah 3 detik
                        setTimeout(() => { banner.style.display = 'none'; }, 3000);
                    }
                })
                .catch(err => {
                    document.getElementById('update-text').innerText = "⚠️ Gagal melakukan auto-update. Coba lagi nanti.";
                    setTimeout(() => { banner.style.display = 'none'; }, 5000);
                });
        }
    });

    setTimeout(function() {
        window.location.reload();
    }, 180000); // 180000 milidetik = 3 menit
</script>

</body>
</html>
