<?php
header('Content-Type: application/json');
if (php_sapi_name() === 'cli' && isset($argv)) { parse_str(implode('&', array_slice($argv, 1)), $_GET); }
$symbol = $_GET['symbol'] ?? '';

if (!$symbol) {
    echo json_encode(['error' => 'No symbol provided']);
    exit;
}

$url = "https://feeds.finance.yahoo.com/rss/2.0/headline?s=" . urlencode($symbol) . "&region=ID&lang=id-ID";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpcode !== 200) {
    echo json_encode(['error' => 'Failed to fetch news', 'code' => $httpcode]);
    exit;
}

$xml = @simplexml_load_string($response);
if (!$xml) {
    echo json_encode(['error' => 'Invalid RSS format']);
    exit;
}

$news = [];
if (isset($xml->channel->item)) {
    foreach ($xml->channel->item as $item) {
        $news[] = [
            'title' => (string)$item->title,
            'link' => (string)$item->link,
            'pubDate' => (string)$item->pubDate,
            'description' => strip_tags((string)$item->description)
        ];
        if (count($news) >= 8) break;
    }
}

echo json_encode(['symbol' => $symbol, 'news' => $news]);
?>
