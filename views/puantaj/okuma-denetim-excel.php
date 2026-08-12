<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Helper\Date;
use App\Model\EndeksOkumaModel;
use App\Model\FirmaModel;
use App\Model\MenuModel;
use App\Model\SystemLogModel;
use App\Service\OkumaDenetimService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if (empty($_SESSION['loggedin']) || $currentUserId <= 0 || !isset($_SESSION['firma_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Bu işlem için oturum açmanız gerekiyor.');
}

try {
    $Menus = new MenuModel();
    if (!$Menus->userCanAccessMenuLink($currentUserId, 'puantaj/okuma-denetim')) {
        header('HTTP/1.1 403 Forbidden');
        exit('Bu raporu indirme yetkiniz bulunmuyor.');
    }
} catch (Exception $e) {
    error_log('okuma-denetim-excel yetki kontrolu hatasi: ' . $e->getMessage());
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

$baslangic = $tarihNormalize($_GET['start_date'] ?? '', date('Y-m-d', strtotime('-29 days')));
$bitis = $tarihNormalize($_GET['end_date'] ?? '', date('Y-m-d'));

if (strtotime($baslangic) > strtotime($bitis)) {
    [$baslangic, $bitis] = [$bitis, $baslangic];
}
if ((int) round((strtotime($bitis) - strtotime($baslangic)) / 86400) > 180) {
    $baslangic = date('Y-m-d', strtotime($bitis . ' -180 days'));
}

$bolge = trim($_GET['bolge'] ?? '');
$ekipKoduId = trim($_GET['ekip_kodu_id'] ?? '');
$arama = trim($_GET['arama'] ?? '');
$gorunum = $_GET['gorunum'] ?? 'tumu';
$dusukVerimEsigi = (int) ($_GET['dusuk_verim_esigi'] ?? 50);
$evdeYokEsigi = (int) ($_GET['evde_yok_esigi'] ?? 35);
$haftaSonuDahil = !empty($_GET['hafta_sonu_dahil']);

try {
    $EndeksOkuma = new EndeksOkumaModel();
    $Denetim = new OkumaDenetimService($dusukVerimEsigi, $evdeYokEsigi, $haftaSonuDahil);

    $ekipGunler = $EndeksOkuma->getDenetimEkipGun($baslangic, $bitis, $bolge, $ekipKoduId, $arama);
    $tanimliEkipler = $EndeksOkuma->getDenetimTanimliEkipler();
    $sayacKirilim = $EndeksOkuma->getDenetimSayacDurumKirilim($baslangic, $bitis, $bolge, $ekipKoduId);

    if ($bolge !== '' || $ekipKoduId !== '' || $arama !== '') {
        $gorunenEkipIdleri = array_unique(array_map(fn($s) => (int) $s->ekip_kodu_id, $ekipGunler));
        $tanimliEkipler = array_values(array_filter(
            $tanimliEkipler,
            fn($e) => in_array((int) $e->id, $gorunenEkipIdleri, true)
        ));
    }

    $ekipler = $Denetim->analizEt($ekipGunler, $tanimliEkipler, $baslangic, $bitis);
    $bolgeOzeti = $Denetim->bolgeOzeti($ekipler);

    $okumasizEkipler = array_filter($ekipler, fn($e) => $e['calisilan_gun'] === 0);
    $aktifEkipler = array_filter($ekipler, fn($e) => $e['calisilan_gun'] > 0);

    if ($gorunum === 'supheli') {
        $aktifEkipler = array_filter($aktifEkipler, fn($e) => ($e['supheli_gun'] + $e['kritik_gun']) > 0);
    } elseif ($gorunum === 'kritik') {
        $aktifEkipler = array_filter($aktifEkipler, fn($e) => $e['kritik_gun'] > 0);
    } elseif ($gorunum === 'okumasiz') {
        $aktifEkipler = array_filter($aktifEkipler, fn($e) => $e['okumasiz_gun_sayisi'] > 0);
    }

    $Firma = new FirmaModel();
    $firma = $Firma->getFirma($_SESSION['firma_id'] ?? 0);
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
    $spreadsheet->getProperties()
        ->setCreator($firmaAdi)
        ->setTitle('Okuma Denetim Raporu');

    $sayfaOlustur = function (Spreadsheet $kitap, $sira, $ad, array $basliklar, array $satirlar, $bilgiSatiri)
        use ($baslikStili, $veriStili) {
        $sayfa = $sira === 0 ? $kitap->getActiveSheet() : $kitap->createSheet();
        $sayfa->setTitle($ad);

        $sayfa->setCellValue('A1', $bilgiSatiri);
        $sayfa->getStyle('A1')->getFont()->setBold(true)->setSize(11);

        $sutunSayisi = count($basliklar);
        $sonSutun = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sutunSayisi);

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
            $harf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sayfa->getColumnDimension($harf)->setAutoSize(true);
        }

        return $sayfa;
    };

    $filtreBilgisi = $firmaAdi . ' — Okuma Denetim Raporu | '
        . Date::dmY($baslangic) . ' - ' . Date::dmY($bitis)
        . ' | Düşük verim eşiği: %' . $dusukVerimEsigi
        . ' | Evde yok eşiği: %' . $evdeYokEsigi
        . ' | Hafta sonu: ' . ($haftaSonuDahil ? 'iş günü sayılıyor' : 'hariç')
        . ($bolge !== '' ? ' | Bölge: ' . $bolge : '')
        . ($arama !== '' ? ' | Arama: ' . $arama : '');

    $ekipOzetSatirlari = [];
    foreach ($aktifEkipler as $ekip) {
        $ekipOzetSatirlari[] = [
            $ekip['gosterilecek_bolge'],
            $ekip['ekip_adi'],
            $ekip['personeller'],
            $ekip['calisilan_gun'],
            $ekip['okumasiz_gun_sayisi'],
            $ekip['toplam_abone'],
            $ekip['okunan_abone'],
            $ekip['evde_yok_abone'],
            $ekip['arizali_abone'],
            $ekip['idari_abone'],
            $ekip['gunluk_ortalama'],
            $ekip['referans'],
            $ekip['okuma_orani'],
            $ekip['evde_yok_orani'],
            $ekip['supheli_gun'],
            $ekip['kritik_gun'],
            implode(', ', array_keys($ekip['bolgeler'])),
            $ekip['listede_var'] ? 'Evet' : 'Hayır',
        ];
    }

    $sayfaOlustur($spreadsheet, 0, 'Ekip Özeti', [
        'Bölge', 'Ekip', 'Personel', 'Çalışılan Gün', 'Okumasız İş Günü',
        'Toplam Okuma', 'Endeks Alınan', 'Evde Yok', 'Arızalı', 'İdari',
        'Günlük Ortalama', 'Referans (Medyan)', 'Okuma Oranı %', 'Evde Yok %',
        'Şüpheli Gün', 'Kritik Gün', 'Okunan Bölgeler', 'Ekip Tanımında Var mı',
    ], $ekipOzetSatirlari, $filtreBilgisi);

    $supheliSatirlar = [];
    foreach ($aktifEkipler as $ekip) {
        foreach ($ekip['gunler'] as $tarih => $gun) {
            if ($gun['seviye'] === OkumaDenetimService::SEVIYE_TEMIZ) {
                continue;
            }
            $supheliSatirlar[] = [
                $ekip['gosterilecek_bolge'],
                $ekip['ekip_adi'],
                $ekip['personeller'],
                Date::dmY($tarih),
                Date::gunAdi($tarih),
                $gun['seviye'] === OkumaDenetimService::SEVIYE_KRITIK ? 'Kritik' : 'Şüpheli',
                implode(' | ', array_column($gun['bayraklar'], 'etiket')),
                implode(' ', array_column($gun['bayraklar'], 'aciklama')),
                $gun['toplam_abone'],
                $ekip['referans'],
                $gun['okunan_abone'],
                $gun['evde_yok_abone'],
                $gun['evde_yok_orani'],
                $gun['defter_sayisi'],
                $gun['okunan_bolgeler'],
            ];
        }
    }

    $sayfaOlustur($spreadsheet, 1, 'Şüpheli Günler', [
        'Bölge', 'Ekip', 'Personel', 'Tarih', 'Gün', 'Seviye', 'Bayraklar', 'Açıklama',
        'Gün Toplamı', 'Referans', 'Endeks Alınan', 'Evde Yok', 'Evde Yok %',
        'Defter Sayısı', 'Okunan Bölgeler',
    ], $supheliSatirlar, $filtreBilgisi);

    $gunDokumSatirlari = [];
    foreach ($aktifEkipler as $ekip) {
        foreach ($ekip['gunler'] as $tarih => $gun) {
            $gunDokumSatirlari[] = [
                $ekip['gosterilecek_bolge'],
                $ekip['ekip_adi'],
                $ekip['personeller'],
                Date::dmY($tarih),
                Date::gunAdi($tarih),
                $gun['hafta_sonu'] ? 'Evet' : 'Hayır',
                $gun['toplam_abone'],
                $gun['okunan_abone'],
                $gun['evde_yok_abone'],
                $gun['arizali_abone'],
                $gun['idari_abone'],
                $gun['okuma_orani'],
                $gun['evde_yok_orani'],
                $gun['defter_sayisi'],
                $gun['personel_sayisi'],
                $gun['okunan_bolgeler'],
                $gun['seviye'] === OkumaDenetimService::SEVIYE_KRITIK ? 'Kritik'
                    : ($gun['seviye'] === OkumaDenetimService::SEVIYE_SUPHELI ? 'Şüpheli' : 'Normal'),
            ];
        }
    }

    $sayfaOlustur($spreadsheet, 2, 'Gün Dökümü', [
        'Bölge', 'Ekip', 'Personel', 'Tarih', 'Gün', 'Hafta Sonu',
        'Toplam Okuma', 'Endeks Alınan', 'Evde Yok', 'Arızalı', 'İdari',
        'Okuma Oranı %', 'Evde Yok %', 'Defter Sayısı', 'Personel Sayısı',
        'Okunan Bölgeler', 'Durum',
    ], $gunDokumSatirlari, $filtreBilgisi);

    $bolgeSatirlari = [];
    foreach ($bolgeOzeti as $satir) {
        $bolgeSatirlari[] = [
            $satir['bolge'],
            $satir['ekip_sayisi'],
            $satir['calisilan_gun'],
            $satir['toplam_abone'],
            $satir['okunan_abone'],
            $satir['evde_yok_abone'],
            $satir['gunluk_ortalama'],
            $satir['evde_yok_orani'],
            $satir['supheli_gun'],
            $satir['kritik_gun'],
            $satir['okumasiz_gun'],
        ];
    }

    $sayfaOlustur($spreadsheet, 3, 'Bölge Özeti', [
        'Bölge', 'Ekip Sayısı', 'Ekip-Gün', 'Toplam Okuma', 'Endeks Alınan',
        'Evde Yok', 'Günlük Ortalama', 'Evde Yok %', 'Şüpheli Gün', 'Kritik Gün',
        'Okumasız İş Günü',
    ], $bolgeSatirlari, $filtreBilgisi);

    $okumasizSatirlar = [];
    foreach ($okumasizEkipler as $ekip) {
        $okumasizSatirlar[] = [
            $ekip['gosterilecek_bolge'],
            $ekip['ekip_adi'],
            $ekip['personeller'],
            $ekip['listede_var'] ? 'Evet' : 'Hayır',
            'Seçilen aralıkta hiç okuma kaydı yok',
        ];
    }
    foreach ($aktifEkipler as $ekip) {
        if (empty($ekip['okumasiz_gunler'])) {
            continue;
        }
        $okumasizSatirlar[] = [
            $ekip['gosterilecek_bolge'],
            $ekip['ekip_adi'],
            $ekip['personeller'],
            $ekip['listede_var'] ? 'Evet' : 'Hayır',
            count($ekip['okumasiz_gunler']) . ' iş gününde okuma yok: '
                . implode(', ', array_map(fn($g) => Date::dmY($g), $ekip['okumasiz_gunler'])),
        ];
    }

    $sayfaOlustur($spreadsheet, 4, 'Okuma Yapmayan Ekipler', [
        'Bölge', 'Ekip', 'Personel', 'Ekip Tanımında Var mı', 'Durum',
    ], $okumasizSatirlar, $filtreBilgisi);

    $ekipAdlari = [];
    foreach ($aktifEkipler as $id => $ekip) {
        $ekipAdlari[$id] = $ekip;
    }

    $sayacSatirlari = [];
    foreach ($sayacKirilim as $satir) {
        $id = (int) $satir->ekip_kodu_id;
        if (!isset($ekipAdlari[$id])) {
            continue;
        }
        $sayacSatirlari[] = [
            $ekipAdlari[$id]['gosterilecek_bolge'],
            $ekipAdlari[$id]['ekip_adi'],
            Date::dmY($satir->tarih),
            $satir->sayac_durum,
            (int) $satir->adet,
        ];
    }

    $sayfaOlustur($spreadsheet, 5, 'Sayaç Durumu', [
        'Bölge', 'Ekip', 'Tarih', 'Sayaç Durumu', 'Abone Sayısı',
    ], $sayacSatirlari, $filtreBilgisi);

    $spreadsheet->setActiveSheetIndex(0);

    try {
        (new SystemLogModel())->logAction(
            $currentUserId,
            'Okuma Denetim Raporu - Excel',
            "Okuma denetim raporu Excel olarak indirildi. Aralık: $baslangic - $bitis, "
                . "Bölge: " . ($bolge !== '' ? $bolge : 'Tümü') . ", "
                . count($aktifEkipler) . " ekip, " . count($supheliSatirlar) . " şüpheli gün.",
            SystemLogModel::LEVEL_INFO
        );
    } catch (Exception $e) {
        error_log('okuma-denetim-excel log hatasi: ' . $e->getMessage());
    }

    $dosyaAdi = 'okuma-denetim-' . date('Ymd-Hi') . '.xlsx';

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log('okuma-denetim-excel hatasi: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Rapor oluşturulamadı. Lütfen sistem yöneticisine başvurun.');
}
