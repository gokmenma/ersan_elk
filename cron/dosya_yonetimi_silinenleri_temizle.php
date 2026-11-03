<?php
/**
 * Dosya Yönetimi: eski soft delete ile işaretlenmiş dosya ve klasörleri kalıcı temizler.
 *
 * Yeni silme işlemleri dosyayı zaten anında kalıcı siler; bu betik yalnızca o değişiklikten
 * önce işaretlenmiş kayıtları bir kez temizlemek içindir.
 *
 * Çalıştırmak için: php cron/dosya_yonetimi_silinenleri_temizle.php
 */

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Bu script sadece CLI üzerinden çalışabilir.');
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\DosyaModel;
use App\Model\SystemLogModel;

$model = new DosyaModel();
$db = $model->getDb();

$stmt = $db->prepare("SELECT id, firma_id, dosya_adi, boyut FROM dosyalar WHERE deleted_at IS NOT NULL OR is_active = 0");
$stmt->execute();
$dosyalar = $stmt->fetchAll(PDO::FETCH_OBJ);

$silinenDosya = 0;
$silinenBayt = 0;
foreach ($dosyalar as $dosya) {
    $db->beginTransaction();
    try {
        $model->dosyaKayitlariniSil([(int) $dosya->id], (int) $dosya->firma_id);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('dosya temizleme: kayıt silinemedi #' . $dosya->id . ': ' . $e->getMessage());
        continue;
    }
    $silinenBayt += (int) $dosya->boyut * $model->diskDosyalariniSil([$dosya]);
    $silinenDosya++;
}

$stmt = $db->prepare("SELECT id, firma_id FROM dosya_klasorleri WHERE deleted_at IS NOT NULL OR is_active = 0");
$stmt->execute();
$klasorler = $stmt->fetchAll(PDO::FETCH_OBJ);
$silinenKlasor = 0;
foreach ($klasorler as $klasor) {
    foreach ([
        "DELETE FROM dosya_erisimleri WHERE firma_id = ? AND nesne_tur = 'klasor' AND nesne_id = ?",
        "DELETE FROM dosya_klasorleri WHERE firma_id = ? AND id = ?",
    ] as $sql) {
        $db->prepare($sql)->execute([(int) $klasor->firma_id, (int) $klasor->id]);
    }
    $db->prepare("DELETE FROM dosya_klasor_rolleri WHERE klasor_id = ?")->execute([(int) $klasor->id]);
    $silinenKlasor++;
}

$ozet = sprintf('Dosya yönetimi temizliği: %d dosya, %d klasör kalıcı silindi, ~%.1f MB boşaltıldı.', $silinenDosya, $silinenKlasor, $silinenBayt / 1048576);
(new SystemLogModel())->logActionForFirm(0, 0, 'Dosya Yönetimi', $ozet, SystemLogModel::LEVEL_IMPORTANT);
echo $ozet . PHP_EOL;
