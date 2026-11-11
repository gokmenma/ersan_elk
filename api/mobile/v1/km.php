<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\Helper;
use App\Helper\ImageUploadService;
use App\Helper\MedyaServis;
use App\Helper\Security;
use App\Model\AracKmBildirimModel;
use App\Model\AracZimmetModel;
use App\Model\BildirimModel;
use App\Model\UserNotificationPreferenceModel;
use App\Service\KmBildirimAiKontrolService;

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

            $KmBildirim = new AracKmBildirimModel();
            $lastKm = $KmBildirim->getLastKm($aracId, $tarih, $tur, $excludeId);
            $alreadyExists = $KmBildirim->checkDuplicate($aracId, $tarih, $tur, $excludeId);

            mobile_ok([
                'arac_id' => $aracId,
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
            $bitisKm = (int) ($input['bitis_km'] ?? 0);
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

            $KmBildirim = new AracKmBildirimModel();

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
            }
            // 2. Base64 resim verisi
            elseif (!empty($input['base64_resim'])) {
                $b64Data = (string) $input['base64_resim'];
                if (str_contains($b64Data, ',')) {
                    $b64Data = explode(',', $b64Data, 2)[1];
                }
                $decoded = base64_decode($b64Data, true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    $uploadDir = PROJECT_ROOT . '/uploads/km_bildirim/';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        // Devam
                    }
                    $fileName = 'km_' . $personelId . '_' . time() . '_' . uniqid() . '.jpg';
                    if (file_put_contents($uploadDir . $fileName, $decoded)) {
                        $resimYolu = 'uploads/km_bildirim/' . $fileName;
                    }
                }
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

            $result = $KmBildirim->saveWithAttr($saveData);
            if (!$result) {
                mobile_fail('KM bildirimi kaydedilemedi, lütfen tekrar deneyin.');
            }

            $kayitId = $id > 0 ? $id : (int) Security::decrypt($result);
            $otomatikOnaylandi = false;
            $aiNedeni = '';

            // Fotoğraf kaydedilmişse AI kontrolünü çalıştır
            if ($resimYolu && class_exists(KmBildirimAiKontrolService::class)) {
                try {
                    $aiKontrol = new KmBildirimAiKontrolService();
                    $aiSonuc = $aiKontrol->kontrolEtVeOnayla([$kayitId], null);
                    $otomatikOnaylandi = (int) ($aiSonuc['onaylanan'] ?? 0) === 1;
                    $aiNedeni = trim((string) ($aiSonuc['sonuclar'][0]['neden'] ?? ''));
                } catch (\Throwable $e) {
                    error_log("Mobil KM AI kontrol hatası (ID {$kayitId}): " . $e->getMessage());
                }
            }

            $tarihFormatli = date('d.m.Y', strtotime($tarih));
            $turLabel = $tur === 'sabah' ? 'Sabah' : 'Akşam';
            $bildirimDetayi = "{$tarihFormatli} tarihli {$turLabel} bildirimi: {$bitisKm} KM.";

            // Otomatik onaylanmadıysa yöneticilere bildirim üret
            if (!$otomatikOnaylandi) {
                try {
                    $BildirimModel = new BildirimModel();
                    $BildirimModel->broadcastByPermission(
                        'arac-takip/km-onaylari',
                        'KM Bildirimi Manuel Onay Bekliyor',
                        ($personel->adi_soyadi ?? 'Personel') . ' – ' . $bildirimDetayi,
                        'index?p=arac-takip/km-onaylari',
                        'error-circle',
                        'warning',
                        UserNotificationPreferenceModel::TYPE_KM,
                        true
                    );
                } catch (\Throwable $e) {
                    error_log('Mobil KM yönetici bildirimi hatası: ' . $e->getMessage());
                }
            }

            $mesaj = $otomatikOnaylandi
                ? 'KM bildiriminiz fotoğrafla eşleşti ve yapay zekâ tarafından otomatik onaylandı.'
                : 'KM bildiriminiz kaydedildi ve yönetici onayına sunuldu.';

            mobile_ok([
                'id' => $kayitId,
                'otomatik_onaylandi' => $otomatikOnaylandi,
                'otomatik_onay_nedeni' => $aiNedeni,
                'message' => $mesaj,
            ]);
            break;
        }

        case 'get_km_data':
        default: {
            $AracZimmetModel = new AracZimmetModel();
            $zimmet = $AracZimmetModel->getAktifZimmetByPersonel($personelId);

            $KmBildirim = new AracKmBildirimModel();

            $vehicle = null;
            if ($zimmet) {
                $lastKm = $KmBildirim->getLastKm((int) $zimmet->arac_id, date('Y-m-d'), 'sabah');
                $vehicle = [
                    'id' => (int) $zimmet->arac_id,
                    'plaka' => (string) ($zimmet->plaka ?? 'Araç'),
                    'marka' => (string) ($zimmet->marka ?? ''),
                    'model' => (string) ($zimmet->model ?? ''),
                    'last_km' => (int) $lastKm,
                    'zimmet_tarihi' => $zimmet->zimmet_tarihi ? date('d.m.Y', strtotime($zimmet->zimmet_tarihi)) : '',
                ];
            }

            $rawHistory = $KmBildirim->getPersonelHistory($personelId, 40);

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

                if ($item->tarih === $today) {
                    if ($item->tur === 'sabah') {
                        $todayMorning = [
                            'durum' => (string) $item->durum,
                            'durum_text' => $durum['text'],
                            'km' => (int) ($item->onaylanan_km ?? $item->bitis_km),
                        ];
                    } elseif ($item->tur === 'aksam') {
                        $todayEvening = [
                            'durum' => (string) $item->durum,
                            'durum_text' => $durum['text'],
                            'km' => (int) ($item->onaylanan_km ?? $item->bitis_km),
                        ];
                    }
                }

                $items[] = [
                    'id' => (int) $item->id,
                    'arac_id' => (int) $item->arac_id,
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
                    'ai_durum' => (string) ($item->ai_durum ?? ''),
                    'ai_nedeni' => (string) ($item->ai_nedeni ?? ''),
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
                'morning_done' => $todayMorning !== null,
                'morning_status' => $todayMorning['durum'] ?? 'yok',
                'morning_text' => $todayMorning['durum_text'] ?? 'Girilmedi',
                'morning_km' => $todayMorning['km'] ?? null,
                'evening_done' => $todayEvening !== null,
                'evening_status' => $todayEvening['durum'] ?? 'yok',
                'evening_text' => $todayEvening['durum_text'] ?? 'Girilmedi',
                'evening_km' => $todayEvening['km'] ?? null,
            ];

            mobile_ok([
                'vehicle' => $vehicle,
                'today_status' => $todayStatus,
                'stats' => $stats,
                'history' => $items,
            ]);
            break;
        }
    }
} catch (\Throwable $e) {
    error_log('Mobil km.php hata: ' . $e->getMessage());
    mobile_fail('KM verileri işlenirken hata oluştu: ' . $e->getMessage(), 500);
}
