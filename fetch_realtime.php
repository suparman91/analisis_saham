<?php
// Fetch realtime quotes for BEI-listed symbols.
// This script attempts to fetch from BEI where possible, but uses Yahoo Finance as a reliable fallback.
// Usage: fetch_realtime.php?symbols=BBCA,TLKM   or  fetch_realtime.php?symbols=all

require_once __DIR__ . '/db.php';

// If Composer autoload exists, include it so we can use Guzzle (preferred when available)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$symbolsParam = $_GET['symbols'] ?? '';
$useAll = strtolower($symbolsParam) === 'all';

$mysqli = db_connect();

// Finnhub API key can be provided via GET param `finnhub_key` or env var FINNHUB_API_KEY
$FINNHUB_API_KEY = $_GET['finnhub_key'] ?? getenv('FINNHUB_API_KEY') ?? '';
// sanitize: url-decode and trim surrounding quotes/spaces
if ($FINNHUB_API_KEY !== '') {
    $FINNHUB_API_KEY = urldecode($FINNHUB_API_KEY);
    $FINNHUB_API_KEY = trim($FINNHUB_API_KEY, "\"' \t\n\r");
}

if ($useAll) {
    $symbols = [];
    $res = $mysqli->query('SELECT symbol FROM stocks');
    while ($r = $res->fetch_assoc()) $symbols[] = $r['symbol'];
} else {
    $symbols = array_filter(array_map('trim', explode(',', $symbolsParam)));
}

if (empty($symbols)) {
    echo json_encode(['error'=>'No symbols provided. Use ?symbols=BBCA,TLKM or ?symbols=all']);
    exit;
}

function fetch_yahoo_quote($symbol) {
    // Yahoo expects .JK suffix for Jakarta Exchange
    $sym = strtoupper($symbol);
    if (strpos($sym, '.JK') === false) $sym = $sym . '.JK';
    // Use v8 chart api to bypass v7 quote blocks
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym) . '?range=1d&interval=1m';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
        'Accept: application/json'
    ]);
    $txt = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($txt === false || $txt === null) return ['error' => 'curl_error', 'message' => $err, 'http' => $http, 'url'=>$url];
    $j = json_decode($txt, true);
    if (!isset($j['chart']['result'][0]['meta'])) return ['error'=>'no_result', 'http'=>$http, 'raw'=>$j, 'url'=>$url];
    $q = $j['chart']['result'][0]['meta'];
    return [
        'symbol' => $symbol,
        'price' => $q['regularMarketPrice'] ?? null,
        'time' => $q['regularMarketTime'] ?? null,
        'open' => $q['regularMarketDayHigh'] ?? null, // approximation from chart meta
        'high' => $q['regularMarketDayHigh'] ?? null,
        'low' => $q['regularMarketDayLow'] ?? null,
        'previousClose' => $q['chartPreviousClose'] ?? null,
        'volume' => $q['regularMarketVolume'] ?? null,
        'raw' => $q,
        'http' => $http,
        'url' => $url
    ];
}

// Try fetching via public raw proxy (AllOrigins) to bypass simple CORS/blocks
function proxy_fetch_raw($url, &$httpCode = null) {
    $proxyUrl = 'https://api.allorigins.win/raw?url=' . urlencode($url);
    $res = curl_fetch($proxyUrl, [], 15, $httpCode);
    return $res;
}

function fetch_finnhub_quote($symbol, $apiKey = '') {
    if (!$apiKey) return ['error'=>'no_api_key'];

    // Prefer Guzzle from Composer when available (more robust than raw curl)
    if (class_exists('GuzzleHttp\\Client')) {
        return fetch_finnhub_quote_guzzle($symbol, $apiKey);
    }
    $orig = strtoupper($symbol);
    $candidates = [$orig];
    if (strpos($orig, '.JK') === false) $candidates[] = $orig . '.JK';
    // also try without .JK if user passed it
    if (strpos($orig, '.JK') !== false) $candidates[] = str_replace('.JK','',$orig);

    foreach ($candidates as $sym) {
        $url = 'https://finnhub.io/api/v1/quote?symbol=' . urlencode($sym) . '&token=' . urlencode($apiKey);
        $http = 0;
        $res = curl_fetch($url, ['Accept: application/json'], 8, $http);
        if (isset($res['error'])) {
            // continue to next candidate
            continue;
        }
        $txt = $res['body'];
        $j = json_decode($txt, true);
        if (is_array($j) && isset($j['c']) && $j['c'] !== null) {
            return [
                'symbol'=>$symbol,
                'price'=>$j['c'] ?? null,
                'open'=>$j['o'] ?? null,
                'high'=>$j['h'] ?? null,
                'low'=>$j['l'] ?? null,
                'previousClose'=>$j['pc'] ?? null,
                'raw'=>$j,
                'http'=>$http,
                'url'=>$url
            ];
        }
    }
    return ['error'=>'no_result','http'=>($http ?? 0),'raw'=>($j ?? null),'url'=>'finnhub_candidates'];
}

// Use Guzzle (from composer) to call Finnhub if available
function fetch_finnhub_quote_guzzle($symbol, $apiKey = '') {
    if (!class_exists('GuzzleHttp\\Client')) return ['error'=>'no_guzzle'];
    $client = new GuzzleHttp\Client(['timeout'=>6]);
    $orig = strtoupper($symbol);
    $candidates = [$orig];
    if (strpos($orig, '.JK') === false) $candidates[] = $orig . '.JK';
    if (strpos($orig, '.JK') !== false) $candidates[] = str_replace('.JK','',$orig);

    foreach ($candidates as $sym) {
        $url = 'https://finnhub.io/api/v1/quote?symbol=' . urlencode($sym) . '&token=' . urlencode($apiKey);
        try {
            $resp = $client->request('GET', $url, ['headers'=>['Accept'=>'application/json']]);
            $http = $resp->getStatusCode();
            $txt = (string)$resp->getBody();
        } catch (Exception $e) {
            continue;
        }
        $j = json_decode($txt, true);
        if (is_array($j) && isset($j['c']) && $j['c'] !== null) {
            return [
                'symbol'=>$symbol,
                'price'=>$j['c'] ?? null,
                'open'=>$j['o'] ?? null,
                'high'=>$j['h'] ?? null,
                'low'=>$j['l'] ?? null,
                'previousClose'=>$j['pc'] ?? null,
                'raw'=>$j,
                'http'=>$http,
                'url'=>$url
            ];
        }
    }
    return ['error'=>'no_result','http'=>($http ?? 0),'raw'=>($j ?? null),'url'=>'finnhub_candidates'];
}

// Probe Finnhub market status for IDX (safe: returns null when not accessible)
function fetch_finnhub_market_status($apiKey = '') {
    if (!$apiKey) return ['error'=>'no_api_key'];
    $url = 'https://finnhub.io/api/v1/stock/market-status?exchange=IDX&token=' . urlencode($apiKey);
    $http = 0;
    $res = curl_fetch($url, ['Accept: application/json'], 6, $http);
    if (isset($res['error'])) return ['error'=>'curl_error','http'=>$http,'detail'=>$res];
    $txt = $res['body'];
    $j = json_decode($txt, true);
    // Finnhub may return an error message like {"error":"You don't have access to this resource."}
    if (is_array($j) && isset($j['error'])) return ['error'=>'access_denied','http'=>$http,'message'=>$j['error'],'url'=>$url];
    // Return parsed payload when available, otherwise raw text
    return ['http'=>$http,'url'=>$url,'body'=>$j ?? $txt];
}

// Centralized cURL fetch helper with browser-like defaults
function curl_fetch($url, $extraHeaders = [], $timeout = 10, &$httpCode = null) {
    $ch = curl_init();
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9,id;q=0.8',
        'Accept-Encoding: gzip, deflate'
    ];
    $headers = array_merge($defaultHeaders, $extraHeaders);
    // use temp cookiejar per request to simulate browser session
    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scraper_cookies_' . md5($url . session_id());
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(5, (int)$timeout));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // only request encodings libcurl commonly supports on Windows builds
    curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $txt = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $httpCode = $http;
    if ($txt === false || $txt === null) return ['error'=>'curl_error','message'=>$err,'http'=>$http,'url'=>$url];
    return ['body'=>$txt,'http'=>$http,'url'=>$url];
}

function insert_price_to_db($mysqli, $symbol, $price, $open = null, $high = null, $low = null, $volume = null, $timestamp = null) {
    if ($price === null || !is_numeric($price)) return ['saved'=>false,'error'=>'invalid_price'];

    // prices table uses columns: symbol, date, open, high, low, close, volume
    // date is a DATE (no time). Use provided timestamp if available, otherwise today.
    $ts = null;
    if ($timestamp !== null && is_numeric($timestamp)) $ts = (int)$timestamp;
    elseif (is_array($price) && isset($price['t']) && is_numeric($price['t'])) $ts = (int)$price['t'];
    else $ts = time();
    $dateStr = date('Y-m-d', $ts);

    // fill missing OHLC with the provided price when possible
    $openVal = ($open !== null && is_numeric($open)) ? $open : $price;
    $highVal = ($high !== null && is_numeric($high)) ? $high : $price;
    $lowVal = ($low !== null && is_numeric($low)) ? $low : $price;
    $closeVal = $price;
    $volVal = ($volume !== null && is_numeric($volume)) ? (int)$volume : 0;

    $sql = "INSERT INTO prices (symbol, date, open, high, low, close, volume) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE open=VALUES(open), high=VALUES(high), low=VALUES(low), close=VALUES(close), volume=VALUES(volume)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return ['saved'=>false,'error'=>'prepare_failed','mysqli_error'=>$mysqli->error];

    $stmt->bind_param('ssddddi', $symbol, $dateStr, $openVal, $highVal, $lowVal, $closeVal, $volVal);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
        $stmt->close();
        return ['saved'=>false,'error'=>'execute_failed','stmt_error'=>$err];
    }
    $insertId = $mysqli->insert_id;
    $stmt->close();
    return ['saved'=>true,'insert_id'=>$insertId, 'date'=>$dateStr];
}

// Placeholder: BEI scraping attempt (best-effort). If it fails, fallback to Yahoo.
function fetch_bei_quote($symbol) {
    // Best-effort scraping of IDX/BEI pages for a single symbol.
    $sym = strtoupper($symbol);
    $candidates = [
        "https://www.idx.co.id/umbraco/Api/Stock/Quote?code={$sym}",
        "https://www.idx.co.id/umbraco/Surface/MarketData/GetStockSummary?code={$sym}",
        "https://www.idx.co.id/umbraco/Surface/ListedCompanies/GetCompanyProfile?kodeEmiten={$sym}",
        "https://www.idx.co.id/en-us/market-data/stock-summary?stockCode={$sym}",
        "https://www.idx.co.id/market-data/stock-summary?stockCode={$sym}",
        "https://www.idx.co.id/umbraco/Surface/ListedCompanies/GetCompanyProfile?kodeEmiten={$sym}",
        "https://www.idx.co.id/search/?q={$sym}"
    ];

    foreach ($candidates as $url) {
        $httpCode = 0;
        $res = curl_fetch($url, ['Accept: application/json, text/html'], 10, $httpCode);
        if (isset($res['error'])) {
            // continue to next candidate
            continue;
        }
        $txt = $res['body'];
        $http = $res['http'];

        // Attempt to decode JSON directly
        $maybeJson = null;
        $trim = ltrim($txt);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $maybeJson = json_decode($txt, true);
        } else {
            // find JSON blob in HTML
            if (preg_match('/(root\.App\.main\s*=\s*)(\{.*\})\s*;\s*\(/sU', $txt, $m)) {
                $maybeJson = json_decode($m[2], true);
            } elseif (preg_match('/"QuoteSummaryStore"\s*:\s*(\{.*?\})\s*\,\s*"isPending"/s', $txt, $m2)) {
                $maybeJson = json_decode('{"QuoteSummaryStore":' . $m2[1] . '}', true);
            }
        }

        if ($maybeJson) {
            $price = null; $open = null; $high = null; $low = null; $volume = null;
            if (isset($maybeJson['price'])) {
                $p = $maybeJson['price'];
                $price = $p['regularMarketPrice']['raw'] ?? $p['lastPrice'] ?? null;
                $open = $p['regularMarketOpen']['raw'] ?? null;
                $high = $p['regularMarketDayHigh']['raw'] ?? null;
                $low = $p['regularMarketDayLow']['raw'] ?? null;
            }
            if (!$price) {
                $qs = null;
                if (isset($maybeJson['context']['dispatcher']['stores']['QuoteSummaryStore'])) {
                    $qs = $maybeJson['context']['dispatcher']['stores']['QuoteSummaryStore'];
                } elseif (isset($maybeJson['QuoteSummaryStore'])) {
                    $qs = $maybeJson['QuoteSummaryStore'];
                }
                if ($qs && isset($qs['price'])) {
                    $p = $qs['price'];
                    $price = $p['regularMarketPrice']['raw'] ?? null;
                    $open = $p['regularMarketOpen']['raw'] ?? null;
                    $high = $p['regularMarketDayHigh']['raw'] ?? null;
                    $low = $p['regularMarketDayLow']['raw'] ?? null;
                    $volume = $p['regularMarketVolume']['raw'] ?? null;
                }
            }
            if ($price !== null) {
                return ['symbol'=>$symbol,'price'=>$price,'open'=>$open,'high'=>$high,'low'=>$low,'volume'=>$volume,'http'=>$http,'url'=>$url,'raw'=>$maybeJson];
            }
        }

        // HTML heuristics
        $patterns = [
            '/Last\s*Price[^\d\n\r]{0,40}([0-9.,]+)/i',
            '/Harga\s*Terakhir[^\d\n\r]{0,40}([0-9.,]+)/i',
            '/>\s*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]+)?)\s*<\s*\/span>\s*<\s*span[^>]*>\s*Terakhir/i',
            '/regularMarketPrice"\s*:\s*\{\s*"raw"\s*:\s*([0-9\.]+)/i',
            '/"lastTrade"\s*:\s*"?([0-9.,]+)"?/i',
            '/\bTerakhir\b[^\d\n\r]{0,40}([0-9.,]+)/i'
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $txt, $mm)) {
                $found = $mm[1];
                $num = str_replace([',',' '], ['','.'], $found);
                $num = preg_replace('/[^0-9\.]/','',$num);
                if ($num !== '') {
                    $val = (float)$num;
                    return ['symbol'=>$symbol,'price'=>$val,'http'=>$http,'url'=>$url,'snippet'=>substr($txt,0,400)];
                }
            }
        }

        // try proxy fetch if blocked or no price found
        $proxyHttp = 0;
        $proxyRes = proxy_fetch_raw($url, $proxyHttp);
        if (isset($proxyRes['body']) && $proxyRes['body'] !== null) {
            $ptxt = $proxyRes['body'];
            // attempt JSON decode again
            $trim2 = ltrim($ptxt);
            $maybeJson2 = null;
            if ($trim2 !== '' && ($trim2[0] === '{' || $trim2[0] === '[')) {
                $maybeJson2 = json_decode($ptxt, true);
            } elseif (preg_match('/(root\.App\.main\s*=\s*)(\{.*\})\s*;\s*\(/sU', $ptxt, $m3)) {
                $maybeJson2 = json_decode($m3[2], true);
            } elseif (preg_match('/"QuoteSummaryStore"\s*:\s*(\{.*?\})\s*\,\s*"isPending"/s', $ptxt, $m4)) {
                $maybeJson2 = json_decode('{"QuoteSummaryStore":' . $m4[1] . '}', true);
            }
            if ($maybeJson2) {
                if (isset($maybeJson2['context']['dispatcher']['stores']['QuoteSummaryStore'])) $qs2 = $maybeJson2['context']['dispatcher']['stores']['QuoteSummaryStore'];
                elseif (isset($maybeJson2['QuoteSummaryStore'])) $qs2 = $maybeJson2['QuoteSummaryStore']; else $qs2 = null;
                if ($qs2 && isset($qs2['price'])) {
                    $p2 = $qs2['price'];
                    $price2 = $p2['regularMarketPrice']['raw'] ?? null;
                    if ($price2 !== null) return ['symbol'=>$symbol,'price'=>$price2,'http'=>$proxyHttp,'url'=>$url,'raw'=>$maybeJson2,'via'=>'proxy'];
                }
            }
            // try HTML heuristics on proxy body
            foreach ($patterns as $pat2) {
                if (preg_match($pat2, $ptxt, $mm2)) {
                    $found2 = $mm2[1];
                    $num2 = str_replace([',',' '], ['','.'], $found2);
                    $num2 = preg_replace('/[^0-9\.]/','',$num2);
                    if ($num2 !== '') {
                        return ['symbol'=>$symbol,'price'=>(float)$num2,'http'=>$proxyHttp,'url'=>$url,'via'=>'proxy','snippet'=>substr($ptxt,0,400)];
                    }
                }
            }
        }

        return ['error'=>'no_price_found','http'=>$http,'url'=>$url,'snippet'=>substr($txt,0,400)];
    }

    return null;
}

function fetch_yahoo_html($symbol) {
    $sym = strtoupper($symbol);
    if (strpos($sym, '.JK') === false) $sym = $sym . '.JK';
    $url = 'https://finance.yahoo.com/quote/' . urlencode($sym);
    $httpCode = 0;
    $res = curl_fetch($url, [], 12, $httpCode);
    if (isset($res['error'])) return $res;
    $txt = $res['body'];
    $http = $res['http'];

    // Try to extract JSON data from root.App.main
    if (preg_match('/root.App.main\s*=\s*(\{.*\})\s*;\s*\(function\(\)\)/sU', $txt, $m)) {
        $json = $m[1];
    } else {
        // fallback: look for 'QuoteSummaryStore' payload
        if (preg_match('/"QuoteSummaryStore"\s*:\s*(\{.*?\})\s*\,\s*"isPending"/s', $txt, $m2)) {
            $json = '{"QuoteSummaryStore":' . $m2[1] . '}';
        } else {
            return ['error'=>'no_json','http'=>$http,'url'=>$url];
        }
    }

    $j = json_decode($json, true);
    if ($j === null) {
        // try proxy raw fetch
        $proxyHttp = 0;
        $proxyRes = proxy_fetch_raw($url, $proxyHttp);
        if (isset($proxyRes['body'])) {
            $json2 = null;
            if (preg_match('/root.App.main\s*=\s*(\{.*\})\s*;\s*\(function\(\)\)/sU', $proxyRes['body'], $m5)) {
                $json2 = $m5[1];
            } elseif (preg_match('/"QuoteSummaryStore"\s*:\s*(\{.*?\})\s*\,\s*"isPending"/s', $proxyRes['body'], $m6)) {
                $json2 = '{"QuoteSummaryStore":' . $m6[1] . '}';
            }
            if ($json2) {
                $j = json_decode($json2, true);
                if ($j !== null) $http = $proxyHttp;
            }
        }
        if ($j === null) return ['error'=>'json_decode_failed','http'=>$http,'url'=>$url,'snippet'=>substr($json,0,200)];
    }

    // navigate to price data
    $price = null; $open = null; $high = null; $low = null; $volume = null;
    if (isset($j['context']['dispatcher']['stores']['QuoteSummaryStore'])) {
        $qs = $j['context']['dispatcher']['stores']['QuoteSummaryStore'];
        if (isset($qs['price'])) {
            $p = $qs['price'];
            $price = $p['regularMarketPrice']['raw'] ?? null;
            $open = $p['regularMarketOpen']['raw'] ?? null;
            $high = $p['regularMarketDayHigh']['raw'] ?? null;
            $low = $p['regularMarketDayLow']['raw'] ?? null;
            $volume = $p['regularMarketVolume']['raw'] ?? null;
        }
    } elseif (isset($j['QuoteSummaryStore'])) {
        $qs = $j['QuoteSummaryStore'];
        if (isset($qs['price'])) {
            $p = $qs['price'];
            $price = $p['regularMarketPrice']['raw'] ?? null;
            $open = $p['regularMarketOpen']['raw'] ?? null;
            $high = $p['regularMarketDayHigh']['raw'] ?? null;
            $low = $p['regularMarketDayLow']['raw'] ?? null;
            $volume = $p['regularMarketVolume']['raw'] ?? null;
        }
    }

    return [
        'symbol'=>$symbol,
        'price'=>$price,
        'open'=>$open,
        'high'=>$high,
        'low'=>$low,
        'volume'=>$volume,
        'http'=>$http,
        'url'=>$url
    ];
}

function fetch_investing_quote($symbol) {
    $sym = strtoupper($symbol);
    $base = 'https://www.investing.com';
    $searchUrl = $base . '/search/?q=' . urlencode($sym);
    $httpCode = 0;
    $res = curl_fetch($searchUrl, [], 12, $httpCode);
    if (isset($res['error'])) return $res;
    $txt = $res['body'];
    $http = $res['http'];

    // try find first equities link
    if (preg_match('/href="(\/equities\/[^"\s>]+)"/i', $txt, $m)) {
        $path = html_entity_decode($m[1]);
        $url = $base . $path;
    } else {
        // try find any instrument link
        if (preg_match('/href="(\/[^"\s>]+\/[^"\s>]+)"/i', $txt, $m2)) {
            $path = html_entity_decode($m2[1]);
            $url = $base . $path;
        } else {
            return ['error'=>'no_search_result','http'=>$http,'url'=>$searchUrl,'snippet'=>substr($txt,0,400)];
        }
    }

    // fetch instrument page
    $http2 = 0;
    $res2 = curl_fetch($url, [], 12, $http2);
    if (isset($res2['error'])) {
        // try proxy
        $proxyHttp = 0;
        $proxyRes = proxy_fetch_raw($url, $proxyHttp);
        if (isset($proxyRes['body'])) {
            $html = $proxyRes['body'];
            $http2 = $proxyHttp;
        } else {
            return $res2;
        }
    } else {
        $html = $res2['body'];
    }

    // common investing.com price id
    if (preg_match('/id="last_last"[^>]*>([0-9.,]+)/i', $html, $mm)) {
        $num = str_replace([',',' '], ['',''], $mm[1]);
        $price = (float)$num;
        return ['symbol'=>$symbol,'price'=>$price,'http'=>$http2,'url'=>$url];
    }

    // fallback try meta tags
    if (preg_match('/property="og:description" content="([^"]+)"/i', $html, $om)) {
        if (preg_match('/([0-9.,]+)/', $om[1], $nm)) {
            $num = str_replace([',',' '], ['',''], $nm[1]);
            return ['symbol'=>$symbol,'price'=> (float)$num,'http'=>$http2,'url'=>$url];
        }
    }

    // final attempt: try proxy search result if still no price
    $proxySearchHttp = 0;
    $proxySearchRes = proxy_fetch_raw($searchUrl, $proxySearchHttp);
    if (isset($proxySearchRes['body'])) {
        if (preg_match('/href="(\/equities\/[^"\s>]+)"/i', $proxySearchRes['body'], $mproxy)) {
            $pathp = html_entity_decode($mproxy[1]);
            $urlp = $base . $pathp;
            $proxyPage = proxy_fetch_raw($urlp, $pphttp = $proxySearchHttp);
            if (isset($proxyPage['body'])) {
                if (preg_match('/id="last_last"[^>]*>([0-9.,]+)/i', $proxyPage['body'], $mmp)) {
                    $num = str_replace([',',' '], ['',''], $mmp[1]);
                    return ['symbol'=>$symbol,'price'=>(float)$num,'http'=>$pphttp,'url'=>$urlp,'via'=>'proxy'];
                }
            }
        }
    }

    return ['error'=>'no_price_found','http'=>$http2,'url'=>$url,'snippet'=>substr($html,0,400)];
}

// iterate symbols and try multiple sources with clear fallback order
$out = [];
foreach ($symbols as $sym) {
    $debug = [];

    // 0) Try local headless Node scraper service if available (try common ports)
    $headlessCandidates = [
        'http://127.0.0.1:3000/quote?symbol=',
        'http://127.0.0.1:8080/quote?symbol='
    ];
    $headlessDebug = [];
    foreach ($headlessCandidates as $base) {
        $headlessUrl = $base . urlencode($sym);
        $chh = curl_init();
        curl_setopt($chh, CURLOPT_URL, $headlessUrl);
        curl_setopt($chh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chh, CURLOPT_TIMEOUT, 6);
        curl_setopt($chh, CURLOPT_HTTPHEADER, ['User-Agent: PHP-cURL/1.0','Accept: application/json']);
        $hresp = curl_exec($chh);
        $herr = curl_error($chh);
        $hhttp = curl_getinfo($chh, CURLINFO_HTTP_CODE);
        curl_close($chh);

        $entry = ['http'=>$hhttp,'raw'=>$hresp !== false ? $hresp : null,'url'=>$headlessUrl];
        // try decode JSON when body present
        if ($hresp !== false && $hresp !== null) {
            $hj = json_decode($hresp, true);
            $entry['json'] = $hj;
            // Accept only HTTP 200 with a numeric price
            if ($hhttp === 200 && is_array($hj) && isset($hj['price']) && $hj['price'] !== null) {
                $hj['source'] = 'headless';
                $hj['debug'] = ['headless_probe'=>$entry];
                $out[$sym] = $hj;
                $debug['headless'] = $entry;
                continue 2; // got result, skip to next symbol
            }
        } else {
            $entry['error'] = 'curl_failed';
            $entry['message'] = $herr;
        }
        $headlessDebug[] = $entry;
    }
    if (!empty($headlessDebug)) $debug['headless'] = $headlessDebug;

    // 1) Try Finnhub API if API key present
    $fb = null;
    if (!empty($FINNHUB_API_KEY)) {
        $fb = fetch_finnhub_quote($sym, $FINNHUB_API_KEY);
        $debug['finnhub'] = $fb;
        if (is_array($fb) && isset($fb['price']) && $fb['price'] !== null) {
            $fb['source'] = 'Finnhub';
            $out[$sym] = $fb;
            continue;
        }
    }

    // 2) Try BEI / IDX endpoints
    $bei = fetch_bei_quote($sym);
    $debug['bei'] = $bei;
    if (is_array($bei) && isset($bei['price']) && $bei['price'] !== null) {
        $bei['source'] = 'BEI';
        $out[$sym] = $bei;
        continue;
    }

    // 2) Try Yahoo API
    $y = fetch_yahoo_quote($sym);
    $debug['yahoo_api'] = $y;
    if (is_array($y) && isset($y['price']) && $y['price'] !== null) {
        $y['source'] = 'Yahoo API';
        $y['debug'] = $debug;
        $out[$sym] = $y;
        continue;
    }

    // 3) Yahoo HTML fallback
    $yh = fetch_yahoo_html($sym);
    $debug['yahoo_html'] = $yh;
    if (is_array($yh) && isset($yh['price']) && $yh['price'] !== null) {
        $yh['source'] = 'Yahoo HTML';
        $yh['debug'] = $debug;
        $out[$sym] = $yh;
        continue;
    }

    // 4) Investing.com as last fallback
    $inv = fetch_investing_quote($sym);
    $debug['investing'] = $inv;
    if (is_array($inv) && isset($inv['price']) && $inv['price'] !== null) {
        $inv['source'] = 'Investing.com';
        $inv['debug'] = $debug;
        $out[$sym] = $inv;
        continue;
    }

    // If nothing provided a price, return combined debug info
    $out[$sym] = ['error'=>'no_price_found','symbol'=>$sym,'debug'=>$debug];
}

header('Content-Type: application/json');

// Persist successful fetches into DB (best-effort)
foreach ($out as $sym => $rec) {
    if (is_array($rec) && isset($rec['price']) && is_numeric($rec['price'])) {
        $save = insert_price_to_db($mysqli, $sym, (float)$rec['price'], $rec['open'] ?? null, $rec['high'] ?? null, $rec['low'] ?? null, $rec['volume'] ?? null);
        $out[$sym]['db'] = $save;
    }
}

// If DB persistence altered $out, re-output short summary (non-pretty)
// Note: keep Content-Type header already set above
echo json_encode(['source'=>'multi_fallback_with_db','data'=>$out], JSON_PRETTY_PRINT);
?>
