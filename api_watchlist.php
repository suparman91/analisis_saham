<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $symbol = $_POST['symbol'] ?? '';
    if ($symbol) {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO watchlist (symbol) VALUES (?)");
        $stmt->bind_param("s", $symbol);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No symbol provided']);
    }
} elseif ($action === 'remove') {
    $symbol = $_POST['symbol'] ?? '';
    if ($symbol) {
        $stmt = $mysqli->prepare("DELETE FROM watchlist WHERE symbol = ?");
        $stmt->bind_param("s", $symbol);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No symbol provided']);
    }
} elseif ($action === 'list') {
    $res = $mysqli->query("SELECT symbol FROM watchlist");
    $symbols = [];
    while ($row = $res->fetch_assoc()) {
        $symbols[] = $row['symbol'];
    }
    echo json_encode(['status' => 'success', 'data' => $symbols]);
}
?>