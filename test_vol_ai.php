<?php require "db.php"; $c=db_connect(); $r=$c->query("SELECT * FROM prices WHERE symbol='BBCA.JK' ORDER BY date DESC LIMIT 2"); while($row=$r->fetch_assoc()) print_r($row); ?>
