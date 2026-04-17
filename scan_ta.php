<?php
require_once 'auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
$mysqli = db_connect();
require_subscription($mysqli);

$action = $_GET['action'] ?? '';

if ($action === 'scan') {
    header('Content-Type: application/json');
    $mysqli = db_connect();
    $type = $_GET['type'] ?? 'golden_cross'; 
    $min_vol = (int)($_GET['min_vol'] ?? 50000);
    $min_price = (int)($_GET['min_price'] ?? 50);

    $res = $mysqli->query("SELECT symbol, name, notation FROM stocks ORDER BY symbol");
    $all = [];
    while($r = $res->fetch_assoc()) $all[] = $r;
    
    $results = [];
    foreach($all as $s) {
        $sym = $s['symbol'];
        $prices = fetch_prices($mysqli, $sym, 100);
        if (count($prices) < 50) continue;
        
        $closes = array_column($prices, 'close');
        $highs = array_column($prices, 'high');
        $vols = array_column($prices, 'volume');
        $latestIdx = count($closes) - 1;
        
        if ($closes[$latestIdx] < $min_price) continue; 
        if ($vols[$latestIdx] < $min_vol) continue; 
        
        $match = false;
        $reason = '';
        
        if ($type === 'golden_cross') {
            $sma5 = sma($closes, 5);
            $sma20 = sma($closes, 20);
            if (isset($sma5[$latestIdx], $sma20[$latestIdx], $sma5[$latestIdx-1], $sma20[$latestIdx-1])) {
                if ($sma5[$latestIdx-1] <= $sma20[$latestIdx-1] && $sma5[$latestIdx] > $sma20[$latestIdx]) {
                    $match = true;
                    $reason = "SMA 5 (".round($sma5[$latestIdx],1).") memotong atas SMA 20 (".round($sma20[$latestIdx],1).")";
                }
            }
        } 
        elseif ($type === 'macd_cross') {
            $macdArr = macd($closes);
            $macdLine = $macdArr['macd'];
            $signalLine = $macdArr['signal'];
            if (isset($macdLine[$latestIdx], $signalLine[$latestIdx], $macdLine[$latestIdx-1], $signalLine[$latestIdx-1])) {
                if ($macdLine[$latestIdx-1] <= $signalLine[$latestIdx-1] && $macdLine[$latestIdx] > $signalLine[$latestIdx]) {
                    if ($macdLine[$latestIdx] < 0) {
                        $reason = "Golden Cross MACD di area negatif (Oversold Bounce).";
                        $match = true;
                    } else {
                        $reason = "Golden Cross MACD di area positif (Uptrend).";
                        $match = true;
                    }
                }
            }
        }
        elseif ($type === 'breakout_bb') {
            $bb = bollinger($closes, 20, 2.0);
            $upper = $bb['upper'];
            if (isset($upper[$latestIdx], $upper[$latestIdx-1])) {
                if ($closes[$latestIdx-1] <= $upper[$latestIdx-1] && $closes[$latestIdx] > $upper[$latestIdx]) {
                    $match = true;
                    $reason = "Breakout Upper Bollinger Band (".round($upper[$latestIdx],2).")";
                }
            }
        }
        elseif ($type === 'breakout_high') {
            $last20Highs = array_slice($highs, -21, 20); 
            if (count($last20Highs) > 0) {
                $top20 = max($last20Highs);
                if ($closes[$latestIdx] > $top20) {
                    $match = true;
                    $reason = "Breakout batas High 20 hari terakhir (".$top20.")";
                }
            }
        }
        
        if ($match) {
            $prevClose = $closes[$latestIdx-1];
            $chg = $closes[$latestIdx] - $prevClose;
            $pct = $prevClose > 0 ? ($chg / $prevClose)*100 : 0;
            
            $results[] = [
                'symbol' => $sym,
                'name' => $s['name'],
                'notation' => $s['notation'],
                'close' => $closes[$latestIdx],
                'change_pct' => round($pct, 2),
                'volume' => $vols[$latestIdx],
                'reason' => $reason
            ];
        }
    }

    usort($results, function($a, $b) {
        return $b['change_pct'] <=> $a['change_pct'];
    });

    echo json_encode(['status'=>'success', 'data'=>$results, 'count'=>count($results)]);
    exit;
}
?>
<?php
$pageTitle = 'Scanner Tekninal & Momentum';
?>
<?php include 'header.php'; ?>
    <style>
        .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); flex-wrap: wrap; }
        .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; white-space: nowrap; }
        .top-menu a:hover { background: #1e293b; color: #fff; }
        .top-menu a.active { background: #3b82f6; color: #fff; }
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f8fafc; }
        .card { background: #fff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .filter-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; align-items:flex-end; }
        .filter-item { display: flex; flex-direction: column; }
        .filter-item label { font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 5px; }
        select, input[type="number"] { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; min-width:180px; }
        button.btn-scan { background: #0d6efd; color:#fff; border:none; padding:10px 20px; border-radius:4px; font-weight:bold; cursor:pointer; min-width: 120px; height: 36px; }
        button.btn-scan:hover { background: #0b5ed7; }
        button:disabled { background: #94a3b8; cursor: not-allowed; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; color: #334155; font-size: 14px; }
        td { font-size: 14px; color: #1e293b; }
        tr:hover { background: #f8fafc; }
        .badge { display:inline-block; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#fff; }
        .badge.up { background: #198754; }
        .badge.down { background: #dc2626; }
        .badge.neutral { background: #6c757d; }
        .notation { display:inline-block; background:#dc2625; color:#fff; padding:2px 5px; border-radius:3px; font-size:10px; margin-left:5px; }
    </style>

    <div class="card">
        <h2 style="margin-top:0; color:#0f172a;">&#x26A1; Scanner Momentum & Teknikal</h2>
        <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Mencari saham dengan sinyal Technical Analysis (Golden Cross, Breakout) berdasarkan data End-Of-Day terbaru.</p>
        
        <div class="filter-row">
            <div class="filter-item">
                <label>Strategi Scanner</label>
                <select id="scanType">
                    <option value="golden_cross">SMA 5 Cross SMA 20 (Golden Cross)</option>
                    <option value="macd_cross">MACD Golden Cross</option>
                    <option value="breakout_bb">Breakout Bollinger Upper</option>
                    <option value="breakout_high">Breakout High 20 Hari</option>
                </select>
            </div>
            <div class="filter-item">
                <label>Min. Harga (Rp)</label>
                <input type="number" id="minPrice" value="50" step="10">
            </div>
            <div class="filter-item">
                <label>Min. Volume</label>
                <input type="number" id="minVol" value="50000" step="10000">
            </div>
            <div class="filter-item">
                <button id="btnStart" class="btn-scan">Mulai Scan</button>
            </div>
        </div>
        <div id="status" style="font-size:14px; font-weight:bold; color:#0d6efd;"></div>
    </div>

    <div id="resultContainer" style="display:none;">
        <h3 id="resultTitle" style="color:#1e293b;">Hasil Scan (<span id="resultCount">0</span> saham)</h3>
        <table>
            <thead>
                <tr>
                    <th>Saham</th>
                    <th>Harga Terakhir</th>
                    <th>Perubahan</th>
                    <th>Volume</th>
                    <th>Alasan / Keterangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="resultBody">
            </tbody>
        </table>
    </div>

    <script>
    document.getElementById('btnStart').addEventListener('click', async () => {
        const btn = document.getElementById('btnStart');
        const status = document.getElementById('status');
        const resultContainer = document.getElementById('resultContainer');
        const resultBody = document.getElementById('resultBody');
        const resultCount = document.getElementById('resultCount');
        const resultTitle = document.getElementById('resultTitle');
        
        const type = document.getElementById('scanType').value;
        const typeText = document.getElementById('scanType').options[document.getElementById('scanType').selectedIndex].text;
        const minPrice = document.getElementById('minPrice').value;
        const minVol = document.getElementById('minVol').value;

        btn.disabled = true;
        btn.innerText = 'Scanning...';
        status.innerText = 'Tunggu sebentar, sedang men-scan ratusan saham...';
        resultContainer.style.display = 'none';
        resultBody.innerHTML = '';

        try {
            const url = `scan_ta.php?action=scan&type=${type}&min_price=${minPrice}&min_vol=${minVol}`;
            const rnd = '&_=' + Date.now();
            const response = await fetch(url + rnd);
            const res = await response.json();

            if (res.status === 'success') {
                status.innerText = `Scan selesai! Ditemukan ${res.count} saham potensial.`;
                resultCount.innerText = res.count;
                
                if (res.count > 0) {
                    let html = '';
                    res.data.forEach(item => {
                        let pctClass = item.change_pct > 0 ? 'up' : (item.change_pct < 0 ? 'down' : 'neutral');
                        let pctSign = item.change_pct > 0 ? '+' : '';
                        let notif = item.notation ? `<span class="notation" title="Notasi Khusus">${item.notation}</span>` : '';
                        
                        html += `
                        <tr>
                            <td><strong>${item.symbol}</strong>${notif}<br><small style="color:#64748b">${item.name}</small></td>
                            <td>${item.close.toLocaleString('id-ID')}</td>
                            <td><span class="badge ${pctClass}">${pctSign}${item.change_pct}%</span></td>
                            <td>${item.volume.toLocaleString('id-ID')}</td>
                            <td>${item.reason}</td>
                            <td><a href="chart.php?symbol=${item.symbol}" target="_blank" style="color:#0d6efd;text-decoration:none;font-weight:bold;font-size:13px;">Buka Chart &#x1F4C8;</a></td>
                        </tr>
                        `;
                    });
                    resultBody.innerHTML = html;
                    resultContainer.style.display = 'block';
                } else {
                    status.innerText = `Scan selesai. Tidak ada saham yang memenuhi kriteria ${typeText}.`;
                }
            } else {
                status.innerText = 'Gagal melakukan scan.';
                status.style.color = '#dc3545';
            }
        } catch (e) {
            console.error(e);
            status.innerText = 'Terjadi kesalahan sistem / Timeout: ' + e.message;
            status.style.color = '#dc3545';
        } finally {
            btn.disabled = false;
            btn.innerText = 'Mulai Scan';
        }
    });
    </script>
<?php include 'footer.php'; ?>
