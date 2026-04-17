<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Reset and Base Better-Nav Styles */
.better-nav { 
    background: #0f172a; 
    display: flex; 
    align-items: stretch; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    flex-wrap: wrap;
    padding: 0;
}

.bnav-item { 
    position: relative; 
    display: flex; 
    align-items: center; 
    padding: 14px 20px; 
    color: #cbd5e1; 
    text-decoration: none; 
    font-weight: 600; 
    font-size: 14px; 
    transition: all 0.2s ease-in-out; 
    cursor: pointer; 
    border-radius: 5px;
}

.bnav-item:hover { 
    background: #1e293b; 
    color: #ffffff; 
}

.bnav-item.active { 
    background: #3b82f6; 
    color: #ffffff; 
}

/* Dropdown specific */
.bnav-dropdown {
    position: relative;
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: #cbd5e1;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.bnav-dropdown:hover {
    background: #1e293b;
    color: #ffffff;
    border-radius: 5px 5px 0 0;
}

.bnav-dropdown-content {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background-color: #1e293b;
    min-width: 220px;
    box-shadow: 0px 8px 16px rgba(0,0,0,0.3);
    z-index: 9999;
    border-radius: 0 0 5px 5px;
    border: 1px solid #334155;
    border-top: none;
    overflow: hidden;
}

.bnav-dropdown-content a {
    color: #cbd5e1;
    padding: 12px 16px;
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

.bnav-dropdown:hover .bnav-dropdown-content {
    display: block;
}

.bnav-right {
    margin-left: auto;
    display: flex;
}
</style>

<nav class="better-nav">
  <!-- 1. Dashboard -->
  <a href="index.php" class="bnav-item <?= ($currentPage=='index.php')?'active':'' ?>">
    &#x1F3E0; Dashboard Market
  </a>

  <!-- 2. Chart & Analisis -->
  <div class="bnav-dropdown">
    &#x1F4C8; Chart &amp; Analisis &#x25BC;
    <div class="bnav-dropdown-content">
      <a href="ihsg.php" class="<?= ($currentPage=='ihsg.php')?'active-sub':'' ?>">Chart IHSG</a>
      <a href="chart.php" class="<?= ($currentPage=='chart.php')?'active-sub':'' ?>">Chart Saham Custom</a>
    </div>
  </div>

  <!-- 3. Screeners & Hunters -->
  <div class="bnav-dropdown">
    &#x1F50D; Screeners &amp; Hunters &#x25BC;
    <div class="bnav-dropdown-content">
      <a href="scan_ta.php" class="<?= ($currentPage=='scan_ta.php')?'active-sub':'' ?>">&#x26A1; Momentum Scanner (Terbaik)</a>
      <a href="ara_hunter.php" class="<?= ($currentPage=='ara_hunter.php')?'active-sub':'' ?>">&#x1F680; ARA Hunter</a>
      <a href="arb_hunter.php" class="<?= ($currentPage=='arb_hunter.php')?'active-sub':'' ?>">&#x1F4C9; ARB Hunter</a>
      <a href="scan_manual.php" class="<?= ($currentPage=='scan_manual.php')?'active-sub':'' ?>">&#x1F50E; Scanner BSJP/BPJP</a>
    </div>
  </div>

  <!-- 4. AI & Automations -->
  <div class="bnav-dropdown">
    &#x1F916; AI &amp; Automation &#x25BC;
    <div class="bnav-dropdown-content">
      <a href="portfolio.php" class="<?= ($currentPage=='portfolio.php')?'active-sub':'' ?>">&#x1F916; Robo-Trader Simulator</a>
      <a href="portfolio_manual.php" class="<?= ($currentPage=='portfolio_manual.php')?'active-sub':'' ?>">&#x1F4BC; Autopilot Portofolio (Manual)</a>
    </div>
  </div>

  <div class="bnav-right" style="display:flex; align-items:center; gap:10px;">
      <a href="telegram_setting.php" class="bnav-item <?= ($currentPage=='telegram_setting.php')?'active':'' ?>" style="background: #475569;">
        &#x2699;&#xFE0F; Telegram &amp; Seting Alert
      </a>

      <?php if(session_status() === PHP_SESSION_NONE) session_start(); ?>
      <?php if(isset($_SESSION['user_id'])): ?>
         <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <div class="bnav-dropdown">
              &#x1F451; Admin &#x25BC;
              <div class="bnav-dropdown-content" style="right:0; left:auto; min-width:180px;">
                 <a href="admin.php" style="color:#3b82f6;">Manage Users</a>
                 <a href="admin_manual.php" style="color:#f59e0b;">Manual Langganan</a>
              </div>
            </div>
         <?php endif; ?>

         <div class="bnav-dropdown">
            <span style="color:#fcd34d; font-weight:bold;">&#x1F464; <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span> &#x25BC;
            <div class="bnav-dropdown-content" style="right:0; left:auto; min-width:180px;">
               <a href="subscribe.php" style="color:#f59e0b; font-weight:bold;">&#x1F4B3; Langganan (SaaS)</a>
               <a href="logout.php" style="color:#ef4444; font-weight:bold;">&#x1F6AA; Logout</a>
            </div>
         </div>
      <?php else: ?>
         <a href="login.php" class="bnav-item" style="background:#3b82f6;">&#x1F4DD; Login Akun</a>
      <?php endif; ?>
  </div>
</nav>
