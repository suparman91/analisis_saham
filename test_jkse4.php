<?php
require 'analyze.php';
$mysqli = db_connect();
$data = analyze_symbol($mysqli, '^JKSE');
echo json_encode($data, JSON_PRETTY_PRINT);
?>
