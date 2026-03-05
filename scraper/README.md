Headless scraper service (Playwright)

Setup:

1. Install dependencies:

```bash
cd scraper
npm install
npx playwright install
```

2. Run service:

```bash
node server.js
```

3. Query:

`http://localhost:3000/quote?symbol=BBCA`

Notes:
- Playwright will download browser binaries on `npx playwright install`.
- Run the service on the same machine as PHP (or allow CORS) so `fetch_realtime.php` can call it.
- If running on Windows with limited memory, consider launching chromium with reduced args.

Running on a custom port (8080 example)

Windows (PowerShell):

```powershell
# set PORT for current session and run
$env:PORT = '8080'
node server.js
```

Linux / macOS:

```bash
PORT=8080 node server.js
```

After service starts, test from the browser or PowerShell:

```powershell
Invoke-RestMethod "http://127.0.0.1:8080/quote?symbol=BBCA"
```

If the scraper returns a JSON with `price`, then call the PHP endpoint:

```
http://localhost/analisis_saham/fetch_realtime.php?symbols=BBCA
```
