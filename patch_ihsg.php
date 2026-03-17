<?php
$content = file_get_contents("ihsg.php");
$content = preg_replace("/window\.addEventListener\('load', function\(\) \{.*?\n    \}\);/s", "window.addEventListener('load', function() { setTimeout(function(){ render('^JKSE'); }, 300); });", $content);
file_put_contents("ihsg.php", $content);
echo "Replaced load event";
?>
