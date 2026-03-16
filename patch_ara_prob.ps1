$content = Get-Content -Raw "ara_hunter.php"

$oldBlock = @"
        `$prob = 50;
        if (`$signal === 'STRONG BUY') `$prob += 25;
        elseif (`$signal === 'BUY') `$prob += 10;

        if (strpos(implode(',', `$alasan), 'Volume') !== false) `$prob += 15;
        if (`$fund === 'UNDERVALUED (Good to Buy)' || strpos(`$fund, 'FAIR') !== false) `$prob += 10; 

        // Add dynamic probability based on actual indicators
        if (strpos(`$tech_detail, 'MACD Positive') !== false) `$prob += 5;
        if (strpos(`$tech_detail, 'RSI Oversold') !== false) `$prob += 5;
        if (strpos(`$tech_detail, 'SMA Bullish') !== false) `$prob += 5;

        // Sentimen Summary
        `$sentimen = [];
        if (`$fund !== 'N/A') `$sentimen[] = "Valuasi: " . explode(' ', `$fund)[0];
        if (`$tech_detail) {
            `$tech_items = explode(', ', `$tech_detail);
            `$sentimen[] = "Tech: " . implode(', ', array_slice(`$tech_items, 0, 2)); // Ambil max 2 sinyal kuat
        }

        if (`$c >= `$ara_limit) `$prob = 99;

        if (`$prob >= 50 || `$status_ara !== 'POTENSI ARA BESOK') {
"@

$newBlock = @"
        `$prob = 20; // Base prob diturunkan
        if (`$signal === 'STRONG BUY') `$prob += 20;
        elseif (`$signal === 'BUY') `$prob += 10;

        if (strpos(implode(',', `$alasan), 'Volume') !== false) `$prob += 15;
        if (`$fund === 'UNDERVALUED (Good to Buy)' || strpos(`$fund, 'FAIR') !== false) `$prob += 10; 

        // Add dynamic probability based on actual indicators
        if (strpos(`$tech_detail, 'MACD Positive') !== false) `$prob += 10;
        if (strpos(`$tech_detail, 'RSI Oversold') !== false) `$prob += 5;
        if (strpos(`$tech_detail, 'SMA Bullish') !== false) `$prob += 10;

        // Sentimen Summary
        `$sentimen = [];
        if (`$fund !== 'N/A') `$sentimen[] = "Valuasi: " . explode(' ', `$fund)[0];
        if (`$tech_detail) {
            `$tech_items = explode(', ', `$tech_detail);
            `$sentimen[] = "Tech: " . implode(', ', array_slice(`$tech_items, 0, 2)); // Ambil max 2 sinyal kuat
        }

        if (`$c >= `$ara_limit) `$prob = 99;
        if (`$pct_to_ara <= 3 && `$pct_to_ara >= 0) `$prob = max(`$prob, 85);

        if (`$prob >= 60 || `$status_ara !== 'POTENSI ARA BESOK') {
"@

$content = $content.Replace($oldBlock, $newBlock)

$content | Set-Content -Path "ara_hunter.php"
