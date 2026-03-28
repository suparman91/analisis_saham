<?php
require 'db.php';
$db = db_connect();

// Ambil tanggal terbaru dengan > 200 simbol (= hari trading terakhir)
$r = $db->query("SELECT date FROM prices GROUP BY date HAVING COUNT(*) > 200 ORDER BY date DESC LIMIT 1");
$today = $r->fetch_row()[0] ?? date('Y-m-d');

// Simbol yang ADA di stocks tapi TIDAK punya data di tanggal $today
$r2 = $db->query("
    SELECT s.symbol FROM stocks s
    LEFT JOIN prices p ON p.symbol = s.symbol AND p.date = '$today'
    WHERE p.symbol IS NULL
    ORDER BY s.symbol
");
$missing = $r2->fetch_all(MYSQLI_ASSOC);

echo "Tanggal terbaru di DB: $today\n";
echo "Jumlah simbol belum diupdate: " . count($missing) . "\n\n";
foreach ($missing as $row) {
    echo $row['symbol'] . "\n";
}
