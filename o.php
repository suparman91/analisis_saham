<?php $_GET["force"] = "1"; ob_start(); require "ajax_update.php"; $o = ob_get_clean(); echo substr($o, -500); ?>
