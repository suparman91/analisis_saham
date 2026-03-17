<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Scan BPJP & BSJP</title>
    <style>
        /* Navigation Menu */
        .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .top-menu a:hover { background: #1e293b; color: #fff; }
        .top-menu a.active { background: #3b82f6; color: #fff; }

        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; max-width: 400px; }
        button { 
            padding: 10px 15px; 
            font-size: 16px; 
            cursor: pointer; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px;
        }
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .status { margin-top: 10px; font-weight: bold; color: #d9534f; }
        .status.active { color: #5cb85c; }
        
        /* Layout Grid untuk Mobile */
        .grid-container { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (min-width: 768px) {
            .grid-container { grid-template-columns: 1fr 1fr; }
            .top-menu { flex-direction: row; }
        }
        @media (max-width: 768px) {
            .top-menu { flex-direction: column; align-items: stretch; text-align: center; }
            .card { max-width: 100%; }
            body { padding: 10px; }
            table { font-size: 12px; }
        }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .history-table th, .history-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .history-table th { background-color: #f8f9fa; }
    </style>
</head>
<body>

    <nav class="top-menu">
        <a href="index.php">📊 Dashboard Market</a>
        <a href="ihsg.php">&#x1F4C8; Chart IHSG</a>
          <a href="chart.php">📈 Chart & Analisis</a>
        <a href="scan_manual.php" class="active">🔍 Scanner BSJP/BPJP</a>
        <a href="stockpick.php">🎯 AI Stockpick Tracker</a>
        <a href="ara_hunter.php">🚀 ARA Hunter</a>
          <a href="arb_hunter.php">&#x1F4C9; ARB Hunter</a>
        <a href="portfolio.php">&#x1F4BC; Autopilot Portofolio</a>
        <a href="telegram_setting.php" style="margin-left:auto; background:#475569;"><img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" width="14" style="vertical-align:middle;margin-right:5px;">Set Alert</a>
    </nav>
    
    <h2>Scanner Manual Multistrategi</h2>
    <p>Waktu Server / Browser: <strong id="current-time" style="color:#007bff;">Loading...</strong></p>

    <div class="grid-container" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <div class="card">
            <h3>BPJP (Scalping Pagi)</h3>
            <p>Jadwal Buka: 08:50 - 09:00 WIB (Senin - Jumat)</p>
            <button id="btn-bpjp" onclick="runScan('BPJP')" disabled>Jalankan Scan BPJP</button>
            <div id="status-bpjp" class="status">Tombol belum aktif</div>
        </div>

        <div class="card">
            <h3>BSJP (Swing Sore)</h3>
            <p>Jadwal Buka: 15:30 - 16:00 WIB (Senin - Jumat)</p>
            <button id="btn-bsjp" onclick="runScan('BSJP')" disabled>Jalankan Scan BSJP</button>
            <div id="status-bsjp" class="status">Tombol belum aktif</div>
        </div>

        <div class="card" style="border-color: #3b82f6;">
            <h3>Swing / Uptrend Tracker</h3>
            <p>Jadwal: Kapan Saja (Data EOD / Tengah Malam)</p>
            <button id="btn-swing" onclick="runScan('SWING')" style="background-color:#1e293b;">Jalankan Swing Scan</button>
            <div id="status-swing" class="status active">Menu Terbuka 24/7</div>
        </div>
    </div>

    <!-- Riwayat Scan Tersimpan -->
    <div class="card" style="max-width:100%;">
        <h3>Riwayat Scan Terakhir (Tersimpan di Database)</h3>
        <div style="overflow-x:auto;">
        <?php
        require_once "db.php";
        $db = db_connect();
        
        // Pengecekan pdo atau mysqli
        if ($db instanceof PDO) {
            $stmt = $db->query("SELECT * FROM scan_history ORDER BY created_at DESC LIMIT 15");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $res = $db->query("SELECT * FROM scan_history ORDER BY created_at DESC LIMIT 15");
            $rows = [];
            if ($res && $res->num_rows > 0) {
                while($row = $res->fetch_assoc()) $rows[] = $row;
            }
        }
        
        if (count($rows) > 0) {
            echo "<table class='history-table'>";
            echo "<tr><th>Tipe</th><th>Saham</th><th>Harga (Rp)</th><th>Tanggal Data</th><th>Waktu Scan</th></tr>";
            foreach ($rows as $row) {
                echo "<tr>";
                echo "<td><span class='status active'>{$row['scan_type']}</span></td>";
                echo "<td><strong>{$row['symbol']}</strong></td>";
                echo "<td>" . number_format($row['price'],0,',','.') . "</td>";
                echo "<td>{$row['scan_date']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Belum ada riwayat hasil scan yang tersimpan.</p>";
        }
        ?>
        </div>
    </div>

    <!-- Kontainer Hasil Scan -->
    <div id="result-container" class="card" style="display: none; max-width: 800px; width: 100%;">
        <!-- Hasil dari PHP scan_bpjs_bsjp.php akan tampil di sini! -->
    </div>

    <script>
        // Set zona waktu ke Asia/Jakarta
        function updateTime() {
            const now = new Date();
            const options = { timeZone: 'Asia/Jakarta', hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const timeString = now.toLocaleTimeString('id-ID', options);
            
            // Format jam untuk perbandingan "HH.MM" -> pastikan formatnya HH:MM
            // Di beberapa sistem toLocaleTimeString ('id-ID') menghasilkan "08.50.00" (pakai titik)
            // Jadi kita ganti semua titik menjadi titik dua supaya aman saat dibanding "08:50"
            const normalizedTime = timeString.replace(/\./g, ':');
            const timeCompare = normalizedTime.substring(0, 5);
            
            // Ambil Hari dari Date object standar supaya tidak terdampak format string
            const dayOfWeek = now.getDay(); // 0 = Minggu, 1 = Senin, ... 6 = Sabtu
            
            document.getElementById('current-time').innerText = timeString;

            const btnBpjp = document.getElementById('btn-bpjp');
            const statusBpjp = document.getElementById('status-bpjp');
            
            const btnBsjp = document.getElementById('btn-bsjp');
            const statusBsjp = document.getElementById('status-bsjp');

            // Cek apakah hari ini Senin - Jumat (1 - 5)
            // SEMENTARA DIBUKA UNTUK UJI COBA (TIDAK ADA BATASAN HARI DAN JAM)
            if (true) { // Aslinya: (dayOfWeek >= 1 && dayOfWeek <= 5)
                
                // Logika BPJP (08:50 - 09:00) 
                // SEMENTARA DIBUKA
                if (true) { // Aslinya: (timeCompare >= "08:50" && timeCompare <= "09:00")
                    btnBpjp.disabled = false;
                    statusBpjp.innerHTML = "Sesi BPJP Dibuka! Tombol Aktif. <span style='color:orange;'>(Mode Uji Coba)</span>";
                    statusBpjp.className = "status active";
                } else {
                    btnBpjp.disabled = true;
                    statusBpjp.innerText = "Di luar jam BPJP.";
                    statusBpjp.className = "status";
                }

                // Logika BSJP (15:30 - 16:00)
                // SEMENTARA DIBUKA
                if (true) { // Aslinya: (timeCompare >= "15:30" && timeCompare <= "16:00")
                    btnBsjp.disabled = false;
                    statusBsjp.innerHTML = "Sesi BSJP Dibuka! Tombol Aktif. <span style='color:orange;'>(Mode Uji Coba)</span>";
                    statusBsjp.className = "status active";
                } else {
                    btnBsjp.disabled = true;
                    statusBsjp.innerText = "Di luar jam BSJP.";
                    statusBsjp.className = "status";
                }
            } else {
                // Hari Libur
                btnBpjp.disabled = true;
                btnBsjp.disabled = true;
                statusBpjp.innerText = "Hari Libur Bursa";
                statusBpjp.className = "status";
                statusBsjp.innerText = "Hari Libur Bursa";
                statusBsjp.className = "status";
            }
        }

        function runScan(type) {
            let btnId = 'btn-' + type.toLowerCase();
            const btn = document.getElementById(btnId);
            const resultDiv = document.getElementById('result-container');

            // Ubah tombol sebentar jadi loading
            const originalText = btn.innerText;
            btn.innerText = "Scanning...";

            // Siapkan div hasil dengan pesan loading
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = "<p>Menganalisis Algoritma <b>" + type + "</b> (Proses Cepat di Database)...</p>";

            // LANGSUNG eksekusi database tanpa nge-fetch sinkronisasi Live Yahoo yg bikin lama.
            fetch('scan_bpjs_bsjp.php?tipe=' + type)
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = data;
                    btn.innerText = "Selesai!";
                    setTimeout(() => btn.innerText = originalText, 1500);
                })
                .catch(err => {
                    resultDiv.innerHTML = "<p style='color:red;'>Terjadi kesalahan: " + err + "</p>";
                    btn.innerText = originalText;
                });
        }

        // Jalankan update waktu setiap 1 detik
        setInterval(updateTime, 1000);
        updateTime(); // Panggil pertama kali
    </script>
</body>
</html>

