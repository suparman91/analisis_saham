<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// Hapus suffix .JK dari prices
$mysqli->query("UPDATE prices SET symbol = REPLACE(symbol, '.JK', '') WHERE symbol LIKE '%.JK'");

// Hapus entri yang terduplikasi jika ada (agar tidak error)
// Tetapi karena ON DUPLICATE KEY UPDATE, bisa jadi double row (BBCA dan BBCA.JK).
// Karena sudah diganti ke BBCA, bisa jadi ada duplicate error jika tanggal sama.
// Untuk amannya, cara terbaik:
// Ambil record yang ada .JK
$res = $mysqli->query("SELECT * FROM prices WHERE symbol LIKE '%.JK'");

$count = 0;
while ($row = $res->fetch_assoc()) {
    $old_sym = $row['symbol'];
    $clean_sym = str_replace('.JK', '', $old_sym);
    $date = $row['date'];
    $open = $row['open'];
    $high = $row['high'];
    $low = $row['low'];
    $close = $row['close'];
    $volume = $row['volume'];
    
    // Insert on duplicate key update ke bersih
    $stmt = $mysqli->prepare("INSERT INTO prices (symbol, date, open, high, low, close, volume) 
                          VALUES (?, ?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          open=VALUES(open), high=VALUES(high), low=VALUES(low), 
                          close=VALUES(close), volume=VALUES(volume)");
    $stmt->bind_param('ssddddi', $clean_sym, $date, $open, $high, $low, $close, $volume);
    if($stmt->execute()) {
        $count++;
    }
}

// Kemudian hapus yang ada .JK nya
$mysqli->query("DELETE FROM prices WHERE symbol LIKE '%.JK'");

echo "Fixed $count prices with .JK";
?>