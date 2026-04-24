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
    .info{margin-top:10px;background:#ffffff;border:1px solid #dbe4f0;border-radius:10px;padding:14px;box-shadow:0 1px 2px rgba(15,23,42,0.04)}
    #raw{max-height:200px;overflow:auto}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;color:#fff;font-weight:700;font-size:12px;line-height:1.3}
    .badge.buy{background:#198754}
    .badge.sell{background:#dc3545}
    .badge.hold{background:#6c757d}
    .panel{margin-top:12px;padding:10px;background:#fafafa;border:1px solid #eee;border-radius:6px}
    .info-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .info-summary{flex:1;min-width:280px}
    .signal-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .signal-meta{display:block;color:#64748b;line-height:1.45;word-break:break-word;margin-top:4px;font-size:12px}
    .info-actions{margin-left:auto}
    .info-actions button{font-size:12px;padding:5px 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer}
    .info-actions button:hover{background:#f8fafc}
    .indicators{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px 24px;align-items:flex-start}
    .ind-col{min-width:160px}
    .ind-col > div{padding:6px 0;border-bottom:1px dashed #e2e8f0;font-size:14px;color:#0f172a}
    .ind-col > div:last-child{border-bottom:none}
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
    
    /* AI Analysis Styles */
    .ai-analysis-panel { margin-top:20px; padding:20px; background:linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%); border:2px solid #0d6efd; border-radius:8px; }
    .ai-analysis-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:15px; }
    .ai-analysis-header h3 { margin:0; color:#0d6efd; font-size:18px; }
    .ai-button-group { display:flex; gap:10px; align-items:center; }
    .btn-ai-analyze { background:#0d6efd; color:#fff; border:none; padding:8px 16px; border-radius:4px; font-weight:bold; cursor:pointer; transition:all 0.3s; }
    .btn-ai-analyze:hover { background:#0b5ed7; }
    .btn-ai-analyze:disabled { background:#ccc; cursor:not-allowed; }
    .btn-ai-free { background:#198754; color:#fff; border:none; padding:8px 12px; border-radius:6px; font-weight:700; cursor:pointer; transition:all 0.2s; }
    .btn-ai-free:hover { background:#157347; }
    .btn-ai-google { background:#0d6efd; color:#fff; border:none; padding:8px 12px; border-radius:6px; font-weight:700; cursor:pointer; transition:all 0.2s; }
    .btn-ai-google:hover { background:#0b5ed7; }
    .ai-engine-badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid transparent; }
    .ai-engine-badge.free { background:#d1fae5; color:#065f46; border-color:#10b981; }
    .ai-engine-badge.google { background:#dbeafe; color:#1e3a8a; border-color:#3b82f6; }
    .ai-engine-badge.auto { background:#f1f5f9; color:#334155; border-color:#94a3b8; }
    .ai-timeframe-select { padding:6px 10px; border-radius:4px; border:1px solid #cbd5e1; background:#fff; font-size:13px; }
    
    /* Speedometer Recommendation Widget */
    .speedometer-container { display:flex; justify-content:center; align-items:center; margin:30px 0; flex-direction:column; position:relative; }
    .speedometer { width:200px; height:120px; border-radius:120px 120px 0 0; background:conic-gradient(red 0deg, orange 45deg, yellow 90deg, lightgreen 135deg, green 180deg); position:relative; margin:0 auto; box-shadow:0 4px 8px rgba(0,0,0,0.2); }
    .speedometer::before { content:''; position:absolute; bottom:10px; left:50%; transform:translateX(-50%); width:0; height:0; border-left:8px solid transparent; border-right:8px solid transparent; border-bottom:12px solid #333; z-index:10; }
    .speedometer-scale { width:200px; display:flex; justify-content:space-between; margin-top:8px; font-weight:bold; font-size:12px; color:#333; }
    .speedometer-label { text-align:center; margin-top:15px; font-size:16px; font-weight:bold; color:#333; }
    .speedometer-value { font-size:24px; font-weight:bold; color:#0d6efd; margin:10px 0; }
    .speedometer-confidence { font-size:12px; color:#666; margin-top:8px; }
    
    /* Analysis Content */
    .ai-analysis-content { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:20px 0; }
    .ai-analysis-section { padding:15px; background:#fff; border-left:4px solid #0d6efd; border-radius:4px; }
    .ai-analysis-section h4 { margin:0 0 10px 0; color:#0d6efd; font-size:14px; font-weight:bold; }
    .ai-analysis-section p { margin:8px 0; font-size:13px; line-height:1.6; color:#555; }
    .ai-analysis-section ul { margin:8px 0; padding-left:20px; font-size:13px; color:#555; }
    .ai-analysis-section li { margin:4px 0; }
    .ai-loading { text-align:center; padding:30px; color:#666; }
    .ai-loading-spinner { display:inline-block; width:30px; height:30px; border:3px solid #f3f3f3; border-top:3px solid #0d6efd; border-radius:50%; animation:spin 1s linear infinite; margin-right:10px; }
    @keyframes spin { 0% { transform:rotate(0deg); } 100% { transform:rotate(360deg); } }
    .ai-error { padding:15px; background:#f8d7da; border-left:4px solid #dc3545; border-radius:4px; color:#721c24; margin:15px 0; }
    .ai-success { padding:15px; background:#d4edda; border-left:4px solid #198754; border-radius:4px; color:#155724; margin:15px 0; }
    .price-targets { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin:15px 0; }
    .price-target { text-align:center; padding:12px; background:#f0f0f0; border-radius:4px; }
    .price-target label { font-size:11px; color:#666; display:block; margin-bottom:5px; }
    .price-target .value { font-size:16px; font-weight:bold; color:#0d6efd; }
    .ai-advanced-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:10px; margin-top:10px; }
    .ai-advanced-item { background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:10px; }
    .ai-advanced-item .k { font-size:11px; color:#64748b; display:block; margin-bottom:4px; }
    .ai-advanced-item .v { font-size:13px; color:#0f172a; font-weight:700; }
    .ai-advanced-list { margin:8px 0 0 18px; padding:0; color:#475569; font-size:13px; }
    @media(max-width:768px) { .ai-analysis-content { grid-template-columns:1fr; } .ai-analysis-header { flex-direction:column; align-items:flex-start; gap:10px; } .ai-button-group { flex-wrap:wrap; } .indicators{grid-template-columns:1fr;} .info-actions{width:100%;display:flex;justify-content:flex-end;} .signal-meta{font-size:11px;} .ind-col > div{font-size:13px;} }
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

  <!-- AI Analysis Modal -->
  <div id="aiAnalysisModal" class="modal-backdrop">
    <div class="modal" style="width:350px; max-height:90vh; overflow-y:auto;">
      <h3>🤖 Analisis by AI (Google Gemini)</h3>
      <label style="display:block; margin-bottom:10px;">
        <strong>Google Gemini API Token:</strong>
        <input id="gemini_api_token" type="password" placeholder="Dapatkan dari console.cloud.google.com" style="width:100%; padding:8px; box-sizing:border-box; margin-top:5px;">
      </label>
      <small style="color:#666;display:block;margin-bottom:15px;line-height:1.4;">
        📝 Masukkan API key dari Google Cloud Console (Gemini 1.5 Flash API).
        <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color:#0d6efd;text-decoration:underline;">Buat API key di sini</a>
      </small>
      <small style="color:#475569;display:block;margin-bottom:12px;line-height:1.4;">
        Tidak ada token? Tetap bisa pakai analisis lokal dengan detail teknikal, fundamental, sentimen, risiko, dan rekomendasi.
      </small>
      <label style="display:block; margin-bottom:15px;">
        <strong>Timeframe Analisis:</strong>
        <select id="gemini_timeframe" style="width:100%; padding:8px; box-sizing:border-box; margin-top:5px;">
          <option value="1W">1 Minggu (1W)</option>
          <option value="1M" selected>1 Bulan (1M)</option>
          <option value="3M">3 Bulan (3M)</option>
          <option value="6M">6 Bulan (6M)</option>
          <option value="1Y">1 Tahun (1Y)</option>
        </select>
      </label>
      <div class="modal-actions">
        <button id="useFreeAnalysis" style="background:#198754;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;margin-right:5px;">Pakai Gratis</button>
        <button id="closeAiModal" style="background:#6c757d;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;margin-right:5px;">Batal</button>
        <button id="saveAiConfig" style="background:#0d6efd;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;">Lanjut</button>
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
    <div class="info-header" style="margin-bottom:10px;">
      <div class="info-summary">
        <div class="signal-row" style="margin-bottom:4px">
          <strong>Technical Signal:</strong> <span id="signal" class="badge hold">-</span>
        </div>
        <small id="signal_details" class="signal-meta"></small>
        <div>
          <strong>Fundamental:</strong> <span id="fund_status" style="font-weight:bold;">-</span> 
          <small>(Score: <span id="fund">-</span>)</small>
        </div>
      </div>
      <div class="info-actions"><button id="toggleRaw">Toggle raw JSON</button></div>
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

    <pre id="raw" style="background:#f4f4f4;padding:8px;display:none"></pre>
  </div>
  <!-- AI Analysis Panel -->
  <div id="aiAnalysisPanel" class="ai-analysis-panel" style="display:none;">
    <div class="ai-analysis-header">
      <div>
        <h3>🤖 Analisis Detail by AI</h3>
        <small style="color:#666;">Pilih mode: Gratis (tanpa token) atau Google AI (pakai token).</small>
      </div>
      <div class="ai-button-group">
        <select id="aiTimeframeSelect" class="ai-timeframe-select">
          <option value="1W">1W</option>
          <option value="1M" selected>1M</option>
          <option value="3M">3M</option>
          <option value="6M">6M</option>
          <option value="1Y">1Y</option>
        </select>
        <button id="btnAnalyzeFree" class="btn-ai-free">Analisis Gratis</button>
        <button id="btnAnalyzeGoogle" class="btn-ai-google">Analisis Google AI</button>
        <span id="aiEngineBadge" class="ai-engine-badge auto">Mode Auto</span>
      </div>
    </div>

    <!-- Recommendation Speedometer -->
    <div id="aiRecommendationContainer" style="display:none;">
      <div class="speedometer-container">
        <div class="speedometer" id="aiSpeedometer"></div>
        <div class="speedometer-scale">
          <div>SELL</div>
          <div>HOLD</div>
          <div>BUY</div>
          <div>HOT</div>
        </div>
        <div class="speedometer-label" id="aiRecommendationText">-</div>
        <div class="speedometer-value" id="aiRecommendationValue">-</div>
        <div style="font-size:12px; color:#666; text-align:center;">
          Confidence: <strong id="aiConfidenceLevel">-</strong>
        </div>
      </div>
    </div>

    <!-- Analysis Content -->
    <div id="aiAnalysisContent" style="display:none;">
      <!-- Technical Analysis -->
      <div class="ai-analysis-section" style="grid-column:1;">
        <h4>📊 Analisis Teknikal</h4>
        <div id="aiTechnicalContent">-</div>
      </div>
      
      <!-- Fundamental Analysis -->
      <div class="ai-analysis-section" style="grid-column:2;">
        <h4>💼 Analisis Fundamental</h4>
        <div id="aiFundamentalContent">-</div>
      </div>
      
      <!-- Sentiment Analysis -->
      <div class="ai-analysis-section">
        <h4>📈 Analisis Sentimen</h4>
        <div id="aiSentimentContent">-</div>
      </div>
      
      <!-- Risk Factors -->
      <div class="ai-analysis-section">
        <h4>⚠️ Faktor Risiko</h4>
        <div id="aiRiskContent">-</div>
      </div>

      <!-- Price Targets -->
      <div style="grid-column:1/-1; margin-top:20px;">
        <h4 style="color:#0d6efd; margin-bottom:10px;">📍 Target Harga & Entry/Exit Points</h4>
        <div class="price-targets">
          <div class="price-target">
            <label>Entry Price</label>
            <div class="value" id="aiEntryPrice">-</div>
          </div>
          <div class="price-target">
            <label>Take Profit</label>
            <div class="value" id="aiTakeProfitPrice" style="color:#28a745;">-</div>
          </div>
          <div class="price-target">
            <label>Stop Loss</label>
            <div class="value" id="aiStopLossPrice" style="color:#dc3545;">-</div>
          </div>
        </div>
      </div>

      <!-- Google Advanced Metrics -->
      <div id="aiAdvancedMetrics" style="grid-column:1/-1; margin-top:20px; display:none;">
        <div class="ai-analysis-section" style="border-left-color:#2563eb;">
          <h4>🧠 Google AI Advanced Metrics</h4>
          <div class="ai-advanced-grid">
            <div class="ai-advanced-item"><span class="k">Probabilitas Menang</span><span id="aiProbWin" class="v">-</span></div>
            <div class="ai-advanced-item"><span class="k">Risk / Reward</span><span id="aiRiskReward" class="v">-</span></div>
            <div class="ai-advanced-item"><span class="k">Horizon Waktu</span><span id="aiTimeHorizon" class="v">-</span></div>
          </div>
          <div style="margin-top:10px; font-size:13px; color:#1f2937;"><strong>Skenario Bull:</strong> <span id="aiScenarioBull">-</span></div>
          <div style="margin-top:6px; font-size:13px; color:#1f2937;"><strong>Skenario Base:</strong> <span id="aiScenarioBase">-</span></div>
          <div style="margin-top:6px; font-size:13px; color:#1f2937;"><strong>Skenario Bear:</strong> <span id="aiScenarioBear">-</span></div>
          <div style="margin-top:10px; font-size:13px; color:#1f2937;"><strong>Katalis Kunci:</strong><ul id="aiCatalysts" class="ai-advanced-list"></ul></div>
          <div style="margin-top:10px; font-size:13px; color:#1f2937;"><strong>Execution Plan:</strong><ul id="aiExecutionPlan" class="ai-advanced-list"></ul></div>
          <div style="margin-top:10px; font-size:13px; color:#1f2937;"><strong>AI Edge:</strong> <span id="aiEdge">-</span></div>
        </div>
      </div>

      <!-- Conclusion -->
      <div style="grid-column:1/-1; margin-top:20px;">
        <div class="ai-analysis-section">
          <h4>✅ Kesimpulan & Rekomendasi</h4>
          <div id="aiConclusionContent">-</div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div id="aiLoadingState" style="display:none;">
      <div class="ai-loading">
        <div class="ai-loading-spinner"></div>
        <div>Sedang melakukan analisis AI...</div>
        <small style="color:#999; margin-top:10px;">Ini mungkin memakan waktu 30-60 detik. Silakan tunggu...</small>
      </div>
    </div>

    <!-- Status Messages -->
    <div id="aiStatusMessage"></div>
  </div>

  <div class="technical-glossary panel" style="font-size:13px; color:#555;">
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
      fundPanel.innerHTML = html;
      fundPanel.style.display = '';
    } else {
      fundPanel.innerHTML = '<div style="font-size:13px;color:#64748b;">Data laporan keuangan eksternal belum tersedia.</div>';
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

      // Auto-run analysis right after chart data is loaded.
      runAutoAnalysis(symbol);
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
    
    const gemini_token = localStorage.getItem('gemini_api_token');
    if(gemini_token) document.getElementById('gemini_api_token').value = gemini_token;
  } catch(e){}
})();

// AI Analysis Functions
let aiAnalysisData = null;

function setEngineBadge(mode) {
  const badge = document.getElementById('aiEngineBadge');
  if (!badge) return;
  if (mode === 'GOOGLE') {
    badge.className = 'ai-engine-badge google';
    badge.innerText = 'Mode Google AI';
    return;
  }
  if (mode === 'FREE') {
    badge.className = 'ai-engine-badge free';
    badge.innerText = 'Mode Lokal';
    return;
  }
  badge.className = 'ai-engine-badge auto';
  badge.innerText = 'Mode Auto';
}

document.getElementById('btnAnalyzeFree').addEventListener('click', function() {
  const symbol = document.getElementById('symbol').value;
  if (!symbol) {
    alert('Silakan pilih saham terlebih dahulu');
    return;
  }
  performFreeAnalysis(symbol);
});

document.getElementById('btnAnalyzeGoogle').addEventListener('click', function() {
  const symbol = document.getElementById('symbol').value;
  if (!symbol) {
    alert('Silakan pilih saham terlebih dahulu');
    return;
  }
  const token = localStorage.getItem('gemini_api_token');
  if (!token) {
    document.getElementById('aiAnalysisModal').style.display = 'block';
    return;
  }
  performAiAnalysis(symbol, token, false);
});

document.getElementById('closeAiModal').addEventListener('click', function() {
  document.getElementById('aiAnalysisModal').style.display = 'none';
});

document.getElementById('saveAiConfig').addEventListener('click', function() {
  const token = document.getElementById('gemini_api_token').value.trim();
  const timeframe = document.getElementById('gemini_timeframe').value;
  
  if (!token) {
    const symbolNoToken = document.getElementById('symbol').value;
    document.getElementById('aiAnalysisModal').style.display = 'none';
    if (symbolNoToken) {
      document.getElementById('aiTimeframeSelect').value = timeframe;
      performFreeAnalysis(symbolNoToken);
      return;
    }
    alert('Silakan pilih saham terlebih dahulu');
    return;
  }
  
  localStorage.setItem('gemini_api_token', token);
  localStorage.setItem('gemini_analysis_timeframe', timeframe);
  document.getElementById('aiAnalysisModal').style.display = 'none';
  
  const symbol = document.getElementById('symbol').value;
  if (symbol) {
    document.getElementById('aiTimeframeSelect').value = timeframe;
    performAiAnalysis(symbol, token, false);
  }
});

document.getElementById('aiTimeframeSelect').addEventListener('change', function() {
  localStorage.setItem('gemini_analysis_timeframe', this.value);
  const symbol = document.getElementById('symbol').value;
  const token = localStorage.getItem('gemini_api_token');
  if (symbol && token) {
    performAiAnalysis(symbol, token, false);
  } else if (symbol) {
    performFreeAnalysis(symbol);
  }
});

document.getElementById('useFreeAnalysis').addEventListener('click', function() {
  const symbol = document.getElementById('symbol').value;
  document.getElementById('aiAnalysisModal').style.display = 'none';
  if (!symbol) {
    alert('Silakan pilih saham terlebih dahulu');
    return;
  }
  performFreeAnalysis(symbol);
});

function performAiAnalysis(symbol, api_token, allowFallbackToLocal = false) {
  const timeframe = document.getElementById('aiTimeframeSelect').value;
  const loadingState = document.getElementById('aiLoadingState');
  const contentState = document.getElementById('aiAnalysisContent');
  const recommendationContainer = document.getElementById('aiRecommendationContainer');
  const statusMessage = document.getElementById('aiStatusMessage');
  const panel = document.getElementById('aiAnalysisPanel');
  
  panel.style.display = 'block';
  setEngineBadge('GOOGLE');
  loadingState.style.display = 'block';
  contentState.style.display = 'none';
  recommendationContainer.style.display = 'none';
  statusMessage.innerHTML = '';
  
  fetch('gemini_analyze.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      symbol: symbol,
      timeframe: timeframe,
      api_token: api_token,
      mode: 'manual'
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      throw new Error(data.error);
    }
    
    aiAnalysisData = data;
    loadingState.style.display = 'none';
    displayAiAnalysis(data);
  })
  .catch(error => {
    loadingState.style.display = 'none';
    const errorMsg = error.message || 'Terjadi kesalahan saat melakukan analisis';
    const lower = String(errorMsg).toLowerCase();
    const isQuotaOrRateLimit = lower.includes('http 429') || lower.includes('quota') || lower.includes('rate limit');

    if (isQuotaOrRateLimit && allowFallbackToLocal) {
      statusMessage.innerHTML = '<div class="ai-error">⚠️ Kuota Google AI habis. Sistem otomatis beralih ke analisis lokal.</div>';
      console.warn('Gemini quota exceeded, fallback to local analysis. Detail:', errorMsg);
      performFreeAnalysis(symbol);
      return;
    }

    if (isQuotaOrRateLimit && !allowFallbackToLocal) {
      if (contentState) contentState.style.display = 'none';
      if (recommendationContainer) recommendationContainer.style.display = 'none';
      const adv = document.getElementById('aiAdvancedMetrics');
      if (adv) adv.style.display = 'none';
      statusMessage.innerHTML = '<div class="ai-error">❌ Google AI tidak berjalan karena kuota habis (429). Hasil lokal tidak ditampilkan agar tidak membingungkan. Silakan isi token lain / aktifkan billing.</div>';
      return;
    }

    statusMessage.innerHTML = '<div class="ai-error">❌ ' + errorMsg + '</div>';
    console.error('AI Analysis Error:', error);
  });
}

function performFreeAnalysis(symbol) {
  const timeframe = document.getElementById('aiTimeframeSelect').value;
  const loadingState = document.getElementById('aiLoadingState');
  const contentState = document.getElementById('aiAnalysisContent');
  const recommendationContainer = document.getElementById('aiRecommendationContainer');
  const statusMessage = document.getElementById('aiStatusMessage');
  const panel = document.getElementById('aiAnalysisPanel');

  panel.style.display = 'block';
  setEngineBadge('FREE');
  loadingState.style.display = 'block';
  contentState.style.display = 'none';
  recommendationContainer.style.display = 'none';
  statusMessage.innerHTML = '';

  fetch('free_ai_analyze.php?symbol=' + encodeURIComponent(symbol) + '&timeframe=' + encodeURIComponent(timeframe))
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        throw new Error(data.error);
      }
      aiAnalysisData = data;
      loadingState.style.display = 'none';
      displayAiAnalysis(data);
    })
    .catch(error => {
      loadingState.style.display = 'none';
      const errorMsg = error.message || 'Terjadi kesalahan saat melakukan analisis gratis';
      statusMessage.innerHTML = '<div class="ai-error">❌ ' + errorMsg + '</div>';
      console.error('Free Analysis Error:', error);
    });
}

function runAutoAnalysis(symbol) {
  if (!symbol) return;
  const token = localStorage.getItem('gemini_api_token');
  setEngineBadge('AUTO');
  if (token) {
    performAiAnalysis(symbol, token, true);
  } else {
    performFreeAnalysis(symbol);
  }
}

function displayAiAnalysis(data) {
  const analysis = data.analysis || {};
  const contentState = document.getElementById('aiAnalysisContent');
  const recommendationContainer = document.getElementById('aiRecommendationContainer');
  const statusMessage = document.getElementById('aiStatusMessage');
  const advancedPanel = document.getElementById('aiAdvancedMetrics');

  const _escapeHtml = (v) => String(v || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
  const _formatAnalysis = (v) => {
    const clean = _escapeHtml(v || 'N/A');
    return '<p>' + clean.replace(/\n/g, '<br>') + '</p>';
  };
  
  // Display technical analysis
  document.getElementById('aiTechnicalContent').innerHTML = _formatAnalysis(analysis.technical_analysis);
  
  // Display fundamental analysis
  document.getElementById('aiFundamentalContent').innerHTML = _formatAnalysis(analysis.fundamental_analysis);
  
  // Display sentiment analysis
  document.getElementById('aiSentimentContent').innerHTML = _formatAnalysis(analysis.sentiment_analysis);
  
  // Display risk factors
  document.getElementById('aiRiskContent').innerHTML = _formatAnalysis(analysis.risk_factors);
  
  // Display conclusion
  document.getElementById('aiConclusionContent').innerHTML = _formatAnalysis(analysis.conclusion);
  
  // Display price targets
  document.getElementById('aiEntryPrice').innerText = 
    analysis.entry_price ? 'Rp ' + Number(analysis.entry_price).toLocaleString('id-ID') : '-';
  document.getElementById('aiTakeProfitPrice').innerText = 
    analysis.take_profit ? 'Rp ' + Number(analysis.take_profit).toLocaleString('id-ID') : '-';
  document.getElementById('aiStopLossPrice').innerText = 
    analysis.stop_loss ? 'Rp ' + Number(analysis.stop_loss).toLocaleString('id-ID') : '-';
  
  // Display recommendation speedometer
  updateSpeedometer(analysis.recommendation, analysis.confidence_level);

  // Show Google-only advanced metrics panel.
  const isGoogle = String(data.engine || '').toUpperCase() === 'GOOGLE_AI';
  if (advancedPanel) {
    if (isGoogle) {
      const setText = (id, v) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = _escapeHtml(v || '-').replace(/\n/g, '<br>');
      };
      const setList = (id, arr) => {
        const el = document.getElementById(id);
        if (!el) return;
        const list = Array.isArray(arr) ? arr.filter(Boolean) : [];
        if (list.length === 0) {
          el.innerHTML = '<li>-</li>';
          return;
        }
        el.innerHTML = list.map(item => '<li>' + _escapeHtml(String(item)) + '</li>').join('');
      };

      setText('aiProbWin', analysis.probability_win_pct != null ? (analysis.probability_win_pct + '%') : '-');
      setText('aiRiskReward', analysis.risk_reward_ratio != null ? String(analysis.risk_reward_ratio) : '-');
      setText('aiTimeHorizon', analysis.time_horizon || '-');
      setText('aiScenarioBull', analysis.scenario_bull || '-');
      setText('aiScenarioBase', analysis.scenario_base || '-');
      setText('aiScenarioBear', analysis.scenario_bear || '-');
      setList('aiCatalysts', analysis.key_catalysts);
      setList('aiExecutionPlan', analysis.execution_plan);
      setText('aiEdge', analysis.ai_edge || '-');
      advancedPanel.style.display = '';
    } else {
      advancedPanel.style.display = 'none';
    }
  }
  
  // Show success message
  const timestamp = data.timestamp;
  const modelInfo = data.model_used ? (' - ' + data.model_used) : '';
  const engine = data.engine ? ' (' + data.engine + modelInfo + ')' : ' (GEMINI)';
  statusMessage.innerHTML = '<div class="ai-success">✅ Analisis berhasil dilakukan pada ' + timestamp + engine + '</div>';

  if (data.engine === 'FREE_LOCAL') {
    setEngineBadge('FREE');
  } else {
    setEngineBadge('GOOGLE');
  }
  
  contentState.style.display = 'grid';
  recommendationContainer.style.display = 'block';
}

function updateSpeedometer(recommendation, confidence) {
  const recommendationText = document.getElementById('aiRecommendationText');
  const recommendationValue = document.getElementById('aiRecommendationValue');
  const confidenceLevel = document.getElementById('aiConfidenceLevel');
  const speedometer = document.getElementById('aiSpeedometer');
  
  // Map recommendation to degree (0-180)
  const degreeMap = {
    'SELL': 0,
    'HOLD': 60,
    'BUY': 120,
    'HOT': 180
  };
  
  const colorMap = {
    'SELL': '#dc3545',
    'HOLD': '#6c757d',
    'BUY': '#28a745',
    'HOT': '#0d6efd'
  };
  
  const degree = degreeMap[recommendation] || 90;
  const color = colorMap[recommendation] || '#6c757d';
  
  // Create arrow with rotation
  speedometer.innerHTML = '';
  speedometer.style.background = 'conic-gradient(red 0deg, orange 45deg, yellow 90deg, lightgreen 135deg, green 180deg)';
  const arrow = document.createElement('div');
  arrow.style.position = 'absolute';
  arrow.style.bottom = '10px';
  arrow.style.left = '50%';
  arrow.style.transform = 'translateX(-50%) rotate(' + degree + 'deg)';
  arrow.style.width = '0';
  arrow.style.height = '0';
  arrow.style.borderLeft = '8px solid transparent';
  arrow.style.borderRight = '8px solid transparent';
  arrow.style.borderBottom = '12px solid #333';
  arrow.style.zIndex = '10';
  speedometer.appendChild(arrow);
  
  recommendationText.innerText = recommendation;
  recommendationText.style.color = color;
  recommendationValue.innerText = '🎯 ' + recommendation;
  recommendationValue.style.color = color;
  confidenceLevel.innerText = confidence || '-';
}

// Check if timeframe was saved
window.addEventListener('load', function() {
  const savedTimeframe = localStorage.getItem('gemini_analysis_timeframe');
  if (savedTimeframe) {
    document.getElementById('aiTimeframeSelect').value = savedTimeframe;
  }
});

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










