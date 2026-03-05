<?php
require_once __DIR__ . '/db.php';

// URL for IDX (Bursa Efek Indonesia) company lists
$url = "https://www.idx.co.id/primary/ListedCompany/GetCompanyProfiles?language=id-id&pageNumber=1&pageSize=9999";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0 Safari/537.36',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    if (isset($data['data']) && is_array($data['data'])) {
        $mysqli = db_connect();
        $count = 0;
        
        $stmt = $mysqli->prepare("INSERT IGNORE INTO stocks (symbol, name) VALUES (?, ?)");
        
        foreach ($data['data'] as $company) {
            if (isset($company['TickerCode']) && isset($company['EmitenName'])) {
                $sym = trim($company['TickerCode']);
                $name = trim($company['EmitenName']);
                if (strlen($sym) >= 4) {
                    $stmt->bind_param('ss', $sym, $name);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $count++;
                        echo "Inserted: $sym - $name\n";
                    }
                }
            }
        }
        
        echo "Done! Added $count new BEI stocks to the database.\n";
        exit;
    }
}
echo "Failed mapping API IDX. Code: $httpCode\n";

// Fallback logic using allorigins proxy if IDX rejects empty referer directly
$proxyUrl = 'https://api.allorigins.win/raw?url=' . urlencode($url);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $proxyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
if (isset($data['data']) && is_array($data['data'])) {
    $mysqli = db_connect();
    $count = 0;
    $stmt = $mysqli->prepare("INSERT IGNORE INTO stocks (symbol, name) VALUES (?, ?)");
    foreach ($data['data'] as $company) {
        if (isset($company['TickerCode']) && isset($company['EmitenName'])) {
            $sym = trim($company['TickerCode']);
            $name = trim($company['EmitenName']);
            if (strlen($sym) >= 4) {
                $stmt->bind_param('ss', $sym, $name);
                $stmt->execute();
                if ($stmt->affected_rows > 0) { $count++; }
            }
        }
    }
    echo "Done via proxy! Added $count new BEI stocks to the database.\n";
    exit;
}

echo "Proxy failed too.\n";
?>