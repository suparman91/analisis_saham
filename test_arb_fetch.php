<?php
$yahoo_url = "https://query1.finance.yahoo.com/v7/finance/spark?symbols=BBCA.JK,GOTO.JK&range=1d&interval=1m";
$ch = curl_init($yahoo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
$res = curl_exec($ch);
curl_close($ch);
$d = json_decode($res, true);
print_r($d["spark"]["result"][0]["symbol"]);
?>
