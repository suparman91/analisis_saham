$f = "analyze.php"
$c = Get-Content $f -Raw
$c = $c -replace '\$sma20 = sma\(\$closes, 20\);', "`$sma20 = sma(`$closes, 20);`n    `$sma50 = sma(`$closes, 50);`n    `$sma200 = sma(`$closes, 200);"
$c = $c -replace "'sma20'=>`$sma20,", "'sma20'=>`$sma20,`n        'sma50'=>`$sma50,`n        'sma200'=>`$sma200,"
Set-Content -Path $f -Value $c
