<?php
$content = file_get_contents("analyze.php");
$content = str_replace("function auto_fetch_history(\$mysqli, \$symbol) {", "function auto_fetch_history(\$mysqli, \$symbol) {\n    \$mysqli->query(\"INSERT IGNORE INTO stocks (symbol, name) VALUES ('\".\$mysqli->real_escape_string(\$symbol).\"', 'Auto Added')\");\n", $content);
file_put_contents("analyze.php", $content);
echo "Ok";
?>
