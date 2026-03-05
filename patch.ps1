
$content = Get-Content index.php -Raw
$content = $content -replace "async function render\(symbol\)\{", "async function render(symbol){`n      if(liveUpdateInterval) clearInterval(liveUpdateInterval);"
$content = $content -replace "(?s)\/\/ After chart rendered, attempt realtime fetch to update latest price.*console.warn\('Realtime fetch failed', e\);\s*\}\);", "updateLivePrice(symbol);`n          liveUpdateInterval = setInterval(() => updateLivePrice(symbol), 60000);"
Set-Content index.php $content

