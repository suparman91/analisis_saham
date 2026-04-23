<?php
/**
 * Migration Script: Add user_id to watchlist & telegram_subscribers
 * Run once to update existing tables.
 * 
 * BACKUP DATABASE FIRST before running!
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!is_logged_in() || !is_admin()) {
    die("<h3>Akses Ditolak</h3>Hanya admin yang bisa menjalankan migrasi.");
}

$mysqli = db_connect();

$results = [];

// --- 1. Update tabel watchlist ---
// Check apakah kolom user_id sudah ada
$col_check = $mysqli->query("SHOW COLUMNS FROM watchlist LIKE 'user_id'");
if ($col_check->num_rows === 0) {
    // Tambah kolom id jika belum ada (watchlist lama mungkin tidak punya id)
    $id_check = $mysqli->query("SHOW COLUMNS FROM watchlist LIKE 'id'");
    if ($id_check->num_rows === 0) {
        $mysqli->query("ALTER TABLE watchlist ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
        $results[] = ['status' => '✅', 'desc' => 'Add id (AUTO_INCREMENT PK) to watchlist'];
    }

    // Tambah kolom user_id
    $r1 = $mysqli->query("ALTER TABLE watchlist ADD COLUMN user_id INT NOT NULL DEFAULT 1");
    $results[] = ['status' => $r1 ? '✅' : '❌', 'desc' => 'Add user_id to watchlist' . ($r1 ? '' : ': ' . $mysqli->error)];

    // Tambah created_at jika belum ada
    $dt_check = $mysqli->query("SHOW COLUMNS FROM watchlist LIKE 'created_at'");
    if ($dt_check->num_rows === 0) {
        $r2 = $mysqli->query("ALTER TABLE watchlist ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $results[] = ['status' => $r2 ? '✅' : '❌', 'desc' => 'Add created_at to watchlist' . ($r2 ? '' : ': ' . $mysqli->error)];
    }

    // Drop unique constraint lama bila ada (constraint `symbol`)
    $idx_check = $mysqli->query("SHOW INDEX FROM watchlist WHERE Key_name = 'symbol'");
    if ($idx_check->num_rows > 0) {
        $mysqli->query("ALTER TABLE watchlist DROP INDEX symbol");
        $results[] = ['status' => '✅', 'desc' => 'Dropped old unique index (symbol) from watchlist'];
    }

    // Tambah unique constraint (user_id, symbol)
    $r3 = $mysqli->query("ALTER TABLE watchlist ADD UNIQUE KEY unique_user_symbol (user_id, symbol)");
    $results[] = ['status' => $r3 ? '✅' : '❌', 'desc' => 'Add unique index (user_id, symbol) to watchlist' . ($r3 ? '' : ': ' . $mysqli->error)];
} else {
    $results[] = ['status' => 'ℹ️', 'desc' => 'watchlist.user_id already exists - skipped'];
}

// --- 2. Update tabel telegram_subscribers ---
$tg_col_check = $mysqli->query("SHOW COLUMNS FROM telegram_subscribers LIKE 'user_id'");
if ($tg_col_check->num_rows === 0) {
    $r4 = $mysqli->query("ALTER TABLE telegram_subscribers ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id");
    $results[] = ['status' => $r4 ? '✅' : '❌', 'desc' => 'Add user_id to telegram_subscribers' . ($r4 ? '' : ': ' . $mysqli->error)];
} else {
    $results[] = ['status' => 'ℹ️', 'desc' => 'telegram_subscribers.user_id already exists - skipped'];
}

?>
<!doctype html>
<html>
<head>
    <title>Database Migration</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f8f9fa; }
        h1 { color: #0f172a; }
        .result-row { padding: 8px; margin: 5px 0; background: #fff; border-radius: 4px; border-left: 3px solid #cbd5e1; }
    </style>
</head>
<body>
    <h1>Migration Result</h1>
    <?php foreach ($results as $r): ?>
        <div class="result-row"><?= $r['status'] ?> <?= htmlspecialchars($r['desc']) ?></div>
    <?php endforeach; ?>
    <br>
    <p>Migration selesai. <a href="app.php?page=index.php">Kembali ke App</a></p>
</body>
</html>
