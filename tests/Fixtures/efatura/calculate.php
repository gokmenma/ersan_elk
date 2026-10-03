<?php
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$payload = json_decode(stream_get_contents(STDIN), true);
try {
    if (isset($payload['header'])) (new App\Service\InvoiceValidationService())->validateDraft($payload['header'], $payload['lines']);
    echo json_encode(['status'=>'success','data'=>(new App\Service\InvoiceCalculationService())->calculate($payload['lines'])]);
} catch (Throwable $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
