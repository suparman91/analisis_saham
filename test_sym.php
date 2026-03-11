<?php require "db.php"; $m=db_connect(); $r=$m->query("SELECT symbol FROM stocks LIMIT 5"); while($row=$r->fetch_assoc()) echo $row["symbol"] . "\n"; ?>
