<?php
require "db.php";
$c = db_connect();
$res = $c->query("SELECT symbol, notation FROM stocks WHERE notation IS NOT NULL AND notation != \"\"");
while ($r = $res->fetch_assoc()) {
    echo $r["symbol"] . ": " . $r["notation"] . "\n";
}

