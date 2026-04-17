<?php 
$files = glob("*.php"); 
foreach ($files as $f) { 
    if ($f == "nav.php" || $f == "apply_nav.php") continue; 
    $c = file_get_contents($f); 
    if (strpos($c, "class=\"top-menu\"") !== false) { 
        $c = preg_replace("/<nav class=\"top-menu\">.*?<\/nav>/s", "<?php include 'nav.php'; ?>", $c); 
        file_put_contents($f, $c); 
        echo "Patched $f\n"; 
    } 
} 
echo "Done\n"; ?>
