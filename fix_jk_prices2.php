<?php
require_once 'db.php';
$m = db_connect();
$m->query("UPDATE portfolio SET symbol = CONCAT(symbol, '.JK') WHERE symbol NOT LIKE '%.%'");
$m->query("UPDATE robo_trades SET symbol = CONCAT(symbol, '.JK') WHERE symbol NOT LIKE '%.%'");
echo "Done.";
