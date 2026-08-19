<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\PersonelIsRaporuModel;
use App\Model\PersonelModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($firmaId <= 0 || $currentUserId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum sonlanmış veya yetkisiz erişim.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$model = new PersonelIsRaporuModel();
$personelModel = new PersonelModel();

// Tarih aralığını çözümleme
$filterType = $_GET['filter_type'] ?? 'period';
$year = (int) ($_GET['year'] ?? date('Y'));
$month = str_pad((string) ($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$personelId = (int) ($_GET['personel_id'] ?? 0);
$category = trim($_GET['category'] ?? '');

if ($filterType === 'period') {
    $calculatedStartDate = sprintf('%04d-%02d-01', $year, $month);
    $calculatedEndDate = date('Y-m-t', strtotime($calculatedStartDate));
} else {
    $calculatedStartDate = !empty($startDate) ? date('Y-m-d', strtotime($startDate)) : date('Y-m-01');
    $calculatedEndDate = !empty($endDate) ? date('Y-m-d', strtotime($endDate)) : date('Y-m-t');
}

if ($calculatedStartDate > $calculatedEndDate) {
    $tmp = $calculatedStartDate;
    $calculatedStartDate = $calculatedEndDate;
    $calculatedEndDate = $tmp;
}

try {
    if ($action === 'get-report-data') {
        if ($personelId <= 0) {
            echo json_encode([
                'status' => 'warning',
                'message' => 'Lütfen bir personel seçiniz.',
                'data' => null
            ]);
            exit;
        }

        $personel = $personelModel->find($personelId);
        $personelAdi = $personel ? $personel->adi_soyadi : 'Seçilen Personel';

        $kpi = $model->getPersonelKpiSummary($firmaId, $personelId, $calculatedStartDate, $calculatedEndDate);
        $trend = $model->getDailyTrendData($firmaId, $personelId, $calculatedStartDate, $calculatedEndDate);
        $distribution = $model->getCategoryDistribution($kpi);
        $detailedLogs = $model->getDetailedWorkLogs($firmaId, $personelId, $calculatedStartDate, $calculatedEndDate, $category ?: null, 1000);

        echo json_encode([
            'status' => 'success',
            'personel' => [
                'id' => $personelId,
                'adi_soyadi' => $personelAdi
            ],
            'period' => [
                'start_date' => $calculatedStartDate,
                'end_date' => $calculatedEndDate,
                'start_date_tr' => date('d.m.Y', strtotime($calculatedStartDate)),
                'end_date_tr' => date('d.m.Y', strtotime($calculatedEndDate))
            ],
            'kpi' => $kpi,
            'trend' => $trend,
            'distribution' => $distribution,
            'logs' => $detailedLogs
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem parametresi.']);
    exit;

} catch (\Throwable $e) {
    error_log('PersonelIsRaporu API Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Rapor verileri hazırlanırken sunucu hatası oluştu.'
    ]);
    exit;
}
