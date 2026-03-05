const express = require('express');
const cors = require('cors');
const { chromium } = require('playwright');

const app = express();
const port = process.env.PORT || 8080;
app.use(cors());

app.get('/quote', async (req, res) => {
  const symbol = (req.query.symbol || '').toUpperCase().trim();
  if (!symbol) return res.status(400).json({ error: 'symbol required' });

  const trySym = symbol.endsWith('.JK') ? symbol : symbol + '.JK';
  const searchUrl = `https://www.investing.com/search/?q=${encodeURIComponent(trySym)}`;
  let browser;
  try {
    browser = await chromium.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    const page = await browser.newPage();
    await page.goto(searchUrl, { waitUntil: 'networkidle', timeout: 20000 });

    // find first equities link
    const link = await page.$('a[href*="/equities/"]') || await page.$('a[href*="/stocks/"]') || await page.$('a[href*="/equities/"]');
    if (!link) {
      await browser.close();
      return res.status(404).json({ error: 'no_search_result', url: searchUrl });
    }

    const href = await link.getAttribute('href');
    const full = href.startsWith('http') ? href : ('https://www.investing.com' + href);
    await page.goto(full, { waitUntil: 'networkidle', timeout: 20000 });

    // Wait for price element used by investing.com
    try {
      await page.waitForSelector('#last_last', { timeout: 10000 });
      const txt = await page.$eval('#last_last', el => el.textContent.trim());
      const cleaned = txt.replace(/[^0-9.,\-]/g, '').replace(/,/g, '');
      const price = parseFloat(cleaned) || null;
      await browser.close();
      return res.json({ symbol: symbol, price: price, raw: txt, url: full });
    } catch (e) {
      // try meta fallback
      const og = await page.$('meta[property="og:description"]');
      if (og) {
        const content = await og.getAttribute('content') || '';
        const m = content.match(/([0-9.,]+)/);
        if (m) {
          const cleaned = m[1].replace(/,/g, '');
          const price = parseFloat(cleaned) || null;
          await browser.close();
          return res.json({ symbol: symbol, price: price, raw: content, url: full });
        }
      }
    }

    await browser.close();
    return res.status(404).json({ error: 'no_price_found', url: full });
  } catch (err) {
    if (browser) await browser.close();
    return res.status(500).json({ error: 'headless_error', message: String(err) });
  }
});

// simple health endpoint for quick browser check
app.get('/', (req, res) => {
  res.json({ status: 'ok', port: port, info: 'Use /quote?symbol=BBCA' });
});

app.listen(port, () => {
  console.log(`Headless scraper listening on port ${port}`);
});
