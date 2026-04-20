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
        
        $toplamYemek = 0;
        $fiiliGun = 25; // Varsayılan çalışma günü
        
        // 1. Maaşa Dahil Yemek Yardımı (mealAllowanceDeduction banka üzerinden ödeniyor)
        if (isset($hesap['mealAllowanceDeduction']) && $hesap['mealAllowanceDeduction'] > 0) {
            $toplamYemek += $hesap['mealAllowanceDeduction'];
            
            // Eğer fiili çalışma günü girilmişse onu al (hesaplama detayından geliyor)
            if (isset($p->hd_fiili_calisma_gunu) && intval($p->hd_fiili_calisma_gunu) > 0) {
                $fiiliGun = intval($p->hd_fiili_calisma_gunu);
            }
        }
        
        // 2. Sodexo / Yemek Kartı Ödemeleri
        if (isset($hesap['sodexoOdemesi']) && $hesap['sodexoOdemesi'] > 0) {
            $toplamYemek += $hesap['sodexoOdemesi'];
            
            // Eğer Maaşa Dahil değilse ama Sodexo varsa, fiili gün olarak puantajdaki çalışma gününü baz alabiliriz
            if (!isset($p->hd_fiili_calisma_gunu) || intval($p->hd_fiili_calisma_gunu) <= 0) {
                if (isset($hesap['calismaGunu']) && $hesap['calismaGunu'] > 0) {
                    $fiiliGun = $hesap['calismaGunu'];
                }
            }
        }

        // Eğer herhangi bir yemek bedeli varsa listeye ekle
        if ($toplamYemek > 0) {
            $gunlukUcret = $toplamYemek / $fiiliGun;
            $yemekVerileri[] = [
                'tc_kimlik' => $p->tc_kimlik_no ?? '-',
                'adi_soyadi' => $p->adi_soyadi ?? '-',
                'fiili_gun' => $fiiliGun,
                'gunluk_ucret' => round($gunlukUcret, 2),
                'toplam_tutar' => $toplamYemek
            ];
        }
    }

    if (empty($yemekVerileri)) {
        die('Bu dönemde yemek bedeli hakedişi olan personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Yemek Bedeli Listesi');

    // Başlıklar
    $basliklar = [
        'A' => 'TC KİMLİK NO',
        'B' => 'ADI SOYADI',
        'C' => 'FİİLİ ÇALIŞMA GÜN',
        'D' => 'YEMEK TUTARI GÜNLÜK ÜCRETİ',
        'E' => 'TOPLAM YEMEK TUTARI'
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
    $sheet->getStyle('A1:E1')->applyFromArray($baslikStyle);
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
        $sheet->setCellValue('C' . $satir, $veri['fiili_gun']);
        $sheet->setCellValue('D' . $satir, $veri['gunluk_ucret']);
        $sheet->setCellValue('E' . $satir, $veri['toplam_tutar']);

        // Formatlar
        $sheet->getStyle('C' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('E' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        
        $sheet->getStyle("A{$satir}:E{$satir}")->applyFromArray($dataStyle);
        
        $satir++;
    }

    // Toplam satırı ekle
    $toplamSatir = $satir;
    $sheet->setCellValue('B' . $toplamSatir, 'TOPLAM');
    $sheet->setCellValue('E' . $toplamSatir, '=SUM(E2:E' . ($satir - 1) . ')');
    $sheet->getStyle('A' . $toplamSatir . ':E' . $toplamSatir)->getFont()->setBold(true);
    $sheet->getStyle('E' . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    $sheet->getStyle('A' . $toplamSatir . ':E' . $toplamSatir)->applyFromArray([
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
    $dosyaAdi = 'yemek_bedeli_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

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
