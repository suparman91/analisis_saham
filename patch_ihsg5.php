<?php
$content = file_get_contents('ihsg.php');
$content = preg_replace("/\/\/ auto-load from URL if present[\s\S]*?\}\); \/\/ 300ms buffer to ensure all core plugin states are registered\s*\}\);/", "
      window.addEventListener('DOMContentLoaded', () => {
          setTimeout(() => {
              render('^JKSE');
          }, 300);
      });", $content);

file_put_contents("ihsg.php", $content);
echo "Ok";
?>
