<?php
require 'db.php';
$db = db_connect();

$res = $db->query("SELECT symbol, name FROM stocks");
$all = [];
while($r = $res->fetch_assoc()) $all[] = $r;

// If we find symbols that are super long or don't look like 4 letters + .JK 
$bad = array_filter($all, function($v) {
   // Typical IDX symbol is like BBCA.JK
   return strlen($v['symbol']) > 8 || substr($v['symbol'], -3) !== '.JK' || strpos($v['symbol'], ' ') !== false || strlen($v['symbol']) < 7;
});

echo "Total stocks: " . count($all) . "\n";
echo "Suspect stocks: " . count($bad) . "\n";

$i = 0;
foreach($bad as $b) {
   echo $b['symbol'] . ' => ' . $b['name'] . "\n";
   if (++$i > 20) break;
}
