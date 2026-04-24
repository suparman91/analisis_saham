<?php
/**
 * AI Analysis Engine - Google Gemini Integration
 * Provides comprehensive stock analysis using AI
 * Supports both automatic and manual analysis modes
 */

header('Content-Type: application/json');

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get request parameters - handle both GET and POST
$request_data = $_GET;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = json_decode(file_get_contents('php://input'), true);
    if ($post_data) {
        $request_data = array_merge($request_data, $post_data);
    } else {
        $request_data = array_merge($request_data, $_POST);
    }
}

$symbol = $request_data['symbol'] ?? '';
$timeframe = $request_data['timeframe'] ?? '1M'; // 1W, 1M, 3M, 6M, 1Y
$api_token = $request_data['api_token'] ?? '';
$mode = $request_data['mode'] ?? 'manual'; // manual or auto

if (!$symbol) {
    http_response_code(400);
    echo json_encode(['error' => 'No symbol provided']);
    exit;
}

if (!$api_token) {
    http_response_code(400);
    echo json_encode(['error' => 'No API token provided. Please enter Google Gemini API key']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_analysis_aggregate.php';

try {
    // Aggregate all data for analysis
    $aggregated_data = aggregate_stock_data($symbol, $timeframe);
    
    if (isset($aggregated_data['error'])) {
        http_response_code(400);
        echo json_encode($aggregated_data);
        exit;
    }

    // Prepare AI analysis prompt
    $prompt = build_analysis_prompt($symbol, $aggregated_data);
    
    // Call Google Gemini API
    $gemini_response = call_gemini_api($api_token, $prompt);
    
    if (isset($gemini_response['error'])) {
        http_response_code(400);
        echo json_encode($gemini_response);
        exit;
    }

    // Parse and structure the response
    $analysis = parse_gemini_response($gemini_response);
    
    // Save analysis to cache/database if applicable
    if ($mode === 'auto') {
        save_analysis_cache($symbol, $analysis);
    }

    echo json_encode([
        'success' => true,
        'symbol' => $symbol,
        'timeframe' => $timeframe,
        'timestamp' => date('Y-m-d H:i:s'),
        'engine' => 'GOOGLE_AI',
        'model_used' => $gemini_response['model'] ?? null,
        'analysis' => $analysis,
        'data_context' => [
            'latest_price' => $aggregated_data['latest_price'] ?? null,
            'change_pct' => $aggregated_data['change_pct'] ?? null,
            'trend' => $aggregated_data['trend'] ?? null,
            'rsi' => $aggregated_data['rsi'] ?? null,
            'macd_signal' => $aggregated_data['macd_signal'] ?? null,
        ]
    ]);

} catch (Exception $e) {
    error_log('AI Analysis Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Analysis failed: ' . $e->getMessage()
    ]);
}

/**
 * Call Google Gemini API
 */
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
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_NONE'
            ]
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

/**
 * Build comprehensive analysis prompt for Gemini
 */
function build_analysis_prompt($symbol, $data) {
    $price = $data['latest_price'] ?? 'N/A';
    $change = $data['change_pct'] ?? 'N/A';
    $trend = $data['trend'] ?? 'N/A';
    
    $prompt = <<<PROMPT
Anda adalah analis saham profesional untuk pasar Indonesia.
Lakukan analisis saham yang sangat detail dan terstruktur untuk: $symbol

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

Keluarkan output HANYA dalam JSON valid tanpa markdown/code block, dengan schema berikut:
{
  "technical_analysis": "string panjang dan rinci",
  "fundamental_analysis": "string panjang dan rinci",
  "sentiment_analysis": "string panjang dan rinci",
  "risk_factors": "string panjang dan rinci",
  "conclusion": "string panjang dan rinci",
  "recommendation": "SELL|HOLD|BUY|HOT",
  "confidence_level": "rendah|sedang|tinggi|sangat tinggi",
  "entry_price": number,
  "take_profit": number,
  "stop_loss": number,
  "probability_win_pct": number,
  "risk_reward_ratio": number,
  "time_horizon": "1-3 hari|1-2 minggu|1-3 bulan",
  "scenario_bull": "string",
  "scenario_base": "string",
  "scenario_bear": "string",
  "key_catalysts": ["string", "string", "string"],
  "execution_plan": ["langkah 1", "langkah 2", "langkah 3"],
  "ai_edge": "apa insight unik yang tidak obvious"
}

ATURAN KERAS:
- Berikan alasan berbasis angka, jangan generik.
- Rekomendasi harus konsisten dengan data teknikal+fundamental+sentimen.
- Entry/TP/SL harus realistis terhadap harga saat ini.
- Gunakan bahasa Indonesia profesional.
PROMPT;

    return $prompt;
}

/**
 * Parse Gemini response and structure it
 */
function parse_gemini_response($response) {
    $text = $response['text'] ?? '';
    
    // Initialize structured analysis
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
        'probability_win_pct' => null,
        'risk_reward_ratio' => null,
        'time_horizon' => null,
        'scenario_bull' => '',
        'scenario_base' => '',
        'scenario_bear' => '',
        'key_catalysts' => [],
        'execution_plan' => [],
        'ai_edge' => '',
        'raw_text' => $text
    ];
    
    // Try parse strict JSON first (preferred, richer output)
    $jsonCandidate = trim($text);
    $jsonCandidate = preg_replace('/^```json\s*/i', '', $jsonCandidate);
    $jsonCandidate = preg_replace('/\s*```$/', '', $jsonCandidate);
    $decoded = json_decode($jsonCandidate, true);

    if (!is_array($decoded)) {
        // Try to find JSON object inside mixed text
        if (preg_match('/\{[\s\S]*\}/', $text, $mJson)) {
            $decoded = json_decode($mJson[0], true);
        }
    }

    if (is_array($decoded)) {
        $analysis['technical_analysis'] = (string)($decoded['technical_analysis'] ?? '');
        $analysis['fundamental_analysis'] = (string)($decoded['fundamental_analysis'] ?? '');
        $analysis['sentiment_analysis'] = (string)($decoded['sentiment_analysis'] ?? '');
        $analysis['risk_factors'] = (string)($decoded['risk_factors'] ?? '');
        $analysis['conclusion'] = (string)($decoded['conclusion'] ?? '');
        $analysis['recommendation'] = strtoupper((string)($decoded['recommendation'] ?? 'HOLD'));
        $analysis['confidence_level'] = strtolower((string)($decoded['confidence_level'] ?? 'sedang'));
        $analysis['entry_price'] = isset($decoded['entry_price']) ? (float)$decoded['entry_price'] : null;
        $analysis['take_profit'] = isset($decoded['take_profit']) ? (float)$decoded['take_profit'] : null;
        $analysis['stop_loss'] = isset($decoded['stop_loss']) ? (float)$decoded['stop_loss'] : null;
        $analysis['probability_win_pct'] = isset($decoded['probability_win_pct']) ? (float)$decoded['probability_win_pct'] : null;
        $analysis['risk_reward_ratio'] = isset($decoded['risk_reward_ratio']) ? (float)$decoded['risk_reward_ratio'] : null;
        $analysis['time_horizon'] = isset($decoded['time_horizon']) ? (string)$decoded['time_horizon'] : null;
        $analysis['scenario_bull'] = isset($decoded['scenario_bull']) ? (string)$decoded['scenario_bull'] : '';
        $analysis['scenario_base'] = isset($decoded['scenario_base']) ? (string)$decoded['scenario_base'] : '';
        $analysis['scenario_bear'] = isset($decoded['scenario_bear']) ? (string)$decoded['scenario_bear'] : '';
        $analysis['key_catalysts'] = (!empty($decoded['key_catalysts']) && is_array($decoded['key_catalysts'])) ? array_values($decoded['key_catalysts']) : [];
        $analysis['execution_plan'] = (!empty($decoded['execution_plan']) && is_array($decoded['execution_plan'])) ? array_values($decoded['execution_plan']) : [];
        $analysis['ai_edge'] = isset($decoded['ai_edge']) ? (string)$decoded['ai_edge'] : '';

        // Append richer scan details into conclusion so UI existing block can show them.
        $extra = [];
        if (isset($decoded['probability_win_pct'])) $extra[] = 'Probabilitas Menang: ' . $decoded['probability_win_pct'] . '%';
        if (isset($decoded['risk_reward_ratio'])) $extra[] = 'Risk/Reward: ' . $decoded['risk_reward_ratio'];
        if (!empty($decoded['time_horizon'])) $extra[] = 'Horizon: ' . $decoded['time_horizon'];
        if (!empty($decoded['scenario_bull'])) $extra[] = 'Skenario Bull: ' . $decoded['scenario_bull'];
        if (!empty($decoded['scenario_base'])) $extra[] = 'Skenario Base: ' . $decoded['scenario_base'];
        if (!empty($decoded['scenario_bear'])) $extra[] = 'Skenario Bear: ' . $decoded['scenario_bear'];
        if (!empty($decoded['key_catalysts']) && is_array($decoded['key_catalysts'])) $extra[] = 'Katalis Kunci: ' . implode('; ', $decoded['key_catalysts']);
        if (!empty($decoded['execution_plan']) && is_array($decoded['execution_plan'])) $extra[] = 'Rencana Eksekusi: ' . implode(' -> ', $decoded['execution_plan']);
        if (!empty($decoded['ai_edge'])) $extra[] = 'AI Edge: ' . $decoded['ai_edge'];
        if (!empty($extra)) {
            $analysis['conclusion'] = trim($analysis['conclusion'] . "\n\n" . implode("\n", $extra));
        }
    }

    // Fallback: Parse markdown sections (legacy)
    if ($analysis['technical_analysis'] === '' && preg_match('/\*\*ANALISIS TEKNIKAL\*\*(.+?)(?=\*\*ANALISIS FUNDAMENTAL\*\*|$)/is', $text, $m)) {
        $analysis['technical_analysis'] = trim(strip_tags($m[1]));
    }
    
    if ($analysis['fundamental_analysis'] === '' && preg_match('/\*\*ANALISIS FUNDAMENTAL\*\*(.+?)(?=\*\*ANALISIS SENTIMEN\*\*|$)/is', $text, $m)) {
        $analysis['fundamental_analysis'] = trim(strip_tags($m[1]));
    }
    
    if ($analysis['sentiment_analysis'] === '' && preg_match('/\*\*ANALISIS SENTIMEN\*\*(.+?)(?=\*\*FAKTOR RISIKO\*\*|$)/is', $text, $m)) {
        $analysis['sentiment_analysis'] = trim(strip_tags($m[1]));
    }
    
    if ($analysis['risk_factors'] === '' && preg_match('/\*\*FAKTOR RISIKO\*\*(.+?)(?=\*\*KESIMPULAN|$)/is', $text, $m)) {
        $analysis['risk_factors'] = trim(strip_tags($m[1]));
    }
    
    if ($analysis['conclusion'] === '' && preg_match('/\*\*KESIMPULAN.+?REKOMENDASI\*\*(.+?)$/is', $text, $m)) {
        $analysis['conclusion'] = trim(strip_tags($m[1]));
    }
    
    // Extract recommendation
    if (preg_match('/(?:Rekomendasi|FINAL)[\s\w:]*?(SELL|BUY|HOLD|HOT)/i', $text, $m)) {
        $analysis['recommendation'] = strtoupper($m[1]);
    }
    
    // Extract confidence level
    if (preg_match('/Tingkat Keyakinan[\s:]*?(rendah|sedang|tinggi|sangat tinggi)/i', $text, $m)) {
        $analysis['confidence_level'] = $m[1];
    }
    
    // Extract prices using more flexible pattern
    if (preg_match('/entry[\s\w]*?[\s:]*?(?:Rp\.?|IDR)?[\s]*?([\d,.]+)/i', $text, $m)) {
        $analysis['entry_price'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }
    
    if (preg_match('/take[\s\w]*?profit[\s:]*?(?:Rp\.?|IDR)?[\s]*?([\d,.]+)/i', $text, $m)) {
        $analysis['take_profit'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }
    
    if (preg_match('/stop[\s\w]*?loss[\s:]*?(?:Rp\.?|IDR)?[\s]*?([\d,.]+)/i', $text, $m)) {
        $analysis['stop_loss'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }
    
    // Determine recommendation strength (for speedometer)
    $strength_map = [
        'SELL' => 'sangat negatif',
        'HOLD' => 'netral',
        'BUY' => 'positif',
        'HOT' => 'sangat positif'
    ];
    $analysis['recommendation_strength'] = $strength_map[$analysis['recommendation']] ?? 'netral';
    
    return $analysis;
}

/**
 * Save analysis to cache/database
 */
function save_analysis_cache($symbol, $analysis) {
    try {
        $mysqli = db_connect();
        
        // Create table if not exists
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
        
        // Insert or update
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

?>
