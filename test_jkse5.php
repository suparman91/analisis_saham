<?php
require 'analyze.php';
$mysqli = db_connect();
$data = analyze_symbol($mysqli, '^JKSE');
if (isset($data['error'])) {
    echo "Error: " . $data['error'];
} else {
    echo "Success: " . count($data['prices']) . " prices loaded.";
}
?>
