<?php
require_once dirname(__DIR__, 3) . '/Autoloader.php';
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

use App\Model\PersonelKesintileriModel;
use App\Model\PersonelIcralariModel;
use App\Model\BordroDonemModel;

$action = $_REQUEST['action'] ?? '';
$personel_id = $_REQUEST['personel_id'] ?? 0;

if (!$personel_id) {
    echo json_encode(['error' => 'Personel ID missing']);
    exit;
}

$kesintiModel = new PersonelKesintileriModel();
$icraModel = new PersonelIcralariModel();

try {
    switch ($action) {
        case 'get_icralar':
            $icralar = $icraModel->getDevamEdenIcralar($personel_id);
            echo json_encode($icralar);
            break;

        case 'save_kesinti':
            $tekrarTipi = $_POST['tekrar_tipi'] ?? 'tek_sefer';
            $hesaplamaTipi = $_POST['hesaplama_tipi'] ?? 'sabit';

            // Tek seferlik kesintilerde dönem kontrolü yap
            if ($tekrarTipi === 'tek_sefer' && !empty($_POST['donem_id'])) {
                $BordroDonem = new BordroDonemModel();
                $donem = $BordroDonem->getDonemById(intval($_POST['donem_id']));
                if ($donem && $donem->kapali_mi == 1) {
                    echo json_encode(['error' => 'Bu dönem kapatılmış. Kapalı dönemlere kesinti eklenemez.']);
                    break;
                }
            }

            $data = [
                'personel_id' => $personel_id,
                'tur' => $_POST['tur'] ?? 'diger',
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => floatval($_POST['tutar'] ?? 0),
                'oran' => floatval($_POST['oran'] ?? 0),
                'tarih' => $_POST['tarih'] ?? date('Y-m-d'),
                'aciklama' => $_POST['aciklama'] ?? '',
                'parametre_id' => !empty($_POST['parametre_id']) ? intval($_POST['parametre_id']) : null,
                'icra_id' => !empty($_POST['icra_id']) ? intval($_POST['icra_id']) : null,
                'kayit_yapan' => $_SESSION['id'] ?? null,
                'aktif' => 1
            ];

            if ($tekrarTipi === 'tek_sefer') {
                // Tek seferlik kesinti - dönem ID kullan
                $data['donem_id'] = $_POST['donem_id'] ?? null;
                $data['baslangic_donemi'] = null;
                $data['bitis_donemi'] = null;
            } else {
                // Sürekli kesinti - dönem aralığı kullan
                $data['donem_id'] = null;
                $data['baslangic_donemi'] = $_POST['baslangic_donemi'] ?? date('Y-m');
                $data['bitis_donemi'] = !empty($_POST['bitis_donemi']) ? $_POST['bitis_donemi'] : null;
            }

            $result = $kesintiModel->saveWithAttr($data);

            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                echo json_encode(['error' => 'Kayıt oluşturulamadı']);
            }
            break;

        case 'update_kesinti':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            $tekrarTipi = $_POST['tekrar_tipi'] ?? 'tek_sefer';
            $hesaplamaTipi = $_POST['hesaplama_tipi'] ?? 'sabit';

            $data = [
                'tur' => $_POST['tur'] ?? 'diger',
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => floatval($_POST['tutar'] ?? 0),
                'oran' => floatval($_POST['oran'] ?? 0),
                'tarih' => $_POST['tarih'] ?? date('Y-m-d'),
                'aciklama' => $_POST['aciklama'] ?? '',
                'parametre_id' => !empty($_POST['parametre_id']) ? intval($_POST['parametre_id']) : null,
                'icra_id' => !empty($_POST['icra_id']) ? intval($_POST['icra_id']) : null,
                'kayit_yapan' => $_SESSION['id'] ?? null
            ];

            if ($tekrarTipi === 'tek_sefer') {
                $data['donem_id'] = $_POST['donem_id'] ?? null;
            } else {
                $data['baslangic_donemi'] = $_POST['baslangic_donemi'] ?? date('Y-m');
                $data['bitis_donemi'] = !empty($_POST['bitis_donemi']) ? $_POST['bitis_donemi'] : null;
            }

            $result = $kesintiModel->updateKesinti($id, $data);
            echo json_encode(['success' => $result]);
            break;

        case 'sonlandir_kesinti':
            $id = intval($_POST['id'] ?? 0);
            $bitis_donemi = $_POST['bitis_donemi'] ?? date('Y-m');

            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            $result = $kesintiModel->sonlandirSurekliKesinti($id, $bitis_donemi);
            echo json_encode(['success' => $result]);
            break;

        case 'get_kesinti':
            $id = intval($_REQUEST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            $kesinti = $kesintiModel->getKesinti($id);
            echo json_encode($kesinti);
            break;

        case 'get_donem_kayitlari':
            // Sürekli kesintiden oluşturulan dönem kayıtlarını getir
            $ana_kesinti_id = intval($_REQUEST['ana_kesinti_id'] ?? 0);
            if (!$ana_kesinti_id) {
                echo json_encode(['error' => 'Ana kesinti ID gerekli']);
                break;
            }

            $kayitlar = $kesintiModel->getDonemKayitlari($ana_kesinti_id);
            echo json_encode($kayitlar);
            break;

        case 'save_icra':
            $data = [
                'personel_id' => $personel_id,
                'sira' => intval($_POST['icra_sira'] ?? 1),
                'dosya_no' => $_POST['icra_dosya_no'],
                'icra_dairesi' => $_POST['icra_dairesi'],
                'toplam_borc' => $_POST['icra_toplam_borc'],
                'aylik_kesinti_tutari' => $_POST['icra_aylik_kesinti'] ?? 0,
                'kesinti_tipi' => $_POST['icra_kesinti_tipi'] ?? 'tutar',
                'kesinti_orani' => $_POST['icra_kesinti_orani'] ?? 0,
                'baslangic_tarihi' => $_POST['icra_baslangic'] ?? null,
                'durum' => $_POST['icra_durum'] ?? 'bekliyor',
                'aciklama' => $_POST['icra_aciklama'] ?? ''
            ];
            $icraModel->saveWithAttr($data);
            echo json_encode(['success' => true]);
            break;

        case 'update_icra':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'İcra ID gerekli']);
                break;
            }
            $data = [
                'id' => $id,
                'sira' => intval($_POST['icra_sira'] ?? 1),
                'dosya_no' => $_POST['icra_dosya_no'],
                'icra_dairesi' => $_POST['icra_dairesi'],
                'toplam_borc' => $_POST['icra_toplam_borc'],
                'aylik_kesinti_tutari' => $_POST['icra_aylik_kesinti'] ?? 0,
                'kesinti_tipi' => $_POST['icra_kesinti_tipi'] ?? 'tutar',
                'kesinti_orani' => $_POST['icra_kesinti_orani'] ?? 0,
                'baslangic_tarihi' => $_POST['icra_baslangic'] ?? null,
                'durum' => $_POST['icra_durum'] ?? 'bekliyor',
                'aciklama' => $_POST['icra_aciklama'] ?? ''
            ];
            $icraModel->saveWithAttr($data);
            echo json_encode(['success' => true]);
            break;

        case 'get_icra':
            $id = intval($_REQUEST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'İcra ID gerekli']);
                break;
            }
            $icra = $icraModel->find($id);
            echo json_encode($icra);
            break;

        case 'delete_kesinti':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            // Kesintinin bağlı olduğu dönemi kontrol et
            $kesinti = $kesintiModel->getKesinti($id);
            if ($kesinti && $kesinti->donem_id) {
                $BordroDonem = new BordroDonemModel();
                $donem = $BordroDonem->getDonemById($kesinti->donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    echo json_encode(['error' => 'Bu dönem kapatılmış. Kapalı dönemlerdeki kesintiler silinemez.']);
                    break;
                }
            }

            $kesintiModel->softDelete($id);
            echo json_encode(['success' => true]);
            break;

        case 'delete_icra':
            $id = $_POST['id'];
            $icraModel->softDelete($id);
            echo json_encode(['success' => true]);
            break;

        case 'get_icra_kesintileri':
            $icra_id = intval($_REQUEST['icra_id'] ?? 0);
            if (!$icra_id) {
                echo json_encode(['error' => 'İcra ID gerekli']);
                break;
            }
            $icra = $icraModel->find($icra_id);
            $kesintiler = $icraModel->getIcraKesintileri($icra_id);
            echo json_encode([
                'icra' => $icra,
                'kesintiler' => $kesintiler
            ]);
            break;

        case 'export_icra_kesintileri':
            $icra_id = intval($_REQUEST['icra_id'] ?? 0);
            if (!$icra_id) {
                echo json_encode(['error' => 'İcra ID gerekli']);
                break;
            }

            $icra = $icraModel->find($icra_id);
            $kesintiler = $icraModel->getIcraKesintileri($icra_id);

            // PhpSpreadsheet kontrolü
            $phpSpreadsheetPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
            if (!file_exists($phpSpreadsheetPath)) {
                // Fallback: CSV olarak export et
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="icra_kesintileri_' . $icra_id . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Sıra', 'Dönem', 'Açıklama', 'Tutar', 'Durum', 'Tarih'], ';');
                $sira = 1;
                foreach ($kesintiler as $k) {
                    fputcsv($output, [
                        $sira++,
                        $k->donem_adi ?? '-',
                        $k->aciklama,
                        number_format($k->tutar, 2, ',', '.'),
                        $k->durum == 'onaylandi' ? 'Onaylandı' : ($k->durum == 'beklemede' ? 'Beklemede' : $k->durum),
                        date('d.m.Y', strtotime($k->olusturma_tarihi))
                    ], ';');
                }
                fclose($output);
                exit;
            }

            require_once $phpSpreadsheetPath;

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('İcra Kesintileri');

            // Başlık bilgileri
            $sheet->setCellValue('A1', 'İcra Dairesi:');
            $sheet->setCellValue('B1', $icra->icra_dairesi ?? '');
            $sheet->setCellValue('A2', 'Dosya No:');
            $sheet->setCellValue('B2', $icra->dosya_no ?? '');
            $sheet->setCellValue('A3', 'Toplam Borç:');
            $sheet->setCellValue('B3', floatval($icra->toplam_borc ?? 0));
            $sheet->getStyle('B3')->getNumberFormat()->setFormatCode('#,##0.00" TL"');

            // Tablo başlıkları
            $headerRow = 5;
            $headers = ['Sıra', 'Dönem', 'Açıklama', 'Tutar (TL)', 'Durum', 'Tarih'];
            foreach ($headers as $col => $header) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $headerRow;
                $sheet->setCellValue($cell, $header);
            }

            // Başlık stili
            $headerRange = 'A' . $headerRow . ':F' . $headerRow;
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2BD61']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
            ]);

            // Info satır stili
            $sheet->getStyle('A1:A3')->getFont()->setBold(true);

            // Veriler
            $row = $headerRow + 1;
            $sira = 1;
            $toplamKesilen = 0;
            foreach ($kesintiler as $k) {
                $sheet->setCellValue('A' . $row, $sira++);
                $sheet->setCellValue('B' . $row, $k->donem_adi ?? '-');
                $sheet->setCellValue('C' . $row, $k->aciklama);
                $sheet->setCellValue('D' . $row, floatval($k->tutar));
                $durumText = $k->durum == 'onaylandi' ? 'Onaylandı' : ($k->durum == 'beklemede' ? 'Beklemede' : $k->durum);
                $sheet->setCellValue('E' . $row, $durumText);
                $sheet->setCellValue('F' . $row, date('d.m.Y', strtotime($k->olusturma_tarihi)));
                $toplamKesilen += floatval($k->tutar);

                // Satır border
                $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $row++;
            }

            // Toplam satırı
            $sheet->setCellValue('C' . $row, 'Toplam Kesilen:');
            $sheet->setCellValue('D' . $row, $toplamKesilen);
            $sheet->getStyle('C' . $row . ':D' . $row)->getFont()->setBold(true);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            $row++;
            $kalanTutar = floatval($icra->toplam_borc ?? 0) - $toplamKesilen;
            $sheet->setCellValue('C' . $row, 'Kalan Tutar:');
            $sheet->setCellValue('D' . $row, $kalanTutar);
            $sheet->getStyle('C' . $row . ':D' . $row)->getFont()->setBold(true);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            if ($kalanTutar > 0) {
                $sheet->getStyle('D' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000'));
            } else {
                $sheet->getStyle('D' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('008000'));
            }

            // Tutar formatı
            $sheet->getStyle('D' . ($headerRow + 1) . ':D' . ($row - 2))->getNumberFormat()->setFormatCode('#,##0.00');

            // Sütun genişlikleri
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(40);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(15);
            $sheet->getColumnDimension('F')->setWidth(15);

            // Dosyayı gönder
            $dosyaAdi = 'icra_kesintileri_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $icra->dosya_no ?? $icra_id) . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
