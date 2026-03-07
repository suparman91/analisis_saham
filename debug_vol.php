<?php
require_once __DIR__ . "/db.php";
$mysqli = db_connect();

$res = $mysqli->query("SELECT COUNT(*) as c, SUM(volume) as v FROM prices WHERE date='2026-03-07'");
$r = $res->fetch_assoc();
echo "Count 07: " . $r['c'] . ", Vol: " . $r['v'] . "\n";

$res = $mysqli->query("SELECT COUNT(*) as c, SUM(volume) as v FROM prices WHERE date='2026-03-06'");
$r = $res->fetch_assoc();
echo "Count 06: " . $r['c'] . ", Vol: " . $r['v'] . "\n";
?>
