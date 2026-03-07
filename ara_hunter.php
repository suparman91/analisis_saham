<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

function getFraksi($price) {
    if ($price < 200) return 1;
    if ($price < 500) return 2;
    if ($price < 2000) return 5;
    if ($price < 5000) return 10;
    return 25;
}

function calcARA($prev) {
    if ($prev <= 50) return $prev; 
    
    if ($prev > 50 && $prev <= 200) {
        $limit = $prev + ($prev * 0.35);
    } elseif ($prev > 200 && $prev <= 5000) {
        $limit = $prev + ($prev * 0.25);
    } elseif ($prev > 5000) {
        $limit = $prev + ($prev * 0.20);
    } else {
        $limit = $prev;
    }
    
    $tick = getFraksi($limit);
    $ara = floor($limit / $tick) * $tick;
    return $ara;
}

function calcARB($prev) {
    if ($prev <= 50) return 50; 
    
    if ($prev > 50 && $prev <= 200) {
        $limit = $prev - ($prev * 0.35);
    } elseif ($prev > 200 && $prev <= 5000) {
        $limit = $prev - ($prev * 0.25);
    } elseif ($prev > 5000) {
        $limit = $prev - ($prev * 0.20);
    } else {
        $limit = $prev;
    }
    
    if ($limit < 50) return 50;
    
    $tick = getFraksi($limit);
    $arb = ceil($limit / $tick) * $tick;
    return $arb;
}

$calc_prev = isset($_POST['prev_price']) ? (float)$_POST['prev_price'] : 0;
$calc_ara = 0;
$calc_arb = 0;
if ($calc_prev > 0) {
    $calc_ara = calcARA($calc_prev);
    $calc_arb = calcARB($calc_prev);
}

require_once __DIR__ . '/analyze.php';

$saham_ara = [];

$sql_screener = "
    SELECT 
        today.symbol, today.close, today.open, today.high, today.volume,
        prev.close as prev_close, prev.volume as prev_vol
    FROM 
        (SELECT symbol, MAX(date) as max_date FROM prices GROUP BY symbol) latest
    JOIN prices today ON latest.symbol = today.symbol AND latest.max_date = today.date
    JOIN prices prev ON today.symbol = prev.symbol 
        AND prev.date = (SELECT MAX(date) FROM prices p3 WHERE p3.symbol = today.symbol AND p3.date < today.date)
    WHERE today.close >= 50 AND today.volume > 0
      AND (
          (today.close > prev.close AND today.volume >= prev.volume * 1.5) 
          OR 
          (today.close >= today.high * 0.98 AND today.close > prev.close) 
          OR
          (today.close >= today.open * 1.05) 
      )
";
$res_screener = $mysqli->query($sql_screener);

while ($row = $res_screener->fetch_assoc()) {
    $sym = $row['symbol'];
    $c = (float)$row['close'];
    $h1 = (float)$row['prev_close'];
    
    $ara_limit = calcARA($h1);
    if ($ara_limit > 0) {
        $pct_to_ara = (($ara_limit - $c) / $ara_limit) * 100;
        
        $status_ara = '';
        $alasan = [];
        
        if ($c >= $ara_limit) {
            $status_ara = 'KUNCI ARA';
            $alasan[] = 'Sudah Limit Atas';
        } elseif ($c >= $h1 && $pct_to_ara <= 3 && $pct_to_ara >= 0) {
            $status_ara = 'MENGINCAR ARA';
            $alasan[] = 'Antrean bid menebal';
        } else {
            $status_ara = 'POTENSI ARA BESOK';
            if ($row['volume'] >= $row['prev_vol'] * 2) $alasan[] = 'Volume Buy Spike';
            if ($c >= $row['high'] * 0.99) $alasan[] = 'Closing High (Marubozu)';
        }

        $analysis = analyze_symbol($mysqli, $sym);
        $signal = $analysis['signal'] ?? 'HOLD';
        $fund = $analysis['fund_status'] ?? 'N/A';
        
        $prob = 50;
        if ($signal === 'STRONG BUY') $prob += 25;
        elseif ($signal === 'BUY') $prob += 10;
        
        if (strpos(implode(',', $alasan), 'Volume') !== false) $prob += 15;
        if ($fund === 'Undervalued' || $fund === 'Fair') $prob += 10;
        if ($c >= $ara_limit) $prob = 99;

        if ($prob >= 50 || $status_ara !== 'POTENSI ARA BESOK') {
            $saham_ara[] = [
                'symbol' => str_replace('.JK', '', $sym),
                'prev' => $h1,
                'close' => $c,
                'ara' => $ara_limit,
                'status' => $status_ara,
                'distance' => round($pct_to_ara, 2),
                'reason' => implode(', ', $alasan),
                'signal' => $signal,
                'prob' => min($prob, 99)
            ];
        }
    }
}

usort($saham_ara, function($a, $b) {
    if ($a['status'] === 'KUNCI ARA' && $b['status'] !== 'KUNCI ARA') return -1;
    if ($b['status'] === 'KUNCI ARA' && $a['status'] !== 'KUNCI ARA') return 1;
    return $b['prob'] <=> $a['prob'];
});
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>ARA Hunter & Kalkulator - Sistem Analisis Saham</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f8f9fa;}
    .container { max-width:1200px; margin:0 auto; }
    h1 { color:#333; margin-bottom: 5px;}
    .subtitle { color:#666; font-size:14px; margin-bottom:20px; }

    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }

    .grid-container { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
    
    .panel { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); margin-bottom:20px; }
    .panel h3 { margin-top:0; border-bottom:2px solid #eee; padding-bottom:10px; color:#495057; font-size:16px;}
    
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
    .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
    .btn { background: #3b82f6; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; display: inline-block; font-weight: bold; width: 100%; }
    .btn:hover { background: #2563eb; }

    .result-box { margin-top:15px; padding:15px; background:#eef2ff; border:1px solid #c7d2fe; border-radius:5px; text-align:center; }
    .result-box .ara-price { font-size: 24px; color: #10b981; font-weight: bold; margin: 10px 0; }
    .result-box .arb-price { font-size: 24px; color: #ef4444; font-weight: bold; margin: 10px 0; }

    table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:0; }
    th, td { padding:12px 8px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f1f3f5; font-weight:bold; color:#495057; }
    
    .badge { display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; font-size:11px; font-weight:bold;}
    .badge-ara { background:#10b981; }
    .badge-mendekati { background:#f59f00; }
  </style>
</head>
<body>
  <div class="container">
      <nav class="top-menu">
        <a href="index.php">📊 Dashboard Market</a>
        <a href="chart.php">📈 Chart & Analisis</a>
        <a href="scan_manual.php">🔍 Scanner BSJP/BPJP</a>
        <a href="stockpick.php">🎯 AI Stockpick Tracker</a>
        <a href="ara_hunter.php" class="active">🚀 ARA Hunter</a>
      </nav>

      <h1>🚀 ARA Hunter & Kalkulator Fraksi</h1>
      <p class="subtitle">Berdasarkan Regulasi Simetris BEI (4 September 2023)</p>
      
      <div class="grid-container">
          <div class="panel" style="grid-column: 1;">
              <h3>🧮 Kalkulator ARA / ARB Manual</h3>
              <p style="font-size:12px; color:#666; margin-bottom:15px;">Masukkan Harga Penutupan (Close) Kemarin untuk mengetahui batas atas dan bawah hari ini.</p>
              
              <form method="POST" action="">
                  <div class="form-group">
                      <label>Harga Penutupan Kemarin (Rp)</label>
                      <input type="number" name="prev_price" value="<?= $calc_prev > 0 ? $calc_prev : '' ?>" required placeholder="Contoh: 100">
                  </div>
                  <button type="submit" class="btn">Hitung Batas</button>
              </form>

              <?php if ($calc_prev > 0): ?>
                  <div class="result-box">
                      <div style="font-size:12px;color:#666;text-transform:uppercase;">Auto Rejection Atas (ARA)</div>
                      <div class="ara-price">Rp <?= number_format($calc_ara, 0, ",", ".") ?></div>
                      
                      <div style="font-size:12px;color:#666;text-transform:uppercase;margin-top:15px;">Auto Rejection Bawah (ARB)</div>
                      <div class="arb-price">Rp <?= number_format($calc_arb, 0, ",", ".") ?></div>
                  </div>
              <?php endif; ?>
          </div>

          <div class="panel" style="grid-column: 2;">
              <h3>⚡ Screener Live: Potensi ARA Besok & Hunter</h3>
              <p style="font-size:12px; color:#666; margin-bottom:15px;">Menampilkan saham dari database hari ini yang mengunci ARA, mengincar ARA, atau berpotensi ARA di hari berikutnya (berdasarkan Momentum Buy, Sinyal Teknikal, dan Fundamental).</p>
              
              <?php if (empty($saham_ara)): ?>
                  <div style="padding:20px; text-align:center; color:#888; background:#fafafa; border:1px dashed #ddd; border-radius:5px;">
                      Belum ada saham yang terpantau berpotensi tinggi ARA saat ini.
                  </div>
              <?php else: ?>
                  <table>
                      <thead>
                          <tr>
                              <th>Symbol</th>
                              <th>Harga Skrg</th>
                              <th>Batas ARA</th>
                              <th>AI Prob</th>
                              <th>Sinyal / Alasan</th>
                              <th>Status</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($saham_ara as $s): ?>
                              <tr>
                                  <td><strong><a href="chart.php?symbol=<?= urlencode($s["symbol"] . '.JK') ?>" target="_blank" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($s["symbol"]) ?></a></strong></td>
                                  <td>Rp <?= number_format($s["close"], 0, ",", ".") ?></td>
                                  <td style="color:#10b981; font-weight:bold;">Rp <?= number_format($s["ara"], 0, ",", ".") ?></td>
                                  <td>
                                    <?php 
                                      $color = $s["prob"] >= 80 ? "color:#10b981" : ($s["prob"] >= 65 ? "color:#f59f00" : "color:#666");
                                    ?>
                                    <span style="font-weight:bold; <?= $color ?>"><?= $s["prob"] ?>%</span>
                                  </td>
                                  <td style="font-size:11px; color:#555;">
                                      <strong style="color:#333;"><?= htmlspecialchars($s["signal"]) ?></strong><br>
                                      <?= htmlspecialchars($s["reason"]) ?>
                                  </td>
                                  <td>
                                      <?php if ($s["status"] === "KUNCI ARA"): ?>
                                        <span class="badge badge-ara" style="background:#5b21b6;">🚀 KUNCI ARA</span>
                                      <?php elseif ($s["status"] === "MENGINCAR ARA"): ?>
                                        <span class="badge badge-mendekati">🔥 MENUJU ARA</span>
                                      <?php else: ?>
                                        <span class="badge badge-ara" style="background:#0ea5e9;">⚡ POTENSI BESOK</span>
                                      <?php endif; ?>
                                  </td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                  </table>
              <?php endif; ?>
          </div>
      </div>
  </div>
</body>
</html>
