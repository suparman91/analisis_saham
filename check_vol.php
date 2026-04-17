<?php
require 'db.php';
$db = db_connect();
$res=$db->query("SELECT symbol, volume, close FROM prices WHERE date='2026-04-08' ORDER BY volume DESC LIMIT 3");
while($r=$res->fetch_assoc()) {
    echo $r['symbol'] . ' vol:' . $r['volume'] . ' close:' . $r['close'] . " => val: " . ($r['volume'] * $r['close']) . "\n";
}
