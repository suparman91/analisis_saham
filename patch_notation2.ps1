$content = Get-Content -Raw "index.php"

$content = [regex]::Replace($content, '<td><strong><a href="chart\.php\?symbol=<\?= urlencode\(`\$s\[''symbol''\]\)\ \?>.*?>.*?</a\></strong></td>', '<td><?= format_symbol_badge($s) ?></td>', [System.Text.RegularExpressions.RegexOptions]::Singleline)

$content | Set-Content -Path "index.php"
