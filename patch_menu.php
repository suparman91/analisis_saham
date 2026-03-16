<?php
$files = ["index.php", "ara_hunter.php", "arb_hunter.php", "chart.php", "scan_manual.php"];
foreach($files as $f) {
  if (!file_exists($f)) continue;
  $c = file_get_contents($f);
  if (strpos($c, "portfolio.php") === false) {
     $c = preg_replace("/<a href=\"arb_hunter.php\"[^>]*>.*?<\/a>/i", "$0\n        <a href=\"portfolio.php\">&#x1F4BC; Autopilot Portofolio</a>", $c);
     file_put_contents($f, $c);
  }
}
echo "Menu patched with Portfolio\n";
?>
