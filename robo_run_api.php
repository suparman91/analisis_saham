<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
require_once __DIR__ . '/robo_runtime.php';

date_default_timezone_set('Asia/Jakarta');
$mysqli = db_connect();
require_subscription($mysqli);

$user_id = get_user_id();
$actions = [];

// Copy logic dari robo_run_now.php, tapi return JSON, tidak redirect
// ...existing logic SELL, BUY, SEROK, seperti di robo_run_now.php...

// (Untuk ringkas, include file aslinya dan tangkap output)

// --- COPY LOGIC UTAMA DARI robo_run_now.php TANPA REDIRECT ---

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
	$valid = array_values(array_filter($closes, function ($v) { return $v !== null; }));
	if (empty($valid)) {
		$cache[$symbol] = 0.0;
		return 0.0;
	}
	$price = (float)end($valid);
	$cache[$symbol] = $price;
	return $price;
}

$resUser = $mysqli->query("SELECT id, email, robo_balance FROM users WHERE id = {$user_id} LIMIT 1");
if (!$resUser || $resUser->num_rows === 0) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'msg' => 'User tidak ditemukan.']);
	exit;
}
$user = $resUser->fetch_assoc();
$balance = (float)$user['robo_balance'];
$roboSettings = robo_get_user_settings($mysqli, $user_id);
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

// 1. SELL check for open positions
$open = $mysqli->query("SELECT * FROM robo_trades WHERE status='OPEN' AND user_id={$user_id}");
while ($open && ($t = $open->fetch_assoc())) {
	$sym = $t['symbol'];
	$buyPrice = (float)$t['buy_price'];
	$buyReasonNow = isset($t['buy_reason']) ? (string)$t['buy_reason'] : '';
	$prices = fetch_prices($mysqli, $sym, 10);
	if (count($prices) < 1 || $buyPrice <= 0) {
		continue;
	}
	$latest = $prices[count($prices) - 1];
	$dbClose = (float)$latest['close'];
	$currPrice = getRealtimePriceYahoo($sym);
	if ($currPrice <= 0) {
		$currPrice = $dbClose;
	}
	$sellDate = date('Y-m-d');
	$pct = ($currPrice - $buyPrice) / $buyPrice;
	$sell = false;
	$reason = '';
	// Auto-serok: jika turun >5% dari harga beli, jangan langsung cutloss, lakukan averaging down jika ada cash cukup
	if ($pct <= -0.05 && $balance > 1000000) {
		$lots_serok = (int)floor(min($balance, 3000000) / ($currPrice * 100));
		if ($lots_serok > 0) {
			$buyValue = $lots_serok * 100 * $currPrice;
			$reasonSerok = 'Averaging Down (Serok AI)';
			$reasonCombined = trim(($buyReasonNow !== '' ? $buyReasonNow . ' | ' : '') . $reasonSerok . ' @' . round($currPrice, 2));
			$reasonEsc = $mysqli->real_escape_string($reasonCombined);
			$old_lots = (int)$t['lots'];
			$new_lots = $old_lots + $lots_serok;
			$new_avg_price = (($buyPrice * $old_lots) + ($currPrice * $lots_serok)) / $new_lots;
			$tradeId = (int)$t['id'];
			$mysqli->query("UPDATE robo_trades SET buy_price={$new_avg_price}, lots={$new_lots}, buy_reason='{$reasonEsc}' WHERE id={$tradeId} AND user_id={$user_id}");
			$mysqli->query("UPDATE users SET robo_balance = robo_balance - {$buyValue} WHERE id = {$user_id}");
			$balance -= $buyValue;
			$actions[] = 'SEROK ' . $sym . ' (' . $lots_serok . ' lot)';
			continue;
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
		if (robo_is_bursa_open()) {
			$lots = (int)$t['lots'];
			$tid = (int)$t['id'];
			$pl = ($currPrice - $buyPrice) * ($lots * 100);
			$value = $currPrice * $lots * 100;
			$reasonEsc = $mysqli->real_escape_string($reason);
			$mysqli->query("UPDATE robo_trades SET status='CLOSED', sell_price={$currPrice}, sell_date='{$sellDate}', sell_reason='{$reasonEsc}', profit_loss_rp={$pl}, profit_loss_pct=" . ($pct * 100) . " WHERE id={$tid} AND user_id={$user_id}");
			$mysqli->query("UPDATE users SET robo_balance = robo_balance + {$value} WHERE id = {$user_id}");
			$balance += $value;
			$actions[] = 'SELL ' . $sym . ' (' . round($pct * 100, 2) . '%)';
		} else {
			$actions[] = 'SKIP SELL ' . $sym . ' (di luar jam bursa)';
		}
	}
}

// 2. BUY scan for current user
if ($balance > 1000000 && $ALLOW_NEW_BUYS) {
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
			$analysis = analyze_symbol($mysqli, $sym);
			if (!isset($analysis['signal']) || !in_array((string)$analysis['signal'], ['BUY', 'STRONG BUY'], true)) {
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
				if ((string)$analysis['signal'] === 'STRONG BUY') {
					$score += 5;
				}
				$score = (int)max(0, min(99, round($score)));
				if ($score < $MIN_ENTRY_SCORE) {
					continue;
				}
				$buyCandidates[] = [
					'symbol' => $sym,
					'price' => $currPrice,
					'score' => $score,
					'reason' => 'Strong Golden Cross & Volume Breakout (AI) | Score ' . $score . '/99'
				];
			}
		}
		usort($buyCandidates, function ($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
		$bought = 0;
		foreach ($buyCandidates as $cand) {
			if ($balance < 1000000 || $bought >= $MAX_BUY_PER_RUN) { break; }
			$currentOpenCount = $openCount + $bought;
			$remainingSlots = max(1, $TARGET_POSITIONS - $currentOpenCount);
			$totalScore = 0;
			foreach ($buyCandidates as $bc) { $totalScore += $bc['score']; }
			$portion = $totalScore > 0 ? ($cand['score'] / $totalScore) : 0;
			$alloc = floor($portion * min($balance, $MAX_ALLOC * $remainingSlots));
			if ($alloc < 1000000) { continue; }
			$buyPrice = getRealtimePriceYahoo($cand['symbol']);
			if ($buyPrice <= 0) { $buyPrice = (float)$cand['price']; }
			$lots = (int)floor($alloc / ($buyPrice * 100));
			if ($lots <= 0) { continue; }
			$buyValue = $lots * 100 * $buyPrice;
			$symEsc = $mysqli->real_escape_string($cand['symbol']);
			$reason = $cand['reason'];
			$reasonEsc = $mysqli->real_escape_string($reason);
			$buyDate = date('Y-m-d');
			$existingRes = $mysqli->query("SELECT id, buy_price, lots, buy_reason FROM robo_trades WHERE user_id={$user_id} AND symbol='{$symEsc}' AND status='OPEN' LIMIT 1");
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
				$mysqli->query("UPDATE robo_trades SET buy_price={$newAvgPrice}, lots={$newLots}, buy_reason='{$mergedReasonEsc}' WHERE id={$tradeId} AND user_id={$user_id}");
			} else {
				$mysqli->query("INSERT INTO robo_trades (user_id, symbol, buy_price, buy_date, buy_reason, lots, status) VALUES ({$user_id}, '{$symEsc}', {$buyPrice}, '{$buyDate}', '{$reasonEsc}', {$lots}, 'OPEN')");
			}
			$mysqli->query("UPDATE users SET robo_balance = robo_balance - {$buyValue} WHERE id = {$user_id}");
			$balance -= $buyValue;
			$actions[] = 'BUY ' . $cand['symbol'] . ' (' . $lots . ' lot, score ' . ($cand['score'] ?? 0) . ')';
			$bought++;
		}
	}
} elseif (!$ALLOW_NEW_BUYS) {
	$actions[] = 'MONITOR ONLY (' . $runtimeConfig['status_note'] . ')';
}

if (count($actions) === 0) {
	$msg = 'Robot dijalankan: tidak ada sinyal baru (HOLD) saat ini.';
} else {
	$msg = 'Robot dijalankan: ' . implode(', ', array_slice($actions, 0, 3));
	if (count($actions) > 3) {
		$msg .= ', ...';
	}
}

header('Content-Type: application/json');
echo json_encode([
	'status' => 'ok',
	'msg' => $msg,
	'actions' => $actions,
	'runtime' => [
		'profile' => $runtimeConfig['profile_label'],
		'min_entry_score' => $MIN_ENTRY_SCORE,
		'max_buy_per_run' => $MAX_BUY_PER_RUN,
		'allow_new_buys' => $ALLOW_NEW_BUYS,
		'status_note' => $runtimeConfig['status_note'],
		'market' => $marketContext,
	]
]);
