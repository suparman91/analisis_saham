<?php require 'db.php'; $db = db_connect(); $r = $db->query('SELECT VERSION() as v'); foreach($r as $row) { echo $row['v']; } ?>
