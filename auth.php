<?php
session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function get_user_id() {
    return $_SESSION['user_id'] ?? 0;
}

function is_admin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        die("<h3>Akses Ditolak!</h3>Hanya Administrator yang diizinkan mengakses halaman ini.");
    }
}

function has_active_subscription($mysqli) {
    if (!is_logged_in()) return false;
    if (is_admin()) return true; // Admin selalu bypass / gratis selamanya

    $uid = get_user_id();
    $res = $mysqli->query("SELECT subscription_end FROM users WHERE id = $uid");
    if ($r = $res->fetch_assoc()) {
        if ($r['subscription_end']) {
            $end = strtotime($r['subscription_end']);
            $now = time();
            return $now <= $end; // TRUE if not expired
        }
    }
    return false;
}

function require_subscription($mysqli) {
    require_login();
    if (!has_active_subscription($mysqli)) {
        header("Location: subscribe.php");
        exit;
    }
}
?>