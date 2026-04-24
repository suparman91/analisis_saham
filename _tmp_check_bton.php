<?php
require_once __DIR__ . '/db.php';
$m = db_connect();

echo "=== robo_trades BTON ===\n";
$sql1 = "SELECT id,user_id,symbol,status,buy_date,sell_date,buy_price,sell_price,lots,sell_reason FROM robo_trades WHERE symbol='BTON.JK' ORDER BY id DESC LIMIT 50";
$r1 = $m->query($sql1);
if ($r1) {
    while ($row = $r1->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} else {
    echo "ERR1: " . $m->error . PHP_EOL;
}

echo "=== robo_audit_log BTON ===\n";
$sql2 = "SELECT id,user_id,symbol,action,price,lots,reason,created_at FROM robo_audit_log WHERE symbol='BTON.JK' ORDER BY id DESC LIMIT 100";
$r2 = $m->query($sql2);
if ($r2) {
    while ($row = $r2->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} else {
    echo "ERR2: " . $m->error . PHP_EOL;
}
