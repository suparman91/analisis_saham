<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$mysqli = db_connect();
$user_id = get_user_id();
$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $symbol = $_POST['symbol'] ?? '';
    if ($symbol) {
        $symbol = strtoupper(trim($symbol));
        $stmt = $mysqli->prepare("INSERT IGNORE INTO watchlist (user_id, symbol) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $symbol);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No symbol provided']);
    }
} elseif ($action === 'remove') {
    $symbol = $_POST['symbol'] ?? '';
    if ($symbol) {
        $symbol = strtoupper(trim($symbol));
        $stmt = $mysqli->prepare("DELETE FROM watchlist WHERE user_id = ? AND symbol = ?");
        $stmt->bind_param("is", $user_id, $symbol);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No symbol provided']);
    }
} elseif ($action === 'list') {
    $stmt = $mysqli->prepare("SELECT symbol FROM watchlist WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $symbols = [];
    while ($row = $res->fetch_assoc()) {
        $symbols[] = $row['symbol'];
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'data' => $symbols]);
}
?>