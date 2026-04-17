<?php
require 'db.php';
$db = db_connect();

$symbols = ['MHKI.JK', 'FWCT.JK', 'RMKO.JK', 'ROCK.JK', 'SOTS.JK'];
foreach ($symbols as $s) {
    echo "$s:\n";
    $res = $db->query("SELECT p.date, p.close, p.volume FROM prices p WHERE p.symbol='$s' ORDER BY p.date DESC LIMIT 2");
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    if (!empty($rows)) {
        foreach ($rows as $r) {
            echo "  Date: " . $r['date'] . " => " . $r['close'] . " (Vol: " . $r['volume'] . ")\n";
        }
    } else {
        echo "  No data in prices.\n";
    }
    
    $res2 = $db->query("SELECT * FROM stocks WHERE symbol='$s'");
    if ($res2 && $res2->num_rows == 0) {
        echo "  NOT IN stocks TABLE\n";
    } elseif ($res2 && $row=$res2->fetch_assoc()) {
        echo "  Found in stocks: " . $row['name'] . "\n";
    }
    echo "\n";
}
