<?php require 'db.php'; $mysqli = db_connect(); $res = $mysqli->query('SELECT * FROM prices WHERE symbol=\
ICON.JK\ ORDER BY date DESC LIMIT 4'); while($r=$res->fetch_assoc()) print_r($r); ?>
