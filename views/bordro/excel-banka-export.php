<?php
/**
 * Banka Formatında Bordro Excel Export
 * Bankaya gönderilmek üzere personel maaş bilgilerini içeren Excel dosyası oluşturur.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
use App\Model\PersonelModel;
use App\Model\FirmaModel;
use App\Helper\Helper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$donemId = $_GET['donem_id'] ?? null;
$ids = $_GET['ids'] ?? null;
$idArray = [];
if ($ids) {
    $idArray = explode(',', $ids);
    $idArray = array_filter(array_map('intval', $idArray));
}
$firmaId = $_SESSION['firma_id'] ?? null;

if (!$donemId) {
    die('Dönem ID belirtilmelidir.');
}

try {
    $BordroPersonel = new BordroPersonelModel();
    $BordroDonem = new BordroDonemModel();
    $Firma = new FirmaModel();

    // Dönem bilgisini al
    $donem = $BordroDonem->getDonemById($donemId);
    if (!$donem) {
        die('Dönem bulunamadı.');
    }

    // Firma bilgisini al
    $firma = null;
    if ($firmaId) {
        $firma = $Firma->getFirma($firmaId);
    }

    // Dönemdeki personelleri getir (detaylı bilgiler dahil)
    $personeller = $BordroPersonel->getPersonellerByDonemDetayli($donemId, $idArray);

    if (empty($personeller)) {
        die('Bu dönemde kriterlere uygun personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Banka Listesi');

    // Stil Tanımlamaları
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];

    // --- BÖLÜM 1: FİRMA ÖZETİ ---
    $sheet->setCellValue('A1', 'Ödeme Tipi');
    $sheet->setCellValue('B1', 'Ödeme Tarihi');
    $sheet->setCellValue('C1', 'Firma IBAN');
    $sheet->setCellValue('D1', 'Toplam Tutar');
    $sheet->setCellValue('E1', 'Firma Ünvanı');
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // Verileri hesapla
    $toplamBankaOdemesi = 0;
    foreach ($personeller as $p) {
        $toplamBankaOdemesi += (float) ($p->banka_odemesi ?? 0);
    }

    $odemeTarihiExcel = ExcelDate::PHPToExcel(time()); // Bugünün tarihini Excel Timestamp'ine çeviriyoruz (isteğe bağlı dönem sonu olabilir)

    $sheet->setCellValue('A2', 'M');
    $sheet->setCellValue('B2', $odemeTarihiExcel);
    $sheet->setCellValueExplicit('C2', $firma->firma_iban ?? '', DataType::TYPE_STRING);
    $sheet->setCellValue('D2', $toplamBankaOdemesi);
    $sheet->setCellValue('E2', $firma->firma_unvan ?? $firma->firma_adi ?? '');

    // Tarih ve Tutar formatları
    $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    $sheet->getStyle('D2')->getNumberFormat()->setFormatCode('#,##0.00');

    // --- BÖLÜM 2: PERSONEL LİSTESİ ---
    $sheet->setCellValue('A4', 'Ödeme Tipi');
    $sheet->setCellValue('B4', 'ödeme Tarihi');
    $sheet->setCellValue('C4', 'Personel IBAN');
    $sheet->setCellValue('D4', 'Tutar');
    $sheet->setCellValue('E4', 'Personel Ad\Soyad');
    $sheet->setCellValue('F4', 'TCKN');
    $sheet->getStyle('A4:F4')->applyFromArray($headerStyle);

    $satir = 5;
    foreach ($personeller as $personel) {
        $bankaOdemesi = (float) ($personel->banka_odemesi ?? 0);

        // Eğer banka ödemesi 0 ise listeye ekleme (isteğe bağlı, resimde 0 olanlar var gerçi)
        // if ($bankaOdemesi <= 0) continue; 

        $sheet->setCellValue('A' . $satir, 'M');
        $sheet->setCellValue('B' . $satir, $odemeTarihiExcel);
        $sheet->setCellValueExplicit('C' . $satir, $personel->iban_numarasi ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('D' . $satir, $bankaOdemesi);
        $sheet->setCellValue('E' . $satir, $personel->adi_soyadi ?? '');
        $sheet->setCellValueExplicit('F' . $satir, $personel->tc_kimlik_no ?? '', DataType::TYPE_STRING);

        // Stil ve formatlar
        $sheet->getStyle('B' . $satir)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('D' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Sütun genişliklerini ayarla
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'Maas_IBAN.xlsx';

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
