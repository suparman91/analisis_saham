<?php require "db.php"; $m=db_connect(); $res=$m->query("SELECT symbol FROM stocks LIMIT 5"); while($row=$res->fetch_row()) echo $row[0]."\n"; ?>
