<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\KacakKontrolModel;
use App\Model\PersonelModel;
use App\Service\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

if (!Gate::allows('kacak_duzenle') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

$basliklar = [
    'TARİH',
    'TUTANAK NO',
    'İSİM SOYİSİM',
    'SAYAÇ NO',
    'TÜR',
    'ENDEKS',
    'İŞLEM YAPAN MEMUR',
    'İLÇE',
    'TUTAR',
    'KONTROL EDİLDİ',
    'USULSÜZ',
    'TESLİM DURUMU',
];

$Personel = new PersonelModel();
$personelListesi = array_values($Personel->optionList('puantaj', date('Y-m-d'), true));
$ornekMemur = count($personelListesi) >= 2
    ? $personelListesi[0] . ',' . $personelListesi[1]
    : ($personelListesi[0] ?? 'BÜNYAMİN ATEŞ,SAMED ARSLAN');

$ornekSatirlar = [
    [date('d.m.Y'), 'TUT-2026-0001', 'AHMET YILMAZ', '10023456', 'Kaçak', '12500', $ornekMemur, KacakKontrolModel::ILCELER[0], '4500,00', 'Evet', '', 'Teslim Edildi'],
    [date('d.m.Y'), 'TUT-2026-0002', 'AYŞE DEMİR', '10023457', 'Usülsüz', '8300', $ornekMemur, KacakKontrolModel::ILCELER[1], '1250,50', 'Hayır', 'Mühür kopmuş', ''],
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kaçak Kayıt Şablonu');

$sheet->fromArray($basliklar, null, 'A1');
$sheet->fromArray($ornekSatirlar, null, 'A2');

$sonSutun = 'L';
$sonSatir = count($ornekSatirlar) + 1;

$sheet->getStyle('A1:' . $sonSutun . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0EA5E9']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(24);

$sheet->getStyle('A1:' . $sonSutun . $sonSatir)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
]);

$sheet->getStyle('A2:' . $sonSutun . $sonSatir)->getFont()->getColor()->setRGB('808080');
$sheet->getStyle('A2:A' . $sonSatir)->getNumberFormat()->setFormatCode('@');
$sheet->getStyle('B2:B' . $sonSatir)->getNumberFormat()->setFormatCode('@');
$sheet->getStyle('D2:D' . $sonSatir)->getNumberFormat()->setFormatCode('@');

foreach (range('A', $sonSutun) as $sutun) {
    $sheet->getColumnDimension($sutun)->setAutoSize(true);
}
$sheet->freezePane('A2');

$bilgi = $spreadsheet->createSheet();
$bilgi->setTitle('Açıklama');
$bilgi->fromArray([
    ['ALAN', 'ZORUNLU', 'AÇIKLAMA'],
    ['TARİH', 'Evet', 'gg.aa.yyyy biçiminde ya da Excel tarih hücresi olarak girin.'],
    ['TUTANAK NO', 'Evet', 'Mükerrer kontrolü bu alana göre yapılır. Sistemde kayıtlı numaralar atlanır.'],
    ['İSİM SOYİSİM', 'Hayır', 'Abone adı.'],
    ['SAYAÇ NO', 'Hayır', 'Sayaç numarası.'],
    ['TÜR', 'Hayır', 'Kaçak / Abonesiz / Usülsüz. Boş bırakılırsa USULSÜZ sütununa göre belirlenir, o da boşsa Kaçak kabul edilir.'],
    ['ENDEKS', 'Hayır', 'Sayaç endeks değeri.'],
    ['İŞLEM YAPAN MEMUR', 'Evet', 'Personel adı. İki kişi için virgülle ayırın: "BÜNYAMİN ATEŞ,SAMED ARSLAN". Ad soyad sistemdeki personel kaydıyla eşleşmelidir.'],
    ['İLÇE', 'Evet', 'Geçerli ilçe adı olmalıdır (aşağıdaki listeye bakın).'],
    ['TUTAR', 'Hayır', 'Tahakkuk tutarı. 4500,00 veya 4500.00 yazılabilir.'],
    ['KONTROL EDİLDİ', 'Hayır', 'Evet / Hayır (X, 1, Var da kabul edilir).'],
    ['USULSÜZ', 'Hayır', 'Serbest metin not alanı. Doluysa ve TÜR boşsa kayıt Usülsüz sayılır.'],
    ['TESLİM DURUMU', 'Hayır', 'Evet / Teslim Edildi yazılırsa kayıt teslim alınmış olarak işaretlenir.'],
], null, 'A1');

$bilgi->getStyle('A1:C1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0EA5E9']],
]);
$bilgi->getStyle('A1:C13')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$bilgi->getColumnDimension('A')->setWidth(24);
$bilgi->getColumnDimension('B')->setWidth(12);
$bilgi->getColumnDimension('C')->setWidth(80);

$satir = 15;
$bilgi->setCellValue('A' . $satir, 'GEÇERLİ İLÇELER');
$bilgi->getStyle('A' . $satir)->getFont()->setBold(true);
$satir++;
foreach (KacakKontrolModel::ILCELER as $ilce) {
    $bilgi->setCellValue('A' . $satir, $ilce);
    $satir++;
}

$spreadsheet->setActiveSheetIndex(0);

$dosyaAdi = 'Kacak_Kayit_Yukleme_Sablonu.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;
