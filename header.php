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
        /* ── Tabel responsif global ───────────────────────────────────────── */
        .auto-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
        }
        .page-card table,
        .container table {
            min-width: 600px;
        }
        .mobile-table-cards {
            display: none;
        }
        .mt-card {
            border: 1px solid #dbeafe;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .mt-card > summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 14px;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            border-bottom: 1px solid #e2e8f0;
        }
        .mt-card > summary::-webkit-details-marker {
            display: none;
        }
        .mt-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .mt-card-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
            word-break: break-word;
        }
        .mt-card-hint {
            color: #475569;
            font-size: 12px;
            white-space: nowrap;
        }
        .mt-card-body {
            padding: 10px 12px 12px;
        }
        .mt-row {
            display: grid;
            grid-template-columns: minmax(90px, 34%) 1fr;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            align-items: start;
        }
        .mt-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .mt-key {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            word-break: break-word;
        }
        .mt-value {
            color: #0f172a;
            font-size: 13px;
            word-break: break-word;
        }
        .mt-empty {
            padding: 10px 12px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .page-card,
            .container {
                padding: 12px;
                border-radius: 10px;
            }
            .page-main {
                padding: 10px;
            }
            .auto-table-wrap.mobile-cards-enabled {
                overflow: visible;
            }
            .auto-table-wrap.mobile-cards-enabled > table {
                display: none;
            }
            .auto-table-wrap.mobile-cards-enabled .mobile-table-cards {
                display: grid;
                gap: 10px;
            }
            .mt-row {
                grid-template-columns: 1fr;
                gap: 4px;
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
<script>
// Auto-wrap tabel + mode kartu di mobile (accordion details)
(function () {
    function getHeaders(table) {
        var headers = [];
        var headRow = table.querySelector('thead tr:last-child');
        if (headRow) {
            headers = Array.prototype.map.call(headRow.children, function (cell) {
                return (cell.textContent || '').trim() || 'Kolom';
            });
        }
        if (!headers.length) {
            var firstBodyRow = table.querySelector('tbody tr');
            if (firstBodyRow) {
                headers = Array.prototype.map.call(firstBodyRow.children, function (_, idx) {
                    return 'Kolom ' + (idx + 1);
                });
            }
        }
        return headers;
    }

    function buildMobileCards(table, wrapper) {
        var preferTable = table.getAttribute('data-mobile-view') === 'table' || table.classList.contains('keep-table-mobile');
        if (preferTable) {
            wrapper.classList.remove('mobile-cards-enabled');
            var existing = wrapper.querySelector('.mobile-table-cards');
            if (existing) existing.remove();
            return;
        }

        var headers = getHeaders(table);
        var rows = table.querySelectorAll('tbody tr');
        var cardsContainer = wrapper.querySelector('.mobile-table-cards');
        if (!cardsContainer) {
            cardsContainer = document.createElement('div');
            cardsContainer.className = 'mobile-table-cards';
            wrapper.appendChild(cardsContainer);
        }
        cardsContainer.innerHTML = '';

        if (!rows.length) {
            var empty = document.createElement('div');
            empty.className = 'mt-empty';
            empty.textContent = 'Belum ada data.';
            cardsContainer.appendChild(empty);
            wrapper.classList.add('mobile-cards-enabled');
            return;
        }

        rows.forEach(function (tr, rowIdx) {
            var cells = tr.querySelectorAll('td, th');
            if (!cells.length) return;

            var title = '';
            for (var i = 0; i < Math.min(2, cells.length); i++) {
                var t = (cells[i].textContent || '').trim();
                if (t) {
                    title = t;
                    break;
                }
            }
            if (!title) title = 'Data #' + (rowIdx + 1);

            var card = document.createElement('details');
            card.className = 'mt-card';

            var summary = document.createElement('summary');
            summary.innerHTML = '<div class="mt-card-head"><span class="mt-card-title"></span><span class="mt-card-hint">Tap untuk detail</span></div>';
            summary.querySelector('.mt-card-title').textContent = title;
            card.appendChild(summary);

            var body = document.createElement('div');
            body.className = 'mt-card-body';

            cells.forEach(function (cell, colIdx) {
                var label = cell.getAttribute('data-label') || headers[colIdx] || ('Kolom ' + (colIdx + 1));
                var valueHtml = (cell.innerHTML || '').trim();
                if (!valueHtml) valueHtml = '-';

                var row = document.createElement('div');
                row.className = 'mt-row';
                row.innerHTML = '<div class="mt-key"></div><div class="mt-value"></div>';
                row.querySelector('.mt-key').textContent = label;
                row.querySelector('.mt-value').innerHTML = valueHtml;
                body.appendChild(row);
            });

            card.appendChild(body);
            cardsContainer.appendChild(card);
        });

        wrapper.classList.add('mobile-cards-enabled');
    }

    function processTable(table) {
        if (!table || !table.parentNode) return;

        var wrapper = table.parentElement;
        if (!wrapper || !wrapper.classList.contains('auto-table-wrap')) {
            wrapper = document.createElement('div');
            wrapper.className = 'auto-table-wrap';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }

        buildMobileCards(table, wrapper);
    }

    function processTables(root) {
        (root || document).querySelectorAll('table').forEach(processTable);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            processTables(document);
        });
    } else {
        processTables(document);
    }

    if (window.MutationObserver) {
        var obs = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.tagName === 'TABLE') processTable(n);
                    else if (n.querySelectorAll) processTables(n);
                });
            });
        });
        document.addEventListener('DOMContentLoaded', function () {
            obs.observe(document.body, { childList: true, subtree: true });
        });
    }
})();
</script>
