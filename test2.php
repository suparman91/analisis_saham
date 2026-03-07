<?php require "db.php"; $mysqli = db_connect(); $res = $mysqli->query("SELECT symbol FROM stocks LIMIT 5"); while ($r = $res->fetch_assoc()) echo $r["symbol"] . PHP_EOL;
