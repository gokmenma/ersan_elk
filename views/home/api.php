<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\PuantajModel;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $puantajModel = new PuantajModel();
    $aylar = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $aylarUzun = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];

    try {
        switch ($action) {
            case 'get-work-type-stats':
                $year = intval($_POST['year'] ?? date('Y'));
                $month = !empty($_POST['month']) ? intval($_POST['month']) : null;
                $stats = $puantajModel->getWorkTypeStats($year, $month);

                $formattedData = [];
                $workTypes = [];

                foreach ($stats as $row) {
                    if (!in_array($row->tur, $workTypes)) {
                        $workTypes[] = $row->tur;
                    }
                    $formattedData[$row->tur][$row->ay] = intval($row->toplam);
                }

                $series = [];
                foreach ($workTypes as $type) {
                    $data = [];
                    if ($month) {
                        $data[] = $formattedData[$type][$month] ?? 0;
                    } else {
                        for ($i = 1; $i <= 12; $i++) {
                            $data[] = $formattedData[$type][$i] ?? 0;
                        }
                    }
                    $series[] = [
                        'name' => $type,
                        'data' => $data
                    ];
                }

                $categories = $month ? [$aylarUzun[$month - 1]] : $aylar;

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'series' => $series,
                        'categories' => $categories
                    ]
                ]);
                break;

            case 'get-work-result-stats':
                $year = intval($_POST['year'] ?? date('Y'));
                $month = !empty($_POST['month']) ? intval($_POST['month']) : date('n'); // Ay verilmezse güncel ayı çek
                $stats = $puantajModel->getWorkResultStats($year, $month);

                $seriesData = [];
                $categories = [];

                foreach ($stats as $row) {
                    $sonuc = $row->sonuc ?: 'Belirtilmemiş';
                    $categories[] = $sonuc;
                    $seriesData[] = intval($row->toplam);
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'series' => [
                            [
                                'name' => 'İş Adeti',
                                'data' => $seriesData
                            ]
                        ],
                        'categories' => $categories,
                        'selected_month' => $aylarUzun[$month - 1]
                    ]
                ]);
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek metodu.']);
}
