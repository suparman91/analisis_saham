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
        today.symbol, today.close, today.open, today.high, today.low, today.volume,
        prev.close as prev_close, prev.volume as prev_vol
    FROM
        (SELECT symbol, MAX(date) as max_date FROM prices GROUP BY symbol) latest
    JOIN prices today ON latest.symbol = today.symbol AND latest.max_date = today.date
    JOIN prices prev ON today.symbol = prev.symbol
        AND prev.date = (SELECT MAX(date) FROM prices p3 WHERE p3.symbol = today.symbol AND p3.date < today.date)
    WHERE today.close >= 50 AND today.volume >= 500000 
      AND today.close < prev.close
      AND (
          -- Kriteria 1: Harga turun > 2% dan close di dekat low (tekanan jual)
          (today.close <= prev.close * 0.98 AND today.close <= today.low * 1.02 AND today.volume >= prev.volume * 1.2)
          OR
          -- Kriteria 2: Spike Volume saat turun (Distribusi)
          (today.close <= prev.close * 0.97 AND today.volume >= prev.volume * 1.5)
          OR
          -- Kriteria 3: Saham anjlok parah (> 5%)
          (today.close <= prev.close * 0.95)
      )
";
$res_screener = $mysqli->query($sql_screener);

while ($row = $res_screener->fetch_assoc()) {
    $sym = $row['symbol'];
    $c = (float)$row['close'];
    $h = (float)$row['high'];
    $h1 = (float)$row['prev_close'];
    
    $arb_limit = calcARB($h1);
    if ($arb_limit > 0) {
        $pct_to_arb = (($c - $arb_limit) / $c) * 100;

        $status_ara = '';
        $alasan = [];
        $is_hit_arb = ($row['low'] <= $arb_limit);

        if ($c <= $arb_limit) {
            $status_ara = 'KUNCI ARB';
            $alasan[] = 'Sudah Limit Bawah (ARB)';
        } elseif ($c <= $h1 && $pct_to_arb <= 3 && $pct_to_arb >= 0) {
            $status_ara = 'MENGINCAR ARB';
            $alasan[] = 'Antrean offer membludak / Bid tipis';
        } else {
            $status_ara = 'POTENSI ARB / TURUN';
            if ($row['volume'] >= $row['prev_vol'] * 2) $alasan[] = 'Volume Distribusi (Sell Spike)';
            if ($c <= $row['low'] * 1.01) $alasan[] = 'Closing Low (Bearish Marubozu)';
        }

        $analysis = analyze_symbol($mysqli, $sym);
        $signal = $analysis['signal'] ?? 'HOLD';
        $fund = $analysis['fund_status'] ?? 'N/A';
        $tech_detail = $analysis['signal_details'] ?? '';
        
        $prob = 35;
        if ($signal === 'STRONG SELL') $prob += 20;
        elseif ($signal === 'SELL') $prob += 10;
        
        if (strpos(implode(',', $alasan), 'Volume') !== false) $prob += 15;
        if ($fund === 'OVERVALUED (Expensive / Risky)' || strpos($fund, 'FAIR') !== false) $prob += 10;
        
        // Add dynamic probability based on actual indicators
        if (strpos($tech_detail, 'MACD Negative') !== false) $prob += 5;
        if (strpos($tech_detail, 'RSI Overbought') !== false) $prob += 5;
        if (strpos($tech_detail, 'SMA Bearish') !== false) $prob += 5;

        // Sentimen Summary
        $sentimen = [];
        if ($fund !== 'N/A') $sentimen[] = "Valuasi: " . explode(' ', $fund)[0];
        if ($tech_detail) {
            $tech_items = explode(', ', $tech_detail);
            $sentimen[] = "Tech: " . implode(', ', array_slice($tech_items, 0, 2)); // Ambil max 2 sinyal kuat
        }

        if ($c <= $arb_limit) $prob = 99;
        if ($pct_to_arb <= 3 && $pct_to_arb >= 0) $prob = max($prob, 85);

        if ($prob >= 50 || $status_ara !== 'POTENSI ARB / TURUN') {
            $saham_ara[] = [
                'symbol' => str_replace('.JK', '', $sym),
                'open' => (float)$row['open'],
                'prev' => $h1,
                'close' => $c,
                'ara' => $arb_limit,
                'status' => $status_ara,
                'distance' => round($pct_to_arb, 2),
                'hit_arb' => $is_hit_arb,
                'reason' => implode(', ', $alasan),
                'signal' => $signal,
                'sentimen' => implode(' | ', $sentimen),
                'prob' => min($prob, 99),
                'analysis_detail' => [
                    'signal_details' => $tech_detail,
                    'pe' => $analysis['fundamental']['pe'] ?? 'N/A',
                    'pbv' => $analysis['fundamental']['pbv'] ?? 'N/A',
                    'roe' => $analysis['fundamental']['roe'] ?? 'N/A',
                    'entry' => $analysis['trading_plan']['entry'] ?? 0,
                    'tp' => $analysis['trading_plan']['take_profit'] ?? 0,
                    'sl' => $analysis['trading_plan']['cut_loss'] ?? 0,
                    'rr' => $analysis['trading_plan']['reward_risk'] ?? 0
                ]
            ];
        }
    }
}

usort($saham_ara, function($a, $b) {
    if ($a['status'] === 'KUNCI ARB' && $b['status'] !== 'KUNCI ARB') return -1;
    if ($b['status'] === 'KUNCI ARB' && $a['status'] !== 'KUNCI ARB') return 1;
    return $b['prob'] <=> $a['prob'];
});
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>ARB Hunter (Saham Potensi Turun) & Kalkulator - Sistem Analisis Saham</title>
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
    
    .badge { display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; font-size:11px; font-weight:bold; cursor:pointer;}
    .badge-ara { background:#10b981; }
    .badge-mendekati { background:#f59f00; }

    /* Modal Styles */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-content { background: #fff; padding: 25px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; }
    .modal-close { position: absolute; top: 15px; right: 15px; font-size: 20px; cursor: pointer; color: #666; font-weight: bold; border: none; background: none; }
    .modal-close:hover { color: #000; }
    .modal-title { margin-top: 0; font-size: 18px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; }
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; margin-bottom: 15px; }
    .detail-item { background: #f8fafc; padding: 10px; border-radius: 4px; border: 1px solid #e2e8f0; }
    .detail-label { color: #64748b; margin-bottom: 5px; font-size: 11px; text-transform: uppercase; }
    .detail-val { font-weight: bold; color: #334155; font-size: 14px; }
    .plan-box { background: #eff6ff; border: 1px solid #bfdbfe; padding: 10px; border-radius: 6px; margin-top: 15px; }
    .plan-title { font-weight: bold; color: #1d4ed8; margin-bottom: 10px; font-size: 13px; }
  </style>
</head>
<body>
  <div class="container">
      <nav class="top-menu">
        <a href="index.php">📊 Dashboard Market</a>
        <a href="chart.php">📈 Chart & Analisis</a>
        <a href="scan_manual.php">🔍 Scanner BSJP/BPJP</a>
        <a href="stockpick.php">🎯 AI Stockpick Tracker</a>
        <a href="arb_hunter.php" class="active">📉 ARB Hunter</a>
        <a href="portfolio.php">&#x1F4BC; Autopilot Portofolio</a>
        <a href="telegram_setting.php" style="margin-left:auto; background:#475569;"><img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" width="14" style="vertical-align:middle;margin-right:5px;">Set Alert</a>
      </nav>

      <h1>📉 ARB Hunter (Saham Potensi Turun) & Kalkulator Fraksi</h1>
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
              <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:15px; border-bottom:2px solid #eee; padding-bottom:10px;">
                  <div>
                      <h3 style="margin-bottom:5px; border:none; padding-bottom:0;">📡 Screener Live: Potensi Turun Besok & Hunter</h3>
                      <p style="font-size:12px; color:#666; margin:0;">Saham mengunci ARB, mengincar ARB, atau potensi turun berdasar Momentum & Fundamental.</p>
                  </div>
                  <label style="font-size:12px; background:#f1f5f9; padding:5px 10px; border-radius:4px; border:1px solid #cbd5e1; cursor:pointer; display:flex; align-items:center; gap:5px;">
                      <input type="checkbox" id="autoRefresh" checked onchange="toggleRefresh()"> 
                      Auto-Refresh <span id="timerText" style="font-weight:bold; color:#2563eb;">(60s)</span>
                  </label>
              </div>
              
              <?php if (empty($saham_ara)): ?>
                  <div style="padding:20px; text-align:center; color:#888; background:#fafafa; border:1px dashed #ddd; border-radius:5px;">
                      Belum ada saham yang terpantau berpotensi tinggi ARB saat ini.
                  </div>
              <?php else: ?>
                  <table>
                      <thead>
                          <tr>
                              <th>Symbol</th>
                              <th>Harga Open</th>
                              <th>Harga Skrg</th>
                              <th>Batas ARB</th>
                              <th>HIT ARB?</th>
                              <th>AI Prob</th>
                              <th>Sinyal / Alasan / Sentimen</th>
                              <th>Status</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($saham_ara as $s): ?>
                              <tr>
                                  <td><strong><a href="chart.php?symbol=<?= urlencode($s["symbol"] . '.JK') ?>" target="_blank" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($s["symbol"]) ?></a></strong></td>                                    <td style="color:#f59f00;">Rp <?= number_format($s["open"], 0, ",", ".") ?></td>                                  <td>Rp <?= number_format($s["close"], 0, ",", ".") ?></td>
                                  <td style="color:#10b981; font-weight:bold;">Rp <?= number_format($s["ara"], 0, ",", ".") ?></td>
                                  <td style="text-align:center;">
                                      <?php if ($s["hit_arb"]): ?>
                                          <span style="color:#10b981; font-weight:bold;">🎯 YES</span>
                                      <?php else: ?>
                                          <span style="color:#ef4444; font-weight:bold;">❌ NO</span>
                                      <?php endif; ?>
                                  </td>
                                  <td>
                                    <?php 
                                      $color = $s["prob"] >= 80 ? "color:#10b981" : ($s["prob"] >= 65 ? "color:#f59f00" : "color:#666");
                                    ?>
                                    <span style="font-weight:bold; <?= $color ?>"><?= $s["prob"] ?>%</span>
                                  </td>
                                  <td style="font-size:11px; color:#555;">
                                      <strong style="color:#333;"><?= htmlspecialchars($s["signal"]) ?></strong>
                                      <?php if ($s['reason']) echo "<br><span style='color:#0284c7;'>".$s['reason']."</span>"; ?>
                                      <?php if ($s['sentimen']) echo "<br><i style='color:#65a30d;'>".$s['sentimen']."</i>"; ?>
                                  </td>
                                  <td>
                                      <?php 
                                        $detail_json = htmlspecialchars(json_encode([
                                            'symbol' => $s['symbol'],
                                            'prob' => $s['prob'],
                                            'signal' => $s['signal'],
                                            'signal_details' => $s['analysis_detail']['signal_details'],
                                            'pe' => is_numeric($s['analysis_detail']['pe']) ? round($s['analysis_detail']['pe'],2) : $s['analysis_detail']['pe'],
                                            'pbv' => is_numeric($s['analysis_detail']['pbv']) ? round($s['analysis_detail']['pbv'],2) : $s['analysis_detail']['pbv'],
                                            'roe' => is_numeric($s['analysis_detail']['roe']) ? round($s['analysis_detail']['roe'],2).'%' : $s['analysis_detail']['roe'],
                                            'entry' => 'Rp '.number_format($s['analysis_detail']['entry'], 0, ",", "."),
                                            'tp' => 'Rp '.number_format($s['analysis_detail']['tp'], 0, ",", "."),
                                            'sl' => 'Rp '.number_format($s['analysis_detail']['sl'], 0, ",", "."),
                                            'rr' => $s['analysis_detail']['rr']
                                        ]), ENT_QUOTES, 'UTF-8');
                                      ?>
                                      <?php if ($s["status"] === "KUNCI ARB"): ?>
                                        <span class="badge badge-ara" style="background:#5b21b6;" onclick="showDetail(<?= $detail_json ?>)">🔒 KUNCI ARB</span>
                                      <?php elseif ($s["status"] === "MENGINCAR ARB"): ?>
                                        <span class="badge badge-mendekati" onclick="showDetail(<?= $detail_json ?>)">⚠️ MENUJU ARB</span>
                                      <?php else: ?>
                                        <span class="badge badge-ara" style="background:#0ea5e9;" onclick="showDetail(<?= $detail_json ?>)">💡 POTENSI TURUN</span>
                                      <?php endif; ?>
                                  </td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                  </table>
              <?php endif; ?>
              
              <div style="margin-top: 20px; background: #fdfce8; border-left: 4px solid #fde047; padding: 15px; border-radius: 4px; font-size: 13px; color: #555;">
                  <strong style="display:block; margin-bottom: 8px; color: #854d0e;">📖 Panduan & Keterangan Lengkap:</strong>
                  <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                      <li><strong>Kunci ARB (🔒):</strong> Harga saham sudah menyentuh batas persentase penurunan maksimal harian dari Bursa. Umumnya antrean jual (offer) mengunci penuh (lock) di harga ini, potensi terbuka <em>gap down</em> esok hari.</li>
                      <li><strong>Mengincar ARB (⚠️):</strong> Harga turun signifikan secara agresif dan selisih harganya berjarak &le; 3% dari Batas ARB. Momentum sangat kuat.</li>
                      <li><strong>Potensi Besok (💡):</strong> Harga mungkin tidak ARB hari ini, namun pola distribusi memenuhi kriteria screener (harga turun solid didukung volume jual). Sangat layak pantau besok.</li>
                      <li><strong>HIT ARB🎯:</strong> Indikator yang menandakan apakah harga <em>Low</em> (Terendah) di hari ini benar-benar sempat menyentuh Batas ARB, meskipun pada penutupan (<em>Close</em>) harga memantul ke level lebih tinggi.</li>
                      <li><strong>Volume Distribusi (Sell Spike):</strong> Volume transaksi hari ini meledak minimal 1.5x hingga 2x lipat dari rata-rata/hari sebelumnya. Indikasi keluarnya atau distribusi oleh <em>big money / institusi</em>.</li>
                      <li><strong>Closing Low (Bearish Marubozu):</strong> Saham ditutup rata atau sangat dekat (selisih &lt;2%) dengan harga titik terendah harian. Sentimen <em>seller (jual)</em> kuat tanpa tekanan beli jelang <em>closing</em> pasar.</li>
                      <li><strong>Sentimen (Valuasi & Technical):</strong> 
                          <ul style="margin:0; padding-left: 20px;">
                              <li><em>Valuasi (Overvalued/Fair)</em> mengindikasikan saham sedang mahal atau overvalued.</li>
                              <li><em>MACD Negative / SMA Bearish</em> mendeteksi tren distribusi atau downtrend sedang berlangsung (Death Cross).</li>
                              <li><em>RSI Overbought</em> menandakan harga saham sudah mencapai atau turun dari titik jenuh beli (terlalu mahal).</li>
                          </ul>
                      </li>
                      <li><strong>AI Prob:</strong> Persentase keakuratan momentum dari kecerdasan sistem (0-99%). Skor dinaikkan jika ada faktor pendukung seperti Tren <em>STRONG BUY</em> teknikal, lonjakan volume masif, atau jika valuasi saham masih murah (<em>Undervalued/Fair</em>). Semakin tinggi semakin bagus.</li>
                  </ul>
              </div>
          </div>
      </div>
  </div>

  <!-- Modal Detail -->
  <div class="modal-overlay" id="detailModal">
      <div class="modal-content">
          <button class="modal-close" onclick="closeModal()">&times;</button>
          <h3 class="modal-title">Laporan Analisis <span id="m-symbol"></span></h3>
          
          <div class="detail-grid">
              <div class="detail-item">
                  <div class="detail-label">AI Probability</div>
                  <div class="detail-val" id="m-prob" style="color:#2563eb;"></div>
              </div>
              <div class="detail-item">
                  <div class="detail-label">Rekomendasi</div>
                  <div class="detail-val" id="m-signal"></div>
              </div>
              <div class="detail-item">
                  <div class="detail-label">Valuasi (PE / PBV)</div>
                  <div class="detail-val"><span id="m-pe"></span>x / <span id="m-pbv"></span>x</div>
              </div>
              <div class="detail-item">
                  <div class="detail-label">Profitabilitas (ROE)</div>
                  <div class="detail-val" id="m-roe"></div>
              </div>
          </div>

          <div style="background:#fdfce8; border:1px solid #fef08a; padding:10px; border-radius:4px; margin-bottom:15px; font-size:13px;">
              <strong style="color:#ca8a04; display:block; margin-bottom:5px;">Sinyal Teknikal:</strong>
              <div id="m-tech" style="color:#4d7c0f; font-weight:bold;"></div>
          </div>

          <div class="plan-box">
              <div class="plan-title">📝 Trading Plan (Saran AI)</div>
              <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                  <span style="color:#64748b;">Area Beli (Entry):</span> 
                  <strong style="color:#0f172a;" id="m-entry"></strong>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                  <span style="color:#64748b;">Target Profit (TP):</span> 
                  <strong style="color:#16a34a;" id="m-tp"></strong>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                  <span style="color:#64748b;">Batas Rugi (SL):</span> 
                  <strong style="color:#dc2626;" id="m-sl"></strong>
              </div>
              <div style="display:flex; justify-content:space-between;">
                  <span style="color:#64748b;">Risk/Reward Ratio:</span> 
                  <strong style="color:#8b5cf6;" id="m-rr"></strong>
              </div>
          </div>
          
          <a href="#" id="m-chart-link" target="_blank" style="display:block; text-align:center; background:#1e293b; color:#fff; padding:12px; border-radius:6px; text-decoration:none; font-weight:bold; margin-top:15px; transition:0.2s;">📈 Buka Chart & Analisis Sengkapnya</a>
      </div>
  </div>

  <script>
      function showDetail(data) {
          document.getElementById('m-symbol').innerText = data.symbol;
          document.getElementById('m-prob').innerText = data.prob + '% Target ARA';
          document.getElementById('m-signal').innerText = data.signal;
          document.getElementById('m-pe').innerText = data.pe;
          document.getElementById('m-pbv').innerText = data.pbv;
          document.getElementById('m-roe').innerText = data.roe;
          document.getElementById('m-tech').innerText = data.signal_details ? data.signal_details : 'Netral';
          
          document.getElementById('m-entry').innerText = data.entry;
          document.getElementById('m-tp').innerText = data.tp;
          document.getElementById('m-sl').innerText = data.sl;
          document.getElementById('m-rr').innerText = data.rr + 'x';
          
          document.getElementById('m-chart-link').href = 'chart.php?symbol=' + encodeURIComponent(data.symbol + '.JK');

          document.getElementById('detailModal').style.display = 'flex';
      }

      function closeModal() {
          document.getElementById('detailModal').style.display = 'none';
      }

      // Close if click outside
      window.onclick = function(event) {
          var modal = document.getElementById('detailModal');
          if (event.target == modal) {
              modal.style.display = "none";
          }
      }

      // Auto Refresh Logic Setup
      let refreshInterval;
      let timeLeft = 60;
      
      function toggleRefresh() {
          const isChecked = document.getElementById('autoRefresh').checked;
          if (isChecked) {
              timeLeft = 60;
              document.getElementById('timerText').innerText = '(' + timeLeft + 's)';
              
              // Clear existing interval just in case
              if (refreshInterval) clearInterval(refreshInterval);
              
              refreshInterval = setInterval(() => {
                  timeLeft--;
                  
                  if (timeLeft > 0) {
                      document.getElementById('timerText').innerText = '(' + timeLeft + 's)';
                  } else if (timeLeft === 0) {
                      document.getElementById('timerText').innerText = '(Loading...)';
                      clearInterval(refreshInterval); // Stop timer from going minus
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
</body>
</html>











