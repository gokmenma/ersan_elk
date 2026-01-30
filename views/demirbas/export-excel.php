<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $autoloaderPath = dirname(__DIR__, 2) . '/Autoloader.php';
    if (!file_exists($autoloaderPath)) {
        throw new Exception("Autoloader bulunamadı.");
    }
    require_once $autoloaderPath;

    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception("Excel kütüphanesi bulunamadı.");
    }

    $tab = $_GET['tab'] ?? 'demirbas';
    $term = $_GET['search'] ?? null;
    $colSearches = [];
    if (isset($_GET['col_search'])) {
        $colSearches = json_decode($_GET['col_search'], true);
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    if ($tab === 'demirbas') {
        $model = new \App\Model\DemirbasModel();
        $data = $model->filter($term, $colSearches);

        $columns = [
            'Sıra' => 'id',
            'D.No' => 'demirbas_no',
            'Kategori' => 'kategori_adi',
            'Demirbaş Adı' => 'demirbas_adi',
            'Marka' => 'marka',
            'Model' => 'model',
            'Stok' => 'kalan_miktar',
            'Toplam Miktar' => 'miktar',
            'Edinme Tutarı' => 'edinme_tutari',
            'Edinme Tarihi' => 'edinme_tarihi'
        ];
        $filename = 'demirbas_listesi_' . date('Y-m-d') . '.xlsx';
    } else {
        $model = new \App\Model\DemirbasZimmetModel();
        $data = $model->filter($term, $colSearches);

        $columns = [
            'ID' => 'id',
            'Kategori' => 'kategori_adi',
            'Demirbaş' => 'demirbas_adi',
            'Marka' => 'marka',
            'Model' => 'model',
            'Personel' => 'personel_adi',
            'Miktar' => 'teslim_miktar',
            'Teslim Tarihi' => 'teslim_tarihi',
            'Durum' => 'durum'
        ];
        $filename = 'zimmet_kayitlari_' . date('Y-m-d') . '.xlsx';
    }

    // Başlıkları yaz
    $colIndex = 1;
    foreach ($columns as $header => $field) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($columnLetter . '1', $header);
        $sheet->getStyle($columnLetter . '1')->getFont()->setBold(true);
        $sheet->getStyle($columnLetter . '1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');
        $colIndex++;
    }

    // Verileri yaz
    $rowIndex = 2;
    foreach ($data as $row) {
        $colIndex = 1;
        foreach ($columns as $header => $field) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = $row->$field ?? '';

            if ($field === 'edinme_tarihi' || $field === 'teslim_tarihi') {
                if (!empty($val) && $val !== '0000-00-00') {
                    $val = date('d.m.Y', strtotime($val));
                }
            } elseif ($field === 'durum' && $tab === 'zimmet') {
                $durumlar = [
                    'teslim' => 'Zimmetli',
                    'iade' => 'İade Edildi',
                    'kayip' => 'Kayıp',
                    'arizali' => 'Arızalı'
                ];
                $val = $durumlar[$val] ?? $val;
            }

            $sheet->setCellValueExplicit($columnLetter . $rowIndex, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colIndex++;
        }
        $rowIndex++;
    }

    // Sütun genişlikleri
    for ($i = 1; $i < $colIndex; $i++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
    }

    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo "Hata: " . $e->getMessage();
}
