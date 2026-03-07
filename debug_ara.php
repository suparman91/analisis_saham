<?php
require_once __DIR__ . "/db.php";
$mysqli = db_connect();

$sql_screener = "
    SELECT today.symbol, today.close, today.open, today.high, today.volume, 
           prev.close as prev_close, prev.volume as prev_vol
    FROM prices today
    JOIN prices prev ON today.symbol = prev.symbol
    WHERE today.date = (SELECT MAX(date) FROM prices)
      AND prev.date = (SELECT max(date) FROM prices WHERE date < (SELECT MAX(date) FROM prices))
      AND today.close >= 50
      AND today.volume > 0
      AND (
          (today.close > prev.close AND today.volume >= prev.volume * 1.5) 
          OR 
          (today.close >= today.high * 0.98 AND today.close > prev.close) 
          OR
          (today.close >= today.open * 1.05) 
      )
";
$res_screener = $mysqli->query($sql_screener);
echo "Rows from SQL: " . $res_screener->num_rows . "\n";
?>
