<?php
require_once 'auth.php';
require_login();

require_once 'db.php';
$mysqli = db_connect();

$user_id = get_user_id();
$launchPromoActive = true;
$packageCatalog = [
    'basic-1m' => ['label' => '1 Bulan (Basic)', 'normal_price' => 100000, 'duration' => 1],
    'pro-3m' => ['label' => '3 Bulan (Pro)', 'normal_price' => 237000, 'duration' => 3],
    'advance-6m' => ['label' => '6 Bulan (Advance)', 'normal_price' => 474000, 'duration' => 6],
    'ultimate-12m' => ['label' => '1 Tahun (Ultimate)', 'normal_price' => 948000, 'duration' => 12],
];

foreach ($packageCatalog as $packageKey => $packageData) {
    $normalPrice = (int)$packageData['normal_price'];
    $promoPrice = $launchPromoActive ? (int)floor($normalPrice * 0.5) : $normalPrice;
    $packageCatalog[$packageKey]['price'] = $promoPrice;
    $packageCatalog[$packageKey]['discount_label'] = $launchPromoActive ? 'Promo Launching 50% selama masa trial' : null;
}

$stmtUser = $mysqli->prepare("SELECT id, name, email, role, subscription_end FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if (!$user) {
    http_response_code(404);
    exit('User tidak ditemukan.');
}

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

function format_rupiah($amount) {
    return number_format((int)$amount, 0, ',', '.');
}

function get_wa_link($package, $price, $phone, $email, $duration, $isPromoActive) {
    $promoText = $isPromoActive ? "\nSaya ingin klaim *promo launching 50% selama masa trial*." : '';
    $text = "Halo Admin, saya ingin konfirmasi langganan paket *{$package}* seharga *Rp {$price}* untuk akun *{$email}* selama *{$duration} bulan*.{$promoText}\nMohon kirim nomor WA pembayaran yang aktif ya.";
    return "https://wa.me/{$phone}?text=" . urlencode($text);
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
        .pkg-promo { display:inline-block; margin-bottom: 16px; background:#dcfce7; color:#166534; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
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
        <div style="margin-top:14px; display:inline-block; background:#fff7ed; color:#9a3412; border:1px solid #fdba74; padding:12px 16px; border-radius:10px; font-size:14px; font-weight:600;">
            Trial gratis tetap 7 hari. Selama masa launching trial, semua paket diskon 50% dan pembayaran dibuka via konfirmasi WhatsApp saja.
        </div>
        <div style="margin-top:10px; display:inline-block; background:#eff6ff; color:#1d4ed8; border:1px solid #93c5fd; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:600;">
            Biaya paket saat ini difokuskan sebagai kontribusi dukungan operasional dan pengembangan sistem agar platform semakin stabil dan bermanfaat.
        </div>
    </div>

    <div class="pricing-grid">
        <!-- Paket 1 -->
        <div class="pkg-card">
            <div class="pkg-title">1 Bulan (Basic)</div>
            <div class="pkg-price">Rp <?= format_rupiah($packageCatalog['basic-1m']['price']) ?></div>
            <div class="pkg-sub">Normal: Rp <?= format_rupiah($packageCatalog['basic-1m']['normal_price']) ?></div>
            <div class="pkg-promo"><?= security_escape($packageCatalog['basic-1m']['discount_label']) ?></div>
            <div class="pkg-desc">
                ✅ Semua fitur analisis aktif<br>
                ✅ Scanner AI + ARA/ARB Hunter<br>
                ✅ Telegram Alert + Robo Tools<br>
                ✅ Dukung pengembangan fitur dan kestabilan platform
            </div>
            <a href="<?= get_wa_link($packageCatalog['basic-1m']['label'], format_rupiah($packageCatalog['basic-1m']['price']), $admin_wa, $user['email'], $packageCatalog['basic-1m']['duration'], $launchPromoActive) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Konfirmasi via WA</a>
        </div>

        <!-- Paket 2 -->
        <div class="pkg-card popular">
            <div class="badge-pop">TERLARIS 🔥</div>
            <div class="pkg-title">3 Bulan (Pro)</div>
            <div class="pkg-price">Rp <?= format_rupiah($packageCatalog['pro-3m']['price']) ?></div>
            <div class="pkg-sub">Normal: Rp <?= format_rupiah($packageCatalog['pro-3m']['normal_price']) ?></div>
            <div class="pkg-promo"><?= security_escape($packageCatalog['pro-3m']['discount_label']) ?></div>
            <div class="pkg-desc">
                ✅ Semua fitur analisis aktif<br>
                ✅ Scanner AI + ARA/ARB Hunter<br>
                ✅ Telegram Alert + Robo Tools<br>
                ✅ Dukung pengembangan fitur dan kestabilan platform
            </div>
            <a href="<?= get_wa_link($packageCatalog['pro-3m']['label'], format_rupiah($packageCatalog['pro-3m']['price']), $admin_wa, $user['email'], $packageCatalog['pro-3m']['duration'], $launchPromoActive) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Konfirmasi via WA</a>
        </div>

        <!-- Paket 3 -->
        <div class="pkg-card">
            <div class="pkg-title">6 Bulan (Advance)</div>
            <div class="pkg-price">Rp <?= format_rupiah($packageCatalog['advance-6m']['price']) ?></div>
            <div class="pkg-sub">Normal: Rp <?= format_rupiah($packageCatalog['advance-6m']['normal_price']) ?></div>
            <div class="pkg-promo"><?= security_escape($packageCatalog['advance-6m']['discount_label']) ?></div>
            <div class="pkg-desc">
                ✅ Semua fitur analisis aktif<br>
                ✅ Scanner AI + ARA/ARB Hunter<br>
                ✅ Telegram Alert + Robo Tools<br>
                ✅ Dukung pengembangan fitur dan kestabilan platform
            </div>
            <a href="<?= get_wa_link($packageCatalog['advance-6m']['label'], format_rupiah($packageCatalog['advance-6m']['price']), $admin_wa, $user['email'], $packageCatalog['advance-6m']['duration'], $launchPromoActive) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Konfirmasi via WA</a>
        </div>

        <!-- Paket 4 -->
        <div class="pkg-card">
            <div class="pkg-title">1 Tahun (Ultimate)</div>
            <div class="pkg-price">Rp <?= format_rupiah($packageCatalog['ultimate-12m']['price']) ?></div>
            <div class="pkg-sub">Normal: Rp <?= format_rupiah($packageCatalog['ultimate-12m']['normal_price']) ?></div>
            <div class="pkg-promo"><?= security_escape($packageCatalog['ultimate-12m']['discount_label']) ?></div>
            <div class="pkg-desc">
                ✅ Semua fitur analisis aktif<br>
                ✅ Scanner AI + ARA/ARB Hunter<br>
                ✅ Telegram Alert + Robo Tools<br>
                ✅ Dukung pengembangan fitur dan kestabilan platform
            </div>
            <a href="<?= get_wa_link($packageCatalog['ultimate-12m']['label'], format_rupiah($packageCatalog['ultimate-12m']['price']), $admin_wa, $user['email'], $packageCatalog['ultimate-12m']['duration'], $launchPromoActive) ?>" class="btn-buy" target="_blank" style="background:#3b82f6;">Konfirmasi via WA</a>
        </div>
    </div>
    
    <div style="margin-top:40px; text-align:center; color:#64748b; font-size:13px;">
        Pembayaran otomatis Midtrans, QRIS, dan transfer publik sedang ditutup sementara.
        Aktivasi saat ini diproses melalui konfirmasi WhatsApp oleh admin.
    </div>

    <div style="margin-top:12px; text-align:center; color:#64748b; font-size:12px; line-height:1.6;">
        Platform ini dijalankan dengan komitmen kepatuhan terhadap ketentuan hukum yang berlaku serta penghormatan atas hak kekayaan intelektual dan hak cipta pihak terkait.
    </div>
</div>
<?php include 'footer.php'; ?>