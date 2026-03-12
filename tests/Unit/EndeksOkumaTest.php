<?php

use App\Model\EndeksOkumaModel;

test('EndeksOkumaModel::getSummaryByRange returns correct composite key format', function () {
    $model = new EndeksOkumaModel();
    
    // We need to simulate the environment or mock the DB if possible, 
    // but since this is a live environment validation, we'll check if the method exists 
    // and if it's returning the expected structure if data exists.
    
    // Setting up session for firma_id
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['firma_id'] = $_SESSION['firma_id'] ?? 17;

    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    
    $summary = $model->getSummaryByRange($startDate, $endDate);
    
    expect(is_array($summary))->toBeTrue();
    
    if (!empty($summary)) {
        $firstPerson = reset($summary);
        $keys = array_keys($firstPerson);
        $firstKey = $keys[0];
        
        // Key should be in format "id|name"
        expect($firstKey)->toMatch('/^\d+\|.+/');
    } else {
        // If no data, we at least verified it doesn't crash
        expect($summary)->toBeArray();
    }
});
