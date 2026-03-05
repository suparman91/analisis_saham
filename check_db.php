<?php require 'db.php'; $db=db_connect(); $r=$db->query('SELECT symbol FROM stocks;'); print_r($r->fetch_all());
