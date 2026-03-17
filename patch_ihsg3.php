<?php
$content = file_get_contents("ihsg.php");
// Adding some debug console.log logic to chart javascript.
$content = preg_replace("/fetch\('analyze_api\.php\?symbol='\+encodeURIComponent\(symbol\)\).then\(async r=>\{/", "console.log('Fetching:', 'analyze_api.php?symbol='+encodeURIComponent(symbol));\n      fetch('analyze_api.php?symbol='+encodeURIComponent(symbol)).then(async r=>{", $content);
file_put_contents("ihsg.php", $content);
echo "Added debug info";
?>
