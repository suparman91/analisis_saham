$content = Get-Content index.php -Raw

$search6 = '// indicators
          const latestIdx = data.prices ? data.prices.length -1 : null;'

$replace6 = '// latest AI data
          document.getElementById(''global_sentiment'').innerText = data.global_sentiment || ''-'';
          document.getElementById(''global_sentiment_details'').innerText = data.global_sentiment_details ? `(${data.global_sentiment_details})` : '''';
          const sentEl = document.getElementById(''global_sentiment'');
          sentEl.style.color = (data.global_sentiment === ''BULLISH'') ? ''green'' : ((data.global_sentiment === ''BEARISH'') ? ''red'' : ''inherit'');
          
          if (data.trading_plan) {
            document.getElementById(''tp_entry'').innerText = Number(data.trading_plan.entry).toLocaleString();
            document.getElementById(''tp_tp'').innerText = Number(data.trading_plan.take_profit).toLocaleString();
            document.getElementById(''tp_sl'').innerText = Number(data.trading_plan.cut_loss).toLocaleString();
            document.getElementById(''tp_rr'').innerText = data.trading_plan.reward_risk;
          }

          // indicators
          const latestIdx = data.prices ? data.prices.length -1 : null;'

$content = $content.Replace($search6, $replace6)

Set-Content index.php $content
