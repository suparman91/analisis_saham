<?php
// Simple mysqli connection helper.
// Prioritas konfigurasi: db.local.php -> environment variable.

function db_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'host' => getenv('APP_DB_HOST') ?: '',
        'user' => getenv('APP_DB_USER') ?: '',
        'pass' => getenv('APP_DB_PASS') ?: '',
        'name' => getenv('APP_DB_NAME') ?: '',
    ];

    $localConfigFile = __DIR__ . '/db.local.php';
    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;
        if (is_array($localConfig)) {
            $config = array_merge($config, array_intersect_key($localConfig, $config));
        }
    }

    return $config;
}

function db_connect() {
    $config = db_config();
    if (empty($config['host']) || empty($config['user']) || empty($config['name'])) {
        http_response_code(500);
        exit(json_encode(['error' => 'Database configuration is missing.']));
    }

    $mysqli = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
    if ($mysqli->connect_errno) {
        error_log('DB connect error: ' . $mysqli->connect_error);
        http_response_code(500);
        exit(json_encode(['error' => 'Database connection failed.']));
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

?>
