<?php
require_once 'auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_crypto.php';
$mysqli = db_connect();
require_subscription($mysqli);

$user_id = get_user_id();
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';

// Auto-Create Tabel Penampung Chat ID (Jika Belum Ada)
$stmt_create = "CREATE TABLE IF NOT EXISTS telegram_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100),
    chat_id_encrypted VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_symbol (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$mysqli->query($stmt_create);

// Handle Save / Add Akun Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = $mysqli->real_escape_string($_POST['name']);
    $chat_id = trim($_POST['chat_id']);
    
    if ($chat_id !== '') {
        $encrypted = tg_encrypt($chat_id);
    
        $stmt = $mysqli->prepare("INSERT INTO telegram_subscribers (user_id, name, chat_id_encrypted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE chat_id_encrypted = ?");
        $stmt->bind_param('isss', $user_id, $name, $encrypted, $encrypted);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: telegram_setting.php?" . ($isEmbedded ? 'embed=1&' : '') . "msg=success_add");
    exit;
}

// Handle Delete Data
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $stmt = $mysqli->prepare("DELETE FROM telegram_subscribers WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    if ($isEmbedded) {
        header("Location: telegram_setting.php?embed=1&msg=success_del");
    } else {
        header("Location: telegram_setting.php?msg=success_del");
    }
    exit;
}

// Mengambil Data Untuk Ditampilkan (Per-User)
$stmt = $mysqli->prepare("SELECT * FROM telegram_subscribers WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<?php
$pageTitle = 'Pengaturan Telegram - Analisis Saham';
?>
<?php if (!$isEmbedded) include 'header.php'; ?>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f8f9fa; margin: 0; padding: 0; box-sizing: border-box; }
    .container { width: 100%; max-width: 860px; margin: 0; padding: 15px; box-sizing: border-box; background: #fff; border-radius: 8px; }
    h2 { color: #1e293b; margin-top: 0; }
    .nav-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #3b82f6; font-weight: bold; border: 1px solid #3b82f6; padding: 6px 12px; border-radius: 4px; }
    .nav-link:hover { background: #eff6ff; }
    
    .form-box { background: #f1f5f9; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-weight: bold; color: #475569; margin-bottom: 5px; font-size: 13px; }
    .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
    .btn { background: #10b981; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .btn:hover { background: #059669; }
    
    table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
    th { background: #e2e8f0; color: #334155; }
    .btn-danger { color: #fff; background: #ef4444; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; }
    .btn-danger:hover { background: #dc2626; }
    .badge-code { background: #1e293b; color: #38bdf8; padding: 4px 8px; border-radius: 4px; font-family: monospace; letter-spacing: 2px;}

    .alert { padding: 10px; background: #dcfce7; color: #065f46; border-left: 4px solid #10b981; margin-bottom: 15px; }
  </style>
  <div class="container">
      <?php if (!$isEmbedded): ?>
      <a href="ara_hunter.php" class="nav-link">← Kembali ke ARA Hunter</a>
      <?php endif; ?>
      <h2>📲 Pengaturan Penerima Notifikasi Telegram</h2>
      <p style="color:#64748b; font-size:14px;">Masukkan daftar akun telegram yang akan menerima update / alert bot secara otomatis. ID Telegram akan <strong>dienkripsi (AES-256)</strong> di dalam database sehingga tidak akan mudah disalahgunakan jika database bocor.</p>
      
      <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success_add'): ?>
          <div class="alert">✅ Akun penerima berhasil ditambahkan &amp; dienkripsi!</div>
      <?php endif; ?>
      <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success_del'): ?>
          <div class="alert">🗑️ Akun penerima berhasil dihapus!</div>
      <?php endif; ?>

      <div class="form-box">
          <form method="POST">
              <input type="hidden" name="action" value="add">
              <div class="form-group">
                  <label>Nama Pemilik (Alias)</label>
                  <input type="text" name="name" required placeholder="Contoh: Pak Budi CEO">
              </div>
              <div class="form-group">
                  <label>Chat ID Telegram</label>
                  <input type="text" name="chat_id" required placeholder="Contoh: 123456789">
              </div>
              <button type="submit" class="btn">+ Tambah Penerima</button>
          </form>
      </div>

      <h3>Daftar Penerima Aktif</h3>
      <table>
          <thead>
              <tr>
                  <th>Nama / Alias</th>
                  <th>Chat ID (Masked)</th>
                  <th>Status</th>
                  <th>Aksi</th>
              </tr>
          </thead>
          <tbody>
              <?php while ($row = $res->fetch_assoc()): ?>
              <?php 
                  $decrypted = tg_decrypt($row['chat_id_encrypted']);
                  // Jika berhasil decrypt, lakukan masking (bintang-bintang di depan, 4 angka terakhir saja yang terlihat)
                  $masked_view = ($decrypted) ? tg_mask($decrypted) : "⚠️ INVALID_DATA"; 
              ?>
              <tr>
                  <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                  <td><span class="badge-code"><?= $masked_view ?></span></td>
                  <td><span style="color:#10b981; font-weight:bold;">Aktif</span></td>
                  <td>
                      <a href="telegram_setting.php?<?= $isEmbedded ? 'embed=1&' : '' ?>del=<?= $row['id'] ?>" class="btn-danger" onclick="return confirm('Yakin ingin menghapus <?php echo addslashes($row['name']); ?>?')">Del</a>
                  </td>
              </tr>
              <?php endwhile; ?>
              
              <?php if($res->num_rows == 0): ?>
              <tr>
                  <td colspan="4" style="text-align:center; color:#94a3b8; padding:20px;">
                      Belum ada penerima. Bot Telegram tidak akan mengirim apa-apa.
                  </td>
              </tr>
              <?php endif; ?>
          </tbody>
      </table>
  </div>
<?php if (!$isEmbedded) include 'footer.php'; ?>