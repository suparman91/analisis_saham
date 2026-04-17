<?php
/**
 * CRON JOB: SCRIPT UPDATE HARGA SAHAM MASSAL
 * Jalankan file ini melalui CMD / Task Scheduler / Cron Job setiap jam 16:30 WIB (Setelah market tutup)
 * Perintah CMD (contoh): php D:\xampp\htdocs\analisis_saham\update_daily_prices.php
 */

require_once __DIR__ . '/db.php';
$mysqli = db_connect();
date_default_timezone_set('Asia/Jakarta');
set_time_limit(0);

echo "Mulai mengambil daftar emiten dari database...\n";
$today = date('Y-m-d');

// Ambil daftar simbol yang sudah terisi untuk hari ini agar bisa di-skip, KECUALI jika mode Paksa Update EOD.
$updatedToday = [];
$is_force_update = isset($forceUpdate) && $forceUpdate === true;

if (!$is_force_update) {
    $resToday = $mysqli->query("SELECT symbol FROM prices WHERE date = '" . $mysqli->real_escape_string($today) . "'");
    if ($resToday) {
        while ($rt = $resToday->fetch_assoc()) {
            $updatedToday[strtoupper(trim($rt['symbol']))] = true;
        }
    }
} else {
    echo "Mode Paksa Update: Mengambil ulang SEMUA harga saham terkini...\n";
}

$symbols = [];
$res = $mysqli->query("SELECT symbol FROM stocks");
while ($r = $res->fetch_assoc()) {
    $sym = strtoupper(trim($r['symbol']));
    // Skip simbol non-ekuitas/indeks atau format tidak valid
    if ($sym === '' || !preg_match('/^[A-Z0-9]+(\.JK)?$/', $sym)) {
        continue;
    }
    // Pastikan berakhiran .JK untuk Yahoo Finance
    if (strpos($sym, '.JK') === false) {
        $sym .= '.JK';
    }
    // Skip simbol yang sudah punya harga untuk hari ini
    if (!isset($updatedToday[$sym])) {
        $symbols[] = $sym;
    }
}

$total_symbols = count($symbols);
echo "Total emiten yang perlu diupdate hari ini: $total_symbols\n";

if ($total_symbols == 0) {
    die("Semua simbol sudah terupdate untuk tanggal $today.\n");
}

// Persiapkan MySQL Statement
$stmt = $mysqli->prepare("INSERT INTO prices (symbol, date, open, high, low, close, volume) 
                          VALUES (?, ?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          open=VALUES(open), high=VALUES(high), low=VALUES(low), 
                          close=VALUES(close), volume=VALUES(volume)");

if (!$stmt) {
    die("Error prepare statement: " . $mysqli->error . "\n");
}

// Batch processing (Tarik 30 saham sekaligus biar cepat dan tidak diblokir)
$batch_size = 30;
$batches = array_chunk($symbols, $batch_size);
$total_updated = 0;

echo "Mulai mass download (Batch per $batch_size emiten)...\n\n";

foreach ($batches as $index => $batch) {
    $multi_curl = curl_multi_init();
    $curl_handles = [];

    foreach ($batch as $sym) {
        // Ambil data ringkas agar proses lebih cepat: 5 hari terakhir saja
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym) . '?range=5d&interval=1d';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        
        curl_multi_add_handle($multi_curl, $ch);
        $curl_handles[$sym] = $ch;
    }

    // Eksekusi semua curl di dalam batch ini secara BERSAMAAN (Paralel)
    $active = null;
    do {
        $mrc = curl_multi_exec($multi_curl, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($multi_curl) == -1) {
            usleep(100);
        }
        do {
            $mrc = curl_multi_exec($multi_curl, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }

    // Setelah paralel download selesai, kita parse JSON-nya
    $mysqli->begin_transaction();
    foreach ($curl_handles as $sym => $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi_curl, $ch);
        curl_close($ch);

        // Gunakan simbol dengan .JK agar cocok dengan format di tabel stocks & prices
        $clean_sym = $sym;

        if ($http_code == 200 && $response) {
            $j = json_decode($response, true);
            
            if (isset($j['chart']['result'][0])) {
                $result = $j['chart']['result'][0];
                $timestamps = $result['timestamp'] ?? [];
                
                if (!empty($timestamps) && isset($result['indicators']['quote'][0])) {
                    $quotes = $result['indicators']['quote'][0];
                    $updates_for_this_sym = 0;

                    // Simpan hanya candle valid terakhir untuk mempercepat update harian.
                    $lastIdx = -1;
                    for ($i = count($timestamps) - 1; $i >= 0; $i--) {
                        if (isset($quotes['close'][$i]) && $quotes['close'][$i] !== null) {
                            $lastIdx = $i;
                            break;
                        }
                    }

                    if ($lastIdx >= 0) {
                        $date = date('Y-m-d', $timestamps[$lastIdx]);
                        $o = $quotes['open'][$lastIdx] ?? $quotes['close'][$lastIdx];
                        $h = $quotes['high'][$lastIdx] ?? $quotes['close'][$lastIdx];
                        $l = $quotes['low'][$lastIdx] ?? $quotes['close'][$lastIdx];
                        $c = $quotes['close'][$lastIdx] ?? null;
                        $v = $quotes['volume'][$lastIdx] ?? 0;

                        if ($c !== null) {
                            $stmt->bind_param('ssddddi', $clean_sym, $date, $o, $h, $l, $c, $v);
                            if ($stmt->execute()) {
                                $total_updated++;
                                $updates_for_this_sym++;
                            }
                        }
                    }
                    if ($updates_for_this_sym > 0) {
                        echo "\033[32m[OK]\033[0m $clean_sym tersimpan ($updates_for_this_sym baris).\n";
                    } else {
                        echo "\033[33m[SKIP]\033[0m $clean_sym (Tidak ada data harga valid).\n";
                    }
                } else {
                    echo "\033[31m[EMPTY]\033[0m $clean_sym mengembalikan response JSON tak terduga.\n";
                }
            } else {
                 echo "\033[31m[FAIL]\033[0m $clean_sym gagal parsing Chart.\n";
            }
        } else {
            echo "\033[31m[ERROR]\033[0m $clean_sym HTTP $http_code\n";
        }
    }
    // Commit transaction for this batch
    $mysqli->commit();

    $batch_num = $index + 1;
    echo "Selesai Batch #$batch_num. Istirahat 2 detik supaya tidak ter-banned Yahoo...\n";
    sleep(2);
}

echo "====================================\n";
echo "PROSES SELESAI!\n";
echo "Total pergerakan harga berhasil disimpan ke database: $total_updated records.\n";
echo "====================================\n";
?>
