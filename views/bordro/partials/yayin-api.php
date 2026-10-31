<?php
// Yalnız views/bordro/api.php içindeki oturum ve menü yetki kontrolünden sonra çağrılır.
if (!isset($userId, $hasBordroAccess) || !$hasBordroAccess || $userId <= 0) { http_response_code(403); exit; }
\App\Helper\BordroYayinGuvenlik::csrfDogrula();
$yayinModel = new \App\Model\BordroYayinModel();
$firma = (int) ($_SESSION['firma_id'] ?? 0);
if ($firma <= 0 || !$MenuModel->userCanAccessMenuLink($userId, 'bordro/list')) throw new \DomainException('Bordro yönetim yetkisi bulunamadı.');
$donem = in_array($action, ['yayin-onizle', 'yayin-yayinla', 'yayin-test-yayinla', 'yayin-takip'], true)
    ? \App\Helper\BordroYayinGuvenlik::id($_POST['donem_token'] ?? null) : 0;
$data = match ($action) {
    'yayin-onizle' => $yayinModel->onizle($firma, $donem),
    'yayin-yayinla' => $yayinModel->yayinla($firma, $donem, $userId, (string) ($_POST['onizleme_hash'] ?? '')),
    'yayin-test-yayinla' => $yayinModel->testYayinla($firma, $donem, $userId, (string) ($_POST['onizleme_hash'] ?? ''), 
        \App\Helper\BordroYayinGuvenlik::id($_POST['personel_token'] ?? null)),
    'yayin-takip' => $yayinModel->takip($firma, $donem),
    'yayin-detay' => $yayinModel->detay($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['token'] ?? null)),
    'yayin-yanitla' => $yayinModel->yanitla($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['talep_token'] ?? null), $userId, (string) ($_POST['mesaj'] ?? '')),
    'yayin-sil' => $yayinModel->yayinSil($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['token'] ?? null), $userId),
    default => throw new \DomainException('Geçersiz yayın işlemi.'),
};
if ($action === 'yayin-yanitla' && is_array($data) && !empty($data['personel_id'])) {
    try {
        (new \App\Service\PushNotificationService())->sendToPersonel((int) $data['personel_id'], [
            'title' => 'Ücret pusulası itirazınız sonuçlandırıldı',
            'body' => 'Yetkili yanıtını görüntülemek için dokunun.',
            'url' => '?page=bordro&dokum=' . rawurlencode((string) $data['dokum_token']),
            'tag' => 'bordro-itiraz-' . (int) $data['personel_id'],
        ], true);
    } catch (\Throwable $bildirimHatasi) {
        error_log('Bordro itiraz sonuç bildirimi: ' . $bildirimHatasi->getMessage());
    }
}
if ($action === 'yayin-sil') {
    $SystemLog->logAction($userId, 'Bordro Yayını', 'Onaylanmamış yayın silindi: ' . ($data['personel'] ?? '') . ' (' . ($data['pusula_no'] ?? '') . ')', \App\Model\SystemLogModel::LEVEL_IMPORTANT);
    $data = ['silindi' => true];
}
// Önizleme yanıtında da ham kayıt/personel ID taşınmaz.
if ($action === 'yayin-onizle') {
    $data['kayitlar'] = array_map(static fn($r) => ['secim_token' => \App\Helper\Security::encrypt((int) $r['personel_id'])] + $r['icerik'], $data['kayitlar']);
}
echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
exit;
