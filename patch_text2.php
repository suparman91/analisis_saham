<?php
$c = file_get_contents("arb_hunter.php");
$c = str_replace("harga naik solid didukung volume", "harga turun solid didukung volume jual", $c);
$c = str_replace("masuknya <em>big money / institusi</em>.", "keluarnya atau distribusi oleh <em>big money / institusi</em>.", $c);
$c = str_replace("harga titik tertinggi harian. Sentimen <em>buyer</em> kuat tanpa tekanan jual jelang <em>closing</em> pasar.", "harga titik terendah harian. Sentimen <em>seller (jual)</em> kuat tanpa tekanan beli jelang <em>closing</em> pasar.", $c);
$c = str_replace("membuktikan saham masih murah (PE/PBV rendah).", "mengindikasikan saham sedang mahal atau overvalued.", $c);
$c = str_replace("mendeteksi tren akumulasi sedang berlangsung (Golden Cross).", "mendeteksi tren distribusi atau downtrend sedang berlangsung (Death Cross).", $c);
$c = str_replace("sudah berbalik dari titik jenuh jual.", "sudah mencapai atau turun dari titik jenuh beli (terlalu mahal).", $c);
$c = str_replace("Valuasi (Undervalued/Fair)", "Valuasi (Overvalued/Fair)", $c);
file_put_contents("arb_hunter.php", $c);
echo "Replaced text for ARB manual.\n";
?>
