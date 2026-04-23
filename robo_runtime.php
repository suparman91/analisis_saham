<?php
function robo_ensure_settings_table($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS robo_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        guard_price_threshold DECIMAL(5,2) DEFAULT 5.0,
        semi_auto_mode INT DEFAULT 0,
        manual_approval INT DEFAULT 0,
        strategy_profile VARCHAR(20) NOT NULL DEFAULT 'balanced',
        market_adaptive INT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hasStrategyProfile = $mysqli->query("SHOW COLUMNS FROM robo_settings LIKE 'strategy_profile'");
    if ($hasStrategyProfile && $hasStrategyProfile->num_rows === 0) {
        $mysqli->query("ALTER TABLE robo_settings ADD COLUMN strategy_profile VARCHAR(20) NOT NULL DEFAULT 'balanced' AFTER manual_approval");
    }

    $hasMarketAdaptive = $mysqli->query("SHOW COLUMNS FROM robo_settings LIKE 'market_adaptive'");
    if ($hasMarketAdaptive && $hasMarketAdaptive->num_rows === 0) {
        $mysqli->query("ALTER TABLE robo_settings ADD COLUMN market_adaptive INT NOT NULL DEFAULT 1 AFTER strategy_profile");
    }
}

function robo_get_user_settings($mysqli, $userId) {
    robo_ensure_settings_table($mysqli);

    $stmt = $mysqli->prepare("SELECT guard_price_threshold, semi_auto_mode, manual_approval, strategy_profile, market_adaptive FROM robo_settings WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $stmtInsert = $mysqli->prepare("INSERT INTO robo_settings (user_id, guard_price_threshold, semi_auto_mode, manual_approval, strategy_profile, market_adaptive) VALUES (?, 5.0, 0, 0, 'balanced', 1)");
        $stmtInsert->bind_param('i', $userId);
        $stmtInsert->execute();
        $stmtInsert->close();

        $row = [
            'guard_price_threshold' => 5.0,
            'semi_auto_mode' => 0,
            'manual_approval' => 0,
            'strategy_profile' => 'balanced',
            'market_adaptive' => 1,
        ];
    }

    $allowedProfiles = ['conservative', 'balanced', 'aggressive'];
    $row['strategy_profile'] = strtolower((string)($row['strategy_profile'] ?? 'balanced'));
    if (!in_array($row['strategy_profile'], $allowedProfiles, true)) {
        $row['strategy_profile'] = 'balanced';
    }
    $row['market_adaptive'] = (int)($row['market_adaptive'] ?? 1);

    return $row;
}

function robo_is_bursa_open() {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $day = $now->format('N');
    $time = $now->format('H:i');

    if ($day >= 1 && $day <= 4) {
        return ($time >= '09:00' && $time <= '12:00') || ($time >= '13:30' && $time <= '15:49');
    }
    if ($day == 5) {
        return ($time >= '09:00' && $time <= '11:30') || ($time >= '14:00' && $time <= '15:49');
    }
    return false;
}

function robo_get_market_context() {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $day = (int)$now->format('N');
    $time = $now->format('H:i');
    $session = 'OFF_MARKET';

    if ($day >= 1 && $day <= 5) {
        if (robo_is_bursa_open()) {
            $session = 'LIVE';
        } elseif (($day >= 1 && $day <= 4 && $time > '12:00' && $time < '13:30') || ($day == 5 && $time > '11:30' && $time < '14:00')) {
            $session = 'BREAK';
        } elseif ($time < '09:00') {
            $session = 'PREOPEN';
        } else {
            $session = 'POSTMARKET';
        }
    }

    $sentimentLabel = 'NETRAL';
    $sentimentScore = 0;
    $sentimentSummary = 'Belum ada pembacaan sentimen terbaru.';
    $sentimentFile = __DIR__ . '/tmp_investor_sentiment.json';
    if (is_file($sentimentFile)) {
        $raw = file_get_contents($sentimentFile);
        $json = json_decode((string)$raw, true);
        if (is_array($json)) {
            $sentimentLabel = strtoupper((string)($json['label'] ?? 'NETRAL'));
            $sentimentScore = (int)($json['score'] ?? 0);
            $sentimentSummary = (string)($json['summary'] ?? $sentimentSummary);
        }
    }

    $sentimentGroup = 'neutral';
    if ($sentimentScore >= 2 || $sentimentLabel === 'BULLISH') {
        $sentimentGroup = 'bullish';
    } elseif ($sentimentScore <= -2 || in_array($sentimentLabel, ['BEARISH', 'NEGATIF'], true)) {
        $sentimentGroup = 'bearish';
    }

    return [
        'timestamp' => $now->format('Y-m-d H:i:s'),
        'session' => $session,
        'is_bursa_open' => robo_is_bursa_open(),
        'sentiment_label' => $sentimentLabel,
        'sentiment_score' => $sentimentScore,
        'sentiment_group' => $sentimentGroup,
        'sentiment_summary' => $sentimentSummary,
    ];
}

function robo_build_runtime_config(array $userSettings, array $marketContext) {
    $profile = $userSettings['strategy_profile'] ?? 'balanced';
    $adaptive = !empty($userSettings['market_adaptive']);

    $config = [
        'profile_label' => 'Balanced',
        'tp_pct' => 0.05,
        'sl_pct' => -0.03,
        'max_alloc' => 10000000,
        'target_positions' => 5,
        'allin_score' => 90,
        'max_buy_per_run' => 2,
        'min_entry_score' => 60,
        'allow_new_buys' => true,
        'status_note' => 'Mode default aktif.',
    ];

    if ($profile === 'conservative') {
        $config['profile_label'] = 'Conservative';
        $config['tp_pct'] = 0.04;
        $config['sl_pct'] = -0.025;
        $config['max_alloc'] = 7000000;
        $config['target_positions'] = 4;
        $config['max_buy_per_run'] = 1;
        $config['min_entry_score'] = 68;
        $config['status_note'] = 'Prioritaskan proteksi modal dan entry lebih selektif.';
    } elseif ($profile === 'aggressive') {
        $config['profile_label'] = 'Aggressive';
        $config['tp_pct'] = 0.07;
        $config['sl_pct'] = -0.04;
        $config['max_alloc'] = 15000000;
        $config['target_positions'] = 6;
        $config['max_buy_per_run'] = 3;
        $config['min_entry_score'] = 55;
        $config['status_note'] = 'Lebih agresif pada momentum kuat dan toleransi swing lebih lebar.';
    }

    if (!$marketContext['is_bursa_open']) {
        $config['allow_new_buys'] = false;
        $config['status_note'] = 'Di luar jam bursa: robo hanya monitor dan evaluasi, tanpa entry baru.';
    }

    if ($adaptive) {
        if ($marketContext['sentiment_group'] === 'bullish') {
            $config['min_entry_score'] = max(45, $config['min_entry_score'] - 5);
            $config['max_buy_per_run'] += 1;
            $config['max_alloc'] = (int)round($config['max_alloc'] * 1.1);
            $config['status_note'] = 'Sentimen pasar mendukung, entry dibuat sedikit lebih aktif.';
        } elseif ($marketContext['sentiment_group'] === 'bearish') {
            $config['min_entry_score'] += 8;
            $config['max_buy_per_run'] = max(1, $config['max_buy_per_run'] - 1);
            $config['max_alloc'] = (int)round($config['max_alloc'] * 0.75);
            $config['status_note'] = 'Sentimen pasar lemah, entry diperketat dan alokasi diperkecil.';
        }

        if ($marketContext['session'] === 'BREAK') {
            $config['allow_new_buys'] = false;
            $config['status_note'] = 'Saat jeda sesi bursa, robo menahan entry baru sementara.';
        }
    }

    return $config;
}
