<?php
/**
 * Robo Pending Approvals AJAX Handler
 * Handles approval/rejection of pending BUY/ACCUMULATE decisions
 */
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$user_id = get_user_id();
$action = $_POST['action'] ?? 'list';
$mysqli = db_connect();

// Ensure pending decisions table exists
function ensurePendingDecisionsTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS robo_pending_decisions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        decision_type ENUM('BUY', 'ACCUMULATE', 'SELL') NOT NULL,
        symbol VARCHAR(20) NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        lots INT NOT NULL,
        reason TEXT NULL,
        status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        approved_by_user INT NULL,
        approved_at DATETIME NULL,
        rejection_reason VARCHAR(255) NULL,
        executed_at DATETIME NULL,
        INDEX idx_user_status (user_id, status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensurePendingDecisionsTable($mysqli);
require_subscription($mysqli);

try {
    if ($action === 'list') {
        // List pending decisions
        $stmt = $mysqli->prepare("SELECT * FROM robo_pending_decisions WHERE user_id = ? AND status = 'PENDING' ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $pending = [];
        while ($row = $result->fetch_assoc()) {
            $pending[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'pending_count' => count($pending),
            'decisions' => $pending
        ]);
        
    } elseif ($action === 'approve') {
        // Approve a pending decision
        $decision_id = (int)$_POST['decision_id'];
        
        $stmt = $mysqli->prepare("SELECT * FROM robo_pending_decisions WHERE id = ? AND user_id = ? AND status = 'PENDING'");
        $stmt->bind_param("ii", $decision_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Decision not found or already processed");
        }
        
        $decision = $result->fetch_assoc();
        
        // Get user balance
        $user_res = $mysqli->query("SELECT robo_balance FROM users WHERE id = {$user_id}");
        $user = $user_res->fetch_assoc();
        $balance = (float)$user['robo_balance'];
        
        $symbol = $decision['symbol'];
        $price = (float)$decision['price'];
        $lots = (int)$decision['lots'];
        $decision_type = $decision['decision_type'];
        $reason = $decision['reason'];
        
        // Execute the decision
        if ($decision_type === 'BUY' || $decision_type === 'ACCUMULATE') {
            $buy_value = $lots * 100 * $price;
            
            if ($balance < $buy_value) {
                // Insufficient balance - reject automatically
                $mysqli->query("UPDATE robo_pending_decisions SET status='REJECTED', rejection_reason='Saldo tidak cukup (Rp " . number_format($buy_value, 0) . " diperlukan, available: Rp " . number_format($balance, 0) . ")' WHERE id={$decision_id}");
                
                throw new Exception("Insufficient balance: Rp " . number_format($buy_value, 0) . " needed, available: Rp " . number_format($balance, 0));
            }
            
            $buy_date = date('Y-m-d');
            $sym_esc = $mysqli->real_escape_string($symbol);
            $reason_esc = $mysqli->real_escape_string($reason);
            
            $insert_sql = "INSERT INTO robo_trades (user_id, symbol, buy_price, buy_date, buy_reason, lots, status) VALUES ({$user_id}, '{$sym_esc}', {$price}, '{$buy_date}', '{$reason_esc} [MANUAL APPROVED]', {$lots}, 'OPEN')";
            
            if (!$mysqli->query($insert_sql)) {
                throw new Exception("Failed to insert trade: " . $mysqli->error);
            }
            
            $mysqli->query("UPDATE users SET robo_balance = robo_balance - {$buy_value} WHERE id = {$user_id}");
            $mysqli->query("UPDATE robo_pending_decisions SET status='APPROVED', approved_by_user={$user_id}, approved_at=NOW(), executed_at=NOW() WHERE id={$decision_id}");
            
            echo json_encode([
                'status' => 'success',
                'message' => "Approved! Trade executed: {$lots} lots {$symbol} @ Rp " . number_format($price, 0, ',', '.')
            ]);
            
        } elseif ($decision_type === 'SELL') {
            // Find the trade to close
            $trade_res = $mysqli->query("SELECT * FROM robo_trades WHERE user_id={$user_id} AND symbol='{$mysqli->real_escape_string($symbol)}' AND status='OPEN' LIMIT 1");
            
            if ($trade_res->num_rows === 0) {
                throw new Exception("Open trade not found for {$symbol}");
            }
            
            $trade = $trade_res->fetch_assoc();
            $sell_value = (float)$trade['lots'] * 100 * $price;
            $pl_rp = $sell_value - ((float)$trade['buy_price'] * (int)$trade['lots'] * 100);
            $pl_pct = ($pl_rp / ((float)$trade['buy_price'] * (int)$trade['lots'] * 100)) * 100;
            
            $sell_date = date('Y-m-d');
            $reason_esc = $mysqli->real_escape_string($reason);
            
            $mysqli->query("UPDATE robo_trades SET status='CLOSED', sell_price={$price}, sell_date='{$sell_date}', sell_reason='{$reason_esc} [MANUAL APPROVED]', profit_loss_rp={$pl_rp}, profit_loss_pct={$pl_pct} WHERE id={$trade['id']}");
            $mysqli->query("UPDATE users SET robo_balance = robo_balance + {$sell_value} WHERE id = {$user_id}");
            $mysqli->query("UPDATE robo_pending_decisions SET status='APPROVED', approved_by_user={$user_id}, approved_at=NOW(), executed_at=NOW() WHERE id={$decision_id}");
            
            echo json_encode([
                'status' => 'success',
                'message' => "Approved! Trade closed: {$symbol} @ Rp " . number_format($price, 0, ',', '.') . " (P/L: " . round($pl_pct, 2) . "%)"
            ]);
        }
        
    } elseif ($action === 'reject') {
        // Reject a pending decision
        $decision_id = (int)$_POST['decision_id'];
        $reason = $_POST['reason'] ?? 'Rejected by user';
        
        $stmt = $mysqli->prepare("SELECT * FROM robo_pending_decisions WHERE id = ? AND user_id = ? AND status = 'PENDING'");
        $stmt->bind_param("ii", $decision_id, $user_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("Decision not found or already processed");
        }
        
        $reason_esc = $mysqli->real_escape_string($reason);
        $mysqli->query("UPDATE robo_pending_decisions SET status='REJECTED', rejection_reason='{$reason_esc}' WHERE id={$decision_id}");
        
        echo json_encode([
            'status' => 'success',
            'message' => "Decision rejected"
        ]);
        
    } else {
        throw new Exception("Invalid action");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$mysqli->close();
?>
