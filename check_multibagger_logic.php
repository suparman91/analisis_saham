<?php
require 'db.php';
$db = db_connect();

$today = '2026-04-08';
$yesterday = '2026-04-07';

$sqlMarket = "
SELECT 
    p1.symbol, 
    s.name, 
    s.notation,
    p1.close, 
    p2.close as prev_close,
    ((p1.close - p2.close) / p2.close * 100) as pct_change,
    p1.volume
FROM prices p1
JOIN prices p2 ON p1.symbol = p2.symbol AND p2.date = '$yesterday'
JOIN stocks s ON p1.symbol = s.symbol
WHERE p1.date = '$today' AND p1.symbol IN ('MHKI.JK', 'FWCT.JK', 'RMKO.JK', 'ROCK.JK', 'SOTS.JK')
";

$res = $db->query($sqlMarket);
$stocks = $res->fetch_all(MYSQLI_ASSOC);

foreach ($stocks as $s) {
    $val = $s['volume'] * $s['close'];
    echo "Sym: {$s['symbol']} | Pct: {$s['pct_change']}% | Vol: {$s['volume']} | Val: {$val}\n";
    if ($val > 1000000000 && $s['pct_change'] >= 3) {
        echo "  [PASS] multibagger criteria\n";
    } else {
        echo "  [FAIL] val: " . ($val > 1000000000) . " pct: " . ($s['pct_change'] >= 3) . "\n";
    }
}
