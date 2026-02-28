<?php
// Çıktı tamponlamasını başlat
ob_start();

// Hataları ekrana basmayı kapat, sadece logla
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Autoloader'ı dahil et
    $autoloaderPath = dirname(__DIR__, 2) . '/Autoloader.php';
    if (!file_exists($autoloaderPath)) {
        throw new Exception("Autoloader bulunamadı: $autoloaderPath");
    }
    require_once $autoloaderPath;

    // Composer Autoloader
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
    }

    // Model
    $ZimmetModel = new \App\Model\DemirbasZimmetModel();
    $PersonelModel = new \App\Model\PersonelModel();

    $personel_id = isset($_GET['personel_id']) ? $_GET['personel_id'] : null;
    $kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

    if (!$personel_id) {
        throw new Exception("Personel ID belirtilmedi.");
    }

    $personel = $PersonelModel->find($personel_id);
    if (!$personel) {
        throw new Exception("Personel bulunamadı.");
    }

    // Zimmetleri çek
    $zimmetler = $ZimmetModel->getByPersonel($personel_id);

    // Kategoriye göre filtrele (eğer filter varsa)
    if (!empty($kategori_filter)) {
        $filtered = [];
        foreach ($zimmetler as $z) {
            $kat = $z->kategori_adi ?? 'Kategorisiz';
            if ($kat === $kategori_filter) {
                $filtered[] = $z;
            }
        }
        $zimmetler = $filtered;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Zimmet Listesi');

    // Başlıklar
    $columns = [
        'Kategori' => 'kategori_adi',
        'Demirbaş Adı' => 'demirbas_adi',
        'Marka' => 'marka',
        'Model' => 'model',
        'Seri No' => 'seri_no',
        'Miktar' => 'teslim_miktar',
        'Teslim Tarihi' => 'teslim_tarihi',
        'İade Tarihi' => 'iade_tarihi',
        'Durum' => 'durum'
    ];

    $colIndex = 1;
    foreach ($columns as $header => $field) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($columnLetter . '1', $header);

        // Stil
        $style = $sheet->getStyle($columnLetter . '1');
        $style->getFont()->setBold(true);
        $style->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');

        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        $colIndex++;
    }

    // Veriler
    $rowIndex = 2;
    foreach ($zimmetler as $zimmet) {
        $colIndex = 1;
        foreach ($columns as $header => $field) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = $zimmet->$field ?? '';

            if ($field == 'teslim_tarihi' || $field == 'iade_tarihi') {
                if (!empty($val) && $val != '0000-00-00' && $val != '-') {
                    $val = date('d.m.Y', strtotime($val));
                } else {
                    $val = '-';
                }
            } elseif ($field == 'durum') {
                $statusMap = [
                    'teslim' => 'Zimmetli',
                    'iade' => 'İade Edildi',
                    'kayip' => 'Kayıp',
                    'arizali' => 'Arızalı'
                ];
                $val = $statusMap[$val] ?? $val;
            }

            $sheet->setCellValueExplicit($columnLetter . $rowIndex, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colIndex++;
        }
        $rowIndex++;
    }

    // Çıktı tamponunu temizle
    ob_end_clean();

    // Dosya İndirme Headerları
    $filename = 'zimmet_listesi_' . \App\Helper\Helper::slugify($personel->adi_soyadi) . '_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo "Hata: " . $e->getMessage();
}
