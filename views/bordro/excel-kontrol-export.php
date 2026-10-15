<?php
/** Seçili personeller için toplu bordro kontrol dökümü. */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use App\Model\BordroPersonelModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!Gate::allowsPolicy('page', 'bordro/list', '', 'GET')) {
    http_response_code(403);
    exit('Bu dosyayı indirmek için bordro yetkiniz bulunmamaktadır.');
}

$donemId = (int) ($_GET['donem_id'] ?? 0);
$idArray = array_values(array_unique(array_filter(array_map(
    'intval',
    explode(',', (string) ($_GET['ids'] ?? ''))
))));

if ($donemId <= 0) {
    exit('Dönem ID belirtilmelidir.');
}
if (!$idArray) {
    exit('Bordro Kontrol dosyası için en az bir personel seçmelisiniz.');
}

try {
    $bordroModel = new BordroPersonelModel();
    $donemModel = new BordroDonemModel();
    $parametreModel = new BordroParametreModel();
    $donem = $donemModel->getDonemById($donemId);
    $firmaId = (int) ($_SESSION['firma_id'] ?? $_SESSION['firm_id'] ?? 0);
    if (!$donem || $firmaId <= 0 || (int) ($donem->firma_id ?? 0) !== $firmaId) {
        exit('Dönem bulunamadı.');
    }

    $personeller = $bordroModel->getPersonellerByDonem($donemId, $idArray);
    if (!$personeller) {
        exit('Seçili personellere ait bordro kaydı bulunamadı.');
    }

    $asgariNet = (float) ($parametreModel->getGenelAyar('asgari_ucret_net', $donem->baslangic_tarihi) ?? 0);
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Bordro Kontrol');
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.3)->setRight(0.25)->setBottom(0.3)->setLeft(0.25);
    $sheet->setShowGridlines(false);
    foreach (range('A', 'P') as $column) {
        $sheet->getColumnDimension($column)->setWidth(12);
    }
    $sheet->getColumnDimension('A')->setWidth(18);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('I')->setWidth(18);
    $sheet->getColumnDimension('M')->setWidth(18);

    $moneyFormat = '#,##0.00 "₺";[Red]-#,##0.00 "₺"';
    $thinBorder = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]];
    $row = 1;
    $exported = 0;

    foreach ($personeller as $personel) {
        $hesap = $bordroModel->hesaplaOrtakGosterimDegerleri($personel, $donem, $asgariNet);
        if (empty($hesap['hesaplanmis'])) {
            continue;
        }
        $odemeOzeti = $bordroModel->getMuhasebeOdemeOzeti($hesap);
        $fazlaMesaiOzeti = $bordroModel->getMuhasebeFazlaMesaiNetOzeti($personel, $donem);
        $sozlesmeOzeti = $bordroModel->getSozlesmeAlacagiKalemleri($hesap, $personel);
        $kesintiOzeti = $bordroModel->getBordroKontrolKesintiKalemleri($hesap, $personel);

        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->mergeCells("I{$row}:L{$row}");
        $sheet->mergeCells("M{$row}:P{$row}");
        $sheet->setCellValue("A{$row}", (string) ($personel->adi_soyadi ?? '-'));
        $sheet->setCellValueExplicit("I{$row}", 'TC: ' . (string) ($personel->tc_kimlik_no ?? '-'), DataType::TYPE_STRING);
        $sheet->setCellValue("M{$row}", 'Fiilî Gün: ' . (int) ($odemeOzeti['fiili_gun'] ?? 0));
        $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $thinBorder,
        ]);
        $sheet->getStyle("I{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M{$row}:P{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row += 2;

        $headerRow = $row;
        $sections = [
            ['A', 'D', 'SÖZLEŞME ALACAĞI HESABI', 'DCFCE7', '166534'],
            ['E', 'H', 'RESMÎ ALACAĞI HESABI', 'DBEAFE', '1D4ED8'],
            ['I', 'L', 'KESİNTİLER', 'FEE2E2', 'B91C1C'],
            ['M', 'P', 'ÖDENECEK NET MAAŞ', 'D1FAE5', '047857'],
        ];
        foreach ($sections as [$from, $to, $title, $fill, $color]) {
            $sheet->mergeCells("{$from}{$row}:{$to}{$row}");
            $sheet->setCellValue("{$from}{$row}", $title);
            $sheet->getStyle("{$from}{$row}:{$to}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $color]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => $thinBorder,
            ]);
        }
        $row++;

        $contractItems = array_map(
            static fn (array $item): array => [
                (string) ($item['etiket'] ?? 'Sözleşme Hakedişi'),
                (float) ($item['tutar'] ?? 0),
            ],
            $sozlesmeOzeti['kalemler'] ?? []
        );
        $officialItems = [
            ['Asgari Ücret (Net)', (float) ($hesap['asgariHakedis'] ?? 0)],
            ['Yemek Yardımı', (float) ($hesap['mealAllowanceDeduction'] ?? 0)],
            ['Eş Yardımı', (float) ($hesap['spouseAllowanceDeduction'] ?? 0)],
        ];
        foreach (($hesap['bankaEkOdemeDetaylari'] ?? []) as $item) {
            $resmiEtiket = (string) ($item['etiket'] ?? 'Resmî Ek Ödeme');
            $resmiEtiketLower = mb_strtolower($resmiEtiket, 'UTF-8');
            if ((float) ($fazlaMesaiOzeti['tutar'] ?? 0) > 0 && (
                strpos($resmiEtiketLower, 'hafta içi') !== false
                || strpos($resmiEtiketLower, 'fazla mesai') !== false
                || strpos($resmiEtiketLower, 'nöbet') !== false
            )) {
                continue;
            }
            $officialItems[] = [(string) ($item['etiket'] ?? 'Resmî Ek Ödeme'), (float) ($item['tutar'] ?? 0)];
        }
        if ((float) ($fazlaMesaiOzeti['tutar'] ?? 0) > 0) {
            $fazlaMesaiEtiketi = (string) ($fazlaMesaiOzeti['etiket'] ?? 'Fazla Mesai Ücreti (Net)');
            if ((int) ($fazlaMesaiOzeti['saat'] ?? 0) > 0) {
                $fazlaMesaiEtiketi .= ' (' . (int) $fazlaMesaiOzeti['saat'] . ' Saat)';
            }
            $officialItems[] = [$fazlaMesaiEtiketi, (float) $fazlaMesaiOzeti['tutar']];
        }
        $deductionItems = [];
        foreach (($kesintiOzeti['kalemler'] ?? []) as $item) {
            $label = trim((string) ($item['aciklama'] ?? '')) ?: (string) ($item['etiket'] ?? 'Kesinti');
            $deductionItems[] = [$label, -(float) ($item['tutar'] ?? 0)];
        }
        if (!$deductionItems) {
            $deductionItems[] = ['Kesinti Yok', 0.0];
        }
        $paymentItems = [
            ['Banka', (float) ($hesap['bankaOdemesi'] ?? 0)],
            ['Elden', (float) ($hesap['eldenOdeme'] ?? 0)],
            ['Sodexo / Kart', (float) ($hesap['sodexoOdemesi'] ?? 0)],
            ['Diğer', (float) ($hesap['digerOdeme'] ?? 0)],
        ];

        $maxItems = max(count($contractItems), count($officialItems), count($deductionItems), count($paymentItems), 4);
        for ($index = 0; $index < $maxItems; $index++, $row++) {
            foreach ([['A', 'D', $contractItems], ['E', 'H', $officialItems], ['I', 'L', $deductionItems], ['M', 'P', $paymentItems]] as [$from, $to, $items]) {
                $valueColumn = $to;
                $labelTo = chr(ord($to) - 1);
                $sheet->mergeCells("{$from}{$row}:{$labelTo}{$row}");
                if (isset($items[$index])) {
                    $sheet->setCellValue("{$from}{$row}", $items[$index][0]);
                    $sheet->setCellValue("{$valueColumn}{$row}", $items[$index][1]);
                    $sheet->getStyle("{$valueColumn}{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
                }
                $sheet->getStyle("{$from}{$row}:{$to}{$row}")->applyFromArray(['borders' => $thinBorder]);
            }
        }

        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'KESİNTİ ÖNCESİ TOPLAM ALACAĞI');
        $sheet->setCellValue("D{$row}", (float) ($sozlesmeOzeti['toplam'] ?? 0));
        $sheet->mergeCells("E{$row}:G{$row}");
        $sheet->setCellValue("E{$row}", 'RESMÎ BANKA MATRAHI');
        $sheet->setCellValue("H{$row}", (float) ($odemeOzeti['resmi_banka_matrahi'] ?? 0));
        $sheet->mergeCells("I{$row}:K{$row}");
        $sheet->setCellValue("I{$row}", 'TOPLAM KESİNTİ');
        $sheet->setCellValue("L{$row}", -(float) ($odemeOzeti['toplam_personel_kesintisi'] ?? 0));
        $sheet->mergeCells("M{$row}:O{$row}");
        $sheet->setCellValue("M{$row}", 'NET ÖDENECEK');
        $sheet->setCellValue("P{$row}", (float) ($hesap['netAlacagi'] ?? 0));
        $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'borders' => $thinBorder,
        ]);
        foreach (['D', 'H', 'L', 'P'] as $column) {
            $sheet->getStyle("{$column}{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
        }
        $sheet->getStyle("P{$row}")->getFont()->getColor()->setRGB('16A34A');
        // Dört kontrol kartının dış sınırı iç ızgaradan daha belirgin olsun.
        foreach (['A:D', 'E:H', 'I:L', 'M:P'] as $cardColumns) {
            [$cardFrom, $cardTo] = explode(':', $cardColumns);
            $sheet->getStyle("{$cardFrom}{$headerRow}:{$cardTo}{$row}")->applyFromArray([
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '64748B'],
                    ],
                ],
            ]);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(24);
        $sheet->setBreak("A" . ($row + 2), \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW);
        $row += 3;
        $exported++;
    }

    if ($exported === 0) {
        exit('Seçili personeller arasında hesaplanmış bordro bulunamadı.');
    }

    $sheet->freezePane('A1');
    try {
        (new SystemLogModel())->logAction(
            (int) ($_SESSION['user_id'] ?? 0),
            'Bordro Kontrol Excel İndirme',
            ($donem->donem_adi ?? '') . ' dönemi için ' . $exported . ' personelin kontrol dosyası indirildi.',
            SystemLogModel::LEVEL_IMPORTANT
        );
    } catch (Throwable $logError) {
        error_log('Bordro Kontrol Excel loglama hatası: ' . $logError->getMessage());
    }

    $donemSlug = preg_replace('/[^a-zA-Z0-9]+/', '_', (string) ($donem->donem_adi ?? 'donem'));
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="bordro_kontrol_' . $donemSlug . '_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
} catch (Throwable $error) {
    error_log('Bordro Kontrol Excel hatası: ' . $error->getMessage());
    http_response_code(500);
    exit('Bordro Kontrol dosyası oluşturulamadı.');
}
