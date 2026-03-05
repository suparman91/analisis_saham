# Analisis Saham IHSG (PHP + MySQL)

Ringkasan: proyek demo ini menyediakan kerangka sederhana untuk menganalisis saham IHSG menggunakan PHP + MySQL (mysqli). Termasuk indikator teknikal (SMA, EMA, RSI, MACD) dan penilaian fundamental sederhana.

Langkah setup:

1. Import database schema via phpMyAdmin atau CLI:

```sql
-- dari folder proyek
SOURCE schema.sql;
```

2. Sesuaikan koneksi database di `db.php` jika perlu (user/password).

3. Isi data historis dan fundamental:
 - Gunakan `fetch_data.php?type=prices&symbol=BBCA&file=prices_bbca.csv` untuk impor CSV price (header: date,open,high,low,close,volume).
 - Gunakan `fetch_data.php?type=fundamentals&symbol=BBCA&file=fund_bbca.csv` untuk impor fundamental (header: date,pe,pbv,roe,eps).

4. Buka `index.php` melalui server lokal (XAMPP): http://localhost/analisis_saham/

5. Pilih saham dan klik "Analisis". Grafik candlestick akan muncul dan API internal `analyze_api.php` menyediakan JSON hasil perhitungan.

Catatan & pengembangan selanjutnya:
- Tambahkan lebih banyak indikator (ADX, Bollinger Bands)
- Perbaiki scoring fundamental dengan data historis dan pembobotan lebih baik
- Integrasi data real-time via API (contoh: Yahoo Finance, AlphaVantage) — perhatikan rate limit dan lisensi
