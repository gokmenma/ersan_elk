<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\MedyaServis;
use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Model\PersonelModel;
use App\Service\KacakTutanakAnalizService;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? 1);

// Oturum ve servis uyumluluğu için
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['personel_id'] = $personelId;
$_SESSION['firma_id'] = $firmaId;

$kacakModel = new KacakKontrolModel();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = mobile_input();

// =========================================================================
// 1. FOTOĞRAF / BELGE SERVİSİ (GET action=photo)
// =========================================================================
if ($method === 'GET' && (($input['action'] ?? '') === 'photo' || isset($_GET['photo_id']))) {
    $photoId = (int) ($input['photo_id'] ?? $input['id'] ?? 0);
    if ($photoId <= 0) {
        mobile_fail('Geçersiz fotoğraf parametresi.', 400);
    }

    $foto = $kacakModel->getPhoto($photoId);
    if (!$foto) {
        mobile_fail('Fotoğraf bulunamadı.', 404);
    }

    $db = $kacakModel->getDb();
    $stmt = $db->prepare("SELECT id FROM kacak_kontrol
                          WHERE id = ? AND silinme_tarihi IS NULL
                            AND (bildiren_personel_id = ? OR FIND_IN_SET(?, personel_ids))
                          LIMIT 1");
    $stmt->execute([(int) $foto['kacak_id'], $personelId, $personelId]);
    if (!$stmt->fetchColumn() && empty($personel->is_admin)) {
        mobile_fail('Bu belgeye erişim yetkiniz yok.', 403);
    }

    $kucukIstendi = ($input['boyut'] ?? '') === 'kucuk';
    $secilenYol = ($kucukIstendi && !empty($foto['kucuk_yol'])) ? $foto['kucuk_yol'] : $foto['dosya_yolu'];
    $dosya = KacakKontrolModel::rootPath() . '/' . ltrim((string) $secilenYol, '/');

    if (!is_file($dosya) && $secilenYol !== $foto['dosya_yolu']) {
        $dosya = KacakKontrolModel::rootPath() . '/' . ltrim((string) $foto['dosya_yolu'], '/');
    }

    if (!is_file($dosya)) {
        mobile_fail($foto['arsivlendi'] ? 'Bu dosya arşivlenmiştir.' : 'Dosya sunucuda bulunamadı.', 404);
    }

    MedyaServis::gonder($dosya);
    exit;
}

// =========================================================================
// 2. DETAY GETİRME (GET id > 0)
// =========================================================================
if ($method === 'GET' && isset($input['id']) && is_numeric($input['id']) && (int) $input['id'] > 0 && ($input['action'] ?? '') !== 'photo') {
    $id = (int) $input['id'];
    $kayit = $kacakModel->getRecord($id);

    if (!$kayit) {
        mobile_fail('Kaçak bildirimi bulunamadı.', 404);
    }

    // Yetki kontrolü (kendi bildirdiği veya ekibinde olduğu kayıt)
    $isOwn = (int) $kayit['bildiren_personel_id'] === $personelId;
    $personelIds = !empty($kayit['personel_ids']) ? array_map('intval', explode(',', $kayit['personel_ids'])) : [];
    $isEkip = in_array($personelId, $personelIds, true);

    if (!$isOwn && !$isEkip && empty($personel->is_admin)) {
        mobile_fail('Bu kaydı görüntüleme yetkiniz yok.', 403);
    }

    $hamFotolar = $kacakModel->getPhotos($id);
    $fotograflar = [];
    foreach ($hamFotolar as $f) {
        $fotograflar[] = [
            'id' => (int) $f['id'],
            'kacak_id' => (int) $f['kacak_id'],
            'tur' => $f['tur'],
            'medya_tipi' => $f['medya_tipi'] ?? 'foto',
            'orijinal_ad' => $f['orijinal_ad'],
            'url' => '/kacak.php?action=photo&photo_id=' . (int) $f['id'],
            'kucuk_url' => '/kacak.php?action=photo&boyut=kucuk&photo_id=' . (int) $f['id'],
            'yuklenme_tarihi' => $f['yuklenme_tarihi'],
            'client_sira' => isset($f['client_sira']) ? (int) $f['client_sira'] : null,
        ];
    }

    $tutanakFoto = null;
    $sahaFotolari = [];
    foreach ($fotograflar as $f) {
        if ($f['tur'] === 'tutanak' && !$tutanakFoto) {
            $tutanakFoto = $f;
        } else {
            $sahaFotolari[] = $f;
        }
    }

    mobile_ok([
        'kayit' => [
            'id' => (int) $kayit['id'],
            'tarih' => $kayit['tarih'],
            'ilce' => $kayit['ilce'],
            'tur' => $kayit['tur'],
            'tutanak_no' => $kayit['tutanak_no'],
            'sayac_no' => $kayit['sayac_no'],
            'abone_adi' => $kayit['abone_adi'],
            'endeks' => $kayit['endeks'],
            'sayi' => (int) ($kayit['sayi'] ?? 1),
            'ekip_adi' => $kayit['ekip_adi'],
            'bildiren_personel_id' => (int) $kayit['bildiren_personel_id'],
            'bildiren_adi' => $kayit['bildiren_adi'] ?? '',
            'personel_ids' => $kayit['personel_ids'],
            'aciklama' => $kayit['aciklama'],
            'durum' => $kayit['durum'],
            'onay_durumu' => $kayit['onay_durumu'],
            'red_nedeni' => $kayit['red_nedeni'],
            'iptal_aciklama' => $kayit['iptal_aciklama'],
            'olusturma_tarihi' => $kayit['olusturma_tarihi'],
            'can_edit' => $isOwn && $kayit['onay_durumu'] === 'beklemede' && $kayit['durum'] !== 'iptal',
            'can_delete' => $isOwn && $kayit['onay_durumu'] === 'beklemede' && $kayit['durum'] !== 'iptal',
        ],
        'tutanak_foto' => $tutanakFoto,
        'saha_fotolari' => $sahaFotolari,
        'fotograflar' => $fotograflar,
    ]);
}

// =========================================================================
// 3. LİSTELEME VE İSTATİSTİK (GET)
// =========================================================================
if ($method === 'GET') {
    $baslangic = !empty($input['baslangic']) && strtotime((string) $input['baslangic'])
        ? date('Y-m-d', strtotime((string) $input['baslangic']))
        : date('Y-m-d', strtotime('-1 month'));

    $bitis = !empty($input['bitis']) && strtotime((string) $input['bitis'])
        ? date('Y-m-d', strtotime((string) $input['bitis']))
        : date('Y-m-d');

    if ($baslangic > $bitis) {
        [$baslangic, $bitis] = [$bitis, $baslangic];
    }

    $rawKayitlar = $kacakModel->getPersonelKayitlari($personelId, $baslangic, $bitis, 300);
    $istatistik = $kacakModel->getPersonelIstatistik($personelId, $baslangic, $bitis);
    $ekipAdaylari = $kacakModel->getEkipAdaylari($personelId);

    $kayitlar = [];
    $ozet = [
        'toplam' => count($rawKayitlar),
        'beklemede' => 0,
        'onaylandi' => 0,
        'reddedildi' => 0,
        'iptal' => 0,
    ];

    foreach ($rawKayitlar as $k) {
        if ($k['durum'] === 'iptal') {
            $ozet['iptal']++;
        } elseif ($k['onay_durumu'] === 'onaylandi') {
            $ozet['onaylandi']++;
        } elseif ($k['onay_durumu'] === 'reddedildi') {
            $ozet['reddedildi']++;
        } else {
            $ozet['beklemede']++;
        }

        $kayitlar[] = [
            'id' => (int) $k['id'],
            'tarih' => $k['tarih'],
            'ilce' => $k['ilce'] ?: '-',
            'tur' => $k['tur'] ?: 'Kaçak',
            'tutanak_no' => $k['tutanak_no'] ?: '-',
            'sayac_no' => $k['sayac_no'] ?: '-',
            'abone_adi' => $k['abone_adi'] ?: '-',
            'endeks' => $k['endeks'] ?: '-',
            'sayi' => (int) ($k['sayi'] ?? 1),
            'ekip_adi' => $k['ekip_adi'] ?: ($k['bildiren_adi'] ?? 'Ekip yok'),
            'bildiren_adi' => $k['bildiren_adi'] ?? '',
            'aciklama' => $k['aciklama'] ?: '',
            'durum' => $k['durum'] ?: 'aktif',
            'onay_durumu' => $k['onay_durumu'] ?: 'beklemede',
            'foto_sayisi' => (int) ($k['foto_sayisi'] ?? 0),
            'is_own' => (int) $k['bildiren_personel_id'] === $personelId,
            'can_edit' => (int) $k['bildiren_personel_id'] === $personelId && $k['onay_durumu'] === 'beklemede' && $k['durum'] !== 'iptal',
        ];
    }

    mobile_ok([
        'kayitlar' => $kayitlar,
        'ozet' => $ozet,
        'istatistik' => $istatistik,
        'baslangic' => $baslangic,
        'bitis' => $bitis,
        'referanslar' => [
            'ilceler' => KacakKontrolModel::ILCELER,
            'turler' => KacakKontrolModel::TURLER,
            'max_saha_foto' => KacakKontrolModel::MAX_SAHA_FOTO,
            'ekip_adaylari' => array_map(static function ($a) {
                return [
                    'id' => (int) $a['id'],
                    'adi_soyadi' => (string) $a['adi_soyadi'],
                ];
            }, $ekipAdaylari),
        ],
    ]);
}

// =========================================================================
// 4. POST İŞLEMLERİ
// =========================================================================
$action = (string) ($input['action'] ?? '');

// -------------------------------------------------------------------------
// 4.1 YAPAY ZEKA TUTANAK ANALİZİ (OCR)
// -------------------------------------------------------------------------
if ($action === 'analyze') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        mobile_fail('Görsel boyutu sunucu yükleme sınırını aşıyor.', 413);
    }

    $file = $_FILES['tutanak_file'] ?? $_FILES['tutanak_foto'] ?? $_FILES['file'] ?? $_FILES['tutanak'] ?? null;
    if (!$file || empty($file['name'])) {
        mobile_fail('Lütfen analiz edilecek tutanak dosyasını seçin.', 422);
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $err = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Tutanak dosyasının boyutu izin verilen sınırı aşıyor.',
            UPLOAD_ERR_PARTIAL => 'Dosya yüklenirken bağlantı kesildi.',
            default => 'Tutanak dosyası okunamadı (Kod: ' . $file['error'] . ').'
        };
        mobile_fail($err, 400);
    }

    $analizTarihi = !empty($input['tarih']) && strtotime((string) $input['tarih'])
        ? date('Y-m-d', strtotime((string) $input['tarih']))
        : date('Y-m-d');

    $adaylar = [['id' => $personelId, 'name' => (string) $personel->adi_soyadi]];
    foreach ($kacakModel->getEkipAdaylari($personelId) as $aday) {
        $adaylar[] = ['id' => (int) $aday['id'], 'name' => (string) $aday['adi_soyadi']];
    }

    try {
        $analizService = new KacakTutanakAnalizService();
        $satirlar = $analizService->analyze(
            $file,
            $analizTarihi,
            $analizService->getPersonelAdaylari($adaylar)
        );

        $sonuc = $satirlar[0] ?? null;
        if (!$sonuc) {
            mobile_fail('Tutanaktan veri çıkartılamadı, lütfen alanları elle doldurun.', 422);
        }

        // Ekip arkadaşı eşleştirme
        $ekipArkadasiId = null;
        if (!empty($sonuc['personel_ids'])) {
            foreach ($sonuc['personel_ids'] as $pid) {
                if ((int) $pid !== $personelId) {
                    $ekipArkadasiId = (int) $pid;
                    break;
                }
            }
        }

        mobile_ok([
            'data' => [
                'tarih' => $sonuc['tarih'] ?? date('Y-m-d'),
                'ilce' => $sonuc['ilce'] ?? '',
                'tur' => $sonuc['tur'] ?? 'Kaçak',
                'tutanak_no' => $sonuc['tutanak_no'] ?? '',
                'sayac_no' => $sonuc['sayac_no'] ?? '',
                'abone_adi' => $sonuc['abone_adi'] ?? '',
                'endeks' => $sonuc['endeks'] ?? '',
                'sayi' => (int) ($sonuc['sayi'] ?? 1),
                'aciklama' => $sonuc['aciklama'] ?? '',
                'ekip_arkadasi_id' => $ekipArkadasiId,
            ],
            'message' => 'Tutanak başarıyla analiz edildi.',
        ]);
    } catch (\Throwable $e) {
        error_log('Mobile Kaçak Tutanak Analiz Hatası: ' . $e->getMessage());
        mobile_fail('Tutanak analiz edilemedi: ' . $e->getMessage(), 500);
    }
}

// -------------------------------------------------------------------------
// 4.2 YENİ KAÇAK BİLDİRİMİ OLUŞTURMA
// -------------------------------------------------------------------------
if ($action === 'create') {
    $tarih = !empty($input['tarih']) && strtotime((string) $input['tarih'])
        ? date('Y-m-d', strtotime((string) $input['tarih']))
        : date('Y-m-d');

    // 7 gün geriye dönük kuralı
    $minTarih = date('Y-m-d', strtotime('-7 days'));
    $maxTarih = date('Y-m-d');
    if ($tarih < $minTarih || $tarih > $maxTarih) {
        mobile_fail('Geriye dönük en fazla 7 gün önceki bir tarih seçilebilir.', 422);
    }

    $ilce = trim((string) ($input['ilce'] ?? ''));
    if (empty($ilce) || !in_array($ilce, KacakKontrolModel::ILCELER, true)) {
        mobile_fail('Lütfen geçerli bir ilçe seçin.', 422);
    }

    $tur = trim((string) ($input['tur'] ?? 'Kaçak'));
    if (!in_array($tur, KacakKontrolModel::TURLER, true)) {
        $tur = 'Kaçak';
    }

    $ekipArkadasiId = (int) ($input['ekip_arkadasi_id'] ?? 0);
    if ($ekipArkadasiId <= 0) {
        mobile_fail('Lütfen ekip arkadaşınızı seçin.', 422);
    }

    $tutanakNo = trim((string) ($input['tutanak_no'] ?? ''));
    $sayacNo = trim((string) ($input['sayac_no'] ?? ''));
    $aboneAdi = trim((string) ($input['abone_adi'] ?? ''));
    $endeks = trim((string) ($input['endeks'] ?? ''));
    $sayi = max(1, (int) ($input['sayi'] ?? 1));
    $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));

    $personelIds = [$personelId, $ekipArkadasiId];
    sort($personelIds);

    // Resim yükleme zorunluluğu kontrolü
    $tutanakFile = $_FILES['tutanak_file'] ?? $_FILES['tutanak_foto'] ?? null;
    $hasTutanak = $tutanakFile && !empty($tutanakFile['name']) && ($tutanakFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    $hasSaha = !empty($_FILES['saha_fotolari']['name']) && (
        (is_array($_FILES['saha_fotolari']['name']) && !empty($_FILES['saha_fotolari']['name'][0])) ||
        (!is_array($_FILES['saha_fotolari']['name']) && !empty($_FILES['saha_fotolari']['name']))
    );

    if (!$hasTutanak && !$hasSaha) {
        if ($tutanakFile && !empty($tutanakFile['name']) && ($tutanakFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            mobile_fail('Tutanak fotoğrafı yüklenemedi (Hata kodu: ' . $tutanakFile['error'] . '). Lütfen tekrar deneyin.', 422);
        }
        mobile_fail('Kaçak bildirimi yapabilmek için tutanak fotoğrafı / resim yüklenmesi zorunludur.', 422);
    }

    // Mükerrer tutanak kontrolü
    if ($tutanakNo !== '') {
        $mukerrer = $kacakModel->findDuplicateRecord([
            'tutanak_no' => $tutanakNo,
            'sayac_no' => $sayacNo,
            'tarih' => $tarih,
        ]);
        if ($mukerrer) {
            mobile_fail("Bu tutanak numarası ({$tutanakNo}) ile {$mukerrer['tarih']} tarihinde zaten bir kayıt girilmiş.", 422);
        }
    }

    $kayitData = [
        'tarih' => $tarih,
        'ilce' => $ilce,
        'tur' => $tur,
        'tutanak_no' => $tutanakNo ?: null,
        'sayac_no' => $sayacNo ?: null,
        'abone_adi' => $aboneAdi ?: null,
        'endeks' => $endeks ?: null,
        'sayi' => $sayi,
        'aciklama' => $aciklama ?: null,
        'ekip_adi' => $kacakModel->buildEkipAdi($personelIds),
        'personel_ids' => implode(',', $personelIds),
        'bildiren_personel_id' => $personelId,
        'durum' => 'aktif',
        'onay_durumu' => 'beklemede',
        'kaynak' => 'mobil_app',
    ];

    $kacakId = $kacakModel->createRecord($kayitData);
    if ($kacakId <= 0) {
        mobile_fail('Kaçak bildirimi kaydedilemedi.', 500);
    }

    // Tutanak fotoğrafı yükleme
    $tutanakFile = $_FILES['tutanak_file'] ?? $_FILES['tutanak_foto'] ?? null;
    if ($tutanakFile && !empty($tutanakFile['name']) && ($tutanakFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        try {
            $dosyaYolu = $kacakModel->storeUploadedFile($tutanakFile, $kacakId, 'tutanak');
            $kacakModel->addPhoto($kacakId, 'tutanak', $dosyaYolu, $tutanakFile['name'], $personelId);
        } catch (\Throwable $e) {
            error_log('Mobile Kaçak Tutanak Fotoğrafı Yükleme Hatası: ' . $e->getMessage());
        }
    }

    // Saha fotoğrafları yükleme ($_FILES['saha_fotolari'])
    if (!empty($_FILES['saha_fotolari']['name'])) {
        $files = $_FILES['saha_fotolari'];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

        $uploadedCount = 0;
        foreach ($names as $idx => $name) {
            if (empty($name) || ($errors[$idx] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($uploadedCount >= KacakKontrolModel::MAX_SAHA_FOTO) {
                break;
            }
            $fileObj = [
                'name' => $name,
                'tmp_name' => $tmpNames[$idx],
                'error' => $errors[$idx],
            ];
            try {
                $dosyaYolu = $kacakModel->storeUploadedFile($fileObj, $kacakId, 'saha');
                $kacakModel->addPhoto($kacakId, 'saha', $dosyaYolu, $name, $personelId, null, $uploadedCount);
                $uploadedCount++;
            } catch (\Throwable $e) {
                error_log('Mobile Kaçak Saha Fotoğrafı Yükleme Hatası: ' . $e->getMessage());
            }
        }
    }

    $kacakModel->syncExpectedPhotoCount($kacakId);

    mobile_ok([
        'id' => $kacakId,
        'message' => 'Kaçak bildirimi başarıyla oluşturuldu.',
    ]);
}

// -------------------------------------------------------------------------
// 4.3 KAÇAK BİLDİRİMİ GÜNCELLEME
// -------------------------------------------------------------------------
if ($action === 'update') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        mobile_fail('Geçersiz kayıt ID.', 400);
    }

    $kayit = $kacakModel->getRecord($id);
    if (!$kayit) {
        mobile_fail('Kaçak bildirimi bulunamadı.', 404);
    }

    if ((int) $kayit['bildiren_personel_id'] !== $personelId && empty($personel->is_admin)) {
        mobile_fail('Bu kaydı düzenleme yetkiniz yok.', 403);
    }

    if ($kayit['onay_durumu'] !== 'beklemede' && empty($personel->is_admin)) {
        mobile_fail('Yalnızca onay bekleyen kayıtlar düzenlenebilir.', 422);
    }

    $tarih = !empty($input['tarih']) && strtotime((string) $input['tarih'])
        ? date('Y-m-d', strtotime((string) $input['tarih']))
        : $kayit['tarih'];

    $ilce = trim((string) ($input['ilce'] ?? $kayit['ilce']));
    $tur = trim((string) ($input['tur'] ?? $kayit['tur']));
    $tutanakNo = trim((string) ($input['tutanak_no'] ?? ''));
    $sayacNo = trim((string) ($input['sayac_no'] ?? ''));
    $aboneAdi = trim((string) ($input['abone_adi'] ?? ''));
    $endeks = trim((string) ($input['endeks'] ?? ''));
    $sayi = max(1, (int) ($input['sayi'] ?? $kayit['sayi'] ?? 1));
    $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));

    $ekipArkadasiId = (int) ($input['ekip_arkadasi_id'] ?? 0);
    $personelIds = $ekipArkadasiId > 0 ? [$personelId, $ekipArkadasiId] : array_map('intval', explode(',', $kayit['personel_ids']));
    sort($personelIds);

    $updateData = [
        'tarih' => $tarih,
        'ilce' => $ilce,
        'tur' => $tur,
        'tutanak_no' => $tutanakNo ?: null,
        'sayac_no' => $sayacNo ?: null,
        'abone_adi' => $aboneAdi ?: null,
        'endeks' => $endeks ?: null,
        'sayi' => $sayi,
        'aciklama' => $aciklama ?: null,
        'ekip_adi' => $kacakModel->buildEkipAdi($personelIds),
        'personel_ids' => implode(',', $personelIds),
    ];

    $hasExistingPhoto = $kacakModel->countPhotos($id) > 0;
    $tutanakFile = $_FILES['tutanak_file'] ?? $_FILES['tutanak_foto'] ?? null;
    $hasNewTutanak = $tutanakFile && !empty($tutanakFile['name']) && ($tutanakFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    $hasNewSaha = !empty($_FILES['saha_fotolari']['name']);
    if (!$hasExistingPhoto && !$hasNewTutanak && !$hasNewSaha) {
        mobile_fail('Kaçak bildirimi için resim (tutanak veya saha fotoğrafı) yüklenmesi zorunludur.', 422);
    }

    $kacakModel->updateRecord($id, $updateData);

    // Yeni Tutanak Fotoğrafı Yüklendiyse
    if ($tutanakFile && !empty($tutanakFile['name']) && ($tutanakFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        try {
            $dosyaYolu = $kacakModel->storeUploadedFile($tutanakFile, $id, 'tutanak');
            $kacakModel->addPhoto($id, 'tutanak', $dosyaYolu, $tutanakFile['name'], $personelId);
        } catch (\Throwable $e) {
            error_log('Mobile Kaçak Tutanak Foto Güncelleme Hatası: ' . $e->getMessage());
        }
    }

    // Yeni Saha Fotoğrafları Yüklendiyse
    if (!empty($_FILES['saha_fotolari']['name'])) {
        $files = $_FILES['saha_fotolari'];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

        $currentCount = $kacakModel->countPhotos($id, 'saha');
        foreach ($names as $idx => $name) {
            if (empty($name) || ($errors[$idx] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($currentCount >= KacakKontrolModel::MAX_SAHA_FOTO) {
                break;
            }
            $fileObj = [
                'name' => $name,
                'tmp_name' => $tmpNames[$idx],
                'error' => $errors[$idx],
            ];
            try {
                $dosyaYolu = $kacakModel->storeUploadedFile($fileObj, $id, 'saha');
                $kacakModel->addPhoto($id, 'saha', $dosyaYolu, $name, $personelId, null, $currentCount);
                $currentCount++;
            } catch (\Throwable $e) {
                error_log('Mobile Kaçak Saha Foto Güncelleme Hatası: ' . $e->getMessage());
            }
        }
    }

    $kacakModel->syncExpectedPhotoCount($id);

    mobile_ok([
        'id' => $id,
        'message' => 'Kaçak bildirimi başarıyla güncellendi.',
    ]);
}

// -------------------------------------------------------------------------
// 4.4 KAÇAK BİLDİRİMİ SİLME / İPTAL
// -------------------------------------------------------------------------
if ($action === 'delete' || $action === 'cancel') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        mobile_fail('Geçersiz kayıt ID.', 400);
    }

    $kayit = $kacakModel->getRecord($id);
    if (!$kayit) {
        mobile_fail('Kaçak bildirimi bulunamadı.', 404);
    }

    if ((int) $kayit['bildiren_personel_id'] !== $personelId && empty($personel->is_admin)) {
        mobile_fail('Bu kaydı silme yetkiniz yok.', 403);
    }

    if ($kayit['onay_durumu'] !== 'beklemede' && empty($personel->is_admin)) {
        mobile_fail('Onaylanmış veya reddedilmiş kayıtlar silinemez.', 422);
    }

    $success = $kacakModel->softDeletePendingByReporter($id, $personelId);
    if (!$success && empty($personel->is_admin)) {
        mobile_fail('Kayıt silinemedi.', 500);
    }

    mobile_ok([
        'id' => $id,
        'message' => 'Kaçak bildirimi başarıyla silindi.',
    ]);
}

// -------------------------------------------------------------------------
// 4.5 FOTOĞRAF SİLME
// -------------------------------------------------------------------------
if ($action === 'delete_photo') {
    $photoId = (int) ($input['photo_id'] ?? $input['id'] ?? 0);
    if ($photoId <= 0) {
        mobile_fail('Geçersiz fotoğraf ID.', 400);
    }

    $foto = $kacakModel->getPhoto($photoId);
    if (!$foto) {
        mobile_fail('Fotoğraf bulunamadı.', 404);
    }

    $kayit = $kacakModel->getRecord((int) $foto['kacak_id']);
    if (!$kayit || ((int) $kayit['bildiren_personel_id'] !== $personelId && empty($personel->is_admin))) {
        mobile_fail('Bu fotoğrafı silme yetkiniz yok.', 403);
    }

    if ($foto['tur'] === 'tutanak' && $kacakModel->countPhotos((int) $foto['kacak_id'], 'tutanak') <= 1) {
        mobile_fail('Kaçak bildiriminde tutanak fotoğrafı zorunludur, son tutanak fotoğrafını silemezsiniz.', 422);
    }

    $kacakModel->deletePhoto($photoId);
    $kacakModel->syncExpectedPhotoCount((int) $foto['kacak_id']);

    mobile_ok([
        'id' => $photoId,
        'message' => 'Fotoğraf başarıyla silindi.',
    ]);
}

mobile_fail('Geçersiz işlem isteği.', 400);
