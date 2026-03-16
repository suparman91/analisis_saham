<?php
$f = "stockpick.php";
$c = file_get_contents($f);

// 1. Add DOMContentLoaded block
$c = str_replace(
    'function runAutoScan() {', 
    'document.addEventListener("DOMContentLoaded", function() { const savedScan = localStorage.getItem("lastAiScanResult"); const savedTime = localStorage.getItem("lastAiScanTime"); if (savedScan) { const resDiv = document.getElementById("aiScanResults"); if (resDiv) resDiv.innerHTML = savedScan; const btn = document.getElementById("btnAutoScan"); if (btn && savedTime) btn.innerHTML = "🔄 Scan Ulang (" + savedTime + "s)"; }});'."\n\n".'    function runAutoScan() {',
    $c
);

// 2. Add localStorage save logic
$search = "resDiv.innerHTML = \"<div style='margin-bottom:15px; text-align:center; font-size:12px; color:#555;'>✅ Scan selesai dalam <strong>\" + totalTime + \" detik</strong>.</div><div style='margin-top:15px;'>\" + html + \"</div>\";";
$replace = "const finalHtml = \"<div style='margin-bottom:15px; text-align:center; font-size:12px; color:#555;'>✅ Scan selesai dalam <strong>\" + totalTime + \" detik</strong>.</div><div style='margin-top:15px;'>\" + html + \"</div>\"; resDiv.innerHTML = finalHtml; localStorage.setItem('lastAiScanResult', finalHtml); localStorage.setItem('lastAiScanTime', totalTime);";

$c = str_replace($search, $replace, $c);

// Also need to correct any earlier patch logic
$c = str_replace(
    '$sym = strtoupper(trim($_POST["symbol"]));',
    '$sym = strtoupper(trim($_POST["symbol"]));'."\n".'        // Normalize agar tidak tersimpan sebagai BBCA.JK lalu menjadi BBCA.JK.JK saat fetch'."\n".'        $sym = preg_replace(\'/\.JK$/i\', \'\', $sym);',
    $c
);

$search_insert = '        $stmt = $mysqli->prepare("INSERT INTO ai_stockpicks (symbol, pick_date, entry_price, target_price, stop_loss, notes) VALUES (?, NOW(), ?, ?, ?, ?)");
        $stmt->bind_param("sddds", $sym, $entry, $tp, $sl, $notes);
        $stmt->execute();';

$replace_insert = '        // Jika simbol sudah ada di tracker, update record terbaru supaya tidak duplikat
        $checkStmt = $mysqli->prepare("SELECT id FROM ai_stockpicks WHERE symbol = ? ORDER BY pick_date DESC, id DESC LIMIT 1");
        $checkStmt->bind_param("s", $sym);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $updateStmt = $mysqli->prepare("UPDATE ai_stockpicks SET pick_date = NOW(), entry_price = ?, target_price = ?, stop_loss = ?, notes = ?, status = \'PENDING\' WHERE id = ?");
            $updateStmt->bind_param("dddsi", $entry, $tp, $sl, $notes, $existing[\'id\']);
            $updateStmt->execute();
            $updateStmt->close();

            // Bersihkan duplikat lama untuk simbol yang sama, sisakan 1 record terbaru
            $cleanupStmt = $mysqli->prepare("DELETE FROM ai_stockpicks WHERE symbol = ? AND id <> ?");
            $cleanupStmt->bind_param("si", $sym, $existing[\'id\']);
            $cleanupStmt->execute();
            $cleanupStmt->close();
        } else {
            $stmt = $mysqli->prepare("INSERT INTO ai_stockpicks (symbol, pick_date, entry_price, target_price, stop_loss, notes) VALUES (?, NOW(), ?, ?, ?, ?)");
            $stmt->bind_param("sddds", $sym, $entry, $tp, $sl, $notes);
            $stmt->execute();
            $stmt->close();
        }';

$c = str_replace($search_insert, $replace_insert, $c);

file_put_contents($f, $c);
echo "Done replacing.";
?>