<?php
require 'db.php';
$db = db_connect();
$res = $db->query("SHOW CREATE TABLE ara_hunter_history");
$r = $res->fetch_assoc();
echo $r['Create Table'];
