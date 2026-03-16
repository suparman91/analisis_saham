$f = "ara_hunter.php"
$c = Get-Content -Raw $f

$oldProb = "        `$prob = 20;`n        if (`$signal === 'STRONG BUY') `$prob += 25;"
$newProb = "        `$prob = 35; // Base prob cukup untuk menyeleksi 10-20 emiten`n        if (`$signal === 'STRONG BUY') `$prob += 20;"

$c = $c -replace '        \$prob = 20;[ \t\r\n]*if \(\$signal === ''STRONG BUY''\) \$prob \+= 25;', "        `$prob = 35;`r`n        if (`$signal === 'STRONG BUY') `$prob += 20;"

$c | Set-Content -Path $f
