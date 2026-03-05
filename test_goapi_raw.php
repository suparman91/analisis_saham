<?php $symbols="none"; $_GET["symbols"]="none"; require "fetch_realtime.php"; echo "\nRESULT_GOAPI:\n" . json_encode(fetch_goapi_quote("BBCA.JK", "f29dc087-640b-5b81-df7c-aee75f5e"));
