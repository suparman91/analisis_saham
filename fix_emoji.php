<?php
$file = "ara_hunter.php";
$content = file_get_contents($file);

// Replace just the broken emojis without breaking PHP code
$replacements = [
    "?? Dashboard Market" => "?? Dashboard Market",
    "?? Chart & Analisis" => "?? Chart & Analisis",
    "?? Scanner BSJP/BPJP" => "?? Scanner BSJP/BPJP",
    "?? AI Stockpick Tracker" => "?? AI Stockpick Tracker",
    "?? ARA Hunter" => "?? ARA Hunter",
    "?? ARA Hunter & Kalkulator Fraksi" => "?? ARA Hunter & Kalkulator Fraksi",
    "?? Kalkulator ARA / ARB Manual" => "?? Kalkulator ARA / ARB Manual",
    "? Screener Live" => "?? Screener Live",
    "?? KUNCI ARA" => "?? KUNCI ARA",
    "?? MENUJU ARA" => "?? MENUJU ARA",
    "?? Panduan & Keterangan Lengkap:" => "?? Panduan & Keterangan Lengkap:",
    "Kunci ARA (??):" => "Kunci ARA (??):",
    "Mengincar ARA (??):" => "Mengincar ARA (??):",
    "Potensi Besok (?):" => "Potensi Besok (??):",
    "HIT ARA?:" => "HIT ARA??:",
    "?? Trading Plan (Saran AI)" => "?? Trading Plan (Saran AI)",
    "?? Buka Chart & Analisis Sengkapnya" => "?? Buka Chart & Analisis Sengkapnya",
    "? YES" => "?? YES",
    "? NO" => "? NO",
    "Set Alert ?" => "?? Set Alert",
    "??" => "??" // do not replace double question marks out of context
];

foreach ($replacements as $old => $new) {
    if ($old !== "??") {
        $content = str_replace($old, $new, $content);
    }
}

file_put_contents($file, $content);
echo "Emojis fixed.\n";

