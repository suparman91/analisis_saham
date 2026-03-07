<?php require 'db.php'; \ = db_connect(); print_r(\->query('SELECT COUNT(*) as x FROM (SELECT symbol FROM prices GROUP BY symbol HAVING COUNT(*) >= 20) as temp')->fetch_assoc()); ?>
