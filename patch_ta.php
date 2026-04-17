<?php
$f = 'scan_ta.php';
$c = file_get_contents($f);
$c = str_replace('? Scanner Momentum &', '&#x26A1; Scanner Momentum &', $c);
$c = str_replace('Buka Chart ?', 'Buka Chart &#x1F4C8;', $c);
file_put_contents($f, $c);
