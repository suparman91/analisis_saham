$f = "ara_hunter.php"
$c = Get-Content -Raw $f

$c = $c.Replace("if (`$prob >= 50 || `$status_ara !== 'POTENSI ARA BESOK') {", "if (`$prob >= 55 || `$status_ara !== 'POTENSI ARA BESOK') {")

$c | Set-Content -Path $f
