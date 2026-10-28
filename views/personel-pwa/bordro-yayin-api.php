<?php
// Oturum ve personel aktiflik kontrolü views/personel-pwa/api.php tarafından yapılır.
if (!isset($personel_id, $personel) || $personel_id <= 0 || !$personel) { http_response_code(403); exit; }
try {
    \App\Helper\BordroYayinGuvenlik::csrfDogrula();
    $model = new \App\Model\BordroYayinModel();
    $firma = (int) $personel->firma_id;
    if ($action === 'bordro-yayin-liste' || $action === 'getBordrolar') {
        response(true, $model->personelListe($firma, (int) $personel_id));
    }
    $id = \App\Helper\BordroYayinGuvenlik::id($_POST['token'] ?? null);
    if ($action === 'bordro-yayin-detay' || $action === 'getBordroDetay') {
        response(true, $model->detay($firma, $id, (int) $personel_id));
    }
    $islem = match ($action) {
        'bordro-yayin-goruntule' => 'goruntule',
        'bordro-yayin-beyan' => 'beyan',
        'bordro-yayin-talep' => 'talep',
        default => throw new \DomainException('Geçersiz işlem.'),
    };
    if ($islem === 'beyan' && (int) ($_POST['beyan_metin_surumu'] ?? 0) !== \App\Service\BordroYayinIcerikService::METIN_SURUMU) throw new \DomainException('Beyan metni güncellendi. Dökümü yeniden açın.');
    if ($islem === 'beyan' && ($_POST['okudum'] ?? '') !== '1') throw new \DomainException('Okuma beyanı kutusunu işaretleyin.');
    $kanit = $islem === 'beyan' ? [
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'session_hash' => hash('sha256', session_id()),
    ] : ($islem === 'talep' ? ['konu' => (string) ($_POST['konu'] ?? 'diger')] : []);
    $talepId = $model->personelIslem($firma, $id, (int) $personel_id, $islem, (string) ($_POST['mesaj'] ?? ''), $kanit);
    if ($islem === 'talep' && isset($_FILES['dosya']) && $_FILES['dosya']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['dosya'];
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new \DomainException('İtiraz eki yüklenemedi.');
        if ((int) $file['size'] > 10 * 1024 * 1024) throw new \DomainException('İtiraz eki en fazla 10 MB olabilir.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $izinli = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($izinli[$mime])) throw new \DomainException('Yalnız PDF, JPG, PNG veya WebP dosyası yüklenebilir.');
        $binary = file_get_contents($file['tmp_name']);
        if ($binary === false) throw new \DomainException('İtiraz eki okunamadı.');
        $dir = dirname(__DIR__, 2) . '/uploads/bordro-itiraz/';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('İtiraz eki dizini oluşturulamadı.');
        $stored = bin2hex(random_bytes(20)) . '.enc';
        if (file_put_contents($dir . $stored, \App\Helper\Security::encryptFile($binary), LOCK_EX) === false) throw new \RuntimeException('İtiraz eki güvenli biçimde kaydedilemedi.');
        try {
            $model->talepEkiKaydet($firma, (int) $talepId, (int) $personel_id, ['adi'=>mb_substr(basename((string) $file['name']),0,255),'yol'=>'uploads/bordro-itiraz/'.$stored,'mime'=>$mime,'boyut'=>(int)$file['size']]);
        } catch (\Throwable $e) { @unlink($dir . $stored); throw $e; }
    }
    if ($islem === 'talep') {
        try {
            (new \App\Model\BildirimModel())->broadcastByPermissionForFirm(
                $firma, 'bordro/list', 'Yeni ücret pusulası itirazı',
                (string) $personel->adi_soyadi . ' ücret pusulası için itiraz bildirdi.',
                'index.php?p=bordro/list', 'message-square-error', 'danger',
                \App\Model\UserNotificationPreferenceModel::TYPE_PAYROLL
            );
        } catch (\Throwable $bildirimHatasi) {
            error_log('Bordro itiraz yönetici bildirimi: ' . $bildirimHatasi->getMessage());
        }
    }
    response(true, $model->detay($firma, $id, (int) $personel_id), 'İşleminiz kaydedildi.');
} catch (\DomainException $e) {
    response(false, null, $e->getMessage());
} catch (\Throwable $e) {
    error_log('Bordro yayın PWA: ' . $e->getMessage());
    http_response_code(500);
    response(false, null, 'Bordro işlemi tamamlanamadı. Lütfen yeniden deneyin.');
}
exit;
