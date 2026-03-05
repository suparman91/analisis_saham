<?php
$url = "https://www.idx.co.id/primary/ListedCompany/GetCompanyProfiles?language=id-id&pageNumber=1&pageSize=9999";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept: application/json',
    'Referer: https://www.idx.co.id/id/perusahaan-tercatat/profil-perusahaan-tercatat',
    'Origin: https://www.idx.co.id'
]);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: $httpCode\n";
if ($httpCode == 200) { echo "Success: " . strlen($res) . " bytes\n"; }
?>