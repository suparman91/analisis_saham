<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($isEmbedded) {
  return;
}
if ($currentPage === 'app.php' && isset($_GET['page'])) {
  $currentPage = basename((string)$_GET['page']);
}
?>
<style>
.better-nav {
  background: #0f172a;
  border-radius: 10px;
  margin: 0 auto 20px auto;
  box-shadow: 0 4px 10px rgba(0,0,0,0.15);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  padding: 0;
  width: min(96vw, 1700px);
}

.bnav-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px;
}

.bnav-brand {
  color: #f8fafc;
  font-size: 14px;
  font-weight: 700;
  padding: 8px 10px;
  white-space: nowrap;
}

.bnav-toggle {
  display: none;
  border: 1px solid #334155;
  background: #1e293b;
  color: #e2e8f0;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.bnav-body {
  display: flex;
  align-items: stretch;
  gap: 2px;
  padding: 0 8px 8px 8px;
}

.bnav-left,
.bnav-right {
  display: flex;
  align-items: stretch;
  gap: 2px;
}

.bnav-right {
  margin-left: auto;
}

.bnav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 12px 14px;
  color: #cbd5e1;
  text-decoration: none;
  font-weight: 600;
  font-size: 13px;
  transition: all 0.2s ease-in-out;
  cursor: pointer;
  border-radius: 6px;
  white-space: nowrap;
}

.bnav-item:hover {
  background: #1e293b;
  color: #ffffff;
}

.bnav-item.active {
  background: #3b82f6;
  color: #ffffff;
}

.bnav-dropdown {
  position: relative;
}

.bnav-dropdown-trigger {
  border: none;
  background: transparent;
}

.bnav-dropdown-content {
  display: none;
  position: absolute;
  top: calc(100% + 2px);
  left: 0;
  background-color: #1e293b;
  min-width: 230px;
  box-shadow: 0 8px 16px rgba(0,0,0,0.3);
  z-index: 9999;
  border-radius: 0 0 6px 6px;
  border: 1px solid #334155;
  overflow: hidden;
}

.bnav-dropdown-content a {
  color: #cbd5e1;
  padding: 10px 14px;
  text-decoration: none;
  display: block;
  font-size: 13px;
  font-weight: 500;
  transition: background 0.2s, color 0.2s;
  border-bottom: 1px solid #334155;
}

.bnav-dropdown-content a:last-child {
  border-bottom: none;
}

.bnav-dropdown-content a:hover,
.bnav-dropdown-content a.active-sub {
  background-color: #3b82f6;
  color: #ffffff;
}

@media (min-width: 993px) {
  .bnav-dropdown:hover .bnav-dropdown-content {
    display: block;
  }
}

.bnav-dropdown.open .bnav-dropdown-content {
  display: block;
}

.bnav-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  opacity: 0.95;
}

.bnav-caret {
  margin-left: 6px;
  font-size: 11px;
  opacity: 0.9;
}

@media (max-width: 992px) {
  .bnav-toggle {
    display: inline-flex;
  }

  .bnav-body {
    display: none;
    flex-direction: column;
    padding-top: 0;
  }

  .better-nav.open .bnav-body {
    display: flex;
  }

  .bnav-left,
  .bnav-right {
    flex-direction: column;
    width: 100%;
    margin-left: 0;
  }

  .bnav-dropdown-content {
    position: static;
    min-width: 100%;
    border-radius: 6px;
    margin: 2px 0 6px 0;
  }

  .bnav-item {
    width: 100%;
    justify-content: space-between;
    white-space: normal;
  }
}
</style>

<nav class="better-nav">
  <div class="bnav-top">
    <div class="bnav-brand">&#x1F4CA; Analisis Saham</div>
    <button type="button" class="bnav-toggle" id="bnavToggle">&#x2630; Menu</button>
  </div>

  <div class="bnav-body" id="bnavBody">
    <div class="bnav-left">
      <a href="app.php?page=index.php" class="bnav-item <?= ($currentPage=='index.php')?'active':'' ?>"><span class="bnav-icon">&#x1F3E0;</span>Dashboard Market</a>

      <div class="bnav-dropdown">
        <button type="button" class="bnav-item bnav-dropdown-trigger"><span class="bnav-icon">&#x1F4C8;</span>Chart &amp; Analisis<span class="bnav-caret">&#x25BE;</span></button>
        <div class="bnav-dropdown-content">
          <a href="app.php?page=ihsg.php" class="<?= ($currentPage=='ihsg.php')?'active-sub':'' ?>">&#x1F4C9; Chart IHSG</a>
          <a href="app.php?page=chart.php" class="<?= ($currentPage=='chart.php')?'active-sub':'' ?>">&#x1F4C8; Chart Saham Custom</a>
        </div>
      </div>

      <div class="bnav-dropdown">
        <button type="button" class="bnav-item bnav-dropdown-trigger"><span class="bnav-icon">&#x1F50D;</span>Screeners &amp; Hunters<span class="bnav-caret">&#x25BE;</span></button>
        <div class="bnav-dropdown-content">
          <a href="app.php?page=scan_ta.php" class="<?= ($currentPage=='scan_ta.php')?'active-sub':'' ?>">&#x26A1; Momentum Scanner</a>
          <a href="app.php?page=ara_hunter.php" class="<?= ($currentPage=='ara_hunter.php')?'active-sub':'' ?>">&#x1F680; ARA Hunter</a>
          <a href="app.php?page=arb_hunter.php" class="<?= ($currentPage=='arb_hunter.php')?'active-sub':'' ?>">&#x1F4C9; ARB Hunter</a>
          <a href="app.php?page=scan_manual.php" class="<?= ($currentPage=='scan_manual.php')?'active-sub':'' ?>">&#x1F50E; Scanner BSJP/BPJP</a>
        </div>
      </div>

      <div class="bnav-dropdown">
        <button type="button" class="bnav-item bnav-dropdown-trigger"><span class="bnav-icon">&#x1F916;</span>AI &amp; Automation<span class="bnav-caret">&#x25BE;</span></button>
        <div class="bnav-dropdown-content">
          <a href="app.php?page=portfolio.php" class="<?= ($currentPage=='portfolio.php')?'active-sub':'' ?>">&#x1F916; Robo-Trader Simulator</a>
          <a href="app.php?page=portfolio_manual.php" class="<?= ($currentPage=='portfolio_manual.php')?'active-sub':'' ?>">&#x1F4BC; Autopilot Portofolio (Manual)</a>
        </div>
      </div>
    </div>

    <div class="bnav-right">
      <a href="app.php?page=telegram_setting.php" class="bnav-item <?= ($currentPage=='telegram_setting.php')?'active':'' ?>" style="background:#475569;"><span class="bnav-icon">&#x2699;</span>Telegram &amp; Setting Alert</a>

      <?php if(session_status() === PHP_SESSION_NONE) session_start(); ?>
      <?php if(isset($_SESSION['user_id'])): ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
          <div class="bnav-dropdown">
            <button type="button" class="bnav-item bnav-dropdown-trigger"><span class="bnav-icon">&#x1F451;</span>Admin<span class="bnav-caret">&#x25BE;</span></button>
            <div class="bnav-dropdown-content" style="right:0; left:auto; min-width:180px;">
              <a href="app.php?page=admin.php">&#x1F465; Manage Users</a>
              <a href="app.php?page=admin_manual.php">&#x1F4B3; Manual Langganan</a>
              <a href="app.php?page=scan_retention_settings.php">&#x1F5C3; Retensi Riwayat Scan</a>
            </div>
          </div>
        <?php endif; ?>

        <div class="bnav-dropdown">
          <button type="button" class="bnav-item bnav-dropdown-trigger" style="color:#fcd34d;"><span class="bnav-icon">&#x1F464;</span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?><span class="bnav-caret">&#x25BE;</span></button>
          <div class="bnav-dropdown-content" style="right:0; left:auto; min-width:190px;">
            <a href="app.php?page=user_settings.php">⚙️ Pengaturan User</a>
            <a href="app.php?page=subscribe.php">&#x1F4B3; Langganan (SaaS)</a>
            <a href="logout.php" style="color:#ef4444; font-weight:bold;">&#x1F6AA; Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="bnav-item" style="background:#3b82f6; color:#fff;"><span class="bnav-icon">&#x1F4DD;</span>Login Akun</a>
        <a href="register.php" class="bnav-item" style="background:#16a34a; color:#fff;"><span class="bnav-icon">&#x1F4DD;</span>Daftar Trial</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
(function () {
  const nav = document.querySelector('.better-nav');
  const toggle = document.getElementById('bnavToggle');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
  }

  const triggers = document.querySelectorAll('.bnav-dropdown-trigger');
  triggers.forEach(function (tr) {
    tr.addEventListener('click', function (e) {
      const parent = tr.closest('.bnav-dropdown');
      const isMobile = window.matchMedia('(max-width: 992px)').matches;
      if (!isMobile) return;
      e.preventDefault();
      parent.classList.toggle('open');
    });
  });
})();
</script>
