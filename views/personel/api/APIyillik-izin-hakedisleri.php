<?php
session_start();
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\SystemLogModel;
use App\Model\YillikIzinHakedisModel;
use App\Service\Gate;

$firma_id = (int) ($_SESSION['firma_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = (string) ($_REQUEST['action'] ?? '');
if ($action !== 'manuel-hakedis-sablon') {
    header('Content-Type: application/json; charset=utf-8');
}

if ($firma_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Oturumunuz sona ermiş. Lütfen sayfayı yenileyin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

Gate::authorizeApiPolicy('personel/api/yillik-izin-hakedisleri', $action);

$Model = new YillikIzinHakedisModel();
$SystemLog = new SystemLogModel();

if ($action === 'yillik-izin-hakedis-liste') {
    try {
        echo json_encode($Model->ajaxList($_POST, $firma_id), JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('yillik-izin-hakedis-liste: ' . $e->getMessage());
        echo json_encode([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Hakediş listesi alınamadı.',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'yillik-izin-hakedis-ozet') {
    try {
        echo json_encode(['status' => 'success', 'summary' => $Model->summary($firma_id, !empty($_POST['pasif_dahil']))], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('yillik-izin-hakedis-ozet: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Özet alınamadı.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'yillik-izin-hakedis-detay') {
    $personelId = (int) Security::decrypt($_POST['id'] ?? '');
    try {
        $detay = $personelId > 0 ? $Model->detay($personelId, $firma_id) : null;
        if (!$detay) {
            echo json_encode(['status' => 'error', 'message' => 'Personel kaydı bulunamadı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $SystemLog->logAction($userId, 'Yıllık İzin Hakediş Detayı', $detay['adi_soyadi'] . ' için yıllık izin hakediş detayı görüntülendi.');
        echo json_encode(['status' => 'success', 'detay' => $detay], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('yillik-izin-hakedis-detay: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Detay alınamadı.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'manuel-hakedis-kaydet') {
    $personelId = (int) Security::decrypt($_POST['personel_id'] ?? '');
    $tarihHam = trim((string) ($_POST['hakedis_tarihi'] ?? ''));
    $tarih = \DateTime::createFromFormat('d.m.Y', $tarihHam) ?: \DateTime::createFromFormat('Y-m-d', $tarihHam);
    $gun = (float) str_replace(',', '.', (string) ($_POST['gun'] ?? '0'));
    $aciklama = (string) ($_POST['aciklama'] ?? '');
    try {
        if ($personelId <= 0 || !$tarih) {
            echo json_encode(['status' => 'error', 'message' => 'Personel ve hakediş tarihi zorunludur.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $id = $Model->manuelEkle($firma_id, $personelId, $tarih->format('Y-m-d'), $gun, $aciklama, 'manuel', $userId);
        $SystemLog->logAction($userId, 'Yıllık İzin Manuel Hakediş', "Personel #{$personelId} için {$gun} gün manuel hakediş eklendi (kayıt #{$id}).", SystemLogModel::LEVEL_IMPORTANT);
        echo json_encode(['status' => 'success', 'message' => 'Manuel hakediş eklendi.'], JSON_UNESCAPED_UNICODE);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('manuel-hakedis-kaydet: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Kayıt eklenemedi.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'manuel-hakedis-sil') {
    $id = (int) Security::decrypt($_POST['id'] ?? '');
    try {
        $kayit = $id > 0 ? $Model->manuelBul($id, $firma_id) : null;
        if (!$kayit || !$Model->manuelSil($id, $firma_id, $userId)) {
            echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $SystemLog->logAction($userId, 'Yıllık İzin Manuel Hakediş Silme', "Personel #{$kayit->personel_id} için {$kayit->gun} gün manuel hakediş silindi (kayıt #{$id}).", SystemLogModel::LEVEL_IMPORTANT);
        echo json_encode(['status' => 'success', 'message' => 'Manuel hakediş silindi.'], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('manuel-hakedis-sil: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Silme işlemi başarısız.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'manuel-hakedis-excel-yukle') {
    try {
        $dosya = $_FILES['excel_file'] ?? null;
        if (!$dosya || (int) $dosya['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Dosya yüklenemedi veya seçilmedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $uzanti = strtolower(pathinfo((string) $dosya['name'], PATHINFO_EXTENSION));
        if (!in_array($uzanti, ['xlsx', 'xls'], true) || (int) $dosya['size'] > 5 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'Yalnızca 5 MB altında .xlsx veya .xls dosyası yüklenebilir.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dosya['tmp_name'])->getActiveSheet();
        $tum = $sheet->toArray(null, true, false);
        if (count($tum) < 2) {
            echo json_encode(['status' => 'error', 'message' => 'Excel dosyası boş veya yalnızca başlık satırı içeriyor.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (count($tum) > 2001) {
            echo json_encode(['status' => 'error', 'message' => 'Tek seferde en fazla 2000 satır yüklenebilir.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $satirlar = [];
        foreach ($tum as $i => $r) {
            if ($i === 0) {
                continue;
            }
            $satirlar[$i + 1] = [
                'tc' => $r[0] ?? '',
                'tarih' => $r[2] ?? '',
                'gun' => $r[3] ?? '',
                'aciklama' => $r[4] ?? '',
            ];
        }

        $sonuc = $Model->excelIceAktar($satirlar, $firma_id, $userId);
        $SystemLog->logAction(
            $userId,
            'Yıllık İzin Manuel Hakediş Excel',
            "Excel ile {$sonuc['eklenen']} manuel hakediş eklendi, {$sonuc['atlanan']} satır atlandı, " . count($sonuc['hatalar']) . ' satır hatalı.',
            SystemLogModel::LEVEL_IMPORTANT
        );
        echo json_encode([
            'status' => 'success',
            'message' => "{$sonuc['eklenen']} kayıt eklendi, {$sonuc['atlanan']} satır atlandı, " . count($sonuc['hatalar']) . ' satır hatalı.',
            'eklenen' => $sonuc['eklenen'],
            'hatalar' => array_slice($sonuc['hatalar'], 0, 50),
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('manuel-hakedis-excel-yukle: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Excel dosyası okunamadı.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'manuel-hakedis-sablon') {
    try {
        require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Manuel Hakediş');
        foreach (['TC Kimlik No', 'Adı Soyadı (bilgi)', 'Hakediş Tarihi (GG.AA.YYYY)', 'Gün', 'Açıklama'] as $i => $baslik) {
            $sheet->setCellValue([$i + 1, 1], $baslik);
        }
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $satir = 2;
        foreach ($Model->sablonPersoneller($firma_id) as $p) {
            $sheet->setCellValueExplicit([1, $satir], $p['tc'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([2, $satir], $p['ad']);
            $satir++;
        }
        foreach (['A', 'B', 'C', 'D', 'E'] as $kolon) {
            $sheet->getColumnDimension($kolon)->setAutoSize(true);
        }
        $SystemLog->logAction($userId, 'Yıllık İzin Manuel Hakediş Şablonu', 'Manuel hakediş Excel şablonu indirildi.');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="manuel_izin_hakedis_sablonu.xlsx"');
        header('Cache-Control: max-age=0');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    } catch (\Throwable $e) {
        error_log('manuel-hakedis-sablon: ' . $e->getMessage());
        http_response_code(500);
        echo 'Şablon oluşturulamadı.';
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem.'], JSON_UNESCAPED_UNICODE);
