<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFile = __DIR__ . '/daftar saham.xlsx';
if (!file_exists($inputFile)) {
    die("File Excel tidak ditemukan: $inputFile\n");
}

$spreadsheet = IOFactory::load($inputFile);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();

require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$count = 0;
foreach ($data as $i => $row) {
    // Skip header or empty rows
    if ($i == 0 || empty($row[0])) continue; 
    
    $symbol = strtoupper(trim($row[0]));
    // Ensure the symbol ends with .JK if it's not already
    if (substr($symbol, -3) !== '.JK') {
        $symbol .= '.JK';
    }
    
    $name = trim($row[1] ?? '');
    
    if (!$symbol || !$name) continue;
    
    $stmt = $mysqli->prepare("INSERT IGNORE INTO stocks (symbol, name) VALUES (?, ?)");
    $stmt->bind_param('ss', $symbol, $name);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $count++;
}
echo "Selesai! Berhasil mengupdate $count saham dari file Excel.\n";
