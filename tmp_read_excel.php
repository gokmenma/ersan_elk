<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'SatisListesi (13).xlsx';
if (!file_exists($file)) die("File not found");
$spreadsheet = IOFactory::load($file);
$sheetNames = $spreadsheet->getSheetNames();
echo "Sheets: " . implode(', ', $sheetNames) . "\n";
$totalNet = 0;
$totalBrut = 0;
$totalCount = 0;
foreach ($sheetNames as $name) {
    $sheet = $spreadsheet->getSheetByName($name);
    $data = $sheet->toArray();
    foreach ($data as $i => $row) {
        if ($i == 0) continue; // skip header
        if (empty($row[25])) continue; // Check Net Tutar instead of Plate
        
        $totalCount++;
        $netRaw = str_replace(['TL', ' ', '₺'], '', $row[25]);
        $brutRaw = str_replace(['TL', ' ', '₺'], '', $row[24] ?? '0');
        
        // Robust parsing
        foreach (['netRaw', 'brutRaw'] as $vName) {
            $val = $$vName;
            if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
                if (strrpos($val, ',') > strrpos($val, '.')) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                } else {
                    $val = str_replace(',', '', $val);
                }
            } elseif (strpos($val, ',') !== false) {
                 if (strlen(substr($val, strpos($val, ',') + 1)) == 3) {
                     $val = str_replace(',', '', $val);
                 } else {
                     $val = str_replace(',', '.', $val);
                 }
            }
            if ($vName == 'netRaw') $netRaw = $val; else $brutRaw = $val;
        }

        $totalNet += (float)$netRaw;
        $totalBrut += (float)$brutRaw;
    }
}
echo "RESULT_START\n";
echo "Total Count: $totalCount\n";
echo "Total Net: " . number_format($totalNet, 2, '.', '') . "\n";
echo "Total Brut: " . number_format($totalBrut, 2, '.', '') . "\n";
echo "RESULT_END\n";
exit;
