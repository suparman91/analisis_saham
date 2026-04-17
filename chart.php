<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();
$stocks = [];
  $res = $mysqli->query('SELECT symbol,name,notation FROM stocks ORDER BY symbol');
while ($r = $res->fetch_assoc()) $stocks[] = $r;
?>
<?php
$pageTitle = 'Analisis Saham IHSG';
?>
<?php include 'header.php'; ?>
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

  <script src="https://unpkg.com/lightweight-charts@4.2.1/dist/lightweight-charts.standalone.production.js"></script>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

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

  <h2 id="chart-title" style="margin-bottom:10px; color:#0d6efd; font-size:22px;"></h2>
  <div style="display:flex; flex-wrap:wrap; gap:20px;">
    <div id="chart-container" style="flex:1; min-width:700px; position: relative;">
      <!-- Loading Overlay -->
      <div id="loadingOverlay" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); z-index:10; flex-direction:column; justify-content:center; align-items:center;">
        <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #0d6efd; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        <div style="margin-top: 15px; font-weight: bold; font-size: 16px; color: #0d6efd;">Memuat Data...</div>
      </div>
      <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
      
      <div id="chart" style="width:100%; height:450px;"></div>
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
        <div><strong>Open (09:00):</strong> <span id="openPrice">-</span></div>
        <div><strong>Prev Close:</strong> <span id="prevClosePrice">-</span></div>
        <div><strong>Perubahan vs Kemarin:</strong> <span id="changePct">-</span></div>
        <div><strong>Date:</strong> <span id="latestDate">-</span></div>
        <div><strong>Price source:</strong> <span id="latestSource" class="badge hold">-</span></div>
      </div>
      <div class="ind-col">
        <div><strong>RSI (latest):</strong> <span id="rsi">-</span></div>
        <div><strong>MACD hist:</strong> <span id="macd">-</span></div>
      </div>
    </div>
    
    <div id="fundamental-ext-panel" style="margin-top:20px; padding:15px; border-top:2px solid #0d6efd; font-size:13px; color:#333; background:#f8fafc; display:none"></div>
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
// Helper: Fundamental glossary
const FUND_GLOSSARY = {
  eps: '<b>EPS (Earnings Per Share):</b> Laba bersih per lembar saham. Semakin tinggi, semakin baik.',
  pe: '<b>PER (Price to Earnings Ratio):</b> Harga saham dibanding laba per saham. <span style="color:#28a745">Rendah = murah</span>, <span style="color:#d9534f">Tinggi = mahal</span>.',
  pbv: '<b>PBV (Price to Book Value):</b> Harga saham dibanding nilai buku. <span style="color:#28a745">Rendah = murah</span>, <span style="color:#d9534f">Tinggi = mahal</span>.',
  roe: '<b>ROE (Return on Equity):</b> Persentase laba terhadap ekuitas. <span style="color:#28a745">Tinggi = efisien</span>.',
  de: '<b>DER (Debt to Equity Ratio):</b> Rasio utang terhadap ekuitas. <span style="color:#28a745">Rendah = sehat</span>, <span style="color:#d9534f">Tinggi = berisiko</span>.',
  revenue: '<b>Pendapatan (Revenue):</b> Total penjualan perusahaan.',
  net_income: '<b>Laba Bersih (Net Income):</b> Sisa pendapatan setelah dikurangi biaya.',
  book_value: '<b>Book Value:</b> Nilai buku per saham (aset bersih).',
  currency: '<b>Mata Uang:</b> Satuan pelaporan.'
};

let symbolSelect, indicatorSelect;
let chart;
let candleSeries;
let indicatorSeries = {};
let lastCandleTime = null;
let lastCandleData = null;
let liveUpdateInterval;

document.addEventListener('DOMContentLoaded', function() {
    symbolSelect = new TomSelect('#symbol', {create: false, sortField: { field: 'text', direction: 'asc' }, maxOptions: 1000});
    indicatorSelect = new TomSelect('#indicatorToggle', {
        valueField: 'id',labelField: 'title',searchField: 'title',
        options: [{id: 'SMA 5', title: 'SMA 5'},{id: 'SMA 20', title: 'SMA 20'},{id: 'SMA 50', title: 'SMA 50'},{id: 'SMA 200', title: 'SMA 200'},{id: 'BB', title: 'Bollinger Bands'}],
        plugins: ['remove_button'],
        onChange: function(values) {
            const selected = values || [];
            Object.keys(indicatorSeries).forEach(key => {
                if (!indicatorSeries[key]) return;
                let shouldShow = false;
                if (selected.includes(key)) shouldShow = true;
                if (selected.includes('BB') && key.startsWith('BB')) shouldShow = true;
                indicatorSeries[key].applyOptions({ visible: shouldShow });
            });
        }
    });
    indicatorSelect.setValue(['SMA 5', 'SMA 20', 'BB']);
});

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
              openP = openEl.innerText !== '-' ? Number(openEl.innerText.replace(/,/g, '')) : null;
          }
          if (openP) openEl.innerText = openP.toLocaleString();
          
          const baseP2 = window._prevCloseVal || openP;
          if (baseP2 && baseP2 > 0) {
              let closeP = Number(p.price);
              let diff = closeP - baseP2;
              let pct = (diff / baseP2) * 100;
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

          if (candleSeries && lastCandleTime) {
              let dtNow = new Date();
              if (p.time) {
                  dtNow = new Date(p.time * 1000);
              } else if (p.raw && p.raw.t) {
                  dtNow = new Date(p.raw.t * 1000);
              }
              
              const year = dtNow.getFullYear();
              const month = String(dtNow.getMonth() + 1).padStart(2, "0");
              const day = String(dtNow.getDate()).padStart(2, "0");
              const dateStr = `${year}-${month}-${day}`;

              if (dateStr === lastCandleTime) {
                  lastCandleData.close = Number(p.price);
                  if (Number(p.price) > lastCandleData.high) lastCandleData.high = Number(p.price);
                  if (Number(p.price) < lastCandleData.low) lastCandleData.low = Number(p.price);
                  candleSeries.update(lastCandleData);
              } else {
                  let openPriceNew = (p.open && !isNaN(p.open) && p.open > 0) ? Number(p.open) : lastCandleData.close;
                  lastCandleTime = dateStr;
                  lastCandleData = {
                      time: dateStr,
                      open: openPriceNew,
                      high: Math.max(openPriceNew, Number(p.price)),
                      low: Math.min(openPriceNew, Number(p.price)),
                      close: Number(p.price)
                  };
                  candleSeries.update(lastCandleData);
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

function render(symbol){
  if(!symbol) return;
  const chartTitle = document.getElementById('chart-title');
  if(chartTitle) chartTitle.innerText = 'Chart Saham: ' + symbol;
  document.title = 'Chart ' + symbol + ' | Analisis Saham IHSG';

  const loadingOverlay = document.getElementById('loadingOverlay');
  const btnLoad = document.getElementById('btnLoad');
  if(loadingOverlay) loadingOverlay.style.display = 'flex';
  if(btnLoad) { btnLoad.disabled = true; btnLoad.innerText = 'Loading...'; }

  if(liveUpdateInterval) clearInterval(liveUpdateInterval);

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

  fetch('analyze_api.php?symbol='+encodeURIComponent(symbol)).then(async r=>{
    if (!r.ok) {
      const txt = await r.text();
      throw new Error('HTTP '+r.status+': '+txt);
    }
    return r.json();
  }).then(data=>{
    if (data.error){ alert(data.error); return; }
    
    // update indicators
    const sigEl = document.getElementById('signal');
    document.getElementById('fund').innerText = (data.fund_score !== undefined && data.fund_score !== null) ? Number(data.fund_score).toFixed(2) : 'N/A';
    document.getElementById('fund_status').innerText = data.fund_status || 'N/A';
    document.getElementById('signal_details').innerText = data.signal_details ? `(${data.signal_details})` : '';
    document.getElementById('raw').innerText = JSON.stringify(data, null, 2);

    // Fundamental eksternal (Yahoo Finance)
    const fundPanel = document.getElementById('fundamental-ext-panel');
    let kode = symbol.split('.')[0];
    let idxLinkHtml = '<div style="margin-top:10px;font-size:13px;">'
      +'<a href="https://www.idx.co.id/id/data-pasar/laporan-keuangan-dan-tahunan/?emiten='+kode+'" target="_blank" style="color:#0d6efd;text-decoration:underline;font-weight:bold;">Lihat Laporan Keuangan Lengkap di IDX ('+kode+')</a>'
      +'</div>';
    if (data.fundamental_ext) {
      let f = data.fundamental_ext;
      let html = '<h4 style="margin:0 0 8px 0; color:#0d6efd;">Laporan Keuangan & Rasio Terbaru</h4>';
      html += '<table style="font-size:13px; margin-bottom:10px; border-collapse:collapse;">';
      html += '<tr><td><b>EPS</b></td><td>'+(f.eps!==null?Number(f.eps).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.eps+'</td></tr>';
      html += '<tr><td><b>PER</b></td><td>'+(f.pe!==null?Number(f.pe).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.pe+'</td></tr>';
      html += '<tr><td><b>PBV</b></td><td>'+(f.pbv!==null?Number(f.pbv).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.pbv+'</td></tr>';
      html += '<tr><td><b>ROE (%)</b></td><td>'+(f.roe!==null?Number(f.roe).toFixed(2):"-")+'</td><td>'+FUND_GLOSSARY.roe+'</td></tr>';
      html += '<tr><td><b>DER</b></td><td>'+(f.de!==null?Number(f.de).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.de+'</td></tr>';
      html += '<tr><td><b>Pendapatan</b></td><td>'+(f.revenue!==null?Number(f.revenue).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.revenue+'</td></tr>';
      html += '<tr><td><b>Laba Bersih</b></td><td>'+(f.net_income!==null?Number(f.net_income).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.net_income+'</td></tr>';
      html += '<tr><td><b>Book Value</b></td><td>'+(f.book_value!==null?Number(f.book_value).toLocaleString():"-")+'</td><td>'+FUND_GLOSSARY.book_value+'</td></tr>';
      html += '<tr><td><b>Mata Uang</b></td><td>'+(f.currency||"-")+'</td><td>'+FUND_GLOSSARY.currency+'</td></tr>';
      html += '</table>';
      html += '<div style="margin-top:10px;font-size:13px;">'
        +'<a href="https://www.idx.co.id/id/data-pasar/laporan-keuangan-dan-tahunan/?emiten='+kode+'" target="_blank" style="color:#0d6efd;text-decoration:underline;font-weight:bold;">Lihat Laporan Keuangan Lengkap di IDX ('+kode+')</a>'
        +'</div>';
      fundPanel.innerHTML = html + idxLinkHtml;
      fundPanel.style.display = '';
    } else {
      fundPanel.innerHTML = idxLinkHtml;
      fundPanel.style.display = '';
    }

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

    const signalText = (data.signal || '').toUpperCase();
    sigEl.innerText = signalText;
    sigEl.className = 'badge ' + (signalText.indexOf('BUY')!==-1 ? 'buy' : signalText.indexOf('SELL')!==-1 ? 'sell' : 'hold');
    
    document.getElementById('latestPrice').innerText = data.latest ? Number(data.latest.close).toLocaleString() : '-';
    if (data.latest && data.latest.open) {
        document.getElementById('openPrice').innerText = Number(data.latest.open).toLocaleString();
    } else {
        document.getElementById('openPrice').innerText = '-';
    }
    
    const prevCloseVal = data.prev_close ? Number(data.prev_close) : null;
    window._prevCloseVal = prevCloseVal;
    if (data.latest) {
        document.getElementById('prevClosePrice').innerText = prevCloseVal ? prevCloseVal.toLocaleString() : '-';
        const closeP = Number(data.latest.close);
        const baseP = prevCloseVal || (data.latest.open ? Number(data.latest.open) : null);
        if (baseP && baseP > 0) {
            const diff = closeP - baseP;
            const pct = (diff / baseP) * 100;
            const cEl = document.getElementById('changePct');
            cEl.innerText = (diff > 0 ? '+' : '') + diff.toLocaleString() + ' (' + (diff > 0 ? '+' : '') + pct.toFixed(2) + '%)';
            cEl.style.color = diff > 0 ? 'green' : (diff < 0 ? 'red' : 'black');
        } else {
            document.getElementById('changePct').innerText = '-';
            document.getElementById('changePct').style.color = 'black';
        }
    } else {
        document.getElementById('prevClosePrice').innerText = '-';
        document.getElementById('changePct').innerText = '-';
        document.getElementById('changePct').style.color = 'black';
    }
    document.getElementById('latestDate').innerText = data.latest ? data.latest.date : '-';
    
    const srcEl = document.getElementById('latestSource');
    srcEl.innerText = 'cache'; srcEl.className = 'badge hold';
    
    const latestIdx = data.prices ? data.prices.length -1 : null;
    document.getElementById('rsi').innerText = (data.rsi && data.rsi[latestIdx]!==undefined && data.rsi[latestIdx]!==null) ? Number(data.rsi[latestIdx]).toFixed(2) : '-';
    document.getElementById('macd').innerText = (data.macd && data.macd.hist && data.macd.hist[latestIdx]!==undefined) ? Number(data.macd.hist[latestIdx]).toFixed(3) : '-';
    
    // -------------
    // CHART RENDERING (Lightweight Charts)
    // -------------
    const container = document.getElementById('chart');
    
    // Create chart if it doesn't exist
    if (!chart) {
        chart = LightweightCharts.createChart(container, {
            layout: {
                background: { type: 'solid', color: '#ffffff' },
                textColor: '#333',
            },
            grid: {
                vertLines: { color: '#f0f0f0' },
                horzLines: { color: '#f0f0f0' },
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
            },
            rightPriceScale: {
                borderColor: '#ccc',
            },
            timeScale: {
                borderColor: '#ccc',
                timeVisible: false,
            },
            watermark: {
                color: 'rgba(11, 94, 215, 0.1)',
                text: 'Analisis Saham IHSG',
                fontSize: 48,
                fontFamily: 'Arial, sans-serif',
                visible: true,
            }
        });

        candleSeries = chart.addCandlestickSeries({
            upColor: '#26a69a',
            downColor: '#ef5350',
            borderVisible: false,
            wickUpColor: '#26a69a',
            wickDownColor: '#ef5350',
        });
        
        indicatorSeries['SMA 5'] = chart.addLineSeries({ color: 'blue', lineWidth: 1, title: 'SMA 5', visible: false });
        indicatorSeries['SMA 20'] = chart.addLineSeries({ color: 'orange', lineWidth: 1, title: 'SMA 20', visible: false });
        indicatorSeries['SMA 50'] = chart.addLineSeries({ color: 'purple', lineWidth: 1, title: 'SMA 50', visible: false });
        indicatorSeries['SMA 200'] = chart.addLineSeries({ color: 'red', lineWidth: 1, title: 'SMA 200', visible: false });
        indicatorSeries['BB Upper'] = chart.addLineSeries({ color: 'rgba(0,0,0,0.3)', lineWidth: 1, title: 'BB Up', visible: false });
        indicatorSeries['BB Lower'] = chart.addLineSeries({ color: 'rgba(0,0,0,0.3)', lineWidth: 1, title: 'BB Low', visible: false });
        
        // Resize listener
        new ResizeObserver(entries => {
            if (entries.length === 0 || entries[0].target !== container) { return; }
            const newRect = entries[0].contentRect;
            chart.applyOptions({ height: newRect.height, width: newRect.width });
        }).observe(container);
    }
    
    // Prepare arrays
    let formattedData = [];
    let s5 = [], s20 = [], s50 = [], s200 = [], bbu = [], bbl = [];
    
    data.prices.forEach((p, i) => {
        const timeObj = p.date;
        formattedData.push({ time: timeObj, open: p.open, high: p.high, low: p.low, close: p.close });
        if(data.sma5 && data.sma5[i] !== null) s5.push({ time: timeObj, value: data.sma5[i] });
        if(data.sma20 && data.sma20[i] !== null) s20.push({ time: timeObj, value: data.sma20[i] });
        if(data.sma50 && data.sma50[i] !== null) s50.push({ time: timeObj, value: data.sma50[i] });
        if(data.sma200 && data.sma200[i] !== null) s200.push({ time: timeObj, value: data.sma200[i] });
        if(data.bollinger && data.bollinger.upper && data.bollinger.upper[i] !== null) bbu.push({ time: timeObj, value: data.bollinger.upper[i] });
        if(data.bollinger && data.bollinger.lower && data.bollinger.lower[i] !== null) bbl.push({ time: timeObj, value: data.bollinger.lower[i] });
    });
    
    if(formattedData.length > 0) {
        lastCandleTime = formattedData[formattedData.length - 1].time;
        lastCandleData = formattedData[formattedData.length - 1];
    }

    candleSeries.setData(formattedData);
    indicatorSeries['SMA 5'].setData(s5);
    indicatorSeries['SMA 20'].setData(s20);
    indicatorSeries['SMA 50'].setData(s50);
    indicatorSeries['SMA 200'].setData(s200);
    indicatorSeries['BB Upper'].setData(bbu);
    indicatorSeries['BB Lower'].setData(bbl);
    
    if (indicatorSelect) {
        const selected = indicatorSelect.getValue() || [];
        Object.keys(indicatorSeries).forEach(key => {
            let shouldShow = false;
            if (selected.includes(key)) shouldShow = true;
            if (selected.includes('BB') && key.startsWith('BB')) shouldShow = true;
            indicatorSeries[key].applyOptions({ visible: shouldShow });
        });
    }
    
    chart.timeScale().fitContent();
    chart.applyOptions({
        watermark: {
            color: 'rgba(11, 94, 215, 0.1)',
            text: symbol + ' - Analisis Saham',
            fontSize: 48,
            fontFamily: 'Arial, sans-serif',
            visible: true,
        }
    });

  }).catch(e=>{ 
      console.error(e); 
      alert('Error loading data: '+e.message);
  }).finally(() => {
      const loadingOverlay = document.getElementById('loadingOverlay');
      const btnLoad = document.getElementById('btnLoad');
      if(loadingOverlay) loadingOverlay.style.display = 'none';
      if(btnLoad) { btnLoad.disabled = false; btnLoad.innerText = 'Load Data'; }

      updateLivePrice(symbol);
      liveUpdateInterval = setInterval(()=>updateLivePrice(symbol), 15000);
  });
}

document.getElementById('btnLoad').addEventListener('click', ()=>{
  const sym = document.getElementById('symbol').value;
  render(sym);
});

const btnSettings = document.getElementById('btnSettings');
if (btnSettings) {
    btnSettings.addEventListener('click', ()=>{
      document.getElementById('settingsModal').style.display = 'block';
    });
}

document.getElementById('btnResetZoom').addEventListener('click', ()=>{
  if (chart) {
    chart.timeScale().fitContent();
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

(function(){
  try { 
    const fk = localStorage.getItem('finnhub_key'); 
    if(fk) document.getElementById('finnhub_key').value = fk; 
    
    const gk = localStorage.getItem('goapi_key');
    if(gk) document.getElementById('goapi_key').value = gk;
  } catch(e){}
})();

window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const symFromUrl = urlParams.get('symbol');
        if (symFromUrl) {
          const selectEl = document.getElementById('symbol');
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
    }, 300);
});
</script>
<?php include 'footer.php'; ?>










