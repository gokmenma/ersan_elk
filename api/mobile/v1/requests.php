<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\TalepModel;
use App\Model\PersonelModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Service\PushNotificationService;
use App\Model\UserNotificationPreferenceModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$model = new TalepModel();
$input = mobile_input();

$kategoriMap = [
    'ariza' => 'Arıza Bildirimi',
    'oneri' => 'Öneri',
    'sikayet' => 'Şikayet',
    'istek' => 'İstek',
    'diger' => 'Diğer Talep',
];

$oncelikMap = [
    'dusuk' => 'Düşük',
    'orta' => 'Orta',
    'yuksek' => 'Yüksek',
];

$durumMap = [
    'beklemede' => 'Beklemede',
    'devam' => 'İnceleniyor',
    'cozuldu' => 'Çözüldü',
    'iptal edildi' => 'İptal Edildi',
    'reddedildi' => 'Reddedildi',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($input['action'] ?? '');

    if ($action === 'create') {
        $kategori = trim((string) ($input['kategori'] ?? ''));
        $oncelik = trim((string) ($input['oncelik'] ?? 'orta'));
        $konum = trim((string) ($input['konum'] ?? ''));
        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));
        $baslikInput = trim((string) ($input['baslik'] ?? ''));
        $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? (string) $input['latitude'] : null;
        $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (string) $input['longitude'] : null;

        if (($latitude === null) !== ($longitude === null)) {
            mobile_fail('Konum koordinatları eksik gönderildi.', 422);
        }
        if ($latitude !== null && (
            !is_numeric($latitude) || !is_numeric($longitude)
            || (float) $latitude < -90 || (float) $latitude > 90
            || (float) $longitude < -180 || (float) $longitude > 180
        )) {
            mobile_fail('Geçersiz konum koordinatı.', 422);
        }

        if (empty($kategori) || !isset($kategoriMap[$kategori])) {
            mobile_fail('Lütfen geçerli bir talep kategorisi seçiniz.', 422);
        }

        if (!isset($oncelikMap[$oncelik])) {
            $oncelik = 'orta';
        }

        if (empty($konum)) {
            mobile_fail('Lütfen konum veya birim bilgisi giriniz.', 422);
        }

        if (empty($aciklama)) {
            mobile_fail('Lütfen talep açıklamasını belirtiniz.', 422);
        }

        $baslik = !empty($baslikInput) ? $baslikInput : ($kategoriMap[$kategori] ?? 'Talep');
        $refNo = $model->generateRefNo();

        $stmt = $model->getDb()->prepare("
            INSERT INTO personel_talepleri 
            (personel_id, ref_no, konum, latitude, longitude, kategori, oncelik, baslik, aciklama, durum, olusturma_tarihi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'beklemede', NOW())
        ");
        $stmt->execute([
            $personelId,
            $refNo,
            $konum,
            $latitude,
            $longitude,
            $kategori,
            $oncelik,
            $baslik,
            $aciklama,
        ]);

        $newId = (int) $model->getDb()->lastInsertId();
        (new \App\Model\SystemLogModel())->logPersonelTalep($personelId, 'Genel Talep Oluşturma', $baslik . ' talebi oluşturuldu (Mobil)', $newId);

        // Bildirim süreci (sessiz hata yakalama)
        try {
            $userModel = new UserModel();
            $personelModel = new PersonelModel();
            $talepEden = $personelModel->find($personelId);
            $bildirimTuru = ($kategori === 'ariza') ? 'ariza' : 'genel';

            $bildirimKullanicilari = $userModel->getInAppBildirimKullanicilari($bildirimTuru);
            foreach ($bildirimKullanicilari as $kullanici) {
                try {
                    $bildirimModel = new BildirimModel();
                    $prefType = $bildirimTuru === 'ariza'
                        ? UserNotificationPreferenceModel::TYPE_FAULT_REQUEST
                        : UserNotificationPreferenceModel::TYPE_GENERAL_REQUEST;

                    $bildirimModel->createNotification(
                        $kullanici->id,
                        "Yeni {$baslik}",
                        ($talepEden->adi_soyadi ?? 'Personel') . " yeni bir talep oluşturdu: {$baslik}",
                        'index.php?p=talepler/list&tab=talep&id=' . $newId,
                        'message-square',
                        'info',
                        $prefType
                    );

                    try {
                        $pushService = new PushNotificationService();
                        $pushService->sendToUser($kullanici->id, [
                            'title' => "📣 Yeni {$baslik}",
                            'body' => ($talepEden->adi_soyadi ?? 'Personel') . " yeni bir talep oluşturdu.",
                            'url' => \App\Helper\MobileSpa::url('/talepler'),
                        ], false, $prefType);
                    } catch (\Exception $pe) {
                        // ignore push err
                    }
                } catch (\Exception $be) {
                    // ignore user notif err
                }
            }
        } catch (\Exception $e) {
            error_log('Mobile Talep Bildirim Hatasi: ' . $e->getMessage());
        }

        mobile_ok([
            'message' => 'Talebiniz başarıyla oluşturuldu.',
            'ref_no' => $refNo,
            'id' => $newId,
        ]);
    }

    if ($action === 'cancel') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            mobile_fail('Geçersiz talep numarası.', 422);
        }

        $stmt = $model->getDb()->prepare("
            UPDATE personel_talepleri 
            SET durum = 'iptal edildi', cozum_aciklama = 'Personel tarafından iptal edildi'
            WHERE id = ? AND personel_id = ? AND durum = 'beklemede' AND silinme_tarihi IS NULL
        ");
        $stmt->execute([$id, $personelId]);

        if ($stmt->rowCount() <= 0) {
            mobile_fail('Yalnızca beklemedeki talepler iptal edilebilir.', 409);
        }

        (new \App\Model\SystemLogModel())->logPersonelTalep($personelId, 'Genel Talep İptal', 'Talep personel tarafından iptal edildi (Mobil)', $id);
        mobile_ok(['message' => 'Talep iptal edildi.']);
    }

    mobile_fail('Geçersiz işlem.', 404);
}

// GET - Talepleri ve İstatistikleri Getir
$taleplerRaw = $model->getPersonelTalepleri($personelId);
$statsRaw = $model->getStats($personelId);

$items = [];
foreach ($taleplerRaw as $item) {
    $durum = mb_strtolower((string) ($item->durum ?? 'beklemede'), 'UTF-8');
    if (str_contains($durum, 'iptal')) {
        $durum = 'iptal edildi';
    }

    $items[] = [
        'id' => (int) $item->id,
        'ref_no' => $item->ref_no,
        'baslik' => $item->baslik ?: ($kategoriMap[$item->kategori] ?? 'Talep'),
        'kategori' => $item->kategori,
        'kategori_text' => $kategoriMap[$item->kategori] ?? $item->kategori,
        'konum' => $item->konum,
        'oncelik' => $item->oncelik ?? 'orta',
        'oncelik_text' => $oncelikMap[$item->oncelik ?? 'orta'] ?? 'Orta',
        'durum' => $durum,
        'durum_text' => $durumMap[$durum] ?? ucfirst($durum),
        'tarih' => !empty($item->olusturma_tarihi) ? date('d.m.Y H:i', strtotime($item->olusturma_tarihi)) : '',
        'aciklama' => $item->aciklama ?? '',
        'cozum_aciklama' => $item->cozum_aciklama ?? null,
        'cozum_tarihi' => !empty($item->cozum_tarihi) ? date('d.m.Y H:i', strtotime($item->cozum_tarihi)) : null,
        'foto_url' => !empty($item->foto) ? mobile_asset_url($item->foto) : null,
    ];
}

$kategoriler = [];
foreach ($kategoriMap as $k => $v) {
    $kategoriler[] = ['key' => $k, 'title' => $v];
}

$oncelikler = [
    ['key' => 'dusuk', 'title' => 'Düşük'],
    ['key' => 'orta', 'title' => 'Orta'],
    ['key' => 'yuksek', 'title' => 'Yüksek'],
];

mobile_ok([
    'stats' => [
        'acik' => (int) ($statsRaw->acik ?? 0),
        'cozulen' => (int) ($statsRaw->cozulen ?? 0),
        'ort_sure' => (float) ($statsRaw->ort_sure ?? 0),
    ],
    'kategoriler' => $kategoriler,
    'oncelikler' => $oncelikler,
    'talepler' => $items,
]);
