<?php
$content = file_get_contents("ara_hunter.php");

// Replace Screen Logic
$content = preg_replace("/WHERE today\.close >= 50.*?\)[\s\r\n]*\"/s", "WHERE today.close >= 50 AND today.volume >= 500000 
      AND today.close < prev.close
      AND (
          -- Kriteria 1: Harga turun > 2% dan close di dekat low (tekanan jual)
          (today.close <= prev.close * 0.98 AND today.close <= today.low * 1.02 AND today.volume >= prev.volume * 1.2)
          OR
          -- Kriteria 2: Spike Volume saat turun (Distribusi)
          (today.close <= prev.close * 0.97 AND today.volume >= prev.volume * 1.5)
          OR
          -- Kriteria 3: Saham anjlok parah (> 5%)
          (today.close <= prev.close * 0.95)
      )
\"", $content);

$content = str_replace("POTENSI ARA BESOK", "POTENSI ARB / TURUN", $content);
$content = str_replace("KUNCI ARA", "KUNCI ARB", $content);
$content = str_replace("MENGINCAR ARA", "MENGINCAR ARB", $content);
$content = str_replace("Volume Buy Spike", "Volume Distribusi (Sell Spike)", $content);
$content = str_replace("Closing High (Marubozu)", "Closing Low (Bearish Marubozu)", $content);
$content = str_replace("Antrean bid menebal", "Antrean offer membludak / Bid tipis", $content);
$content = str_replace("Sudah Limit Atas", "Sudah Limit Bawah (ARB)", $content);
$content = str_replace("ARA Hunter", "ARB Hunter (Saham Potensi Turun)", $content);
$content = str_replace("\$ara_limit", "\$arb_limit", $content);
$content = str_replace("calcARA(\$h1)", "calcARB(\$h1)", $content);
$content = str_replace("Hit ARA", "Hit ARB", $content);

// Inverting checking logic for ARB
$content = str_replace("\$pct_to_ara = ((\$ara_limit - \$c) / \$ara_limit) * 100;", "\$pct_to_ara = ((\$c - \$arb_limit) / \$c) * 100;", $content);
$content = str_replace("\$c >= \$arb_limit", "\$c <= \$arb_limit", $content);
$content = str_replace("\$h >= \$arb_limit", "\$row['low'] <= \$arb_limit", $content);
$content = str_replace("\$is_hit_ara", "\$is_hit_arb", $content);
$content = str_replace("'hit_ara' => \$is_hit_ara", "'hit_arb' => \$is_hit_arb", $content);

// Display replacements
$content = str_replace("Harga ARA", "Harga ARB", $content);
$content = str_replace("Status ARA", "Status Down", $content);
$content = str_replace("Potensi ARA", "Potensi Turun", $content);
$content = str_replace("Jarak ARA", "Jarak ARB", $content);

// Update probability logic
$content = str_replace("\$signal === 'STRONG BUY'", "\$signal === 'STRONG SELL'", $content);
$content = str_replace("\$signal === 'BUY'", "\$signal === 'SELL'", $content);
$content = str_replace("UNDERVALUED (Good to Buy)", "OVERVALUED (Good to Sell)", $content);
$content = str_replace("MACD Positive", "MACD Negative", $content);
$content = str_replace("RSI Oversold", "RSI Overbought", $content);
$content = str_replace("SMA Bullish", "SMA Bearish", $content);

// Visuals
$content = str_replace("ara-badge", "arb-badge", $content);
$content = str_replace("🚀", "📉", $content);
$content = str_replace("🔥", "⚠️", $content);
$content = str_replace("color: #ebf5ff; background: #3b82f6", "color: #ffebee; background: #ef4444", $content);
$content = str_replace("color: #166534; background: #dcfce7", "color: #991b1b; background: #fee2e2", $content);
$content = str_replace("color: #b45309; background: #fef3c7", "color: #9f1239; background: #ffedd5", $content);
$content = str_replace("\$c >= \$h1 && \$pct_to_ara <= 3", "\$c <= \$h1 && \$pct_to_ara <= 3", $content);

// Update to ARB menu
$content = str_replace("<a href=\"scan_manual.php\">Scanner Manual</a>", "<a href=\"scan_manual.php\">Scanner Manual</a> <a href=\"ara_hunter.php\">🚀 ARA Hunter</a>", $content);
$content = preg_replace("/<a href=\"ara_hunter\.php\" class=\"active\">.*?<\/a>/", "<a href=\"arb_hunter.php\" class=\"active\">📉 ARB Hunter</a>", $content);


file_put_contents("arb_hunter.php", $content);
echo "ARB Hunter created successfully.\n";
