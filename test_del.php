<?php require "db.php"; $m=db_connect(); $m->query("DELETE FROM stocks WHERE symbol NOT LIKE '%.JK' AND symbol NOT IN ('AAPL', 'MSFT')"); ?>
