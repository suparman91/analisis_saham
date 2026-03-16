$content = Get-Content -Raw "index.php"

$func = @"
function format_symbol_badge(`$s) {
    `$html = '<strong><a href="chart.php?symbol=' . urlencode(`$s['symbol']) . '" target="_blank" style="text-decoration:none;">' . htmlspecialchars(`$s['symbol']) . '</a></strong>';
    if (!empty(`$s['notation'])) {
        `$html .= ' <span class="badge notation-badge" title="Notasi Khusus: ' . htmlspecialchars(`$s['notation']) . '">' . htmlspecialchars(`$s['notation']) . '</span>';
    }
    return `$html;
}

// For Multibagger: All stocks with strong momentum
"@

$content = $content -replace "// For Multibagger: All stocks with strong momentum", $func

$content | Set-Content -Path "index.php"
