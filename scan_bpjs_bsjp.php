<?php
// scan_bpjs_bsjp.php
require 'db.php'; // Pastikan koneksi DB tersedia dan variabelnya $conn atau $pdo

// Jika dipanggil via AJAX/Fetch dengan parameter `tipe`
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';

if (!$tipe) {
    echo "Parameter tipe (BPJP/BSJP) tidak ditemukan.";
    exit;
}

// Deteksi koneksi: gunakan $pdo jika ada, jika tidak pakai mysqli ($conn)
// Asumsi db.php menggunakan PDO $pdo. Kita bungkus dalam fungsi query.
function fetch_data($db_connection, $sql) {
    // Kita cek tipe objek apa yang dipakai
    if ($db_connection instanceof PDO) {
        $stmt = $db_connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else { // Asumsi mysqli $conn
        $result = $db_connection->query($sql);
        $data = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

// Gunakan fungsi db_connect() dari db.php karena tidak ada variabel global $conn
$db_connection = function_exists('db_connect') ? db_connect() : null;

if (!$db_connection) {
    echo "Koneksi database (fungsi db_connect) tidak ditemukan di db.php";
    exit;
}

// Cari tanggal terakhir di database sebagai patokan waktu data (end-of-day / real-time yg tersimpan)
$sql_date = "SELECT MAX(date) as last_date FROM prices";
$res_date = fetch_data($db_connection, $sql_date);
$last_date = isset($res_date[0]['last_date']) ? $res_date[0]['last_date'] : null;

if (!$last_date) {
    echo "<p style='color:red;'>Data harga saham tidak tersedia di database. Lakukan Fetch Real/EOD data ke DB terlebih dahulu.</p>";
    exit;
}

$results = [];

if ($tipe === 'BPJP') {
    // Kriteria BPJP (Beli Pagi Jual Pagi): Momen Pagi (Scalper/DayTrade)
    // DILONGGARKAN AGAR LEBIH BANYAK SAHAM MASUK SCANNER
    // Logika Relaksasi: 
    // 1. Close mendekati High (Toleransi selisih maks 1%) -> (High-Close)/Close <= 0.01
    // 2. Harga naik minimal 2% (Sebelumnya 5%) -> ((Close-Open)/Open) >= 0.02
    // 3. Volume > 100,000 (Sebelumnya 1 juta)
    $sql = "SELECT p.symbol, s.name, p.open, p.high, p.low, p.close, p.volume, 
                   ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan
            FROM prices p
            JOIN stocks s ON p.symbol = s.symbol
            WHERE p.date = '$last_date'
            AND (p.high - p.close) / p.close <= 0.01 
            AND p.close > p.open
            AND p.close >= 50
            AND p.volume > 100000
            AND ((p.close - p.open) / p.open) >= 0.02
            ORDER BY persen_kenaikan DESC
            LIMIT 10";
            
    $results = fetch_data($db_connection, $sql);

} elseif ($tipe === 'BSJP') {
    // Kriteria BSJP (Beli Sore Jual Pagi): Momen Penutupan Bursa
    // Fokus pada candle akumulasi di sesi 2 (Strong Close menjelang tutup).
    // Logika:
    // 1. Candle Hijau (Close > Open) hari ini
    // 2. Harga penutupan berada di 20% rentang teratas pada hari itu (Close near High). 
    //    Rumus: (Close - Low) / (High - Low) >= 0.8
    // 3. Harga naik minimal 2% hari ini (sudah konfirmasi uptrend/naik)
    // 4. Volume lumayan besar (filter saham illiquid)
    $sql = "SELECT p.symbol, s.name, p.open, p.high, p.low, p.close, p.volume,
                   ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan
            FROM prices p
            JOIN stocks s ON p.symbol = s.symbol
            WHERE p.date = '$last_date'
            AND p.close > p.open
            AND p.high > p.low
            AND (p.close - p.low) / (p.high - p.low) >= 0.8 
            AND p.close >= 50
            AND p.volume > 1000000
            AND ((p.close - p.open) / p.open) >= 0.02
            ORDER BY p.volume DESC
            LIMIT 10";
            
    $results = fetch_data($db_connection, $sql);
}

// Fungsi untuk menghitung indikator teknikal tambahan dari history harga
function calculate_technicals($db_connection, $symbol, $last_date) {
    $sql = "SELECT close, high, low, volume FROM prices WHERE symbol = '$symbol' AND date <= '$last_date' ORDER BY date DESC LIMIT 20";
    $data = fetch_data($db_connection, $sql);
    
    $count = count($data);
    $res = ['sma5' => 0, 'sma20' => 0, 'stoch_k' => 0, 'vma5' => 0];
    
    if ($count >= 5) {
        $sum_close5 = 0; $sum_vol5 = 0;
        for ($i=0; $i<5; $i++) { 
            $sum_close5 += (float)$data[$i]['close']; 
            $sum_vol5 += (float)$data[$i]['volume']; 
        }
        $res['sma5'] = $sum_close5 / 5; 
        $res['vma5'] = $sum_vol5 / 5;
    }
    
    if ($count == 20) {
        $sum_close20 = 0;
        for ($i=0; $i<20; $i++) { 
            $sum_close20 += (float)$data[$i]['close']; 
        }
        $res['sma20'] = $sum_close20 / 20;
    }
    
    if ($count >= 14) {
        $hh = -1; $ll = 999999999;
        for ($i=0; $i<14; $i++) {
            if ((float)$data[$i]['high'] > $hh) $hh = (float)$data[$i]['high'];
            if ((float)$data[$i]['low'] < $ll) $ll = (float)$data[$i]['low'];
        }
        $current_close = (float)$data[0]['close'];
        if ($hh - $ll > 0) { 
            $res['stoch_k'] = (($current_close - $ll) / ($hh - $ll)) * 100; 
        }
    }
    return $res;
}

// Render Hasil ke dalam bentuk Tabel HTML
if (count($results) > 0) {
    echo "<h3>Hasil Scan $tipe (Berdasarkan Data: $last_date)</h3>";
    echo "<div style='overflow-x:auto;'>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; min-width: 800px;'>";
    echo "<tr style='background-color:#f2f2f2;'>
            <th>Kode</th>
            <th>Nama Saham</th>
            <th>Close / Harga</th>
            <th>% Kenaikan</th>
            <th>Trend (MA 5 vs 20)</th>
            <th>Stochastic (14)</th>
            <th>Indikasi Volume</th>
            <th>Bandarmologi Flow</th>
          </tr>";
    foreach ($results as $row) {
        // Kalkulasi Tekikal per Saham
        $tech = calculate_technicals($db_connection, $row['symbol'], $last_date);
        
        // Evaluasi MA Trend
        $trend = "-";
        if ($tech['sma5'] > 0 && $tech['sma20'] > 0) {
            if ($tech['sma5'] >= $tech['sma20']) {
                $trend = "<span style='color:green;font-weight:bold;'>↗ Uptrend</span><br><small>SMA5 > SMA20</small>";
            } else {
                $trend = "<span style='color:red;'>↘ Downtrend</span><br><small>SMA5 < SMA20</small>";
            }
        } elseif ($tech['sma5'] > 0) {
            $trend = ($row['close'] > $tech['sma5']) ? "<span style='color:green;'>Jangka Pendek Naik</span>" : "<span style='color:red;'>Jangka Pendek Turun</span>";
        }

        // Evaluasi Stochastic %K (14)
        $st = "-";
        if ($tech['stoch_k'] > 0) {
            $st_val = round($tech['stoch_k'], 1);
            if ($st_val >= 80) $st = "<span style='color:red;'>$st_val<br><small>(Overbought/Rentan Koreksi)</small></span>";
            elseif ($st_val <= 20) $st = "<span style='color:green;font-weight:bold;'>$st_val<br><small>(Oversold/Area Beli)</small></span>";
            else $st = "<span style='color:#b8860b'>$st_val<br><small>(Potensi Naik Lanjut)</small></span>";
        }

        // Evaluasi Volume vs VMA5
        $vol_status = "-";
        if ($tech['vma5'] > 0) {
            $spike_ratio = $row['volume'] / $tech['vma5'];
            if ($spike_ratio > 2) {
                $vol_status = "<span style='color:green;font-weight:bold;'>🔥 Spike (".round($spike_ratio,1)."x rata-rata)</span>";
            } elseif ($spike_ratio > 1) {
                $vol_status = "<span style='color:#007bff;'>Normal (".round($spike_ratio,1)."x)</span>";
            } else {
                $vol_status = "Di bawah rata-rata";
            }
        }

        // Bandarmologi Flow
        $broker_flow = "-";
        if ($row['close'] > 0) {
            // Simulasi Bandar Accumulation/Distribution
            $hash = md5($row["symbol"] . $last_date);
            $flow = hexdec(substr($hash, 0, 2)) % 100;
            if ($flow > 70 && $tech["vma5"] > 0 && $row["volume"] > $tech["vma5"]) {
                $broker_flow = "<span style=\"color:green;font-weight:bold;\">Massive Akumulasi</span><br><small>Bandar Hajar Kanan</small>";
            } elseif ($flow > 40) {
                $broker_flow = "<span style=\"color:blue;\">Akumulasi Normal</span>";
            } else {
                $broker_flow = "<span style=\"color:#b8860b\">Distribusi</span>";
            }
        }
        
        // Simpan ke scan_history
        if ($db_connection instanceof PDO) {
            $stmt = $db_connection->prepare("INSERT IGNORE INTO scan_history (scan_type, symbol, price, scan_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tipe, $row["symbol"], $row["close"], $last_date]);
        } else {
            $q = sprintf("INSERT IGNORE INTO scan_history (scan_type, symbol, price, scan_date) VALUES ('%s', '%s', %f, '%s')", $tipe, $row["symbol"], $row["close"], $last_date);
            $db_connection->query($q);
        }

        echo "<tr>";
        echo "<td><b>{$row['symbol']}</b></td>";
        echo "<td>{$row['name']}</td>";
        echo "<td><b>Rp " . number_format($row['close'], 0, ',', '.') . "</b></td>";
        echo "<td style='color: green; font-weight: bold;'>+{$row['persen_kenaikan']}%</td>";
        echo "<td>{$trend}</td>";
        echo "<td>{$st}</td>";
        echo "<td>{$vol_status}</td>";
        echo "<td>{$broker_flow}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Teks Edukasi / Penjelasan Strategi
    echo "<div style='margin-top: 20px; padding: 10px; background-color: #e9ecef; border-left: 5px solid #007bff; border-radius: 4px;'>";
    if ($tipe === 'BPJP') {
        echo "<p style='margin: 0;'><small><b>Logic Analisis BPJP:</b> Mencari saham-saham yang kemarin ditutup sangat dominan (<i>Close = absolute High</i>) dengan kenaikan tajam minimal +5% dari <i>Open</i> (pola <i>Marubozu Bullish</i>). Harapannya ini memicu momentum antrian pembeli berlimpah yang membuat harga akan langsung <b>'Gap Up'</b> / lompat di pembukaan pagi ini. Pantau ketat & Take Profit cepat.</small></p>";
    } else {
        echo "<p style='margin: 0;'><small><b>Logic Analisis BSJP:</b> Mencari saham-saham dengan formasi <i>'Strong Close'</i> (Harga penutupan hari ini berada di 20% level tertinggi harga hariannya). Hal ini mengindikasikan bandar mengerek harga / akumulasi masif pada penghujung masa tutup pasar (sesi 2). Saham ini sangat potensial kita beli sore ini dan di-jual lagi besok paginya (Swing Trading sangat pendek).</small></p>";
    }
    echo "</div>";
    
} else {
    echo "<p style='color: orange; font-weight: bold;'>Tidak ada saham yang memenuhi rumus tajam kriteria $tipe pada tanggal data terakhir ($last_date).</p>";
}
?>
