<?php
require_once "db.php";
require_once "telegram_crypto.php";
require_once "telegram_setting.php";

$botSettings = getTelegramSettings($mysqli);

if (!$botSettings["bot_token"] || empty($botSettings["users"])) {
    die("Telegram not configured.\n");
}

function sendTelegramMessage($token, $chatId, $message) {
    if (!$token || !$chatId) return false;
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        "chat_id" => $chatId,
        "text" => $message,
        "parse_mode" => "HTML"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$stmt = $mysqli->prepare("SELECT id, symbol, buy_price, total_lot FROM portfolio WHERE last_notified IS NULL OR last_notified < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute();
$result = $stmt->get_result();

$alerts = [];
while ($row = $result->fetch_assoc()) {
    $symbol = $row["symbol"];
    $buyPrice = (float)$row["buy_price"];
    $totalLot = (int)$row["total_lot"];
    
    // fetch live price logic
    $dateQuery = $mysqli->query("SELECT MAX(date) as max_date FROM stocks");
    $maxDateRow = $dateQuery->fetch_assoc();
    $maxDate = $maxDateRow["max_date"];
    
    $q2 = $mysqli->prepare("SELECT close FROM stocks WHERE symbol=? AND date=?");
    $q2->bind_param("ss", $symbol, $maxDate);
    $q2->execute();
    $r2 = $q2->get_result();
    $livePrice = $buyPrice;
    if ($row2 = $r2->fetch_assoc()) {
        $livePrice = (float)$row2["close"];
    }
    
    $profit_pct = ($buyPrice > 0) ? (($livePrice - $buyPrice) / $buyPrice) * 100 : 0;
    
    $action = "HOLD ⏳";
    $reason = "Pergerakan harga wajar.";
    if ($profit_pct >= 5) {
        $action = "TAKE PROFIT 💰";
        $reason = "Target profit >= 5% tercapai. Amankan keuntungan.";
        $alerts[] = [ "id" => $row["id"], "symbol" => $symbol, "action" => $action, "reason" => $reason, "pct" => $profit_pct ];
    } elseif ($profit_pct <= -5) {
        $action = "CUT LOSS 🛑";
        $reason = "Batas kerugian >= 5% tersentuh. Segera batasi risiko.";
        $alerts[] = [ "id" => $row["id"], "symbol" => $symbol, "action" => $action, "reason" => $reason, "pct" => $profit_pct ];
    } elseif ($profit_pct <= -3 && $profit_pct > -5) {
        $action = "AVERAGE DOWN 📉";
        $reason = "Harga turun -3% s/d -5%. Area serok bawah jika tren utuh.";
        $alerts[] = [ "id" => $row["id"], "symbol" => $symbol, "action" => $action, "reason" => $reason, "pct" => $profit_pct ];
    }
}

if (count($alerts) > 0) {
    $message = "🤖 <b>AUTOPILOT PORTFOLIO ALERT</b> 🤖\n\n";
    $idsToUpdate = [];
    foreach ($alerts as $a) {
        $message .= "<b>" . $a["symbol"] . "</b> (" . number_format($a["pct"], 2) . "%)\n";
        $message .= "Tindakan: <b>" . $a["action"] . "</b>\n";
        $message .= "Alasan: " . $a["reason"] . "\n\n";
        $idsToUpdate[] = $a["id"];
    }
    
    foreach ($botSettings["users"] as $u) {
        sendTelegramMessage($botSettings["bot_token"], $u["chat_id"], $message);
    }
    
    // Update last_notified
    if (count($idsToUpdate) > 0) {
        $in = str_repeat("?,", count($idsToUpdate) - 1) . "?";
        $types = str_repeat("i", count($idsToUpdate));
        $updateStmt = $mysqli->prepare("UPDATE portfolio SET last_notified = NOW() WHERE id IN ($in)");
        $updateStmt->bind_param($types, ...$idsToUpdate);
        $updateStmt->execute();
    }
    echo "Alerts sent.\n";
} else {
    echo "No actionable alerts needed right now.\n";
}
?>
