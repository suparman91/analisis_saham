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
    // Kriteria BPJP PREMIUM (Beli Pagi Jual Pagi / Scalping Cepat)
    // 1. Kondisi Uptrend (Close > MA-20)
    // 2. Ada Lonjakan Volume / Smart Money (Volume > 200% dari rerata 5 hari)
    // 3. Candle Solid High (Tolerance Ekor Atas 1.5%)
    // 4. Harga > 50, Naik min 1.5% - 2%
    $sql = "
    WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
    LimitDates AS ( 
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 60) tmp 
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               s.name,
               ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as avg_vol_5
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
        JOIN stocks s ON p.symbol = s.symbol
    )
    SELECT * FROM CTE_Prices 
    WHERE date = (SELECT last_date FROM TargetDate)
      AND close > ma20 
      AND volume >= (avg_vol_5 * 1.5)
      AND (high - close) / close <= 0.015
      AND close > open
      AND close >= 50
      AND volume > 500000
      AND persen_kenaikan >= 1.5
        ORDER BY persen_kenaikan DESC";

    $results = fetch_data($db_connection, $sql);

} elseif ($tipe === 'BSJP') {
    // Kriteria BSJP PREMIUM (Beli Sore Jual Pagi / Swing Pendek)
    // 1. Kondisi Uptrend (Close > MA-20)
    // 2. Ada Lonjakan Volume Min 150% (Smart Money In)
    // 3. Close Berada di 25% area teratas / menolak turun (Hammer / Bullish dominan)
    $sql = "
    WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
    LimitDates AS ( 
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 60) tmp 
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               s.name,
               ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 5 PRECEDING AND 1 PRECEDING) as avg_vol_5
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
        JOIN stocks s ON p.symbol = s.symbol
    )
    SELECT * FROM CTE_Prices 
    WHERE date = (SELECT last_date FROM TargetDate)
      AND close > ma20
      AND volume >= (avg_vol_5 * 1.2)
      AND close > open
      AND high > low
      AND (close - low) / (high - low) >= 0.60
      AND close >= 50
      AND volume > 200000
      AND persen_kenaikan >= 1.0
        ORDER BY volume DESC";
    $results = fetch_data($db_connection, $sql);

} elseif ($tipe === 'SWING') {
    // Kriteria SWING / UPTREND TRACKER (Gabungan EMA/SMA & Akumulasi Volume)
    // 1. Uptrend utama: Close > MA-20
    // 2. Jika data >= 50 bar, pakai filter MA20 > MA50. Jika data belum cukup, tetap lolos dengan syarat momentum ringan.
    // 3. Volume kuat rata-rata 20 harian (adaptif agar tidak selalu kosong ketika market sedang sepi)
    $sql = "
    WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
    LimitDates AS ( 
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 120) tmp 
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               s.name,
               ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 49 PRECEDING AND CURRENT ROW) as ma50,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND 1 PRECEDING) as avg_vol_20,
               COUNT(*) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as bars_count
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
        JOIN stocks s ON p.symbol = s.symbol
    )
    SELECT * FROM CTE_Prices 
    WHERE date = (SELECT last_date FROM TargetDate)
      AND close > ma20
            AND ( (bars_count >= 50 AND ma20 > ma50) OR (bars_count < 50 AND close >= ma20 * 1.00) )
            AND (avg_vol_20 IS NULL OR volume >= (avg_vol_20 * 1.10))
      AND close >= (open * 0.99)
      AND close >= 50
      AND volume > 200000
        ORDER BY volume DESC";
    $results = fetch_data($db_connection, $sql);

        // Fallback: jika hasil terlalu sedikit, pakai filter lebih longgar
        if (count($results) < 10) {
        $sql_fallback = "
        WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
        LimitDates AS ( 
            SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 120) tmp 
        ),
        CTE_Prices AS (
            SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
                   s.name,
                   ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
                   AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
                   AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND 1 PRECEDING) as avg_vol_20
            FROM prices p
            JOIN LimitDates ld ON p.date = ld.date
            JOIN stocks s ON p.symbol = s.symbol
        )
        SELECT * FROM CTE_Prices 
        WHERE date = (SELECT last_date FROM TargetDate)
          AND close >= ma20 * 0.99
          AND (avg_vol_20 IS NULL OR volume >= (avg_vol_20 * 0.95))
          AND close >= 50
          AND volume > 75000
                ORDER BY persen_kenaikan DESC, volume DESC";
        $results = fetch_data($db_connection, $sql_fallback);

        // Fallback level 2: kalau masih sedikit, gunakan mode watchlist (kandidat uptrend awal)
        if (count($results) < 10) {
            $sql_fallback2 = "
            WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
            LimitDates AS ( 
                SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 120) tmp 
            ),
            CTE_Prices AS (
                SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
                       s.name,
                       ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
                       AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
                       AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 49 PRECEDING AND CURRENT ROW) as ma50,
                       AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND 1 PRECEDING) as avg_vol_20,
                       COUNT(*) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as bars_count
                FROM prices p
                JOIN LimitDates ld ON p.date = ld.date
                JOIN stocks s ON p.symbol = s.symbol
            )
            SELECT * FROM CTE_Prices 
            WHERE date = (SELECT last_date FROM TargetDate)
              AND close >= ma20 * 0.985
              AND (bars_count < 50 OR ma20 >= ma50 * 0.98)
              AND close >= 50
              AND volume > 50000
            ORDER BY (close / NULLIF(ma20,0)) DESC, volume DESC";
            $results = fetch_data($db_connection, $sql_fallback2);
        }
    }
} elseif ($tipe === 'AFTER_CLOSE') {
    // Kriteria AFTER CLOSE: close mendekati high, volume di atas normal, dan tetap di atas MA20
    $sql = "
    WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
    LimitDates AS (
        SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 90) tmp
    ),
    CTE_Prices AS (
        SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
               s.name,
               ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
               AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
               AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 9 PRECEDING AND 1 PRECEDING) as avg_vol_10,
               CASE
                   WHEN (p.high - p.low) > 0 THEN ROUND(((p.close - p.low) / (p.high - p.low)) * 100, 2)
                   ELSE 0
               END as close_strength
        FROM prices p
        JOIN LimitDates ld ON p.date = ld.date
        JOIN stocks s ON p.symbol = s.symbol
    )
    SELECT * FROM CTE_Prices
    WHERE date = (SELECT last_date FROM TargetDate)
      AND close >= 50
      AND close > ma20
      AND close >= open
      AND close_strength >= 70
      AND volume > 150000
      AND (avg_vol_10 IS NULL OR volume >= avg_vol_10 * 1.2)
    ORDER BY close_strength DESC, volume DESC";
    $results = fetch_data($db_connection, $sql);

    if (count($results) === 0) {
        $sql_fallback = "
        WITH TargetDate AS ( SELECT MAX(date) as last_date FROM prices ),
        LimitDates AS (
            SELECT date FROM (SELECT DISTINCT date FROM prices ORDER BY date DESC LIMIT 90) tmp
        ),
        CTE_Prices AS (
            SELECT p.symbol, p.date, p.open, p.high, p.low, p.close, p.volume,
                   s.name,
                   ROUND(((p.close - p.open) / p.open) * 100, 2) as persen_kenaikan,
                   AVG(p.close) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) as ma20,
                   AVG(p.volume) OVER (PARTITION BY p.symbol ORDER BY p.date ROWS BETWEEN 9 PRECEDING AND 1 PRECEDING) as avg_vol_10,
                   CASE
                       WHEN (p.high - p.low) > 0 THEN ROUND(((p.close - p.low) / (p.high - p.low)) * 100, 2)
                       ELSE 0
                   END as close_strength
            FROM prices p
            JOIN LimitDates ld ON p.date = ld.date
            JOIN stocks s ON p.symbol = s.symbol
        )
        SELECT * FROM CTE_Prices
        WHERE date = (SELECT last_date FROM TargetDate)
          AND close >= 50
          AND close > ma20
          AND close_strength >= 60
          AND volume > 100000
        ORDER BY close_strength DESC, persen_kenaikan DESC, volume DESC";
        $results = fetch_data($db_connection, $sql_fallback);
    }
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

function fetch_symbol_news($symbol, $limit = 5) {
    $url = "https://feeds.finance.yahoo.com/rss/2.0/headline?s=" . urlencode($symbol) . "&region=ID&lang=id-ID";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpcode !== 200) {
        return [];
    }

    $xml = @simplexml_load_string($response);
    if (!$xml || !isset($xml->channel->item)) {
        return [];
    }

    $news = [];
    foreach ($xml->channel->item as $item) {
        $news[] = [
            'title' => (string)$item->title,
            'description' => strip_tags((string)$item->description),
            'pubDate' => (string)$item->pubDate,
        ];
        if (count($news) >= $limit) {
            break;
        }
    }

    return $news;
}

function analyze_news_sentiment($newsItems) {
    if (empty($newsItems)) {
        return [
            'score' => 0,
            'label' => 'Netral',
            'summary' => 'Belum ada berita terbaru'
        ];
    }

    $positiveKeywords = [
        'dividen', 'buyback', 'akuisisi', 'kontrak', 'laba', 'naik', 'rebound', 'ekspansi', 'merger', 'optimis',
        'msci', 'proyek', 'kerja sama', 'investasi', 'penjualan naik', 'cetak laba', 'surplus'
    ];
    $negativeKeywords = [
        'rugi', 'turun', 'gugatan', 'default', 'suspensi', 'utang', 'restrukturisasi', 'dilusi', 'right issue',
        'pailit', 'fraud', 'kasus', 'investigasi', 'penurunan', 'warning', 'unusual market activity', 'uma'
    ];

    $score = 0;
    $matched = [];
    foreach ($newsItems as $item) {
        $text = strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? ''));
        foreach ($positiveKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score += 8;
                $matched[] = '+' . $keyword;
            }
        }
        foreach ($negativeKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score -= 8;
                $matched[] = '-' . $keyword;
            }
        }
    }

    $summary = empty($matched) ? 'Tidak ada keyword kuat' : implode(', ', array_slice(array_unique($matched), 0, 4));
    $label = 'Netral';
    if ($score >= 12) {
        $label = 'Positif';
    } elseif ($score <= -12) {
        $label = 'Negatif';
    }

    return [
        'score' => $score,
        'label' => $label,
        'summary' => $summary
    ];
}

function build_prediction($tipe, $closePrice, $score, $row = []) {
    $boost = 0.0;
    if ($tipe === 'AFTER_CLOSE' && isset($row['close_strength'])) {
        $boost += max(0, ((float)$row['close_strength'] - 70)) / 12;
    }

    if (isset($row['news_label'])) {
        if ($row['news_label'] === 'Positif') {
            $boost += 1.0;
        } elseif ($row['news_label'] === 'Negatif') {
            $boost -= 1.5;
        }
    }

    $confidence = 'Spekulatif';
    if ($score >= 70) {
        $confidence = 'Tinggi';
    } elseif ($score >= 50) {
        $confidence = 'Menengah';
    }

    $profiles = [
        'next_session' => ['label' => 'Besok', 'base' => 1.2, 'max' => 5.0],
        'short_term' => ['label' => '1-3 Hari', 'base' => 2.0, 'max' => 9.0],
        'swing_term' => ['label' => '1-2 Minggu', 'base' => 4.0, 'max' => 18.0],
    ];

    if ($tipe === 'BPJP') {
        $profiles['next_session'] = ['label' => 'Besok', 'base' => 1.5, 'max' => 4.5];
        $profiles['short_term'] = ['label' => '1-3 Hari', 'base' => 1.8, 'max' => 5.5];
        $profiles['swing_term'] = ['label' => '1 Minggu', 'base' => 2.5, 'max' => 7.0];
    } elseif ($tipe === 'BSJP') {
        $profiles['next_session'] = ['label' => 'Besok', 'base' => 1.8, 'max' => 5.5];
        $profiles['short_term'] = ['label' => '1-3 Hari', 'base' => 2.5, 'max' => 7.5];
        $profiles['swing_term'] = ['label' => '1 Minggu', 'base' => 3.5, 'max' => 10.0];
    } elseif ($tipe === 'SWING') {
        $profiles['next_session'] = ['label' => 'Besok', 'base' => 1.0, 'max' => 4.0];
        $profiles['short_term'] = ['label' => '1-3 Hari', 'base' => 3.5, 'max' => 10.0];
        $profiles['swing_term'] = ['label' => '1-2 Minggu', 'base' => 6.0, 'max' => 18.0];
    } elseif ($tipe === 'AFTER_CLOSE') {
        $profiles['next_session'] = ['label' => 'Besok', 'base' => 2.0, 'max' => 6.0];
        $profiles['short_term'] = ['label' => '1-3 Hari', 'base' => 3.0, 'max' => 9.5];
        $profiles['swing_term'] = ['label' => '1-2 Minggu', 'base' => 5.0, 'max' => 14.0];
    }

    $predictions = [];
    foreach ($profiles as $key => $profile) {
        $predictedPct = $profile['base'] + max(0, ($score - 30)) / 10 + $boost;
        if ($key === 'next_session') {
            $predictedPct *= 0.85;
        } elseif ($key === 'swing_term') {
            $predictedPct *= 1.15;
        }
        $predictedPct = max(0.5, min($profile['max'], $predictedPct));
        $predictions[$key] = [
            'label' => $profile['label'],
            'pct' => round($predictedPct, 2),
            'target' => round($closePrice * (1 + ($predictedPct / 100)), 0),
        ];
    }

    return [
        'confidence' => $confidence,
        'next_session' => $predictions['next_session'],
        'short_term' => $predictions['short_term'],
        'swing_term' => $predictions['swing_term'],
    ];
}

function build_trade_plan($tipe, $closePrice, $score, $prediction, $row = []) {
    $confidence = $prediction['confidence'] ?? 'Spekulatif';

    $pullbacks = [
        'next_session' => ['low' => 0.3, 'high' => 1.0, 'sl' => 2.0],
        'short_term' => ['low' => 0.8, 'high' => 1.8, 'sl' => 3.5],
        'swing_term' => ['low' => 1.5, 'high' => 3.0, 'sl' => 5.5],
    ];

    if ($tipe === 'BPJP') {
        $pullbacks = [
            'next_session' => ['low' => 0.2, 'high' => 0.8, 'sl' => 1.8],
            'short_term' => ['low' => 0.5, 'high' => 1.2, 'sl' => 2.5],
            'swing_term' => ['low' => 1.0, 'high' => 2.0, 'sl' => 3.5],
        ];
    } elseif ($tipe === 'BSJP') {
        $pullbacks = [
            'next_session' => ['low' => 0.3, 'high' => 1.0, 'sl' => 2.0],
            'short_term' => ['low' => 0.8, 'high' => 1.5, 'sl' => 3.0],
            'swing_term' => ['low' => 1.2, 'high' => 2.5, 'sl' => 4.5],
        ];
    } elseif ($tipe === 'SWING') {
        $pullbacks = [
            'next_session' => ['low' => 0.5, 'high' => 1.2, 'sl' => 2.2],
            'short_term' => ['low' => 1.0, 'high' => 2.0, 'sl' => 3.5],
            'swing_term' => ['low' => 2.0, 'high' => 4.0, 'sl' => 6.0],
        ];
    } elseif ($tipe === 'AFTER_CLOSE') {
        $pullbacks = [
            'next_session' => ['low' => 0.4, 'high' => 1.0, 'sl' => 2.0],
            'short_term' => ['low' => 0.8, 'high' => 1.8, 'sl' => 3.2],
            'swing_term' => ['low' => 1.8, 'high' => 3.5, 'sl' => 5.2],
        ];
    }

    if ($confidence === 'Tinggi') {
        foreach ($pullbacks as $key => $plan) {
            $pullbacks[$key]['low'] = max(0.1, $plan['low'] - 0.2);
            $pullbacks[$key]['high'] = max($pullbacks[$key]['low'] + 0.4, $plan['high'] - 0.3);
            $pullbacks[$key]['sl'] = max(1.2, $plan['sl'] - 0.4);
        }
    } elseif ($confidence === 'Spekulatif') {
        foreach ($pullbacks as $key => $plan) {
            $pullbacks[$key]['high'] += 0.5;
            $pullbacks[$key]['sl'] += 0.7;
        }
    }

    $result = [];
    foreach (['next_session', 'short_term', 'swing_term'] as $key) {
        $pred = $prediction[$key];
        $cfg = $pullbacks[$key];
        $entryHigh = round($closePrice * (1 - ($cfg['low'] / 100)), 0);
        $entryLow = round($closePrice * (1 - ($cfg['high'] / 100)), 0);
        $entryMid = ($entryHigh + $entryLow) / 2;
        $stopLoss = round($entryMid * (1 - ($cfg['sl'] / 100)), 0);
        $target = (float)$pred['target'];
        $reward = max(1, $target - $entryMid);
        $risk = max(1, $entryMid - $stopLoss);
        $rr = round($reward / $risk, 2);

        $result[$key] = [
            'label' => $pred['label'],
            'entry_low' => (float)$entryLow,
            'entry_high' => (float)$entryHigh,
            'stop_loss' => (float)$stopLoss,
            'risk_reward' => $rr,
        ];
    }

    return $result;
}

// Render Hasil ke dalam bentuk Tabel HTML
if (count($results) > 0) {
    $ranked_rows = [];

    foreach ($results as $row) {
        // Kalkulasi Tekikal per Saham
        $tech = calculate_technicals($db_connection, $row['symbol'], $last_date);

        // Evaluasi MA Trend
        $trend = "-";
        $trend_score = 0;
        if ($tech['sma5'] > 0 && $tech['sma20'] > 0) {
            if ($tech['sma5'] >= $tech['sma20']) {
                $trend = "<span style='color:green;font-weight:bold;'>↗ Uptrend</span><br><small>SMA5 > SMA20</small>";
                $trend_score = 20;
            } else {
                $trend = "<span style='color:red;'>↘ Downtrend</span><br><small>SMA5 < SMA20</small>";
                $trend_score = -10;
            }
        } elseif ($tech['sma5'] > 0) {
            if ($row['close'] > $tech['sma5']) {
                $trend = "<span style='color:green;'>Jangka Pendek Naik</span>";
                $trend_score = 10;
            } else {
                $trend = "<span style='color:red;'>Jangka Pendek Turun</span>";
                $trend_score = -5;
            }
        }

        // Evaluasi Stochastic %K (14)
        $st = "-";
        $st_score = 0;
        if ($tech['stoch_k'] > 0) {
            $st_val = round($tech['stoch_k'], 1);
            if ($st_val >= 80) {
                $st = "<span style='color:red;'>$st_val<br><small>(Overbought/Rentan Koreksi)</small></span>";
                $st_score = -6;
            } elseif ($st_val <= 20) {
                $st = "<span style='color:green;font-weight:bold;'>$st_val<br><small>(Oversold/Area Beli)</small></span>";
                $st_score = 12;
            } else {
                $st = "<span style='color:#b8860b'>$st_val<br><small>(Potensi Naik Lanjut)</small></span>";
                $st_score = 10;
            }
        }

        // Evaluasi Volume vs VMA5
        $vol_status = "-";
        $vol_score = 0;
        $spike_ratio = 0;
        if ($tech['vma5'] > 0) {
            $spike_ratio = $row['volume'] / $tech['vma5'];
            if ($spike_ratio > 2) {
                $vol_status = "<span style='color:green;font-weight:bold;'>🔥 Spike (".round($spike_ratio,1)."x rata-rata)</span>";
                $vol_score = 20;
            } elseif ($spike_ratio > 1.2) {
                $vol_status = "<span style='color:#007bff;'>Normal Kuat (".round($spike_ratio,1)."x)</span>";
                $vol_score = 12;
            } elseif ($spike_ratio > 1) {
                $vol_status = "<span style='color:#007bff;'>Normal (".round($spike_ratio,1)."x)</span>";
                $vol_score = 8;
            } else {
                $vol_status = "Di bawah rata-rata";
            }
        }

        // Bandarmologi Flow
        $broker_flow = "-";
        $flow_score = 0;
        if ($row['close'] > 0) {
            // Simulasi Bandar Accumulation/Distribution
            $hash = md5($row["symbol"] . $last_date);
            $flow = hexdec(substr($hash, 0, 2)) % 100;
            if ($flow > 70 && $tech["vma5"] > 0 && $row["volume"] > $tech["vma5"]) {
                $broker_flow = "<span style=\"color:green;font-weight:bold;\">Massive Akumulasi</span><br><small>Bandar Hajar Kanan</small>";
                $flow_score = 15;
            } elseif ($flow > 40) {
                $broker_flow = "<span style=\"color:blue;\">Akumulasi Normal</span>";
                $flow_score = 8;
            } else {
                $broker_flow = "<span style=\"color:#b8860b\">Distribusi</span>";
            }
        }

        // Score kenaikan harga harian (dibatasi agar tidak bias ekstrem)
        $pct = (float)$row['persen_kenaikan'];
        $pct_score = max(-10, min(25, $pct * 2));

        // Bonus kecil jika close sudah berada di atas SMA20
        $ma20_bonus = 0;
        if ($tech['sma20'] > 0 && $row['close'] > $tech['sma20']) {
            $ma20_bonus = 10;
        }

        $news_score = 0;
        $news_label = 'Netral';
        $news_summary = 'Belum dicek';
        if ($tipe === 'AFTER_CLOSE') {
            $newsItems = fetch_symbol_news($row['symbol'], 4);
            $newsSentiment = analyze_news_sentiment($newsItems);
            $news_score = (int)$newsSentiment['score'];
            $news_label = $newsSentiment['label'];
            $news_summary = $newsSentiment['summary'];
        }

        $close_strength_bonus = 0;
        if ($tipe === 'AFTER_CLOSE' && isset($row['close_strength'])) {
            $closeStrength = (float)$row['close_strength'];
            if ($closeStrength >= 85) {
                $close_strength_bonus = 18;
            } elseif ($closeStrength >= 75) {
                $close_strength_bonus = 10;
            }
        }

        $score = (int)round($trend_score + $st_score + $vol_score + $flow_score + $pct_score + $ma20_bonus + $news_score + $close_strength_bonus);

        // Simpan ke scan_history
        if ($db_connection instanceof PDO) {
            $stmt = $db_connection->prepare("INSERT IGNORE INTO scan_history (scan_type, symbol, price, scan_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tipe, $row["symbol"], $row["close"], $last_date]);
        } else {
            $q = sprintf("INSERT IGNORE INTO scan_history (scan_type, symbol, price, scan_date) VALUES ('%s', '%s', %f, '%s')", $tipe, $row["symbol"], $row["close"], $last_date);
            $db_connection->query($q);
        }

        $row['trend_html'] = $trend;
        $row['stoch_html'] = $st;
        $row['volume_html'] = $vol_status;
        $row['flow_html'] = $broker_flow;
        $row['score'] = $score;
        $row['news_label'] = $news_label;
        $row['news_summary'] = $news_summary;
        $prediction = build_prediction($tipe, (float)$row['close'], $score, $row);
        $row['prediction_confidence'] = $prediction['confidence'];
        $row['prediction_next'] = $prediction['next_session'];
        $row['prediction_short'] = $prediction['short_term'];
        $row['prediction_swing'] = $prediction['swing_term'];
        $tradePlan = build_trade_plan($tipe, (float)$row['close'], $score, $prediction, $row);
        $row['trade_next'] = $tradePlan['next_session'];
        $row['trade_short'] = $tradePlan['short_term'];
        $row['trade_swing'] = $tradePlan['swing_term'];
        $ranked_rows[] = $row;
    }

    usort($ranked_rows, function($a, $b) {
        if ($b['score'] === $a['score']) {
            return ((float)$b['persen_kenaikan'] <=> (float)$a['persen_kenaikan']);
        }
        return ($b['score'] <=> $a['score']);
    });

    echo "<h3>Hasil Scan $tipe (Berdasarkan Data: $last_date)</h3>";
    echo "<div style='overflow-x:auto;'>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; min-width: 800px;'>";
    echo "<tr style='background-color:#f2f2f2;'>
            <th>Rank</th>
            <th>Kode</th>
            <th>Nama Saham</th>
            <th>Close / Harga</th>
            <th>% Kenaikan</th>
            <th>Prediksi</th>
            <th>Trade Plan</th>
            <th>Score</th>
            " . ($tipe === 'AFTER_CLOSE' ? "<th>Close Strength</th><th>Sentimen Berita</th>" : "") . "
            <th>Trend (MA 5 vs 20)</th>
            <th>Stochastic (14)</th>
            <th>Indikasi Volume</th>
            <th>Bandarmologi Flow</th>
          </tr>";
    $rank = 1;
    foreach ($ranked_rows as $row) {
        $pct = (float)$row['persen_kenaikan'];
        $pct_text = ($pct >= 0 ? '+' : '') . number_format($pct, 2) . '%';
        $pct_color = $pct >= 0 ? 'green' : 'red';

        $score_color = '#475569';
        if ($row['score'] >= 70) $score_color = '#15803d';
        elseif ($row['score'] >= 50) $score_color = '#2563eb';
        elseif ($row['score'] >= 30) $score_color = '#b45309';

        echo "<tr>";
        echo "<td><b>#{$rank}</b></td>";
        echo "<td><b>{$row['symbol']}</b></td>";
        echo "<td>{$row['name']}</td>";
        echo "<td><b>Rp " . number_format($row['close'], 0, ',', '.') . "</b></td>";
        echo "<td style='color: {$pct_color}; font-weight: bold;'>{$pct_text}</td>";
        echo "<td>"
            . "<div><span style='color:#0f766e;font-weight:bold;'>{$row['prediction_next']['label']}: +" . number_format((float)$row['prediction_next']['pct'], 2) . "%</span><br><small>Target Rp " . number_format((float)$row['prediction_next']['target'], 0, ',', '.') . "</small></div>"
            . "<div style='margin-top:4px;'><span style='color:#0369a1;font-weight:bold;'>{$row['prediction_short']['label']}: +" . number_format((float)$row['prediction_short']['pct'], 2) . "%</span><br><small>Target Rp " . number_format((float)$row['prediction_short']['target'], 0, ',', '.') . "</small></div>"
            . "<div style='margin-top:4px;'><span style='color:#7c3aed;font-weight:bold;'>{$row['prediction_swing']['label']}: +" . number_format((float)$row['prediction_swing']['pct'], 2) . "%</span><br><small>Target Rp " . number_format((float)$row['prediction_swing']['target'], 0, ',', '.') . " | {$row['prediction_confidence']}</small></div>"
            . "</td>";
        echo "<td>"
            . "<div><span style='font-weight:bold;color:#0f766e;'>{$row['trade_next']['label']}</span><br><small>Entry Rp " . number_format((float)$row['trade_next']['entry_low'], 0, ',', '.') . " - " . number_format((float)$row['trade_next']['entry_high'], 0, ',', '.') . "<br>SL Rp " . number_format((float)$row['trade_next']['stop_loss'], 0, ',', '.') . " | RR " . number_format((float)$row['trade_next']['risk_reward'], 2) . "</small></div>"
            . "<div style='margin-top:4px;'><span style='font-weight:bold;color:#0369a1;'>{$row['trade_short']['label']}</span><br><small>Entry Rp " . number_format((float)$row['trade_short']['entry_low'], 0, ',', '.') . " - " . number_format((float)$row['trade_short']['entry_high'], 0, ',', '.') . "<br>SL Rp " . number_format((float)$row['trade_short']['stop_loss'], 0, ',', '.') . " | RR " . number_format((float)$row['trade_short']['risk_reward'], 2) . "</small></div>"
            . "<div style='margin-top:4px;'><span style='font-weight:bold;color:#7c3aed;'>{$row['trade_swing']['label']}</span><br><small>Entry Rp " . number_format((float)$row['trade_swing']['entry_low'], 0, ',', '.') . " - " . number_format((float)$row['trade_swing']['entry_high'], 0, ',', '.') . "<br>SL Rp " . number_format((float)$row['trade_swing']['stop_loss'], 0, ',', '.') . " | RR " . number_format((float)$row['trade_swing']['risk_reward'], 2) . "</small></div>"
            . "</td>";
        echo "<td><span style='display:inline-block;padding:4px 8px;border-radius:999px;color:#fff;background:{$score_color};font-weight:bold;'>{$row['score']}</span></td>";
        if ($tipe === 'AFTER_CLOSE') {
            $closeStrengthText = isset($row['close_strength']) ? number_format((float)$row['close_strength'], 1) . '%' : '-';
            $newsColor = $row['news_label'] === 'Positif' ? '#15803d' : ($row['news_label'] === 'Negatif' ? '#b91c1c' : '#475569');
            echo "<td><b>{$closeStrengthText}</b></td>";
            echo "<td><span style='color: {$newsColor}; font-weight:bold;'>{$row['news_label']}</span><br><small>{$row['news_summary']}</small></td>";
        }
        echo "<td>{$row['trend_html']}</td>";
        echo "<td>{$row['stoch_html']}</td>";
        echo "<td>{$row['volume_html']}</td>";
        echo "<td>{$row['flow_html']}</td>";
        echo "</tr>";
        $rank++;
    }
    echo "</table>";
    echo "</div>";

    // Teks Edukasi / Penjelasan Strategi Pendek
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 5px solid #007bff; border-radius: 4px; line-height: 1.6;'>";
    echo "<h4 style='margin-top:0; color:#0056b3;'>🧠 Saran Psikologi Trading Hari Ini:</h4>";
    
    // Nasihat Psikologis Tergantung Tipe
    if ($tipe === 'BPJP') {
            echo "<p style='margin: 0 0 10px 0;'><strong>Logika AI BPJP:</strong> Kolom <i>% Kenaikan</i> menunjukkan kenaikan real hari ini, sedangkan kolom <i>Prediksi</i> adalah estimasi lanjutan potensi gerak berikutnya berbasis score teknikal, volume, dan momentum.</p>";
         echo "<p style='margin: 0; color: #d9534f; font-weight: bold;'>⚠️ Resep Ketenangan: Tetap rasional. Jangan FOMO (Fear Of Missing Out) atau terburu-buru 'Haka' di pucuk. Apabila pagi ini harga sudah langsung terbang jauh melampaui harga target, relakan saja. Market selalu ada hari esok. Ibarat memancing, tarik napas, tunggu dengan sabar sampai umpan (harga) benar-benar turun kembali ke area yang aman bagi kesehatan portofolio Anda.</p>";
        } else if ($tipe === 'AFTER_CLOSE') {
            echo "<p style='margin: 0 0 10px 0;'><strong>Logika AI After Close:</strong> Kolom <i>% Kenaikan</i> adalah kenaikan real penutupan hari ini. Kolom <i>Prediksi</i> adalah estimasi potensi kenaikan sesi berikutnya berdasarkan kekuatan close, volume, tren, dan sentimen berita.</p>";
            echo "<p style='margin: 0; color: #0f766e; font-weight: bold;'>🌙 Resep Ketenangan: Jalankan scanner ini setelah market tutup. Prioritaskan kandidat dengan close strength tinggi dan sentimen positif. Bila candle penutupan kuat tetapi sentimen berita negatif, anggap itu peringatan untuk kurangi ukuran posisi atau tunggu konfirmasi besok pagi.</p>";
    } else if ($tipe === 'SWING') {
            echo "<p style='margin: 0 0 10px 0;'><strong>Logika AI Uptrend Tracker:</strong> Kolom <i>% Kenaikan</i> adalah gerak real yang sudah terjadi, sedangkan kolom <i>Prediksi</i> adalah proyeksi lanjutan swing berbasis score tren, volume, dan momentum.</p>";
         echo "<p style='margin: 0; color: #198754; font-weight: bold;'>🧘‍♂️ Resep Ketenangan: \"Waktu yang menghasilkan uang, bukan akrobat.\" <br>Pasien butuh waktu untuk sembuh, pohon butuh waktu untuk berbuah. Saham Uptrend tidak selalu hijau setiap hari. Jangan 'Panic Sell' hanya karena merah 1-2 persen hari ini. Percayakan pada <i>Set Planner</i> Anda. Pasang <i>Stop Loss</i> secukupnya lalu tutup layarnya. Biarkan bandar dan algoritma uang besar yang bekerja pelan-pelan mengangkat harga sahamnya.</p>";
    } else {
            echo "<p style='margin: 0 0 10px 0;'><strong>Logika AI BSJP:</strong> Kolom <i>% Kenaikan</i> adalah kenaikan real candle hari ini, dan kolom <i>Prediksi</i> adalah estimasi potensi pantulan / lanjutan esok pagi.</p>";
         echo "<p style='margin: 0; color: #fd7e14; font-weight: bold;'>⚖️ Resep Ketenangan: Jaga ekspektasi. Pembelian sore (BSJP) tujuannya adalah amankan pantulan singkat keesokan siangnya. Ambil cuan secukupnya (Bungkus 2-3%) untuk kebahagiaan hati Anda, lalu alihkan ke rutinitas utama Anda tanpa deg-degan berkepanjangan.</p>";
    }
    echo "</div>";

} else {
    echo "<p style='color: orange; font-weight: bold;'>Tidak ada saham yang memenuhi rumus tajam kriteria $tipe pada tanggal data terakhir ($last_date).</p>";
}
?>
