<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/analyze.php';

$symbol = $_GET['symbol'] ?? '';
if (!$symbol) {
    echo json_encode(['error'=>'symbol required']);
    exit;
}

$mysqli = db_connect();
$res = analyze_symbol($mysqli, $symbol);
echo json_encode($res);

?>
