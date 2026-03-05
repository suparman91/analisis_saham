<?php
require 'db.php';
$db = db_connect();

$sql = "SELECT date, COUNT(symbol) as jumlah FROM prices GROUP BY date ORDER BY date DESC LIMIT 5";
$result = $db->query($sql);
echo "Data 5 Hari Terakhir di DB:\n";
while($row = $result->fetch_assoc()) {
    echo "- Tanggal " . $row['date'] . " : " . $row['jumlah'] . " Emiten/Saham\n";
}
?>