<?php
require 'db.php'; // Sesuaikan jika ada koneksi database
date_default_timezone_set('Asia/Jakarta');

$currentTime = date('H:i');
$currentDay = date('N'); // 1 = Senin, 7 = Minggu

echo "Current time: " . $currentTime . "\n";

// Jangan jalan di hari Sabtu (6) dan Minggu (7)
if ($currentDay >= 6) {
    echo "Hari libur bursa. Tidak ada scanning.\n";
    exit;
}

// Waktu BEI (Bursa Efek Indonesia):
// Buka: 09:00 WIB  --> 10 menit sebelum: 08:50 WIB
// Tutup: 16:00 WIB --> 30 menit sebelum: 15:30 WIB

// Beri toleransi waktu eksekusi (range)
$isBPJSTime = ($currentTime >= '08:50' && $currentTime <= '09:10');
$isBSJPTime = ($currentTime >= '15:30' && $currentTime <= '16:00');

if ($isBPJSTime) {
    echo "=== Menjalankan Scanning BPJS (Beli Pagi Jual Sore) ===\n";
    scanBPJS();
} elseif ($isBSJPTime) {
    echo "=== Menjalankan Scanning BSJP (Beli Sore Jual Pagi) ===\n";
    scanBSJP();
} else {
    echo "Bukan waktunya scanning BPJS (08:50) atau BSJP (15:30).\n";
}

function scanBPJS() {
    // Logic untuk screening saham BPJS
    // Contoh parameter yg biasa dicari:
    // - Antrian bid/offer pre-opening yang tebal di bid
    // - Saham gap up tapi tidak lebih dari 3%
    // - Saham dengan volume spike kemarin
    
    echo "Mencari kandidat saham BPJS...\n";
    
    // TODO: Tambahkan logic ambil data dari database atau API (fetch_api)
    // $sql = "SELECT symbol FROM stocks WHERE ...";
    
    echo "Selesai scanning BPJS.\n";
}

function scanBSJP() {
    // Logic untuk screening saham BSJP
    // Contoh parameter yg biasa dicari:
    // - Saham yang ditarik naik sore hari (sesi 2) dengan volume tinggi
    // - Candlestick mendekati High harian (Marubozu)
    // - Breakout resistance
    
    echo "Mencari kandidat saham BSJP...\n";
    
    // TODO: Tambahkan logic ambil data intraday bar terakhir & screener
    
    echo "Selesai scanning BSJP.\n";
}
?>
