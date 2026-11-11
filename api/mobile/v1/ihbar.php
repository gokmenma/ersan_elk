<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\MedyaServis;
use App\Helper\Security;
use App\Model\IhbarModel;
use App\Model\PwaTransferModel;
use App\Service\IhbarBildirimService;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? 1);

if (!IhbarBildirimService::yetkiliMi($personel)) {
    mobile_fail('İhbar işlemleri için yetkiniz bulunmuyor.', 403, ['code' => 'FORBIDDEN', 'transfer_error' => 'permission']);
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['personel_id'] = $personelId;
$_SESSION['firma_id'] = $firmaId;

$hesapAnahtari = hash('sha256', 'pwa:' . $firmaId . ':' . $personelId);
$ihbarModel = new IhbarModel();
$servis = new IhbarBildirimService($ihbarModel);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = mobile_input();
$action = (string) ($input['action'] ?? '');

$durumMap = [
    'yeni' => 'Yeni',
    'yonlendirildi' => 'Yönlendirildi',
    'islemde' => 'İşlemde',
    'olumlu' => 'Olumlu',
    'olumsuz' => 'Olumsuz',
];

$medyaYolu = static fn(int $id, bool $kucuk = false): string => '/ihbar.php?action=photo' . ($kucuk ? '&boyut=kucuk' : '') . '&photo_id=' . $id;

if ($method === 'GET' && $action === 'photo') {
    $medya = $ihbarModel->getPersonelMedyasi((int) ($input['photo_id'] ?? 0), $personelId, $firmaId);
    if (!$medya) {
        mobile_fail('Belge bulunamadı veya erişim yetkiniz yok.', 404);
    }
    $kok = realpath(PROJECT_ROOT . '/' . IhbarModel::UPLOAD_DIR);
    $adaylar = ($input['boyut'] ?? '') === 'kucuk' && !empty($medya['kucuk_yol'])
        ? [$medya['kucuk_yol'], $medya['dosya_yolu']]
        : [$medya['dosya_yolu']];
    foreach ($adaylar as $aday) {
        $dosya = realpath(PROJECT_ROOT . '/' . ltrim((string) $aday, '/'));
        if ($kok && $dosya && str_starts_with($dosya, $kok . DIRECTORY_SEPARATOR) && is_file($dosya)) {
            MedyaServis::gonder($dosya, 86400);
            exit;
        }
    }
    mobile_fail('Dosya sunucuda bulunamadı.', 404);
}

if (in_array($_GET['action'] ?? '', ['transfer_create', 'transfer_update', 'transfer_photo'], true) && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    mobile_fail('Gönderilen dosya sunucu yükleme sınırını aşıyor.', 413, ['transfer_error' => 'validation']);
}

if ($method === 'POST' && in_array($action, ['transfer_create', 'transfer_update', 'transfer_photo'], true)) {
    if (!hash_equals($hesapAnahtari, (string) ($input['hesap_anahtari'] ?? ''))) {
        mobile_fail('Gönderim hesabı değişmiş. Kaydı oluşturan hesapla giriş yapın.', 403, ['transfer_error' => 'account']);
    }
    $islemAnahtari = (string) ($input['operation_key'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $islemAnahtari)) {
        mobile_fail('Geçersiz işlem anahtarı.', 422, ['transfer_error' => 'validation']);
    }

    $transfer = new PwaTransferModel($firmaId, $personelId);
    $onbellektenDon = static function (?array $onbellek) use ($transfer): void {
        if ($onbellek) {
            $transfer->getDb()->rollBack();
            mobile_ok(($onbellek['data'] ?? []) + ['message' => (string) ($onbellek['message'] ?? '')]);
        }
    };
    $tamamla = static function (array $veri, string $mesaj) use ($transfer): void {
        $transfer->finish(['success' => true, 'data' => $veri, 'message' => $mesaj]);
    };

    try {
        if ($action === 'transfer_create') {
            $onbellektenDon($transfer->begin($islemAnahtari, 'mobileIhbarCreate'));
            $veri = $servis->dogrula($input);
            $sonuc = $servis->olustur($veri, $personelId, $_FILES['foto'] ?? []);
            $yanit = ['target_token' => Security::encrypt($sonuc['id'])];
            $tamamla($yanit, 'İhbarınız kaydedildi.');
            $servis->bilgilendir($sonuc['id'], $sonuc['atanan_personel_id'], $firmaId, (string) ($personel->adi_soyadi ?? ''));
            mobile_ok($yanit + ['message' => 'İhbarınız kaydedildi.']);
        }

        if ($action === 'transfer_update') {
            $kayitId = (int) ($input['kayit_id'] ?? 0);
            $onbellektenDon($transfer->begin($islemAnahtari, 'mobileIhbarUpdate'));
            $servis->duzenlenebilirKayit($kayitId, $personelId, $firmaId);
            $transfer->lockRecord('ihbar', $kayitId);
            $servis->duzenlenebilirKayit($kayitId, $personelId, $firmaId);
            $servis->guncelle($kayitId, $personelId, $servis->dogrula($input));
            $yanit = ['target_token' => Security::encrypt($kayitId)];
            $tamamla($yanit, 'İhbar güncellendi.');
            mobile_ok($yanit + ['message' => 'İhbar güncellendi.']);
        }

        $hedefId = (int) Security::decrypt((string) ($input['target_token'] ?? ''));
        $transferAnahtari = (string) ($input['transfer_key'] ?? '');
        $anaMakbuz = $transfer->receipt($transferAnahtari, 'mobileIhbarCreate') ?? $transfer->receipt($transferAnahtari, 'mobileIhbarUpdate');
        if (!$anaMakbuz || $hedefId <= 0 || (int) Security::decrypt((string) ($anaMakbuz['data']['target_token'] ?? '')) !== $hedefId) {
            mobile_fail('Dosya gönderimi bu ihbara ait değil.', 403, ['transfer_error' => 'permission']);
        }
        if (!$servis->kayitSahibiMi($hedefId, $personelId, $firmaId)) {
            mobile_fail('Bu ihbara dosya ekleme yetkiniz yok.', 403, ['transfer_error' => 'permission']);
        }
        $onbellektenDon($transfer->begin($islemAnahtari, 'mobileIhbarPhoto'));
        $transfer->lockRecord('ihbar', $hedefId);
        $servis->fotografEkle($hedefId, $_FILES['foto'] ?? []);
        $yanit = ['target_token' => Security::encrypt($hedefId)];
        $tamamla($yanit, 'Fotoğraf kaydedildi.');
        mobile_ok($yanit + ['message' => 'Fotoğraf kaydedildi.']);
    } catch (DomainException $e) {
        $transfer->finish(['success' => false]);
        mobile_fail($e->getMessage(), 422, ['transfer_error' => 'validation']);
    } catch (Throwable $e) {
        $transfer->finish(['success' => false]);
        error_log('Mobil ihbar gönderimi action=' . $action . ' personel=' . $personelId . ' hata=' . get_class($e) . ': ' . $e->getMessage());
        mobile_fail('Sunucu gönderimi tamamlayamadı; ihbar cihazda korunuyor.', 503, ['transfer_error' => 'temporary']);
    }
}

if ($method === 'POST' && $action === 'add_note') {
    $id = (int) ($input['id'] ?? 0);
    $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));
    if ($aciklama === '') {
        mobile_fail('Lütfen not içeriğini yazınız.', 422);
    }
    if (!$ihbarModel->personelErisebilirMi($id, $personelId, $firmaId)) {
        mobile_fail('Bu ihbara not ekleme yetkiniz bulunmuyor.', 403);
    }
    try {
        $ihbarModel->addNote($id, mb_substr($aciklama, 0, 1000), 'personel', $personelId);
    } catch (Throwable $e) {
        error_log('Mobil ihbar notu eklenemedi ihbar=' . $id . ': ' . $e->getMessage());
        mobile_fail('Not kaydedilemedi. Lütfen tekrar deneyin.', 500);
    }
    mobile_ok(['message' => 'Not eklendi.']);
}

if ($method === 'POST' && $action === 'close') {
    $id = (int) ($input['id'] ?? 0);
    $durum = trim((string) ($input['durum'] ?? ''));
    $tutanakNo = trim((string) ($input['tutanak_no'] ?? ''));
    $sebep = trim(strip_tags((string) ($input['sebep'] ?? '')));

    if (!in_array($durum, ['olumlu', 'olumsuz'], true)) {
        mobile_fail('Lütfen sonucu seçiniz.', 422);
    }
    if ($durum === 'olumlu' && $tutanakNo === '') {
        mobile_fail('Olumlu sonuç için tutanak numarası girilmelidir.', 422);
    }
    if ($durum === 'olumsuz' && $sebep === '') {
        mobile_fail('Olumsuz sonuç için sebep yazılmalıdır.', 422);
    }
    if (!$ihbarModel->personelErisebilirMi($id, $personelId, $firmaId) || !in_array($personelId, $ihbarModel->getAtananPersonelIds($id), true)) {
        mobile_fail('Bu ihbarı yalnızca yönlendirilen personel sonuçlandırabilir.', 403);
    }
    try {
        $ihbarModel->closeSonuc($id, $durum, $tutanakNo ?: null, $sebep ?: null, 'personel', $personelId);
    } catch (Throwable $e) {
        error_log('Mobil ihbar sonuçlandırılamadı ihbar=' . $id . ': ' . $e->getMessage());
        mobile_fail('İhbar sonuçlandırılamadı. Lütfen tekrar deneyin.', 500);
    }
    mobile_ok(['message' => 'İhbar sonuçlandırıldı.']);
}

if ($method === 'POST') {
    mobile_fail('Geçersiz işlem.', 404);
}

$detayId = (int) ($input['id'] ?? 0);
if ($detayId > 0) {
    if (!$ihbarModel->personelErisebilirMi($detayId, $personelId, $firmaId)) {
        mobile_fail('İhbar bulunamadı veya erişim yetkiniz yok.', 404);
    }
    $ihbar = $ihbarModel->getById($detayId);
    $atananIds = $ihbarModel->getAtananPersonelIds($detayId);
    $sonuclandi = in_array($ihbar->durum, ['olumlu', 'olumsuz'], true);

    mobile_ok([
        'ihbar' => [
            'id' => (int) $ihbar->id,
            'ihbar_turu' => (string) ($ihbar->ihbar_turu ?? ''),
            'ilce' => (string) ($ihbar->ilce ?? ''),
            'mahalle' => (string) ($ihbar->mahalle ?? ''),
            'telefon' => (string) ($ihbar->telefon ?? ''),
            'komsu_abone_no' => (string) ($ihbar->komsu_abone_no ?? ''),
            'aciklama' => (string) ($ihbar->aciklama ?? ''),
            'konum_lat' => $ihbar->konum_lat !== null ? (float) $ihbar->konum_lat : null,
            'konum_lng' => $ihbar->konum_lng !== null ? (float) $ihbar->konum_lng : null,
            'konum_dogruluk' => $ihbar->konum_dogruluk !== null ? (float) $ihbar->konum_dogruluk : null,
            'durum' => $ihbar->durum,
            'durum_text' => $durumMap[$ihbar->durum] ?? ucfirst((string) $ihbar->durum),
            'tutanak_no' => $ihbar->tutanak_no ?? null,
            'olumsuz_sebep' => $ihbar->olumsuz_sebep ?? null,
            'bildiren_personel_adi' => (string) ($ihbar->bildiren_personel_adi ?? ''),
            'tarih' => !empty($ihbar->created_at) ? date('Y-m-d H:i:s', strtotime($ihbar->created_at)) : '',
            'duzenlenebilir' => (int) $ihbar->bildiren_personel_id === $personelId && !$sonuclandi,
            'atanan_mi' => in_array($personelId, $atananIds, true),
            'sonuclandirilabilir' => in_array($personelId, $atananIds, true) && !$sonuclandi,
            'atanan_ekip' => array_map(static fn($a) => ['personel_id' => (int) $a->personel_id, 'adi_soyadi' => $a->adi_soyadi], $ihbar->atanan_ekip ?? []),
            'fotograflar' => array_map(static fn($f) => [
                'id' => (int) $f->id,
                'medya_tipi' => $f->medya_tipi ?? 'foto',
                'sure_saniye' => $f->sure_saniye !== null ? (int) $f->sure_saniye : null,
                'url' => $medyaYolu((int) $f->id),
                'kucuk_url' => $medyaYolu((int) $f->id, true),
            ], $ihbar->fotograflar ?? []),
            'tarihce' => array_map(static fn($t) => [
                'id' => (int) $t->id,
                'tip' => $t->tip,
                'aciklama' => $t->aciklama,
                'ekleyen_adi' => $t->ekleyen_adi ?? 'Sistem',
                'tarih' => !empty($t->created_at) ? date('Y-m-d H:i:s', strtotime($t->created_at)) : '',
            ], $ihbar->tarihce ?? []),
        ],
    ]);
}

$bicimle = static function ($item, bool $benim) use ($durumMap): array {
    $durum = (string) ($item->durum ?? 'yeni');
    return [
        'id' => (int) $item->id,
        'ihbar_turu' => (string) ($item->ihbar_turu ?? ''),
        'ilce' => (string) ($item->ilce ?? ''),
        'mahalle' => (string) ($item->mahalle ?? ''),
        'aciklama' => (string) ($item->aciklama ?? ''),
        'durum' => $durum,
        'durum_text' => $durumMap[$durum] ?? ucfirst($durum),
        'atanan_ekip_adi' => $item->atanan_ekip_adi ?? null,
        'tarih' => !empty($item->created_at) ? date('Y-m-d H:i:s', strtotime($item->created_at)) : '',
        'duzenlenebilir' => $benim && !in_array($durum, ['olumlu', 'olumsuz'], true),
        'konum_lat' => $item->konum_lat !== null ? (float) $item->konum_lat : null,
        'konum_lng' => $item->konum_lng !== null ? (float) $item->konum_lng : null,
    ];
};

$gelen = array_map(static fn($i) => $bicimle($i, false), $ihbarModel->getPersoneleAtananIhbarlar($personelId));
$bildirdiklerim = array_map(static fn($i) => $bicimle($i, true), $ihbarModel->getPersonelinIhbarlari($personelId));

mobile_ok([
    'hesap_anahtari' => $hesapAnahtari,
    'gelen' => $gelen,
    'bildirdiklerim' => $bildirdiklerim,
    'ilceler' => IhbarModel::ILCELER,
    'turler' => IhbarModel::IHBAR_TURLERI,
    'limits' => [
        'max_foto' => IhbarModel::MAX_FOTO,
    ],
]);
