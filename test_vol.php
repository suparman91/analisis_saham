<?php require "db.php"; $c=db_connect(); $r=$c->query("SELECT symbol, close, volume FROM prices ORDER BY date DESC LIMIT 10"); while($row=$r->fetch_assoc()) { print_r($row); }
