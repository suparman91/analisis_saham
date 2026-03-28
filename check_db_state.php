<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

echo "=== Count per date (top 5 dates) ===\n";
$res = $mysqli->query("SELECT date, COUNT(*) as cnt FROM prices GROUP BY date ORDER BY date DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    echo $r['date'] . " => " . $r['cnt'] . " symbols\n";
}

echo "\n=== Symbols with max date < 2026-03-20 (stuck old data sample) ===\n";
$res = $mysqli->query("SELECT symbol, MAX(date) as max_date FROM prices GROUP BY symbol HAVING max_date < '2026-03-20' LIMIT 10");
$cnt = 0;
while ($r = $res->fetch_assoc()) {
    echo $r['symbol'] . " : " . $r['max_date'] . "\n";
    $cnt++;
}
if ($cnt === 0) echo "Semua simbol sudah update ke tanggal terbaru!\n";

echo "\n=== Total symbols stuck (max_date < 2026-03-20) ===\n";
$res = $mysqli->query("SELECT COUNT(*) as cnt FROM (SELECT symbol FROM prices GROUP BY symbol HAVING MAX(date) < '2026-03-20') t");
$r = $res->fetch_assoc();
echo "Total stuck: " . $r['cnt'] . "\n";
?>