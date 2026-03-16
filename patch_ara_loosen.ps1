$f = "ara_hunter.php"
$c = Get-Content -Raw $f

$oldSql = @"
    WHERE today.close >= 50 AND today.volume >= 2000000 
      AND today.close > prev.close
      AND (
          -- Kriteria 1: Momentum Volume & Harga kuat (Naik > 3%, Close dekat High, Volume > 1.5x)
          (today.close >= prev.close * 1.03 AND today.close >= today.high * 0.95 AND today.volume >= prev.volume * 1.5)
          OR
          -- Kriteria 2: Spike Volume masif (Volume > 3x, harga naik > 4%)
          (today.close >= prev.close * 1.04 AND today.volume >= prev.volume * 3)
          OR
          -- Kriteria 3: Sudah hampir ARA (naik > 15-20% tergantung fraksi)
          (today.close >= prev.close * 1.15)
      )
"@

$newSql = @"
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

$c = $c.Replace($oldSql, $newSql)

$c = $c.Replace('if ($prob >= 60 || $status_ara !== ''POTENSI ARA BESOK'') {', 'if ($prob >= 40 || $status_ara !== ''POTENSI ARA BESOK'') {')

$c | Set-Content -Path $f
