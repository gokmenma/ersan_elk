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
    $model->personelIslem($firma, $id, (int) $personel_id, $islem, (string) ($_POST['mesaj'] ?? ''));
    response(true, $model->detay($firma, $id, (int) $personel_id), 'İşleminiz kaydedildi.');
} catch (\DomainException $e) {
    response(false, null, $e->getMessage());
} catch (\Throwable $e) {
    error_log('Bordro yayın PWA: ' . $e->getMessage());
    http_response_code(500);
    response(false, null, 'Bordro işlemi tamamlanamadı. Lütfen yeniden deneyin.');
}
exit;
