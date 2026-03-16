<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();
$stocks = [];
  $res = $mysqli->query('SELECT symbol,name,notation FROM stocks ORDER BY symbol');
while ($r = $res->fetch_assoc()) $stocks[] = $r;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Analisis Saham IHSG</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:20px}
    #chart-container{width:900px;height:500px}
    .info{margin-top:10px}
    #raw{max-height:200px;overflow:auto}
    .badge{display:inline-block;padding:6px 10px;border-radius:6px;color:#fff;font-weight:700}
    .badge.buy{background:#198754}
    .badge.sell{background:#dc3545}
    .badge.hold{background:#6c757d}
    .panel{margin-top:12px;padding:10px;background:#fafafa;border:1px solid #eee;border-radius:6px}
    .indicators{display:flex;gap:16px;align-items:flex-start}
    .ind-col{min-width:160px}
    /* Modal Styles */
    .modal-backdrop {display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999;}
    .modal {position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);width:300px;}
    .modal h3 {margin-top:0;}
    .modal input {width:100%;box-sizing:border-box;margin:10px 0;padding:8px;}
    .modal-actions {text-align:right;}
    /* Navigation Menu */
    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; white-space: nowrap; }
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }
    .top-menu-right { margin-left: auto; }
    @media(max-width:768px) {
        .top-menu { flex-direction: column; align-items: stretch; text-align: center; }
        .top-menu-right { margin-left: 0; margin-top: 10px; }
        #chart-container { min-width: 100% !important; height: 350px !important; }
        #news-panel { width: 100% !important; max-height: none !important; }
        .controls-container { flex-direction: column; width: 100%; align-items: stretch !important; }
        .controls-container select, .controls-container .ts-control { width: 100% !important; }
    }
    .btn-settings { background: #475569; border:none; cursor:pointer; color: #fff; padding: 8px 15px; border-radius: 5px; font-weight: 600; font-size: 14px; transition: background 0.2s; width:100%; }
    .btn-settings:hover { background: #64748b; }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-financial@0.1.1/dist/chartjs-chart-financial.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.0/dist/chartjs-plugin-zoom.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
</head>
<body>

  <nav class="top-menu">
    <a href="index.php">📊 Dashboard Market</a>
    <a href="chart.php" class="active">📈 Chart & Analisis</a>
    <a href="scan_manual.php">🔍 Scanner BSJP/BPJP</a>
    <a href="stockpick.php">🎯 AI Stockpick Tracker</a>
    <a href="ara_hunter.php">🚀 ARA Hunter</a>
          <a href="arb_hunter.php">&#x1F4C9; ARB Hunter</a>
        <a href="portfolio.php">&#x1F4BC; Autopilot Portofolio</a>
    <div class="top-menu-right" style="display: flex; gap: 10px;">
      <a href="telegram_setting.php" style="background:#475569; padding: 8px 15px; border-radius: 5px; color: white; display:flex; align-items:center; height:18px;"><img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" width="14" style="margin-right:5px;">Set Alert</a>
      <button id="btnSettings" class="btn-settings" style="margin-top:0;">⚙️ Settings & API Key</button>
    </div>
  </nav>

  <div class="controls-container" style="margin-bottom:15px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; display:flex; align-items:center; gap: 10px;">
    <strong style="margin-right:10px;">Pilih Saham:</strong>
    <select id="symbol" style="min-width: 250px; padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1;">
      <option value="">-- Pilih Saham --</option>
        <?php foreach ($stocks as $s) {
          $not = !empty($s['notation']) ? " [{$s['notation']}]" : "";
          echo '<option value="'.$s['symbol'].'">'.$s['symbol'].' - '.$s['name'].$not.'</option>';
        } ?>
    </select>
    <button id="btnLoad" style="background:#0d6efd; color:#fff; border:none; padding:8px 16px; border-radius:4px; font-weight:bold; cursor:pointer;">Load Data</button>
    <button id="btnResetZoom" style="margin-left:12px; padding:8px 16px; border-radius:4px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">🔄 Reset Zoom</button>
    <div style="margin-left:auto; display:flex; gap:10px; align-items:center; flex: 1; min-width: 200px; max-width: 450px;"><strong style="white-space:nowrap;font-size:13px;">Tampilkan Indikator:</strong><select id="indicatorToggle" multiple placeholder="Pilih Indikator..."></select></div>
  </div>

  <!-- Settings Modal -->
  <div id="settingsModal" class="modal-backdrop">
    <div class="modal">
      <h3>Data Sources & API</h3>
      <label>GoAPI.io Key (IDX/IHSG):
        <input id="goapi_key" placeholder="Optional for IDX fast Realtime/Historical (app.goapi.io)">
      </label>
      <small style="color:#666;display:block;margin-bottom:10px;line-height:1.4;">
        * Token/API Key dari app.goapi.io (khusus data Bursa Efek Indonesia).
      </small>
      <label>Finnhub API Key:
        <input id="finnhub_key" placeholder="Optional for US/Global stocks">
      </label>
      <small style="color:#666;display:block;margin-bottom:15px;line-height:1.4;">
        * For BEI/IDX stocks, the system already uses internal scrapers and Yahoo Finance (free) as the primary engine. Finnhub is an optional alternative.
      </small>
      <div class="modal-actions">
        <button id="closeSettings">Cancel</button>
        <button id="saveSettings" style="background:#0d6efd;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;">Save</button>
      </div>
    </div>
  </div>

  <div style="display:flex; flex-wrap:wrap; gap:20px;">
    <div id="chart-container" style="flex:1; min-width:700px; position: relative;">
      <!-- Loading Overlay -->
      <div id="loadingOverlay" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); z-index:10; flex-direction:column; justify-content:center; align-items:center;">
        <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #0d6efd; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        <div style="margin-top: 15px; font-weight: bold; font-size: 16px; color: #0d6efd;">Memuat Data...</div>
      </div>
      <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
      
      <canvas id="chart"></canvas>
    </div>
    
    <div id="news-panel" class="panel" style="width:300px; max-height:500px; display:flex; flex-direction:column;">
      <div style="margin-bottom:15px; background:#e9ecef; padding:10px; border-radius:6px; border:1px solid #ced4da;">
        <h4 style="margin:0 0 8px 0; color:#495057; font-size:14px;">Robo Trading Plan</h4>
        <div style="font-size:13px; margin-bottom:4px;"><strong>Market Sentiment:</strong> <span id="global_sentiment" class="badge hold">-</span></div>
        <div style="font-size:13px; margin-bottom:4px;"><strong>Entry Price:</strong> <span id="tp_entry">-</span></div>
        <div style="font-size:13px; margin-bottom:4px; color:#28a745;"><strong>Take Profit:</strong> <span id="tp_tp">-</span></div>
        <div style="font-size:13px; margin-bottom:4px; color:#dc3545;"><strong>Stop Loss:</strong> <span id="tp_sl">-</span></div>
      </div>
      
      <h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;">Berita Terkait</h3>
      <div id="news-list" style="font-size:13px; overflow-y:auto; flex:1;">Pilih saham untuk melihat berita...</div>
    </div>
  </div>

  <div class="panel info">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <div>
        <div style="margin-bottom:8px">
          <strong>Technical Signal:</strong> <span id="signal" class="badge hold">-</span>
          <small id="signal_details" style="color:#666; margin-left:8px;"></small>
        </div>
        <div>
          <strong>Fundamental:</strong> <span id="fund_status" style="font-weight:bold;">-</span> 
          <small>(Score: <span id="fund">-</span>)</small>
        </div>
      </div>
      <div><button id="toggleRaw">Toggle raw JSON</button></div>
    </div>
    <div class="indicators">
      <div class="ind-col">
        <div><strong>Latest price:</strong> <span id="latestPrice">-</span></div>
        <div><strong>Open price:</strong> <span id="openPrice">-</span></div>
        <div><strong>Daily Change:</strong> <span id="changePct">-</span></div>
        <div><strong>Date:</strong> <span id="latestDate">-</span></div>
        <div><strong>Price source:</strong> <span id="latestSource" class="badge hold">-</span></div>
      </div>
      <div class="ind-col">
        <div><strong>RSI (latest):</strong> <span id="rsi">-</span></div>
        <div><strong>MACD hist:</strong> <span id="macd">-</span></div>
      </div>
    </div>
    
    <div class="technical-glossary" style="margin-top:20px; padding-top:15px; border-top:1px solid #eee; font-size:13px; color:#555;">
      <h4 style="margin:0 0 8px 0; color:#333; font-size:14px;">Kamus Indikator Teknikal:</h4>
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <strong>SMA (Simple Moving Average):</strong> Menunjukkan arah tren pergerakan harga. <br>
          <span style="color:#28a745">• Bullish:</span> Tren sedang membaik/naik. <br>
          <span style="color:#d9534f">• Bearish:</span> Tren sedang melemah/turun.
        </div>
        <div>
          <strong>MACD (Moving Average Convergence Divergence):</strong> Mengukur momentum tren. <br>
          <span style="color:#28a745">• Positive:</span> Momentum harga menguat (Bagus). <br>
          <span style="color:#d9534f">• Negative:</span> Momentum harga melemah (Hati-hati).
        </div>
        <div>
          <strong>RSI (Relative Strength Index):</strong> Indikator tingkat kejenuhan volatilitas (0-100). <br>
          <span style="color:#28a745">• Oversold (&lt;30):</span> Harga terlalu murah (rapat pantul naik). <br>
          <span style="color:#d9534f">• Overbought (&gt;70):</span> Harga terlalu mahal (rawan koreksi/turun).
        </div>
        <div>
          <strong>Bollinger Bands (BB):</strong> Mengukur batasan wajar harga dalam volatilitas. <br>
          <span style="color:#28a745">• Price &lt; BB Lower:</span> Harga melewati batas bawah (Peluang Rebound). <br>
          <span style="color:#d9534f">• Price &gt; BB Upper:</span> Harga melewati batas atas (Peluang Jual).
        </div>
      </div>
    </div>

    <pre id="raw" style="background:#f4f4f4;padding:8px;display:none"></pre>
  </div>

<script>
    let symbolSelect, indicatorSelect;
    document.addEventListener('DOMContentLoaded', function() {
        symbolSelect = new TomSelect('#symbol', {create: false, sortField: { field: 'text', direction: 'asc' }, maxOptions: 1000});
        indicatorSelect = new TomSelect('#indicatorToggle', {valueField: 'id',labelField: 'title',searchField: 'title',options: [{id: 'SMA 5', title: 'SMA 5'},{id: 'SMA 20', title: 'SMA 20'},{id: 'SMA 50', title: 'SMA 50'},{id: 'SMA 200', title: 'SMA 200'},{id: 'BB', title: 'Bollinger Bands'}],plugins: ['remove_button'],onChange: function(values) {if(!chart) return; const selected=values||[]; chart.data.datasets.forEach((ds) => {if(ds.label.includes('OHLC')) return; let shouldShow=false; if(selected.includes(ds.label)) shouldShow=true; if(selected.includes('BB') && ds.label.includes('BB ')) shouldShow=true; ds.hidden=!shouldShow;}); chart.update();}});
        indicatorSelect.setValue(['SMA 5', 'SMA 20', 'BB']);
    });
    let chart;
    let liveUpdateInterval;

    function updateLivePrice(symbol) {
      const srcEl2 = document.getElementById('latestSource');
      srcEl2.innerText = 'checking live...';
      srcEl2.className = 'badge hold';

      const finnhubKey = localStorage.getItem('finnhub_key') || '';
      const goapiKey = localStorage.getItem('goapi_key') || '';
      fetch('fetch_realtime.php?symbols='+encodeURIComponent(symbol)+'&finnhub_key='+encodeURIComponent(finnhubKey)+'&goapi_key='+encodeURIComponent(goapiKey))
        .then(r=>r.json())
        .then(rt=>{
          if (rt && rt.data && rt.data[symbol]) {
            const p = rt.data[symbol];
            if (p.price && !isNaN(p.price)) {
              document.getElementById('latestPrice').innerText = Number(p.price).toLocaleString();
              let openEl = document.getElementById('openPrice');
              let openP = (p.open && !isNaN(p.open) && p.open > 0) ? Number(p.open) : null;
              if (!openP) {
                  // Fallback to reading the open price from history chart if not in realtime payload
                  openP = openEl.innerText !== '-' ? Number(openEl.innerText.replace(/,/g, '')) : null;
              }
              if (openP) {
                  let closeP = Number(p.price);
                  let diff = closeP - openP;
                  let pct = (diff / openP) * 100;
                  openEl.innerText = openP.toLocaleString();
                  let cEl = document.getElementById('changePct');
                  cEl.innerText = (diff > 0 ? '+' : '') + diff.toLocaleString() + ' (' + (diff > 0 ? '+' : '') + pct.toFixed(2) + '%)';
                  cEl.style.color = diff > 0 ? 'green' : (diff < 0 ? 'red' : 'black');
              }

              if (p.time) {
                const dt = new Date(p.time * 1000);
                document.getElementById('latestDate').innerText = dt.toLocaleString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
              } else if (p.raw && p.raw.t) {
                const dt = new Date(p.raw.t * 1000);
                document.getElementById('latestDate').innerText = dt.toLocaleString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
              }

              srcEl2.innerText = p.source ? 'live (' + p.source + ')' : 'live (auto)'; 
              srcEl2.className = 'badge buy';

              if (chart && chart.data && chart.data.datasets && chart.data.datasets[0] && chart.data.datasets[0].data) {
                  const dataArr = chart.data.datasets[0].data;
                  if (dataArr.length > 0) {
                        let dtNow = new Date();
                        if (p.time) {
                            dtNow = new Date(p.time * 1000);
                        } else if (p.raw && p.raw.t) {
                            dtNow = new Date(p.raw.t * 1000);
                        }
                        const lastCandle = dataArr[dataArr.length - 1];
                        const lastCandleDate = new Date(lastCandle.x);
                        
                        // Check if the last candle is from today or not
                        if (dtNow.getDate() === lastCandleDate.getDate() && dtNow.getMonth() === lastCandleDate.getMonth() && dtNow.getFullYear() === lastCandleDate.getFullYear()) {
                            // Update current day candle
                            lastCandle.c = p.price;
                            if (p.price > lastCandle.h) lastCandle.h = p.price;
                            if (p.price < lastCandle.l) lastCandle.l = p.price;
                        } else {
                            // Target open price either from p.open or last day's close
                            let openP = (p.open && !isNaN(p.open) && p.open > 0) ? Number(p.open) : lastCandle.c;
                            dataArr.push({
                                x: dtNow.valueOf(),
                                o: openP,
                                h: Math.max(openP, p.price),
                                l: Math.min(openP, p.price),
                                c: p.price
                            });
                        }
                      chart.update('none');
                  }
              }
            } else {
              srcEl2.innerText = p.error ? 'err: ' + p.error : 'cache (live fail)';
              srcEl2.className = 'badge sell';
            }
          } else {
            srcEl2.innerText = 'cache (no live)';
            srcEl2.className = 'badge hold';
          }
        }).catch(e=>{
          console.warn('Realtime fetch failed', e);
          srcEl2.innerText = 'offline';
          srcEl2.className = 'badge sell';
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
      if(!symbol) return;
      
      // Tampilkan Loading
      const loadingOverlay = document.getElementById('loadingOverlay');
      const btnLoad = document.getElementById('btnLoad');
      if(loadingOverlay) loadingOverlay.style.display = 'flex';
      if(btnLoad) { btnLoad.disabled = true; btnLoad.innerText = 'Loading...'; }

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
        if (data.latest && data.latest.open) {
            let openP = Number(data.latest.open);
            let closeP = Number(data.latest.close);
            let diff = closeP - openP;
            let pct = (diff / openP) * 100;
            document.getElementById('openPrice').innerText = openP.toLocaleString();
            let cEl = document.getElementById('changePct');
            cEl.innerText = (diff > 0 ? '+' : '') + diff.toLocaleString() + ' (' + (diff > 0 ? '+' : '') + pct.toFixed(2) + '%)';
            cEl.style.color = diff > 0 ? 'green' : (diff < 0 ? 'red' : 'black');
        } else {
            document.getElementById('openPrice').innerText = '-';
            document.getElementById('changePct').innerText = '-';
            document.getElementById('changePct').style.color = 'black';
        }
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
        const sma50 = (data.sma50 || []).map(v=>v===null?null:v);
        const sma200 = (data.sma200 || []).map(v=>v===null?null:v);
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
              { label: 'SMA 5', type: 'line', hidden: true, data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: sma5[i]})), borderColor: 'blue', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'SMA 20', type: 'line', hidden: true, data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: sma20[i]})), borderColor: 'orange', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'SMA 50', type: 'line', hidden: true, data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: sma50[i]})), borderColor: 'purple', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'SMA 200', type: 'line', hidden: true, data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: sma200[i]})), borderColor: 'red', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'BB Upper', type: 'line', hidden: true, data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: bbUpper[i]})), borderColor: 'rgba(0,0,0,0.2)', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' },
              { label: 'BB Lower', type: 'line', hidden: true, data: data.prices.map((p,i)=>({x: new Date(p.date).valueOf(), y: bbLower[i]})), borderColor: 'rgba(0,0,0,0.2)', borderWidth: 1, pointRadius:0, spanGaps: true, yAxisID: 'y' }
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
          
          if(typeof indicatorSelect !== "undefined") {
              const selected = indicatorSelect.getValue() || [];
              chart.data.datasets.forEach((ds) => {
                  if(ds.label.includes("OHLC")) return;
                  let shouldShow = false;
                  if(selected.includes(ds.label)) shouldShow = true;
                  if(selected.includes("BB") && ds.label.includes("BB ")) shouldShow = true;
                  ds.hidden = !shouldShow;
              });
              chart.update();
          }

          
      }).catch(e=>{ 
          console.error(e); 
          alert('Error loading data: '+e.message);
      }).finally(() => {
          // Sembunyikan Loading
          const loadingOverlay = document.getElementById('loadingOverlay');
          const btnLoad = document.getElementById('btnLoad');
          if(loadingOverlay) loadingOverlay.style.display = 'none';
          if(btnLoad) { btnLoad.disabled = false; btnLoad.innerText = 'Load'; }

          updateLivePrice(symbol);
          liveUpdateInterval = setInterval(()=>updateLivePrice(symbol), 15000);
      });
    }

    document.getElementById('btnLoad').addEventListener('click', ()=>{
      const sym = document.getElementById('symbol').value;
      render(sym);
    });

    // Modal interactions
    const btnSettings = document.getElementById('btnSettings');
    if (btnSettings) {
        btnSettings.addEventListener('click', ()=>{
          document.getElementById('settingsModal').style.display = 'block';
        });
    }

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
      const g = document.getElementById('goapi_key').value.trim();
      
      if (v==='') localStorage.removeItem('finnhub_key');
      else localStorage.setItem('finnhub_key', v);
      
      if (g==='') localStorage.removeItem('goapi_key');
      else localStorage.setItem('goapi_key', g);
      
      alert('Konfigurasi API Key berhasil disimpan!');
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

    // populate api keys from localStorage
    (function(){
      try { 
        const fk = localStorage.getItem('finnhub_key'); 
        if(fk) document.getElementById('finnhub_key').value = fk; 
        
        const gk = localStorage.getItem('goapi_key');
        if(gk) document.getElementById('goapi_key').value = gk;
      } catch(e){}
    })();

    // auto-load from URL if present
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const urlParams = new URLSearchParams(window.location.search);
            const symFromUrl = urlParams.get('symbol');
            if (symFromUrl) {
              const selectEl = document.getElementById('symbol');
              // Ensure option exists or create it temporarily
              let exists = false;
              for (let i = 0; i < selectEl.options.length; i++) {
                  if (selectEl.options[i].value === symFromUrl) {
                      exists = true; break;
                  }
              }
              if (!exists) {
                if (typeof symbolSelect !== 'undefined') {
                    symbolSelect.addOption({value: symFromUrl, text: symFromUrl});
                } else {
                    const newOption = document.createElement('option');
                    newOption.value = symFromUrl;
                    newOption.text = symFromUrl;
                    selectEl.appendChild(newOption);
                }
              }

              if (typeof symbolSelect !== 'undefined') {
                  symbolSelect.setValue(symFromUrl);
              } else {
                  selectEl.value = symFromUrl;
              }
              render(symFromUrl);
            } else if (document.getElementById('symbol').value) {
              render(document.getElementById('symbol').value);
            }
        }, 300); // 300ms buffer to ensure all core plugin states are registered
    });
  </script>
</body>
</html>








