$content = Get-Content index.php -Raw

$searchJS = 'async function render(symbol){
        if(liveUpdateInterval) clearInterval(liveUpdateInterval);
        await waitForChartReady().catch(e=>{ console.warn(e); /* continue, will error later if plugin missing */ });
        // Load analysis (historical prices) first to render chart'

$replaceJS = 'function fetchNews(symbol) {
        const newsContainer = document.getElementById(''news-list'');
        newsContainer.innerHTML = ''<p style="color:#666; font-style:italic;">Memuat berita...</p>'';
        fetch(''fetch_news.php?symbol=''+encodeURIComponent(symbol)).then(r=>r.json()).then(data=>{
            if(data.error || !data.news || data.news.length === 0) {
                newsContainer.innerHTML = ''<p style="color:#888; font-style:italic;">Tidak ada berita terbaru.</p>'';
                return;
            }
            let html = '''';
            data.news.forEach(item => {
                const dateRaw = new Date(item.pubDate);
                const dateStr = !isNaN(dateRaw) ? dateRaw.toLocaleDateString(''id-ID'', { day: ''numeric'', month: ''short'', year: ''numeric'', hour: ''2-digit'', minute: ''2-digit'' }) : item.pubDate;
                html += `
                    <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                        <a href="${item.link}" target="_blank" style="color:#0d6efd; text-decoration:none; font-weight:bold; display:block; margin-bottom:4px;">${item.title}</a>
                        <div style="font-size:11px; color:#888; margin-bottom:4px;">${dateStr}</div>
                        <div style="font-size:12px; color:#555; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">${item.description}</div>
                    </div>
                `;
            });
            newsContainer.innerHTML = html;
        }).catch(e=>{
            newsContainer.innerHTML = ''<p style="color:red; font-style:italic;">Gagal memuat berita.</p>'';
        });
      }

      async function render(symbol){
        if(liveUpdateInterval) clearInterval(liveUpdateInterval);
        await waitForChartReady().catch(e=>{ console.warn(e); /* continue, will error later if plugin missing */ });
        
        // Fetch News for the side panel
        fetchNews(symbol);
        
        // Load analysis (historical prices) first to render chart'

$content = $content.Replace($searchJS, $replaceJS)

Set-Content index.php $content
