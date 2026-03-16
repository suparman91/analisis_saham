$content = Get-Content -Raw "stockpick.php"

$replacement = @"
<td><strong><a href="chart.php?symbol=<?= urlencode(`$p["symbol"]) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= htmlspecialchars(`$p["symbol"]) ?></a></strong><?php if(!empty(`$p['notation'])): ?> <span style="background:#ffc107; color:#000; font-size:9px; padding:2px 4px; border-radius:4px; margin-left:4px; vertical-align:super; font-weight:bold; display:inline-block;" title="Notasi Khusus: <?= htmlspecialchars(`$p['notation']) ?>"><?= htmlspecialchars(`$p['notation']) ?></span><?php endif; ?></td>
"@

$content = $content.Replace('<td><strong><a href="chart.php?symbol=<?= urlencode($p["symbol"]) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= $p["symbol"] ?></a></strong></td>', $replacement)

$content | Set-Content -Path "stockpick.php"
