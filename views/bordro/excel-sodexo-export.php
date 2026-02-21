<?php
/**
 * Sodexo Formatında Bordro Excel Export
 * Sodexo'ya gönderilmek üzere personel sodexo kart bilgilerini içeren Excel dosyası oluşturur.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
use App\Model\PersonelModel;
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

    // Dönemdeki personelleri getir (detaylı bilgiler dahil)
    $personeller = $BordroPersonel->getPersonellerByDonemDetayli($donemId, $idArray);

    if (empty($personeller)) {
        die('Bu dönemde kriterlere uygun personel bulunmamaktadır.');
    }

    // Sadece sodexo ödemesi olan personelleri filtrele
    $sodexoPersoneller = array_filter($personeller, function ($p) {
        return ($p->sodexo_odemesi ?? 0) > 0;
    });

    if (empty($sodexoPersoneller)) {
        die('Bu dönemde sodexo ödemesi olan personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sodexo Listesi');

    // Sodexo formatı başlıkları (Resimden alınan sıralama)
    $basliklar = [
        'A' => 'Adı Soyadı *',
        'B' => 'Kart No *',
        'C' => 'Kullanıcı Adı',
        'D' => 'Telefon No',
        'E' => 'Cep Telefonu',
        'F' => 'Email',
        'G' => 'Kart Tipi *',
        'H' => 'Şube *',
        'I' => 'Yükleme Tutarı *'
    ];

    // Başlık satırını ayarla
    $baslikStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => '000000']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9E1F2'] // Açık mavi arka plan
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
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Veri stili
    $dataStyle = [
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD']
            ]
        ]
    ];

    // Personel verilerini ekle
    $satir = 2;
    foreach ($sodexoPersoneller as $personel) {
        // Ad Soyad
        $adiSoyadi = $personel->adi_soyadi ?? '';

        // Kart No (sodexo_kart_no varsa ondan, yoksa TC'den)
        $kartNo = !empty($personel->sodexo_kart_no) ? $personel->sodexo_kart_no : '';

        // Kullanıcı Adı (TC Kimlik No kullanılabilir)
        $kullaniciAdi = $personel->tc_kimlik_no ?? '';

        // Telefon No (Sabit telefon varsa kullan, yoksa cep telefonu)
        $telefonNo = ''; // Sistemde sabit telefon alanı yok gibi görünüyor

        // Cep Telefonu
        $cepTelefonu = $personel->cep_telefonu ?? '';

        // Email
        $email = $personel->email_adresi ?? '';

        // Kart Tipi (Resimden sabit: "Mobil Kart")
        $kartTipi = 'Mobil Kart';

        // Şube (Resimden sabit: "MERKEZ")
        $sube = 'MERKEZ';

        // Yükleme Tutarı (Sodexo ödemesi)
        $yuklemeTutari = $personel->sodexo_odemesi ?? 0;

        // Satıra verileri yaz
        $sheet->setCellValue('A' . $satir, $adiSoyadi);
        $sheet->setCellValueExplicit('B' . $satir, $kartNo, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $satir, $kullaniciAdi, DataType::TYPE_STRING);
        $sheet->setCellValue('D' . $satir, $telefonNo);
        $sheet->setCellValue('E' . $satir, $cepTelefonu);
        $sheet->setCellValue('F' . $satir, $email);
        $sheet->setCellValue('G' . $satir, $kartTipi);
        $sheet->setCellValue('H' . $satir, $sube);
        $sheet->setCellValue('I' . $satir, $yuklemeTutari);

        // Stili uygula
        $sheet->getStyle("A{$satir}:I{$satir}")->applyFromArray($dataStyle);

        // Kart No ve Kullanıcı Adı sütunlarını metin olarak formatla
        $sheet->getStyle('B' . $satir)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('C' . $satir)->getNumberFormat()->setFormatCode('@');

        // Yükleme Tutarı sütununu formatlı göster
        $sheet->getStyle('I' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'sodexo_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

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
