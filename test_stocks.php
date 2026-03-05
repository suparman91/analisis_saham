<?php
require 'db.php';
$db = db_connect();
$q = $db->query("SELECT symbol FROM stocks LIMIT 30");
while($r = $q->fetch_assoc()) echo $r['symbol'] . ' ';
?>