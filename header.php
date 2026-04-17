<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Analisis Saham';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        :root {
            color-scheme: light;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: #f1f5f9;
            color: #0f172a;
            line-height: 1.6;
        }
        a {
            color: inherit;
        }
        .page-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-main {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px;
            flex: 1;
        }
        .page-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
        }
        .page-header .title {
            margin: 0;
            font-size: 28px;
            color: #0f172a;
        }
        .page-subtitle {
            margin: 8px 0 0;
            color: #475569;
            font-size: 14px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
            text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-secondary { background: #475569; color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .text-muted { color: #64748b; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        @media (max-width: 900px) {
            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
            }
        }
        .link-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            text-decoration: none;
            font-weight: 700;
        }
        .link-card:hover {
            background: #eef2ff;
        }
    </style>
</head>
<body>
<div class="page-shell">
    <?php include __DIR__ . '/nav.php'; ?>
    <div class="page-main">
