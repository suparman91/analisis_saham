<?php
$content = file_get_contents('ihsg.php');
$lines = explode("\n", $content);
$filtered = [];
$skip = false;
foreach ($lines as $line) {
    if (strpos($line, '// auto-load from URL if present') !== false) {
        $skip = true;
    }
    if (!$skip) {
        $filtered[] = $line;
    }
    if ($skip && strpos($line, '300ms buffer') !== false) {
        // Skip this one and the next one
    }
    if ($skip && trim($line) == '});') {
        // We found end of block, skip this and inject replacement
        $skip = false;
        $filtered[] = "      window.addEventListener('DOMContentLoaded', () => { setTimeout(() => { render('^JKSE'); }, 300); });";
    }
}
file_put_contents('ihsg.php', implode("\n", $filtered));
echo "Force replaced.\n";
?>
