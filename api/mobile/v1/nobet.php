<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\Security;
use App\Model\BildirimModel;
use App\Model\NobetModel;
use App\Model\SettingsModel;
use App\Model\UserNotificationPreferenceModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));

$departman = (string) ($personel->departman ?? '');
if (stripos($departman, 'Kesme-Açma') === false && stripos($departman, 'Kesme Açma') === false) {
    mobile_fail('Bu işlem için yetkiniz bulunmuyor.', 403);
}

$input = mobile_input();
$action = (string) ($input['action'] ?? 'list');
$model = new NobetModel();

$tok = static fn(int $id): string => Security::encrypt($id);
$untok = static function (mixed $token): int {
    $id = is_string($token) && $token !== '' && strlen($token) <= 2048 ? Security::decrypt($token) : null;
    if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) {
        mobile_fail('Geçersiz kayıt.', 422);
    }

    return (int) $id;
};
$isDate = static function (mixed $v): bool {
    if (!is_string($v)) {
        return false;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $v);

    return $d !== false && $d->format('Y-m-d') === $v;
};
$periyot = static function (array $input): array {
    $yil = (int) ($input['yil'] ?? date('Y'));
    $ay = (int) ($input['ay'] ?? date('n'));
    if ($yil < 2000 || $yil > 2100 || $ay < 1 || $ay > 12) {
        mobile_fail('Geçersiz dönem.', 422);
    }
    $bas = sprintf('%04d-%02d-01', $yil, $ay);

    return [$yil, $ay, $bas, date('Y-m-t', strtotime($bas))];
};
$bildir = static function (string $permission, string $title, string $message, string $link, string $icon, string $color) use ($firmaId): void {
    try {
        (new BildirimModel())->broadcastByPermissionForFirm(
            $firmaId,
            $permission,
            $title,
            $message,
            $link,
            $icon,
            $color,
            UserNotificationPreferenceModel::TYPE_SHIFT
        );
    } catch (\Throwable $e) {
        error_log('Mobil nöbet yönetici bildirimi: ' . $e->getMessage());
    }
};

$talepSatiri = fn(object $t): array => [
    'token' => Security::encrypt((int) $t->id),
    'nobet_tarihi' => (string) ($t->nobet_tarihi ?? ''),
    'talep_eden_adi' => (string) ($t->talep_eden_adi ?? ''),
    'talep_edilen_adi' => (string) ($t->talep_edilen_adi ?? ''),
    'durum' => (string) $t->durum,
    'aciklama' => (string) ($t->aciklama ?? ''),
    'talep_tarihi' => (string) ($t->talep_tarihi ?? ''),
    'yon' => (int) $t->talep_edilen_id === $personelId ? 'gelen' : 'giden',
];

try {
    switch ($action) {
        case 'degisim_list': {
            $liste = array_map($talepSatiri, $model->getPersonelDegisimTalepleri($personelId, 'hepsi'));
            mobile_ok(['talepler' => $liste]);
            break;
        }

        case 'adaylar': {
            $adaylar = array_map(static fn($p): array => [
                'token' => Security::encrypt((int) $p->id),
                'adi_soyadi' => (string) $p->adi_soyadi,
                'departman' => (string) ($p->departman ?? ''),
            ], $model->getDegisimAdaylari($firmaId, $personelId));
            mobile_ok(['adaylar' => $adaylar]);
            break;
        }

        case 'degisim_create': {
            $nobet = $model->find($untok($input['nobet_token'] ?? null));
            if (!$nobet || (int) $nobet->personel_id !== $personelId) {
                mobile_fail('Bu nöbet size ait değil.', 403);
            }
            if (strtotime((string) $nobet->nobet_tarihi) < strtotime('today')) {
                mobile_fail('Geçmiş tarihli nöbetler için talep oluşturamazsınız.', 409);
            }
            if (($nobet->durum ?? null) === 'mazeret_bildirildi') {
                mobile_fail('Mazeret bildirilen nöbet için değişim talebi oluşturulamaz.', 409);
            }
            $edilenId = $untok($input['aday_token'] ?? null);
            $adayIdleri = array_map(static fn($p): int => (int) $p->id, $model->getDegisimAdaylari($firmaId, $personelId));
            if (!in_array($edilenId, $adayIdleri, true)) {
                mobile_fail('Seçilen personel ile nöbet değişimi yapılamaz.', 422);
            }
            if ($model->hasPendingDegisimTalebi((int) $nobet->id, $personelId)) {
                mobile_fail('Bu nöbet için zaten bekleyen bir değişim talebiniz bulunuyor.', 409);
            }
            $aciklama = mb_substr(trim(strip_tags((string) ($input['aciklama'] ?? ''))), 0, 500);

            $id = $model->createDegisimTalebi([
                'nobet_id' => (int) $nobet->id,
                'talep_eden_id' => $personelId,
                'talep_edilen_id' => $edilenId,
                'aciklama' => $aciklama !== '' ? $aciklama : null,
            ]);
            if (!$id) {
                mobile_fail('Talep oluşturulamadı.', 500);
            }
            try {
                $model->sendDegisimTalepBildirimi($edilenId, (string) $personel->adi_soyadi, (string) $nobet->nobet_tarihi);
            } catch (\Throwable $e) {
                error_log('Mobil nöbet değişim bildirimi: ' . $e->getMessage());
            }
            mobile_ok(['message' => 'Değişim talebiniz oluşturuldu. Karşı tarafın onayı bekleniyor.']);
            break;
        }

        case 'degisim_update': {
            $talep = $model->getKendiBekleyenDegisimTalebi($untok($input['talep_token'] ?? null), $personelId);
            if (!$talep) {
                mobile_fail('Bu talep güncellenemez.', 409);
            }
            $edilenId = $untok($input['aday_token'] ?? null);
            $adayIdleri = array_map(static fn($p): int => (int) $p->id, $model->getDegisimAdaylari($firmaId, $personelId));
            if (!in_array($edilenId, $adayIdleri, true)) {
                mobile_fail('Seçilen personel ile nöbet değişimi yapılamaz.', 422);
            }
            $aciklama = mb_substr(trim(strip_tags((string) ($input['aciklama'] ?? ''))), 0, 500);
            $model->updateDegisimTalebi((int) $talep->id, [
                'talep_edilen_id' => $edilenId,
                'aciklama' => $aciklama !== '' ? $aciklama : null,
            ]);
            mobile_ok(['message' => 'Değişim talebiniz güncellendi.']);
            break;
        }

        case 'degisim_iptal': {
            $talep = $model->getKendiBekleyenDegisimTalebi($untok($input['talep_token'] ?? null), $personelId);
            if (!$talep) {
                mobile_fail('Talep iptal edilemedi veya zaten sonuçlanmış.', 409);
            }
            $model->iptalDegisimTalebi((int) $talep->id, $personelId);
            mobile_ok(['message' => 'Değişim talebiniz iptal edildi.']);
            break;
        }

        case 'degisim_onayla': {
            $talep = $model->getGelenBekleyenDegisimTalebi($untok($input['talep_token'] ?? null), $personelId);
            if (!$talep) {
                mobile_fail('Bu talep size ait değil veya zaten işlem görmüş.', 409);
            }
            $model->onaylaPersonelTalebi((int) $talep->id);
            $bildir(
                'nobet/list',
                'Nöbet Değişim Talebi Onayı Bekliyor',
                $personel->adi_soyadi . ' nöbet değişim talebini onayladı. Yönetici onayı bekleniyor.',
                'index?p=nobet/list',
                'bell',
                'primary'
            );
            mobile_ok(['message' => 'Talebi onayladınız. Yönetici onayı bekleniyor.']);
            break;
        }

        case 'degisim_reddet': {
            $talep = $model->getGelenBekleyenDegisimTalebi($untok($input['talep_token'] ?? null), $personelId);
            if (!$talep) {
                mobile_fail('Bu talep size ait değil veya zaten işlem görmüş.', 409);
            }
            $model->reddetTalebi((int) $talep->id, $personelId, 'Personel tarafından reddedildi');
            try {
                $model->sendDegisimSonucBildirimi((int) $talep->talep_eden_id, 'reddedildi', '');
            } catch (\Throwable $e) {
                error_log('Mobil nöbet red bildirimi: ' . $e->getMessage());
            }
            mobile_ok(['message' => 'Talebi reddettiniz.']);
            break;
        }

        case 'mazeret': {
            $nobet = $model->find($untok($input['nobet_token'] ?? null));
            if (!$nobet || (int) $nobet->personel_id !== $personelId) {
                mobile_fail('Bu nöbet size ait değil.', 403);
            }
            if (strtotime((string) $nobet->nobet_tarihi) < strtotime('today')) {
                mobile_fail('Geçmiş tarihli nöbetler için mazeret bildirilemez.', 409);
            }
            if (($nobet->durum ?? null) === 'mazeret_bildirildi') {
                mobile_fail('Bu nöbet için zaten mazeret bildirilmiş.', 409);
            }
            $aciklama = mb_substr(trim(strip_tags((string) ($input['aciklama'] ?? ''))), 0, 1000);
            if (mb_strlen($aciklama) < 5) {
                mobile_fail('Mazeret açıklaması en az 5 karakter olmalıdır.', 422);
            }
            $model->updateNobet((int) $nobet->id, [
                'durum' => 'mazeret_bildirildi',
                'mazeret_aciklama' => $aciklama,
                'mazeret_tarihi' => date('Y-m-d H:i:s'),
            ]);
            $bildir(
                'nobet/list',
                'Nöbet Mazeret Bildirimi',
                $personel->adi_soyadi . ', ' . date('d.m.Y', strtotime((string) $nobet->nobet_tarihi))
                    . ' tarihli nöbeti için mazeret bildirdi: ' . mb_substr($aciklama, 0, 100),
                'index?p=nobet/list',
                'bell',
                'primary'
            );
            mobile_ok(['message' => 'Mazeret bildiriminiz yöneticiye iletildi.']);
            break;
        }

        case 'musait_gunler': {
            [, , $bas, $bit] = $periyot($input);
            $atanan = array_map(static fn($n): string => (string) $n->nobet_tarihi, $model->getPersonelNobetleri($personelId, $bas, $bit));
            $talepli = $model->getPersonelBekleyenTalepTarihleri($personelId);
            $bugun = date('Y-m-d');
            $gunler = [];
            for ($t = strtotime($bas); $t <= strtotime($bit); $t = strtotime('+1 day', $t)) {
                $d = date('Y-m-d', $t);
                if ($d >= $bugun && !in_array($d, $atanan, true) && !in_array($d, $talepli, true)) {
                    $gunler[] = $d;
                }
            }
            mobile_ok(['gunler' => $gunler]);
            break;
        }

        case 'talep_create': {
            $tarihler = $input['tarihler'] ?? [];
            if (!is_array($tarihler) || $tarihler === [] || count($tarihler) > 31) {
                mobile_fail('Lütfen en az bir tarih seçiniz.', 422);
            }
            $tarihler = array_values(array_unique($tarihler));
            $bugun = date('Y-m-d');
            $bekleyen = $model->getPersonelBekleyenTalepTarihleri($personelId);
            foreach ($tarihler as $t) {
                if (!$isDate($t) || $t < $bugun) {
                    mobile_fail('Geçersiz veya geçmiş bir tarih seçtiniz.', 422);
                }
                if (in_array($t, $bekleyen, true) || $model->hasNobetOnDate($personelId, $t)) {
                    mobile_fail(date('d.m.Y', strtotime($t)) . ' için zaten nöbet veya talebiniz var.', 409);
                }
            }
            $aciklama = mb_substr(trim(strip_tags((string) ($input['aciklama'] ?? ''))), 0, 500);

            $olusan = 0;
            foreach ($tarihler as $t) {
                if ($model->addNobetTalebi([
                    'firma_id' => $firmaId,
                    'personel_id' => $personelId,
                    'nobet_tarihi' => $t,
                    'aciklama' => $aciklama,
                ])) {
                    $olusan++;
                }
            }
            if ($olusan === 0) {
                mobile_fail('Talepler oluşturulamadı.', 500);
            }
            $adSoyad = (string) ($personel->adi_soyadi ?? 'Bir personel');
            $bildir(
                'nobet/talepler',
                'Yeni Nöbet Talebi',
                $olusan === 1
                    ? $adSoyad . ', ' . date('d.m.Y', strtotime($tarihler[0])) . ' tarihi için nöbet talep etti.'
                    : $adSoyad . ', ' . $olusan . ' farklı gün için nöbet talep etti.',
                'index?p=nobet/talepler#talepler',
                'calendar',
                'info'
            );
            mobile_ok(['message' => $olusan . ' adet nöbet talebiniz iletildi. Yönetici onayı bekleniyor.']);
            break;
        }

        case 'talep_iptal': {
            $talep = $model->getKendiBekleyenNobetTalebi($untok($input['talep_token'] ?? null), $personelId);
            if (!$talep) {
                mobile_fail('Talep iptal edilemedi veya zaten sonuçlanmış.', 409);
            }
            $model->iptalNobetTalebi((int) $talep->id, $personelId);
            mobile_ok(['message' => 'Nöbet talebi iptal edildi.']);
            break;
        }

        case 'list':
        default: {
            [$yil, $ay, $bas, $bit] = $periyot($input);
            $silinmisGoster = (new SettingsModel())->getAllSettingsAsKeyValue($firmaId)['nobet_silinmis_goster'] ?? '0';
            $aktifDegisim = [];
            foreach ($model->getPersonelDegisimTalepleri($personelId, 'giden') as $t) {
                if (in_array($t->durum, ['beklemede', 'personel_onayladi'], true)) {
                    $aktifDegisim[(int) $t->nobet_id] = ['token' => Security::encrypt((int) $t->id), 'durum' => (string) $t->durum];
                }
            }
            $gelenBekleyen = count(array_filter(
                $model->getPersonelDegisimTalepleri($personelId, 'gelen'),
                static fn($t): bool => $t->durum === 'beklemede'
            ));

            $nobetler = array_map(static function ($n) use ($aktifDegisim): array {
                $tarih = (string) $n->nobet_tarihi;
                $aktif = $aktifDegisim[(int) $n->id] ?? null;

                return [
                    'token' => Security::encrypt((int) $n->id),
                    'tarih' => $tarih,
                    'baslangic_saati' => substr((string) ($n->baslangic_saati ?: '18:00'), 0, 5),
                    'bitis_saati' => substr((string) ($n->bitis_saati ?: '08:00'), 0, 5),
                    'hafta_sonu' => ($n->nobet_tipi ?? '') === 'hafta_sonu' || (int) date('w', strtotime($tarih)) === 0,
                    'aciklama' => (string) ($n->aciklama ?? ''),
                    'silinmis' => !empty($n->silinme_tarihi),
                    'durum' => (string) ($n->durum ?? 'aktif'),
                    'gecmis' => $tarih < date('Y-m-d'),
                    'degisim' => $aktif,
                ];
            }, $model->getPersonelNobetleri($personelId, $bas, $bit, $silinmisGoster === '1', true));

            $talepler = array_map(static fn($t): array => [
                'token' => Security::encrypt((int) $t['id']),
                'tarih' => (string) $t['nobet_tarihi'],
                'aciklama' => (string) ($t['aciklama'] ?? ''),
            ], $model->getPersonelBekleyenTalepler($personelId, $bas, $bit));

            mobile_ok([
                'yil' => $yil,
                'ay' => $ay,
                'nobetler' => $nobetler,
                'talepler' => $talepler,
                'gelen_bekleyen' => $gelenBekleyen,
            ]);
        }
    }
} catch (\Throwable $e) {
    error_log('Mobil nobet.php hata: ' . $e->getMessage());
    mobile_fail('Nöbet işlemi tamamlanamadı. Lütfen yeniden deneyin.', 500);
}
