<?php
/**
 * CRON JOB: SCRIPT UPDATE HARGA SAHAM MASSAL
 * Jalankan file ini melalui CMD / Task Scheduler / Cron Job setiap jam 16:30 WIB (Setelah market tutup)
 * Perintah CMD (contoh): php D:\xampp\htdocs\analisis_saham\update_daily_prices.php
 */

require_once __DIR__ . '/db.php';
$mysqli = db_connect();

echo "Mulai mengambil daftar emiten dari database...\n";
$symbols = [];
$res = $mysqli->query("SELECT symbol FROM stocks");
while ($r = $res->fetch_assoc()) {
    $sym = strtoupper(trim($r['symbol']));
    // Pastikan berakhiran .JK untuk Yahoo Finance
    if (strpos($sym, '.JK') === false) {
        $sym .= '.JK';
    }
    $symbols[] = $sym;
}

$total_symbols = count($symbols);
echo "Total emiten ditemukan: $total_symbols\n";

if ($total_symbols == 0) {
    die("Database saham kosong. Silakan jalankan update_stocks.php dulu.\n");
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
        // Ambil data 6 bulan terakhir (6mo) agar rumus MA-50, MA-20, dan historis Volume bisa dihitung akurat
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym) . '?range=6mo&interval=1d';
        
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
    foreach ($curl_handles as $sym => $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi_curl, $ch);
        curl_close($ch);

        // Jangan hapus .JK jika di tabel stocks pakai .JK, atau sesuaikan dengan kebutuhan database Anda
        $clean_sym = $sym; 

        if ($http_code == 200 && $response) {
            $j = json_decode($response, true);
            
            if (isset($j['chart']['result'][0])) {
                $result = $j['chart']['result'][0];
                $timestamps = $result['timestamp'] ?? [];
                
                if (!empty($timestamps) && isset($result['indicators']['quote'][0])) {
                    $quotes = $result['indicators']['quote'][0];
                    $updates_for_this_sym = 0;

                    foreach ($timestamps as $i => $ts) {
                        $date = date('Y-m-d', $ts);
                        $o = $quotes['open'][$i] ?? null;
                        $h = $quotes['high'][$i] ?? null;
                        $l = $quotes['low'][$i] ?? null;
                        $c = $quotes['close'][$i] ?? null;
                        $v = $quotes['volume'][$i] ?? null;

                        // Pastikan data hari tsb bukan null
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
    
    curl_multi_close($multi_curl);
    
    $batch_num = $index + 1;
    echo "Selesai Batch #$batch_num. Istirahat 2 detik supaya tidak ter-banned Yahoo...\n";
    sleep(2);
}

echo "====================================\n";
echo "PROSES SELESAI!\n";
echo "Total pergerakan harga berhasil disimpan ke database: $total_updated records.\n";
echo "====================================\n";
?>
