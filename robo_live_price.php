<?php
/**
 * AJAX endpoint: return live prices for a list of symbols.
 * Request: POST JSON { "symbols": ["BBCA.JK","TLKM.JK",...] }
 * Response: JSON { "BBCA.JK": 9500, "TLKM.JK": 3120, ... }
 */
require_once 'auth.php';
require_login();
require_once __DIR__ . '/db.php';
$mysqli = db_connect();
require_subscription($mysqli);

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$symbols = isset($body['symbols']) && is_array($body['symbols']) ? $body['symbols'] : [];

if (empty($symbols)) {
    echo json_encode([]);
    exit;
}

// Sanitize and limit
$symbols = array_slice(array_unique($symbols), 0, 30);

function _fetchLivePrice($symbol) {
    static $cache = [];
    if (isset($cache[$symbol])) return $cache[$symbol];

    $encoded = urlencode($symbol);
    $url = "https://query1.finance.yahoo.com/v7/finance/spark?symbols={$encoded}&range=1d&interval=1m";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) { $cache[$symbol] = null; return null; }

    $data = json_decode($res, true);
    if (!isset($data['spark']['result'][0]['response'][0]['indicators']['quote'][0]['close'])) {
        $cache[$symbol] = null;
        return null;
    }

    $closes = $data['spark']['result'][0]['response'][0]['indicators']['quote'][0]['close'];
    $valid = array_values(array_filter($closes, fn($v) => $v !== null));
    if (empty($valid)) { $cache[$symbol] = null; return null; }

    $price = (float)end($valid);
    $cache[$symbol] = $price;
    return $price;
}

// Fallback: get latest DB close for a symbol
function _dbLastClose($mysqli, $symbol) {
    $esc = $mysqli->real_escape_string($symbol);
    $r = $mysqli->query("SELECT close FROM prices WHERE symbol='$esc' ORDER BY date DESC LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (float)$row['close'];
    return null;
}

$result = [];
foreach ($symbols as $sym) {
    $price = _fetchLivePrice($sym);
    if ($price === null || $price <= 0) {
        $price = _dbLastClose($mysqli, $sym);
    }
    $result[$sym] = $price;
}

echo json_encode($result);
