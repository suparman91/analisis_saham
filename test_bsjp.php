<?php
$ch = curl_init('http://localhost/analisis_saham/scan_bpjs_bsjp.php?tipe=BSJP');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
echo curl_exec($ch);
