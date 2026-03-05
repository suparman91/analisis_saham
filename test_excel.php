<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFile = __DIR__ . '/daftar saham.xlsx';
$spreadsheet = IOFactory::load($inputFile);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();

echo "Total rows in Excel: " . count($data) . "\n";
echo "First 3 rows:\n";
for($i=0; $i<3; $i++) {
    print_r($data[$i]);
}

$foundInet = false;
foreach($data as $i => $row) {
    if (!empty($row[0]) && strpos(strtoupper($row[0]), 'INET') !== false) {
        $foundInet = true;
        echo "Found INET at row $i: " . json_encode($row) . "\n";
    }
}
if (!$foundInet) echo "INET not found in Excel.\n";

