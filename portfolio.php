<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
$mysqli = db_connect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $symbol = strtoupper(trim($_POST['symbol']));
        $buy_price = (float)$_POST['buy_price'];
        $target_price = (float)$_POST['target_price'];
        
        if (!empty($symbol) && $buy_price > 0 && $target_price > 0) {
            $stmt = $mysqli->prepare("INSERT INTO portfolio (symbol, buy_price, target_price) VALUES (?, ?, ?)");
            $stmt->bind_param("sdd", $symbol, $buy_price, $target_price);
            $stmt->execute();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $mysqli->query("DELETE FROM portfolio WHERE id = $id");
    }
    
    // Redirect to clear POSt
    header("Location: portfolio.php");
    exit;
}

// Fetch Portfolio
$portfolio = [];
$res = $mysqli->query("SELECT * FROM portfolio ORDER BY added_on DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sym = $row['symbol'];
        
        // Ambil harga terkini (Paling update di database)
        // Kita juga bisa memanggil script API jika diperlukan, tp asumsi DB diupdate oleh fetch_realtime.php
        $latest = $mysqli->query("SELECT close, date FROM prices WHERE symbol = '{$sym}.JK' OR symbol = '{$sym}' ORDER BY date DESC LIMIT 1")->fetch_assoc();
        
        $current_price = $latest ? (float)$latest['close'] : 0;
        $buy_price = (float)$row['buy_price'];
        $target_price = (float)$row['target_price'];
        
        $profit_val = $current_price - $buy_price;
        $profit_pct = $buy_price > 0 ? ($profit_val / $buy_price) * 100 : 0;
        
        $target_val = $target_price - $current_price;
        $target_pct = $current_price > 0 ? ($target_val / $current_price) * 100 : 0;

        // Auto-pilot Recommendation Logic
        $analysis = analyze_symbol($mysqli, $sym . '.JK');
        $signal = $analysis['signal'] ?? 'HOLD';
        $tech_detail = $analysis['signal_details'] ?? '';
        
        $action = 'HOLD';
        $action_color = '#64748b'; // gray
        $reason = 'Pergerakan wajar, pantau support terdekat.';

        if ($current_price >= $target_price) {
            $action = 'TAKE PROFIT (JUAL)';
            $action_color = '#10b981'; // green
            $reason = 'Target harga sudah tercapai.';
        } elseif ($profit_pct <= -5) {
            if (strpos($signal, 'SELL') !== false) {
                $action = 'CUT LOSS (JUAL)';
                $action_color = '#ef4444'; // red
                $reason = 'Minus melebihi 5% dan sinyal teknikal memburuk (Downtrend).';
            } elseif (strpos($signal, 'BUY') !== false) {
                $action = 'SEROK BAWAH (BUY MORE)';
                $action_color = '#3b82f6'; // blue
                $reason = 'Sedang koreksi tapi fundamental/teknikal masih solid. Cocok untuk Average Down.';
            } else {
                $action = 'HOLD & PANTAU';
                $action_color = '#f59f00'; // orange
                $reason = 'Minus 5%, indikator netral. Waspada patah tren.';
            }
        } elseif ($profit_pct > 0 && $profit_pct < ($target_price-$buy_price)/$buy_price*100) {
            if (strpos($signal, 'SELL') !== false) {
                $action = 'TAKE PROFIT SEBAGIAN';
                $action_color = '#d97706'; // warning
                $reason = 'Masih profit tapi muncul tekanan jual. Amankan cuan sebagian.';
            } else {
                $action = 'HOLD (LET cuan RUN)';
                $action_color = '#059669'; 
                $reason = 'Masih uptrend menuju target harga.';
            }
        } elseif (strpos($signal, 'STRONG BUY') !== false) {
             $action = 'TAMBAH MUATAN';
             $action_color = '#0284c7';
             $reason = 'Sinyal Strong Buy terdeteksi, momentum sangat kuat.';
        }

        $row['current_price'] = $current_price;
        $row['profit_val'] = $profit_val;
        $row['profit_pct'] = $profit_pct;
        $row['action'] = $action;
        $row['action_color'] = $action_color;
        $row['reason'] = $reason;
        $row['signal'] = $signal;
        $row['tech_detail'] = $tech_detail;
        
        $portfolio[] = $row;
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Autopilot & Tracker Portofolio - Sistem Analisis Saham</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f8f9fa;}
    .container { max-width:1200px; margin:0 auto; }
    h1 { color:#333; margin-bottom: 5px;}
    .subtitle { color:#666; font-size:14px; margin-bottom:20px; }

    /* Top Menu Sama Seperti yang Lain */
    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); flex-wrap: wrap; }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; white-space: nowrap;}
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }

    .grid-container { display: grid; grid-template-columns: 1fr 3fr; gap: 20px; }
    
    .panel { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); margin-bottom:20px; }
    .panel h3 { margin-top:0; border-bottom:2px solid #eee; padding-bottom:10px; color:#495057; font-size:16px;}
    
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
    .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
    .btn { background: #3b82f6; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; display: inline-block; font-weight: bold; width: 100%; }
    .btn:hover { background: #2563eb; }
    .btn-delete { background: #ef4444; color: #fff; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;}
    .btn-delete:hover { background: #dc2626;}

    table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:0; }
    th, td { padding:12px 8px; border-bottom:1px solid #eee; vertical-align:top;}
    th { background:#f1f3f5; font-weight:bold; color:#495057; text-align: left; }
    
    .pos { color: #10b981; font-weight: bold; }
    .neg { color: #ef4444; font-weight: bold; }
    
    .action-badge { display:inline-block; padding:5px 10px; border-radius:4px; color:#fff; font-size:12px; font-weight:bold; margin-bottom: 5px;}
  </style>
</head>
<body>
  <div class="container">
      <nav class="top-menu">
        <a href="index.php">📊 Dashboard Market</a>
        <a href="ihsg.php">&#x1F4C8; Chart IHSG</a>
          <a href="chart.php">📈 Chart & Analisis</a>
        <a href="scan_manual.php">🔍 Scanner BSJP/BPJP</a>
        <a href="stockpick.php">🎯 AI Stockpick Tracker</a>
        <a href="ara_hunter.php">🚀 ARA Hunter</a>
        <a href="arb_hunter.php">📉 ARB Hunter</a>
        <a href="portfolio.php" class="active">💼 Autopilot Portofolio</a>
        <a href="telegram_setting.php" style="margin-left:auto; background:#475569;"><img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" width="14" style="vertical-align:middle;margin-right:5px;">Set Alert</a>
      </nav>

      <h1>💼 Autopilot Tindakan & Portofolio</h1>
      <p class="subtitle">Pantau saham yang sedang Anda pegang (hold) dan dapatkan rekomendasi AI apakah waktunya Take Profit, Cut Loss, Hold, atau Serok Bawah (Average Down).</p>

      <div class="grid-container">
          <!-- Sidebar Form -->
          <div class="panel" style="align-self: start;">
              <h3>➕ Tambah Saham Beli</h3>
              <form method="POST">
                  <input type="hidden" name="action" value="add">
                  <div class="form-group">
                      <label>Kode Saham</label>
                      <input type="text" name="symbol" placeholder="Misal: BBCA" required>
                  </div>
                  <div class="form-group">
                      <label>Harga Beli (Average)</label>
                      <input type="number" name="buy_price" step="1" placeholder="Contoh: 8500" required>
                  </div>
                  <div class="form-group">
                      <label>Target Jual (Take Profit)</label>
                      <input type="number" name="target_price" step="1" placeholder="Contoh: 9500" required>
                  </div>
                  <button type="submit" class="btn">Simpan ke Portofolio</button>
              </form>

            <!-- Money Management inside Sidebar -->
            <div style="margin-top:30px; border-top:2px solid #eee; padding-top:15px;">
                <h3 style="margin-top:0; color:#1e293b; font-size:15px; border-bottom:none; padding-bottom:5px;">🧮 Kalkulator Money Management</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div><label style="font-size:12px; font-weight:bold; color:#475569;">Modal (Rp)</label><input type="number" id="totalModal" value="10000000" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;"></div>
                    <div><label style="font-size:12px; font-weight:bold; color:#475569;">Resiko (%)</label><input type="number" id="riskPct" value="2" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;"></div>
                    <div><label style="font-size:12px; font-weight:bold; color:#475569;">Harga Beli (Rp)</label><input type="number" id="mmBuyPrice" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;"></div>
                    <div><label style="font-size:12px; font-weight:bold; color:#475569;">Cut Loss (Rp)</label><input type="number" id="mmCLPrice" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;"></div>
                    <button type="button" onclick="calculateMM()" style="background:#3b82f6; color:#fff; font-weight:bold; border:none; padding:10px; border-radius:4px; width:100%; cursor:pointer;">Hitung Max Lot</button>
                </div>
                <div id="mmResult" style="margin-top:15px; font-weight:bold; font-size:13px;"></div>
            </div>
        </div>

        <script>
        function calculateMM() {
            let totalModal = parseFloat(document.getElementById('totalModal').value);
            let riskPct = parseFloat(document.getElementById('riskPct').value);
            let buyPrice = parseFloat(document.getElementById('mmBuyPrice').value);
            let clPrice = parseFloat(document.getElementById('mmCLPrice').value);
            if(!totalModal || !riskPct || !buyPrice || !clPrice) return;
            let maxRiskValue = totalModal * (riskPct / 100);
            let riskPerShare = buyPrice - clPrice;
            if(riskPerShare <= 0) { alert("Harga cut loss harus lebih rendah dari harga beli."); return; }
            let maxLot = Math.floor(maxRiskValue / (riskPerShare * 100));
            document.getElementById('mmResult').innerHTML = `<div style="padding:10px; background:#eff6ff; border-radius:4px;">Maks Resiko: <br><span style="color:#ef4444;">Rp ` + maxRiskValue.toLocaleString('id-ID') + `</span><br><br>Maks Beli: <br><span style="color:#10b981; font-size:18px;">` + maxLot + ` Lot</span></div>`;
        }
        </script>

          
<!-- Main Table -->

          <div class="panel">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:2px solid #eee; padding-bottom:10px;">
                  <h3 style="margin:0; border:none; padding:0;">Daftar Portofolio & Rekomendasi Terkini</h3>
                  <label style="font-size:12px; background:#e0f2fe; padding:6px 12px; border-radius:4px; border:1px solid #bae6fd; cursor:pointer;">
                      <input type="checkbox" id="autoRefresh" checked onchange="toggleRefresh()"> 
                      Auto-Refresh <span id="timerText" style="font-weight:bold; color:#0284c7;">(60s)</span>
                  </label>
              </div>
              <?php if (empty($portfolio)): ?>
                  <div style="padding:20px; text-align:center; color:#888; background:#fafafa; border:1px dashed #ddd; border-radius:5px;">
                      Portofolio Anda masih kosong. Silakan tambah saham yang Anda miliki di form sebelah kiri.
                  </div>
              <?php else: ?>
                  <table>
                      <thead>
                          <tr>
                              <th>Saham</th>
                              <th>Harga Beli</th>
                              <th>Harga Skrg</th>
                              <th>Target Jual</th>
                              <th>P/L (%)</th>
                              <th>Tindakan Autopilot AI</th>
                              <th>Aksi</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($portfolio as $p): ?>
                                <?php 
                                    $pl_class = $p['profit_pct'] > 0 ? 'pos' : ($p['profit_pct'] < 0 ? 'neg' : ''); 
                                    $pl_sign = $p['profit_pct'] > 0 ? '+' : '';
                                ?>
                              <tr>
                                  <td><strong><a href="chart.php?symbol=<?= urlencode($p["symbol"] . '.JK') ?>" target="_blank" style="color:#2563eb; text-decoration:none; font-size:15px;"><?= htmlspecialchars($p["symbol"]) ?></a></strong></td>
                                  <td>Rp <?= number_format($p['buy_price'], 0, ",", ".") ?></td>
                                  <td><strong>Rp <?= number_format($p['current_price'], 0, ",", ".") ?></strong></td>
                                  <td style="color:#059669;">Rp <?= number_format($p['target_price'], 0, ",", ".") ?></td>
                                  <td class="<?= $pl_class ?>">
                                      Rp <?= number_format($p['profit_val'], 0, ",", ".") ?><br>
                                      <?= $pl_sign ?><?= round($p['profit_pct'], 2) ?>%
                                  </td>
                                  <td style="line-height:1.4;">
                                      <span class="action-badge" style="background: <?= $p['action_color'] ?>"><?= $p['action'] ?></span><br>
                                      <span style="font-size:12px; color:#555;"><?= $p['reason'] ?></span><br>
                                      <i style="color:#888; font-size:11px;">Tech: <?= $p['signal'] ?></i>
                                  </td>
                                  <td style="text-align:center;">
                                      <button style="background:#28a745; color:#fff; border:none; padding:5px 10px; border-radius:3px; cursor:pointer; margin-bottom:5px;" type="button" onclick="showAvgModal('<?= $p['id'] ?>', '<?= $p['symbol'] ?>')">Avg Down</button>
<br>
<form method="POST" style="display:inline;" onsubmit="return confirm('Hapus saham ini dari portofolio?');">
                                          <input type="hidden" name="action" value="delete">
                                          <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                          <button type="submit" class="btn-delete">Hapus</button>
                                      </form>
                                  </td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                  </table>
              <?php endif; ?>
          </div>
      </div>
  </div>
  <script>
      let refreshInterval;
      let timeLeft = 60;

      function toggleRefresh() {
          const isChecked = document.getElementById('autoRefresh').checked;
          if (isChecked) {
              if (refreshInterval) clearInterval(refreshInterval);
              
              refreshInterval = setInterval(() => {
                  timeLeft--;
                  
                  if (timeLeft > 0) {
                      document.getElementById('timerText').innerText = '(' + timeLeft + 's)';
                  } else if (timeLeft === 0) {
                      clearInterval(refreshInterval);
                      window.location.reload();
                  }
              }, 1000);
          } else {
              if (refreshInterval) clearInterval(refreshInterval);
              document.getElementById('timerText').innerText = '(Off)';
          }
      }

      window.onload = function() {
          toggleRefresh();
      };
  </script>

<!-- Modal Avg Down -->
<div id="avgModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:20px; border-radius:5px; width:90%; max-width:400px; zoom:1.3;">
        <h3 style="margin-top:0;">Serok Bawah (Avg Down) - <span id="avgSymbol"></span></h3>
        <form method="POST">
            <input type="hidden" name="action" value="buy_more">
            <input type="hidden" name="id" id="avgCourseId" value="">
            <div class="form-group">
                <label>Harga Beli Penambahan</label>
                <input type="number" name="add_price" step="1" required>
            </div>
            <div class="form-group">
                <label>Jumlah Lot Penambahan</label>
                <input type="number" name="add_lot" step="1" required>
            </div>
            <div style="text-align:right; margin-top:15px;">
                <button type="button" onclick="document.getElementById('avgModal').style.display='none'" style="background:#ccc; padding:8px 15px; border:none; border-radius:3px; cursor:pointer; margin-right:5px;">Batal</button>
                <button type="submit" style="background:#007bff; color:#fff; padding:8px 15px; border:none; border-radius:3px; cursor:pointer;">Simpan</button>
            </div>
        </form>
    </div>
</div>
<script>
function showAvgModal(id, symbol) {
    document.getElementById('avgCourseId').value = id;
    document.getElementById('avgSymbol').innerText = symbol;
    document.getElementById('avgModal').style.display = 'flex';
}
</script>
</body>
</html>

