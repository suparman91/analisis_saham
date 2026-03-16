<?php
$c = file_get_contents("arb_hunter.php");
$c = str_replace("Saham mengunci ARA, mengincar ARA, atau potensi naik", "Saham mengunci ARB, mengincar ARB, atau potensi turun", $c);
$c = str_replace("berpotensi tinggi ARA saat ini.", "berpotensi tinggi ARB saat ini.", $c);
$c = str_replace("<th>Batas ARA</th>", "<th>Batas ARB</th>", $c);
$c = str_replace("<th>HIT ARA?</th>", "<th>HIT ARB?</th>", $c);
$c = str_replace("MENUJU ARA", "MENUJU ARB", $c);
$c = str_replace("Kunci ARA", "Kunci ARB", $c);
$c = str_replace("Mengincar ARA", "Mengincar ARB", $c);
$c = str_replace("Batas ARA", "Batas ARB", $c);
$c = str_replace("tidak ARA hari ini", "tidak ARB hari ini", $c);
$c = str_replace("HIT ARA", "HIT ARB", $c);
$c = str_replace("kenaikan maksimal", "penurunan maksimal", $c);
$c = str_replace("antrean beli mengunci", "antrean jual (offer) mengunci penuh", $c);
$c = str_replace("gap up", "gap down", $c);
$c = str_replace("Harga naik", "Harga turun", $c);
$c = preg_replace("/POTENSI BESOK<\/\w+>/i", "POTENSI TURUN</span>", $c);
$c = str_replace("pola akumulasi", "pola distribusi", $c);
$c = str_replace("apakah harga <em>High</em> (Tertinggi)", "apakah harga <em>Low</em> (Terendah)", $c);
$c = str_replace("harga turun ke level lebih rendah", "harga memantul ke level lebih tinggi", $c);
file_put_contents("arb_hunter.php", $c);
echo "Replaced text for ARB.\n";
?>
