<?php require 'db.php'; $c = db_connect(); $c->query('ALTER TABLE stocks ADD COLUMN notation VARCHAR(100) DEFAULT NULL'); echo 'OK';
