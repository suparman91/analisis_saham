<?php $_GET["symbols"]="WMUU"; ob_start(); require "fetch_realtime.php"; $out = ob_get_clean(); echo substr($out, strpos($out, "{")); ?>
