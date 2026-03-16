$content = Get-Content -Raw "ara_hunter.php"
$content = $content -replace "\`$prob = 50;", "`$prob = 20;"
$content = $content -replace "if \(`$prob >= 50 \|\| `$status_ara \!\=\= 'POTENSI ARA BESOK'\)", "if (`$pct_to_ara <= 3 && `$pct_to_ara >= 0) `$prob = [Math]::Max(`$prob, 85);`n`n        if (`$prob >= 60 || `$status_ara !== 'POTENSI ARA BESOK')"
$content | Set-Content -Path "ara_hunter.php"
