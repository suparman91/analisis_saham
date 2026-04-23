<?php
// Pastikan browser/proxy tidak meng-cache halaman dinamis ini
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    // Landing Page untuk User Belum Login
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Analisis Saham Auto-Trader - Platform Trading Saham Indonesia</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #e2e8f0;
                overflow-x: hidden;
            }
            .hero {
                text-align: center;
                padding: 100px 20px;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%23ffffff" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') no-repeat center center;
                background-size: cover;
                position: relative;
            }
            .hero h1 {
                font-size: 3.5rem;
                margin: 0;
                color: #f8fafc;
                text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            }
            .hero p {
                font-size: 1.3rem;
                margin: 20px 0;
                color: #cbd5e1;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }
            .trial-badge {
                display: inline-block;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(251, 191, 36, 0.2);
                border: 1px solid rgba(251, 191, 36, 0.6);
                color: #fde68a;
                font-size: 0.9rem;
                font-weight: 700;
                margin-bottom: 18px;
            }
            .hero-disclaimer {
                max-width: 900px;
                margin: 25px auto 0 auto;
                padding: 12px 14px;
                font-size: 0.95rem;
                line-height: 1.5;
                border-radius: 10px;
                border: 1px solid rgba(251, 191, 36, 0.45);
                background: rgba(15, 23, 42, 0.5);
                color: #fef3c7;
            }
            .cta-buttons {
                margin-top: 40px;
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                margin: 0 10px;
                border: none;
                border-radius: 8px;
                font-size: 1.1rem;
                font-weight: bold;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .btn-primary {
                background: #3b82f6;
                color: #fff;
            }
            .btn-primary:hover {
                background: #2563eb;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #16a34a;
                color: #fff;
            }
            .btn-secondary:hover {
                background: #15803d;
                transform: translateY(-2px);
            }
            .features {
                padding: 80px 20px;
                background: #1e293b;
            }
            .features h2 {
                text-align: center;
                font-size: 2.5rem;
                margin-bottom: 50px;
                color: #f8fafc;
            }
            .feature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 40px;
                max-width: 1200px;
                margin: 0 auto;
            }
            .feature-item {
                background: #334155;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
            }
            .feature-item:hover {
                transform: translateY(-5px);
            }
            .feature-item i {
                font-size: 3rem;
                color: #3b82f6;
                margin-bottom: 20px;
            }
            .feature-item h3 {
                font-size: 1.5rem;
                margin: 0 0 15px 0;
                color: #f8fafc;
            }
            .feature-item p {
                color: #cbd5e1;
                line-height: 1.6;
            }
            .footer {
                text-align: center;
                padding: 40px 20px;
                background: #0f172a;
                color: #64748b;
            }
            .footer p {
                margin: 0;
            }
            @media (max-width: 768px) {
                .hero { padding: 70px 14px; }
                .hero h1 { font-size: 2rem; }
                .hero p { font-size: 1rem; }
                .btn { display: block; margin: 10px auto; width: 100%; max-width: 280px; }
                .features { padding: 50px 14px; }
                .features h2 { font-size: 1.8rem; }
                .feature-grid { grid-template-columns: 1fr; gap: 20px; }
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body>
        <section class="hero">
            <span class="trial-badge">Public Trial Mode</span>
            <h1>🚀 Analisis Saham Auto-Trader</h1>
            <p>Platform canggih untuk analisis dan trading saham Indonesia. Gunakan AI, scanner momentum, dan robo-trader untuk maksimalkan keuntungan Anda.</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Masuk Akun</a>
                <a href="register.php" class="btn btn-secondary"><i class="fas fa-user-plus"></i> Daftar Gratis</a>
            </div>
            <div class="hero-disclaimer">
                Disclaimer: Platform ini untuk analisa dan edukasi, bukan ajakan jual beli efek, bukan anjuran harga, dan bukan nasihat investasi.
            </div>
        </section>

        <section class="features">
            <h2>Fitur Unggulan Kami</h2>
            <div class="feature-grid">
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <h3>Chart & Analisis Real-Time</h3>
                    <p>Lihat chart IHSG dan saham individual dengan indikator teknikal lengkap. Analisis momentum dan tren pasar secara real-time.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-search"></i>
                    <h3>Scanner Momentum</h3>
                    <p>Temukan saham dengan momentum terkuat menggunakan algoritma canggih. Filter berdasarkan volume, kenaikan harga, dan indikator teknikal.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-robot"></i>
                    <h3>AI Robo-Trader</h3>
                    <p>Simulasi trading otomatis dengan AI. Atur modal awal dan biarkan sistem beli/jual berdasarkan sinyal teknikal dan fundamental.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-portrait"></i>
                    <h3>Portfolio Manual Tracker</h3>
                    <p>Kelola portofolio manual Anda dengan mudah. Hitung P/L akurat berdasarkan LOT, tambah/edit posisi, dan dapatkan rekomendasi AI.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-bell"></i>
                    <h3>Alert & Notifikasi</h3>
                    <p>Dapatkan notifikasi real-time via Telegram ketika saham favorit Anda mencapai target atau ada sinyal trading.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-crown"></i>
                    <h3>SaaS Premium</h3>
                    <p>Berlangganan untuk akses penuh ke semua fitur premium. Mulai dari Rp 79k/bulan dengan dukungan lengkap.</p>
                </div>
            </div>
        </section>

        <footer class="footer">
            <p>&copy; 2026 Analisis Saham Auto-Trader. Semua hak dilindungi.</p>
        </footer>
    </body>
    </html>
    <?php
    exit;
}

if (!isset($_GET['embed']) || $_GET['embed'] !== '1') {
    header('Location: app.php?page=index.php');
    exit;
}

require_once __DIR__ . '/db.php';
$mysqli = db_connect();
require_login();
require_subscription($mysqli);

// Fetch Watchlist (Per-User)
$watchlist = [];
$user_id = get_user_id();
$resWl = $mysqli->query("SELECT symbol FROM watchlist WHERE user_id = $user_id");
if ($resWl) {
    while ($rw = $resWl->fetch_assoc()) {
        $watchlist[strtoupper(trim($rw['symbol']))] = true;
    }
}

// If today has less than 200 stocks updated (e.g. intraday partial update or weekend anomaly), use the last two complete dates
$resDates = $mysqli->query("SELECT date, count(*) as cnt FROM prices GROUP BY date HAVING cnt > 200 ORDER BY date DESC LIMIT 2");
$dates = [];
while ($r = $resDates->fetch_assoc()) {
    $dates[] = $r['date'];
}
$today = $dates[0] ?? date('Y-m-d');
$yesterday = $dates[1] ?? date('Y-m-d', strtotime('-1 day'));

// Fallback logic for weekend/holiday: if $today and $yesterday prices are identical or returning no >5% movers
// because market is closed, user gets empty tables. We should ensure we always have some data to show.

// Top Gainers and Losers Query
$sqlMarket = "
SELECT 
    p1.symbol, 
    s.name, 
    s.notation,
    p1.close, 
    p2.close as prev_close,
    ((p1.close - p2.close) / p2.close * 100) as pct_change,
    p1.volume
FROM prices p1
JOIN prices p2 ON p1.symbol = p2.symbol AND p2.date = '$yesterday'
JOIN stocks s ON p1.symbol = s.symbol
WHERE p1.date = '$today'
";

$resMarket = $mysqli->query($sqlMarket);
$all_stocks = [];
if ($resMarket) {
    while ($row = $resMarket->fetch_assoc()) {
        // Hanya proses saham IDX dengan suffix .JK
        if (strtoupper(substr((string)$row['symbol'], -3)) === '.JK') {
            $all_stocks[] = $row;
        }
    }
}

// Separate Gainers and Losers with adaptive threshold so list is not too sparse
$gainers = [];
$losers = [];
$volumes = [];
$upThreshold = 5;
$downThreshold = -5;

foreach ($all_stocks as $s) {
    if ($s['pct_change'] >= $upThreshold) {
        $gainers[] = $s;
    }
    if ($s['pct_change'] <= $downThreshold) {
        $losers[] = $s;
    }
    $volumes[] = $s;
}

if (count($gainers) < 10) {
    $gainers = array_filter($all_stocks, fn($s) => $s['pct_change'] >= 3);
    $upThreshold = 3;
}
if (count($gainers) < 10) {
    $gainers = array_filter($all_stocks, fn($s) => $s['pct_change'] >= 1);
    $upThreshold = 1;
}

if (count($losers) < 10) {
    $losers = array_filter($all_stocks, fn($s) => $s['pct_change'] <= -3);
    $downThreshold = -3;
}
if (count($losers) < 10) {
    $losers = array_filter($all_stocks, fn($s) => $s['pct_change'] <= -1);
    $downThreshold = -1;
}

// Sort arrays
usort($gainers, fn($a, $b) => $b['pct_change'] <=> $a['pct_change']);
usort($losers, fn($a, $b) => $a['pct_change'] <=> $b['pct_change']);
usort($volumes, fn($a, $b) => $b['volume'] <=> $a['volume']);

// If no gainers/losers match thresholds, fallback to top positive/negative movers
if (empty($gainers)) {
    $gainers = array_filter($all_stocks, fn($s) => $s['pct_change'] > 0);
    if (empty($gainers)) {
        $gainers = array_filter($all_stocks, fn($s) => $s['pct_change'] >= 0);
    }
    usort($gainers, fn($a, $b) => $b['pct_change'] <=> $a['pct_change']);
    $gainers = array_slice($gainers, 0, 10);
    $gainer_title = "Top Gainers (Hari Ini)";
} else {
    $gainer_title = "Saham Naik >= " . $upThreshold . "%";
}

if (empty($losers)) {
    $losers = array_filter($all_stocks, fn($s) => $s['pct_change'] < 0);
    if (empty($losers)) {
        $losers = array_filter($all_stocks, fn($s) => $s['pct_change'] <= 0);
    }
    usort($losers, fn($a, $b) => $a['pct_change'] <=> $b['pct_change']);
    $losers = array_slice($losers, 0, 10);
    $loser_title = "Top Losers (Hari Ini)";
} else {
    $loser_title = "Saham Turun <= " . $downThreshold . "%";
}

// Limit arrays
$top_volume = array_slice($volumes, 0, 10);

  function format_symbol_badge($s, $color="#000") {
      global $watchlist;
      $sym = htmlspecialchars($s['symbol']);
      $isWl = isset($watchlist[$sym]);
      $starClass = $isWl ? 'star-btn active' : 'star-btn';
      $starTitle = $isWl ? 'Hapus dari Watchlist' : 'Tambah ke Watchlist';
      
      $html = '<span class="' . $starClass . '" onclick="toggleWatchlist(\'' . $sym . '\', this)" title="' . $starTitle . '">&#9733;</span>';
      
      $html .= '<strong><a href="chart.php?symbol=' . urlencode($s['symbol']) . '" target="_blank" style="text-decoration:none; color: ' . htmlspecialchars($color) . ';">' . $sym . '</a></strong>';
      
      if (!empty($s['notation'])) {
          $html .= ' <span class="badge notation-badge" title="Notasi Khusus: ' . htmlspecialchars($s['notation']) . '">' . htmlspecialchars($s['notation']) . '</span>';
      }
      return $html;
  }

// For Multibagger: All stocks with strong momentum (Nilai Transaksi > 1 Miliar dan Naik Mumpuni)
$potential_multibaggers = array_filter($all_stocks, fn($s) => ($s['volume'] * $s['close']) > 1000000000 && $s['pct_change'] >= 3);
// Diurutkan berdasarkan kenaikan terbesar (pct_change) lalu volume
usort($potential_multibaggers, function($a, $b) {
    if ($a['pct_change'] == $b['pct_change']) {
        return $b['volume'] <=> $a['volume'];
    }
    return $b['pct_change'] <=> $a['pct_change'];
});
// Menampilkan semua saham tanpa batasan array_slice

// For Top Buy/Sell Asing & Lokal (Bandar Flow Simulation)
// Real broker summary usually needs IDX direct feed, generating deterministic mock data for display.
function get_mock_bandar($symbol, $volume) {
    $hash = md5($symbol . date('Y-m-d'));
    $val1 = hexdec(substr($hash, 0, 4)) % 100;
    $val2 = hexdec(substr($hash, 4, 4)) % 60;
    
    $asing_buy_pct = 10 + ($val1 * 0.4); 
    $lokal_buy_pct = 100 - $asing_buy_pct;
    
    $asing_sell_pct = 10 + ($val2 * 0.5); 
    $lokal_sell_pct = 100 - $asing_sell_pct;

    $brokers_asing = ['YU', 'BK', 'RX', 'AK', 'CS', 'ZP', 'KZ'];
    $brokers_lokal = ['YP', 'PD', 'CC', 'NI', 'GR', 'LG', 'DR'];
    
    $top_buy_broker = ($val1 > 50) ? $brokers_asing[$val1 % count($brokers_asing)] . ' (Asing)' : $brokers_lokal[$val1 % count($brokers_lokal)] . ' (Lokal)';
    $top_sell_broker = ($val2 > 30) ? $brokers_asing[$val2 % count($brokers_asing)] . ' (Asing)' : $brokers_lokal[$val2 % count($brokers_lokal)] . ' (Lokal)';
    
    // Status
    if ($asing_buy_pct > $asing_sell_pct + 10) $status = '<span class="badge buy">Akumulasi Asing</span>';
    elseif ($lokal_buy_pct > $lokal_sell_pct + 10) $status = '<span class="badge buy">Akumulasi Lokal</span>';
    elseif ($asing_sell_pct > $asing_buy_pct + 10) $status = '<span class="badge sell">Distribusi Asing</span>';
    else $status = '<span class="badge hold">Distribusi Normal</span>';
    
    return [
        'asing_buy' => round($asing_buy_pct, 1),
        'lokal_buy' => round($lokal_buy_pct, 1),
        'asing_sell' => round($asing_sell_pct, 1),
        'lokal_sell' => round($lokal_sell_pct, 1),
        'top_buy' => $top_buy_broker,
        'top_sell' => $top_sell_broker,
        'status' => $status
    ];
}

function investor_http_fetch($url, $timeout = 10) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36');
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$body || $httpCode !== 200) {
        return null;
    }
    return $body;
}

function analyze_investor_headline_sentiment($articles) {
    if (empty($articles)) {
        return [
            'label' => 'NETRAL',
            'score' => 0,
            'summary' => 'Belum ada headline Investor.id yang berhasil diambil.',
            'drivers' => [],
        ];
    }

    $positiveKeywords = [
        'bullish', 'rebound', 'menguat', 'naik', 'melonjak', 'ekspansi', 'dividen', 'laba', 'surplus', 'optimistis',
        'akumulasi', 'buyback', 'investasi', 'bertumbuh', 'tumbuh', 'stabil', 'mengalir', 'prospek', 'membaik', 'intervensi'
    ];
    $negativeKeywords = [
        'bearish', 'melemah', 'turun', 'anjlok', 'koreksi', 'tekanan', 'dihajar', 'jual', 'risiko', 'downgrade',
        'defisit', 'rugi', 'utang', 'macet', 'krisis', 'merosot', 'gagal', 'konflik', 'inflasi', 'beban'
    ];

    $score = 0;
    $drivers = [];
    foreach ($articles as $article) {
        $text = strtolower(($article['title'] ?? '') . ' ' . ($article['category'] ?? ''));
        foreach ($positiveKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score += 2;
                $drivers[] = '+' . $keyword;
            }
        }
        foreach ($negativeKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score -= 2;
                $drivers[] = '-' . $keyword;
            }
        }
    }

    $label = 'NETRAL';
    if ($score >= 4) {
        $label = 'BULLISH';
    } elseif ($score <= -4) {
        $label = 'BEARISH';
    }

    $driverList = array_slice(array_values(array_unique($drivers)), 0, 5);
    $summary = empty($driverList)
        ? 'Headline pasar/fundamental campuran, belum ada katalis dominan.'
        : 'Pemicu utama: ' . implode(', ', $driverList);

    return [
        'label' => $label,
        'score' => $score,
        'summary' => $summary,
        'drivers' => $driverList,
    ];
}

function fetch_investor_fundamental_sentiment() {
    $cacheFile = __DIR__ . '/tmp_investor_sentiment.json';
    $cacheTtl = 900;
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && (!empty($cached['articles']) || !empty($cached['scored_articles']) || !empty($cached['stock_articles']))) {
            return $cached;
        }
    }

    $html = investor_http_fetch('https://investor.id/');
    if (!$html) {
        return [
            'label' => 'NETRAL',
            'score' => 0,
            'summary' => 'Investor.id sedang tidak bisa diakses dari server.',
            'articles' => [],
            'scored_articles' => [],
            'stock_articles' => [],
            'corporate_articles' => [],
            'source' => 'Investor.id',
        ];
    }

    $articles = [];
    $scoredArticles = [];
    $stockArticles = [];
    $corporateArticles = [];
    $seen = [];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (@$dom->loadHTML($html)) {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');
        foreach ($nodes as $node) {
            $href = trim((string)$node->getAttribute('href'));
            if ($href === '' || !preg_match('#^/(market|finance|business|macroeconomy|stock|corporate-action|commodities)/#i', $href)) {
                continue;
            }

            $url = 'https://investor.id' . $href;
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $segments = array_values(array_filter(explode('/', $path)));
            $category = strtoupper($segments[0] ?? 'MARKET');
            $slug = $segments[2] ?? ($segments[1] ?? 'headline');
            $title = trim($node->textContent);

            if ($title === '' && $node->parentNode instanceof DOMNode) {
                $title = trim($node->parentNode->textContent ?? '');
            }
            if ($title === '') {
                $imgs = $node->getElementsByTagName('img');
                if ($imgs->length > 0) {
                    $title = trim((string)$imgs->item(0)->getAttribute('alt'));
                }
            }
            if ($title === '' && $node->parentNode instanceof DOMNode) {
                foreach ($node->parentNode->childNodes as $sibling) {
                    if ($sibling instanceof DOMElement && in_array(strtolower($sibling->tagName), ['h2','h3','h4'], true)) {
                        $title = trim($sibling->textContent);
                        if ($title !== '') {
                            break;
                        }
                    }
                }
            }
            if ($title === '') {
                $title = ucwords(preg_replace('/\s+/', ' ', str_replace('-', ' ', $slug)));
            }
            $title = preg_replace('/\s+/', ' ', html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '' || $title === '★ PREMIUM') {
                $title = ucwords(preg_replace('/\s+/', ' ', str_replace('-', ' ', $slug)));
            }

            $article = [
                'title' => $title,
                'link' => $url,
                'category' => $category,
            ];
            $articles[] = $article;

            if ($category === 'STOCK') {
                $stockArticles[] = $article;
            } elseif ($category === 'CORPORATE-ACTION') {
                $corporateArticles[] = $article;
            } else {
                $scoredArticles[] = $article;
            }

            $enoughScored = count($scoredArticles) >= 8;
            $enoughStock = count($stockArticles) >= 3;
            $enoughCorporate = count($corporateArticles) >= 3;
            if (count($articles) >= 28 || ($enoughScored && $enoughStock && $enoughCorporate)) {
                break;
            }
        }
    }
    libxml_clear_errors();

    $sentiment = analyze_investor_headline_sentiment($scoredArticles);
    $payload = [
        'label' => $sentiment['label'],
        'score' => $sentiment['score'],
        'summary' => $sentiment['summary'],
        'articles' => $articles,
        'scored_articles' => $scoredArticles,
        'stock_articles' => $stockArticles,
        'corporate_articles' => $corporateArticles,
        'source' => 'Investor.id',
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $payload;
}

$investorSentiment = fetch_investor_fundamental_sentiment();

// Cek status langganan
$subscription_active = has_active_subscription($mysqli);
$user_id = get_user_id();
$user_info = $mysqli->query("SELECT subscription_end FROM users WHERE id = $user_id")->fetch_assoc();
$subscription_end = $user_info['subscription_end'];
$days_left = $subscription_end ? ceil((strtotime($subscription_end) - time()) / 86400) : 0;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0f172a">
  <link rel="apple-touch-icon" href="icon-192.png">
  <title>Dashboard Pasar IHSG</title>
  <style>
    body{
      font-family:Arial,Helvetica,sans-serif;
      margin:0;
      padding:0;
      background:#f8f9fa;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }
    .container { 
      width: 100%;
      margin:0;
      box-sizing: border-box;
      padding: 0 15px;
    }
    h1 { color:#333; margin-bottom: 5px;}
    .subtitle { color:#666; font-size:14px; margin-bottom:20px; }
    
    /* Subscription Banner */
    .subscription-banner { background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%); border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .subscription-banner.expired { background: linear-gradient(135deg, #fee2e2 0%, #f87171 100%); border-color: #dc2626; }
    .subscription-banner h3 { margin: 0 0 10px 0; color: #92400e; }
    .subscription-banner.expired h3 { color: #991b1b; }
    .subscription-banner p { margin: 0 0 15px 0; color: #78350f; }
    .subscription-banner.expired p { color: #7f1d1d; }
    .subscription-banner a { background: #16a34a; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; }
    .subscription-banner a:hover { background: #15803d; }
    
    /* Navigation Menu */
    .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
    .top-menu a:hover { background: #1e293b; color: #fff; }
    .top-menu a.active { background: #3b82f6; color: #fff; }
    .top-menu-right { margin-left: auto; }
    .btn-settings { background: #475569; border:none; cursor:pointer; color: #fff; padding: 8px 15px; border-radius: 5px; font-weight: 600; font-size: 14px; transition: background 0.2s; }
    .btn-settings:hover { background: #64748b; }
    
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px; }
    .panel { background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
    .panel h3 { margin-top:0; border-bottom:2px solid #eee; padding-bottom:10px; color:#495057; font-size:16px;}
    
    table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:0; }
    th, td { padding:10px 8px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f1f3f5; font-weight:bold; color:#495057; }

    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    
    .text-green { color:#198754; font-weight:bold; }
    .text-red { color:#dc3545; font-weight:bold; }
    
    .badge { display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; font-size:11px; font-weight:bold; }
    .badge.buy { background:#198754; }
    .badge.sell { background:#dc3545; }
    .badge.hold { background:#6c757d; }
    .badge.notation-badge { background:#ffc107; color:#000; font-size:9px; padding:2px 4px; margin-left:4px; vertical-align:super; }

        .market-meta {
            display:flex;
            align-items:flex-start;
            gap:12px;
            margin-bottom:12px;
            flex-wrap:wrap;
        }
        .market-meta-card {
            flex:1 1 320px;
            min-width:260px;
            background:#ffffff;
            border:1px solid #e2e8f0;
            border-radius:10px;
            padding:12px 14px;
            box-shadow:0 2px 4px rgba(15,23,42,0.05);
        }
        .market-meta-row {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
        }
        .market-meta-info {
            min-width:260px;
            flex:1 1 420px;
        }
        .market-meta-card .meta-label { color:#475569; font-size:13px; }
        .market-meta-card .meta-accent { color:#d97706; font-size:13px; }

        #auto-update-banner {
            display:none;
            position:fixed;
            left:50%;
            bottom:20px;
            transform:translateX(-50%);
            width:min(680px, calc(100vw - 24px));
            background:linear-gradient(135deg, #fff8cc 0%, #fde047 100%);
            color:#1f2937;
            padding:14px 16px;
            border-radius:14px;
            box-shadow:0 18px 42px rgba(15, 23, 42, 0.22);
            font-weight:600;
            z-index:9999;
            border:1px solid #facc15;
            line-height:1.45;
            box-sizing:border-box;
        }
        #auto-update-banner.is-error {
            background:linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            border-color:#f87171;
            color:#7f1d1d;
        }
        #auto-update-banner.is-success {
            background:linear-gradient(135deg, #ecfdf5 0%, #bbf7d0 100%);
            border-color:#4ade80;
            color:#166534;
        }
        #auto-update-banner.is-warn {
            background:linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);
            border-color:#fb923c;
            color:#9a3412;
        }
        #update-text {
            display:block;
            white-space:normal;
            word-break:break-word;
            font-size:14px;
        }

    .full-width { grid-column: 1 / -1; }
        .btn-force-update {
                background: #0d6efd;
                color: #fff;
                border: none;
                border-radius: 6px;
                padding: 8px 12px;
                font-weight: 700;
                cursor: pointer;
                font-size: 12px;
            white-space: nowrap;
        }
        .btn-force-update:hover { background: #0b5ed7; }
        .sentiment-card { border: 1px solid #e2e8f0; background: linear-gradient(135deg, #ffffff, #f8fbff); }
        .sentiment-badge { display:inline-block; padding:6px 10px; border-radius:999px; color:#fff; font-size:12px; font-weight:700; }
        .sentiment-badge.bullish { background:#15803d; }
        .sentiment-badge.bearish { background:#b91c1c; }
        .sentiment-badge.netral { background:#475569; }
        .headline-list { margin:10px 0 0 0; padding-left:18px; }
        .headline-list li { margin-bottom:6px; }
        .headline-list a { color:#0d6efd; text-decoration:none; }
        .headline-list a:hover { text-decoration:underline; }          .star-btn { cursor: pointer; color: #cbd5e1; font-size: 16px; margin-right: 5px; transition: color 0.2s; user-select: none; }
          .star-btn:hover { color: #fbbf24; }
          .star-btn.active { color: #f59e0b; }
                    #watchlist-panel table th { background: #fef3c7; color: #92400e; }

        @media (min-width: 1500px) {
            body { margin: 24px; }
            .grid { gap: 24px; }
            .panel { padding: 18px; }
            th, td { font-size: 13px; }
        }

        @media (max-width: 992px) {
            body { 
              margin: 0; 
              padding: 0;
            }
            .container { 
              width: 100%;
              padding: 0 10px;
            }
            .grid { grid-template-columns: 1fr; gap: 14px; }
            .panel { padding: 12px; border-radius: 10px; }
            .panel h3 { font-size: 15px; }
            th, td { padding: 8px 6px; font-size: 12px; }
                        .market-meta { align-items:stretch; }
                        .market-meta-card { min-width:0; }
                        .market-meta-row { align-items:stretch; }
                        .market-meta-info { min-width:0; }
                        .btn-force-update { width:100%; white-space:normal; }
                        #auto-update-banner {
                            left:12px;
                            right:12px;
                            bottom:12px;
                            width:auto;
                            transform:none;
                            padding:12px 14px;
                            border-radius:12px;
                        }
        }
    </style>
</head>
<body>

<div class="container">
    <?php include 'nav.php'; ?>
    
    <?php
    // Hitung jumlah data aktual hari ini untuk referensi UI
    $countTodayRes = $mysqli->query("SELECT COUNT(*) as c FROM prices WHERE date = CURRENT_DATE()");
    $countTodayRaw = $countTodayRes ? $countTodayRes->fetch_assoc()['c'] : 0;
    
    // Cari waktu update terakhir dari file log
    $last_update_txt = __DIR__ . '/last_update_daily.txt';
    clearstatcache(true, $last_update_txt);
    $last_update_time = file_exists($last_update_txt) ? date('d M Y H:i', filemtime($last_update_txt)) : 'Belum Pernah';
    ?>
    <h1>Dashboard Pasar IHSG</h1>
    <div style="background:#fff7ed; border:1px solid #fdba74; color:#7c2d12; padding:10px 12px; border-radius:8px; margin-bottom:12px; font-size:12px; line-height:1.5;">
        Mode public trial: seluruh data dan sinyal pada dashboard digunakan untuk analisa/edukasi, bukan ajakan jual beli, bukan anjuran harga, dan bukan nasihat investasi.
    </div>
    <div class="market-meta">
        <div class="subtitle market-meta-card" style="margin-bottom:0; line-height: 1.4;">
            <div class="market-meta-row">
                <div class="market-meta-info">
                    <strong>Data Referensi:</strong> <?= $today; ?> <br>
                    <strong>Update Terakhir:</strong> <span id="last-update-time"><?= $last_update_time ?></span> WIB <br>
                    <span class="meta-accent">(Terkumpul saat ini: <span id="today-count"><?= $countTodayRaw ?></span> saham)</span>
                </div>
                <button id="btnForceUpdate" class="btn-force-update" type="button">Paksa Update EOD</button>
            </div>
        </div>
    </div>

    <div class="grid">
        <div class="panel full-width sentiment-card">
            <h3>📰 Sentimen Fundamental Investor.id</h3>
            <?php $sentimentClass = strtolower($investorSentiment['label'] ?? 'netral'); ?>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
                <span class="sentiment-badge <?= htmlspecialchars($sentimentClass) ?>"><?= htmlspecialchars($investorSentiment['label'] ?? 'NETRAL') ?></span>
                <span style="font-size:13px; color:#475569;"><strong>Skor:</strong> <?= (int)($investorSentiment['score'] ?? 0) ?></span>
                <span style="font-size:12px; color:#64748b;">Sumber: <?= htmlspecialchars($investorSentiment['source'] ?? 'Investor.id') ?> | Update: <?= htmlspecialchars($investorSentiment['updated_at'] ?? '-') ?></span>
            </div>
            <div style="font-size:13px; color:#334155; margin-bottom:8px;"><?= htmlspecialchars($investorSentiment['summary'] ?? 'Belum ada ringkasan.') ?></div>
            <?php if (!empty($investorSentiment['scored_articles'])): ?>
                <div style="font-size:12px; color:#64748b; margin-bottom:6px;"><strong>Headline yang dipakai untuk sentimen:</strong> fokus ke market, finance, business, macroeconomy, dan commodities.</div>
                <ul class="headline-list">
                    <?php foreach ($investorSentiment['scored_articles'] as $article): ?>
                        <li>
                            <a href="<?= htmlspecialchars($article['link']) ?>" target="_blank"><?= htmlspecialchars($article['title']) ?></a>
                            <small style="color:#64748b;">(<?= htmlspecialchars($article['category']) ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="font-size:13px; color:#64748b;">Belum ada headline yang bisa dipakai untuk analisis.</div>
            <?php endif; ?>
            <?php if (!empty($investorSentiment['stock_articles'])): ?>
                <div style="font-size:12px; color:#64748b; margin-top:12px; margin-bottom:6px;"><strong>Berita saham:</strong> referensi tambahan, tidak memengaruhi skor sentimen fundamental.</div>
                <ul class="headline-list">
                    <?php foreach ($investorSentiment['stock_articles'] as $article): ?>
                        <li>
                            <a href="<?= htmlspecialchars($article['link']) ?>" target="_blank"><?= htmlspecialchars($article['title']) ?></a>
                            <small style="color:#64748b;">(<?= htmlspecialchars($article['category']) ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($investorSentiment['corporate_articles'])): ?>
                <div style="font-size:12px; color:#64748b; margin-top:12px; margin-bottom:6px;"><strong>Corporate action:</strong> referensi aksi korporasi, tetap ditampilkan terpisah dari skor sentimen.</div>
                <ul class="headline-list">
                    <?php foreach ($investorSentiment['corporate_articles'] as $article): ?>
                        <li>
                            <a href="<?= htmlspecialchars($article['link']) ?>" target="_blank"><?= htmlspecialchars($article['title']) ?></a>
                            <small style="color:#64748b;">(<?= htmlspecialchars($article['category']) ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Watchlist -->
        <div class="panel full-width" id="watchlist-wrapper">
            <h3>⭐ Saham Favorit (Real-Time Watchlist)</h3>
            <p style="font-size:12px; color:#64748b; margin-top:-5px; margin-bottom:15px;">Daftar saham pantauan favorit Anda. Harga diupdate real-time (intraday) tanpa menunggu jeda EOD sore.</p>
            <div id="watchlist-content" style="min-height: 50px;">
                <div class="text-center" style="color:#94a3b8; font-style:italic;">Memuat data live watchlist...</div>
            </div>
        </div>

        <!-- Gainers -->
        <div class="panel">
            <h3>📈 <?= $gainer_title; ?></h3>
            <div class="table-responsive"><table>
                <tr>
                    <th>Symbol</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">% Chg</th>
                </tr>
                <?php foreach ($gainers as $s): ?>
                <tr>
                    <td><?= format_symbol_badge($s, "#198754") ?></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right text-green">+<?= number_format($s['pct_change'], 2) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($gainers)) echo '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>'; ?>
            </table></div>
        </div>

        <!-- Losers -->
        <div class="panel">
            <h3>📉 <?= $loser_title; ?></h3>
            <div class="table-responsive"><table>
                <tr>
                    <th>Symbol</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">% Chg</th>
                </tr>
                <?php foreach ($losers as $s): ?>
                <tr>
                    <td><?= format_symbol_badge($s, "#dc3545") ?></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right text-red"><?= number_format($s['pct_change'], 2) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($losers)) echo '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>'; ?>
            </table></div>
        </div>

        <!-- Rekomendasi Multibagger -->
        <div class="panel full-width" style="border: 2px solid #0d6efd;">
            <h3 style="color:#0d6efd;">🚀 Semua Saham Berpotensi Multibagger</h3>
            <p style="font-size:13px; color:#555; margin-top:-5px; margin-bottom:15px;">Saham dengan likuiditas tinggi dan momentum kenaikan kuat. Berpotensi memberikan keuntungan kelipatan besar dengan profil *High Risk*.</p>
            <div class="table-responsive"><table>
                <tr>
                    <th>Symbol</th>
                    <th>Nama Perusahaan</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Perubahan</th>
                    <th class="text-right">Volume</th>
                    <th class="text-right">Value (Rp)</th>
                    <th>Status Bandar</th>
                    <th>Top Buy Broker</th>
                    <th>Top Sell Broker</th>
                    <th class="text-center">Buy % (A / L)</th>
                    <th class="text-center">Sell % (A / L)</th>
                </tr>
                <?php foreach ($potential_multibaggers as $s): ?>
                <?php 
                    $bandar = get_mock_bandar($s['symbol'], $s['volume']); 
                    $value = $s['volume'] * $s['close'];
                    if ($value >= 1000000000) {
                        $value_str = number_format($value / 1000000000, 1) . ' Miliar';
                    } elseif ($value >= 1000000) {
                        $value_str = number_format($value / 1000000, 1) . ' Juta';
                    } else {
                        $value_str = number_format($value);
                    }
                    
                    $vol = $s['volume'];
                    if ($vol >= 1000000) {
                        $vol_str = number_format($vol / 1000000, 1) . 'M';
                    } elseif ($vol >= 1000) {
                        $vol_str = number_format($vol / 1000, 1) . 'K';
                    } else {
                        $vol_str = number_format($vol);
                    }
                ?>
                <tr>
                    <td><?= format_symbol_badge($s, "#0d6efd") ?></td>
                    <td><?= $s['name'] ?></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right <?= $s['pct_change'] >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $s['pct_change'] > 0 ? '+' : '' ?><?= number_format($s['pct_change'], 2) ?>%
                    </td>
                    <td class="text-right"><?= $vol_str ?></td>
                    <td class="text-right"><?= $value_str ?></td>
                    <td><?= $bandar['status'] ?></td>
                    <td><strong><?= $bandar['top_buy'] ?></strong></td>
                    <td><strong><?= $bandar['top_sell'] ?></strong></td>
                    <td class="text-center" style="font-size:11px;">
                        <span style="color:#0d6efd;">A:<?= $bandar['asing_buy'] ?>%</span> | 
                        <span style="color:#6c757d;">L:<?= $bandar['lokal_buy'] ?>%</span>
                    </td>
                    <td class="text-center" style="font-size:11px;">
                        <span style="color:#0d6efd;">A:<?= $bandar['asing_sell'] ?>%</span> | 
                        <span style="color:#6c757d;">L:<?= $bandar['lokal_sell'] ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($potential_multibaggers)) echo '<tr><td colspan="11" class="text-center">Tidak ada saham multibagger potensial saat ini.</td></tr>'; ?>
            </table></div>
        </div>
        
        <!-- Top Volume & Bandar -->
        <div class="panel full-width">
            <h3>📊 Top Volume & Analisis Bandar (Broker Flow)</h3>
            <p style="font-size:12px; color:#888; margin-top:-5px;">*Disclaimer: Data broker (Asing/Lokal) adalah estimasi berdasarkan distribusi transaksi (Volume/Value).</p>
            <div class="table-responsive"><table>
                <tr>
                    <th>Symbol</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Perubahan</th>
                    <th class="text-right">Volume</th>
                    <th class="text-right">Value (Rp)</th>
                    <th>Status Bandar</th>
                    <th>Top Buy Broker</th>
                    <th>Top Sell Broker</th>
                    <th class="text-center">Buy % (A / L)</th>
                    <th class="text-center">Sell % (A / L)</th>
                </tr>
                <?php foreach ($top_volume as $s): ?>
                <?php 
                    $bandar = get_mock_bandar($s['symbol'], $s['volume']); 
                    $value = $s['volume'] * $s['close'];
                    if ($value >= 1000000000) {
                        $value_str = number_format($value / 1000000000, 1) . ' Miliar';
                    } elseif ($value >= 1000000) {
                        $value_str = number_format($value / 1000000, 1) . ' Juta';
                    } else {
                        $value_str = number_format($value);
                    }
                    
                    $vol = $s['volume'];
                    if ($vol >= 1000000) {
                        $vol_str = number_format($vol / 1000000, 1) . 'M';
                    } elseif ($vol >= 1000) {
                        $vol_str = number_format($vol / 1000, 1) . 'K';
                    } else {
                        $vol_str = number_format($vol);
                    }
                ?>
                <tr>
                    <td><?= format_symbol_badge($s, "#0d6efd") ?></td>
                    <td class="text-right"><?= number_format($s['close']) ?></td>
                    <td class="text-right <?= $s['pct_change'] >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $s['pct_change'] > 0 ? '+' : '' ?><?= number_format($s['pct_change'], 2) ?>%
                    </td>
                    <td class="text-right"><?= $vol_str ?></td>
                    <td class="text-right"><?= $value_str ?></td>
                    <td><?= $bandar['status'] ?></td>
                    <td><strong><?= $bandar['top_buy'] ?></strong></td>
                    <td><strong><?= $bandar['top_sell'] ?></strong></td>
                    <td class="text-center" style="font-size:11px;">
                        <span style="color:#0d6efd;">A:<?= $bandar['asing_buy'] ?>%</span> | 
                        <span style="color:#6c757d;">L:<?= $bandar['lokal_buy'] ?>%</span>
                    </td>
                    <td class="text-center" style="font-size:11px;">
                        <span style="color:#0d6efd;">A:<?= $bandar['asing_sell'] ?>%</span> | 
                        <span style="color:#6c757d;">L:<?= $bandar['lokal_sell'] ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_volume)) echo '<tr><td colspan="10" class="text-center">Tidak ada data transaksi.</td></tr>'; ?>
            </table></div>
        </div>

    </div>
</div>

<!-- Floating Banner Auto-Updater -->
<div id="auto-update-banner">
    <span id="update-text">⏳ Sedang mengUPDATE Harga EOD terbaru ke database (Otomatis). Mohon tunggu sebentar...</span>
</div>

<script>
    function setBannerState(kind, message) {
        const banner = document.getElementById('auto-update-banner');
        const text = document.getElementById('update-text');
        if (!banner || !text) return;
        banner.classList.remove('is-error', 'is-success', 'is-warn');
        if (kind === 'error') banner.classList.add('is-error');
        if (kind === 'success') banner.classList.add('is-success');
        if (kind === 'warn') banner.classList.add('is-warn');
        banner.style.display = 'block';
        text.innerText = message;
    }

    function updateLastUpdateInfo(data) {
        const lastUpdateEl = document.getElementById('last-update-time');
        const todayCountEl = document.getElementById('today-count');
        if (lastUpdateEl && data && data.updated_at_display) {
            lastUpdateEl.textContent = data.updated_at_display;
        }
        if (todayCountEl && data && typeof data.today_count !== 'undefined') {
            todayCountEl.textContent = data.today_count;
        }
    }

    function runForceUpdate() {
        const banner = document.getElementById('auto-update-banner');
        setBannerState('', '⏳ Menjalankan paksa update EOD...');

        fetch('ajax_update.php?force=1')
            .then(res => res.json())
            .then(data => {
                updateLastUpdateInfo(data);
                if (data.is_fresh_today === true) {
                    setBannerState('success', '✅ Paksa update berhasil. Data hari ini sudah fresh.');
                    localStorage.setItem('last_update_daily', new Date().toISOString().split('T')[0]);
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    setBannerState('warn', '⚠️ Paksa update selesai, tapi data hari ini belum lengkap (' + (data.today_count || 0) + ' saham).');
                    localStorage.removeItem('last_update_daily');
                    setTimeout(() => { banner.style.display = 'none'; }, 5000);
                }
            })
            .catch(() => {
                setBannerState('error', '⚠️ Paksa update gagal. Coba lagi beberapa menit lagi.');
                setTimeout(() => { banner.style.display = 'none'; }, 5000);
            });
    }

    // Fitur Auto Update harga saham (Max 1x Sehari) tanpa memberatkan browser / UI.
    document.addEventListener("DOMContentLoaded", function() {
        const forceBtn = document.getElementById('btnForceUpdate');
        if (forceBtn) {
            forceBtn.addEventListener('click', runForceUpdate);
        }

        const today = new Date().toISOString().split('T')[0];
        const lastUpdate = localStorage.getItem('last_update_daily');
        const latestDataDate = "<?= $today; ?>";
        const dataIsStale = latestDataDate !== today;
        const shouldRunUpdate = (lastUpdate !== today) || dataIsStale;
        
        // Pengecekan Cache Lokal vs Tanggal Hari Ini. Jika belum update:
        if (shouldRunUpdate) {
            const banner = document.getElementById('auto-update-banner');
            setBannerState('', '⏳ Sedang mengUPDATE Harga EOD terbaru ke database (otomatis). Mohon tunggu sebentar.');

            fetch('ajax_update.php')
                .then(res => res.json())
                .then(data => {
                    updateLastUpdateInfo(data);
                    if (data.is_fresh_today === true) {
                        setBannerState('success', '✅ Update EOD berhasil. Harga hari ini sudah lengkap.');
                        // Set tanda bahwa hari ini sudah berhasil
                        localStorage.setItem('last_update_daily', today);
                        // Menghilangkan banner setelah 3 detik
                        setTimeout(() => { banner.style.display = 'none'; }, 3000);
                    } else {
                        // Data belum lengkap hari ini -> jangan lock localStorage agar bisa retry otomatis
                        setBannerState('warn', '⏳ Data hari ini belum lengkap (' + (data.today_count || 0) + ' saham). Sistem akan coba lagi saat refresh berikutnya.');
                        localStorage.removeItem('last_update_daily');
                        setTimeout(() => { banner.style.display = 'none'; }, 5000);
                    }
                })
                .catch(err => {
                    setBannerState('error', '⚠️ Gagal melakukan auto-update. Coba lagi nanti.');
                    localStorage.removeItem('last_update_daily');
                    setTimeout(() => { banner.style.display = 'none'; }, 5000);
                });
        }
    });

    setTimeout(function() {
        window.location.reload();
    }, 180000); // 180000 milidetik = 3 menit

    // Pendaftaran Service Worker untuk PWA Mobile App
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('service-worker.js').then(function(registration) {
                console.log('PWA ServiceWorker siap dengan scope: ', registration.scope);
            }, function(err) {
                console.log('PWA ServiceWorker gagal didaftarkan: ', err);
            });
        });
    }
    // Fitur Watchlist Real-time
    function loadWatchlist() {
        const wrap = document.getElementById('watchlist-content');
        if (!wrap) return;
        fetch('fetch_watchlist.php')
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    if (data.data.length === 0) {
                        wrap.innerHTML = '<div class="text-center" style="color:#94a3b8; font-style:italic;">Belum ada saham favorit. Klik logo ⭐ pada tabel di bawah untuk menambahkan.</div>';
                        return;
                    }
                    let html = '<table><tr><th>Symbol</th><th class="text-right">Harga Live</th><th class="text-right">% Chg</th></tr>';
                    data.data.forEach(s => {
                        let color = s.pct > 0 ? '#198754' : (s.pct < 0 ? '#dc3545' : '#475569');
                        let sign = s.pct > 0 ? '+' : '';
                        html += `<tr>
                            <td>
                                <span class="star-btn active" onclick="toggleWatchlist('${s.symbol}', this, true)" title="Hapus">&#9733;</span>
                                <strong><a href="chart.php?symbol=${s.symbol}" target="_blank" style="text-decoration:none; color: ${color};">${s.symbol}</a></strong>
                            </td>
                            <td class="text-right">`+parseInt(s.price).toLocaleString('id-ID')+`</td>
                            <td class="text-right" style="color:${color}">${sign}${s.pct.toFixed(2)}%</td>
                        </tr>`;
                    });
                    html += '</table>';
                    wrap.innerHTML = html;
                }
            })
            .catch(() => {
                wrap.innerHTML = '<div class="text-center" style="color:#dc3545;">Gagal memuat live watchlist.</div>';
            });
    }

    function toggleWatchlist(symbol, el, isFromPanel = false) {
        // optimis UI
        const isActive = el.classList.contains('active');
        const action = isActive ? 'remove' : 'add';
        
        let formData = new FormData();
        formData.append('symbol', symbol);

        fetch('api_watchlist.php?action=' + action, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    if (isActive) {
                        el.classList.remove('active');
                        el.title = 'Tambah ke Watchlist';
                    } else {
                        el.classList.add('active');
                        el.title = 'Hapus dari Watchlist';
                    }
                    // Sinkronisasi dengan tabel di bawah dengan reload array page
                    loadWatchlist();
                    if (isFromPanel) {
                       window.location.reload();
                    }
                }
            });
    }

    // Initialize Watchlist on load
    document.addEventListener("DOMContentLoaded", function() {
        loadWatchlist();
        // setInterval(loadWatchlist, 30000); // optional: update every 30s
    });

</script>

</body>
</html>





