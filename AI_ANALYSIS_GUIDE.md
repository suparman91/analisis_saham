# AI Analysis Feature Documentation

## Overview
Fitur **Analisis by AI** menggunakan Google Gemini API untuk memberikan analisis saham yang komprehensif mencakup teknikal, fundamental, sentimen, dan rekomendasi trading.

## Features

### 1. **Manual Analysis** (Real-time)
- Analisis on-demand dari Chart page
- Hanya perlu input Google Gemini API token
- Hasil instant dengan detail lengkap

### 2. **Automatic Analysis** (Cron-based)
- Analisis otomatis untuk top 20 stocks harian
- Hasil disimpan di cache database
- Bisa diintegrasikan dengan scheduler/cron job

### 3. **Comprehensive Analysis**
Analisis mencakup:
- **Teknikal**: Trend, SMA, RSI, MACD, Support/Resistance, Bollinger Bands, Volume
- **Fundamental**: P/E Ratio, PBV, ROE, DER, EPS Growth, Dividend Yield
- **Sentimen**: Market sentiment, news sentiment, investor interest
- **Risk Factors**: Technical, fundamental, dan market risks

### 4. **Speedometer Recommendation**
Visual recommendation display dengan 4 levels:
- 🔴 **SELL** - Rekomendasi jual (bearish)
- ⚪ **HOLD** - Pertahankan posisi (netral)
- 🟢 **BUY** - Rekomendasi beli (bullish)
- 🔵 **HOT** - Rekomendasi kuat (very bullish)

Dengan gauge-style speedometer untuk visualisasi intuitif.

### 5. **Trading Signals**
- Entry Price
- Take Profit Target
- Stop Loss Level
- Confidence Level (Rendah/Sedang/Tinggi/Sangat Tinggi)

---

## Setup

### 1. Get Google Gemini API Token

1. Go to [Google AI Studio](https://aistudio.google.com/apikey) atau [Google Cloud Console](https://console.cloud.google.com)
2. Create a new API key
3. Enable **Generative Language API**
4. Copy your API token

### 2. Configuration

#### Option A: Manual Input (Per Session)
1. Go to Chart page (`chart.php`)
2. Select a stock
3. Click **🤖 AI Analysis** button
4. Input your Gemini API token in modal
5. Select timeframe (1W, 1M, 3M, 6M, 1Y)
6. Click **Lanjut**

Token akan disimpan di browser localStorage otomatis.

#### Option B: Environment Variable (For Auto-Analysis)
Set environment variable untuk auto-analysis:
```bash
# Windows (set in system environment atau .env file)
set GEMINI_API_TOKEN=your_api_token_here

# Linux/Mac
export GEMINI_API_TOKEN=your_api_token_here
```

### 3. Database Setup

Sistem otomatis membuat table `ai_analysis_cache` pada saat pertama kali analisis. Tidak perlu setup manual.

### 4. Auto-Analysis Scheduler

#### Setup Cron Job (Linux/Mac)
```bash
# Edit crontab
crontab -e

# Add this line to run daily at 4:30 AM (before market opens at 9 AM)
30 4 * * 1-5 php /path/to/analisis_saham/auto_ai_analysis.php
```

#### Setup Task Scheduler (Windows)
1. Open Task Scheduler
2. Create Basic Task
3. Name: "AI Analysis Auto"
4. Trigger: Daily at 4:30 AM
5. Action: Start a program
   - Program: `php.exe`
   - Arguments: `D:\xampp\htdocs\analisis_saham\auto_ai_analysis.php`
   - Start in: `D:\xampp\htdocs\analisis_saham`

---

## Usage

### Manual Analysis
1. Open Chart page
2. Select stock symbol
3. Click **🤖 AI Analysis** button
4. Provide API token (if not saved)
5. View results in the analysis panel below

### Auto-Analysis Results
Auto-analysis results are cached. To view:
1. Query database: 
   ```sql
   SELECT * FROM ai_analysis_cache WHERE symbol = 'BBCA.JK';
   ```
2. Parse JSON `analysis` column for results

---

## API Endpoints

### `gemini_analyze.php`
**Method**: POST/GET

**Parameters**:
```json
{
  "symbol": "BBCA.JK",
  "timeframe": "1M",          // 1W, 1M, 3M, 6M, 1Y
  "api_token": "your_token",
  "mode": "manual"            // manual or auto
}
```

**Response**:
```json
{
  "success": true,
  "symbol": "BBCA.JK",
  "timeframe": "1M",
  "timestamp": "2026-04-24 10:30:00",
  "analysis": {
    "technical_analysis": "...",
    "fundamental_analysis": "...",
    "sentiment_analysis": "...",
    "risk_factors": "...",
    "conclusion": "...",
    "recommendation": "BUY",
    "recommendation_strength": "positif",
    "confidence_level": "tinggi",
    "entry_price": 17500,
    "take_profit": 18500,
    "stop_loss": 16500
  },
  "data_context": {
    "latest_price": 17200,
    "change_pct": 1.25,
    "trend": "Bullish",
    "rsi": 65,
    "macd_signal": "Positive"
  }
}
```

### `ai_analysis_aggregate.php`
Internal functions for data collection:
- `aggregate_stock_data($symbol, $timeframe)` - Kumpulkan semua data
- `calculate_technical_indicators($closes, $volumes)` - Hitung indikator teknikal
- `get_fundamental_data($mysqli, $symbol)` - Ambil data fundamental
- `get_sentiment_data($mysqli, $symbol)` - Ambil sentiment data
- `get_market_context($mysqli)` - Ambil konteks pasar

---

## Files Structure

```
analisis_saham/
├── chart.php                    # Main chart interface with AI Analysis UI
├── gemini_analyze.php           # Google Gemini API integration
├── ai_analysis_aggregate.php    # Data collection module
├── auto_ai_analysis.php         # Cron scheduler script
└── logs/
    └── auto_analysis.log        # Auto-analysis logs
```

---

## Cost Considerations

### Google Gemini API Pricing
- **Free Tier**: 60 requests per minute, generous free credits
- **Paid**: $0.075 per 1M input tokens, $0.30 per 1M output tokens

### Cost Optimization
- Use caching to avoid re-analyzing same stock
- Set reasonable analysis frequency (daily vs real-time)
- Use shorter timeframes (1M) for faster analysis

---

## Troubleshooting

### Issue: "No API token provided"
**Solution**: 
1. Click AI Analysis button
2. Input API token in modal
3. Ensure token is valid from console.cloud.google.com

### Issue: "Gemini API Error (HTTP 400)"
**Solution**:
1. Check API token validity
2. Ensure API is enabled in Google Cloud Console
3. Check Gemini 1.5 Flash model is available

### Issue: "Insufficient historical data"
**Solution**:
1. Wait for more price history to be populated
2. Try different symbol with more data
3. Check prices table is populated: `SELECT COUNT(*) FROM prices WHERE symbol = 'BBCA.JK'`

### Issue: "Analysis appears incomplete"
**Solution**:
1. Check Gemini token rate limit (60/min)
2. Wait 2-3 minutes before next analysis
3. Check internet connection

---

## Advanced Configuration

### Customize Analysis Timeframe
Modify `chart.php` aiTimeframeSelect options:
```html
<option value="1W">1 Minggu (1W)</option>
<option value="1M" selected>1 Bulan (1M)</option>
<option value="3M">3 Bulan (3M)</option>
<option value="6M">6 Bulan (6M)</option>
<option value="1Y">1 Tahun (1Y)</option>
```

### Change Speedometer Colors
Edit styles in `chart.php`:
```css
.speedometer {
  background: conic-gradient(
    red 0deg,        /* SELL */
    orange 45deg,    /* Transition */
    yellow 90deg,    /* HOLD */
    lightgreen 135deg, /* BUY */
    green 180deg     /* HOT */
  );
}
```

### Customize Analysis Prompt
Edit `build_analysis_prompt()` in `gemini_analyze.php` to add/remove data points or change analysis focus.

---

## Performance Tips

1. **Cache Results**: Auto-analysis saves results to DB, use cache for frequently accessed stocks
2. **Rate Limiting**: Space out API calls 2+ seconds apart
3. **Database Index**: Add index on `ai_analysis_cache.symbol` for faster lookups
   ```sql
   ALTER TABLE ai_analysis_cache ADD INDEX idx_symbol (symbol);
   ```
4. **Batch Analysis**: Run auto-analysis during off-market hours

---

## Security

### API Token Security
- ✅ Stored in browser localStorage (client-side)
- ✅ Never logged or transmitted to other servers
- ⚠️ Protect your API token - don't share publicly
- 🔐 Consider using environment variables for production

### Rate Limiting
- Gemini Free: 60 requests/minute
- Implement caching to respect limits

---

## Future Enhancements

Possible improvements:
- [ ] Multi-AI support (Anthropic Claude, OpenAI GPT-4)
- [ ] Historical analysis trend tracking
- [ ] AI confidence score alerts
- [ ] Portfolio-level AI analysis
- [ ] Discord/Telegram notifications with AI insights
- [ ] ML model training on past AI recommendations
- [ ] Real-time streaming updates during market hours
- [ ] Comparative analysis (stock vs peers/sector)

---

## Support & Feedback

For issues or feature requests:
1. Check logs: `logs/auto_analysis.log`
2. Test manual analysis first before auto-analysis
3. Verify API token and network connectivity
4. Check database table structure

---

**Last Updated**: 2026-04-24  
**Version**: 1.0.0
