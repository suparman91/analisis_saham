<?php $j = json_decode("{\"BBCA.JK\":{\"symbol\":\"BBCA.JK\",\"close\":[6875.0]}}", true); foreach($j as $s => $d){ if(isset($d["close"][0])){ echo $s . " => " . $d["close"][0] . "\n"; } } ?>
