<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
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
$mysqli = db_connect();

$sql = "
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
$res = $mysqli->query($sql);
while ($row = $res->fetch_assoc()) {
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

        echo "Sym: $sym, Score: $prob, stat: $status_ara ". implode(',', $alasan) . "\n";
    }
}
?>
