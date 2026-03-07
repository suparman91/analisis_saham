
$c = Get-Content scan_ai.php -Raw
$c = $c -replace "<div class='card mb-3 border-success'>", "<div style='border:1px solid #28a745; border-radius:5px; margin-bottom:15px; overflow:hidden;'>"
$c = $c -replace "<div class='card-header bg-success text-white py-1'>", "<div style='background:#28a745; color:white; padding:8px 12px; display:flex; justify-content:space-between;'>"
$c = $c -replace "<strong>\$sym</strong> <span class='badge badge-light float-right'>Score: {\$rec\['score']}</span>", "<strong>\$sym</strong> <span style='background:white; color:#28a745; padding:2px 6px; border-radius:10px; font-size:11px;'>Score: {\$rec['score']}</span>"
$c = $c -replace "<div class='card-body py-2'>", "<div style='padding:12px; background:#f9fff9;'>"
$c = $c -replace "<div class='row'>", "<div style='display:flex; justify-content:space-between;'>"
$c = $c -replace "<div class='col-6'>", "<div style='flex:1;'>"
$c = $c -replace "<div class='col-6 text-right'>", "<div style='flex:1; text-align:right;'>"
$c = $c -replace "<div class='card-footer py-1'>", "<div style='padding:8px; background:#e2fbe6; border-top:1px solid #c3e6cb;'>"
$c = $c -replace "<button type='submit' class='btn btn-sm btn-success btn-block'>", "<button type='submit' style='width:100%; padding:8px; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;'>"
$c | Set-Content scan_ai.php

