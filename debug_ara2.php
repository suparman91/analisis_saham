<?php
require_once __DIR__ . "/db.php";
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
$t = microtime(true);
$res = $mysqli->query($sql);
echo "Time: " . (microtime(true) - $t) . "s\n";
echo "Rows: " . $res->num_rows . "\n";
?>
