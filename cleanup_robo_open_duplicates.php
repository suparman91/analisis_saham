<?php
// One-time cleanup script: merge duplicate OPEN rows in robo_trades per (user_id, symbol)
// Usage: php cleanup_robo_open_duplicates.php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/db.php';

$mysqli = db_connect();
$mysqli->set_charset('utf8mb4');

function fetch_duplicate_groups(mysqli $mysqli): array {
    $groups = [];
    $sql = "SELECT user_id, symbol, COUNT(*) AS cnt
            FROM robo_trades
            WHERE status = 'OPEN'
            GROUP BY user_id, symbol
            HAVING COUNT(*) > 1
            ORDER BY user_id ASC, symbol ASC";

    $res = $mysqli->query($sql);
    if (!$res) {
        throw new RuntimeException('Gagal membaca grup duplikat: ' . $mysqli->error);
    }

    while ($row = $res->fetch_assoc()) {
        $groups[] = [
            'user_id' => (int)$row['user_id'],
            'symbol' => (string)$row['symbol'],
            'cnt' => (int)$row['cnt'],
        ];
    }

    return $groups;
}

function merge_one_group(mysqli $mysqli, int $userId, string $symbol): array {
    $symbolEsc = $mysqli->real_escape_string($symbol);
    $sqlRows = "SELECT id, buy_price, lots, buy_date, buy_reason
                FROM robo_trades
                WHERE status = 'OPEN' AND user_id = {$userId} AND symbol = '{$symbolEsc}'
                ORDER BY id ASC";
    $resRows = $mysqli->query($sqlRows);
    if (!$resRows) {
        throw new RuntimeException('Gagal ambil detail rows: ' . $mysqli->error);
    }

    $rows = [];
    while ($r = $resRows->fetch_assoc()) {
        $rows[] = $r;
    }

    if (count($rows) <= 1) {
        return ['merged' => false, 'removed' => 0];
    }

    $keepId = (int)$rows[0]['id'];
    $minBuyDate = (string)$rows[0]['buy_date'];
    $totalLots = 0;
    $weighted = 0.0;
    $reasons = [];
    $deleteIds = [];

    foreach ($rows as $idx => $r) {
        $id = (int)$r['id'];
        $lots = (int)$r['lots'];
        $price = (float)$r['buy_price'];
        $buyDate = (string)$r['buy_date'];
        $reason = trim((string)($r['buy_reason'] ?? ''));

        if ($lots <= 0 || $price <= 0) {
            continue;
        }

        $totalLots += $lots;
        $weighted += ($price * $lots);

        if ($buyDate !== '' && $buyDate < $minBuyDate) {
            $minBuyDate = $buyDate;
        }

        if ($reason !== '') {
            $reasons[] = $reason;
        }

        if ($idx > 0) {
            $deleteIds[] = $id;
        }
    }

    if ($totalLots <= 0) {
        return ['merged' => false, 'removed' => 0];
    }

    $avgPrice = $weighted / $totalLots;
    $mergedReason = 'MERGED DUPLICATE OPEN: ' . implode(' | ', array_values(array_unique($reasons)));
    $mergedReasonEsc = $mysqli->real_escape_string($mergedReason);

    $updateSql = "UPDATE robo_trades
                  SET buy_price = {$avgPrice}, lots = {$totalLots}, buy_date = '{$minBuyDate}', buy_reason = '{$mergedReasonEsc}'
                  WHERE id = {$keepId} AND user_id = {$userId} AND status = 'OPEN'";
    if (!$mysqli->query($updateSql)) {
        throw new RuntimeException('Gagal update keeper row: ' . $mysqli->error);
    }

    $removed = 0;
    if (!empty($deleteIds)) {
        $ids = implode(',', array_map('intval', $deleteIds));
        $deleteSql = "DELETE FROM robo_trades WHERE user_id = {$userId} AND status = 'OPEN' AND id IN ({$ids})";
        if (!$mysqli->query($deleteSql)) {
            throw new RuntimeException('Gagal hapus row duplikat: ' . $mysqli->error);
        }
        $removed = $mysqli->affected_rows;
    }

    return [
        'merged' => true,
        'removed' => $removed,
        'keep_id' => $keepId,
        'lots' => $totalLots,
        'avg_price' => $avgPrice,
    ];
}

try {
    $groups = fetch_duplicate_groups($mysqli);
    if (count($groups) === 0) {
        echo "Tidak ada duplikat OPEN yang perlu dirapikan.\n";
        exit(0);
    }

    echo "Ditemukan " . count($groups) . " grup duplikat OPEN. Mulai merge...\n";
    $totalRemoved = 0;
    $mergedGroups = 0;

    $mysqli->begin_transaction();
    foreach ($groups as $g) {
        $result = merge_one_group($mysqli, $g['user_id'], $g['symbol']);
        if (!empty($result['merged'])) {
            $mergedGroups++;
            $totalRemoved += (int)$result['removed'];
            echo "[OK] U" . $g['user_id'] . " " . $g['symbol']
                . " -> keep id " . $result['keep_id']
                . ", total lot " . $result['lots']
                . ", avg " . number_format((float)$result['avg_price'], 4, '.', '')
                . ", removed " . $result['removed'] . "\n";
        }
    }
    $mysqli->commit();

    echo "Selesai. Grup dirapikan: {$mergedGroups}, baris duplikat dihapus: {$totalRemoved}.\n";
    exit(0);
} catch (Throwable $e) {
    if ($mysqli->errno === 0) {
        // no-op
    }
    if ($mysqli->ping()) {
        $mysqli->rollback();
    }
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
