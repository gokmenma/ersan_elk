<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\AracZimmetModel;
use App\Model\PersonelIcralariModel;
use App\Model\PersonelModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;

$departman = $personel->departman ?? '';
$gorev = $personel->gorev ?? '';

$isEndeksOkuma = stripos($departman, 'Endeks Okuma') !== false;
$isBuro = stripos($departman, 'BÜRO') !== false || stripos($departman, 'Büro') !== false;
$isSef = stripos($gorev, 'Şef') !== false;
$isKesmeAcma = stripos($departman, 'Kesme-Açma') !== false || stripos($departman, 'Kesme Açma') !== false;
$isKacakKontrol = stripos($departman, 'Kaçak') !== false;

$personelModel = new PersonelModel();

if ((string) ($_GET['action'] ?? '') === 'performans') {
    if ($isBuro) {
        mobile_fail('Performans özeti bu personel için kullanılamıyor.', 403);
    }
    try {
        $okumaSefi = false;
        if ($isEndeksOkuma) {
            foreach ($personelModel->getEkipGecmisi($personelId) as $gecmis) {
                if ((int) ($gecmis->ekip_sefi_mi ?? 0) === 1 && (empty($gecmis->bitis_tarihi) || $gecmis->bitis_tarihi >= date('Y-m-d'))) {
                    $okumaSefi = true;
                    break;
                }
            }
        }
        $performans = (new \App\Model\PersonelPerformansModel())->getAnaSayfaPerformans($personel, (int) $personel->firma_id, $okumaSefi);
        $performans['son_guncelleme'] = \App\Helper\Helper::getLastUpdateDate('yapilan_isler');
        mobile_ok(['performans' => $performans]);
    } catch (\Throwable $e) {
        error_log('Mobil ana sayfa performans hatasi: ' . $e->getMessage());
        mobile_fail('Performans özeti şu anda alınamıyor.', 500);
    }
}

$isEkipSefi = false;
if ($isEndeksOkuma && $isSef) {
    try {
        foreach ($personelModel->getEkipGecmisi($personelId) as $gecmis) {
            $bitis = $gecmis->bitis_tarihi ?? null;
            if ((int) ($gecmis->ekip_sefi_mi ?? 0) === 1 && (empty($bitis) || $bitis >= date('Y-m-d'))) {
                $isEkipSefi = true;
                break;
            }
        }
    } catch (\Throwable $e) {
        error_log('Mobil ana sayfa ekip gecmisi hatasi: ' . $e->getMessage());
    }
}

$aktifArac = null;
try {
    $zimmet = (new AracZimmetModel())->getAktifZimmetByPersonel($personelId);
    if ($zimmet) {
        $aktifArac = [
            'zimmet_id' => (int) $zimmet->id,
            'plaka' => $zimmet->plaka ?? 'Zimmetli Araç',
            'zimmet_tarihi' => $zimmet->zimmet_tarihi ?? null,
        ];
    }
} catch (\Throwable $e) {
    error_log('Mobil ana sayfa arac zimmeti hatasi: ' . $e->getMessage());
}

$icraSayisi = 0;
try {
    $icralar = (new PersonelIcralariModel())->getDevamEdenIcralar($personelId);
    $icraSayisi = is_array($icralar) ? count($icralar) : 0;
} catch (\Throwable $e) {
    error_log('Mobil ana sayfa icra hatasi: ' . $e->getMessage());
}

$hasIcra = $icraSayisi > 0;

$katalog = [
    'izin' => ['İzin Talebi', 'Yeni izin planla', 'event-busy', '#6366f1', true],
    'yardim' => ['Destek Talebi', 'Yardım ve Destek', 'support-agent', '#10b981', true],
    'bordro' => ['Avanslar', 'Avans Talebi Yap', 'receipt-long', '#f97316', true],
    'zimmetler' => ['Zimmetler', 'Demirbaş Takibi', 'inventory', '#f59e0b', true],
    'ihbar' => ['İhbar Yap', 'Kaçak Su İhbarı', 'campaign', '#ef4444', true],
    'puantaj' => ['İş Takip', 'Saha İş ve Okuma Takibi', 'checklist', '#3b82f6', true],
    'talep' => ['Taleplerim', 'Talep ve Görevler', 'assignment', '#8b5cf6', true],
    'km-bildirimleri' => ['KM Bildirimi', 'Araç KM Girişi', 'speed', '#f43f5e', true],
    'etkinlikler' => ['Etkinlikler', 'Duyuru & Etkinlik', 'event', '#d946ef', true],
    'icralar' => ['İcralarım', 'İcra Dosya Takibi', 'gavel', '#a855f7', $hasIcra],
    'ekip-takibi' => ['Ekip Takibi', 'Saha Ekip Durumu', 'groups', '#06b6d4', $isEndeksOkuma && $isEkipSefi],
    'nobet' => ['Nöbet Çizelgesi', 'Nöbet Listesi', 'nights-stay', '#14b8a6', $isKesmeAcma],
    'aparat' => ['Aparat Takip', 'Kesme-Açma Kaydı', 'build', '#6366f1', $isKesmeAcma],
    'kacak' => ['Kaçak İşlemleri', 'Tutanak Bildirimi', 'policy', '#dc2626', true],
];

$kullanilabilir = [];
foreach ($katalog as $key => [$title, $desc, $icon, $color, $izinli]) {
    if ($izinli === true) {
        $kullanilabilir[$key] = [
            'key' => $key,
            'title' => $title,
            'desc' => $desc,
            'icon' => $icon,
            'color' => $color,
        ];
    }
}

$secilenler = [];
try {
    $secilenler = $personelModel->getHizliIslemler($personelId);
} catch (\Throwable $e) {
    error_log('Mobil ana sayfa hizli islem hatasi: ' . $e->getMessage());
    $secilenler = PersonelModel::DEFAULT_PWA_HIZLI_ISLEMLER;
}

$aktifHizliIslemler = [];
foreach ($secilenler as $key) {
    if (isset($kullanilabilir[$key])) {
        $aktifHizliIslemler[] = $kullanilabilir[$key];
    }
}

$saat = (int) date('H');
if ($saat < 12) {
    $selamlama = 'Günaydın!';
} elseif ($saat < 18) {
    $selamlama = 'İyi günler!';
} else {
    $selamlama = 'İyi akşamlar!';
}

mobile_ok([
    'personel' => mobile_personel_ozet($personel),
    'selamlama' => $selamlama,
    'roller' => [
        'endeks_okuma' => $isEndeksOkuma,
        'buro' => $isBuro,
        'sef' => $isSef,
        'ekip_sefi' => $isEkipSefi,
        'kesme_acma' => $isKesmeAcma,
        'kacak_kontrol' => $isKacakKontrol,
    ],
    'aktif_arac' => $aktifArac,
    'icra' => [
        'devam_eden_sayisi' => $icraSayisi,
    ],
    'hizli_islemler' => $aktifHizliIslemler,
    'hizli_islem_katalogu' => array_values($kullanilabilir),
]);
