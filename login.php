<?php
require_once 'db.php';
require_once 'auth.php';
$mysqli = db_connect();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_valid_csrf();

  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $error = 'Email atau password tidak valid.';
  } else {
    $stmt = $mysqli->prepare("SELECT id, name, email, role, password FROM users WHERE email = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $res = $stmt->get_result();
      $user = $res ? $res->fetch_assoc() : null;
      $stmt->close();

      if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
        complete_login($user);
        header("Location: app.php?page=index.php");
        exit;
      }
    }

    $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login - Analisis Saham Auto-Trader</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f8fafc; display:flex; justify-content:center; align-items:center; height:100vh; margin:0;}
    .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px;}
    .login-box h2 { text-align: center; color: #0f172a; margin-top:0;}
    input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; }
    button { width: 100%; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    p.err { color: #dc2626; text-align: center; }
  </style>
</head>
<body>
  <div class="login-box">
    <h2>Login Akun</h2>
    <?php if($error) echo "<p class='err'>" . security_escape($error) . "</p>"; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
      <label>Email:</label>
      <input type="email" name="email" required placeholder="email@anda.com" autofocus>
      <label>Password:</label>
      <input type="password" name="password" required placeholder="******">
      <button type="submit">Masuk</button>
    </form>
    <p style="text-align:center; font-size:14px; margin-top:20px;">
      Belum punya akun? <a href="register.php" style="color:#3b82f6;">Daftar di sini</a>
    </p>
  </div>
</body>
</html>