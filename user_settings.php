<?php
require_once 'auth.php';
require_login();

require_once __DIR__ . '/db.php';
$mysqli = db_connect();

$user_id = get_user_id();
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';

// Fetch User Info
$stmt = $mysqli->prepare("SELECT id, username, email, subscription_end, created_at, robo_capital, robo_balance FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

// Calculate subscription status
$subscription_active = has_active_subscription($mysqli);
$subscription_end = $user['subscription_end'];
$days_left = 0;
$status_class = 'expired';
$status_text = 'Langganan Berakhir';

if ($subscription_active && $subscription_end) {
    $end_time = strtotime($subscription_end);
    $now_time = time();
    $days_left = ceil(($end_time - $now_time) / 86400);
    
    if ($days_left > 7) {
        $status_class = 'active';
        $status_text = 'Langganan Aktif';
    } elseif ($days_left > 0) {
        $status_class = 'warning';
        $status_text = 'Langganan Segera Berakhir';
    } else {
        $status_class = 'expired';
        $status_text = 'Langganan Berakhir';
    }
}

// Fetch watchlist count
$wl_res = $mysqli->query("SELECT COUNT(*) as total FROM watchlist WHERE user_id = $user_id");
$wl_count = $wl_res->fetch_assoc()['total'];

// Fetch telegram subscribers count
$tg_res = $mysqli->query("SELECT COUNT(*) as total FROM telegram_subscribers WHERE user_id = $user_id");
$tg_count = $tg_res->fetch_assoc()['total'];

// Handle password change
$pwd_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pwd = $_POST['current_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';
    
    // Verify current password
    $pwd_stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ?");
    $pwd_stmt->bind_param('i', $user_id);
    $pwd_stmt->execute();
    $pwd_row = $pwd_stmt->get_result()->fetch_assoc();
    $pwd_stmt->close();
    
    if (password_verify($current_pwd, $pwd_row['password'])) {
        if ($new_pwd === $confirm_pwd && strlen($new_pwd) >= 6) {
            $hashed = password_hash($new_pwd, PASSWORD_BCRYPT);
            $update_stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param('si', $hashed, $user_id);
            if ($update_stmt->execute()) {
                $pwd_msg = '<div class="alert success">✅ Password berhasil diubah</div>';
            } else {
                $pwd_msg = '<div class="alert error">❌ Gagal mengubah password</div>';
            }
            $update_stmt->close();
        } else {
            $pwd_msg = '<div class="alert error">❌ Password baru harus sama dan minimal 6 karakter</div>';
        }
    } else {
        $pwd_msg = '<div class="alert error">❌ Password saat ini salah</div>';
    }
}
?>
<?php if (!$isEmbedded) include 'header.php'; ?>
<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        margin: 0;
        padding: 0;
        background: #f8f9fa;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .user-container {
        width: 100%;
        margin: 0;
        box-sizing: border-box;
        padding: 15px;
        max-width: 1000px;
    }
    h1, h2 {
        color: #0f172a;
        margin: 20px 0 10px 0;
    }
    .card {
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .card-title {
        font-size: 18px;
        font-weight: bold;
        color: #1e293b;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 15px;
    }
    .info-box {
        background: #f8fafc;
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }
    .info-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .info-value {
        font-size: 16px;
        color: #1e293b;
        font-weight: bold;
    }
    .subscription-badge {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 15px;
    }
    .subscription-badge.active {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .subscription-badge.warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
    }
    .subscription-badge.expired {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .btn {
        display: inline-block;
        padding: 10px 16px;
        background: #3b82f6;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        text-decoration: none;
        transition: background 0.2s;
        margin-right: 8px;
        margin-bottom: 8px;
    }
    .btn:hover {
        background: #2563eb;
    }
    .btn-secondary {
        background: #64748b;
    }
    .btn-secondary:hover {
        background: #475569;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        font-weight: bold;
        color: #475569;
        margin-bottom: 5px;
        font-size: 13px;
    }
    .form-group input {
        width: 100%;
        padding: 10px;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
    }
    .alert {
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 15px;
        border-left: 4px solid;
    }
    .alert.success {
        background: #dcfce7;
        color: #065f46;
        border-left-color: #10b981;
    }
    .alert.error {
        background: #fee2e2;
        color: #7f1d1d;
        border-left-color: #ef4444;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    .stat-card {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        padding: 15px;
        border-radius: 6px;
        text-align: center;
    }
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .stat-label {
        font-size: 12px;
        opacity: 0.9;
    }
    @media (max-width: 768px) {
        .user-container {
            padding: 10px;
        }
        .card {
            padding: 15px;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="user-container">
    <?php if (!$isEmbedded) include 'nav.php'; ?>
    
    <h1>⚙️ Pengaturan User & Langganan</h1>
    
    <!-- Subscription Status -->
    <div class="card">
        <div class="card-title">📋 Status Langganan</div>
        <div class="subscription-badge <?= $status_class ?>">
            <?= $status_text ?>
            <?php if ($subscription_active && $days_left > 0): ?>
                - <?= $days_left ?> hari lagi
            <?php endif; ?>
        </div>
        
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Status Akses</div>
                <div class="info-value" style="color: <?= $subscription_active ? '#10b981' : '#dc2626' ?>">
                    <?= $subscription_active ? '✅ Aktif' : '❌ Tidak Aktif' ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Tanggal Berakhir</div>
                <div class="info-value">
                    <?= $subscription_end ? date('d M Y', strtotime($subscription_end)) : 'Belum Berlangganan' ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Anggota Sejak</div>
                <div class="info-value">
                    <?= date('d M Y', strtotime($user['created_at'])) ?>
                </div>
            </div>
        </div>
        
        <?php if (!$subscription_active): ?>
            <a href="subscribe.php<?= $isEmbedded ? '?embed=1' : '' ?>" class="btn">🔄 Perpanjang Langganan</a>
        <?php endif; ?>
    </div>
    
    <!-- Profil User -->
    <div class="card">
        <div class="card-title">👤 Profil User</div>
        
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Username</div>
                <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Email</div>
                <div class="info-value" style="font-size: 14px; word-break: break-all;">
                    <?= htmlspecialchars($user['email']) ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">User ID</div>
                <div class="info-value" style="color: #64748b; font-size: 14px;">
                    #<?= $user['id'] ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Robo Trader Stats -->
    <div class="card">
        <div class="card-title">🤖 Statistik AI Robo-Trader</div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">Rp <?= number_format($user['robo_capital'], 0, ',', '.') ?></div>
                <div class="stat-label">Modal Awal</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="stat-value">Rp <?= number_format($user['robo_balance'], 0, ',', '.') ?></div>
                <div class="stat-label">Saldo Tersedia</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="stat-value">
                    <?php 
                        $pl = $user['robo_balance'] - $user['robo_capital'];
                        $pl_pct = $user['robo_capital'] > 0 ? ($pl / $user['robo_capital']) * 100 : 0;
                        echo number_format($pl_pct, 2);
                    ?>%
                </div>
                <div class="stat-label">Return</div>
            </div>
        </div>
        
        <a href="portfolio.php<?= $isEmbedded ? '?embed=1' : '' ?>" class="btn">📊 Kelola Portfolio Robot</a>
    </div>
    
    <!-- Watchlist & Telegram -->
    <div class="card">
        <div class="card-title">⭐ Preferensi & Notifikasi</div>
        
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Saham Favorit</div>
                <div class="info-value"><?= $wl_count ?> Saham</div>
                <a href="index.php<?= $isEmbedded ? '?embed=1' : '' ?>" style="display: inline-block; margin-top: 8px; font-size: 12px; color: #3b82f6; text-decoration: none;">
                    Kelola →
                </a>
            </div>
            <div class="info-box">
                <div class="info-label">Penerima Telegram</div>
                <div class="info-value"><?= $tg_count ?> Akun</div>
                <a href="telegram_setting.php<?= $isEmbedded ? '?embed=1' : '' ?>" style="display: inline-block; margin-top: 8px; font-size: 12px; color: #3b82f6; text-decoration: none;">
                    Kelola →
                </a>
            </div>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="card">
        <div class="card-title">🔐 Ubah Password</div>
        
        <?= $pwd_msg ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label>Password Saat Ini</label>
                <input type="password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="new_password" required>
            </div>
            
            <div class="form-group">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn">Ubah Password</button>
        </form>
    </div>
    
    <!-- Account Info -->
    <div class="card" style="background: #f1f5f9; border-color: #cbd5e1;">
        <div class="card-title">ℹ️ Informasi Akun</div>
        
        <div style="font-size: 13px; color: #64748b; line-height: 1.6;">
            <p><strong>Terdaftar:</strong> <?= date('d F Y - H:i', strtotime($user['created_at'])) ?></p>
            <p><strong>User ID:</strong> #<?= $user['id'] ?></p>
            <p style="margin-bottom: 0;">
                <strong>Hubungi Support:</strong> Jika ada pertanyaan, silakan hubungi tim support kami melalui Telegram atau email.
            </p>
        </div>
    </div>
</div>

<?php if (!$isEmbedded) include 'footer.php'; ?>
