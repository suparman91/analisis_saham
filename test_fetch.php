<?php $time_start = microtime(true); $_GET['symbols'] = 'BBCA.JK,BBRI.JK,BBNI.JK,BMRI.JK,ASII.JK,TLKM.JK,GOTO.JK'; require 'fetch_realtime.php'; echo '\nTime: ' . (microtime(true) - $time_start); ?>
