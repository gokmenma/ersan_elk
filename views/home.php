<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';
require_once __DIR__ . '/home/render_widgets.php';

use App\Helper\Security;
use App\Model\PersonelModel;
use App\Model\PersonelIzinleriModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Helper\Helper;
use App\Model\PermissionsModel;
use App\Model\NobetModel;
use App\Model\PersonelHareketleriModel;
use App\Model\GorevModel;
use App\Service\RequestPerformanceProfiler;

if (Gate::allows("ana_sayfa")) {

    $personelModel = new PersonelModel();
    $izinModel = new PersonelIzinleriModel();
    $systemLogModel = new SystemLogModel();
    $nobetModel = new NobetModel();
    $hareketModel = new PersonelHareketleriModel();
    $gorevModel = new GorevModel();

    $measureDb = static function (string $segment, callable $callback, int $dbCount = 1) {
        return RequestPerformanceProfiler::measure($segment, $callback, $dbCount);
    };

    $bugun = date('Y-m-d');
    
    // DB'den ayarları çek (Cookie'den önce kontrol et)
    $settingsModel = new \App\Model\SettingsModel();
    $userId = $_SESSION['user_id'] ?? 0;
    $firmaId = $_SESSION['firma_id'] ?? 0;

    $dbSettingsJson = $settingsModel->getSettingByUser('dashboard_settings', $userId, $firmaId);
    $dbOrderJson = $settingsModel->getSettingByUser('dashboard_order', $userId, $firmaId);
    $dbFreeLayout = $settingsModel->getSettingByUser('switch_free_layout', $userId, $firmaId);

    if ($dbSettingsJson) {
        $saved_settings = json_decode($dbSettingsJson, true) ?: [];
        $_COOKIE['dashboard_settings'] = $dbSettingsJson;
    } else {
        $saved_settings = isset($_COOKIE['dashboard_settings']) ? json_decode($_COOKIE['dashboard_settings'], true) : [];
    }

    if ($dbOrderJson) {
        $render_order = json_decode($dbOrderJson, true) ?: [];
        $_COOKIE['dashboard_order'] = $dbOrderJson; // Mevcut istekte de geçerli olsun
    } else {
        $render_order = isset($_COOKIE['dashboard_order']) ? json_decode($_COOKIE['dashboard_order'], true) : [];
    }

    if ($dbFreeLayout !== null) {
        $dashboard_is_free = $dbFreeLayout === 'true';
        $_COOKIE['switch_free_layout'] = $dbFreeLayout;
    } else {
        $dashboard_is_free = ($_COOKIE['switch_free_layout'] ?? 'false') === 'true';
    }

    // Operasyonel istatistikler ve son güncelleme bilgileri
    $last_update_endeks = $last_update_isler = $last_update_sayac = null;
    $last_user_endeks = $last_user_isler = $last_user_sayac = null;

    $firmaId = $_SESSION['firma_id'] ?? 0;
    $cache_key = "home_last_update_cache_" . $firmaId;

    if (isset($_SESSION[$cache_key]) && $_SESSION[$cache_key]['expires'] > time()) {
        $cached = $_SESSION[$cache_key];
        $last_update_endeks = $cached['last_update_endeks'];
        $last_update_isler = $cached['last_update_isler'];
        $last_update_sayac = $cached['last_update_sayac'];
        $last_user_endeks = $cached['last_user_endeks'];
        $last_user_isler = $cached['last_user_isler'];
        $last_user_sayac = $cached['last_user_sayac'];
    } else {
        try {
            $db = $personelModel->getDb();

            // Combined Last Log User Lookup
            $stmtLogs = $db->prepare("SELECT u.adi_soyadi, l.action_type, l.user_id FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.firma_id = ? AND l.created_at >= CURDATE() AND (l.action_type LIKE 'Online%' OR l.action_type LIKE 'Cron%') ORDER BY l.created_at DESC");
            $stmtLogs->execute([$firmaId]);
            $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            $findUser = function($types) use ($allLogs) {
                foreach($allLogs as $log) {
                    if (in_array($log['action_type'], $types) || stripos($log['action_type'], 'Cron') !== false) {
                        if ($log['user_id'] == 0 || stripos($log['action_type'], 'Cron') !== false) return 'Cron';
                        return $log['adi_soyadi'] ?: 'Sistem';
                    }
                }
                return null;
            };

            $last_user_isler = $findUser(['Online Kesme/Açma Sorgulama', 'Online Puantaj Sorgulama']);
            $last_user_endeks = $findUser(['Online Endeks Okuma Sorgulama', 'Online İcmal (Endeks Okuma) Sorgulama']);
            $last_user_sayac = $findUser(['Online Sayaç Değişim Sorgulama']);

            $updates = $measureDb('home.stmtUpdates', function() use ($db, $firmaId) {
                $stmtUpdates = $db->prepare("SELECT
                    (SELECT MAX(created_at) FROM endeks_okuma WHERE firma_id = :firma_id AND created_at >= CURDATE()) AS last_update_endeks,
                    (SELECT MAX(created_at) FROM yapilan_isler WHERE firma_id = :firma_id AND created_at >= CURDATE()) AS last_update_isler,
                    (SELECT MAX(created_at) FROM sayac_degisim WHERE firma_id = :firma_id AND created_at >= CURDATE()) AS last_update_sayac");
                $stmtUpdates->execute([':firma_id' => $firmaId]);
                return $stmtUpdates->fetch(PDO::FETCH_OBJ);
            }, 1);
            if ($updates) {
                $last_update_endeks = $updates->last_update_endeks;
                $last_update_isler = $updates->last_update_isler;
                $last_update_sayac = $updates->last_update_sayac;
            }

            $_SESSION[$cache_key] = [
                'expires' => time() + 1800,
                'last_update_endeks' => $last_update_endeks,
                'last_update_isler' => $last_update_isler,
                'last_update_sayac' => $last_update_sayac,
                'last_user_endeks' => $last_user_endeks,
                'last_user_isler' => $last_user_isler,
                'last_user_sayac' => $last_user_sayac,
            ];
        } catch (\Exception $e) { /* silent */ }
    }


    if (!function_exists('getWidthControl')) {
        function getWidthControl()
        {
            return '
            <div class="dropdown ms-1 d-inline-block">
                <button class="btn btn-link btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Boyutları Ayarla">
                    <i class="bx bx-expand-alt"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="min-width: 200px; z-index: 1060;">
                    <li><h6 class="dropdown-header fw-bold text-primary">Genişlik Ayarla</h6></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-2" href="javascript:void(0);">col-2 (1/6)</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-3" href="javascript:void(0);">col-3 (1/4)</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-4" href="javascript:void(0);">col-4 (1/3)</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-5" href="javascript:void(0);">col-5</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-6" href="javascript:void(0);">col-6 (1/2)</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-7" href="javascript:void(0);">col-7</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-8" href="javascript:void(0);">col-8 (2/3)</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-9" href="javascript:void(0);">col-9 (3/4)</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-10" href="javascript:void(0);">col-10</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-11" href="javascript:void(0);">col-11</a></li>
                    <li><a class="dropdown-item btn-resize-width" data-width="col-md-12" href="javascript:void(0);">col-12 (Tam)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header fw-bold text-success">Yükseklik Ayarla</h6></li>
                    <li><a class="dropdown-item btn-resize-height" data-height="300px" href="javascript:void(0);">Kısa (300px)</a></li>
                    <li><a class="dropdown-item btn-resize-height" data-height="400px" href="javascript:void(0);">Orta (400px)</a></li>
                    <li><a class="dropdown-item btn-resize-height" data-height="500px" href="javascript:void(0);">Uzun (500px)</a></li>
                    <li><a class="dropdown-item btn-resize-height" data-height="600px" href="javascript:void(0);">X-Uzun (600px)</a></li>
                    <li><a class="dropdown-item btn-resize-height" data-height="800px" href="javascript:void(0);">Maksimum (800px)</a></li>
                    <li><a class="dropdown-item btn-resize-height" data-height="auto" href="javascript:void(0);">Otomatik (Auto)</a></li>
                </ul>
            </div>';
        }
    }

    // Sistem Logları
    // $recent_logs = $systemLogModel->getRecentLogs(10);


    // $istatistik = $personelModel->personelSayilari('personel'); // Lazy load edilecek

    //Helper::dd($istatistik);

    // Personel Sayıları
// $personel_sayisi = count($personelModel->where('aktif_mi', 1));
// $pasif_personel_sayisi = count(
//     $personelModel->where('isten_cikis_tarihi', null, 'IS NOT')
// );
// $aktif_personel_sayisi = count(
//     $personelModel->where('isten_cikis_tarihi', null, 'IS')
// );
// $toplam_personel_sayisi = count($personelModel->all());

    // Bekleyen Talepler
    $avans_count = 0;
    $avanslar = [];
    $izin_count = 0;
    $izinler = [];
    $talep_count = 0;
    $talepler = [];
    $nobet_degisim_count = 0;
    $nobet_degisimler = [];
    $nobet_mazeret_count = 0;
    $nobet_mazeretler = [];
    $nobet_talep_count = 0;
    $nobet_talepleri = [];
    $personel_talep_sayisi = 0;
    $all_requests = [];
    $recent_requests = [];
    $personel_map = [];
    $active_leaves = [];

    // Chart değişkenleri (Placeholder values for now)
    $months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $totals = [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65];
    $toplam_gelir = 50000;
    $toplam_gider = 30000;
    $toplam_bakiye = 20000;

    // Araç Verilerini ve Duyuruları Çek (Cache'li)
    $cache_key_slider = "home_slider_notifications_cache_" . $firmaId;
    if (isset($_SESSION[$cache_key_slider]) && $_SESSION[$cache_key_slider]['expires'] > time()) {
        $slider_notifications = $_SESSION[$cache_key_slider]['slider_notifications'];
        $duyurular = $_SESSION[$cache_key_slider]['duyurular'];
    } else {
        // Araç Verilerini Çek (Dashboard Hatırlatıcı için)
        $aracModelHome = new \App\Model\AracModel();
        $expiredCounts = $aracModelHome->getAracEvrakStats();
        $aracNotifText = "";
        $hasExpired = false;

        if ($expiredCounts) {
            $parts = [];
            if ($expiredCounts->muayene_biten > 0) {
                $parts[] = '<a href="index.php?p=arac-takip/list&filter=muayene" class="text-white fw-bold" style="text-decoration:underline">' . $expiredCounts->muayene_biten . ' muayene</a>';
                $hasExpired = true;
            }
            if ($expiredCounts->sigorta_biten > 0) {
                $parts[] = '<a href="index.php?p=arac-takip/list&filter=sigorta" class="text-white fw-bold" style="text-decoration:underline">' . $expiredCounts->sigorta_biten . ' sigorta</a>';
                $hasExpired = true;
            }
            if ($expiredCounts->kasko_biten > 0) {
                $parts[] = '<a href="index.php?p=arac-takip/list&filter=kasko" class="text-white fw-bold" style="text-decoration:underline">' . $expiredCounts->kasko_biten . ' kasko</a>';
                $hasExpired = true;
            }

            if (!empty($parts)) {
                $aracNotifText = implode(', ', $parts) . ' süresi dolan araçlar bulunmaktadır.';
            }
        }

        if (!$aracNotifText) {
            $aracNotifText = "Tüm araçların evrakları (Muayene, Sigorta, Kasko) günceldir.";
        }

        // Slider Duyuruları
        $slider_notifications = [];

        // Sabit araç hatırlatması da bir slider elemanı olsun (Eğer gösterilecekse)
        if ($hasExpired || $aracNotifText !== "Tüm araçların evrakları (Muayene, Sigorta, Kasko) günceldir.") {
            $slider_notifications[] = [
                'id' => 0,
                'title' => $hasExpired ? 'Araç Evrak Hatırlatması' : 'Araçlar Güncel',
                'description' => $aracNotifText,
                'icon' => $hasExpired ? 'bx-error-circle' : 'bx-check-shield',
                'gradient' => $hasExpired ? 'linear-gradient(135deg, #7f1d1d 0%, #ef4444 100%)' : 'linear-gradient(135deg, #1e293b 0%, #2563eb 100%)',
                'link_action' => '',
                'link_class' => ''
            ];
        }

        try {
            $db = $personelModel->getDb();

            $duyuruSql = "SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi 
                          FROM duyurular 
                          WHERE silinme_tarihi IS NULL 
                          AND ana_sayfada_goster = 1
                          AND firma_id = :firma_id
                          AND durum = 'Yayında'
                          AND (etkinlik_tarihi IS NULL OR etkinlik_tarihi >= CURDATE())
                          ORDER BY id DESC LIMIT 5";
            $stmt = $db->prepare($duyuruSql);
            $measureDb('home.duyuru_list', fn() => $stmt->execute([':firma_id' => $_SESSION['firma_id']]));
            $duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $de) {
            $duyurular = [];
        }

        $_SESSION[$cache_key_slider] = [
            'expires' => time() + 300,
            'slider_notifications' => $slider_notifications,
            'duyurular' => $duyurular
        ];
    }

    $gradients = [
        'linear-gradient(135deg, #0f172a 0%, #10b981 100%)',
        'linear-gradient(135deg, #0f172a 0%, #8b5cf6 100%)',
        'linear-gradient(135deg, #1e293b 0%, #f59e0b 100%)',
        'linear-gradient(135deg, #0f172a 0%, #ec4899 100%)',
        'linear-gradient(135deg, #1e1b4b 0%, #3b82f6 100%)'
    ];

    foreach ($duyurular as $idx => $d) {
        $icon = 'bx-bell';
        if (strpos(strtolower($d['baslik']), 'toplantı') !== false) {
            $icon = 'bx-group';
        }

        $grad = $gradients[$idx % count($gradients)];
        $bgStyle = $d['resim'] ? "linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.3)), url('{$d['resim']}')" : $grad;

        // Ensure background renders correctly without overriding gradient rules entirely
        $bgStyle = $d['resim'] ? $bgStyle . " center/cover no-repeat" : $grad;

        $desc = mb_strimwidth(strip_tags($d['icerik']), 0, 150, "...");
        if ($d['etkinlik_tarihi']) {
            $desc .= '<br><small class="text-warning fw-bold mt-1 d-inline-block"><i class="bx bx-time-five"></i> Son Tarih: ' . date('d.m.Y', strtotime($d['etkinlik_tarihi'])) . '</small>';
        }

        $linkClass = $d['hedef_sayfa'] ? 'cursor-pointer' : '';
        $onClick = $d['hedef_sayfa'] ? "onclick=\"window.location.href='" . htmlspecialchars($d['hedef_sayfa']) . "'\"" : "";

        $slider_notifications[] = [
            'id' => $d['id'],
            'title' => $d['baslik'],
            'description' => $desc,
            'icon' => $icon,
            'gradient' => $bgStyle,
            'link_action' => $onClick,
            'link_class' => $linkClass
        ];
    }

    // Widget İçeriklerini Tanımla
    $widgets = [];



    if (!empty($slider_notifications)) {
        ob_start(); ?>
        <div class="col-md-4 col-xl-4 widget-item" id="widget-ana-slider" style="margin-bottom: 1.5rem; position: relative;">
            <!-- Mac title bar will be injected here automatically -->

            <div id="dashboardCarousel" class="carousel slide animate-card bordro-summary-card h-100" data-bs-ride="carousel"
                style="--delay: 0s; cursor: grab;">
                <div class="carousel-indicators" style="margin-bottom: 0.5rem;">
                    <?php foreach ($slider_notifications as $index => $notif): ?>
                        <button type="button" data-bs-target="#dashboardCarousel" data-bs-slide-to="<?php echo $index; ?>"
                            class="<?php echo $index === 0 ? 'active' : ''; ?>"
                            aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            aria-label="Slide <?php echo $index + 1; ?>"
                            style="width: 8px; height: 8px; border-radius: 50%;"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner shadow-sm rounded-3 overflow-hidden border-0 h-100">
                    <?php foreach ($slider_notifications as $index => $notif): ?>
                        <div class="carousel-item h-100 <?php echo $index === 0 ? 'active' : ''; ?>">
                            <div class="carousel-content p-4 px-5 d-flex align-items-center <?= $notif['link_class'] ?> h-100"
                                <?= $notif['link_action'] ?>
                                style="background: <?php echo $notif['gradient']; ?>; min-height: 260px; position: relative; overflow: hidden;">
                                <div class="circles" style="opacity: 0.12;">
                                    <div></div>
                                    <div></div>
                                    <div></div>
                                    <div></div>
                                </div>
                                <div class="flex-grow-1 position-relative" style="z-index: 2; padding-right: 25px;">
                                    <h5 class="text-white fw-bold mb-3"
                                        style="font-family: 'Outfit', sans-serif; letter-spacing: -0.01em; font-size: 1.1rem;">
                                        <?php echo $notif['title']; ?>
                                    </h5>
                                    <p class="text-white-50 mb-0"
                                        style="max-width: 580px; line-height: 1.5; opacity: 0.8; font-size: 0.95rem;">
                                        <?php echo $notif['description']; ?>
                                    </p>
                                </div>
                                <div class="flex-shrink-0 ms-auto d-none d-md-block opacity-20 position-relative"
                                    style="z-index: 1;">
                                    <i class='bx <?php echo $notif['icon']; ?>' style="font-size: 90px; color: white;"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#dashboardCarousel" data-bs-slide="prev"
                    style="width: 4%;">
                    <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 1.2rem; height: 1.2rem;"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#dashboardCarousel" data-bs-slide="next"
                    style="width: 4%;">
                    <span class="carousel-control-next-icon" aria-hidden="true" style="width: 1.2rem; height: 1.2rem;"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
        <?php $widgets['widget-ana-slider'] = ob_get_clean();
    }

    if (\App\Service\Gate::allows("personel_listesi")) {
        $widgets['widget-personel-ozet'] = renderSkeleton('widget-personel-ozet', 'col-md-4 col-xl-4', '260px');
    }

    if (\App\Service\Gate::allows("arac_takip_yonetim")) {
        $widgets['widget-arac-ozet'] = renderSkeleton('widget-arac-ozet', 'col-md-4 col-xl-4', '260px');
    }

    if (\App\Service\Gate::allows("talepler")) {
        $widgets['widget-bekleyen-talepler'] = renderSkeleton('widget-bekleyen-talepler', 'col-6 col-md-2', '140px');
    }

    ob_start(); ?>
    <div class="col-6 col-md-2 widget-item" id="widget-gec-kalanlar">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card stat-card"
            style="--card-color: #f46a6a; border-bottom: 3px solid var(--card-color) !important; --delay: 0.65s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(244, 106, 106, 0.1);">
                        <i class="bx bx-alarm-exclamation fs-4" style="color: #f46a6a;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">GECİKME</span>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">GEÇ KALANLAR</p>
                <h4 class="mb-0 fw-bold bordro-text-heading text-danger">
                    <?php echo $gec_kalan_sayisi ?? 0; ?>
                </h4>
                <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Bugün geç kalanlar</div>
                <div class="card-footer-actions mt-2 d-flex justify-content-end">
                    <a href="index.php?p=personel-takip/list&tab=tabGecKalanlar" class="btn btn-xs btn-soft-danger rounded-pill">
                        <i class="bx bx-right-arrow-alt"></i> Git
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    $widgets['widget-gec-kalanlar'] = ob_get_clean();

    $widgets['widget-nobetciler'] = renderSkeleton('widget-nobetciler', 'col-6 col-md-2', '140px');

    ob_start(); ?>
    <?php $widgets['widget-row-break'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-6 col-md-2 widget-item" id="widget-gunluk-muhurleme">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative stat-card"
            style="--card-color: #858796; border-bottom: 3px solid var(--card-color) !important; --delay: 0.7s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container d-flex justify-content-between align-items-start">
                    <div class="icon-box" style="background: rgba(133, 135, 150, 0.1);">
                        <i class="bx bx-shield fs-4" style="color: #858796;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <a href="javascript:void(0);" class="btn-api-sync text-muted" data-action="online-puantaj-sorgula"
                            data-active-tab="muhurleme" data-bs-toggle="tooltip" title="Online sorgula(API)">
                            <i class="bx bx-refresh fs-5"></i>
                        </a>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞ</span>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                    MÜHÜRLEME</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                    data-daily="<?php echo (int)($dailyWorkStats->muhurleme ?? 0); ?>"
                    data-monthly="<?php echo (int)($monthlyWorkStats->muhurleme ?? 0); ?>" data-label-daily="GÜNLÜK MÜHÜRLEME"
                    data-label-monthly="AYLIK MÜHÜRLEME" data-sub-daily="Bugün yapılan mühürleme"
                    data-sub-monthly="Bu ay yapılan mühürleme">
                    <?php echo (int)($dailyWorkStats->muhurleme ?? 0); ?>
                </h4>
                <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün yapılan mühürleme
                </div>
                <div class="card-footer-actions mt-2">
                    <div class="btn-group btn-group-sm stats-local-toggle-group">
                        <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                            data-mode="daily">Gün</button>
                        <button type="button" class="btn btn-outline-secondary stats-local-btn"
                            data-mode="monthly">Ay</button>
                    </div>
                    <a href="index.php?p=puantaj/raporlar&tab=muhurleme" class="btn btn-xs btn-soft-primary rounded-pill">
                        <i class="bx bx-right-arrow-alt"></i> Git
                    </a>
                </div>
                <div class="mt-2 text-center py-1 rounded"
                    style="background: rgba(133, 135, 150, 0.05); font-size: 10px; color: #858796; border-top: 1px dashed rgba(133, 135, 150, 0.2);">
                    <i class="bx bx-time-five"></i> Son Güncelleme: <span
                        class="fw-bold last-update-value"><?php echo $last_update_isler ? date('d.m.Y H:i', strtotime($last_update_isler)) : '-'; ?></span>
                    <br><i class="bx bx-user-circle"></i> Yapan: <span class="fw-bold last-update-user-value"><?php echo $last_user_isler ?: '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-muhurleme'] = ob_get_clean();



    ob_start(); ?>
    <div class="col-6 col-md-2 widget-item" id="widget-gunluk-kesme-acma">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative stat-card"
            style="--card-color: #e74a3b; border-bottom: 3px solid var(--card-color) !important; --delay: 0.9s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container d-flex justify-content-between align-items-start">
                    <div class="icon-box" style="background: rgba(231, 74, 59, 0.1);">
                        <i class="bx bx-cut fs-4" style="color: #e74a3b;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <a href="javascript:void(0);" class="btn-api-sync text-muted" data-action="online-puantaj-sorgula"
                            data-active-tab="kesme" data-bs-toggle="tooltip" title="Online sorgula(API)">
                            <i class="bx bx-refresh fs-5"></i>
                        </a>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞ</span>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                    KESME AÇMA</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                    data-daily="<?php echo (int)($dailyWorkStats->kesme_acma ?? 0); ?>"
                    data-monthly="<?php echo (int)($monthlyWorkStats->kesme_acma ?? 0); ?>" data-label-daily="GÜNLÜK KESME AÇMA"
                    data-label-monthly="AYLIK KESME AÇMA" data-sub-daily="Bugün yapılan kesme/açma"
                    data-sub-monthly="Bu ay yapılan kesme/açma">
                    <?php echo (int)($dailyWorkStats->kesme_acma ?? 0); ?>
                </h4>
                <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün yapılan kesme/açma
                </div>
                <div class="card-footer-actions mt-2">
                    <div class="btn-group btn-group-sm stats-local-toggle-group">
                        <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                            data-mode="daily">Gün</button>
                        <button type="button" class="btn btn-outline-secondary stats-local-btn"
                            data-mode="monthly">Ay</button>
                    </div>
                    <a href="index.php?p=puantaj/raporlar&tab=kesme" class="btn btn-xs btn-soft-danger rounded-pill">
                        <i class="bx bx-right-arrow-alt"></i> Git
                    </a>
                </div>
                <div class="mt-2 text-center py-1 rounded"
                    style="background: rgba(231, 74, 59, 0.05); font-size: 10px; color: #e74a3b; border-top: 1px dashed rgba(231, 74, 59, 0.2);">
                    <i class="bx bx-time-five"></i> Son Güncelleme: <span
                        class="fw-bold last-update-value"><?php echo $last_update_isler ? date('d.m.Y H:i', strtotime($last_update_isler)) : '-'; ?></span>
                    <br><i class="bx bx-user-circle"></i> Yapan: <span class="fw-bold last-update-user-value"><?php echo $last_user_isler ?: '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-kesme-acma'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-6 col-md-2 widget-item" id="widget-gunluk-endeks-okuma">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative stat-card"
            style="--card-color: #36b9cc; border-bottom: 3px solid var(--card-color) !important; --delay: 1.0s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container d-flex justify-content-between align-items-start">
                    <div class="icon-box" style="background: rgba(54, 185, 204, 0.1);">
                        <i class="bx bx-tachometer fs-4" style="color: #36b9cc;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <a href="javascript:void(0);" class="btn-api-sync text-muted" data-action="online-icmal-sorgula"
                            data-bs-toggle="tooltip" title="Online sorgula(API)">
                            <i class="bx bx-refresh fs-5"></i>
                        </a>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞ</span>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                    ENDEKS OKUMA</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value" data-daily="<?php echo (int)($dailyReadingTotal ?? 0); ?>"
                    data-monthly="<?php echo (int)($monthlyReadingTotal ?? 0); ?>" data-label-daily="GÜNLÜK ENDEKS OKUMA"
                    data-label-monthly="AYLIK ENDEKS OKUMA" data-sub-daily="Bugün okunan endeksler"
                    data-sub-monthly="Bu ay okunan endeksler">
                    <?php echo (int)($dailyReadingTotal ?? 0); ?>
                </h4>
                <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün okunan endeksler
                </div>
                <div class="card-footer-actions mt-2">
                    <div class="btn-group btn-group-sm stats-local-toggle-group">
                        <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                            data-mode="daily">Gün</button>
                        <button type="button" class="btn btn-outline-secondary stats-local-btn"
                            data-mode="monthly">Ay</button>
                    </div>
                    <a href="index.php?p=puantaj/raporlar&tab=okuma" class="btn btn-xs btn-soft-info rounded-pill">
                        <i class="bx bx-right-arrow-alt"></i> Git
                    </a>
                </div>
                <div class="mt-2 text-center py-1 rounded"
                    style="background: rgba(54, 185, 204, 0.05); font-size: 10px; color: #36b9cc; border-top: 1px dashed rgba(54, 185, 204, 0.2);">
                    <i class="bx bx-time-five"></i> Son Güncelleme: <span
                        class="fw-bold last-update-value"><?php echo $last_update_endeks ? date('d.m.Y H:i', strtotime($last_update_endeks)) : '-'; ?></span>
                    <br><i class="bx bx-user-circle"></i> Yapan: <span class="fw-bold last-update-user-value"><?php echo $last_user_endeks ?: '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-endeks-okuma'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-6 col-md-2 widget-item" id="widget-gunluk-sayac-degisimi">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative stat-card"
            style="--card-color: #1cc88a; border-bottom: 3px solid var(--card-color) !important; --delay: 1.1s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container d-flex justify-content-between align-items-start">
                    <div class="icon-box" style="background: rgba(28, 200, 138, 0.1);">
                        <i class="bx bx-refresh fs-4" style="color: #1cc88a;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <a href="javascript:void(0);" class="btn-api-sync text-muted" data-action="online-sayac-degisim-sorgula"
                                data-active-tab="sokme_takma" data-bs-toggle="tooltip" title="Online sorgula(API)">
                                <i class="bx bx-refresh fs-5"></i>
                            </a>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞ</span>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                        SAYAÇ DEĞİŞİMİ</p>
                    <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                        data-daily="<?php echo (int)($dailyWorkStats->sayac_degisimi ?? 0); ?>"
                        data-monthly="<?php echo (int)($monthlyWorkStats->sayac_degisimi ?? 0); ?>"
                        data-label-daily="GÜNLÜK SAYAÇ DEĞİŞİMİ" data-label-monthly="AYLIK SAYAÇ DEĞİŞİMİ"
                        data-sub-daily="Bugün yapılan sayaç değişimi" data-sub-monthly="Bu ay yapılan sayaç değişimi">
                        <?php echo (int)($dailyWorkStats->sayac_degisimi ?? 0); ?>
                    </h4>
                    <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün yapılan sayaç
                        değişimi</div>
                    <div class="card-footer-actions mt-2">
                        <div class="btn-group btn-group-sm stats-local-toggle-group">
                            <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                                data-mode="daily">Gün</button>
                            <button type="button" class="btn btn-outline-secondary stats-local-btn"
                                data-mode="monthly">Ay</button>
                        </div>
                        <a href="index.php?p=puantaj/raporlar&tab=sokme_takma" class="btn btn-xs btn-soft-success rounded-pill">
                            <i class="bx bx-right-arrow-alt"></i> Git
                        </a>
                    </div>
                    <div class="mt-2 text-center py-1 rounded"
                        style="background: rgba(28, 200, 138, 0.05); font-size: 10px; color: #1cc88a; border-top: 1px dashed rgba(28, 200, 138, 0.2);">
                        <i class="bx bx-time-five"></i> Son Güncelleme: <span
                            class="fw-bold last-update-value"><?php echo $last_update_sayac ? date('d.m.Y H:i', strtotime($last_update_sayac)) : '-'; ?></span>
                        <br><i class="bx bx-user-circle"></i> Yapan: <span class="fw-bold last-update-user-value"><?php echo $last_user_sayac ?: '-'; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-gunluk-sayac-degisimi'] = ob_get_clean();

        ob_start(); ?>
        <div class="col-6 col-md-2 widget-item" id="widget-kacak-sayisi">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative stat-card"
                style="--card-color: #f46a6a; border-bottom: 3px solid var(--card-color) !important; --delay: 1.15s">
                <div class="card-body p-3 pb-2">
                    <div class="icon-label-container d-flex justify-content-between align-items-start">
                        <div class="icon-box" style="background: rgba(244, 106, 106, 0.1);">
                            <i class="bx bx-error-circle fs-4" style="color: #f46a6a;"></i>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">KAÇAK</span>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                        KAÇAK
                    </p>
                    <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                        data-daily="<?php echo (int)($kacakDailyTotal->toplam ?? 0); ?>"
                        data-monthly="<?php echo (int)($kacakMonthlyTotal->toplam ?? 0); ?>" data-label-daily="GÜNLÜK KAÇAK"
                        data-label-monthly="AYLIK KAÇAK" data-sub-daily="Bugün tespit edilen/girilen"
                        data-sub-monthly="Bu ay tespit edilen/girilen">
                        <?php echo (int)($kacakDailyTotal->toplam ?? 0); ?>
                    </h4>
                    <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün tespit edilen/girilen
                    </div>
                    <div class="card-footer-actions mt-2">
                        <div class="btn-group btn-group-sm stats-local-toggle-group">
                            <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                                data-mode="daily">Gün</button>
                            <button type="button" class="btn btn-outline-secondary stats-local-btn"
                                data-mode="monthly">Ay</button>
                        </div>
                        <a href="index.php?p=puantaj/raporlar&tab=kacakkontrol"
                            class="btn btn-xs btn-soft-danger rounded-pill">
                            <i class="bx bx-right-arrow-alt"></i> Git
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-kacak-sayisi'] = ob_get_clean();

        ob_start(); ?>
        <div class="<?php echo getWidgetWidthClass('widget-endeks-karsilastirma', 'col-12'); ?> widget-item"
            id="widget-endeks-karsilastirma" style="<?php echo getWidgetStyle('widget-endeks-karsilastirma'); ?>">
            <div class="card summary-card"
                style="background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(248,250,252,0.99)); border: 1px solid rgba(226,232,240,0.8); border-radius: 12px; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.05), 0 2px 5px -2px rgba(0,0,0,0.02);">
                <div class="card-header align-items-center d-flex flex-wrap gap-2"
                    style="border-bottom: 1px solid rgba(226,232,240,0.6);">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2" style="font-family: 'Outfit', sans-serif;">
                        <i class='bx bx-grid-vertical drag-handle' style="cursor: move;"></i>
                        <i class='bx bx-git-compare' style="color: #6366f1;"></i>
                        Endeks Okuma Karşılaştırması
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <span id="endeksCompGunBadge" class="badge bg-primary-subtle text-primary d-none"
                            style="font-size: 11px; font-weight: 600; border-radius: 6px; padding: 5px 10px; border: 1px solid rgba(99,102,241,0.2);">
                            <i class="bx bx-calendar-event me-1"></i>Ayın 1'i - <span id="endeksCompGunNo"></span>'ı arası
                        </span>
                        <div class="btn-group btn-group-sm" role="group" id="endeksCompViewToggle">
                            <button type="button" class="btn btn-outline-primary btn-sm active fw-semibold" data-view="bolge"
                                style="font-size: 11px;">
                                <i class="bx bx-map-alt me-1"></i>Bölge
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-view="personel"
                                style="font-size: 11px;">
                                <i class="bx bx-user me-1"></i>Personel
                            </button>
                        </div>
                        <?php echo getWidthControl(); ?>
                    </div>
                </div>
                <div class="card-body p-0"
                    style="min-height: <?php echo getWidgetHeight('widget-endeks-karsilastirma', 'auto'); ?>;">
                    <div id="endeksCompLoading" class="p-3">
                        <div class="dashboard-skeleton-table">
                            <div class="skeleton-line w-35 mb-3"></div>
                            <div class="skeleton-line w-100 mb-2"></div>
                            <div class="skeleton-line w-100 mb-2"></div>
                            <div class="skeleton-line w-90 mb-2"></div>
                            <div class="skeleton-line w-95"></div>
                        </div>
                    </div>
                    <div id="endeksCompContent" style="display: none;">
                        <!-- Bölge bazlı görünüm -->
                        <div id="endeksCompBolge"></div>
                        <!-- Personel bazlı görünüm -->
                        <div id="endeksCompPersonel" style="display: none;"></div>
                    </div>
                    <div id="endeksCompEmpty" style="display: none;" class="text-center py-5">
                        <i class="bx bx-bar-chart-alt-2" style="font-size: 48px; opacity: 0.2; color: #94a3b8;"></i>
                        <p class="text-muted mt-2 mb-0">Karşılaştırma verisi bulunamadı.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php
    $widgets['widget-endeks-karsilastirma'] = ob_get_clean();

    if (\App\Service\Gate::allows("gorevler")) {
        $widgets['widget-yaklasan-gorevler'] = renderSkeleton('widget-yaklasan-gorevler', getWidgetWidth('widget-yaklasan-gorevler', 'col-md-6'), '260px');
    }

    if (\App\Service\Gate::allows("gorev_bildirim_log_kayitlari")) {
        $widgets['widget-bildirimler'] = renderSkeleton('widget-bildirimler', getWidgetWidth('widget-bildirimler', 'col-12'), '400px');
    }

    if (\App\Service\Gate::allows("talepler")) {
        $widgets['widget-talepler'] = renderSkeleton('widget-talepler', getWidgetWidth('widget-talepler', 'col-md-6'), '300px');
    }

    if (Gate::allows("ana_sayfa_izinli_personel_karti") || $izinModel->getRestrictedDept() !== null) {
        $widgets['widget-izindekiler'] = renderSkeleton('widget-izindekiler', getWidgetWidth('widget-izindekiler', 'col-md-6'), '300px');
    }

    ob_start(); ?>
        <div class="<?php echo getWidgetWidthClass('widget-is-turu-istatistikleri', 'col-md-6'); ?> widget-item"
            id="widget-is-turu-istatistikleri" style="<?php echo getWidgetStyle('widget-is-turu-istatistikleri'); ?>">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> İş Türü İstatistikleri</h5>
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-shrink-0">
                            <select class="form-select form-select-sm" id="stats-year-filter" style="width: 100px;">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
                                    echo "<option value='$y'>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <?php echo getWidthControl(); ?>
                    </div>
                </div>
                <div class="card-body"
                    style="height: <?php echo getWidgetHeight('widget-is-turu-istatistikleri', 'auto'); ?>; overflow-y: auto;">
                    <div id="work-type-stats-chart" style="min-height: 400px; height: 100%;">
                        <div id="work-type-stats-skeleton" class="dashboard-chart-skeleton">
                            <div class="skeleton-line w-40 mb-3"></div>
                            <div class="skeleton-chart-bars">
                                <span style="height: 32%;"></span>
                                <span style="height: 46%;"></span>
                                <span style="height: 64%;"></span>
                                <span style="height: 52%;"></span>
                                <span style="height: 75%;"></span>
                                <span style="height: 58%;"></span>
                                <span style="height: 80%;"></span>
                                <span style="height: 43%;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-is-turu-istatistikleri'] = ob_get_clean();

        ob_start(); ?>
        <div class="<?php echo getWidgetWidthClass('widget-is-emri-sonucu-istatistikleri', 'col-md-6'); ?> widget-item"
            id="widget-is-emri-sonucu-istatistikleri" style="<?php echo getWidgetStyle('widget-is-emri-sonucu-istatistikleri'); ?>">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> İş Emri Sonuç İstatistikleri</h5>
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-shrink-0 d-flex gap-2">
                            <select class="form-select form-select-sm" id="stats-result-month-filter" style="width: 120px;">
                                <?php
                                $aylar = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
                                $currentMonth = date('n');
                                foreach ($aylar as $index => $ay) {
                                    $val = $index + 1;
                                    $selected = ($val == $currentMonth) ? 'selected' : '';
                                    echo "<option value='$val' $selected>$ay</option>";
                                }
                                ?>
                            </select>
                            <select class="form-select form-select-sm" id="stats-result-year-filter" style="width: 100px;">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
                                    echo "<option value='$y'>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <?php echo getWidthControl(); ?>
                    </div>
                </div>
                <div class="card-body"
                    style="height: <?php echo getWidgetHeight('widget-is-emri-sonucu-istatistikleri', 'auto'); ?>; overflow-y: auto;">
                    <div id="work-result-stats-chart" style="min-height: 400px; height: 100%;">
                        <div id="work-result-stats-skeleton" class="dashboard-chart-skeleton">
                            <div class="skeleton-line w-50 mb-3"></div>
                            <div class="skeleton-chart-lines">
                                <span class="w-100"></span>
                                <span class="w-85"></span>
                                <span class="w-70"></span>
                                <span class="w-92"></span>
                                <span class="w-60"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-is-emri-sonucu-istatistikleri'] = ob_get_clean();

        //ob_start(); ?>
        <!-- <div class="col-md-4 widget-item" id="widget-istatistikler">
        <div class="card ">
            <div class="card-header">
                <h5><i class='bx bx-grid-vertical drag-handle me-1'></i> Genel Özet</h5>
            </div>
            <div class="card-body">
                <div id="chart3"></div>
            </div>
        </div>
    </div> -->
        <?php //$widgets['widget-istatistikler'] = ob_get_clean();
        
            // Sıralamayı DB öncelikli, çerez yedekli akıştan kullan
            $render_order = !empty($render_order) ? $render_order : array_keys($widgets);

            // Slider her zaman üstte olmalı
            if (!in_array('widget-ana-slider', $render_order) && isset($widgets['widget-ana-slider'])) {
                array_unshift($render_order, 'widget-ana-slider');
            }
            ?>

        <div class="container-fluid">

            <style id="dashboard-skeleton-critical">
                /* Widget visibility */
                .widget-hidden {
                    display: none !important;
                }

                /* Widget collapsed state */
                #dashboard-widgets .widget-collapsed .card > *:not(.mac-title-bar) {
                    display: none !important;
                }
                #dashboard-widgets .widget-collapsed {
                    height: 32px !important;
                    min-height: 32px !important;
                    overflow: hidden !important;
                }
                #dashboard-widgets .widget-collapsed .card,
                #dashboard-widgets .widget-collapsed .carousel {
                    height: 32px !important;
                    min-height: 32px !important;
                    overflow: hidden !important;
                }

                /* Free layout */
                #dashboard-widgets.free-layout-active {
                    position: relative !important;
                    min-height: 1000px;
                }

                /* Resizable widget */
                #dashboard-widgets .resizable-widget {
                    position: relative;
                    overflow: visible !important;
                    resize: none !important;
                    margin-bottom: 0;
                    min-width: 180px;
                    min-height: 120px;
                }
                #dashboard-widgets .resizable-widget .card {
                    height: 100% !important;
                    position: relative !important;
                    z-index: 1;
                }
                #dashboard-widgets .dashboard-resizing {
                    user-select: none !important;
                }

                /* Resize grips */
                #dashboard-widgets .dashboard-resize-grip {
                    position: absolute !important;
                    right: -7px !important;
                    bottom: -7px !important;
                    width: 26px !important;
                    height: 26px !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    background: #2563eb !important;
                    border: 3px solid #fff !important;
                    border-radius: 50% !important;
                    cursor: nwse-resize !important;
                    z-index: 2147483000 !important;
                    pointer-events: auto !important;
                    touch-action: none !important;
                    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45) !important;
                }
                #dashboard-widgets .dashboard-resize-grip::after {
                    content: "" !important;
                    width: 8px !important;
                    height: 8px !important;
                    border-right: 2px solid #fff !important;
                    border-bottom: 2px solid #fff !important;
                    display: block !important;
                }
            </style>
            <style>
                /* ===================================================
                   macOS / iOS Style Widget Title Bars
                   Premium Design - Enterprise Dashboard
                   =================================================== */
                
                /* Card border-radius global */
                #dashboard-widgets .card,
                #dashboard-widgets .carousel {
                    border-radius: 14px !important;
                    overflow: hidden !important;
                    border: 1px solid rgba(203, 213, 225, 0.6) !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04) !important;
                }

                /* MAC TITLE BAR — authentic macOS window chrome */
                .mac-title-bar {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    height: 32px !important;
                    min-height: 32px !important;
                    padding: 0 8px 0 10px !important;
                    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
                    border-bottom: 1px solid #e2e8f0 !important;
                    border-top-left-radius: 14px !important;
                    border-top-right-radius: 14px !important;
                    cursor: grab !important;
                    user-select: none !important;
                    position: relative !important;
                    z-index: 10 !important;
                    flex-shrink: 0 !important;
                    transition: background 0.15s ease !important;
                }
                .mac-title-bar:active {
                    cursor: grabbing !important;
                }
                .widget-item:hover .mac-title-bar {
                    background: linear-gradient(180deg, #f1f5f9 0%, #e8eef5 100%) !important;
                }

                /* Traffic-light control group */
                .mac-controls {
                    display: flex !important;
                    align-items: center !important;
                    gap: 5px !important;
                    flex-shrink: 0 !important;
                    width: 34px !important;
                }

                /* Individual traffic-light button */
                .mac-control {
                    width: 9px !important;
                    height: 9px !important;
                    border-radius: 50% !important;
                    cursor: pointer !important;
                    position: relative !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    transition: filter 0.12s ease, transform 0.1s ease !important;
                    flex-shrink: 0 !important;
                }
                .mac-control:hover {
                    filter: brightness(0.88) !important;
                    transform: scale(1.15) !important;
                }
                .mac-control:active {
                    transform: scale(0.92) !important;
                    filter: brightness(0.75) !important;
                }

                /* Hover icon reveal on traffic lights */
                .mac-control::after {
                    content: '' !important;
                    position: absolute !important;
                    opacity: 0 !important;
                    transition: opacity 0.1s ease !important;
                    font-size: 6px !important;
                    font-weight: 900 !important;
                    line-height: 1 !important;
                    color: rgba(0,0,0,0.45) !important;
                    pointer-events: none !important;
                }
                .mac-controls:hover .mac-control::after {
                    opacity: 1 !important;
                }
                .mac-close::after  { content: '✕' !important; }
                .mac-minimize::after { content: '−' !important; }
                .mac-maximize::after { content: '+' !important; }

                /* Traffic light colors — authentic macOS */
                .mac-close {
                    background: #ff5f57 !important;
                    box-shadow: 0 0 0 0.5px #e0443e !important;
                }
                .mac-minimize {
                    background: #febc2e !important;
                    box-shadow: 0 0 0 0.5px #d9a11c !important;
                }
                .mac-maximize {
                    background: #28c840 !important;
                    box-shadow: 0 0 0 0.5px #1aab29 !important;
                }

                /* Title text — centered, bold, professional */
                .mac-title-text {
                    flex: 1 !important;
                    display: block !important;
                    min-width: 0 !important;
                    text-align: center !important;
                    font-size: 0.68rem !important;
                    font-weight: 600 !important;
                    color: #334155 !important;
                    letter-spacing: 0.01em !important;
                    text-transform: uppercase !important;
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                    padding: 0 4px !important;
                    pointer-events: none !important;
                }
                .mac-title-text:empty::before {
                    content: attr(data-fallback) !important;
                }

                /* Drag indicator (right side) */
                .drag-handle-indicator {
                    width: 42px !important;
                    display: flex !important;
                    justify-content: flex-end !important;
                    align-items: center !important;
                    color: #94a3b8 !important;
                    font-size: 1rem !important;
                    opacity: 0.55 !important;
                    cursor: grab !important;
                    flex-shrink: 0 !important;
                    transition: opacity 0.15s ease !important;
                }
                .widget-item:hover .drag-handle-indicator {
                    opacity: 0.85 !important;
                }

                .finder-tabs-nav .nav-link {
                    transition: all 0.18s ease !important;
                }
                .finder-tabs-nav .nav-link.active {
                    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
                    color: #0f172a !important;
                    border-color: rgba(148,163,184,0.32) !important;
                    box-shadow: 0 8px 20px -14px rgba(15,23,42,0.45), inset 0 1px 0 rgba(255,255,255,0.95) !important;
                }
                .finder-tabs-nav .nav-link:not(.active):hover {
                    background: rgba(255,255,255,0.78) !important;
                    color: #1e293b !important;
                }
                .mac-toolbar-slot {
                    display: flex !important;
                    align-items: center !important;
                    min-width: 0 !important;
                    margin-left: 12px !important;
                    flex: 0 1 auto !important;
                }
                .finder-tabs-shell-inbar {
                    display: block !important;
                    padding: 0 !important;
                    border-bottom: 0 !important;
                    background: transparent !important;
                }
                .finder-tabs-shell-inbar .d-flex {
                    gap: 0.5rem !important;
                    flex-wrap: nowrap !important;
                }
                .finder-tabs-shell-inbar .flex-grow-1 {
                    flex: 0 1 auto !important;
                }
                .finder-tabs-shell-inbar .finder-tabs-nav {
                    flex-wrap: nowrap !important;
                }
                .finder-tabs-shell-inbar .finder-tabs-nav .nav-link {
                    padding: 0.34rem 0.72rem !important;
                    font-size: 0.72rem !important;
                }
                .finder-tabs-shell-inbar .btn {
                    padding: 0.34rem 0.72rem !important;
                    font-size: 0.72rem !important;
                    white-space: nowrap !important;
                }
                .widget-item#widget-bildirimler .mac-title-text {
                    display: none !important;
                }
                .widget-item#widget-bildirimler .mac-title-bar {
                    justify-content: flex-start !important;
                    gap: 12px !important;
                    height: 54px !important;
                    min-height: 54px !important;
                    padding-top: 6px !important;
                    padding-bottom: 6px !important;
                }
                .widget-item#widget-bildirimler .drag-handle-indicator {
                    margin-left: auto !important;
                    align-self: center !important;
                }
                .widget-item#widget-bildirimler .mac-toolbar-slot {
                    align-self: stretch !important;
                    display: flex !important;
                    align-items: center !important;
                    overflow: visible !important;
                }
                .widget-item#widget-bildirimler .finder-tabs-shell-inbar .finder-tabs-nav,
                .widget-item#widget-bildirimler .finder-tabs-shell-inbar .d-flex {
                    align-items: center !important;
                }
                .widget-item#widget-bildirimler .finder-tabs-shell-inbar .finder-tabs-nav .nav-link {
                    min-height: 34px !important;
                }

                /* Free layout window shadow */
                #dashboard-widgets.free-layout-active .widget-item {
                    box-shadow: 0 10px 30px -6px rgba(0, 0, 0, 0.12),
                                0 4px 10px -2px rgba(0, 0, 0, 0.07) !important;
                    border-radius: 14px !important;
                    margin-bottom: 0 !important;
                }

                /* Collapsed state */
                .widget-collapsed .card > *:not(.mac-title-bar) {
                    display: none !important;
                }

                /* Suppress original card headers & duplicate drag handles */
                .widget-item .card-header:not(.mac-title-bar-wrapper),
                .widget-item .drag-handle:not(.mac-title-bar .drag-handle),
                .widget-item i.bx-grid-vertical:not(.mac-title-bar i),
                .widget-item i.bx-grid-horizontal:not(.mac-title-bar i) {
                    display: none !important;
                }
                #dashboard-widgets .ui-resizable-s {
                    left: 14px !important;
                    right: 14px !important;
                    bottom: -5px !important;
                    height: 10px !important;
                    border-radius: 999px !important;
                    background: rgba(37, 99, 235, 0.28) !important;
                    cursor: s-resize !important;
                }
                #dashboard-page-skeleton {
                    padding: 0.5rem 0 1rem;
                    display: block;
                }

                #dashboard-page-skeleton .skeleton-toolbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 1rem;
                }

                #dashboard-page-skeleton .skeleton-toolbar-right {
                    display: flex;
                    gap: 8px;
                }

                #dashboard-page-skeleton .skeleton-grid {
                    display: grid;
                    grid-template-columns: repeat(12, minmax(0, 1fr));
                    gap: 16px;
                }

                #dashboard-page-skeleton .skeleton-card {
                    min-height: 140px;
                    border-radius: 12px;
                    padding: 14px;
                    border: 1px solid rgba(203, 213, 225, 0.45);
                    background: rgba(248, 250, 252, 0.8);
                }

                #dashboard-page-skeleton .skeleton-card-lg {
                    min-height: 260px;
                    grid-column: span 6;
                }

                #dashboard-page-skeleton .skeleton-card-sm {
                    grid-column: span 3;
                }

                #dashboard-page-skeleton .skeleton-card-full {
                    min-height: 260px;
                    grid-column: span 12;
                }

                #dashboard-page-skeleton .skeleton-line {
                    display: block;
                    height: 12px;
                    border-radius: 8px;
                    margin-bottom: 10px;
                    background: linear-gradient(90deg, rgba(203, 213, 225, 0.35) 25%, rgba(203, 213, 225, 0.6) 50%, rgba(203, 213, 225, 0.35) 75%);
                    background-size: 200% 100%;
                    animation: skeleton-shimmer 1.4s linear infinite;
                }

                @media (max-width: 991.98px) {

                    #dashboard-page-skeleton .skeleton-card-lg,
                    #dashboard-page-skeleton .skeleton-card-sm,
                    #dashboard-page-skeleton .skeleton-card-full {
                        grid-column: span 12;
                    }
                }
            </style>

            <div id="dashboard-page-skeleton" class="dashboard-page-skeleton">
                <div class="skeleton-toolbar">
                    <div class="skeleton-line w-35"></div>
                    <div class="skeleton-toolbar-right">
                        <div class="skeleton-line" style="width: 170px;"></div>
                        <div class="skeleton-line" style="width: 190px;"></div>
                    </div>
                </div>

                <div class="skeleton-grid">
                    <div class="skeleton-card skeleton-card-lg">
                        <div class="skeleton-line w-60"></div>
                        <div class="skeleton-line w-95"></div>
                        <div class="skeleton-line w-85"></div>
                    </div>
                    <div class="skeleton-card skeleton-card-lg">
                        <div class="skeleton-line w-50"></div>
                        <div class="skeleton-line w-90"></div>
                        <div class="skeleton-line w-70"></div>
                    </div>

                    <div class="skeleton-card skeleton-card-sm">
                        <div class="skeleton-line w-60"></div>
                        <div class="skeleton-line w-90"></div>
                        <div class="skeleton-line w-50"></div>
                    </div>
                    <div class="skeleton-card skeleton-card-sm">
                        <div class="skeleton-line w-50"></div>
                        <div class="skeleton-line w-85"></div>
                        <div class="skeleton-line w-60"></div>
                    </div>
                    <div class="skeleton-card skeleton-card-sm">
                        <div class="skeleton-line w-55"></div>
                        <div class="skeleton-line w-95"></div>
                        <div class="skeleton-line w-40"></div>
                    </div>
                    <div class="skeleton-card skeleton-card-sm">
                        <div class="skeleton-line w-45"></div>
                        <div class="skeleton-line w-80"></div>
                        <div class="skeleton-line w-65"></div>
                    </div>

                    <div class="skeleton-card skeleton-card-full">
                        <div class="skeleton-line w-40"></div>
                        <div class="skeleton-line w-100"></div>
                        <div class="skeleton-line w-100"></div>
                        <div class="skeleton-line w-95"></div>
                    </div>
                </div>
            </div>

            <div id="dashboard-page-content" style="display: none;">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <?php
                        $maintitle = 'Ana Sayfa';
                        $title = '';
                        ?>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary btn-sm dropdown-toggle d-flex align-items-center" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false" style="font-weight: 600; padding: 6px 16px; border-radius: 8px;">
                                <i class="bx bx-cog me-2 fs-5"></i> Dashboard Ayarları
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2"
                                style="min-width: 320px; z-index: 1060; border-radius: 12px;">
                                <li class="px-3 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="dropdown-header px-0 mb-0 fw-bold text-primary">YERLEŞİM AYARLARI</h6>
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0 fw-bold" id="btn-reset-dashboard" style="text-decoration: none; font-size: 11px;">
                                            <i class="bx bx-reset me-1"></i> SIFIRLA
                                        </button>
                                    </div>
                                </li>
                                <li class="px-3 mb-2">
                                    <div class="form-check form-switch p-0 d-flex justify-content-between align-items-center" style="margin-left: 0;">
                                        <label class="form-check-label mb-0 cursor-pointer fw-semibold text-muted" for="switch-free-layout" style="font-size: 13px;">Serbest Yerleşim Modu</label>
                                        <input class="form-check-input cursor-pointer m-0" type="checkbox" id="switch-free-layout" style="width: 36px; height: 18px;">
                                    </div>
                                    <div class="small text-muted mt-1" style="font-size: 10px; line-height: 1.2;">Kartları istediğiniz yere taşıyabilir ve boyutlandırabilirsiniz.</div>
                                </li>
                                <li class="px-3 mb-2" id="random-layout-row" style="display:none;">
                                    <button type="button" class="btn btn-soft-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-2" id="btn-random-layout" style="border-radius: 10px; font-weight: 600;">
                                        <i class="bx bx-shuffle"></i> Rastgele Yerleşim
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li class="px-3 py-1">
                                    <h6 class="dropdown-header px-0 mb-1 fw-bold text-primary">KART GÖRÜNÜRLÜĞÜ</h6>
                                </li>
                                <div style="max-height: 350px; overflow-y: auto; padding: 0 8px;">
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-ana-slider" checked>
                                            <strong style="font-size: 13px;">Haberler ve Duyurular</strong>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-personel-ozet" checked>
                                            <span style="font-size: 13px;">Personel Durumu</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-arac-ozet" checked>
                                            <span style="font-size: 13px;">Araç Durumu</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-bekleyen-talepler" checked>
                                            <span style="font-size: 13px;">Bekleyen Talepler</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-gec-kalanlar" checked>
                                            <span style="font-size: 13px;">Geç Kalanlar</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-nobetciler" checked>
                                            <span style="font-size: 13px;">Bugünkü Nöbetçiler</span>
                                        </label>
                                    </li>
                                    <li><hr class="dropdown-divider opacity-50"></li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-bildirimler" checked>
                                            <span style="font-size: 13px;">Görev ve Bildirimler</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-talepler" checked>
                                            <span style="font-size: 13px;">Arıza/İzin/Avans Talepleri</span>
                                        </label>
                                    </li>
                                    <?php if (Gate::allows("ana_sayfa_izinli_personel_karti") || $izinModel->getRestrictedDept() !== null): ?>
                                        <li>
                                            <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                                <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-izindekiler" checked>
                                                <span style="font-size: 13px;">İzinde Olan Personeller</span>
                                            </label>
                                        </li>
                                    <?php endif; ?>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-is-turu-istatistikleri" checked>
                                            <span style="font-size: 13px;">İş Türü İstatistikleri</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-is-emri-sonucu-istatistikleri" checked>
                                            <span style="font-size: 13px;">İş Emri Sonuç İstatistikleri</span>
                                        </label>
                                    </li>
                                    <li><hr class="dropdown-divider opacity-50"></li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-gunluk-kesme-acma" checked>
                                            <span style="font-size: 13px;">Günlük Kesme Açma</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-gunluk-endeks-okuma" checked>
                                            <span style="font-size: 13px;">Günlük Endeks Okuma</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-gunluk-sayac-degisimi" checked>
                                            <span style="font-size: 13px;">Günlük Sayaç Değişimi</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-gunluk-muhurleme" checked>
                                            <span style="font-size: 13px;">Günlük Mühürleme</span>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-kacak-sayisi" checked>
                                            <span style="font-size: 13px;">Kaçak Kontrol</span>
                                        </label>
                                    </li>
                                    <?php if (Gate::allows("gorevler")): ?>
                                        <li>
                                            <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                                <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-yaklasan-gorevler" checked>
                                                <span style="font-size: 13px;">Yaklaşan Görevler</span>
                                            </label>
                                        </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider opacity-50"></li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-endeks-karsilastirma" checked>
                                            <strong style="font-size: 13px;">Endeks Karşılaştırma</strong>
                                        </label>
                                    </li>
                                </div>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="<?php echo $dashboard_is_free ? 'd-block free-layout-active' : 'row'; ?>" id="dashboard-widgets" style="position: relative; min-height: 800px;">
                    <?php
                    foreach ($render_order as $widget_id) {
                        if (isset($widgets[$widget_id])) {
                            echo $widgets[$widget_id];
                            unset($widgets[$widget_id]);
                        }
                    }
                    // Eksik kalan widget varsa ekle
                    foreach ($widgets as $widget_html) {
                        echo $widget_html;
                    }
                    ?>
                </div>

            </div>
        </div>

        <!-- Modals (Detaylar, Onaylar vs.) -->
        <div class="modal fade" id="modalHomeDetay" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header py-2 px-3 position-relative" id="modalHeader"
                        style="background: #00d2ff; min-height: 50px; display: flex; align-items: center;">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-list-ul text-white fs-4 me-2" id="modalHeaderIcon"></i>
                            <h5 class="modal-title text-white fw-semibold mb-0" id="modalTalepTipi">Talep Detayı</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute"
                            style="right: 1rem; top: 50%; transform: translateY(-50%);" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="modalLoading" class="text-center py-5">
                            <div class="spinner-border text-info" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                        </div>
                        <div id="modalContent" class="row" style="display:none;">
                            <!-- Personel Bilgileri (Sol Taraf) -->
                            <div class="col-md-4 text-center border-end">
                                <div class="mb-3 mt-2">
                                    <img id="modalResim" src="assets/images/users/user-dummy-img.jpg"
                                        class="rounded-circle shadow-sm"
                                        style="width:120px;height:120px;object-fit:cover;border:4px solid #f8f9fa;">
                                </div>
                                <h5 class="fw-bold mb-1" id="modalPersonelAdi">-</h5>
                                <p class="text-muted small mb-1" id="modalDepartman"></p>
                                <p class="text-muted fs-11 text-uppercase mb-0" id="modalGorev"></p>
                            </div>
                            <!-- Talep Detayları (Sağ Taraf) -->
                            <div class="col-md-8">
                                <table class="table table-sm table-borderless align-middle mb-0">
                                    <tbody>
                                        <tr class="border-bottom">
                                            <td class="text-muted py-2" width="30%">Oluşturma Tarihi:</td>
                                            <td class="fw-semibold py-2" id="modalTarih">-</td>
                                        </tr>
                                        <tr class="border-bottom" id="rowBaslik">
                                            <td class="text-muted py-2">Başlık:</td>
                                            <td class="fw-bold py-2" id="modalBaslik">-</td>
                                        </tr>
                                        <tr class="border-bottom">
                                            <td class="text-muted py-2">Durum:</td>
                                            <td class="py-2" id="modalDurum"></td>
                                        </tr>
                                        <tr class="border-bottom">
                                            <td class="text-muted py-2">Açıklama:</td>
                                            <td class="py-2 text-wrap" id="modalDetay">-</td>
                                        </tr>
                                        <tr id="rowFotograf" style="display:none;">
                                            <td class="text-muted py-2">Fotoğraf:</td>
                                            <td class="py-2">
                                                <a href="#" id="modalFotoLink" target="_blank">
                                                    <img id="modalFoto" src="" class="img-thumbnail"
                                                        style="max-height: 150px; cursor: pointer;">
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Kapat</button>
                        <a href="#" id="modalGitBtn" class="btn btn-primary d-none">Git</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Detay Modal - Premium Design -->
        <style>
            #modalLogDetay .modal-content {
                border: none;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 25px 70px rgba(0, 0, 0, 0.18);
            }

            #modalLogDetay .log-modal-header {
                background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
                padding: 1.5rem 1.75rem;
                position: relative;
                overflow: hidden;
            }

            #modalLogDetay .log-modal-header::before {
                content: '';
                position: absolute;
                top: -40px;
                right: -40px;
                width: 140px;
                height: 140px;
                background: rgba(255, 255, 255, 0.07);
                border-radius: 50%;
            }

            #modalLogDetay .log-modal-header::after {
                content: '';
                position: absolute;
                bottom: -50px;
                left: 30px;
                width: 100px;
                height: 100px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 50%;
            }

            #modalLogDetay .modal-icon-wrap {
                width: 44px;
                height: 44px;
                background: rgba(255, 255, 255, 0.15);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(6px);
                flex-shrink: 0;
            }

            #modalLogDetay .log-meta-card {
                background: #f8f9fc;
                border: 1px solid #e9ecf3;
                border-radius: 12px;
                padding: 0.85rem 1rem;
                transition: border-color 0.2s;
            }

            #modalLogDetay .log-meta-card:hover {
                border-color: #4361ee44;
            }

            #modalLogDetay .log-meta-label {
                font-size: 0.7rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #8a94ad;
                margin-bottom: 3px;
            }

            #modalLogDetay .log-meta-value {
                font-size: 0.925rem;
                font-weight: 700;
                color: #2d3a56;
                margin: 0;
                line-height: 1.3;
            }

            #modalLogDetay .log-content-box {
                background: linear-gradient(135deg, #f8f9fc 0%, #f0f3ff 100%);
                border: 1px solid #dde2f1;
                border-radius: 14px;
                padding: 1.1rem 1.25rem;
                min-height: 60px;
            }

            #modalLogDetay .log-content-box .change-table {
                border-radius: 10px;
                overflow: hidden;
                border: 1px solid #dde2f1;
                margin-top: 0.75rem;
            }

            #modalLogDetay .log-content-box .change-table thead th {
                background: #4361ee;
                color: #fff;
                font-size: 0.78rem;
                font-weight: 600;
                letter-spacing: 0.04em;
                padding: 0.6rem 0.9rem;
                border: none;
            }

            #modalLogDetay .log-content-box .change-table tbody td {
                padding: 0.55rem 0.9rem;
                font-size: 0.85rem;
                border-color: #eaecf4;
                vertical-align: middle;
            }

            #modalLogDetay .log-content-box .change-table tbody tr:hover td {
                background: #f0f3ff;
            }

            #modalLogDetay .log-content-box .change-table .field-cell {
                font-weight: 600;
                color: #4361ee;
                width: 32%;
            }

            #modalLogDetay .change-arrow {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 0.82rem;
            }

            #modalLogDetay .change-arrow .from-val {
                background: #fee2e2;
                color: #b91c1c;
                padding: 1px 8px;
                border-radius: 20px;
                font-size: 0.78rem;
            }

            #modalLogDetay .change-arrow .to-val {
                background: #dcfce7;
                color: #15803d;
                padding: 1px 8px;
                border-radius: 20px;
                font-size: 0.78rem;
            }

            #modalLogDetay .change-arrow .arrow-icon {
                color: #94a3b8;
                font-size: 1rem;
            }

            #modalLogDetay .log-type-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 0.03em;
            }

            #modalLogDetay .modal-body {
                padding: 1.5rem 1.75rem 1rem;
            }

            #modalLogDetay .modal-footer {
                padding: 1rem 1.75rem 1.5rem;
                border-top: 1px solid #eaecf4;
                background: #fcfcff;
            }

            #modalLogDetay .btn-close-modal {
                background: linear-gradient(135deg, #4361ee, #3a0ca3);
                color: #fff;
                border: none;
                padding: 0.55rem 1.75rem;
                border-radius: 10px;
                font-size: 0.875rem;
                font-weight: 600;
                transition: opacity 0.2s, transform 0.15s;
                box-shadow: 0 4px 14px rgba(67, 97, 238, 0.35);
            }

            #modalLogDetay .btn-close-modal:hover {
                opacity: 0.9;
                transform: translateY(-1px);
                color: #fff;
            }

            #modalLogDetay .section-divider {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 1.1rem 0 0.85rem;
                color: #8a94ad;
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.07em;
            }

            #modalLogDetay .section-divider::before,
            #modalLogDetay .section-divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: #e2e6f0;
            }
        </style>

        <div class="modal fade" id="modalLogDetay" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="log-modal-header d-flex align-items-center gap-3" style="position:relative;z-index:1;">
                        <div class="modal-icon-wrap">
                            <i class="bx bx-bell text-white fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 text-white fw-bold" style="font-size:1rem;letter-spacing:0.01em;">Bildirim Detayı
                            </h5>
                            <small class="text-white" style="opacity:0.65;font-size:0.75rem;">Sistem Olay Kaydı</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Kapat"></button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">

                        <!-- Meta Kartlar -->
                        <div class="row g-3 mb-1">
                            <div class="col-md-5">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-tag me-1"></i>İşlem Tipi</div>
                                    <p id="logDetayTitle" class="log-meta-value">-</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-user me-1"></i>İşlemi Yapan</div>
                                    <p id="logDetayUser" class="log-meta-value">-</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-calendar me-1"></i>Tarih</div>
                                    <p id="logDetayDate" class="log-meta-value" style="font-size:0.82rem;">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- İçerik -->
                        <div class="section-divider">İçerik Detayı</div>
                        <div id="logDetayContent" class="log-content-box"
                            style="white-space:pre-wrap;line-height:1.65;font-size:0.875rem;color:#374151;">-</div>

                    </div>

                    <!-- Footer -->
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn-close-modal" data-bs-dismiss="modal">
                            <i class="bx bx-x me-1"></i>Kapat
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="modal fade" id="modalAvansOnay" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bx bx-check-circle me-2"></i>Avans Onayı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formAvansOnay">
                        <input type="hidden" name="id" id="avans_onay_id">
                        <input type="hidden" name="action" value="avans-onayla">
                        <div class="modal-body">
                            <div class="alert alert-success">
                                <strong id="avans_onay_personel"></strong> personelinin
                                <strong id="avans_onay_tutar"></strong> tutarındaki avans talebini onaylamak istediğinize emin
                                misiniz?
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama (Opsiyonel)</label>
                                <textarea class="form-control" name="aciklama" rows="2"
                                    placeholder="Onay açıklaması..."></textarea>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="hesaba_isle" id="hesabaIsle" value="1"
                                    checked>
                                <label class="form-check-label" for="hesabaIsle">
                                    Avansı bordroya kesinti olarak işle
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i>Onayla</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalAvansRed" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bx bx-x-circle me-2"></i>Avans Reddi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formAvansRed">
                        <input type="hidden" name="id" id="avans_red_id">
                        <input type="hidden" name="action" value="avans-reddet">
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <strong id="avans_red_personel"></strong> personelinin avans talebini reddetmek istediğinize
                                emin misiniz?
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Red Açıklaması <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="aciklama" rows="3"
                                    placeholder="Red sebebini açıklayınız..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-danger"><i class="bx bx-x me-1"></i>Reddet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalIzinOnay" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bx bx-calendar-check me-2"></i>İzin Onayı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formIzinOnay">
                        <input type="hidden" name="id" id="izin_onay_id">
                        <input type="hidden" name="action" value="izin-onayla">
                        <div class="modal-body">
                            <div class="alert alert-success">
                                <strong id="izin_onay_personel"></strong> personelinin
                                <strong id="izin_onay_gun"></strong> günlük <strong id="izin_onay_tur"></strong> talebini
                                onaylamak istediğinize emin misiniz?
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama (Opsiyonel)</label>
                                <textarea class="form-control" name="aciklama" rows="2"
                                    placeholder="Onay açıklaması..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i>Onayla</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalIzinRed" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bx bx-x-circle me-2"></i>İzin Reddi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formIzinRed">
                        <input type="hidden" name="id" id="izin_red_id">
                        <input type="hidden" name="action" value="izin-reddet">
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <strong id="izin_red_personel"></strong> personelinin izin talebini reddetmek istediğinize emin
                                misiniz?
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Red Açıklaması <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="aciklama" rows="3"
                                    placeholder="Red sebebini açıklayınız..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-danger"><i class="bx bx-x me-1"></i>Reddet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalTalepCozuldu" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bx bx-check-circle me-2"></i>Talep Çözümü</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formTalepCozuldu">
                        <input type="hidden" name="id" id="talep_cozuldu_id">
                        <input type="hidden" name="action" value="talep-cozuldu">
                        <div class="modal-body">
                            <div class="alert alert-success">
                                <strong id="talep_cozuldu_baslik"></strong> talebini çözüldü olarak işaretlemek istediğinize
                                emin misiniz?
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Çözüm Açıklaması</label>
                                <textarea class="form-control" name="aciklama" rows="3"
                                    placeholder="Çözüm hakkında bilgi veriniz..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i>Çözüldü Olarak
                                İşaretle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
            /* Dashboard Slider Minimal Styles */
            #widget-ana-slider .carousel-inner {
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
            }

            .circles {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: 1;
            }

            .circles div {
                position: absolute;
                display: block;
                width: 20px;
                height: 20px;
                background: rgba(255, 255, 255, 0.1);
                animation: animate_circles 25s linear infinite;
                bottom: -150px;
                border-radius: 50%;
            }

            .circles div:nth-child(1) {
                left: 25%;
                width: 80px;
                height: 80px;
                animation-delay: 0s;
            }

            .circles div:nth-child(2) {
                left: 10%;
                width: 20px;
                height: 20px;
                animation-delay: 2s;
                animation-duration: 12s;
            }

            .circles div:nth-child(3) {
                left: 70%;
                width: 20px;
                height: 20px;
                animation-delay: 4s;
            }

            .circles div:nth-child(4) {
                left: 40%;
                width: 60px;
                height: 60px;
                animation-delay: 0s;
                animation-duration: 18s;
            }

            @keyframes animate_circles {
                0% {
                    transform: translateY(0) rotate(0deg);
                    opacity: 1;
                    border-radius: 50%;
                }

                100% {
                    transform: translateY(-1000px) rotate(720deg);
                    opacity: 0;
                    border-radius: 50%;
                }
            }

            .carousel-indicators .active {
                width: 24px !important;
                border-radius: 4px !important;
            }

            /* Premium Dashboard Cards */
            .bordro-summary-card {
                border-radius: 12px !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
                border: 1px solid rgba(0, 0, 0, 0.04) !important;
                background: #fff;
                position: relative;
            }

            .bordro-summary-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06) !important;
                border-color: rgba(0, 0, 0, 0.08) !important;
            }

            .icon-box {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12px;
                transition: all 0.3s ease;
            }

            /* Card Loading State */
            .card.is-loading {
                position: relative;
                pointer-events: none;
                user-select: none;
            }

            .card.is-loading .card-loading-overlay {
                display: flex !important;
            }

            .card-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.7);
                z-index: 10;
                display: none;
                align-items: center;
                justify-content: center;
                border-radius: inherit;
                backdrop-filter: blur(1.5px);
                animation: fadeIn 0.3s ease-in-out;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .animate-card {
                animation: fadeInCard 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
                animation-delay: var(--delay, 0s);
            }

            @keyframes fadeInCard {
                0% {
                    opacity: 0;
                    transform: translateY(10px);
                }

                100% {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .trend-badge {
                font-size: 0.7rem;
                padding: 2px 8px;
                border-radius: 20px;
                font-weight: 600;
            }

            .trend-badge.up {
                background: rgba(16, 185, 129, 0.1);
                color: #10b981;
            }

            .trend-badge.down {
                background: rgba(244, 63, 94, 0.1);
                color: #f43f5e;
            }

            .modal-detay-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 1em;
                text-align: center;
            }

            .modal-detay-header.tip-avans {
                background: linear-gradient(135deg, #34c38f 0%, #1abc9c 100%);
            }

            .modal-detay-header.tip-izin {
                background: linear-gradient(135deg, #556ee6 0%, #3b5998 100%);
            }

            .modal-detay-header.tip-talep {
                background: linear-gradient(135deg, #50a5f1 0%, #3498db 100%);
            }

            .modal-detay-header .icon-wrapper {
                width: 70px;
                height: 70px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                backdrop-filter: blur(10px);
            }

            .modal-detay-header .icon-wrapper i {
                font-size: 32px;
                color: #fff;
            }

            .modal-detay-header h5 {
                color: #fff;
                margin: 0;
                font-weight: 600;
                font-size: 1.25rem;
            }

            .modal-detay-header .badge-tip {
                background: rgba(255, 255, 255, 0.25);
                color: #fff;
                padding: 0.5rem 1.25rem;
                font-size: 0.9rem;
                font-weight: 600;
                border-radius: 50px;
                display: inline-block;
                margin-bottom: 0.5rem;
            }

            .modal-detay-card {
                background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
                border-radius: 12px;
                padding: 1rem 1.25rem;
                margin-bottom: 0.75rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                transition: all 0.2s ease;
            }

            .modal-detay-card:hover {
                transform: translateX(4px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            }

            .modal-detay-card .label {
                font-size: 0.7rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 0.35rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-weight: 600;
                color: #6c757d;
            }

            .modal-detay-card .label i {
                font-size: 14px;
                opacity: 0.7;
            }

            .modal-detay-card .value {
                font-size: 1.1rem;
                font-weight: 600;
                color: #2c3e50;
            }

            .modal-detay-card.tip-avans {
                border-left-color: #34c38f;
            }

            .modal-detay-card.tip-izin {
                border-left-color: #556ee6;
            }

            .modal-detay-card.tip-talep {
                border-left-color: #50a5f1;
            }

            #modalHomeDetay .modal-content {
                border: none;
                border-radius: 1rem;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                overflow: hidden;
            }

            #modalHomeDetay .modal-body {
                padding: 1.5rem;
                background: #fafbfc;
            }

            #modalHomeDetay .modal-footer {
                background: #fff;
                border-top: 1px solid #e9ecef;
                padding: 1rem 1.5rem;
            }

            #modalHomeDetay .btn-close-custom {
                position: absolute;
                top: 1rem;
                right: 1rem;
                width: 32px;
                height: 32px;
                background: rgba(255, 255, 255, 0.2);
                border: none;
                border-radius: 50%;
                color: #fff;
                font-size: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
                z-index: 10;
            }

            #modalHomeDetay .btn-close-custom:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: rotate(90deg);
            }

            .icon-label-container {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0.5rem;
            }



            /* Sortable Styles */
            .widget-item {
                margin-bottom: 24px;
            }

            .card-header,
            .card-header-flex,
            .bordro-summary-card {
                cursor: grab;
            }

            .card-header:active,
            .card-header-flex:active,
            .bordro-summary-card:active {
                cursor: grabbing;
            }

            .drag-handle {
                color: #cbd5e1;
                font-size: 1.2rem;
                margin-right: 8px;
                transition: color 0.2s;
            }

            .card-header:hover .drag-handle,
            .card-header-flex:hover .drag-handle {
                color: #94a3b8;
            }

            .ui-sortable-placeholder {
                border: 2px dashed #cbd5e1 !important;
                visibility: visible !important;
                background: rgba(241, 245, 249, 0.5) !important;
                border-radius: 12px;
                margin-bottom: 24px;
            }

            [data-bs-theme="dark"] .modal-detay-card {
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }

            [data-bs-theme="dark"] .modal-detay-card .label {
                color: #94a3b8;
            }

            [data-bs-theme="dark"] .modal-detay-card .value {
                color: #f8fafc;
            }

            [data-bs-theme="dark"] #modalHomeDetay .modal-body {
                background: #020817;
            }

            [data-bs-theme="dark"] #modalHomeDetay .modal-footer {
                background: #020817;
                border-top-color: #1e293b;
            }

            [data-bs-theme="dark"] .ui-sortable-placeholder {
                background: rgba(30, 41, 59, 0.5) !important;
                border-color: #334155 !important;
            }

            /* Dashboard Controls Theme */
            .btn-soft-primary {
                background-color: rgba(var(--bs-primary-rgb), 0.1);
                color: var(--bs-primary);
                border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
            }

            .btn-soft-primary:hover {
                background-color: rgba(var(--bs-primary-rgb), 0.2);
                border-color: rgba(var(--bs-primary-rgb), 0.3);
            }

            .btn-soft-secondary {
                background-color: rgba(var(--bs-secondary-rgb), 0.1);
                color: var(--bs-secondary);
                border: 1px solid rgba(var(--bs-secondary-rgb), 0.2);
            }

            .btn-soft-secondary:hover {
                background-color: rgba(var(--bs-secondary-rgb), 0.2);
                border-color: rgba(var(--bs-secondary-rgb), 0.3);
            }

            /* Card Actions */
            .card-footer-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 8px;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
            }

            .stats-local-toggle-group .btn {
                padding: 2px 10px;
                font-size: 10px;
                font-weight: 700;
                color: var(--card-color);
                border-color: var(--card-color);
                background-color: transparent;
            }

            .stats-local-toggle-group .btn.active,
            .stats-local-toggle-group .btn:hover {
                background-color: var(--card-color) !important;
                color: #fff !important;
                border-color: var(--card-color) !important;
            }

            .btn-xs {
                padding: 2px 8px;
                font-size: 10px;
            }

            .btn-soft-danger {
                background-color: rgba(var(--bs-danger-rgb), 0.1);
                color: var(--bs-danger);
                border: 1px solid rgba(var(--bs-danger-rgb), 0.2);
            }

            .btn-soft-info {
                background-color: rgba(var(--bs-info-rgb), 0.1);
                color: var(--bs-info);
                border: 1px solid rgba(var(--bs-info-rgb), 0.2);
            }

            .btn-soft-success {
                background-color: rgba(var(--bs-success-rgb), 0.1);
                color: var(--bs-success);
                border: 1px solid rgba(var(--bs-success-rgb), 0.2);
            }

            [data-bs-theme="dark"] .dropdown-menu {
                background-color: #1e293b;
                border-color: #334155;
            }

            [data-bs-theme="dark"] .dropdown-menu .dropdown-header {
                color: #94a3b8;
                border-color: #334155;
            }

            .dropdown-menu .dropdown-header {
                color: #6c757d;
            }

            [data-bs-theme="dark"] .dropdown-menu .dropdown-divider {
                border-color: #334155;
            }

            [data-bs-theme="dark"] .dropdown-item {
                color: #cbd5e1;
            }

            [data-bs-theme="dark"] .dropdown-item:hover,
            [data-bs-theme="dark"] .dropdown-item:focus {
                background-color: #334155;
                color: #f1f5f9;
            }

            [data-bs-theme="dark"] .dropdown-item.active,
            [data-bs-theme="dark"] .dropdown-item:active {
                background-color: #334155;
            }

            /* Light mode form-check-input */
            .widget-toggle {
                background-color: #f1f5f9 !important;
                border-color: #cbd5e1 !important;
            }

            .widget-toggle:checked {
                background-color: var(--dashboard-theme-color, #5156be) !important;
                border-color: var(--dashboard-theme-color, #5156be) !important;
            }

            .dashboard-chart-skeleton,
            .dashboard-skeleton-table {
                animation: skeleton-pulse 1.4s ease-in-out infinite;
            }

            #dashboard-widgets .dashboard-phase2-hidden {
                display: none !important;
            }

            .dashboard-page-skeleton {
                padding: 0.5rem 0 1rem;
            }

            .dashboard-page-skeleton .skeleton-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                margin-bottom: 1rem;
            }

            .dashboard-page-skeleton .skeleton-toolbar-right {
                display: flex;
                gap: 8px;
            }

            .dashboard-page-skeleton .skeleton-grid {
                display: grid;
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 16px;
            }

            .dashboard-page-skeleton .skeleton-card {
                min-height: 140px;
                border-radius: 12px;
                padding: 14px;
                border: 1px solid rgba(203, 213, 225, 0.45);
                background: rgba(248, 250, 252, 0.8);
            }

            .dashboard-page-skeleton .skeleton-card-lg {
                min-height: 260px;
                grid-column: span 6;
            }

            .dashboard-page-skeleton .skeleton-card-sm {
                grid-column: span 3;
            }

            .dashboard-page-skeleton .skeleton-card-full {
                min-height: 260px;
                grid-column: span 12;
            }

            .dashboard-page-skeleton .skeleton-card .skeleton-line {
                margin-bottom: 10px;
            }

            @media (max-width: 991.98px) {

                .dashboard-page-skeleton .skeleton-card-lg,
                .dashboard-page-skeleton .skeleton-card-sm,
                .dashboard-page-skeleton .skeleton-card-full {
                    grid-column: span 12;
                }
            }

            .skeleton-line {
                display: block;
                height: 12px;
                border-radius: 8px;
                background: linear-gradient(90deg, rgba(203, 213, 225, 0.35) 25%, rgba(203, 213, 225, 0.6) 50%, rgba(203, 213, 225, 0.35) 75%);
                background-size: 200% 100%;
                animation: skeleton-shimmer 1.4s linear infinite;
            }

            .skeleton-chart-bars {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 10px;
                height: 280px;
                padding-top: 8px;
            }

            .skeleton-chart-bars span {
                display: block;
                flex: 1;
                border-radius: 6px 6px 0 0;
                background: rgba(148, 163, 184, 0.35);
            }

            .skeleton-chart-lines {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding-top: 8px;
            }

            .skeleton-chart-lines span {
                display: block;
                height: 18px;
                border-radius: 8px;
                background: rgba(148, 163, 184, 0.35);
            }

            .w-100 {
                width: 100%;
            }

            .w-95 {
                width: 95%;
            }

            .w-92 {
                width: 92%;
            }

            .w-90 {
                width: 90%;
            }

            .w-85 {
                width: 85%;
            }

            .w-70 {
                width: 70%;
            }

            .w-60 {
                width: 60%;
            }

            .w-50 {
                width: 50%;
            }

            .w-40 {
                width: 40%;
            }

            .w-35 {
                width: 35%;
            }

            @keyframes skeleton-shimmer {
                0% {
                    background-position: 200% 0;
                }

                100% {
                    background-position: -200% 0;
                }
            }

            @keyframes skeleton-pulse {

                0%,
                100% {
                    opacity: 1;
                }

                50% {
                    opacity: 0.75;
                }
            }

            [data-bs-theme="dark"] .skeleton-line {
                background: linear-gradient(90deg, rgba(51, 65, 85, 0.45) 25%, rgba(71, 85, 105, 0.65) 50%, rgba(51, 65, 85, 0.45) 75%);
                background-size: 200% 100%;
            }

            [data-bs-theme="dark"] .skeleton-chart-bars span,
            [data-bs-theme="dark"] .skeleton-chart-lines span {
                background: rgba(71, 85, 105, 0.55);
            }

            [data-bs-theme="dark"] .dashboard-page-skeleton .skeleton-card {
                background: rgba(15, 23, 42, 0.8);
                border-color: rgba(71, 85, 105, 0.45);
            }

            .widget-toggle:focus {
                box-shadow: 0 0 0 0.15rem rgba(var(--dashboard-theme-color-rgb, 81, 86, 190), 0.25) !important;
            }

            [data-bs-theme="dark"] .widget-toggle {
                background-color: #334155 !important;
                border-color: #475569 !important;
            }

            [data-bs-theme="dark"] .widget-toggle:checked {
                background-color: var(--dashboard-theme-color, #5156be) !important;
                border-color: var(--dashboard-theme-color, #5156be) !important;
            }

            [data-bs-theme="dark"] .btn-soft-primary {
                background-color: rgba(var(--bs-primary-rgb), 0.15);
                color: #60a5fa;
                border-color: rgba(var(--bs-primary-rgb), 0.3);
            }

            [data-bs-theme="dark"] .btn-soft-primary:hover {
                background-color: rgba(var(--bs-primary-rgb), 0.25);
                color: #93c5fd;
                border-color: rgba(var(--bs-primary-rgb), 0.5);
            }

            [data-bs-theme="dark"] .btn-soft-secondary {
                background-color: rgba(108, 117, 125, 0.15);
                color: #9ca3af;
                border-color: rgba(108, 117, 125, 0.3);
            }

            [data-bs-theme="dark"] .btn-soft-secondary:hover {
                background-color: rgba(108, 117, 125, 0.25);
                color: #d1d5db;
                border-color: rgba(108, 117, 125, 0.5);
            }

            @media (max-width: 767.98px) {
                .widget-item {
                    margin-bottom: 12px;
                }

                .stat-card {
                    min-height: 75px !important;
                }

                .stat-card .card-body {
                    display: flex !important;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 10px 4px !important;
                    text-align: center;
                    gap: 2px;
                }

                .stat-card .icon-label-container {
                    display: flex !important;
                    justify-content: center;
                    width: 100%;
                    margin-bottom: 2px !important;
                }
                
                .stat-card .icon-label-container > span,
                .stat-card .icon-label-container > div:not(.icon-box) {
                    display: none !important;
                }

                .stat-card .icon-box {
                    width: 26px !important;
                    height: 26px !important;
                    margin: 0 auto !important;
                }

                .stat-card .icon-box i {
                    font-size: 1.1rem !important;
                }

                .stat-card .card-body>p.stat-label,
                .stat-card .card-body>p.text-muted {
                    margin: 0 !important;
                    font-size: 8px !important;
                    line-height: 1.2;
                    opacity: 0.8;
                }

                .stat-card .card-body>h4 {
                    margin: 0 !important;
                    font-size: 1.05rem !important;
                    line-height: 1;
                    margin-top: 2px !important;
                }

                .stat-card .trend-badge,
                .stat-card .sub-text,
                .stat-card .card-footer-actions,
                .stat-card .card-body > .mt-2.text-center {
                    display: none !important;
                }

                .trend-badge {
                    font-size: 8px !important;
                    padding: 1px 4px !important;
                    vertical-align: middle;
                }
            }
        </style>

        <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
        <script>            // Number Cou            nt                                 er F           unction
            function animateValue(obj, start, end, duration) {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    obj.innerHTML = Math.floor(progress * (end - start) + start);
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }

            var months = <?php echo json_encode($months); ?>;
            var totals = <?php echo json_encode($totals); ?>;

            var options = {
                chart: { type: 'line', height: 350 },
                series: [{ name: 'Üye Sayısı', data: totals }],
                xaxis: { categories: months },
                colors: ['#556ee6']
            }
            // new ApexCharts(document.querySelector("#chart"), options).render();

            var options2 = {
                series: [{ name: 'Gelir', data: [44, 55, 57, 56, 61, 58, 63, 60, 66, 85, 96, 85] },
                { name: 'Gider', data: [76, 85, 101, 98, 87, 105, 91, 114, 94, 78, 77, 25] }],
                chart: { type: 'bar', height: 350 },
                plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
                xaxis: { categories: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'] },
                colors: ['#34c38f', '#f46a6a']
            };
            // new ApexCharts(document.querySelector("#chart2"), options2).render();

            // var options3 = {
            //     series: [<?php echo $toplam_gelir; ?>, <?php echo $toplam_gider; ?>, <?php echo $toplam_bakiye; ?>],
            //     chart: { type: 'polarArea', height: 350 },
            //     labels: ['Gelir', 'Gider', 'Kasa'],
            //     colors: ['#34c38f', '#f46a6a', '#556ee6']
            // };
            // new ApexCharts(document.querySelector("#chart3"), options3).render();

            let workTypeChart;
            function showChartSkeleton(chartElement, mode = 'bar') {
                if (!chartElement) return;

                const skeletonHtml = mode === 'line'
                    ? `<div class="dashboard-chart-skeleton"><div class="skeleton-line w-50 mb-3"></div><div class="skeleton-chart-lines"><span class="w-100"></span><span class="w-85"></span><span class="w-70"></span><span class="w-92"></span><span class="w-60"></span></div></div>`
                    : `<div class="dashboard-chart-skeleton"><div class="skeleton-line w-40 mb-3"></div><div class="skeleton-chart-bars"><span style="height: 32%;"></span><span style="height: 46%;"></span><span style="height: 64%;"></span><span style="height: 52%;"></span><span style="height: 75%;"></span><span style="height: 58%;"></span><span style="height: 80%;"></span><span style="height: 43%;"></span></div></div>`;

                chartElement.innerHTML = skeletonHtml;
            }

            function loadWorkTypeStats(year) {
                if (typeof ApexCharts === 'undefined') {
                    console.log('ApexCharts henüz yüklenmedi, 500ms sonra tekrar denenecek...');
                    setTimeout(() => loadWorkTypeStats(year), 500);
                    return;
                }

                const chartElement = document.querySelector("#work-type-stats-chart");
                if (!chartElement) return;
                showChartSkeleton(chartElement, 'bar');

                const formData = new FormData();
                formData.append('action', 'get-work-type-stats');
                formData.append('year', year);
                // İş türü her zaman tüm yılı gösterecek

                fetch('views/home/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (!data.data.series || data.data.series.length === 0) {
                                chartElement.innerHTML = '<div class="alert alert-info text-center mt-5">Seçilen yıla ait istatistik verisi bulunamadı.</div>';
                                workTypeChart = null;
                                return;
                            }

                            const options = {
                                series: data.data.series,
                                chart: {
                                    type: 'bar',
                                    height: '100%',
                                    stacked: false,
                                    toolbar: { show: true },
                                    animations: { enabled: true }
                                },
                                plotOptions: {
                                    bar: {
                                        horizontal: false,
                                        columnWidth: '55%',
                                        borderRadius: 5
                                    },
                                },
                                dataLabels: { enabled: false },
                                stroke: {
                                    show: true,
                                    width: 2,
                                    colors: ['transparent']
                                },
                                xaxis: {
                                    categories: data.data.categories,
                                },
                                yaxis: {
                                    title: { text: 'İş Adeti' }
                                },
                                fill: { opacity: 1 },
                                colors: ['#556ee6', '#34c38f', '#f46a6a', '#f1b44c', '#50a5f1'],
                                tooltip: {
                                    y: {
                                        formatter: function (val) {
                                            return val + " adet"
                                        }
                                    }
                                }
                            };

                            chartElement.innerHTML = '';
                            if (workTypeChart) {
                                workTypeChart.destroy();
                            }

                            workTypeChart = new ApexCharts(chartElement, options);
                            workTypeChart.render();
                        }
                    })
                    .catch(err => {
                        console.error('İstatistik yükleme hatası:', err);
                        chartElement.innerHTML = '<div class="alert alert-danger text-center mt-5">Veriler yüklenirken bir hata oluştu.</div>';
                    });
            }

            let workResultChart;
            function loadWorkResultStats(year, month = "") {
                if (typeof ApexCharts === 'undefined') {
                    setTimeout(() => loadWorkResultStats(year, month), 500);
                    return;
                }

                const chartElement = document.querySelector("#work-result-stats-chart");
                if (!chartElement) return;
                showChartSkeleton(chartElement, 'line');

                const formData = new FormData();
                formData.append('action', 'get-work-result-stats');
                formData.append('year', year);
                formData.append('month', month);

                fetch('views/home/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (!data.data.series || data.data.series.length === 0) {
                                chartElement.innerHTML = '<div class="alert alert-info text-center mt-5">Seçilen yıla ait sonuç verisi bulunamadı.</div>';
                                return;
                            }

                            const options = {
                                series: data.data.series,
                                chart: {
                                    type: 'bar',
                                    height: '100%',
                                    stacked: false,
                                    toolbar: { show: true }
                                },
                                plotOptions: {
                                    bar: {
                                        horizontal: true,
                                        columnWidth: '55%',
                                        borderRadius: 5,
                                        dataLabels: { position: 'top' }
                                    },
                                },
                                dataLabels: {
                                    enabled: true,
                                    offsetX: -6,
                                    style: { fontSize: '12px', colors: ['#fff'] }
                                },
                                xaxis: {
                                    categories: data.data.categories,
                                },
                                title: {
                                    text: data.data.selected_month + ' Ayı Sonuç Dağılımı',
                                    align: 'center'
                                },
                                yaxis: {
                                    labels: {
                                        maxWidth: 300,
                                        style: { fontSize: '11px' }
                                    }
                                },
                                fill: { opacity: 1 },
                                tooltip: {
                                    y: {
                                        formatter: function (val) {
                                            return val + " adet"
                                        }
                                    }
                                }
                            };

                            chartElement.innerHTML = '';
                            if (workResultChart) {
                                workResultChart.destroy();
                            }

                            workResultChart = new ApexCharts(chartElement, options);
                            workResultChart.render();
                        }
                    });
            }


            document.addEventListener('DOMContentLoaded', function () {
                const pageSkeleton = document.getElementById('dashboard-page-skeleton');
                const pageContent = document.getElementById('dashboard-page-content');
                const criticalSkeletonStyle = document.getElementById('dashboard-skeleton-critical');
                const criticalWidgetIds = new Set([
                    'widget-ana-slider',
                    'widget-personel-ozet',
                    'widget-arac-ozet',
                    'widget-bekleyen-talepler',
                    'widget-gec-kalanlar',
                    'widget-nobetciler'
                ]);
                let dashboardRevealed = false;

                const prepareStagedWidgets = () => {
                    document.querySelectorAll('#dashboard-widgets .widget-item').forEach((widget) => {
                        if (!criticalWidgetIds.has(widget.id)) {
                            widget.classList.add('dashboard-phase2-hidden');
                        }
                    });
                };

                const revealPhase2Widgets = () => {
                    document.querySelectorAll('#dashboard-widgets .dashboard-phase2-hidden').forEach((widget) => {
                        widget.classList.remove('dashboard-phase2-hidden');
                    });
                };

                const revealDashboardPage = () => {
                    if (dashboardRevealed) return;
                    dashboardRevealed = true;
                    if (pageSkeleton) pageSkeleton.style.display = 'none';
                    if (pageContent) pageContent.style.display = '';
                    if (criticalSkeletonStyle) criticalSkeletonStyle.remove();
                    if (typeof initResizableWidgets === 'function') {
                        setTimeout(initResizableWidgets, 80);
                    }

                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(() => revealPhase2Widgets(), { timeout: 500 });
                    } else {
                        setTimeout(revealPhase2Widgets, 350);
                    }
                };

                prepareStagedWidgets();

                const scheduleDashboardReveal = () => {
                    window.requestAnimationFrame(() => {
                        setTimeout(revealDashboardPage, 60);
                    });
                };

                scheduleDashboardReveal();

                setTimeout(() => {
                    if (!dashboardRevealed) {
                        revealDashboardPage();
                    }
                }, 1200);

                const API_URL = 'views/talepler/api.php';

                // Load widget visibility from localStorage
                function setWidgetVisibility(widgetId, isVisible, options = {}) {
                    const widget = $(`#${widgetId}`);
                    if (!widget.length) return;

                    const animate = options.animate === true;
                    const syncCheckbox = options.syncCheckbox !== false;

                    widget.toggleClass('widget-hidden', !isVisible);

                    if (animate) {
                        if (isVisible) {
                            widget.stop(true, true).fadeIn(200);
                        } else {
                            widget.stop(true, true).fadeOut(200);
                        }
                    } else if (isVisible) {
                        widget.show();
                    } else {
                        widget.hide();
                    }

                    if (syncCheckbox) {
                        $(`input[data-widget="${widgetId}"]`).prop('checked', isVisible);
                    }
                }

                function loadWidgetVisibility() {
                    const visibility = localStorage.getItem('dashboard_widget_visibility');
                    if (visibility) {
                        const visibleWidgets = JSON.parse(visibility);
                        $('#dashboard-widgets .widget-item').each(function () {
                            const id = $(this).attr('id');
                            // Eğer localStorage'da yoksa varsayılan olarak göster (true)
                            const isVisible = visibleWidgets[id] !== false;
                            setWidgetVisibility(id, isVisible, { syncCheckbox: true });
                        });
                    }
                }

                // Save widget visibility to localStorage
                function saveWidgetVisibility() {
                    const visibility = {};
                    $('input.widget-toggle').each(function () {
                        const widgetId = $(this).data('widget');
                        visibility[widgetId] = $(this).is(':checked');
                    });
                    localStorage.setItem('dashboard_widget_visibility', JSON.stringify(visibility));
                }

                // Toggle widget visibility
                $(document).on('change', '.widget-toggle', function () {
                    const widgetId = $(this).data('widget');
                    const isChecked = $(this).is(':checked');
                    setWidgetVisibility(widgetId, isChecked, { animate: true, syncCheckbox: false });
                    saveWidgetVisibility();
                    saveDashboardConfig();
                });

                // Load visibility on page load
                loadWidgetVisibility();

                // Theme change listener for checkbox colors and button colors
                function updateThemeColors() {
                    const html = document.documentElement;
                    const isDarkMode = html.getAttribute('data-bs-theme') === 'dark';
                    const themeMode = html.getAttribute('data-theme-mode') || 'default';

                    // Color Palette Map
                    const colors = {
                        'red': '#f46a6a',
                        'orange': '#f1b44c',
                        'emerald': '#34c38f',
                        'purple': '#6f42c1',
                        'slate': '#475569',
                        'default': '#5156be'
                    };

                    // Get color based on theme
                    const color = colors[themeMode] || colors['default'];

                    // Set CSS custom property for checkboxes
                    document.documentElement.style.setProperty('--dashboard-theme-color', color);

                    // Update checkboxes
                    const checkboxes = document.querySelectorAll('.widget-toggle');
                    checkboxes.forEach(checkbox => {
                        checkbox.style.accentColor = color;
                    });

                    // Update dashboard control buttons
                    const dashboardBtns = document.querySelectorAll('#btn-reset-dashboard, .d-flex.gap-2 .dropdown > .btn');
                    dashboardBtns.forEach(btn => {
                        if (isDarkMode) {
                            btn.style.borderColor = '#334155';
                            btn.style.backgroundColor = '#1e293b';
                        } else {
                            btn.style.borderColor = '#e5e7eb';
                            btn.style.backgroundColor = '#fff';
                        }
                        btn.style.color = color;
                    });

                    // Update soft-primary buttons (like Detay)
                    const softPrimaryBtns = document.querySelectorAll('.btn-soft-primary');
                    softPrimaryBtns.forEach(btn => {
                        btn.style.backgroundColor = hexToRgba(color, 0.1);
                        btn.style.borderColor = hexToRgba(color, 0.2);
                        btn.style.color = color;
                    });
                }

                // Helper: Hex to RGBA
                function hexToRgba(hex, alpha) {
                    const r = parseInt(hex.slice(1, 3), 16);
                    const g = parseInt(hex.slice(3, 5), 16);
                    const b = parseInt(hex.slice(5, 7), 16);
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                }

                // Initial call
                updateThemeColors();

                // Watch for theme changes
                const observer = new MutationObserver(() => {
                    updateThemeColors();
                });

                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-bs-theme', 'data-theme-mode']
                });

                // Start counters
                document.querySelectorAll('.main-value').forEach(el => {
                    const finalValue = parseInt(el.innerText);
                    el.innerText = '0';
                    setTimeout(() => {
                        animateValue(el, 0, finalValue, 1500);
                    }, 300);
                });

                // Log Detay Modal
                document.querySelectorAll('.btn-log-detay').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var title = this.dataset.title;
                        var user = this.dataset.user;
                        var date = this.dataset.date;
                        var content = this.dataset.content;
                        document.getElementById('logDetayTitle').textContent = title;
                        document.getElementById('logDetayUser').textContent = user;
                        document.getElementById('logDetayDate').textContent = date;

                        if (content.includes('(Güncellenen veriler: {')) {
                            try {
                                let parts = content.split(' (Güncellenen veriler: { ');
                                let mainText = parts[0];
                                let changesPart = parts[1].replace(/ ?\}\)?$/, '');
                                let changes = changesPart.split(', ');

                                let formattedContent = `<div class="d-flex align-items-start gap-2 mb-3">
                                <i class='bx bx-edit-alt text-primary mt-1' style='font-size:1.1rem;flex-shrink:0;'></i>
                                <span style='font-size:0.875rem;color:#374151;line-height:1.55;'>${mainText}</span>
                            </div>`;

                                if (changes.some(c => c.includes(': '))) {
                                    formattedContent += `<div class="change-table">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Alan</th><th>Değişim</th></tr></thead>
                                        <tbody>`;
                                    changes.forEach(change => {
                                        if (change.includes(': ')) {
                                            let sepIdx = change.indexOf(': ');
                                            let key = change.substring(0, sepIdx).trim();
                                            let val = change.substring(sepIdx + 2).trim();
                                            let displayVal = val;
                                            if (val.includes(' -> ')) {
                                                let [from, to] = val.split(' -> ');
                                                displayVal = `<span class="change-arrow">
                                                <span class="from-val">${from || 'Boş'}</span>
                                                <i class='bx bx-right-arrow-alt arrow-icon'></i>
                                                <span class="to-val">${to || 'Boş'}</span>
                                            </span>`;
                                            } else if (val.includes(' → ')) {
                                                let [from, to] = val.split(' → ');
                                                displayVal = `<span class="change-arrow">
                                                <span class="from-val">${from || 'Boş'}</span>
                                                <i class='bx bx-right-arrow-alt arrow-icon'></i>
                                                <span class="to-val">${to || 'Boş'}</span>
                                            </span>`;
                                            }
                                            formattedContent += `<tr>
                                            <td class="field-cell">${key}</td>
                                            <td>${displayVal}</td>
                                        </tr>`;
                                        }
                                    });
                                    formattedContent += `</tbody></table></div>`;
                                }
                                document.getElementById('logDetayContent').innerHTML = formattedContent;
                                document.getElementById('logDetayContent').style.whiteSpace = 'normal';
                            } catch (e) {
                                document.getElementById('logDetayContent').textContent = content;
                            }
                        } else {
                            // Düz metin — satır sonlarını <br> ile göster
                            document.getElementById('logDetayContent').innerHTML =
                                '<i class="bx bx-info-circle text-primary me-2" style="font-size:1rem;vertical-align:middle;"></i>' +
                                content.replace(/\n/g, '<br>');
                            document.getElementById('logDetayContent').style.whiteSpace = 'normal';
                        }
                        new bootstrap.Modal(document.getElementById('modalLogDetay')).show();
                    });
                });

                // Detay Modal - API'den detay çekiyor
                document.querySelectorAll('.btn-home-detay').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = this.dataset.id;
                        var tip = this.dataset.tip;
                        var headerClass = tip === 'Avans' ? 'tip-avans' : (tip === 'İzin' ? 'tip-izin' : 'tip-talep');
                        var headerIcon = tip === 'Avans' ? 'bx-money' : (tip === 'İzin' ? 'bx-calendar-check' : 'bx-message-square-detail');

                        // Header'ı ayarla
                        document.getElementById('modalHeader').className = 'modal-detay-header ' + headerClass;
                        document.getElementById('modalTalepTipi').textContent = tip;
                        document.getElementById('modalHeaderIcon').className = 'bx ' + headerIcon;

                        // Tab parametresini ayarla
                        var tabParam = tip === 'Avans' ? 'avans' : (tip === 'İzin' ? 'izin' : 'talep');
                        document.getElementById('modalGitBtn').href = 'index.php?p=talepler/list&tab=' + tabParam;

                        // Loading göster, content gizle
                        document.getElementById('modalLoading').style.display = 'block';
                        document.getElementById('modalContent').style.display = 'none';

                        // Modalı aç
                        new bootstrap.Modal(document.getElementById('modalHomeDetay')).show();

                        // API'den detay çek
                        var actionName = tip === 'Avans' ? 'get-avans-detay' : (tip === 'İzin' ? 'get-izin-detay' : 'get-talep-detay');
                        var formData = new FormData();
                        formData.append('action', actionName);
                        formData.append('id', id);

                        fetch(API_URL, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                document.getElementById('modalLoading').style.display = 'none';
                                document.getElementById('modalContent').style.display = 'flex';

                                if (data.status === 'success') {
                                    var d = data.data;

                                    // Resim
                                    var resimEl = document.getElementById('modalResim');
                                    resimEl.src = d.resim_yolu || 'assets/images/users/user-dummy-img.jpg';
                                    resimEl.onerror = function () { this.src = 'assets/images/users/user-dummy-img.jpg'; };

                                    // Personel bilgileri
                                    document.getElementById('modalPersonelAdi').textContent = d.adi_soyadi || '-';
                                    document.getElementById('modalDepartman').textContent = d.departman || '';
                                    document.getElementById('modalGorev').textContent = d.gorev || '';

                                    // Başlık satırını kontrol et (Sadece Talep tipinde gösterilir)
                                    var rowBaslik = document.getElementById('rowBaslik');
                                    if (tip === 'Talep') {
                                        rowBaslik.style.display = 'table-row';
                                        document.getElementById('modalBaslik').textContent = d.baslik || '-';
                                    } else {
                                        rowBaslik.style.display = 'none';
                                    }

                                    // Fotoğraf satırını kontrol et
                                    var rowFotograf = document.getElementById('rowFotograf');
                                    if (d.foto || d.dosya_yolu || d.fotograf_yolu) {
                                        var fotoPath = d.foto || d.dosya_yolu || d.fotograf_yolu;
                                        rowFotograf.style.display = 'table-row';
                                        document.getElementById('modalFoto').src = fotoPath;
                                        document.getElementById('modalFotoLink').href = fotoPath;
                                    } else {
                                        rowFotograf.style.display = 'none';
                                    }

                                    // Tip'e göre detay ve tarih bilgisi
                                    if (tip === 'Avans') {
                                        var tutar = parseFloat(d.tutar || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
                                        document.getElementById('modalDetay').textContent = tutar;
                                        document.getElementById('modalTarih').textContent = formatTarih(d.talep_tarihi);
                                        document.getElementById('modalDurum').innerHTML = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bx bx-time me-1"></i>' + ucFirst(d.durum) + '</span>';
                                    } else if (tip === 'İzin') {
                                        var izinDetay = (d.izin_tipi_adi || d.izin_tipi || 'İzin');
                                        if (d.gun_sayisi) izinDetay += ' (' + d.gun_sayisi + ' gün)';
                                        document.getElementById('modalDetay').textContent = izinDetay;
                                        document.getElementById('modalTarih').textContent = formatTarih(d.baslangic_tarihi) + ' - ' + formatTarih(d.bitis_tarihi);
                                        document.getElementById('modalDurum').innerHTML = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bx bx-time me-1"></i>' + ucFirst(d.onay_durumu) + '</span>';
                                    } else {
                                        document.getElementById('modalDetay').textContent = d.aciklama || '-';
                                        document.getElementById('modalTarih').textContent = formatTarih(d.olusturma_tarihi);
                                        document.getElementById('modalDurum').innerHTML = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bx bx-time me-1"></i>' + ucFirst(d.durum) + '</span>';
                                    }
                                } else {
                                    document.getElementById('modalContent').innerHTML = '<div class="col-12 text-center py-4"><div class="alert alert-danger">' + (data.message || 'Bir hata oluştu') + '</div></div>';
                                }
                            })
                            .catch(error => {
                                document.getElementById('modalLoading').style.display = 'none';
                                document.getElementById('modalContent').style.display = 'flex';
                                document.getElementById('modalContent').innerHTML = '<div class="col-12 text-center"><div class="alert alert-danger">Detaylar yüklenirken hata oluştu.</div></div>';
                            });
                    });
                });

                // Yardımcı fonksiyonlar
                function formatTarih(dateStr) {
                    if (!dateStr) return '-';
                    var date = new Date(dateStr);
                    return date.toLocaleDateString('tr-TR');
                }

                function ucFirst(str) {
                    if (!str) return '';
                    return str.charAt(0).toUpperCase() + str.slice(1);
                }

                // Avans Onayla/Reddet, İzin Onayla/Reddet, Talep Çözüldü
                document.querySelectorAll('.btn-avans-onayla').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('avans_onay_id').value = this.dataset.id;
                        document.getElementById('avans_onay_personel').textContent = this.dataset.personel;
                        document.getElementById('avans_onay_tutar').textContent = parseFloat(this.dataset.tutar).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
                        new bootstrap.Modal(document.getElementById('modalAvansOnay')).show();
                    });
                });
                document.querySelectorAll('.btn-avans-reddet').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('avans_red_id').value = this.dataset.id;
                        document.getElementById('avans_red_personel').textContent = this.dataset.personel;
                        new bootstrap.Modal(document.getElementById('modalAvansRed')).show();
                    });
                });
                document.querySelectorAll('.btn-izin-onayla').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('izin_onay_id').value = this.dataset.id;
                        document.getElementById('izin_onay_personel').textContent = this.dataset.personel;
                        document.getElementById('izin_onay_tur').textContent = this.dataset.tur;
                        document.getElementById('izin_onay_gun').textContent = this.dataset.gun;
                        new bootstrap.Modal(document.getElementById('modalIzinOnay')).show();
                    });
                });
                document.querySelectorAll('.btn-izin-reddet').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('izin_red_id').value = this.dataset.id;
                        document.getElementById('izin_red_personel').textContent = this.dataset.personel;
                        new bootstrap.Modal(document.getElementById('modalIzinRed')).show();
                    });
                });
                document.querySelectorAll('.btn-talep-cozuldu').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('talep_cozuldu_id').value = this.dataset.id;
                        document.getElementById('talep_cozuldu_baslik').textContent = this.dataset.baslik;
                        new bootstrap.Modal(document.getElementById('modalTalepCozuldu')).show();
                    });
                });

                const handleFormSubmit = (formId) => {
                    const form = document.getElementById(formId);
                    if (!form) return;
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        const submitBtn = this.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> İşleniyor...';
                        fetch(API_URL, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire({ icon: 'success', title: 'Başarılı', text: data.message, timer: 1500, showConfirmButton: false })
                                        .then(() => location.reload());
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Hata', text: data.message });
                                    submitBtn.disabled = false;
                }

                                    submitBtn.innerHTML = originalText;
                            })
                            .catch(error => {
                                Swal.fire({ icon: 'error', title: 'Hata', text: 'Bir sorun oluştu.' });
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            });
                    });
                };

                handleFormSubmit('formAvansOnay');
                handleFormSubmit('formAvansRed');
                handleFormSubmit('formIzinOnay');
                handleFormSubmit('formIzinRed');
                handleFormSubmit('formTalepCozuldu');

                // İş Türü İstatistikleri (Yıllık)
                const yearFilter = document.getElementById('stats-year-filter');
                if (yearFilter) {
                    yearFilter.addEventListener('change', function () {
                        loadWorkTypeStats(this.value);
                    });
                    loadWorkTypeStats(yearFilter.value);
                }

                // İş Emri Sonuçları (Aylık)
                const resultMonthFilter = document.getElementById('stats-result-month-filter');
                const resultYearFilter = document.getElementById('stats-result-year-filter');

                if (resultMonthFilter && resultYearFilter) {
                    const refreshResultStats = () => {
                        loadWorkResultStats(resultYearFilter.value, resultMonthFilter.value);
                    };
                    resultMonthFilter.addEventListener('change', refreshResultStats);
                    resultYearFilter.addEventListener('change', refreshResultStats);
                    refreshResultStats();
                }

                let saveConfigTimeout = null;
                const defaultWidgetWidthClasses = {
                    'widget-ana-slider': 'col-md-4 col-xl-4',
                    'widget-personel-ozet': 'col-md-4 col-xl-4',
                    'widget-personel-ozeti': 'col-md-4 col-xl-4',
                    'widget-arac-ozet': 'col-md-4 col-xl-4',
                    'widget-arac-ozeti': 'col-md-4 col-xl-4',
                    'widget-bekleyen-talepler': 'col-6 col-md-2',
                    'widget-gec-kalanlar': 'col-6 col-md-2',
                    'widget-nobetciler': 'col-6 col-md-2',
                    'widget-gunluk-muhurleme': 'col-6 col-md-2',
                    'widget-gunluk-kesme-acma': 'col-6 col-md-2',
                    'widget-gunluk-endeks-okuma': 'col-6 col-md-2',
                    'widget-gunluk-sayac-degisimi': 'col-6 col-md-2',
                    'widget-kacak-sayisi': 'col-6 col-md-2'
                };

                function saveDashboardConfig(immediate = true) {
                    const order = [];
                    const settings = {};
                    let visibility = {};
                    try {
                        visibility = JSON.parse(localStorage.getItem('dashboard_widget_visibility') || '{}') || {};
                    } catch (e) {
                        visibility = {};
                    }
                    $('#dashboard-widgets .widget-item').each(function () {
                        const id = $(this).attr('id');
                        if (!id || id === 'widget-row-break') return;
                        order.push(id);

                        const s = {};
                        const isHidden = $(this).hasClass('widget-hidden');
                        visibility[id] = !isHidden;
                        if (isHidden) {
                            s.hidden = 'true';
                        }

                        const isFree = $('#switch-free-layout').is(':checked');

                        if (isFree) {
                            s.width = $(this).outerWidth() + 'px';
                            s.height = $(this).outerHeight() + 'px';
                        } else {
                            const widthClasses = ($(this).attr('class') || '')
                                .split(' ')
                                .filter(cls => /^col(-[a-z]+)?-\d+$/.test(cls));
                            if (widthClasses.length) {
                                s.width = widthClasses.join(' ');
                            }

                            const cardBody = $(this).find('.card-body').first();
                            const bodyHeight = cardBody.length ? cardBody[0].style.height : '';
                            if (bodyHeight && bodyHeight !== '0px') {
                                s.height = bodyHeight;
                            }
                        }

                        // Only save absolute position data when truly in free-layout mode
                        const positionVal = $(this).css('position');
                        if (isFree && positionVal === 'absolute') {
                            const offset = $(this).position();
                            s.left = $(this).css('left') !== 'auto' ? $(this).css('left') : offset.left + 'px';
                            s.top = $(this).css('top') !== 'auto' ? $(this).css('top') : offset.top + 'px';
                            let zVal = parseInt($(this).css('z-index'), 10);
                            if (Number.isFinite(zVal)) {
                                s.zIndex = zVal;
                            }
                        }
                        
                        if (isFree || $(this).attr('data-resized') === 'true') {
                            s.width = $(this).css('width');
                            s.height = $(this).css('height');
                        }

                        if (id && Object.keys(s).length > 0) {
                            settings[id] = s;
                        }
                    });

                    const settingsStr = JSON.stringify(settings);
                    const orderStr = JSON.stringify(order);
                    const visibilityStr = JSON.stringify(visibility);
                    localStorage.setItem('dashboard_widget_settings', settingsStr);
                    localStorage.setItem('dashboard_order', orderStr);
                    localStorage.setItem('dashboard_widget_visibility', visibilityStr);

                    const cookieOptions = "; path=/; max-age=" + (60 * 60 * 24 * 30);
                    document.cookie = "dashboard_settings=" + encodeURIComponent(settingsStr) + cookieOptions;
                    document.cookie = "dashboard_order=" + encodeURIComponent(orderStr) + cookieOptions;

                    // Background sync to server if it's a real change (immediate=true)
                    if (immediate) {
                        $.ajax({
                            url: 'views/home/api.php',
                            type: 'POST',
                            data: {
                                action: 'save-dashboard-settings',
                                settings: settingsStr,
                                order: orderStr,
                                is_free: $('#switch-free-layout').is(':checked') ? 'true' : 'false'
                            }
                        });
                    }
                }

                let dashboardMutationGuard = false;

                function applySavedWidgetOrder() {
                    if (dashboardMutationGuard) return;
                    let savedOrder = [];
                    try {
                        const rawOrder = localStorage.getItem('dashboard_order') || '[]';
                        savedOrder = JSON.parse(rawOrder);
                        if (!Array.isArray(savedOrder)) {
                            savedOrder = [];
                        }
                    } catch (e) {
                        savedOrder = [];
                    }

                    if (!savedOrder.length) return;

                    const container = document.getElementById('dashboard-widgets');
                    if (!container) return;

                    dashboardMutationGuard = true;
                    savedOrder.forEach(function(widgetId) {
                        const widget = document.getElementById(widgetId);
                        if (widget && widget.parentNode === container) {
                            container.appendChild(widget);
                        }
                    });
                    dashboardMutationGuard = false;
                }

                function getSavedDashboardSettings() {
                    try {
                        let settings = JSON.parse(localStorage.getItem('dashboard_widget_settings') || '{}');
                        if (typeof settings === 'string') {
                            settings = JSON.parse(settings);
                        }
                        return settings && typeof settings === 'object' ? settings : {};
                    } catch (e) {
                        return {};
                    }
                }

                function cleanDashboardCssValue(value, allowZero = false) {
                    if (typeof value !== 'string') return '';
                    const cleaned = value.replace(/!important/g, '').trim();
                    if (!cleaned || cleaned === 'auto') return '';
                    if (!allowZero && cleaned === '0px') return '';
                    return cleaned;
                }

                function restoreFreeLayoutSettings() {
                    if (!$('#switch-free-layout').is(':checked')) return;

                    const settings = getSavedDashboardSettings();
                    const container = $('#dashboard-widgets');
                    let maxBottom = 1000;

                    dashboardMutationGuard = true;
                    try {
                        Object.keys(settings).forEach(function(id) {
                            const widget = $('#' + id);
                            const s = settings[id] || {};
                            if (!widget.length || widget.hasClass('widget-hidden')) return;
                            const savedLeft = cleanDashboardCssValue(s.left, true);
                            const savedTop = cleanDashboardCssValue(s.top, true);
                            if (!savedLeft || !savedTop) return;

                            const savedWidth = (s.width && s.width.indexOf('col-') === -1) ? cleanDashboardCssValue(s.width) : '';
                            const savedHeight = cleanDashboardCssValue(s.height);
                            const css = {
                                position: 'absolute',
                                left: savedLeft,
                                top: savedTop,
                                flex: 'none',
                                maxWidth: 'none',
                                overflow: 'visible'
                            };

                            if (savedWidth) {
                                css.width = savedWidth;
                            }
                            if (savedHeight) {
                                css.height = savedHeight;
                            }
                            if (s.zIndex) {
                                css.zIndex = s.zIndex;
                            }

                            widget.addClass('resizable-widget').css(css);
                            widget.find('.card, .carousel, .carousel-inner, .card-body, .tab-content').css({
                                height: savedHeight ? '100%' : '',
                                minHeight: '0',
                                maxHeight: 'none'
                            });

                            const bottom = parseFloat(savedTop) + parseFloat(savedHeight || widget.outerHeight());
                            if (Number.isFinite(bottom)) {
                                maxBottom = Math.max(maxBottom, bottom);
                            }
                        });

                        container.css('min-height', (maxBottom + 100) + 'px');
                    } finally {
                        dashboardMutationGuard = false;
                    }
                }

                function applyWidgetSettings() {
                    let settings = {};
                    try {
                        settings = getSavedDashboardSettings();
                    } catch(e) {
                        console.warn("Dashboard settings corrupted, resetting:", e.message);
                        settings = {};
                        // Clear corrupted data to prevent infinite error loops
                        localStorage.removeItem('dashboard_widget_settings');
                        localStorage.removeItem('dashboard_order');
                        document.cookie = "dashboard_settings=; path=/; max-age=0";
                        document.cookie = "dashboard_order=; path=/; max-age=0";
                        // Also clear dashboard cache to force fresh load
                        Object.keys(localStorage).forEach(function(key) {
                            if (key.startsWith('dashboard_cache_')) localStorage.removeItem(key);
                        });
                    }
                    const isFreeLayout = $('#switch-free-layout').is(':checked');

                    let maxBottom = 1000;

                    Object.keys(settings).forEach(id => {
                        const widget = $('#' + id);
                        if (widget.length && settings[id]) {
                            const s = settings[id];
                            setWidgetVisibility(id, s.hidden !== 'true', { syncCheckbox: true });

                            if (s.width && s.width.indexOf('col-') === 0) {
                                const classes = (widget.attr('class') || '').split(' ').filter(Boolean);
                                const newClasses = classes.filter(cls => !/^col(-[a-z]+)?-\d+$/.test(cls));
                                const savedWidthClasses = s.width.split(' ').filter(cls => /^col(-[a-z]+)?-\d+$/.test(cls));
                                const fallbackWidth = defaultWidgetWidthClasses[id] ? defaultWidgetWidthClasses[id].split(' ') : [];
                                const resolvedWidthClasses = (
                                    savedWidthClasses.length === 1 &&
                                    fallbackWidth.length > 1 &&
                                    fallbackWidth.includes(savedWidthClasses[0])
                                ) ? fallbackWidth : savedWidthClasses;
                                newClasses.push(...resolvedWidthClasses);
                                widget.attr('class', newClasses.join(' '));
                            }

                            if (s.height && s.height !== 'auto' && s.height.indexOf('col-') === -1) {
                                widget.find('.card-body').first().css('height', s.height);
                            }
                        }
                    });

                    if (!isFreeLayout) {
                        ['widget-ana-slider', 'widget-personel-ozet', 'widget-arac-ozet'].forEach(function (id) {
                            const widget = $('#' + id);
                            const fallbackWidth = defaultWidgetWidthClasses[id];
                            if (!widget.length || !fallbackWidth) return;

                            const classes = (widget.attr('class') || '').split(' ').filter(Boolean);
                            const newClasses = classes.filter(cls => !/^col(-[a-z]+)?-\d+$/.test(cls));
                            newClasses.push(...fallbackWidth.split(' '));
                            widget.attr('class', newClasses.join(' '));
                        });
                    }

                    if (isFreeLayout) {
                        Object.keys(settings).forEach(id => {
                            const widget = $('#' + id);
                            if (widget.length && settings[id]) {
                                const s = settings[id];
                                const css = {};
                                const savedWidth = (s.width && s.width.indexOf('col-') === -1) ? cleanDashboardCssValue(s.width) : '';
                                const savedHeight = cleanDashboardCssValue(s.height);
                                const savedLeft = cleanDashboardCssValue(s.left, true);
                                const savedTop = cleanDashboardCssValue(s.top, true);

                                if (savedWidth) {
                                    css.width = savedWidth;
                                    css.flex = 'none';
                                    css.maxWidth = 'none';
                                }
                                if (savedHeight) {
                                    css.height = savedHeight;
                                    widget.find('.card, .carousel').css({
                                        'height': '100%',
                                        'min-height': '0',
                                        'max-height': 'none'
                                    });
                                }
                                if (savedLeft && savedTop) {
                                    css.position = 'absolute';
                                    css.left = savedLeft;
                                    css.top = savedTop;
                                    css.zIndex = s.zIndex || 100;
                                    
                                    // Calculate max height for scroll
                                    const topVal = parseFloat(savedTop);
                                    const hVal = parseFloat(savedHeight || widget.outerHeight());
                                    if (!isNaN(topVal) && !isNaN(hVal)) {
                                        const bottom = topVal + hVal;
                                        if (bottom > maxBottom) maxBottom = bottom;
                                    }
                                }
                                widget.css(css);
                            }
                        });
                        
                        // Set container height based on maxBottom
                        $('#dashboard-widgets').css('min-height', (maxBottom + 100) + 'px');
                    } else {
                        // Clear manual styles if free layout is not active
                        $('#dashboard-widgets').css('min-height', '');
                        $('#dashboard-widgets .widget-item').each(function() {
                            $(this).removeAttr('style').css({
                                position: '',
                                left: '',
                                top: '',
                                width: '',
                                height: '',
                                flex: '',
                                maxWidth: '',
                                zIndex: '',
                                overflow: ''
                            });
                        });
                        $('.card, .carousel', '#dashboard-widgets').css({
                            height: '',
                            minHeight: '',
                            maxHeight: ''
                        });
                    }
                    
                    // Always initialize Mac controls after applying settings
                    if (typeof initMacControls === 'function') {
                        initMacControls();
                    }

                    if (isFreeLayout) {
                        restoreFreeLayoutSettings();
                    }
                }

                // Auto-apply settings when DOM changes (Lazy Loading Robustness)
                const dashboardObserver = new MutationObserver(function(mutations) {
                    if (dashboardMutationGuard) return;
                    let shouldReapply = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            $(mutation.addedNodes).each(function() {
                                if (
                                    $(this).hasClass('widget-item') ||
                                    $(this).find('.widget-item').length ||
                                    $(this).hasClass('lazy-widget') ||
                                    $(this).find('.lazy-widget').length ||
                                    (this.nodeType === 1 && this.hasAttribute && this.hasAttribute('data-lazy-load'))
                                ) {
                                    shouldReapply = true;
                                }
                            });
                        }
                    });
                    if (shouldReapply) {
                        dashboardMutationGuard = true;
                        try {
                            applyWidgetSettings();
                            if (typeof initResizableWidgets === 'function') initResizableWidgets();
                        } finally {
                            dashboardMutationGuard = false;
                        }
                    }
                });

                $(document).ready(function() {
                    const dashboardNode = document.getElementById('dashboard-widgets');
                    if (dashboardNode) {
                        dashboardObserver.observe(dashboardNode, { childList: true, subtree: false });
                    }
                });

                let gridSortable = null;

                function initGridSortable() {
                    if (typeof Sortable !== 'undefined') {
                        const container = document.getElementById('dashboard-widgets');
                        if (container && !gridSortable) {
                            gridSortable = new Sortable(container, {
                                animation: 150,
                                handle: '.mac-title-bar, .drag-handle, .card-header, .stat-card, .card',
                                filter: '.mac-control, .btn, a, input, select, textarea, .custom-resize-handle, .dashboard-resize-grip, .mac-controls',
                                preventOnFilter: true,
                                ghostClass: 'bg-light',
                                onEnd: function () {
                                    saveDashboardConfig();
                                }
                            });
                        }
                    }
                }

                function destroyGridSortable() {
                    if (gridSortable) {
                        gridSortable.destroy();
                        gridSortable = null;
                    }
                }

                applyWidgetSettings();
                applySavedWidgetOrder();

                if (typeof Sortable === 'undefined') {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js';
                    s.onload = function() {
                        if (!$('#switch-free-layout').is(':checked')) {
                            initGridSortable();
                        }
                    };
                    document.head.appendChild(s);
                } else {
                    if (!$('#switch-free-layout').is(':checked')) {
                        initGridSortable();
                    }
                }

                // Raise z-index on clicking/focusing any part of a card
                $(document).on('mousedown', '#dashboard-widgets .widget-item', function () {
                    if ($('#switch-free-layout').is(':checked')) {
                        maxZIndex++;
                        $(this).css('z-index', maxZIndex);
                    }
                });

                // Switch change listener
                $(document).on('change', '#switch-free-layout', function() {
                    const active = this.checked;
                    localStorage.setItem('switch_free_layout', active ? 'true' : 'false');
                    document.cookie = "switch_free_layout=" + (active ? 'true' : 'false') + "; path=/; max-age=" + (60 * 60 * 24 * 30);
                    updateRandomLayoutButtonVisibility();
                    if (active) {
                        destroyGridSortable();
                        $('#dashboard-widgets').removeClass('row').addClass('d-block free-layout-active');
                        applyWidgetSettings();
                        if (typeof initResizableWidgets === 'function') initResizableWidgets();
                        $('#dashboard-widgets .widget-item').each(function () {
                            const widget = $(this);
                            if (!widget.hasClass('widget-hidden') && widget.is(':visible')) {
                                widget.css({
                                    width: widget.outerWidth() + 'px',
                                    height: widget.outerHeight() + 'px'
                                });
                            }
                        });
                        saveDashboardConfig(true);
                    } else {
                        initGridSortable();
                        $('#dashboard-widgets').removeClass('d-block free-layout-active').addClass('row');
                        $('#dashboard-widgets .widget-item').removeClass('resizable-widget').css({
                            position: '',
                            left: '',
                            top: '',
                            width: '',
                            height: '',
                            flex: '',
                            maxWidth: '',
                            zIndex: '',
                            overflow: ''
                        }).find('.card, .carousel, .carousel-inner, .card-body, .tab-content').css({
                            height: '',
                            minHeight: '',
                            maxHeight: ''
                        });
                        $('.dashboard-resize-edge-e, .dashboard-resize-edge-s, .dashboard-resize-grip').remove();
                        // Redraw standard layout
                        initMacControls();
                        saveDashboardConfig(true);
                        setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 100);
                    }
                });

                $(document).on('click', '#btn-random-layout', function () {
                    arrangeWidgetsRandomly();
                });

                let maxZIndex = 1100;

                // Card Movement Logic (Move like a Windows Desktop Window)
                $(document).on('mousedown touchstart', '#dashboard-widgets .widget-item .mac-title-bar, #dashboard-widgets .widget-item .card-header', function (e) {
                    if (!$('#switch-free-layout').is(':checked')) return;
                    if ($(e.target).closest('.mac-control, .btn, a, input, select, textarea, .custom-resize-handle, .dashboard-resize-grip').length) return;

                    let clientX = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientX : e.clientX;
                    let clientY = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientY : e.clientY;

                    const widget = $(this).closest('.widget-item');
                    maxZIndex++;

                    if (widget.css('position') !== 'absolute') {
                        const offset = widget.position();
                        widget.css({
                            'position': 'absolute',
                            'left': offset.left + 'px',
                            'top': offset.top + 'px',
                            'z-index': maxZIndex,
                            'flex': 'none',
                            'max-width': 'none'
                        });
                    } else {
                        widget.css('z-index', maxZIndex);
                    }

                    let startX = clientX;
                    let startY = clientY;
                    let initialLeft = parseFloat(widget.css('left')) || 0;
                    let initialTop = parseFloat(widget.css('top')) || 0;
                    let isMoving = true;

                    $(document).on('mousemove.widgetMove touchmove.widgetMove', function (e) {
                        if (!isMoving) return;
                        let moveX = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientX : e.clientX;
                        let moveY = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientY : e.clientY;
                        const newLeft = initialLeft + (moveX - startX);
                        const newTop = initialTop + (moveY - startY);

                        widget.css({
                            'left': newLeft + 'px',
                            'top': newTop + 'px'
                        });

                        // Dynamically increase container height as cards go down
                        const cardBottom = newTop + widget.outerHeight();
                        const container = $('#dashboard-widgets');
                        if (cardBottom > container.height()) {
                            container.css('min-height', (cardBottom + 100) + 'px');
                            localStorage.setItem('dashboard_container_height', (cardBottom + 100) + 'px');
                        }
                    });

                    $(document).on('mouseup.widgetMove touchend.widgetMove', function () {
                        if (isMoving) {
                            isMoving = false;
                            $(document).off('.widgetMove');
                            saveDashboardConfig();
                        }
                    });
                });

                // Apple/Mac Style Controls Logic — Premium iOS/macOS Design
                function initMacControls() {
                    $('#dashboard-widgets .widget-item').each(function() {
                        const widgetItem = $(this);
                        if (widgetItem.hasClass('widget-hidden') || widgetItem.attr('id') === 'widget-row-break') return;

                        // Target the first card or carousel inside the widget
                        let card = widgetItem.children('.card').first();
                        if (!card.length) card = widgetItem.find('.card').first();
                        if (!card.length) card = widgetItem.find('.carousel').first();
                        if (!card.length) return;

                        // Remove any existing mac-title-bar first (for idempotent re-init)
                        card.find('.mac-title-bar').remove();

                        // ── Title resolution ──────────────────────────────────────
                        const titleMap = {
                            'widget-ana-slider'              : 'Haberler & Duyurular',
                            'widget-personel-ozeti'          : 'Personel Durumu',
                            'widget-personel-ozet'           : 'Personel Durumu',
                            'widget-arac-ozeti'              : 'Araç Durumu',
                            'widget-arac-ozet'               : 'Araç Durumu',
                            'widget-bekleyen-talepler'       : 'Bekleyen Talepler',
                            'widget-gec-kalanlar'            : 'Geç Kalanlar',
                            'widget-nobetciler'              : 'Bugünkü Nöbetçiler',
                            'widget-gunluk-muhurleme'        : 'Günlük Mühürleme',
                            'widget-gunluk-kesme-acma'       : 'Günlük Kesme/Açma',
                            'widget-gunluk-endeks-okuma'     : 'Endeks Okuma',
                            'widget-gunluk-sayac-degisimi'   : 'Sayaç Değişimi',
                            'widget-gunluk-kacak'            : 'Kaçak Kontrolü',
                            'widget-kacak-sayisi'            : 'Kaçak Sayısı',
                            'widget-izinliler'               : 'İzinli Personeller',
                            'widget-izindekiler'             : 'İzinde Olanlar',
                            'widget-bildirimler'             : 'Görev & Bildirimler',
                            'widget-talepler'                : 'Talep Yönetimi',
                            'widget-yaklasan-gorevler'       : 'Yaklaşan Görevler',
                            'widget-endeks-karsilastirma'    : 'Endeks Karşılaştırma',
                            'widget-is-turu-istatistikleri'  : 'İş Türü İstatistikleri',
                            'widget-is-emri-sonucu-istatistikleri': 'İş Emri Sonuçları'
                        };

                        const wId = widgetItem.attr('id') || '';
                        const existingHeader = card.children('.card-header').first();
                        const existingFinderShell = card.find('> .mac-title-bar .finder-tabs-shell').first();
                        if (existingFinderShell.length && wId !== 'widget-bildirimler') {
                            card.children('.card-body').first().prepend(existingFinderShell.removeClass('finder-tabs-shell-inbar'));
                        }
                        let titleText = titleMap[wId];

                        if (!titleText) {
                            // Try to pull from card header or first heading
                            if (existingHeader.length) {
                                titleText = existingHeader.find('h1,h2,h3,h4,h5,h6,strong,.card-title').first().text();
                            }
                            if (!titleText || titleText.trim() === '') {
                                titleText = card.find('h1,h2,h3,h4,h5,h6,strong,.stat-label,.card-title').first().text();
                            }
                        }

                        // Sanitise
                        titleText = (titleText || 'Bilgi Kartı')
                            .replace(/drag_handle/gi, '')
                            .replace(/^bx-[a-z0-9-]+/i, '')
                            .trim();
                        if (!titleText) titleText = 'Bilgi Kartı';

                        // ── Build the Mac title bar ───────────────────────────────
                        const $bar = $(`
                            <div class="mac-title-bar">
                                <div class="mac-controls">
                                    <div class="mac-control mac-close"    title="Kapat"></div>
                                    <div class="mac-control mac-minimize" title="Küçült"></div>
                                    <div class="mac-control mac-maximize" title="Tam Ekran"></div>
                                </div>
                                <span class="mac-title-text" data-fallback="${titleText}">${titleText}</span>
                                <div class="drag-handle-indicator">
                                    <i class="bx bx-grid-vertical"></i>
                                </div>
                            </div>
                        `);

                        // ── Button handlers ───────────────────────────────────────
                        // Close (hide widget)
                        $bar.find('.mac-close').on('click', function(e) {
                            e.preventDefault(); e.stopPropagation();
                            if (typeof setWidgetVisibility === 'function') {
                                setWidgetVisibility(wId, false, { syncCheckbox: true });
                            }
                            if (typeof saveWidgetVisibility === 'function') {
                                saveWidgetVisibility();
                            }
                            if (typeof saveDashboardConfig === 'function') saveDashboardConfig();
                        });

                        // Minimize (collapse card body)
                        $bar.find('.mac-minimize').on('click', function(e) {
                            e.preventDefault(); e.stopPropagation();
                            const collapsed = widgetItem.toggleClass('widget-collapsed').hasClass('widget-collapsed');
                            if (collapsed) {
                                widgetItem.data('restore-h', widgetItem.css('height'));
                                widgetItem.css({ height: '32px', minHeight: '32px', overflow: 'hidden' });
                            } else {
                                const rh = widgetItem.data('restore-h');
                                widgetItem.css({ height: (rh && rh !== '32px') ? rh : '', minHeight: '', overflow: '' });
                            }
                        });

                        // Maximize (fullscreen overlay)
                        $bar.find('.mac-maximize').on('click', function(e) {
                            e.preventDefault(); e.stopPropagation();
                            const isMax = widgetItem.data('is-maximized');
                            if (isMax) {
                                widgetItem.css({
                                    position : widgetItem.data('mx-pos')    || '',
                                    left     : widgetItem.data('mx-left')   || '',
                                    top      : widgetItem.data('mx-top')    || '',
                                    width    : widgetItem.data('mx-width')  || '',
                                    height   : widgetItem.data('mx-height') || '',
                                    zIndex   : widgetItem.data('mx-z')      || ''
                                });
                                widgetItem.data('is-maximized', false);
                                widgetItem.removeClass('widget-maximized');
                            } else {
                                widgetItem.data('mx-pos',    widgetItem.css('position'));
                                widgetItem.data('mx-left',   widgetItem.css('left'));
                                widgetItem.data('mx-top',    widgetItem.css('top'));
                                widgetItem.data('mx-width',  widgetItem.css('width'));
                                widgetItem.data('mx-height', widgetItem.css('height'));
                                widgetItem.data('mx-z',      widgetItem.css('z-index'));
                                widgetItem.css({
                                    position: 'fixed',
                                    left: '0', top: '0',
                                    width: '100vw', height: '100vh',
                                    zIndex: 99999
                                });
                                widgetItem.data('is-maximized', true);
                                widgetItem.addClass('widget-maximized');
                                setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
                            }
                        });

                        // ── Inject & clean up ─────────────────────────────────────
                        // Prepend bar to card
                        card.prepend($bar);

                        if (wId === 'widget-bildirimler') {
                            const toolbarShell = card.find('.finder-tabs-shell').first();
                            if (toolbarShell.length) {
                                const toolbarSlot = $('<div class="mac-toolbar-slot"></div>');
                                const toolbarClone = toolbarShell.clone(true, true);
                                toolbarClone.removeAttr('style').addClass('finder-tabs-shell-inbar');
                                toolbarSlot.append(toolbarClone);
                                $bar.find('.mac-controls').after(toolbarSlot);
                            }
                        }

                        // Hide original card-header only.
                        // Widget data values also use h4/h6 tags, so hiding all headings
                        // makes numeric content disappear after lazy rendering.
                        if (existingHeader.length) existingHeader.hide();

                        // Hide drag indicators/header elements that are not inside the new bar.
                        card.find(
                            '.card-title, .drag-handle, ' +
                            '.card-header, [class*="drag_handle"]'
                        ).filter(function() {
                            return $(this).closest('.mac-title-bar').length === 0;
                        }).hide();
                    });
                }

                // Card Resize Logic (Width)
                $(document).on('click', '.btn-resize-width', function (e) {
                    e.preventDefault();
                    const newWidth = $(this).data('width');
                    const widget = $(this).closest('.widget-item');

                    widget.removeAttr('data-resized');
                    widget.css('width', '');

                    // Remove existing col- classes
                    const classes = widget.attr('class').split(' ');
                    const newClasses = classes.filter(c => !c.startsWith('col-'));
                    newClasses.push(newWidth);

                    widget.attr('class', newClasses.join(' '));
                    saveDashboardConfig();

                    // Trigger window resize to let charts adjust
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 100);
                });

                // Card Resize Logic (Height)
                $(document).on('click', '.btn-resize-height', function (e) {
                    e.preventDefault();
                    const newHeight = $(this).data('height');
                    const widget = $(this).closest('.widget-item');
                    
                    widget.removeAttr('data-resized');
                    widget.css('height', '');

                    const cardBody = widget.find('.card-body');
                    cardBody.css('height', newHeight);
                    saveDashboardConfig();

                    // Trigger window resize to let charts adjust
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 100);
                });

                $(document).on('mousedown', '.mac-control', function(e) {
                    e.stopPropagation();
                });

                function ensureAbsoluteWidgetPosition(widget) {
                    if (widget.css('position') !== 'absolute') {
                        const offset = widget.position();
                        widget.css({
                            position: 'absolute',
                            left: offset.left + 'px',
                            top: offset.top + 'px',
                            flex: 'none',
                            maxWidth: 'none'
                        });
                    }
                }

                function updateRandomLayoutButtonVisibility() {
                    $('#random-layout-row').toggle($('#switch-free-layout').is(':checked'));
                }

                function arrangeWidgetsRandomly() {
                    if (!$('#switch-free-layout').is(':checked')) return;

                    const container = $('#dashboard-widgets');
                    const visibleWidgets = container.find('.widget-item:visible').not('.widget-hidden, #widget-row-break').toArray();
                    if (!visibleWidgets.length) return;

                    const shuffled = visibleWidgets
                        .map(widget => ({ widget, sort: Math.random() }))
                        .sort((a, b) => a.sort - b.sort)
                        .map(item => item.widget);

                    const gap = 24;
                    const containerWidth = Math.max(container.innerWidth() || 0, 960);
                    let x = 0;
                    let y = 0;
                    let rowHeight = 0;
                    let localMaxZ = maxZIndex;

                    shuffled.forEach(function(element) {
                        const widget = $(element);
                        if (!widget.length) return;

                        ensureAbsoluteWidgetPosition(widget);

                        const width = Math.ceil(widget.outerWidth());
                        const height = Math.ceil(widget.outerHeight());

                        if (x > 0 && (x + width) > containerWidth) {
                            x = 0;
                            y += rowHeight + gap;
                            rowHeight = 0;
                        }

                        localMaxZ += 1;
                        widget.css({
                            position: 'absolute',
                            left: x + 'px',
                            top: y + 'px',
                            zIndex: localMaxZ,
                            flex: 'none',
                            maxWidth: 'none'
                        });

                        x += width + gap;
                        rowHeight = Math.max(rowHeight, height);
                    });

                    maxZIndex = localMaxZ;
                    container.css('min-height', (y + rowHeight + 120) + 'px');
                    localStorage.setItem('dashboard_container_height', (y + rowHeight + 120) + 'px');
                    saveDashboardConfig(true);
                    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 100);
                }

                // Rebuilt resize layer with pure JavaScript to ensure it always works without any UI plugin dependencies
                function initResizableWidgets() {
                    if (!$('#switch-free-layout').is(':checked')) return;
                    
                    const pageContent = $('#dashboard-page-content');
                    const savedSettings = getSavedDashboardSettings();

                    if (pageContent.length && pageContent.css('display') === 'none') {
                        pageContent.show();
                        $('#dashboard-page-skeleton').hide();
                        $('#dashboard-skeleton-critical').remove();
                    }

                    $('#dashboard-widgets .widget-item').each(function () {
                        const widget = $(this);
                        const id = widget.attr('id');
                        if (!id || id === 'widget-row-break' || widget.hasClass('widget-hidden')) return;
                        if (!widget.is(':visible') || widget.outerWidth() <= 0 || widget.outerHeight() <= 0) return;

                        const saved = savedSettings[id] || {};
                        const position = widget.position();
                        const currentLeft = parseFloat(widget.css('left'));
                        const currentTop = parseFloat(widget.css('top'));
                        const currentWidth = widget[0].style.width;
                        const currentHeight = widget[0].style.height;
                        const savedLeft = cleanDashboardCssValue(saved.left, true);
                        const savedTop = cleanDashboardCssValue(saved.top, true);
                        const savedWidth = (saved.width && saved.width.indexOf('col-') === -1) ? cleanDashboardCssValue(saved.width) : '';
                        const savedHeight = cleanDashboardCssValue(saved.height);

                        widget.addClass('resizable-widget').css({
                            position: 'absolute',
                            left: savedLeft || (Number.isFinite(currentLeft) ? currentLeft + 'px' : position.left + 'px'),
                            top: savedTop || (Number.isFinite(currentTop) ? currentTop + 'px' : position.top + 'px'),
                            width: savedWidth || (currentWidth && currentWidth !== '0px' ? currentWidth : widget.outerWidth() + 'px'),
                            height: savedHeight || (currentHeight && currentHeight !== '0px' && currentHeight !== 'auto' ? currentHeight : 'auto'),
                            zIndex: saved.zIndex || widget.css('z-index') || 100,
                            flex: 'none',
                            maxWidth: 'none',
                            overflow: 'visible'
                        });

                        widget.find('.card, .carousel, .carousel-inner, .card-body, .tab-content').css({
                            height: '100%',
                            minHeight: '0',
                            maxHeight: 'none'
                        });

                        if (widget.find('.dashboard-resize-edge-e').length === 0) {
                            widget.append('<div class="dashboard-resize-edge-e" style="position: absolute; width: 6px; top: 0; right: -3px; bottom: 0; cursor: e-resize; z-index: 1051; pointer-events: auto !important;" title="Sağa Boyutlandır"></div>');
                        }
                        if (widget.find('.dashboard-resize-edge-s').length === 0) {
                            widget.append('<div class="dashboard-resize-edge-s" style="position: absolute; height: 6px; left: 0; right: 0; bottom: -3px; cursor: s-resize; z-index: 1051; pointer-events: auto !important;" title="Aşağı Boyutlandır"></div>');
                        }
                        if (widget.find('.dashboard-resize-grip').length === 0) {
                            widget.append('<div class="dashboard-resize-grip" style="position: absolute; width: 14px; height: 14px; bottom: -3px; right: -3px; cursor: se-resize; z-index: 1051; background: transparent; pointer-events: auto !important;" title="Çapraz Boyutlandır"></div>');
                        }
                    });

                    restoreFreeLayoutSettings();

                    $(document)
                        .off('mousedown.widgetResize touchstart.widgetResize', '.dashboard-resize-grip, .dashboard-resize-edge-e, .dashboard-resize-edge-s')
                        .on('mousedown.widgetResize touchstart.widgetResize', '.dashboard-resize-grip, .dashboard-resize-edge-e, .dashboard-resize-edge-s', function (e) {
                            e.preventDefault();
                            e.stopPropagation();

                            const handle = $(this);
                            const widget = handle.closest('.widget-item');
                            const isTouch = e.type.startsWith('touch');
                            const startX = isTouch ? e.originalEvent.touches[0].clientX : e.clientX;
                            const startY = isTouch ? e.originalEvent.touches[0].clientY : e.clientY;
                            const startWidth = widget.outerWidth();
                            const startHeight = widget.outerHeight();
                            const minWidth = 180;
                            const minHeight = 120;

                            const isE = handle.hasClass('dashboard-resize-edge-e');
                            const isS = handle.hasClass('dashboard-resize-edge-s');
                            const isSE = handle.hasClass('dashboard-resize-grip');

                            widget.addClass('dashboard-resizing');

                            $(document)
                                .off('mousemove.widgetResize touchmove.widgetResize')
                                .on('mousemove.widgetResize touchmove.widgetResize', function (moveEvent) {
                                    const moveIsTouch = moveEvent.type.startsWith('touch');
                                    const movePoint = moveIsTouch ? moveEvent.originalEvent.touches[0] : moveEvent;
                                    if (!movePoint) return;

                                    let newWidth = startWidth;
                                    let newHeight = startHeight;

                                    if (isE || isSE) {
                                        newWidth = Math.max(minWidth, startWidth + (movePoint.clientX - startX));
                                    }
                                    if (isS || isSE) {
                                        newHeight = Math.max(minHeight, startHeight + (movePoint.clientY - startY));
                                    }

                                    widget.css({
                                        width: newWidth + 'px',
                                        height: newHeight + 'px'
                                    });

                                    widget.find('.card, .carousel, .carousel-inner, .card-body, .tab-content').css({
                                        height: '100%',
                                        minHeight: '0',
                                        maxHeight: 'none'
                                    });
                                })
                                .off('mouseup.widgetResize touchend.widgetResize')
                                .on('mouseup.widgetResize touchend.widgetResize', function () {
                                    $(document).off('mousemove.widgetResize touchmove.widgetResize mouseup.widgetResize touchend.widgetResize');
                                    widget.removeClass('dashboard-resizing');
                                    widget.attr('data-resized', 'true');
                                    saveDashboardConfig();
                                    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 100);
                                });
                        });
                    if (typeof initMacControls === 'function') initMacControls();
                }

                let savedFreeLayout = localStorage.getItem('switch_free_layout');
                if (savedFreeLayout === null) {
                    savedFreeLayout = 'false';
                    localStorage.setItem('switch_free_layout', 'false');
                }
                document.cookie = "switch_free_layout=" + savedFreeLayout + "; path=/; max-age=" + (60 * 60 * 24 * 30);
                const isFreeLayoutActive = savedFreeLayout === 'true';
                $('#switch-free-layout').prop('checked', isFreeLayoutActive);
                updateRandomLayoutButtonVisibility();
                if (isFreeLayoutActive) {
                    destroyGridSortable();
                    $('#dashboard-widgets').addClass('free-layout-active').removeClass('row').addClass('d-block');
                    applyWidgetSettings();
                    applySavedWidgetOrder();
                    initResizableWidgets();
                    setTimeout(initResizableWidgets, 300);
                } else {
                    initGridSortable();
                    $('#dashboard-widgets').removeClass('free-layout-active').addClass('row').removeClass('d-block');
                    applyWidgetSettings();
                    applySavedWidgetOrder();
                    // Mac controls are called inside applyWidgetSettings
                }


                // Reset Dashboard Logic
                $('#btn-reset-dashboard').on('click', function () {
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: "Tüm kart yerleşimleri ve genişlikleri varsayılan ayarlara dönecektir.",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Evet, Sıfırla',
                        cancelButtonText: 'İptal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Clear cookies
                            const cookieOptions = "; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
                            document.cookie = "dashboard_order=" + cookieOptions;
                            document.cookie = "dashboard_settings=" + cookieOptions;
                            document.cookie = "switch_free_layout=" + cookieOptions;
                            
                            // Clear localStorage
                            localStorage.removeItem('dashboard_widget_settings');
                            localStorage.removeItem('dashboard_order');
                            localStorage.removeItem('dashboard_container_height');
                            localStorage.removeItem('switch_free_layout');
                            localStorage.removeItem('dashboard_widget_visibility');
                            
                            // Notify server to reset database settings
                            $.ajax({
                                url: 'views/home/api.php',
                                type: 'POST',
                                data: {
                                    action: 'save-dashboard-settings',
                                    settings: '{}',
                                    order: '[]',
                                    is_free: 'false'
                                },
                                complete: function() {
                                    location.reload();
                                }
                            });
                        }
                    });
                });
                // Operasyonel İstatistikler Local Toggle Logic
                $(document).on('click', '.stats-local-btn', function () {
                    const mode = $(this).data('mode');
                    const cardBody = $(this).closest('.card-body');
                    const statValue = cardBody.find('.stat-value');

                    // Update local buttons state
                    cardBody.find('.stats-local-btn').removeClass('active');
                    $(this).addClass('active');

                    // Update data
                    const newValue = parseInt(statValue.data(mode)) || 0;
                    const label = statValue.data('label-' + mode);
                    const subtext = statValue.data('sub-' + mode);

                    cardBody.find('.stat-label').text(label);
                    cardBody.find('.stat-subtext').text(subtext);

                    const oldValue = parseInt(statValue.text().replace(/[^0-9]/g, '')) || 0;
                    animateValue(statValue[0], oldValue, newValue, 800);
                });

                function formatDashboardTimestamp(timestamp) {
                    if (!timestamp) return '-';
                    const normalized = String(timestamp).replace(' ', 'T');
                    const dt = new Date(normalized);
                    if (Number.isNaN(dt.getTime())) return '-';
                    return dt.toLocaleString('tr-TR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }

                function updateOperationalWidget(widgetId, dailyValue, monthlyValue, lastUpdate, updatedByUser) {
                    const cardBody = document.querySelector(`#${widgetId} .card-body`);
                    if (!cardBody) return;

                    const statValueEl = cardBody.querySelector('.stat-value');
                    if (!statValueEl) return;

                    const safeDaily = Number.isFinite(Number(dailyValue)) ? Number(dailyValue) : 0;
                    const safeMonthly = Number.isFinite(Number(monthlyValue)) ? Number(monthlyValue) : 0;

                    statValueEl.dataset.daily = safeDaily;
                    statValueEl.dataset.monthly = safeMonthly;

                    const activeMode = cardBody.querySelector('.stats-local-btn.active')?.dataset.mode === 'monthly' ? 'monthly' : 'daily';
                    const nextValue = activeMode === 'monthly' ? safeMonthly : safeDaily;
                    const currentValue = parseInt((statValueEl.textContent || '0').replace(/[^0-9]/g, ''), 10) || 0;

                    const labelEl = cardBody.querySelector('.stat-label');
                    const subtextEl = cardBody.querySelector('.stat-subtext');
                    if (labelEl) {
                        labelEl.textContent = activeMode === 'monthly'
                            ? (statValueEl.dataset.labelMonthly || labelEl.textContent)
                            : (statValueEl.dataset.labelDaily || labelEl.textContent);
                    }
                    if (subtextEl) {
                        subtextEl.textContent = activeMode === 'monthly'
                            ? (statValueEl.dataset.subMonthly || subtextEl.textContent)
                            : (statValueEl.dataset.subDaily || subtextEl.textContent);
                    }

                    animateValue(statValueEl, currentValue, nextValue, 700);

                    const updateEl = cardBody.querySelector('.last-update-value');
                    if (updateEl) {
                        updateEl.textContent = formatDashboardTimestamp(lastUpdate);
                    }

                    const userEl = cardBody.querySelector('.last-update-user-value');
                    if (userEl) {
                        userEl.textContent = updatedByUser || '-';
                    }
                }

                function initDashboard(force = false) {
                    const $operationalCards = $('.widget-item .stat-card');
                    const lazyWidgets = document.querySelectorAll('[data-lazy-load="true"]');
                    
                    const showContent = function() {
                        $('#dashboard-page-skeleton').hide();
                        $('#dashboard-page-content').show();
                        $('#dashboard-skeleton-critical').remove();
                    };

                    // 1. Client-Side Cache (Instant Feel)
                    const isFreeLayout = $('#switch-free-layout').is(':checked');
                    const cacheKey = 'dashboard_cache_v2_<?php echo $_SESSION['user_id']; ?>' + (isFreeLayout ? '_free' : '_grid');
                    const cachedData = localStorage.getItem(cacheKey);
                    
                    if (cachedData && !force) {
                        try {
                            const data = JSON.parse(cachedData);
                            if (data.stats) renderOperationalStats(data.stats);
                            if (data.results) {
                                Object.keys(data.results).forEach(id => {
                                    const $el = $('#' + id);
                                    if ($el.length && $el.hasClass('lazy-widget')) $el.replaceWith(data.results[id]);
                                });
                                if (typeof applyWidgetSettings === 'function') applyWidgetSettings();
                                if (typeof initResizableWidgets === 'function') initResizableWidgets();
                                // MUST call initMacControls after widgets are in DOM
                                if (typeof initMacControls === 'function') initMacControls();
                            }
                            // Cache exists, show content IMMEDIATELY
                            showContent();
                            // Final pass after paint
                            setTimeout(function() {
                                if (typeof initMacControls === 'function') initMacControls();
                                if (typeof restoreFreeLayoutSettings === 'function') restoreFreeLayoutSettings();
                            }, 250);
                        } catch(e) { console.error("Cache render error", e); }
                    }

                    // 2. Loading State
                    $operationalCards.addClass('is-loading');
                    $operationalCards.each(function() {
                        if (!$(this).find('.card-loading-overlay').length) {
                            $(this).append('<div class="card-loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
                        }
                    });

                    const widgetIds = [];
                    const widths = [];
                    const widgetVisibility = JSON.parse(localStorage.getItem('dashboard_widget_visibility') || '{}');

                    lazyWidgets.forEach(widget => {
                        const isVisible = widgetVisibility[widget.id] !== false;
                        if (!isVisible) return; // Kapalı olan kartların verilerini getirmesin

                        const widthStr = $(widget).attr('class') || '';
                        const width = widthStr.split(' ').filter(c => /^col(-[a-z]+)?-\d+$/.test(c)).join(' ') || 'col-md-6';
                        widgetIds.push(widget.id);
                        widths.push(width);
                    });

                    // 3. Single Combined Request
                    return $.ajax({
                        url: 'views/home/api.php',
                        type: 'POST',
                        data: { 
                            action: 'batch-load-all',
                            widgets: widgetIds,
                            widths: widths,
                            force: force
                        }
                    }).done(function (response) {
                        try {
                            const res = typeof response === 'object' ? response : JSON.parse(response);
                            if (res.status === 'success') {
                                // Save to localStorage
                                localStorage.setItem(cacheKey, JSON.stringify({
                                    stats: res.stats,
                                    results: res.results,
                                    time: Date.now()
                                }));

                                // Render Stats
                                if (res.stats) renderOperationalStats(res.stats);

                                // Render Widgets
                                if (res.results) {
                                    Object.keys(res.results).forEach(widgetId => {
                                        const $el = $('#' + widgetId);
                                        if ($el.length) $el.replaceWith(res.results[widgetId]);
                                    });
                                }

                                setTimeout(() => {
                                    window.dispatchEvent(new Event('resize'));
                                    if (window.feather) feather.replace();
                                    if (typeof applyWidgetSettings === 'function') applyWidgetSettings();
                                    if (typeof initResizableWidgets === 'function') initResizableWidgets();
                                    if (typeof initMacControls === 'function') initMacControls();
                                    // Data loaded, ensure content is shown
                                    showContent();
                                    // Extra delayed pass in case of slow render
                                    setTimeout(function() {
                                        if (typeof initMacControls === 'function') initMacControls();
                                        if (typeof restoreFreeLayoutSettings === 'function') restoreFreeLayoutSettings();
                                    }, 400);
                                }, 150);
                            }
                        } catch (err) { console.error('Dashboard init error:', err); }
                    }).always(function() {
                        $operationalCards.removeClass('is-loading');
                    });
                }

                function renderOperationalStats(stats) {
                    const daily = stats.daily || {};
                    const monthly = stats.monthly || {};
                    const lastUpdate = stats.last_update || {};

                    updateOperationalWidget('widget-gunluk-muhurleme', daily.muhurleme, monthly.muhurleme, lastUpdate.isler, lastUpdate.isler_user);
                    updateOperationalWidget('widget-gunluk-kesme-acma', daily.kesme_acma, monthly.kesme_acma, lastUpdate.isler, lastUpdate.isler_user);
                    updateOperationalWidget('widget-gunluk-endeks-okuma', daily.endeks_okuma, monthly.endeks_okuma, lastUpdate.endeks, lastUpdate.endeks_user);
                    updateOperationalWidget('widget-gunluk-sayac-degisimi', daily.sayac_degisimi, monthly.sayac_degisimi, lastUpdate.sayac, lastUpdate.sayac_user);
                    updateOperationalWidget('widget-kacak-sayisi', daily.kacak, monthly.kacak, null, '-');
                }

                function refreshOperationalStats() { return initDashboard(true); }

                initDashboard();

                // Online API Sync Logic
                $(document).on('click', '.btn-api-sync', function (e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const $card = $btn.closest('.card');
                    const $icon = $btn.find('i');
                    const action = $btn.data('action');
                    const today = '<?php echo date('Y-m-d'); ?>';
                    const firmaKodu = '<?php echo $_SESSION['firma_kodu'] ?? 17; ?>';

                    if ($btn.hasClass('syncing')) return;

                    $btn.addClass('syncing');
                    $icon.addClass('bx-spin text-primary');

                    // Kartı loading moduna al
                    if ($card.length) {
                        $card.addClass('is-loading');
                        if (!$card.find('.card-loading-overlay').length) {
                            $card.append('<div class="card-loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
                        }
                    }

                    $.ajax({
                        url: 'views/puantaj/api.php',
                        type: 'POST',
                        data: {
                            action: action,
                            active_tab: $(this).data(
                                'active-tab') || '',
                            baslangic_tarihi: today,
                            bitis_tarihi: today,
                            ilk_firma: firmaKodu,
                            son_firma: firmaKodu
                        },
                        success: function (response) {
                            $btn.removeClass('syncing');
                            $icon.removeClass('bx-spin text-primary');

                            try {
                                const res = typeof response === 'object' ? response : JSON.parse(response);
                                if (res.status === 'success') {
                                    let msg = res.message || (res.yeni_kayit || 0) + ' adet yeni kayıt eklendi.';
                                    if (res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0) {
                                        msg += '<br><br><span class="text-danger fw-bold">⚠️ Aparat Zimmeti Eksik Personeller (' + Object.keys(res.eksik_zimmetler).length + '):</span><br><small>Şu personellerin zimmetinde aparat olmadığı için tüketim düşülemedi.</small>';
                                    }
                                    refreshOperationalStats().always(function () {
                                        $card.removeClass('is-loading');
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Sorgulama Başarılı',
                                            html: msg,
                                            timer: res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0 ? 5000 : 2000,
                                            showConfirmButton: res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0
                                        });
                                    });
                                } else {
                                    $card.removeClass('is-loading');
                                    Swal.fire('Hata', res.message || 'Sorgulama sırasında bir hata oluştu.', 'error');
                                }
                            } catch (err) {
                                $card.removeClass('is-loading');
                                console.error("API Response Error:", err);
                                console.log("Raw Response:", response);
                                Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                            }
                        },
                        error: function () {
                            $btn.removeClass('syncing');
                            $icon.removeClass('bx-spin text-primary');
                            $card.removeClass('is-loading');
                            Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                        }
                    });
                });

                // Tekil Nöbet Hatırlatma Bildirimi
                $(document).on('click', '.btn-send-nobet-reminder', function (e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const $icon = $btn.find('i');
                    const pId = $btn.data('id');
                    const pName = $btn.data('name');

                    Swal.fire({
                        title: 'Bildirim Gönderilsin mi?',
                        text: pName + ' isimli personele bugün nöbetçi olduğuna dair hatırlatma bildirimi gönderilecek.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Evet, Gönder',
                        cancelButtonText: 'İptal',
                        confirmButtonColor: '#556ee6',
                        cancelButtonColor: '#f46a6a',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $icon.removeClass('bx-bell').addClass('bx-loader-alt bx-spin');
                            $btn.addClass('disabled');

                            $.ajax({
                                url: 'views/nobet/api.php',
                                type: 'POST',
                                data: {
                                    action: 'send-today-nobet-reminder',
                                    personel_id: pId
                                },
                                success: function (response) {
                                    $icon.removeClass('bx-loader-alt bx-spin').addClass('bx-bell');
                                    $btn.removeClass('disabled');

                                    try {
                                        const res = typeof response === 'string' ? JSON.parse(response) : response;
                                        if (res.status === 'success' || res.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Başarılı',
                                                text: res.message,
                                                timer: 1500,
                                                showConfirmButton: false
                                            });
                                        } else {
                                            Swal.fire('Hata', res.message || 'Bildirim gönderilemedi.', 'error');
                                        }
                                    } catch (err) {
                                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                                    }
                                },
                                error: function () {
                                    $icon.removeClass('bx-loader-alt bx-spin').addClass('bx-bell');
                                    $btn.removeClass('disabled');
                                    Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                                }
                            });
                        }
                    });
                });

                // ========== ENDEKS KARŞILAŞTIRMA KART LOGIC ==========
                (function () {
                    let endeksCompData = null;
                    let currentView = 'bolge';

                    function loadEndeksComparison() {
                        $.ajax({
                            url: 'views/home/api.php',
                            type: 'POST',
                            data: { action: 'get-endeks-comparison' },
                            success: function (response) {
                                try {
                                    const res = typeof response === 'object' ? response : JSON.parse(response);
                                    if (res.status === 'success' && res.data) {
                                        endeksCompData = res.data;
                                        $('#endeksCompGunNo').text(res.data.gun);
                                        $('#endeksCompGunBadge').removeClass('d-none');
                                        renderEndeksComparison();
                                    } else {
                                        showEndeksEmpty();
                                    }
                                } catch (e) {
                                    showEndeksEmpty();
                                }
                            },
                            error: function () {
                                showEndeksEmpty();
                            }
                        });
                    }

                    function showEndeksEmpty() {
                        $('#endeksCompLoading').hide();
                        $('#endeksCompContent').hide();
                        $('#endeksCompEmpty').show();
                    }

                    function renderEndeksComparison() {
                        if (!endeksCompData) return;
                        $('#endeksCompLoading').hide();

                        const bolgeData = endeksCompData.bolge || {};
                        const personelData = endeksCompData.personel || {};
                        const periods = endeksCompData.periods || [];

                        if (Object.keys(bolgeData).length === 0) {
                            showEndeksEmpty();
                            return;
                        }

                        renderBolgeView(bolgeData, periods);
                        renderPersonelView(personelData, periods);

                        $('#endeksCompContent').show();
                        if (currentView === 'bolge') {
                            $('#endeksCompBolge').show();
                            $('#endeksCompPersonel').hide();
                        } else {
                            $('#endeksCompBolge').hide();
                            $('#endeksCompPersonel').show();
                        }
                    }

                    function getTrendInfo(current, previous) {
                        if (!previous || previous === 0) return { text: '-', class: 'text-muted', icon: '', pct: 0 };
                        const diff = current - previous;
                        const pct = ((diff / previous) * 100).toFixed(1);
                        if (diff > 0) return { text: '+' + pct + '%', class: 'text-success', icon: 'bx-trending-up', pct: parseFloat(pct) };
                        if (diff < 0) return { text: pct + '%', class: 'text-danger', icon: 'bx-trending-down', pct: parseFloat(pct) };
                        return { text: '0%', class: 'text-muted', icon: 'bx-minus', pct: 0 };
                    }

                    function formatNumber(n) {
                        return new Intl.NumberFormat('tr-TR').format(n || 0);
                    }

                    // Satır arka plan rengini trend yüzdesine göre belirle
                    function getRowBgByTrend(pct) {
                        if (pct <= -20) return 'rgba(239, 68, 68, 0.08)';
                        if (pct <= -10) return 'rgba(245, 158, 11, 0.07)';
                        if (pct < 0) return 'rgba(251, 191, 36, 0.05)';
                        if (pct > 10) return 'rgba(16, 185, 129, 0.06)';
                        return 'transparent';
                    }

                    function getLeftBorderByTrend(pct) {
                        if (pct <= -20) return '3px solid #ef4444';
                        if (pct <= -10) return '3px solid #f59e0b';
                        if (pct < 0) return '3px solid #fbbf24';
                        if (pct > 10) return '3px solid #10b981';
                        return '3px solid transparent';
                    }

                    function getTrendBadge(trend) {
                        if (!trend.icon) return '<span class="text-muted" style="font-size: 12px;">-</span>';
                        const pct = trend.pct;
                        let bgColor, textColor, borderColor;
                        if (pct <= -20) {
                            bgColor = 'rgba(239, 68, 68, 0.12)'; textColor = '#dc2626'; borderColor = 'rgba(239, 68, 68, 0.3)';
                        } else if (pct <= -10) {
                            bgColor = 'rgba(245, 158, 11, 0.12)'; textColor = '#d97706'; borderColor = 'rgba(245, 158, 11, 0.3)';
                        } else if (pct < 0) {
                            bgColor = 'rgba(251, 191, 36, 0.1)'; textColor = '#b45309'; borderColor = 'rgba(251, 191, 36, 0.3)';
                        } else if (pct > 0) {
                            bgColor = 'rgba(16, 185, 129, 0.1)'; textColor = '#059669'; borderColor = 'rgba(16, 185, 129, 0.3)';
                        } else {
                            bgColor = 'rgba(148, 163, 184, 0.1)'; textColor = '#64748b'; borderColor = 'rgba(148, 163, 184, 0.3)';
                        }
                        return `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; background: ${bgColor}; color: ${textColor}; border: 1px solid ${borderColor}; white-space: nowrap;"><i class="bx ${trend.icon}" style="font-size: 14px;"></i>${trend.text}</span>`;
                    }

                    function getPerfDot(pct) {
                        if (pct <= -20) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:6px;box-shadow:0 0 4px rgba(239,68,68,0.4);animation:pulse-dot 2s infinite;" title="Kritik Düşüş"></span>';
                        if (pct <= -10) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:6px;" title="Düşüş"></span>';
                        if (pct < 0) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fbbf24;margin-right:6px;" title="Hafif Düşüş"></span>';
                        if (pct > 10) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px;" title="İyi Performans"></span>';
                        if (pct > 0) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6ee7b7;margin-right:6px;" title="Hafif Artış"></span>';
                        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#cbd5e1;margin-right:6px;" title="Değişim Yok"></span>';
                    }

                    function getMiniBar(value, maxValue, color) {
                        const pctWidth = maxValue > 0 ? Math.max(2, (value / maxValue) * 100) : 0;
                        return `<div style="width: 60px; height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 4px; overflow: hidden;">
                        <div style="height: 100%; width: ${pctWidth}%; background: ${color}; border-radius: 2px; transition: width 0.6s ease;"></div>
                    </div>`;
                    }

                    function buildSummaryCards(items, type) {
                        if (items.length === 0) return '';
                        const sorted = [...items].sort((a, b) => a.trendPct - b.trendPct);
                        const worst = sorted.slice(0, 3);
                        const best = sorted.slice(-3).reverse();

                        let html = '<div class="d-flex flex-wrap gap-2 px-3 py-3" style="border-bottom: 1px solid #f1f5f9; background: linear-gradient(135deg, #fafbff 0%, #f8fafc 100%);">';

                        if (worst.length > 0) {
                            html += '<div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">';
                            html += '<div class="d-flex align-items-center gap-1 me-2" style="white-space: nowrap;"><i class="bx bx-down-arrow-circle" style="color: #ef4444; font-size: 16px;"></i><span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.05em;">Düşük Performans</span></div>';
                            worst.forEach(w => {
                                html += `<div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); font-size: 12px;">`;
                                html += `<span style="font-weight: 600; color: #334155;">${w.name}</span>`;
                                html += `<span style="font-weight: 800; color: #dc2626; font-size: 13px;">${w.trendText}</span>`;
                                html += `</div>`;
                            });
                            html += '</div>';
                        }

                        if (best.length > 0) {
                            html += '<div class="d-flex align-items-center gap-2 flex-wrap ms-auto">';
                            html += '<div class="d-flex align-items-center gap-1 me-2" style="white-space: nowrap;"><i class="bx bx-up-arrow-circle" style="color: #10b981; font-size: 16px;"></i><span style="font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: 0.05em;">Yüksek Performans</span></div>';
                            best.forEach(b => {
                                html += `<div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.15); font-size: 12px;">`;
                                html += `<span style="font-weight: 600; color: #334155;">${b.name}</span>`;
                                html += `<span style="font-weight: 800; color: #059669; font-size: 13px;">${b.trendText}</span>`;
                                html += `</div>`;
                            });
                            html += '</div>';
                        }

                        html += '</div>';
                        return html;
                    }

                    function renderBolgeView(bolgeData, periods) {
                        const periodLabels = periods.map(p => p.label);

                        let bolgeEntries = [];
                        let firmaToplam = {};
                        let maxLastVal = 0;
                        periodLabels.forEach(label => { firmaToplam[label] = 0; });

                        Object.keys(bolgeData).forEach(bolge => {
                            const bData = bolgeData[bolge];
                            const periodValues = [];
                            periodLabels.forEach(label => {
                                const val = bData.periods[label]?.toplam || 0;
                                periodValues.push(val);
                                firmaToplam[label] += val;
                            });

                            const lastVal = periodValues[periodValues.length - 1];
                            const prevVal = periodValues.length > 1 ? periodValues[periodValues.length - 2] : 0;
                            const trend = getTrendInfo(lastVal, prevVal);
                            if (lastVal > maxLastVal) maxLastVal = lastVal;

                            bolgeEntries.push({
                                bolge, bData, periodValues, lastVal, trend,
                                trendPct: trend.pct, trendText: trend.text, name: bolge
                            });
                        });

                        // En düşük performans üstte
                        bolgeEntries.sort((a, b) => a.trendPct - b.trendPct);

                        let html = '';
                        html += '<style>@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.5;transform:scale(1.3);}}</style>';

                        // Özet kartlar
                        html += buildSummaryCards(bolgeEntries, 'bolge');

                        html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                        html += '<table class="table table-nowrap align-middle mb-0" style="font-size: 13px;">';

                        // Dark header
                        html += '<thead style="position: sticky; top: 0; z-index: 5;">';
                        html += '<tr style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">';
                        html += '<th style="padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 180px; border-bottom: none;">BÖLGE</th>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            html += `<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: ${isCurrent ? '#c7d2fe' : '#ffffff'} !important; font-weight: 800; min-width: 130px; border-bottom: none; ${isCurrent ? 'background: rgba(99,102,241,0.15);' : ''}">${label}</th>`;
                        });
                        html += '<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 120px; border-bottom: none;">DEĞİŞİM</th>';
                        html += '<th style="width: 40px; border-bottom: none;"></th>';
                        html += '</tr></thead>';

                        html += '<tbody>';

                        bolgeEntries.forEach((entry, bIdx) => {
                            const { bolge, bData, periodValues, trend } = entry;
                            const hasPersonel = bData.personeller && Object.keys(bData.personeller).length > 0;
                            const rowBg = getRowBgByTrend(trend.pct);
                            const leftBorder = getLeftBorderByTrend(trend.pct);
                            const pSayisi = bData.periods[periodLabels[periodLabels.length - 1]]?.personel_sayisi || 0;

                            html += `<tr class="bolge-row cursor-pointer" data-bolge="${bIdx}" style="border-bottom: 1px solid #f1f5f9; transition: all 0.25s; background: ${rowBg}; border-left: ${leftBorder};" ${hasPersonel ? `onclick="toggleBolgeDetail(${bIdx})"` : ''} onmouseover="this.style.filter='brightness(0.97)'" onmouseout="this.style.filter='none'">`;
                            html += `<td style="padding: 12px 16px;"><div class="d-flex align-items-center gap-2">`;
                            if (hasPersonel) {
                                html += `<i class="bx bx-chevron-right bolge-chevron-${bIdx} text-muted" style="font-size: 16px; transition: transform 0.2s;"></i>`;
                            } else {
                                html += `<span style="width: 16px;"></span>`;
                            }
                            html += getPerfDot(trend.pct);
                            html += `<div><span class="fw-bold" style="color: #1e293b; font-size: 13px;">${bolge}</span>`;
                            if (pSayisi > 0) html += `<br><span class="text-muted" style="font-size: 10px;">${pSayisi} personel</span>`;
                            html += `</div></div></td>`;

                            periodValues.forEach((val, pIdx) => {
                                const isCurrent = periods[pIdx].is_current;
                                const cellBg = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                const barColor = isCurrent ? '#6366f1' : '#94a3b8';
                                html += `<td class="text-center" style="padding: 10px 12px; background: ${cellBg};">`;
                                html += `<span class="fw-bold" style="color: #1e293b; font-size: 15px;">${formatNumber(val)}</span>`;
                                html += `<div class="d-flex justify-content-center">${getMiniBar(val, maxLastVal, barColor)}</div></td>`;
                            });

                            html += `<td class="text-center" style="padding: 10px 12px;">${getTrendBadge(trend)}</td>`;
                            html += `<td style="padding: 10px 8px;">`;
                            if (hasPersonel) html += `<i class="bx bx-expand-vertical text-muted" style="font-size: 12px; opacity: 0.5;"></i>`;
                            html += `</td></tr>`;

                            // Personel detay satırları
                            if (hasPersonel) {
                                const personeller = bData.personeller;
                                const pEntries = Object.keys(personeller).map(pKey => {
                                    const p = personeller[pKey];
                                    const pPeriods = [];
                                    periodLabels.forEach(l => pPeriods.push(p.periods[l]?.toplam || 0));
                                    const pLastVal = pPeriods[pPeriods.length - 1];
                                    const pPrevVal = pPeriods.length > 1 ? pPeriods[pPeriods.length - 2] : 0;
                                    return { p, pPeriods, pTrend: getTrendInfo(pLastVal, pPrevVal) };
                                });
                                pEntries.sort((a, b) => a.pTrend.pct - b.pTrend.pct);

                                pEntries.forEach(({ p, pPeriods, pTrend }) => {
                                    const detailBg = getRowBgByTrend(pTrend.pct);
                                    html += `<tr class="bolge-detail-${bIdx}" style="display: none; background: ${detailBg || '#fafbfc'}; border-bottom: 1px solid #f1f5f9; border-left: 3px solid #e2e8f0; animation: fadeInDown 0.2s;">`;
                                    html += `<td style="padding: 8px 16px 8px 56px;"><div class="d-flex align-items-center">${getPerfDot(pTrend.pct)}<div>`;
                                    html += `<span style="color: #475569; font-size: 12px; font-weight: 600;">${p.personel_adi}</span>`;
                                    html += `<br><span class="text-muted" style="font-size: 10px;">${p.ekip_adi}</span>`;
                                    html += `</div></div></td>`;

                                    pPeriods.forEach((val, ppIdx) => {
                                        const isCurrent = periods[ppIdx].is_current;
                                        const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                        html += `<td class="text-center" style="padding: 8px 12px; background: ${bgColor}; font-size: 12px;"><span class="fw-semibold" style="color: #475569;">${formatNumber(val)}</span></td>`;
                                    });

                                    html += `<td class="text-center" style="padding: 8px 12px;">${getTrendBadge(pTrend)}</td><td></td></tr>`;
                                });
                            }
                        });

                        // Firma toplam footer
                        html += '<tr style="background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); border-top: 2px solid #a5b4fc; font-weight: 700;">';
                        html += '<td style="padding: 14px 16px; color: #3730a3; font-size: 13px; border-left: 3px solid #6366f1;"><i class="bx bx-buildings me-2"></i>GENEL TOPLAM</td>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            const bgColor = isCurrent ? 'rgba(99,102,241,0.12)' : 'transparent';
                            html += `<td class="text-center" style="padding: 14px 12px; color: #312e81; font-size: 16px; font-weight: 800; background: ${bgColor};">${formatNumber(firmaToplam[label])}</td>`;
                        });
                        const fLastVal = firmaToplam[periodLabels[periodLabels.length - 1]];
                        const fPrevVal = periodLabels.length > 1 ? firmaToplam[periodLabels[periodLabels.length - 2]] : 0;
                        const fTrend = getTrendInfo(fLastVal, fPrevVal);
                        html += `<td class="text-center" style="padding: 14px 12px;">${getTrendBadge(fTrend)}</td><td></td></tr>`;

                        html += '</tbody></table></div>';

                        // Alt açıklama + lejand
                        html += '<div class="px-3 py-2 d-flex align-items-center gap-3" style="border-top: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">';
                        html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Her ayın 1\'i ile ' + endeksCompData.gun + '\'ı arası abone okuma sayıları karşılaştırılmaktadır.</span>';
                        html += '<div class="ms-auto d-flex align-items-center gap-3">';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>≤-20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;"></span>-10~20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>>+10%</div>';
                        html += '<a href="index.php?p=puantaj/raporlar&tab=karsilastirma" class="btn btn-sm btn-primary" style="font-size: 11px; border-radius: 6px; padding: 4px 14px; font-weight: 600;"><i class="bx bx-right-arrow-alt me-1"></i>Detaylı Rapor</a>';
                        html += '</div></div>';

                        $('#endeksCompBolge').html(html);
                    }

                    function renderPersonelView(personelData, periods) {
                        const periodLabels = periods.map(p => p.label);

                        let personelEntries = [];
                        let maxPersonelVal = 0;

                        Object.keys(personelData).forEach(pKey => {
                            const p = personelData[pKey];
                            const pPeriods = [];
                            periodLabels.forEach(l => pPeriods.push(p.periods[l]?.toplam || 0));
                            const pLastVal = pPeriods[pPeriods.length - 1];
                            const pPrevVal = pPeriods.length > 1 ? pPeriods[pPeriods.length - 2] : 0;
                            const pTrend = getTrendInfo(pLastVal, pPrevVal);
                            if (pLastVal > maxPersonelVal) maxPersonelVal = pLastVal;

                            personelEntries.push({
                                p, pPeriods, pLastVal, trend: pTrend,
                                trendPct: pTrend.pct, trendText: pTrend.text, name: p.personel_adi
                            });
                        });

                        // En düşük performanslılar üstte
                        personelEntries.sort((a, b) => a.trendPct - b.trendPct);

                        let html = '';
                        html += buildSummaryCards(personelEntries, 'personel');

                        html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                        html += '<table class="table table-nowrap align-middle mb-0" style="font-size: 13px;">';

                        // Dark header
                        html += '<thead style="position: sticky; top: 0; z-index: 5;">';
                        html += '<tr style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">';
                        html += '<th style="padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 180px; border-bottom: none;">PERSONEL</th>';
                        html += '<th style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 100px; border-bottom: none;">BÖLGE</th>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            html += `<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: ${isCurrent ? '#c7d2fe' : '#ffffff'} !important; font-weight: 800; min-width: 130px; border-bottom: none; ${isCurrent ? 'background: rgba(99,102,241,0.15);' : ''}">${label}</th>`;
                        });
                        html += '<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 120px; border-bottom: none;">DEĞİŞİM</th>';
                        html += '</tr></thead>';

                        html += '<tbody>';

                        personelEntries.forEach(entry => {
                            const { p, pPeriods, trend } = entry;
                            const rowBg = getRowBgByTrend(trend.pct);
                            const leftBorder = getLeftBorderByTrend(trend.pct);

                            html += `<tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.25s; background: ${rowBg}; border-left: ${leftBorder};" onmouseover="this.style.filter='brightness(0.97)'" onmouseout="this.style.filter='none'">`;
                            html += `<td style="padding: 12px 16px;"><div class="d-flex align-items-center">${getPerfDot(trend.pct)}<div>`;
                            html += `<span class="fw-bold" style="color: #1e293b; font-size: 12px;">${p.personel_adi}</span>`;
                            html += `<br><span class="text-muted" style="font-size: 10px;">${p.ekip_adi}</span>`;
                            html += `</div></div></td>`;
                            html += `<td style="padding: 12px 12px;"><span class="badge" style="font-size: 10px; font-weight: 600; background: rgba(99,102,241,0.08); color: #4338ca; border: 1px solid rgba(99,102,241,0.15); padding: 4px 8px; border-radius: 6px;">${p.bolge}</span></td>`;

                            pPeriods.forEach((val, ppIdx) => {
                                const isCurrent = periods[ppIdx].is_current;
                                const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                const barColor = isCurrent ? '#6366f1' : '#94a3b8';
                                html += `<td class="text-center" style="padding: 10px 12px; background: ${bgColor};">`;
                                html += `<span class="fw-bold" style="color: #1e293b; font-size: 14px;">${formatNumber(val)}</span>`;
                                html += `<div class="d-flex justify-content-center">${getMiniBar(val, maxPersonelVal, barColor)}</div></td>`;
                            });

                            html += `<td class="text-center" style="padding: 10px 12px;">${getTrendBadge(trend)}</td></tr>`;
                        });

                        html += '</tbody></table></div>';

                        html += '<div class="px-3 py-2 d-flex align-items-center" style="border-top: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">';
                        html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Personeller performans değişimine göre sıralanmıştır (en düşük performans üstte).</span>';
                        html += '<div class="ms-auto d-flex align-items-center gap-3">';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>≤-20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;"></span>-10~20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>>+10%</div>';
                        html += '</div></div>';

                        $('#endeksCompPersonel').html(html);
                    }

                    // Bölge detay toggle
                    window.toggleBolgeDetail = function (bIdx) {
                        const rows = $(`.bolge-detail-${bIdx}`);
                        const chevron = $(`.bolge-chevron-${bIdx}`);
                        if (rows.first().is(':visible')) {
                            rows.slideUp(200);
                            chevron.css('transform', 'rotate(0deg)');
                        } else {
                            rows.slideDown(200);
                            chevron.css('transform', 'rotate(90deg)');
                        }
                    };

                    // View toggle
                    $('#endeksCompViewToggle button').on('click', function () {
                        $('#endeksCompViewToggle button').removeClass('active');
                        $(this).addClass('active');
                        currentView = $(this).data('view');
                        if (currentView === 'bolge') {
                            $('#endeksCompBolge').fadeIn(200);
                            $('#endeksCompPersonel').hide();
                        } else {
                            $('#endeksCompBolge').hide();
                            $('#endeksCompPersonel').fadeIn(200);
                        }
                    });

                    // Sayfa yüklendiğinde veri çek
                    loadEndeksComparison();
                })();
                // ========== /ENDEKS KARŞILAŞTIRMA KART LOGIC ==========

                // DB'den gelen ayarları localStorage'a işle (Sayfa yüklendiğinde bir kez)
                (function() {
                    <?php if ($dbSettingsJson): ?>
                        if (!localStorage.getItem('dashboard_widget_settings')) {
                            localStorage.setItem('dashboard_widget_settings', <?php echo json_encode($dbSettingsJson); ?>);
                        }
                    <?php endif; ?>
                    <?php if ($dbOrderJson): ?>
                        if (!localStorage.getItem('dashboard_order')) {
                            localStorage.setItem('dashboard_order', <?php echo json_encode($dbOrderJson); ?>);
                        }
                    <?php endif; ?>
                    <?php if ($dbFreeLayout !== null): ?>
                        if (localStorage.getItem('switch_free_layout') === null) {
                            localStorage.setItem('switch_free_layout', <?php echo json_encode($dbFreeLayout); ?>);
                        }
                    <?php endif; ?>
                })();

                // Initialize everything on load
                $(document).ready(function() {
                    applyWidgetSettings();
                    applySavedWidgetOrder();
                    if (typeof initMacControls === 'function') initMacControls();
                    
                    // If free layout is active but no settings exist in localStorage, 
                    // capture current positions and save them so refresh doesn't break layout.
                    if ($('#switch-free-layout').is(':checked')) {
                        const existingSettings = localStorage.getItem('dashboard_widget_settings');
                        if (!existingSettings || existingSettings === '{}') {
                            setTimeout(() => { saveDashboardConfig(true); }, 500);
                        }
                    }
                });
            });
        </script>
        <?php
} else {
    //Alert::danger("Bu sayfaya erişim yetkiniz yok!");
    /**Personelin yetkili olduğu ilk sayfaya yönlendir */
    $permissionModel = new PermissionsModel();
    $permissionModel->redirectFirstPersmissionPage();
}
