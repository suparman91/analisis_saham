<?php
require "db.php";
$c = db_connect();
$stocks = ["PANI.JK" => "FCA", "CUAN.JK" => "UMA", "GOTO.JK" => "FCA", "BREN.JK" => "UMA"];
foreach($stocks as $s => $n) {
    if (!$c->query("UPDATE stocks SET notation = \"$n\" WHERE symbol = \"$s\"")) {
        echo "Error: " . $c->error . "\n";
    }
}
echo "OK\n";

