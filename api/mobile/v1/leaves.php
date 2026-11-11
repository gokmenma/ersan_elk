<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\PersonelIzinleriModel;
use App\Model\TanimlamalarModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$model = new PersonelIzinleriModel();
$input = mobile_input();

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
        $tanimModel = new TanimlamalarModel();
        if ($izinTipiId <= 0 || !$tanimModel->isVisiblePersonelIzinTuru($izinTipiId) || !$isValidDate($baslangic) || !$isValidDate($bitis)) {
            mobile_fail('İzin türü ve tarihler eksiksiz girilmelidir.', 422);
        }
        if ($bitis < $baslangic) {
            mobile_fail('Bitiş tarihi başlangıç tarihinden önce olamaz.', 422);
        }

        $toplamGun = $model->hesaplaIzinGunu($baslangic, $bitis);
        $secilenIzinTuru = $tanimModel->find($izinTipiId);
        if ($model->isAnnualLeaveType($secilenIzinTuru) && $toplamGun > 3) {
            $gunFarki = (int) floor((strtotime($baslangic) - strtotime(date('Y-m-d'))) / 86400);
            if ($gunFarki < 14) {
                mobile_fail('3 günden uzun yıllık izinler en az iki hafta önceden bildirilmelidir.', 422);
            }
            if ($toplamGun > 20) {
                mobile_fail('Tek talepte en fazla 20 gün yıllık izin istenebilir.', 422);
            }
        }
        if ($model->hasActiveOverlap($personelId, $baslangic, $bitis)) {
            mobile_fail('Bu tarih aralığında bekleyen veya onaylanmış izniniz bulunuyor.', 409);
        }

        $model->createPersonelTalebi($personelId, $izinTipiId, $baslangic, $bitis, $aciklama);
        (new \App\Model\SystemLogModel())->logPersonelTalep($personelId, 'İzin Talebi Oluşturma', $baslangic . ' - ' . $bitis . ' arası izin talebi oluşturuldu (Mobil)');
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
        $tanimModel = new TanimlamalarModel();
        if ($izinTipiId <= 0 || !$tanimModel->isVisiblePersonelIzinTuru($izinTipiId) || !$isValidDate($baslangic) || !$isValidDate($bitis)) {
            mobile_fail('İzin türü ve tarihler eksiksiz girilmelidir.', 422);
        }
        if ($bitis < $baslangic) {
            mobile_fail('Bitiş tarihi başlangıç tarihinden önce olamaz.', 422);
        }

        $toplamGun = $model->hesaplaIzinGunu($baslangic, $bitis);
        $secilenIzinTuru = $tanimModel->find($izinTipiId);
        if ($model->isAnnualLeaveType($secilenIzinTuru) && $toplamGun > 3) {
            $gunFarki = (int) floor((strtotime($baslangic) - strtotime(date('Y-m-d'))) / 86400);
            if ($gunFarki < 14) {
                mobile_fail('3 günden uzun yıllık izinler en az iki hafta önceden bildirilmelidir.', 422);
            }
            if ($toplamGun > 20) {
                mobile_fail('Tek talepte en fazla 20 gün yıllık izin istenebilir.', 422);
            }
        }
        if ($model->hasActiveOverlap($personelId, $baslangic, $bitis, $id)) {
            mobile_fail('Bu tarih aralığında bekleyen veya onaylanmış başka bir izniniz bulunuyor.', 409);
        }

        if (!$model->updatePersonelTalebi($id, $personelId, $izinTipiId, $baslangic, $bitis, $aciklama)) {
            mobile_fail('Yalnızca bekleyen izin talepleri güncellenebilir.', 409);
        }

        (new \App\Model\SystemLogModel())->logPersonelTalep($personelId, 'İzin Talebi Güncelleme', $baslangic . ' - ' . $bitis . ' arası olarak güncellendi (Mobil)', $id);
        mobile_ok(['message' => 'İzin talebiniz başarıyla güncellendi.']);
    }

    if ($action === 'cancel') {
        if (!$model->cancelPendingPersonelTalebi((int) ($input['id'] ?? 0), $personelId)) {
            mobile_fail('Yalnızca bekleyen izin talepleri iptal edilebilir.', 409);
        }
        (new \App\Model\SystemLogModel())->logPersonelTalep($personelId, 'İzin Talebi İptal', 'İzin talebi personel tarafından iptal edildi (Mobil)', (int) ($input['id'] ?? 0));
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
        'durum' => $durum,
        'aciklama' => $izin->aciklama ?? '',
        'talep_tarihi' => $talepTarihi,
        'gecmis' => $gecmis,
    ];
}

$turler = array_map(static fn($tur) => [
    'id' => (int) $tur->id,
    'ad' => $tur->tur_adi,
    'aciklama' => $tur->aciklama ?? '',
], (new TanimlamalarModel())->getPersonelIzinTurleri());

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

