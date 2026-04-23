<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
$mysqli = db_connect();
$msg = '';

$launchPromoActive = true;
$packageCatalog = [
    'basic-1m' => ['label' => '1 Bulan (Basic)', 'normal_price' => 100000, 'duration' => 1],
    'pro-3m' => ['label' => '3 Bulan (Pro)', 'normal_price' => 237000, 'duration' => 3],
    'advance-6m' => ['label' => '6 Bulan (Advance)', 'normal_price' => 474000, 'duration' => 6],
    'ultimate-12m' => ['label' => '1 Tahun (Ultimate)', 'normal_price' => 948000, 'duration' => 12],
];

foreach ($packageCatalog as $packageKey => $packageData) {
    $normalPrice = (int)$packageData['normal_price'];
    $packageCatalog[$packageKey]['price'] = $launchPromoActive ? (int)floor($normalPrice * 0.5) : $normalPrice;
}

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
    require_valid_csrf();
    $action = $_POST['action'];

    if ($action === 'request_manual') {
        $user_id = (int)$_POST['user_id'];
        $packageKey = (string)($_POST['package_key'] ?? '');
        $status = $_POST['status'] === 'paid' ? 'paid' : 'pending';
        $order_id = 'MANUAL-' . $user_id . '-' . time();

        if ($user_id <= 0 || !isset($packageCatalog[$packageKey])) {
            $msg = 'User atau paket tidak valid.';
        } else {
            $package = $packageCatalog[$packageKey]['label'];
            $amount = (int)$packageCatalog[$packageKey]['price'];
            $duration = (int)$packageCatalog[$packageKey]['duration'];

            $stmtInsert = $mysqli->prepare("INSERT INTO payment_orders (user_id, order_id, package, amount, duration, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsert->bind_param('issiis', $user_id, $order_id, $package, $amount, $duration, $status);
            $stmtInsert->execute();
            $stmtInsert->close();

            if ($status === 'paid') {
                $stmtCurrent = $mysqli->prepare("SELECT subscription_end FROM users WHERE id = ? LIMIT 1");
                $stmtCurrent->bind_param('i', $user_id);
                $stmtCurrent->execute();
                $current = $stmtCurrent->get_result()->fetch_assoc()['subscription_end'] ?? null;
                $stmtCurrent->close();

                if ($current && strtotime($current) > time()) {
                    $new_end = date('Y-m-d', strtotime($current . " +$duration months"));
                } else {
                    $new_end = date('Y-m-d', strtotime("+$duration months"));
                }

                $stmtUpdateUser = $mysqli->prepare("UPDATE users SET subscription_end = ? WHERE id = ?");
                $stmtUpdateUser->bind_param('si', $new_end, $user_id);
                $stmtUpdateUser->execute();
                $stmtUpdateUser->close();

                $stmtPaidAt = $mysqli->prepare("UPDATE payment_orders SET paid_at = NOW() WHERE order_id = ?");
                $stmtPaidAt->bind_param('s', $order_id);
                $stmtPaidAt->execute();
                $stmtPaidAt->close();
            }

            $msg = 'Pengajuan manual berhasil dibuat.';
        }
    }

    if ($action === 'approve_manual') {
        $order_id = (string)($_POST['order_id'] ?? '');
        $stmtOrder = $mysqli->prepare("SELECT user_id, duration, status FROM payment_orders WHERE order_id = ? AND status = 'pending' LIMIT 1");
        $stmtOrder->bind_param('s', $order_id);
        $stmtOrder->execute();
        $order = $stmtOrder->get_result()->fetch_assoc();
        $stmtOrder->close();

        if ($order) {
            $orderUserId = (int)$order['user_id'];
            $orderDuration = (int)$order['duration'];
            $stmtCurrent = $mysqli->prepare("SELECT subscription_end FROM users WHERE id = ? LIMIT 1");
            $stmtCurrent->bind_param('i', $orderUserId);
            $stmtCurrent->execute();
            $current = $stmtCurrent->get_result()->fetch_assoc()['subscription_end'] ?? null;
            $stmtCurrent->close();

            if ($current && strtotime($current) > time()) {
                $new_end = date('Y-m-d', strtotime($current . " +$orderDuration months"));
            } else {
                $new_end = date('Y-m-d', strtotime("+$orderDuration months"));
            }

            $stmtUpdateUser = $mysqli->prepare("UPDATE users SET subscription_end = ? WHERE id = ?");
            $stmtUpdateUser->bind_param('si', $new_end, $orderUserId);
            $stmtUpdateUser->execute();
            $stmtUpdateUser->close();

            $stmtPaid = $mysqli->prepare("UPDATE payment_orders SET status = 'paid', paid_at = NOW() WHERE order_id = ?");
            $stmtPaid->bind_param('s', $order_id);
            $stmtPaid->execute();
            $stmtPaid->close();
            $msg = 'Pengajuan manual disetujui dan langganan diaktifkan.';
        }
    }

    if ($action === 'fail_manual') {
        $order_id = (string)($_POST['order_id'] ?? '');
        $stmtFailed = $mysqli->prepare("UPDATE payment_orders SET status = 'failed' WHERE order_id = ?");
        $stmtFailed->bind_param('s', $order_id);
        $stmtFailed->execute();
        $stmtFailed->close();
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
    <div style="margin:16px 0 24px; background:#fff7ed; color:#9a3412; border:1px solid #fdba74; padding:12px 16px; border-radius:8px; font-weight:600;">
        Promo launching aktif: semua paket memakai diskon 50% selama masa trial publik. Trial gratis user tetap 7 hari.
    </div>

    <?php if ($msg): ?><div class="msg"><?= security_escape($msg) ?></div><?php endif; ?>

    <div class="panel">
        <h3>Tambah Pengajuan Manual</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="request_manual">
            <label>User</label>
            <select name="user_id" required>
                <option value="">Pilih user</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= security_escape($u['name']) ?> (<?= security_escape($u['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label>Paket</label>
            <select name="package_key" required>
                <option value="">Pilih paket</option>
                <?php foreach ($packageCatalog as $packageKey => $package): ?>
                    <option value="<?= security_escape($packageKey) ?>">
                        <?= security_escape($package['label']) ?> - Rp <?= number_format($package['price'], 0, ',', '.') ?>
                        (Normal Rp <?= number_format($package['normal_price'], 0, ',', '.') ?>)
                    </option>
                <?php endforeach; ?>
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
                    <td><?= security_escape($order['user_name']) ?> (<?= security_escape($order['user_email']) ?>)</td>
                    <td><?= security_escape($order['package']) ?></td>
                    <td>Rp <?= number_format($order['amount'], 0, ',', '.') ?></td>
                    <td><?= (int)$order['duration'] ?> bulan</td>
                    <td class="status-<?= security_escape($order['status']) ?>"><?= strtoupper(security_escape($order['status'])) ?></td>
                    <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
                    <td><?= $order['paid_at'] ? date('d M Y H:i', strtotime($order['paid_at'])) : '-' ?></td>
                    <td>
                        <?php if ($order['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline-block; margin-right:4px;">
                                <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
                                <input type="hidden" name="action" value="approve_manual">
                                <input type="hidden" name="order_id" value="<?= security_escape($order['order_id']) ?>">
                                <button type="submit" class="green">Approve</button>
                            </form>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
                                <input type="hidden" name="action" value="fail_manual">
                                <input type="hidden" name="order_id" value="<?= security_escape($order['order_id']) ?>">
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
