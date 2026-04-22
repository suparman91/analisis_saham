<?php
$mysqli = new mysqli('192.168.1.223', 'nas_it', 'Nasityc@2025', 'analisis_saham');
if ($mysqli->connect_errno) {
    die('DB Error: ' . $mysqli->connect_error);
}
$res = $mysqli->query("UPDATE robo_trades SET buy_price=202 WHERE symbol='ASPR.JK' AND status='OPEN'");
if ($res) {
    echo "Update sukses";
} else {
    echo "Update gagal: " . $mysqli->error;
}
$mysqli->close();
