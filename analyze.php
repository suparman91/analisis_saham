<?php
// Analysis functions: SMA, EMA, RSI, MACD, and a simple fundamental score
require_once __DIR__ . '/db.php';

function fetch_prices($mysqli, $symbol, $limit = 500) {
    $stmt = $mysqli->prepare('SELECT date, open, high, low, close, volume FROM prices WHERE symbol=? ORDER BY date ASC LIMIT ?');
    $stmt->bind_param('si', $symbol, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $arr = [];
    while ($r = $res->fetch_assoc()) {
        $arr[] = [
            'date' => $r['date'],
            'open' => (float)$r['open'],
            'high' => (float)$r['high'],
            'low' => (float)$r['low'],
            'close' => (float)$r['close'],
            'volume' => (int)$r['volume']
        ];
    }
    return $arr;
}

function auto_fetch_history($mysqli, $symbol) {
    $mysqli->query("INSERT IGNORE INTO stocks (symbol, name) VALUES ('".$mysqli->real_escape_string($symbol)."', 'Auto Added')");

    // Try to fetch 6 months of historical data from Yahoo Finance
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?range=6mo&interval=1d";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $json = curl_exec($ch);
    curl_close($ch);
    
    if (!$json) return false;
    $data = json_decode($json, true);
    if (!isset($data['chart']['result'][0]['timestamp'])) return false;
    
    $result = $data['chart']['result'][0];
    $timestamps = $result['timestamp'];
    $quote = $result['indicators']['quote'][0];
    
    $sql = "INSERT INTO prices (symbol, date, open, high, low, close, volume) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE open=IF(open > 0, open, VALUES(open)), high=IF(VALUES(high) > high, VALUES(high), high), low=IF(VALUES(low) > 0 AND VALUES(low) < low, VALUES(low), low), close=VALUES(close), volume=IF(VALUES(volume) > volume, VALUES(volume), volume)";
    $stmt = $mysqli->prepare($sql);
    
    $count = 0;
    for ($i = 0; $i < count($timestamps); $i++) {
        if (!isset($quote['close'][$i])) continue;
        
        $dateStr = date('Y-m-d', $timestamps[$i]);
        $open = $quote['open'][$i] ?? $quote['close'][$i];
        $high = $quote['high'][$i] ?? $quote['close'][$i];
        $low = $quote['low'][$i] ?? $quote['close'][$i];
        $close = $quote['close'][$i];
        $volume = $quote['volume'][$i] ?? 0;
        
        // Skip nulls
        if ($close === null) continue;
        
        $stmt->bind_param('ssddddi', $symbol, $dateStr, $open, $high, $low, $close, $volume);
        $stmt->execute();
        $count++;
    }
    return $count > 0;
}

function sma(array $data, int $period) {
    $res = [];
    $sum = 0.0;
    for ($i=0;$i<count($data);$i++) {
        $sum += $data[$i];
        if ($i >= $period) $sum -= $data[$i-$period];
        if ($i >= $period-1) $res[$i] = $sum / $period;
        else $res[$i] = null;
    }
    return $res;
}

function ema(array $data, int $period) {
    $res = [];
    $k = 2/($period+1);
    $ema = null;
    for ($i=0;$i<count($data);$i++) {
        $price = $data[$i];
        if ($ema === null) {
            $ema = $price; // seed
        } else {
            $ema = $price * $k + $ema * (1-$k);
        }
        $res[$i] = $ema;
    }
    return $res;
}

function rsi(array $data, int $period = 14) {
    $res = array_fill(0, count($data), null);
    $gains = 0.0;
    $losses = 0.0;
    for ($i=1;$i<count($data);$i++) {
        $change = $data[$i] - $data[$i-1];
        $gain = max(0, $change);
        $loss = max(0, -$change);
        if ($i <= $period) {
            $gains += $gain; $losses += $loss;
            if ($i == $period) {
                $avgGain = $gains / $period;
                $avgLoss = $losses / $period;
                $rs = $avgLoss == 0 ? 100 : $avgGain / $avgLoss;
                $res[$i] = 100 - (100 / (1 + $rs));
                $prevAvgGain = $avgGain; $prevAvgLoss = $avgLoss;
            }
        } else {
            $avgGain = ($prevAvgGain * ($period -1) + $gain) / $period;
            $avgLoss = ($prevAvgLoss * ($period -1) + $loss) / $period;
            $prevAvgGain = $avgGain; $prevAvgLoss = $avgLoss;
            $rs = $avgLoss == 0 ? 100 : $avgGain / $avgLoss;
            $res[$i] = 100 - (100 / (1 + $rs));
        }
    }
    return $res;
}

function macd(array $data, $fast = 12, $slow = 26, $signal = 9) {
    $emaFast = ema($data, $fast);
    $emaSlow = ema($data, $slow);
    $macd = [];
    for ($i=0;$i<count($data);$i++) {
        $macd[$i] = $emaFast[$i] - $emaSlow[$i];
    }
    $signalArr = ema($macd, $signal);
    $hist = [];
    for ($i=0;$i<count($macd);$i++) $hist[$i] = $macd[$i] - $signalArr[$i];
    return ['macd'=>$macd,'signal'=>$signalArr,'hist'=>$hist];
}

function bollinger(array $data, int $period = 20, float $mult = 2.0) {
    $basis = sma($data, $period);
    $upper = array_fill(0, count($data), null);
    $lower = array_fill(0, count($data), null);
    for ($i = 0; $i < count($data); $i++) {
        if ($i >= $period - 1) {
            $sum = 0.0;
            for ($j = $i - $period + 1; $j <= $i; $j++) $sum += pow($data[$j] - $basis[$i], 2);
            $std = sqrt($sum / $period);
            $upper[$i] = $basis[$i] + $mult * $std;
            $lower[$i] = $basis[$i] - $mult * $std;
        }
    }
    return ['middle'=>$basis, 'upper'=>$upper, 'lower'=>$lower];
}

function get_latest_fundamental($mysqli, $symbol) {
    $stmt = $mysqli->prepare('SELECT pe,pbv,roe,eps,date FROM fundamentals WHERE symbol=? ORDER BY date DESC LIMIT 1');
    $stmt->bind_param('s', $symbol);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ?: null;
}

function fundamental_score($f) {
    if (!$f) return ['score' => null, 'status' => 'N/A'];
    
    $score = 0;
    
    // PE Ratio (Valuation)
    // < 10: Undervalued (+2), 10-20: Fair (+1), > 20: Overvalued (-1)
    if ($f['pe'] !== null) {
        if ($f['pe'] > 0 && $f['pe'] <= 10) $score += 2;
        elseif ($f['pe'] > 10 && $f['pe'] <= 20) $score += 1;
        else if ($f['pe'] > 20) $score -= 1;
    }
    
    // PBV (Valuation)
    // < 1: Undervalued (+2), 1-2: Fair (+1), > 2: Overvalued (-1)
    if ($f['pbv'] !== null) {
        if ($f['pbv'] > 0 && $f['pbv'] <= 1) $score += 2;
        elseif ($f['pbv'] > 1 && $f['pbv'] <= 2) $score += 1;
        else if ($f['pbv'] > 2) $score -= 1;
    }
    
    // ROE (Profitability)
    // > 15%: Excellent (+2), 5-15%: Good (+1), < 5%: Bad (-1)
    if ($f['roe'] !== null) {
        if ($f['roe'] >= 15) $score += 2;
        elseif ($f['roe'] >= 5 && $f['roe'] < 15) $score += 1;
        else if ($f['roe'] < 5) $score -= 1;
    }
    
    $status = 'FAIR';
    if ($score >= 4) $status = 'UNDERVALUED (Good to Buy)';
    elseif ($score <= 0) $status = 'OVERVALUED (Expensive / Risky)';
    
    // Calculate 0-100 normalized score for legacy compatibility
    $peScoreC = $f['pe'] ? max(0, 50 - $f['pe']) : 25;
    $pbvScoreC = $f['pbv'] ? max(0, 20 - abs($f['pbv'] - 1) * 10) : 10;
    $roeScoreC = $f['roe'] ? min(30, $f['roe']) : 10;
    $total100 = round($peScoreC + $pbvScoreC + $roeScoreC, 2);

    return [
        'score' => $total100,
        'points' => $score,
        'status' => $status
    ];
}

function analyze_symbol($mysqli, $symbol, $strategy = 'day') {
    $prices = fetch_prices($mysqli, $symbol, 1000);
    
    if (count($prices) < 20) {
        // Auto fetch if not enough data
        auto_fetch_history($mysqli, $symbol);
        $prices = fetch_prices($mysqli, $symbol, 1000);
    }
    
    if (count($prices) < 5) return ['error'=>'Not enough data'];
    $closes = array_column($prices, 'close');
    $sma5 = sma($closes, 5);
    $sma20 = sma($closes, 20);
    $sma50 = sma($closes, 50);
    $sma200 = sma($closes, 200);
    $rsiArr = rsi($closes, 14);
    $macdArr = macd($closes);
    $bb = bollinger($closes, 20, 2.0);

    $latestIdx = count($closes)-1;
    $latest = $prices[$latestIdx];

    // Technical Signals Aggregation
    $techScore = 0;
    $signals = [];
    
    // SMA Crossover
    if ($sma5[$latestIdx] !== null && $sma20[$latestIdx] !== null) {
        if ($sma5[$latestIdx] > $sma20[$latestIdx]) {
            $techScore += 1; $signals[] = 'SMA Bullish';
        } else {
            $techScore -= 1; $signals[] = 'SMA Bearish';
        }
    }
    
    // RSI
    $rsiLatest = $rsiArr[$latestIdx] ?? null;
    if ($rsiLatest !== null) {
        if ($rsiLatest < 30) {
            $techScore += 2; $signals[] = 'RSI Oversold';
        } elseif ($rsiLatest > 70) {
            $techScore -= 2; $signals[] = 'RSI Overbought';
        }
    }
    
    // MACD
    $macdHistLatest = $macdArr['hist'][$latestIdx] ?? null;
    if ($macdHistLatest !== null) {
        if ($macdHistLatest > 0) {
            $techScore += 1; $signals[] = 'MACD Positive';
        } else {
            $techScore -= 1; $signals[] = 'MACD Negative';
        }
    }
    
    // Bollinger Band
    $bbUpper = $bb['upper'][$latestIdx] ?? null;
    $bbLower = $bb['lower'][$latestIdx] ?? null;
    if ($bbUpper !== null && $bbLower !== null) {
        if ($latest['close'] >= $bbUpper) {
            $techScore -= 1; $signals[] = 'Price > BB Upper';
        } elseif ($latest['close'] <= $bbLower) {
            $techScore += 1; $signals[] = 'Price < BB Lower';
        }
    }
    
    $signal = 'HOLD';
    if ($techScore >= 3) $signal = 'STRONG BUY';
    elseif ($techScore >= 1) $signal = 'BUY';
    elseif ($techScore <= -3) $signal = 'STRONG SELL';
    elseif ($techScore <= -1) $signal = 'SELL';
    
    $fund = get_latest_fundamental($mysqli, $symbol);
    $fundAnalysis = fundamental_score($fund);
    
    // ==========================================
    // GLOBAL SENTIMENT AND TRADING PLAN (ROBO)
    // ==========================================
    
    // 1. Mock Global Sentiment Analysis (can be real APIs later)
    // E.g., world indices, geo-politics, MSCI, exchange rate, etc.
    $globalIssues = [
        ['topic' => 'US-Iran Tension', 'impact' => -1, 'description' => 'Geopolitical instability affecting markets'],
        ['topic' => 'MSCI Rebalancing', 'impact' => 0, 'description' => 'Neutral/mixed effect for broad market'],
        ['topic' => 'Fed Rate', 'impact' => 1, 'description' => 'Dovish tilt, positive for emerging markets']
    ];
    
    $sentimentScore = 0;
    $sentimentReasons = [];
    foreach ($globalIssues as $issue) {
        $sentimentScore += $issue['impact'];
        if ($issue['impact'] !== 0) {
            $sentimentReasons[] = $issue['topic'] . ($issue['impact'] > 0 ? ' (Bullish)' : ' (Bearish)');
        }
    }
    
    $globalSentiment = 'NEUTRAL';
    if ($sentimentScore >= 1) $globalSentiment = 'BULLISH';
    if ($sentimentScore <= -1) $globalSentiment = 'BEARISH';
    
    // Adjust final signal based on macro sentiment
    if ($globalSentiment === 'BEARISH' && strpos($signal, 'BUY') !== false) {
        $signal = 'HOLD'; // Downgrade buy to hold if world is burning
        $signals[] = 'Downgraded due to Global Bearish sentiment';
    } else if ($globalSentiment === 'BULLISH' && $signal === 'HOLD') {
        $signal = 'BUY'; // Upgrade hold to buy if world is pumping
        $signals[] = 'Upgraded due to Global Bullish sentiment';
    }

    // 2. Trading Plan Calculation (Entry, TP, SL)
    // Base it on recent volatility and Support/Resistance (e.g., Bollinger Bands)
    if ($bbLower !== null && $bbUpper !== null) {
        $entry_price = round($latest['close']);
        
        // Prediksi persentase arah naik maksimal
        // Cek berapa beda antara harga saat ini dengan Atap Bollinger.
        $potensi_upside_bb = ($bbUpper - $entry_price) / $entry_price;
        
        // Logika Dinamis Take Profit:
        // - Kalau bbUpper cuma beda dikit < 5%, kita set default 5%.
        // - Kalau bbUpper lumayan lebar (misal 8-15%), kita pasang sesuai bbUpper.
        // - Tapi kalau harga sedang memuncak dengan dorongan MACD dan RSI > 60 belum mentok, 
        //   kita proyeksikan target fibonacci / rentang rally dinamis bisa sampai 15% (bahkan potensi ARA 20-34% di papan tertentu).
        $target_multiplier = 1.05; // Base minimal +5%
        
        if ($strategy === 'swing') {
            $target_multiplier = 1.10; // Base minimal +10% untuk Swing
        }
        
        if ($potensi_upside_bb > 0.05) {
            // Gunakan ceiling Bollinger band kalau ternyata rentangnya lebar
            $target_multiplier = max($target_multiplier, 1 + $potensi_upside_bb); 
        } 
        
        // Cek anomali kuat: jika ada MACD Positive & Signal Strong Buy, boost ekspektasi
        if (strpos($signal, 'STRONG BUY') !== false && $macdHistLatest > 0) {
            // Tambahkan booster profit 8% s/d 15% lebih agresif
            $target_multiplier = max($target_multiplier, ($strategy === 'swing' ? 1.20 : 1.15)); 
        }

        // Kalau saham gocap/ratusan perak volatilitasnya lebih brutal (Gampang capai +20%)
        if ($entry_price > 50 && $entry_price <= 500 && strpos($signal, 'BUY') !== false) {
            $target_multiplier = max($target_multiplier, ($strategy === 'swing' ? 1.25 : 1.20)); 
        }

        // Maksimal ekspektasi yang wajar (agar sistem ga menyuruh TP +80% esok hari)
        $max_limit = $strategy === 'swing' ? 1.50 : 1.34;
        $target_multiplier = min($target_multiplier, $max_limit); // Mentok limit wajar ARA/multidays swing (34% / 50%)

        $take_profit = round($entry_price * $target_multiplier, 0);
        
        // Stop loss: menyesuaikan Support BB Lower, dengan toleransi minus 3-7%
        // Apabila bbLower sangat jauh ke bawah, batasi SL maksimal minus 8% biar risk rasionya rasional.
        $sl_limit_max = $strategy === 'swing' ? 0.85 : 0.92; // minus 15% untuk swing, minus 8% untuk day
        $sl_limit_min = $strategy === 'swing' ? 0.95 : 0.97; // minus 5% untuk swing, minus 3% untuk day
        
        $sl_multiplier = max(($bbLower / $entry_price), $sl_limit_max); 
        $sl_multiplier = min($sl_multiplier, $sl_limit_min); 

        $cut_loss = round($entry_price * $sl_multiplier, 0);
        
        // Perbaiki pembulatan harga saham sesuai fraksi / tick rupiah
        if ($entry_price < 200) {
            $take_profit = round($take_profit);
            $cut_loss = round($cut_loss);
        } else {
             // Pembulatan wajar kelipatan 5 atau 10
             $take_profit = round($take_profit / 5) * 5;
             $cut_loss = round($cut_loss / 5) * 5;
        }
        
    } else {
        // Fallback simple percentages (Bila data tidak cukup)
        $entry_price = $latest['close'];
        $take_profit = $entry_price * 1.05;
        $cut_loss = $entry_price * 0.95;
    }
    
    $trading_plan = [
        'entry' => $entry_price,
        'take_profit' => $take_profit,
        'cut_loss' => $cut_loss,
        'reward_risk' => ($entry_price - $cut_loss) > 0 ? round(($take_profit - $entry_price) / ($entry_price - $cut_loss), 2) : 0
    ];

    return [
        'symbol'=>$symbol,
        'prices'=>$prices,
        'sma5'=>$sma5,
        'sma20'=>$sma20,
        'sma50'=>$sma50,
        'sma200'=>$sma200,
        'bollinger'=>$bb,
        'rsi'=>$rsiArr,
        'macd'=>$macdArr,
        'latest'=>$latest,
        'signal'=>$signal,
        'signal_details'=>implode(', ', $signals),
        'fundamental'=>$fund,
        'fund_score'=> $fundAnalysis ? $fundAnalysis['score'] : null,
        'fund_status'=> $fundAnalysis ? $fundAnalysis['status'] : 'N/A',
        'global_sentiment' => $globalSentiment,
        'global_sentiment_details' => implode(', ', $sentimentReasons) ?: 'No major catalysts',
        'trading_plan' => $trading_plan
    ];
}

?>

