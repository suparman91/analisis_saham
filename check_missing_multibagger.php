<?php
require 'db.php';
$db = db_connect();

$symbols = ['MHKI', 'FWCT', 'RMKO', 'ROCK', 'SOTS'];
foreach ($symbols as $s) {
    $res = $db->query("SELECT p.date, p.close, p.volume FROM prices p WHERE symbol='$s' ORDER BY p.date DESC LIMIT 2");
    echo "$s: ";
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    if (!empty($rows)) {
        foreach ($rows as $r) {
            echo $r['date'] . " => " . $r['close'] . " (Vol: " . $r['volume'] . ") | ";
        }
    } else {
        echo "No data in prices.";
    }
    
    $res2 = $db->query("SELECT * FROM stocks WHERE symbol='$s'");
    if ($res2->num_rows == 0) echo " | NOT IN stocks TABLE";
    echo "\n";
}
