<?php
// Yalnız views/bordro/api.php içindeki oturum ve menü yetki kontrolünden sonra çağrılır.
if (!isset($userId, $hasBordroAccess) || !$hasBordroAccess || $userId <= 0) { http_response_code(403); exit; }
\App\Helper\BordroYayinGuvenlik::csrfDogrula();
$yayinModel = new \App\Model\BordroYayinModel();
$firma = (int) ($_SESSION['firma_id'] ?? 0);
if ($firma <= 0 || !$MenuModel->userCanAccessMenuLink($userId, 'bordro/list')) throw new \DomainException('Bordro yönetim yetkisi bulunamadı.');
$donem = in_array($action, ['yayin-onizle', 'yayin-yayinla', 'yayin-test-yayinla', 'yayin-takip', 'yayin-revizyon-onizle', 'yayin-revizyon-uygula'], true)
    ? \App\Helper\BordroYayinGuvenlik::id($_POST['donem_token'] ?? null) : 0;
$yayinYetkiAnahtari = match ($action) {
    'yayin-onay-geri-al' => 'bordro_onay_geri_al',
    'yayin-revizyon-onizle', 'yayin-revizyon-uygula' => 'bordro_toplu_revizyon',
    default => null,
};
if ($yayinYetkiAnahtari !== null && !\App\Service\Gate::allows($yayinYetkiAnahtari)) {
    http_response_code(403);
    throw new \DomainException('Bu işlem için yetkiniz bulunmuyor.');
}
$revizyonDokumleri = static function (): array {
    $tokenlar = $_POST['dokum_tokens'] ?? [];
    if (!is_array($tokenlar)) throw new \DomainException('Geçersiz personel seçimi.');
    if (count($tokenlar) > 1000) throw new \DomainException('Çok fazla personel seçildi.');
    return array_values(array_unique(array_map(static fn($t) => \App\Helper\BordroYayinGuvenlik::id($t), $tokenlar)));
};
$yayinKullaniciAdi = (string) ($_SESSION['user_full_name'] ?? ($_SESSION['user']->adi_soyadi ?? 'Sistem'));
$data = match ($action) {
    'yayin-onizle' => $yayinModel->onizle($firma, $donem),
    'yayin-yayinla' => $yayinModel->yayinla($firma, $donem, $userId, (string) ($_POST['onizleme_hash'] ?? '')),
    'yayin-test-yayinla' => $yayinModel->testYayinla($firma, $donem, $userId, (string) ($_POST['onizleme_hash'] ?? ''), 
        \App\Helper\BordroYayinGuvenlik::id($_POST['personel_token'] ?? null)),
    'yayin-takip' => $yayinModel->takip($firma, $donem),
    'yayin-detay' => $yayinModel->detay($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['token'] ?? null)),
    'yayin-yanitla' => $yayinModel->yanitla($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['talep_token'] ?? null), $userId, (string) ($_POST['mesaj'] ?? '')),
    'yayin-sil' => $yayinModel->yayinSil($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['token'] ?? null), $userId),
    'yayin-onay-geri-al' => $yayinModel->onayGeriAl($firma, \App\Helper\BordroYayinGuvenlik::id($_POST['token'] ?? null), $userId, (string) ($_POST['gerekce'] ?? '')),
    'yayin-revizyon-onizle' => $yayinModel->revizyonOnizle($firma, $donem, $revizyonDokumleri(), $userId, $yayinKullaniciAdi),
    'yayin-revizyon-uygula' => $yayinModel->revizyonUygula($firma, $donem, $revizyonDokumleri(), $userId, $yayinKullaniciAdi, (string) ($_POST['gerekce'] ?? ''), (string) ($_POST['onizleme_hash'] ?? ''), (int) ($_POST['onay_sayisi'] ?? -1)),
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
if ($action === 'yayin-onay-geri-al') {
    $SystemLog->logAction($userId, 'Bordro Onayı Geri Alındı', ($data['personel'] ?? '') . ' (' . ($data['pusula_no'] ?? '') . ') onayı geri alındı. Gerekçe: ' . trim((string) ($_POST['gerekce'] ?? '')), \App\Model\SystemLogModel::LEVEL_IMPORTANT);
    $data = ['personel' => $data['personel']];
}
if ($action === 'yayin-revizyon-uygula') {
    try {
        $donemKaydi = (new \App\Model\BordroDonemModel())->getDonemById($donem);
        if ($donemKaydi) {
            (new \App\Model\PuantajMutabakatModel())->refreshRecalculationState($firma, $donem, substr((string) $donemKaydi->baslangic_tarihi, 0, 7));
        }
    } catch (\Throwable $mutabakatHatasi) {
        error_log('Toplu revizyon mutabakat yenileme: ' . $mutabakatHatasi->getMessage());
    }
    $o = $data['ozet'];
    $SystemLog->logAction($userId, 'Bordro Toplu Revizyon', "Dönem #$donem toplu revizyon #{$data['revizyon_id']}: incelenen {$o['incelenen']}, onayı kaldırılan {$o['revize']}, onay korunarak iç hesabı güncellenen {$o['ic_guncellenen']}, değişmeyen {$o['korunan']}, hatalı {$o['hata']}. Gerekçe: " . trim((string) ($_POST['gerekce'] ?? '')), \App\Model\SystemLogModel::LEVEL_IMPORTANT);
}
if ($action === 'yayin-revizyon-onizle') {
    $o = $data['ozet'];
    $SystemLog->logAction($userId, 'Bordro Toplu Revizyon Önizleme', "Dönem #$donem: incelenen {$o['incelenen']}, değişecek {$o['revize']}, iç hesap {$o['ic_guncellenen']}, değişmeyen {$o['korunan']}, hatalı {$o['hata']}.", \App\Model\SystemLogModel::LEVEL_INFO);
}
if (in_array($action, ['yayin-revizyon-onizle', 'yayin-revizyon-uygula'], true)) {
    $data['satirlar'] = array_map(static function (array $s): array {
        $s['dokum_token'] = \App\Helper\Security::encrypt((int) $s['dokum_id']);
        unset($s['bordro_personel_id'], $s['personel_id'], $s['dokum_id'], $s['parmak_izi'], $s['ic_ozet']);
        return $s;
    }, $data['satirlar']);
    unset($data['revizyon_id']);
}
// Önizleme yanıtında da ham kayıt/personel ID taşınmaz.
if ($action === 'yayin-onizle') {
    $data['kayitlar'] = array_map(static fn($r) => ['secim_token' => \App\Helper\Security::encrypt((int) $r['personel_id']), 'yayin_durumu' => $r['yayin_durumu']] + $r['icerik'], $data['kayitlar']);
}
echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
exit;
