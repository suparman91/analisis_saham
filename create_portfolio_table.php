<?php
require "db.php";
$mysqli = db_connect();
$sql = "CREATE TABLE IF NOT EXISTS portfolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20),
    buy_price DECIMAL(10,2),
    target_price DECIMAL(10,2),
    added_on DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($mysqli->query($sql)) echo "Portfolio table ready.\n";
else echo "Error: " . $mysqli->error;
?>
