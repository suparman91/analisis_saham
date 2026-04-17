<?php
require_once 'auth.php';
require_login();

require_once 'db.php';
$mysqli = db_connect();

// Load Midtrans SDK
require_once 'vendor/autoload.php';
$midtransConfig = require __DIR__ . '/midtrans_config.php';

if (empty($midtransConfig['server_key']) || empty($midtransConfig['client_key'])) {
    die('Midtrans belum dikonfigurasi. Silakan buka midtrans_config.php dan isi server_key serta client_key.');
}

\Midtrans\Config::$serverKey = $midtransConfig['server_key'];
\Midtrans\Config::$isProduction = (bool)$midtransConfig['is_production'];
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

$user_id = get_user_id();
$res = $mysqli->query("SELECT * FROM users WHERE id = $user_id LIMIT 1");
$user = $res->fetch_assoc();

$status = 'EXPIRED / BELUM BERLANGGANAN';
$status_color = '#dc2626'; // Merah
$bg_color = '#fee2e2';
$sisa_hari = 0;

if ($user['subscription_end'] && strtotime($user['subscription_end']) >= time()) {
    $status = 'AKTIF 🚀';
    $status_color = '#16a34a'; // Hijau
    $bg_color = '#dcfce7';
    $sisa_hari = round((strtotime($user['subscription_end']) - time()) / 86400);
}

if ($user['role'] === 'admin') {
    $status = 'LIFETIME (ADMIN) 👑';
    $sisa_hari = 'Unlimited';
}

// Nomor WA Anda sebagai admin (format 62...)
$admin_wa = "6281234567890";
// URL Domain web Anda
$domain = "https://app-analisis-saham.com";

function get_wa_link($package, $price, $phone) {
    $text = "Halo Admin, saya ingin berlangganan paket *{$package}* seharga *Rp {$price}* untuk Akun Email saya.\nMohon info nomor rekening / QRIS nya ya.";
    return "https://wa.me/{$phone}?text=" . urlencode($text);
}

// Handle create payment Midtrans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    $package = $_POST['package'];
    $price = (int)$_POST['price'];
    $duration = (int)$_POST['duration']; // dalam bulan

    $transaction_details = array(
        'order_id' => 'SUB-' . $user_id . '-' . time(),
        'gross_amount' => $price,
    );

    $item_details = array(
        array(
            'id' => 'SUB-' . $package,
            'price' => $price,
            'quantity' => 1,
            'name' => 'Langganan ' . $package,
        ),
    );

    $customer_details = array(
        'first_name' => $user['name'],
        'email' => $user['email'],
    );

    $transaction = array(
        'transaction_details' => $transaction_details,
        'item_details' => $item_details,
        'customer_details' => $customer_details,
    );

    try {
        $snapToken = \Midtrans\Snap::getSnapToken($transaction);
        // Simpan order_id ke DB untuk verifikasi nanti
        $mysqli->query("INSERT INTO payment_orders (user_id, order_id, package, amount, duration, status) VALUES ($user_id, '{$transaction_details['order_id']}', '$package', $price, $duration, 'pending')");
        echo json_encode(['snap_token' => $snapToken]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
<?php
$pageTitle = 'Paket Langganan VIP | Analisis Saham';
?>
<?php include 'header.php'; ?>
  <style>
    body { font-family: Arial, sans-serif; background: #f8fafc; margin:20px; }
    .container { max-width: 1000px; margin: 0 auto; }
    .status-box { background: <?php echo $bg_color; ?>; padding: 20px; border-radius: 8px; border: 1px solid <?php echo $status_color; ?>; margin-bottom: 30px; text-align: center; }
    
    .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
    .pkg-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); position:relative; }
    .pkg-card.popular { border: 2px solid #3b82f6; transform: scale(1.05); z-index:10; }
    .badge-pop { position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:#3b82f6; color:#fff; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold; }
    
    .pkg-title { font-size: 18px; color: #64748b; margin-bottom: 10px; font-weight:bold; }
    .pkg-price { font-size: 32px; color: #0f172a; font-weight: bold; margin-bottom: 5px; }
    .pkg-sub { font-size: 13px; color: #94a3b8; text-decoration: line-through; margin-bottom: 20px; }
    .pkg-desc { font-size: 14px; color: #475569; margin-bottom: 25px; text-align:left; line-height:1.6;}
    .btn-buy { display: block; padding: 12px; background: #16a34a; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
    .btn-buy:hover { background: #15803d; }
  </style>

<div class="container">
    <div class="status-box">
        <h3 style="margin-top:0; color: <?php echo $status_color; ?>;">Status Paket Anda: <?php echo $status; ?></h3>
        <p style="margin:5px 0 0 0; color: #475569;">Berlaku s/d: <b><?php echo $user['subscription_end'] ? date('d F Y', strtotime($user['subscription_end'])) : '-'; ?></b> (Sisa: <?php echo $sisa_hari; ?> Hari)</p>
    </div>

    <div style="text-align:center; margin-bottom:40px;">
        <h2 style="margin-bottom:10px; color:#0f172a;">Buka Kunci Seluruh Fitur Robot Saham 🚀</h2>
        <p style="color:#64748b;">Akses penuh ke Screener AI, Momentum ARA/ARB Hunter, dan Robo-Trader Simulator.</p>
    </div>

    <div class="pricing-grid">
        <!-- Paket 1 -->
        <div class="pkg-card">
            <div class="pkg-title">1 Bulan (Basic)</div>
            <div class="pkg-price">Rp 79.000</div>
            <div class="pkg-sub">Normal: Rp 100.000</div>
            <div class="pkg-desc">
                ✅ Akses Screener Momentum AI<br>
                ✅ Telegram Alert Otomatis<br>
                ✅ Auto-Portofolio Tracker<br>
                ❌ Prioritas Support
            </div>
            <div style="margin-bottom:10px;">
                <button class="btn-buy" onclick="payWithMidtrans('1 Bulan (Basic)', 79000, 1)">Bayar Otomatis (QRIS)</button>
            </div>
            <a href="<?= get_wa_link('1 Bulan (Basic)', '79.000', $admin_wa) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Bayar Manual (WA)</a>
        </div>

        <!-- Paket 2 -->
        <div class="pkg-card popular">
            <div class="badge-pop">TERLARIS 🔥</div>
            <div class="pkg-title">3 Bulan (Pro)</div>
            <div class="pkg-price">Rp 195.000</div>
            <div class="pkg-sub">Normal: Rp 237.000</div>
            <div class="pkg-desc">
                ✅ <b>Semua Fitur Basic</b><br>
                ✅ ARA & ARB Hunter Scanner<br>
                ✅ Akses Papan Akselerasi<br>
                ✅ Diskon 17% (Lebih Hemat)
            </div>
            <div style="margin-bottom:10px;">
                <button class="btn-buy" onclick="payWithMidtrans('3 Bulan (Pro)', 195000, 3)">Bayar Otomatis (QRIS)</button>
            </div>
            <a href="<?= get_wa_link('3 Bulan (Pro)', '195.000', $admin_wa) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Bayar Manual (WA)</a>
        </div>

        <!-- Paket 3 -->
        <div class="pkg-card">
            <div class="pkg-title">6 Bulan (Advance)</div>
            <div class="pkg-price">Rp 350.000</div>
            <div class="pkg-sub">Normal: Rp 474.000</div>
            <div class="pkg-desc">
                ✅ <b>Semua Fitur Pro</b><br>
                ✅ Robo-Trader Simulator Max<br>
                ✅ Grup Komunitas Rahasia<br>
                ✅ Diskon 26%
            </div>
            <div style="margin-bottom:10px;">
                <button class="btn-buy" onclick="payWithMidtrans('6 Bulan (Advance)', 350000, 6)">Bayar Otomatis (QRIS)</button>
            </div>
            <a href="<?= get_wa_link('6 Bulan (Advance)', '350.000', $admin_wa) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Bayar Manual (WA)</a>
        </div>

        <!-- Paket 4 -->
        <div class="pkg-card">
            <div class="pkg-title">1 Tahun (Ultimate)</div>
            <div class="pkg-price">Rp 650.000</div>
            <div class="pkg-sub">Normal: Rp 948.000</div>
            <div class="pkg-desc">
                ✅ <b>FITUR UNLIMITED</b><br>
                ✅ Private Mentoring 1-on-1<br>
                ✅ VIP Support Langsung<br>
                ✅ Diskon 30% (Best Value!)
            </div>
            <div style="margin-bottom:10px;">
                <button class="btn-buy" onclick="payWithMidtrans('1 Tahun (Ultimate)', 650000, 12)">Bayar Otomatis (QRIS)</button>
            </div>
            <a href="<?= get_wa_link('1 Tahun (Ultimate)', '650.000', $admin_wa) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Bayar Manual (WA)</a>
        </div>
    </div>
    
    <div style="margin-top:40px; text-align:center; color:#64748b; font-size:13px;">
        Pembayaran dilakukan secara manual via transfer Bank/QRIS. 
        Admin akan memproses aktivasi limit Anda maksimal 1x15 menit setelah konfirmasi.
    </div>
</div>

<!-- Midtrans Snap JS -->
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?= htmlspecialchars($midtransConfig['client_key']) ?>"></script>
<script>
function payWithMidtrans(package, price, duration) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'create_payment=1&package=' + encodeURIComponent(package) + '&price=' + price + '&duration=' + duration
    })
    .then(response => response.json())
    .then(data => {
        if (data.snap_token) {
            snap.pay(data.snap_token, {
                onSuccess: function(result) {
                    alert('Pembayaran berhasil! Langganan akan diaktifkan otomatis.');
                    location.reload();
                },
                onPending: function(result) {
                    alert('Pembayaran pending. Tunggu konfirmasi.');
                },
                onError: function(result) {
                    alert('Pembayaran gagal: ' + result.status_message);
                },
                onClose: function() {
                    alert('Pembayaran dibatalkan.');
                }
            });
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error);
    });
}
</script>
<?php include 'footer.php'; ?>