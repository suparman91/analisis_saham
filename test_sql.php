<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$sql = "
    SELECT today.symbol
    FROM (
        SELECT symbol, close, open, volume FROM prices WHERE date = (SELECT MAX(date) FROM prices)
    ) today
    JOIN (
        SELECT symbol, close, volume FROM prices WHERE date = (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 1 OFFSET 1)
    ) prev ON today.symbol = prev.symbol
";
$res = $mysqli->query($sql);
echo "Count: " . $res->num_rows . "\n";
