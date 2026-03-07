<?php
function fetch_yahoo_quote($symbol) {
    // Yahoo expects .JK suffix for Jakarta Exchange
    $sym = strtoupper($symbol);
    if (strpos($sym, '.JK') === false) $sym = $sym . '.JK';
    echo "Fetching $sym ...\n";
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym) . '?range=1d&interval=1m';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
        'Accept: application/json'
    ]);
    $txt = curl_exec($ch);
    curl_close($ch);
    if ($txt) {
        $j = json_decode($txt, true);
        if (isset($j['chart']['result'][0]['meta']['regularMarketPrice'])) {
            return $j['chart']['result'][0]['meta']['regularMarketPrice'];
        }
    }
    return false;
}
echo fetch_yahoo_quote('BNBR') . "\n";
echo fetch_yahoo_quote('BBCA') . "\n";
?>
