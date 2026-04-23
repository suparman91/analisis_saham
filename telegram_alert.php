<?php
/**
 * Telegram Auto-Screener Alert
 * Mengecek otomatis saham potensi ARA dan mengirimkan notif ke Telegram.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
require_once __DIR__ . '/telegram_crypto.php';

$bot_token = tg_bot_token();
if ($bot_token === '') {
    exit("Telegram bot token belum dikonfigurasi.\n");
}

$mysqli = db_connect();

// Fungsi Fraksi & ARA
function getFraksi($price) {
    if ($price < 200) return 1;
    if ($price < 500) return 2;
    if ($price < 2000) return 5;
    if ($price < 5000) return 10;
    return 25;
}

function calcARA($prev) {
    if ($prev <= 50) return $prev; 
    if ($prev > 50 && $prev <= 200) { $limit = $prev + ($prev * 0.35); } 
    elseif ($prev > 200 && $prev <= 5000) { $limit = $prev + ($prev * 0.25); } 
    elseif ($prev > 5000) { $limit = $prev + ($prev * 0.20); } 
    else { $limit = $prev; }
    
    $tick = getFraksi($limit);
    return floor($limit / $tick) * $tick;
}

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

$ara_messages = [];

while ($row = $res_screener->fetch_assoc()) {
    $sym = str_replace('.JK', '', $row['symbol']);
    $c = (float)$row['close'];
    $h = (float)$row['high'];
    $h1 = (float)$row['prev_close'];
    
    $ara_limit = calcARA($h1);
    if ($ara_limit > 0) {
        $pct_to_ara = (($ara_limit - $c) / $ara_limit) * 100;
        
        $status_ara = '';
        $alasan = [];
        
        if ($c >= $ara_limit) {
            $status_ara = 'KUNCI ARA 🚀';
            $alasan[] = 'Sudah Limit Atas';
        } elseif ($c >= $h1 && $pct_to_ara <= 3 && $pct_to_ara >= 0) {
            $status_ara = 'MENGINCAR ARA 🔥';
            $alasan[] = 'Antrean bid menebal';
        } else {
            $status_ara = 'POTENSI HARI INI/BESOK ⚡';
            if ($row['volume'] >= $row['prev_vol'] * 2) $alasan[] = 'Volume Buy Spike';
            if ($c >= $row['high'] * 0.99) $alasan[] = 'Closing High (Marubozu)';
        }

        $analysis = analyze_symbol($mysqli, $row['symbol']);
        $signal = $analysis['signal'] ?? 'HOLD';
        $fund = $analysis['fund_status'] ?? 'N/A';
        $tech_detail = $analysis['signal_details'] ?? '';
        
        $prob = 50;
        if ($signal === 'STRONG BUY') $prob += 25;
        elseif ($signal === 'BUY') $prob += 10;
        if (strpos(implode(',', $alasan), 'Volume') !== false) $prob += 15;
        if ($fund === 'UNDERVALUED (Good to Buy)' || strpos($fund, 'FAIR') !== false) $prob += 10;
        if (strpos($tech_detail, 'MACD Positive') !== false) $prob += 5;
        if (strpos($tech_detail, 'RSI Oversold') !== false) $prob += 5;
        if (strpos($tech_detail, 'SMA Bullish') !== false) $prob += 5;
        if ($c >= $ara_limit) $prob = 99;
        
        $prob = min($prob, 99);

        // HANYA KIRIM ALERT JIKA PROB > 70 ATAU SEDANG KUNCI ARA / MENGINCAR ARA
        if ($prob >= 70 || strpos($status_ara, 'KUNCI') !== false || strpos($status_ara, 'MENGINCAR') !== false) {
            
            $entry = $analysis['trading_plan']['entry'] ?? $c;
            $tp = $analysis['trading_plan']['take_profit'] ?? 0;
            $sl = $analysis['trading_plan']['cut_loss'] ?? 0;

            $msg = "<b>🏷 {$sym}</b> | {$status_ara}\n";
            $msg .= "Harga: Rp".number_format($c,0,",",".")." / ARA: Rp".number_format($ara_limit,0,",",".")."\n";
            $msg .= "Sinyal: <b>{$signal}</b> (Probabilitas: {$prob}%)\n";
            $msg .= "Alasan: " . implode(', ', $alasan) . "\n";
            $msg .= "Sentimen: " . strip_tags($tech_detail) . "\n";
            $msg .= "Plan -> Beli: Rp{$entry} | TP: Rp{$tp} | SL: Rp{$sl}\n";
            
            $ara_messages[] = $msg;
        }
    }
}

// ==========================================
// 2. SCAN BTJP / BPJP (Beli Pagi Jual Pagi)
// ==========================================
// Kriteria: Pagi (Scalper/DayTrade), Uptrend, Volume Spike, Close near High
$bpjp_messages = [];
$sql_bpjp = "
    WITH TargetDate AS ( SELECT MAX(date) as max_date FROM prices ),
    LimitDates AS ( 
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 60) tmp 
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as avg_vol_5
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
    )
    SELECT * FROM CTE_Prices 
    WHERE date = (SELECT max_date FROM TargetDate)
      AND close > ma20 
      AND volume >= (avg_vol_5 * 1.5)
      AND (high - close) / close <= 0.015
      AND close > open
      AND close >= 50
      AND volume > 500000
      AND persen_kenaikan >= 1.5
    ORDER BY persen_kenaikan DESC
    LIMIT 3
";
$res_bpjp = $mysqli->query($sql_bpjp);
while ($row = $res_bpjp->fetch_assoc()) {
    $sym = str_replace('.JK', '', $row['symbol']);
    $bpjp_messages[] = "🔹 <b>{$sym}</b> (Rp".number_format($row['close'],0,",",".").") - Naik: +{$row['persen_kenaikan']}% [Uptrend & Volume 🚀]";
}

// ==========================================
// 3. SCAN BSJP (Beli Sore Jual Pagi)
// ==========================================
// Kriteria: Closing mendekati High, Uptrend, lonjakan volume min 150%
$bsjp_messages = [];
$sql_bsjp = "
    WITH TargetDate AS ( SELECT MAX(date) as max_date FROM prices ),
    LimitDates AS ( 
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 60) tmp 
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as avg_vol_5
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
    )
    SELECT * FROM CTE_Prices 
    WHERE date = (SELECT max_date FROM TargetDate)
      AND close > ma20
      AND volume >= (avg_vol_5 * 1.5)
      AND close > open
      AND high > low
      AND (close - low) / (high - low) >= 0.75
      AND close >= 50
      AND volume > 500000
      AND persen_kenaikan >= 1.5
    ORDER BY volume DESC
    LIMIT 3
";
$res_bsjp = $mysqli->query($sql_bsjp);
while ($row = $res_bsjp->fetch_assoc()) {
    $sym = str_replace('.JK', '', $row['symbol']);
    $bsjp_messages[] = "🔸 <b>{$sym}</b> (Rp".number_format($row['close'],0,",",".").") - Naik: +{$row['persen_kenaikan']}% [Akumulasi Sore 🔨]";
}

// ==========================================
// 3b. SCAN SWING / UPTREND TRACKER
// ==========================================
$swing_messages = [];
$sql_swing = "
    WITH TargetDate AS ( SELECT MAX(date) as max_date FROM prices ),
    LimitDates AS ( 
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 120) tmp 
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 49 PRECEDING AND CURRENT ROW) as ma50,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND 1 PRECEDING) as avg_vol_20
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
    )
    SELECT * FROM CTE_Prices 
    WHERE date = (SELECT max_date FROM TargetDate)
      AND close > ma20
      AND ma20 > ma50
      AND volume >= (avg_vol_20 * 1.5)
      AND close > open
      AND close >= 50
      AND volume > 500000
    ORDER BY volume DESC
    LIMIT 3
";
$res_swing = $mysqli->query($sql_swing);
if($res_swing) {
    while ($row = $res_swing->fetch_assoc()) {
        $sym = str_replace('.JK', '', $row['symbol']);
        $swing_messages[] = "📈 <b>{$sym}</b> (Rp".number_format($row['close'],0,",",".").") - [Uptrend & Volume Breakout Layer]";
    }
}

// ==========================================
// 4. AI STOCKPICK (Saham Pending / Fresh)
// ==========================================
$stockpick_messages = [];
// Asumsi tabel ada `ai_stockpicks`
$sql_stockpick = "SELECT symbol, entry_price, target_price, stop_loss, status FROM ai_stockpicks WHERE status='PENDING' ORDER BY id DESC LIMIT 3";
$res_stockpick = $mysqli->query($sql_stockpick);
if ($res_stockpick) {
    while ($row = $res_stockpick->fetch_assoc()) {
        $sym = str_replace('.JK', '', $row['symbol']);
        $stockpick_messages[] = "🎯 <b>{$sym}</b> | Masuk: Rp{$row['entry_price']} | TP: Rp{$row['target_price']} | SL: Rp{$row['stop_loss']}";
    }
}

// ==========================================
// BUILD FINAL MESSAGE
// ==========================================
if (count($ara_messages) > 0 || count($bpjp_messages) > 0 || count($bsjp_messages) > 0 || count($stockpick_messages) > 0 || count($swing_messages) > 0) {
    $title = "🚨 <b>REPORT SAHAM OTOMATIS</b> 🚨\n\n";
    $final_message = $title;

    if (count($stockpick_messages) > 0) {
        $final_message .= "🤖 <b>AI STOCKPICKS TERBARU</b>\n" . implode("\n", $stockpick_messages) . "\n\n";
    }
    if (count($swing_messages) > 0) {
        $final_message .= "🌊 <b>POTENSI SWING / UPTREND TRACKER</b>\n" . implode("\n", $swing_messages) . "\n\n";
    }
    if (count($bsjp_messages) > 0) {
        $final_message .= "🌅 <b>POTENSI BSJP (Beli Sore Jual Pagi)</b>\n" . implode("\n", $bsjp_messages) . "\n\n";
    }
    if (count($bpjp_messages) > 0) {
        $final_message .= "☕ <b>POTENSI BPJP (Beli Pagi Jual Pagi / Scalping)</b>\n" . implode("\n", $bpjp_messages) . "\n\n";
    }
    if (count($ara_messages) > 0) {
        $final_message .= "🚀 <b>ARA HUNTER & MOMENTUM TINGGI</b>\n" . implode("\n-----------------------\n", $ara_messages) . "\n";
    }
    
    // Pecah jika terlalu panjang (batas telegram 4096 karakter)
    $chunks = str_split($final_message, 4000);
    
    // -------------------------------------------------------------
    // AMBIL DATA PENERIMA DARI DATABASE (Multi-User)
    // -------------------------------------------------------------
    $res_subs = $mysqli->query("SELECT name, chat_id_encrypted FROM telegram_subscribers WHERE is_active = 1");
    $send_count = 0;
    
    while ($sub = $res_subs->fetch_assoc()) {
        $decrypted_chat_id = tg_decrypt($sub['chat_id_encrypted']);
        
        // Skip jika gagal dekripsi
        if (!$decrypted_chat_id) continue;
        
        foreach($chunks as $text) {
            $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
            $data = [
                'chat_id' => $decrypted_chat_id,
                'text' => "Hai {$sub['name']}!\n" . $text,
                'parse_mode' => 'HTML'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
        }
        $send_count++;
    }
    
    if ($send_count > 0) {
        echo "Alert sent successfully to {$send_count} users!\n";
    } else {
        echo "Error: Database mempunyai 0 penerima Telegram yang valid!\n";
    }
    // -------------------------------------------------------------

} else {
    echo "Belum ada saham dengan probabilitas tinggi hari ini.\n";
}
?>