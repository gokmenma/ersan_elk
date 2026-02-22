<?php
/**
 * Vergi Raporu Excel Export
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
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

    // Dönem bilgisini al
    $donem = $BordroDonem->getDonemById($donemId);
    if (!$donem) {
        die('Dönem bulunamadı.');
    }

    // Dönemdeki personelleri getir
    $personeller = $BordroPersonel->getPersonellerByDonem($donemId, $idArray);

    if (empty($personeller)) {
        die('Bu dönemde kriterlere uygun personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Vergi Raporu (GV DV)');

    // Başlıklar
    $basliklar = [
        'A' => 'TC Kimlik No',
        'B' => 'Personel Ad Soyad',
        'C' => 'Gelir Vergisi Matrahı',
        'D' => 'Kümülatif GV Matrahı',
        'E' => 'Gelir Vergisi İstisnası',
        'F' => 'Kesilen Gelir Vergisi',
        'G' => 'Damga Vergisi Matrahı',
        'H' => 'Damga Vergisi İstisnası',
        'I' => 'Kesilen Damga Vergisi',
        'J' => 'Toplam Vergi Kesintisi'
    ];

    // Başlık stili
    $baslikStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4B5563']
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
    $sheet->getStyle('A1:J1')->applyFromArray($baslikStyle);

    // Veri stili
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD']
            ]
        ]
    ];

    $satir = 2;
    $toplamGvMatrah = 0;
    $toplamGvIstisna = 0;
    $toplamGv = 0;

    $toplamDvMatrah = 0;
    $toplamDvIstisna = 0;
    $toplamDv = 0;

    $toplamTumu = 0;

    // Personel verilerini ekle
    foreach ($personeller as $personel) {
        $gvMatrah = 0;
        $kumulatifGv = 0;
        $gvIstisna = 0;

        $dvMatrah = 0;
        $dvIstisna = 0;

        if (!empty($personel->hesaplama_detay)) {
            $detay = json_decode($personel->hesaplama_detay, true);
            $gvMatrah = floatval($detay['matrahlar']['gelir_vergisi_matrahi'] ?? 0);
            $kumulatifGv = floatval($detay['matrahlar']['kumulatif_vergi_matrahi_ay_basi'] ?? 0);
            $gvIstisna = floatval($detay['istisnalar']['gv_istisnasi'] ?? 0);

            $dvMatrah = floatval($detay['matrahlar']['damga_vergisi_matrahi'] ?? 0);
            $dvIstisna = floatval($detay['istisnalar']['dv_istisnasi'] ?? 0);
        }

        $odenecekGv = floatval($personel->gelir_vergisi ?? 0);
        $odenecekDv = floatval($personel->damga_vergisi ?? 0);
        $personelToplamVergi = $odenecekGv + $odenecekDv;

        // Tabloya Yazımı
        $sheet->setCellValueExplicit('A' . $satir, $personel->tc_kimlik_no ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $satir, $personel->adi_soyadi);
        $sheet->setCellValue('C' . $satir, $gvMatrah);
        $sheet->setCellValue('D' . $satir, $kumulatifGv);
        $sheet->setCellValue('E' . $satir, $gvIstisna);
        $sheet->setCellValue('F' . $satir, $odenecekGv);
        $sheet->setCellValue('G' . $satir, $dvMatrah);
        $sheet->setCellValue('H' . $satir, $dvIstisna);
        $sheet->setCellValue('I' . $satir, $odenecekDv);
        $sheet->setCellValue('J' . $satir, $personelToplamVergi);

        $toplamGvMatrah += $gvMatrah;
        $toplamGvIstisna += $gvIstisna;
        $toplamGv += $odenecekGv;

        $toplamDvMatrah += $dvMatrah;
        $toplamDvIstisna += $dvIstisna;
        $toplamDv += $odenecekDv;

        $toplamTumu += $personelToplamVergi;

        // Sayı formatları
        $sheet->getStyle('C' . $satir . ':J' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Alt Toplam Satırı Ekle
    $sheet->setCellValue('B' . $satir, 'GENEL TOPLAMLAR:');
    $sheet->getStyle('B' . $satir)->getFont()->setBold(true);
    $sheet->getStyle('B' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $sheet->setCellValue('C' . $satir, $toplamGvMatrah);
    $sheet->setCellValue('E' . $satir, $toplamGvIstisna);
    $sheet->setCellValue('F' . $satir, $toplamGv);

    $sheet->setCellValue('G' . $satir, $toplamDvMatrah);
    $sheet->setCellValue('H' . $satir, $toplamDvIstisna);
    $sheet->setCellValue('I' . $satir, $toplamDv);

    $sheet->setCellValue('J' . $satir, $toplamTumu);

    // Toplam satırına vurgu formatı
    $sheet->getStyle('A' . $satir . ':J' . $satir)->getFont()->setBold(true);
    $sheet->getStyle('A' . $satir . ':J' . $satir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
    $sheet->getStyle('C' . $satir . ':J' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

    // Tüm tabloya stil uygula
    $sheet->getStyle('A1:J' . ($satir))->applyFromArray($dataStyle);


    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'vergi_raporu_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

    // HTTP başlıkları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}
