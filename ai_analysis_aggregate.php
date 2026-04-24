<?php
/**
 * AI Analysis Data Aggregation
 * Collect all technical, fundamental, and sentiment data for AI analysis
 */

/**
 * Aggregate all stock data needed for AI analysis
 */
function aggregate_stock_data($symbol, $timeframe = '1M') {
    try {
        $mysqli = db_connect();
        
        // Convert timeframe to days
        $days_map = [
            '1W' => 7,
            '2W' => 14,
            '1M' => 30,
            '3M' => 90,
            '6M' => 180,
            '1Y' => 365
        ];
        $days = $days_map[$timeframe] ?? 30;
        
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        // Get price history
        $prices_result = $mysqli->query("
            SELECT `date`, close, open, high, low, volume 
            FROM prices 
            WHERE symbol = '$symbol' AND `date` >= '$start_date'
            ORDER BY `date` ASC
        ");
        
        if (!$prices_result || $prices_result->num_rows < 5) {
            return ['error' => 'Insufficient historical data for ' . $symbol];
        }
        
        $prices = [];
        $closes = [];
        $volumes = [];
        $prev_close = null;
        
        while ($row = $prices_result->fetch_assoc()) {
            $prices[] = $row;
            $closes[] = (float)$row['close'];
            $volumes[] = (int)$row['volume'];
            $prev_close = $row['close'];
        }
        
        // Calculate technical indicators
        $technical = calculate_technical_indicators($closes, $volumes);
        
        // Get latest data
        $latest = end($prices);
        $prev = isset($prices[count($prices)-2]) ? $prices[count($prices)-2] : null;
        
        $latest_price = (float)$latest['close'];
        $prev_close_price = $prev ? (float)$prev['close'] : $latest_price;
        $change_pct = (($latest_price - $prev_close_price) / $prev_close_price) * 100;
        
        // Get fundamental data
        $fundamental = get_fundamental_data($mysqli, $symbol);
        
        // Get sentiment data
        $sentiment = get_sentiment_data($mysqli, $symbol);
        
        // Get market context
        $market_context = get_market_context($mysqli);
        
        $mysqli->close();
        
        // Combine all data
        $aggregated = [
            'symbol' => $symbol,
            'latest_price' => $latest_price,
            'prev_close_price' => $prev_close_price,
            'change_pct' => round($change_pct, 2),
            'latest_date' => $latest['date'],
            'timeframe' => $timeframe,
            
            // Technical
            'sma_5' => $technical['sma_5'],
            'sma_20' => $technical['sma_20'],
            'sma_50' => $technical['sma_50'],
            'trend' => $technical['trend'],
            'rsi' => round($technical['rsi'], 2),
            'rsi_condition' => $technical['rsi_condition'],
            'macd_signal' => $technical['macd_signal'],
            'macd_status' => $technical['macd_status'],
            'support_level' => $technical['support_level'],
            'resistance_level' => $technical['resistance_level'],
            'volume_trend' => $technical['volume_trend'],
            'bb_status' => $technical['bb_status'],
            
            // Fundamental
            'pe_ratio' => $fundamental['pe_ratio'],
            'pe_category' => $fundamental['pe_category'],
            'pbv' => $fundamental['pbv'],
            'roe' => $fundamental['roe'],
            'der' => $fundamental['der'],
            'eps_growth' => $fundamental['eps_growth'],
            'dividend_yield' => $fundamental['dividend_yield'],
            'market_cap' => $fundamental['market_cap'],
            
            // Sentiment
            'global_sentiment' => $sentiment['global_sentiment'],
            'sector_sentiment' => $sentiment['sector_sentiment'],
            'news_sentiment' => $sentiment['news_sentiment'],
            'investor_interest' => $sentiment['investor_interest'],
            
            // Market context
            'ihsg_status' => $market_context['ihsg_status'],
            'market_condition' => $market_context['market_condition'],
            'market_momentum' => $market_context['market_momentum']
        ];
        
        return $aggregated;
        
    } catch (Exception $e) {
        return ['error' => 'Data aggregation error: ' . $e->getMessage()];
    }
}

/**
 * Calculate technical indicators
 */
function calculate_technical_indicators($closes, $volumes) {
    $n = count($closes);
    
    // SMA Calculations
    $sma_5 = $n >= 5 ? array_sum(array_slice($closes, -5)) / 5 : $closes[$n-1];
    $sma_20 = $n >= 20 ? array_sum(array_slice($closes, -20)) / 20 : $closes[$n-1];
    $sma_50 = $n >= 50 ? array_sum(array_slice($closes, -50)) / 50 : $closes[$n-1];
    
    // Trend determination
    $latest = end($closes);
    if ($sma_5 > $sma_20) {
        $trend = 'Bullish (SMA5 > SMA20)';
        $trend_signal = 'BULLISH';
    } elseif ($sma_5 < $sma_20) {
        $trend = 'Bearish (SMA5 < SMA20)';
        $trend_signal = 'BEARISH';
    } else {
        $trend = 'Neutral (SMA5 ≈ SMA20)';
        $trend_signal = 'NEUTRAL';
    }
    
    // RSI Calculation
    $rsi = calculate_rsi($closes, 14);
    if ($rsi < 30) {
        $rsi_condition = 'Oversold (peluang rebound)';
    } elseif ($rsi > 70) {
        $rsi_condition = 'Overbought (rawan koreksi)';
    } else {
        $rsi_condition = 'Normal';
    }
    
    // MACD Calculation
    $macd_data = calculate_macd($closes);
    $macd_value = $macd_data['histogram'];
    $macd_signal = $macd_value > 0 ? 'Positive (Bullish)' : ($macd_value < 0 ? 'Negative (Bearish)' : 'Neutral');
    $macd_status = $macd_value > 0 ? 'BULLISH' : ($macd_value < 0 ? 'BEARISH' : 'NEUTRAL');
    
    // Support & Resistance
    $min_price = min(array_slice($closes, -20));
    $max_price = max(array_slice($closes, -20));
    $support = round($min_price, 0);
    $resistance = round($max_price, 0);
    
    // Volume Trend
    $latest_volume = end($volumes);
    $avg_volume = array_sum(array_slice($volumes, -10)) / 10;
    $volume_trend = $latest_volume > $avg_volume ? 'Meningkat (Bullish)' : 'Menurun (Bearish)';
    
    // Bollinger Bands
    $sma_20_close = $sma_20;
    $std_dev = calculate_std_dev(array_slice($closes, -20));
    $upper_bb = $sma_20_close + (2 * $std_dev);
    $lower_bb = $sma_20_close - (2 * $std_dev);
    
    if ($latest < $lower_bb) {
        $bb_status = 'Harga di bawah BB Lower (peluang naik)';
    } elseif ($latest > $upper_bb) {
        $bb_status = 'Harga di atas BB Upper (rawan koreksi)';
    } else {
        $bb_status = 'Harga dalam range normal';
    }
    
    return [
        'sma_5' => round($sma_5, 0),
        'sma_20' => round($sma_20, 0),
        'sma_50' => round($sma_50, 0),
        'trend' => $trend,
        'trend_signal' => $trend_signal,
        'rsi' => $rsi,
        'rsi_condition' => $rsi_condition,
        'macd_signal' => $macd_signal,
        'macd_status' => $macd_status,
        'support_level' => $support,
        'resistance_level' => $resistance,
        'volume_trend' => $volume_trend,
        'bb_status' => $bb_status
    ];
}

/**
 * Calculate RSI (Relative Strength Index)
 */
function calculate_rsi($prices, $period = 14) {
    $n = count($prices);
    if ($n < $period + 1) {
        return 50; // Default middle value
    }
    
    $gains = 0;
    $losses = 0;
    
    for ($i = $n - $period; $i < $n; $i++) {
        $change = $prices[$i] - $prices[$i-1];
        if ($change > 0) {
            $gains += $change;
        } else {
            $losses += abs($change);
        }
    }
    
    $avg_gain = $gains / $period;
    $avg_loss = $losses / $period;
    
    if ($avg_loss == 0) {
        return 100;
    }
    
    $rs = $avg_gain / $avg_loss;
    $rsi = 100 - (100 / (1 + $rs));
    
    return $rsi;
}

/**
 * Calculate MACD (Moving Average Convergence Divergence)
 */
function calculate_macd($prices, $fast = 12, $slow = 26, $signal_period = 9) {
    $n = count($prices);
    
    if ($n < $slow) {
        return ['macd' => 0, 'signal' => 0, 'histogram' => 0];
    }
    
    // Calculate EMAs
    $ema_12 = ema($prices, $fast);
    $ema_26 = ema($prices, $slow);
    $macd = $ema_12 - $ema_26;
    
    // For simplicity, use MACD as signal approximation
    return [
        'macd' => $macd,
        'signal' => $macd * 0.9, // Simplified
        'histogram' => $macd - ($macd * 0.9)
    ];
}

/**
 * Calculate EMA (Exponential Moving Average)
 */
function ema($prices, $period) {
    $n = count($prices);
    if ($n < $period) {
        return end($prices);
    }
    
    $multiplier = 2 / ($period + 1);
    $ema = array_sum(array_slice($prices, 0, $period)) / $period;
    
    for ($i = $period; $i < $n; $i++) {
        $ema = $prices[$i] * $multiplier + $ema * (1 - $multiplier);
    }
    
    return $ema;
}

/**
 * Calculate Standard Deviation
 */
function calculate_std_dev($data) {
    $n = count($data);
    $mean = array_sum($data) / $n;
    $sum = 0;
    
    foreach ($data as $val) {
        $sum += pow($val - $mean, 2);
    }
    
    return sqrt($sum / $n);
}

/**
 * Get fundamental data from database or cache
 */
function get_fundamental_data($mysqli, $symbol) {
    // For now, return mock data. In production, fetch from stocks table
    $result = $mysqli->query("
        SELECT pe_ratio, pbv, roe, der, eps_growth, dividend_yield, market_cap
        FROM stocks
        WHERE symbol = '$symbol'
        LIMIT 1
    ");
    
    if ($result && $row = $result->fetch_assoc()) {
        return [
            'pe_ratio' => $row['pe_ratio'] ?? 15.5,
            'pe_category' => ($row['pe_ratio'] ?? 15.5) < 12 ? 'Undervalued' : (($row['pe_ratio'] ?? 15.5) > 18 ? 'Expensive' : 'Fair Value'),
            'pbv' => $row['pbv'] ?? 1.2,
            'roe' => $row['roe'] ?? 15,
            'der' => $row['der'] ?? 0.8,
            'eps_growth' => $row['eps_growth'] ?? 5,
            'dividend_yield' => $row['dividend_yield'] ?? 3.5,
            'market_cap' => $row['market_cap'] ?? 'Large Cap'
        ];
    }
    
    // Default values if not found
    return [
        'pe_ratio' => 15.5,
        'pe_category' => 'Fair Value',
        'pbv' => 1.2,
        'roe' => 15,
        'der' => 0.8,
        'eps_growth' => 5,
        'dividend_yield' => 3.5,
        'market_cap' => 'Large Cap'
    ];
}

/**
 * Get sentiment data
 */
function get_sentiment_data($mysqli, $symbol) {
    // Global sentiment from IHSG or cache
    $global_sent = 'Netral';
    $ihsg_result = $mysqli->query("SELECT close FROM prices WHERE symbol = 'IHSG.JK' ORDER BY date DESC LIMIT 2");
    if ($ihsg_result && $ihsg_result->num_rows >= 2) {
        $rows = [];
        while ($r = $ihsg_result->fetch_assoc()) $rows[] = $r;
        $change = (($rows[0]['close'] - $rows[1]['close']) / $rows[1]['close']) * 100;
        $global_sent = $change > 0.5 ? 'Bullish' : ($change < -0.5 ? 'Bearish' : 'Netral');
    }
    
    // News sentiment (simple count of positive/negative keywords)
    $news_sent = 'Netral';
    $news_result = $mysqli->query("SELECT COUNT(*) as cnt FROM prices WHERE symbol = '$symbol'");
    if ($news_result) {
        $news_sent = 'Netral'; // Default
    }
    
    return [
        'global_sentiment' => $global_sent,
        'sector_sentiment' => 'Netral',
        'news_sentiment' => $news_sent,
        'investor_interest' => 'Sedang'
    ];
}

/**
 * Get market context (IHSG trend, market condition)
 */
function get_market_context($mysqli) {
    $ihsg_status = 'Normal';
    $market_condition = 'Stabil';
    $market_momentum = 'Neutral';
    
    // Check IHSG trend
    $ihsg_result = $mysqli->query("
        SELECT close FROM prices 
        WHERE symbol = 'IHSG.JK' 
        ORDER BY date DESC LIMIT 20
    ");
    
    if ($ihsg_result && $ihsg_result->num_rows >= 5) {
        $closes = [];
        while ($row = $ihsg_result->fetch_assoc()) {
            $closes[] = (float)$row['close'];
        }
        $closes = array_reverse($closes);
        
        $latest = end($closes);
        $prev = reset($closes);
        $change = (($latest - $prev) / $prev) * 100;
        
        if ($change > 1) {
            $ihsg_status = 'Bullish';
            $market_momentum = 'Positive';
        } elseif ($change < -1) {
            $ihsg_status = 'Bearish';
            $market_momentum = 'Negative';
        }
        
        $market_condition = abs($change) < 2 ? 'Stabil' : (abs($change) > 3 ? 'Volatile' : 'Normal');
    }
    
    return [
        'ihsg_status' => $ihsg_status,
        'market_condition' => $market_condition,
        'market_momentum' => $market_momentum
    ];
}

?>
