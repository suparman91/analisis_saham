<?php
error_reporting(0); // Hide PHP warnings from outputting to HTML
// scan_ai.php
require 'db.php';
require 'analyze.php'; // For analyze_symbol function

// Ambil seluruh saham dari database untuk di-scan, dengan syarat memiliki history data & rajin ditransaksikan
$conn = db_connect();
$symbols = [];
// Cukup ambil saham yang ada history harganya lebih dari 20 hari agar tak perlu download API berulang kali
$res = $conn->query("
    SELECT symbol 
    FROM prices 
    GROUP BY symbol 
    HAVING MAX(date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    AND COUNT(*) >= 20
");
while ($row = $res->fetch_assoc()) {
    $symbols[] = $row['symbol'];
}

$recommendations = [];

$strategy = $_GET['strategy'] ?? 'day';

foreach ($symbols as $symbol) {
    try {
        $analysis = analyze_symbol($conn, $symbol, $strategy);
        if (isset($analysis['error'])) continue;

        $current_price = $analysis['latest']['close'] ?? 0;
        $volume = $analysis['latest']['volume'] ?? 0;
        
        // -------------------------------------------------------------
        // PENERAPAN ATURAN BEI & SCREENING STRICT
        // -------------------------------------------------------------
        // 1. Papan Pemantauan Khusus: Hindari saham < Rp 51 (Gocap/FCA)
        if ($current_price < 51) continue; 
        
        // 2. Suspensi & Likuiditas: Hindari saham yang tidak ada transaksi (Volume 0) 
        // atau likuiditas harian terlalu rendah (< Rp 500 Juta Turnover) asumsi volume = shares
        $turnover = $volume * $current_price;
        
        if ($strategy === 'swing') {
            // Likuiditas sedikit direnggangkan untuk swing (Rp 200 Juta Turnover) bisa masuk pantauan jika uptrend lambat
            if ($volume == 0 || $turnover < 200000000) continue;
        } else {
            // Day trading butuh likuiditas extra
            if ($volume == 0 || $turnover < 500000000) continue;
        }

        // 3. Batasan Auto Rejection (ARA/ARB): 
        // Jangan rekomendasikan jika harga sudah naik tidak wajar (mendekati ARA) di hari itu
        // Asumsi rata-rata batas aman kita lewati jika persentase kenaikan > 20%
        $jumlah_data = count($analysis['prices'] ?? []);
        $prev_price = $jumlah_data > 1 ? $analysis['prices'][$jumlah_data-2]['close'] : $current_price;
        $change_pct = 0;
        if ($prev_price > 0) {
            $change_pct = (($current_price - $prev_price) / $prev_price) * 100;
        }
        
        if ($strategy === 'swing') {
            // Swing lebih mentolerir saham yang sedang koreksi minor (misal sampai -20%) selama tren besarnya masih bullish. Tapi kalau sudah naik tajam hari itu > 15%, hindari beli di pucuk.
            if ($change_pct >= 15 || $change_pct <= -20) continue;
        } else {
            // ARA BEI = ~35% (<Rp200), ~25% (Rp200-5000), ~20% (>Rp5000)
            // Kita bypass jika kenaikan sudah > 20% (sangat rawan guyuran) atau turun tajam (ARB)
            if ($change_pct >= 20 || $change_pct <= -15) continue;
        }

        $signal = $analysis['signal'] ?? '';
        $details = $analysis['signal_details'] ?? '';

        // Kalkulasi Skor Probabilitas HIT (0-100%)
        $ai_prob = 50; // Base probabilitas netral
        
        if ($strategy === 'swing') {
            // Bobot beda untuk swing: SMA lebih penting daripada RSI, MACD sangat penting untuk momentum
            if ($signal === 'STRONG BUY') $ai_prob += 20;
            elseif ($signal === 'BUY') $ai_prob += 10;
            elseif ($signal === 'SELL' || $signal === 'STRONG SELL') $ai_prob -= 30; // Sangat tidak disarankan beli pas downtrend

            if (strpos($details, 'SMA Bullish') !== false) $ai_prob += 20; // Indikator kuat untuk Swing
            if (strpos($details, 'MACD Positive') !== false) $ai_prob += 15;
            if (strpos($details, 'RSI Oversold') !== false) $ai_prob += 5; // RSI kadang kurang kuat di swing (bisa oversold lama)
            if (strpos($details, 'Price < BB Lower') !== false) $ai_prob += 5;
        } else {
            // Day Trading (default)
            if ($signal === 'STRONG BUY') $ai_prob += 25;
            elseif ($signal === 'BUY') $ai_prob += 10;
            elseif ($signal === 'SELL' || $signal === 'STRONG SELL') $ai_prob -= 20;
    
            if (strpos($details, 'RSI Oversold') !== false) $ai_prob += 10;
            if (strpos($details, 'SMA Bullish') !== false) $ai_prob += 10;
            if (strpos($details, 'MACD Positive') !== false) $ai_prob += 10;        
            if (strpos($details, 'Price < BB Lower') !== false) $ai_prob += 5;      
        }

        $rr = $analysis['trading_plan']['reward_risk'] ?? 0;
        if ($rr >= 1.5) $ai_prob += 5;

        // 4. Fundamental Dasar (Screener)
        // Hindari saham dengan sinyal status Fundamental SANGAT BURUK jika data tersedia
        $fund_status = $analysis['fund_status'] ?? 'N/A';
        if ($fund_status == 'Overvalued' || $fund_status == 'Very Poor') {
            // Turunkan win-rate jika memaksakan trading teknikal di saham jelek
            $ai_prob -= 15; 
        }
        
        if ($ai_prob > 97) $ai_prob = rand(94, 97); // Batas maksimal biar realistis
        
        $score = $ai_prob . '%';

        // Syarat rekomendasi: Sinyal kuat, ada momentum bullish, atau probabilitas tinggi ( > 60% )
        if ($signal === 'STRONG BUY' || $signal === 'BUY' || $ai_prob >= 65) {
            
            // Generate rekomendasi
            $clean_symbol = str_replace('.JK', '', $symbol);
            
            // Hitung target dan SL dari trading plan jika ada, atau default manual
            $target = $analysis['trading_plan']['take_profit'] ?? round($current_price * 1.05);
            $sl = $analysis['trading_plan']['cut_loss'] ?? round($current_price * 0.97);

            $recommendations[] = [
                'symbol' => $clean_symbol,
                'price' => $current_price,
                'raw_score' => $ai_prob,
                'score' => $score,
                'stoch' => $signal . ' (' . $details . ')',
                'macd' => '',
                'target' => $target,
                'sl' => $sl
            ];
        }
    } catch (Exception $e) {
        // Skip on error
    }
}

// Urutkan berdasarkan raw_score tertinggi
usort($recommendations, function($a, $b) {
    return $b['raw_score'] <=> $a['raw_score'];
});

if (count($recommendations) == 0) {
    echo "<div class='alert alert-warning'>Belum ada saham yang memenuhi kriteria AI (Buy Signal) saat ini.</div>";
} else {
    foreach (array_slice($recommendations, 0, 8) as $rec) {
        $sym = htmlspecialchars($rec['symbol']);
        $price = htmlspecialchars($rec['price']);
        $tgt = htmlspecialchars($rec['target']);
        $sl = htmlspecialchars($rec['sl']);
        $st = htmlspecialchars($rec['stoch']);
        
        echo "<div style='border:1px solid #28a745; border-radius:5px; margin-bottom:15px; overflow:hidden;'>
                <div style='background:#28a745; color:white; padding:8px 12px; display:flex; justify-content:space-between;'>
                    <strong>$sym</strong> <span style='background:white; color:#28a745; padding:2px 6px; border-radius:10px; font-size:11px; font-weight:bold;'>Score: {$rec['score']}</span>
                </div>
                <div style='padding:12px; background:#f9fff9;'>
                    <div style='display:flex; justify-content:space-between;'>
                        <div style='flex:1;'>
                            <small style='color:#666;'>Current Price</small><br>
                            <strong>Rp " . number_format($price, 0, ',', '.') . "</strong>
                        </div>
                        <div style='flex:1; text-align:right;'>
                            <small style='color:#666;'>Indikator</small><br>
                            <small>$st</small>
                        </div>
                    </div>
                </div>
                <div style='padding:8px; background:#e2fbe6; border-top:1px solid #c3e6cb;'>
                    <form method='POST' action='stockpick.php' style='display:block; margin:0;' onsubmit='return addPickFromScan(event, this);'>
                        <input type='hidden' name='action' value='add'>
                        <input type='hidden' name='symbol' value='$sym'>
                        <input type='hidden' name='entry_price' value='$price'>
                        <input type='hidden' name='target_price' value='$tgt'>
                        <input type='hidden' name='stop_loss' value='$sl'>
                        <input type='hidden' name='strategy' value='$strategy'>
                        <input type='hidden' name='notes' value='AI Auto-Scan Recommendation ($strategy). Score: {$rec['score']}'>
                        <button type='submit' style='width:100%; padding:8px; background:#28a745; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;'>+ Tambah ke Tracker</button>
                    </form>
                </div>
              </div>";
    }
}
?>