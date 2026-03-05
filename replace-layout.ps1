$content = Get-Content index.php -Raw

$searchHTML = '<div id="chart-container">
      <canvas id="chart"></canvas>
    </div>'

$replaceHTML = '<div style="display:flex; gap:20px; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap;">
      <div id="chart-container" style="flex: 1 1 70%; min-width:300px; height: 500px;">
        <canvas id="chart"></canvas>
      </div>
      <div class="panel" style="flex: 1 1 25%; max-width:350px; height:500px; overflow-y:auto; margin:0; box-sizing:border-box; border-left: 4px solid #0d6efd; padding: 15px;">
        <h4 style="margin: 0 0 10px 0; padding-bottom:10px; border-bottom:1px solid #ddd; color:#0d6efd;">?? Berita Terkini</h4>
        <div id="news-list" style="font-size:13px; line-height:1.4;">
           <p style="color:#666; font-style:italic;">Memuat berita...</p>
        </div>
      </div>
    </div>'

$content = $content.Replace($searchHTML, $replaceHTML)

Set-Content index.php $content
