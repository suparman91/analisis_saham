<?php
require_once 'db.php';
$mysqli = db_connect();

$sql1 = "CREATE TABLE IF NOT EXISTS robo_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    balance DECIMAL(15,2) DEFAULT 100000000.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$mysqli->query($sql1);

$res = $mysqli->query("SELECT COUNT(*) AS c FROM robo_balance");
$row = $res->fetch_assoc();
if ($row['c'] == 0) {
    $mysqli->query("INSERT INTO robo_balance (balance) VALUES (100000000.00)");
}

$sql2 = "CREATE TABLE IF NOT EXISTS robo_trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    buy_price DECIMAL(10,2) NOT NULL,
    buy_date DATE NOT NULL,
    buy_reason VARCHAR(255) NOT NULL,
    lots INT NOT NULL,
    status ENUM('OPEN', 'CLOSED') DEFAULT 'OPEN',
    sell_price DECIMAL(10,2) DEFAULT NULL,
    sell_date DATE DEFAULT NULL,
    sell_reason VARCHAR(255) DEFAULT NULL,
    profit_loss_rp DECIMAL(15,2) DEFAULT NULL,
    profit_loss_pct DECIMAL(5,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$mysqli->query($sql2);

echo "Robo Trade Tables Created.";
