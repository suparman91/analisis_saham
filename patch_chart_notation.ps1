$content = Get-Content -Raw "chart.php"
$content = $content.Replace("SELECT symbol,name FROM stocks ORDER BY symbol", "SELECT symbol,name,notation FROM stocks ORDER BY symbol")
$content = $content.Replace("'</option>'; ?>", " . (!empty(`$s['notation']) ? \" [\" . `$s['notation'] . \"]\" : '') . '</option>'; ?>")
$content | Set-Content -Path "chart.php"
