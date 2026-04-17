<?php
require_once 'db.php';
$m = db_connect();

$check1 = $m->query("SHOW COLUMNS FROM users LIKE 'robo_capital'");
if ($check1 && $check1->num_rows == 0) {
    $m->query("ALTER TABLE users ADD COLUMN robo_capital DECIMAL(20,2) DEFAULT 100000000");
}

$check2 = $m->query("SHOW COLUMNS FROM users LIKE 'robo_balance'");
if ($check2 && $check2->num_rows == 0) {
    $m->query("ALTER TABLE users ADD COLUMN robo_balance DECIMAL(20,2) DEFAULT 100000000");
}

echo "Added robo_capital and robo_balance to users table.\n";
?>