<?php
$f = "chart.php";
$c = file_get_contents($f);
$new_head = "<link href=\"https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css\" rel=\"stylesheet\">\n  <script src=\"https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js\"></script>\n</head>";
$c = str_replace("</head>", $new_head, $c);
file_put_contents($f, $c);
echo "Patched head.";
?>
