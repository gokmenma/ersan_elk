<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception("Excel kütüphanesi bulunamadı.");
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Demirbaş No',
        'Demirbaş Adı',
        'Marka',
        'Model',
        'Seri No',
        'Miktar',
        'Edinme Tutarı',
        'Edinme Tarihi (GG.AA.YYYY)',
        'Kategori Adı'
    ];

    $colIndex = 1;
    foreach ($headers as $header) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($columnLetter . '1', $header);
        $sheet->getStyle($columnLetter . '1')->getFont()->setBold(true);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        $colIndex++;
    }

    // Örnek veri
    $sheet->setCellValue('A2', 'D001');
    $sheet->setCellValue('B2', 'Dizüstü Bilgisayar');
    $sheet->setCellValue('C2', 'Dell');
    $sheet->setCellValue('D2', 'Latitude 5420');
    $sheet->setCellValue('E2', 'SN12345678');
    $sheet->setCellValue('F2', '1');
    $sheet->setCellValue('G2', '25000');
    $sheet->setCellValue('H2', '01.01.2024');
    $sheet->setCellValue('I2', 'Bilgisayar');

    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="demirbas_yukleme_sablonu.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_end_clean();
    echo "Hata: " . $e->getMessage();
}
