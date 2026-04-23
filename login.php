<?php
require_once 'db.php';
require_once 'auth.php';
$mysqli = db_connect();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $mysqli->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];

    $res = $mysqli->query("SELECT * FROM users WHERE email = '$email' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
          header("Location: app.php?page=index.php");
            exit;
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Email tidak ditemukan!';
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
    <?php if($error) echo "<p class='err'>$error</p>"; ?>
    <form method="POST">
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