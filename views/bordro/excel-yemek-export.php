<?php
/**
 * Yemek Bedeli Listesi Excel Export
 * Muhasebeye bildirilmek üzere yemek bedellerini içeren Excel dosyası oluşturur.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$donemId = $_GET['donem_id'] ?? null;
$ids = $_GET['ids'] ?? null;
$idArray = [];
if ($ids) {
    $idArray = explode(',', $ids);
    $idArray = array_filter(array_map('intval', $idArray));
}

if (!$donemId) {
    die('Dönem ID belirtilmelidir.');
}

try {
    $BordroPersonel = new BordroPersonelModel();
    $BordroDonem = new BordroDonemModel();
    $BordroParametre = new BordroParametreModel();

    // Dönem bilgisini al
    $donem = $BordroDonem->getDonemById($donemId);
    if (!$donem) {
        die('Dönem bulunamadı.');
    }

    // Asgari ücreti çek
    $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donem->baslangic_tarihi) ?? 17002.12;

    // Dönemdeki personelleri getir
    $personeller = $BordroPersonel->getPersonellerByDonem($donemId, $idArray);

    if (empty($personeller)) {
        die('Bu dönemde kriterlere uygun personel bulunmamaktadır.');
    }

    $yemekVerileri = [];

    foreach ($personeller as $p) {
        // Ortak hesaplama değerlerini al
        $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($p, $donem, floatval($asgariUcretNet));
        
        $nakitYemek = 0;
        $sodexoYemek = 0;
        $esYardimi = 0;
        $fiiliGun = intval($hesap['includedAllowanceFiiliGun'] ?? 0);
        
        // 1. Maaşa Dahil Yemek Yardımı (Nakit/Banka)
        if (isset($hesap['mealAllowanceDeduction']) && $hesap['mealAllowanceDeduction'] > 0) {
            $nakitYemek = $hesap['mealAllowanceDeduction'];
            
            $fiiliGun = intval($hesap['includedAllowanceFiiliGun'] ?? $fiiliGun);
        }
        
        // 2. Sodexo / Yemek Kartı Ödemeleri
        if (isset($hesap['sodexoOdemesi']) && $hesap['sodexoOdemesi'] > 0) {
            $sodexoYemek = $hesap['sodexoOdemesi'];
            
            // Eğer Maaşa Dahil değilse ama Sodexo varsa, fiili gün olarak puantajdaki çalışma gününü baz alabiliriz
            if ($nakitYemek <= 0) {
                if (isset($hesap['calismaGunu']) && $hesap['calismaGunu'] > 0) {
                    $fiiliGun = $hesap['calismaGunu'];
                }
            }
        }

        // 3. Eş Yardımı
        if (isset($hesap['spouseAllowanceDeduction']) && $hesap['spouseAllowanceDeduction'] > 0) {
            $esYardimi = $hesap['spouseAllowanceDeduction'];
        }

        // Avansları hesapla
        $avansToplam = 0;
        $kesintiler = $BordroPersonel->getDonemKesintileriListe($p->personel_id, $donemId);
        foreach ($kesintiler as $k) {
            $tur = mb_strtolower((string)($k->tur ?? ''), 'UTF-8');
            if (strpos($tur, 'avans') !== false) {
                $avansToplam += floatval($k->tutar);
            }
        }
        
        $icra = $hesap['icraKesintisi'] ?? 0;
        $toplamGun = $hesap['calismaGunu'] ?? 0;

        $htcGun = intval($hesap['htcGun'] ?? 0);
        $htcResmiTutar = $htcGun > 0 ? round(floatval($asgariUcretNet) / 30 * $htcGun, 2) : 0.0;
        $nominalMaas = floatval($hesap['maasTutari'] ?? 0);
        $htcEldenTutar = $htcGun > 0 ? round($nominalMaas / 30 * $htcGun, 2) : 0.0;
        $htcToplamTutar = round($htcEldenTutar + $htcResmiTutar, 2);

        $toplamAlacak = floatval($hesap['toplamAlacagi'] ?? 0);
        $toplamYasalKesinti = 0;
        if ($p->sgk_isci > 0) $toplamYasalKesinti += floatval($p->sgk_isci);
        if ($p->issizlik_isci > 0) $toplamYasalKesinti += floatval($p->issizlik_isci);
        if ($p->gelir_vergisi > 0) $toplamYasalKesinti += floatval($p->gelir_vergisi);
        if ($p->damga_vergisi > 0) $toplamYasalKesinti += floatval($p->damga_vergisi);
        
        $guncelKesintiGosterim = 0;
        $kesintiKayitlari = $BordroPersonel->getDonemKesintileriListe($p->personel_id, $donemId);
        foreach ($kesintiKayitlari as $k) {
            if ($k->tur !== 'izin_kesinti') {
                $guncelKesintiGosterim += floatval($k->tutar);
            }
        }
        $kesintiTutarOzet = round($toplamYasalKesinti + $guncelKesintiGosterim, 2);
        $gorunenNetMaas = max(0, round($toplamAlacak - $kesintiTutarOzet, 2));

        $yemekVerileri[] = [
            'tc_kimlik' => $p->tc_kimlik_no ?? '-',
            'adi_soyadi' => $p->adi_soyadi ?? '-',
            'toplam_gun' => $toplamGun,
            'fiili_gun' => $fiiliGun,
            'gunluk_nakit' => round($gunlukNakit, 2),
            'nakit_yemek' => $nakitYemek,
            'sodexo_yemek' => $sodexoYemek,
            'es_yardimi' => $esYardimi,
            'avans' => $avansToplam,
            'icra' => $icra,
            'resmi_alacak_asgari' => floatval($hesap['asgariHakedis'] ?? 0),
            'htc_calisma' => $htcToplamTutar,
            'fazla_mesai' => floatval($p->fazla_mesai_tutar ?? 0),
            'resmi_alacak_toplam' => floatval($hesap['resmiAlacagi'] ?? 0),
            'gelir_vergisi' => floatval($p->gelir_vergisi ?? 0),
            'net_maas' => $gorunenNetMaas
        ];
    }

    if (empty($yemekVerileri)) {
        die('Bu dönemde veri bulunan personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Muhasebe Listesi');

    // Başlıklar
    $basliklar = [
        'A' => 'TC KİMLİK NO',
        'B' => 'ADI SOYADI',
        'C' => 'TOPLAM GÜN',
        'D' => 'FİİLİ GÜN',
        'E' => 'GÜNLÜK YEMEK (NAKİT)',
        'F' => 'YEMEK (NAKİT/BANKA)',
        'G' => 'SODEXO / KART',
        'H' => 'EŞ YARDIMI',
        'I' => 'AVANS',
        'J' => 'İCRA',
        'K' => 'RESMİ ALACAK (ASGARİ ÜCRET)',
        'L' => 'HAFTA TATİLİ ÇALIŞMASI',
        'M' => 'FAZLA MESAİ',
        'N' => 'RESMİ ALACAK TOPLAMI',
        'O' => 'GELİR VERGİSİ KESİNTİSİ',
        'P' => 'ÖDENECEK NET MAAŞ'
    ];

    // Başlık stili
    $baslikStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4B5563'] // Koyu gri/lacivert tonu
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];

    // Başlıkları yaz
    foreach ($basliklar as $kolon => $baslik) {
        $sheet->setCellValue($kolon . '1', $baslik);
        $sheet->getColumnDimension($kolon)->setAutoSize(true);
    }

    // Başlık satırına stil uygula
    $sheet->getStyle('A1:P1')->applyFromArray($baslikStyle);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Veri stili
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];

    // Verileri ekle
    $satir = 2;
    foreach ($yemekVerileri as $veri) {
        $sheet->setCellValueExplicit('A' . $satir, $veri['tc_kimlik'], DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $satir, $veri['adi_soyadi']);
        $sheet->setCellValue('C' . $satir, $veri['toplam_gun']);
        $sheet->setCellValue('D' . $satir, $veri['fiili_gun']);
        $sheet->setCellValue('E' . $satir, $veri['gunluk_nakit']);
        $sheet->setCellValue('F' . $satir, $veri['nakit_yemek']);
        $sheet->setCellValue('G' . $satir, $veri['sodexo_yemek']);
        $sheet->setCellValue('H' . $satir, $veri['es_yardimi']);
        $sheet->setCellValue('I' . $satir, $veri['avans']);
        $sheet->setCellValue('J' . $satir, $veri['icra']);
        $sheet->setCellValue('K' . $satir, $veri['resmi_alacak_asgari']);
        $sheet->setCellValue('L' . $satir, $veri['htc_calisma']);
        $sheet->setCellValue('M' . $satir, $veri['fazla_mesai']);
        $sheet->setCellValue('N' . $satir, $veri['resmi_alacak_toplam']);
        $sheet->setCellValue('O' . $satir, $veri['gelir_vergisi']);
        $sheet->setCellValue('P' . $satir, $veri['net_maas']);

        // Formatlar
        $sheet->getStyle('C' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('F' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('G' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('H' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('I' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('J' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('K' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('L' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('M' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('N' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('O' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('P' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        
        $sheet->getStyle("A{$satir}:P{$satir}")->applyFromArray($dataStyle);
        
        $satir++;
    }

    // Toplam satırı ekle
    $toplamSatir = $satir;
    $sheet->setCellValue('B' . $toplamSatir, 'TOPLAM');
    $sheet->setCellValue('F' . $toplamSatir, '=SUM(F2:F' . ($satir - 1) . ')');
    $sheet->setCellValue('G' . $toplamSatir, '=SUM(G2:G' . ($satir - 1) . ')');
    $sheet->setCellValue('H' . $toplamSatir, '=SUM(H2:H' . ($satir - 1) . ')');
    $sheet->setCellValue('I' . $toplamSatir, '=SUM(I2:I' . ($satir - 1) . ')');
    $sheet->setCellValue('J' . $toplamSatir, '=SUM(J2:J' . ($satir - 1) . ')');
    $sheet->setCellValue('K' . $toplamSatir, '=SUM(K2:K' . ($satir - 1) . ')');
    $sheet->setCellValue('L' . $toplamSatir, '=SUM(L2:L' . ($satir - 1) . ')');
    $sheet->setCellValue('M' . $toplamSatir, '=SUM(M2:M' . ($satir - 1) . ')');
    $sheet->setCellValue('N' . $toplamSatir, '=SUM(N2:N' . ($satir - 1) . ')');
    $sheet->setCellValue('O' . $toplamSatir, '=SUM(O2:O' . ($satir - 1) . ')');
    $sheet->setCellValue('P' . $toplamSatir, '=SUM(P2:P' . ($satir - 1) . ')');
    
    $sheet->getStyle('A' . $toplamSatir . ':P' . $toplamSatir)->getFont()->setBold(true);
    $sheet->getStyle('F' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('G' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('H' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('I' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('J' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('K' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('L' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('M' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('N' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('O' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('P' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    
    $sheet->getStyle('A' . $toplamSatir . ':P' . $toplamSatir)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F3F4F6']
        ],
        'borders' => [
            'top' => ['borderStyle' => Border::BORDER_MEDIUM]
        ]
    ]);

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'muhasebe_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

    // HTTP başlıkları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // Excel dosyasını oluştur ve indir
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}
