<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;
use App\Model\ManuelGiderModel;
use App\Model\MaliyetKalemEslestirmeModel;
use App\Model\MaliyetOzelKalemModel;
use App\Model\MaliyetRaporuModel;
use App\Model\PermissionPolicyModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('maliyet-raporu/api', $action);
} elseif (!Gate::allows('maliyet_raporu')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Maliyet raporu için yetkiniz bulunmamaktadır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$eslestirmeAksiyonlari = ['eslestirme-listesi', 'eslestirme-kaydet', 'eslestirme-sil', 'fatura-kalem-ata', 'kalem-ekle', 'eslestirme-toplu-kaydet'];
if (in_array($action, $eslestirmeAksiyonlari, true) && !Gate::allows('maliyet_raporu_eslestirme')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Maliyet kalem eşleştirme yetkiniz bulunmamaktadır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$kullaniciId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

$log = static function (string $tur, string $aciklama) use ($kullaniciId): void {
    try {
        (new SystemLogModel())->logAction($kullaniciId, $tur, $aciklama, SystemLogModel::LEVEL_INFO);
    } catch (\Throwable $e) {
        error_log('maliyet-raporu log: ' . $e->getMessage());
    }
};

$cevap = static function (array $veri): void {
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
};

$MaliyetRaporu = new MaliyetRaporuModel();

if ($action === 'maliyet-ajax-list') {
    try {
        [$bas, $bit] = MaliyetRaporuModel::tarihAraligi($_POST['yil'] ?? '', $_POST['ay'] ?? '');
        $kalem = trim((string) ($_POST['kalem'] ?? '')) ?: null;
        $sonuc = $MaliyetRaporu->ajaxList($_POST, $bas, $bit, $kalem);
        $atayabilir = Gate::allows('maliyet_raporu_eslestirme');

        $sonuc['data'] = array_map(static function ($r) use ($atayabilir) {
            $fatura = $r->kaynak_tablo === 'faturalar';
            return [
                'tarih'        => $r->tarih ? date('d.m.Y', strtotime($r->tarih)) : '-',
                'kalem'        => $r->kalem,
                'kalem_adi'    => $r->kalem_adi,
                'alt_kategori' => $r->alt_kategori ?? '-',
                'tutar'        => (float) $r->tutar,
                'kaynak'       => $r->kaynak_adi,
                'aciklama'     => $r->aciklama ?? '-',
                'fatura_id'    => ($fatura && $atayabilir) ? Security::encrypt((int) $r->kaynak_id) : null,
            ];
        }, $sonuc['data']);

        $cevap($sonuc);
    } catch (\Throwable $e) {
        error_log('maliyet-ajax-list: ' . $e->getMessage());
        $cevap(['draw' => (int) ($_POST['draw'] ?? 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Liste yüklenemedi.']);
    }
}

if ($action === 'eslesmemis-faturalar') {
    try {
        [$bas, $bit] = MaliyetRaporuModel::tarihAraligi($_POST['yil'] ?? '', $_POST['ay'] ?? '');
        $liste = array_map(static function ($r) {
            return [
                'vkn'      => (string) $r->vkn,
                'unvan'    => (string) ($r->unvan ?? '-'),
                'adet'     => (int) $r->adet,
                'toplam'   => round((float) $r->toplam, 2),
                'son_tarih' => $r->son_tarih ? date('d.m.Y', strtotime($r->son_tarih)) : '-',
            ];
        }, $MaliyetRaporu->eslesmemisFaturalar($bas, $bit));
        $cevap(['status' => 'success', 'data' => $liste]);
    } catch (\Throwable $e) {
        error_log('eslesmemis-faturalar: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Eşleşmemiş faturalar yüklenemedi.']);
    }
}

if ($action === 'kalem-ekle') {
    $ad = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['ad'] ?? '')));
    $renk = (string) ($_POST['renk'] ?? '');
    if (!in_array($renk, MaliyetOzelKalemModel::RENKLER, true)) {
        $renk = MaliyetOzelKalemModel::RENKLER[0];
    }
    if (mb_strlen($ad) < 2 || mb_strlen($ad) > 60) {
        $cevap(['status' => 'error', 'message' => 'Kalem adı 2 ile 60 karakter arasında olmalıdır.']);
    }
    $mevcutAdlar = array_map(static fn($c) => mb_strtolower($c['ad']), MaliyetRaporuModel::kalemler());
    if (in_array(mb_strtolower($ad), $mevcutAdlar, true) || mb_strtolower($ad) === 'rapora dahil etme') {
        $cevap(['status' => 'error', 'message' => 'Bu isimde bir maliyet kalemi zaten var.']);
    }
    try {
        $kod = (new MaliyetOzelKalemModel())->ekle($ad, $renk, $kullaniciId);
        $log('Maliyet Özel Kalem', sprintf('"%s" maliyet kalemi eklendi.', $ad));
        $cevap(['status' => 'success', 'message' => 'Maliyet kalemi eklendi.', 'kalem' => ['kod' => $kod, 'ad' => $ad, 'renk' => $renk]]);
    } catch (\Throwable $e) {
        error_log('kalem-ekle: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Maliyet kalemi eklenemedi.']);
    }
}

if ($action === 'eslestirme-listesi') {
    try {
        $Eslestirme = new MaliyetKalemEslestirmeModel();
        $liste = array_map(static function ($r) {
            return [
                'id'        => Security::encrypt((int) $r->id),
                'kaynak'    => $r->kaynak,
                'kaynak_adi' => MaliyetKalemEslestirmeModel::KAYNAKLAR[$r->kaynak] ?? $r->kaynak,
                'deger'     => $r->eslesme_degeri,
                'etiket'    => $r->eslesme_etiketi ?: $r->eslesme_degeri,
                'kalem'     => $r->kalem,
                'kalem_adi' => MaliyetRaporuModel::kalemAdi($r->kalem),
            ];
        }, $Eslestirme->liste());
        $cevap(['status' => 'success', 'data' => $liste, 'gelir_gider_kategorileri' => $Eslestirme->gelirGiderKategorileri()]);
    } catch (\Throwable $e) {
        error_log('eslestirme-listesi: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Eşleştirmeler yüklenemedi.']);
    }
}

if ($action === 'eslestirme-kaydet') {
    $kaynak = trim((string) ($_POST['kaynak'] ?? ''));
    $deger  = trim((string) ($_POST['deger'] ?? ''));
    $etiket = trim((string) ($_POST['etiket'] ?? '')) ?: null;
    $kalem  = trim((string) ($_POST['kalem'] ?? ''));

    if (!isset(MaliyetKalemEslestirmeModel::KAYNAKLAR[$kaynak]) || $deger === '' || mb_strlen($deger) > 255
        || !MaliyetRaporuModel::kalemGecerliMi($kalem, true)) {
        $cevap(['status' => 'error', 'message' => 'Kaynak, eşleşme değeri ve maliyet kalemi zorunludur.']);
    }
    if ($kaynak === 'fatura' && !preg_match('/^\d{10,11}$/', $deger)) {
        $cevap(['status' => 'error', 'message' => 'Fatura eşleştirmesi için 10 veya 11 haneli VKN/TCKN giriniz.']);
    }
    if ($etiket !== null) {
        $etiket = mb_substr($etiket, 0, 255);
    }

    try {
        (new MaliyetKalemEslestirmeModel())->kaydet($kaynak, $deger, $etiket, $kalem, $kullaniciId);
        $log('Maliyet Kalem Eşleştirme', sprintf('%s "%s" → %s olarak eşleştirildi.',
            MaliyetKalemEslestirmeModel::KAYNAKLAR[$kaynak], $etiket ?? $deger, MaliyetRaporuModel::kalemAdi($kalem)));
        $cevap(['status' => 'success', 'message' => 'Eşleştirme kaydedildi.']);
    } catch (\Throwable $e) {
        error_log('eslestirme-kaydet: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Eşleştirme kaydedilemedi.']);
    }
}

if ($action === 'eslestirme-toplu-kaydet') {
    $kalem = trim((string) ($_POST['kalem'] ?? ''));
    $vkns = (array) ($_POST['vkn'] ?? []);
    $etiketler = (array) ($_POST['etiket'] ?? []);
    if (!MaliyetRaporuModel::kalemGecerliMi($kalem, true)) {
        $cevap(['status' => 'error', 'message' => 'Geçerli bir maliyet kalemi seçiniz.']);
    }
    $kayitlar = [];
    foreach ($vkns as $i => $vkn) {
        $vkn = trim((string) $vkn);
        if (preg_match('/^\d{10,11}$/', $vkn)) {
            $kayitlar[$vkn] = mb_substr(trim((string) ($etiketler[$i] ?? '')), 0, 255);
        }
    }
    if (!$kayitlar || count($kayitlar) > 500) {
        $cevap(['status' => 'error', 'message' => 'Eşleştirilecek geçerli firma seçiniz (en fazla 500).']);
    }
    try {
        $adet = (new MaliyetKalemEslestirmeModel())->topluKaydet($kayitlar, $kalem, $kullaniciId);
        $log('Maliyet Kalem Toplu Eşleştirme', sprintf('%d firma %s kalemine toplu eşleştirildi.', $adet, MaliyetRaporuModel::kalemAdi($kalem)));
        $cevap(['status' => 'success', 'message' => $adet . ' firma eşleştirildi.', 'adet' => $adet]);
    } catch (\Throwable $e) {
        error_log('eslestirme-toplu-kaydet: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Toplu eşleştirme yapılamadı.']);
    }
}

if ($action === 'eslestirme-sil') {
    $id = (int) Security::decrypt($_POST['id'] ?? '');
    try {
        $Eslestirme = new MaliyetKalemEslestirmeModel();
        $kayit = $id > 0 ? $Eslestirme->bul($id) : null;
        if (!$kayit || !$Eslestirme->softDelete($id)) {
            $cevap(['status' => 'error', 'message' => 'Eşleştirme bulunamadı.']);
        }
        $log('Maliyet Kalem Eşleştirme Silme', sprintf('"%s" eşleştirmesi (%s) kaldırıldı.',
            $kayit->eslesme_etiketi ?: $kayit->eslesme_degeri, MaliyetRaporuModel::kalemAdi($kayit->kalem)));
        $cevap(['status' => 'success', 'message' => 'Eşleştirme kaldırıldı.']);
    } catch (\Throwable $e) {
        error_log('eslestirme-sil: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Eşleştirme kaldırılamadı.']);
    }
}

if ($action === 'fatura-kalem-ata') {
    $faturaId = (int) Security::decrypt($_POST['id'] ?? '');
    $kalem = trim((string) ($_POST['kalem'] ?? ''));
    if ($kalem !== '' && !MaliyetRaporuModel::kalemGecerliMi($kalem, true)) {
        $cevap(['status' => 'error', 'message' => 'Geçersiz maliyet kalemi.']);
    }
    try {
        $Eslestirme = new MaliyetKalemEslestirmeModel();
        $fatura = $faturaId > 0 ? $Eslestirme->faturaBul($faturaId) : null;
        if (!$fatura) {
            $cevap(['status' => 'error', 'message' => 'Fatura bulunamadı.']);
        }
        $Eslestirme->faturaKalemAta($faturaId, $kalem ?: null, $kullaniciId);
        $log('Maliyet Fatura Kalem Atama', sprintf('%s nolu fatura (%s) için maliyet kalemi: %s',
            $fatura->fatura_no, $fatura->unvan, $kalem ? MaliyetRaporuModel::kalemAdi($kalem) : 'firma kuralına bırakıldı'));
        $cevap(['status' => 'success', 'message' => 'Fatura maliyet kalemi güncellendi.']);
    } catch (\Throwable $e) {
        error_log('fatura-kalem-ata: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Fatura kalemi güncellenemedi.']);
    }
}

$ManuelGider = new ManuelGiderModel();

if ($action === 'manuel-gider-kaydet') {
    $id = (int) Security::decrypt($_POST['manuel_gider_id'] ?? '');
    $kalem = trim((string) ($_POST['kategori'] ?? ''));
    $tutar = Helper::formattedMoneyToNumber($_POST['tutar'] ?? '');
    $tarih = Date::Ymd($_POST['tarih'] ?? '');

    if (!MaliyetRaporuModel::kalemGecerliMi($kalem) || (float) $tutar <= 0 || !$tarih) {
        $cevap(['status' => 'error', 'message' => 'Maliyet kalemi, tutar ve tarih zorunludur.']);
    }

    try {
        $data = [
            'kategori'     => $kalem,
            'alt_kategori' => trim((string) ($_POST['alt_kategori'] ?? '')) ?: null,
            'tutar'        => $tutar,
            'tarih'        => $tarih,
            'aciklama'     => trim((string) ($_POST['aciklama'] ?? '')) ?: null,
            'belge_no'     => trim((string) ($_POST['belge_no'] ?? '')) ?: null,
        ];

        if ($id > 0) {
            if (!$ManuelGider->getDetailById($id)) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
            }
            $ManuelGider->updateById($id, $data);
            $lastInsertId = Security::encrypt($id);
        } else {
            $lastInsertId = $ManuelGider->create($data);
        }

        $log('Maliyet Manuel Gider', sprintf('%s manuel gider %s: %s ₺ (%s)',
            MaliyetRaporuModel::kalemAdi($kalem), $id > 0 ? 'güncellendi' : 'eklendi',
            number_format((float) $tutar, 2, ',', '.'), $tarih));
        $cevap(['status' => 'success', 'message' => 'Gider kaydı başarıyla kaydedildi.', 'id' => $lastInsertId]);
    } catch (\Throwable $e) {
        error_log('manuel-gider-kaydet: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Gider kaydı kaydedilemedi.']);
    }
}

if ($action === 'manuel-gider-sil') {
    $id = (int) Security::decrypt($_POST['id'] ?? '');
    try {
        $kayit = $id > 0 ? $ManuelGider->getDetailById($id) : null;
        if (!$kayit) {
            $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
        }
        $ManuelGider->softDelete($id);
        $log('Maliyet Manuel Gider Silme', sprintf('%s manuel gider silindi: %s ₺',
            MaliyetRaporuModel::kalemAdi($kayit->kategori), number_format((float) $kayit->tutar, 2, ',', '.')));
        $cevap(['status' => 'success', 'message' => 'Kayıt silindi.']);
    } catch (\Throwable $e) {
        error_log('manuel-gider-sil: ' . $e->getMessage());
        $cevap(['status' => 'error', 'message' => 'Kayıt silinemedi.']);
    }
}

if ($action === 'manuel-gider-detay') {
    $id = (int) Security::decrypt($_POST['id'] ?? '');
    try {
        $kayit = $id > 0 ? $ManuelGider->getDetailById($id) : null;
        if ($kayit) {
            $kayit->enc_id = Security::encrypt($kayit->id);
            unset($kayit->id, $kayit->firma_id, $kayit->olusturan_kullanici_id);
            $cevap(['status' => 'success', 'data' => $kayit]);
        }
        $cevap(['status' => 'error', 'data' => null]);
    } catch (\Throwable $e) {
        error_log('manuel-gider-detay: ' . $e->getMessage());
        $cevap(['status' => 'error', 'data' => null]);
    }
}

$cevap(['status' => 'error', 'message' => 'Geçersiz işlem.']);
