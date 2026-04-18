<?php
/**
 * Robo Advanced Settings
 * Guard Price, Whitelist/Blacklist, Semi-Auto Mode
 */
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$user_id = get_user_id();
$action = $_POST['action'] ?? 'get_settings';
$mysqli = db_connect();

// Create settings table if not exists
function ensureSettingsTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS robo_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        guard_price_threshold DECIMAL(5,2) DEFAULT 5.0,
        semi_auto_mode INT DEFAULT 0,
        manual_approval INT DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $mysqli->query("CREATE TABLE IF NOT EXISTS robo_whitelist_blacklist (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        symbol VARCHAR(20) NOT NULL,
        list_type ENUM('WHITELIST', 'BLACKLIST') NOT NULL,
        reason VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_symbol (user_id, symbol),
        INDEX idx_user_type (user_id, list_type),
        UNIQUE KEY idx_unique (user_id, symbol, list_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureSettingsTable($mysqli);
require_subscription($mysqli);

try {
    if ($action === 'get_settings') {
        // Get user settings
        $stmt = $mysqli->prepare("SELECT guard_price_threshold, semi_auto_mode, manual_approval FROM robo_settings WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Initialize default settings
            $mysqli->query("INSERT INTO robo_settings (user_id, guard_price_threshold, semi_auto_mode, manual_approval) VALUES ({$user_id}, 5.0, 0, 0)");
            $settings = [
                'guard_price_threshold' => 5.0,
                'semi_auto_mode' => 0,
                'manual_approval' => 0
            ];
        } else {
            $settings = $result->fetch_assoc();
        }
        
        // Get whitelist count
        $whitelist_count = $mysqli->query("SELECT COUNT(*) c FROM robo_whitelist_blacklist WHERE user_id={$user_id} AND list_type='WHITELIST'")->fetch_assoc()['c'];
        // Get blacklist count
        $blacklist_count = $mysqli->query("SELECT COUNT(*) c FROM robo_whitelist_blacklist WHERE user_id={$user_id} AND list_type='BLACKLIST'")->fetch_assoc()['c'];
        
        echo json_encode([
            'status' => 'success',
            'settings' => $settings,
            'whitelist_count' => $whitelist_count,
            'blacklist_count' => $blacklist_count
        ]);
        
    } elseif ($action === 'update_guard_price') {
        // Update guard price threshold
        $threshold = (float)$_POST['threshold'];
        
        if ($threshold < 0 || $threshold > 50) {
            throw new Exception("Guard price threshold harus antara 0-50%");
        }
        
        $stmt = $mysqli->prepare("INSERT INTO robo_settings (user_id, guard_price_threshold) VALUES (?, ?) ON DUPLICATE KEY UPDATE guard_price_threshold = VALUES(guard_price_threshold)");
        $stmt->bind_param("id", $user_id, $threshold);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => "Guard price threshold diubah menjadi {$threshold}%"
        ]);
        
    } elseif ($action === 'toggle_semi_auto') {
        // Toggle semi-auto mode
        $enabled = (int)$_POST['enabled'];
        
        $stmt = $mysqli->prepare("INSERT INTO robo_settings (user_id, semi_auto_mode) VALUES (?, ?) ON DUPLICATE KEY UPDATE semi_auto_mode = VALUES(semi_auto_mode)");
        $stmt->bind_param("ii", $user_id, $enabled);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => "Semi-auto mode: " . ($enabled ? "ENABLED" : "DISABLED")
        ]);
        
    } elseif ($action === 'toggle_manual_approval') {
        // Toggle manual approval mode
        $enabled = (int)$_POST['enabled'];
        
        $stmt = $mysqli->prepare("INSERT INTO robo_settings (user_id, manual_approval) VALUES (?, ?) ON DUPLICATE KEY UPDATE manual_approval = VALUES(manual_approval)");
        $stmt->bind_param("ii", $user_id, $enabled);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => "Manual approval mode: " . ($enabled ? "ENABLED" : "DISABLED")
        ]);
        
    } elseif ($action === 'add_whitelist') {
        // Add to whitelist
        $symbol = strtoupper($_POST['symbol']);
        $reason = $_POST['reason'] ?? '';
        
        if (strlen($symbol) < 2 || strlen($symbol) > 20) {
            throw new Exception("Symbol tidak valid");
        }
        
        $symbol_esc = $mysqli->real_escape_string($symbol);
        $reason_esc = $mysqli->real_escape_string($reason);
        
        $stmt = $mysqli->prepare("INSERT INTO robo_whitelist_blacklist (user_id, symbol, list_type, reason) VALUES (?, ?, 'WHITELIST', ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
        $stmt->bind_param("iss", $user_id, $symbol_esc, $reason_esc);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => "{$symbol} ditambahkan ke whitelist"
        ]);
        
    } elseif ($action === 'remove_whitelist') {
        // Remove from whitelist
        $symbol = strtoupper($_POST['symbol']);
        $symbol_esc = $mysqli->real_escape_string($symbol);
        
        $mysqli->query("DELETE FROM robo_whitelist_blacklist WHERE user_id={$user_id} AND symbol='{$symbol_esc}' AND list_type='WHITELIST'");
        
        echo json_encode([
            'status' => 'success',
            'message' => "{$symbol} dihapus dari whitelist"
        ]);
        
    } elseif ($action === 'add_blacklist') {
        // Add to blacklist
        $symbol = strtoupper($_POST['symbol']);
        $reason = $_POST['reason'] ?? '';
        
        if (strlen($symbol) < 2 || strlen($symbol) > 20) {
            throw new Exception("Symbol tidak valid");
        }
        
        $symbol_esc = $mysqli->real_escape_string($symbol);
        $reason_esc = $mysqli->real_escape_string($reason);
        
        $stmt = $mysqli->prepare("INSERT INTO robo_whitelist_blacklist (user_id, symbol, list_type, reason) VALUES (?, ?, 'BLACKLIST', ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
        $stmt->bind_param("iss", $user_id, $symbol_esc, $reason_esc);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => "{$symbol} ditambahkan ke blacklist"
        ]);
        
    } elseif ($action === 'remove_blacklist') {
        // Remove from blacklist
        $symbol = strtoupper($_POST['symbol']);
        $symbol_esc = $mysqli->real_escape_string($symbol);
        
        $mysqli->query("DELETE FROM robo_whitelist_blacklist WHERE user_id={$user_id} AND symbol='{$symbol_esc}' AND list_type='BLACKLIST'");
        
        echo json_encode([
            'status' => 'success',
            'message' => "{$symbol} dihapus dari blacklist"
        ]);
        
    } elseif ($action === 'get_lists') {
        // Get all whitelist and blacklist entries
        $whitelist = $mysqli->query("SELECT symbol, reason FROM robo_whitelist_blacklist WHERE user_id={$user_id} AND list_type='WHITELIST' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
        $blacklist = $mysqli->query("SELECT symbol, reason FROM robo_whitelist_blacklist WHERE user_id={$user_id} AND list_type='BLACKLIST' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'whitelist' => $whitelist,
            'blacklist' => $blacklist
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
