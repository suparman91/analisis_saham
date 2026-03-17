<?php
require 'db.php';
$mysqli = db_connect();
$sql = "CREATE TABLE IF NOT EXISTS ara_hunter_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(32) NOT NULL,
    tanggal_prediksi DATE NOT NULL,
    harga_kemarin DOUBLE DEFAULT 0,
    harga_terakhir DOUBLE DEFAULT 0,
    batas_ara DOUBLE DEFAULT 0,
    probabilitas INT DEFAULT 0,
    status VARCHAR(50),
    alasan TEXT,
    tanggal_tercapai DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ara_pred (symbol, tanggal_prediksi)
)";
if ($mysqli->query($sql)) { echo "Table created"; } else { echo "Error: " . $mysqli->error; }
?>
