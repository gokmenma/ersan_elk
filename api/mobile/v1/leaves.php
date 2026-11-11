<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\PersonelIzinleriModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Model\UserNotificationPreferenceModel;
use App\Service\PushNotificationService;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$model = new PersonelIzinleriModel();
$input = mobile_input();

function mobile_validate_izin_turu(PersonelIzinleriModel $model, object $personel, object $izinTuru, float $toplamGun, string $aciklama): void
{
    $medeni = mb_strtolower(trim((string) ($personel->medeni_durum ?? '')), 'UTF-8');
    if ($medeni === 'bekar' && $model->isPaternityLeaveType($izinTuru)) {
        mobile_fail('Bekar personeller babalık izni talebinde bulunamaz.', 422);
    }
    if ($medeni === 'evli' && $model->isMarriageLeaveType($izinTuru)) {
        mobile_fail('Evli personeller evlilik izni talebinde bulunamaz.', 422);
    }
    if ($model->isEntitlementRequiredLeaveType($izinTuru)) {
        $hak = $model->calculateLeaveEntitlement((int) $personel->id);
        if ((float) $hak['kalan_izin'] <= 0) {
            mobile_fail('İzin hakedişiniz bulunmadığı için ücretli veya yıllık izin talep edemezsiniz. Lütfen mazeret izni seçiniz.', 422);
        }
    }
    if ($model->isExcuseLeaveType($izinTuru) && $aciklama === '') {
        mobile_fail('Mazeret izni için açıklama girmeniz zorunludur.', 422);
    }
    if ($model->isAnnualLeaveType($izinTuru) && $toplamGun > 20) {
        mobile_fail('Yıllık izin talepleri en fazla 20 gün olabilir.', 422);
    }
}

function mobile_notify_leave_request(object $personel, object $izinTuru, int $izinId, ?array $saatlik): void
{
    try {
        $userModel = new UserModel();
        $talepEdenAdi = $personel->adi_soyadi ?? 'Personel';
        $turAdi = (string) ($izinTuru->tur_adi ?? 'İzin');
        if ($saatlik) {
            $turAdi .= ' (' . $saatlik['saat'] . ' - ' . rtrim(rtrim(number_format($saatlik['sure'], 1, ',', ''), '0'), ',') . ' saat)';
        }
        $mesaj = $talepEdenAdi . ' ' . $turAdi . ' talep etti.';

        $alicilar = [];
        $onayci = $userModel->getIzinOnayPersonel();
        if ($onayci && $userModel->hasUserPermission($onayci, 'izin_talepleri')) {
            $alicilar[$onayci->id] = $onayci;
        }
        foreach ($userModel->getInAppBildirimKullanicilari('izin') as $kullanici) {
            $alicilar[$kullanici->id] = $kullanici;
        }

        $bildirimModel = new BildirimModel();
        $pushService = new PushNotificationService();
        foreach ($alicilar as $kullanici) {
            try {
                $bildirimModel->createNotification(
                    $kullanici->id,
                    'Yeni İzin Talebi',
                    $mesaj,
                    'index.php?p=talepler/list&tab=izin&id=' . $izinId,
                    'calendar',
                    'warning',
                    UserNotificationPreferenceModel::TYPE_LEAVE_REQUEST
                );
                $pushService->sendToUser($kullanici->id, [
                    'title' => '📅 Yeni İzin Talebi',
                    'body' => $mesaj,
                    'url' => 'mobile/index.php?p=talepler',
                ], true, UserNotificationPreferenceModel::TYPE_LEAVE_REQUEST);
            } catch (\Throwable $t) {
                error_log('Mobile izin bildirim hatasi: ' . $t->getMessage());
            }
        }
    } catch (\Throwable $e) {
        error_log('Mobile izin bildirim ana hatasi: ' . $e->getMessage());
    }
}

function mobile_parse_hourly(PersonelIzinleriModel $model, object $izinTuru, array $input, string &$bitis, string $baslangic): ?array
{
    if (!$model->isHourlyLeaveType($izinTuru)) {
        return null;
    }
    $saat = trim((string) ($input['baslangic_saati'] ?? ''));
    $sure = $input['izin_saati'] ?? null;
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $saat)) {
        mobile_fail('Lütfen izin başlangıç saatini seçiniz.', 422);
    }
    if (!is_numeric($sure) || (float) $sure < 0.5 || (float) $sure > 8) {
        mobile_fail('İzin süresi 0,5 ile 8 saat arasında olmalıdır.', 422);
    }
    $sure = round(((float) $sure) * 2) / 2;
    [$sa, $dk] = array_map('intval', explode(':', $saat));
    if ($sa * 60 + $dk + $sure * 60 > 24 * 60) {
        mobile_fail('Saatlik izin gün sonunu aşamaz.', 422);
    }
    $bitis = $baslangic;
    return ['saat' => $saat, 'sure' => (float) $sure];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($input['action'] ?? '');

    if ($action === 'create') {
        $izinTipiId = (int) ($input['izin_tipi_id'] ?? 0);
        $baslangic = trim((string) ($input['baslangic_tarihi'] ?? ''));
        $bitis = trim((string) ($input['bitis_tarihi'] ?? ''));
        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));

        $isValidDate = static function (string $value): bool {
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        $izinTuru = $izinTipiId > 0 ? $model->getPersonnelVisibleLeaveType($izinTipiId, (int) ($_SESSION['firma_id'] ?? 0)) : null;
        $saatlik = null;
        if ($izinTuru && $isValidDate($baslangic)) {
            $saatlik = mobile_parse_hourly($model, $izinTuru, $input, $bitis, $baslangic);
        }
        if (!$izinTuru || !$isValidDate($baslangic) || !$isValidDate($bitis)) {
            mobile_fail('İzin türü ve tarihler eksiksiz girilmelidir.', 422);
        }
        if ($bitis < $baslangic) {
            mobile_fail('Bitiş tarihi başlangıç tarihinden önce olamaz.', 422);
        }

        $toplamGun = $saatlik ? 0.0 : $model->hesaplaIzinGunu($baslangic, $bitis);
        mobile_validate_izin_turu($model, $personel, $izinTuru, $toplamGun, $aciklama);
        if ($saatlik) {
            if ($model->countHourlyLeaveConflicts($personelId, $baslangic, $saatlik['saat'], $saatlik['sure']) > 0) {
                mobile_fail('Seçilen gün ve saat aralığında bekleyen veya onaylanmış başka bir izin talebiniz bulunmaktadır.', 409);
            }
        } elseif ($model->hasActiveOverlap($personelId, $baslangic, $bitis)) {
            mobile_fail('Bu tarih aralığında bekleyen veya onaylanmış izniniz bulunuyor.', 409);
        }

        $yeniId = $model->createPersonelTalebi($personelId, $izinTipiId, $baslangic, $bitis, $aciklama, $saatlik['saat'] ?? null, $saatlik['sure'] ?? null);
        mobile_notify_leave_request($personel, $izinTuru, $yeniId, $saatlik);
        mobile_ok(['message' => 'İzin talebiniz oluşturuldu.']);
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        $izinTipiId = (int) ($input['izin_tipi_id'] ?? 0);
        $baslangic = trim((string) ($input['baslangic_tarihi'] ?? ''));
        $bitis = trim((string) ($input['bitis_tarihi'] ?? ''));
        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));

        if ($id <= 0) {
            mobile_fail('Geçersiz izin kaydı.', 422);
        }

        $isValidDate = static function (string $value): bool {
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        $izinTuru = $izinTipiId > 0 ? $model->getPersonnelVisibleLeaveType($izinTipiId, (int) ($_SESSION['firma_id'] ?? 0)) : null;
        $saatlik = null;
        if ($izinTuru && $isValidDate($baslangic)) {
            $saatlik = mobile_parse_hourly($model, $izinTuru, $input, $bitis, $baslangic);
        }
        if (!$izinTuru || !$isValidDate($baslangic) || !$isValidDate($bitis)) {
            mobile_fail('İzin türü ve tarihler eksiksiz girilmelidir.', 422);
        }
        if ($bitis < $baslangic) {
            mobile_fail('Bitiş tarihi başlangıç tarihinden önce olamaz.', 422);
        }

        $toplamGun = $saatlik ? 0.0 : $model->hesaplaIzinGunu($baslangic, $bitis);
        mobile_validate_izin_turu($model, $personel, $izinTuru, $toplamGun, $aciklama);
        if ($saatlik) {
            if ($model->countHourlyLeaveConflicts($personelId, $baslangic, $saatlik['saat'], $saatlik['sure'], $id) > 0) {
                mobile_fail('Seçilen gün ve saat aralığında bekleyen veya onaylanmış başka bir izniniz bulunmaktadır.', 409);
            }
        } elseif ($model->hasActiveOverlap($personelId, $baslangic, $bitis, $id)) {
            mobile_fail('Bu tarih aralığında bekleyen veya onaylanmış başka bir izniniz bulunuyor.', 409);
        }

        if (!$model->updatePersonelTalebi($id, $personelId, $izinTipiId, $baslangic, $bitis, $aciklama, $saatlik['saat'] ?? null, $saatlik['sure'] ?? null)) {
            mobile_fail('Yalnızca bekleyen izin talepleri güncellenebilir.', 409);
        }

        mobile_ok(['message' => 'İzin talebiniz başarıyla güncellendi.']);
    }

    if ($action === 'cancel') {
        $sebep = trim(strip_tags((string) ($input['sebep'] ?? '')));
        if (mb_strlen($sebep, 'UTF-8') < 3) {
            mobile_fail('Lütfen iptal sebebini yazınız.', 422);
        }
        if (mb_strlen($sebep, 'UTF-8') > 300) {
            mobile_fail('İptal sebebi en fazla 300 karakter olabilir.', 422);
        }
        if (!$model->cancelPendingPersonelTalebi((int) ($input['id'] ?? 0), $personelId, $sebep)) {
            mobile_fail('Yalnızca bekleyen izin talepleri iptal edilebilir.', 409);
        }
        mobile_ok(['message' => 'İzin talebiniz iptal edildi.']);
    }

    mobile_fail('Geçersiz işlem.', 404);
}

$entitlement = $model->calculateLeaveEntitlement($personelId);
$izinler = $model->getPersonelIzinleri($personelId);
$bekleyen = 0;
$items = [];
foreach ($izinler as $izin) {
    $durum = mb_strtolower((string) ($izin->onay_durumu ?? 'beklemede'), 'UTF-8');
    if ($durum === 'beklemede') $bekleyen++;
    if (in_array($durum, ['onaylandı', 'onaylandi', 'kabuledildi'], true)) $durum = 'onaylandi';
    if (str_contains($durum, 'iptal')) $durum = 'iptal_edildi';

    $talepTarihi = !empty($izin->talep_tarihi) ? $izin->talep_tarihi : ($izin->olusturma_tarihi ?? date('Y-m-d H:i:s'));

    // Geçmiş / Timeline oluşturma
    $gecmis = [];
    $gecmis[] = [
        'id' => 0,
        'tarih' => $talepTarihi,
        'baslik' => 'Talep Oluşturuldu',
        'aciklama' => 'İzin talebi sisteme iletildi.',
        'islem_yapan' => $personel->adi_soyadi ?? 'Personel',
        'durum' => 'olusturuldu'
    ];

    if (!empty($izin->onaylar) && is_array($izin->onaylar)) {
        foreach ($izin->onaylar as $idx => $onay) {
            $onayDurum = mb_strtolower(trim((string) $onay->durum), 'UTF-8');
            $aciklama = (string) ($onay->aciklama ?? '');

            if (str_contains($aciklama, 'oluşturuldu') && $idx === 0) {
                continue;
            }

            $baslik = 'İşlem Kaydı';
            $durumKodu = 'islem';

            if (str_contains($onayDurum, 'güncellendi') || str_contains($aciklama, 'güncellendi')) {
                $baslik = 'Talep Güncellendi';
                $durumKodu = 'guncellendi';
            } elseif (str_contains($onayDurum, 'onay') || str_contains($aciklama, 'onay')) {
                $baslik = 'Talep Onaylandı';
                $durumKodu = 'onaylandi';
            } elseif (str_contains($onayDurum, 'red') || str_contains($aciklama, 'red')) {
                $baslik = 'Talep Reddedildi';
                $durumKodu = 'reddedildi';
            } elseif (str_contains($onayDurum, 'iptal') || str_contains($aciklama, 'iptal')) {
                $baslik = 'Talep İptal Edildi';
                $durumKodu = 'iptal_edildi';
            }

            $gecmis[] = [
                'id' => (int) ($onay->id ?? ($idx + 1)),
                'tarih' => $onay->tarih,
                'baslik' => $baslik,
                'aciklama' => $aciklama,
                'islem_yapan' => $onay->adi ?? 'Sistem / Yetkili',
                'durum' => $durumKodu
            ];
        }
    }

    $items[] = [
        'id' => (int) $izin->id,
        'izin_tipi_id' => (int) ($izin->izin_tipi_id ?? 0),
        'izin_tipi' => $izin->izin_tipi_adi ?? 'İzin',
        'baslangic' => $izin->baslangic_tarihi,
        'bitis' => $izin->bitis_tarihi,
        'toplam_gun' => (float) $izin->toplam_gun,
        'baslangic_saati' => !empty($izin->baslangic_saati) && $izin->izin_saati !== null ? substr((string) $izin->baslangic_saati, 0, 5) : null,
        'izin_saati' => $izin->izin_saati !== null ? (float) $izin->izin_saati : null,
        'durum' => $durum,
        'aciklama' => $izin->aciklama ?? '',
        'talep_tarihi' => $talepTarihi,
        'gecmis' => $gecmis,
    ];
}

$turler = array_map(static fn($tur) => [
    'id' => (int) $tur->id,
    'ad' => $tur->tur_adi,
    'aciklama' => in_array(trim((string) ($tur->aciklama ?? '')), ['', '0'], true) ? '' : trim((string) $tur->aciklama),
    'kisa_kod' => $tur->kisa_kod ?? '',
    'renk' => $tur->renk ?? '',
    'ikon' => $tur->ikon ?? '',
    'ucretli' => (int) ($tur->ucretli_mi ?? 0) === 1,
    'is_yillik' => $model->isAnnualLeaveType($tur),
    'is_hastalik' => $model->isSickLeaveType($tur),
    'is_babalik' => $model->isPaternityLeaveType($tur),
    'is_evlilik' => $model->isMarriageLeaveType($tur),
    'is_mazeret' => $model->isExcuseLeaveType($tur),
    'is_saatlik' => $model->isHourlyLeaveType($tur),
    'hakedis_gerektirir' => (bool) $model->isEntitlementRequiredLeaveType($tur),
], $model->getPersonnelVisibleLeaveTypes((int) ($_SESSION['firma_id'] ?? 0), $personel->medeni_durum ?? null));

mobile_ok([
    'stats' => [
        'kalan_izin' => (float) $entitlement['kalan_izin'],
        'toplam_hakedis' => (float) $entitlement['toplam_hakedis'],
        'kullanilan_izin' => (float) $entitlement['kullanilan_izin'],
        'bekleyen' => $bekleyen,
    ],
    'turler' => $turler,
    'izinler' => $items,
]);

