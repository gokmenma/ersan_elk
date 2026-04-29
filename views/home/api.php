<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once __DIR__ . '/render_widgets.php';

use App\Model\PuantajModel;
use App\Model\EndeksOkumaModel;
use App\Service\SayacDegisimService;

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // PERF: Session değişkenlerini oku ve session lock'ı hemen serbest bırak.
    $firmaId = $_SESSION['firma_id'] ?? 0;
    $userId = $_SESSION['user_id'] ?? 0;
    $firmaKodu = $_SESSION['firma_kodu'] ?? 17;
    session_write_close();

    $action = $_POST['action'] ?? '';
    $puantajModel = new PuantajModel();
    $aylar = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $aylarUzun = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];

    // CACHE HELPER
    $getWidgetCache = function($key) use ($firmaId) {
        $cachePath = dirname(__DIR__, 2) . "/cache/dashboard/widget_{$firmaId}_{$key}.html";
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 300) {
            return file_get_contents($cachePath);
        }
        return null;
    };

    $setWidgetCache = function($key, $html) use ($firmaId) {
        $dir = dirname(__DIR__, 2) . "/cache/dashboard";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $cachePath = "$dir/widget_{$firmaId}_{$key}.html";
        file_put_contents($cachePath, $html);
    };

    $getDataCache = function($key) use ($firmaId) {
        $cachePath = dirname(__DIR__, 2) . "/cache/dashboard/data_{$firmaId}_{$key}.json";
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 300) {
            return json_decode(file_get_contents($cachePath), true);
        }
        return null;
    };

    $setDataCache = function($key, $data) use ($firmaId) {
        $dir = dirname(__DIR__, 2) . "/cache/dashboard";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $cachePath = "$dir/data_{$firmaId}_{$key}.json";
        file_put_contents($cachePath, json_encode($data));
    };

    try {
        switch ($action) {
            case 'batch-load-all':
                $widgetIds = $_POST['widgets'] ?? [];
                $widths = $_POST['widths'] ?? [];
                $force = isset($_POST['force']) && $_POST['force'] == 'true';
                $response = ['status' => 'success', 'results' => [], 'stats' => null];

                // 1. Stats
                $stats = $force ? null : $getDataCache('operational_stats');
                if (!$stats) {
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

                    $lastUpdateEndeks = null; $lastUpdateIsler = null; $lastUpdateSayac = null;
                    $lastUserEndeks = null; $lastUserIsler = null; $lastUserSayac = null;
                    try {
                        $db = $puantajModel->getDb();
                        $stmtUpdates = $db->prepare("SELECT
                                (SELECT MAX(created_at) FROM endeks_okuma WHERE firma_id = :firma_id AND created_at >= CURDATE()) AS last_update_endeks,
                                (SELECT MAX(created_at) FROM yapilan_isler WHERE firma_id = :firma_id AND created_at >= CURDATE()) AS last_update_isler,
                                (SELECT MAX(created_at) FROM sayac_degisim WHERE firma_id = :firma_id AND created_at >= CURDATE()) AS last_update_sayac");
                        $stmtUpdates->execute([':firma_id' => $firmaId]);
                        $updates = $stmtUpdates->fetch(PDO::FETCH_ASSOC) ?: [];
                        $lastUpdateEndeks = $updates['last_update_endeks'] ?? null;
                        $lastUpdateIsler = $updates['last_update_isler'] ?? null;
                        $lastUpdateSayac = $updates['last_update_sayac'] ?? null;

                        $getLastUpdateUser = function ($actionTypes) use ($db, $firmaId) {
                            $placeholders = implode(',', array_fill(0, count($actionTypes), '?'));
                            $stmt = $db->prepare("SELECT u.adi_soyadi, l.action_type FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.firma_id = ? AND (l.action_type IN ($placeholders) OR l.action_type LIKE 'Cron%') AND l.created_at >= CURDATE() ORDER BY l.created_at DESC LIMIT 1");
                            $stmt->execute(array_merge([$firmaId], $actionTypes));
                            $log = $stmt->fetch(PDO::FETCH_ASSOC);
                            return $log ? ($log['adi_soyadi'] ?: 'Sistem') : null;
                        };
                        $lastUserIsler = $getLastUpdateUser(['Online Kesme/Açma Sorgulama', 'Online Puantaj Sorgulama']);
                        $lastUserEndeks = $getLastUpdateUser(['Online Endeks Okuma Sorgulama', 'Online İcmal (Endeks Okuma) Sorgulama']);
                        $lastUserSayac = $getLastUpdateUser(['Online Sayaç Değişim Sorgulama']);
                    } catch (\Exception $e) {}

                    $stats = [
                        'daily' => ['muhurleme' => (int)($dailyWorkStats->muhurleme ?? 0), 'kesme_acma' => (int)($dailyWorkStats->kesme_acma ?? 0), 'endeks_okuma' => (int)($dailyReadingTotal ?? 0), 'sayac_degisimi' => (int)($sayacDailyStats->sayac_degisimi ?? 0), 'kacak' => (int)($kacakDailyTotal->toplam ?? 0)],
                        'monthly' => ['muhurleme' => (int)($monthlyWorkStats->muhurleme ?? 0), 'kesme_acma' => (int)($monthlyWorkStats->kesme_acma ?? 0), 'endeks_okuma' => (int)($monthlyReadingTotal ?? 0), 'sayac_degisimi' => (int)($sayacMonthlyStats->sayac_degisimi ?? 0), 'kacak' => (int)($kacakMonthlyTotal->toplam ?? 0)],
                        'last_update' => ['isler' => $lastUpdateIsler, 'isler_user' => $lastUserIsler, 'endeks' => $lastUpdateEndeks, 'endeks_user' => $lastUserEndeks, 'sayac' => $lastUpdateSayac, 'sayac_user' => $lastUserSayac]
                    ];
                    $setDataCache('operational_stats', $stats);
                }
                $response['stats'] = $stats;

                // 2. Widgets
                $personelModel = new \App\Model\PersonelModel();
                $avansModel = new \App\Model\AvansModel();
                $izinModel = new \App\Model\PersonelIzinleriModel();
                $aracModel = new \App\Model\AracModel();
                $talepModel = new \App\Model\TalepModel();
                $nobetModel = new \App\Model\NobetModel();
                $gorevModel = new \App\Model\GorevModel();
                $systemLogModel = new \App\Model\SystemLogModel();
                $hareketModel = new \App\Model\PersonelHareketleriModel();

                foreach ($widgetIds as $index => $widgetId) {
                    $cacheHtml = $force ? null : $getWidgetCache($widgetId);
                    if ($cacheHtml) { $response['results'][$widgetId] = $cacheHtml; continue; }

                    $data = []; $data['width'] = $widths[$index] ?? null;
                    try {
                        if ($widgetId == 'widget-personel-ozet' || $widgetId == 'widget-personel-ozeti') {
                            $data['istatistik'] = $personelModel->personelSayilari('personel');
                            $data['extraStats'] = $personelModel->getAdvancedDashboardStats();
                            $data['gec_kalan_sayisi'] = $hareketModel->getGecKalanlarCount($firmaId);
                        } elseif ($widgetId == 'widget-arac-ozet' || $widgetId == 'widget-arac-ozeti') {
                            $aracStats = $aracModel->getStats();
                            $data['toplam_aktif_arac'] = $aracStats->aktif_arac ?? 0;
                            $data['servisteki_arac'] = $aracModel->getServistekiAracSayisi();
                            $data['bosta_arac'] = $aracStats->bosta_arac ?? 0;
                            $data['saha_arac'] = max(0, $data['toplam_aktif_arac'] - $data['servisteki_arac'] - $data['bosta_arac']);
                            $total_for_calc = $data['toplam_aktif_arac'] ?: 1;
                            $data['aktif_a_yuzde'] = ($data['saha_arac'] / $total_for_calc) * 100;
                            $data['servis_a_yuzde'] = ($data['servisteki_arac'] / $total_for_calc) * 100;
                            $data['bosta_a_yuzde'] = ($data['bosta_arac'] / $total_for_calc) * 100;
                        } elseif ($widgetId == 'widget-bekleyen-talepler') {
                            $data['personel_talep_sayisi'] = (int)$avansModel->getBekleyenAvansSayisi() + (int)$izinModel->getBekleyenIzinSayisi() + (int)$talepModel->getBekleyenTalepSayisi();
                        } elseif ($widgetId == 'widget-nobetciler') {
                            $data['nobetciler'] = $nobetModel->getNobetlerByTarih(date('Y-m-d'));
                        } elseif ($widgetId == 'widget-gorevler' || $widgetId == 'widget-yaklasan-gorevler') {
                            $data['yaklasan_gorevler'] = $gorevModel->getYaklasanGorevler($firmaId, $userId, 10);
                        } elseif ($widgetId == 'widget-bildirimler') {
                            $data['recent_logs'] = $systemLogModel->getRecentLogs(20, 0);
                            $data['personelLogs'] = $systemLogModel->getPersonelLoginLogs(10);
                            $data['kullaniciLogs'] = $systemLogModel->getUserLoginLogs(10);
                        } elseif ($widgetId == 'widget-gec-kalanlar') {
                            $data['gec_kalan_sayisi'] = $hareketModel->getGecKalanlarCount($firmaId);
                        } elseif ($widgetId == 'widget-talepler') {
                            $avanslar = $avansModel->getBekleyenAvanslarForDashboard(5);
                            $izinler = $izinModel->getBekleyenIzinlerForDashboard(5);
                            $talepler = $talepModel->getBekleyenTaleplerForDashboard(5);
                            $all = array_merge($avanslar, $izinler, $talepler);
                            usort($all, fn($a, $b) => strtotime($b->tarih) - strtotime($a->tarih));
                            $data['recent_requests'] = array_slice($all, 0, 10);
                            $pIds = array_unique(array_column($data['recent_requests'], 'personel_id'));
                            $data['personel_map'] = [];
                            if (!empty($pIds)) { $personeller = $personelModel->whereIn('id', $pIds); foreach ($personeller as $p) $data['personel_map'][$p->id] = $p; }
                        } elseif ($widgetId == 'widget-izindekiler') {
                            $data['izindekiler'] = $izinModel->getAktifIzinler(10);
                        }
                        $html = renderWidget($widgetId, $data);
                        $setWidgetCache($widgetId, $html);
                        $response['results'][$widgetId] = $html;
                    } catch (Throwable $we) { $response['results'][$widgetId] = '<div class="alert alert-danger small p-2">Widget Error: ' . $we->getMessage() . '</div>'; }
                }
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;

            case 'get-work-type-stats':
                $year = intval($_POST['year'] ?? date('Y'));
                $month = !empty($_POST['month']) ? intval($_POST['month']) : null;
                $stats = $puantajModel->getWorkTypeStats($year, $month);
                $formattedData = []; $workTypes = [];
                foreach ($stats as $row) {
                    if (!in_array($row->tur, $workTypes)) $workTypes[] = $row->tur;
                    $formattedData[$row->tur][$row->ay] = intval($row->toplam);
                }
                $series = [];
                foreach ($workTypes as $type) {
                    $data = [];
                    if ($month) $data[] = $formattedData[$type][$month] ?? 0;
                    else for ($i = 1; $i <= 12; $i++) $data[] = $formattedData[$type][$i] ?? 0;
                    $series[] = ['name' => $type, 'data' => $data];
                }
                echo json_encode(['status' => 'success', 'data' => ['series' => $series, 'categories' => $month ? [$aylarUzun[$month - 1]] : $aylar]]);
                break;

            case 'get-work-result-stats':
                $year = intval($_POST['year'] ?? date('Y'));
                $month = !empty($_POST['month']) ? intval($_POST['month']) : date('n');
                $stats = $puantajModel->getWorkResultStats($year, $month);
                $seriesData = []; $categories = [];
                foreach ($stats as $row) {
                    $sonuc = $row->sonuc ?: 'Belirtilmemiş';
                    $categories[] = $sonuc;
                    $seriesData[] = intval($row->toplam);
                }
                echo json_encode(['status' => 'success', 'data' => ['series' => [['name' => 'İş Adeti', 'data' => $seriesData]], 'categories' => $categories, 'selected_month' => $aylarUzun[$month - 1]]]);
                break;

            case 'get-endeks-comparison':
                $endeksModel = new EndeksOkumaModel();
                echo json_encode(['status' => 'success', 'data' => $endeksModel->getMonthlyComparisonByDay()], JSON_UNESCAPED_UNICODE);
                break;

            case 'get-dashboard-operational-stats':
                // Legacy support (optional, can be redirected to batch-load-all)
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
                echo json_encode(['status' => 'success', 'data' => ['daily' => ['muhurleme' => (int)($dailyWorkStats->muhurleme ?? 0), 'kesme_acma' => (int)($dailyWorkStats->kesme_acma ?? 0), 'endeks_okuma' => (int)($dailyReadingTotal ?? 0), 'sayac_degisimi' => (int)($sayacDailyStats->sayac_degisimi ?? 0), 'kacak' => (int)($kacakDailyTotal->toplam ?? 0)], 'monthly' => ['muhurleme' => (int)($monthlyWorkStats->muhurleme ?? 0), 'kesme_acma' => (int)($monthlyWorkStats->kesme_acma ?? 0), 'endeks_okuma' => (int)($monthlyReadingTotal ?? 0), 'sayac_degisimi' => (int)($sayacMonthlyStats->sayac_degisimi ?? 0), 'kacak' => (int)($kacakMonthlyTotal->toplam ?? 0)]]], JSON_UNESCAPED_UNICODE);
                break;

            case 'batch-load-widgets':
                // Legacy support
                $widgetIds = $_POST['widgets'] ?? [];
                $results = [];
                foreach ($widgetIds as $id) { $results[$id] = renderWidget($id, []); }
                echo json_encode(['status' => 'success', 'results' => $results]);
                break;

            case 'get-arac-evrak-stats':
                $aracModel = new \App\Model\AracModel();
                $expiredCounts = $aracModel->getAracEvrakStats();
                echo json_encode(['status' => 'success', 'data' => ['has_expired' => ($expiredCounts->muayene_biten > 0 || $expiredCounts->sigorta_biten > 0 || $expiredCounts->kasko_biten > 0), 'counts' => $expiredCounts]], JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
