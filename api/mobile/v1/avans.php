<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\AvansModel;
use App\Model\UserModel;
use App\Model\PersonelModel;
use App\Model\BildirimModel;
use App\Model\UserNotificationPreferenceModel;
use App\Services\PushNotificationService;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$avansModel = new AvansModel();
$input = mobile_input();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($input['action'] ?? '');

    if ($action === 'create') {
        $tutar = (float) ($input['tutar'] ?? 0);
        $odemeSekli = trim((string) ($input['odeme_sekli'] ?? 'tek'));
        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));

        if ($tutar <= 0) {
            mobile_fail('Geçerli bir avans tutarı giriniz.', 422);
        }

        $talepDurumu = $avansModel->avansTalepDurumu($personelId);
        if (!$talepDurumu['izinli']) {
            mobile_fail($talepDurumu['mesaj'] ?: 'Avans talep etme yetkiniz bulunmamaktadır.', 403);
        }

        $limit = $avansModel->getAvansLimiti($personelId);
        if ($tutar > $limit) {
            mobile_fail('Talep edilen tutar avans limitinizi (' . number_format($limit, 2, ',', '.') . ' ₺) aşamaz.', 422);
        }

        $avansModel->saveWithAttr([
            'personel_id' => $personelId,
            'tutar' => $tutar,
            'odeme_sekli' => $odemeSekli,
            'aciklama' => $aciklama,
            'durum' => 'beklemede',
            'kayit_yapan' => $personelId,
            'talep_tarihi' => date('Y-m-d H:i:s'),
        ]);

        $newId = (int) $avansModel->getDb()->lastInsertId();

        // Bildirim & Push Gönderimi
        try {
            $userModel = new UserModel();
            $personelModel = new PersonelModel();
            $talepEden = $personelModel->find($personelId);
            $talepEdenAdi = $talepEden->adi_soyadi ?? 'Personel';

            $bildirimKullanicilari = $userModel->getInAppBildirimKullanicilari('avans');
            $bildirimModel = new BildirimModel();
            $pushService = class_exists(PushNotificationService::class) ? new PushNotificationService() : null;

            foreach ($bildirimKullanicilari as $kullanici) {
                try {
                    $bildirimModel->createNotification(
                        $kullanici->id,
                        'Yeni Avans Talebi',
                        $talepEdenAdi . ' ' . number_format($tutar, 2, ',', '.') . ' ₺ avans talep etti.',
                        'index.php?p=talepler/list&tab=avans&id=' . $newId,
                        'bx-money',
                        'success',
                        UserNotificationPreferenceModel::TYPE_ADVANCE_REQUEST
                    );

                    if ($pushService) {
                        $pushService->sendToUser($kullanici->id, [
                            'title' => '💰 Yeni Avans Talebi',
                            'body' => $talepEdenAdi . ' ' . number_format($tutar, 2, ',', '.') . ' ₺ avans talep etti.',
                            'url' => 'mobile/index.php?p=talepler'
                        ], false, UserNotificationPreferenceModel::TYPE_ADVANCE_REQUEST);
                    }
                } catch (\Throwable $t) {
                    error_log('Mobile avans bildirim hatasi: ' . $t->getMessage());
                }
            }
        } catch (\Throwable $e) {
            error_log('Mobile avans bildirim ana hatasi: ' . $e->getMessage());
        }

        mobile_ok([
            'message' => 'Avans talebiniz başarıyla oluşturuldu.',
            'id' => $newId,
        ]);
    }

    if ($action === 'cancel') {
        $id = (int) ($input['id'] ?? 0);
        $avans = $avansModel->find($id);

        if (!$avans || (int) $avans->personel_id !== $personelId) {
            mobile_fail('Avans talebi bulunamadı.', 404);
        }

        $durum = mb_strtolower(trim((string) $avans->durum), 'UTF-8');
        if ($durum !== 'beklemede') {
            mobile_fail('Yalnızca bekleyen avans talepleri iptal edilebilir.', 409);
        }

        $yeniAciklama = trim(($avans->aciklama ?? '') . ' (Personel tarafından iptal edildi)');
        $avansModel->saveWithAttr([
            'id' => $id,
            'durum' => 'iptal edildi',
            'aciklama' => $yeniAciklama,
        ]);

        mobile_ok(['message' => 'Avans talebiniz iptal edildi.']);
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        $tutar = (float) ($input['tutar'] ?? 0);
        $odemeSekli = trim((string) ($input['odeme_sekli'] ?? 'tek'));
        $aciklama = trim(strip_tags((string) ($input['aciklama'] ?? '')));

        if ($tutar <= 0) {
            mobile_fail('Geçerli bir avans tutarı giriniz.', 422);
        }

        $avans = $avansModel->find($id);
        if (!$avans || (int) $avans->personel_id !== $personelId) {
            mobile_fail('Avans talebi bulunamadı.', 404);
        }

        $durum = mb_strtolower(trim((string) $avans->durum), 'UTF-8');
        if ($durum !== 'beklemede') {
            mobile_fail('Yalnızca bekleyen avans talepleri güncellenebilir.', 409);
        }

        $limit = $avansModel->getAvansLimiti($personelId);
        $availableLimit = $limit + (float) $avans->tutar;
        if ($tutar > $availableLimit) {
            mobile_fail('Talep edilen tutar avans limitinizi (' . number_format($availableLimit, 2, ',', '.') . ' ₺) aşamaz.', 422);
        }

        $avansModel->saveWithAttr([
            'id' => $id,
            'tutar' => $tutar,
            'odeme_sekli' => $odemeSekli,
            'aciklama' => $aciklama,
        ]);

        mobile_ok(['message' => 'Avans talebiniz güncellendi.']);
    }

    mobile_fail('Geçersiz işlem.', 404);
}

// GET Talebi - Liste ve İstatistikler
$limit = (float) $avansModel->getAvansLimiti($personelId);
$talepDurumu = $avansModel->avansTalepDurumu($personelId);
$avanslar = $avansModel->getPersonelAvanslari($personelId);

$bekleyenSayisi = 0;
$onaylananSayisi = 0;
$onaylananToplam = 0.0;
$items = [];

foreach ($avanslar as $item) {
    $durumRaw = mb_strtolower(trim((string) ($item->durum ?? 'beklemede')), 'UTF-8');
    $durum = $durumRaw;
    if (in_array($durumRaw, ['onaylandi', 'onaylandı'], true)) {
        $durum = 'onaylandi';
        $durumText = 'Onaylandı';
        $onaylananSayisi++;
        $onaylananToplam += (float) ($item->tutar ?? 0);
    } elseif ($durumRaw === 'beklemede') {
        $durum = 'beklemede';
        $durumText = 'Beklemede';
        $bekleyenSayisi++;
    } elseif ($durumRaw === 'reddedildi') {
        $durum = 'reddedildi';
        $durumText = 'Reddedildi';
    } elseif (str_contains($durumRaw, 'iptal')) {
        $durum = 'iptal_edildi';
        $durumText = 'İptal Edildi';
    } else {
        $durumText = ucfirst($durumRaw);
    }

    $odemeSekliRaw = strtolower(trim((string) ($item->odeme_sekli ?? 'tek')));
    $odemeSekliText = 'Tek Kesinti';
    if ($odemeSekliRaw === '2' || $odemeSekliRaw === 'taksit') {
        $odemeSekliText = '2 Taksit';
    } elseif ($odemeSekliRaw === '3') {
        $odemeSekliText = '3 Taksit';
    } elseif (is_numeric($odemeSekliRaw) && (int) $odemeSekliRaw > 1) {
        $odemeSekliText = ((int) $odemeSekliRaw) . ' Taksit';
    }

    $items[] = [
        'id' => (int) $item->id,
        'tutar' => (float) $item->tutar,
        'talep_tarihi' => $item->talep_tarihi ? date('Y-m-d H:i:s', strtotime($item->talep_tarihi)) : null,
        'odeme_sekli' => $odemeSekliRaw,
        'odeme_sekli_text' => $odemeSekliText,
        'durum' => $durum,
        'durum_text' => $durumText,
        'aciklama' => (string) ($item->aciklama ?? ''),
        'onay_aciklama' => (string) ($item->onay_aciklama ?? ''),
        'onay_tarihi' => $item->onay_tarihi ? date('Y-m-d H:i:s', strtotime($item->onay_tarihi)) : null,
    ];
}

mobile_ok([
    'stats' => [
        'kalan_limit' => $limit,
        'bekleyen_sayisi' => $bekleyenSayisi,
        'onaylanan_sayisi' => $onaylananSayisi,
        'onaylanan_toplam' => $onaylananToplam,
    ],
    'talep_durumu' => [
        'izinli' => (bool) ($talepDurumu['izinli'] ?? true),
        'mesaj' => (string) ($talepDurumu['mesaj'] ?? ''),
    ],
    'avanslar' => $items,
]);
