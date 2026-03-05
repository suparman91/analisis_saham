<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: PHP-Fetch-Agent/1.0\r\n"
    ]
];
$context = stream_context_create($opts);
$html = file_get_contents('https://id.wikipedia.org/wiki/LQ45', false, $context);
preg_match_all('/<td>(?:<a href="[^"]+" title=".*?">)?([A-Z]{4})(?:<\/a>)?<\/td>\s*<td>(.*?)<\/td>/is', $html, $matches);

$count = 0;
$stmt = $mysqli->prepare("INSERT IGNORE INTO stocks (symbol, name) VALUES (?, ?)");

if (!empty($matches[1])) {
    for ($i=0; $i<count($matches[1]); $i++) {
        $sym = trim($matches[1][$i]);
        $name = strip_tags(trim($matches[2][$i])); 
        $stmt->bind_param('ss', $sym, $name);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $count++;
            echo "Inserted: $sym - $name\n";
        }
    }
}
echo "Done! Added $count new LQ45 stocks.\n";
?>