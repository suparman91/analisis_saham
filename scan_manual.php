<?php
require_once 'auth.php';
require_login();

require_once __DIR__ . '/db.php';
$mysqli = db_connect();
require_subscription($mysqli);

$currentUserId = (int)get_user_id();

function scan_history_get_columns($db) {
    $hasUserId = false;
    $hasSession = false;

    if ($db instanceof PDO) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM scan_history");
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($cols as $col) {
                $name = strtolower((string)($col['Field'] ?? ''));
                if ($name === 'user_id') $hasUserId = true;
                if ($name === 'scan_session') $hasSession = true;
            }
        } catch (Throwable $e) {
            // ignore
        }
    } else {
        $res = $db->query("SHOW COLUMNS FROM scan_history");
        if ($res) {
            while ($col = $res->fetch_assoc()) {
                $name = strtolower((string)($col['Field'] ?? ''));
                if ($name === 'user_id') $hasUserId = true;
                if ($name === 'scan_session') $hasSession = true;
            }
        }
    }

    return [$hasUserId, $hasSession];
}

function scan_history_try_migrate($db) {
    [$hasUserId, $hasSession] = scan_history_get_columns($db);

    if ($db instanceof PDO) {
        try {
            if (!$hasUserId) {
                $db->exec("ALTER TABLE scan_history ADD COLUMN user_id INT NULL AFTER scan_date");
            }
            if (!$hasSession) {
                $db->exec("ALTER TABLE scan_history ADD COLUMN scan_session VARCHAR(64) NULL AFTER user_id");
            }
        } catch (Throwable $e) {
            // ignore migration failure
        }
    } else {
        if (!$hasUserId) {
            $db->query("ALTER TABLE scan_history ADD COLUMN user_id INT NULL AFTER scan_date");
        }
        if (!$hasSession) {
            $db->query("ALTER TABLE scan_history ADD COLUMN scan_session VARCHAR(64) NULL AFTER user_id");
        }
    }

    return scan_history_get_columns($db);
}

function scan_history_summary_table_exists($db) {
    if ($db instanceof PDO) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'scan_history_summary'");
            return $stmt && $stmt->fetch(PDO::FETCH_NUM) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    $res = $db->query("SHOW TABLES LIKE 'scan_history_summary'");
    return ($res && $res->num_rows > 0);
}

function scan_history_details_table_exists($db) {
    if ($db instanceof PDO) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'scan_history_details'");
            return $stmt && $stmt->fetch(PDO::FETCH_NUM) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    $res = $db->query("SHOW TABLES LIKE 'scan_history_details'");
    return ($res && $res->num_rows > 0);
}

function scan_history_get_summaries($db, $userId, $hasUserId, $hasSession, $hasSummaryTable, $limit = 20) {
    $rows = [];
    $limit = max(1, (int)$limit);

    if ($hasSummaryTable) {
        if ($db instanceof PDO) {
            $stmt = $db->prepare(
                "SELECT scan_session, scan_type, scan_date, last_scan_at, total_symbols
                 FROM scan_history_summary
                 WHERE user_id = ?
                 ORDER BY last_scan_at DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $uid = (int)$userId;
            $res = $db->query(
                "SELECT scan_session, scan_type, scan_date, last_scan_at, total_symbols
                 FROM scan_history_summary
                 WHERE user_id = {$uid}
                 ORDER BY last_scan_at DESC
                 LIMIT {$limit}"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
        }

        foreach ($rows as &$r) {
            $r['detail_mode'] = 'session';
            $r['detail_session'] = (string)$r['scan_session'];
        }
        return $rows;
    }

    if ($hasUserId && $hasSession) {
        if ($db instanceof PDO) {
            $stmt = $db->prepare(
                "SELECT scan_session, scan_type, scan_date, MAX(created_at) AS last_scan_at, COUNT(DISTINCT symbol) AS total_symbols
                 FROM scan_history
                 WHERE user_id = ? AND scan_session IS NOT NULL AND scan_session <> ''
                 GROUP BY scan_session, scan_type, scan_date
                 ORDER BY last_scan_at DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $uid = (int)$userId;
            $res = $db->query(
                "SELECT scan_session, scan_type, scan_date, MAX(created_at) AS last_scan_at, COUNT(DISTINCT symbol) AS total_symbols
                 FROM scan_history
                 WHERE user_id = {$uid} AND scan_session IS NOT NULL AND scan_session <> ''
                 GROUP BY scan_session, scan_type, scan_date
                 ORDER BY last_scan_at DESC
                 LIMIT {$limit}"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
        }

        foreach ($rows as &$r) {
            $r['detail_mode'] = 'session';
            $r['detail_session'] = (string)$r['scan_session'];
        }
        return $rows;
    }

    if ($hasUserId) {
        if ($db instanceof PDO) {
            $stmt = $db->prepare(
                "SELECT scan_type, scan_date,
                        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS group_time,
                        MAX(created_at) AS last_scan_at,
                        COUNT(DISTINCT symbol) AS total_symbols
                 FROM scan_history
                 WHERE user_id = ?
                 GROUP BY scan_type, scan_date, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
                 ORDER BY last_scan_at DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $uid = (int)$userId;
            $res = $db->query(
                "SELECT scan_type, scan_date,
                        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS group_time,
                        MAX(created_at) AS last_scan_at,
                        COUNT(DISTINCT symbol) AS total_symbols
                 FROM scan_history
                 WHERE user_id = {$uid}
                 GROUP BY scan_type, scan_date, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
                 ORDER BY last_scan_at DESC
                 LIMIT {$limit}"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
        }

        foreach ($rows as &$r) {
            $r['detail_mode'] = 'legacy';
            $r['detail_session'] = '';
        }
        return $rows;
    }

    return [];
}

function scan_history_get_detail_rows($db, $userId, $hasUserId, $hasSession, $mode, $session, $scanType, $scanDate, $groupTime) {
    $rows = [];

    if ($mode === 'session' && $hasUserId && $hasSession && $session !== '') {
        if ($db instanceof PDO) {
            $stmt = $db->prepare("SELECT scan_type, symbol, price, scan_date, created_at FROM scan_history WHERE user_id = ? AND scan_session = ? ORDER BY symbol ASC");
            $stmt->execute([$userId, $session]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $uid = (int)$userId;
        $sessionEsc = $db->real_escape_string($session);
        $res = $db->query("SELECT scan_type, symbol, price, scan_date, created_at FROM scan_history WHERE user_id = {$uid} AND scan_session = '{$sessionEsc}' ORDER BY symbol ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    if ($mode === 'legacy' && $hasUserId && $scanType !== '' && $scanDate !== '' && $groupTime !== '') {
        if ($db instanceof PDO) {
            $stmt = $db->prepare("SELECT scan_type, symbol, price, scan_date, created_at FROM scan_history WHERE user_id = ? AND scan_type = ? AND scan_date = ? AND DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') = ? ORDER BY symbol ASC");
            $stmt->execute([$userId, $scanType, $scanDate, $groupTime]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $uid = (int)$userId;
        $typeEsc = $db->real_escape_string($scanType);
        $dateEsc = $db->real_escape_string($scanDate);
        $timeEsc = $db->real_escape_string($groupTime);
        $res = $db->query("SELECT scan_type, symbol, price, scan_date, created_at FROM scan_history WHERE user_id = {$uid} AND scan_type = '{$typeEsc}' AND scan_date = '{$dateEsc}' AND DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') = '{$timeEsc}' ORDER BY symbol ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    return [];
}

function scan_history_render_summary_html($db, $userId, $hasUserId, $hasSession, $hasSummaryTable) {
    $rows = scan_history_get_summaries($db, $userId, $hasUserId, $hasSession, $hasSummaryTable, 20);
    ob_start();
    if (!$hasUserId) {
        echo "<p style='color:#b91c1c; margin:8px 0;'>Kolom <b>user_id</b> pada scan_history belum tersedia, jadi riwayat per-user belum bisa difilter penuh.</p>";
    }
    if (count($rows) > 0) {
        echo "<table class='history-table'>";
        echo "<tr><th>Tipe Scan</th><th>Tanggal Data</th><th>Waktu Scan</th><th>Jumlah Saham</th><th>Detail</th></tr>";
        foreach ($rows as $row) {
            $scanType = htmlspecialchars((string)$row['scan_type']);
            $scanDate = htmlspecialchars((string)$row['scan_date']);
            $lastScan = htmlspecialchars((string)$row['last_scan_at']);
            $total = (int)($row['total_symbols'] ?? 0);
            $mode = htmlspecialchars((string)($row['detail_mode'] ?? 'legacy'));
            $session = htmlspecialchars((string)($row['detail_session'] ?? ''));
            $groupTime = htmlspecialchars((string)($row['group_time'] ?? ''));

            echo "<tr>";
            echo "<td><span class='status active'>{$scanType}</span></td>";
            echo "<td>{$scanDate}</td>";
            echo "<td>{$lastScan}</td>";
            echo "<td><strong>{$total}</strong> saham</td>";
            echo "<td><a href='#' class='history-detail-link' data-mode='{$mode}' data-session='{$session}' data-type='{$scanType}' data-date='{$scanDate}' data-time='{$groupTime}'>Lihat Detail</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Belum ada riwayat scan untuk akun ini.</p>";
    }
    return ob_get_clean();
}

[$scanHistoryHasUserId, $scanHistoryHasSession] = scan_history_try_migrate($mysqli);
$scanHistoryHasSummaryTable = scan_history_summary_table_exists($mysqli);
$scanHistoryHasDetailsTable = scan_history_details_table_exists($mysqli);

if (isset($_GET['history_summary']) && $_GET['history_summary'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    echo scan_history_render_summary_html($mysqli, $currentUserId, $scanHistoryHasUserId, $scanHistoryHasSession, $scanHistoryHasSummaryTable);
    exit;
}

if (isset($_GET['history_detail']) && $_GET['history_detail'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    $mode = isset($_GET['mode']) ? (string)$_GET['mode'] : 'legacy';
    $session = isset($_GET['session']) ? (string)$_GET['session'] : '';
    $scanType = isset($_GET['type']) ? (string)$_GET['type'] : '';
    $scanDate = isset($_GET['date']) ? (string)$_GET['date'] : '';
    $groupTime = isset($_GET['time']) ? (string)$_GET['time'] : '';

    if ($mode === 'session' && $scanHistoryHasDetailsTable && $session !== '') {
        $detailRows = [];
        if ($mysqli instanceof PDO) {
            $stmt = $mysqli->prepare("SELECT * FROM scan_history_details WHERE user_id = ? AND scan_session = ? ORDER BY rank_no ASC");
            $stmt->execute([$currentUserId, $session]);
            $detailRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $uid = (int)$currentUserId;
            $sessionEsc = $mysqli->real_escape_string($session);
            $res = $mysqli->query("SELECT * FROM scan_history_details WHERE user_id = {$uid} AND scan_session = '{$sessionEsc}' ORDER BY rank_no ASC");
            if ($res) {
                while ($r = $res->fetch_assoc()) $detailRows[] = $r;
            }
        }

        if (count($detailRows) > 0) {
            $scanTypeSession = (string)($detailRows[0]['scan_type'] ?? '');
            $isAfterClose = ($scanTypeSession === 'AFTER_CLOSE');

            echo "<div style='overflow:auto; max-height:80vh;'>";
            echo "<table class='history-table' style='min-width: 1300px;'>";
            echo "<tr>
                    <th>Rank</th>
                    <th>Kode</th>
                    <th>Nama Saham</th>
                    <th>Close / Harga</th>
                    <th>% Kenaikan</th>
                    <th>Sinyal Aksi</th>
                    <th>Score</th>";
            if ($isAfterClose) {
                echo "<th>Close Strength</th><th>Sentimen Berita</th>";
            }
            echo "<th>Trend (MA 5 vs 20)</th>
                    <th>Stochastic (14)</th>
                    <th>Indikasi Volume</th>
                    <th>Bandarmologi Flow</th>
                  </tr>";

            foreach ($detailRows as $row) {
                $rankNo = (int)($row['rank_no'] ?? 0);
                $symbol = htmlspecialchars((string)($row['symbol'] ?? ''));
                $stockName = htmlspecialchars((string)($row['stock_name'] ?? ''));
                $closeText = htmlspecialchars((string)($row['close_text'] ?? ''));
                $pctText = htmlspecialchars((string)($row['pct_text'] ?? ''));
                $pctColor = htmlspecialchars((string)($row['pct_color'] ?? '#475569'));
                $score = (int)($row['score'] ?? 0);
                $scoreColor = htmlspecialchars((string)($row['score_color'] ?? '#475569'));
                $closeStrengthText = htmlspecialchars((string)($row['close_strength_text'] ?? '-'));
                $newsLabel = htmlspecialchars((string)($row['news_label'] ?? 'Netral'));
                $newsSummary = htmlspecialchars((string)($row['news_summary'] ?? '-'));
                $newsColor = htmlspecialchars((string)($row['news_color'] ?? '#475569'));
                $trendHtml = (string)($row['trend_html'] ?? '');
                $stochHtml = (string)($row['stoch_html'] ?? '');
                $volHtml = (string)($row['volume_html'] ?? '');
                $flowHtml = (string)($row['flow_html'] ?? '');

                $effectiveScore = $score;
                if ($isAfterClose && strtolower($newsLabel) === 'negatif') {
                    $effectiveScore -= 10;
                }
                // Re-derive signal flags dari HTML snapshot yang tersimpan
                $sigUptrend    = (strpos($trendHtml, 'Uptrend') !== false);
                $sigVolStrong  = (strpos($volHtml, 'Spike') !== false || strpos($volHtml, 'Normal Kuat') !== false);
                $sigOverbought = (strpos($stochHtml, 'Overbought') !== false);

                $actionText  = 'WAIT / OBSERVE';
                $actionColor = '#475569';
                if ($sigOverbought && $effectiveScore < 72) {
                    $actionText  = 'WASPADA OVERBOUGHT';
                    $actionColor = '#b45309';
                } elseif ($effectiveScore >= 70 && $sigUptrend && $sigVolStrong) {
                    $actionText  = 'BUY KUAT';
                    $actionColor = '#15803d';
                } elseif ($effectiveScore >= 55 && ($sigUptrend || $sigVolStrong) && !$sigOverbought) {
                    $actionText  = 'BUY BERTAHAP';
                    $actionColor = '#16a34a';
                } elseif ($effectiveScore >= 38) {
                    $actionText  = 'WATCHLIST';
                    $actionColor = '#b45309';
                } else {
                    $actionText  = 'HINDARI';
                    $actionColor = '#b91c1c';
                }

                echo "<tr>";
                echo "<td><b>#{$rankNo}</b></td>";
                echo "<td><b>{$symbol}</b></td>";
                echo "<td>{$stockName}</td>";
                echo "<td><b>{$closeText}</b></td>";
                echo "<td style='color: {$pctColor}; font-weight: bold;'>{$pctText}</td>";
                echo "<td><span style='display:inline-block;padding:4px 8px;border-radius:999px;color:#fff;background:{$actionColor};font-weight:bold;'>{$actionText}</span></td>";
                echo "<td><span style='display:inline-block;padding:4px 8px;border-radius:999px;color:#fff;background:{$scoreColor};font-weight:bold;'>{$score}</span></td>";
                if ($isAfterClose) {
                    echo "<td><b>{$closeStrengthText}</b></td>";
                    echo "<td><span style='color: {$newsColor}; font-weight:bold;'>{$newsLabel}</span><br><small>{$newsSummary}</small></td>";
                }
                echo "<td>{$trendHtml}</td>";
                echo "<td>{$stochHtml}</td>";
                echo "<td>{$volHtml}</td>";
                echo "<td>{$flowHtml}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
            exit;
        }
    }

    $detailRows = scan_history_get_detail_rows(
        $mysqli,
        $currentUserId,
        $scanHistoryHasUserId,
        $scanHistoryHasSession,
        $mode,
        $session,
        $scanType,
        $scanDate,
        $groupTime
    );

    if (count($detailRows) === 0) {
        echo "<p style='margin:0;'>Detail riwayat tidak ditemukan atau sudah melewati masa retensi detail.</p>";
        exit;
    }

    echo "<div style='overflow:auto; max-height:65vh;'>";
    echo "<table class='history-table'>";
    echo "<tr><th>Tipe</th><th>Symbol</th><th>Harga (Rp)</th><th>Tanggal Data</th><th>Waktu Scan</th></tr>";
    foreach ($detailRows as $row) {
        $typeSafe = htmlspecialchars((string)$row['scan_type']);
        $symbolSafe = htmlspecialchars((string)$row['symbol']);
        $scanDateSafe = htmlspecialchars((string)$row['scan_date']);
        $createdSafe = htmlspecialchars((string)$row['created_at']);
        $price = (float)($row['price'] ?? 0);
        echo "<tr>";
        echo "<td><span class='status active'>{$typeSafe}</span></td>";
        echo "<td><strong>{$symbolSafe}</strong></td>";
        echo "<td>" . number_format($price, 0, ',', '.') . "</td>";
        echo "<td>{$scanDateSafe}</td>";
        echo "<td>{$createdSafe}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    exit;
}
?>
<?php
$pageTitle = 'Manual Scan BPJP & BSJP';
?>
<?php include 'header.php'; ?>
    <style>
        /* Navigation Menu */
        .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .top-menu a:hover { background: #1e293b; color: #fff; }
        .top-menu a.active { background: #3b82f6; color: #fff; }

        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1600px; margin: 0 auto; }
        .card { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; max-width: 400px; }
        button { 
            padding: 10px 15px; 
            font-size: 16px; 
            cursor: pointer; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px;
        }
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .status { margin-top: 10px; font-weight: bold; color: #d9534f; }
        .status.active { color: #5cb85c; }
        
        /* Layout Grid untuk Mobile */
        .grid-container { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (min-width: 768px) {
            .grid-container { grid-template-columns: 1fr 1fr; }
            .top-menu { flex-direction: row; }
        }
        @media (max-width: 768px) {
            .top-menu { flex-direction: column; align-items: stretch; text-align: center; }
            .card { max-width: 100%; }
            body { padding: 10px; }
            table { font-size: 12px; }
        }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .history-table th, .history-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .history-table th { background-color: #f8f9fa; }
        .history-header { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .history-content { overflow-x:auto; overflow-y:auto; max-height: 340px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; }
        .history-content.collapsed { display:none; }
        .btn-toggle-history { background:#334155; font-size:13px; padding:7px 10px; }
        .history-detail-link { color:#2563eb; text-decoration:none; font-weight:700; }
        .history-detail-link:hover { text-decoration:underline; }
        .history-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 9998;
            display: none;
        }
        .history-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(99vw, 1700px);
            max-height: 92vh;
            overflow: hidden;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.25);
            z-index: 9999;
            display: none;
        }
        .history-modal-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            background:#f8fafc;
        }
        .history-modal-body { padding: 12px 14px; overflow:auto; max-height: calc(92vh - 60px); }
        .history-modal-close { background:#dc2626; font-size:13px; padding:7px 10px; }
        #result-container { max-width: 100% !important; width: 100% !important; }
    </style>
    
    <h2>Scanner Manual Multistrategi</h2>
    <p>Waktu Server / Browser: <strong id="current-time" style="color:#007bff;">Loading...</strong></p>

    <div class="grid-container" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <div class="card">
            <h3>BPJP (Scalping Pagi)</h3>
            <p>Jadwal Buka: 08:50 - 09:05 WIB (Prime: 08:55 - 09:00, Senin - Jumat)</p>
            <button id="btn-bpjp" onclick="runScan('BPJP')" disabled>Jalankan Scan BPJP</button>
            <div id="status-bpjp" class="status">Tombol belum aktif</div>
        </div>

        <div class="card">
            <h3>BSJP (Swing Sore)</h3>
            <p>Jadwal Buka: 15:45 - 16:00 WIB (Prime: 15:55 - 16:00, Senin - Jumat)</p>
            <button id="btn-bsjp" onclick="runScan('BSJP')" disabled>Jalankan Scan BSJP</button>
            <div id="status-bsjp" class="status">Tombol belum aktif</div>
        </div>

        <div class="card" style="border-color: #3b82f6;">
            <h3>Swing / Uptrend Tracker</h3>
            <p>Jadwal: Kapan Saja (Data EOD / Tengah Malam)</p>
            <button id="btn-swing" onclick="runScan('SWING')" style="background-color:#1e293b;">Jalankan Swing Scan</button>
            <div id="status-swing" class="status active">Menu Terbuka 24/7</div>
        </div>

        <div class="card" style="border-color: #0f766e;">
            <h3>After Close Scanner</h3>
            <p>Jadwal Ideal: 16:05 - 18:00 WIB (Setelah Market Tutup)</p>
            <button id="btn-after_close" onclick="runScan('AFTER_CLOSE')" style="background-color:#0f766e;">Jalankan After Close</button>
            <div id="status-after_close" class="status active">Menu Terbuka Setelah Penutupan</div>
        </div>
    </div>

    <!-- Riwayat Scan Tersimpan -->
    <div class="card" style="max-width:100%;">
        <div class="history-header">
            <h3 style="margin:0;">Riwayat Scan Terakhir (Tersimpan di Database)</h3>
            <button type="button" id="btnToggleHistory" class="btn-toggle-history">Tampilkan Riwayat</button>
        </div>
        <div id="historyContent" class="history-content collapsed">
        <?= scan_history_render_summary_html($mysqli, $currentUserId, $scanHistoryHasUserId, $scanHistoryHasSession, $scanHistoryHasSummaryTable) ?>
        </div>
    </div>

    <div id="historyModalBackdrop" class="history-modal-backdrop"></div>
    <div id="historyModal" class="history-modal">
        <div class="history-modal-header">
            <strong>Detail Riwayat Scan</strong>
            <button type="button" id="historyModalClose" class="history-modal-close">Tutup</button>
        </div>
        <div id="historyModalBody" class="history-modal-body">
            <p style="margin:0; color:#64748b;">Memuat detail...</p>
        </div>
    </div>

    <!-- Kontainer Hasil Scan -->
    <div id="result-container" class="card" style="display: none; max-width: 100%; width: 100%;">
        <!-- Hasil dari PHP scan_bpjs_bsjp.php akan tampil di sini! -->
    </div>

    <script>
        function isTimeBetween(time, start, end) {
            return time >= start && time <= end;
        }

        function getWindowMode(type, timeCompare) {
            if (type === 'BPJP') {
                if (isTimeBetween(timeCompare, '08:55', '09:00')) return 'bpjp_prime_5m';
                if (isTimeBetween(timeCompare, '08:50', '09:05')) return 'bpjp_open_15m';
                return 'bpjp_outside';
            }
            if (type === 'BSJP') {
                if (isTimeBetween(timeCompare, '15:55', '16:00')) return 'bsjp_prime_5m';
                if (isTimeBetween(timeCompare, '15:45', '16:00')) return 'bsjp_open_15m';
                return 'bsjp_outside';
            }
            return 'standard';
        }

        function triggerAutoSyncOnce(tag) {
            const today = new Date().toISOString().split('T')[0];
            const key = 'scan_sync_' + tag + '_' + today;
            if (localStorage.getItem(key) === '1') return;

            fetch('ajax_update.php')
                .then(() => localStorage.setItem(key, '1'))
                .catch(() => {});
        }

        // Set zona waktu ke Asia/Jakarta
        function updateTime() {
            const now = new Date();
            const options = { timeZone: 'Asia/Jakarta', hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const timeString = now.toLocaleTimeString('id-ID', options);
            
            // Format jam untuk perbandingan "HH.MM" -> pastikan formatnya HH:MM
            // Di beberapa sistem toLocaleTimeString ('id-ID') menghasilkan "08.50.00" (pakai titik)
            // Jadi kita ganti semua titik menjadi titik dua supaya aman saat dibanding "08:50"
            const normalizedTime = timeString.replace(/\./g, ':');
            const timeCompare = normalizedTime.substring(0, 5);
            
            // Ambil Hari dari Date object standar supaya tidak terdampak format string
            const dayOfWeek = now.getDay(); // 0 = Minggu, 1 = Senin, ... 6 = Sabtu
            
            document.getElementById('current-time').innerText = timeString;

            const btnBpjp = document.getElementById('btn-bpjp');
            const statusBpjp = document.getElementById('status-bpjp');
            
            const btnBsjp = document.getElementById('btn-bsjp');
            const statusBsjp = document.getElementById('status-bsjp');

            const debugAlwaysOpen = new URLSearchParams(window.location.search).get('debug_time') === '1';
            const isWeekday = (dayOfWeek >= 1 && dayOfWeek <= 5);
            const isBpjpOpen = isTimeBetween(timeCompare, '08:50', '09:05');
            const isBpjpPrime = isTimeBetween(timeCompare, '08:55', '09:00');
            const isBsjpOpen = isTimeBetween(timeCompare, '15:45', '16:00');
            const isBsjpPrime = isTimeBetween(timeCompare, '15:55', '16:00');

            // Cek apakah hari ini Senin - Jumat (1 - 5)
            if (isWeekday || debugAlwaysOpen) {

                // Logika BPJP (08:50 - 09:05) dengan fokus 5 menit menjelang open
                if (isBpjpOpen || debugAlwaysOpen) {
                    btnBpjp.disabled = false;
                    statusBpjp.innerHTML = isBpjpPrime
                        ? "Sesi BPJP PRIME (08:55-09:00) aktif. Fokus kandidat volume tertinggi."
                        : "Sesi BPJP 15 menit aktif (08:50-09:05).";
                    statusBpjp.className = "status active";
                } else {
                    btnBpjp.disabled = true;
                    statusBpjp.innerText = "Di luar jam BPJP.";
                    statusBpjp.className = "status";
                }

                // Logika BSJP (15:45 - 16:00) dengan fokus 5 menit menjelang close
                if (isBsjpOpen || debugAlwaysOpen) {
                    btnBsjp.disabled = false;
                    statusBsjp.innerHTML = isBsjpPrime
                        ? "Sesi BSJP PRIME (15:55-16:00) aktif. Prioritas momentum penutupan."
                        : "Sesi BSJP 15 menit aktif (15:45-16:00).";
                    statusBsjp.className = "status active";
                } else {
                    btnBsjp.disabled = true;
                    statusBsjp.innerText = "Di luar jam BSJP.";
                    statusBsjp.className = "status";
                }

                // Sinkronisasi data otomatis sekali per window agar data scanner lebih match terhadap sesi.
                if (isBpjpPrime) triggerAutoSyncOnce('bpjp_prime_5m');
                if (isBsjpOpen && !isBsjpPrime) triggerAutoSyncOnce('bsjp_open_15m');
                if (isBsjpPrime) triggerAutoSyncOnce('bsjp_prime_5m');
            } else {
                // Hari Libur
                btnBpjp.disabled = true;
                btnBsjp.disabled = true;
                statusBpjp.innerText = "Hari Libur Bursa";
                statusBpjp.className = "status";
                statusBsjp.innerText = "Hari Libur Bursa";
                statusBsjp.className = "status";
            }
        }

        function runScan(type) {
            let btnId = 'btn-' + type.toLowerCase();
            const btn = document.getElementById(btnId);
            const resultDiv = document.getElementById('result-container');
            const now = new Date();
            const timeCompare = now.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour12: false, hour: '2-digit', minute: '2-digit' }).replace(/\./g, ':');
            const windowMode = getWindowMode(type, timeCompare);

            // Ubah tombol sebentar jadi loading
            const originalText = btn.innerText;
            btn.innerText = "Scanning...";

            // Siapkan div hasil dengan pesan loading
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = "<p>Menganalisis Algoritma <b>" + type + "</b> (Window: <b>" + windowMode + "</b>)...</p>";

            // LANGSUNG eksekusi database tanpa nge-fetch sinkronisasi Live Yahoo yg bikin lama.
            fetch('scan_bpjs_bsjp.php?tipe=' + type + '&window=' + encodeURIComponent(windowMode))
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = data;
                    enhanceScanResult(resultDiv, type);
                    loadHistorySummary();
                    btn.innerText = "Selesai!";
                    setTimeout(() => btn.innerText = originalText, 1500);
                })
                .catch(err => {
                    resultDiv.innerHTML = "<p style='color:red;'>Terjadi kesalahan: " + err + "</p>";
                    btn.innerText = originalText;
                });
        }

        function tableToCSV(tableEl) {
            const rows = Array.from(tableEl.querySelectorAll('tr'));
            return rows.map(row => {
                const cols = Array.from(row.querySelectorAll('th,td'));
                return cols.map(col => {
                    const text = (col.innerText || '').replace(/\s+/g, ' ').trim();
                    const escaped = text.replace(/"/g, '""');
                    return '"' + escaped + '"';
                }).join(',');
            }).join('\n');
        }

        function enhanceScanResult(container, scanType) {
            const table = container.querySelector('table');
            if (!table) return;

            const allRows = Array.from(table.querySelectorAll('tr')).slice(1); // skip header
            if (allRows.length === 0) return;

            const toolbar = document.createElement('div');
            toolbar.style.display = 'flex';
            toolbar.style.flexWrap = 'wrap';
            toolbar.style.gap = '10px';
            toolbar.style.alignItems = 'center';
            toolbar.style.margin = '12px 0';

            const totalInfo = document.createElement('span');
            totalInfo.style.fontWeight = 'bold';
            totalInfo.style.color = '#1e293b';
            totalInfo.innerText = 'Total hasil: ' + allRows.length + ' saham';

            const perPageLabel = document.createElement('label');
            perPageLabel.innerText = 'Baris per halaman:';
            perPageLabel.style.fontSize = '14px';

            const perPageSelect = document.createElement('select');
            perPageSelect.style.padding = '6px';
            perPageSelect.style.border = '1px solid #cbd5e1';
            perPageSelect.style.borderRadius = '4px';
            [25, 50, 100, 200].forEach(n => {
                const opt = document.createElement('option');
                opt.value = String(n);
                opt.textContent = String(n);
                if (n === 50) opt.selected = true;
                perPageSelect.appendChild(opt);
            });

            const btnPrev = document.createElement('button');
            btnPrev.type = 'button';
            btnPrev.innerText = '← Prev';
            btnPrev.style.background = '#334155';

            const pageInfo = document.createElement('span');
            pageInfo.style.minWidth = '110px';
            pageInfo.style.textAlign = 'center';
            pageInfo.style.fontWeight = 'bold';

            const btnNext = document.createElement('button');
            btnNext.type = 'button';
            btnNext.innerText = 'Next →';
            btnNext.style.background = '#334155';

            const btnExport = document.createElement('button');
            btnExport.type = 'button';
            btnExport.innerText = 'Export CSV';
            btnExport.style.background = '#0f766e';

            toolbar.appendChild(totalInfo);
            toolbar.appendChild(perPageLabel);
            toolbar.appendChild(perPageSelect);
            toolbar.appendChild(btnPrev);
            toolbar.appendChild(pageInfo);
            toolbar.appendChild(btnNext);
            toolbar.appendChild(btnExport);

            table.parentElement.insertBefore(toolbar, table);

            let currentPage = 1;
            function renderPage() {
                const perPage = Number(perPageSelect.value) || 50;
                const totalPages = Math.max(1, Math.ceil(allRows.length / perPage));
                if (currentPage > totalPages) currentPage = totalPages;

                const start = (currentPage - 1) * perPage;
                const end = start + perPage;

                allRows.forEach((row, idx) => {
                    row.style.display = (idx >= start && idx < end) ? '' : 'none';
                });

                pageInfo.innerText = 'Hal ' + currentPage + '/' + totalPages;
                btnPrev.disabled = currentPage <= 1;
                btnNext.disabled = currentPage >= totalPages;
            }

            btnPrev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage();
                }
            });

            btnNext.addEventListener('click', () => {
                const perPage = Number(perPageSelect.value) || 50;
                const totalPages = Math.max(1, Math.ceil(allRows.length / perPage));
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPage();
                }
            });

            perPageSelect.addEventListener('change', () => {
                currentPage = 1;
                renderPage();
            });

            btnExport.addEventListener('click', () => {
                const csv = tableToCSV(table);
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const stamp = now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '_' + pad(now.getHours()) + pad(now.getMinutes());
                a.href = url;
                a.download = 'scan_' + (scanType || 'result').toLowerCase() + '_' + stamp + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            renderPage();
        }

        function initHistoryToggle() {
            const btn = document.getElementById('btnToggleHistory');
            const content = document.getElementById('historyContent');
            if (!btn || !content) return;

            btn.addEventListener('click', () => {
                const isCollapsed = content.classList.toggle('collapsed');
                btn.innerText = isCollapsed ? 'Tampilkan Riwayat' : 'Sembunyikan Riwayat';
            });
        }

        function closeHistoryModal() {
            const modal = document.getElementById('historyModal');
            const backdrop = document.getElementById('historyModalBackdrop');
            if (modal) modal.style.display = 'none';
            if (backdrop) backdrop.style.display = 'none';
        }

        function openHistoryModal() {
            const modal = document.getElementById('historyModal');
            const backdrop = document.getElementById('historyModalBackdrop');
            if (modal) modal.style.display = 'block';
            if (backdrop) backdrop.style.display = 'block';
        }

        function bindHistoryDetailLinks() {
            const links = document.querySelectorAll('.history-detail-link');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const mode = this.getAttribute('data-mode') || 'legacy';
                    const session = this.getAttribute('data-session') || '';
                    const type = this.getAttribute('data-type') || '';
                    const date = this.getAttribute('data-date') || '';
                    const time = this.getAttribute('data-time') || '';

                    const body = document.getElementById('historyModalBody');
                    if (body) {
                        body.innerHTML = '<p style="margin:0; color:#64748b;">Memuat detail...</p>';
                    }

                    openHistoryModal();

                    const params = new URLSearchParams({
                        history_detail: '1',
                        mode: mode,
                        session: session,
                        type: type,
                        date: date,
                        time: time
                    });

                    fetch('scan_manual.php?' + params.toString())
                        .then(r => r.text())
                        .then(html => {
                            if (body) body.innerHTML = html;
                        })
                        .catch(() => {
                            if (body) body.innerHTML = '<p style="margin:0; color:#dc2626;">Gagal memuat detail riwayat.</p>';
                        });
                });
            });
        }

        function loadHistorySummary() {
            const content = document.getElementById('historyContent');
            if (!content) return;

            fetch('scan_manual.php?history_summary=1')
                .then(r => r.text())
                .then(html => {
                    content.innerHTML = html;
                    bindHistoryDetailLinks();
                })
                .catch(() => {
                    // keep old content on failure
                });
        }

        // Jalankan update waktu setiap 1 detik
        setInterval(updateTime, 1000);
        updateTime(); // Panggil pertama kali
        initHistoryToggle();
        bindHistoryDetailLinks();

        const modalCloseBtn = document.getElementById('historyModalClose');
        const modalBackdrop = document.getElementById('historyModalBackdrop');
        if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeHistoryModal);
        if (modalBackdrop) modalBackdrop.addEventListener('click', closeHistoryModal);
    </script>
<?php include 'footer.php'; ?>

