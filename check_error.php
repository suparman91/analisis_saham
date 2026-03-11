<?php require "db.php"; $m=db_connect(); $r=$m->query("SHOW CREATE TABLE prices"); echo $r->fetch_row()[1]; ?>
