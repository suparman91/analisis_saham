<?php
// Simple CSV importer for prices and fundamentals
// Usage (CLI or browser): fetch_data.php?type=prices&symbol=BBCA&file=prices_bbca.csv
require_once __DIR__ . '/db.php';

$type = $_GET['type'] ?? '';
$symbol = $_GET['symbol'] ?? '';
$file = $_GET['file'] ?? '';

if (!$type || !$symbol || !$file) {
    echo "Usage: fetch_data.php?type=prices|fundamentals&symbol=SYMB&file=path.csv\n";
    exit;
}

$mysqli = db_connect();

if ($type === 'prices') {
    $stmt = $mysqli->prepare("INSERT INTO prices (symbol,date,open,high,low,close,volume) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE open=IF(open > 0, open, VALUES(open)), high=IF(VALUES(high) > high, VALUES(high), high), low=IF(VALUES(low) > 0 AND VALUES(low) < low, VALUES(low), low), close=VALUES(close), volume=IF(VALUES(volume) > volume, VALUES(volume), volume)");
    if (($handle = fopen($file, 'r')) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            // expect date,open,high,low,close,volume
            if ($row === 0) { $row++; continue; }
            [$date,$open,$high,$low,$close,$volume] = $data;
            $stmt->bind_param('ssddddd', $symbol, $date, $open, $high, $low, $close, $volume);
            $stmt->execute();
        }
        fclose($handle);
        echo "Imported prices for $symbol\n";
    } else {
        echo "Cannot open file: $file\n";
    }
} elseif ($type === 'fundamentals') {
    $stmt = $mysqli->prepare("INSERT INTO fundamentals (symbol,date,pe,pbv,roe,eps) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE pe=VALUES(pe), pbv=VALUES(pbv), roe=VALUES(roe), eps=VALUES(eps)");
    if (($handle = fopen($file, 'r')) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            // expect date,pe,pbv,roe,eps
            if ($row === 0) { $row++; continue; }
            [$date,$pe,$pbv,$roe,$eps] = $data;
            $stmt->bind_param('ssdddd', $symbol, $date, $pe, $pbv, $roe, $eps);
            $stmt->execute();
        }
        fclose($handle);
        echo "Imported fundamentals for $symbol\n";
    } else {
        echo "Cannot open file: $file\n";
    }
} else {
    echo "Unknown type\n";
}

?>
