$content = Get-Content -Raw "ara_hunter.php"

$newSql = @"
`$sql_screener = "
    SELECT
        today.symbol, today.close, today.open, today.high, today.volume,
        prev.close as prev_close, prev.volume as prev_vol
    FROM
        (SELECT symbol, MAX(date) as max_date FROM prices GROUP BY symbol) latest
    JOIN prices today ON latest.symbol = today.symbol AND latest.max_date = today.date
    JOIN prices prev ON today.symbol = prev.symbol
        AND prev.date = (SELECT MAX(date) FROM prices p3 WHERE p3.symbol = today.symbol AND p3.date < today.date)
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
";
"@

$oldSqlRegex = '\$sql_screener = ".*?\)[\s\n]*";'
$content = [regex]::Replace($content, $oldSqlRegex, $newSql, [System.Text.RegularExpressions.RegexOptions]::Singleline)

$content | Set-Content -Path "ara_hunter.php"
