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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$donemId = $_GET['donem_id'] ?? null;

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
    $personeller = $BordroPersonel->getPersonellerByDonemDetayli($donemId);

    if (empty($personeller)) {
        die('Bu dönemde personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Banka Listesi');

    // Banka formatı başlıkları (Resimden alınan sıralama)
    $basliklar = [
        'A' => 'AD',
        'B' => 'İKİNCİ AD',
        'C' => 'SOYAD',
        'D' => 'CİNSİYET',
        'E' => 'AYLIK BRÜT',
        'F' => 'BABA ADI',
        'G' => 'DOĞUM YERİ',
        'H' => 'DOĞUM TARİHİ',
        'I' => 'EV TEL/ALAN KODU',
        'J' => 'EV TELEFONU',
        'K' => 'ANNE ADI',
        'L' => 'VERGİ KİMLİK NO',
        'M' => 'TCKN',
        'N' => 'UYRUK',
        'O' => 'CEP/ALAN KODU',
        'P' => 'CEP TEL',
        'Q' => 'EMAIL',
        'R' => 'ADRES',
        'S' => 'İL KODU',
        'T' => 'İLÇE KODU',
        'U' => 'IBAN'
    ];

    // Başlık satırını ayarla
    $baslikStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'C0392B'] // Kırmızı/koyu metin (resimden)
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FCE4D6'] // Açık turuncu arka plan
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'B5B5B5']
            ]
        ]
    ];

    // Başlıkları yaz
    foreach ($basliklar as $kolon => $baslik) {
        $sheet->setCellValue($kolon . '1', $baslik);
        $sheet->getColumnDimension($kolon)->setAutoSize(true);
    }

    // Başlık satırına stil uygula
    $sheet->getStyle('A1:U1')->applyFromArray($baslikStyle);
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
    foreach ($personeller as $personel) {
        // Ad ve Soyad ayırma
        $fullName = $personel->adi_soyadi ?? '';
        $nameParts = explode(' ', trim($fullName));

        // En az 2 parça varsa son parçayı soyad, kalanları ad olarak al
        if (count($nameParts) >= 2) {
            $soyad = array_pop($nameParts);
            $ad = implode(' ', $nameParts);

            // 2'den fazla parça varsa, ilk parça ana ad, ortadakiler ikinci ad
            if (count($nameParts) > 1) {
                $ad = $nameParts[0];
                $ikinciAd = implode(' ', array_slice($nameParts, 1));
            } else {
                $ikinciAd = '';
            }
        } else {
            $ad = $fullName;
            $ikinciAd = '';
            $soyad = '';
        }

        // Cinsiyet formatı
        $cinsiyet = '';
        if (!empty($personel->cinsiyet)) {
            $cinsiyet = strtoupper(substr($personel->cinsiyet, 0, 1)); // E veya K
        }

        // Doğum tarihi formatı (GG.AA.YYYY)
        $dogumTarihi = '';
        if (!empty($personel->dogum_tarihi)) {
            $dogumTarihi = date('d.m.Y', strtotime($personel->dogum_tarihi));
        }

        // Cep telefonu alan kodu ve numara ayırma
        $cepTel = $personel->cep_telefonu ?? '';
        $cepAlanKodu = '';
        $cepNumara = '';
        if (!empty($cepTel)) {
            // Telefon numarasını temizle
            $cepTel = preg_replace('/[^0-9]/', '', $cepTel);
            if (strlen($cepTel) >= 10) {
                if (strlen($cepTel) == 11 && substr($cepTel, 0, 1) == '0') {
                    $cepTel = substr($cepTel, 1);
                }
                $cepAlanKodu = '0' . substr($cepTel, 0, 3);
                $cepNumara = substr($cepTel, 3);
            } else {
                $cepNumara = $cepTel;
            }
        }

        // IBAN formatı
        $iban = $personel->iban_numarasi ?? '';

        // Satıra verileri yaz
        $sheet->setCellValue('A' . $satir, $ad);
        $sheet->setCellValue('B' . $satir, $ikinciAd);
        $sheet->setCellValue('C' . $satir, $soyad);
        $sheet->setCellValue('D' . $satir, $cinsiyet);
        $sheet->setCellValue('E' . $satir, $personel->banka_odemesi ?? 0);
        $sheet->setCellValue('F' . $satir, $personel->baba_adi ?? '');
        $sheet->setCellValue('G' . $satir, $personel->dogum_yeri_il ?? '');
        $sheet->setCellValue('H' . $satir, $dogumTarihi);
        $sheet->setCellValue('I' . $satir, ''); // Ev Tel Alan Kodu
        $sheet->setCellValue('J' . $satir, ''); // Ev Telefonu
        $sheet->setCellValue('K' . $satir, $personel->anne_adi ?? '');
        $sheet->setCellValue('L' . $satir, ''); // Vergi Kimlik No
        $sheet->setCellValueExplicit('M' . $satir, $personel->tc_kimlik_no ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('N' . $satir, 'TR'); // Uyruk
        $sheet->setCellValue('O' . $satir, $cepAlanKodu);
        $sheet->setCellValue('P' . $satir, $cepNumara);
        $sheet->setCellValue('Q' . $satir, $personel->email_adresi ?? '');
        $sheet->setCellValue('R' . $satir, $personel->adres ?? '');
        $sheet->setCellValue('S' . $satir, ''); // İl Kodu
        $sheet->setCellValue('T' . $satir, ''); // İlçe Kodu
        $sheet->setCellValueExplicit('U' . $satir, $iban, DataType::TYPE_STRING);

        // Stili uygula
        $sheet->getStyle("A{$satir}:U{$satir}")->applyFromArray($dataStyle);

        // TC Kimlik ve IBAN sütunlarını metin olarak formatla
        $sheet->getStyle('M' . $satir)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('U' . $satir)->getNumberFormat()->setFormatCode('@');

        // Tutar sütununu formatlı göster (AYLIK BRÜT)
        $sheet->getStyle('E' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'banka_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

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
