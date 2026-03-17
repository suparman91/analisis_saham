<?php
require 'db.php';
$mysqli = db_connect();
$res = $mysqli->query("SELECT COUNT(*) FROM prices WHERE symbol='^JKSE'");
$row = $res->fetch_row();
echo "Count ^JKSE = " . $row[0];
?>
