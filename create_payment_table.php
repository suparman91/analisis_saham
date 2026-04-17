<?php
require_once 'db.php';
$mysqli = db_connect();

// Buat tabel untuk payment orders Midtrans
$mysqli->query("
CREATE TABLE IF NOT EXISTS payment_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id VARCHAR(100) UNIQUE NOT NULL,
    package VARCHAR(50) NOT NULL,
    amount INT NOT NULL,
    duration INT NOT NULL, -- dalam bulan
    status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

echo "Tabel payment_orders berhasil dibuat!";
?>