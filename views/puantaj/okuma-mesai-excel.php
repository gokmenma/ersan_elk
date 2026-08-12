<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Helper\Date;
use App\Model\FirmaModel;
use App\Model\MenuModel;
use App\Model\OkumaDetayModel;
use App\Model\SystemLogModel;
use App\Service\OkumaMesaiAnalizService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

if (empty($_SESSION['loggedin']) || $currentUserId <= 0 || $firmaId <= 0) {
    header('HTTP/1.1 403 Forbidden');
    exit('Bu işlem için oturum açmanız gerekiyor.');
}

try {
    if (!(new MenuModel())->userCanAccessMenuLink($currentUserId, 'puantaj/okuma-denetim')) {
        header('HTTP/1.1 403 Forbidden');
        exit('Bu raporu indirme yetkiniz bulunmuyor.');
    }
} catch (Exception $e) {
    error_log('okuma-mesai-excel yetki hatasi: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('İşlem tamamlanamadı.');
}

$tarihNormalize = function ($deger, $varsayilan) {
    $deger = trim((string) $deger);
    if ($deger === '') {
        return $varsayilan;
    }
    foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $bicim) {
        $nesne = DateTime::createFromFormat($bicim, $deger);
        if ($nesne && $nesne->format($bicim) === $deger) {
            return $nesne->format('Y-m-d');
        }
    }
    return $varsayilan;
};

try {
    ini_set('memory_limit', '512M');
    set_time_limit(600);

    $Model = new OkumaDetayModel();
    $aralik = $Model->getTarihAraligi($firmaId);

    $baslangic = $tarihNormalize($_GET['start_date'] ?? '', $aralik->ilk ?? date('Y-m-d'));
    $bitis = $tarihNormalize($_GET['end_date'] ?? '', $aralik->son ?? date('Y-m-d'));

    if (strtotime($baslangic) > strtotime($bitis)) {
        [$baslangic, $bitis] = [$bitis, $baslangic];
    }

    $bolge = trim($_GET['bolge'] ?? '');
    $ekipKodu = trim($_GET['ekip_kodu'] ?? '');
    $arama = trim($_GET['arama'] ?? '');
    $gorunum = $_GET['gorunum'] ?? 'tumu';
    $tumSatirlar = !empty($_GET['tum_satirlar']);

    $Analiz = new OkumaMesaiAnalizService($_GET['esik'] ?? 30);
    $esikDakika = $Analiz->esikDakika();

    $okumalar = $Model->getOkumalar($firmaId, $baslangic, $bitis, $bolge, $ekipKodu, $arama);
    $ekipTanimlari = $Model->getEkipEslesmeleri();

    $sonuclar = $Analiz->analizEt($okumalar, $ekipTanimlari);
    $bolgeOzeti = $Analiz->bolgeOzeti($sonuclar);

    if ($gorunum === 'supheli') {
        $sonuclar = array_values(array_filter($sonuclar, fn($s) => !empty($s['bosluklar'])));
    } elseif ($gorunum === 'kritik') {
        $sonuclar = array_values(array_filter($sonuclar, fn($s) => $s['kritik_bosluk'] > 0));
    }

    $Firma = new FirmaModel();
    $firma = $Firma->getFirma($firmaId);
    $firmaAdi = $firma->firma_adi ?? 'Er-San Elektrik';

    $baslikStili = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ];

    $veriStili = [
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ];

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()->setCreator($firmaAdi)->setTitle('Okuma Mesai Denetimi');

    $sayfaSayaci = 0;
    $sayfaOlustur = function (array $basliklar, array $satirlar, $ad, $bilgi)
        use ($spreadsheet, &$sayfaSayaci, $baslikStili, $veriStili) {
        $sayfa = $sayfaSayaci === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sayfaSayaci++;
        $sayfa->setTitle($ad);

        $sayfa->setCellValue('A1', $bilgi);
        $sayfa->getStyle('A1')->getFont()->setBold(true)->setSize(11);

        $sutunSayisi = count($basliklar);
        $sonSutun = Coordinate::stringFromColumnIndex($sutunSayisi);

        $sayfa->fromArray($basliklar, null, 'A3');
        $sayfa->getStyle('A3:' . $sonSutun . '3')->applyFromArray($baslikStili);
        $sayfa->getRowDimension(3)->setRowHeight(28);

        if (!empty($satirlar)) {
            $sayfa->fromArray($satirlar, null, 'A4');
            $sonSatir = 3 + count($satirlar);
            $sayfa->getStyle('A4:' . $sonSutun . $sonSatir)->applyFromArray($veriStili);
        } else {
            $sayfa->setCellValue('A4', 'Bu kriterlerde kayıt bulunamadı.');
            $sonSatir = 4;
        }

        $sayfa->setAutoFilter('A3:' . $sonSutun . $sonSatir);
        $sayfa->freezePane('A4');

        for ($i = 1; $i <= $sutunSayisi; $i++) {
            $sayfa->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    };

    $bilgi = $firmaAdi . ' — Okuma Mesai Denetimi | '
        . Date::dmY($baslangic) . ' - ' . Date::dmY($bitis)
        . ' | Şüpheli boşluk eşiği: ' . $esikDakika . ' dk'
        . ' | Kritik: ' . ($esikDakika * 2) . ' dk üzeri'
        . ($bolge !== '' ? ' | Bölge: ' . $bolge : '')
        . ($ekipKodu !== '' ? ' | Ekip: ' . $ekipKodu : '');

    $sure = fn($sn) => OkumaMesaiAnalizService::sureMetni($sn);

    $ekipSatirlari = [];
    foreach ($sonuclar as $satir) {
        $ekipSatirlari[] = [
            !empty($satir['bolgeler']) ? (string) array_key_first($satir['bolgeler']) : 'TANIMSIZ',
            $satir['ekip_kodu'],
            $satir['ekip_adi'],
            Date::dmY($satir['tarih']),
            Date::gunAdi($satir['tarih']),
            date('H:i', $satir['ilk_okuma']),
            date('H:i', $satir['son_okuma']),
            $sure($satir['sahada_sure']),
            round($satir['sahada_sure'] / 3600, 2),
            count($satir['bosluklar']),
            $satir['kritik_bosluk'],
            $sure($satir['bosluk_toplami']),
            round($satir['bosluk_toplami'] / 3600, 2),
            $sure($satir['net_calisma']),
            round($satir['net_calisma'] / 3600, 2),
            $satir['okuma_sayisi'],
            $satir['okuma_hizi'],
            implode(', ', array_keys($satir['bolgeler'])),
        ];
    }

    $sayfaOlustur([
        'Bölge', 'Ekip Kodu', 'Ekip Adı', 'Tarih', 'Gün', 'İlk Okuma', 'Son Okuma',
        'Sahada Süre', 'Sahada (saat)', 'Boşluk Sayısı', 'Kritik Boşluk',
        'Toplam Boşluk', 'Boşluk (saat)', 'Net Çalışma', 'Net (saat)',
        'Okuma Sayısı', 'Okuma/Saat', 'Okunan Bölgeler',
    ], $ekipSatirlari, 'Ekip Özeti', $bilgi);

    $boslukSatirlari = [];
    foreach ($sonuclar as $satir) {
        foreach ($satir['bosluklar'] as $bosluk) {
            $boslukSatirlari[] = [
                !empty($satir['bolgeler']) ? (string) array_key_first($satir['bolgeler']) : 'TANIMSIZ',
                $satir['ekip_kodu'],
                $satir['ekip_adi'],
                Date::dmY($satir['tarih']),
                date('H:i', $bosluk['baslangic']),
                date('H:i', $bosluk['bitis']),
                $sure($bosluk['sure']),
                round($bosluk['sure'] / 60),
                $bosluk['seviye'] === OkumaMesaiAnalizService::SEVIYE_KRITIK ? 'Kritik' : 'Şüpheli',
                $bosluk['onceki']['abone_no'],
                $bosluk['onceki']['abone_adsoyad'],
                $bosluk['onceki']['mahalle'],
                $bosluk['onceki']['defter'],
                $bosluk['onceki']['sayfa'],
                $bosluk['onceki']['sira_no'],
                $bosluk['sonraki']['abone_no'],
                $bosluk['sonraki']['abone_adsoyad'],
                $bosluk['sonraki']['mahalle'],
                $bosluk['sonraki']['defter'],
                $bosluk['sonraki']['sayfa'],
                $bosluk['sonraki']['sira_no'],
            ];
        }
    }

    $sayfaOlustur([
        'Bölge', 'Ekip Kodu', 'Ekip Adı', 'Tarih', 'Başlangıç', 'Bitiş', 'Süre', 'Süre (dk)', 'Seviye',
        'Önceki Abone No', 'Önceki Abone', 'Önceki Mahalle', 'Önceki Defter', 'Önceki Sayfa', 'Önceki Sıra',
        'Sonraki Abone No', 'Sonraki Abone', 'Sonraki Mahalle', 'Sonraki Defter', 'Sonraki Sayfa', 'Sonraki Sıra',
    ], $boslukSatirlari, 'Şüpheli Boşluklar', $bilgi);

    $bolgeSatirlari = [];
    foreach ($bolgeOzeti as $satir) {
        $bolgeSatirlari[] = [
            $satir['bolge'],
            $satir['ekip_sayisi'],
            $satir['ekip_gun'],
            $satir['okuma_sayisi'],
            $satir['bosluk_sayisi'],
            $satir['kritik_bosluk'],
            $sure($satir['bosluk_suresi']),
            $sure($satir['sahada_sure']),
            $sure($satir['net_calisma']),
            $satir['bosluk_orani'],
        ];
    }

    $sayfaOlustur([
        'Bölge', 'Ekip Sayısı', 'Ekip-Gün', 'Toplam Okuma', 'Şüpheli Boşluk', 'Kritik Boşluk',
        'Toplam Boşluk Süresi', 'Sahada Süre', 'Net Süre', 'Boşluk Oranı %',
    ], $bolgeSatirlari, 'Bölge Özeti', $bilgi);

    $dosyaSatirlari = [];
    foreach ($Model->getDosyalar($firmaId) as $dosya) {
        $dosyaSatirlari[] = [
            $dosya->orijinal_adi,
            (int) $dosya->mevcut_satir,
            (int) $dosya->atlanan_tarih,
            (int) $dosya->atlanan_tekrar,
            $dosya->ilk_tarih ? Date::dmY($dosya->ilk_tarih) : '',
            $dosya->son_tarih ? Date::dmY($dosya->son_tarih) : '',
            $dosya->durum === 'hatali' ? 'Hatalı' : 'Başarılı',
            $dosya->hata_mesaji,
            $dosya->yukleyen_adi,
            Date::dmYHis($dosya->kayit_tarihi),
        ];
    }

    $sayfaOlustur([
        'Dosya Adı', 'Kullanılan Satır', 'Tarihi Okunamayan', 'Tekrar Olduğu İçin Atlanan',
        'İlk Tarih', 'Son Tarih', 'Durum', 'Hata Mesajı', 'Yükleyen', 'Yükleme Zamanı',
    ], $dosyaSatirlari, 'Yüklenen Dosyalar', $bilgi);

    if ($tumSatirlar) {
        $okumaSatirlari = [];
        foreach ($sonuclar as $satir) {
            $onceki = null;
            foreach ($satir['okumalar'] as $okuma) {
                $zaman = strtotime($okuma->okuma_zamani);
                $fark = $onceki === null ? null : $zaman - $onceki;
                $onceki = $zaman;

                $okumaSatirlari[] = [
                    $okuma->bolge,
                    $satir['ekip_kodu'],
                    $satir['ekip_adi'],
                    Date::dmY($okuma->tarih),
                    date('H:i:s', $zaman),
                    $fark === null ? '' : round($fark / 60),
                    $okuma->abone_no,
                    $okuma->abone_adsoyad,
                    $okuma->mahalle,
                    $okuma->defter,
                    $okuma->sayfa,
                    $okuma->sira_no,
                    $okuma->sayac_durum,
                ];
            }
        }

        $sayfaOlustur([
            'Bölge', 'Ekip Kodu', 'Ekip Adı', 'Tarih', 'Okuma Saati', 'Önceki Okumadan Fark (dk)',
            'Abone No', 'Abone', 'Mahalle', 'Defter', 'Sayfa', 'Sıra No', 'Sayaç Durumu',
        ], $okumaSatirlari, 'Tüm Okumalar', $bilgi);
    }

    $spreadsheet->setActiveSheetIndex(0);

    try {
        (new SystemLogModel())->logAction(
            $currentUserId,
            'Okuma Mesai Denetimi - Excel',
            "Okuma mesai raporu indirildi. Aralık: $baslangic - $bitis, eşik: $esikDakika dk, "
                . count($sonuclar) . " ekip-gün, " . count($boslukSatirlari) . " boşluk.",
            SystemLogModel::LEVEL_INFO
        );
    } catch (Exception $e) {
        error_log('okuma-mesai-excel log hatasi: ' . $e->getMessage());
    }

    $dosyaAdi = 'okuma-analizi-' . date('Ymd-Hi') . '.xlsx';

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');

    (new Xlsx($spreadsheet))->save('php://output');
    exit;

} catch (Exception $e) {
    error_log('okuma-mesai-excel hatasi: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Rapor oluşturulamadı. Lütfen sistem yöneticisine başvurun.');
}
