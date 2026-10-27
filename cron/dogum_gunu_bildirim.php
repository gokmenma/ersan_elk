<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

$_SESSION = [];

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\DogumGunuBildirimModel;

try {
    $pdo = (new \App\Core\Db())->db;
    $firmalar = $pdo->query("SELECT id FROM firmalar WHERE silinme_tarihi IS NULL")->fetchAll(PDO::FETCH_COLUMN);

    $model = new DogumGunuBildirimModel();
    $toplam = 0;
    foreach ($firmalar as $firmaId) {
        $_SESSION['firma_id'] = $firmaId;
        $gonderilen = $model->sendDogumGunuBildirimleri((int) $firmaId);
        $toplam += $gonderilen;
        echo '[' . date('Y-m-d H:i:s') . "] Firma #$firmaId: $gonderilen doğum günü bildirimi gönderildi.\n";
    }
    echo '[' . date('Y-m-d H:i:s') . "] Toplam: $toplam\n";
} catch (\Throwable $e) {
    error_log('Doğum günü cron hatası: ' . $e->getMessage());
    echo "[HATA] Doğum günü cron çalışırken hata oluştu.\n";
}
