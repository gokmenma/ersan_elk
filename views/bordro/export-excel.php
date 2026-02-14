<?php
/**
 * Genel Bordro Excel Export
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
    $personeller = $BordroPersonel->getPersonellerByDonem($donemId);

    if (empty($personeller)) {
        die('Bu dönemde personel bulunmamaktadır.');
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Bordro Listesi');

    // Başlıklar
    $basliklar = [
        'A' => 'TC Kimlik No',
        'B' => 'Personel Ad Soyad',
        'C' => 'Maaş Tipi',
        'D' => 'Ekip',
        'E' => 'Bölge',
        'F' => 'Çalışma Günü',
        'G' => 'Brüt Maaş',
        'H' => 'SGK İşçi',
        'I' => 'İşsizlik İşçi',
        'J' => 'Gelir Vergisi',
        'K' => 'Damga Vergisi',
        'L' => 'Toplam Ek Ödeme',
        'M' => 'Toplam Kesinti',
        'N' => 'Net Maaş',
        'O' => 'Banka',
        'P' => 'Sodexo',
        'Q' => 'Diğer',
        'R' => 'Elden'
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
    $sheet->getStyle('A1:R1')->applyFromArray($baslikStyle);

    // Veri stili
    $dataStyle = [
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
        // Elden ödeme hesapla
        $eldenOdeme = ($personel->net_maas ?? 0) - ($personel->banka_odemesi ?? 0) - ($personel->sodexo_odemesi ?? 0) - ($personel->diger_odeme ?? 0);

        // İzin ve çalışma günü hesapla
        $ucretsizIzinGunu = 0;
        $ucretliIzinGunu = 0;
        if (!empty($personel->hesaplama_detay)) {
            $detay = json_decode($personel->hesaplama_detay, true);
            if (isset($detay['matrahlar']['ucretsiz_izin_kesinti']) && isset($detay['matrahlar']['brut_maas']) && $detay['matrahlar']['brut_maas'] > 0) {
                $gunlukUcret = $detay['matrahlar']['brut_maas'] / 30;
                $ucretsizIzinGunu = round($detay['matrahlar']['ucretsiz_izin_kesinti'] / $gunlukUcret);
            }
            if (isset($detay['matrahlar']['ucretli_izin_gunu'])) {
                $ucretliIzinGunu = intval($detay['matrahlar']['ucretli_izin_gunu']);
            }
        }
        $calismaGunu = 30 - $ucretsizIzinGunu - $ucretliIzinGunu;

        // Ek ödeme toplamı (hesaplanan)
        $hesaplananEkOdeme = $personel->guncel_toplam_ek_odeme;
        if (!empty($personel->hesaplama_detay)) {
            $detayEkOdeme = json_decode($personel->hesaplama_detay, true);
            if (isset($detayEkOdeme['ek_odemeler']) && is_array($detayEkOdeme['ek_odemeler'])) {
                $hesaplananEkOdeme = 0;
                foreach ($detayEkOdeme['ek_odemeler'] as $eo) {
                    $hesaplananEkOdeme += floatval($eo['net_etki'] ?? $eo['tutar'] ?? 0);
                }
            }
        }

        $sheet->setCellValueExplicit('A' . $satir, $personel->tc_kimlik_no ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $satir, $personel->adi_soyadi);
        $sheet->setCellValue('C' . $satir, $personel->maas_durumu ?? '-');
        $sheet->setCellValue('D' . $satir, $personel->ekip_adi ?? '');
        $sheet->setCellValue('E' . $satir, $personel->ekip_bolge ?? '');
        $sheet->setCellValue('F' . $satir, $calismaGunu);
        $sheet->setCellValue('G' . $satir, $personel->brut_maas ?? 0);
        $sheet->setCellValue('H' . $satir, $personel->sgk_isci ?? 0);
        $sheet->setCellValue('I' . $satir, $personel->issizlik_isci ?? 0);
        $sheet->setCellValue('J' . $satir, $personel->gelir_vergisi ?? 0);
        $sheet->setCellValue('K' . $satir, $personel->damga_vergisi ?? 0);
        $sheet->setCellValue('L' . $satir, $hesaplananEkOdeme);
        $sheet->setCellValue('M' . $satir, $personel->guncel_toplam_kesinti ?? 0);
        $sheet->setCellValue('N' . $satir, $personel->net_maas ?? 0);
        $sheet->setCellValue('O' . $satir, $personel->banka_odemesi ?? 0);
        $sheet->setCellValue('P' . $satir, $personel->sodexo_odemesi ?? 0);
        $sheet->setCellValue('Q' . $satir, $personel->diger_odeme ?? 0);
        $sheet->setCellValue('R' . $satir, $eldenOdeme);

        // Sayı formatları
        $sheet->getStyle('G' . $satir . ':R' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Tüm tabloya stil uygula
    $sheet->getStyle('A1:R' . ($satir - 1))->applyFromArray($dataStyle);

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'bordro_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

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
