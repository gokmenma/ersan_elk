<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once __DIR__ . '/render_widgets.php';

use App\Model\PuantajModel;
use App\Model\EndeksOkumaModel;
use App\Service\SayacDegisimService;

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

            case 'get-endeks-comparison':
                $endeksModel = new EndeksOkumaModel();
                $data = $endeksModel->getMonthlyComparisonByDay();
                echo json_encode([
                    'status' => 'success',
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'get-dashboard-operational-stats':
                $endeksModel = new EndeksOkumaModel();
                $sayacService = new SayacDegisimService();

                $dailyWorkStats = $puantajModel->getDailyStats();
                $monthlyWorkStats = $puantajModel->getMonthlyStats();
                $dailyReadingTotal = $endeksModel->getDailyStats();
                $monthlyReadingTotal = $endeksModel->getMonthlyStats();
                $sayacDailyStats = $sayacService->getDailyStats();
                $sayacMonthlyStats = $sayacService->getMonthlyStats();
                $kacakDailyTotal = $puantajModel->getKacakDailyStats();
                $kacakMonthlyTotal = $puantajModel->getKacakMonthlyStats();

                $lastUpdateEndeks = null;
                $lastUpdateIsler = null;
                $lastUpdateSayac = null;

                $lastUserEndeks = null;
                $lastUserIsler = null;
                $lastUserSayac = null;

                try {
                    $db = $puantajModel->getDb();
                    $firmaId = $_SESSION['firma_id'] ?? 0;

                    // Timestamps query
                    $stmtUpdates = $db->prepare("SELECT
                            (SELECT MAX(created_at)
                             FROM endeks_okuma
                             WHERE firma_id = :firma_id
                               AND created_at >= CURDATE()
                               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS last_update_endeks,
                            (SELECT MAX(created_at)
                             FROM yapilan_isler
                             WHERE firma_id = :firma_id
                               AND created_at >= CURDATE()
                               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS last_update_isler,
                            (SELECT MAX(created_at)
                             FROM sayac_degisim
                             WHERE firma_id = :firma_id
                               AND created_at >= CURDATE()
                               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS last_update_sayac");
                    $stmtUpdates->execute([':firma_id' => $firmaId]);
                    $updates = $stmtUpdates->fetch(\PDO::FETCH_ASSOC) ?: [];

                    $lastUpdateEndeks = $updates['last_update_endeks'] ?? null;
                    $lastUpdateIsler = $updates['last_update_isler'] ?? null;
                    $lastUpdateSayac = $updates['last_update_sayac'] ?? null;

                    // Function to get last update user/cron
                    $getLastUpdateUser = function ($actionTypes) use ($db, $firmaId) {
                        $placeholders = implode(',', array_fill(0, count($actionTypes), '?'));
                        $stmt = $db->prepare("SELECT l.user_id, u.adi_soyadi, l.action_type
                                             FROM system_logs l
                                             LEFT JOIN users u ON l.user_id = u.id
                                             WHERE l.firma_id = ? 
                                               AND (l.action_type IN ($placeholders) OR l.action_type LIKE 'Cron%')
                                               AND l.created_at >= CURDATE()
                                             ORDER BY l.created_at DESC LIMIT 1");
                        
                        $params = array_merge([$firmaId], $actionTypes);
                        $stmt->execute($params);
                        $log = $stmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if (!$log) return null;
                        if ($log['user_id'] == 0 || stripos($log['action_type'], 'Cron') !== false) {
                            return 'Cron';
                        }
                        return $log['adi_soyadi'] ?: 'Sistem';
                    };

                    $lastUserIsler = $getLastUpdateUser(['Online Kesme/Açma Sorgulama', 'Online Puantaj Sorgulama']);
                    $lastUserEndeks = $getLastUpdateUser(['Online Endeks Okuma Sorgulama', 'Online İcmal (Endeks Okuma) Sorgulama']);
                    $lastUserSayac = $getLastUpdateUser(['Online Sayaç Değişim Sorgulama']);

                } catch (\Exception $e) {
                    // Ignore timestamp query failures; numeric stats are still returned.
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'daily' => [
                            'muhurleme' => intval($dailyWorkStats->muhurleme ?? 0),
                            'kesme_acma' => intval($dailyWorkStats->kesme_acma ?? 0),
                            'endeks_okuma' => intval($dailyReadingTotal ?? 0),
                            'sayac_degisimi' => intval($sayacDailyStats->sayac_degisimi ?? 0),
                            'kacak' => intval($kacakDailyTotal->toplam ?? 0),
                        ],
                        'monthly' => [
                            'muhurleme' => intval($monthlyWorkStats->muhurleme ?? 0),
                            'kesme_acma' => intval($monthlyWorkStats->kesme_acma ?? 0),
                            'endeks_okuma' => intval($monthlyReadingTotal ?? 0),
                            'sayac_degisimi' => intval($sayacMonthlyStats->sayac_degisimi ?? 0),
                            'kacak' => intval($kacakMonthlyTotal->toplam ?? 0),
                        ],
                        'last_update' => [
                            'isler' => $lastUpdateIsler,
                            'isler_user' => $lastUserIsler,
                            'endeks' => $lastUpdateEndeks,
                            'endeks_user' => $lastUserEndeks,
                            'sayac' => $lastUpdateSayac,
                            'sayac_user' => $lastUserSayac,
                        ],
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'load-widget':
                $widgetId = $_POST['widget_id'] ?? '';
                $data = [];

                try {
                    $personelModel = new \App\Model\PersonelModel();
                    $aracModel = new \App\Model\AracModel();
                    $talepModel = new \App\Model\TalepModel();
                    $nobetModel = new \App\Model\NobetModel();
                    $gorevModel = new \App\Model\GorevModel();

                    if ($widgetId == 'widget-personel-ozet') {
                        $data['istatistik'] = $personelModel->personelSayilari('personel');
                        $data['extraStats'] = $personelModel->getAdvancedDashboardStats();
                        
                        $hareketModel = new \App\Model\PersonelHareketleriModel();
                        $data['gec_kalan_sayisi'] = $hareketModel->getGecKalanlarCount($_SESSION['firma_id'] ?? null);
                    } 
                    elseif ($widgetId == 'widget-arac-ozet') {
                        $aracStats = $aracModel->getStats();
                        $data['toplam_aktif_arac'] = $aracStats->aktif_arac ?? 0;
                        $data['servisteki_arac'] = $aracModel->getServistekiAracSayisi();
                        $data['bosta_arac'] = $aracStats->bosta_arac ?? 0;
                        $data['saha_arac'] = max(0, $data['toplam_aktif_arac'] - $data['servisteki_arac'] - $data['bosta_arac']);
                        
                        $total_for_calc = $data['toplam_aktif_arac'] ?: 1;
                        $data['aktif_a_yuzde'] = ($data['saha_arac'] / $total_for_calc) * 100;
                        $data['servis_a_yuzde'] = ($data['servisteki_arac'] / $total_for_calc) * 100;
                        $data['bosta_a_yuzde'] = ($data['bosta_arac'] / $total_for_calc) * 100;
                    } 
                    elseif ($widgetId == 'widget-bekleyen-talepler') {
                        $data['personel_talep_sayisi'] = $talepModel->getBekleyenTalepSayisi();
                    } 
                    elseif ($widgetId == 'widget-nobetciler') {
                        $data['nobetciler'] = $nobetModel->getNobetlerByTarih(date('Y-m-d'));
                    } 
                    elseif ($widgetId == 'widget-yaklasan-gorevler') {
                        $data['yaklasan_gorevler'] = $gorevModel->getYaklasanGorevler($_SESSION['firma_id'], $_SESSION['user_id'], 10);
                    } 
                    elseif ($widgetId == 'widget-bildirimler') {
                        $systemLogModel = new \App\Model\SystemLogModel();
                        $data['loginLogs'] = $systemLogModel->getRecentLogs(20, \App\Model\SystemLogModel::LEVEL_IMPORTANT);
                    }

                    $html = renderWidget($widgetId, $data);
                    echo json_encode(['status' => 'success', 'html' => $html]);
                } catch (Exception $e) {
                    error_log("Dashboard Widget Error ($widgetId): " . $e->getMessage());
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
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
