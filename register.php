<?php
require_once 'db.php';
require_once 'auth.php';
$mysqli = db_connect();
$error = ''; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_valid_csrf();

  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
    $agreeDisclaimer = isset($_POST['agree_disclaimer']) && $_POST['agree_disclaimer'] === '1';
  $plainPassword = (string)($_POST['password'] ?? '');

    if (!$agreeDisclaimer) {
        $error = 'Anda wajib menyetujui disclaimer sebelum membuat akun.';
  } elseif ($name === '' || mb_strlen($name) > 100) {
    $error = 'Nama wajib diisi dan maksimal 100 karakter.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $error = 'Format email tidak valid.';
  } elseif (strlen($plainPassword) < 8) {
    $error = 'Password minimal 8 karakter.';
    } else {
    $password = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $existingUser = null;
    if ($stmt) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $res = $stmt->get_result();
      $existingUser = $res ? $res->fetch_assoc() : null;
      $stmt->close();
    }

    if ($existingUser) {
            $error = 'Email ini sudah terdaftar!';
        } else {
            $trial_end = date('Y-m-d', strtotime('+7 days'));
      $insert = $mysqli->prepare("INSERT INTO users (name, email, password, subscription_end) VALUES (?, ?, ?, ?)");
      if ($insert) {
        $insert->bind_param('ssss', $name, $email, $password, $trial_end);
        $saved = $insert->execute();
        $insert->close();
      } else {
        $saved = false;
      }

      if ($saved) {
        $success = true;
            } else {
        $error = 'Gagal membuat akun. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Daftar - Analisis Saham Auto-Trader</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      box-sizing: border-box;
    }
    .login-box {
      background: #fff;
      padding: 24px;
      border-radius: 12px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 460px;
    }
    .login-box h2 { text-align: center; color: #0f172a; margin-top:0; margin-bottom: 8px; }
    .small-note { text-align:center; color:#475569; font-size:13px; margin: 0 0 18px 0; }
    label { display:block; color:#334155; font-size:13px; font-weight:700; margin-top:10px; }
    input[type="text"], input[type="email"], input[type="password"] {
      width: 100%;
      padding: 11px;
      margin: 6px 0;
      border: 1px solid #cbd5e1;
      border-radius: 7px;
      box-sizing: border-box;
      font-size: 14px;
    }
    .disclaimer-box {
      background: #fff7ed;
      border: 1px solid #fdba74;
      color: #7c2d12;
      border-radius: 8px;
      padding: 12px;
      font-size: 12px;
      line-height: 1.5;
      margin: 14px 0;
    }
    .consent-row {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin: 10px 0 16px 0;
      font-size: 12px;
      color: #334155;
    }
    .consent-row input { margin-top: 2px; }
    button {
      width: 100%;
      padding: 12px;
      background: #16a34a;
      color: white;
      border: none;
      border-radius: 7px;
      font-weight: bold;
      cursor: pointer;
      font-size: 14px;
    }
    button:hover { background: #15803d; }
    p.err { color: #dc2626; text-align: center; background: #fee2e2; padding:10px; border-radius:5px; font-size:13px; }
    p.succ { color: #166534; text-align: center; background: #dcfce7; padding:10px; border-radius:5px; font-size:13px; }

    @media (max-width: 600px) {
      body { padding: 12px; align-items: flex-start; }
      .login-box { padding: 18px; margin-top: 12px; }
    }
  </style>
</head>
<body>
  <div class="login-box">
    <h2>Daftar Akun Baru</h2>
    <p class="small-note">Mulai trial publik 7 hari untuk uji fitur platform.</p>
    <?php if($error) echo "<p class='err'>" . security_escape($error) . "</p>"; ?>
    <?php if($success) { ?>
    <p class='succ'>Akun berhasil dibuat dengan trial 7 hari gratis! Silakan <a href='login.php' style='color:#16a34a; font-weight:bold; text-decoration:underline;'>login di sini</a>.</p>
    <?php } else { ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= security_escape(csrf_token()) ?>">
      <label>Nama Lengkap:</label>
      <input type="text" name="name" required placeholder="Budi Santoso" autofocus>
      <label>Email:</label>
      <input type="email" name="email" required placeholder="email@anda.com">
      <label>Password:</label>
      <input type="password" name="password" required placeholder="minimal 8 karakter" minlength="8">

      <div class="disclaimer-box">
        Disclaimer: Web ini hanya untuk analisa dan edukasi pasar saham. Seluruh informasi, skor, sinyal, scanner, maupun simulasi di platform ini bukan ajakan jual beli efek, bukan anjuran harga, dan bukan nasihat investasi. Keputusan transaksi sepenuhnya menjadi tanggung jawab pengguna.
      </div>
      <label class="consent-row">
        <input type="checkbox" name="agree_disclaimer" value="1" required>
        <span>Saya memahami dan menyetujui disclaimer di atas.</span>
      </label>

      <button type="submit">Daftar Sekarang</button>
    </form>
    <?php } ?>
    <p style="text-align:center; font-size:14px; margin-top:20px; color:#64748b;">
      Sudah punya akun? <a href="login.php" style="color:#3b82f6;">Login</a>
    </p>
  </div>
</body>
</html>