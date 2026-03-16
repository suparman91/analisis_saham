<?php
require "db.php";
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
    WHERE today.close >= 50 AND today.volume >= 1000000 
      AND today.close > prev.close
      AND (
          -- Naik 2%, Close 90% dari High, Vol 1.2x
          (today.close >= prev.close * 1.02 AND today.close >= today.high * 0.90 AND today.volume >= prev.volume * 1.2)
          OR
          -- Naik 3% Vol 2x
          (today.close >= prev.close * 1.03 AND today.volume >= prev.volume * 2)
          OR
          -- Naik > 8%
          (today.close >= prev.close * 1.08)
      )
";
$res = $mysqli->query($sql);
$count = $res->num_rows;
echo "Found $count stocks with relaxed SQL\n";


