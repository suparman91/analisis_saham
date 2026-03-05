<?php
// Simple mysqli connection helper
$DB_HOST = '192.168.1.223';
$DB_USER = 'nas_it';
$DB_PASS = 'Nasityc@2025'; // sesuaikan jika perlu
$DB_NAME = 'analisis_saham';

function db_connect() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_errno) {
        http_response_code(500);
        die(json_encode(['error' => 'DB connect error: ' . $mysqli->connect_error]));
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

?>
