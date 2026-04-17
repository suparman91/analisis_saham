<?php
require_once 'db.php';
$m = db_connect();

// 1. Buat tabel Users
$m->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    subscription_end DATE DEFAULT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Tambahkan relasi user_id ke tabel portfolio (jika belum ada)
$check = $m->query("SHOW COLUMNS FROM portfolio LIKE 'user_id'");
if ($check && $check->num_rows == 0) {
    $m->query("ALTER TABLE portfolio ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id");
}

// 3. Tambahkan relasi user_id ke tabel robo_trades (jika belum ada)
$check2 = $m->query("SHOW COLUMNS FROM robo_trades LIKE 'user_id'");
if ($check2 && $check2->num_rows == 0) {
    $m->query("ALTER TABLE robo_trades ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id");
}

// 4. Buat Akun Admin/Master pertama (gratis & lifetime)
$res = $m->query("SELECT id FROM users LIMIT 1");
if ($res && $res->num_rows == 0) {
    $hash = password_hash('123456', PASSWORD_DEFAULT); // Password default
    $m->query("INSERT INTO users (name, email, password, subscription_end, role) VALUES ('Master Admin', 'admin@app.com', '$hash', '2030-12-31', 'admin')");
}

echo "Database Migration for Multi-User completed successfully.\n";
?>