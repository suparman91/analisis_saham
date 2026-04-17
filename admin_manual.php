<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
$mysqli = db_connect();
$msg = '';

// Pastikan tabel payment_orders ada
$mysqli->query("CREATE TABLE IF NOT EXISTS payment_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id VARCHAR(120) UNIQUE NOT NULL,
    package VARCHAR(80) NOT NULL,
    amount INT NOT NULL,
    duration INT NOT NULL,
    status ENUM('pending','paid','failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'request_manual') {
        $user_id = (int)$_POST['user_id'];
        $package = $mysqli->real_escape_string(trim($_POST['package']));
        $amount = (int)$_POST['amount'];
        $duration = (int)$_POST['duration'];
        $status = $_POST['status'] === 'paid' ? 'paid' : 'pending';
        $order_id = 'MANUAL-' . $user_id . '-' . time();

        $mysqli->query("INSERT INTO payment_orders (user_id, order_id, package, amount, duration, status) VALUES ($user_id, '$order_id', '$package', $amount, $duration, '$status')");

        if ($status === 'paid') {
            $current = $mysqli->query("SELECT subscription_end FROM users WHERE id = $user_id")->fetch_assoc()['subscription_end'];
            if ($current && strtotime($current) > time()) {
                $new_end = date('Y-m-d', strtotime($current . " +$duration months"));
            } else {
                $new_end = date('Y-m-d', strtotime("+$duration months"));
            }
            $mysqli->query("UPDATE users SET subscription_end = '$new_end' WHERE id = $user_id");
            $mysqli->query("UPDATE payment_orders SET paid_at = NOW() WHERE order_id = '$order_id'");
        }

        $msg = 'Pengajuan manual berhasil dibuat.';
    }

    if ($action === 'approve_manual') {
        $order_id = $mysqli->real_escape_string($_POST['order_id']);
        $order = $mysqli->query("SELECT * FROM payment_orders WHERE order_id = '$order_id' AND status = 'pending'")->fetch_assoc();
        if ($order) {
            $current = $mysqli->query("SELECT subscription_end FROM users WHERE id = {$order['user_id']}")->fetch_assoc()['subscription_end'];
            if ($current && strtotime($current) > time()) {
                $new_end = date('Y-m-d', strtotime($current . " +{$order['duration']} months"));
            } else {
                $new_end = date('Y-m-d', strtotime("+{$order['duration']} months"));
            }
            $mysqli->query("UPDATE users SET subscription_end = '$new_end' WHERE id = {$order['user_id']}");
            $mysqli->query("UPDATE payment_orders SET status = 'paid', paid_at = NOW() WHERE order_id = '$order_id'");
            $msg = 'Pengajuan manual disetujui dan langganan diaktifkan.';
        }
    }

    if ($action === 'fail_manual') {
        $order_id = $mysqli->real_escape_string($_POST['order_id']);
        $mysqli->query("UPDATE payment_orders SET status = 'failed' WHERE order_id = '$order_id'");
        $msg = 'Pengajuan manual ditandai gagal.';
    }
}

$users = [];
$res = $mysqli->query("SELECT id, name, email FROM users ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

$orders = [];
$res2 = $mysqli->query("SELECT p.*, u.name AS user_name, u.email AS user_email FROM payment_orders p JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC");
while ($row = $res2->fetch_assoc()) {
    $orders[] = $row;
}
?>
<?php
$pageTitle = 'Admin Manual Langganan | Analisis Saham';
?>
<?php include 'header.php'; ?>
<style>
    body { font-family: Arial, sans-serif; background: #f8fafc; margin:20px; }
    .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
    .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
    .tab { padding: 10px 16px; border-radius: 8px; text-decoration: none; color: #334155; background: #e2e8f0; font-weight: 600; }
    .tab.active { background: #3b82f6; color: #fff; }
    .msg { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
    .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 30px; }
    .panel h3 { margin-top: 0; }
    label { display: block; margin-bottom: 6px; color: #334155; font-weight: 600; }
    input, select { width: 100%; padding: 10px; margin-bottom: 16px; border: 1px solid #cbd5e1; border-radius: 8px; }
    button { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
    button.green { background: #16a34a; color:#fff; }
    button.red { background: #dc2626; color:#fff; }
    button.yellow { background: #f59e0b; color:#fff; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { border-bottom: 1px solid #e2e8f0; padding: 12px; text-align: left; }
    th { background: #f1f5f9; color: #334155; }
    .status-pending { color: #d97706; font-weight: bold; }
    .status-paid { color: #16a34a; font-weight: bold; }
    .status-failed { color: #dc2626; font-weight: bold; }
  </style>
<div class="container">
    <div class="tabs">
        <a class="tab" href="admin.php">Manage Users</a>
        <a class="tab active" href="admin_manual.php">Manual Langganan</a>
    </div>

    <h2>📝 Manual Langganan</h2>
    <p>Di halaman ini admin bisa mencatat pengajuan langganan manual, lalu menyetujui atau menolak setelah konfirmasi pembayaran WA.</p>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="panel">
        <h3>Tambah Pengajuan Manual</h3>
        <form method="POST">
            <input type="hidden" name="action" value="request_manual">
            <label>User</label>
            <select name="user_id" required>
                <option value="">Pilih user</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label>Paket</label>
            <input type="text" name="package" placeholder="Contoh: 3 Bulan (Pro)" required>
            <label>Harga (Rp)</label>
            <input type="number" name="amount" placeholder="79000" required>
            <label>Durasi (bulan)</label>
            <select name="duration" required>
                <option value="1">1 Bulan</option>
                <option value="3">3 Bulan</option>
                <option value="6">6 Bulan</option>
                <option value="12">12 Bulan</option>
            </select>
            <label>Status</label>
            <select name="status" required>
                <option value="pending">Pending (Menunggu konfirmasi)</option>
                <option value="paid">Paid (Aktifkan langsung)</option>
            </select>
            <button type="submit" class="green">Simpan Pengajuan</button>
        </form>
    </div>

    <div class="panel">
        <h3>Daftar Pengajuan Manual</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Paket</th>
                    <th>Harga</th>
                    <th>Durasi</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Paid At</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_id']) ?></td>
                    <td><?= htmlspecialchars($order['user_name']) ?> (<?= htmlspecialchars($order['user_email']) ?>)</td>
                    <td><?= htmlspecialchars($order['package']) ?></td>
                    <td>Rp <?= number_format($order['amount'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($order['duration']) ?> bulan</td>
                    <td class="status-<?= htmlspecialchars($order['status']) ?>"><?= strtoupper($order['status']) ?></td>
                    <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
                    <td><?= $order['paid_at'] ? date('d M Y H:i', strtotime($order['paid_at'])) : '-' ?></td>
                    <td>
                        <?php if ($order['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline-block; margin-right:4px;">
                                <input type="hidden" name="action" value="approve_manual">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                <button type="submit" class="green">Approve</button>
                            </form>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="action" value="fail_manual">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                <button type="submit" class="red">Tolak</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#64748b;">Tidak ada aksi</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
