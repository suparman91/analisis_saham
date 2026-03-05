<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$url = 'https://goapi.id/api/stock/v1/idx';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$out = curl_exec($ch);
curl_close($ch);

$j = json_decode($out, true);
if (isset($j['data']['results']) && is_array($j['data']['results'])) {
    $count = 0;
    $stmt = $mysqli->prepare("INSERT INTO stocks (symbol, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    foreach ($j['data']['results'] as $c) {
        if (!isset($c['ticker'])) continue;
        $sym = strtoupper(trim($c['ticker'])) . '.JK';
        $n = trim($c['name']);
        
        $stmt->bind_param('ss', $sym, $n);
        $stmt->execute();
        if ($stmt->affected_rows > 0) $count++;
    }
    echo "Selesai! Berhasil mengupdate " . $count . " emiten dari API (goapi.id).\n";
} else {
    // fallback if api changed
    echo "Menggunakan proxy untuk idx official api...\n";
    $url2 = 'https://api.allorigins.win/raw?url=' . urlencode('https://www.idx.co.id/primary/ListedCompany/GetCompanyProfiles?language=id-id&pageNumber=1&pageSize=9999');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $out = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($out, true);
    if(isset($j['data'])){
        $count=0;
        $stmt = $mysqli->prepare("INSERT INTO stocks (symbol, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        foreach($j['data'] as $c){
            $sym = strtoupper(trim($c['TickerCode'])) . '.JK';
            $n = trim($c['EmitenName']);
            $stmt->bind_param('ss', $sym, $n);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $count++;
        }
        echo "Selesai IDX/AllOrigins! Berhasil mengupdate " . $count . " emiten.\n";
    }
}
