<?php
/**
 * fetch_fundamentals_cron.php
 * ===========================
 * Updater otomatis data fundamental (PE, PBV, ROE, EPS).
 *
 * Sumber data (urutan prioritas):
 *   1. IDX.co.id  — bulk fetch semua saham sekaligus (PE, PBV). Resmi, gratis.
 *   2. Yahoo Finance v10 + crumb auth — per-simbol (PE, PBV, ROE, EPS). Gratis, unofficial.
 *      Yahoo Finance sejak ~2023 wajib pakai cookie + crumb, tanpanya semua return null.
 *
 * Cara pakai:
 *   - Browser  : http://localhost/analisis_saham/fetch_fundamentals_cron.php  (admin only)
 *   - CLI/cron : php fetch_fundamentals_cron.php  (tidak butuh login)
 *   - Cron job : 0 18 * * 5  php /path/to/fetch_fundamentals_cron.php  (setiap Jumat jam 18:00)
 *
 * Rate limiting: 1 detik per request Yahoo supaya tidak diblokir.
 * Caching      : skip simbol yang sudah diupdate hari ini.
 */

// Browser: wajib login sebagai admin
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/auth.php';
    require_admin();

    // Tampilkan halaman konfirmasi dulu jika belum klik Start
    if (!isset($_GET['start']) || $_GET['start'] !== '1') {
        require_once __DIR__ . '/db.php';
        $mysqli_tmp = db_connect();
        $fund_last  = null;
        $fund_total = 0;
        $chkTmp = $mysqli_tmp->query("SELECT MAX(fetched_at) as last_at, COUNT(DISTINCT symbol) as total FROM fundamentals");
        if ($chkTmp && $rowTmp = $chkTmp->fetch_assoc()) {
            $fund_last  = $rowTmp['last_at'];
            $fund_total = (int)$rowTmp['total'];
        }
        $sym_count = 0;
        $chkSym = $mysqli_tmp->query("SELECT COUNT(DISTINCT symbol) as c FROM prices WHERE symbol LIKE '%.JK'");
        if ($chkSym && $rowSym = $chkSym->fetch_assoc()) $sym_count = (int)$rowSym['c'];
        ?>
        <!DOCTYPE html><html><head><meta charset="utf-8">
        <title>Update Fundamental</title>
        <style>
        body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;padding:30px;margin:0;}
        .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:28px 32px;max-width:560px;margin:0 auto;}
        h2{margin:0 0 6px 0;color:#60a5fa;font-size:20px;}
        .sub{color:#94a3b8;font-size:14px;margin-bottom:24px;}
        .info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #334155;font-size:14px;}
        .info-row:last-of-type{border-bottom:none;}
        .label{color:#94a3b8;}
        .val{font-weight:600;color:#f1f5f9;}
        .warn{background:#7f1d1d;border:1px solid #ef4444;color:#fca5a5;padding:12px 16px;border-radius:8px;margin:20px 0;font-size:13px;}
        .ok{background:#052e16;border:1px solid #16a34a;color:#86efac;padding:12px 16px;border-radius:8px;margin:20px 0;font-size:13px;}
        .btn-start{display:inline-block;background:#3b82f6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;margin-right:10px;}
        .btn-start:hover{background:#2563eb;}
        .btn-back{display:inline-block;background:#475569;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-size:15px;}
        .btn-back:hover{background:#334155;}
        .note{color:#64748b;font-size:12px;margin-top:16px;}
        </style></head><body>
        <div class="card">
            <h2>&#x1F504; Update Data Fundamental</h2>
            <p class="sub">Fetch PE, PBV, ROE, EPS dari IDX.co.id &amp; Yahoo Finance.</p>
            <div class="info-row"><span class="label">Jumlah simbol .JK</span><span class="val"><?= $sym_count ?> saham</span></div>
            <div class="info-row"><span class="label">Data tersimpan</span><span class="val"><?= $fund_total ?> saham</span></div>
            <div class="info-row"><span class="label">Terakhir diupdate</span><span class="val"><?= $fund_last ? date('d M Y H:i', strtotime($fund_last)) : 'Belum pernah' ?></span></div>
            <div class="info-row"><span class="label">Estimasi durasi</span><span class="val">~<?= ceil($sym_count / 60) ?> menit</span></div>
            <?php
            $overdue = !$fund_last || (time() - strtotime($fund_last)) > 7*86400;
            if ($overdue): ?>
            <div class="warn">&#x26A0; Data sudah lebih dari 7 hari — sebaiknya diperbarui sekarang.</div>
            <?php else: ?>
            <div class="ok">&#x2705; Data masih fresh. Update opsional saja.</div>
            <?php endif; ?>
            <div style="margin-top:20px;">
                <a href="fetch_fundamentals_cron.php?start=1" class="btn-start">&#x25B6; Mulai Update</a>
                <a href="app.php?page=admin.php" class="btn-back">Batal</a>
            </div>
            <p class="note">Proses akan berjalan di halaman ini. Jangan tutup tab sampai selesai.<br>Rate limit: 1 detik/simbol dari Yahoo Finance.</p>
        </div>
        </body></html>
        <?php
        exit;
    }

    // Klik Start — mulai proses
    set_time_limit(0);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>Update Fundamental</title>';
    echo '<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:20px;}';
    echo 'pre{white-space:pre-wrap;word-break:break-all;line-height:1.6;}';
    echo '.back{display:inline-block;margin-bottom:20px;padding:8px 16px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:14px;}';
    echo '</style></head><body>';
    echo '<a href="app.php?page=admin.php" class="back">&laquo; Kembali ke Admin</a>';
    echo '<h2 style="font-family:sans-serif;color:#60a5fa;">&#x1F504; Update Data Fundamental &mdash; Sedang Berjalan...</h2>';
    echo '<pre>';
    ob_implicit_flush(true);
    if (ob_get_level()) ob_end_flush();
}

require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// ── Pastikan tabel fundamentals ada ─────────────────────────────────────────
$mysqli->query("
    CREATE TABLE IF NOT EXISTS fundamentals (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        symbol    VARCHAR(30) NOT NULL,
        date      DATE NOT NULL,
        pe        DECIMAL(10,4) DEFAULT NULL,
        pbv       DECIMAL(10,4) DEFAULT NULL,
        roe       DECIMAL(10,4) DEFAULT NULL,
        eps       DECIMAL(14,4) DEFAULT NULL,
        de        DECIMAL(10,4) DEFAULT NULL,
        revenue   BIGINT DEFAULT NULL,
        net_income BIGINT DEFAULT NULL,
        book_value DECIMAL(14,4) DEFAULT NULL,
        currency  VARCHAR(10) DEFAULT NULL,
        fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_symbol_date (symbol, date),
        INDEX idx_symbol (symbol)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tambah kolom baru jika tabel lama belum punya
foreach (['de', 'revenue', 'net_income', 'book_value', 'currency', 'fetched_at'] as $col) {
    $check = $mysqli->query("SHOW COLUMNS FROM fundamentals LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $defs = [
            'de'         => 'DECIMAL(10,4) DEFAULT NULL',
            'revenue'    => 'BIGINT DEFAULT NULL',
            'net_income' => 'BIGINT DEFAULT NULL',
            'book_value' => 'DECIMAL(14,4) DEFAULT NULL',
            'currency'   => "VARCHAR(10) DEFAULT NULL",
            'fetched_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
        $mysqli->query("ALTER TABLE fundamentals ADD COLUMN `$col` {$defs[$col]}");
    }
}

// ── Ambil semua simbol .JK dari tabel prices ────────────────────────────────
$today = date('Y-m-d');
$res = $mysqli->query("SELECT DISTINCT symbol FROM prices WHERE symbol LIKE '%.JK' ORDER BY symbol");
if (!$res) {
    echo "ERROR: Tidak bisa ambil daftar simbol dari tabel prices.\n";
    exit(1);
}

$symbols = [];
while ($r = $res->fetch_assoc()) {
    $symbols[] = $r['symbol'];
}
$total = count($symbols);
echo "Total simbol .JK: $total\n";
echo "Tanggal update: $today\n";
echo str_repeat('-', 60) . "\n";

// ── Indeks simbol yang sudah diupdate hari ini (skip) ───────────────────────
$alreadyDone = [];
$resExisting = $mysqli->query("SELECT symbol FROM fundamentals WHERE date = '$today'");
if ($resExisting) {
    while ($r = $resExisting->fetch_assoc()) {
        $alreadyDone[$r['symbol']] = true;
    }
}
echo 'Sudah ada hari ini: ' . count($alreadyDone) . " simbol, akan di-skip.\n\n";

// ════════════════════════════════════════════════════════════════════════════
// SUMBER 1: IDX.co.id — bulk fetch semua saham (PE + PBV, resmi & gratis)
// ════════════════════════════════════════════════════════════════════════════
/**
 * Ambil semua data fundamental dari idx.co.id sekaligus.
 * Endpoint ini dipakai oleh website resmi IDX — gratis, tidak butuh API key.
 * Return: array ['BBCA' => ['pe'=>..., 'pbv'=>...], ...]  (tanpa .JK)
 */
function fetch_idx_bulk(): array {
    $result = [];
    $start  = 0;
    $length = 500;

    do {
        $url = "https://idx.co.id/primary/TradingSummary/GetStockWithFundamental"
             . "?start={$start}&length={$length}&indexCode=&sectors=&subsectors=&stockCode=";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $json   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$json || $status !== 200) break;

        $data = json_decode($json, true);

        // IDX returns: {"draw":1,"recordsTotal":N,"recordsFiltered":N,"data":[...]}
        $rows  = $data['data']          ?? [];
        $total = $data['recordsTotal']  ?? 0;

        foreach ($rows as $row) {
            $code = strtoupper(trim($row['StockCode'] ?? $row['stockCode'] ?? ''));
            if ($code === '') continue;

            $pe  = isset($row['PER'])  && $row['PER']  !== '-' ? (float)$row['PER']  : null;
            $pbv = isset($row['PBV'])  && $row['PBV']  !== '-' ? (float)$row['PBV']  : null;
            // IDX juga kadang menyediakan EPS
            $eps = isset($row['EPS'])  && $row['EPS']  !== '-' ? (float)$row['EPS']  : null;

            $result[$code] = ['pe' => $pe, 'pbv' => $pbv, 'eps' => $eps];
        }

        $start += $length;
    } while ($start < $total);

    return $result;
}

// ════════════════════════════════════════════════════════════════════════════
// SUMBER 2: Yahoo Finance — per-simbol + crumb authentication
// ════════════════════════════════════════════════════════════════════════════
/**
 * Dapatkan cookie + crumb Yahoo Finance.
 * Sejak ~2023, Yahoo Finance wajib crumb — tanpanya semua quoteSummary return null/401.
 */
function get_yahoo_session(): ?array {
    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yf_cookies_' . getmypid() . '.txt';

    // Step 1: Kunjungi Yahoo Finance untuk dapat cookies (termasuk consent)
    $ch = curl_init('https://finance.yahoo.com/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.5'],
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Step 2: Ambil crumb token
    $ch = curl_init('https://query1.finance.yahoo.com/v1/test/getcrumb');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
    ]);
    $crumb  = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$crumb || $status !== 200 || strlen(trim($crumb)) < 3 || trim($crumb) === 'null') {
        return null;
    }

    return ['crumb' => trim($crumb), 'cookieFile' => $cookieFile];
}

/**
 * Fetch fundamental satu simbol dari Yahoo Finance (butuh crumb).
 * Mengisi ROE + EPS yang tidak tersedia di IDX.co.id.
 */
function fetch_yahoo_one(string $symbol, string $crumb, string $cookieFile): ?array {
    $url = 'https://query2.finance.yahoo.com/v10/finance/quoteSummary/'
         . urlencode($symbol)
         . '?modules=defaultKeyStatistics,financialData,price&crumb=' . urlencode($crumb);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en-US,en;q=0.5'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $json   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$json || $status !== 200) return null;

    $data = json_decode($json, true);
    if (!isset($data['quoteSummary']['result'][0])) return null;

    $r     = $data['quoteSummary']['result'][0];
    $fd    = $r['financialData']        ?? [];
    $ks    = $r['defaultKeyStatistics'] ?? [];
    $price = $r['price']                ?? [];

    $eps = $ks['trailingEps']['raw']  ?? null;
    $pe  = $ks['trailingPE']['raw']   ?? null;
    $pbv = $ks['priceToBook']['raw']  ?? null;

    $roe = null;
    if (isset($fd['returnOnEquity']['raw'])) {
        $roe = (float)$fd['returnOnEquity']['raw'] * 100;
    }

    $de        = $fd['debtToEquity']['raw']     ?? null;
    $revenue   = $fd['totalRevenue']['raw']      ?? null;
    $netIncome = $fd['netIncomeToCommon']['raw'] ?? null;
    $bookValue = $fd['bookValue']['raw']         ?? null;
    $currency  = $price['currency']              ?? null;

    if ($roe === null && $netIncome !== null && $bookValue !== null && (float)$bookValue != 0) {
        $roe = ((float)$netIncome / (float)$bookValue) * 100;
    }

    if ($pe === null && $pbv === null && $roe === null && $eps === null) return null;

    return [
        'pe'         => $pe,
        'pbv'        => $pbv,
        'roe'        => $roe,
        'eps'        => $eps,
        'de'         => $de,
        'revenue'    => $revenue   !== null ? (int)$revenue   : null,
        'net_income' => $netIncome !== null ? (int)$netIncome : null,
        'book_value' => $bookValue,
        'currency'   => $currency,
    ];
}

// ════════════════════════════════════════════════════════════════════════════
// Fase 1: Bulk fetch dari IDX.co.id
// ════════════════════════════════════════════════════════════════════════════
echo "Fase 1: Fetch bulk dari IDX.co.id...\n";
$idxData = fetch_idx_bulk();
echo "  => Dapat " . count($idxData) . " saham dari IDX.co.id\n\n";

// ════════════════════════════════════════════════════════════════════════════
// Fase 2: Yahoo Finance crumb session
// ════════════════════════════════════════════════════════════════════════════
echo "Fase 2: Inisialisasi sesi Yahoo Finance (crumb auth)...\n";
$yahooSession = get_yahoo_session();
if ($yahooSession) {
    echo "  => Crumb OK: " . substr($yahooSession['crumb'], 0, 6) . "...\n\n";
} else {
    echo "  => GAGAL mendapatkan crumb — akan pakai IDX saja (tanpa ROE/EPS dari Yahoo)\n\n";
}

// ════════════════════════════════════════════════════════════════════════════
// Fase 3: Loop per simbol — gabungkan IDX + Yahoo
// ════════════════════════════════════════════════════════════════════════════
$updated  = 0;
$skipped  = 0;
$idxOnly  = 0;
$yahooOk  = 0;
$notFound = 0;
$errors   = 0;

$stmt = $mysqli->prepare("
    INSERT INTO fundamentals (symbol, date, pe, pbv, roe, eps, de, revenue, net_income, book_value, currency, fetched_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        pe = VALUES(pe), pbv = VALUES(pbv), roe = VALUES(roe), eps = VALUES(eps),
        de = VALUES(de), revenue = VALUES(revenue), net_income = VALUES(net_income),
        book_value = VALUES(book_value), currency = VALUES(currency),
        fetched_at = NOW()
");

if (!$stmt) {
    echo "ERROR: Gagal prepare statement: " . $mysqli->error . "\n";
    exit(1);
}

foreach ($symbols as $i => $symbol) {
    $num = $i + 1;

    if (isset($alreadyDone[$symbol])) {
        echo "[$num/$total] SKIP  $symbol (sudah ada hari ini)\n";
        $skipped++;
        continue;
    }

    // Kode saham tanpa .JK untuk lookup IDX (IDX pakai BBCA, bukan BBCA.JK)
    $code = strtoupper(str_replace('.JK', '', $symbol));

    // ── Ambil data IDX (PE + PBV) ────────────────────────────────────────
    $idxRow = $idxData[$code] ?? null;

    // ── Ambil data Yahoo (ROE + EPS + detail lainnya) ────────────────────
    $yahooRow = null;
    if ($yahooSession) {
        $yahooRow = fetch_yahoo_one($symbol, $yahooSession['crumb'], $yahooSession['cookieFile']);
        usleep(1000000); // 1 detik rate limit
        if (php_sapi_name() !== 'cli') flush();
    }

    // ── Gabungkan: Yahoo lebih lengkap, IDX sebagai fallback PE/PBV ──────
    if ($yahooRow !== null) {
        $f = $yahooRow;
        // Isi PE/PBV dari IDX jika Yahoo tidak punya
        if ($f['pe']  === null && $idxRow !== null) $f['pe']  = $idxRow['pe'];
        if ($f['pbv'] === null && $idxRow !== null) $f['pbv'] = $idxRow['pbv'];
        if ($f['eps'] === null && $idxRow !== null) $f['eps'] = $idxRow['eps'];
        $source = 'YAHOO';
        $yahooOk++;
    } elseif ($idxRow !== null && ($idxRow['pe'] !== null || $idxRow['pbv'] !== null)) {
        $f = [
            'pe'         => $idxRow['pe'],
            'pbv'        => $idxRow['pbv'],
            'roe'        => null,
            'eps'        => $idxRow['eps'],
            'de'         => null,
            'revenue'    => null,
            'net_income' => null,
            'book_value' => null,
            'currency'   => 'IDR',
        ];
        $source = 'IDX';
        $idxOnly++;
    } else {
        echo "[$num/$total] -     $symbol (tidak ada data di IDX maupun Yahoo)\n";
        $notFound++;
        continue;
    }

    $stmt->bind_param(
        'ssdddddiids',
        $symbol,
        $today,
        $f['pe'],
        $f['pbv'],
        $f['roe'],
        $f['eps'],
        $f['de'],
        $f['revenue'],
        $f['net_income'],
        $f['book_value'],
        $f['currency']
    );

    if ($stmt->execute()) {
        $peStr  = $f['pe']  !== null ? number_format((float)$f['pe'],  2) : '-';
        $pbvStr = $f['pbv'] !== null ? number_format((float)$f['pbv'], 2) : '-';
        $roeStr = $f['roe'] !== null ? number_format((float)$f['roe'], 2) . '%' : '-';
        $epsStr = $f['eps'] !== null ? number_format((float)$f['eps'], 2) : '-';
        echo "[$num/$total] OK [$source]  $symbol | PE=$peStr PBV=$pbvStr ROE=$roeStr EPS=$epsStr\n";
        $updated++;
    } else {
        echo "[$num/$total] ERROR $symbol: " . $stmt->error . "\n";
        $errors++;
    }

    if (php_sapi_name() !== 'cli') flush();
}

$stmt->close();

// Bersihkan cookie file sementara
if ($yahooSession && file_exists($yahooSession['cookieFile'])) {
    @unlink($yahooSession['cookieFile']);
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Selesai      : $today\n";
echo "Diupdate     : $updated  (Yahoo=$yahooOk, IDX-only=$idxOnly)\n";
echo "Di-skip      : $skipped\n";
echo "Tidak ada    : $notFound\n";
echo "Error        : $errors\n";

if (php_sapi_name() !== 'cli') {
    echo '</pre>';
    echo '<p style="font-family:sans-serif;color:#86efac;font-size:16px;">&#x2705; Selesai. Data fundamental telah diperbarui.</p>';
    echo '<a href="app.php?page=admin.php" class="back">Kembali ke Admin</a>';
    echo '</body></html>';
}
