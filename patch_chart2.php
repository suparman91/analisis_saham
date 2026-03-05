<?php
$f = "chart.php";
$c = file_get_contents($f);

// Make chart container and news panel responsive + menu
$styles = <<<EOT
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
EOT;

// Replace older CSS for top-menu:
$c = preg_replace("/\/\* Navigation Menu \*\/.*\.btn-settings:hover \{ background: #64748b; \}/s", "/* Navigation Menu */\n" . $styles . "\n    .btn-settings { background: #475569; border:none; cursor:pointer; color: #fff; padding: 8px 15px; border-radius: 5px; font-weight: 600; font-size: 14px; transition: background 0.2s; width:100%; }\n    .btn-settings:hover { background: #64748b; }", $c);

// Add class to the controls div
$c = str_replace("<div style=\"margin-bottom:15px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; display:flex; align-items:center; gap: 10px;\">", "<div class=\"controls-container\" style=\"margin-bottom:15px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; display:flex; align-items:center; gap: 10px;\">", $c);

// Also add a dropdown for TA
$ta_dropdown = <<<EOT
    <div style="margin-left:auto; display:flex; gap:10px; align-items:center;">
        <strong style="white-space:nowrap;font-size:13px;">Tampilkan Indikator:</strong>
        <select id="indicatorToggle" multiple placeholder="Pilih Indikator..."></select>
    </div>
EOT;

// Insert TA dropdown right after reset zoom button
$c = str_replace("<button id=\"btnResetZoom\" style=\"margin-left:12px; padding:8px 16px; border-radius:4px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;\">?? Reset Zoom</button>", "<button id=\"btnResetZoom\" style=\"margin-left:12px; padding:8px 16px; border-radius:4px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;\">?? Reset Zoom</button>\n" . $ta_dropdown, $c);

file_put_contents($f, $c);
echo "Patched styles and layout.";
?>
