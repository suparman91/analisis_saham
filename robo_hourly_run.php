<?php
/**
 * Robo Hourly Run - AJAX endpoint for hourly portfolio analysis
 * Returns JSON with latest decisions and updates
 * Supports averaging-down logic for positions with loss < 5%
 */
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';
require_once __DIR__ . '/telegram_crypto.php';

ini_set('display_errors', '0');
ini_set('html_errors', '0');
ob_start();
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

$user_id = get_user_id();
$mysqli = db_connect();

// Config for averaging-down
$LOSS_WARN_PCT = -0.05;    // -5% = stop accumulating, start warning
$LOSS_CRITICAL_PCT = -0.10; // -10% = force consideration to sell or stop
$LOSS_HOLDOFF_PCT = -0.02;  // -2% = still aggressive on accumulation
$MAX_ACCUMULATION_TIMES = 3; // Max times we buy same stock to average

function getRealtimePriceYahoo($symbol) {
    static $cache = [];
    if (isset($cache[$symbol])) {
        return $cache[$symbol];
    }

    if (!function_exists('curl_init')) {
        global $mysqli;
        if ($mysqli instanceof mysqli) {
            $stmt = $mysqli->prepare("SELECT close FROM prices WHERE symbol = ? ORDER BY date DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $symbol);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && ($r = $res->fetch_assoc())) {
                    $fallback = (float)$r['close'];
                    $cache[$symbol] = $fallback;
                    return $fallback;
                }
            }
        }
        $cache[$symbol] = 0.0;
        return 0.0;
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

try {
    require_subscription($mysqli);
    
    // Get user's current positions
    $stmt = $mysqli->prepare("
        SELECT
            t.symbol,
            SUM(t.lots) AS total_lots,
            SUM(t.buy_price * t.lots) / NULLIF(SUM(t.lots), 0) AS entry_price,
            MIN(t.buy_date) AS first_buy_date,
            COUNT(*) AS accumulation_count
        FROM robo_trades t
        WHERE t.user_id = ? AND t.status = 'OPEN'
        GROUP BY t.symbol
        ORDER BY first_buy_date ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $decisions = [];
    $accumulation_buys = [];
    $forced_sells = [];
    $hold_signals = [];
    
    while ($row = $result->fetch_assoc()) {
        $symbol = $row['symbol'];
        $entry_price = (float)$row['entry_price'];
        $lots = (int)$row['total_lots'];
        $accumulation_count = (int)$row['accumulation_count'];
        
        // Get realtime price
        $live_price = getRealtimePriceYahoo($symbol);
        
        if ($live_price <= 0) {
            continue; // Skip if we can't get price
        }
        
        // Calculate loss percentage
        $qty = $lots * 100;
        $current_value = $live_price * $qty;
        $entry_value = $entry_price * $qty;
        $pl_rp = $current_value - $entry_value;
        if ($entry_value <= 0) {
            continue;
        }
        $pl_pct = ($pl_rp / $entry_value);
        
        // Decision logic
        if ($pl_pct >= 0) {
            // Position is profitable, no action needed
            $hold_signals[] = [
                'symbol' => $symbol,
                'entry' => $entry_price,
                'current' => $live_price,
                'pl_pct' => round($pl_pct * 100, 2),
                'reason' => 'Menguntungkan, tahan posisi'
            ];
        } 
        elseif ($pl_pct >= $LOSS_HOLDOFF_PCT && $pl_pct < 0) {
            // Loss between -2% to 0%, aggressive accumulation
            if ($accumulation_count < $MAX_ACCUMULATION_TIMES) {
                // Recommend accumulation (averaging down)
                $accumulation_buys[] = [
                    'symbol' => $symbol,
                    'entry' => $entry_price,
                    'current' => $live_price,
                    'pl_pct' => round($pl_pct * 100, 2),
                    'accumulation_count' => $accumulation_count,
                    'reason' => 'Rugi kecil, akumulasi untuk rata-rata harga'
                ];
            } else {
                $hold_signals[] = [
                    'symbol' => $symbol,
                    'entry' => $entry_price,
                    'current' => $live_price,
                    'pl_pct' => round($pl_pct * 100, 2),
                    'reason' => 'Sudah akumulasi ' . $MAX_ACCUMULATION_TIMES . 'x, tahan'
                ];
            }
        }
        elseif ($pl_pct >= $LOSS_WARN_PCT && $pl_pct < $LOSS_HOLDOFF_PCT) {
            // Loss between -2% to -5%, warning zone - hold but monitor
            $hold_signals[] = [
                'symbol' => $symbol,
                'entry' => $entry_price,
                'current' => $live_price,
                'pl_pct' => round($pl_pct * 100, 2),
                'reason' => 'Rugi ' . round($pl_pct * 100, 2) . '%, tunggu recovery'
            ];
        }
        elseif ($pl_pct >= $LOSS_CRITICAL_PCT && $pl_pct < $LOSS_WARN_PCT) {
            // Loss between -5% to -10%, kelonggaran zone - give time but monitor closely
            $hold_signals[] = [
                'symbol' => $symbol,
                'entry' => $entry_price,
                'current' => $live_price,
                'pl_pct' => round($pl_pct * 100, 2),
                'reason' => 'Rugi ' . round($pl_pct * 100, 2) . '%, monitor kelonggaran'
            ];
        }
        else {
            // Loss > -10%, force consideration
            $forced_sells[] = [
                'symbol' => $symbol,
                'entry' => $entry_price,
                'current' => $live_price,
                'pl_pct' => round($pl_pct * 100, 2),
                'reason' => 'Rugi ' . round($pl_pct * 100, 2) . '%, pertimbangkan jual'
            ];
        }
    }
    
    // Get new candidates for analysis
    $candidates = [];
    $stmt2 = $mysqli->prepare("SELECT symbol FROM stocks LIMIT 20");
    if (!$stmt2) {
        error_log("Prepare failed for stocks: " . $mysqli->error);
    } else {
        $stmt2->execute();
        $stock_result = $stmt2->get_result();
        $new_candidates_count = 0;
        
        while ($stock_row = $stock_result->fetch_assoc()) {
            $sym = $stock_row['symbol'];
            
            // Skip if already held
            $held_stmt = $mysqli->prepare("SELECT id FROM robo_trades WHERE user_id = ? AND symbol = ? AND status = 'OPEN' LIMIT 1");
            if (!$held_stmt) {
                error_log("Prepare failed for held check: " . $mysqli->error);
                continue;
            }
            $held_stmt->bind_param("is", $user_id, $sym);
            $held_stmt->execute();
            if ($held_stmt->get_result()->num_rows > 0) {
                $held_stmt->close();
                continue;
            }
            $held_stmt->close();
            
            // Analyze for new opportunity
            $analysis = analyze_symbol($mysqli, $sym, 'day');
            if (!is_array($analysis) || isset($analysis['error'])) {
                continue;
            }
            
            $signal = (string)($analysis['signal'] ?? '');
            if (strpos($signal, 'BUY') !== false) {
                $new_candidates_count++;
                if ($new_candidates_count <= 5) { // Show top 5
                    $score = (int)($analysis['fund_score'] ?? 0);
                    if ($score <= 0) {
                        $score = ($signal === 'STRONG BUY') ? 85 : 70;
                    }
                    $candidates[] = [
                        'symbol' => $sym,
                        'signal' => $signal,
                        'score' => $score,
                        'reason' => (string)($analysis['signal_details'] ?? 'Sinyal BUY terdeteksi')
                    ];
                }
            }
        }
        $stmt2->close();
    }
    
    // Format response
    $response = [
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'success',
        'accumulation_buys' => $accumulation_buys,
        'forced_sells' => $forced_sells,
        'hold_signals' => $hold_signals,
        'new_candidates' => $candidates,
        'summary' => [
            'accumulation_candidates' => count($accumulation_buys),
            'hold_positions' => count($hold_signals),
            'forced_sell_candidates' => count($forced_sells),
            'new_opportunities' => $new_candidates_count
        ]
    ];
    
    $noise = trim((string)ob_get_clean());
    if ($noise !== '') {
        error_log('robo_hourly_run noise: ' . $noise);
    }
    echo json_encode($response);
    $stmt->close();
    
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$mysqli->close();
restore_error_handler();
?>
