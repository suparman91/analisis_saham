<?php require "db.php"; $m=db_connect(); $res=$m->query("SELECT COUNT(*) FROM prices WHERE symbol='ADRO.JK'"); echo "Prices: " . $res->fetch_row()[0]; ?>
