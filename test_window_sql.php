<?php
require 'db.php';
$db = db_connect();
$sql = "
WITH CTE_Prices AS (
    SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
           AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20
    FROM prices p
),
LatestDay AS (
    SELECT MAX(date) AS max_date FROM prices
)
SELECT CTE.symbol, round(CTE.ma20, 2) as ma20, CTE.close
FROM CTE_Prices CTE
JOIN LatestDay LD ON CTE.date = LD.max_date
LIMIT 5;
";
$r = $db->query($sql);
if ($r) { foreach($r as $row) { print_r($row); } } else { echo "Error: " . $db->error; }
