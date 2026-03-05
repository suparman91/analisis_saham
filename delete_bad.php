<?php
require 'db.php';
$db = db_connect();

$res = $db->query("SELECT symbol FROM stocks");
$bad_symbols = [];
while($r = $res->fetch_assoc()) {
    $sym = $r['symbol'];
    // if symbol contains digits before .JK e.g. 1.JK, 100.JK
    // Or if name is exactly 4 letters
    $base = str_replace('.JK', '', $sym);
    if (is_numeric($base)) {
        $bad_symbols[] = $sym;
    }
}

echo "Found " . count($bad_symbols) . " bad symbols.\n";

if (count($bad_symbols) > 0) {
    // Delete them using single quotes in php string to build an IN clause
    $db->query("DELETE FROM stocks WHERE symbol REGEXP '^[0-9]+\\\\.JK$'");
    echo "Deleted via Regex.\n";
} else {
    echo "No bad data found.\n";
}
