<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

require_login();

$allowedPages = [
    'index.php',
    'ihsg.php',
    'chart.php',
    'scan_ta.php',
    'scan_manual.php',
    'ara_hunter.php',
    'arb_hunter.php',
    'portfolio.php',
    'portfolio_manual.php',
    'telegram_setting.php',
    'user_settings.php',
    'subscribe.php',
    'admin.php',
    'admin_manual.php',
    'scan_retention_settings.php'
];

$page = basename((string)($_GET['page'] ?? 'index.php'));
if (!in_array($page, $allowedPages, true)) {
    $page = 'index.php';
}

$contentSrc = $page . '?embed=1';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Analisis Saham - App Shell</title>
  <style>
    html, body {
      margin: 0;
      padding: 0;
      background: #f8f9fa;
      font-family: Arial, Helvetica, sans-serif;
    }
    .app-wrap {
      min-height: 100vh;
      padding: 0;
      box-sizing: border-box;
    }
    .content-wrapper {
      width: min(96vw, 1700px);
      margin: 0 auto;
      padding: 0 8px 20px 8px;
      box-sizing: border-box;
    }
    .content-shell {
      width: 100%;
      margin: 0;
      border: 0;
      border-radius: 10px;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .content-loading {
      height: 3px;
      width: 100%;
      background: linear-gradient(90deg, #0d6efd, #60a5fa, #0d6efd);
      background-size: 200% 100%;
      animation: loadingMove 1.1s linear infinite;
      display: none;
    }
    .content-shell.loading .content-loading {
      display: block;
    }
    #appContent {
      width: 100%;
      min-height: calc(100vh - 180px);
      border: none;
      background: #fff;
      display: block;
    }
    @keyframes loadingMove {
      0% { background-position: 0% 50%; }
      100% { background-position: 200% 50%; }
    }
    @media (max-width: 992px) {
      .app-wrap {
        padding: 0;
      }
      .content-wrapper {
        width: 100%;
        padding: 0 6px 15px 6px;
      }
      #appContent {
        min-height: calc(100vh - 150px);
      }
    }
  </style>
</head>
<body>
<div class="app-wrap">
  <?php include __DIR__ . '/nav.php'; ?>

  <div class="content-wrapper">
    <div class="content-shell">
      <div class="content-loading"></div>
      <iframe id="appContent" src="<?= htmlspecialchars($contentSrc, ENT_QUOTES, 'UTF-8') ?>" title="Analisis Saham Content"></iframe>
    </div>
  </div>
</div>

<script>
(function () {
  const frame = document.getElementById('appContent');
  const shell = document.querySelector('.content-shell');
  const NAV_SELECTOR = '.better-nav a[href^="app.php?page="]';

  function setLoading(isLoading) {
    if (!shell) return;
    shell.classList.toggle('loading', !!isLoading);
  }

  function getPageFromHref(href) {
    try {
      const url = new URL(href, window.location.origin);
      const p = url.searchParams.get('page') || 'index.php';
      return p.split('/').pop();
    } catch (e) {
      return 'index.php';
    }
  }

  function updateActive(page) {
    const pageName = (page || 'index.php').split('/').pop();
    document.querySelectorAll('.better-nav .active').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.better-nav .active-sub').forEach(el => el.classList.remove('active-sub'));

    document.querySelectorAll(NAV_SELECTOR).forEach(link => {
      const linkPage = getPageFromHref(link.getAttribute('href') || '');
      if (linkPage !== pageName) return;
      if (link.classList.contains('bnav-item')) {
        link.classList.add('active');
      } else {
        link.classList.add('active-sub');
      }
    });
  }

  function loadPage(page, pushState = true) {
    const cleanPage = (page || 'index.php').split('/').pop();
    setLoading(true);
    frame.src = cleanPage + '?embed=1';
    if (pushState) {
      const u = new URL(window.location.href);
      u.searchParams.set('page', cleanPage);
      history.pushState({ page: cleanPage }, '', u.toString());
    }
    updateActive(cleanPage);
  }

  frame.addEventListener('load', function () {
    setLoading(false);

    try {
      const doc = frame.contentDocument;
      if (!doc) return;

      let wideStyle = doc.getElementById('app-shell-wide-style');
      if (!wideStyle) {
        wideStyle = doc.createElement('style');
        wideStyle.id = 'app-shell-wide-style';
        wideStyle.textContent = `
          html, body {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
          }
          body > * {
            width: 100% !important;
            max-width: none !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
          }
          .container,
          .db-container,
          .page-main,
          .content,
          .main-content,
          .wrapper,
          .content-wrap,
          .app-container,
          .layout,
          .layout-content,
          div[class*="container"],
          main,
          article,
          section {
            width: 100% !important;
            max-width: none !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
          }
          table {
            width: 100% !important;
          }
          @media (max-width: 992px) {
            html, body {
              padding: 0 !important;
            }
          }
        `;
        doc.head.appendChild(wideStyle);
      }

      doc.querySelectorAll('a[href]').forEach(function (a) {
        a.addEventListener('click', function (ev) {
          const raw = a.getAttribute('href') || '';
          if (raw === '' || raw.startsWith('#') || raw.startsWith('javascript:') || a.target === '_blank') {
            return;
          }

          const url = new URL(raw, window.location.origin);
          const path = (url.pathname.split('/').pop() || '').toLowerCase();
          const allowed = ['index.php','ihsg.php','chart.php','scan_ta.php','scan_manual.php','ara_hunter.php','arb_hunter.php','portfolio.php','portfolio_manual.php','telegram_setting.php','user_settings.php','subscribe.php','admin.php','admin_manual.php','scan_retention_settings.php'];
          if (!allowed.includes(path)) {
            return;
          }

          ev.preventDefault();
          loadPage(path, true);
        });
      });
    } catch (e) {
      // Ignore cross-document access issues.
    }
  });

  document.querySelectorAll(NAV_SELECTOR).forEach(link => {
    if (link.getAttribute('href') && link.getAttribute('href').includes('logout.php')) {
      return;
    }
    link.addEventListener('click', function (e) {
      e.preventDefault();
      loadPage(getPageFromHref(link.getAttribute('href') || 'index.php'));
    });
  });

  window.addEventListener('popstate', function (e) {
    const page = (e.state && e.state.page) || new URL(window.location.href).searchParams.get('page') || 'index.php';
    loadPage(page, false);
  });

  updateActive('<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>');
  setLoading(true);
})();
</script>
</body>
</html>
