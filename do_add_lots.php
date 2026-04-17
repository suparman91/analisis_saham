<?php
require_once 'db.php';
$mysqli = db_connect();
$mysqli->query("ALTER TABLE portfolio ADD COLUMN lots INT NOT NULL DEFAULT 0 AFTER target_price");
echo "Done.\n";
