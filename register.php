<?php
require_once 'db.php';
require_once 'auth.php';
$mysqli = db_connect();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $mysqli->real_escape_string(trim($_POST['name']));
    $email = $mysqli->real_escape_string(trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Cek duplikat
    $res = $mysqli->query("SELECT id FROM users WHERE email = '$email'");
    if ($res && $res->num_rows > 0) {
        $error = 'Email ini sudah terdaftar!';
    } else {
        // Daftar akun baru dengan trial 7 hari gratis
        $trial_end = date('Y-m-d', strtotime('+7 days'));
        if ($mysqli->query("INSERT INTO users (name, email, password, subscription_end) VALUES ('$name', '$email', '$password', '$trial_end')")) {
            $success = "Akun berhasil dibuat dengan trial 7 hari gratis! Silakan <a href='login.php' style='color:#16a34a; font-weight:bold; text-decoration:underline;'>Login di sini</a>.";
        } else {
            $error = 'Gagal membuat akun: ' . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Daftar - Analisis Saham Auto-Trader</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f8fafc; display:flex; justify-content:center; align-items:center; height:100vh; margin:0;}
    .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px;}
    .login-box h2 { text-align: center; color: #0f172a; margin-top:0;}
    input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; }
    button { width: 100%; padding: 12px; background: #16a34a; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    p.err { color: #dc2626; text-align: center; background: #fee2e2; padding:10px; border-radius:5px;}
    p.succ { color: #166534; text-align: center; background: #dcfce7; padding:10px; border-radius:5px;}
  </style>
</head>
<body>
  <div class="login-box">
    <h2>Daftar Akun Baru</h2>
    <?php if($error) echo "<p class='err'>$error</p>"; ?>
    <?php if($success) echo "<p class='succ'>$success</p>"; else { ?>
    <form method="POST">
      <label>Nama Lengkap:</label>
      <input type="text" name="name" required placeholder="Budi Santoso" autofocus>
      <label>Email:</label>
      <input type="email" name="email" required placeholder="email@anda.com">
      <label>Password:</label>
      <input type="password" name="password" required placeholder="minimal 6 karakter" minlength="6">
      <button type="submit">Daftar Sekarang</button>
    </form>
    <?php } ?>
    <p style="text-align:center; font-size:14px; margin-top:20px; color:#64748b;">
      Sudah punya akun? <a href="login.php" style="color:#3b82f6;">Login</a>
    </p>
  </div>
</body>
</html>