<?php $d = json_decode(file_get_contents("tmp.txt"), true); print_r($d["error"] ?? array_keys($d["data"] ?? [])); ?>
