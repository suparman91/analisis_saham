<?php $txt = file_get_contents("tmp.txt"); $start = strpos($txt, "{"); echo substr($txt, $start, 500); ?>
