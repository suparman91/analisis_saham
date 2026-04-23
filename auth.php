<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function security_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_valid_csrf() {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

function complete_login(array $user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)($user['id'] ?? 0);
    $_SESSION['user_name'] = (string)($user['name'] ?? '');
    $_SESSION['user_email'] = (string)($user['email'] ?? '');
    $_SESSION['user_role'] = (string)($user['role'] ?? 'user');
}

function logout_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

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
    return (int)($_SESSION['user_id'] ?? 0);
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