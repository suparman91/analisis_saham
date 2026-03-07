<?php
require_once __DIR__ . "/db.php";
$mysqli = db_connect();

$res = $mysqli->query("SELECT symbol FROM prices WHERE date='2026-03-07'");
$syms = [];
while ($r = $res->fetch_assoc()) {
    $syms[] = $r['symbol'];
}
echo "Symbols on 07: " . implode(', ', $syms) . "\n";
?>
