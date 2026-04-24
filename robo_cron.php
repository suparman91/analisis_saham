<?php
set_time_limit(0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
require_once __DIR__ . '/robo_runtime.php';
require_once __DIR__ . '/telegram_crypto.php';

date_default_timezone_set('Asia/Jakarta');

$mysqli = db_connect();

// Fungsi Telegram Alert Robo
function sendRoboAlert($mysqli, $msg) {
    try {
        $bot_token = tg_bot_token();
        if ($bot_token === '') {
            return;
        }
        
        $res = $mysqli->query("SELECT chat_id_encrypted FROM telegram_subscribers WHERE is_active=1");
        if(!$res || $res->num_rows === 0) return;
        
        while($r = $res->fetch_assoc()) {
            $chat_id = tg_decrypt($r['chat_id_encrypted']);
            if($chat_id) {
                $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
                $data = ["chat_id" => $chat_id, "text" => $msg, "parse_mode" => "HTML"];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    } catch(Exception $e) {}
}

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

echo "[ ROBO TRADER AI EXECUTION ]\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";

// Shared symbol list for all users
$symbols = [];
$resSym = $mysqli->query("SELECT DISTINCT symbol FROM prices");
while ($resSym && ($r = $resSym->fetch_assoc())) {
    if (!empty($r['symbol'])) {
        $symbols[] = $r['symbol'];
    }
}

// Process robo logic per user so each account gets its own simulation result
$users = $mysqli->query("SELECT id, email, role, subscription_end, robo_balance FROM users WHERE robo_balance IS NOT NULL");
while ($users && ($u = $users->fetch_assoc())) {
    $uid = (int)$u['id'];
    $isEligible = ($u['role'] === 'admin') || (!empty($u['subscription_end']) && $u['subscription_end'] >= date('Y-m-d'));
    if (!$isEligible) {
        continue;
    }

    echo "-- User #{$uid} ({$u['email']}) --\n";
    $balance = (float)$u['robo_balance'];
    $roboSettings = robo_get_user_settings($mysqli, $uid);
    $marketContext = robo_get_market_context();
    $runtimeConfig = robo_build_runtime_config($roboSettings, $marketContext);
    $TP_PCT = (float)$runtimeConfig['tp_pct'];
    $SL_PCT = (float)$runtimeConfig['sl_pct'];
    $MAX_ALLOC = (int)$runtimeConfig['max_alloc'];
    $TARGET_POSITIONS = (int)$runtimeConfig['target_positions'];
    $ALLIN_SCORE = (int)$runtimeConfig['allin_score'];
    $MAX_BUY_PER_RUN = (int)$runtimeConfig['max_buy_per_run'];
    $MIN_ENTRY_SCORE = (int)$runtimeConfig['min_entry_score'];
    $ALLOW_NEW_BUYS = !empty($runtimeConfig['allow_new_buys']);

    echo "Runtime [U{$uid}] {$runtimeConfig['profile_label']} | {$marketContext['session']} | {$marketContext['sentiment_label']} | min score {$MIN_ENTRY_SCORE} | max buy {$MAX_BUY_PER_RUN}\n";

    // 1. CHECK OPEN POSITIONS (EXIT STRATEGY)
    $open = $mysqli->query("SELECT * FROM robo_trades WHERE status = 'OPEN' AND user_id = {$uid}");
    while ($open && ($t = $open->fetch_assoc())) {
        $sym = $t['symbol'];
        $buy_p = (float)$t['buy_price'];

        $prices = fetch_prices($mysqli, $sym, 5);
        if (count($prices) < 1) {
            continue;
        }

        $dbClose = (float)$prices[count($prices)-1]['close'];
        $curr_p = getRealtimePriceYahoo($sym);
        if ($curr_p <= 0) {
            $curr_p = $dbClose;
        }
        $curr_date = date('Y-m-d');
        $pct = ($buy_p > 0) ? (($curr_p - $buy_p) / $buy_p) : 0;

        $sell = false;
        $reason = '';

        if ($pct >= $TP_PCT) {
            $sell = true;
            $reason = "Take Profit (+" . round($pct * 100, 2) . "%)";
        } elseif ($pct <= $SL_PCT) {
            $sell = true;
            $reason = "Stop Loss (" . round($pct * 100, 2) . "%)";
        }

        if ($sell) {
            if (!robo_is_bursa_open()) {
                echo "SKIP SELL [U{$uid}] {$sym} (di luar jam bursa).\n";
                continue;
            }

            $lots = (int)$t['lots'];
            $pl = ($curr_p - $buy_p) * ($lots * 100);
            $val = ($curr_p * $lots * 100);
            $tid = (int)$t['id'];

            $sellReasonEsc = $mysqli->real_escape_string($reason);
            $mysqli->query("UPDATE robo_trades SET status='CLOSED', sell_price={$curr_p}, sell_date='{$curr_date}', sell_reason='{$sellReasonEsc}', profit_loss_rp={$pl}, profit_loss_pct=" . ($pct * 100) . " WHERE id={$tid} AND user_id={$uid}");
            $mysqli->query("UPDATE users SET robo_balance = robo_balance + {$val} WHERE id = {$uid}");
            $balance += $val;
            echo "SELL [U{$uid}] {$sym} @ {$curr_p}. Reason: {$reason}. P/L: Rp {$pl}\n";

            $pct_fmt = number_format($pct * 100, 2);
            $pl_fmt = number_format($pl, 0, ',', '.');
            $msg = "🔔 <b>ROBO-TRADER SELL ALERT</b>\nUser: <b>#{$uid}</b>\n\nSaham: <b>{$sym}</b>\nHarga Jual: Rp " . number_format($curr_p, 0, ',', '.') . "\nP/L: <b>{$pct_fmt}% (Rp {$pl_fmt})</b>\n\nAlasan Jual: <b>{$reason}</b>";
            sendRoboAlert($mysqli, $msg);
        }
    }

    // 2. CHECK ALGO SIGNALS (ENTRY STRATEGY)
    if ($balance <= 1000000) {
        echo "Out of cash [U{$uid}]! Balance: Rp " . number_format($balance) . "\n";
        continue;
    }

    if (!$ALLOW_NEW_BUYS) {
        echo "Monitor only [U{$uid}] - {$runtimeConfig['status_note']}\n";
        continue;
    }

    $open_count_res = $mysqli->query("SELECT COUNT(*) AS c FROM robo_trades WHERE status = 'OPEN' AND user_id = {$uid}");
    $open_count = (int)$open_count_res->fetch_assoc()['c'];

    if ($open_count >= 10) {
        echo "Portfolio is FULL [U{$uid}] (10 stocks max). Waiting for SL/TP.\n";
        continue;
    }

    $buy_candidates = [];
    foreach ($symbols as $sym) {
        $symEsc = $mysqli->real_escape_string($sym);
        $cek = $mysqli->query("SELECT id FROM robo_trades WHERE symbol='{$symEsc}' AND status='OPEN' AND user_id={$uid}");
        if ($cek && $cek->num_rows > 0) {
            continue;
        }

        $prices = fetch_prices($mysqli, $sym, 30);
        if (count($prices) < 25) {
            continue;
        }

        $closes = array_column($prices, 'close');
        $vols = array_column($prices, 'volume');
        $latestIdx = count($closes) - 1;

        $curr_p = (float)$closes[$latestIdx];
        if ($curr_p < 50) {
            continue;
        }
        if ((int)$vols[$latestIdx] < 50000) {
            continue;
        }

        $sma5 = sma($closes, 5);
        $sma20 = sma($closes, 20);
        if (isset($sma5[$latestIdx], $sma20[$latestIdx], $sma5[$latestIdx - 1], $sma20[$latestIdx - 1])) {
            $analysis = analyze_symbol($mysqli, $sym);
            if (!isset($analysis['signal']) || !in_array((string)$analysis['signal'], ['BUY', 'STRONG BUY'], true)) {
                continue;
            }

            $avg_vol_5 = array_sum(array_slice($vols, -5)) / 5;
            if ($sma5[$latestIdx - 1] <= $sma20[$latestIdx - 1] && $sma5[$latestIdx] > $sma20[$latestIdx] && $vols[$latestIdx] > $avg_vol_5 * 1.5) {
                $volRatio = $avg_vol_5 > 0 ? ($vols[$latestIdx] / $avg_vol_5) : 1;
                $smaSpreadPct = $sma20[$latestIdx] > 0 ? (($sma5[$latestIdx] - $sma20[$latestIdx]) / $sma20[$latestIdx]) * 100 : 0;
                $ret5 = 0;
                if ($latestIdx >= 5 && $closes[$latestIdx - 5] > 0) {
                    $ret5 = (($curr_p - $closes[$latestIdx - 5]) / $closes[$latestIdx - 5]) * 100;
                }

                $score = 55;
                $score += min(25, max(0, ($volRatio - 1.5) * 20));
                $score += min(10, max(0, $smaSpreadPct * 2));
                $score += min(10, max(0, $ret5));
                if ((string)$analysis['signal'] === 'STRONG BUY') {
                    $score += 5;
                }
                $score = (int)max(0, min(99, round($score)));

                if ($score < $MIN_ENTRY_SCORE) {
                    continue;
                }

                $buy_candidates[] = [
                    'symbol' => $sym,
                    'price' => $curr_p,
                    'score' => $score,
                    'reason' => 'Strong Golden Cross & Volume Breakout (AI) | Score ' . $score . '/99'
                ];
            }
        }
    }

    // Execute BUY for top 2 strongest candidates
    usort($buy_candidates, function ($a, $b) {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    $b = 0;
    foreach ($buy_candidates as $cand) {
        if ($balance < 1000000 || $b >= $MAX_BUY_PER_RUN) {
            break;
        }

        $currentOpenCount = $open_count + $b;
        $remainingSlots = max(1, $TARGET_POSITIONS - $currentOpenCount);
        $equalAlloc = (float)floor($balance / $remainingSlots);
        $isAllIn = (($cand['score'] ?? 0) >= $ALLIN_SCORE) && ($currentOpenCount === 0);

        if ($isAllIn) {
            $alloc = $balance;
        } else {
            $alloc = min($MAX_ALLOC, $equalAlloc);
        }

        if ($alloc < 1000000) {
            continue;
        }

        $symEsc = $mysqli->real_escape_string($cand['symbol']);
        $reason = $cand['reason'];
        if ($isAllIn) {
            $reason .= ' | High Conviction ALL-IN';
        }
        $reasonEsc = $mysqli->real_escape_string($reason);
        $buyDate = date('Y-m-d');
        $buyPrice = getRealtimePriceYahoo($cand['symbol']);
        if ($buyPrice <= 0) {
            $buyPrice = (float)$cand['price'];
        }

        $lots = (int)floor($alloc / ($buyPrice * 100));
        if ($lots <= 0) {
            continue;
        }
        $bought_val = $lots * 100 * $buyPrice;

        $existingRes = $mysqli->query("SELECT id, buy_price, lots, buy_reason FROM robo_trades WHERE user_id={$uid} AND symbol='{$symEsc}' AND status='OPEN' LIMIT 1");
        if ($existingRes && $existingRes->num_rows > 0) {
            $ex = $existingRes->fetch_assoc();
            $oldLots = (int)$ex['lots'];
            $oldPrice = (float)$ex['buy_price'];
            $newLots = $oldLots + $lots;
            $newAvgPrice = (($oldPrice * $oldLots) + ($buyPrice * $lots)) / $newLots;
            $prevReason = isset($ex['buy_reason']) ? (string)$ex['buy_reason'] : '';
            $mergedReason = trim(($prevReason !== '' ? $prevReason . ' | ' : '') . 'ADD LOT @' . round($buyPrice, 2) . ' | ' . $reason);
            $mergedReasonEsc = $mysqli->real_escape_string($mergedReason);
            $tradeId = (int)$ex['id'];
            $mysqli->query("UPDATE robo_trades SET buy_price={$newAvgPrice}, lots={$newLots}, buy_reason='{$mergedReasonEsc}' WHERE id={$tradeId} AND user_id={$uid}");
        } else {
            $mysqli->query("INSERT INTO robo_trades (user_id, symbol, buy_price, buy_date, buy_reason, lots, status) VALUES ({$uid}, '{$symEsc}', {$buyPrice}, '{$buyDate}', '{$reasonEsc}', {$lots}, 'OPEN')");
        }
        $mysqli->query("UPDATE users SET robo_balance = robo_balance - {$bought_val} WHERE id = {$uid}");

        $balance -= $bought_val;
        echo "BUY [U{$uid}] {$cand['symbol']} @ {$buyPrice} ({$lots} lots) - {$reason}\n";

        $val_fmt = number_format($bought_val, 0, ',', '.');
        $price_fmt = number_format($buyPrice, 0, ',', '.');
        $msg = "🚀 <b>ROBO-TRADER BUY ALERT</b>\nUser: <b>#{$uid}</b>\n\nSaham: <b>{$cand['symbol']}</b>\nHarga Beli: <b>Rp {$price_fmt}</b>\nPembelian: {$lots} Lot (Total Rp {$val_fmt})\n\nSinyal AI: <b>{$reason}</b>";
        sendRoboAlert($mysqli, $msg);

        $b++;
    }
}

echo "Robo execution done.\n";
