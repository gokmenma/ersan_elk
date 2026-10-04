<?php
// Web requests cannot invoke a worker or bypass the authorized API.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
date_default_timezone_set('Europe/Istanbul');
set_time_limit(0);
$id = $argv[1] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/D', $id)) exit(1);
$worker = new App\Service\EInvoiceSyncWorker(
    new App\Model\EInvoiceSyncJobModel(),
    new App\Service\EInvoiceService(),
    static fn(int $firm) => new App\Service\EdmSoapClient($firm),
);
$worker->run($id);
