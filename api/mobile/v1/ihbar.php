<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\IhbarModel;
use App\Model\PersonelModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Model\PersonelHareketleriModel;
use App\Model\UserNotificationPreferenceModel;
use App\Service\PushNotificationService;
use App\Helper\Security;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? 1);
$ihbarModel = new IhbarModel();
$input = mobile_input();

$durumMap = [
    'yeni' => 'Yeni',
    'yonlendirildi' => 'Yönlendirildi',
    'islemde' => 'İşlemde',
    'olumlu' => 'Olumlu',
    'olumsuz' => 'Olumsuz',
];

$ilceler = [
    'Afşin',
    'Andırın',
    'Çağlayancerit',
    'Dulkadiroğlu',
    'Ekinözü',
    'Elbistan',
    'Göksun',
    'Nurhak',
    'Onikişubat',
    'Pazarcık',
    'Türkoğlu',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($input['action'] ?? '');

    if ($action === 'create') {
        $ilce = trim((string) ($input['ilce'] ?? ''));
        if ($ilce === '') {
            mobile_fail('İlçe seçimi zorunludur.', 422);
        }

        $mahalle = trim((string) ($input['mahalle'] ?? ''));
        if ($mahalle === '') {
            mobile_fail('Mahalle alanı zorunludur.', 422);
        }

        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));
        if ($aciklama === '') {
            mobile_fail('İhbar açıklaması zorunludur.', 422);
        }

        $telefon = trim((string) ($input['telefon'] ?? ''));
        $komsuAboneNo = trim((string) ($input['komsu_abone_no'] ?? ''));
        $konumLink = trim((string) ($input['konum_link'] ?? ''));

        $lat = isset($input['konum_lat']) && is_numeric($input['konum_lat']) ? (float) $input['konum_lat'] : null;
        $lng = isset($input['konum_lng']) && is_numeric($input['konum_lng']) ? (float) $input['konum_lng'] : null;
        $dogruluk = isset($input['konum_dogruluk']) && is_numeric($input['konum_dogruluk']) ? (float) $input['konum_dogruluk'] : null;

        if (empty($konumLink) && ($lat === null || $lng === null)) {
            mobile_fail('Konum bilgisi zorunludur.', 422);
        }

        $hasFiles = !empty($_FILES['fotograflar']) && !empty($_FILES['fotograflar']['name']);
        $hasBase64 = !empty($input['base64_fotograflar']) && is_array($input['base64_fotograflar']);
        if (!$hasFiles && !$hasBase64) {
            mobile_fail('En az 1 adet fotoğraf yüklenmelidir.', 422);
        }

        if (empty($konumLink) && $lat !== null && $lng !== null) {
            $konumLink = "https://maps.google.com/?q={$lat},{$lng}";
        }

        $ihbarDb = $ihbarModel->getDb();
        $ihbarDb->beginTransaction();

        try {
            $ihbarId = $ihbarModel->create([
                'ilce' => $ilce,
                'mahalle' => $mahalle,
                'telefon' => $telefon ?: null,
                'komsu_abone_no' => $komsuAboneNo ?: null,
                'aciklama' => $aciklama,
                'konum_link' => $konumLink ?: null,
                'konum_lat' => $lat,
                'konum_lng' => $lng,
                'konum_dogruluk' => $dogruluk,
                'bildiren_personel_id' => $personelId,
            ]);

            // Görevdeki Kaçak personellerinden güncel konum talep et
            try {
                (new PersonelHareketleriModel())->requestKacakPersonelKonumlari($firmaId);
            } catch (\Throwable $e) {
                // ignore
            }

            // En yakın Kaçak personeline otomatik atama
            $atananPersonelId = null;
            if ($lat !== null && $lng !== null) {
                try {
                    $atananPersonelId = $ihbarModel->autoAssignNearest($ihbarId, $lat, $lng, $personelId);
                } catch (\Throwable $e) {
                    error_log('Mobile İhbar Otomatik Atama Hatası: ' . $e->getMessage());
                }
            }

            // Multipart Dosya Yüklemeleri ($_FILES)
            if (!empty($_FILES['fotograflar']) && !empty($_FILES['fotograflar']['name'])) {
                $fileNames = is_array($_FILES['fotograflar']['name']) ? $_FILES['fotograflar']['name'] : [$_FILES['fotograflar']['name']];
                $tmpNames = is_array($_FILES['fotograflar']['tmp_name']) ? $_FILES['fotograflar']['tmp_name'] : [$_FILES['fotograflar']['tmp_name']];
                $errors = is_array($_FILES['fotograflar']['error']) ? $_FILES['fotograflar']['error'] : [$_FILES['fotograflar']['error']];
                $sizes = is_array($_FILES['fotograflar']['size']) ? $_FILES['fotograflar']['size'] : [$_FILES['fotograflar']['size']];

                $maxFiles = min(count($fileNames), IhbarModel::MAX_FOTO);
                for ($i = 0; $i < $maxFiles; $i++) {
                    if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    try {
                        $fotoSonuc = IhbarModel::storeUploadedFoto([
                            'name' => $fileNames[$i],
                            'tmp_name' => $tmpNames[$i],
                            'error' => $errors[$i],
                            'size' => $sizes[$i],
                        ], $ihbarId);

                        $ihbarModel->addFotograf($ihbarId, $fotoSonuc['yol'], $fotoSonuc['kucuk']);
                    } catch (\Throwable $e) {
                        error_log("Mobile ihbar foto yükleme hatası (ihbar {$ihbarId}): " . $e->getMessage());
                    }
                }
            }

            // Base64 Fotoğraf Yüklemeleri (JSON gövdesinden)
            if (!empty($input['base64_fotograflar']) && is_array($input['base64_fotograflar'])) {
                $altDizin = IhbarModel::UPLOAD_DIR . '/' . date('Y/m');
                $hedefDizin = PROJECT_ROOT . '/' . $altDizin;
                if (!is_dir($hedefDizin) && !mkdir($hedefDizin, 0775, true) && !is_dir($hedefDizin)) {
                    // devam et
                }

                $kalanFoto = max(0, IhbarModel::MAX_FOTO - $ihbarModel->countFotograflar($ihbarId));
                $b64List = array_slice($input['base64_fotograflar'], 0, $kalanFoto);

                foreach ($b64List as $bIndex => $b64Item) {
                    try {
                        $b64Data = is_string($b64Item) ? $b64Item : ($b64Item['data'] ?? '');
                        if (str_contains($b64Data, ',')) {
                            $b64Data = explode(',', $b64Data)[1];
                        }
                        $binary = base64_decode($b64Data, true);
                        if ($binary === false || strlen($binary) < 10) {
                            continue;
                        }

                        $fileName = 'ihbar_' . $ihbarId . '_' . time() . '_' . $bIndex . '.jpg';
                        $fullPath = $hedefDizin . '/' . $fileName;
                        file_put_contents($fullPath, $binary);

                        $relPath = $altDizin . '/' . $fileName;
                        $ihbarModel->addFotograf($ihbarId, $relPath, $relPath);
                    } catch (\Throwable $e) {
                        error_log("Mobile base64 foto hatası: " . $e->getMessage());
                    }
                }
            }

            // Video Yüklemeleri ($_FILES)
            if (!empty($_FILES['videolar']) && !empty($_FILES['videolar']['name'])) {
                $vNames = is_array($_FILES['videolar']['name']) ? $_FILES['videolar']['name'] : [$_FILES['videolar']['name']];
                $vTmps = is_array($_FILES['videolar']['tmp_name']) ? $_FILES['videolar']['tmp_name'] : [$_FILES['videolar']['tmp_name']];
                $vErrors = is_array($_FILES['videolar']['error']) ? $_FILES['videolar']['error'] : [$_FILES['videolar']['error']];
                $vSizes = is_array($_FILES['videolar']['size']) ? $_FILES['videolar']['size'] : [$_FILES['videolar']['size']];
                $vSureler = is_array($input['video_sureleri'] ?? null) ? $input['video_sureleri'] : [];
                $vKapaklar = is_array($input['video_kapaklari'] ?? null) ? $input['video_kapaklari'] : [];

                $maxV = min(count($vNames), IhbarModel::MAX_VIDEO);
                for ($v = 0; $v < $maxV; $v++) {
                    if (($vErrors[$v] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    try {
                        $vSonuc = $ihbarModel->storeUploadedVideo(
                            [
                                'name' => $vNames[$v],
                                'tmp_name' => $vTmps[$v],
                                'error' => $vErrors[$v],
                                'size' => $vSizes[$v],
                            ],
                            $ihbarId,
                            isset($vSureler[$v]) && is_numeric($vSureler[$v]) ? (int) ceil((float) $vSureler[$v]) : null,
                            isset($vKapaklar[$v]) ? (string) $vKapaklar[$v] : null
                        );
                        $ihbarModel->addVideo($ihbarId, $vSonuc['yol'], $vSonuc['kapak'], $vSonuc['sure_saniye']);
                    } catch (\Throwable $e) {
                        error_log("Mobile video yükleme hatası: " . $e->getMessage());
                    }
                }
            }

            $ihbarDb->commit();

            // Bildirim Gönderimi
            try {
                $personelAdi = $personel->adi_soyadi ?? 'Personel';

                // Otomatik atanan personele push bildirim
                if ($atananPersonelId) {
                    try {
                        $pushService = new PushNotificationService();
                        $pushService->sendToPersonel($atananPersonelId, [
                            'title' => '📍 Yeni İhbar Atandı',
                            'body' => 'Konumunuza en yakın yeni kaçak ihbarı size yönlendirildi.',
                            'url' => 'ihbar?id=' . $ihbarId,
                        ]);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }

                // Sistem yöneticilerine bildirim
                $userModel = new UserModel();
                $sorumlular = $userModel->getInAppBildirimKullanicilari('ihbar');
                foreach ($sorumlular as $sorumlu) {
                    try {
                        $bildirimModel = new BildirimModel();
                        $bildirimModel->createNotificationWithPush(
                            (int) $sorumlu->id,
                            '📣 Yeni İhbar',
                            $personelAdi . ' tarafından yeni bir ihbar bildirildi.',
                            'index.php?p=ihbar/list&ihbar_id=' . $ihbarId,
                            'alert-triangle',
                            'danger',
                            UserNotificationPreferenceModel::TYPE_IHBAR_CREATED
                        );
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            } catch (\Throwable $e) {
                error_log('Mobile İhbar Bildirim Gönderim Hatası: ' . $e->getMessage());
            }

            mobile_ok([
                'message' => 'İhbarınız başarıyla kaydedildi.',
                'id' => $ihbarId,
            ]);
        } catch (\Throwable $e) {
            if ($ihbarDb->inTransaction()) {
                $ihbarDb->rollBack();
            }
            mobile_fail($e->getMessage() ?: 'İhbar kaydedilirken hata oluştu.', 500);
        }
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            mobile_fail('Geçersiz ihbar numarası.', 422);
        }

        $ilce = trim((string) ($input['ilce'] ?? ''));
        if ($ilce === '') {
            mobile_fail('İlçe seçimi zorunludur.', 422);
        }

        $mahalle = trim((string) ($input['mahalle'] ?? ''));
        if ($mahalle === '') {
            mobile_fail('Mahalle alanı zorunludur.', 422);
        }

        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));
        if ($aciklama === '') {
            mobile_fail('Açıklama zorunludur.', 422);
        }

        $telefon = trim((string) ($input['telefon'] ?? ''));
        $komsuAboneNo = trim((string) ($input['komsu_abone_no'] ?? ''));
        $konumLink = trim((string) ($input['konum_link'] ?? ''));

        $lat = isset($input['konum_lat']) && is_numeric($input['konum_lat']) ? (float) $input['konum_lat'] : null;
        $lng = isset($input['konum_lng']) && is_numeric($input['konum_lng']) ? (float) $input['konum_lng'] : null;
        $dogruluk = isset($input['konum_dogruluk']) && is_numeric($input['konum_dogruluk']) ? (float) $input['konum_dogruluk'] : null;

        if (empty($konumLink) && ($lat === null || $lng === null)) {
            mobile_fail('Konum bilgisi zorunludur.', 422);
        }

        if (empty($konumLink) && $lat !== null && $lng !== null) {
            $konumLink = "https://maps.google.com/?q={$lat},{$lng}";
        }

        try {
            $ihbarModel->updateByBildiren($id, $personelId, [
                'ilce' => $ilce,
                'mahalle' => $mahalle,
                'telefon' => $telefon ?: null,
                'komsu_abone_no' => $komsuAboneNo ?: null,
                'aciklama' => $aciklama,
                'konum_link' => $konumLink ?: null,
                'konum_lat' => $lat,
                'konum_lng' => $lng,
                'konum_dogruluk' => $dogruluk,
            ]);

            // Ek fotoğraflar ($_FILES)
            if (!empty($_FILES['fotograflar']) && !empty($_FILES['fotograflar']['name'])) {
                $kalanKota = max(0, IhbarModel::MAX_FOTO - $ihbarModel->countFotograflar($id));
                if ($kalanKota > 0) {
                    $fileNames = is_array($_FILES['fotograflar']['name']) ? $_FILES['fotograflar']['name'] : [$_FILES['fotograflar']['name']];
                    $tmpNames = is_array($_FILES['fotograflar']['tmp_name']) ? $_FILES['fotograflar']['tmp_name'] : [$_FILES['fotograflar']['tmp_name']];
                    $errors = is_array($_FILES['fotograflar']['error']) ? $_FILES['fotograflar']['error'] : [$_FILES['fotograflar']['error']];
                    $sizes = is_array($_FILES['fotograflar']['size']) ? $_FILES['fotograflar']['size'] : [$_FILES['fotograflar']['size']];

                    $maxFiles = min(count($fileNames), $kalanKota);
                    for ($i = 0; $i < $maxFiles; $i++) {
                        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                            continue;
                        }
                        try {
                            $fotoSonuc = IhbarModel::storeUploadedFoto([
                                'name' => $fileNames[$i],
                                'tmp_name' => $tmpNames[$i],
                                'error' => $errors[$i],
                                'size' => $sizes[$i],
                            ], $id);
                            $ihbarModel->addFotograf($id, $fotoSonuc['yol'], $fotoSonuc['kucuk']);
                        } catch (\Throwable $e) {
                            error_log('Mobile update foto yükleme hatası: ' . $e->getMessage());
                        }
                    }
                }
            }

            mobile_ok(['message' => 'İhbar bilgileri güncellendi.', 'id' => $id]);
        } catch (\Throwable $e) {
            mobile_fail($e->getMessage(), 400);
        }
    }

    if ($action === 'add_note') {
        $id = (int) ($input['id'] ?? 0);
        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? $input['not'] ?? '')));

        if ($id <= 0) {
            mobile_fail('Geçersiz ihbar numarası.', 422);
        }
        if ($aciklama === '') {
            mobile_fail('Lütfen not içeriğini yazınız.', 422);
        }

        $atananIds = $ihbarModel->getAtananPersonelIds($id);
        $ihbar = $ihbarModel->getById($id);

        if (!$ihbar) {
            mobile_fail('İhbar kaydı bulunamadı.', 404);
        }

        $yetkili = in_array($personelId, $atananIds, true) || (int) $ihbar->bildiren_personel_id === $personelId;
        if (!$yetkili) {
            mobile_fail('Bu ihbara not ekleme yetkiniz bulunmuyor.', 403);
        }

        $ihbarModel->addNote($id, $aciklama, 'personel', $personelId);
        mobile_ok(['message' => 'İşlem notu eklendi.']);
    }

    if ($action === 'close') {
        $id = (int) ($input['id'] ?? 0);
        $durum = trim((string) ($input['durum'] ?? ''));
        $tutanakNo = trim((string) ($input['tutanak_no'] ?? ''));
        $sebep = trim((string) ($input['sebep'] ?? ''));

        if ($id <= 0) {
            mobile_fail('Geçersiz ihbar numarası.', 422);
        }
        if (!in_array($durum, ['olumlu', 'olumsuz'], true)) {
            mobile_fail('Sonuç durumu \'olumlu\' veya \'olumsuz\' olmalıdır.', 422);
        }
        if ($durum === 'olumlu' && empty($tutanakNo)) {
            mobile_fail('Olumlu sonuç için tutanak numarası girilmelidir.', 422);
        }
        if ($durum === 'olumsuz' && empty($sebep)) {
            mobile_fail('Olumsuz sonuç için işlem sebebi belirtilmelidir.', 422);
        }

        $atananIds = $ihbarModel->getAtananPersonelIds($id);
        if (!in_array($personelId, $atananIds, true)) {
            mobile_fail('Bu ihbarı yalnızca atanan personel sonuçlandırabilir.', 403);
        }

        try {
            $ihbarModel->closeSonuc($id, $durum, $tutanakNo ?: null, $sebep ?: null, 'personel', $personelId);
            mobile_ok(['message' => 'İhbar başarıyla sonuçlandırıldı.']);
        } catch (\Throwable $e) {
            mobile_fail($e->getMessage(), 400);
        }
    }

    mobile_fail('Geçersiz işlem.', 404);
}

// GET - Tekil İhbar Detayı
$detailId = (int) ($_GET['id'] ?? $input['id'] ?? 0);
if ($detailId > 0) {
    $ihbar = $ihbarModel->getById($detailId);
    if (!$ihbar) {
        mobile_fail('İhbar bulunamadı.', 404);
    }

    $atananIds = $ihbarModel->getAtananPersonelIds($detailId);
    $duzenlenebilir = ((int) $ihbar->bildiren_personel_id === $personelId) && !in_array($ihbar->durum, ['olumlu', 'olumsuz'], true);
    $atananMi = in_array($personelId, $atananIds, true);

    $fotograflar = [];
    foreach ($ihbar->fotograflar ?? [] as $f) {
        $fotograflar[] = [
            'id' => (int) $f->id,
            'medya_tipi' => $f->medya_tipi ?? 'foto',
            'sure_saniye' => $f->sure_saniye !== null ? (int) $f->sure_saniye : null,
            'url' => mobile_asset_url($f->dosya_yolu),
            'kucuk_url' => !empty($f->kucuk_yol) ? mobile_asset_url($f->kucuk_yol) : mobile_asset_url($f->dosya_yolu),
            'created_at' => !empty($f->created_at) ? date('d.m.Y H:i', strtotime($f->created_at)) : '',
        ];
    }

    $tarihce = [];
    foreach ($ihbar->tarihce ?? [] as $t) {
        $tarihce[] = [
            'id' => (int) $t->id,
            'tip' => $t->tip,
            'aciklama' => $t->aciklama,
            'ekleyen_adi' => $t->ekleyen_adi ?? 'Sistem',
            'ekleyen_tip' => $t->ekleyen_tip,
            'tarih' => !empty($t->created_at) ? date('d.m.Y H:i', strtotime($t->created_at)) : '',
        ];
    }

    $atananEkip = [];
    foreach ($ihbar->atanan_ekip ?? [] as $ae) {
        $atananEkip[] = [
            'personel_id' => (int) $ae->personel_id,
            'adi_soyadi' => $ae->adi_soyadi,
        ];
    }

    mobile_ok([
        'ihbar' => [
            'id' => (int) $ihbar->id,
            'ilce' => $ihbar->ilce ?? '',
            'mahalle' => $ihbar->mahalle ?? '',
            'telefon' => $ihbar->telefon ?? '',
            'komsu_abone_no' => $ihbar->komsu_abone_no ?? '',
            'aciklama' => $ihbar->aciklama ?? '',
            'konum_lat' => $ihbar->konum_lat ? (float) $ihbar->konum_lat : null,
            'konum_lng' => $ihbar->konum_lng ? (float) $ihbar->konum_lng : null,
            'konum_dogruluk' => $ihbar->konum_dogruluk ? (float) $ihbar->konum_dogruluk : null,
            'konum_link' => $ihbar->konum_link ?? '',
            'durum' => $ihbar->durum,
            'durum_text' => $durumMap[$ihbar->durum] ?? ucfirst($ihbar->durum),
            'tutanak_no' => $ihbar->tutanak_no ?? null,
            'olumsuz_sebep' => $ihbar->olumsuz_sebep ?? null,
            'bildiren_personel_adi' => $ihbar->bildiren_personel_adi ?? '',
            'tarih' => !empty($ihbar->created_at) ? date('d.m.Y H:i', strtotime($ihbar->created_at)) : '',
            'duzenlenebilir' => $duzenlenebilir,
            'atanan_mi' => $atananMi,
            'atanan_ekip' => $atananEkip,
            'fotograflar' => $fotograflar,
            'tarihce' => $tarihce,
        ],
    ]);
}

// GET - Liste & İstatistikler
$gelenRaw = $ihbarModel->getPersoneleAtananIhbarlar($personelId);
$bildirdiklerimRaw = $ihbarModel->getPersonelinIhbarlari($personelId);

$formatItem = function ($item, bool $isBildirdiklerim = false) use ($durumMap, $personelId) {
    $durum = mb_strtolower((string) ($item->durum ?? 'yeni'), 'UTF-8');
    $duzenlenebilir = $isBildirdiklerim && !in_array($durum, ['olumlu', 'olumsuz'], true);

    return [
        'id' => (int) $item->id,
        'ilce' => $item->ilce ?? '',
        'mahalle' => $item->mahalle ?? '',
        'telefon' => $item->telefon ?? '',
        'komsu_abone_no' => $item->komsu_abone_no ?? '',
        'aciklama' => $item->aciklama ?? '',
        'durum' => $durum,
        'durum_text' => $durumMap[$durum] ?? ucfirst($durum),
        'atanan_ekip_adi' => $item->atanan_ekip_adi ?? null,
        'tarih' => !empty($item->created_at) ? date('d.m.Y H:i', strtotime($item->created_at)) : '',
        'duzenlenebilir' => $duzenlenebilir,
        'konum_lat' => $item->konum_lat ? (float) $item->konum_lat : null,
        'konum_lng' => $item->konum_lng ? (float) $item->konum_lng : null,
    ];
};

$gelenList = array_map(fn($item) => $formatItem($item, false), $gelenRaw);
$bildirdiklerimList = array_map(fn($item) => $formatItem($item, true), $bildirdiklerimRaw);

$toplam = count($gelenList) + count($bildirdiklerimList);
$bekleyen = 0;
$olumlu = 0;
$olumsuz = 0;

$allUnique = [];
foreach (array_merge($gelenList, $bildirdiklerimList) as $row) {
    $allUnique[$row['id']] = $row;
}

foreach ($allUnique as $row) {
    if (in_array($row['durum'], ['yeni', 'yonlendirildi', 'islemde'], true)) {
        $bekleyen++;
    } elseif ($row['durum'] === 'olumlu') {
        $olumlu++;
    } elseif ($row['durum'] === 'olumsuz') {
        $olumsuz++;
    }
}

mobile_ok([
    'stats' => [
        'toplam' => count($allUnique),
        'bekleyen' => $bekleyen,
        'olumlu' => $olumlu,
        'olumsuz' => $olumsuz,
        'gelen_sayisi' => count($gelenList),
        'bildirdiklerim_sayisi' => count($bildirdiklerimList),
    ],
    'gelen' => $gelenList,
    'bildirdiklerim' => $bildirdiklerimList,
    'ilceler' => $ilceler,
    'limits' => [
        'max_foto' => IhbarModel::MAX_FOTO,
        'max_video' => IhbarModel::MAX_VIDEO,
        'video_max_sure' => IhbarModel::VIDEO_MAX_SURE,
    ],
]);
