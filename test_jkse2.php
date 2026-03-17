<?php
require 'db.php';
$mysqli = db_connect();
$mysqli->query("INSERT IGNORE INTO stocks (symbol, name) VALUES ('^JKSE', 'IHSG - Indeks Harga Saham Gabungan')");
echo $mysqli->error;
?>
