<?php $d = json_decode(file_get_contents("tmp.txt"), true); echo "\nSOURCE IS: " . ($d["data"]["BBCA"]["source"] ?? "NOT FOUND") . "\n"; ?>
