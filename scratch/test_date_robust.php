<?php
$dates = ['4/3/2026', '4.3.2026', '4-3-2026'];
foreach($dates as $d) {
    try {
        $dt = new DateTime($d);
        echo "Input: $d | Result: " . $dt->format('Y-m-d') . "\n";
    } catch (Exception $e) {
        echo "Input: $d | Error: " . $e->getMessage() . "\n";
    }
}

echo "Using strtotime:\n";
foreach($dates as $d) {
    $t = strtotime($d);
    echo "Input: $d | Result: " . ($t ? date('Y-m-d', $t) : 'Fail') . "\n";
}
