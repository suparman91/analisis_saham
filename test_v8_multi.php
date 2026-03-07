<?php
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/?symbol=invalid&symbols=BBCA.JK,BNBR.JK,ICON.JK,WMUU.JK&range=1d&interval=1d';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
        'Accept: application/json'
    ]);
    echo substr(curl_exec($ch), 0, 300) . "\n";
?>
