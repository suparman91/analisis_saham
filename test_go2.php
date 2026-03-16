<?php $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, "https://goapi.id/api/stock/v1/idx"); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); echo substr(curl_exec($ch), 0, 500);
