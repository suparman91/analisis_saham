$content = Get-Content -Raw "index.php"

$content = $content.Replace('<td><strong><a href="chart.php?symbol=<?= urlencode($s[''symbol'']) ?>" target="_blank" style="text-decoration:none; color:#198754;"><?= $s[''symbol''] ?></a></strong></td>', '<td><?= format_symbol_badge($s, "#198754") ?></td>')
$content = $content.Replace('<td><strong><a href="chart.php?symbol=<?= urlencode($s[''symbol'']) ?>" target="_blank" style="text-decoration:none; color:#dc3545;"><?= $s[''symbol''] ?></a></strong></td>', '<td><?= format_symbol_badge($s, "#dc3545") ?></td>')
$content = $content.Replace('<td><strong><a href="chart.php?symbol=<?= urlencode($s[''symbol'']) ?>" target="_blank" style="text-decoration:none; color:#0d6efd;"><?= $s[''symbol''] ?></a></strong></td>', '<td><?= format_symbol_badge($s, "#0d6efd") ?></td>')

$content | Set-Content -Path "index.php"
