<?php require "db.php"; $c = db_connect(); print_r($c->query("SELECT COUNT(DISTINCT symbol) as cnt FROM prices")->fetch_assoc()); ?>
