<?php
function tg_security_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'encryption_key' => getenv('APP_TG_ENCRYPTION_KEY') ?: '',
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    ];

    $localConfigFile = __DIR__ . '/security.local.php';
    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;
        if (is_array($localConfig)) {
            $config = array_merge($config, array_intersect_key($localConfig, $config));
        }
    }

    return $config;
}

function tg_secret_key() {
    $config = tg_security_config();
    return (string)($config['encryption_key'] ?? '');
}

function tg_bot_token() {
    $config = tg_security_config();
    return trim((string)($config['bot_token'] ?? ''));
}

// Fungsi untuk Enkripsi Chat ID
function tg_encrypt($data) {
    $tg_key = tg_secret_key();
    if ($tg_key === '') {
        return false;
    }
    $cipher = "aes-256-cbc";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($data, $cipher, $tg_key, 0, $iv);
    // Gabungkan IV dan Hasil Enkripsi lalu convert ke base64 agar aman disimpan di string/database
    return base64_encode($encrypted . '::' . $iv);
}

// Fungsi untuk Dekripsi Chat ID
function tg_decrypt($data) {
    $tg_key = tg_secret_key();
    if ($tg_key === '') {
        return false;
    }
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