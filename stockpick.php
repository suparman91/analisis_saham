<?php
require_once __DIR__ . "/db.php";
$mysqli = db_connect();

// Auto add strategy & updated_at columns if missing (silent fail if already exists)
$mysqli->query("ALTER TABLE ai_stockpicks ADD COLUMN strategy VARCHAR(20) DEFAULT 'day'");
$mysqli->query("ALTER TABLE ai_stockpicks ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

// Auto delete picks that have failed and have stayed failed for >= 3 days
$mysqli->query("DELETE FROM ai_stockpicks WHERE status = 'FAILED' AND DATEDIFF(NOW(), updated_at) >= 3");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"]) && $_POST["action"] == "add") {
        $sym = strtoupper(trim($_POST["symbol"]));
        $entry = (float)$_POST["entry_price"];
        $tp = (float)$_POST["target_price"];
        $sl = (float)$_POST["stop_loss"];
        $notes = trim($_POST["notes"]);
        $strategy = isset($_POST["strategy"]) ? $_POST["strategy"] : 'day';
        
        $stmt = $mysqli->prepare("INSERT INTO ai_stockpicks (symbol, pick_date, entry_price, target_price, stop_loss, notes, strategy) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdddss", $sym, $entry, $tp, $sl, $notes, $strategy);
        $stmt->execute();
        
        header("Location: stockpick.php");
        exit;
    }
    
    if (isset($_POST["action"]) && $_POST["action"] == "delete") {
        $id = (int)$_POST["id"];
        $mysqli->query("DELETE FROM ai_stockpicks WHERE id = $id");
        header("Location: stockpick.php");
        exit;
    }
}

// Check real-time status updates from DB (from latest prices)
$currentPrices = [];
$resPrices = $mysqli->query("
    SELECT symbol, close, date 
    FROM prices 
    WHERE date = (SELECT MAX(date) FROM prices)
");
while ($pr = $resPrices->fetch_assoc()) {
    $currentPrices[$pr["symbol"]] = $pr["close"];
    $clean_sym = str_replace('.JK', '', $pr["symbol"]);
    $currentPrices[$clean_sym] = $pr["close"];
}

// ==== LIVE PRICE API INJECTION ====
// Secara otomatis fetch harga sangat live dari API untuk semua saham di tracker
$pending_symbols = [];
$resPending = $mysqli->query("SELECT DISTINCT symbol FROM ai_stockpicks");
while ($row = $resPending->fetch_assoc()) {
    $pending_symbols[] = $row['symbol'];
}

if (!empty($pending_symbols)) {
    $yf_symbols = array_map(function($s) { return $s . '.JK'; }, $pending_symbols);
    $url = 'https://query1.finance.yahoo.com/v7/finance/spark?symbols=' . implode(',', $yf_symbols) . '&range=1d&interval=1d';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
        'Accept: application/json'
    ]);
    
    $resp = curl_exec($ch);
    curl_close($ch);
    
    if ($resp) {
        $j = json_decode($resp, true);
        if (isset($j['spark']['result'])) {
            foreach ($j['spark']['result'] as $item) {
                if (isset($item['symbol']) && isset($item['response'][0]['meta']['regularMarketPrice'])) {
                    $sym_clean = str_replace('.JK', '', $item['symbol']);
                    $price = (float)$item['response'][0]['meta']['regularMarketPrice'];
                    
                    if ($price > 0) {
                        $currentPrices[$sym_clean] = $price; // Overwrite data DB usang dengan data detik ini!
                    }
                }
            }
        }
    }
}
// ====================================

// Fetch all stockpicks
$resPicks = $mysqli->query("SELECT a.*, s.notation FROM ai_stockpicks a LEFT JOIN stocks s ON a.symbol = s.symbol ORDER BY a.pick_date DESC");
$picks_day = [];
$picks_swing = [];
$notifications = [];

while ($r = $resPicks->fetch_assoc()) {
    $sym = $r["symbol"];
    // For evaluating PENDING picks
    if (isset($currentPrices[$sym]) && $r["status"] === "PENDING") {
        $cp = $currentPrices[$sym];
        $newStat = "PENDING";
        
        if ($cp >= $r["target_price"]) {
            $newStat = "HIT";
        } else if ($cp <= $r["stop_loss"] && $r["stop_loss"] > 0) {
            $newStat = "FAILED";
        }
        
        if ($newStat !== "PENDING") {
            $r["status"] = $newStat;
            $mysqli->query("UPDATE ai_stockpicks SET status='$newStat' WHERE id=".$r["id"]);
            $notifications[] = [
                'symbol' => $sym,
                'status' => $newStat,
                'price' => $cp
            ];
        }
    }

    $r["current_price"] = $currentPrices[$sym] ?? $r["entry_price"];

    // Evaluate profit/loss percentage
    $r["profit_pct"] = 0;
    if ($r["entry_price"] > 0) {
        $r["profit_pct"] = (($r["current_price"] - $r["entry_price"]) / $r["entry_price"]) * 100;
    }

    $strat = isset($r["strategy"]) ? $r["strategy"] : 'day';
    if ($strat === 'swing') {
        $picks_swing[] = $r;
    } else {
        $picks_day[] = $r;
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>AI Stockpick Tracker</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f8f9fa;}
    .container { max-width:1200px; margin:0 auto; }
    h1 { color:#333; margin-bottom: 5px;}
    .subtitle { color:#666; font-size:14px; margin-bottom:20px; }

    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }

    .panel { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); margin-bottom:20px; }
    .panel h3 { margin-top:0; border-bottom:2px solid #eee; padding-bottom:10px; color:#495057; font-size:16px;}

    table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:0; }
    th, td { padding:12px 8px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f1f3f5; font-weight:bold; color:#495057; }

    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    
    .btn { padding: 8px 15px; border: none; border-radius: 4px; color: #fff; cursor: pointer; font-weight: bold; }
    .btn-primary { background: #0d6efd; }
    .btn-primary:hover { background: #0b5ed7; }
    .btn-danger { background: #dc3545; padding: 5px 10px; font-size: 11px; }
    .btn-danger:hover { background: #bb2d3b; }

    .text-right { text-align:right; }
    .text-center { text-align:center; }
    .text-green { color:#198754; font-weight:bold; }
    .text-red { color:#dc3545; font-weight:bold; }

    .badge { display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; font-size:11px; font-weight:bold; text-transform:uppercase; }
    .badge.hit { background:#198754; }
    .badge.failed { background:#dc3545; }
    .badge.pending { background:#f59f00; }
  </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="container">
    <nav class="top-menu">
        <a href="index.php">📊 Dashboard Market</a>
        <a href="chart.php">📈 Chart & Analisis</a>
        <a href="scan_manual.php">🔍 Scanner BSJP/BPJP</a>
        <a href="stockpick.php" class="active">🎯 AI Stockpick Tracker</a>
        <a href="ara_hunter.php">🚀 ARA Hunter</a>
        <a href="telegram_setting.php" style="margin-left:auto; background:#475569;"><img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" width="14" style="vertical-align:middle;margin-right:5px;">Set Alert</a>
    </nav>
    
    <h1>🎯 AI Stockpick Tracker</h1>
    <div class="subtitle">Pantau performa hasil analisis saham AI (Target Profit vs Stop Loss)</div>

    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 20px;">
        
        <!-- Left Panel: Form Input & AI Scan -->
        <div class="sidebar">
            <div class="panel">
                <h3>➕ Tambah Stockpick AI</h3>
                <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Symbol Saham</label>
                    <input type="text" name="symbol" class="form-control" placeholder="Contoh: BBCA" required>
                </div>
                <div class="form-group">
                    <label>Entry Price (Harga Beli)</label>
                    <input type="number" step="any" name="entry_price" id="entry" class="form-control" required oninput="calcTarget()">
                </div>
                <div class="form-group">
                    <label>Target Price (+5%)</label>
                    <input type="number" step="any" name="target_price" id="target" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Stop Loss (-3%)</label>
                    <input type="number" step="any" name="stop_loss" id="sl" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Catatan Analisis (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Alasan masuk..."></textarea>
                </div>
                <div class="form-group">
                    <label>Strategi</label>
                    <select name="strategy" class="form-control" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
                        <option value="day">Day Trading (Sinyal Kuat & Cepat)</option>
                        <option value="swing">Swing Trading (Medium Term & Uptrend)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Simpan Pick</button>
            </form>
            <script>
                function calcTarget() {
                    const el = document.getElementById("entry");
                    if(el.value) {
                        const en = parseFloat(el.value);
                        document.getElementById("target").value = Math.round(en * 1.05);
                        document.getElementById("sl").value = Math.round(en * 0.97);
                    }
                }
            </script>
        </div>
        </div>

        <!-- Right Panel: Tabel Tracker & AI Scan -->
        <div class="main-content">

        <!-- Auto-Scan Panel -->
        <div class="panel">
            <h3>🤖 Auto-Scan AI Recommendations</h3>
            <p style="font-size:12px; color:#555;">Otomatis scan saham dengan liquiditas tinggi untuk mencari sinyal BUY kuat.</p>
            <div style="margin-bottom:15px;">
                <label style="font-size:12px; color:#555;">Strategi:</label>
                <select id="scanStrategy" style="width:100%; padding:8px; border-radius:5px; border:1px solid #ccc;">
                    <option value="day">Day Trading (Sinyal Kuat & Cepat)</option>
                    <option value="swing">Swing Trading (Medium Term & Uptrend)</option>
                </select>
            </div>
            <button id="btnAutoScan" onclick="runAutoScan()" style="width:100%; padding:10px; background-color:#28a745; color:white; border:none; border-radius:5px; margin-bottom:15px; cursor:pointer; font-weight:bold;">
                🔄 Mulai Scan AI Sekarang
            </button>

            <div id="aiScanResults">
                <p style="color:#888; text-align:center; font-size:13px; margin-top:10px;">Klik tombol untuk memulai AI analysis.</p>
            </div>
        </div>

        <div class="panel" id="tabelTrackerContainer">
            <h3>📈 Daftar Stockpick AI & Performa</h3>
            
            <h4 style="margin-top: 20px; color: #0d6efd; border-bottom: 2px solid #0d6efd; display: inline-block;">Day Trading (Cepat)</h4>
            <table>
                <tr>
                    <th>Waktu Pick</th>
                    <th>Symbol</th>
                    <th class="text-right">Entry</th>
                    <th class="text-right">Target (TP)</th>
                    <th class="text-right">Stop (SL)</th>
                    <th class="text-right">Harga Skrg</th>
                    <th class="text-right">P/L %</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <?php foreach($picks_day as $p): ?>
                <tr>
                    <td><?= date("d M Y H:i", strtotime($p["pick_date"])) ?></td>
                    <td><strong><a href="chart.php?symbol=<?= urlencode($p["symbol"]) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= htmlspecialchars($p["symbol"]) ?></a></strong><?php if(!empty($p['notation'])): ?> <span style="background:#ffc107; color:#000; font-size:9px; padding:2px 4px; border-radius:4px; margin-left:4px; vertical-align:super; font-weight:bold; display:inline-block;" title="Notasi Khusus: <?= htmlspecialchars($p['notation']) ?>"><?= htmlspecialchars($p['notation']) ?></span><?php endif; ?></td>
                    <td class="text-right"><?= number_format($p["entry_price"]) ?></td>
                    <td class="text-right text-green"><?= number_format($p["target_price"]) ?></td>
                    <td class="text-right text-red"><?= number_format($p["stop_loss"]) ?></td>
                    <td class="text-right">
                        <?= number_format($p["current_price"]) ?>
                    </td>
                    <td class="text-right <?= $p["profit_pct"] >= 0 ? "text-green" : "text-red" ?>">
                        <?= $p["profit_pct"] > 0 ? "+" : "" ?><?= number_format($p["profit_pct"], 2) ?>%
                    </td>
                    <td class="text-center">
                        <span class="badge <?= strtolower($p["status"]) ?>"><?= $p["status"] ?></span>
                    </td>
                    <td class="text-center">
                        <form method="POST" onsubmit="return confirm('Hapus pick ini?');" style="margin:0;padding:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p["id"] ?>">
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php if($p["notes"]): ?>
                <tr>
                    <td colspan="9" style="font-size:11px; color:#666; border-bottom: 2px solid #eee; padding-top:0;">
                        <em>Catatan: <?= htmlspecialchars($p["notes"]) ?></em>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if(empty($picks_day)): ?>
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px;">Belum ada stockpick Day Trading yang disimpan.</td>
                </tr>
                <?php endif; ?>
            </table>

            <h4 style="margin-top: 30px; color: #198754; border-bottom: 2px solid #198754; display: inline-block;">Swing Trading (Medium Term)</h4>
            <table>
                <tr>
                    <th>Waktu Pick</th>
                    <th>Symbol</th>
                    <th class="text-right">Entry</th>
                    <th class="text-right">Target (TP)</th>
                    <th class="text-right">Stop (SL)</th>
                    <th class="text-right">Harga Skrg</th>
                    <th class="text-right">P/L %</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <?php foreach($picks_swing as $p): ?>
                <tr>
                    <td><?= date("d M Y H:i", strtotime($p["pick_date"])) ?></td>
                    <td><strong><a href="chart.php?symbol=<?= urlencode($p["symbol"]) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= htmlspecialchars($p["symbol"]) ?></a></strong><?php if(!empty($p['notation'])): ?> <span style="background:#ffc107; color:#000; font-size:9px; padding:2px 4px; border-radius:4px; margin-left:4px; vertical-align:super; font-weight:bold; display:inline-block;" title="Notasi Khusus: <?= htmlspecialchars($p['notation']) ?>"><?= htmlspecialchars($p['notation']) ?></span><?php endif; ?></td>
                    <td class="text-right"><?= number_format($p["entry_price"]) ?></td>
                    <td class="text-right text-green"><?= number_format($p["target_price"]) ?></td>
                    <td class="text-right text-red"><?= number_format($p["stop_loss"]) ?></td>
                    <td class="text-right">
                        <?= number_format($p["current_price"]) ?>
                    </td>
                    <td class="text-right <?= $p["profit_pct"] >= 0 ? "text-green" : "text-red" ?>">
                        <?= $p["profit_pct"] > 0 ? "+" : "" ?><?= number_format($p["profit_pct"], 2) ?>%
                    </td>
                    <td class="text-center">
                        <span class="badge <?= strtolower($p["status"]) ?>"><?= $p["status"] ?></span>
                    </td>
                    <td class="text-center">
                        <form method="POST" onsubmit="return confirm('Hapus pick ini?');" style="margin:0;padding:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p["id"] ?>">
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php if($p["notes"]): ?>
                <tr>
                    <td colspan="9" style="font-size:11px; color:#666; border-bottom: 2px solid #eee; padding-top:0;">
                        <em>Catatan: <?= htmlspecialchars($p["notes"]) ?></em>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if(empty($picks_swing)): ?>
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px;">Belum ada stockpick Swing Trading yang disimpan.</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        </div>

    </div>
</div>

<script>
    const liquidSymbols = "BBCA.JK,BBRI.JK,BBNI.JK,BMRI.JK,ASII.JK,TLKM.JK,GOTO.JK,ADRO.JK,AMMN.JK,AMRT.JK,UNTR.JK,KLBF.JK,CPIN.JK,ICBP.JK,INDF.JK,BRPT.JK,PTBA.JK,ITMG.JK,PGAS.JK,MEDC.JK";

    function runAutoScan() {
        const btn = document.getElementById('btnAutoScan');
        const resDiv = document.getElementById('aiScanResults');

        // Start timer
        const startTime = performance.now();
        // Asumsi rata-rata scan seluruh DB saham butuh waktu ~30 detik
        const ETA_SECONDS = 30; 
        
        let timerInterval = setInterval(() => {
            const currentObj = document.getElementById('scanTimer');
            const progressObj = document.getElementById('scanProgress');
            const percObj = document.getElementById('scanPerc');
            
            if (currentObj && progressObj && percObj) {
                const elapsed = ((performance.now() - startTime) / 1000);
                let remaining = ETA_SECONDS - elapsed;
                
                // Jangan sampai negatif
                if (remaining < 0.1) remaining = 0.1;
                
                currentObj.innerHTML = remaining.toFixed(1) + " dtk";
                
                // Hitung persen progres (maks 99% sampai kelar beneran)
                let pct = Math.min(99, Math.floor((elapsed / ETA_SECONDS) * 100));
                percObj.innerHTML = pct + "%";
                progressObj.style.width = pct + "%";
            }
        }, 100);

        btn.disabled = true;
        btn.innerHTML = "⏳ Menyiapkan AI...";
        
        // Buat Template Loading Bar di HTML
        resDiv.innerHTML = `
            <div style="margin-top:20px; font-size:13px; text-align:center;">
                <p>AI sedang mencari saham potensial di seluruh BEI...</p>
                <div style="background:#e9ecef; border-radius:10px; width:100%; height:15px; overflow:hidden; margin:10px 0;">
                    <div id="scanProgress" style="background:#28a745; width:0%; height:100%; transition: width 0.1s linear;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; color:#666;">
                    <span id="scanPerc">0%</span>
                    <span>Sisa Waktu: <strong id="scanTimer">${ETA_SECONDS.toFixed(1)} dtk</strong></span>
                    <span>Total Estimasi: <strong>${ETA_SECONDS} dtk</strong></span>
                </div>
                <p id="scanStatusText" style="margin-top:10px; font-weight:bold; color:#007bff;">Memuat harga realtime terkini...</p>
            </div>
        `;

        // Sync liquid symbols prices via GoAPI first to ensure top caps are fresh
        let syncUrl = 'fetch_realtime.php?symbols=' + liquidSymbols;
        try {
            const gk = localStorage.getItem('goapi_key');
            if (gk) syncUrl += '&goapi_key=' + encodeURIComponent(gk);
        } catch(e){}

        fetch(syncUrl)
        .then(response => response.text())
        .then(data => {
            btn.innerHTML = "🧠 Evaluasi Indikator...";
            const stObj = document.getElementById('scanStatusText');
            if (stObj) stObj.innerHTML = "Harga real-time berhasil diambil.<br>Kini menghitung Moving Average & MACD...";

            // Fetch Auto-Scan Logic
            const stratObj = document.getElementById('scanStrategy');
            const stratVal = stratObj ? stratObj.value : 'day';
            return fetch('scan_ai.php?strategy=' + stratVal);
        })
        .then(response => response.text())
        .then(html => {
            clearInterval(timerInterval);
            const totalTime = ((performance.now() - startTime) / 1000).toFixed(2);
            
            btn.innerHTML = "🔄 Scan Ulang (" + totalTime + "s)";
            btn.disabled = false;
            
            resDiv.innerHTML = "<div style='margin-bottom:15px; text-align:center; font-size:12px; color:#555;'>✅ Scan selesai dalam <strong>" + totalTime + " detik</strong>.</div><div style='margin-top:15px;'>" + html + "</div>";
        })
        .catch(err => {
            clearInterval(timerInterval);
            btn.innerHTML = "⚠️ Gagal! Coba Lagi";
            btn.disabled = false;
            resDiv.innerHTML = "<p style='color:red;'>Terjadi kesalahan: " + err + "</p>";
        });
    }

    // Fungsi AJAX untuk form di scan_ai.php
    function addPickFromScan(e, formObj) {
        e.preventDefault(); // Mencegah reload halaman
        
        const btn = formObj.querySelector('button');
        const formData = new FormData(formObj);
        
        btn.disabled = true;
        btn.innerHTML = "⏳ Menambahkan...";

        fetch('stockpick.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Ambil bagian tabel dari respons full HTML stockpick.php dan update
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");
            const newTable = doc.getElementById('tabelTrackerContainer');
            
            if (newTable) {
                document.getElementById('tabelTrackerContainer').innerHTML = newTable.innerHTML;
            }

            // Ubah tombol jadi sukses
            btn.innerHTML = "✅ Berhasil Disimpan";
            btn.style.backgroundColor = "#6c757d"; // warna abu-abu disabled
            btn.style.cursor = "not-allowed";
            // Jangan enable tombol lagi biar ga di-spam klik
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = "⚠️ Gagal! Coba Lagi";
            console.error("Gagal menambahkan stockpick: ", err);
        });

        return false;
    }
</script>

<?php if (!empty($notifications)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Collect notifications
        const notifs = <?= json_encode($notifications) ?>;
        
        let i = 0;
        function showNextNotif() {
            if (i >= notifs.length) return;
            let n = notifs[i];
            
            let titleText = n.status === "HIT" ? "Target Tercapai!" : "Stop Loss Terkena!";
            let iconType = n.status === "HIT" ? "success" : "error";
            let colorType = n.status === "HIT" ? "#198754" : "#dc3545";

            Swal.fire({
                title: titleText,
                html: `<strong>${n.symbol}</strong> telah mencapai level <b>${n.price}</b><br>Status: <span style="color:${colorType}; font-weight:bold;">${n.status}</span>`,
                icon: iconType,
                confirmButtonText: 'Lanjut',
                confirmButtonColor: '#3b82f6',
                allowOutsideClick: false
            }).then(() => {
                i++;
                showNextNotif();
            });
        }
        
        showNextNotif();
    });
</script>
<?php endif; ?>

</body>
</html>

