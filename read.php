<?php $j = json_decode(file_get_contents("tmp_clean.json"), true); unset($j["data"]["WMUU"]["debug"]); echo json_encode($j["data"]["WMUU"]); ?>
