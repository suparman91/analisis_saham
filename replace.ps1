$content = Get-Content index.php -Raw

$search1 = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">'
$replace1 = '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;flex-direction:row;">'

$search2 = '<div>
          <div style="margin-bottom:8px">
            <strong>Technical Signal:</strong> <span id="signal"'
            
$replace2 = '<div style="flex:1">
          <div style="margin-bottom:8px">
            <strong>Technical Signal:</strong> <span id="signal"'

$search3 = '<strong>Fundamental:</strong> <span id="fund_status"'

$replace3 = '<strong>Global Sentiment:</strong> <span id="global_sentiment" style="font-weight:bold;">-</span>
            <small id="global_sentiment_details" style="color:#666; margin-left:8px;"></small>
          </div>
          <div style="margin-bottom:8px">
            <strong>Fundamental:</strong> <span id="fund_status"'

$search4 = '<small>(Score: <span id="fund">-</span>)</small>
          </div>'
          
$replace4 = '<small>(Score: <span id="fund">-</span>)</small>
          </div>
          
          <div style="background:#fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 15px; border-radius: 4px;">
            <h4 style="margin: 0 0 10px 0; color:#856404; font-size:14px;">?? Trading Plan (Estimasi Robo-Advisor)</h4>
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 13px;">
               <div style="color:#17a2b8;"><strong>Area Entry</strong><br>Rp <span id="tp_entry">-</span></div>
               <div style="color:#28a745;"><strong>Take Profit (TP)</strong><br>Rp <span id="tp_tp">-</span></div>
               <div style="color:#dc3545;"><strong>Cutloss (SL)</strong><br>Rp <span id="tp_sl">-</span></div>
            </div>
            <div style="margin-top:5px; font-size:11px; color:#856404;">Risk/Reward Ratio: <span id="tp_rr">-</span>x</div>
          </div>'

$content = $content.Replace($search1, $replace1)
$content = $content.Replace($search2, $replace2)
$content = $content.Replace($search3, $replace3)
$content = $content.Replace($search4, $replace4)

Set-Content index.php $content
