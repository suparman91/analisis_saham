<?php
require_once __DIR__ . "/db.php";
$mysqli = db_connect();

$resDates = $mysqli->query("SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 5");
while ($r = $resDates->fetch_assoc()) {
    echo $r['date'] . "\n";
}
?>
