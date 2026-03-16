<?php
require "db.php";
$m = db_connect();
$m->query("ALTER TABLE portfolio ADD COLUMN total_lot INT NOT NULL DEFAULT 0");
$m->query("ALTER TABLE portfolio ADD COLUMN last_notified DATETIME NULL");
echo "Altered!";
?>
