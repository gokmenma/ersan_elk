<?php
require_once __DIR__ . '/Autoloader.php';

use App\Helper\Security;
use App\Model\PersonelEvrakModel;
use App\Model\PersonelKarsilamaModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Service\GuvenliBelgeYuklemeService;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

const MADDE_ENFAZLA_DOSYA = 4;
const TOPLAM_ENFAZLA_DOSYA = 30;
const SAATLIK_DENEME_SINIRI = 60;

$yanit = static function (bool $ok, string $mesaj, array $ek = [], int $kod = 200): void {
    http_response_code($kod);
    echo json_encode(array_merge(['ok' => $ok, 'mesaj' => $mesaj], $ek), JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $yanit(false, 'Geçersiz istek.', [], 405);
}

$siteBaglami = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($siteBaglami !== '' && !in_array($siteBaglami, ['same-origin', 'none'], true)) {
    $yanit(false, 'Geçersiz istek.', [], 403);
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = (string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
    if (!hash_equals(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), strtolower($originHost))) {
        $yanit(false, 'Geçersiz istek.', [], 403);
    }
}

$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'bilinmiyor'), 0, 45);
$yuklenenYol = null;

try {
    $Karsilama = new PersonelKarsilamaModel();
    $kayit = $Karsilama->findByToken(strtolower(trim((string) ($_POST['t'] ?? ''))));
    if (!$kayit) {
        $yanit(false, 'Bağlantı geçersiz veya süresi dolmuş.', [], 404);
    }

    $karsilamaId = (int) $kayit->id;
    $firmaId = (int) $kayit->firma_id;
    $personelId = (int) $kayit->personel_id;
    $islem = (string) ($_POST['a'] ?? '');

    if ($Karsilama->sonSaatDeneme($ip, $karsilamaId) >= SAATLIK_DENEME_SINIRI) {
        $yanit(false, 'Çok fazla işlem yapıldı. Lütfen bir süre sonra tekrar deneyin.', [], 429);
    }

    $PersonelEvrak = new PersonelEvrakModel();
    $SystemLog = new SystemLogModel();

    if ($islem === 'sil') {
        $yuklemeId = (int) ($_POST['id'] ?? 0);
        $evrakId = $yuklemeId > 0 ? $Karsilama->yuklemeSil($yuklemeId, $karsilamaId) : null;
        if ($evrakId === null) {
            $yanit(false, 'Dosya bulunamadı.', [], 404);
        }
        if ($evrakId > 0) {
            $st = $PersonelEvrak->getDb()->prepare("UPDATE personel_evraklar SET aktif = 0 WHERE id = :id AND personel_id = :pid");
            $st->execute(['id' => $evrakId, 'pid' => $personelId]);
        }
        $SystemLog->logActionForFirm($firmaId, 0, 'Karşılama Yükleme', 'Personel yüklediği evrakı kaldırdı (Personel ID: ' . $personelId . ', Yükleme ID: ' . $yuklemeId . ')', SystemLogModel::LEVEL_INFO);
        $yanit(true, 'Dosya kaldırıldı.');
    }

    if ($islem === 'iban') {
        $ibanReddet = static function (string $mesaj, int $kod = 422) use ($Karsilama, $karsilamaId, $firmaId, $personelId, $ip, $yanit): void {
            try {
                $Karsilama->yuklemeKaydet(['karsilama_id' => $karsilamaId, 'firma_id' => $firmaId, 'personel_id' => $personelId, 'madde_index' => 1001, 'madde_adi' => 'IBAN', 'ip' => $ip, 'durum' => 'reddedildi', 'red_nedeni' => $mesaj]);
            } catch (Throwable $e) {
                error_log('karsilama-yukle iban red kaydı yazılamadı: ' . $e->getMessage());
            }
            $yanit(false, $mesaj, [], $kod);
        };

        if (($_POST['onay'] ?? '') !== '1') {
            $ibanReddet('Hesabın size ait olduğunu onaylamanız gerekir.', 400);
        }
        if ((int) $kayit->iban_degisim_sayisi >= 5) {
            $ibanReddet('IBAN değiştirme sınırına ulaşıldı. Lütfen yetkili ile iletişime geçin.', 429);
        }

        $PersonelModel = new PersonelModel();
        $personel = $PersonelModel->find($personelId);
        if (!$personel || (int) $personel->firma_id !== $firmaId) {
            $yanit(false, 'Personel bulunamadı.', [], 404);
        }
        $mevcut = strtoupper(preg_replace('/\s+/', '', (string) ($personel->iban_numarasi ?? '')));
        if ($mevcut !== '' && empty($kayit->iban_girildi_tarihi)) {
            $ibanReddet('Hesap bilginiz zaten kayıtlı. Değişiklik için lütfen yetkili ile iletişime geçin.', 403);
        }

        $temiz = PersonelKarsilamaModel::ibanTemizle((string) ($_POST['iban'] ?? ''));
        if ($temiz === null) {
            $ibanReddet('Geçerli bir IBAN girin (TR ile başlayan 26 karakter).');
        }

        $banka = PersonelKarsilamaModel::bankaAdi($temiz);
        if ($temiz !== $mevcut) {
            $guncelle = ['id' => $personelId, 'iban_numarasi' => $temiz];
            if ($banka !== null && trim((string) ($personel->banka ?? '')) === '') {
                $guncelle['banka'] = $banka;
            }
            $PersonelModel->saveWithAttr($guncelle);
            $Karsilama->ibanIsaretle($karsilamaId);
            $SystemLog->logActionForFirm($firmaId, 0, 'Karşılama IBAN', 'Personel maaş IBAN bilgisini karşılama bağlantısından girdi: ' . PersonelKarsilamaModel::ibanMaske($temiz) . ' (Personel ID: ' . $personelId . ')', SystemLogModel::LEVEL_IMPORTANT);
        }
        $yanit(true, 'IBAN kaydedildi.', ['maske' => PersonelKarsilamaModel::ibanMaske($temiz), 'banka' => $banka]);
    }

    if ($islem !== 'yukle') {
        $yanit(false, 'Geçersiz istek.', [], 400);
    }

    $sablon = $Karsilama->getSablon($firmaId);
    $maddeler = PersonelKarsilamaModel::maddeleriAyir((string) $sablon['evraklar']);
    if (($_POST['madde'] ?? '') === 'iban') {
        $maddeIndex = 1000;
        $madde = ['ad' => 'Maaş Hesabı (IBAN) Belgesi', 'not' => [], 'elden' => false, 'turu' => 'diger'];
    } else {
        $maddeIndex = (int) ($_POST['madde'] ?? -1);
        $madde = $maddeler[$maddeIndex] ?? null;
    }
    if (!$madde) {
        $yanit(false, 'Evrak maddesi bulunamadı.', [], 400);
    }
    if (!empty($madde['elden'])) {
        $yanit(false, 'Bu evrak elden teslim edilecektir, dijital yükleme yapılamaz.', [], 400);
    }

    $sayilar = $Karsilama->yuklemeSayilari($karsilamaId, $maddeIndex);
    if ($sayilar['madde'] >= MADDE_ENFAZLA_DOSYA) {
        $yanit(false, 'Bu evrak için en fazla ' . MADDE_ENFAZLA_DOSYA . ' dosya yükleyebilirsiniz. Önce birini kaldırın.', [], 400);
    }
    if ($sayilar['toplam'] >= TOPLAM_ENFAZLA_DOSYA) {
        $yanit(false, 'Yükleme sınırına ulaşıldı.', [], 400);
    }

    $reddet = static function (string $neden) use ($Karsilama, $karsilamaId, $firmaId, $personelId, $maddeIndex, $madde, $ip): void {
        try {
            $Karsilama->yuklemeKaydet([
                'karsilama_id' => $karsilamaId,
                'firma_id' => $firmaId,
                'personel_id' => $personelId,
                'madde_index' => $maddeIndex,
                'madde_adi' => $madde['ad'],
                'ip' => $ip,
                'durum' => 'reddedildi',
                'red_nedeni' => $neden,
            ]);
        } catch (Throwable $e) {
            error_log('karsilama-yukle red kaydı yazılamadı: ' . $e->getMessage());
        }
    };

    if (!isset($_FILES['dosya'])) {
        $yanit(false, 'Dosya seçilmedi.', [], 400);
    }

    try {
        $islenmis = GuvenliBelgeYuklemeService::isle($_FILES['dosya']);
    } catch (DomainException $e) {
        $reddet($e->getMessage());
        $yanit(false, $e->getMessage(), [], 422);
    }

    $sha = hash('sha256', $islenmis['veri']);
    if ($Karsilama->ayniDosyaVarMi($karsilamaId, $sha)) {
        $yanit(false, 'Bu dosyayı zaten yüklediniz.', [], 409);
    }

    $kok = __DIR__ . '/uploads/personel_evraklar/';
    $dizin = $kok . $personelId . '/';
    if (!is_dir($dizin) && !@mkdir($dizin, 0733, true) && !is_dir($dizin)) {
        throw new RuntimeException('Evrak klasörü oluşturulamadı.');
    }
    $dosyaAdi = bin2hex(random_bytes(16)) . '.enc';
    $yuklenenYol = $dizin . $dosyaAdi;
    if (file_put_contents($yuklenenYol, Security::encryptFile($islenmis['veri']), LOCK_EX) === false) {
        throw new RuntimeException('Dosya kaydedilemedi.');
    }

    $db = $PersonelEvrak->getDb();
    $db->beginTransaction();
    try {
        $sira = $sayilar['madde'] + 1;
        $gorunenAd = preg_replace('/[^\p{L}\p{N} ._()-]/u', '', $madde['ad']) ?: 'evrak';
        $evrakSifreli = $PersonelEvrak->saveWithAttr([
            'id' => 0,
            'personel_id' => $personelId,
            'evrak_adi' => mb_substr($madde['ad'], 0, 255, 'UTF-8'),
            'evrak_turu' => $madde['turu'],
            'dosya_adi' => $dosyaAdi,
            'orijinal_dosya_adi' => mb_substr($gorunenAd, 0, 100, 'UTF-8') . ' - ' . $sira . '.' . $islenmis['uzanti'],
            'dosya_boyutu' => strlen($islenmis['veri']),
            'dosya_tipi' => $islenmis['mime'],
            'aciklama' => 'Personel tarafından karşılama bağlantısı üzerinden dijital yüklendi (IP: ' . $ip . '). İnceleme bekliyor.',
            'yukleyen_id' => null,
        ]);
        $evrakId = (int) Security::decrypt((string) $evrakSifreli);
        if ($evrakId <= 0) {
            throw new RuntimeException('Evrak kaydı oluşturulamadı.');
        }
        $yuklemeId = $Karsilama->yuklemeKaydet([
            'karsilama_id' => $karsilamaId,
            'firma_id' => $firmaId,
            'personel_id' => $personelId,
            'evrak_id' => $evrakId,
            'madde_index' => $maddeIndex,
            'madde_adi' => $madde['ad'],
            'ip' => $ip,
            'sha256' => $sha,
            'boyut' => strlen($islenmis['veri']),
            'tip' => $islenmis['mime'],
            'durum' => 'basarili',
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $SystemLog->logActionForFirm($firmaId, 0, 'Karşılama Yükleme', 'Personel dijital evrak yükledi: ' . $madde['ad'] . ' (Personel ID: ' . $personelId . ', Evrak ID: ' . $evrakId . ')', SystemLogModel::LEVEL_IMPORTANT);
    $yanit(true, 'Dosya yüklendi.', ['dosya' => ['id' => $yuklemeId, 'boyut' => strlen($islenmis['veri']), 'madde' => $maddeIndex]]);
} catch (Throwable $e) {
    if ($yuklenenYol !== null && is_file($yuklenenYol)) {
        @unlink($yuklenenYol);
    }
    error_log('karsilama-yukle hatası: ' . $e->getMessage());
    $yanit(false, 'İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.', [], 500);
}
