<?php
require_once 'auth.php'; // Panggil session
require_login();         // Wajib masuk

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
$mysqli = db_connect();
require_subscription($mysqli); // Wajib langganan aktif

$user_id = get_user_id();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $symbol = strtoupper(trim($_POST['symbol']));
        if (!empty($symbol) && strpos($symbol, '.') === false) {
            $symbol .= '.JK'; // Otomatis tambahkan '.JK' untuk saham lokal Indonesia
        }
        
        $buy_price = (float)$_POST['buy_price'];
        $target_price = (float)$_POST['target_price'];
        $lots = (int)$_POST['lots'];

        if (!empty($symbol) && $buy_price > 0 && $target_price > 0 && $lots > 0) {
            $stmt = $mysqli->prepare("INSERT INTO portfolio (user_id, symbol, buy_price, target_price, lots) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isddi", $user_id, $symbol, $buy_price, $target_price, $lots);
            $stmt->execute();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_lot') {
        $id = (int)$_POST['id'];
        $lots = (int)$_POST['lots'];
        if ($lots >= 0) {
            $stmt = $mysqli->prepare("UPDATE portfolio SET lots = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $lots, $id, $user_id);
            $stmt->execute();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $mysqli->prepare("DELETE FROM portfolio WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Redirect to clear POST
    header("Location: portfolio_manual.php");
    exit;
}

// Fetch Portfolio (HANYA MILIK USER TERSEBUT)
$portfolio = [];
$res = $mysqli->query("SELECT * FROM portfolio WHERE user_id = $user_id ORDER BY added_on DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $portfolio[] = $r;
    }
}
?>
<?php
$pageTitle = 'Autopilot Portofolio Planner | Analisis Saham';
?>
<?php include 'header.php'; ?>
  <style>
    body{font-family:Arial,sans-serif;margin:20px; background: #f8fafc;}
    table{border-collapse:collapse;width:100%;margin-top:20px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-radius:8px; overflow:hidden;}
    th,td{border-bottom:1px solid #e2e8f0;padding:12px;text-align:left; font-size:14px;}
    th{background:#f1f5f9; color:#64748b; font-weight:bold; font-size:13px; text-transform:uppercase;}
    .badge{display:inline-block;padding:4px 8px;border-radius:4px;color:#fff;font-weight:bold;font-size:12px}
    .buy{background:#28a745}
    .sell{background:#dc3545}
    .hold{background:#ffc107;color:#000}
    .db-container {
        max-width: 1200px;
        margin: 0 auto;
    }
  </style>
    <div class="db-container">

        <h2 style="color:#0f172a; margin-top:0;">&#x1F4BC; Autopilot Portofolio Planner (Manual Tracker)</h2>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <span style="color:#64748b; font-size:14px;">
            Pantau harga saham yang Anda beli secara *real-time* dan hitung *Take Profit* beserta *Cut Loss* secara otomatis.
          </span>
          <span style="font-size:12px; background:#e0f2fe; color:#1e3a8a; padding:6px 12px; border-radius:20px; font-weight:bold; display:flex; align-items:center; gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>
            Auto-Refresh tiap 10 Detik
          </span>
        </div>

        <div style="margin-bottom:20px; background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
          <form id="addPlanForm" method="POST" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="action" value="add">
            <input type="text" name="symbol" placeholder="Symbol (misal: BBCA.JK)" required style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; flex:1;">
            <input type="number" step="0.01" name="buy_price" placeholder="Harga Beli" required style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; flex:1;">
            <input type="number" step="0.01" name="target_price" placeholder="Target TP" required style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; flex:1;">
            <input type="number" name="lots" placeholder="Jml Lot" required style="padding:10px; border:1px solid #cbd5e1; border-radius:5px; flex:0.5; min-width:80px;">
            <button type="submit" id="btnAddPlan" style="padding: 11px 20px; background: #3b82f6; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight:bold;">+ Tambah ke Plan</button>
          </form>
        </div>

        <table>
          <thead>
            <tr>
              <th>Saham</th>
              <th>Lot</th>
              <th>Harga Beli</th>
              <th>Target TP</th>
              <th>Current Price</th>
              <th>Nilai Invest (<span style="font-weight:normal; font-size:12px;">P/L Rp</span>)</th>
              <th>Floating %</th>
              <th>Analisis AI (Tech &amp; Fund)</th>
              <th>Status / Rekomendasi Robot</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="portBody">
            <?php if (count($portfolio) == 0): ?>
            <tr><td colspan="10" style="text-align:center; padding:20px; color:#94a3b8;">Belum ada saham yang dipantau (Tracker kosong).</td></tr>
            <?php else: ?>
                <?php foreach($portfolio as $p): 
                  $lot = isset($p['lots']) ? $p['lots'] : 0;
                  $invest_val = $p['buy_price'] * $lot * 100;
                  
                  // Proses Analisis AI (Teknikal & Fundamental)
                  $ai_data = analyze_symbol($mysqli, $p['symbol']);
                  $tech_signal = $ai_data['signal'] ?? 'N/A';
                  $fund_score = $ai_data['fund_score'] ?? 'N/A';
                  $fund_status = $ai_data['fund_status'] ?? 'N/A';
                  
                  // Label Warna Signal Teknikal
                  $tech_color = strpos($tech_signal, 'BUY') !== false ? '#16a34a' : (strpos($tech_signal, 'SELL') !== false ? '#dc2626' : '#ca8a04');
                ?>
                <tr data-symbol="<?php echo htmlspecialchars($p['symbol']); ?>"
                    data-buy="<?php echo htmlspecialchars($p['buy_price']); ?>"
                    data-target="<?php echo htmlspecialchars($p['target_price']); ?>"
                    data-lots="<?php echo $lot; ?>">
                  <td><b><a href="chart.php?symbol=<?php echo htmlspecialchars($p['symbol']); ?>" style="color:#3b82f6;text-decoration:none;" target="_blank"><?php echo htmlspecialchars($p['symbol']); ?></a></b></td>
                  <td><?php echo number_format($lot,0,',','.'); ?></td>
                  <td>Rp <?php echo number_format($p['buy_price'],0,',','.'); ?></td>
                  <td>Rp <?php echo number_format($p['target_price'],0,',','.'); ?></td>
                  <td class="curr-price" style="font-weight:bold; color:#0f172a;">-</td>
                  <td class="invest-val">
                     Rp <?php echo number_format($invest_val,0,',','.'); ?><br>
                     <small class="pl-val" style="color:#64748b;">-</small>
                  </td>
                  <td class="float-pct">-</td>
                  <td style="font-size:12px; line-height:1.5; color:#475569; max-width:200px;">
                    <strong style="color:#0f172a;">Teknikal:</strong> <span style="background:<?php echo $tech_color; ?>20; color:<?php echo $tech_color; ?>; padding:2px 6px; border-radius:4px; font-weight:bold;"><?php echo $tech_signal; ?></span><br>
                    <span style="font-size:11px; color:#94a3b8; display:block; margin-top:4px; margin-bottom:6px; line-height:1.4;">
                      <?php echo htmlspecialchars($ai_data['signal_details'] ?? 'N/A'); ?>
                    </span>
                    <strong style="color:#0f172a;">Fundamen:</strong> <b><?php echo $fund_status; ?></b> <span style="color:#64748b;">(<?php echo $fund_score; ?>/10)</span>
                  </td>
                  <td class="status-bot">-</td>
                  <td>
                    <form method="POST" style="display:inline" id="form_update_<?php echo $p['id']; ?>">
                      <input type="hidden" name="action" value="update_lot">
                      <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                      <input type="hidden" name="lots" id="input_lot_<?php echo $p['id']; ?>" value="">
                    </form>
                    <button type="button" onclick="updateLot(<?php echo $p['id']; ?>, <?php echo $lot; ?>)" style="padding:5px 10px; background:#f59e0b; color:#fff; border:none; border-radius:4px; cursor:pointer; margin-right:5px;">Edit Lot</button>
                    <button type="button" onclick="deletePlan(<?php echo $p['id']; ?>)" style="padding:5px 10px; background:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer;">Delete</button>
                  </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
    </div>

  <script>
    // Polling live price tiap 10 detik via ajax goapi
    function fetchLivePrices() {
      const rows = document.querySelectorAll('#portBody tr[data-symbol]');
      rows.forEach(r => {
        const sym = r.getAttribute('data-symbol');
        const buy = parseFloat(r.getAttribute('data-buy'));
        const target = parseFloat(r.getAttribute('data-target'));
        const lots = parseInt(r.getAttribute('data-lots')) || 0;

        fetch('fetch_realtime.php?symbols=' + sym)
        .then(res => res.json())
        .then(data => {
            if(data.data && data.data[sym] && data.data[sym].price) {
                const currStr = data.data[sym].price;
                // remove commas if any
                const curr = parseFloat(String(currStr).replace(/,/g, ''));
                r.querySelector('.curr-price').innerText = "Rp " + curr.toLocaleString('id-ID');

                const pct = ((curr - buy) / buy) * 100;
                let color = pct > 0 ? "#166534" : (pct < 0 ? "#991b1b" : "#475569");
                let bgcolor = pct > 0 ? "#dcfce7" : (pct < 0 ? "#fee2e2" : "#f1f5f9");
                r.querySelector('.float-pct').innerHTML = `<span style="background:${bgcolor}; color:${color}; padding:4px 8px; border-radius:4px; font-weight:bold;">${pct > 0 ? '+' : ''}${pct.toFixed(2)}%</span>`;

                // Calculate P/L
                const invest_val = buy * lots * 100;
                const curr_val = curr * lots * 100;
                const pl = curr_val - invest_val;
                const plText = (pl > 0 ? '+' : '') + "Rp " + pl.toLocaleString('id-ID');
                r.querySelector('.pl-val').innerHTML = `<span style="color:${color}; font-weight:bold;">${plText}</span>`;

                let recClass = "hold";
                let recText = "HOLDING (AMAN)";
                let recIcon = "&#x23F3;"; // hourglass
                let recDetail = "";

                if (curr >= target) {
                   recClass = "buy";
                   recText = "TAKE PROFIT (HIT)";
                   recIcon = "&#x1F680;"; // rocket
                   recDetail = "Harga menembus target, segera amankan profit Anda!";
                } else if (pct <= -3) {
                   recClass = "sell";
                   recText = "CUT LOSS (-3%)";
                   recIcon = "&#x1F6A8;"; // siren
                   recDetail = "Batas resiko tertembus! Jangan biarkan minus semakin dalam.";
                } else if (pct > 0) {
                   recIcon = "&#x2705;"; // green check
                   recDetail = "Posisi untung. Tahan sampai menyentuh target TP Anda.";
                } else {
                   recIcon = "&#x26A0;&#xFE0F;"; // warning
                   recDetail = "Posisi merah. Awasi harga support terdekat sebelum Cut Loss.";
                }

                r.querySelector('.status-bot').innerHTML = `<span class="badge ${recClass}">${recIcon} ${recText}</span><br><span style="font-size:11px; color:#64748b; display:inline-block; margin-top:6px; line-height:1.4;">${recDetail}</span>`;
            }
        });
      });
    }

    fetchLivePrices();
    setInterval(fetchLivePrices, 10000); // refresh tiap 10 detik

    // --- AJAX CRUD (No Reload) ---
    
    // 1. Fungsi Reload Table (AJAX HTML Replacement)
    async function reloadTable() {
      const res = await fetch('portfolio_manual.php');
      const text = await res.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(text, 'text/html');
      document.getElementById('portBody').innerHTML = doc.getElementById('portBody').innerHTML;
      fetchLivePrices(); // Re-trigger live prices untuk data HTML baru
    }

    // 2. Add via AJAX
    document.getElementById('addPlanForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      formData.append('ajax', '1');
      await fetch('portfolio_manual.php', { method: 'POST', body: formData });
      this.reset();
      await reloadTable();
    });

    // 3. Edit Lot via AJAX
    async function updateLot(id, currentLot) {
      let newLot = prompt("Masukkan jumlah Lot baru:", currentLot);
      if (newLot !== null && newLot.trim() !== "") {
        let parsed = parseInt(newLot);
        if (!isNaN(parsed) && parsed >= 0) {
          const formData = new FormData();
          formData.append('action', 'update_lot');
          formData.append('id', id);
          formData.append('lots', parsed);
          formData.append('ajax', '1');
          await fetch('portfolio_manual.php', { method: 'POST', body: formData });
          await reloadTable();
        } else {
          alert('Jumlah Lot tidak valid.');
        }
      }
    }

    // 4. Delete via AJAX
    async function deletePlan(id) {
      if (confirm('Hapus plan ini?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('ajax', '1');
        await fetch('portfolio_manual.php', { method: 'POST', body: formData });
        await reloadTable();
      }
    }

  </script>
<?php include 'footer.php'; ?>