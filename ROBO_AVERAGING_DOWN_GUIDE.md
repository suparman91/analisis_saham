# Robo Trader Simulator - Averaging-Down Feature Guide

## 📋 Overview

Sistem robo trader simulator telah ditingkatkan dengan fitur **averaging-down** dan **hourly auto-analysis**. Robot sekarang dapat:
1. Berjalan otomatis setiap 1 jam (via JavaScript di portfolio.php)
2. Menganalisis posisi dengan loss tolerance intelligent
3. Membeli kembali (accumulate) saat harga turun untuk rata-rata entry price
4. Memberikan kelonggaran (leeway) sebelum force sell

---

## 🤖 Logika Averaging-Down

Robot menggunakan loss tolerance bertingkat untuk keputusan buy/hold/sell:

### Loss Percentage Zones:

| Loss % | Aksi | Keterangan |
|--------|------|-----------|
| **≥ 0%** | HOLD | Posisi menguntungkan, tahan |
| **-2% to 0%** | ACCUMULATE | Beli lebih banyak untuk rata-rata harga (max 3x) |
| **-2% to -5%** | WARNING | Monitor, jangan jual tapi jangan akumulasi |
| **-5% to -10%** | KELONGGARAN | Leeway zone - beri waktu recovery, jangan jual |
| **< -10%** | FORCE SELL | Pertimbangkan jual (critical loss) |

### Tresholds Configuration (di robo_run_now.php & robo_cron.php):

```php
$LOSS_HOLDOFF_PCT = -0.02;      // -2% - threshold untuk akumulasi agresif
$LOSS_WARN_PCT = -0.05;         // -5% - batas warning zone
$LOSS_CRITICAL_PCT = -0.10;     // -10% - batas force sell consideration
$MAX_ACCUMULATION_TIMES = 3;    // Max 3x akumulasi per saham
```

---

## 🔄 Hourly Auto-Analysis (New Feature)

### Cara Kerja:

1. **Auto-trigger setiap 1 jam** (via JavaScript setInterval)
2. Panggil endpoint: `robo_hourly_run.php`
3. Analisis menggunakan loss tolerance logic
4. Tampilkan hasil di dashboard panel
5. Reset timer untuk 1 jam berikutnya

### Endpoint: `robo_hourly_run.php`

Method: **POST**  
Returns: **JSON**

```javascript
{
  "timestamp": "2026-04-18 14:30:45",
  "status": "success",
  "accumulation_buys": [
    {
      "symbol": "BBCA",
      "entry": 10000,
      "current": 9800,
      "pl_pct": -2.0,
      "accumulation_count": 1,
      "reason": "Rugi kecil, akumulasi untuk rata-rata harga"
    }
  ],
  "forced_sells": [
    {
      "symbol": "BRIS",
      "entry": 12000,
      "current": 10800,
      "pl_pct": -10.0,
      "reason": "Rugi -10%, pertimbangkan jual"
    }
  ],
  "hold_signals": [...],
  "new_candidates": [...],
  "summary": {
    "accumulation_candidates": 1,
    "hold_positions": 5,
    "forced_sell_candidates": 1,
    "new_opportunities": 3
  }
}
```

### UI Display (portfolio.php):

Panel baru menampilkan hasil analisis dengan kategori:
- 📈 **Akumulasi** (Rata-rata Harga) - Saham yang loss < -2%, siap accumulate
- ⏸ **Tahan Posisi** - Saham dalam monitoring/recovery zone
- 🚫 **Pertimbangkan Jual** - Saham dengan critical loss > -10%
- ⭐ **Kandidat Baru** - Peluang beli baru dengan sinyal Golden Cross

Countdown timer menunjukkan sisa waktu hingga analisis berikutnya.

---

## 📊 Execution Logic (robo_run_now.php & robo_cron.php)

### Phase 1: Exit Strategy + Averaging-Down
```
Untuk setiap OPEN position:
1. Hitung current P/L %
2. Check kondisi akumulasi:
   - Jika loss -2% to 0% dan count < 3x → ACCUMULATE
3. Check kondisi sell:
   - Jika profit ≥ 5% → TAKE PROFIT
   - Jika loss ≤ -10% → CRITICAL LOSS (sell)
   - Jika loss -3% to -5% → STOP LOSS (sell)
   - Jika loss -5% to -3% → HOLD (kelonggaran)
4. Execute accumulation atau sell dengan realtime price
```

### Phase 2: Entry Strategy (New Signals)
- Scan untuk candidate dengan Golden Cross + volume
- Top 2 scores dipilih untuk BUY
- Allocate berdasarkan available balance dan open position slots

### Phase 3: Audit Logging
```php
// Contoh audit summary
"BUY 1, SELL 1, ACCUMULATE 2"

// Detail
"Candidates: 15 | Notes: Accumulated BBCA 2x, Closed BRIS at critical loss"
```

---

## 🎯 Example Scenario

**Initial State:**
- Position BBCA: Beli @ Rp 10.000, Lots 1, Current Rp 9.800 (-2%)
- Cash: Rp 10 juta

**Hourly Analysis (Hour 1):**
- BBCA rugi -2% → **ACCUMULATE** triggered
- Robot beli Rp 5juta/100 di Rp 9.800 → 5 lots tambahan
- New average price: (10.000 + 9.800)/2 = 9.900
- Status di dashboard: "Akumulasi untuk rata-rata harga"

**Next Hour:**
- Harga naik ke Rp 9.950
- BBCA now +0.5% profit on new average price
- Posisi: HOLD, tunggu untuk TP atau accumulate lagi jika turun

**Skenario Worst Case:**
- Harga terus turun ke Rp 9.500
- Loss mencapai -5% → **KELONGGARAN ZONE** (no sell yet, tapi no accumulate)
- Jika turun lagi ke Rp 9.000 (-10%) → **FORCED SELL** triggered

---

## 🛠️ Configuration untuk Customize

### Di robo_run_now.php & robo_cron.php:

```php
// Jika mau lebih conservative:
$LOSS_HOLDOFF_PCT = -0.01;      // Mulai accumulate di -1% saja
$MAX_ACCUMULATION_TIMES = 2;    // Max 2x saja

// Jika mau lebih aggressive:
$LOSS_HOLDOFF_PCT = -0.03;      // Accumulate sampai -3%
$MAX_ACCUMULATION_TIMES = 5;    // Max 5x

// Jika mau change critical loss threshold:
$LOSS_CRITICAL_PCT = -0.15;     // Tunggu -15% baru force sell
```

---

## 🔔 Audit Log Tracking

Semua keputusan robot (BUY/SELL/ACCUMULATE/HOLD) dicatat di tabel `robo_audit_logs`:

```sql
SELECT * FROM robo_audit_logs 
WHERE user_id = 1 AND run_type IN ('cron', 'manual')
ORDER BY created_at DESC 
LIMIT 20;
```

Contoh audit entry:
```
id: 152
user_id: 1
run_type: cron
action_summary: BUY 1, SELL 1, ACCUMULATE 2
decision_detail: Candidates: 18 | Notes: Accumulated BBCA 2x at -2% and -1.5%, Closed BRIS at critical loss -10.2%
created_at: 2026-04-18 16:30:00
```

---

## ⚠️ Limitasi & Risk Management

### Kelemahan Averaging-Down:
1. **Kompounding Loss** - Jika harga terus turun, loss bisa sangat besar
2. **Capital Allocation** - Banyak modal terserap untuk accumulation
3. **Recovery Wait** - Tunggu lama untuk recovery ke break-even

### Risk Mitigation:
- Max accumulation 3x saja (prevent excessive capital allocation)
- Critical loss threshold di -10% (force close jika sangat rugi)
- Kelonggaran zone -5% to -10% (give time untuk recovery tapi monitoring ketat)
- Hourly analysis (realtime monitoring, bukan blind)

---

## 📱 Browser Auto-Refresh Setup

Portfolio.php sekarang punya dua timer:

1. **Live Price Refresh**: Setiap 30 detik
   - Update harga OPEN positions
   - Update unrealized P/L cards
   - Manual refresh button tersedia

2. **Hourly Robo Analysis**: Setiap 1 jam
   - Auto trigger robo_hourly_run.php
   - Update decision panel
   - Show accumulation/hold/sell candidates
   - Countdown timer

### User bisa:
- ✅ Hard refresh (Ctrl+F5) untuk clear cache
- ✅ Manual run robot: portfolio.php?robo_run=manual
- ✅ View audit logs: portfolio.php (scroll ke bawah)
- ✅ Customize thresholds di robo_run_now.php

---

## 🚀 Next Steps (Optional Enhancement)

1. **Telegram Alerts untuk Accumulation**
   - Notify user saat robot accumulate
   - Update when critical loss detected

2. **User Settings UI**
   - Allow user customize thresholds tanpa edit PHP
   - Save per-user configuration

3. **Performance Stats Dashboard**
   - Average buy price tracking
   - Accumulation cost analysis
   - ROI calculation with averaging

4. **Manual Approval Mode**
   - Semua BUY/ACCUMULATE perlu approval user
   - SELL otomatis (emergency exit)

---

**Status**: ✅ Implemented & Validated  
**Last Updated**: April 18, 2026  
**Version**: 1.0 (Averaging-Down Release)
