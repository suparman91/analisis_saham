<?php
require 'db.php';
$db = db_connect();

$time_start = microtime(true);

$sql = "
WITH TargetDate AS ( SELECT MAX(date) as max_date FROM prices ),
LimitDates AS ( 
    SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 60) tmp 
),
CTE_Prices AS (
    SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
           AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
           AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as avg_vol_5
    FROM prices p
    JOIN LimitDates ld ON p.date = ld.date
)
SELECT CTE.symbol, round(CTE.ma20, 2) as ma20, CTE.close
FROM CTE_Prices CTE
WHERE date = (SELECT max_date FROM TargetDate)
LIMIT 5;
";

$r = $db->query($sql);
if ($r) { 
    foreach($r as $row) { print_r($row); } 
} else { 
    echo "Error: " . $db->error; 
}
$time_end = microtime(true);
echo "\nExecution time: ".($time_end - $time_start)." seconds\n";
