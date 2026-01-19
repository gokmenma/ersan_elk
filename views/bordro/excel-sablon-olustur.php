<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroParametreModel;
use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Tip kontrolü: gelir veya kesinti
$tip = $_GET['tip'] ?? 'gelir';
$donemId = $_GET['donem'] ?? null;

if (!in_array($tip, ['gelir', 'kesinti'])) {
    die('Geçersiz tip. Sadece "gelir" veya "kesinti" olabilir.');
}

if (!$donemId) {
    die('Dönem ID belirtilmelidir.');
}

try {
    $BordroParametre = new BordroParametreModel();
    $BordroPersonel = new BordroPersonelModel();
    $BordroDonem = new BordroDonemModel();

    // Dönem bilgisini al
    $donem = $BordroDonem->getDonemById($donemId);
    if (!$donem) {
        die('Dönem bulunamadı.');
    }

    // Dönemdeki personelleri getir
    $personeller = $BordroPersonel->getPersonellerByDonem($donemId);

    if (empty($personeller)) {
        die('Bu dönemde personel bulunmamaktadır.');
    }

    // Kategoriye göre parametreleri getir
    $parametreler = $BordroParametre->getParametrelerByKategori($tip);

    if (empty($parametreler)) {
        die("Henüz tanımlanmış $tip parametresi bulunmamaktadır.");
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Başlık satırını ayarla
    $baslikRengi = $tip === 'gelir' ? '4CAF50' : 'F44336';
    $baslikMetni = $tip === 'gelir' ? 'GELİR EKLEME ŞABLONU' : 'KESİNTİ EKLEME ŞABLONU';
    $donemMetni = $donem->donem_adi . ' (' . date('d.m.Y', strtotime($donem->baslangic_tarihi)) . ' - ' . date('d.m.Y', strtotime($donem->bitis_tarihi)) . ')';

    // Son kolon harfini hesapla
    $sonKolonIndex = 67 + count($parametreler); // C + parametre sayısı
    $sonKolonHarf = chr($sonKolonIndex);

    // A1: Ana başlık
    $sheet->mergeCells("A1:{$sonKolonHarf}1");
    $sheet->setCellValue('A1', $baslikMetni . ' - ' . $donemMetni);
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 14,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $baslikRengi]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Kolon başlıkları (Satır 2)
    $sheet->setCellValue('A2', 'Sıra');
    $sheet->setCellValue('B2', 'TC Kimlik No');
    $sheet->setCellValue('C2', 'Adı Soyadı');

    // Dinamik parametre kolonları
    foreach ($parametreler as $index => $param) {
        $kolon = chr(68 + $index); // D'den başlayarak
        $sheet->setCellValue($kolon . '2', $param->etiket);
        $sheet->getColumnDimension($kolon)->setWidth(15);
    }

    // Kolon genişlikleri
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('C')->setWidth(25);

    // Başlık satırı stili (Satır 2)
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '607D8B']
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

    $sheet->getStyle("A2:{$sonKolonHarf}2")->applyFromArray($headerStyle);
    $sheet->getRowDimension(2)->setRowHeight(25);

    // Veri satırlarına stil
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];

    // Personelleri ekle
    $satir = 3;
    foreach ($personeller as $index => $personel) {
        $sheet->setCellValue('A' . $satir, ($index + 1));
        $sheet->setCellValueExplicit('B' . $satir, $personel->tc_kimlik_no ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $satir, $personel->adi_soyadi ?? '');

        // Parametre kolonlarına boş (0,00) değer
        foreach ($parametreler as $paramIndex => $param) {
            $kolon = chr(68 + $paramIndex);
            $sheet->setCellValue($kolon . $satir, '');
        }

        // Stili uygula
        $sheet->getStyle("A{$satir}:{$sonKolonHarf}{$satir}")->applyFromArray($dataStyle);

        // TC Kimlik sütununu metin olarak formatla
        $sheet->getStyle('B' . $satir)->getNumberFormat()->setFormatCode('@');

        // Sıra ve TC ortala
        $sheet->getStyle('A' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $satir++;
    }

    // Bilgilendirme notu ekle (personel listesinin altına)
    $notSatir = $satir + 1;
    $sheet->mergeCells("A{$notSatir}:{$sonKolonHarf}{$notSatir}");
    $sheet->setCellValue("A{$notSatir}", "NOT: TC Kimlik No değiştirilemez. Sadece tutar kolonlarını doldurun. Tutarları virgül veya nokta ile yazabilirsiniz (örn: 1.500 veya 1500,00)");
    $sheet->getStyle("A{$notSatir}")->applyFromArray([
        'font' => [
            'italic' => true,
            'color' => ['rgb' => '666666'],
            'size' => 9
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFF9C4']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ]
    ]);
    $sheet->getRowDimension($notSatir)->setRowHeight(30);

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = ($tip === 'gelir' ? 'bordro_gelir_' : 'bordro_kesinti_') . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

    // HTTP başlıkları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');

    // Excel dosyasını oluştur ve indir
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}
