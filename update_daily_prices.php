<?php
/**
 * update_daily_prices.php
 * Dipanggil oleh ajax_update.php untuk fetch & simpan harga EOD hari ini.
 * Menggunakan Yahoo Finance v8 chart endpoint, dengan fallback ke v7 spark.
 * Proses saham secara batch kecil untuk hindari timeout/rate-limit.
 */

if (!defined('ABSPATH') && php_sapi_name() !== 'cli' && !isset($GLOBALS['_ajax_update_caller'])) {
    // Boleh dipanggil via require dari ajax_update.php saja (atau CLI)
    // Jika diakses langsung via browser (bukan embed), redirect ke index
    if (!isset($_SERVER['HTTP_HOST'])) {
        // CLI mode - ok
    }
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    require_once __DIR__ . '/db.php';
    $mysqli = db_connect();
}

date_default_timezone_set('Asia/Jakarta');

$today = date('Y-m-d');
$batchSize = 50; // Ambil per batch agar tidak OOM
$delayMs = 200;  // Delay antar batch (microseconds * 1000)
$totalInserted = 0;
$totalSkipped = 0;
$totalFailed = 0;

// Ambil semua simbol dari DB
$allSymbols = [];
$resSyms = $mysqli->query("SELECT symbol FROM stocks ORDER BY symbol ASC");
if ($resSyms) {
    while ($r = $resSyms->fetch_assoc()) {
        if (!empty($r['symbol'])) {
            $allSymbols[] = $r['symbol'];
        }
    }
}

if (empty($allSymbols)) {
    echo "Tidak ada simbol di tabel stocks.\n";
    return;
}

echo "Total simbol: " . count($allSymbols) . "\n";

$upsertSql = "INSERT INTO prices (symbol, date, open, high, low, close, volume)
              VALUES (?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
                open   = IF(open > 0, open, VALUES(open)),
                high   = IF(VALUES(high) > high, VALUES(high), high),
                low    = IF(VALUES(low) > 0 AND VALUES(low) < low, VALUES(low), low),
                close  = VALUES(close),
                volume = IF(VALUES(volume) > volume, VALUES(volume), volume)";
$stmt = $mysqli->prepare($upsertSql);
if (!$stmt) {
    echo "Prepare gagal: " . $mysqli->error . "\n";
    return;
}

/**
 * Fetch satu simbol via Yahoo Finance v8 chart endpoint (1 hari terakhir, interval 1d).
 * Return array ['open','high','low','close','volume'] atau false jika gagal.
 */
function udp_fetch_yahoo_eod(string $symbol) {
    $sym = strtoupper($symbol);
    if (strpos($sym, '.JK') === false) {
        $sym .= '.JK';
    }
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym)
         . '?range=5d&interval=1d&includePrePost=false';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$body || $httpCode !== 200) {
        return false;
    }

    $data = json_decode($body, true);
    if (!isset($data['chart']['result'][0]['timestamp'])) {
        return false;
    }

    $result    = $data['chart']['result'][0];
    $timestamps = $result['timestamp'];
    $quote     = $result['indicators']['quote'][0] ?? [];

    // Ambil data paling baru (last entry)
    $lastIdx = count($timestamps) - 1;
    for ($i = $lastIdx; $i >= 0; $i--) {
        $close = isset($quote['close'][$i]) ? (float)$quote['close'][$i] : 0.0;
        if ($close <= 0) continue;

        return [
            'open'   => isset($quote['open'][$i])   ? (float)$quote['open'][$i]   : $close,
            'high'   => isset($quote['high'][$i])   ? (float)$quote['high'][$i]   : $close,
            'low'    => isset($quote['low'][$i])    ? (float)$quote['low'][$i]    : $close,
            'close'  => $close,
            'volume' => isset($quote['volume'][$i]) ? (int)$quote['volume'][$i]   : 0,
            'date_ts'=> (int)$timestamps[$i],
        ];
    }
    return false;
}

// Proses batch
$batches = array_chunk($allSymbols, $batchSize);
$batchNum = 0;
foreach ($batches as $batch) {
    $batchNum++;
    echo "Batch {$batchNum}/" . count($batches) . " (" . count($batch) . " saham)... ";
    $bInserted = 0;

    foreach ($batch as $sym) {
        $eod = udp_fetch_yahoo_eod($sym);
        if (!$eod) {
            $totalFailed++;
            continue;
        }

        // Pastikan tanggal data sesuai hari ini atau kemarin (toleransi weekend)
        $dataDate = date('Y-m-d', $eod['date_ts']);
        // Ambil tanggal terakhir yang valid (max 3 hari ke belakang untuk toleransi libur/weekend)
        $diffDays = (int)((strtotime($today) - strtotime($dataDate)) / 86400);
        if ($diffDays > 3) {
            $totalSkipped++;
            continue;
        }

        $sym_db   = $sym;
        $date_db  = $dataDate;
        $open_db  = $eod['open'];
        $high_db  = $eod['high'];
        $low_db   = $eod['low'];
        $close_db = $eod['close'];
        $vol_db   = $eod['volume'];

        $stmt->bind_param('ssddddd', $sym_db, $date_db, $open_db, $high_db, $low_db, $close_db, $vol_db);
        if ($stmt->execute()) {
            $totalInserted++;
            $bInserted++;
        } else {
            $totalFailed++;
        }
    }

    echo "Masuk: {$bInserted}\n";
    // Jeda ringan antar batch agar tidak rate-limited
    if ($batchNum < count($batches)) {
        usleep($delayMs * 1000);
    }
}

$stmt->close();

echo "Selesai. Total berhasil: {$totalInserted} | Dilewati (terlalu lama): {$totalSkipped} | Gagal fetch: {$totalFailed}\n";

echo "Mulai mengambil daftar emiten dari database...\n";
$today = date('Y-m-d');
$now_time = date('H:i:s');
$day_of_week = (int)date('N');
$is_trading_day = ($day_of_week >= 1 && $day_of_week <= 5);
$market_open_time = '09:00:00';
$market_close_time = '16:15:00';
$is_pre_open = $is_trading_day && ($now_time < $market_open_time);
$is_after_close = $is_trading_day && ($now_time >= $market_close_time);

function is_placeholder_row(array $row): bool {
    $o = isset($row['open']) ? (float)$row['open'] : null;
    $h = isset($row['high']) ? (float)$row['high'] : null;
    $l = isset($row['low']) ? (float)$row['low'] : null;
    $c = isset($row['close']) ? (float)$row['close'] : null;
    $v = isset($row['volume']) ? (int)$row['volume'] : null;

    if ($o === null || $h === null || $l === null || $c === null || $v === null) {
        return false;
    }

    return ($v === 0 && $o === $h && $h === $l && $l === $c);
}

// Ambil daftar simbol yang sudah terisi untuk hari ini agar bisa di-skip, KECUALI jika mode Paksa Update EOD.
$updatedToday = [];
$is_force_update = isset($forceUpdate) && $forceUpdate === true;

// Pre-open: isi placeholder hari ini dari close valid terakhir agar semua simbol sudah punya data baseline.
// Placeholder dibuat dengan pola O=H=L=C dan volume=0 agar mudah dikenali lalu di-refresh lagi saat market buka.
$prefilled_preopen = 0;
if (!$is_force_update && $is_pre_open) {
    $missingRes = $mysqli->query("\n        SELECT s.symbol\n        FROM stocks s\n        LEFT JOIN prices p ON p.symbol = s.symbol AND p.date = '" . $mysqli->real_escape_string($today) . "'\n        WHERE p.symbol IS NULL\n          AND s.symbol LIKE '%.JK'\n          AND s.symbol <> '^JKSE'\n        ORDER BY s.symbol ASC\n    ");

    if ($missingRes) {
        $stmtPreOpen = $mysqli->prepare("\n            INSERT INTO prices (symbol, date, open, high, low, close, volume)\n            VALUES (?, ?, ?, ?, ?, ?, ?)\n            ON DUPLICATE KEY UPDATE\n                open=VALUES(open), high=VALUES(high), low=VALUES(low), close=VALUES(close), volume=VALUES(volume)\n        ");

        if ($stmtPreOpen) {
            while ($m = $missingRes->fetch_assoc()) {
                $sym = strtoupper(trim((string)$m['symbol']));
                if ($sym === '') {
                    continue;
                }

                $symEsc = $mysqli->real_escape_string($sym);
                $ref = $mysqli->query("\n                    SELECT close\n                    FROM prices\n                    WHERE symbol = '" . $symEsc . "'\n                      AND date < '" . $mysqli->real_escape_string($today) . "'\n                    ORDER BY date DESC\n                    LIMIT 1\n                ");
                $rr = $ref ? $ref->fetch_assoc() : null;
                if (!$rr || !isset($rr['close']) || $rr['close'] === null) {
                    continue;
                }

                $c = (float)$rr['close'];
                $o = $c;
                $h = $c;
                $l = $c;
                $v = 0;

                $stmtPreOpen->bind_param('ssddddi', $sym, $today, $o, $h, $l, $c, $v);
                if ($stmtPreOpen->execute()) {
                    $prefilled_preopen++;
                }
            }
            $stmtPreOpen->close();
        }
    }

    if ($prefilled_preopen > 0) {
        echo "Pre-open placeholder terisi: $prefilled_preopen simbol (baseline close terakhir).\n";
    }
}

if (!$is_force_update) {
    $resToday = $mysqli->query("SELECT symbol, open, high, low, close, volume FROM prices WHERE date = '" . $mysqli->real_escape_string($today) . "'");
    $allow_placeholder_refresh = !$is_pre_open;
    if ($resToday) {
        while ($rt = $resToday->fetch_assoc()) {
            if ($allow_placeholder_refresh && is_placeholder_row($rt)) {
                // Placeholder pre-open harus diganti data real saat market berjalan.
                continue;
            }
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

// Fallback carry-forward dibatasi setelah jam tutup bursa agar intraday tetap murni dari feed realtime.
$backfill_days = 14;
$backfilled = 0;
$allow_backfill = $is_force_update || $is_after_close;

if ($allow_backfill) {
    $missingRes = $mysqli->query("\n        SELECT s.symbol\n        FROM stocks s\n        LEFT JOIN prices p ON p.symbol = s.symbol AND p.date = '" . $mysqli->real_escape_string($today) . "'\n        WHERE p.symbol IS NULL\n          AND s.symbol LIKE '%.JK'\n          AND s.symbol <> '^JKSE'\n        ORDER BY s.symbol ASC\n    ");

    if ($missingRes) {
        $stmtBackfill = $mysqli->prepare("\n            INSERT INTO prices (symbol, date, open, high, low, close, volume)\n            VALUES (?, ?, ?, ?, ?, ?, ?)\n            ON DUPLICATE KEY UPDATE\n                open=VALUES(open), high=VALUES(high), low=VALUES(low), close=VALUES(close), volume=VALUES(volume)\n        ");

        if ($stmtBackfill) {
            while ($m = $missingRes->fetch_assoc()) {
                $sym = strtoupper(trim((string)$m['symbol']));
                if ($sym === '') {
                    continue;
                }

                $symEsc = $mysqli->real_escape_string($sym);
                $ref = $mysqli->query("\n                    SELECT open, high, low, close\n                    FROM prices\n                    WHERE symbol = '" . $symEsc . "'\n                      AND date < '" . $mysqli->real_escape_string($today) . "'\n                      AND date >= DATE_SUB('" . $mysqli->real_escape_string($today) . "', INTERVAL " . (int)$backfill_days . " DAY)\n                    ORDER BY date DESC\n                    LIMIT 1\n                ");
                $rr = $ref ? $ref->fetch_assoc() : null;
                if (!$rr || !isset($rr['close']) || $rr['close'] === null) {
                    continue;
                }

                $o = (float)($rr['open'] ?? $rr['close']);
                $h = (float)($rr['high'] ?? $rr['close']);
                $l = (float)($rr['low'] ?? $rr['close']);
                $c = (float)$rr['close'];
                $v = 0;

                $stmtBackfill->bind_param('ssddddi', $sym, $today, $o, $h, $l, $c, $v);
                if ($stmtBackfill->execute()) {
                    $backfilled++;
                }
            }
            $stmtBackfill->close();
        }
    }

    if ($backfilled > 0) {
        $total_updated += $backfilled;
        echo "\nFallback carry-forward berhasil untuk $backfilled simbol (window $backfill_days hari).\n";
    }
} else {
    echo "Fallback carry-forward dilewati (waktu sekarang $now_time, aktif setelah $market_close_time atau mode paksa).\n";
}

echo "====================================\n";
echo "PROSES SELESAI!\n";
echo "Total pergerakan harga berhasil disimpan ke database: $total_updated records.\n";
echo "====================================\n";
?>
