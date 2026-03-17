<?php
$url = 'http://localhost/analisis_saham/analyze_api.php?symbol=%5EJKSE';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo substr($res, 0, 100);
?>
