<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\Helper;
use App\Service\ImageUploadService;
use App\Helper\MedyaServis;
use App\Helper\Security;
use App\Model\AracKmBildirimModel;
use App\Model\MobileKmModel;
use App\Model\AracZimmetModel;
use App\Model\BildirimModel;
use App\Model\UserNotificationPreferenceModel;
use App\Service\KmBildirimAiKontrolService;
use App\Service\KmFotoDamgaService;

function kmYoneticiyeBildir(object $personel, string $tarih, string $tur, int $bitisKm): void
{
    $turLabel = $tur === 'sabah' ? 'Sabah' : 'Akşam';
    $detay = date('d.m.Y', strtotime($tarih)) . " tarihli {$turLabel} bildirimi: {$bitisKm} KM.";
    try {
        (new BildirimModel())->broadcastByPermission(
            'arac-takip/km-onaylari',
            'KM Bildirimi Manuel Onay Bekliyor',
            ($personel->adi_soyadi ?? 'Personel') . ' – ' . $detay,
            'index?p=arac-takip/km-onaylari',
            'error-circle',
            'warning',
            UserNotificationPreferenceModel::TYPE_KM
        );
    } catch (\Throwable $e) {
        error_log('Mobil KM yönetici bildirimi hatası: ' . $e->getMessage());
    }
}

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));

$input = mobile_input();
$action = (string) ($input['action'] ?? 'get_km_data');
if ($action === 'foto') {
    $bildirimId = (int) Security::decrypt((string) ($input['id'] ?? ''));
    $resimYolu = $bildirimId > 0 ? (new AracKmBildirimModel())->getPersonelResimYolu($bildirimId, $personelId) : null;
    $dosya = $resimYolu !== null ? AracKmBildirimModel::resimDosyasiniBul($resimYolu) : null;
    if ($dosya === null) {
        mobile_fail('Fotoğraf bulunamadı.', 404);
    }

    MedyaServis::gonder($dosya, 86400);
    exit;
}

if (!in_array($action, ['get_km_data', 'get_last_km', 'submit_report', 'ai_check', 'ai_read'], true)) { mobile_fail('Geçersiz işlem.'); }
if ($action !== 'get_km_data' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { mobile_fail('POST isteği gerekli.', 405); }
// Resolve the vehicle only among the employee's own active assignments, never trust a caller-supplied ID.
$zimmetler = (new AracZimmetModel())->getAktifZimmetlerByPersonel($personelId);
$requestedAracId = !empty($input['arac_id']) ? (int) Security::decrypt((string) $input['arac_id']) : 0;
if ($requestedAracId <= 0 && $action !== 'get_km_data' && !empty($input['id'] ?? $input['exclude_id'] ?? null)) {
    $tokenRecord = (new MobileKmModel())->find((int) Security::decrypt((string) ($input['id'] ?? $input['exclude_id'])));
    $requestedAracId = $tokenRecord ? (int) $tokenRecord->arac_id : 0;
}
$activeVehicle = $zimmetler[0] ?? null;
$requestedVehicleFound = false;
foreach ($zimmetler as $zimmetItem) {
    if ($requestedAracId > 0 && (int) $zimmetItem->arac_id === $requestedAracId) {
        $activeVehicle = $zimmetItem;
        $requestedVehicleFound = true;
        break;
    }
}
if ($action !== 'get_km_data') {
    if (!$activeVehicle) { mobile_fail('Zimmetli aktif araç bulunamadı.', 403); }
    if ($requestedAracId > 0 && !$requestedVehicleFound) {
        mobile_fail('Bu araç için bildirim yetkiniz yok.', 403);
    }
    $input['arac_id'] = (int) $activeVehicle->arac_id;
    $date = (string) ($input['tarih'] ?? date('Y-m-d'));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > date('Y-m-d') || $date < substr($activeVehicle->zimmet_tarihi, 0, 10)) {
        mobile_fail('Tarih zimmet başlangıcı ile bugün arasında olmalıdır.');
    }
    if (!in_array($input['tur'] ?? 'sabah', ['sabah', 'aksam'], true)) { mobile_fail('Geçersiz bildirim dönemi.'); }
    $recordToken = $input['id'] ?? $input['exclude_id'] ?? null;
    $existingReport = null;
    if ($recordToken) {
        $recordId = (int) Security::decrypt((string) $recordToken);
        $existingReport = (new MobileKmModel())->find($recordId);
        if (!$existingReport || (int) $existingReport->personel_id !== $personelId
            || (int) $existingReport->firma_id !== $firmaId || (int) $existingReport->arac_id !== (int) $activeVehicle->arac_id
            || $existingReport->silinme_tarihi !== null || $existingReport->durum === 'onaylandi') {
            mobile_fail('Bu kaydı değiştirme yetkiniz yok.', 403);
        }
        $input['id'] = $input['exclude_id'] = $recordId;
    }
}


try {
    switch ($action) {
        case 'get_last_km': {
            $aracId = (int) ($input['arac_id'] ?? 0);
            $tarih = (string) ($input['tarih'] ?? date('Y-m-d'));
            $tur = (string) ($input['tur'] ?? 'sabah');
            $excludeId = (int) ($input['exclude_id'] ?? 0);

            if ($aracId <= 0) {
                // Eğer araç ID verilmediyse aktif zimmeti bul
                $AracZimmetModel = new AracZimmetModel();
                $zimmet = $AracZimmetModel->getAktifZimmetByPersonel($personelId);
                $aracId = $zimmet ? (int) $zimmet->arac_id : 0;
            }

            if ($aracId <= 0) {
                mobile_fail('Zimmetli aktif araç bulunamadı.');
            }

            $KmBildirim = new MobileKmModel();
            $lastKm = $KmBildirim->getLastKm($aracId, $tarih, $tur, $excludeId);
            $alreadyExists = $KmBildirim->checkDuplicate($aracId, $tarih, $tur, $excludeId);

            mobile_ok([
                'arac_id' => Security::encrypt($aracId),
                'tarih' => $tarih,
                'tur' => $tur,
                'last_km' => (int) $lastKm,
                'exists' => (bool) $alreadyExists,
            ]);
            break;
        }

        case 'submit_report': {
            $aracId = (int) ($input['arac_id'] ?? 0);
            $tarih = trim((string) ($input['tarih'] ?? date('Y-m-d')));
            $tur = trim((string) ($input['tur'] ?? 'sabah'));
            $validatedKm = filter_var($input['bitis_km'] ?? null, FILTER_VALIDATE_INT);
            $bitisKm = $validatedKm === false ? 0 : (int) $validatedKm;
            $aciklama = trim((string) ($input['aciklama'] ?? ''));
            $id = (int) ($input['id'] ?? 0);

            if ($aracId <= 0) {
                $AracZimmetModel = new AracZimmetModel();
                $zimmet = $AracZimmetModel->getAktifZimmetByPersonel($personelId);
                $aracId = $zimmet ? (int) $zimmet->arac_id : 0;
            }

            if ($aracId <= 0) {
                mobile_fail('Zimmetli araç bulunamadı.');
            }

            if ($bitisKm <= 0) {
                mobile_fail('Lütfen geçerli bir kilometre değeri girin.');
            }

            if (!in_array($tur, ['sabah', 'aksam'], true)) {
                $tur = 'sabah';
            }

            $KmBildirim = new MobileKmModel();

            // KM Doğrulaması (Backend)
            $lastKm = $KmBildirim->getLastKm($aracId, $tarih, $tur, $id);
            if ($bitisKm < $lastKm) {
                mobile_fail("Girilen KM değeri ({$bitisKm}), önceki KM değerinden ({$lastKm}) düşük olamaz.");
            }

            // Mükerrer Kayıt Kontrolü
            if ($KmBildirim->checkDuplicate($aracId, $tarih, $tur, $id)) {
                $turLabel = $tur === 'sabah' ? 'Sabah' : 'Akşam';
                $tarihF = date('d.m.Y', strtotime($tarih));
                mobile_fail("{$tarihF} tarihi {$turLabel} dönemi için zaten bir bildiriminiz bulunuyor.");
            }

            $resimYolu = null;

            // 1. Multipart dosya yükleme
            if (isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = PROJECT_ROOT . '/uploads/km_bildirim/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    mobile_fail('KM fotoğrafı yükleme dizini oluşturulamadı.');
                }

                $storedImage = (new ImageUploadService())->store(
                    $_FILES['resim'],
                    $uploadDir,
                    'km_' . $personelId . '_' . time(),
                    1920,
                    85,
                    15 * 1024 * 1024
                );
                $resimYolu = 'uploads/km_bildirim/' . $storedImage['filename'];
                (new KmFotoDamgaService())->uygula($storedImage['path'], [
                    (string) ($personel->adi_soyadi ?? ''),
                    (string) ($activeVehicle->plaka ?? ''),
                    $tur === 'sabah' ? 'Sabah Bildirimi' : 'Akşam Bildirimi',
                    date('d.m.Y H:i'),
                ]);
            }
            if (!$resimYolu && empty($existingReport->resim_yolu)) {
                mobile_fail('Kilometre göstergesinin fotoğrafını ekleyin.');
            }

            $saveData = [
                'arac_id' => $aracId,
                'personel_id' => $personelId,
                'firma_id' => $firmaId,
                'tarih' => $tarih,
                'tur' => $tur,
                'bitis_km' => $bitisKm,
                'aciklama' => $aciklama,
                'durum' => 'beklemede',
            ];

            if ($id > 0) {
                $saveData['id'] = $id;
            }

            if ($resimYolu) {
                $saveData['resim_yolu'] = $resimYolu;
            }

            $result = $KmBildirim->saveReport($saveData);
            if (!$result) {
                mobile_fail('KM bildirimi kaydedilemedi, lütfen tekrar deneyin.');
            }

            $kayitId = $id > 0 ? $id : (int) Security::decrypt($result);
            if (isset($input['on_okuma_km']) && $input['on_okuma_km'] !== '') {
                error_log("Mobil KM ön okuma: kayit={$kayitId} okunan=" . (int) $input['on_okuma_km'] . " girilen={$bitisKm}");
            }
            $aiBekliyor = (bool) ($resimYolu && class_exists(KmBildirimAiKontrolService::class));
            if (!$aiBekliyor) {
                kmYoneticiyeBildir($personel, $tarih, $tur, $bitisKm);
            }

            mobile_ok([
                'id' => Security::encrypt($kayitId),
                'otomatik_onaylandi' => false,
                'otomatik_onay_nedeni' => '',
                'ai_bekliyor' => $aiBekliyor,
                'message' => $aiBekliyor
                    ? 'KM bildiriminiz kaydedildi. Fotoğraf yapay zekâ ile kontrol ediliyor.'
                    : 'KM bildiriminiz kaydedildi ve yönetici onayına sunuldu.',
            ]);
            break;
        }

        case 'ai_read': {
            $dosya = $_FILES['resim'] ?? null;
            if (!$dosya || ($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $dosya['tmp_name'])
                || (int) $dosya['size'] <= 0 || (int) $dosya['size'] > 4 * 1024 * 1024) {
                mobile_fail('Fotoğraf okunamadı veya 4 MB sınırını aşıyor.');
            }
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($dosya['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                mobile_fail('Sadece JPEG, PNG veya WebP formatında fotoğraf okunabilir.');
            }

            $throttleFile = sys_get_temp_dir() . '/km_ai_read_' . $personelId . '.json';
            $now = time();
            $kayitlar = array_values(array_filter(
                (array) json_decode((string) @file_get_contents($throttleFile), true),
                static fn($t) => is_int($t) && $t > $now - 3600
            ));
            if (count($kayitlar) >= 20) {
                mobile_ok(['km' => null, 'guven' => 0]);
            }
            $kayitlar[] = $now;
            @file_put_contents($throttleFile, json_encode($kayitlar), LOCK_EX);

            try {
                $sonuc = (new KmBildirimAiKontrolService())->onOkuma($dosya['tmp_name'], $mime);
            } catch (\Throwable $e) {
                error_log('Mobil KM ön okuma hatası: ' . $e->getMessage());
                $sonuc = ['km' => null, 'guven' => 0];
            }
            mobile_ok(['km' => $sonuc['km'], 'guven' => $sonuc['guven']]);
            break;
        }

        case 'ai_check': {
            if (!$existingReport || $existingReport->durum !== 'beklemede' || empty($existingReport->resim_yolu)) {
                mobile_ok(['otomatik_onaylandi' => false, 'otomatik_onay_nedeni' => '']);
            }
            set_time_limit(300);
            $otomatikOnaylandi = false;
            $aiNedeni = '';
            try {
                $aiSonuc = (new KmBildirimAiKontrolService())->kontrolEtVeOnayla([(int) $existingReport->id], null);
                $otomatikOnaylandi = (int) ($aiSonuc['onaylanan'] ?? 0) === 1;
                $aiNedeni = trim((string) ($aiSonuc['sonuclar'][0]['neden'] ?? ''));
            } catch (\Throwable $e) {
                error_log("Mobil KM AI kontrol hatası (ID {$existingReport->id}): " . $e->getMessage());
            }
            if (!$otomatikOnaylandi) {
                kmYoneticiyeBildir($personel, (string) $existingReport->tarih, (string) $existingReport->tur, (int) $existingReport->bitis_km);
            }
            mobile_ok([
                'otomatik_onaylandi' => $otomatikOnaylandi,
                'otomatik_onay_nedeni' => $aiNedeni,
            ]);
            break;
        }

        case 'get_km_data':
        default: {
            $zimmet = $activeVehicle;

            $KmBildirim = new MobileKmModel();

            $vehicle = null;
            if ($zimmet) {
                $lastKm = $KmBildirim->getLastKm((int) $zimmet->arac_id, date('Y-m-d'), 'sabah');
                $vehicle = [
                    'id' => Security::encrypt($zimmet->arac_id),
                    'plaka' => (string) ($zimmet->plaka ?? 'Araç'),
                    'marka' => (string) ($zimmet->marka ?? ''),
                    'model' => (string) ($zimmet->model ?? ''),
                    'last_km' => (int) $lastKm,
                    'zimmet_tarihi' => $zimmet->zimmet_tarihi ? date('d.m.Y', strtotime($zimmet->zimmet_tarihi)) : '',
                ];
            }

            $rawHistory = $KmBildirim->getPersonelHistory($personelId, 40, date('Y-m-d', strtotime('-6 days')), $zimmet ? (int) $zimmet->arac_id : null);

            $durumMap = [
                'beklemede' => ['text' => 'Beklemede', 'tone' => 'amber'],
                'onaylandi' => ['text' => 'Onaylandı', 'tone' => 'emerald'],
                'reddedildi' => ['text' => 'Reddedildi', 'tone' => 'rose'],
            ];

            $today = date('Y-m-d');
            $todayMorning = null;
            $todayEvening = null;

            $items = [];
            $totalKmDriven = 0;

            foreach ($rawHistory as $item) {
                $durum = $durumMap[$item->durum] ?? ['text' => (string) $item->durum, 'tone' => 'primary'];
                $resimUrl = !empty($item->resim_yolu) ? '/km.php?action=foto&id=' . Security::encrypt($item->id) : null;
                $tarihF = date('d.m.Y', strtotime($item->tarih));
                $turLabel = $item->tur === 'sabah' ? 'Sabah' : 'Akşam';

                if ($item->tarih === $today && $zimmet && (int) $item->arac_id === (int) $zimmet->arac_id) {
                    if ($item->tur === 'sabah' && $todayMorning === null) {
                        $todayMorning = [
                            'durum' => (string) $item->durum,
                            'durum_text' => $durum['text'],
                            'km' => (int) ($item->onaylanan_km ?? $item->bitis_km),
                        ];
                    } elseif ($item->tur === 'aksam' && $todayEvening === null) {
                        $todayEvening = [
                            'durum' => (string) $item->durum,
                            'durum_text' => $durum['text'],
                            'km' => (int) ($item->onaylanan_km ?? $item->bitis_km),
                        ];
                    }
                }

                $items[] = [
                    'id' => Security::encrypt($item->id),
                    'arac_id' => Security::encrypt($item->arac_id),
                    'plaka' => (string) ($item->plaka ?? ($vehicle['plaka'] ?? '-')),
                    'tarih' => (string) $item->tarih,
                    'tarih_format' => $tarihF,
                    'tur' => (string) $item->tur,
                    'tur_label' => $turLabel,
                    'bitis_km' => (int) $item->bitis_km,
                    'onaylanan_km' => $item->onaylanan_km !== null ? (int) $item->onaylanan_km : null,
                    'durum' => (string) $item->durum,
                    'durum_text' => $durum['text'],
                    'durum_tone' => $durum['tone'],
                    'resim_url' => $resimUrl,
                    'ai_durum' => (int) ($item->ai_onay_mi ?? 0) === 1
                        ? 'onaylandi'
                        : (trim((string) ($item->ai_onaylanmama_nedeni ?? '')) !== '' ? 'manuel' : ''),
                    'ai_nedeni' => trim((string) ($item->ai_onaylanmama_nedeni ?? '')),
                    'red_nedeni' => trim((string) ($item->red_nedeni ?? '')),
                    'aciklama' => (string) ($item->aciklama ?? ''),
                    'olusturma_tarihi' => $item->olusturma_tarihi ? date('d.m.Y H:i', strtotime($item->olusturma_tarihi)) : '-',
                ];
            }

            $totalCount = count($items);
            $pendingCount = count(array_filter($items, fn($r) => $r['durum'] === 'beklemede'));
            $approvedCount = count(array_filter($items, fn($r) => $r['durum'] === 'onaylandi'));
            $rejectedCount = count(array_filter($items, fn($r) => $r['durum'] === 'reddedildi'));

            $stats = [
                'total_reports' => $totalCount,
                'pending_reports' => $pendingCount,
                'approved_reports' => $approvedCount,
                'rejected_reports' => $rejectedCount,
            ];

            $todayStatus = [
                'today_date' => date('d.m.Y'),
                'morning_done' => $todayMorning !== null && $todayMorning['durum'] !== 'reddedildi',
                'morning_status' => $todayMorning['durum'] ?? 'yok',
                'morning_text' => $todayMorning['durum_text'] ?? 'Girilmedi',
                'morning_km' => $todayMorning['km'] ?? null,
                'evening_done' => $todayEvening !== null && $todayEvening['durum'] !== 'reddedildi',
                'evening_status' => $todayEvening['durum'] ?? 'yok',
                'evening_text' => $todayEvening['durum_text'] ?? 'Girilmedi',
                'evening_km' => $todayEvening['km'] ?? null,
            ];

            mobile_ok([
                'vehicle' => $vehicle,
                'vehicles' => array_map(static fn($z) => [
                    'id' => Security::encrypt($z->arac_id),
                    'plaka' => (string) ($z->plaka ?? 'Araç'),
                    'marka' => (string) ($z->marka ?? ''),
                    'model' => (string) ($z->model ?? ''),
                ], $zimmetler),
                'today_status' => $todayStatus,
                'stats' => $stats,
                'history' => $items,
            ]);
            break;
        }
    }
} catch (\DomainException $e) {
    mobile_fail($e->getMessage(), 409);
} catch (\Throwable $e) {
    error_log('Mobil km.php hata: ' . $e->getMessage());
    mobile_fail('KM işlemi tamamlanamadı. Fotoğrafı ve bağlantınızı kontrol edip tekrar deneyin.', 500);
}
