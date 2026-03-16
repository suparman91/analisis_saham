$f = "ara_hunter.php"
$c = Get-Content -Raw $f

$oldSql = @"
    WHERE today.close >= 50 AND today.volume >= 1000000 
      AND today.close > prev.close
      AND (
          -- Kriteria 1: Naik >2%, Close 90% dekat High, Volume > 1.2x rata-rata
          (today.close >= prev.close * 1.02 AND today.close >= today.high * 0.90 AND today.volume >= prev.volume * 1.2)
          OR
          -- Kriteria 2: Spike Volume (Volume > 2x, harga naik > 3%)
          (today.close >= prev.close * 1.03 AND today.volume >= prev.volume * 2)
          OR
          -- Kriteria 3: Akumulasi harga agresif (Naik > 8%)
          (today.close >= prev.close * 1.08)
      )
"@

$newSql = @"
    WHERE today.close >= 50 AND today.volume >= 500000 
      AND today.close > prev.close
      AND (
          -- Kriteria 1: Naik >2%, Close 90% dekat High, Volume > 1.2x rata-rata
          (today.close >= prev.close * 1.02 AND today.close >= today.high * 0.90 AND today.volume >= prev.volume * 1.2)
          OR
          -- Kriteria 2: Spike Volume (Volume > 1.5x, harga naik > 3%)
          (today.close >= prev.close * 1.03 AND today.volume >= prev.volume * 1.5)
          OR
          -- Kriteria 3: Akumulasi harga agresif (Naik > 5%)
          (today.close >= prev.close * 1.05)
      )
"@

$c = $c.Replace($oldSql, $newSql)

$c | Set-Content -Path $f
