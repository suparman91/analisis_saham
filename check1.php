<?php
require 'db.php';
$db = db_connect();
$r = $db->query('SELECT symbol, tanggal_prediksi, tanggal_tercapai, status FROM ara_hunter_history');
while($row = $r->fetch_assoc()) {
    print_r($row);
}
