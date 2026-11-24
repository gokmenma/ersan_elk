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

    $puantajModel = new \App\Model\PuantajModel();
    $endeksModel = new \App\Model\EndeksOkumaModel();
    $sayacService = new \App\Service\SayacDegisimService();
    $ihbarModel = new \App\Model\IhbarModel();

    $dailyWorkStats = $puantajModel->getDailyStats();
    $monthlyWorkStats = $puantajModel->getMonthlyStats();
    $dailyReadingTotal = $endeksModel->getDailyStats();
    $monthlyReadingTotal = $endeksModel->getMonthlyStats();
    $sayacDailyStats = $sayacService->getDailyStats();
    $sayacMonthlyStats = $sayacService->getMonthlyStats();
    $kacakDailyTotal = $puantajModel->getKacakDailyStats();
    $kacakMonthlyTotal = $puantajModel->getKacakMonthlyStats();
    $ihbarDailyTotal = $ihbarModel->getDailyStats();
    $ihbarMonthlyTotal = $ihbarModel->getMonthlyStats();

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

    $dashboard_is_free = false;
    $_COOKIE['switch_free_layout'] = 'false';

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
        $today = date('Y-m-d');
        $duyurular = array_values(array_filter(
            $_SESSION[$cache_key_slider]['duyurular'],
            static function (array $duyuru) use ($today): bool {
                return empty($duyuru['etkinlik_tarihi'])
                    || substr($duyuru['etkinlik_tarihi'], 0, 10) >= $today;
            }
        ));
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
                          AND (etkinlik_tarihi IS NULL OR DATE(etkinlik_tarihi) >= :today)
                          ORDER BY id DESC LIMIT 5";
            $stmt = $db->prepare($duyuruSql);
            $measureDb('home.duyuru_list', fn() => $stmt->execute([
                ':firma_id' => $_SESSION['firma_id'],
                ':today' => date('Y-m-d')
            ]));
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

        $hasLink = !empty($d['hedef_sayfa']);
        $linkClass = 'cursor-pointer';

        $duyuruJsonData = json_encode([
            'id' => $d['id'],
            'baslik' => $d['baslik'],
            'icerik' => $d['icerik'],
            'resim' => $d['resim'],
            'tarih' => date('d.m.Y', strtotime($d['tarih'])),
            'etkinlik_tarihi' => $d['etkinlik_tarihi'] ? date('d.m.Y', strtotime($d['etkinlik_tarihi'])) : null,
            'hedef_sayfa' => $d['hedef_sayfa']
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($hasLink) {
            $onClick = "onclick=\"window.location.href='" . htmlspecialchars($d['hedef_sayfa'], ENT_QUOTES, 'UTF-8') . "'\"";
        } else {
            $onClick = "onclick=\"openHomeDuyuruModal(this)\" data-duyuru='" . htmlspecialchars($duyuruJsonData, ENT_QUOTES, 'UTF-8') . "'";
        }

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

    if (\App\Service\Gate::allows("personel_listesi")) {
        try {
            $bugunAy = (int) date('n');
            $bugunGun = (int) date('j');
            $bugunDogumlar = array_values(array_filter(
                (new \App\Model\DogumGunuBildirimModel())->getTumDogumGunleri((int) $firmaId),
                static fn(array $k): bool => $k['ay'] === $bugunAy && $k['gun'] === $bugunGun
            ));
            if (!empty($bugunDogumlar)) {
                $isimler = array_map(
                    static fn(array $k): string => htmlspecialchars($k['ad'], ENT_QUOTES, 'UTF-8'),
                    array_slice($bugunDogumlar, 0, 3)
                );
                $digerSayisi = count($bugunDogumlar) - count($isimler);
                $dogumAciklama = '<strong>' . implode(', ', $isimler) . '</strong>' . ($digerSayisi > 0 ? ' ve ' . $digerSayisi . ' kişi daha' : '') . ' için mutlu yıllar dileriz.';
                array_unshift($slider_notifications, [
                    'id' => 0,
                    'title' => '🎂 Bugün ' . count($bugunDogumlar) . ' kişinin doğum günü',
                    'description' => $dogumAciklama,
                    'icon' => 'bx-cake',
                    'gradient' => 'linear-gradient(135deg, #5b21b6 0%, #a78bfa 100%)',
                    'link_action' => "onclick=\"window.location.href='index.php?p=personel/list'\"",
                    'link_class' => 'cursor-pointer'
                ]);
            }
        } catch (\Throwable $e) {
            error_log('Doğum günü slaytı hatası: ' . $e->getMessage());
        }
    }

    // Widget İçeriklerini Tanımla
    $widgets = [];



    if (!empty($slider_notifications)) {
        ob_start(); ?>
        <section class="dash-slider" id="widget-ana-slider" aria-label="Haberler ve duyurular">
            <div id="dashboardCarousel" class="carousel slide animate-card bordro-summary-card h-100" data-bs-ride="carousel" data-bs-interval="6000" data-bs-pause="hover" data-bs-touch="true"
                style="--delay: 0s;">
                <div class="carousel-indicators<?php echo count($slider_notifications) < 2 ? ' d-none' : ''; ?>" style="margin-bottom: 0.5rem;">
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
                                style="background: <?php echo $notif['gradient']; ?>; position: relative; overflow: hidden;">
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
                <button class="carousel-control-prev<?php echo count($slider_notifications) < 2 ? ' d-none' : ''; ?>" type="button" data-bs-target="#dashboardCarousel" data-bs-slide="prev"
                    style="width: 4%;">
                    <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 1.2rem; height: 1.2rem;"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next<?php echo count($slider_notifications) < 2 ? ' d-none' : ''; ?>" type="button" data-bs-target="#dashboardCarousel" data-bs-slide="next"
                    style="width: 4%;">
                    <span class="carousel-control-next-icon" aria-hidden="true" style="width: 1.2rem; height: 1.2rem;"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </section>
        <?php $dashSliderHtml = ob_get_clean();
    }

    if (\App\Service\Gate::allowsAny(['personel_listesi', 'arac_takip_yonetim'])) {
        $widgets['widget-personel-arac-ozet'] = renderSkeleton('widget-personel-arac-ozet', getWidgetWidth('widget-personel-arac-ozet', 'col-12'), '300px');
    }


    ob_start(); ?>
    <?php $widgets['widget-row-break'] = ob_get_clean();

    $fmtLastUpdate = static fn($dt): string => $dt ? date('d.m.Y H:i', strtotime((string) $dt)) : '-';
    $canOpsReports = Gate::allows('puantaj/raporlar');
    $canOpsKacak = Gate::allows('kacak_islemleri');
    $canOpsIhbar = Gate::allows('ihbar/list');
    $opsTiles = [];
    if ($canOpsReports) {
        $opsTiles[] = ['key' => 'reading', 'stat' => 'endeks_okuma', 'label' => 'Endeks Okuma', 'icon' => 'bx-tachometer', 'href' => 'index.php?p=puantaj/raporlar&tab=okuma', 'daily' => (int) ($dailyReadingTotal ?? 0), 'monthly' => (int) ($monthlyReadingTotal ?? 0), 'last' => $fmtLastUpdate($last_update_endeks), 'last_user' => $last_user_endeks ?: '-', 'update_key' => 'endeks'];
        $opsTiles[] = ['key' => 'cut_open', 'stat' => 'kesme_acma', 'label' => 'Kesme / Açma', 'icon' => 'bx-cut', 'href' => 'index.php?p=puantaj/raporlar&tab=kesme', 'daily' => (int) ($dailyWorkStats->kesme_acma ?? 0), 'monthly' => (int) ($monthlyWorkStats->kesme_acma ?? 0), 'last' => $fmtLastUpdate($last_update_isler), 'last_user' => $last_user_isler ?: '-', 'update_key' => 'isler'];
        $opsTiles[] = ['key' => 'meter_change', 'stat' => 'sayac_degisimi', 'label' => 'Sayaç Değişimi', 'icon' => 'bx-refresh', 'href' => 'index.php?p=puantaj/raporlar&tab=sokme_takma', 'daily' => (int) round((float) ($sayacDailyStats->sayac_degisimi ?? 0)), 'monthly' => (int) round((float) ($sayacMonthlyStats->sayac_degisimi ?? 0)), 'last' => $fmtLastUpdate($last_update_sayac), 'last_user' => $last_user_sayac ?: '-', 'update_key' => 'sayac'];
        $opsTiles[] = ['key' => 'sealing', 'stat' => 'muhurleme', 'label' => 'Mühürleme', 'icon' => 'bx-shield', 'href' => 'index.php?p=puantaj/raporlar&tab=muhurleme', 'daily' => (int) ($dailyWorkStats->muhurleme ?? 0), 'monthly' => (int) ($monthlyWorkStats->muhurleme ?? 0), 'last' => $fmtLastUpdate($last_update_isler), 'last_user' => $last_user_isler ?: '-', 'update_key' => 'isler'];
    }
    if ($canOpsKacak) {
        $opsTiles[] = ['key' => 'illegal', 'stat' => 'kacak', 'sub_stat' => 'kacak_onaylanan', 'sub_label' => 'Onaylanan', 'label' => 'Kaçak Kontrol', 'icon' => 'bx-error-circle', 'href' => 'index.php?p=kacak/list&tab=pane-ekip-ozet', 'daily' => (int) ($kacakDailyTotal->toplam ?? 0), 'monthly' => (int) ($kacakMonthlyTotal->toplam ?? 0), 'sub_daily' => (int) ($kacakDailyTotal->onaylanan ?? 0), 'sub_monthly' => (int) ($kacakMonthlyTotal->onaylanan ?? 0)];
    }
    if ($canOpsIhbar) {
        $opsTiles[] = ['key' => 'notice', 'stat' => 'ihbar', 'sub_stat' => 'ihbar_olumlu', 'sub_label' => 'Olumlu', 'label' => 'İhbar', 'icon' => 'bx-bell', 'href' => 'index.php?p=ihbar/list', 'daily' => (int) ($ihbarDailyTotal->toplam ?? 0), 'monthly' => (int) ($ihbarMonthlyTotal->toplam ?? 0), 'sub_daily' => (int) ($ihbarDailyTotal->olumlu ?? 0), 'sub_monthly' => (int) ($ihbarMonthlyTotal->olumlu ?? 0)];
    }
    $opsEsc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    if ($opsTiles !== []) {
    $opsDailyTotal = array_sum(array_column($opsTiles, 'daily'));
    ob_start(); ?>
    <div class="<?php echo getWidgetWidthClass('widget-is-operasyonlari', 'col-12'); ?> widget-item kpi-widget" id="widget-is-operasyonlari" style="<?php echo getWidgetStyle('widget-is-operasyonlari'); ?>">
        <div class="card border-0 h-100 stat-card ops-card">
            <div class="card-body d-flex flex-column">
                <div class="ops-head">
                    <div>
                        <span class="dash-kpi-label">İş Operasyonları · <span class="ops-total-period">Bugün</span></span>
                        <div class="dash-kpi-value"><span class="ops-total-num"><?php echo number_format($opsDailyTotal, 0, ',', '.'); ?></span><span class="dash-kpi-unit">adet</span></div>
                    </div>
                    <div class="ops-head-actions">
                        <div class="ops-mode-toggle" role="group" aria-label="Operasyon dönemi">
                            <button type="button" class="ops-mode-btn is-active" data-mode="daily" aria-pressed="true">Gün</button>
                            <button type="button" class="ops-mode-btn" data-mode="monthly" aria-pressed="false">Ay</button>
                            <?php if ($canOpsReports || $canOpsKacak): ?>
                                <button type="button" class="ops-mode-btn" data-mode="contract" aria-pressed="false" title="Sözleşme dönemi toplamı, kalan ve gerçekleşme oranı">Dönem</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($canOpsReports): ?>
                            <button type="button" class="ops-sync-all" title="Tüm günlük operasyonları online sorgula (API)" aria-label="Tüm günlük operasyonları online sorgula">
                                <i class="bx bx-refresh"></i><span>Tümünü Sorgula</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ops-contract-note">Bugün yapılan işlerin dağılımı</div>
                <div class="ops-bar" role="img" aria-label="Operasyon dağılımı">
                    <?php foreach ($opsTiles as $tile): ?>
                        <span class="ops-bar-seg ops-c--<?php echo $opsEsc($tile['key']); ?>" data-key="<?php echo $opsEsc($tile['key']); ?>" style="width: <?php echo $opsDailyTotal > 0 ? round($tile['daily'] / $opsDailyTotal * 100, 2) : 0; ?>%;" title="<?php echo $opsEsc($tile['label']); ?>"></span>
                    <?php endforeach; ?>
                    <span class="ops-bar-track d-none"><span class="ops-bar-contract" style="width: 0%;"></span></span>
                </div>
                <div class="ops-items">
                    <?php foreach ($opsTiles as $tile): ?>
                        <a class="ops-item ops-c--<?php echo $opsEsc($tile['key']); ?>" href="<?php echo $opsEsc($tile['href']); ?>" data-key="<?php echo $opsEsc($tile['key']); ?>" data-stat="<?php echo $opsEsc($tile['stat']); ?>">
                            <div class="dash-kpi-header">
                                <div class="min-w-0" style="min-width:0;">
                                    <span class="dash-kpi-label"><?php echo $opsEsc($tile['label']); ?></span>
                                    <div class="dash-kpi-value"><span class="ops-num" data-daily="<?php echo (int) $tile['daily']; ?>" data-monthly="<?php echo (int) $tile['monthly']; ?>"><?php echo number_format((int) $tile['daily'], 0, ',', '.'); ?></span></div>
                                </div>
                                <div class="dash-kpi-icon"><i class="bx <?php echo $opsEsc($tile['icon']); ?>"></i></div>
                            </div>
                            <div class="ops-contract d-none">
                                <span class="ops-mini-progress"><span class="ops-mini-fill"></span></span>
                            </div>
                            <div class="dash-kpi-footer">
                                <?php if (isset($tile['sub_stat'])): ?>
                                    <span class="dash-kpi-note ops-sub" data-sub-stat="<?php echo $opsEsc($tile['sub_stat']); ?>"><i class="bx bx-check-circle"></i> <?php echo $opsEsc($tile['sub_label']); ?>: <b class="ops-sub-num" data-daily="<?php echo (int) $tile['sub_daily']; ?>" data-monthly="<?php echo (int) $tile['sub_monthly']; ?>"><?php echo number_format((int) $tile['sub_daily'], 0, ',', '.'); ?></b></span>
                                <?php elseif (isset($tile['update_key'])): ?>
                                    <span class="dash-kpi-note ops-last" data-update-key="<?php echo $opsEsc($tile['update_key']); ?>" title="Son güncelleme / yapan"><i class="bx bx-time-five"></i> <span class="last-update-value"><?php echo $opsEsc($tile['last']); ?></span> · <span class="last-update-user-value"><?php echo $opsEsc($tile['last_user']); ?></span></span>
                                <?php else: ?>
                                    <span class="dash-kpi-note"></span>
                                <?php endif; ?>
                                <span class="dash-kpi-note ops-contract-text d-none"></span>
                                <span class="dash-badge-soft ops-rate d-none"></span>
                                <i class="bx bx-right-arrow-alt ops-go" aria-hidden="true"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-is-operasyonlari'] = ob_get_clean();
    }

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

    ob_start(); ?>
        <div class="<?php echo getWidgetWidthClass('widget-kesme-karsilastirma', 'col-12'); ?> widget-item"
            id="widget-kesme-karsilastirma" style="<?php echo getWidgetStyle('widget-kesme-karsilastirma'); ?>">
            <div class="card summary-card comparison-dashboard-card">
                <div class="card-header align-items-center d-flex flex-wrap gap-2">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2"><i class="bx bx-grid-vertical drag-handle"></i><i class="bx bx-power-off text-danger"></i>Kesme/Açma Karşılaştırması</h5>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <span id="kesmeCompGunBadge" class="badge bg-danger-subtle text-danger d-none"><i class="bx bx-calendar-event me-1"></i>Ayın 1'i - <span id="kesmeCompGunNo"></span>'ı arası</span>
                        <div class="btn-group btn-group-sm" id="kesmeCompTypeToggle" role="group" aria-label="Sonuç Türü">
                            <button type="button" class="btn btn-outline-secondary" data-type="all">Tümü</button>
                            <button type="button" class="btn btn-outline-success active" data-type="olumlu"><i class="bx bx-check-circle me-1"></i>Olumlu</button>
                            <button type="button" class="btn btn-outline-danger" data-type="olumsuz"><i class="bx bx-x-circle me-1"></i>Olumsuz</button>
                        </div>
                        <div class="btn-group btn-group-sm" id="kesmeCompViewToggle">
                            <button type="button" class="btn btn-outline-danger active" data-view="bolge"><i class="bx bx-map-alt me-1"></i>Bölge</button>
                            <button type="button" class="btn btn-outline-danger" data-view="personel"><i class="bx bx-user me-1"></i>Personel</button>
                        </div>
                        <?php echo getWidthControl(); ?>
                    </div>
                </div>
                <div class="card-body p-0" style="min-height:<?php echo getWidgetHeight('widget-kesme-karsilastirma', 'auto'); ?>;">
                    <div id="kesmeCompLoading" class="p-3"><div class="dashboard-skeleton-table"><div class="skeleton-line w-35 mb-3"></div><div class="skeleton-line w-100 mb-2"></div><div class="skeleton-line w-100 mb-2"></div><div class="skeleton-line w-90"></div></div></div>
                    <div id="kesmeCompContent" style="display:none"><div id="kesmeCompBolge"></div><div id="kesmeCompPersonel" style="display:none"></div></div>
                    <div id="kesmeCompEmpty" class="text-center py-5" style="display:none"><i class="bx bx-power-off text-muted" style="font-size:45px;opacity:.25"></i><p class="text-muted mt-2 mb-0">Karşılaştırma verisi bulunamadı.</p></div>
                </div>
            </div>
        </div>
    <?php $widgets['widget-kesme-karsilastirma'] = ob_get_clean();

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

            foreach (array_keys($widgets) as $widgetKey) {
                if (!homeWidgetAllowed((string) $widgetKey)) {
                    unset($widgets[$widgetKey]);
                }
            }
        
            // Sıralamayı DB öncelikli, çerez yedekli akıştan kullan
            $render_order = !empty($render_order) ? $render_order : array_keys($widgets);

            $legacyOpsWidgets = ['widget-gunluk-endeks-okuma', 'widget-gunluk-kesme-acma', 'widget-gunluk-sayac-degisimi', 'widget-gunluk-muhurleme', 'widget-kacak-sayisi', 'widget-ihbar-sayisi'];
            if (!in_array('widget-is-operasyonlari', $render_order, true)) {
                foreach ($render_order as $orderIndex => $orderId) {
                    if (in_array($orderId, $legacyOpsWidgets, true)) {
                        $render_order[$orderIndex] = 'widget-is-operasyonlari';
                        break;
                    }
                }
            }
            $render_order = array_values(array_diff($render_order, $legacyOpsWidgets));

            $render_order = array_values(array_diff($render_order, ['widget-ana-slider', 'widget-takvim-alarmlar', 'widget-gorev-evrak-takvimi', 'widget-dogum-gunleri', 'widget-talep-nobet-ozet', 'widget-personel-ozet', 'widget-arac-ozet']));
            ?>

        <div class="container-fluid">

            <?php
            if (Gate::allows('evrak-takip/list')) {
                $uetsOkunmamis = (new \App\Model\EvrakSevkModel())->unreadUets((int) $firmaId, (int) $userId, 5);
                if ($uetsOkunmamis['count'] > 0): ?>
                    <div class="alert alert-danger border-danger shadow-sm d-flex flex-wrap align-items-start gap-3 mb-3" role="alert" id="uetsOkunmamisUyari">
                        <div class="flex-shrink-0 rounded-3 bg-danger text-white d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="bx bx-envelope fs-4"></i></div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">Okunmamış UETS Tebligatı: <?= (int) $uetsOkunmamis['count']; ?></h6>
                            <ul class="mb-2 ps-3 font-size-13">
                                <?php foreach ($uetsOkunmamis['items'] as $uetsItem): ?>
                                    <li><strong><?= htmlspecialchars((string) $uetsItem->kurum_adi, ENT_QUOTES, 'UTF-8'); ?></strong> — <?= htmlspecialchars(mb_substr((string) $uetsItem->konu, 0, 90), ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted">(<?= htmlspecialchars(date('d.m.Y', strtotime((string) $uetsItem->tarih)), ENT_QUOTES, 'UTF-8'); ?>)</span></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="index?p=evrak-takip/list" class="btn btn-sm btn-danger">Evrak Takip'e Git</a>
                        </div>
                    </div>
                <?php endif;
            }
            ?>

            <style id="dashboard-skeleton-critical">
                #dashboard-page-content.dashboard-content-pending { height: 0; overflow: hidden; visibility: hidden; }
                /* Widget visibility */
                .widget-hidden {
                    display: none !important;
                }

                /* Widget collapsed state */
                #dashboard-widgets .widget-collapsed .card > *:not(.mac-title-bar) {
                    display: none !important;
                }
                #dashboard-widgets .widget-collapsed {
                    height: 40px !important;
                    min-height: 40px !important;
                    overflow: hidden !important;
                }
                #dashboard-widgets .widget-collapsed .card,
                #dashboard-widgets .widget-collapsed .carousel {
                    height: 40px !important;
                    min-height: 40px !important;
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
                /* Grid bazlı boyutlandırma (normal mod) */
                #dashboard-widgets .widget-item .grid-resize-grip {
                    position: absolute !important;
                    left: auto !important;
                    top: auto !important;
                    right: 4px;
                    bottom: 4px;
                    width: 16px !important;
                    height: 16px !important;
                    min-height: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: 0;
                    border-radius: 0 0 8px 0;
                    background: linear-gradient(135deg, transparent 0 48%, currentColor 48% 58%, transparent 58% 72%, currentColor 72% 82%, transparent 82%);
                    color: rgba(100, 116, 139, .55);
                    cursor: nwse-resize;
                    z-index: 20;
                    display: none;
                    touch-action: none;
                    opacity: 0;
                    transition: opacity .15s ease, color .15s ease;
                }
                [data-bs-theme="dark"] #dashboard-widgets .widget-item .grid-resize-grip { color: rgba(203, 213, 225, .6); }
                #dashboard-widgets:not(.free-layout-active) > .widget-item { position: relative; }
                #dashboard-widgets:not(.free-layout-active) {
                    overflow-anchor: none;
                    display: grid !important;
                    grid-template-columns: repeat(12, minmax(0, 1fr));
                    grid-auto-rows: 8px;
                    grid-auto-flow: row dense;
                    align-items: stretch;
                }
                #dashboard-widgets:not(.free-layout-active) { --bs-gutter-x: 12px; --bs-gutter-y: 0; }
                #dashboard-widgets:not(.free-layout-active) > .widget-item { margin-bottom: 12px !important; }
                #dashboard-widgets:not(.free-layout-active) > .widget-item > .card, #dashboard-widgets:not(.free-layout-active) > .widget-item > .carousel { height: 100%; margin-bottom: 0; }
                #dashboard-widgets:not(.free-layout-active) > .widget-item {
                    width: auto;
                    max-width: none;
                    flex: none;
                    min-width: 0;
                    grid-column: span 12;
                }
<?php
$gridBreakpoints = ['' => 0, 'sm-' => 576, 'md-' => 768, 'lg-' => 992, 'xl-' => 1200, 'xxl-' => 1400];
foreach ($gridBreakpoints as $bpName => $bpMin) {
    if ($bpMin > 0) {
        echo "                @media (min-width: {$bpMin}px) {\n";
    }
    for ($gi = 1; $gi <= 12; $gi++) {
        echo "                #dashboard-widgets:not(.free-layout-active) > .widget-item.col-{$bpName}{$gi} { grid-column: span {$gi}; }\n";
    }
    if ($bpMin > 0) {
        echo "                }\n";
    }
}
$pinGroups = [768 => ['', 'sm-', 'md-'], 992 => ['lg-'], 1200 => ['xl-'], 1400 => ['xxl-']];
foreach ($pinGroups as $pinMin => $pinNames) {
    echo "                @media (min-width: {$pinMin}px) {\n";
    foreach ($pinNames as $pinName) {
        for ($gi = 1; $gi <= 12; $gi++) {
            echo "                #dashboard-widgets:not(.free-layout-active) > .widget-item.col-{$pinName}{$gi}[data-grid-x] { grid-column: var(--gx) / span {$gi}; grid-row-start: var(--gy); }\n";
        }
    }
    echo "                }\n";
}
?>
                #dashboard-widgets .widget-item:hover .grid-resize-grip { opacity: 1; }
                #dashboard-widgets .widget-item .grid-resize-grip:hover,
                #dashboard-widgets .widget-item.grid-resizing .grid-resize-grip { opacity: 1; color: rgba(37, 99, 235, .95); }
                @media (min-width: 768px) {
                    #dashboard-widgets:not(.free-layout-active) .widget-item .grid-resize-grip { display: block; }
                }
                body.grid-dragging-active { user-select: none; cursor: grabbing; }
                #dashboard-widgets:not(.free-layout-active) > .widget-item { -webkit-user-drag: none; }
                #dashboard-widgets .widget-item.grid-dragging {
                    z-index: 1050;
                    pointer-events: none;
                    opacity: .92;
                    transition: none !important;
                    filter: drop-shadow(0 18px 28px rgba(15, 23, 42, .28));
                }
                #dashboard-widgets .grid-drop-preview {
                    position: absolute;
                    z-index: 5;
                    pointer-events: none;
                    border: 2px dashed rgba(37, 99, 235, .75);
                    background: rgba(37, 99, 235, .08);
                    border-radius: 14px;
                    transition: left .08s ease, top .08s ease, width .08s ease, height .08s ease;
                }
                #dashboard-widgets .widget-item.grid-resizing { user-select: none; }
                #dashboard-widgets .widget-item.grid-resizing::after {
                    content: attr(data-grid-label);
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -50%);
                    padding: 6px 14px;
                    border-radius: 999px;
                    background: rgba(15, 23, 42, .85);
                    color: #fff;
                    font-size: 12px;
                    font-weight: 700;
                    z-index: 30;
                    pointer-events: none;
                }
                #dashboard-widgets .widget-item.grid-sized .carousel-content { min-height: 0 !important; }
                #dashboard-widgets .widget-item.grid-sized > .card,
                #dashboard-widgets .widget-item.grid-sized > .carousel { height: 100% !important; max-height: none !important; }
                #dashboard-widgets .widget-item > .card,
                #dashboard-widgets .widget-item > .carousel { position: relative; }
                #dashboard-widgets .widget-item.grid-sized > .card > .card-body { overflow: auto; }

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

                /* Dark mode compatibility for macOS Widget Title Bars */
                [data-bs-theme="dark"] .mac-title-bar {
                    background: linear-gradient(180deg, #2e3548 0%, #222736 100%) !important;
                    border-bottom: 1px solid #2e3548 !important;
                }
                [data-bs-theme="dark"] .widget-item:hover .mac-title-bar {
                    background: linear-gradient(180deg, #32394e 0%, #272d3f 100%) !important;
                }
                [data-bs-theme="dark"] #dashboard-widgets .card,
                [data-bs-theme="dark"] #dashboard-widgets .carousel {
                    border-color: #46515d !important;
                }
                [data-bs-theme="dark"] .mac-title-text {
                    color: #a6b0cf !important;
                }
                [data-bs-theme="dark"] .drag-handle-indicator {
                    color: #74788d !important;
                }
                [data-bs-theme="dark"] .finder-tabs-nav .nav-link.active,
                [data-bs-theme="dark"] .finder-tabs-nav .nav-link.active * {
                    color: #f6f6f6 !important;
                }
                [data-bs-theme="dark"] .finder-tabs-nav .nav-link.active {
                    background: linear-gradient(180deg, #2e3548 0%, #222736 100%) !important;
                    border-color: rgba(255,255,255,0.1) !important;
                    box-shadow: none !important;
                }
                [data-bs-theme="dark"] .finder-tabs-nav .nav-link.active .badge {
                    background: #32394e !important;
                    color: #cbd5e1 !important;
                }
                [data-bs-theme="dark"] .finder-tabs-nav .nav-link:not(.active):hover,
                [data-bs-theme="dark"] .finder-tabs-nav .nav-link:not(.active):hover * {
                    background: rgba(255,255,255,0.05) !important;
                    color: #ffffff !important;
                }

                /* Home Page Widgets Dark Mode Compatibility Overrides */
                [data-bs-theme="dark"] .card.summary-card,
                [data-bs-theme="dark"] .widget-item .card.summary-card,
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .card {
                    background: var(--bs-card-bg, #222830) !important;
                    border-color: var(--bs-card-border-color, #2b333e) !important;
                    box-shadow: 0 4px 15px -3px rgba(0,0,0,0.3) !important;
                }
                #widget-endeks-karsilastirma .endeks-overview {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(150px, 1fr));
                    gap: 10px;
                    padding: 14px;
                    background: linear-gradient(135deg, #f8fafc 0%, #f5f7ff 100%);
                    border-bottom: 1px solid #e9edf5;
                }
                #widget-endeks-karsilastirma .endeks-kpi {
                    position: relative;
                    overflow: hidden;
                    min-height: 82px;
                    padding: 13px 14px;
                    background: rgba(255,255,255,.92);
                    border: 1px solid #e7eaf2;
                    border-radius: 12px;
                    box-shadow: 0 7px 22px -18px rgba(15,23,42,.65);
                }
                #widget-endeks-karsilastirma .endeks-kpi.is-actionable { cursor:pointer; transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
                #widget-endeks-karsilastirma .endeks-kpi.is-actionable:hover { transform:translateY(-3px); border-color:#a9adf5; box-shadow:0 15px 30px -20px rgba(79,70,229,.75); }
                #widget-endeks-karsilastirma .endeks-kpi.is-actionable:focus-visible { outline:3px solid rgba(99,102,241,.24); outline-offset:2px; }
                #widget-endeks-karsilastirma .endeks-kpi::after {
                    content: '';
                    position: absolute;
                    right: -18px;
                    bottom: -24px;
                    width: 72px;
                    height: 72px;
                    border-radius: 50%;
                    background: var(--kpi-accent, #6366f1);
                    opacity: .07;
                }
                #widget-endeks-karsilastirma .endeks-kpi-label { color:#64748b; font-size:10px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
                #widget-endeks-karsilastirma .endeks-kpi-value { margin-top:4px; color:#172033; font-size:21px; line-height:1.1; font-weight:800; }
                #widget-endeks-karsilastirma .endeks-kpi-note { margin-top:6px; color:#8b96a8; font-size:10px; }
                #widget-endeks-karsilastirma .endeks-cell-meta { margin-top:4px; color:#94a3b8; font-size:9px; line-height:1; }
                #widget-endeks-karsilastirma .endeks-absolute-diff { display:block; margin-top:4px; color:#8994a6; font-size:9px; font-weight:600; }
                #widget-endeks-karsilastirma .endeks-summary-strip { border-bottom:1px solid #eef1f6; background:#fff; }
                #widget-endeks-karsilastirma .endeks-table-wrap { scrollbar-color:#cbd5e1 transparent; scrollbar-width:thin; }
                #widget-endeks-karsilastirma .endeks-table-head th {
                    background:#253247 !important;
                    background-image:none !important;
                    color:#f8fafc !important;
                    border-color:#344258 !important;
                    text-shadow:0 1px 1px rgba(0,0,0,.18);
                }
                #widget-endeks-karsilastirma .endeks-table-head th.is-current-period {
                    background:#4f55a7 !important;
                    color:#ffffff !important;
                    box-shadow:inset 3px 0 0 rgba(255,255,255,.08), inset -3px 0 0 rgba(255,255,255,.08);
                }
                @media (max-width: 991.98px) {
                    #widget-endeks-karsilastirma .endeks-overview { grid-template-columns:repeat(2, minmax(140px, 1fr)); }
                }
                @media (max-width: 575.98px) {
                    #widget-endeks-karsilastirma .endeks-overview { grid-template-columns:1fr; padding:10px; }
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-overview,
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-summary-strip { background:linear-gradient(135deg,#202634,#1b202c); border-color:#32394e; }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-kpi { background:#272e3d; border-color:#364052; }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-kpi-value { color:#f2f5fa; }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-kpi-label { color:#aab4c7; }
                #widget-kesme-karsilastirma .comparison-dashboard-card { border:1px solid rgba(226,232,240,.85); border-radius:12px; overflow:hidden; box-shadow:0 4px 15px -5px rgba(15,23,42,.12); }
                #widget-kesme-karsilastirma .kesme-overview { display:grid; grid-template-columns:repeat(4,minmax(150px,1fr)); gap:10px; padding:14px; background:linear-gradient(135deg,#fff8f8,#f8fafc); border-bottom:1px solid #edf0f5; }
                #widget-kesme-karsilastirma .kesme-kpi { padding:13px 14px; min-height:82px; background:#fff; border:1px solid #e7eaf1; border-radius:12px; box-shadow:0 7px 22px -18px rgba(15,23,42,.65); }
                #widget-kesme-karsilastirma .kesme-kpi.is-actionable { cursor:pointer; transition:.2s ease; }
                #widget-kesme-karsilastirma .kesme-kpi.is-actionable:hover { transform:translateY(-3px); border-color:#fda4af; box-shadow:0 15px 30px -20px rgba(225,29,72,.65); }
                #widget-kesme-karsilastirma .kesme-kpi-label { color:#64748b;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase; }
                #widget-kesme-karsilastirma .kesme-kpi-value { margin-top:4px;color:#172033;font-size:21px;line-height:1.1;font-weight:800; }
                #widget-kesme-karsilastirma .kesme-kpi-note { margin-top:6px;color:#8b96a8;font-size:10px; }
                #widget-kesme-karsilastirma .kesme-table-head th { background:#3b2935 !important;background-image:none !important;color:#fff !important;border-color:#503747 !important; }
                #widget-kesme-karsilastirma .kesme-table-head th.is-current-period { background:#be123c !important; }
                #widget-kesme-karsilastirma .kesme-bar { height:4px;background:#e8edf3;border-radius:5px;overflow:hidden;margin:5px auto 0;width:62px; }
                #widget-kesme-karsilastirma .kesme-bar span { display:block;height:100%;border-radius:5px;background:#fb7185; }
                @media(max-width:991.98px){#widget-kesme-karsilastirma .kesme-overview{grid-template-columns:repeat(2,minmax(140px,1fr));}}
                @media(max-width:575.98px){#widget-kesme-karsilastirma .kesme-overview{grid-template-columns:1fr;}}
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .comparison-dashboard-card,
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .kesme-kpi { background:#252c39;border-color:#374151; }
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .kesme-overview { background:#1d232f;border-color:#323b49; }
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .kesme-kpi-value { color:#f8fafc; }
                #dashboard-widgets .card.dash-kpi-card { position:relative; background:#fff; border:1px solid #e2e8f0 !important; border-radius:12px !important; box-shadow:0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.02) !important; transition:transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s cubic-bezier(.4,0,.2,1), border-color .25s ease; }
                #dashboard-widgets .card.dash-kpi-card:hover { transform:translateY(-3px); box-shadow:0 10px 20px -5px rgba(15,23,42,.08), 0 4px 6px -2px rgba(15,23,42,.03) !important; border-color:#cbd5e1 !important; }
                .dash-kpi-body { padding:20px 22px !important; display:flex; flex-direction:column; height:100%; }
                .dash-kpi-trio { display:grid !important; grid-template-columns:repeat(3, minmax(0,1fr)); gap:0; padding:0 !important; }
                .dash-kpi-trio-col { display:flex; flex-direction:column; min-width:0; padding:18px 16px; }
                .dash-kpi-trio .dash-kpi-label { font-size:12px; }
                .dash-kpi-trio .dash-kpi-icon { width:36px; height:36px; flex:0 0 36px; font-size:18px; }
                .dash-kpi-trio .dash-kpi-note { white-space:normal; overflow:visible; text-overflow:clip; }
                .dash-kpi-trio .dash-kpi-footer { flex-wrap:wrap; }
                .dash-kpi-trio-col + .dash-kpi-trio-col { border-left:1px solid #f1f5f9; }
                @media (max-width: 767.98px) { .dash-kpi-trio { grid-template-columns:1fr; } .dash-kpi-trio-col + .dash-kpi-trio-col { border-left:0; border-top:1px solid #f1f5f9; } }
                [data-bs-theme="dark"] .dash-kpi-trio-col + .dash-kpi-trio-col { border-color:#3a4256; }
                .dash-kpi-header { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:12px; }
                .dash-kpi-label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.03em; line-height:1.25; }
                .dash-kpi-value { font-size:26px; font-weight:800; color:#0f172a; line-height:1.15; letter-spacing:-.03em; font-feature-settings:"tnum"; }
                .dash-kpi-unit { margin-left:6px; font-size:13px; font-weight:500; color:#94a3b8; letter-spacing:0; }
                .dash-kpi-icon { width:44px; height:44px; flex:0 0 44px; display:flex; align-items:center; justify-content:center; border-radius:10px; font-size:21px; transition:transform .2s ease; }
                .dash-kpi-card:hover .dash-kpi-icon { transform:scale(1.08); }
                .dash-kpi-icon.icon-blue { background:#eff6ff; color:#2563eb; }
                .dash-kpi-icon.icon-emerald { background:#ecfdf5; color:#059669; }
                .dash-kpi-icon.icon-amber { background:#fffbeb; color:#d97706; }
                .dash-kpi-icon.icon-purple { background:#f5f3ff; color:#7c3aed; }
                .dash-kpi-icon.icon-cyan { background:#ecfeff; color:#0891b2; }
                .dash-kpi-icon.icon-rose { background:#fff1f2; color:#e11d48; }
                .dash-kpi-footer { margin-top:auto; padding-top:12px; border-top:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; color:#64748b; min-height:38px; }
                .dash-kpi-note { font-size:11.5px; font-weight:500; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
                .dash-badge-soft { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; white-space:nowrap; }
                .dash-badge-soft.soft-blue { background:#eff6ff; color:#1d4ed8; }
                .dash-badge-soft.soft-emerald { background:#ecfdf5; color:#047857; }
                .dash-badge-soft.soft-amber { background:#fffbeb; color:#b45309; }
                .dash-badge-soft.soft-purple { background:#f5f3ff; color:#6d28d9; }
                .dash-badge-soft.soft-rose { background:#fff1f2; color:#be123c; }
                .dash-badge-soft.soft-slate { background:#f1f5f9; color:#475569; }
                .dash-card-link { display:inline-flex; align-items:center; gap:2px; font-size:12px; font-weight:600; color:#3b82f6; text-decoration:none !important; white-space:nowrap; transition:color .15s ease; }
                .dash-card-link:hover { color:#1d4ed8; text-decoration:underline !important; }
                .dash-kpi-seg { display:flex; gap:3px; height:8px; margin:4px 0 14px; }
                .dash-kpi-seg > span { display:block; height:100%; border-radius:999px; min-width:0; transition:width .5s ease; }
                .dash-kpi-legend { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px 14px; margin-bottom:14px; }
                .dash-kpi-legend-item { display:flex; flex-direction:column; min-width:0; }
                .dash-kpi-legend-label { display:flex; align-items:center; gap:6px; font-size:11.5px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .dash-kpi-legend-label::before { content:''; flex:0 0 8px; width:8px; height:8px; border-radius:50%; background:var(--dot, #94a3b8); }
                .dash-kpi-legend-value { padding-left:14px; font-size:15px; font-weight:700; color:#1e293b; font-feature-settings:"tnum"; }
                .dash-kpi-legend.cols-3 { grid-template-columns:repeat(3, minmax(0,1fr)); }
                .dash-kpi-people { display:flex; flex-direction:column; gap:8px; max-height:112px; overflow-y:auto; margin-bottom:12px; }
                .dash-kpi-person { display:flex; align-items:center; gap:8px; min-width:0; }
                .dash-kpi-person img { width:28px; height:28px; border-radius:50%; object-fit:cover; flex:0 0 28px; }
                .dash-kpi-person-name { font-size:12.5px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .dash-kpi-person-sub { font-size:11px; color:#94a3b8; }
                .dash-kpi-person a { color:#64748b; font-size:16px; }
                .dash-kpi-person a:hover { color:#2563eb; }

                #dashboard-widgets .mac-title-bar { height:40px !important; min-height:40px !important; background:#fff !important; border-bottom:1px solid #f1f5f9 !important; padding:0 10px 0 12px !important; }
                #dashboard-widgets .widget-item:hover .mac-title-bar { background:#fff !important; }
                #dashboard-widgets .mac-title-text { order:2; text-align:left !important; text-transform:none !important; font-size:14px !important; font-weight:700 !important; color:#1e293b !important; letter-spacing:-.005em !important; padding:0 8px !important; }
                #dashboard-widgets .mac-controls { order:3; justify-content:flex-end; width:auto !important; }
                #dashboard-widgets .drag-handle-indicator { order:1; width:auto !important; justify-content:flex-start !important; font-size:.95rem !important; }
                #dashboard-widgets .mac-control { background:#e2e8f0 !important; box-shadow:none !important; transition:background .15s ease, transform .1s ease !important; }
                #dashboard-widgets .widget-item:hover .mac-close { background:#ff5f57 !important; }
                #dashboard-widgets .widget-item:hover .mac-minimize { background:#febc2e !important; }
                #dashboard-widgets .widget-item:hover .mac-maximize { background:#28c840 !important; }

                #dashboard-widgets .kpi-widget .mac-title-bar { position:absolute !important; top:6px; left:50%; transform:translateX(-50%); width:auto !important; height:20px !important; min-height:20px !important; padding:0 8px !important; gap:8px; border:1px solid #e2e8f0 !important; border-radius:999px !important; box-shadow:0 2px 6px rgba(15,23,42,.08); opacity:0; pointer-events:none; transition:opacity .15s ease !important; z-index:20 !important; }
                #dashboard-widgets .kpi-widget:hover .mac-title-bar { opacity:1; pointer-events:auto; }
                #dashboard-widgets .kpi-widget .mac-title-text { display:none !important; }
                #dashboard-widgets .kpi-widget.widget-collapsed .mac-title-bar { opacity:1; pointer-events:auto; }

                .dash-hero { display:flex; justify-content:space-between; align-items:center; gap:24px; flex-wrap:wrap; margin-bottom:12px; padding:22px 26px; background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.02); }
                .dash-hero-main { min-width:0; }
                .dash-hero-chips { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
                .dash-hero-chip { display:inline-flex; align-items:center; gap:6px; height:30px; padding:0 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#475569; font-size:12.5px; font-weight:600; }
                .dash-hero-chip i { font-size:15px; color:#64748b; }
                .dash-hero .dashboard-settings-toolbar > .dropdown > .btn { height:30px; padding:0 12px !important; border:1px solid #e2e8f0 !important; border-radius:8px !important; background:#fff !important; color:#475569 !important; font-size:12.5px; font-weight:600 !important; box-shadow:none !important; backdrop-filter:none; }
                .dash-hero .dashboard-settings-toolbar > .dropdown > .btn:hover,
                .dash-hero .dashboard-settings-toolbar > .dropdown > .btn.show { border-color:#cbd5e1 !important; background:#f8fafc !important; color:#1e293b !important; }
                .dash-hero .dashboard-settings-toolbar > .dropdown > .btn i { font-size:16px !important; color:#64748b; }
                .dash-hero-title { margin:0 0 4px; font-size:24px; font-weight:800; color:#0f172a; letter-spacing:-.02em; line-height:1.2; }
                .dash-hero-wave { display:inline-block; transform-origin:70% 70%; animation:dashWave 2.4s ease-in-out 1; }
                @keyframes dashWave { 0%,60%,100% { transform:rotate(0); } 10%,30% { transform:rotate(14deg); } 20% { transform:rotate(-8deg); } 40% { transform:rotate(-4deg); } 50% { transform:rotate(10deg); } }
                @media (prefers-reduced-motion: reduce) { .dash-hero-wave { animation:none; } }
                .dash-hero-sub { margin:0; font-size:13.5px; color:#64748b; }
                .pa-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(15,23,42,.06) !important; }
                .pa-body { padding:18px 22px !important; display:flex; flex-direction:column; gap:14px; }
                .pa-divider { height:1px; background:#e2e8f0; }
                .pa-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
                .pa-title { display:flex; align-items:baseline; gap:10px; min-width:0; }
                .pa-icon { align-self:center; width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:17px; }
                .pa-icon.tone-blue, .pa-badge.tone-blue { background:#eff6ff; color:#2563eb; }
                .pa-icon.tone-purple, .pa-badge.tone-purple { background:#f5f3ff; color:#7c3aed; }
                .pa-label { font-size:12px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#64748b; }
                .pa-total { font-size:22px; font-weight:700; color:#0f172a; line-height:1; }
                .pa-unit { font-size:12.5px; color:#94a3b8; }
                .pa-extra { margin-left:10px; padding-left:14px; border-left:1px solid #e2e8f0; }
                .pa-actions { display:flex; align-items:center; gap:12px; }
                .pa-badge { font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:6px; }
                .pa-bar { position:relative; display:flex; gap:3px; height:8px; border-radius:6px; overflow:hidden; background:#f1f5f9; }
                .pa-bar-overlay { position:absolute; left:0; top:0; height:100% !important; opacity:.9; pointer-events:auto; background:repeating-linear-gradient(135deg, #e11d48 0 2px, rgba(225,29,72,.12) 2px 5px) !important; }
                .pa-bar span { display:block; height:100%; border-radius:6px; }
                .pa-bar span.pa-bar-overlay { position:absolute; }
                .pa-legend { display:flex; flex-wrap:wrap; gap:6px 28px; margin-top:10px; }
                .pa-legend-sep { width:1px; align-self:stretch; background:#e2e8f0; margin:2px 0; }
                .pa-legend-item { display:inline-flex; align-items:center; gap:7px; font-size:12.5px; color:#64748b; }
                .pa-legend-item i { width:8px; height:8px; border-radius:50%; background:var(--dot); }
                .pa-legend-item b { font-size:14px; font-weight:700; color:#0f172a; }
                [data-bs-theme="dark"] .pa-card { background:#2a3042; }
                [data-bs-theme="dark"] .pa-divider { background:#3a4256; }
                [data-bs-theme="dark"] .pa-total, [data-bs-theme="dark"] .pa-legend-item b { color:#f8fafc; }
                [data-bs-theme="dark"] .pa-bar { background:#232838; }
                .dash-size-row { display:flex; gap:6px; padding:0 8px 8px 34px; }
                .dash-size-row select { flex:1; min-width:0; height:28px; padding:0 6px; font-size:11.5px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#334155; }
                #dashboard-widgets .widget-item .grid-resize-grip { display:none !important; }
                .dash-slider { margin-bottom:12px; width:100%; flex:0 0 100%; max-width:100%; }
                .dash-slider .carousel { width:100%; }
                .dash-slider .carousel-inner { border-radius:12px; box-shadow:0 1px 4px rgba(15,23,42,.06) !important; }
                .dash-slider .carousel-content { min-height:64px; padding:12px 56px !important; }
                .dash-slider .carousel-content { filter:saturate(.62) brightness(1.1); opacity:.92; }
                .dash-slider .carousel-content h5 { font-weight:600 !important; }
                .dash-slider .carousel-content p { opacity:.9 !important; color:rgba(255,255,255,.88) !important; }
                .dash-slider .carousel-content > .flex-shrink-0 i.bx { opacity:.55; }
                .dash-slider .carousel-content > .flex-grow-1 { display:flex; align-items:baseline; flex-wrap:wrap; gap:4px 14px; padding-right:12px !important; }
                .dash-slider .carousel-content h5 { font-size:1rem !important; margin:0 !important; white-space:nowrap; }
                .dash-slider .carousel-content p { max-width:none !important; font-size:.875rem !important; margin:0 !important; }
                .dash-slider .carousel-content > .flex-shrink-0 i.bx { font-size:34px !important; }
                .dash-slider .carousel-content p br { display:none; }
                .dash-slider .carousel-content p small { margin:0 0 0 10px !important; font-size:.75rem; }
                .dash-slider .carousel-content p small i.bx { font-size:.95rem !important; opacity:1; vertical-align:-2px; }
                .dash-slider .carousel-indicators { margin-bottom:.2rem; }
                .dash-slider .carousel-indicators [data-bs-target] { width:6px !important; height:6px !important; }
                .dash-slider .carousel-control-prev-icon, .dash-slider .carousel-control-next-icon { width:1rem !important; height:1rem !important; }
                .dash-hero-cal { flex:1 0 100%; min-width:0; border-top:1px solid #e2e8f0; padding-top:14px; }
                .hcal-loading { padding:18px 0; text-align:center; font-size:12.5px; color:#64748b; }
                .hcal-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
                .hcal-nav, .hcal-meta { display:flex; align-items:center; gap:8px; }
                .hcal-title { display:inline-flex; align-items:center; gap:6px; min-width:140px; justify-content:center; font-size:14px; font-weight:700; color:#1e293b; }
                .hcal-title i { color:#64748b; font-size:17px; }
                .hcal-nav-btn, .hcal-today-btn, .hcal-reset { border:1px solid #e2e8f0; background:#fff; color:#475569; border-radius:8px; height:30px; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; cursor:pointer; padding:0 10px; transition:background .15s, border-color .15s; }
                .hcal-nav-btn { width:30px; padding:0; font-size:18px; }
                .hcal-nav-btn:hover, .hcal-today-btn:hover, .hcal-reset:hover { background:#f1f5f9; border-color:#cbd5e1; }
                .hcal-filters { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; }
                .hcal-chip.chip-all { color:#475569; background:#f1f5f9; border-color:#cbd5e1; }
                .hcal-chip.chip-all b { background:#fff; color:#475569; }
                .hcal-chip.chip-all.is-active { background:#475569; border-color:#475569; color:#fff; }
                .hcal-chip.chip-all.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip.chip-evrak { color:#b45309; background:#fffbeb; border-color:#fde68a; }
                .hcal-chip.chip-evrak b { background:#fff; color:#b45309; }
                .hcal-chip.chip-evrak.is-active { background:#b45309; border-color:#b45309; color:#fff; }
                .hcal-chip.chip-evrak.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip.chip-gorev { color:#2563eb; background:#eff6ff; border-color:#bfdbfe; }
                .hcal-chip.chip-gorev b { background:#fff; color:#2563eb; }
                .hcal-chip.chip-gorev.is-active { background:#2563eb; border-color:#2563eb; color:#fff; }
                .hcal-chip.chip-gorev.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip.chip-dogum { color:#7c3aed; background:#f5f3ff; border-color:#ddd6fe; }
                .hcal-chip.chip-dogum b { background:#fff; color:#7c3aed; }
                .hcal-chip.chip-dogum.is-active { background:#7c3aed; border-color:#7c3aed; color:#fff; }
                .hcal-chip.chip-dogum.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip.chip-nobet { color:#0284c7; background:#f0f9ff; border-color:#bae6fd; }
                .hcal-chip.chip-nobet b { background:#fff; color:#0284c7; }
                .hcal-chip.chip-nobet.is-active { background:#0284c7; border-color:#0284c7; color:#fff; }
                .hcal-chip.chip-nobet.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip.chip-talep { color:#c2410c; background:#fff7ed; border-color:#fed7aa; }
                .hcal-chip.chip-talep b { background:#fff; color:#c2410c; }
                .hcal-chip.chip-talep.is-active { background:#c2410c; border-color:#c2410c; color:#fff; }
                .hcal-chip.chip-talep.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip.chip-gec { color:#be123c; background:#fff1f2; border-color:#fecdd3; }
                .hcal-chip.chip-gec b { background:#fff; color:#be123c; }
                .hcal-chip.chip-gec.is-active { background:#be123c; border-color:#be123c; color:#fff; }
                .hcal-chip.chip-gec.is-active b { background:rgba(255,255,255,.22); color:#fff; }
                .hcal-chip { display:inline-flex; align-items:center; gap:5px; height:30px; padding:0 12px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#475569; font-size:12px; font-weight:600; cursor:pointer; transition:background .15s, color .15s, border-color .15s; }
                .hcal-chip i { font-size:15px; }
                .hcal-chip b { font-weight:700; padding:0 6px; border-radius:6px; background:#f1f5f9; color:#475569; font-size:11px; line-height:18px; }
                .hcal-chip:hover { background:#f8fafc; border-color:#cbd5e1; }
                .hcal-chip.is-active { background:#475569; border-color:#475569; color:#fff; }
                .hcal-chip.is-active b { background:rgba(255,255,255,.2); color:#fff; }
                .hcal-item.is-nobet { border-left-color:#0ea5e9; }
                .hcal-item.is-nobet .hcal-item-date, .hcal-item.is-nobet .hcal-item-tag { color:#0284c7; }
                .hcal-item.is-nobet .hcal-item-tag { background:#f0f9ff; border-color:#bae6fd; }
                .hcal-item.is-talep { border-left-color:#f97316; }
                .hcal-item.is-talep .hcal-item-date, .hcal-item.is-talep .hcal-item-tag { color:#c2410c; }
                .hcal-item.is-talep .hcal-item-tag { background:#fff7ed; border-color:#fed7aa; }
                .hcal-item.is-gec { border-left-color:#e11d48; }
                .hcal-item.is-gec .hcal-item-date, .hcal-item.is-gec .hcal-item-tag { color:#be123c; }
                .hcal-item.is-gec .hcal-item-tag { background:#fff1f2; border-color:#fecdd3; }
                .hcal-strip { padding-top:8px !important; display:grid; grid-template-columns:repeat(var(--hcal-n, 30), minmax(34px, 1fr)); gap:4px; overflow-x:auto; padding-bottom:4px; }
                .hcal-day { position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1px; height:54px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#334155; cursor:pointer; padding:0; transition:background .15s, border-color .15s, transform .15s; }
                .hcal-day small { font-size:9.5px; font-weight:600; text-transform:uppercase; color:#94a3b8; line-height:1; }
                .hcal-day b { font-size:14px; font-weight:700; line-height:1.1; }
                .hcal-day:hover { background:#fff; border-color:#94a3b8; }
                .hcal-day.is-weekend { background:#f1f5f9; }
                .hcal-day.has-alarm { border-color:#fcd34d; background:#fffbeb; }
                .hcal-day.is-today { background:#475569; border-color:#475569; color:#fff; }
                .hcal-day.is-today small { color:#cbd5e1; }
                .hcal-day.is-selected { outline:2px solid #2563eb; outline-offset:1px; }
                .hcal-badge { position:absolute; top:-5px; right:-3px; min-width:16px; height:16px; padding:0 4px; border-radius:8px; background:#dc2626; color:#fff; font-style:normal; font-size:10px; font-weight:700; line-height:16px; text-align:center; }
                .hcal-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:8px; margin-top:12px; max-height:230px; overflow-y:auto; }
                .hcal-item { display:block; padding:9px 12px; border:1px solid #e2e8f0; border-left:3px solid #f59e0b; border-radius:8px; background:#fff; text-decoration:none !important; color:#334155; transition:background .15s, box-shadow .15s; min-width:0; }
                .hcal-item.is-gorev { border-left-color:#3b82f6; }
                .hcal-item.is-dogum { border-left-color:#8b5cf6; }
                .hcal-item.is-dogum .hcal-item-date { color:#7c3aed; }
                .hcal-item.is-dogum .hcal-item-tag { background:#f5f3ff; color:#7c3aed; border-color:#ddd6fe; }
                .hcal-day.is-past:not(.is-selected) { opacity:.5; background:#f8fafc; }
                .hcal-day.is-past.has-alarm:not(.is-selected) { border-color:#e2e8f0; background:#f8fafc; }
                .hcal-day.is-past .hcal-badge { background:#94a3b8; }
                .hcal-item.is-past { opacity:.55; border-left-color:#cbd5e1 !important; background:#f8fafc; }
                .hcal-item.is-past .hcal-item-date, .hcal-item.is-past .hcal-item-tag { color:#94a3b8 !important; background:#f1f5f9 !important; border-color:#e2e8f0 !important; }
                .hcal-item.is-past .hcal-item-title { font-weight:500; color:#64748b; }
                .hcal-day.is-past:hover, .hcal-item.is-past:hover { opacity:.9; }
                .hcal-item:hover { background:#f8fafc; box-shadow:0 2px 8px rgba(15,23,42,.06); }
                .hcal-item.d-none { display:none !important; }
                .hcal-item-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
                .hcal-item-date { font-size:11px; font-weight:700; color:#b45309; }
                .hcal-item.is-gorev .hcal-item-date { color:#2563eb; }
                .hcal-item-tag { font-size:10.5px; font-weight:700; padding:1px 8px; border-radius:6px; background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
                .hcal-item.is-gorev .hcal-item-tag { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
                .hcal-item-title { font-size:13px; font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .hcal-item-sub { font-size:11.5px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .hcal.is-collapsed .hcal-list { display:none; }
                .hcal-empty { grid-column:1 / -1; text-align:center; padding:14px 0; font-size:12.5px; color:#64748b; }
                .hcal-empty i { font-size:18px; vertical-align:middle; margin-right:4px; }
                [data-bs-theme="dark"] .dash-hero-cal { border-color:#3a4256; }
                [data-bs-theme="dark"] .hcal-title { color:#f8fafc; }
                [data-bs-theme="dark"] .hcal-day, [data-bs-theme="dark"] .hcal-item, [data-bs-theme="dark"] .hcal-nav-btn, [data-bs-theme="dark"] .hcal-chip, [data-bs-theme="dark"] .hcal-today-btn, [data-bs-theme="dark"] .hcal-reset { background:#232838; border-color:#3a4256; color:#e2e8f0; }
                [data-bs-theme="dark"] .hcal-day.is-weekend { background:#1e2332; }
                [data-bs-theme="dark"] .hcal-day.has-alarm { background:#3a3220; border-color:#a16207; }
                [data-bs-theme="dark"] .hcal-day.is-today { background:#64748b; border-color:#64748b; }
                [data-bs-theme="dark"] .hcal-item-title { color:#f1f5f9; }
                @media (max-width: 767.98px) { .dash-slider .carousel-content { padding:10px 36px !important; } .dash-slider .carousel-content h5 { white-space:normal; } }
                .dash-hero-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-left:auto; }
                .dash-hero-action { width:82px; height:82px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; border:1.5px solid #e2e8f0; border-radius:14px; background:#f8fafc; box-shadow:0 1px 3px rgba(0,0,0,.04); color:#334155; text-decoration:none !important; cursor:pointer; transition:all .25s cubic-bezier(.4,0,.2,1); }
                .dash-hero-action:hover { transform:translateY(-3px); border-color:#2563eb; background:#fff; box-shadow:0 8px 18px -4px rgba(37,99,235,.18), 0 4px 8px -2px rgba(0,0,0,.04); color:#0f172a; }
                .dash-hero-action:active { transform:translateY(-1px); }
                .dash-hero-action:focus-visible { outline:2px solid rgba(37,99,235,.45); outline-offset:2px; }
                .dash-hero-action-icon { width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:8px; background:rgba(226,232,240,.6); color:#475569; font-size:20px; transition:all .25s ease; }
                .dash-hero-action:hover .dash-hero-action-icon { color:#2563eb; background:rgba(37,99,235,.1); transform:scale(1.08); }
                .dash-hero-action-label { font-size:11px; font-weight:600; line-height:1.2; text-align:center; color:#334155; transition:color .25s ease; }
                .dash-hero-action:hover .dash-hero-action-label { color:#0f172a; }
                @media (max-width: 767.98px) {
                    .dash-hero { padding:18px; }
                    .dash-hero-title { font-size:20px; }
                    .dash-hero-actions { width:100%; display:grid; grid-template-columns:repeat(auto-fit, minmax(72px, 1fr)); }
                    .dash-hero-action { width:auto; }
                    .dash-hero .dashboard-settings-toolbar > .dropdown > .btn { color:transparent !important; width:34px; }
                }
                [data-bs-theme="dark"] .dash-hero,
                [data-bs-theme="dark"] .dash-hero-action { background:#2a3042; border-color:#3a4256; }
                [data-bs-theme="dark"] .dash-hero-chip,
                [data-bs-theme="dark"] .dash-hero .dashboard-settings-toolbar > .dropdown > .btn { background:#232838 !important; border-color:#3a4256 !important; color:#cbd5e1 !important; }
                [data-bs-theme="dark"] .dash-hero-title { color:#f8fafc; }
                [data-bs-theme="dark"] .dash-hero-sub { color:#94a3b8; }
                [data-bs-theme="dark"] .dash-hero-action { color:#e2e8f0; }

                .dash-kpi-legend-item { padding:9px 12px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:10px; }
                .dash-kpi-legend-value { padding-left:14px; font-size:17px; }
                .dash-kpi-legend { gap:10px; }
                [data-bs-theme="dark"] .dash-kpi-legend-item { background:#232838; border-color:#3a4256; }

                #widget-endeks-karsilastirma .endeks-overview,
                #widget-kesme-karsilastirma .kesme-overview { background:#fff !important; border-color:#f1f5f9 !important; gap:10px !important; padding:14px !important; }
                #widget-endeks-karsilastirma .endeks-kpi,
                #widget-kesme-karsilastirma .kesme-kpi { background:#fff !important; border:1px solid #e2e8f0 !important; border-radius:12px !important; box-shadow:0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.02) !important; padding:13px 15px !important; transition:transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s cubic-bezier(.4,0,.2,1), border-color .25s ease !important; }
                #widget-endeks-karsilastirma .endeks-kpi::after { display:none !important; }
                #widget-endeks-karsilastirma .endeks-kpi.is-actionable:hover,
                #widget-kesme-karsilastirma .kesme-kpi.is-actionable:hover { transform:translateY(-3px) !important; border-color:#cbd5e1 !important; box-shadow:0 10px 20px -5px rgba(15,23,42,.08), 0 4px 6px -2px rgba(15,23,42,.03) !important; }
                #widget-endeks-karsilastirma .endeks-kpi-label,
                #widget-kesme-karsilastirma .kesme-kpi-label { font-size:11px !important; font-weight:600 !important; color:#64748b !important; letter-spacing:.04em !important; }
                #widget-endeks-karsilastirma .endeks-kpi-value,
                #widget-kesme-karsilastirma .kesme-kpi-value { margin-top:4px !important; font-size:22px !important; font-weight:800 !important; letter-spacing:-.03em; font-feature-settings:"tnum"; }
                #widget-endeks-karsilastirma .endeks-kpi-note,
                #widget-kesme-karsilastirma .kesme-kpi-note { margin-top:6px !important; font-size:11px !important; color:#64748b !important; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                #widget-kesme-karsilastirma .comparison-dashboard-card { border:0 !important; box-shadow:none !important; }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-overview,
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .kesme-overview,
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-kpi,
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .kesme-kpi { background:#2a3042 !important; border-color:#3a4256 !important; }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .endeks-kpi-note,
                [data-bs-theme="dark"] #widget-kesme-karsilastirma .kesme-kpi-note { border-top-color:#3a4256; }
                #widget-is-operasyonlari .ops-bar:not(:has(.ops-bar-seg:not([style*="width: 0%"]):not(.d-none))):not(:has(.ops-bar-track:not(.d-none))) { display:none; }

                #widget-is-operasyonlari .ops-c--reading { --ops-color:#0891b2; --ops-soft:#ecfeff; }
                #widget-is-operasyonlari .ops-c--cut_open { --ops-color:#e11d48; --ops-soft:#fff1f2; }
                #widget-is-operasyonlari .ops-c--meter_change { --ops-color:#059669; --ops-soft:#ecfdf5; }
                #widget-is-operasyonlari .ops-c--sealing { --ops-color:#475569; --ops-soft:#f1f5f9; }
                #widget-is-operasyonlari .ops-c--illegal { --ops-color:#ea580c; --ops-soft:#fff7ed; }
                #widget-is-operasyonlari .ops-c--notice { --ops-color:#d97706; --ops-soft:#fffbeb; }
                #widget-is-operasyonlari .ops-card { max-height:none !important; }
                #widget-is-operasyonlari .ops-card > .card-body > * { flex-shrink:0; }
                #widget-is-operasyonlari .ops-card > .card-body { padding:18px 20px 20px !important; }
                #widget-is-operasyonlari .ops-head { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
                #widget-is-operasyonlari .ops-head .dash-kpi-label { margin-bottom:2px; }
                #widget-is-operasyonlari .ops-head-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
                #widget-is-operasyonlari .ops-mode-toggle { display:inline-flex; padding:3px; gap:2px; background:#f1f5f9; border-radius:8px; }
                #widget-is-operasyonlari .ops-mode-btn { border:0; background:transparent; color:#64748b; font-size:12px; font-weight:600; padding:5px 14px; border-radius:6px; line-height:1.3; transition:background .2s ease, color .2s ease, box-shadow .2s ease; }
                #widget-is-operasyonlari .ops-mode-btn:hover { color:#1e293b; }
                #widget-is-operasyonlari .ops-mode-btn.is-active { background:#fff; color:#2563eb; box-shadow:0 1px 3px rgba(15,23,42,.12); }
                #widget-is-operasyonlari .ops-mode-btn:focus-visible { outline:2px solid rgba(37,99,235,.4); outline-offset:1px; }
                #widget-is-operasyonlari .ops-sync-all { display:inline-flex; align-items:center; gap:5px; border:1px solid #e2e8f0; background:#fff; color:#475569; font-size:12px; font-weight:600; padding:5px 12px; border-radius:8px; transition:.15s ease; }
                #widget-is-operasyonlari .ops-sync-all:hover:not(:disabled) { border-color:#cbd5e1; color:#1d4ed8; background:#f8fafc; }
                #widget-is-operasyonlari .ops-sync-all i { font-size:15px; }
                #widget-is-operasyonlari .ops-contract-note { min-height:18px; margin:-4px 0 10px; font-size:12px; color:#64748b; }
                #widget-is-operasyonlari .ops-contract-note b { color:#1e293b; }
                #widget-is-operasyonlari .ops-bar { display:flex; gap:3px; height:8px; margin-bottom:16px; }
                #widget-is-operasyonlari .ops-bar-seg { display:block; height:100%; border-radius:999px; background:var(--ops-color); transition:width .5s ease; min-width:0; }
                #widget-is-operasyonlari .ops-bar-seg[style*="width: 0%"] { display:none; }
                #widget-is-operasyonlari .ops-bar-contract { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,#10b981,#059669); transition:width .5s ease; }
                #widget-is-operasyonlari .ops-bar-track { flex:1 1 100%; width:100%; display:flex; height:100%; background:#f1f5f9; border-radius:999px; overflow:hidden; }
                #widget-is-operasyonlari .ops-items { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; }
                #widget-is-operasyonlari .ops-item { display:flex; flex-direction:column; padding:16px 18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; color:inherit; text-decoration:none !important; box-shadow:0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.02); transition:transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s cubic-bezier(.4,0,.2,1), border-color .25s ease; min-width:0; }
                #widget-is-operasyonlari .ops-item:hover { transform:translateY(-3px); border-color:#cbd5e1; box-shadow:0 10px 20px -5px rgba(15,23,42,.08), 0 4px 6px -2px rgba(15,23,42,.03); }
                #widget-is-operasyonlari .ops-item .dash-kpi-header { margin-bottom:8px; }
                #widget-is-operasyonlari .ops-item .dash-kpi-label { font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                #widget-is-operasyonlari .ops-item .dash-kpi-value { font-size:24px; }
                #widget-is-operasyonlari .ops-item .dash-kpi-icon { width:40px; height:40px; flex-basis:40px; font-size:19px; background:var(--ops-soft); color:var(--ops-color); }
                #widget-is-operasyonlari .ops-item:hover .dash-kpi-icon { transform:scale(1.08); }
                #widget-is-operasyonlari .ops-contract { display:block; margin:-2px 0 0; }
                #widget-is-operasyonlari .ops-contract.d-none { display:none !important; }
                #widget-is-operasyonlari .ops-mini-progress { display:block; height:6px; background:#f1f5f9; border-radius:999px; overflow:hidden; }
                #widget-is-operasyonlari .ops-mini-fill { display:block; height:100%; width:0; background:var(--ops-color); border-radius:999px; transition:width .5s ease; }
                #widget-is-operasyonlari .ops-contract-text { font-feature-settings:"tnum"; }
                #widget-is-operasyonlari.is-contract .ops-item .dash-kpi-footer { border-top:0; padding-top:8px; }
                #widget-is-operasyonlari .ops-item .dash-kpi-footer { padding-top:10px; min-height:34px; }
                #widget-is-operasyonlari .ops-sub b { color:#047857; }
                #widget-is-operasyonlari .ops-sub i { color:#059669; }
                #widget-is-operasyonlari .ops-rate { background:var(--ops-soft); color:var(--ops-color); }
                #widget-is-operasyonlari .ops-go { color:var(--ops-color); font-size:18px; line-height:1; opacity:.55; transition:opacity .15s ease, transform .15s ease; }
                #widget-is-operasyonlari .ops-item:hover .ops-go { opacity:1; transform:translateX(2px); }
                #widget-is-operasyonlari.is-contract .ops-go,
                #widget-is-operasyonlari .ops-rate:empty { display:none !important; }

                [data-bs-theme="dark"] #dashboard-widgets .card.dash-kpi-card,
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-item { background:#2a3042; border-color:#3a4256 !important; }
                [data-bs-theme="dark"] .dash-kpi-label,
                [data-bs-theme="dark"] .dash-kpi-legend-label { color:#94a3b8; }
                [data-bs-theme="dark"] .dash-kpi-value,
                [data-bs-theme="dark"] .dash-kpi-legend-value,
                [data-bs-theme="dark"] .dash-kpi-person-name,
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-contract-note b { color:#f8fafc; }
                [data-bs-theme="dark"] .dash-kpi-footer { border-top-color:#3a4256; }
                [data-bs-theme="dark"] .dash-kpi-icon.icon-blue { background:rgba(37,99,235,.2); color:#60a5fa; }
                [data-bs-theme="dark"] .dash-kpi-icon.icon-emerald { background:rgba(5,150,105,.2); color:#34d399; }
                [data-bs-theme="dark"] .dash-kpi-icon.icon-amber { background:rgba(217,119,6,.2); color:#fbbf24; }
                [data-bs-theme="dark"] .dash-kpi-icon.icon-purple { background:rgba(124,58,237,.2); color:#a78bfa; }
                [data-bs-theme="dark"] .dash-kpi-icon.icon-cyan { background:rgba(8,145,178,.2); color:#22d3ee; }
                [data-bs-theme="dark"] .dash-kpi-icon.icon-rose { background:rgba(225,29,72,.2); color:#fb7185; }
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-item .dash-kpi-icon,
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-rate { background:rgba(148,163,184,.14); }
                [data-bs-theme="dark"] #dashboard-widgets .mac-title-bar { background:#2a3042 !important; border-bottom-color:#3a4256 !important; }
                [data-bs-theme="dark"] #dashboard-widgets .widget-item:hover .mac-title-bar { background:#2a3042 !important; }
                [data-bs-theme="dark"] #dashboard-widgets .mac-title-text { color:#e2e8f0 !important; }
                [data-bs-theme="dark"] #dashboard-widgets .mac-control { background:#4b5563 !important; }
                [data-bs-theme="dark"] #dashboard-widgets .kpi-widget .mac-title-bar { border-color:#3a4256 !important; }
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-mode-toggle,
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-mini-progress,
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-bar-track { background:#1d232f; }
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-mode-btn.is-active { background:#374151; color:#93c5fd; }
                [data-bs-theme="dark"] #widget-is-operasyonlari .ops-sync-all { background:#2a3042; border-color:#3a4256; color:#cbd5e1; }
                .comparison-detail-toggle { display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px 14px;border:0;border-top:1px solid #e9edf4;border-bottom:1px solid #e9edf4;background:linear-gradient(180deg,#fff,#f8fafc);color:#536174;font-size:11px;font-weight:700;letter-spacing:.02em;transition:background .2s ease,color .2s ease; }
                .comparison-detail-toggle:hover { background:#f1f5f9;color:#3730a3; }
                .comparison-detail-toggle i { font-size:16px;transition:transform .28s cubic-bezier(.2,.8,.2,1); }
                .comparison-detail-toggle[aria-expanded="true"] i { transform:rotate(180deg); }
                .comparison-collapsible-detail { display:none;overflow:hidden; }
                [data-bs-theme="dark"] .comparison-detail-toggle { background:linear-gradient(180deg,#252c39,#202631);border-color:#374151;color:#b8c1cf; }
                [data-bs-theme="dark"] .comparison-detail-toggle:hover { background:#2b3442;color:#fff; }
                #kesmeDistributionModal .modal-dialog { transform:translateY(28px) scale(.96);transition:transform .32s cubic-bezier(.2,.8,.2,1); }
                #kesmeDistributionModal.show .modal-dialog { transform:translateY(0) scale(1); }
                [data-bs-theme="dark"] #kesmeDistributionModal .modal-body { background:#1b202b !important; }
                [data-bs-theme="dark"] #kesmeDistributionModal .modal-body .bg-white { background:#252c39 !important;border-color:#374151 !important; }
                .kesme-modal-filter-pills { display:inline-flex;align-items:center;background:rgba(0,0,0,.22);backdrop-filter:blur(8px);padding:3px;border-radius:30px;border:1px solid rgba(255,255,255,.24);box-shadow:inset 0 1px 3px rgba(0,0,0,.2); }
                .kesme-pill-btn { border:none;background:transparent;color:rgba(255,255,255,.88);font-size:11.5px;font-weight:600;padding:5px 13px;border-radius:20px;display:inline-flex;align-items:center;cursor:pointer;transition:all .18s cubic-bezier(.4,0,.2,1);white-space:nowrap;line-height:1.2; }
                .kesme-pill-btn:hover { color:#fff;background:rgba(255,255,255,.14); }
                .kesme-pill-btn.active[data-type="all"] { background:#fff;color:#1e293b;font-weight:700;box-shadow:0 2px 7px rgba(0,0,0,.22); }
                .kesme-pill-btn.active[data-type="olumlu"] { background:#fff;color:#059669;font-weight:700;box-shadow:0 2px 7px rgba(0,0,0,.22); }
                .kesme-pill-btn.active[data-type="olumsuz"] { background:#fff;color:#dc2626;font-weight:700;box-shadow:0 2px 7px rgba(0,0,0,.22); }
                #endeksDistributionModal .modal-dialog { transform:translateY(28px) scale(.96); transition:transform .32s cubic-bezier(.2,.8,.2,1); }
                #endeksDistributionModal.show .modal-dialog { transform:translateY(0) scale(1); }
                #endeksDistributionModal .modal-content { overflow:hidden; border:1px solid rgba(148,163,184,.22); border-radius:18px; box-shadow:0 28px 75px -28px rgba(15,23,42,.55); }
                #endeksDistributionModal .endeks-modal-hero { padding:20px 22px; color:#fff; background:radial-gradient(circle at 90% 10%,rgba(255,255,255,.2),transparent 25%),linear-gradient(135deg,#3730a3,#4f46e5 54%,#2563eb); }
                #endeksDistributionModal .endeks-modal-stat { padding:12px 14px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); border-radius:12px; backdrop-filter:blur(8px); }
                #endeksDistributionModal .endeks-modal-section { padding:18px; background:#fff; border:1px solid #e9edf4; border-radius:14px; }
                #endeksDistributionModal .endeks-status-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
                #endeksDistributionModal .endeks-status-item { padding:12px; border:1px solid #e8ecf3; border-radius:11px; background:#fbfcfe; transition:transform .18s ease,border-color .18s ease; animation:endeksItemIn .35s both; }
                #endeksDistributionModal .endeks-status-item:hover { transform:translateY(-2px); border-color:#c7d2fe; }
                #endeksDistributionModal .endeks-region-row + .endeks-region-row { margin-top:14px; }
                #endeksDistributionModal .endeks-region-track { height:7px; overflow:hidden; background:#eef2f7; border-radius:20px; }
                #endeksDistributionModal .endeks-region-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#6366f1,#38bdf8); animation:endeksBarGrow .7s cubic-bezier(.2,.8,.2,1) both; transform-origin:left; }
                @keyframes endeksItemIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
                @keyframes endeksBarGrow { from{transform:scaleX(0)} to{transform:scaleX(1)} }
                @media(max-width:767.98px){ #endeksDistributionModal .endeks-status-grid{grid-template-columns:1fr} }
                [data-bs-theme="dark"] #endeksDistributionModal .endeks-modal-section { background:#222936; border-color:#354052; }
                [data-bs-theme="dark"] #endeksDistributionModal .endeks-status-item { background:#282f3d; border-color:#394456; }
                [data-bs-theme="dark"] #endeksDistributionModal .modal-body { background:#1b202b !important; }
                [data-bs-theme="dark"] #endeksDistributionModal .endeks-region-track,
                [data-bs-theme="dark"] #endeksDistributionModal .progress { background:#343d4d !important; }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .card-body,
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .table-responsive {
                    background-color: var(--bs-card-bg, #222830) !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table {
                    background-color: var(--bs-card-bg, #222830) !important;
                    color: #a6b0cf !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table td {
                    color: #a6b0cf !important;
                    border-bottom-color: #32394e !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table td span {
                    color: #ffffff !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table tr:hover td {
                    background-color: #32394e !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table thead tr {
                    background: linear-gradient(135deg, #181d29 0%, #202636 100%) !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table tbody tr.bolge-row:hover,
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table tbody tr.bolge-row {
                    border-bottom: 1px solid #32394e !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .d-flex.flex-wrap.gap-2.px-3.py-3 {
                    background: linear-gradient(135deg, #222736 0%, #1d222e 100%) !important;
                    border-bottom-color: #32394e !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .d-flex.flex-wrap.gap-2.px-3.py-3 span {
                    color: #e5e7eb !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .d-flex.flex-wrap.gap-2.px-3.py-3 div[style*="background: rgba(239,68,68"] {
                    background-color: rgba(239, 68, 68, 0.15) !important;
                    border-color: rgba(239, 68, 68, 0.3) !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .d-flex.flex-wrap.gap-2.px-3.py-3 div[style*="background: rgba(16,185,129"] {
                    background-color: rgba(16, 185, 129, 0.15) !important;
                    border-color: rgba(16, 185, 129, 0.3) !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .d-flex.flex-wrap.gap-2.px-3.py-3 div[style*="background: rgba(239,68,68"] span,
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .d-flex.flex-wrap.gap-2.px-3.py-3 div[style*="background: rgba(16,185,129"] span {
                    color: #ffffff !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma .px-3.py-2.d-flex {
                    background: linear-gradient(135deg, #222736 0%, #1d222e 100%) !important;
                    border-top-color: #32394e !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table tr[style*="background: linear-gradient(135deg, #eef2ff"] {
                    background: linear-gradient(135deg, #1b202e 0%, #161a26 100%) !important;
                    border-top-color: #3b82f6 !important;
                }
                [data-bs-theme="dark"] #widget-endeks-karsilastirma table tr[style*="background: linear-gradient(135deg, #eef2ff"] td {
                    color: #6366f1 !important;
                }
                [data-bs-theme="dark"] .widget-item .gorev-card {
                    background-color: #2e3548 !important;
                    border-color: #32394e !important;
                }
                [data-bs-theme="dark"] .widget-item .gorev-card:hover {
                    background-color: #32394e !important;
                }
                [data-bs-theme="dark"] .widget-item .gorev-card h6 {
                    color: #ffffff !important;
                }

                .finder-tabs-nav .nav-link {
                    transition: all 0.18s ease !important;
                }
                .finder-tabs-nav .nav-link.active,
                .finder-tabs-nav .nav-link.active * {
                    color: #0f172a !important;
                }
                .finder-tabs-nav .nav-link.active {
                    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
                    border-color: rgba(148,163,184,0.32) !important;
                    box-shadow: 0 8px 20px -14px rgba(15,23,42,0.45), inset 0 1px 0 rgba(255,255,255,0.95) !important;
                }
                .finder-tabs-nav .nav-link.active .badge {
                    background: #e2e8f0 !important;
                    color: #334155 !important;
                }
                .finder-tabs-nav .nav-link:not(.active):hover,
                .finder-tabs-nav .nav-link:not(.active):hover * {
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

                [data-bs-theme="dark"] #dashboard-page-skeleton .skeleton-card {
                    background: #222830;
                    border-color: #2b333e;
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

            <?php
            $skeletonItems = [];
            foreach ($render_order as $skeletonId) {
                if (!isset($widgets[$skeletonId]) || !preg_match('/<div\b[^>]*>/', (string) $widgets[$skeletonId], $skTag)) {
                    continue;
                }
                $skSaved = is_array($saved_settings[$skeletonId] ?? null) ? $saved_settings[$skeletonId] : [];
                $skClass = preg_match('/class="([^"]*)"/', $skTag[0], $skC) ? $skC[1] : '';
                $skStyle = preg_match('/style="([^"]*)"/', $skTag[0], $skS) ? $skS[1] : '';
                if (str_contains($skClass, 'widget-hidden') || preg_match('/display\s*:\s*none/i', $skStyle) || ($skSaved['hidden'] ?? '') === 'true') {
                    continue;
                }
                $skCols = array_values(array_filter(preg_split('/\s+/', $skClass) ?: [], static fn($c) => preg_match('/^col(-[a-z]+)?(-\d+)?$/', $c)));
                $skProps = [];
                $skHeight = 0;
                foreach (explode(';', $skStyle) as $skDecl) {
                    if (preg_match('/^\s*(width|height|min-height|position|left|top)\s*:\s*([^;]+?)\s*$/i', $skDecl, $skP)) {
                        $skProps[strtolower($skP[1])] = preg_replace('/\s*!important/i', '', $skP[2]);
                        if (in_array(strtolower($skP[1]), ['height', 'min-height'], true) && preg_match('/^(\d+(?:\.\d+)?)px$/', trim($skProps[strtolower($skP[1])]), $skPx)) {
                            $skHeight = max($skHeight, (int) $skPx[1]);
                        }
                    }
                }
                $skItem = ['id' => $skeletonId, 'class' => $skCols ? implode(' ', $skCols) : 'col-12', 'style' => '', 'height' => 0];
                if ($dashboard_is_free) {
                    if (!isset($skProps['height']) && !isset($skProps['min-height'])) {
                        $skProps['min-height'] = '180px';
                    }
                    $skItem['style'] = implode('; ', array_map(static fn($k, $v) => $k . ': ' . $v, array_keys($skProps), $skProps));
                } else {
                    $skGridCols = (int) ($skSaved['gridCols'] ?? 0);
                    if ($skGridCols >= 1 && $skGridCols <= 12) {
                        $skItem['class'] = 'col-md-' . $skGridCols . ' col-xl-' . $skGridCols;
                    }
                    $skGridHeight = (int) ($skSaved['gridHeight'] ?? 0);
                    $skDefaultHeights = ["widget-personel-arac-ozet" => 300, "widget-ana-slider" => 304, "widget-is-operasyonlari" => 408, "widget-dogum-gunleri" => 280, "widget-talepler" => 360, "widget-izindekiler" => 360, "widget-yaklasan-gorevler" => 260, "widget-endeks-karsilastirma" => 220, "widget-kesme-karsilastirma" => 220];
                    $skH = $skGridHeight >= 120 ? $skGridHeight : ($skDefaultHeights[$skeletonId] ?? ($skHeight > 0 ? $skHeight : 180));
                    $skStyleParts = ['grid-row-end: span ' . (int) ceil(($skH + 12) / 8)];
                    $skGridX = (int) ($skSaved['gridX'] ?? 0);
                    $skGridY = (int) ($skSaved['gridY'] ?? 0);
                    if ($skGridX >= 1 && $skGridX <= 12 && $skGridY >= 1) {
                        $skStyleParts[] = '--gx: ' . $skGridX;
                        $skStyleParts[] = '--gy: ' . $skGridY;
                        $skItem['grid'] = [$skGridX, $skGridY];
                    }
                    $skCols12 = 12;
                    if ($skGridCols >= 1 && $skGridCols <= 12) {
                        $skCols12 = $skGridCols;
                    } elseif (preg_match('/col-(?:xl-|lg-|md-)?(\d+)/', $skItem['class'], $skColM)) {
                        $skCols12 = min(12, max(1, (int) $skColM[1]));
                    }
                    $skStyleParts[] = '--c: ' . $skCols12;
                    $skItem['style'] = implode('; ', $skStyleParts);
                }
                $skeletonItems[] = $skItem;
            }
            $skeletonEsc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            ?>
            <style id="dashboard-skeleton-grid">
                #dashboard-page-skeleton .skeleton-widgets-grid { margin-left: -12px; margin-right: -12px; display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); grid-auto-rows: 8px; grid-auto-flow: row dense; align-items: stretch; }
                #dashboard-page-skeleton .skeleton-widgets-grid > .skeleton-widget { grid-column: span 12; min-width: 0; padding: 0 12px; box-sizing: border-box; }
                @media (min-width: 768px) {
                    #dashboard-page-skeleton .skeleton-widgets-grid > .skeleton-widget { grid-column: span var(--c, 12); }
                    #dashboard-page-skeleton .skeleton-widgets-grid > .skeleton-widget[data-grid-x] { grid-column: var(--gx) / span var(--c, 12); grid-row-start: var(--gy); }
                }
            </style>
            <div id="dashboard-page-skeleton" class="dashboard-page-skeleton" aria-hidden="true">
                <div class="skeleton-card" style="min-height: 110px; margin-bottom: 24px;">
                    <div class="skeleton-line w-35"></div>
                    <div class="skeleton-line w-60"></div>
                </div>
                <div class="<?php echo $dashboard_is_free ? 'd-block' : 'skeleton-widgets-grid'; ?>" style="position: relative; min-height: 800px;">
                    <?php foreach ($skeletonItems as $skeletonItem): ?>
                        <div class="skeleton-widget <?php echo $skeletonEsc($dashboard_is_free ? $skeletonItem['class'] : ''); ?>"<?php echo isset($skeletonItem['grid']) ? ' data-grid-x="' . (int) $skeletonItem['grid'][0] . '"' : ''; ?> style="<?php echo $skeletonEsc($skeletonItem['style']); ?>">
                            <div class="skeleton-card" style="height: calc(100% - 24px); min-height: 0;">
                                <div class="skeleton-line w-50"></div>
                                <div class="skeleton-line w-90"></div>
                                <div class="skeleton-line w-70"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            echo str_repeat(' ', 4096);
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            if (function_exists('flush')) {
                @flush();
            }
            ?>

            <div id="dashboard-page-content" class="dashboard-content-pending">

                <?php
                $maintitle = 'Ana Sayfa';
                $title = '';
                $heroAylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                $heroGunler = [1 => 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
                $heroTarih = date('j') . ' ' . $heroAylar[(int) date('n')] . ' ' . date('Y') . ', ' . $heroGunler[(int) date('N')];
                $heroSaat = (int) date('G');
                $heroSelam = $heroSaat < 6 ? 'İyi geceler' : ($heroSaat < 12 ? 'Günaydın' : ($heroSaat < 18 ? 'İyi günler' : 'İyi akşamlar'));
                $heroAdSoyad = trim((string) ($_SESSION['user']->adi_soyadi ?? ''));
                $heroAdParcalari = $heroAdSoyad !== '' ? preg_split('/\s+/u', $heroAdSoyad) : [];
                $heroAd = count($heroAdParcalari) > 1 ? implode(' ', array_slice($heroAdParcalari, 0, -1)) : ($heroAdParcalari[0] ?? '');
                $heroAksiyonlar = [];
                if (Gate::allows('personel_listesi')) {
                    $heroAksiyonlar[] = ['href' => 'index.php?p=personel/list', 'icon' => 'bx-group', 'label' => 'Personel', 'tone' => 'blue'];
                }
                if (Gate::allows('puantaj/raporlar')) {
                    $heroAksiyonlar[] = ['href' => 'index.php?p=puantaj/raporlar', 'icon' => 'bx-bar-chart-alt-2', 'label' => 'İş Raporları', 'tone' => 'emerald'];
                }
                if (Gate::allows('arac_takip_yonetim')) {
                    $heroAksiyonlar[] = ['href' => 'index.php?p=arac-takip/list', 'icon' => 'bx-car', 'label' => 'Araç Takip', 'tone' => 'purple'];
                }
                if (Gate::allows('talepler')) {
                    $heroAksiyonlar[] = ['href' => 'index.php?p=talepler/list', 'icon' => 'bx-message-square-detail', 'label' => 'Talepler', 'tone' => 'amber'];
                }
                if (Gate::allows('nobet/list')) {
                    $heroAksiyonlar[] = ['href' => 'index.php?p=nobet/list', 'icon' => 'bx-calendar-star', 'label' => 'Nöbetler', 'tone' => 'cyan'];
                }
                $heroEsc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
                ?>
                <?php echo $dashSliderHtml ?? ''; ?>
                <section class="dash-hero" aria-label="Karşılama">
                    <div class="dash-hero-main">
                        <div class="dash-hero-chips">
                            <span class="dash-hero-chip"><i class="bx bx-calendar"></i> <?php echo $heroEsc($heroTarih); ?></span>
                    <div class="d-flex gap-2 dashboard-settings-toolbar" id="dashboard-settings-toolbar">
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary btn-sm dropdown-toggle d-flex align-items-center" type="button"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="font-weight: 600; padding: 6px 16px; border-radius: 8px;">
                                <i class="bx bx-cog me-2 fs-5"></i> Dashboard Ayarları
                            </button>
                            <ul class="dropdown-menu dropdown-menu-start shadow-lg border-0 py-2"
                                style="min-width: 400px; z-index: 1060; border-radius: 12px;">
                                <li class="px-3 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="dropdown-header px-0 mb-0 fw-bold text-primary">YERLEŞİM AYARLARI</h6>
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0 fw-bold" id="btn-reset-dashboard" style="text-decoration: none; font-size: 11px;">
                                            <i class="bx bx-reset me-1"></i> SIFIRLA
                                        </button>
                                    </div>
                                </li>
                                <li class="px-3 mb-2">
                                    <input type="checkbox" id="switch-free-layout" class="d-none" tabindex="-1" aria-hidden="true">
                                    <div class="small text-muted" style="font-size: 11px; line-height: 1.3;">Kart genişliği (12 sütunluk grid) ve yüksekliği aşağıdaki listeden seçilir.</div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li class="px-3 py-1">
                                    <h6 class="dropdown-header px-0 mb-1 fw-bold text-primary">KARTLAR · GÖRÜNÜRLÜK, GENİŞLİK, YÜKSEKLİK</h6>
                                </li>
                                <div style="max-height: 420px; overflow-y: auto; padding: 0 8px;" id="dash-card-settings-list">
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-ana-slider" checked>
                                            <strong style="font-size: 13px;">Haberler ve Duyurular</strong>
                                        </label>
                                    </li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-personel-arac-ozet" checked>
                                            <span style="font-size: 13px;">Personel ve Araç Durumu</span>
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
                                    <li><hr class="dropdown-divider opacity-50"></li>
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-is-operasyonlari" checked>
                                            <span style="font-size: 13px;">İş Operasyonları</span>
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
                                    <li>
                                        <label class="dropdown-item rounded cursor-pointer py-2" style="cursor: pointer;">
                                            <input type="checkbox" class="form-check-input widget-toggle me-2" data-widget="widget-kesme-karsilastirma" checked>
                                            <strong style="font-size: 13px;">Kesme/Açma Karşılaştırma</strong>
                                        </label>
                                    </li>
                                </div>
                            </ul>
                        </div>
                    </div>
                        </div>
                        <h1 class="dash-hero-title"><?php echo $heroEsc($heroSelam . ($heroAd !== '' ? ', ' . $heroAd : '')); ?> <span class="dash-hero-wave" aria-hidden="true">👋</span></h1>
                        <p class="dash-hero-sub">Operasyon, personel ve araç süreçlerinize bugünkü genel bakış.</p>
                    </div>
                    <?php if ($heroAksiyonlar !== []): ?>
                        <nav class="dash-hero-actions" aria-label="Hızlı işlemler">
                            <?php foreach ($heroAksiyonlar as $aksiyon): ?>
                                <a href="<?php echo $heroEsc($aksiyon['href']); ?>" class="dash-hero-action tone-<?php echo $heroEsc($aksiyon['tone']); ?>">
                                    <span class="dash-hero-action-icon"><i class="bx <?php echo $heroEsc($aksiyon['icon']); ?>"></i></span>
                                    <span class="dash-hero-action-label"><?php echo $heroEsc($aksiyon['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                    <div class="dash-hero-cal" id="heroCalendarHost" aria-label="Görev ve evrak takvimi">
                        <?php
                        try {
                            echo renderWidget('widget-hero-takvim', [
                                'takvim_alarmlar' => homeHeroTakvimAlarmlari((int) $firmaId, (int) $userId, (int) date('Y'), (int) date('n')),
                                'secili_yil' => (int) date('Y'),
                                'secili_ay' => (int) date('n'),
                            ]);
                        } catch (\Throwable $e) {
                            error_log('Hero takvim hatası: ' . $e->getMessage());
                        }
                        ?>
                    </div>
                </section>

                <style>
                    .dashboard-settings-toolbar {
                        position: relative;
                        z-index: 5;
                    }

                    .quick-fav-inner > .dashboard-settings-toolbar {
                        position: relative;
                        top: auto;
                        right: auto;
                        z-index: 2;
                        flex-shrink: 0;
                        margin-left: auto;
                    }

                    .quick-fav-inner:has(> .dashboard-settings-toolbar) {
                        overflow: visible;
                    }

                    .dashboard-settings-toolbar > .dropdown > .btn {
                        height: 30px;
                        padding: 4px 12px !important;
                        border-color: var(--bs-border-color);
                        background: var(--bs-card-bg, #ffffff);
                        color: var(--bs-body-color);
                        backdrop-filter: blur(8px);
                    }

                    .dashboard-settings-toolbar > .dropdown > .btn:hover,
                    .dashboard-settings-toolbar > .dropdown > .btn:focus,
                    .dashboard-settings-toolbar > .dropdown > .btn.show {
                        border-color: rgba(139, 164, 255, .65);
                        background: rgba(51, 65, 85, .92);
                        color: #ffffff;
                    }

                    [data-bs-theme="dark"] .dashboard-settings-toolbar > .dropdown > .btn {
                        border-color: rgba(139, 164, 255, .34);
                        background: rgba(30, 41, 59, .72);
                        color: #cbd5e1;
                    }

                    @media (max-width: 767.98px) {
                        .dashboard-settings-toolbar {
                            right: 10px;
                        }

                        .dashboard-settings-toolbar > .dropdown > .btn {
                            width: 34px;
                            padding: 4px !important;
                            overflow: hidden;
                            white-space: nowrap;
                            color: transparent;
                        }

                        .dashboard-settings-toolbar > .dropdown > .btn i {
                            margin: 0 !important;
                            color: #ffffff;
                        }
                    }
                </style>

                <script>
                    (() => {
                        const favoritesInner = document.querySelector('#quick-favorites-bar .quick-fav-inner');
                        const dashboardToolbar = document.getElementById('dashboard-settings-toolbar');

                        if (favoritesInner && dashboardToolbar && !dashboardToolbar.closest('.dash-hero')) {
                            favoritesInner.appendChild(dashboardToolbar);
                        }
                    })();
                </script>

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

        <!-- Endeks dönem dağılımı -->
        <div class="modal fade" id="endeksDistributionModal" tabindex="-1" aria-labelledby="endeksDistributionTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="endeks-modal-hero">
                        <div class="d-flex align-items-start gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width:46px;height:46px;background:rgba(255,255,255,.16);"><i class="bx bx-layer fs-3"></i></span>
                            <div>
                                <div class="text-uppercase fw-bold" style="font-size:10px;letter-spacing:.12em;opacity:.75;">Endeks okuma analizi</div>
                                <h4 class="modal-title text-white mb-1" id="endeksDistributionTitle">Bu Dönemin Dağılımı</h4>
                                <div id="endeksDistributionPeriod" style="font-size:12px;opacity:.78;"></div>
                            </div>
                            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="row g-2 mt-3" id="endeksDistributionHeroStats"></div>
                    </div>
                    <div class="modal-body p-3 p-lg-4" style="background:#f5f7fb;">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <section class="endeks-modal-section h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div><h6 class="mb-1 fw-bold"><i class="bx bx-pie-chart-alt-2 text-primary me-1"></i>Sayaç Durumu Dağılımı</h6><div class="text-muted" style="font-size:11px;">Okunan abonelerin sonuç türlerine göre dağılımı</div></div>
                                        <span class="badge bg-primary-subtle text-primary" id="endeksStatusCount"></span>
                                    </div>
                                    <div class="endeks-status-grid" id="endeksStatusDistribution"></div>
                                </section>
                            </div>
                            <div class="col-lg-5">
                                <section class="endeks-modal-section h-100">
                                    <div class="mb-3"><h6 class="mb-1 fw-bold"><i class="bx bx-map-alt text-info me-1"></i>Bölge Katkısı</h6><div class="text-muted" style="font-size:11px;">Toplam okumaya en çok katkı sağlayan bölgeler</div></div>
                                    <div id="endeksRegionDistribution"></div>
                                </section>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 py-2 px-4">
                        <span class="text-muted me-auto" style="font-size:10px;"><i class="bx bx-info-circle me-1"></i>Veriler, kartta gösterilen dönem aralığını kapsar.</span>
                        <a href="index.php?p=puantaj/raporlar&tab=karsilastirma" class="btn btn-primary btn-sm rounded-pill px-3"><i class="bx bx-line-chart me-1"></i>Detaylı Rapora Git</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="kesmeDistributionModal" tabindex="-1" aria-labelledby="kesmeDistributionTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0" style="border-radius:18px;overflow:hidden;box-shadow:0 28px 75px -28px rgba(15,23,42,.6);">
                    <div class="p-4 text-white" style="background:radial-gradient(circle at 90% 10%,rgba(255,255,255,.2),transparent 25%),linear-gradient(135deg,#881337,#e11d48 58%,#f97316);">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width:46px;height:46px;background:rgba(255,255,255,.16)">
                                    <i class="bx bx-power-off fs-3"></i>
                                </span>
                                <div>
                                    <div class="text-uppercase fw-bold" style="font-size:10px;letter-spacing:.12em;opacity:.75">Kesme/Açma analizi</div>
                                    <h4 class="text-white mb-1" id="kesmeDistributionTitle">Bu Dönemin İş Dağılımı</h4>
                                    <div id="kesmeDistributionPeriod" style="font-size:12px;opacity:.8"></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <div class="kesme-modal-filter-pills" id="kesmeModalTypeToggle" role="group" aria-label="Modal Sonuç Türü">
                                    <button type="button" class="kesme-pill-btn" data-type="all">Tümü</button>
                                    <button type="button" class="kesme-pill-btn active" data-type="olumlu"><i class="bx bx-check-circle me-1"></i>Olumlu</button>
                                    <button type="button" class="kesme-pill-btn" data-type="olumsuz"><i class="bx bx-x-circle me-1"></i>Olumsuz</button>
                                </div>
                                <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                        </div>
                        <div class="row g-2 mt-3" id="kesmeDistributionStats"></div>
                    </div>
                    <div class="modal-body p-3 p-lg-4" style="background:#f6f7fb"><div class="row g-3"><div class="col-lg-7"><div class="bg-white border rounded-4 p-3 h-100"><h6 class="fw-bold mb-1"><i class="bx bx-category text-danger me-1"></i>İş Sonucu Dağılımı</h6><p class="text-muted mb-3" style="font-size:11px">Sonuçlanan işlerin işlem türlerine göre dağılımı</p><div id="kesmeResultDistribution" class="row g-2"></div></div></div><div class="col-lg-5"><div class="bg-white border rounded-4 p-3 h-100"><h6 class="fw-bold mb-1"><i class="bx bx-map-alt text-warning me-1"></i>Bölge Katkısı</h6><p class="text-muted mb-3" style="font-size:11px">Dönem toplamına en çok katkı sağlayan bölgeler</p><div id="kesmeRegionDistribution"></div></div></div></div></div>
                    <div class="modal-footer border-0 py-2 px-4"><span class="text-muted me-auto" style="font-size:10px"><i class="bx bx-info-circle me-1"></i>Aynı gün aralığındaki sonuçlanan işler gösterilir.</span><a href="index.php?p=puantaj/raporlar&tab=kesme" class="btn btn-danger btn-sm rounded-pill px-3"><i class="bx bx-line-chart me-1"></i>Detaylı Rapora Git</a></div>
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

        
        

        
        <!-- Duyuru Detay Modal - Home Page -->
        <div class="modal fade no-upgrade" id="modalDuyuruDetay" tabindex="-1" aria-hidden="true" data-modal-subtitle="Kurumsal Bildirim" data-modal-icon="bx bx-news">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-header py-3 px-4 bg-white border-bottom">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bx bx-news text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title text-dark fw-bold mb-0">Duyuru Detayı</h5>
                                <small class="text-muted">Kurumsal Bildirim</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="homeDuyuruModalResimContainer" class="mb-4 text-center d-none">
                            <img id="homeDuyuruModalResim" src="" class="img-fluid rounded-3 shadow-sm" style="max-height: 280px; width: 100%; object-fit: cover;">
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-3 border-bottom">
                            <h4 class="fw-bold text-dark mb-0" id="homeDuyuruModalBaslik"></h4>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill" id="homeDuyuruModalTarih" style="font-size: 11px;"></span>
                                <span class="badge bg-soft-warning text-warning px-3 py-2 rounded-pill d-none" id="homeDuyuruModalEtkinlikTarih" style="font-size: 11px;"></span>
                            </div>
                        </div>
                        <div id="homeDuyuruModalIcerik" class="duyuru-modal-body-content text-secondary leading-relaxed pt-2" style="font-size: 15px; word-break: break-word;">
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-3 border-0">
                        <button type="button" class="btn btn-secondary px-4 fw-semibold shadow-sm" data-bs-dismiss="modal">Kapat</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function openHomeDuyuruModal(el) {
                let duyuruData;
                try {
                    const raw = typeof el === 'string' ? el : el.getAttribute('data-duyuru');
                    duyuruData = typeof raw === 'string' ? JSON.parse(raw) : raw;
                } catch(e) {
                    console.error('Duyuru verisi okunamadı', e);
                    return;
                }

                if (!duyuruData) return;

                $('#homeDuyuruModalBaslik').text(duyuruData.baslik || '');
                $('#homeDuyuruModalTarih').html('<i class="bx bx-calendar me-1"></i> Yayın: ' + (duyuruData.tarih || ''));

                if (duyuruData.etkinlik_tarihi) {
                    $('#homeDuyuruModalEtkinlikTarih').html('<i class="bx bx-time-five me-1"></i> Son Tarih: ' + duyuruData.etkinlik_tarihi).removeClass('d-none');
                } else {
                    $('#homeDuyuruModalEtkinlikTarih').addClass('d-none');
                }

                if (duyuruData.resim) {
                    $('#homeDuyuruModalResim').attr('src', duyuruData.resim);
                    $('#homeDuyuruModalResimContainer').removeClass('d-none');
                } else {
                    $('#homeDuyuruModalResimContainer').addClass('d-none');
                }

                let icerikHtml = duyuruData.icerik || '';
                if (!/<[a-z][\s\S]*>/i.test(icerikHtml)) {
                    icerikHtml = icerikHtml.split(/\r?\n\r?\n/).map(p => '<p>' + p.replace(/\r?\n/g, '<br>') + '</p>').join('');
                }
                $('#homeDuyuruModalIcerik').html(icerikHtml);

                $('#modalDuyuruDetay').modal('show');
            }
        </script>

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
                                <input type="hidden" name="hesaba_isle" value="1">
                                <input class="form-check-input" type="checkbox" id="hesabaIsle" value="1" checked disabled>
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
                visibility: hidden !important;
            }

            #dashboard-widgets .widget-item.widget-hidden {
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
                background: #222830;
                border-color: #2b333e;
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
                    'widget-personel-arac-ozet',
                    'widget-talep-nobet-ozet'
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
                    runDashboardGridLayout(true);
                    if (pageContent) {
                        pageContent.classList.remove('dashboard-content-pending');
                        pageContent.style.display = '';
                    }
                    if (pageSkeleton) pageSkeleton.style.display = 'none';
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

                window.__revealDashboard = revealDashboardPage;
                prepareStagedWidgets();

                const scheduleDashboardReveal = () => {
                    window.requestAnimationFrame(() => {
                        setTimeout(revealDashboardPage, 60);
                    });
                };

                setTimeout(() => {
                    if (!dashboardRevealed) {
                        revealDashboardPage();
                    }
                }, 3000);

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

                // Log Detay Modal (Delegated handler for dynamic widgets)
                $(document).on('click', '.btn-log-detay', function () {
                    var title = this.dataset.title;
                    var user = this.dataset.user;
                    var date = this.dataset.date;
                    var content = this.dataset.content;
                    document.getElementById('logDetayTitle').textContent = title;
                    document.getElementById('logDetayUser').textContent = user;
                    document.getElementById('logDetayDate').textContent = date;

                    if (title.includes('Yapay Zeka')) {
                        // AI Sorgusu Gösterimi
                        let mainPrompt = content;
                        let jsonPart = null;
                        let responseText = this.dataset.response || '';

                        let match = content.match(/^(.*?)(?:\s*(?:Bordro Bağlamı|Bağlam|Context|Veri Seti|Veri|JSON|Payload)\s*:\s*|\s+)(\{.*)$/s);
                        if (match) {
                            mainPrompt = match[1].trim();
                            jsonPart = match[2].trim();
                        } else {
                            let match2 = content.match(/^(.*?)(?:\n\s*|\s+)(\{.*)$/s);
                            if (match2) {
                                mainPrompt = match2[1].trim();
                                jsonPart = match2[2].trim();
                            }
                        }

                        let aiHtml = `<div class="ai-prompt-box p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 font-size-11">
                                    <i class="bx bx-bot me-1"></i>Sorgu Metni (Prompt)
                                </span>
                            </div>
                            <div class="font-size-13 text-dark fw-medium" style="line-height:1.6;white-space:pre-wrap;">${mainPrompt}</div>
                        </div>`;

                        if (jsonPart) {
                            let formattedJson = jsonPart;
                            try {
                                formattedJson = JSON.stringify(JSON.parse(jsonPart), null, 2);
                            } catch (e) {}

                            aiHtml += `<div class="ai-payload-box rounded-3 overflow-hidden mb-3" style="border:1px solid #e2e8f0;">
                                <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light border-bottom">
                                    <span class="font-size-12 fw-semibold text-secondary">
                                        <i class="bx bx-data me-1 text-primary"></i>Bağlam Veri Seti (JSON Payload)
                                    </span>
                                    <span class="badge bg-light text-muted border font-size-10">JSON</span>
                                </div>
                                <pre class="m-0 p-3 bg-dark text-light font-monospace font-size-11" style="max-height:260px;overflow:auto;border-radius:0 0 6px 6px;"><code>${formattedJson}</code></pre>
                            </div>`;
                        }

                        if (responseText && responseText.trim() !== '') {
                            aiHtml += `<div class="ai-response-box p-3 rounded-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 font-size-11">
                                        <i class="bx bx-check-circle me-1"></i>Model Yanıtı
                                    </span>
                                </div>
                                <div class="font-size-13 text-dark" style="line-height:1.6;white-space:pre-wrap;">${responseText}</div>
                            </div>`;
                        }

                        document.getElementById('logDetayContent').innerHTML = aiHtml;
                        document.getElementById('logDetayContent').style.whiteSpace = 'normal';
                    } else if (content.includes('(Güncellenen veriler: {')) {
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
                function gridColumnSpan(el) {
                    const cs = getComputedStyle(el);
                    const m = /span\s+(\d+)/.exec(cs.gridColumnEnd) || /span\s+(\d+)/.exec(cs.gridColumnStart);
                    return m ? Math.min(12, parseInt(m[1], 10)) : 12;
                }

                function unpinGridWidget(el) {
                    el.removeAttribute('data-grid-x');
                    el.removeAttribute('data-grid-y');
                    el.style.removeProperty('--gx');
                    el.style.removeProperty('--gy');
                }

                let gridLayoutRaf = null;
                let gridForceNext = false;
                let gridLastSignature = '';
                let gridLastHeight = 0;
                function layoutDashboardGrid(force) {
                    if (force) gridForceNext = true;
                    if (gridLayoutRaf) cancelAnimationFrame(gridLayoutRaf);
                    gridLayoutRaf = requestAnimationFrame(function () { runDashboardGridLayout(false); });
                }

                function dashboardGridSignature(container) {
                    let sig = window.innerWidth + '|' + container.getElementsByTagName('*').length;
                    for (let i = 0; i < container.children.length; i++) {
                        const el = container.children[i];
                        sig += '|' + el.id + el.className + (el.getAttribute('style') || '');
                    }
                    return sig;
                }

                function runDashboardGridLayout(force) {
                    gridLayoutRaf = null;
                    force = force === true || gridForceNext;
                    gridForceNext = false;
                    const container = document.getElementById('dashboard-widgets');
                    if (!container || container.classList.contains('free-layout-active')) return;
                    const pendingContent = document.getElementById('dashboard-page-content');
                    if (!force && pendingContent && pendingContent.classList.contains('dashboard-content-pending')) return;
                    if (!force && dashboardGridSignature(container) === gridLastSignature) return;
                    const rowUnit = 8;
                    const prevMinHeight = container.style.minHeight;
                    const prevScrollY = window.scrollY;
                    container.style.minHeight = Math.max(gridLastHeight || 0, container.offsetHeight) + 'px';
                    const items = Array.from(container.children).filter(function (el) {
                        return el.classList.contains('widget-item') && getComputedStyle(el).display !== 'none';
                    });

                    items.forEach(function (el) {
                        if (el.hasAttribute('data-grid-x')) {
                            const span = gridColumnSpan(el);
                            const x = parseInt(el.getAttribute('data-grid-x'), 10);
                            if (x + span - 1 > 12) {
                                const fixedX = Math.max(1, 13 - span);
                                el.setAttribute('data-grid-x', fixedX);
                                el.style.setProperty('--gx', fixedX);
                            }
                        }
                        if (el.hasAttribute('data-grid-y')) el.style.setProperty('--gy', el.getAttribute('data-grid-y'));
                        el.style.alignSelf = 'start';
                        el.style.gridRowEnd = 'auto';
                    });
                    const info = items.map(function (el) {
                        const cs = getComputedStyle(el);
                        const h = el.getBoundingClientRect().height + (parseFloat(cs.marginTop) || 0) + (parseFloat(cs.marginBottom) || 0);
                        return {
                            el: el,
                            span: Math.max(1, Math.ceil(h / rowUnit)),
                            fixed: el.hasAttribute('data-grid-height') || el.hasAttribute('data-grid-x') || el.classList.contains('widget-collapsed')
                        };
                    });
                    gridLastHeight = Math.round(container.getBoundingClientRect().height);
                    info.forEach(function (i) {
                        i.el.style.gridRowEnd = 'span ' + i.span;
                        i.el.style.alignSelf = '';
                    });

                    const groups = {};
                    info.forEach(function (i) {
                        if (i.fixed) return;
                        const key = Math.round(i.el.getBoundingClientRect().top / rowUnit);
                        (groups[key] = groups[key] || []).push(i);
                    });
                    Object.keys(groups).forEach(function (key) {
                        const list = groups[key];
                        const max = Math.max.apply(null, list.map(function (i) { return i.span; }));
                        list.forEach(function (i) {
                            if (i.span !== max) i.el.style.gridRowEnd = 'span ' + max;
                        });
                    });

                    for (let pass = 0; pass < 6; pass++) {
                        let moved = false;
                        const rects = items.map(function (el) { return { el: el, r: el.getBoundingClientRect() }; });
                        rects.forEach(function (cur) {
                            if (!cur.el.hasAttribute('data-grid-y')) return;
                            let need = 0;
                            rects.forEach(function (other) {
                                if (other === cur) return;
                                const horizontal = Math.min(cur.r.right, other.r.right) - Math.max(cur.r.left, other.r.left);
                                const vertical = Math.min(cur.r.bottom, other.r.bottom) - Math.max(cur.r.top, other.r.top);
                                const otherGap = parseFloat(getComputedStyle(other.el).marginBottom) || 0;
                                if (horizontal > 4 && vertical > -otherGap + 4 && other.r.top < cur.r.top) {
                                    need = Math.max(need, other.r.bottom + otherGap - cur.r.top);
                                }
                            });
                            if (need > 0) {
                                const curY = parseInt(getComputedStyle(cur.el).getPropertyValue('--gy'), 10) || parseInt(cur.el.getAttribute('data-grid-y'), 10) || 1;
                                cur.el.style.setProperty('--gy', curY + Math.ceil(need / rowUnit));
                                moved = true;
                            }
                        });
                        if (!moved) break;
                    }
                    container.style.minHeight = prevMinHeight;
                    if (Math.abs(window.scrollY - prevScrollY) > 1) window.scrollTo({ left: window.scrollX, top: prevScrollY, behavior: 'instant' });
                    gridLastSignature = dashboardGridSignature(container);
                }

                window.addEventListener('resize', function () { layoutDashboardGrid(true); });
                window.addEventListener('load', function () {
                    layoutDashboardGrid(true);
                    [800, 2000, 4500].forEach(function (delay) { setTimeout(function () { layoutDashboardGrid(true); }, delay); });
                });

                (function () {
                    const gridContainer = document.getElementById('dashboard-widgets');
                    if (!gridContainer || !window.MutationObserver) return;
                    let settleTimer = null;
                    new MutationObserver(function (mutations) {
                        if (gridContainer.classList.contains('free-layout-active')) return;
                        if (mutations.some(function (m) { return m.target === gridContainer; })) {
                            runDashboardGridLayout(false);
                            return;
                        }
                        if (settleTimer) clearTimeout(settleTimer);
                        settleTimer = setTimeout(function () { settleTimer = null; runDashboardGridLayout(false); }, 500);
                    }).observe(gridContainer, { childList: true, subtree: true });
                })();

                const gridLegacyDefaultIds = ['widget-ana-slider', 'widget-personel-arac-ozet'];
                const gridMinHeight = 120;
                const gridRowStep = 20;
                const gridMinCols = 2;
                const defaultWidgetWidthClasses = {
                    'widget-ana-slider': 'col-md-4 col-xl-4',
                    'widget-personel-arac-ozet': 'col-12',
                    'widget-personel-ozet': 'col-md-4 col-xl-4',
                    'widget-personel-ozeti': 'col-md-4 col-xl-4',
                    'widget-dogum-gunleri': 'col-md-4 col-xl-4',
                    'widget-arac-ozet': 'col-md-4 col-xl-4',
                    'widget-arac-ozeti': 'col-md-4 col-xl-4',
                    'widget-talep-nobet-ozet': 'col-12 col-md-6',
                    'widget-is-operasyonlari': 'col-12'
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

                        {
                            const gCols = parseInt($(this).attr('data-grid-cols'), 10);
                            const gHeight = parseInt($(this).attr('data-grid-height'), 10);
                            if (gCols >= 2 && gCols <= 12) s.gridCols = gCols;
                            if (gHeight >= gridMinHeight) s.gridHeight = gHeight;
                            const gX = parseInt($(this).attr('data-grid-x'), 10);
                            const gY = parseInt($(this).attr('data-grid-y'), 10);
                            if (gX >= 1 && gX <= 12 && gY >= 1) {
                                s.gridX = gX;
                                s.gridY = gY;
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

                    if (!isFreeLayout) {
                        $('#dashboard-widgets > .widget-item').each(function () {
                            if (this.hasAttribute('data-default-width')) return;
                            const own = (this.className || '').split(' ').filter(cls => /^col(-[a-z]+)?-\d+$/.test(cls));
                            this.setAttribute('data-default-width', own.join(' '));
                        });
                    }

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
                    
                    if (!isFreeLayout) {
                        $('#dashboard-widgets > .widget-item').each(function () {
                            const widget = $(this);
                            const id = widget.attr('id');
                            if (!id || id === 'widget-row-break') return;
                            const s = settings[id] || {};
                            const cols = parseInt(s.gridCols, 10);
                            const height = parseInt(s.gridHeight, 10);
                            const hasCols = cols >= gridMinCols && cols <= 12;
                            const fallbackWidth = gridLegacyDefaultIds.indexOf(id) !== -1 ? defaultWidgetWidthClasses[id] : '';
                            const widthClasses = hasCols ? ['col-md-' + cols, 'col-xl-' + cols] : (fallbackWidth ? fallbackWidth.split(' ') : []);

                            if (widthClasses.length) {
                                const classes = (widget.attr('class') || '').split(' ').filter(Boolean);
                                const newClasses = classes.filter(cls => !/^col(-[a-z]+)?-\d+$/.test(cls));
                                newClasses.push(...widthClasses);
                                widget.attr('class', newClasses.join(' '));
                            }
                            if (hasCols) widget.attr('data-grid-cols', cols); else widget.removeAttr('data-grid-cols');

                            if (height >= gridMinHeight) {
                                widget.attr('data-grid-height', height).addClass('grid-sized').css('height', height + 'px');
                            } else {
                                widget.removeAttr('data-grid-height').removeClass('grid-sized').css('height', '');
                            }

                            const pinX = parseInt(s.gridX, 10);
                            const pinY = parseInt(s.gridY, 10);
                            const node = widget[0];
                            if (pinX >= 1 && pinX <= 12 && pinY >= 1) {
                                widget.attr('data-grid-x', pinX).attr('data-grid-y', pinY);
                                node.style.setProperty('--gx', pinX);
                                node.style.setProperty('--gy', pinY);
                            } else {
                                widget.removeAttr('data-grid-x').removeAttr('data-grid-y');
                                node.style.removeProperty('--gx');
                                node.style.removeProperty('--gy');
                            }

                            if (!widget.find('.grid-resize-grip').length) {
                                (widget.children('.card, .carousel').first().length ? widget.children('.card, .carousel').first() : widget).append('<div class="grid-resize-grip" title="Grid\'e göre boyutlandır"></div>');
                            }
                        });
                    }

                    if (!isFreeLayout) layoutDashboardGrid();

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
                        let gridContentTimer = null;
                        new MutationObserver(function () {
                            clearTimeout(gridContentTimer);
                            gridContentTimer = setTimeout(layoutDashboardGrid, 200);
                        }).observe(dashboardNode, { childList: true, subtree: true, characterData: true });
                    }
                });

                let gridSortable = null;
                let gridCustomDragBound = false;
                const gridDragIgnore = '.mac-control, .mac-controls, .btn, a, input, select, textarea, button, label, .custom-resize-handle, .dashboard-resize-grip, .grid-resize-grip, .dropdown-menu, [data-bs-toggle]';
                const gridDragHandle = '.mac-title-bar, .drag-handle, .card-header, .stat-card, .card, .carousel';

                function gridFindTarget(item, container, dx, dy, startRect, crPage) {
                    const colWidth = container.getBoundingClientRect().width / 12;
                    const span = gridColumnSpan(item);
                    let col = Math.min(13 - span, Math.max(1, Math.round((startRect.left + dx - crPage.left) / colWidth) + 1));
                    let row = Math.max(1, Math.round((startRect.top + dy - crPage.top) / 8) + 1);
                    const height = startRect.height;
                    const cr = container.getBoundingClientRect();
                    const others = Array.from(container.children).filter(function (w) {
                        return w !== item && w.classList.contains('widget-item') && w.hasAttribute('data-grid-x') && w.getBoundingClientRect().width > 0;
                    }).map(function (w) {
                        const r = w.getBoundingClientRect();
                        return { left: r.left - cr.left, right: r.right - cr.left, top: r.top - cr.top, bottom: r.bottom - cr.top };
                    });
                    const collides = function (c, r) {
                        const left = (c - 1) * colWidth;
                        const right = left + span * colWidth;
                        const top = (r - 1) * 8;
                        const bottom = top + height;
                        return others.some(function (o) {
                            return left < o.right - 1 && right > o.left + 1 && top < o.bottom - 1 && bottom > o.top + 1;
                        });
                    };
                    if (others.length && collides(col, row)) {
                        let best = null;
                        for (let dc = -(col - 1); dc <= 13 - span - col; dc++) {
                            for (let dr = -160; dr <= 160; dr++) {
                                const r = row + dr;
                                if (r < 1) continue;
                                const cost = Math.abs(dc) * colWidth + Math.abs(dr) * 8;
                                if (best && cost >= best.cost) continue;
                                if (!collides(col + dc, r)) best = { c: col + dc, r: r, cost: cost };
                            }
                        }
                        if (best) {
                            col = best.c;
                            row = best.r;
                        }
                    }
                    return { col: col, row: row, span: span, colWidth: colWidth, height: height };
                }

                function gridPointerDown(e) {
                    if (e.pointerType === 'mouse' && e.button !== 0) return;
                    if (window.innerWidth < 768) return;
                    const container = document.getElementById('dashboard-widgets');
                    if (!container || container.classList.contains('free-layout-active')) return;
                    if (!e.target.closest || e.target.closest(gridDragIgnore)) return;
                    const item = e.target.closest('.widget-item');
                    if (!item || item.parentNode !== container) return;
                    const hasCardShell = item.querySelector(':scope > .card, :scope > .carousel');
                    if (hasCardShell && !e.target.closest(gridDragHandle)) return;

                    const rect0 = item.getBoundingClientRect();
                    const startRect = {
                        left: rect0.left + window.scrollX,
                        top: rect0.top + window.scrollY,
                        width: rect0.width,
                        height: rect0.height
                    };
                    const startX = e.clientX + window.scrollX;
                    const startY = e.clientY + window.scrollY;
                    let lastX = e.clientX;
                    let lastY = e.clientY;
                    let dragging = false;
                    let preview = null;
                    let rafId = null;
                    let crPage = null;

                    const update = function () {
                        const dx = lastX + window.scrollX - startX;
                        const dy = lastY + window.scrollY - startY;
                        item.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
                        const target = gridFindTarget(item, container, dx, dy, startRect, crPage);
                        preview.style.left = ((target.col - 1) * target.colWidth) + 'px';
                        preview.style.top = ((target.row - 1) * 8) + 'px';
                        preview.style.width = (target.span * target.colWidth) + 'px';
                        preview.style.height = target.height + 'px';
                        preview.dataset.col = target.col;
                        preview.dataset.row = target.row;
                    };

                    const loop = function () {
                        if (!dragging) return;
                        if (lastY < 90) window.scrollBy(0, -18);
                        else if (lastY > window.innerHeight - 90) window.scrollBy(0, 18);
                        update();
                        rafId = requestAnimationFrame(loop);
                    };

                    const blockNativeDrag = function (ev) { ev.preventDefault(); };
                    document.addEventListener('dragstart', blockNativeDrag, true);

                    const begin = function () {
                        dragging = true;
                        const cr = container.getBoundingClientRect();
                        crPage = { left: cr.left + window.scrollX, top: cr.top + window.scrollY };
                        preview = document.createElement('div');
                        preview.className = 'grid-drop-preview';
                        container.appendChild(preview);
                        item.classList.add('grid-dragging');
                        document.body.classList.add('grid-dragging-active');
                        rafId = requestAnimationFrame(loop);
                    };

                    const onMove = function (ev) {
                        lastX = ev.clientX;
                        lastY = ev.clientY;
                        if (!dragging) {
                            const moved = Math.abs(ev.clientX + window.scrollX - startX) + Math.abs(ev.clientY + window.scrollY - startY);
                            if (moved < 6) return;
                            begin();
                        }
                        ev.preventDefault();
                    };

                    const finish = function (ev) {
                        document.removeEventListener('pointermove', onMove, true);
                        document.removeEventListener('pointerup', finish, true);
                        document.removeEventListener('pointercancel', finish, true);
                        document.removeEventListener('dragstart', blockNativeDrag, true);
                        if (!dragging) return;
                        dragging = false;
                        if (rafId) cancelAnimationFrame(rafId);
                        const col = parseInt(preview.dataset.col, 10);
                        const row = parseInt(preview.dataset.row, 10);
                        preview.remove();
                        item.classList.remove('grid-dragging');
                        item.style.transform = '';
                        document.body.classList.remove('grid-dragging-active');
                        if (ev.type === 'pointerup' && col >= 1 && row >= 1) {
                            item.setAttribute('data-grid-x', col);
                            item.setAttribute('data-grid-y', row);
                            item.style.setProperty('--gx', col);
                            item.style.setProperty('--gy', row);
                            layoutDashboardGrid(true);
                            saveDashboardConfig();
                        }
                        const swallow = function (clickEv) {
                            clickEv.stopPropagation();
                            clickEv.preventDefault();
                        };
                        document.addEventListener('click', swallow, { capture: true, once: true });
                        setTimeout(function () { document.removeEventListener('click', swallow, true); }, 50);
                    };

                    document.addEventListener('pointermove', onMove, true);
                    document.addEventListener('pointerup', finish, true);
                    document.addEventListener('pointercancel', finish, true);
                }

                window.addEventListener('resize', function () {
                    if (gridSortable) gridSortable.option('disabled', window.innerWidth >= 768);
                });

                function initGridSortable() {
                    const container = document.getElementById('dashboard-widgets');
                    if (!container) return;
                    if (!gridCustomDragBound) {
                        container.addEventListener('pointerdown', gridPointerDown);
                        gridCustomDragBound = true;
                    }
                    if (typeof Sortable !== 'undefined' && !gridSortable) {
                        gridSortable = new Sortable(container, {
                            animation: 150,
                            disabled: window.innerWidth >= 768,
                            handle: '.mac-title-bar, .drag-handle, .card-header, .stat-card, .card',
                            filter: '.mac-control, .btn, a, input, select, textarea, .custom-resize-handle, .dashboard-resize-grip, .grid-resize-grip, .mac-controls',
                            preventOnFilter: true,
                            ghostClass: 'bg-light',
                            onEnd: function (evt) {
                                unpinGridWidget(evt.item);
                                layoutDashboardGrid(true);
                                saveDashboardConfig();
                            }
                        });
                    }
                }

                function destroyGridSortable() {
                    if (gridSortable) {
                        gridSortable.destroy();
                        gridSortable = null;
                    }
                    const container = document.getElementById('dashboard-widgets');
                    if (container && gridCustomDragBound) {
                        container.removeEventListener('pointerdown', gridPointerDown);
                        gridCustomDragBound = false;
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
                        applyWidgetSettings();
                        // Redraw standard layout
                        initMacControls();
                        saveDashboardConfig(true);
                        setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 100);
                    }
                });

                $(document).on('pointerdown.gridResize', '#dashboard-widgets .grid-resize-grip', function (e) {
                    if ($('#switch-free-layout').is(':checked')) return;
                    e.preventDefault();
                    e.stopPropagation();

                    const widget = $(this).closest('.widget-item');
                    const container = $('#dashboard-widgets');
                    const startX = e.clientX + window.scrollX;
                    const startY = e.clientY + window.scrollY;
                    const startWidth = widget.outerWidth();
                    const startHeight = widget.outerHeight();
                    const colWidth = container.width() / 12;
                    let lastX = e.clientX;
                    let lastY = e.clientY;
                    let resizing = true;
                    widget.addClass('grid-resizing');

                    const applySize = function () {
                        const cols = Math.min(12, Math.max(gridMinCols, Math.round((startWidth + (lastX + window.scrollX - startX)) / colWidth)));
                        const height = Math.max(gridMinHeight, Math.round((startHeight + (lastY + window.scrollY - startY)) / gridRowStep) * gridRowStep);
                        if (String(cols) === widget.attr('data-grid-cols') && String(height) === widget.attr('data-grid-height')) return;

                            const classes = (widget.attr('class') || '').split(' ').filter(cls => cls && !/^col(-[a-z]+)?-\d+$/.test(cls));
                            classes.push('col-md-' + cols, 'col-xl-' + cols);
                            widget.attr('class', classes.join(' '))
                                .attr('data-grid-cols', cols)
                                .attr('data-grid-height', height)
                                .attr('data-grid-label', cols + '/12 × ' + height + 'px')
                                .addClass('grid-sized')
                                .css('height', height + 'px');
                            layoutDashboardGrid(true);
                    };

                    const edgeScroll = function () {
                        if (!resizing) return;
                        if (lastY > window.innerHeight - 70) window.scrollBy(0, 16);
                        else if (lastY < 70) window.scrollBy(0, -16);
                        applySize();
                        requestAnimationFrame(edgeScroll);
                    };
                    requestAnimationFrame(edgeScroll);

                    $(document)
                        .off('pointermove.gridResize')
                        .on('pointermove.gridResize', function (p) {
                            lastX = p.clientX;
                            lastY = p.clientY;
                            applySize();
                        })
                        .off('pointerup.gridResize pointercancel.gridResize')
                        .on('pointerup.gridResize pointercancel.gridResize', function () {
                            resizing = false;
                            $(document).off('pointermove.gridResize pointerup.gridResize pointercancel.gridResize');
                            widget.removeClass('grid-resizing').removeAttr('data-grid-label');
                            saveDashboardConfig();
                            setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 100);
                        });
                });

                const dashWidthOptions = [['', 'Genişlik: Varsayılan'], [3, '¼ · 3/12'], [4, '⅓ · 4/12'], [6, '½ · 6/12'], [8, '⅔ · 8/12'], [9, '¾ · 9/12'], [12, 'Tam · 12/12']];
                const dashHeightOptions = [['', 'Yükseklik: Otomatik'], [120, '120 px'], [160, '160 px'], [200, '200 px'], [240, '240 px'], [280, '280 px'], [320, '320 px'], [360, '360 px'], [400, '400 px'], [480, '480 px'], [560, '560 px'], [640, '640 px']];

                function dashSizeSelect(kind, options) {
                    const $sel = $('<select class="dash-size-select" aria-label="' + (kind === 'cols' ? 'Kart genişliği' : 'Kart yüksekliği') + '"></select>').attr('data-kind', kind);
                    options.forEach(function (o) { $sel.append($('<option></option>').attr('value', o[0]).text(o[1])); });
                    return $sel;
                }

                function buildDashSizeControls() {
                    $('#dash-card-settings-list > li').each(function () {
                        const $li = $(this);
                        const id = $li.find('.widget-toggle').first().attr('data-widget');
                        if (!id || $li.find('.dash-size-row').length) return;
                        const $row = $('<div class="dash-size-row"></div>').attr('data-widget', id);
                        $row.append(dashSizeSelect('cols', dashWidthOptions), dashSizeSelect('height', dashHeightOptions));
                        $li.append($row);
                    });
                }

                function setDashSizeValue(sel, value, suffix) {
                    if (value !== '' && !Array.prototype.some.call(sel.options, function (op) { return op.value === String(value); })) {
                        const opt = document.createElement('option');
                        opt.value = String(value);
                        opt.textContent = value + suffix;
                        sel.appendChild(opt);
                    }
                    sel.value = String(value);
                }

                function syncDashSizeControls() {
                    $('#dash-card-settings-list .dash-size-row').each(function () {
                        const $row = $(this);
                        const widget = $('#' + $row.attr('data-widget'));
                        $row.toggle(widget.length > 0 && widget.closest('#dashboard-widgets').length > 0);
                        setDashSizeValue($row.find('[data-kind="cols"]')[0], widget.attr('data-grid-cols') || '', '/12');
                        setDashSizeValue($row.find('[data-kind="height"]')[0], widget.attr('data-grid-height') || '', ' px');
                    });
                }

                function applyDashSize(widget, cols, height) {
                    const classes = (widget.attr('class') || '').split(' ').filter(cls => cls && !/^col(-[a-z]+)?-\d+$/.test(cls));
                    if (cols) {
                        classes.push('col-md-' + cols, 'col-xl-' + cols);
                        widget.attr('data-grid-cols', cols);
                    } else {
                        const defaults = widget.attr('data-default-width') || defaultWidgetWidthClasses[widget.attr('id')] || '';
                        if (defaults) classes.push(...defaults.split(' '));
                        widget.removeAttr('data-grid-cols');
                    }
                    widget.attr('class', classes.join(' '));
                    if (height) {
                        widget.attr('data-grid-height', height).addClass('grid-sized').css('height', height + 'px');
                    } else {
                        widget.removeAttr('data-grid-height').removeClass('grid-sized').css('height', '');
                    }
                    layoutDashboardGrid(true);
                    saveDashboardConfig();
                    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 100);
                }

                buildDashSizeControls();
                $(document).on('show.bs.dropdown', '#dashboard-settings-toolbar .dropdown', function () {
                    buildDashSizeControls();
                    syncDashSizeControls();
                });
                $(document).on('change', '.dash-size-row .dash-size-select', function () {
                    const $row = $(this).closest('.dash-size-row');
                    const widget = $('#' + $row.attr('data-widget'));
                    if (!widget.length) return;
                    const cols = parseInt($row.find('[data-kind="cols"]').val(), 10) || 0;
                    const height = parseInt($row.find('[data-kind="height"]').val(), 10) || 0;
                    applyDashSize(widget, cols, height);
                });

                $(document).on('dblclick', '#dashboard-widgets .grid-resize-grip', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const widget = $(this).closest('.widget-item');
                    widget.removeAttr('data-grid-cols data-grid-height').removeClass('grid-sized').css('height', '');
                    unpinGridWidget(widget[0]);
                    const defaults = widget.attr('data-default-width') || defaultWidgetWidthClasses[widget.attr('id')];
                    if (defaults) {
                        const classes = (widget.attr('class') || '').split(' ').filter(cls => cls && !/^col(-[a-z]+)?-\d+$/.test(cls));
                        classes.push(...defaults.split(' '));
                        widget.attr('class', classes.join(' '));
                    }
                    saveDashboardConfig();
                    layoutDashboardGrid(true);
                    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 100);
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
                            'widget-personel-arac-ozet'      : 'Personel ve Araç Durumu',
                            'widget-personel-ozeti'          : 'Personel Durumu',
                            'widget-personel-ozet'           : 'Personel Durumu',
                            'widget-dogum-gunleri'           : 'Doğum Günleri',
                            'widget-arac-ozeti'              : 'Araç Durumu',
                            'widget-arac-ozet'               : 'Araç Durumu',
                            'widget-takvim-alarmlar'         : 'Görev & Evrak Takvimi',
                            'widget-gorev-evrak-takvimi'     : 'Görev & Evrak Takvimi',
                            'widget-bekleyen-talepler'       : 'Bekleyen Talepler',
                            'widget-gec-kalanlar'            : 'Geç Kalanlar',
                            'widget-nobetciler'              : 'Bugünkü Nöbetçiler',
                            'widget-talep-nobet-ozet'        : 'Talep, Geç Kalan ve Nöbetçi Özeti',
                            'widget-gunluk-muhurleme'        : 'Günlük Mühürleme',
                            'widget-gunluk-kesme-acma'       : 'Günlük Kesme/Açma',
                            'widget-gunluk-endeks-okuma'     : 'Endeks Okuma',
                            'widget-gunluk-sayac-degisimi'   : 'Sayaç Değişimi',
                            'widget-gunluk-kacak'            : 'Kaçak Kontrolü',
                            'widget-kacak-sayisi'            : 'Kaçak Sayısı',
                            'widget-ihbar-sayisi'            : 'İhbar Sayısı',
                            'widget-is-operasyonlari'        : 'İş Operasyonları',
                            'widget-izinliler'               : 'İzinli Personeller',
                            'widget-izindekiler'             : 'İzinde Olanlar',
                            'widget-bildirimler'             : 'Görev & Bildirimler',
                            'widget-talepler'                : 'Talep Yönetimi',
                            'widget-yaklasan-gorevler'       : 'Yaklaşan Görevler',
                            'widget-endeks-karsilastirma'    : 'Endeks Karşılaştırma',
                            'widget-kesme-karsilastirma'     : 'Kesme/Açma Karşılaştırma',
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
                                widgetItem.css({ height: '40px', minHeight: '40px', overflow: 'hidden' });
                            } else {
                                const rh = widgetItem.data('restore-h');
                                widgetItem.css({ height: (rh && rh !== '40px') ? rh : '', minHeight: '', overflow: '' });
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

                    if (pageContent.length && pageContent.hasClass('dashboard-content-pending') && typeof window.__revealDashboard === 'function') {
                        window.__revealDashboard();
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
                const isFreeLayoutActive = false;
                localStorage.setItem('switch_free_layout', 'false');
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
                    if (statValue.length) {
                        const oldValue = parseInt(statValue.text().replace(/[^0-9]/g, '')) || 0;
                        animateValue(statValue[0], oldValue, newValue, 800);
                    }

                    cardBody.find('.stat-local-value').each(function () {
                        const valEl = $(this);
                        const valNew = parseInt(valEl.data(mode)) || 0;
                        const valOld = parseInt(valEl.text().replace(/[^0-9]/g, '')) || 0;
                        animateValue(valEl[0], valOld, valNew, 800);
                    });
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
                        if (typeof window.__revealDashboard === 'function') window.__revealDashboard();
                    };

                    // 1. Client-Side Cache (Instant Feel)
                    const isFreeLayout = $('#switch-free-layout').is(':checked');
                    const cacheKey = 'dashboard_cache_v12_<?php echo $_SESSION['user_id']; ?>' + (isFreeLayout ? '_free' : '_grid');
                    const cachedData = localStorage.getItem(cacheKey);
                    
                    if (cachedData && !force) {
                        try {
                            const data = JSON.parse(cachedData);
                            if (data.stats) {
                                try { renderOperationalStats(data.stats); } catch (statsErr) { console.error('Operasyon istatistikleri çizilemedi', statsErr); }
                            }
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
                        if (!isVisible) {
                            setWidgetVisibility(widget.id, false, { syncCheckbox: true });
                            return;
                        }

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
                                try {
                                    Object.keys(localStorage).forEach(function (k) {
                                        if (k.indexOf('dashboard_cache_') === 0 && k.indexOf('dashboard_cache_v12_') !== 0) localStorage.removeItem(k);
                                    });
                                    localStorage.setItem(cacheKey, JSON.stringify({
                                        stats: res.stats,
                                        results: res.results,
                                        time: Date.now()
                                    }));
                                } catch (storageErr) {
                                    try { localStorage.removeItem(cacheKey); } catch (e) {}
                                }

                                if (res.stats) {
                                    try { renderOperationalStats(res.stats); } catch (statsErr) { console.error('Operasyon istatistikleri çizilemedi', statsErr); }
                                }

                                // Render Widgets
                                if (res.results) {
                                    Object.keys(res.results).forEach(widgetId => {
                                        const $el = $('#' + widgetId);
                                        if ($el.length) $el.replaceWith(res.results[widgetId]);
                                    });
                                }
                                widgetIds.forEach(widgetId => {
                                    const $pending = $('#' + widgetId + '.lazy-widget');
                                    if ($pending.length) $pending.remove();
                                });

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

                    opsUpdateStats(daily, monthly, lastUpdate);
                }

                function refreshOperationalStats() { return initDashboard(true); }

                const OPS_MODE_KEY = 'dashboard_ops_mode_<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';
                const opsNumberFmt = new Intl.NumberFormat('tr-TR');
                let opsContractData = null;
                let opsContractLoading = null;

                function opsSetNumber(el, value) {
                    if (!el) return;
                    const end = Number(value) || 0;
                    const start = parseInt(String(el.textContent || '0').replace(/[^0-9]/g, ''), 10) || 0;
                    if (start === end) { el.textContent = opsNumberFmt.format(end); return; }
                    let t0 = null;
                    const step = (ts) => {
                        if (!t0) t0 = ts;
                        const p = Math.min((ts - t0) / 600, 1);
                        el.textContent = opsNumberFmt.format(Math.round(start + (end - start) * p));
                        if (p < 1) window.requestAnimationFrame(step);
                    };
                    window.requestAnimationFrame(step);
                }

                function opsCurrentMode() {
                    const active = document.querySelector('#widget-is-operasyonlari .ops-mode-btn.is-active');
                    return active ? active.dataset.mode : 'daily';
                }

                function opsRenderPeriod(mode) {
                    const root = document.getElementById('widget-is-operasyonlari');
                    if (!root) return;
                    const isContract = mode === 'contract';
                    const items = Array.from(root.querySelectorAll('.ops-item'));
                    const totals = { value: 0, rateSum: 0, rateCount: 0 };
                    const values = {};

                    items.forEach((item) => {
                        const num = item.querySelector('.ops-num');
                        const sub = item.querySelector('.ops-sub');
                        const last = item.querySelector('.ops-last');
                        const contractBox = item.querySelector('.ops-contract');
                        const rate = item.querySelector('.ops-rate');
                        if (!isContract) {
                            const v = Number(num.dataset[mode]) || 0;
                            values[item.dataset.key] = v;
                            totals.value += v;
                            opsSetNumber(num, v);
                            if (sub) {
                                sub.classList.remove('d-none');
                                const subNum = sub.querySelector('.ops-sub-num');
                                opsSetNumber(subNum, subNum.dataset[mode]);
                            }
                            if (last) last.classList.remove('d-none');
                            contractBox.classList.add('d-none');
                            item.querySelector('.ops-contract-text').classList.add('d-none');
                            rate.classList.add('d-none');
                            return;
                        }
                        if (sub) sub.classList.add('d-none');
                        if (last) last.classList.add('d-none');
                        const data = opsContractData && opsContractData.items ? opsContractData.items[item.dataset.key] : null;
                        rate.classList.remove('d-none');
                        if (!data) {
                            num.textContent = '-';
                            rate.textContent = '';
                            contractBox.classList.remove('d-none');
                            contractBox.querySelector('.ops-mini-fill').style.width = '0%';
                            item.querySelector('.ops-contract-text').classList.remove('d-none');
                            item.querySelector('.ops-contract-text').textContent = opsContractLoading ? 'Yükleniyor…' : 'Sözleşme hedefi yok';
                            return;
                        }
                        const oran = Number(data.oran) || 0;
                        totals.value += Number(data.yapilan) || 0;
                        totals.rateSum += Math.min(Math.max(oran, 0), 100);
                        totals.rateCount++;
                        opsSetNumber(num, data.yapilan);
                        rate.textContent = '%' + oran.toLocaleString('tr-TR', { maximumFractionDigits: 1 });
                        contractBox.classList.remove('d-none');
                        contractBox.querySelector('.ops-mini-fill').style.width = Math.min(Math.max(oran, 0), 100) + '%';
                        const text = item.querySelector('.ops-contract-text');
                        text.classList.remove('d-none');
                        text.textContent = 'Kalan ' + opsNumberFmt.format(data.kalan || 0);
                        text.title = 'Hedef ' + opsNumberFmt.format(data.hedef || 0) + ' · Hakediş ' + opsNumberFmt.format(data.hakedis || 0) + ' + Güncel ' + opsNumberFmt.format(data.guncel || 0);
                    });

                    opsSetNumber(root.querySelector('.ops-total-num'), totals.value);
                    const periodLabel = root.querySelector('.ops-total-period');
                    if (periodLabel) periodLabel.textContent = isContract ? 'Sözleşme Dönemi' : (mode === 'monthly' ? 'Bu Ay' : 'Bugün');
                    root.classList.toggle('is-contract', isContract);

                    const contractBar = root.querySelector('.ops-bar-contract');
                    const contractTrack = root.querySelector('.ops-bar-track');
                    if (contractTrack) contractTrack.classList.toggle('d-none', !isContract);
                    root.querySelectorAll('.ops-bar-seg').forEach((seg) => {
                        seg.classList.toggle('d-none', isContract);
                        if (!isContract) {
                            const v = values[seg.dataset.key] || 0;
                            seg.style.width = (totals.value > 0 ? (v / totals.value * 100) : 0) + '%';
                            seg.title = seg.getAttribute('title').split(':')[0] + ': ' + opsNumberFmt.format(v);
                        }
                    });
                    if (contractBar) {
                        const pct = totals.rateCount > 0 ? totals.rateSum / totals.rateCount : 0;
                        contractBar.style.width = pct + '%';
                        contractBar.title = 'Kalemlerin ortalama gerçekleşmesi: %' + pct.toLocaleString('tr-TR', { maximumFractionDigits: 1 });
                    }

                    window.requestAnimationFrame(function () { window.dispatchEvent(new Event('resize')); });

                    const note = root.querySelector('.ops-contract-note');
                    if (!note) return;
                    if (!isContract) { note.textContent = mode === 'monthly' ? 'Bu ay yapılan işlerin dağılımı' : 'Bugün yapılan işlerin dağılımı'; return; }
                    const period = opsContractData ? opsContractData.period : null;
                    if (opsContractLoading && !opsContractData) {
                        note.textContent = 'Sözleşme verileri yükleniyor…';
                    } else if (!period) {
                        note.textContent = opsContractData ? 'Aktif sözleşme veya hedef miktar bulunamadı.' : 'Sözleşme verileri alınamadı.';
                    } else {
                        note.textContent = '';
                        const label = document.createElement('span');
                        label.textContent = 'Sözleşme dönemi: ';
                        const range = document.createElement('b');
                        range.textContent = (period.start || '-') + ' – ' + (period.end || '-');
                        note.append(label, range);
                        if (period.last_hakedis) {
                            const lh = document.createElement('span');
                            lh.textContent = ' · Son hakediş: ';
                            const lhb = document.createElement('b');
                            lhb.textContent = period.last_hakedis;
                            note.append(lh, lhb);
                        }
                    }
                }

                function opsLoadContract(force = false) {
                    if (opsContractLoading) return opsContractLoading;
                    if (opsContractData && !force) return $.Deferred().resolve().promise();
                    opsContractLoading = $.ajax({
                        url: 'views/home/api.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { action: 'get-operasyon-donem' }
                    }).done(function (res) {
                        if (res && res.status === 'success') opsContractData = res.data || { period: null, items: {} };
                    }).always(function () {
                        opsContractLoading = null;
                        if (opsCurrentMode() === 'contract') opsRenderPeriod('contract');
                    });
                    return opsContractLoading;
                }

                function opsSetMode(mode) {
                    const root = document.getElementById('widget-is-operasyonlari');
                    if (!root) return;
                    const btn = root.querySelector('.ops-mode-btn[data-mode="' + mode + '"]');
                    if (!btn) mode = 'daily';
                    root.querySelectorAll('.ops-mode-btn').forEach((b) => {
                        const on = b.dataset.mode === mode;
                        b.classList.toggle('is-active', on);
                        b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    try { localStorage.setItem(OPS_MODE_KEY, mode); } catch (e) {}
                    opsRenderPeriod(mode);
                    if (mode === 'contract') opsLoadContract();
                }

                function opsUpdateStats(daily, monthly, lastUpdate) {
                    const root = document.getElementById('widget-is-operasyonlari');
                    if (!root) return;
                    root.querySelectorAll('.ops-item').forEach((tile) => {
                        const stat = tile.dataset.stat;
                        const num = tile.querySelector('.ops-num');
                        if (stat in daily) num.dataset.daily = Number(daily[stat]) || 0;
                        if (stat in monthly) num.dataset.monthly = Number(monthly[stat]) || 0;
                        const sub = tile.querySelector('.ops-sub');
                        if (sub) {
                            const subStat = sub.dataset.subStat;
                            const subNum = sub.querySelector('.ops-sub-num');
                            if (subStat in daily) subNum.dataset.daily = Number(daily[subStat]) || 0;
                            if (subStat in monthly) subNum.dataset.monthly = Number(monthly[subStat]) || 0;
                        }
                        const last = tile.querySelector('.ops-last[data-update-key]');
                        if (last) {
                            const key = last.dataset.updateKey;
                            if (key in lastUpdate) last.querySelector('.last-update-value').textContent = formatDashboardTimestamp(lastUpdate[key]);
                            if ((key + '_user') in lastUpdate) last.querySelector('.last-update-user-value').textContent = lastUpdate[key + '_user'] || '-';
                        }
                    });
                    opsRenderPeriod(opsCurrentMode());
                }

                $(document).on('click', '#widget-is-operasyonlari .ops-mode-btn', function () {
                    opsSetMode(this.dataset.mode);
                });

                $(document).on('click', '#widget-is-operasyonlari .ops-sync-all', async function () {
                    const $btn = $(this);
                    if ($btn.hasClass('syncing')) return;
                    const $card = $btn.closest('.card');
                    const today = '<?php echo date('Y-m-d'); ?>';
                    const firmaKodu = '<?php echo (int) ($_SESSION['firma_kodu'] ?? 17); ?>';
                    const steps = [
                        { action: 'online-icmal-sorgula', active_tab: '', label: 'Endeks Okuma' },
                        { action: 'online-puantaj-sorgula', active_tab: 'kesme', label: 'Kesme / Açma' },
                        { action: 'online-puantaj-sorgula', active_tab: 'muhurleme', label: 'Mühürleme' },
                        { action: 'online-sayac-degisim-sorgula', active_tab: 'sokme_takma', label: 'Sayaç Değişimi' }
                    ];
                    $btn.addClass('syncing').prop('disabled', true).find('i').addClass('bx-spin');
                    $card.addClass('is-loading');
                    if (!$card.find('.card-loading-overlay').length) {
                        $card.append('<div class="card-loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
                    }
                    const lines = [];
                    let errors = 0;
                    for (const step of steps) {
                        try {
                            const raw = await $.ajax({
                                url: 'views/puantaj/api.php',
                                type: 'POST',
                                dataType: 'text',
                                data: { action: step.action, active_tab: step.active_tab, baslangic_tarihi: today, bitis_tarihi: today, ilk_firma: firmaKodu, son_firma: firmaKodu }
                            });
                            let res = null;
                            try { res = JSON.parse(raw); } catch (parseErr) { res = null; }
                            if (res && res.status === 'success') {
                                lines.push('<b>' + step.label + ':</b> ' + (Number(res.yeni_kayit) || 0) + ' yeni kayıt');
                            } else {
                                errors++;
                                lines.push('<b>' + step.label + ':</b> <span class="text-danger">sorgulanamadı</span>');
                            }
                        } catch (err) {
                            errors++;
                            lines.push('<b>' + step.label + ':</b> <span class="text-danger">bağlantı hatası</span>');
                        }
                    }
                    opsContractData = null;
                    const done = function () {
                        $btn.removeClass('syncing').prop('disabled', false).find('i').removeClass('bx-spin');
                        $card.removeClass('is-loading');
                        if (opsCurrentMode() === 'contract') opsLoadContract(true);
                        Swal.fire({
                            icon: errors === steps.length ? 'error' : (errors ? 'warning' : 'success'),
                            title: errors ? 'Sorgulama kısmen tamamlandı' : 'Sorgulama Başarılı',
                            html: lines.join('<br>'),
                            timer: errors ? undefined : 2500,
                            showConfirmButton: !!errors
                        });
                    };
                    refreshOperationalStats().always(done);
                });

                (function () {
                    let savedMode = 'daily';
                    try { savedMode = localStorage.getItem(OPS_MODE_KEY) || 'daily'; } catch (e) {}
                    if (savedMode !== 'daily') opsSetMode(savedMode);
                })();

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
                        if ((!previous || previous === 0) && current > 0) return { text: 'Yeni', class: 'text-primary', icon: 'bx-plus', pct: 100, isNew: true };
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

                    function escapeEndeksHtml(value) {
                        return String(value ?? '').replace(/[&<>'"]/g, function (char) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char];
                        });
                    }

                    function getAbsoluteDiff(current, previous) {
                        const diff = (current || 0) - (previous || 0);
                        if (diff === 0) return 'Fark yok';
                        return (diff > 0 ? '+' : '') + formatNumber(diff) + ' okuma';
                    }

                    function buildOverview(items, periods, type) {
                        if (!items.length || !periods.length) return '';
                        const currentTotal = items.reduce((sum, item) => sum + (item.lastVal || 0), 0);
                        const previousTotal = items.reduce((sum, item) => sum + (item.periodValues ? item.periodValues[item.periodValues.length - 2] : item.pPeriods[item.pPeriods.length - 2]) || 0, 0);
                        const trend = getTrendInfo(currentTotal, previousTotal);
                        const diff = currentTotal - previousTotal;
                        const dayCount = Math.max(1, parseInt(endeksCompData.gun, 10) || 1);
                        const dailyAverage = Math.round(currentTotal / dayCount);
                        const increasing = items.filter(item => item.trendPct > 0).length;
                        const decreasing = items.filter(item => item.trendPct < 0).length;
                        const scopeLabel = type === 'bolge' ? 'Aktif bölge' : 'Aktif personel';
                        const trendColor = diff >= 0 ? '#10b981' : '#ef4444';
                        const trendIcon = diff >= 0 ? 'bx-trending-up' : 'bx-trending-down';

                        return `<div class="endeks-overview">
                            <div class="endeks-kpi is-actionable endeks-total-trigger" role="button" tabindex="0" aria-label="Bu dönemin detaylı dağılımını göster" title="Dağılımı incelemek için tıklayın" style="--kpi-accent:#6366f1"><div class="endeks-kpi-label">Bu dönem toplamı <i class="bx bx-expand-alt ms-1 text-primary"></i></div><div class="endeks-kpi-value">${formatNumber(currentTotal)}</div><div class="endeks-kpi-note">${escapeEndeksHtml(periods[periods.length - 1].label)} · ilk ${dayCount} gün</div></div>
                            <div class="endeks-kpi" style="--kpi-accent:${trendColor}"><div class="endeks-kpi-label">Önceki aya göre</div><div class="endeks-kpi-value" style="color:${trendColor}"><i class="bx ${trendIcon}" style="font-size:18px"></i> ${trend.text}</div><div class="endeks-kpi-note">${diff >= 0 ? '+' : ''}${formatNumber(diff)} net okuma</div></div>
                            <div class="endeks-kpi" style="--kpi-accent:#0ea5e9"><div class="endeks-kpi-label">Günlük ortalama</div><div class="endeks-kpi-value">${formatNumber(dailyAverage)}</div><div class="endeks-kpi-note">Takvim günü başına okuma</div></div>
                            <div class="endeks-kpi" style="--kpi-accent:#8b5cf6"><div class="endeks-kpi-label">${scopeLabel}</div><div class="endeks-kpi-value">${formatNumber(items.length)}</div><div class="endeks-kpi-note"><span style="color:#059669">${increasing} yükselişte</span> · <span style="color:#dc2626">${decreasing} düşüşte</span></div></div>
                        </div>`;
                    }

                    function buildComparisonDetailToggle(target, viewLabel) {
                        return `<button type="button" class="comparison-detail-toggle" data-detail-target="${target}" aria-expanded="false"><i class="bx bx-chevron-down"></i><span>${viewLabel} detaylarını göster</span></button>`;
                    }

                    function renderEndeksDistributionModal() {
                        if (!endeksCompData) return;
                        const statuses = endeksCompData.status_distribution || [];
                        const regions = endeksCompData.region_distribution || [];
                        const periods = endeksCompData.periods || [];
                        const currentPeriod = periods[periods.length - 1] || {};
                        const total = statuses.reduce((sum, item) => sum + Number(item.toplam || 0), 0);
                        const records = statuses.reduce((sum, item) => sum + Number(item.kayit_sayisi || 0), 0);
                        const normal = statuses.filter(item => item.durum === 'SAYAÇ NORMAL').reduce((sum, item) => sum + Number(item.toplam || 0), 0);
                        const exception = Math.max(0, total - normal);
                        $('#endeksDistributionPeriod').text((currentPeriod.label || '') + ' · ' + (currentPeriod.start || '') + ' / ' + (currentPeriod.end || ''));
                        $('#endeksDistributionHeroStats').html([
                            ['Toplam abone', formatNumber(total), 'bx-group'],
                            ['Sayaç normal', formatNumber(normal), 'bx-check-circle'],
                            ['Diğer durumlar', formatNumber(exception), 'bx-error-circle'],
                            ['Toplam kayıt', formatNumber(records), 'bx-receipt']
                        ].map(item => `<div class="col-6 col-lg-3"><div class="endeks-modal-stat"><div style="font-size:10px;opacity:.72;">${item[0]}</div><div class="fs-5 fw-bold mt-1"><i class="bx ${item[2]} me-1" style="font-size:16px"></i>${item[1]}</div></div></div>`).join(''));

                        $('#endeksStatusCount').text(statuses.length + ' durum');
                        const palette = ['#34c38f','#f1b44c','#f46a6a','#50a5f1','#74788d','#556ee6','#22c3dd','#fb8c00'];
                        const statusHtml = statuses.map((item, index) => {
                            const value = Number(item.toplam || 0);
                            const rate = total > 0 ? value / total * 100 : 0;
                            const color = item.durum === 'SAYAÇ NORMAL' ? '#34c38f' : palette[index % palette.length];
                            return `<article class="endeks-status-item" style="animation-delay:${Math.min(index * 35, 280)}ms">
                                <div class="d-flex align-items-start gap-2"><span class="mt-1 rounded-circle flex-shrink-0" style="width:9px;height:9px;background:${color};box-shadow:0 0 0 4px ${color}1c"></span><div class="min-w-0 flex-grow-1"><div class="d-flex gap-2 justify-content-between"><span class="fw-bold text-truncate" title="${escapeEndeksHtml(item.durum)}" style="font-size:11px;">${escapeEndeksHtml(item.durum)}</span><span class="fw-bold" style="font-size:11px;color:${color}">%${rate.toFixed(1)}</span></div><div class="d-flex align-items-end justify-content-between mt-2"><span class="fw-bold fs-5">${formatNumber(value)}</span><span class="text-muted" style="font-size:10px;">${formatNumber(item.kayit_sayisi)} kayıt</span></div><div class="progress mt-2" style="height:4px;background:#e9edf4"><div class="progress-bar" style="width:${rate}%;background:${color}"></div></div></div></div>
                            </article>`;
                        }).join('');
                        $('#endeksStatusDistribution').html(statusHtml || '<div class="text-muted py-4 text-center">Durum dağılımı bulunamadı.</div>');

                        const maxRegion = Math.max(...regions.map(item => Number(item.toplam || 0)), 1);
                        const regionHtml = regions.map((item, index) => {
                            const value = Number(item.toplam || 0);
                            const width = value / maxRegion * 100;
                            const share = total > 0 ? value / total * 100 : 0;
                            return `<div class="endeks-region-row"><div class="d-flex align-items-center justify-content-between mb-1"><span class="fw-semibold text-truncate pe-2" style="font-size:11px;">${index + 1}. ${escapeEndeksHtml(item.bolge)}</span><span class="fw-bold" style="font-size:12px;">${formatNumber(value)}</span></div><div class="endeks-region-track"><div class="endeks-region-fill" style="width:${width}%;animation-delay:${Math.min(index * 45, 300)}ms"></div></div><div class="d-flex justify-content-between text-muted mt-1" style="font-size:9px;"><span>${formatNumber(item.kayit_sayisi)} kayıt</span><span>Toplamın %${share.toFixed(1)}'i</span></div></div>`;
                        }).join('');
                        $('#endeksRegionDistribution').html(regionHtml || '<div class="text-muted py-4 text-center">Bölge dağılımı bulunamadı.</div>');

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('endeksDistributionModal')).show();
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
                        const worst = sorted.filter(item => item.trendPct < 0).slice(0, 3);
                        const best = sorted.filter(item => item.trendPct > 0).slice(-3).reverse();

                        if (worst.length === 0 && best.length === 0) return '';

                        let html = '<div class="endeks-summary-strip d-flex flex-wrap gap-2 px-3 py-3">';

                        if (worst.length > 0) {
                            html += '<div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">';
                            html += '<div class="d-flex align-items-center gap-1 me-2" style="white-space: nowrap;"><i class="bx bx-down-arrow-circle" style="color: #ef4444; font-size: 16px;"></i><span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.05em;">Düşük Performans</span></div>';
                            worst.forEach(w => {
                                html += `<div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); font-size: 12px;">`;
                                html += `<span style="font-weight: 600; color: #334155;">${escapeEndeksHtml(w.name)}</span>`;
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
                                html += `<span style="font-weight: 600; color: #334155;">${escapeEndeksHtml(b.name)}</span>`;
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

                        html += buildOverview(bolgeEntries, periods, 'bolge');
                        html += buildComparisonDetailToggle('endeks-bolge-detail', 'Bölge');
                        html += '<div id="endeks-bolge-detail" class="comparison-collapsible-detail">';
                        // Özet kartlar
                        html += buildSummaryCards(bolgeEntries, 'bolge');

                        html += '<div class="table-responsive endeks-table-wrap" style="max-height: 520px; overflow-y: auto;">';
                        html += '<table class="table table-nowrap align-middle mb-0" style="font-size: 13px;">';

                        // Dark header
                        html += '<thead class="endeks-table-head" style="position: sticky; top: 0; z-index: 5;">';
                        html += '<tr style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">';
                        html += '<th style="padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 180px; border-bottom: none;">BÖLGE</th>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            html += `<th class="text-center ${isCurrent ? 'is-current-period' : ''}" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 800; min-width: 130px; border-bottom: none;">${escapeEndeksHtml(label)}</th>`;
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
                            html += `<div><span class="fw-bold" style="color: #1e293b; font-size: 13px;">${escapeEndeksHtml(bolge)}</span>`;
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

                            html += `<td class="text-center" style="padding: 10px 12px;">${getTrendBadge(trend)}<span class="endeks-absolute-diff">${getAbsoluteDiff(periodValues[periodValues.length - 1], periodValues[periodValues.length - 2])}</span></td>`;
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
                                    html += `<span style="color: #475569; font-size: 12px; font-weight: 600;">${escapeEndeksHtml(p.personel_adi)}</span>`;
                                    html += `<br><span class="text-muted" style="font-size: 10px;">${escapeEndeksHtml(p.ekip_adi)}</span>`;
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

                        html += '</div>';

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
                        html += buildOverview(personelEntries, periods, 'personel');
                        html += buildComparisonDetailToggle('endeks-personel-detail', 'Personel');
                        html += '<div id="endeks-personel-detail" class="comparison-collapsible-detail">';
                        html += buildSummaryCards(personelEntries, 'personel');

                        html += '<div class="table-responsive endeks-table-wrap" style="max-height: 520px; overflow-y: auto;">';
                        html += '<table class="table table-nowrap align-middle mb-0" style="font-size: 13px;">';

                        // Dark header
                        html += '<thead class="endeks-table-head" style="position: sticky; top: 0; z-index: 5;">';
                        html += '<tr style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">';
                        html += '<th style="padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 180px; border-bottom: none;">PERSONEL</th>';
                        html += '<th style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 100px; border-bottom: none;">BÖLGE</th>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            html += `<th class="text-center ${isCurrent ? 'is-current-period' : ''}" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 800; min-width: 130px; border-bottom: none;">${escapeEndeksHtml(label)}</th>`;
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
                            html += `<span class="fw-bold" style="color: #1e293b; font-size: 12px;">${escapeEndeksHtml(p.personel_adi)}</span>`;
                            html += `<br><span class="text-muted" style="font-size: 10px;">${escapeEndeksHtml(p.ekip_adi)}</span>`;
                            html += `</div></div></td>`;
                            html += `<td style="padding: 12px 12px;"><span class="badge" style="font-size: 10px; font-weight: 600; background: rgba(99,102,241,0.08); color: #4338ca; border: 1px solid rgba(99,102,241,0.15); padding: 4px 8px; border-radius: 6px;">${escapeEndeksHtml(p.bolge)}</span></td>`;

                            pPeriods.forEach((val, ppIdx) => {
                                const isCurrent = periods[ppIdx].is_current;
                                const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                const barColor = isCurrent ? '#6366f1' : '#94a3b8';
                                html += `<td class="text-center" style="padding: 10px 12px; background: ${bgColor};">`;
                                html += `<span class="fw-bold" style="color: #1e293b; font-size: 14px;">${formatNumber(val)}</span>`;
                                const activeDays = p.periods[periodLabels[ppIdx]]?.gun_sayisi || 0;
                                const perDay = activeDays > 0 ? Math.round(val / activeDays) : 0;
                                html += `<div class="d-flex justify-content-center">${getMiniBar(val, maxPersonelVal, barColor)}</div>`;
                                html += `<div class="endeks-cell-meta">${activeDays} gün · ${formatNumber(perDay)}/gün</div></td>`;
                            });

                            html += `<td class="text-center" style="padding: 10px 12px;">${getTrendBadge(trend)}<span class="endeks-absolute-diff">${getAbsoluteDiff(pPeriods[pPeriods.length - 1], pPeriods[pPeriods.length - 2])}</span></td></tr>`;
                        });

                        html += '</tbody></table></div>';

                        html += '<div class="px-3 py-2 d-flex align-items-center" style="border-top: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">';
                        html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Personeller performans değişimine göre sıralanmıştır (en düşük performans üstte).</span>';
                        html += '<div class="ms-auto d-flex align-items-center gap-3">';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>≤-20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;"></span>-10~20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>>+10%</div>';
                        html += '</div></div>';

                        html += '</div>';

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

                    $(document).on('click', '#widget-endeks-karsilastirma .endeks-total-trigger', renderEndeksDistributionModal);
                    $(document).on('keydown', '#widget-endeks-karsilastirma .endeks-total-trigger', function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            renderEndeksDistributionModal();
                        }
                    });

                    // Sayfa yüklendiğinde veri çek
                    if (document.getElementById('widget-endeks-karsilastirma')) loadEndeksComparison();
                })();
                // ========== /ENDEKS KARŞILAŞTIRMA KART LOGIC ==========

                // ========== KESME/AÇMA KARŞILAŞTIRMA ==========
                (function () {
                    let rootComparisonData = null;
                    let currentType = 'olumlu'; // 'olumlu' (varsayılan), 'all', 'olumsuz'
                    let currentView = 'bolge';
                    const numberFormat = new Intl.NumberFormat('tr-TR');
                    const format = value => numberFormat.format(Number(value || 0));
                    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
                    const trend = (current, previous) => {
                        if (!previous) return current > 0 ? { text:'Yeni', pct:100, color:'#059669', icon:'bx-plus' } : { text:'-', pct:0, color:'#64748b', icon:'bx-minus' };
                        const pct = Number((((current - previous) / previous) * 100).toFixed(1));
                        return { text:(pct > 0 ? '+' : '') + pct + '%', pct, color:pct > 0 ? '#059669' : (pct < 0 ? '#dc2626' : '#64748b'), icon:pct > 0 ? 'bx-trending-up' : (pct < 0 ? 'bx-trending-down' : 'bx-minus') };
                    };

                    function getActiveTypeData(type) {
                        type = type || currentType;
                        if (!rootComparisonData) return { bolge:{}, personel:{}, firma:{}, result_distribution:[] };
                        if (rootComparisonData.types && rootComparisonData.types[type]) {
                            return rootComparisonData.types[type];
                        }
                        return rootComparisonData;
                    }

                    function entriesFor(view, type) {
                        const activeData = getActiveTypeData(type);
                        const source = activeData[view] || {};
                        const labels = (rootComparisonData?.periods || []).map(period => period.label);
                        return Object.keys(source).map(key => {
                            const item = source[key];
                            const values = labels.map(label => Number(item.periods?.[label]?.toplam || 0));
                            const last = values[values.length - 1] || 0;
                            const previous = values[values.length - 2] || 0;
                            return { key, item, values, last, previous, trend:trend(last, previous) };
                        }).sort((a,b) => b.last - a.last);
                    }

                    function overview(entries) {
                        const periods = rootComparisonData.periods || [];
                        const current = entries.reduce((sum,item) => sum + item.last, 0);
                        const previous = entries.reduce((sum,item) => sum + item.previous, 0);
                        const change = trend(current, previous);
                        const days = Math.max(1, Number(rootComparisonData.gun || 1));
                        const rising = entries.filter(item => item.trend.pct > 0).length;

                        let typeBadge = '';
                        if (currentType === 'olumlu') {
                            typeBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-10 px-2 py-0.5 ms-1"><i class="bx bx-check-circle me-1"></i>Olumlu</span>';
                        } else if (currentType === 'olumsuz') {
                            typeBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-10 px-2 py-0.5 ms-1"><i class="bx bx-x-circle me-1"></i>Olumsuz</span>';
                        }

                        return `<div class="kesme-overview"><div class="kesme-kpi is-actionable kesme-total-trigger" role="button" tabindex="0" title="İş dağılımını inceleyin"><div class="kesme-kpi-label">Bu dönem toplam iş ${typeBadge} <i class="bx bx-expand-alt text-danger ms-1"></i></div><div class="kesme-kpi-value">${format(current)}</div><div class="kesme-kpi-note">${escapeHtml(periods.at(-1)?.label || '')} · ilk ${days} gün</div></div><div class="kesme-kpi"><div class="kesme-kpi-label">Önceki aya göre</div><div class="kesme-kpi-value" style="color:${change.color}"><i class="bx ${change.icon}"></i> ${change.text}</div><div class="kesme-kpi-note">${current-previous >= 0 ? '+' : ''}${format(current-previous)} net iş</div></div><div class="kesme-kpi"><div class="kesme-kpi-label">Günlük ortalama</div><div class="kesme-kpi-value">${format(Math.round(current/days))}</div><div class="kesme-kpi-note">Takvim günü başına sonuçlanan</div></div><div class="kesme-kpi"><div class="kesme-kpi-label">Aktif ${currentView === 'bolge' ? 'bölge' : 'personel'}</div><div class="kesme-kpi-value">${format(entries.length)}</div><div class="kesme-kpi-note"><span class="text-success">${rising} yükselişte</span> · ${entries.length-rising} diğer</div></div></div>`;
                    }

                    function renderTable(view) {
                        const entries = entriesFor(view, currentType);
                        const periods = rootComparisonData.periods || [];
                        const max = Math.max(...entries.map(item => item.last), 1);
                        const periodTotals = periods.map((period, index) => entries.reduce((sum, entry) => sum + Number(entry.values[index] || 0), 0));
                        const totalCurrent = periodTotals[periodTotals.length - 1] || 0;
                        const totalPrevious = periodTotals[periodTotals.length - 2] || 0;
                        const totalTrend = trend(totalCurrent, totalPrevious);
                        const detailId = 'kesme-' + view + '-detail';
                        const detailLabel = view === 'bolge' ? 'Bölge' : 'Personel';
                        let html = overview(entries) + `<button type="button" class="comparison-detail-toggle" data-detail-target="${detailId}" aria-expanded="false"><i class="bx bx-chevron-down"></i><span>${detailLabel} detaylarını göster</span></button><div id="${detailId}" class="comparison-collapsible-detail"><div class="table-responsive" style="max-height:520px;overflow:auto"><table class="table table-nowrap align-middle mb-0"><thead class="kesme-table-head"><tr><th style="padding:12px 16px">` + (view === 'bolge' ? 'BÖLGE' : 'PERSONEL / EKİP') + '</th>';
                        periods.forEach(period => html += `<th class="text-center ${period.is_current ? 'is-current-period' : ''}" style="padding:12px;min-width:135px">${escapeHtml(period.label)}</th>`);
                        html += '<th class="text-center" style="min-width:130px">DEĞİŞİM</th></tr></thead><tbody>';
                        if (entries.length === 0) {
                            html += `<tr><td colspan="${periods.length + 2}" class="text-center py-4 text-muted"><i class="bx bx-info-circle font-size-18 d-block mb-1"></i>Bu filtreye ait sonuçlanan iş bulunamadı.</td></tr>`;
                        } else {
                            entries.forEach(entry => {
                                const label = view === 'bolge' ? entry.key : entry.item.personel_adi;
                                const sub = view === 'bolge' ? ((entry.item.periods?.[periods.at(-1)?.label]?.personel_sayisi || 0) + ' personel') : ((entry.item.ekip_adi || '-') + ' · ' + (entry.item.bolge || '-'));
                                html += `<tr style="border-bottom:1px solid #eef1f5"><td style="padding:11px 16px"><div class="fw-bold" style="font-size:12px">${escapeHtml(label)}</div><div class="text-muted" style="font-size:9px">${escapeHtml(sub)}</div></td>`;
                                entry.values.forEach((value,index) => html += `<td class="text-center" style="padding:10px;background:${periods[index].is_current?'rgba(225,29,72,.045)':'transparent'}"><span class="fw-bold">${format(value)}</span><div class="kesme-bar"><span style="width:${value/max*100}%"></span></div></td>`);
                                html += `<td class="text-center"><span class="badge rounded-pill" style="padding:5px 10px;background:${entry.trend.color}16;color:${entry.trend.color};border:1px solid ${entry.trend.color}35"><i class="bx ${entry.trend.icon} me-1"></i>${entry.trend.text}</span><div class="text-muted mt-1" style="font-size:9px">${entry.last-entry.previous >= 0 ? '+' : ''}${format(entry.last-entry.previous)} iş</div></td></tr>`;
                            });

                            html += '<tr class="kesme-total-row" style="background:rgba(15,23,42,.045);border-top:2px solid rgba(225,29,72,.25)"><td style="padding:12px 16px"><div class="fw-bold text-uppercase" style="font-size:11px;letter-spacing:.04em">TOPLAM</div><div class="text-muted" style="font-size:9px">Gösterilen ' + (view === 'bolge' ? 'bölgeler' : 'personeller') + '</div></td>';
                            periodTotals.forEach((value, index) => html += `<td class="text-center" style="padding:12px;background:${periods[index].is_current?'rgba(225,29,72,.09)':'transparent'}"><span class="fw-bold" style="font-size:13px">${format(value)}</span></td>`);
                            html += `<td class="text-center"><span class="badge rounded-pill" style="padding:5px 10px;background:${totalTrend.color}16;color:${totalTrend.color};border:1px solid ${totalTrend.color}35"><i class="bx ${totalTrend.icon} me-1"></i>${totalTrend.text}</span><div class="text-muted mt-1" style="font-size:9px">${totalCurrent-totalPrevious >= 0 ? '+' : ''}${format(totalCurrent-totalPrevious)} iş</div></td></tr>`;
                        }
                        html += '</tbody></table></div><div class="d-flex align-items-center px-3 py-2 border-top bg-light-subtle"><span class="text-muted" style="font-size:10px"><i class="bx bx-info-circle me-1"></i>Ayların aynı gün aralıkları karşılaştırılır.</span><a href="index.php?p=puantaj/raporlar&tab=karsilastirma" class="btn btn-sm btn-danger ms-auto rounded-pill px-3">Detaylı Rapor</a></div></div>';
                        $('#kesmeComp' + (view === 'bolge' ? 'Bolge' : 'Personel')).html(html);
                    }

                    function openDistribution(type) {
                        type = type || currentType;
                        const activeData = getActiveTypeData(type);
                        const distribution = activeData.result_distribution || [];
                        const regions = entriesFor('bolge', type);
                        const total = distribution.reduce((sum,item) => sum + Number(item.toplam || 0), 0);
                        const records = distribution.reduce((sum,item) => sum + Number(item.kayit_sayisi || 0), 0);
                        const period = rootComparisonData.periods?.at(-1) || {};

                        // Modal başlığı filtre haplarını senkronize et
                        $('#kesmeModalTypeToggle .kesme-pill-btn').removeClass('active');
                        $('#kesmeModalTypeToggle .kesme-pill-btn[data-type="' + type + '"]').addClass('active');

                        const typeLabel = type === 'olumlu' ? ' (Olumlu)' : (type === 'olumsuz' ? ' (Olumsuz)' : '');
                        $('#kesmeDistributionPeriod').text(`${period.label || ''} · ${period.start || ''} / ${period.end || ''}${typeLabel}`);
                        $('#kesmeDistributionStats').html([['Toplam iş',total,'bx-check-double'],['İşlem türü',distribution.length,'bx-category'],['Toplam kayıt',records,'bx-receipt'],['Aktif bölge',regions.length,'bx-map']].map(item => `<div class="col-6 col-lg-3"><div class="p-3 rounded-3" style="background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.18)"><div style="font-size:10px;opacity:.75">${item[0]}</div><div class="fs-5 fw-bold mt-1"><i class="bx ${item[2]} me-1"></i>${format(item[1])}</div></div></div>`).join(''));

                        $('#kesmeResultDistribution').html(distribution.map((item,index) => {
                            const pct = total ? Number(item.toplam)/total*100 : 0;
                            const isOlumsuz = item.sonuc_tipi === 'olumsuz';
                            const badge = isOlumsuz
                                ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-10 px-2 py-0.5"><i class="bx bx-x-circle me-1"></i>Olumsuz</span>'
                                : '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-10 px-2 py-0.5"><i class="bx bx-check-circle me-1"></i>Olumlu</span>';
                            const barColor = isOlumsuz ? 'bg-danger' : 'bg-success';
                            const textColor = isOlumsuz ? 'text-danger' : 'text-success';

                            return `<div class="col-md-6"><div class="p-3 border rounded-3 h-100 ${isOlumsuz ? 'bg-danger-subtle bg-opacity-10 border-danger-subtle' : ''}"><div class="d-flex justify-content-between align-items-center gap-2"><span class="fw-bold text-truncate" title="${escapeHtml(item.sonuc)}" style="font-size:11px">${escapeHtml(item.sonuc)}</span><div class="d-flex align-items-center gap-1">${badge}<span class="${textColor} fw-bold ms-1">%${pct.toFixed(1)}</span></div></div><div class="d-flex justify-content-between align-items-end mt-2"><strong class="fs-5">${format(item.toplam)}</strong><small class="text-muted">${format(item.kayit_sayisi)} kayıt</small></div><div class="progress mt-2" style="height:5px"><div class="progress-bar ${barColor}" style="width:${pct}%"></div></div></div></div>`;
                        }).join('') || '<div class="col-12 text-muted py-3 text-center"><i class="bx bx-info-circle me-1"></i>Bu filtreye ait işlem sonucu dağılımı bulunamadı.</div>');

                        const maxRegion = Math.max(...regions.map(item => item.last), 1);
                        $('#kesmeRegionDistribution').html(regions.map((item,index) => `<div class="mb-3"><div class="d-flex justify-content-between mb-1"><span class="fw-semibold" style="font-size:11px">${index+1}. ${escapeHtml(item.key)}</span><strong>${format(item.last)}</strong></div><div class="progress" style="height:7px"><div class="progress-bar" style="width:${item.last/maxRegion*100}%;background:linear-gradient(90deg,#e11d48,#fb923c)"></div></div><div class="text-muted mt-1 text-end" style="font-size:9px">Toplamın %${total ? (item.last/total*100).toFixed(1) : '0.0'}'i</div></div>`).join('') || '<div class="text-muted py-3 text-center"><i class="bx bx-info-circle me-1"></i>Bölge verisi bulunamadı.</div>');

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('kesmeDistributionModal')).show();
                    }

                    function switchType(newType) {
                        currentType = newType;
                        $('#kesmeCompTypeToggle button').removeClass('active');
                        $('#kesmeCompTypeToggle button[data-type="' + currentType + '"]').addClass('active');

                        $('#kesmeModalTypeToggle .kesme-pill-btn').removeClass('active');
                        $('#kesmeModalTypeToggle .kesme-pill-btn[data-type="' + currentType + '"]').addClass('active');

                        renderTable('bolge');
                        renderTable('personel');

                        // Modal açıksa onu da güncelle
                        const modalEl = document.getElementById('kesmeDistributionModal');
                        if (modalEl && modalEl.classList.contains('show')) {
                            openDistribution(currentType);
                        }
                    }

                    if (!document.getElementById('widget-kesme-karsilastirma')) return;

                    $.post('views/home/api.php', {action:'get-kesme-comparison', type: currentType}).done(response => {
                        const result = typeof response === 'object' ? response : JSON.parse(response);
                        $('#kesmeCompLoading').hide();
                        if (result.status !== 'success' || !result.data || (!Object.keys(result.data.bolge || {}).length && !Object.keys(result.data.types || {}).length)) {
                            $('#kesmeCompEmpty').show();
                            return;
                        }
                        rootComparisonData = result.data;
                        $('#kesmeCompGunNo').text(rootComparisonData.gun);
                        $('#kesmeCompGunBadge').removeClass('d-none');
                        renderTable('bolge');
                        renderTable('personel');
                        $('#kesmeCompContent').show();
                    }).fail(() => {
                        $('#kesmeCompLoading').hide();
                        $('#kesmeCompEmpty').show();
                    });

                    // Kart Üstü Görünüm Toggle (Bölge / Personel)
                    $('#kesmeCompViewToggle button').on('click', function(){
                        $('#kesmeCompViewToggle button').removeClass('active');
                        $(this).addClass('active');
                        currentView = $(this).data('view');
                        $('#kesmeCompBolge').toggle(currentView === 'bolge');
                        $('#kesmeCompPersonel').toggle(currentView === 'personel');
                        renderTable(currentView);
                    });

                    // Kart Üstü Tür Toggle (Tümü / Olumlu / Olumsuz)
                    $('#kesmeCompTypeToggle button').on('click', function(){
                        switchType($(this).data('type'));
                    });

                    // Modal İçi Tür Toggle (Tümü / Olumlu / Olumsuz)
                    $(document).on('click', '#kesmeModalTypeToggle .kesme-pill-btn', function(){
                        switchType($(this).data('type'));
                    });

                    $(document).on('click', '.kesme-total-trigger', function() {
                        openDistribution(currentType);
                    }).on('keydown', '.kesme-total-trigger', event => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openDistribution(currentType);
                        }
                    });
                })();
                // ========== /KESME/AÇMA KARŞILAŞTIRMA ==========

                $(document).on('click', '.comparison-detail-toggle', function () {
                    const button = $(this);
                    const target = $('#' + button.data('detail-target'));
                    const isOpen = button.attr('aria-expanded') === 'true';
                    const label = button.find('span').text();
                    const subject = label.replace(/ detaylarını (göster|gizle)$/i, '');
                    button.attr('aria-expanded', isOpen ? 'false' : 'true');
                    button.find('span').text(subject + ' detaylarını ' + (isOpen ? 'göster' : 'gizle'));
                    const widget = button.closest('.widget-item');
                    const fixedHeight = parseInt(widget.attr('data-grid-height'), 10);
                    if (fixedHeight && !isOpen) widget.css('height', 'auto');
                    const relayout = function () { if (typeof runDashboardGridLayout === 'function') runDashboardGridLayout(true); };
                    target.stop(true, true);
                    if (isOpen) {
                        target.slideUp(320, function () {
                            if (fixedHeight) widget.css('height', fixedHeight + 'px');
                            relayout();
                        });
                    } else {
                        const startHeight = widget.outerHeight();
                        target.show();
                        const extraHeight = target.outerHeight(true);
                        target.hide();
                        widget.css('min-height', (startHeight + extraHeight) + 'px');
                        relayout();
                        target.slideDown(320, function () {
                            widget.css('min-height', '');
                            relayout();
                        });
                    }
                });

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

                // Takvim & Alarmlar Widget Etkileşim Fonksiyonları
                window.selectHomeCalendarDay = function(day) {
                    const $card = $('#widget-takvim-alarmlar');
                    if (!$card.length) return;
                    
                    const $clickedCell = $card.find('.cal-day-cell[data-day="' + day + '"]');
                    if ($clickedCell.hasClass('is-selected')) {
                        resetHomeCalendarDayFilter();
                        return;
                    }

                    $card.find('.cal-day-cell').removeClass('is-selected');
                    $clickedCell.addClass('is-selected');

                    const $items = $card.find('.cal-alarm-item');
                    let visibleCount = 0;
                    $items.each(function() {
                        const itemDay = parseInt($(this).data('day'), 10);
                        if (itemDay === day) {
                            $(this).removeClass('d-none');
                            visibleCount++;
                        } else {
                            $(this).addClass('d-none');
                        }
                    });

                    $card.find('#calAlarmCountTitle').text(visibleCount + ' ALARM');
                    $card.find('#btnCalResetFilter').removeClass('d-none');
                };

                window.resetHomeCalendarDayFilter = function() {
                    const $card = $('#widget-takvim-alarmlar');
                    if (!$card.length) return;

                    $card.find('.cal-day-cell').removeClass('is-selected');
                    const $items = $card.find('.cal-alarm-item');
                    $items.removeClass('d-none');
                    $card.find('#calAlarmCountTitle').text($items.length + ' ALARM');
                    $card.find('#btnCalResetFilter').addClass('d-none');
                };

                const dogumAylar = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

                window.renderDogumTakvimi = function($card, secilenGun) {
                    const yil = parseInt($card.attr('data-yil'), 10);
                    const ay = parseInt($card.attr('data-ay'), 10);
                    let veri = [];
                    try { veri = JSON.parse($card.attr('data-dogum') || '[]'); } catch (e) { veri = []; }

                    const gunSayisi = new Date(yil, ay, 0).getDate();
                    const ilkGun = (new Date(yil, ay - 1, 1).getDay() + 6) % 7;
                    const bugun = new Date();
                    const buAy = bugun.getFullYear() === yil && (bugun.getMonth() + 1) === ay;

                    const gunler = {};
                    veri.forEach(function (k) {
                        if (k.ay !== ay) return;
                        const g = Math.min(k.gun, gunSayisi);
                        (gunler[g] = gunler[g] || []).push(k);
                    });

                    let grid = '';
                    for (let i = 0; i < ilkGun; i++) grid += '<div class="cal-day-cell cal-empty"></div>';
                    for (let d = 1; d <= gunSayisi; d++) {
                        let cls = 'cal-day-cell';
                        if (gunler[d]) cls += ' has-alarm';
                        if (buAy && d === bugun.getDate()) cls += ' is-today';
                        else if (yil * 100 + ay < bugun.getFullYear() * 100 + bugun.getMonth() + 1 || (buAy && d < bugun.getDate())) cls += ' dg-past';
                        if (d === secilenGun) cls += ' is-selected';
                        grid += '<div class="' + cls + '" data-day="' + d + '" onclick="selectDogumGunu(this, ' + d + ')"><span class="cal-day-num">' + d + '</span></div>';
                    }
                    $card.find('.dg-days-grid').html(grid);
                    $card.find('.dg-month-title').text(dogumAylar[ay] + ' ' + yil);

                    const gosterilenGun = secilenGun || (buAy ? bugun.getDate() : 0);
                    const gunNoListe = Object.keys(gunler).map(Number).sort(function (a, b) { return a - b; })
                        .filter(function (g) { return g === gosterilenGun; });
                    let html = '';
                    let adet = 0;
                    gunNoListe.forEach(function (g) {
                        gunler[g].forEach(function (k) {
                            adet++;
                            html += $('<div class="cal-alarm-item d-block mb-1"></div>').append(
                                $('<div class="d-flex align-items-center justify-content-between"></div>').append(
                                    $('<span class="cal-alarm-date fw-bold"></span>').text(g + ' ' + dogumAylar[ay].substring(0, 3)),
                                    $('<span class="badge dg-pill cal-mini-pill"></span>').text((yil - k.yil) + '. yaş')
                                ),
                                $('<div class="cal-alarm-text text-truncate fw-semibold text-dark"></div>').text(k.ad).attr('title', k.ad + (k.bilgi ? ' · ' + k.bilgi : ''))
                            )[0].outerHTML;
                        });
                    });
                    if (!html) {
                        html = '<div class="text-center py-3 text-muted" style="font-size: 0.72rem;"><i class="bx bx-cake text-muted opacity-50 fs-3 d-block mb-1"></i><span>' + (secilenGun ? 'Bu gün doğum günü yok' : (buAy ? 'Bugün doğum günü yok' : 'Günü seçerek görün')) + '</span></div>';
                    }
                    $card.find('.dg-list').html(html);
                    $card.find('.dg-count').text(secilenGun || !buAy ? adet + ' DOĞUM GÜNÜ' : 'BUGÜN · ' + adet + ' KİŞİ');
                    $card.find('.dg-reset').toggleClass('d-none', !secilenGun);
                    $card.data('secili', secilenGun || 0);
                };

                window.navigateDogumTakvimi = function(btn, yon) {
                    const $card = $(btn).closest('.dg-card');
                    let yil = parseInt($card.attr('data-yil'), 10);
                    let ay = parseInt($card.attr('data-ay'), 10) + yon;
                    if (ay < 1) { ay = 12; yil--; } else if (ay > 12) { ay = 1; yil++; }
                    $card.attr('data-yil', yil).attr('data-ay', ay);
                    renderDogumTakvimi($card, 0);
                };

                window.selectDogumGunu = function(cell, gun) {
                    const $card = $(cell).closest('.dg-card');
                    renderDogumTakvimi($card, $card.data('secili') === gun ? 0 : gun);
                };

                window.resetDogumGunu = function(btn) {
                    renderDogumTakvimi($(btn).closest('.dg-card'), 0);
                };

                window.loadHeroCalendar = function(year, month) {
                    const $host = $('#heroCalendarHost');
                    if (!$host.length) return;
                    $host.css('opacity', '0.6');
                    $.ajax({
                        url: 'views/home/api.php',
                        type: 'POST',
                        data: { action: 'get-calendar-alarms', layout: 'hero', year: year, month: month },
                        dataType: 'json'
                    }).done(function(res) {
                        if (res.status === 'success' && typeof res.html === 'string') {
                            if (res.html.trim() === '') { $host.remove(); return; }
                            $host.html(res.html);
                            const bugun = parseInt($('#heroCalendar').attr('data-bugun'), 10) || 0;
                            if (window.heroCalState) { window.heroCalState.day = 0; window.applyHeroCalendar(window.heroCalState); }
                            else if (bugun) window.applyHeroCalendar({ open: true, type: 'all', day: bugun });
                        } else {
                            $host.html('<div class="hcal-loading">Takvim yüklenemedi.</div>');
                        }
                    }).fail(function() {
                        $host.html('<div class="hcal-loading">Takvim yüklenemedi.</div>');
                    }).always(function() {
                        $host.css('opacity', '1');
                    });
                };

                window.heroCalState = null;

                window.applyHeroCalendar = function(st) {
                    const $cal = $('#heroCalendar');
                    if (!$cal.length) return;
                    let visible = 0;
                    $cal.find('.hcal-item').each(function() {
                        const $it = $(this);
                        const match = (st.type === 'all' || $it.attr('data-type') === st.type)
                            && (!st.day || parseInt($it.attr('data-day'), 10) === st.day);
                        $it.toggleClass('d-none', !match);
                        if (match) visible++;
                    });
                    $cal.toggleClass('is-collapsed', !st.open);
                    $cal.find('.hcal-day').removeClass('is-selected');
                    if (st.day) $cal.find('.hcal-day[data-day="' + st.day + '"]').addClass('is-selected');
                    $cal.find('.hcal-chip').each(function() {
                        const on = st.open && $(this).attr('data-type') === st.type;
                        $(this).toggleClass('is-active', on).attr('aria-pressed', on ? 'true' : 'false');
                    });
                    $cal.find('#heroCalNoMatch').toggleClass('d-none', !(st.open && visible === 0 && $cal.find('.hcal-item').length > 0));
                    window.heroCalState = st;
                };

                window.currentHeroCalendarState = function() {
                    if (window.heroCalState) return $.extend({}, window.heroCalState);
                    const $cal = $('#heroCalendar');
                    return { open: !$cal.hasClass('is-collapsed'), type: 'all', day: 0 };
                };

                window.filterHeroCalendar = function(type) {
                    const st = window.currentHeroCalendarState();
                    if (st.open && st.type === type && !st.day) { st.open = false; }
                    else { st.open = true; st.type = type; }
                    st.day = 0;
                    window.applyHeroCalendar(st);
                };

                window.navigateHeroCalendar = function(year, month) {
                    if (month < 1) { month = 12; year--; } else if (month > 12) { month = 1; year++; }
                    window.loadHeroCalendar(year, month);
                };

                window.selectHeroCalendarDay = function(day) {
                    const st = window.currentHeroCalendarState();
                    st.open = true;
                    st.day = st.day === day ? 0 : day;
                    window.applyHeroCalendar(st);
                };

                window.navigateHomeCalendar = function(year, month) {
                    if (month < 1) {
                        month = 12;
                        year--;
                    } else if (month > 12) {
                        month = 1;
                        year++;
                    }

                    const $widget = $('#widget-takvim-alarmlar');
                    $widget.css('opacity', '0.6');

                    $.ajax({
                        url: 'views/home/api.php',
                        type: 'POST',
                        data: {
                            action: 'get-calendar-alarms',
                            year: year,
                            month: month
                        },
                        dataType: 'json'
                    }).done(function(res) {
                        if (res.status === 'success' && res.html) {
                            const $newEl = $(res.html);
                            $widget.replaceWith($newEl);
                            if (typeof initMacControls === 'function') {
                                initMacControls();
                            }
                        }
                    }).always(function() {
                        $('#widget-takvim-alarmlar').css('opacity', '1');
                    });
                };

                // Initialize everything on load
                $(document).ready(function() {
                    (function () {
                        const bugun = parseInt($('#heroCalendar').attr('data-bugun'), 10) || 0;
                        if (bugun && !$('#heroCalendar').hasClass('is-collapsed')) window.heroCalState = { open: true, type: 'all', day: bugun };
                    })();
                    applyWidgetSettings();
                    applySavedWidgetOrder();
                    if (typeof initMacControls === 'function') initMacControls();
                    window.requestAnimationFrame(() => window.requestAnimationFrame(scheduleDashboardReveal));
                    
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
