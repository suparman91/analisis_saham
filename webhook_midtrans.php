<?php
// Webhook untuk Midtrans - handle notifikasi pembayaran
require_once 'db.php';
$mysqli = db_connect();

// Load Midtrans SDK
require_once 'vendor/autoload.php';
$midtransConfig = require __DIR__ . '/midtrans_config.php';

if (empty($midtransConfig['server_key'])) {
    http_response_code(500);
    exit('Midtrans server key belum dikonfigurasi.');
}

\Midtrans\Config::$serverKey = $midtransConfig['server_key'];
\Midtrans\Config::$isProduction = (bool)$midtransConfig['is_production'];

// Ambil data JSON dari Midtrans
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Verifikasi signature
$signature = hash('sha512', $data['order_id'] . $data['status_code'] . $data['gross_amount'] . \Midtrans\Config::$serverKey);
if ($signature !== $data['signature_key']) {
    http_response_code(403);
    exit('Invalid signature');
}

// Cek status transaksi
$order_id = $data['order_id'];
$transaction_status = $data['transaction_status'];

if ($transaction_status == 'settlement' || $transaction_status == 'capture') {
    // Pembayaran sukses
    $res = $mysqli->query("SELECT * FROM payment_orders WHERE order_id = '$order_id' AND status = 'pending'");
    if ($order = $res->fetch_assoc()) {
        $user_id = $order['user_id'];
        $duration = $order['duration'];

        // Hitung subscription_end baru
        $current_end = $mysqli->query("SELECT subscription_end FROM users WHERE id = $user_id")->fetch_assoc()['subscription_end'];
        if ($current_end && strtotime($current_end) > time()) {
            $new_end = date('Y-m-d', strtotime($current_end . " +$duration months"));
        } else {
            $new_end = date('Y-m-d', strtotime("+$duration months"));
        }

        // Update subscription
        $mysqli->query("UPDATE users SET subscription_end = '$new_end' WHERE id = $user_id");
        $mysqli->query("UPDATE payment_orders SET status = 'paid', paid_at = NOW() WHERE order_id = '$order_id'");

        // Log sukses
        file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - Payment success: $order_id\n", FILE_APPEND);
    }
} elseif ($transaction_status == 'deny' || $transaction_status == 'cancel' || $transaction_status == 'expire') {
    // Pembayaran gagal
    $mysqli->query("UPDATE payment_orders SET status = 'failed' WHERE order_id = '$order_id'");
    file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - Payment failed: $order_id\n", FILE_APPEND);
}

// Response OK ke Midtrans
http_response_code(200);
echo 'OK';
?>