<?php
/**
 * SGK Bildirge Raporu Excel Export
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
    $sheet->setTitle('SGK Bildirge');

    // Başlıklar
    $basliklar = [
        'A' => 'TC Kimlik No',
        'B' => 'Personel Ad Soyad',
        'C' => 'Prim Gün',
        'D' => 'SGK Matrahı (PEK)',
        'E' => 'İşçi SGK',
        'F' => 'İşçi İşsizlik',
        'G' => 'İşveren SGK',
        'H' => 'İşveren İşsizlik',
        'I' => 'Toplam Prim Tutarı'
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
    $sheet->getStyle('A1:I1')->applyFromArray($baslikStyle);

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
    $toplamMatrah = 0;
    $toplamIsciSgk = 0;
    $toplamIsciIssizlik = 0;
    $toplamIsverenSgk = 0;
    $toplamIsverenIssizlik = 0;
    $toplamGenelSgk = 0;
    $toplamGun = 0;

    // Personel verilerini ekle
    foreach ($personeller as $personel) {
        // SGK Matrahı ve Prim Gününü hesaplama_detay JSON'dan al
        $sgkMatrahi = floatval($personel->brut_maas ?? 0); // Varsayılan brüt
        $primGunu = 30; // Varsayılan 30 gün

        if (!empty($personel->hesaplama_detay)) {
            $detay = json_decode($personel->hesaplama_detay, true);
            if (isset($detay['matrahlar']['sgk_matrahi'])) {
                $sgkMatrahi = floatval($detay['matrahlar']['sgk_matrahi']);
            }

            // Gün hesaplama
            $ucretsizIzinGunu = 0;
            $ucretliIzinGunu = 0;
            if (isset($detay['matrahlar']['ucretsiz_izin_gunu'])) {
                $ucretsizIzinGunu = intval($detay['matrahlar']['ucretsiz_izin_gunu']);
            } elseif (isset($detay['matrahlar']['ucretsiz_izin_dusumu']) && isset($detay['matrahlar']['nominal_maas']) && $detay['matrahlar']['nominal_maas'] > 0) {
                $gunlukUcret = $detay['matrahlar']['nominal_maas'] / 30;
                $ucretsizIzinGunu = round($detay['matrahlar']['ucretsiz_izin_dusumu'] / $gunlukUcret);
            }
            if (isset($detay['matrahlar']['ucretli_izin_gunu'])) {
                $ucretliIzinGunu = intval($detay['matrahlar']['ucretli_izin_gunu']);
            }
            $primGunu = max(0, 30 - $ucretsizIzinGunu - $ucretliIzinGunu);
        }

        $isciSgk = floatval($personel->sgk_isci ?? 0);
        $isciIssizlik = floatval($personel->issizlik_isci ?? 0);
        $isverenSgk = floatval($personel->sgk_isveren ?? 0);
        $isverenIssizlik = floatval($personel->issizlik_isveren ?? 0);

        $personelToplamPrim = $isciSgk + $isciIssizlik + $isverenSgk + $isverenIssizlik;

        // Tabloya Yazımı
        $sheet->setCellValueExplicit('A' . $satir, $personel->tc_kimlik_no ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $satir, $personel->adi_soyadi);
        $sheet->setCellValue('C' . $satir, $primGunu);
        $sheet->setCellValue('D' . $satir, $sgkMatrahi);
        $sheet->setCellValue('E' . $satir, $isciSgk);
        $sheet->setCellValue('F' . $satir, $isciIssizlik);
        $sheet->setCellValue('G' . $satir, $isverenSgk);
        $sheet->setCellValue('H' . $satir, $isverenIssizlik);
        $sheet->setCellValue('I' . $satir, $personelToplamPrim);

        $toplamGun += $primGunu;
        $toplamMatrah += $sgkMatrahi;
        $toplamIsciSgk += $isciSgk;
        $toplamIsciIssizlik += $isciIssizlik;
        $toplamIsverenSgk += $isverenSgk;
        $toplamIsverenIssizlik += $isverenIssizlik;
        $toplamGenelSgk += $personelToplamPrim;

        // Sayı formatları
        $sheet->getStyle('D' . $satir . ':I' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Alt Toplam Satırı Ekle
    $sheet->setCellValue('B' . $satir, 'GENEL TOPLAMLAR:');
    $sheet->getStyle('B' . $satir)->getFont()->setBold(true);
    $sheet->getStyle('B' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $sheet->setCellValue('C' . $satir, $toplamGun);
    $sheet->setCellValue('D' . $satir, $toplamMatrah);
    $sheet->setCellValue('E' . $satir, $toplamIsciSgk);
    $sheet->setCellValue('F' . $satir, $toplamIsciIssizlik);
    $sheet->setCellValue('G' . $satir, $toplamIsverenSgk);
    $sheet->setCellValue('H' . $satir, $toplamIsverenIssizlik);
    $sheet->setCellValue('I' . $satir, $toplamGenelSgk);

    // Toplam satırına vurgu formatı
    $sheet->getStyle('A' . $satir . ':I' . $satir)->getFont()->setBold(true);
    $sheet->getStyle('A' . $satir . ':I' . $satir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
    $sheet->getStyle('D' . $satir . ':I' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

    // Tüm tabloya stil uygula
    $sheet->getStyle('A1:I' . ($satir))->applyFromArray($dataStyle);


    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'sgk_bildirge_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

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
