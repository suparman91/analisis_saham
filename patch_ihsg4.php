<?php
$content = file_get_contents("ihsg.php");
// Remove the leftover saveSettings reference to symbol
$content = str_replace("if (document.getElementById('symbol').value) render(document.getElementById('symbol').value);", "if ('^JKSE') render('^JKSE');", $content);

file_put_contents("ihsg.php", $content);
echo "Fixed JS crash\n";
?>
