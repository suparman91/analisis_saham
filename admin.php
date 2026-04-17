<?php
require_once 'auth.php';
require_admin(); // Pastikan HANYA Admin yang bisa buka

require_once 'db.php';
$mysqli = db_connect();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $uid = (int)$_POST['user_id'];
    
    if ($_POST['action'] === 'perpanjang') {
        $bulan = (int)$_POST['bulan'];
        if ($bulan > 0) {
            // Cek sisa langganan
            $res = $mysqli->query("SELECT subscription_end FROM users WHERE id = $uid");
            $curr = $res->fetch_assoc()['subscription_end'];
            
            $now = time();
            if ($curr && strtotime($curr) > $now) {
                // Tambah dari sisa
                $new_date = date('Y-m-d', strtotime($curr . " +$bulan months"));
            } else {
                // Tambah dari hari ini
                $new_date = date('Y-m-d', strtotime("+$bulan months"));
            }
            
            $mysqli->query("UPDATE users SET subscription_end = '$new_date' WHERE id = $uid");
            $msg = "Berhasil menambah $bulan bulan untuk user ID $uid.";
        }
    } elseif ($_POST['action'] === 'cabut') {
        $mysqli->query("UPDATE users SET subscription_end = NULL WHERE id = $uid AND role != 'admin'");
        $msg = "Akses (Subscription) user ID $uid berhasil dicabut.";
    } elseif ($_POST['action'] === 'hapus') {
        // Hapus child data
        $mysqli->query("DELETE FROM portfolio WHERE user_id = $uid");
        $mysqli->query("DELETE FROM robo_trades WHERE user_id = $uid");
        // Hapus user
        $mysqli->query("DELETE FROM users WHERE id = $uid AND role != 'admin'");
        $msg = "Akun user ID $uid beserta datanya berhasil dihapus.";
    }
}

$users = [];
$res = $mysqli->query("SELECT * FROM users ORDER BY created_at DESC");
while ($r = $res->fetch_assoc()) {
    $users[] = $r;
}
?>
<?php
$pageTitle = 'Admin Dashboard | User Manager';
?>
<?php include 'header.php'; ?>
<style>
    body { font-family: Arial, sans-serif; background: #f8fafc; margin:20px; }
    .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border-bottom: 1px solid #e2e8f0; padding: 12px; text-align: left; font-size:14px; }
    th { background: #f1f5f9; color: #64748b; font-size: 13px; text-transform: uppercase; }
    .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: #fff; }
    .btn-green { background: #16a34a; }
    .btn-red { background: #dc2626; }
    .btn-yellow { background: #f59e0b; color:#fff; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: #fff; }
    .b-aktif { background: #16a34a; }
    .b-mati { background: #dc2626; }
    .b-admin { background: #3b82f6; }
  </style>
<div class="container">
    <h2>👑 Administrator - Manage Pengguna & Langganan</h2>
    <a href="index.php" style="color:#3b82f6; text-decoration:none;">&laquo; Kembali ke Home</a>
    <div style="margin: 20px 0; display:flex; gap:10px; flex-wrap:wrap;">
        <a href="admin.php" style="display:inline-block; background:#3b82f6; color:#fff; padding:10px 14px; border-radius:8px; text-decoration:none;">Manage Users</a>
        <a href="admin_manual.php" style="display:inline-block; background:#f59e0b; color:#fff; padding:10px 14px; border-radius:8px; text-decoration:none;">Manual Langganan</a>
    </div>
    <div style="background:#f8fafc; border:1px solid #cbd5e1; color:#334155; padding:15px; border-radius:8px; margin:20px 0;">
        <strong>Proses Manual via WA:</strong> Gunakan tombol <span style="font-weight:bold; color:#16a34a;">ACC / Aktifkan</span> untuk memberi akses langganan setelah user konfirmasi pembayaran melalui WhatsApp. Jika ingin cabut akses, gunakan tombol <span style="font-weight:bold; color:#b45309;">Cabut Akses</span>.
    </div>

    <?php if($msg) echo "<div style='background:#dcfce7; color:#166534; padding:10px; margin:20px 0; border-radius:5px;'>$msg</div>"; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama / Email</th>
                <th>Tgl Daftar</th>
                <th>Status (Berakhir Pada)</th>
                <th>Akses Alat</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): 
                $is_admin = $u['role'] === 'admin';
                $is_active = $u['subscription_end'] && strtotime($u['subscription_end']) > time();
            ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td>
                    <b><?= htmlspecialchars($u['name']) ?></b><br>
                    <span style="color:#64748b; font-size:12px;"><?= htmlspecialchars($u['email']) ?></span>
                </td>
                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($is_admin): ?>
                        <span class="badge b-admin">LIFETIME (ADMIN)</span>
                    <?php elseif($is_active): ?>
                        <span class="badge b-aktif">AKTIF</span><br>
                        <small style="color:#16a34a;"><?= date('d M Y', strtotime($u['subscription_end'])) ?></small>
                    <?php else: ?>
                        <span class="badge b-mati">EXPIRED/FREE</span><br>
                        <small style="color:#dc2626;"><?= $u['subscription_end'] ? date('d M Y', strtotime($u['subscription_end'])) : 'Belum Berlangganan' ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$is_admin): ?>
                    <form method="POST" style="display:inline-block; margin-bottom:5px;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="perpanjang">
                        <select name="bulan" style="padding:5px;">
                            <option value="1">+ 1 Bulan</option>
                            <option value="3">+ 3 Bulan</option>
                            <option value="6">+ 6 Bulan</option>
                            <option value="12">+ 1 Tahun</option>
                        </select>
                        <button type="submit" class="btn btn-green">ACC / Aktifkan</button>
                    </form><br>
                    
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="cabut">
                        <button type="submit" class="btn btn-yellow" onclick="return confirm('Cabut masa aktif user ini?')">Cabut Akses</button>
                    </form>
                    
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="hapus">
                        <button type="submit" class="btn btn-red" onclick="return confirm('Hapus user beserta portofolionya? Permanen lho.')">Del Akun</button>
                    </form>
                    <?php else: ?>
                        <i>(Full Control)</i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>