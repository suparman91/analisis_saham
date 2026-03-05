<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// Fetch from goapi.id (free community endpoint)
$url = 'https://goapi.id/api/stock/v1/idx';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    if (isset($data['data']['results']) && is_array($data['data']['results'])) {
        $count = 0;
        $stmt = $mysqli->prepare("INSERT IGNORE INTO stocks (symbol, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        
        foreach ($data['data']['results'] as $company) {
            if (isset($company['ticker']) && isset($company['name'])) {
                $sym = trim($company['ticker']) . '.JK'; // append .JK for yahoo compatibility
                $name = trim($company['name']);
                
                $stmt->bind_param('ss', $sym, $name);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) $count++;
                }
            }
        }
        echo "Berhasil memasukkan/memperbarui $count rincian saham IHSG ke database!\n";
    } else {
        echo "Format JSON tidak sesuai harapan.\n";
    }
} else {
    echo "Gagal mengambil data. Code: $httpCode\n";
}
?>
