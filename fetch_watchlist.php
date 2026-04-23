<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
date_default_timezone_set('Asia/Jakarta');

$mysqli = db_connect();
$user_id = get_user_id();

$res = $mysqli->query("SELECT symbol FROM watchlist WHERE user_id = $user_id ORDER BY created_at DESC");
$symbols = [];
while ($row = $res->fetch_assoc()) {
    $sym = strtoupper(trim($row['symbol']));
    if (strpos($sym, '.JK') === false) {
        $sym .= '.JK';
    }
    $symbols[] = $sym;
}

if (empty($symbols)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$multi_curl = curl_multi_init();
$curl_handles = [];

foreach ($symbols as $sym) {
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym) . '?range=1d&interval=1d';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_multi_add_handle($multi_curl, $ch);
    $curl_handles[$sym] = $ch;
}

$active = null;
do {
    $mrc = curl_multi_exec($multi_curl, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($multi_curl) == -1) {
        usleep(100);
    }
    do {
        $mrc = curl_multi_exec($multi_curl, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
}

$results = [];

foreach ($curl_handles as $sym => $ch) {
    $response = curl_multi_getcontent($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($multi_curl, $ch);
    curl_close($ch);
    
    $clean_sym = str_replace('.JK', '', $sym);

    if ($http_code == 200 && $response) {
        $j = json_decode($response, true);
        if (isset($j['chart']['result'][0])) {
            $r = $j['chart']['result'][0];
            $pm = $r['meta'];
            $price = $pm['regularMarketPrice'] ?? 0;
            $prev = $pm['chartPreviousClose'] ?? 0;
            $pct = 0;
            if ($prev > 0) {
                $pct = (($price - $prev) / $prev) * 100;
            }
            // we could get volume from indicators
            $vol = 0;
            if (isset($r['indicators']['quote'][0]['volume'])) {
                $vols = $r['indicators']['quote'][0]['volume'];
                $vol = end($vols) ?: 0;
            }

            $results[] = [
                'symbol' => $clean_sym,
                'price' => $price,
                'prev' => $prev,
                'pct' => $pct,
                'volume' => $vol
            ];
        }
    }
}

usort($results, function($a, $b) {
    return $b['pct'] <=> $a['pct'];
});

echo json_encode(['status' => 'success', 'data' => $results]);
?>