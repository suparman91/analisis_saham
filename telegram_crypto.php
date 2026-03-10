<?php
$tg_key = "ARAhunter2026!secret#"; // Kunci rahasia untuk enkripsi/dekripsi AES

// Fungsi untuk Enkripsi Chat ID
function tg_encrypt($data) {
    global $tg_key;
    $cipher = "aes-256-cbc";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($data, $cipher, $tg_key, 0, $iv);
    // Gabungkan IV dan Hasil Enkripsi lalu convert ke base64 agar aman disimpan di string/database
    return base64_encode($encrypted . '::' . $iv);
}

// Fungsi untuk Dekripsi Chat ID
function tg_decrypt($data) {
    global $tg_key;
    $cipher = "aes-256-cbc";
    $decoded = base64_decode($data);
    if (strpos($decoded, '::') === false) return false;
    
    list($encrypted_data, $iv) = explode('::', $decoded, 2);
    return openssl_decrypt($encrypted_data, $cipher, $tg_key, 0, $iv);
}

// Fungsi untuk Menyembunyikan (Masking) karakter untuk di layar tampilan
function tg_mask($data) {
    $len = strlen($data);
    if ($len <= 4) return str_repeat('*', $len);
    return str_repeat('*', $len - 4) . substr($data, -4);
}
?>