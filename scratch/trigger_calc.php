<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Clear log
// file_put_contents('php_error.log', '');

$model = new App\Model\BordroPersonelModel();
$res = $model->hesaplaMaasByPersonelDonem(84, 20);

echo "Hesaplama sonucu: " . ($res ? "Başarılı" : "Başarısız") . "\n";
