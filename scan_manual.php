<?php
require_once 'auth.php';
require_login();

require_once __DIR__ . '/db.php';
$mysqli = db_connect();
require_subscription($mysqli);
?>
<?php
$pageTitle = 'Manual Scan BPJP & BSJP';
?>
<?php include 'header.php'; ?>
    <style>
        /* Navigation Menu */
        .top-menu { background: #0f172a; padding: 12px 20px; display: flex; align-items: center; gap: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .top-menu a { color: #cbd5e1; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .top-menu a:hover { background: #1e293b; color: #fff; }
        .top-menu a.active { background: #3b82f6; color: #fff; }

        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1600px; margin: 0 auto; }
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
        .history-header { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .history-content { overflow-x:auto; overflow-y:auto; max-height: 340px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; }
        .history-content.collapsed { display:none; }
        .btn-toggle-history { background:#334155; font-size:13px; padding:7px 10px; }
        #result-container { max-width: 100% !important; width: 100% !important; }
    </style>
    
    <h2>Scanner Manual Multistrategi</h2>
    <p>Waktu Server / Browser: <strong id="current-time" style="color:#007bff;">Loading...</strong></p>

    <div class="grid-container" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <div class="card">
            <h3>BPJP (Scalping Pagi)</h3>
            <p>Jadwal Buka: 08:50 - 09:05 WIB (Prime: 08:55 - 09:00, Senin - Jumat)</p>
            <button id="btn-bpjp" onclick="runScan('BPJP')" disabled>Jalankan Scan BPJP</button>
            <div id="status-bpjp" class="status">Tombol belum aktif</div>
        </div>

        <div class="card">
            <h3>BSJP (Swing Sore)</h3>
            <p>Jadwal Buka: 15:45 - 16:00 WIB (Prime: 15:55 - 16:00, Senin - Jumat)</p>
            <button id="btn-bsjp" onclick="runScan('BSJP')" disabled>Jalankan Scan BSJP</button>
            <div id="status-bsjp" class="status">Tombol belum aktif</div>
        </div>

        <div class="card" style="border-color: #3b82f6;">
            <h3>Swing / Uptrend Tracker</h3>
            <p>Jadwal: Kapan Saja (Data EOD / Tengah Malam)</p>
            <button id="btn-swing" onclick="runScan('SWING')" style="background-color:#1e293b;">Jalankan Swing Scan</button>
            <div id="status-swing" class="status active">Menu Terbuka 24/7</div>
        </div>

        <div class="card" style="border-color: #0f766e;">
            <h3>After Close Scanner</h3>
            <p>Jadwal Ideal: 16:05 - 18:00 WIB (Setelah Market Tutup)</p>
            <button id="btn-after_close" onclick="runScan('AFTER_CLOSE')" style="background-color:#0f766e;">Jalankan After Close</button>
            <div id="status-after_close" class="status active">Menu Terbuka Setelah Penutupan</div>
        </div>
    </div>

    <!-- Riwayat Scan Tersimpan -->
    <div class="card" style="max-width:100%;">
        <div class="history-header">
            <h3 style="margin:0;">Riwayat Scan Terakhir (Tersimpan di Database)</h3>
            <button type="button" id="btnToggleHistory" class="btn-toggle-history">Tampilkan Riwayat</button>
        </div>
        <div id="historyContent" class="history-content collapsed">
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
    <div id="result-container" class="card" style="display: none; max-width: 100%; width: 100%;">
        <!-- Hasil dari PHP scan_bpjs_bsjp.php akan tampil di sini! -->
    </div>

    <script>
        function isTimeBetween(time, start, end) {
            return time >= start && time <= end;
        }

        function getWindowMode(type, timeCompare) {
            if (type === 'BPJP') {
                if (isTimeBetween(timeCompare, '08:55', '09:00')) return 'bpjp_prime_5m';
                if (isTimeBetween(timeCompare, '08:50', '09:05')) return 'bpjp_open_15m';
                return 'bpjp_outside';
            }
            if (type === 'BSJP') {
                if (isTimeBetween(timeCompare, '15:55', '16:00')) return 'bsjp_prime_5m';
                if (isTimeBetween(timeCompare, '15:45', '16:00')) return 'bsjp_open_15m';
                return 'bsjp_outside';
            }
            return 'standard';
        }

        function triggerAutoSyncOnce(tag) {
            const today = new Date().toISOString().split('T')[0];
            const key = 'scan_sync_' + tag + '_' + today;
            if (localStorage.getItem(key) === '1') return;

            fetch('ajax_update.php')
                .then(() => localStorage.setItem(key, '1'))
                .catch(() => {});
        }

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

            const debugAlwaysOpen = new URLSearchParams(window.location.search).get('debug_time') === '1';
            const isWeekday = (dayOfWeek >= 1 && dayOfWeek <= 5);
            const isBpjpOpen = isTimeBetween(timeCompare, '08:50', '09:05');
            const isBpjpPrime = isTimeBetween(timeCompare, '08:55', '09:00');
            const isBsjpOpen = isTimeBetween(timeCompare, '15:45', '16:00');
            const isBsjpPrime = isTimeBetween(timeCompare, '15:55', '16:00');

            // Cek apakah hari ini Senin - Jumat (1 - 5)
            if (isWeekday || debugAlwaysOpen) {

                // Logika BPJP (08:50 - 09:05) dengan fokus 5 menit menjelang open
                if (isBpjpOpen || debugAlwaysOpen) {
                    btnBpjp.disabled = false;
                    statusBpjp.innerHTML = isBpjpPrime
                        ? "Sesi BPJP PRIME (08:55-09:00) aktif. Fokus kandidat volume tertinggi."
                        : "Sesi BPJP 15 menit aktif (08:50-09:05).";
                    statusBpjp.className = "status active";
                } else {
                    btnBpjp.disabled = true;
                    statusBpjp.innerText = "Di luar jam BPJP.";
                    statusBpjp.className = "status";
                }

                // Logika BSJP (15:45 - 16:00) dengan fokus 5 menit menjelang close
                if (isBsjpOpen || debugAlwaysOpen) {
                    btnBsjp.disabled = false;
                    statusBsjp.innerHTML = isBsjpPrime
                        ? "Sesi BSJP PRIME (15:55-16:00) aktif. Prioritas momentum penutupan."
                        : "Sesi BSJP 15 menit aktif (15:45-16:00).";
                    statusBsjp.className = "status active";
                } else {
                    btnBsjp.disabled = true;
                    statusBsjp.innerText = "Di luar jam BSJP.";
                    statusBsjp.className = "status";
                }

                // Sinkronisasi data otomatis sekali per window agar data scanner lebih match terhadap sesi.
                if (isBpjpPrime) triggerAutoSyncOnce('bpjp_prime_5m');
                if (isBsjpOpen && !isBsjpPrime) triggerAutoSyncOnce('bsjp_open_15m');
                if (isBsjpPrime) triggerAutoSyncOnce('bsjp_prime_5m');
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
            const now = new Date();
            const timeCompare = now.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour12: false, hour: '2-digit', minute: '2-digit' }).replace(/\./g, ':');
            const windowMode = getWindowMode(type, timeCompare);

            // Ubah tombol sebentar jadi loading
            const originalText = btn.innerText;
            btn.innerText = "Scanning...";

            // Siapkan div hasil dengan pesan loading
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = "<p>Menganalisis Algoritma <b>" + type + "</b> (Window: <b>" + windowMode + "</b>)...</p>";

            // LANGSUNG eksekusi database tanpa nge-fetch sinkronisasi Live Yahoo yg bikin lama.
            fetch('scan_bpjs_bsjp.php?tipe=' + type + '&window=' + encodeURIComponent(windowMode))
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = data;
                    enhanceScanResult(resultDiv, type);
                    btn.innerText = "Selesai!";
                    setTimeout(() => btn.innerText = originalText, 1500);
                })
                .catch(err => {
                    resultDiv.innerHTML = "<p style='color:red;'>Terjadi kesalahan: " + err + "</p>";
                    btn.innerText = originalText;
                });
        }

        function tableToCSV(tableEl) {
            const rows = Array.from(tableEl.querySelectorAll('tr'));
            return rows.map(row => {
                const cols = Array.from(row.querySelectorAll('th,td'));
                return cols.map(col => {
                    const text = (col.innerText || '').replace(/\s+/g, ' ').trim();
                    const escaped = text.replace(/"/g, '""');
                    return '"' + escaped + '"';
                }).join(',');
            }).join('\n');
        }

        function enhanceScanResult(container, scanType) {
            const table = container.querySelector('table');
            if (!table) return;

            const allRows = Array.from(table.querySelectorAll('tr')).slice(1); // skip header
            if (allRows.length === 0) return;

            const toolbar = document.createElement('div');
            toolbar.style.display = 'flex';
            toolbar.style.flexWrap = 'wrap';
            toolbar.style.gap = '10px';
            toolbar.style.alignItems = 'center';
            toolbar.style.margin = '12px 0';

            const totalInfo = document.createElement('span');
            totalInfo.style.fontWeight = 'bold';
            totalInfo.style.color = '#1e293b';
            totalInfo.innerText = 'Total hasil: ' + allRows.length + ' saham';

            const perPageLabel = document.createElement('label');
            perPageLabel.innerText = 'Baris per halaman:';
            perPageLabel.style.fontSize = '14px';

            const perPageSelect = document.createElement('select');
            perPageSelect.style.padding = '6px';
            perPageSelect.style.border = '1px solid #cbd5e1';
            perPageSelect.style.borderRadius = '4px';
            [25, 50, 100, 200].forEach(n => {
                const opt = document.createElement('option');
                opt.value = String(n);
                opt.textContent = String(n);
                if (n === 50) opt.selected = true;
                perPageSelect.appendChild(opt);
            });

            const btnPrev = document.createElement('button');
            btnPrev.type = 'button';
            btnPrev.innerText = '← Prev';
            btnPrev.style.background = '#334155';

            const pageInfo = document.createElement('span');
            pageInfo.style.minWidth = '110px';
            pageInfo.style.textAlign = 'center';
            pageInfo.style.fontWeight = 'bold';

            const btnNext = document.createElement('button');
            btnNext.type = 'button';
            btnNext.innerText = 'Next →';
            btnNext.style.background = '#334155';

            const btnExport = document.createElement('button');
            btnExport.type = 'button';
            btnExport.innerText = 'Export CSV';
            btnExport.style.background = '#0f766e';

            toolbar.appendChild(totalInfo);
            toolbar.appendChild(perPageLabel);
            toolbar.appendChild(perPageSelect);
            toolbar.appendChild(btnPrev);
            toolbar.appendChild(pageInfo);
            toolbar.appendChild(btnNext);
            toolbar.appendChild(btnExport);

            table.parentElement.insertBefore(toolbar, table);

            let currentPage = 1;
            function renderPage() {
                const perPage = Number(perPageSelect.value) || 50;
                const totalPages = Math.max(1, Math.ceil(allRows.length / perPage));
                if (currentPage > totalPages) currentPage = totalPages;

                const start = (currentPage - 1) * perPage;
                const end = start + perPage;

                allRows.forEach((row, idx) => {
                    row.style.display = (idx >= start && idx < end) ? '' : 'none';
                });

                pageInfo.innerText = 'Hal ' + currentPage + '/' + totalPages;
                btnPrev.disabled = currentPage <= 1;
                btnNext.disabled = currentPage >= totalPages;
            }

            btnPrev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage();
                }
            });

            btnNext.addEventListener('click', () => {
                const perPage = Number(perPageSelect.value) || 50;
                const totalPages = Math.max(1, Math.ceil(allRows.length / perPage));
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPage();
                }
            });

            perPageSelect.addEventListener('change', () => {
                currentPage = 1;
                renderPage();
            });

            btnExport.addEventListener('click', () => {
                const csv = tableToCSV(table);
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const stamp = now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '_' + pad(now.getHours()) + pad(now.getMinutes());
                a.href = url;
                a.download = 'scan_' + (scanType || 'result').toLowerCase() + '_' + stamp + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            renderPage();
        }

        function initHistoryToggle() {
            const btn = document.getElementById('btnToggleHistory');
            const content = document.getElementById('historyContent');
            if (!btn || !content) return;

            btn.addEventListener('click', () => {
                const isCollapsed = content.classList.toggle('collapsed');
                btn.innerText = isCollapsed ? 'Tampilkan Riwayat' : 'Sembunyikan Riwayat';
            });
        }

        // Jalankan update waktu setiap 1 detik
        setInterval(updateTime, 1000);
        updateTime(); // Panggil pertama kali
        initHistoryToggle();
    </script>
<?php include 'footer.php'; ?>

