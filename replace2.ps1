$content = Get-Content index.php -Raw

$search5 = '<div><button id="toggleRaw">Toggle raw JSON</button></div>'

$replace5 = '<div style="background:#fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 15px; border-radius: 4px; width: 100%;">
            <h4 style="margin: 0 0 10px 0; color:#856404; font-size:14px;">?? Trading Plan (Estimasi Robo-Advisor)</h4>
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 13px;">
               <div style="color:#17a2b8;"><strong>Area Entry</strong><br>Rp <span id="tp_entry">-</span></div>
               <div style="color:#28a745;"><strong>Take Profit (TP)</strong><br>Rp <span id="tp_tp">-</span></div>
               <div style="color:#dc3545;"><strong>Cutloss (SL)</strong><br>Rp <span id="tp_sl">-</span></div>
            </div>
            <div style="margin-top:5px; font-size:11px; color:#856404;">Risk/Reward Ratio: <span id="tp_rr">-</span>x</div>
          </div>
        </div>
        <div><button id="toggleRaw">Toggle raw JSON</button></div>'

$content = $content.Replace($search5, $replace5)

Set-Content index.php $content
