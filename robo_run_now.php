<?php
// Fungsi cek jam bursa BEI
function is_bursa_open() {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $day = $now->format('N'); // 1=Senin, ..., 5=Jumat
    $time = $now->format('H:i');

    if ($day >= 1 && $day <= 4) { // Senin-Kamis
        return ($time >= '09:00' && $time <= '12:00') || ($time >= '13:30' && $time <= '15:49');
    } elseif ($day == 5) { // Jumat
        return ($time >= '09:00' && $time <= '11:30') || ($time >= '14:00' && $time <= '15:49');
    }
    return false; // Sabtu/Minggu
}

require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';

date_default_timezone_set('Asia/Jakarta');

$mysqli = db_connect();
require_subscription($mysqli);

$user_id = get_user_id();
$TP_PCT = 0.05;
$SL_PCT = -0.03;
$MAX_ALLOC = 10000000;
$TARGET_POSITIONS = 5;
$ALLIN_SCORE = 90;
$actions = [];

function getRealtimePriceYahoo($symbol) {
    static $cache = [];
    if (isset($cache[$symbol])) {
        return $cache[$symbol];
    }

    $encoded = urlencode($symbol);
    $url = "https://query1.finance.yahoo.com/v7/finance/spark?symbols={$encoded}&range=1d&interval=1m";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) {
        $cache[$symbol] = 0.0;
        return 0.0;
    }

    $data = json_decode($res, true);
    if (!isset($data['spark']['result'][0]['response'][0]['indicators']['quote'][0]['close'])) {
        $cache[$symbol] = 0.0;
        return 0.0;
    }

    $closes = $data['spark']['result'][0]['response'][0]['indicators']['quote'][0]['close'];
    $valid = array_values(array_filter($closes, function ($v) {
        return $v !== null;
    }));
    if (empty($valid)) {
        $cache[$symbol] = 0.0;
        return 0.0;
    }

    $price = (float)end($valid);
    $cache[$symbol] = $price;
    return $price;
}

function beiTickSize($price) {
    $p = (float)$price;
    if ($p < 200) return 1;
    if ($p < 500) return 2;
    if ($p < 2000) return 5;
    if ($p < 5000) return 10;
    return 25;
}

function roundToBeiTick($price) {
    $tick = beiTickSize($price);
    return round(((float)$price) / $tick) * $tick;
}

function isRealtimePriceValid($rtPrice, $dbClose) {
    $rt = (float)$rtPrice;
    $db = (float)$dbClose;
    if ($rt <= 0 || $db <= 0) {
        return false;
    }
    $diff = abs($rt - $db) / $db;
    // Toleransi 35% agar lonjakan ekstrem/salah feed tidak dipakai.
    return $diff <= 0.35;
}

// Validate user & current balance
$resUser = $mysqli->query("SELECT id, email, robo_balance FROM users WHERE id = {$user_id} LIMIT 1");
if (!$resUser || $resUser->num_rows === 0) {
    header('Location: portfolio.php?robo_run=err&msg=' . urlencode('User tidak ditemukan.'));
    exit;
}
$user = $resUser->fetch_assoc();
$balance = (float)$user['robo_balance'];

// 1. SELL check for open positions
$open = $mysqli->query("SELECT * FROM robo_trades WHERE status='OPEN' AND user_id={$user_id}");
while ($open && ($t = $open->fetch_assoc())) {
    $sym = $t['symbol'];
    $buyPrice = (float)$t['buy_price'];
    $prices = fetch_prices($mysqli, $sym, 10);
    if (count($prices) < 1 || $buyPrice <= 0) {
        continue;
    }

    $latest = $prices[count($prices) - 1];
    $dbClose = (float)$latest['close'];

    // Koreksi 1x untuk harga beli anomali hasil serok sebelumnya: kembalikan ke referensi DB terakhir.
    $buyReasonNow = isset($t['buy_reason']) ? (string)$t['buy_reason'] : '';
    if ($dbClose > 0 && stripos($buyReasonNow, 'Serok') !== false) {
        $anomDiff = abs($buyPrice - $dbClose) / $dbClose;
        if ($anomDiff > 0.35) {
            $tidFix = (int)$t['id'];
            $fixPrice = roundToBeiTick($dbClose);
            $mysqli->query("UPDATE robo_trades SET buy_price={$fixPrice} WHERE id={$tidFix} AND user_id={$user_id}");
            $buyPrice = $fixPrice;
            $actions[] = 'FIX BUY ' . $sym . ' (anomali->' . $fixPrice . ')';
        }
    }

    $rtPrice = getRealtimePriceYahoo($sym);
    $isRtValid = isRealtimePriceValid($rtPrice, $dbClose);
    $currPrice = $isRtValid ? (float)$rtPrice : $dbClose;
    if ($currPrice <= 0) {
        continue;
    }
    $sellDate = date('Y-m-d');

    $pct = ($currPrice - $buyPrice) / $buyPrice;
    $sell = false;
    $reason = '';

    // Auto-serok: wajib pakai harga realtime valid, jangan fallback ke DB close.
    $serokPct = $isRtValid ? (($rtPrice - $buyPrice) / $buyPrice) : 0;
    if ($isRtValid && $serokPct <= -0.05 && $balance > 1000000) {
        $lots_serok = (int)floor(min($balance, 3000000) / ($rtPrice * 100)); // max 3jt per serok
        if ($lots_serok > 0) {
            $buyValue = $lots_serok * 100 * $rtPrice;
            $reasonSerok = 'Averaging Down (Serok AI)';
            $reasonCombined = trim(($buyReasonNow !== '' ? $buyReasonNow . ' | ' : '') . $reasonSerok . ' @' . round($rtPrice, 2));
            $reasonEsc = $mysqli->real_escape_string($reasonCombined);
            // Hitung harga rata-rata baru dan lot baru
            $old_lots = (int)$t['lots'];
            $old_price = (float)$t['buy_price'];
            $new_lots = $old_lots + $lots_serok;
            $new_avg_price = (($old_price * $old_lots) + ($rtPrice * $lots_serok)) / $new_lots;
            $new_avg_price = roundToBeiTick($new_avg_price);
            // Update posisi lama (lot, harga rata-rata, alasan). buy_date tidak diubah.
            $mysqli->query("UPDATE robo_trades SET buy_price={$new_avg_price}, lots={$new_lots}, buy_reason='{$reasonEsc}' WHERE id=" . (int)$t['id']);
            $mysqli->query("UPDATE users SET robo_balance = robo_balance - {$buyValue} WHERE id = {$user_id}");
            $balance -= $buyValue;
            $actions[] = 'SEROK ' . $sym . ' (' . $lots_serok . ' lot, avg: ' . round($new_avg_price,2) . ')';
            // Audit log SEROK
            $log_stmt = $mysqli->prepare("INSERT INTO robo_audit_log (user_id, symbol, action, price, lots, reason) VALUES (?, ?, ?, ?, ?, ?)");
            if ($log_stmt) {
                $log_action = 'SEROK';
                $log_reason = $reasonSerok;
                $log_price = (float)$rtPrice;
                $log_lots = (int)$lots_serok;
                $log_stmt->bind_param("issdis", $user_id, $sym, $log_action, $log_price, $log_lots, $log_reason);
                $log_stmt->execute();
            } else {
                error_log('Prepare failed (SEROK): ' . $mysqli->error);
            }
            continue; // Lewati cutloss, beri kesempatan rebound
        }
    }

    if ($pct >= $TP_PCT) {
        $sell = true;
        $reason = 'Take Profit (+' . round($pct * 100, 2) . '%)';
    } elseif ($pct <= $SL_PCT) {
        $sell = true;
        $reason = 'Stop Loss (' . round($pct * 100, 2) . '%)';
    }

    if ($sell) {
        $lots = (int)$t['lots'];
        if (is_bursa_open()) {
            $tid = (int)$t['id'];
            $pl = ($currPrice - $buyPrice) * ($lots * 100);
            $value = $currPrice * $lots * 100;
            $reasonEsc = $mysqli->real_escape_string($reason);

            $mysqli->query("UPDATE robo_trades SET status='CLOSED', sell_price={$currPrice}, sell_date='{$sellDate}', sell_reason='{$reasonEsc}', profit_loss_rp={$pl}, profit_loss_pct=" . ($pct * 100) . " WHERE id={$tid} AND user_id={$user_id}");
            $mysqli->query("UPDATE users SET robo_balance = robo_balance + {$value} WHERE id = {$user_id}");
            $balance += $value;

            $actions[] = 'SELL ' . $sym . ' (' . round($pct * 100, 2) . '%)';
            // Audit log SELL
            $log_stmt = $mysqli->prepare("INSERT INTO robo_audit_log (user_id, symbol, action, price, lots, reason) VALUES (?, ?, ?, ?, ?, ?)");
            if ($log_stmt) {
                $log_action = 'SELL';
                $log_reason = $reason;
                $log_stmt->bind_param("issdis", $user_id, $sym, $log_action, $currPrice, $lots, $log_reason);
                $log_stmt->execute();
            } else {
                error_log('Prepare failed (SELL): ' . $mysqli->error);
            }
        } else {
            // Di luar jam bursa, skip jual dan catat audit
            $actions[] = 'SKIP SELL ' . $sym . ' (di luar jam bursa)';
            $log_stmt = $mysqli->prepare("INSERT INTO robo_audit_log (user_id, symbol, action, price, lots, reason) VALUES (?, ?, ?, ?, ?, ?)");
            if ($log_stmt) {
                $log_action = 'SKIP_SELL';
                $log_reason = 'Jual tertunda, di luar jam bursa';
                $log_stmt->bind_param("issdis", $user_id, $sym, $log_action, $currPrice, $lots, $log_reason);
                $log_stmt->execute();
            } else {
                error_log('Prepare failed (SKIP_SELL): ' . $mysqli->error);
            }
        }
    }
}

// 2. BUY scan for current user
if ($balance > 1000000) {
    $openCountRes = $mysqli->query("SELECT COUNT(*) c FROM robo_trades WHERE status='OPEN' AND user_id={$user_id}");
    $openCount = $openCountRes ? (int)$openCountRes->fetch_assoc()['c'] : 0;

    if ($openCount < 10) {
        $symbols = [];
        $resSym = $mysqli->query("SELECT DISTINCT symbol FROM prices");
        while ($resSym && ($r = $resSym->fetch_assoc())) {
            if (!empty($r['symbol'])) {
                $symbols[] = $r['symbol'];
            }
        }

        $buyCandidates = [];
        foreach ($symbols as $sym) {
            if (strtoupper(substr((string)$sym, -3)) !== '.JK') {
                continue;
            }
            $symEsc = $mysqli->real_escape_string($sym);
            $cek = $mysqli->query("SELECT id FROM robo_trades WHERE symbol='{$symEsc}' AND status='OPEN' AND user_id={$user_id}");
            if ($cek && $cek->num_rows > 0) {
                continue;
            }

            $prices = fetch_prices($mysqli, $sym, 30);
            if (count($prices) < 25) {
                continue;
            }

            $closes = array_column($prices, 'close');
            $vols = array_column($prices, 'volume');
            $i = count($closes) - 1;

            $currPrice = (float)$closes[$i];
            if ($currPrice < 50 || (int)$vols[$i] < 50000) {
                continue;
            }

            $sma5 = sma($closes, 5);
            $sma20 = sma($closes, 20);
            if (!isset($sma5[$i], $sma20[$i], $sma5[$i - 1], $sma20[$i - 1])) {
                continue;
            }

            $avgVol5 = array_sum(array_slice($vols, -5)) / 5;
            if ($sma5[$i - 1] <= $sma20[$i - 1] && $sma5[$i] > $sma20[$i] && $vols[$i] > $avgVol5 * 1.5) {
                $volRatio = $avgVol5 > 0 ? ($vols[$i] / $avgVol5) : 1;
                $smaSpreadPct = $sma20[$i] > 0 ? (($sma5[$i] - $sma20[$i]) / $sma20[$i]) * 100 : 0;
                $ret5 = 0;
                if ($i >= 5 && $closes[$i - 5] > 0) {
                    $ret5 = (($currPrice - $closes[$i - 5]) / $closes[$i - 5]) * 100;
                }

                $score = 55;
                $score += min(25, max(0, ($volRatio - 1.5) * 20));
                $score += min(10, max(0, $smaSpreadPct * 2));
                $score += min(10, max(0, $ret5));
                $score = (int)max(0, min(99, round($score)));

                $buyCandidates[] = [
                    'symbol' => $sym,
                    'price' => $currPrice,
                    'score' => $score,
                    'reason' => 'Strong Golden Cross & Volume Breakout (AI) | Score ' . $score . '/99'
                ];
            }
        }

        usort($buyCandidates, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        $bought = 0;
        foreach ($buyCandidates as $cand) {
            if ($balance < 1000000 || $bought >= 2) {
                break;
            }

            $currentOpenCount = $openCount + $bought;
            $remainingSlots = max(1, $TARGET_POSITIONS - $currentOpenCount);
            // --- Pembagian modal proporsional sesuai score ---
            $totalScore = 0;
            foreach ($buyCandidates as $bc) { $totalScore += $bc['score']; }
            $portion = $totalScore > 0 ? ($cand['score'] / $totalScore) : 0;
            $alloc = floor($portion * min($balance, $MAX_ALLOC * $remainingSlots));
            if ($alloc < 1000000) {
                continue;
            }
            $buyPrice = getRealtimePriceYahoo($cand['symbol']);
            if ($buyPrice <= 0) {
                // Jika harga real-time gagal, skip pembelian dan log pesan
                $actions[] = 'SKIP ' . $cand['symbol'] . ' (harga real-time tidak tersedia, pembelian dibatalkan)';
                // Audit log SKIP
                $log_stmt = $mysqli->prepare("INSERT INTO robo_audit_log (user_id, symbol, action, price, lots, reason) VALUES (?, ?, ?, ?, ?, ?)");
                if ($log_stmt) {
                    $log_symbol = $cand['symbol'];
                    $log_action = 'SKIP';
                    $log_price = 0.0;
                    $log_lots = 0;
                    $log_reason = 'Harga real-time tidak tersedia';
                    $log_stmt->bind_param("issdis", $user_id, $log_symbol, $log_action, $log_price, $log_lots, $log_reason);
                    $log_stmt->execute();
                } else {
                    error_log('Prepare failed (SKIP): ' . $mysqli->error);
                }
                continue;
            }
            $lots = (int)floor($alloc / ($buyPrice * 100));
            if ($lots <= 0) {
                continue;
            }
            $buyValue = $lots * 100 * $buyPrice;
            $symEsc = $mysqli->real_escape_string($cand['symbol']);
            $reason = $cand['reason'];
            $reasonEsc = $mysqli->real_escape_string($reason);
            $buyDate = date('Y-m-d');
            $mysqli->query("INSERT INTO robo_trades (user_id, symbol, buy_price, buy_date, buy_reason, lots, status) VALUES ({$user_id}, '{$symEsc}', {$buyPrice}, '{$buyDate}', '{$reasonEsc}', {$lots}, 'OPEN')");
            $mysqli->query("UPDATE users SET robo_balance = robo_balance - {$buyValue} WHERE id = {$user_id}");
            $balance -= $buyValue;
            $actions[] = 'BUY ' . $cand['symbol'] . ' (' . $lots . ' lot, score ' . ($cand['score'] ?? 0) . ')';
            // Audit log BUY
            $log_stmt = $mysqli->prepare("INSERT INTO robo_audit_log (user_id, symbol, action, price, lots, reason) VALUES (?, ?, ?, ?, ?, ?)");
            if ($log_stmt) {
                $log_symbol = $cand['symbol'];
                $log_action = 'BUY';
                $log_price = $buyPrice;
                $log_lots = $lots;
                $log_reason = $reason;
                $log_stmt->bind_param("issdis", $user_id, $log_symbol, $log_action, $log_price, $log_lots, $log_reason);
                $log_stmt->execute();
            } else {
                error_log('Prepare failed (BUY): ' . $mysqli->error);
            }
            $bought++;
        }
    }
}

if (count($actions) === 0) {
    $msg = 'Robot dijalankan: tidak ada sinyal baru (HOLD) saat ini.';
} else {
    $msg = 'Robot dijalankan: ' . implode(', ', array_slice($actions, 0, 3));
    if (count($actions) > 3) {
        $msg .= ', ...';
    }
}

header('Location: portfolio.php?robo_run=ok&msg=' . urlencode($msg));
exit;
