<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Scan BPJP & BSJP</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
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
    </style>
</head>
<body>

    <h2>Scan Saham Manual (Berdasarkan Jam Bursa)</h2>
    <p>Waktu saat ini (Server): <span id="current-time">Loading...</span> WIB</p>

    <div class="card">
        <h3>BPJP (Beli Pagi Jual Pagi)</h3>
        <p>Jadwal Buka: 08:50 - 09:00 WIB (Senin - Jumat)</p>
        <button id="btn-bpjp" onclick="runScan('BPJP')" disabled>Jalankan Scan BPJP</button>
        <div id="status-bpjp" class="status">Tombol belum aktif</div>
    </div>

    <div class="card">
        <h3>BSJP (Beli Sore Jual Pagi)</h3>
        <p>Jadwal Buka: 15:30 - 16:00 WIB (Senin - Jumat)</p>
        <button id="btn-bsjp" onclick="runScan('BSJP')" disabled>Jalankan Scan BSJP</button>
        <div id="status-bsjp" class="status">Tombol belum aktif</div>
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
            const btn = (type === 'BPJP') ? document.getElementById('btn-bpjp') : document.getElementById('btn-bsjp');
            const resultDiv = document.getElementById('result-container');
            
            // Ubah tombol sebentar jadi loading
            const originalText = btn.innerText;
            btn.innerText = "Sync & Loading...";
            
            // Siapkan div hasil dengan pesan loading
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = "<p><em>[Langkah 1/2]</em> Sedang menyinkronkan data realtime dari pasar. Mohon tunggu beberapa saat...</p>";

            // Fetch realtime data SEBELUM discan (Update database ke kondisi hari/detik ini)
            // KITA BATASI KE EMITEN LIQUID/LQ45 AGAR PROSESNYA SANGAT CEPAT
            // Pastikan format ticker menggunakan format database Anda (.JK)
            const liquidSymbols = "BBCA.JK,BBRI.JK,BBNI.JK,BMRI.JK,ASII.JK,TLKM.JK,GOTO.JK,ADRO.JK,AMMN.JK,AMRT.JK,UNTR.JK,KLBF.JK,CPIN.JK,ICBP.JK,INDF.JK,BRPT.JK,PTBA.JK,ITMG.JK,PGAS.JK,MEDC.JK";
            fetch('fetch_realtime.php?symbols=' + liquidSymbols)
                .then(res => res.text()) // bisa return json/text
                .then(syncData => {
                    // Update UI kalau sync beres
                    resultDiv.innerHTML = "<p><em>[Langkah 2/2]</em> Sinkronisasi cepat (Liquid Stocks) berhasil! Mengeksekusi rumus dan menarik rekomendasi saham...</p>";
                    
                    // Lanjut Eksekusi Script Scan di DB yang sudah diupdate
                    return fetch('scan_bpjs_bsjp.php?tipe=' + type);
                })
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = data;
                    btn.innerText = originalText;
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