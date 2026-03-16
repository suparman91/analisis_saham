$f = "ara_hunter.php"
$c = Get-Content -Raw $f

$oldBlock = @"
        $prob = 20; // Base prob diturunkan agar lebih selektif
        if ($signal === 'STRONG BUY') $prob += 20;
        elseif ($signal === 'BUY') $prob += 10;

        if (strpos(implode(',', $alasan), 'Volume') !== false) $prob += 15;
        if (strpos(implode(',', $alasan), 'Antrean') !== false) $prob += 15;
"@

$newBlock = @"
        `$prob = 30; // Sedikit dilonggarkan
        if (`$signal === 'STRONG BUY') `$prob += 20;
        elseif (`$signal === 'BUY') `$prob += 10;

        if (strpos(implode(',', `$alasan), 'Volume') !== false) `$prob += 15;
        if (strpos(implode(',', `$alasan), 'Antrean') !== false) `$prob += 15;
"@

$c = $c.Replace("        `$prob = 20; // Base prob diturunkan agar lebih selektif", "        `$prob = 30; // Base prob sedikit dilonggarkan")

$c | Set-Content -Path $f
