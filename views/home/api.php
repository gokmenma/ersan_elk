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

if (!function_exists('safeJsonEncode')) {
    function safeJsonEncode($data): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        $json = json_encode($data, $flags);

        if ($json === false) {
            $json = json_encode(
                ['status' => 'error', 'message' => 'JSON encode failed: ' . json_last_error_msg()],
                $flags
            );
        }

        return $json === false ? '{"status":"error","message":"JSON encode failed"}' : $json;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // PERF: Session değişkenlerini oku ve session lock'ı hemen serbest bırak.
    $firmaId = $_SESSION['firma_id'] ?? 0;
    $userId = $_SESSION['user_id'] ?? 0;
    $firmaKodu = $_SESSION['firma_kodu'] ?? 17;
    session_write_close();

    $settingsModel = new \App\Model\SettingsModel();
    $dbSettingsJson = $settingsModel->getSettingByUser('dashboard_settings', $userId, $firmaId);
    $dbFreeLayout = $settingsModel->getSettingByUser('switch_free_layout', $userId, $firmaId);
    $saved_settings = $dbSettingsJson ? (json_decode($dbSettingsJson, true) ?: []) : [];
    $dashboard_is_free = $dbFreeLayout === 'true';

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
        file_put_contents($cachePath, safeJsonEncode($data));
    };

    try {
        switch ($action) {
            case 'save-dashboard-settings':
                $settings = $_POST['settings'] ?? '{}';
                $order = $_POST['order'] ?? '[]';
                $isFree = $_POST['is_free'] ?? 'false';
                
                $settingsModel = new \App\Model\SettingsModel();
                $ok = $settingsModel->upsertMultipleSettings([
                    'dashboard_settings' => $settings,
                    'dashboard_order' => $order,
                    'switch_free_layout' => $isFree
                ], $firmaId, $userId);
                
                echo safeJsonEncode(['status' => $ok ? 'success' : 'error']);
                exit;

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

                        $stmtLogs = $db->prepare("SELECT u.adi_soyadi, l.action_type FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.firma_id = ? AND l.created_at >= CURDATE() AND (l.action_type LIKE 'Online%' OR l.action_type LIKE 'Cron%') ORDER BY l.created_at DESC");
                        $stmtLogs->execute([$firmaId]);
                        $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

                        $findUser = function($types) use ($allLogs) {
                            foreach($allLogs as $log) {
                                if (in_array($log['action_type'], $types) || stripos($log['action_type'], 'Cron') !== false) {
                                    return $log['adi_soyadi'] ?: 'Sistem';
                                }
                            }
                            return null;
                        };
                        $lastUserIsler = $findUser(['Online Kesme/Açma Sorgulama', 'Online Puantaj Sorgulama']);
                        $lastUserEndeks = $findUser(['Online Endeks Okuma Sorgulama', 'Online İcmal (Endeks Okuma) Sorgulama']);
                        $lastUserSayac = $findUser(['Online Sayaç Değişim Sorgulama']);
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
                            $db = $nobetModel->getDb();
                            $degisim_c = 0; $mazeret_c = 0; $talep_c = 0;
                            try {
                                $stmtDegisim = $db->prepare("SELECT COUNT(*) as count FROM nobet_degisim_talepleri dt LEFT JOIN nobetler n ON dt.nobet_id = n.id WHERE dt.durum IN ('beklemede', 'personel_onayladi') AND n.firma_id = :firma_id AND n.nobet_tarihi >= CURDATE()");
                                $stmtDegisim->execute([':firma_id' => $firmaId]);
                                $degisim_c = (int) $stmtDegisim->fetch(PDO::FETCH_OBJ)->count;

                                $stmtMazeret = $db->prepare("SELECT COUNT(*) as count FROM nobetler WHERE durum = 'mazeret_bildirildi' AND silinme_tarihi IS NULL AND firma_id = :firma_id AND nobet_tarihi >= CURDATE()");
                                $stmtMazeret->execute([':firma_id' => $firmaId]);
                                $mazeret_c = (int) $stmtMazeret->fetch(PDO::FETCH_OBJ)->count;

                                $stmtTalep = $db->prepare("SELECT COUNT(*) as count FROM nobetler WHERE durum = 'talep_edildi' AND silinme_tarihi IS NULL AND firma_id = :firma_id AND nobet_tarihi >= CURDATE()");
                                $stmtTalep->execute([':firma_id' => $firmaId]);
                                $talep_c = (int) $stmtTalep->fetch(PDO::FETCH_OBJ)->count;
                            } catch (\Exception $e) {}

                            $data['personel_talep_sayisi'] = (int)$avansModel->getBekleyenAvansSayisi() + (int)$izinModel->getBekleyenIzinSayisi() + (int)$talepModel->getBekleyenTalepSayisi() + $degisim_c + $mazeret_c + $talep_c;
                        } elseif ($widgetId == 'widget-nobetciler') {
                            $data['nobetciler'] = $nobetModel->getNobetlerByTarih(date('Y-m-d'));
                        } elseif ($widgetId == 'widget-gorevler' || $widgetId == 'widget-yaklasan-gorevler') {
                            $data['yaklasan_gorevler'] = $gorevModel->getYaklasanGorevler($firmaId, $userId, 10);
                        } elseif ($widgetId == 'widget-bildirimler') {
                            $data['recent_logs'] = $systemLogModel->getRecentLogs(10, 0);
                            $data['personelLogs'] = $systemLogModel->getPersonelLoginLogs(10);
                            $data['kullaniciLogs'] = $systemLogModel->getUserLoginLogs(10);
                        } elseif ($widgetId == 'widget-gec-kalanlar') {
                            $data['gec_kalan_sayisi'] = $hareketModel->getGecKalanlarCount($firmaId);
                        } elseif ($widgetId == 'widget-talepler') {
                            $avanslar = $avansModel->getBekleyenAvanslarForDashboard(5);
                            $izinler = $izinModel->getBekleyenIzinlerForDashboard(5);
                            $talepler = $talepModel->getBekleyenTaleplerForDashboard(5);

                            $nobet_all = [];
                            try {
                                $db = $nobetModel->getDb();
                                // Nöbet Değişim
                                $stmt = $db->prepare("SELECT dt.id, dt.talep_eden_id as personel_id, dt.talep_tarihi as tarih, dt.durum, n.nobet_tarihi, p1.adi_soyadi as talep_eden_adi, p2.adi_soyadi as talep_edilen_adi FROM nobet_degisim_talepleri dt LEFT JOIN nobetler n ON dt.nobet_id = n.id LEFT JOIN personel p1 ON dt.talep_eden_id = p1.id LEFT JOIN personel p2 ON dt.talep_edilen_id = p2.id WHERE dt.durum IN ('beklemede', 'personel_onayladi') AND n.firma_id = :firma_id AND n.nobet_tarihi >= CURDATE()");
                                $stmt->execute([':firma_id' => $firmaId]);
                                foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $nd) {
                                    $nobet_all[] = (object) [
                                        'tip' => 'Nöbet Değişim',
                                        'id' => $nd->id,
                                        'personel_id' => $nd->personel_id,
                                        'tarih' => $nd->tarih ?: $nd->nobet_tarihi,
                                        'durum' => $nd->durum,
                                        'detay' => ($nd->talep_eden_adi ?? 'Personel') . ' -> ' . ($nd->talep_edilen_adi ?? 'Personel') . ' (' . date('d.m.Y', strtotime($nd->nobet_tarihi)) . ')',
                                    ];
                                }

                                // Nöbet Mazeret
                                $stmt = $db->prepare("SELECT id, personel_id, nobet_tarihi as tarih, durum, mazeret_aciklama as detay FROM nobetler WHERE durum = 'mazeret_bildirildi' AND silinme_tarihi IS NULL AND firma_id = :firma_id AND nobet_tarihi >= CURDATE()");
                                $stmt->execute([':firma_id' => $firmaId]);
                                foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $nm) {
                                    $nobet_all[] = (object) [
                                        'tip' => 'Nöbet Mazeret',
                                        'id' => $nm->id,
                                        'personel_id' => $nm->personel_id,
                                        'tarih' => $nm->tarih,
                                        'durum' => $nm->durum,
                                        'detay' => 'Mazeret Bildirimi: ' . date('d.m.Y', strtotime($nm->tarih)) . ' ' . ($nm->detay ?: ''),
                                    ];
                                }

                                // Nöbet Talebi
                                $stmt = $db->prepare("SELECT id, personel_id, nobet_tarihi as tarih, durum, aciklama as detay FROM nobetler WHERE durum = 'talep_edildi' AND silinme_tarihi IS NULL AND firma_id = :firma_id AND nobet_tarihi >= CURDATE()");
                                $stmt->execute([':firma_id' => $firmaId]);
                                foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $nt) {
                                    $nobet_all[] = (object) [
                                        'tip' => 'Nöbet Talebi',
                                        'id' => $nt->id,
                                        'personel_id' => $nt->personel_id,
                                        'tarih' => $nt->tarih,
                                        'durum' => $nt->durum,
                                        'detay' => 'Nöbet Talebi: ' . date('d.m.Y', strtotime($nt->tarih)) . ' ' . ($nt->detay ?: ''),
                                    ];
                                }
                            } catch (\Exception $e) {}

                            $all = array_merge($avanslar, $izinler, $talepler, $nobet_all);
                            usort($all, fn($a, $b) => strtotime($b->tarih) - strtotime($a->tarih));
                            $data['recent_requests'] = array_slice($all, 0, 10);
                            $pIds = array_values(array_filter(array_unique(array_column($data['recent_requests'], 'personel_id'))));
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
                echo safeJsonEncode($response);
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
                echo safeJsonEncode(['status' => 'success', 'data' => ['series' => $series, 'categories' => $month ? [$aylarUzun[$month - 1]] : $aylar]]);
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
                echo safeJsonEncode(['status' => 'success', 'data' => ['series' => [['name' => 'İş Adeti', 'data' => $seriesData]], 'categories' => $categories, 'selected_month' => $aylarUzun[$month - 1]]]);
                break;

            case 'get-endeks-comparison':
                $endeksModel = new EndeksOkumaModel();
                echo safeJsonEncode(['status' => 'success', 'data' => $endeksModel->getMonthlyComparisonByDay()]);
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
                echo safeJsonEncode(['status' => 'success', 'data' => ['daily' => ['muhurleme' => (int)($dailyWorkStats->muhurleme ?? 0), 'kesme_acma' => (int)($dailyWorkStats->kesme_acma ?? 0), 'endeks_okuma' => (int)($dailyReadingTotal ?? 0), 'sayac_degisimi' => (int)($sayacDailyStats->sayac_degisimi ?? 0), 'kacak' => (int)($kacakDailyTotal->toplam ?? 0)], 'monthly' => ['muhurleme' => (int)($monthlyWorkStats->muhurleme ?? 0), 'kesme_acma' => (int)($monthlyWorkStats->kesme_acma ?? 0), 'endeks_okuma' => (int)($monthlyReadingTotal ?? 0), 'sayac_degisimi' => (int)($sayacMonthlyStats->sayac_degisimi ?? 0), 'kacak' => (int)($kacakMonthlyTotal->toplam ?? 0)]]]);
                break;

            case 'batch-load-widgets':
                // Legacy support
                $widgetIds = $_POST['widgets'] ?? [];
                $results = [];
                foreach ($widgetIds as $id) { $results[$id] = renderWidget($id, []); }
                echo safeJsonEncode(['status' => 'success', 'results' => $results]);
                break;

            case 'get-arac-evrak-stats':
                $aracModel = new \App\Model\AracModel();
                $expiredCounts = $aracModel->getAracEvrakStats();
                echo safeJsonEncode(['status' => 'success', 'data' => ['has_expired' => ($expiredCounts->muayene_biten > 0 || $expiredCounts->sigorta_biten > 0 || $expiredCounts->kasko_biten > 0), 'counts' => $expiredCounts]]);
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        echo safeJsonEncode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
