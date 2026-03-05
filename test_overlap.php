<?php
require 'db.php';
$db = db_connect();
$q = $db->query("SELECT p1.symbol FROM prices p1 JOIN prices p2 ON p1.symbol=p2.symbol WHERE p1.date='2026-03-05' AND p2.date='2026-03-04'");
echo "Overlap: ";
while($r = $q->fetch_assoc()) echo $r['symbol'] . ' ';
echo "\nToday: ";
$q2 = $db->query("SELECT symbol FROM prices WHERE date='2026-03-05'");
while($r = $q2->fetch_assoc()) echo $r['symbol'] . ' ';
echo "\nYesterday: ";
$q3 = $db->query("SELECT symbol FROM prices WHERE date='2026-03-04'");
while($r = $q3->fetch_assoc()) echo $r['symbol'] . ' ';
?>