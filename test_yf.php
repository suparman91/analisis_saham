<?php
$currentPrices = [];
$sym_list = ['BNBR', 'ICON', 'TOWR', 'JGLE', 'WMUU'];
$yf_symbols = implode(',', array_map(function($s) { return $s . '.JK'; }, $sym_list));
$yf_url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols=" . urlencode($yf_symbols);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $yf_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
// Set user agent 
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
$yf_resp = curl_exec($ch);
curl_close($ch);
echo "Response:\n";
echo substr($yf_resp, 0, 100) . "...\n";
if ($yf_resp) {
    $yf_data = json_decode($yf_resp, true);
    if (isset($yf_data['quoteResponse']['result'])) {
        foreach ($yf_data['quoteResponse']['result'] as $q) {
            echo "Symbol: " . $q['symbol'] . " Price: " . $q['regularMarketPrice'] . "\n";
        }
    }
}
?>
