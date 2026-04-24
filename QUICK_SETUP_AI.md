# 🚀 Quick Setup - AI Analysis Feature

## 5-Minute Setup Guide

### Step 1: Get API Token (2 minutes)
1. Visit: https://aistudio.google.com/apikey
2. Click "Create API Key"
3. Copy your token
4. Keep it safe!

### Step 2: Test Manual Analysis (3 minutes)
1. Go to: `http://localhost/analisis_saham/chart.php`
2. Select any stock (e.g., BBCA.JK)
3. Click **🤖 AI Analysis** button (next to "Reset Zoom")
4. Paste your API token
5. Select timeframe (1M recommended)
6. Click **Lanjut**
7. Wait 30-60 seconds for AI to process

### Step 3: View Results
You should see:
- ✅ Speedometer recommendation (SELL/HOLD/BUY/HOT)
- ✅ Detailed technical analysis
- ✅ Fundamental analysis
- ✅ Sentiment analysis  
- ✅ Risk factors
- ✅ Entry/Exit prices
- ✅ Confidence level

---

## Testing Checklist

- [ ] Manual analysis works for at least 3 different stocks
- [ ] Speedometer needle moves to correct position
- [ ] All analysis sections display content
- [ ] Entry, TP, SL prices are shown
- [ ] Token is saved in localStorage (don't need to re-enter)

---

## Common Issues & Quick Fixes

| Issue | Fix |
|-------|-----|
| "No API token provided" | Make sure to paste full token in modal |
| "HTTP 400 Error" | Check token validity at console.cloud.google.com |
| "Analysis taking too long" | Normal - may take 30-60 seconds on first load |
| "Speedometer not showing" | Try refreshing page after analysis completes |
| Button not showing | Select a stock first, button appears |

---

## Next Steps (Optional)

### Setup Auto-Analysis
1. Set environment variable: `GEMINI_API_TOKEN=your_token`
2. Setup cron job (Linux): `crontab -e`
   ```
   30 4 * * 1-5 php /path/to/auto_ai_analysis.php
   ```
3. Or Task Scheduler (Windows) pointing to `auto_ai_analysis.php`
4. Check logs in: `logs/auto_analysis.log`

### Integrate with Other Features
- Add AI results to portfolio analysis
- Show recommendations in stock watchlist
- Send notifications for HOT recommendations
- Track AI recommendation accuracy

---

## File Locations

| File | Purpose | Location |
|------|---------|----------|
| **chart.php** | Main interface | `/analisis_saham/chart.php` |
| **gemini_analyze.php** | API handler | `/analisis_saham/gemini_analyze.php` |
| **ai_analysis_aggregate.php** | Data collector | `/analisis_saham/ai_analysis_aggregate.php` |
| **auto_ai_analysis.php** | Auto scheduler | `/analisis_saham/auto_ai_analysis.php` |
| **Documentation** | Full guide | `/analisis_saham/AI_ANALYSIS_GUIDE.md` |

---

## Support

- 📖 Full documentation: `AI_ANALYSIS_GUIDE.md`
- 🐛 Check logs: `logs/auto_analysis.log`
- 🔧 Edit prompts in: `gemini_analyze.php`
- 💾 View results in DB: `ai_analysis_cache` table

---

**Ready to analyze?** Go to Chart page and select a stock! 🚀
