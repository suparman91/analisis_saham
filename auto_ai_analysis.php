<?php
/**
 * AI Analysis Auto-Scheduler (Cron Task)
 * Automatically performs AI analysis on selected stocks daily
 * Call this via cron job: php auto_ai_analysis.php
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_analysis_aggregate.php';

set_time_limit(300); // 5 minutes limit
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = db_connect();

// Get list of stocks to analyze (from watchlist or most traded)
$stocks_to_analyze = get_stocks_to_analyze($mysqli);

if (empty($stocks_to_analyze)) {
    log_auto_analysis('No stocks to analyze');
    exit;
}

log_auto_analysis('Starting auto-analysis for ' . count($stocks_to_analyze) . ' stocks');

$api_token = getenv('GEMINI_API_TOKEN') ?: '';

if (empty($api_token)) {
    log_auto_analysis('ERROR: GEMINI_API_TOKEN not set in environment');
    exit;
}

// Analyze each stock
$results = [];
foreach ($stocks_to_analyze as $symbol) {
    try {
        $aggregated_data = aggregate_stock_data($symbol, '1M');
        
        if (isset($aggregated_data['error'])) {
            log_auto_analysis("ERROR: $symbol - " . $aggregated_data['error']);
            continue;
        }
        
        // Prepare AI prompt
        $prompt = build_analysis_prompt($symbol, $aggregated_data);
        
        // Call Gemini API
        $gemini_response = call_gemini_api($api_token, $prompt);
        
        if (isset($gemini_response['error'])) {
            log_auto_analysis("ERROR: $symbol - " . $gemini_response['error']);
            continue;
        }
        
        // Parse response
        $analysis = parse_gemini_response($gemini_response);
        
        // Save to cache
        save_analysis_cache($symbol, $analysis);
        
        $results[$symbol] = [
            'status' => 'success',
            'recommendation' => $analysis['recommendation']
        ];
        
        log_auto_analysis("SUCCESS: $symbol - Recommendation: {$analysis['recommendation']}");
        
        // Rate limiting - wait 2 seconds between API calls
        sleep(2);
        
    } catch (Exception $e) {
        log_auto_analysis("ERROR: $symbol - " . $e->getMessage());
        $results[$symbol] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

$mysqli->close();

log_auto_analysis('Auto-analysis completed. Results: ' . json_encode($results));

/**
 * Get list of stocks to analyze
 */
function get_stocks_to_analyze($mysqli) {
    // Get stocks from watchlist or top traded
    $result = $mysqli->query("
        SELECT DISTINCT symbol FROM prices 
        WHERE date = (SELECT MAX(date) FROM prices)
        ORDER BY volume DESC
        LIMIT 20
    ");
    
    $stocks = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stocks[] = $row['symbol'];
        }
    }
    
    return $stocks;
}

/**
 * Wrapper functions for gemini_analyze functions
 */
function build_analysis_prompt($symbol, $data) {
    $price = $data['latest_price'] ?? 'N/A';
    $change = $data['change_pct'] ?? 'N/A';
    $trend = $data['trend'] ?? 'N/A';
    
    $prompt = <<<PROMPT
Analisis saham yang sangat detail dan terstruktur untuk: $symbol

DATA TEKNIKAL:
- Harga Terbaru: $price
- Perubahan Hari Ini: $change%
- Tren (SMA5 vs SMA20): $trend
- RSI: {$data['rsi']} (Kondisi: {$data['rsi_condition']})
- MACD: {$data['macd_signal']} (Status: {$data['macd_status']})
- Support Level: {$data['support_level']}
- Resistance Level: {$data['resistance_level']}
- Volume Trend: {$data['volume_trend']}
- Bollinger Bands: {$data['bb_status']}

DATA FUNDAMENTAL:
- P/E Ratio: {$data['pe_ratio']} (Kategori: {$data['pe_category']})
- PBV: {$data['pbv']} 
- ROE: {$data['roe']}%
- DER: {$data['der']}
- Pertumbuhan EPS: {$data['eps_growth']}%
- Dividend Yield: {$data['dividend_yield']}%
- Market Cap: {$data['market_cap']}

SENTIMEN PASAR:
- Sentimen Global: {$data['global_sentiment']}
- Sentimen Sektor: {$data['sector_sentiment']}
- Sentimen Berita: {$data['news_sentiment']}
- Investor Interest (dari volume): {$data['investor_interest']}

KONTEKS PASAR:
- Status IHSG: {$data['ihsg_status']}
- Kondisi Bursa: {$data['market_condition']}
- Momentum Pasar: {$data['market_momentum']}

Berdasarkan data di atas, berikan ANALISIS KOMPREHENSIF dengan format berikut:

1. **ANALISIS TEKNIKAL** (3-4 paragraf):
   - Analisis trend jangka pendek dan menengah
   - Pola chart dan resistance/support yang relevan
   - Indikator momentum (RSI, MACD, Bollinger Bands)
   - Proyeksi pergerakan harga

2. **ANALISIS FUNDAMENTAL** (2-3 paragraf):
   - Valuasi saham (P/E, PBV ratio)
   - Kualitas bisnis (ROE, DER)
   - Pertumbuhan (EPS growth, revenue)
   - Perbandingan dengan peer dan industri

3. **ANALISIS SENTIMEN** (1-2 paragraf):
   - Sentimen pasar dan media
   - Aktivitas investor
   - Momentum institusional/retail

4. **FAKTOR RISIKO**:
   - Risiko teknikal (breakout negatif, support break)
   - Risiko fundamental (leverage tinggi, growth melambat)
   - Risiko pasar (koreksi pasar, sektor weakness)

5. **KESIMPULAN DAN REKOMENDASI**:
   - Kekuatan utama saham
   - Kelemahan utama saham
   - Target harga jangka pendek dan menengah
   - Rekomendasi FINAL: SELL / BUY / HOLD / HOT (dengan penjelasan alasan memilih rekomendasi ini)
   - Tingkat Keyakinan: rendah/sedang/tinggi/sangat tinggi
   - Entry/Exit points yang ideal
PROMPT;

    return $prompt;
}

function call_gemini_api($api_token, $prompt) {
    $modelCandidates = [
        'gemini-1.5-flash',
        'gemini-1.5-pro',
        'gemini-2.0-flash'
    ];
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2000,
            'topP' => 0.9,
            'topK' => 40
        ]
    ];
    
    $lastError = 'Unknown Gemini API error';

    foreach ($modelCandidates as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
        $ch = curl_init($url . '?key=' . $api_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            $lastError = 'Model ' . $model . ' (HTTP ' . $http_code . '): ' . substr((string)$response, 0, 200);
            continue;
        }

        $data = json_decode((string)$response, true);
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $lastError = 'Model ' . $model . ': Invalid response format';
            continue;
        }

        return [
            'text' => $data['candidates'][0]['content']['parts'][0]['text'],
            'model' => $model
        ];
    }

    return ['error' => 'Gemini API Error: ' . $lastError];
}

function parse_gemini_response($response) {
    $text = $response['text'] ?? '';
    
    $analysis = [
        'technical_analysis' => '',
        'fundamental_analysis' => '',
        'sentiment_analysis' => '',
        'risk_factors' => '',
        'conclusion' => '',
        'recommendation' => 'HOLD',
        'recommendation_strength' => 'sedang',
        'confidence_level' => 'sedang',
        'entry_price' => null,
        'take_profit' => null,
        'stop_loss' => null,
        'raw_text' => $text
    ];
    
    if (preg_match('/\*\*ANALISIS TEKNIKAL\*\*(.+?)(?=\*\*ANALISIS FUNDAMENTAL\*\*|$)/is', $text, $m)) {
        $analysis['technical_analysis'] = trim(strip_tags($m[1]));
    }
    
    if (preg_match('/\*\*ANALISIS FUNDAMENTAL\*\*(.+?)(?=\*\*ANALISIS SENTIMEN\*\*|$)/is', $text, $m)) {
        $analysis['fundamental_analysis'] = trim(strip_tags($m[1]));
    }
    
    if (preg_match('/\*\*ANALISIS SENTIMEN\*\*(.+?)(?=\*\*FAKTOR RISIKO\*\*|$)/is', $text, $m)) {
        $analysis['sentiment_analysis'] = trim(strip_tags($m[1]));
    }
    
    if (preg_match('/\*\*FAKTOR RISIKO\*\*(.+?)(?=\*\*KESIMPULAN|$)/is', $text, $m)) {
        $analysis['risk_factors'] = trim(strip_tags($m[1]));
    }
    
    if (preg_match('/\*\*KESIMPULAN.+?REKOMENDASI\*\*(.+?)$/is', $text, $m)) {
        $analysis['conclusion'] = trim(strip_tags($m[1]));
    }
    
    if (preg_match('/(?:Rekomendasi|FINAL)[\s\w:]*?(SELL|BUY|HOLD|HOT)/i', $text, $m)) {
        $analysis['recommendation'] = strtoupper($m[1]);
    }
    
    if (preg_match('/Tingkat Keyakinan[\s:]*?(rendah|sedang|tinggi|sangat tinggi)/i', $text, $m)) {
        $analysis['confidence_level'] = $m[1];
    }
    
    if (preg_match('/entry[\s\w]*?[\s:]*?(?:Rp\.?|IDR)?[\s]*?([\d,.]+)/i', $text, $m)) {
        $analysis['entry_price'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }
    
    if (preg_match('/take[\s\w]*?profit[\s:]*?(?:Rp\.?|IDR)?[\s]*?([\d,.]+)/i', $text, $m)) {
        $analysis['take_profit'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }
    
    if (preg_match('/stop[\s\w]*?loss[\s:]*?(?:Rp\.?|IDR)?[\s]*?([\d,.]+)/i', $text, $m)) {
        $analysis['stop_loss'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }
    
    $strength_map = [
        'SELL' => 'sangat negatif',
        'HOLD' => 'netral',
        'BUY' => 'positif',
        'HOT' => 'sangat positif'
    ];
    $analysis['recommendation_strength'] = $strength_map[$analysis['recommendation']] ?? 'netral';
    
    return $analysis;
}

function save_analysis_cache($symbol, $analysis) {
    try {
        $mysqli = db_connect();
        
        $create_sql = <<<SQL
        CREATE TABLE IF NOT EXISTS ai_analysis_cache (
            id INT PRIMARY KEY AUTO_INCREMENT,
            symbol VARCHAR(20) NOT NULL,
            analysis JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_symbol (symbol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
        $mysqli->query($create_sql);
        
        $analysis_json = json_encode($analysis);
        $stmt = $mysqli->prepare("
            INSERT INTO ai_analysis_cache (symbol, analysis, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE analysis = ?, created_at = NOW()
        ");
        $stmt->bind_param('sss', $symbol, $analysis_json, $analysis_json);
        $stmt->execute();
        
        $mysqli->close();
    } catch (Exception $e) {
        error_log('Failed to save analysis cache: ' . $e->getMessage());
    }
}

function log_auto_analysis($message) {
    $log_file = __DIR__ . '/logs/auto_analysis.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // Create logs directory if not exists
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

?>
