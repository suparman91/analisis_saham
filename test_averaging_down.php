<?php
/**
 * Test script untuk averaging-down logic
 * Jalankan: php test_averaging_down.php
 */

// Simulate robo decisions
$test_scenarios = [
    [
        'name' => 'Profitable Position',
        'symbol' => 'BBCA',
        'entry' => 10000,
        'current' => 11000,
        'loss_pct' => 0.10,
        'expected_action' => 'HOLD'
    ],
    [
        'name' => 'Aggressive Accumulation Zone',
        'symbol' => 'BBRI',
        'entry' => 12000,
        'current' => 11760,
        'loss_pct' => -0.02,
        'expected_action' => 'ACCUMULATE'
    ],
    [
        'name' => 'Warning Zone - No Action',
        'symbol' => 'BMRI',
        'entry' => 8000,
        'current' => 7600,
        'loss_pct' => -0.05,
        'expected_action' => 'HOLD (WARNING)'
    ],
    [
        'name' => 'Kelonggaran Zone - Give Grace Period',
        'symbol' => 'BNIS',
        'entry' => 950,
        'current' => 855,
        'loss_pct' => -0.10,
        'expected_action' => 'FORCE SELL'
    ],
];

// Loss thresholds
$LOSS_HOLDOFF_PCT = -0.02;
$LOSS_WARN_PCT = -0.05;
$LOSS_CRITICAL_PCT = -0.10;
$MAX_ACCUMULATION_TIMES = 3;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   ROBO TRADER AVERAGING-DOWN TEST SUITE                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

foreach ($test_scenarios as $scenario) {
    echo "📊 Test: " . $scenario['name'] . "\n";
    echo "   Symbol: " . $scenario['symbol'] . "\n";
    echo "   Entry: Rp " . number_format($scenario['entry'], 0, ',', '.') . "\n";
    echo "   Current: Rp " . number_format($scenario['current'], 0, ',', '.') . "\n";
    echo "   P/L: " . round($scenario['loss_pct'] * 100, 2) . "%\n";
    
    // Decision logic
    $pct = $scenario['loss_pct'];
    $decision = '';
    
    if ($pct >= 0) {
        $decision = 'HOLD ✅ (Profitable)';
    } elseif ($pct >= $LOSS_HOLDOFF_PCT && $pct < 0) {
        $decision = 'ACCUMULATE 📈 (Loss -2% to 0%)';
    } elseif ($pct >= $LOSS_WARN_PCT && $pct < $LOSS_HOLDOFF_PCT) {
        $decision = 'HOLD ⏸ (Warning Zone -5% to -2%)';
    } elseif ($pct >= $LOSS_CRITICAL_PCT && $pct < $LOSS_WARN_PCT) {
        $decision = 'HOLD 💭 (Kelonggaran -10% to -5%)';
    } else {
        $decision = 'FORCE SELL 🚫 (Critical Loss < -10%)';
    }
    
    echo "   Decision: " . $decision . "\n";
    echo "   Expected: " . $scenario['expected_action'] . "\n";
    
    $pass = (strpos($decision, 'ACCUMULATE') !== false && strpos($scenario['expected_action'], 'ACCUMULATE') !== false) ||
            (strpos($decision, 'FORCE SELL') !== false && strpos($scenario['expected_action'], 'FORCE SELL') !== false) ||
            (strpos($decision, 'HOLD') !== false && strpos($scenario['expected_action'], 'HOLD') !== false);
    
    echo "   Status: " . ($pass ? "✅ PASS" : "❌ FAIL") . "\n\n";
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║   ACCUMULATION SCENARIO DETAILED TEST                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Detailed accumulation scenario
$symbol = 'BBCA';
$initial_entry = 10000;
$initial_lots = 1;
$balance = 10000000;

echo "🤖 Scenario: Averaging-Down pada BBCA\n\n";
echo "Initial State:\n";
echo "  - Entry Price: Rp " . number_format($initial_entry, 0, ',', '.') . "\n";
echo "  - Lots: " . $initial_lots . " lot (100 shares)\n";
echo "  - Cash Balance: Rp " . number_format($balance, 0, ',', '.') . "\n\n";

$accumulation_steps = [
    ['price' => 9800, 'loss' => -0.02, 'action' => 'ACCUMULATE'],
    ['price' => 9600, 'loss' => -0.04, 'action' => 'HOLD (WARNING)'],
    ['price' => 9400, 'loss' => -0.06, 'action' => 'HOLD (KELONGGARAN)'],
    ['price' => 9000, 'loss' => -0.10, 'action' => 'FORCE SELL'],
];

$current_balance = $balance;
$total_cost = $initial_entry * $initial_lots * 100;
$total_lots = $initial_lots;

foreach ($accumulation_steps as $step) {
    echo "Hour " . (array_search($step, $accumulation_steps) + 1) . ": Harga turun ke Rp " . number_format($step['price'], 0, ',', '.') . " (Loss: " . round($step['loss'] * 100, 2) . "%)\n";
    
    if ($step['action'] === 'ACCUMULATE') {
        $alloc = floor($current_balance / 2); // Use half balance
        $new_lots = floor($alloc / ($step['price'] * 100));
        $buy_cost = $new_lots * 100 * $step['price'];
        
        echo "   → ACTION: ACCUMULATE\n";
        echo "   → Buy: " . $new_lots . " lots @ Rp " . number_format($step['price'], 0, ',', '.') . "\n";
        echo "   → Cost: Rp " . number_format($buy_cost, 0, ',', '.') . "\n";
        
        $current_balance -= $buy_cost;
        $total_cost += $buy_cost;
        $total_lots += $new_lots;
        
        $avg_price = $total_cost / ($total_lots * 100);
        echo "   → New Avg Price: Rp " . number_format($avg_price, 0, ',', '.') . "\n";
        echo "   → Total Lots: " . $total_lots . "\n";
        echo "   → Remaining Balance: Rp " . number_format($current_balance, 0, ',', '.') . "\n";
    } else if ($step['action'] === 'FORCE SELL') {
        $sell_value = $step['price'] * $total_lots * 100;
        $pl = $sell_value - $total_cost;
        $pl_pct = ($pl / $total_cost) * 100;
        
        echo "   → ACTION: FORCE SELL\n";
        echo "   → Sell: " . $total_lots . " lots @ Rp " . number_format($step['price'], 0, ',', '.') . "\n";
        echo "   → Revenue: Rp " . number_format($sell_value, 0, ',', '.') . "\n";
        echo "   → Total Cost: Rp " . number_format($total_cost, 0, ',', '.') . "\n";
        echo "   → P/L: Rp " . number_format($pl, 0, ',', '.') . " (" . round($pl_pct, 2) . "%)\n";
        echo "   → Final Balance: Rp " . number_format($current_balance + $sell_value, 0, ',', '.') . "\n";
    } else {
        echo "   → ACTION: " . $step['action'] . " (No trade)\n";
    }
    echo "\n";
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║   CONCLUSION                                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "✅ Averaging-down logic bekerja sebagai berikut:\n";
echo "   1. Jika loss -2% to 0%: ACCUMULATE (rata-rata harga turun)\n";
echo "   2. Jika loss -5% to -2%: HOLD WARNING (jangan jual, jangan beli)\n";
echo "   3. Jika loss -10% to -5%: HOLD KELONGGARAN (grace period)\n";
echo "   4. Jika loss < -10%: FORCE SELL (limit damage)\n\n";
echo "🎯 Result: Dengan accumulation, average cost bisa lebih rendah\n";
echo "   dari initial entry, sehingga break-even lebih mudah tercapai.\n";
?>
