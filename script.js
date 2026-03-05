
    let chart;
    let liveUpdateInterval;

    function updateLivePrice(symbol) {
      const finnhubKey = localStorage.getItem('finnhub_key') || '';
      fetch('fetch_realtime.php?symbols='+encodeURIComponent(symbol)+'&finnhub_key='+encodeURIComponent(finnhubKey))
        .then(r=>r.json())
        .then(rt=>{
          if (rt && rt.data && rt.data[symbol] && rt.data[symbol].price && !isNaN(rt.data[symbol].price)) {
            const p = rt.data[symbol];
            document.getElementById('latestPrice').innerText = Number(p.price).toLocaleString();
            
            if (p.time) {
              const dt = new Date(p.time * 1000);
              document.getElementById('latestDate').innerText = dt.toLocaleString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
            } else if (p.raw && p.raw.t) {
              const dt = new Date(p.raw.t * 1000);
              document.getElementById('latestDate').innerText = dt.toLocaleString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
            
            const srcEl2 = document.getElementById('latestSource');
            srcEl2.innerText = 'live (auto)'; srcEl2.className = 'badge buy';

            if (chart && chart.data && chart.data.datasets && chart.data.datasets[0] && chart.data.datasets[0].data) {
                const dataArr = chart.data.datasets[0].data;
                if (dataArr.length > 0) {
                    const lastCandle = dataArr[dataArr.length - 1];
                    lastCandle.c = p.price;
                    if (p.price > lastCandle.h) lastCandle.h = p.price;
                    if (p.price < lastCandle.l) lastCandle.l = p.price;
                    chart.update('none');
                }
            }
          }
        }).catch(e=>{
          console.warn('Realtime fetch failed', e);
        });
    }

    function waitForChartReady(timeout = 5000) {
      return new Promise((resolve, reject) => {
        const start = Date.now();
        (function check() {
          if (typeof Chart !== 'undefined' && Chart.registry && Chart.registry.getController('candlestick')) return resolve();
          // try manual registration from known globals
          const p = window.ChartFinancial || window.chartjsChartFinancial || window['chartjs-chart-financial'] || window.ChartjsChartFinancial || window.ChartjsFinancial || window['ChartjsChartFinancial'];
          if (p) {
            try {
              const cand = p.CandlestickController || (p.default && p.default.CandlestickController);
              const candEl = p.CandlestickElement || (p.default && p.default.CandlestickElement);
              const ohlc = p.OhlcController || (p.default && p.default.OhlcController);
              const ohlcEl = p.OhlcElement || (p.default && p.default.OhlcElement);
              if (cand) Chart.register(cand);
              if (candEl) Chart.register(candEl);
              if (ohlc) Chart.register(ohlc);
              if (ohlcEl) Chart.register(ohlcEl);
            } catch (e) { /* ignore */ }
            if (Chart.registry && Chart.registry.getController('candlestick')) return resolve();
          }
          if (Date.now() - start > timeout) return reject(new Error('Chart financial plugin not available'));
          setTimeout(check, 100);
        })();
      });
    }
    async function render(symbol){
      if(liveUpdateInterval) clearInterval(liveUpdateInterval);
      await waitForChartReady().catch(e=>{ console.warn(e); /* continue, will error later if plugin missing */ });

      const newsContainer = document.getElementById('news-list');
      if (newsContainer) {
          newsContainer.innerHTML = '<p style="color:#666; font-style:italic;">Memuat berita...</p>';
          fetch('fetch_news.php?symbol='+encodeURIComponent(symbol)).then(r=>r.json()).then(data=>{
              if(data.error || !data.news || data.news.length === 0) {
                  newsContainer.innerHTML = '<p style="color:#888; font-style:italic;">Tidak ada berita terbaru.</p>';
                  return;
              }
              let html = '';
              data.news.forEach(item => {
                  const dateRaw = new Date(item.pubDate);
                  const dateStr = !isNaN(dateRaw) ? dateRaw.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : item.pubDate;
                  html += `<div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                          <a href="${item.link}" target="_blank" style="color:#0d6efd; text-decoration:none; font-weight:bold; display:block; margin-bottom:4px;">${item.title}</a>
                          <div style="font-size:11px; color:#888; margin-bottom:4px;">${dateStr}</div>
                          <div style="font-size:12px; color:#555; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">${item.description}</div>
                      </div>`;
              });
              newsContainer.innerHTML = html;
          }).catch(e=>{
              newsContainer.innerHTML = '<p style="color:red; font-style:italic;">Gagal memuat berita.</p>';
          });
      }

      // Load analysis (historical prices) first to render chart
      fetch('analyze_api.php?symbol='+encodeURIComponent(symbol)).then(async r=>{
        if (!r.ok) {
          const txt = await r.text();
          throw new Error('HTTP '+r.status+': '+txt);
        }
        return r.json();
      }).then(data=>{
        if (data.error){ alert(data.error); return; }
        // populate UI fields
        const sigEl = document.getElementById('signal');
        document.getElementById('fund').innerText = (data.fund_score !== undefined && data.fund_score !== null) ? Number(data.fund_score).toFixed(2) : 'N/A';
        document.getElementById('fund_status').innerText = data.fund_status || 'N/A';
        document.getElementById('signal_details').innerText = data.signal_details ? `(${data.signal_details})` : '';
        document.getElementById('raw').innerText = JSON.stringify(data, null, 2);
        
        // Trading plan & Sentiment
        if (data.trading_plan) {
            document.getElementById('tp_entry').innerText = Number(data.trading_plan.entry).toLocaleString();
            document.getElementById('tp_tp').innerText = Number(data.trading_plan.take_profit).toLocaleString();
            document.getElementById('tp_sl').innerText = Number(data.trading_plan.cut_loss).toLocaleString();
        }
        if (data.global_sentiment) {
            const gs = document.getElementById('global_sentiment');
            gs.innerText = data.global_sentiment;
            gs.className = 'badge ' + (data.global_sentiment==='BULLISH'?'buy':data.global_sentiment==='BEARISH'?'sell':'hold');
            if(data.global_sentiment_details) gs.title = data.global_sentiment_details;
        }

        // signal badge
        const signalText = (data.signal || '').toUpperCase();
        sigEl.innerText = signalText;
        sigEl.className = 'badge ' + (signalText.indexOf('BUY')!==-1 ? 'buy' : signalText.indexOf('SELL')!==-1 ? 'sell' : 'hold');
        // latest price/date (from DB by default)
        document.getElementById('latestPrice').innerText = data.latest ? Number(data.latest.close).toLocaleString() : '-';
        document.getElementById('latestDate').innerText = data.latest ? data.latest.date : '-';
        // default source is cached (DB)
        const srcEl = document.getElementById('latestSource');
        srcEl.innerText = 'cache'; srcEl.className = 'badge hold';
        // indicators
        const latestIdx = data.prices ? data.prices.length -1 : null;
        document.getElementById('rsi').innerText = (data.rsi && data.rsi[latestIdx]!==undefined && data.rsi[latestIdx]!==null) ? Number(data.rsi[latestIdx]).toFixed(2) : '-';
        document.getElementById('macd').innerText = (data.macd && data.macd.hist && data.macd.hist[latestIdx]!==undefined) ? Number(data.macd.hist[latestIdx]).toFixed(3) : '-';

        // Ensure candlestick controller is registered (try common plugin globals)
        if (!Chart.registry.getController('candlestick')) {
          const pluginCandidates = [window.ChartjsFinancial, window.ChartFinancial, window.chartjsChartFinancial, window['chartjs-chart-financial'], window.ChartjsChartFinancial];
          for (const p of pluginCandidates) {
            if (!p) continue;
            const cand = p.CandlestickController || (p.default && p.default.CandlestickController);
            const candEl = p.CandlestickElement || (p.default && p.default.CandlestickElement);
            const ohlc = p.OhlcController || (p.default && p.default.OhlcController);
            const ohlcEl = p.OhlcElement || (p.default && p.default.OhlcElement);
            try {
              if (cand) Chart.register(cand);
              if (candEl) Chart.register(candEl);
              if (ohlc) Chart.register(ohlc);
              if (ohlcEl) Chart.register(ohlcEl);
            } catch (e) {
              // ignore registration errors
            }
          }
        }

        // Convert dates to Date objects for reliable parsing by adapter
        const ohlc = data.prices.map(p=>({x: new Date(p.date).valueOf(), o: p.open, h: p.high, l: p.low, c: p.close}));
        const sma5 = (data.sma5 || []).map(v=>v===null?null:v);
        const sma20 = (data.sma20 || []).map(v=>v===null?null:v);
        const bbUpper = (data.bollinger && data.bollinger.upper) ? data.bollinger.upper : [];
        const bbLower = (data.bollinger && data.bollinger.lower) ? data.bollinger.lower : [];

        const ctx = document.getElementById('chart').getContext('2d');
        const existingChart = Chart.getChart(ctx);
        if (existingChart) existingChart.destroy();
        chart = new Chart(ctx, {
          type: 'candlestick',
          data: {
            datasets: [
              { label: symbol+' OHLC', data: ohlc, color: { up: '#0f0', down: '#f00', unchanged: '#999' } },
              { label: 'SMA 5', type: 'line', data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: sma5[i]})), borderColor: 'blue', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'SMA 20', type: 'line', data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: sma20[i]})), borderColor: 'orange', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'BB Upper', type: 'line', data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: bbUpper[i]})), borderColor: 'rgba(0,0,0,0.2)', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'BB Lower', type: 'line', data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: bbLower[i]})), borderColor: 'rgba(0,0,0,0.2)', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
              legend: { display:true },
              zoom: {
                pan: {
                  enabled: true,
                  mode: 'x', // Enable panning horizontally
                },
                zoom: {
                  wheel: {
                    enabled: true, // Enable zooming via mouse wheel
                  },
                  pinch: {
                    enabled: true // Enable zooming via pinch (touch)
                  },
                  mode: 'x', // Enable zooming horizontally
                }
              }
            },
            scales: { x: { type: 'time', time: { unit: 'day' } }, y: { position: 'right' } }
          }
        });
        updateLivePrice(symbol);
          liveUpdateInterval = setInterval(() => updateLivePrice(symbol), 60000);
      }).catch(e=>{ console.error(e); alert('Error loading data: '+e.message) });
    }

    document.getElementById('btnLoad').addEventListener('click', ()=>{
      const sym = document.getElementById('symbol').value;
      render(sym);
    });

    // Modal interactions
    document.getElementById('btnSettings').addEventListener('click', ()=>{
      document.getElementById('settingsModal').style.display = 'block';
    });

    // Reset Zoom Button
    document.getElementById('btnResetZoom').addEventListener('click', ()=>{
      if (chart) {
        chart.resetZoom();
      }
    });
    
    document.getElementById('closeSettings').addEventListener('click', ()=>{
      document.getElementById('settingsModal').style.display = 'none';
    });

    document.getElementById('saveSettings').addEventListener('click', ()=>{
      const v = document.getElementById('finnhub_key').value.trim();
      if (v==='') {
        localStorage.removeItem('finnhub_key');
        alert('Configuration saved (Finnhub disabled).');
      } else {
        localStorage.setItem('finnhub_key', v);
        alert('Finnhub key saved successfully!');
      }
      document.getElementById('settingsModal').style.display = 'none';
      if (document.getElementById('symbol').value) render(document.getElementById('symbol').value);
    });

    document.getElementById('toggleRaw').addEventListener('click', ()=>{
      const rawEl = document.getElementById('raw');
      if (rawEl.style.display === 'none') {
        rawEl.style.display = 'block';
      } else {
        rawEl.style.display = 'none';
      }
    });

    // populate finnhub input from localStorage
    (function(){
      try { const k = localStorage.getItem('finnhub_key'); if (k) document.getElementById('finnhub_key').value = k; } catch(e){}
    })();

    // auto-load from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    const symFromUrl = urlParams.get('symbol');
    if (symFromUrl) {
      const selectEl = document.getElementById('symbol');
      selectEl.value = symFromUrl;
      if(selectEl.value === symFromUrl) {
        render(symFromUrl);
      } else {
        // If symbol not in dropdown, add it temporarily and render
        const newOption = document.createElement('option');
        newOption.value = symFromUrl;
        newOption.text = symFromUrl;
        selectEl.appendChild(newOption);
        selectEl.value = symFromUrl;
        render(symFromUrl);
      }
    } else if (document.getElementById('symbol').value) {
      render(document.getElementById('symbol').value);
    }
  