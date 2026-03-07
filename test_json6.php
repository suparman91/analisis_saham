<?php $txt = file_get_contents("tmp.txt"); $start = strpos($txt, "{"); $json = substr($txt, $start); $d = json_decode($json, true); print_r($d["data"]["BBCA"]); ?>
