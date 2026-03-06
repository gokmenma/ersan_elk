<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\PersonelModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Model\TalepModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Helper\Alert;
use App\Helper\Helper;
use App\Model\PermissionsModel;
use App\Model\PuantajModel;
use App\Model\EndeksOkumaModel;
use App\Model\NobetModel;
use App\Model\PersonelHareketleriModel;
use App\Model\GorevModel;

$personelModel = new PersonelModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$talepModel = new TalepModel();
$systemLogModel = new SystemLogModel();
$puantajModel = new PuantajModel();
$endeksOkumaModel = new EndeksOkumaModel();
$nobetModel = new NobetModel();
$hareketModel = new PersonelHareketleriModel();
$gorevModel = new GorevModel();

if (Gate::allows("ana_sayfa")) {

    $bugun = date('Y-m-d');
    $nobetciler = $nobetModel->getNobetlerByTarih($bugun);
    $gec_kalan_sayisi = $hareketModel->getGecKalanlarCount($_SESSION['firma_id'] ?? null);
    $yaklasan_gorevler = $gorevModel->getYaklasanGorevler($_SESSION['firma_id'] ?? 0, 5);

    // Dashboard Ayarlarını Çerezden Oku
    $extraStats = $personelModel->getAdvancedDashboardStats();
    $dailyWorkStats = $puantajModel->getDailyStats();
    $dailyReadingTotal = $endeksOkumaModel->getDailyStats();
    $monthlyWorkStats = $puantajModel->getMonthlyStats();
    $monthlyReadingTotal = $endeksOkumaModel->getMonthlyStats();
    $kacakDailyTotal = $puantajModel->getKacakDailyStats();
    $kacakMonthlyTotal = $puantajModel->getKacakMonthlyStats();
    $extraStatsMonthly = $personelModel->getMonthlyAdvancedDashboardStats();
    $saved_settings = isset($_COOKIE['dashboard_settings']) ? json_decode($_COOKIE['dashboard_settings'], true) : [];

    $last_update_endeks = $last_update_isler = $last_update_sayac = null;
    $db_conn = $personelModel->getDb();
    try {
        $stmt_updates = $db_conn->prepare("SELECT
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
        $stmt_updates->execute([':firma_id' => $_SESSION['firma_id'] ?? 0]);
        $updates = $stmt_updates->fetch(PDO::FETCH_ASSOC) ?: [];
        $last_update_endeks = $updates['last_update_endeks'] ?? null;
        $last_update_isler = $updates['last_update_isler'] ?? null;
        $last_update_sayac = $updates['last_update_sayac'] ?? null;
    } catch (\Exception $e) {
    }

    if (!function_exists('getWidgetWidth')) {
        function getWidgetWidth($id, $default)
        {
            global $saved_settings;
            return $saved_settings[$id]['width'] ?? $default;
        }
    }

    if (!function_exists('getWidgetHeight')) {
        function getWidgetHeight($id, $default)
        {
            global $saved_settings;
            return $saved_settings[$id]['height'] ?? $default;
        }
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
    $recent_logs = $systemLogModel->getRecentLogs(10);


    $istatistik = $personelModel->personelSayilari();

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
    $db = $personelModel->getDb();

    // Avanslar
    $stmt = $db->prepare("SELECT count(*) as count FROM personel_avanslari WHERE durum = 'beklemede' AND silinme_tarihi IS NULL");
    $stmt->execute();
    $avans_count = $stmt->fetch(PDO::FETCH_OBJ)->count;

    // İzinler
    try {
        $izin_count = $izinModel->getBekleyenIzinSayisi();
    } catch (\Exception $e) {
        $izin_count = 0;
    }

    // Talepler
    $stmt = $db->prepare("SELECT count(*) as count FROM personel_talepleri WHERE durum != 'cozuldu' AND silinme_tarihi IS NULL");
    $stmt->execute();
    $talep_count = $stmt->fetch(PDO::FETCH_OBJ)->count;

    $personel_talep_sayisi = $avans_count + $izin_count + $talep_count;

    // Son Talepleri Listeleme
// Avanslar
    $stmt = $db->prepare("SELECT 'Avans' as tip, id, personel_id, talep_tarihi as tarih, durum, tutar as detay FROM personel_avanslari WHERE durum = 'beklemede' AND silinme_tarihi IS NULL LIMIT 5");
    $stmt->execute();
    $avanslar = $stmt->fetchAll(PDO::FETCH_OBJ);

    // İzinler
    try {
        $izinler = $izinModel->getBekleyenIzinlerForDashboard(5);
    } catch (\Exception $e) {
        $izinler = [];
    }

    // Talepler
    $stmt = $db->prepare("SELECT 'Talep' as tip, id, personel_id, olusturma_tarihi as tarih, durum, baslik as detay FROM personel_talepleri WHERE durum != 'cozuldu' AND silinme_tarihi IS NULL LIMIT 5");
    $stmt->execute();
    $talepler = $stmt->fetchAll(PDO::FETCH_OBJ);

    $all_requests = array_merge($avanslar, $izinler, $talepler);

    // Tarihe göre sırala
    usort($all_requests, function ($a, $b) {
        return strtotime($b->tarih) - strtotime($a->tarih);
    });

    $recent_requests = array_slice($all_requests, 0, 10);

    // Personel bilgilerini çek
    $personel_map = [];
    if (!empty($recent_requests)) {
        $p_ids = array_unique(array_map(function ($r) {
            return $r->personel_id;
        }, $recent_requests));
        if (!empty($p_ids)) {
            $ids_str = implode(',', $p_ids);
            $stmt = $db->prepare("SELECT id, adi_soyadi, resim_yolu, departman FROM personel WHERE id IN ($ids_str)");
            $stmt->execute();
            $personels = $stmt->fetchAll(PDO::FETCH_OBJ);
            foreach ($personels as $p) {
                $personel_map[$p->id] = $p;
            }
        }
    }

    // Şu anda izinde olanlar
    try {
        $active_leaves = $izinModel->getAktifIzinler(10);
    } catch (\Exception $e) {
        $active_leaves = [];
    }

    // Chart değişkenleri (Placeholder values for now)
    $months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $totals = [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65];
    $toplam_gelir = 50000;
    $toplam_gider = 30000;
    $toplam_bakiye = 20000;

    // Araç Verilerini Çek (Dashboard Hatırlatıcı için)
    $aracModelHome = new \App\Model\AracModel();
    $aracStats = $aracModelHome->getStats();
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

    $db = $personelModel->getDb();

    // Hem geçmiş gönderimleri kapatmak, hem de geçerli olanları listelemenin sorgusu
    // (etkinlik_tarihi NULL ise geçerlidir, atanmışsa CURDATE() veya sonrası olmalıdır)
    $duyuruSql = "SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi 
                  FROM duyurular 
                  WHERE silinme_tarihi IS NULL 
                  AND ana_sayfada_goster = 1
                  AND firma_id = :firma_id
                  AND durum = 'Yayında'
                  AND (etkinlik_tarihi IS NULL OR etkinlik_tarihi >= CURDATE())
                  ORDER BY id DESC LIMIT 5";
    $stmt = $db->prepare($duyuruSql);
    $stmt->execute([':firma_id' => $_SESSION['firma_id']]);
    $duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <div class="col-md-6 col-xl-4 widget-item" id="widget-ana-slider" style="margin-bottom: 1.5rem; position: relative;">
            <!-- Drag Handle (Separated from Carousel to avoid event blocking) -->
            <div class="drag-handle shadow-sm"
                style="position: absolute; top: 12px; left: 20px; z-index: 1000; cursor: move; background: rgba(0,0,0,0.2); border-radius: 4px; padding: 2px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);">
                <i class='bx bx-grid-vertical text-white' style="font-size: 1.2rem; opacity: 0.8;"></i>
            </div>

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
    ?>
    <?php if (\App\Service\Gate::allows("personel_listesi")) {
        ob_start(); ?>
        <div class="col-md-6 col-xl-4 widget-item" id="widget-personel-ozet">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
                style="border-radius: 12px; background: #fff;">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h6 class="fw-bold text-dark mb-1 d-flex align-items-center"
                                style="font-size: 1.1rem; letter-spacing: -0.01em;">
                                <i class='bx bx-grid-vertical drag-handle me-1 text-muted'></i> Personel Durumu
                            </h6>
                            <p class="text-muted mb-0 ms-4" style="font-size: 0.8rem;">Toplam Personel</p>
                            <h4 class="mb-0 text-dark fw-bold ms-4"><?php echo $istatistik->toplam_personel ?? 0; ?> <span
                                    style="font-size: 0.9rem; font-weight: normal; color: #6c757d;">adet</span></h4>
                        </div>
                        <a href="index.php?p=personel/list" class="text-primary fw-semibold small text-decoration-none"
                            style="font-size: 0.8rem;">Personel Listesine Git</a>
                    </div>

                    <?php
                    $toplam_p = ($istatistik->toplam_personel ?? 0) ?: 1;
                    $aktif_p = $istatistik->aktif_personel ?? 0;
                    $saha_p = $extraStats->sahadaki_personel ?? 0;
                    $izinli_p = $extraStats->izinli_personel ?? 0;
                    $diger_p = max(0, $aktif_p - $saha_p - $izinli_p);
                    $pasif_p = $istatistik->pasif_personel ?? 0;

                    $s_rate = ($saha_p / $toplam_p) * 100;
                    $d_rate = ($diger_p / $toplam_p) * 100;
                    $i_rate = ($izinli_p / $toplam_p) * 100;
                    $p_rate = ($pasif_p / $toplam_p) * 100;
                    ?>
                    <div class="progress mb-4" style="height: 20px; border-radius: 4px;">
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $s_rate; ?>%; background-color: #0d6efd;"
                            title="Saha: <?php echo $saha_p; ?>"></div>
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $d_rate; ?>%; background-color: #0dcaf0;"
                            title="İçeride/Diğer: <?php echo $diger_p; ?>"></div>
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $i_rate; ?>%; background-color: #ffc107;"
                            title="İzinli: <?php echo $izinli_p; ?>"></div>
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $p_rate; ?>%; background-color: #adb5bd;"
                            title="Pasif: <?php echo $pasif_p; ?>"></div>
                    </div>

                    <div class="row mt-auto">
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #0d6efd; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">Saha Görevlisi</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $saha_p; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #0dcaf0; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">İçeride / Diğer</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $diger_p; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #ffc107; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">İzinli</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $izinli_p; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #adb5bd; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">Pasif</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $pasif_p; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-personel-ozet'] = ob_get_clean();
    }

    if (\App\Service\Gate::allows("arac_takip_yonetim")) {
        ob_start(); ?>
        <div class="col-md-6 col-xl-4 widget-item" id="widget-arac-ozet">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
                style="border-radius: 12px; background: #fff;">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h6 class="fw-bold text-dark mb-1 d-flex align-items-center"
                                style="font-size: 1.1rem; letter-spacing: -0.01em;">
                                <i class='bx bx-grid-vertical drag-handle me-1 text-muted'></i> Araç Durumu
                            </h6>
                            <?php
                            $sahadaki_arac = $extraStats->sahadaki_arac ?? 0;
                            $servisteki_arac = $extraStats->servisteki_arac ?? 0;
                            $bosta_arac = $aracStats->bosta_arac ?? 0;
                            $toplam_arac = $aracStats->toplam_arac ?? ($sahadaki_arac + $servisteki_arac + $bosta_arac);
                            $toplam_a_div = $toplam_arac ?: 1;
                            $saha_a_yuzde = ($sahadaki_arac / $toplam_a_div) * 100;
                            $servis_a_yuzde = ($servisteki_arac / $toplam_a_div) * 100;
                            $bosta_a_yuzde = ($bosta_arac / $toplam_a_div) * 100;
                            ?>
                            <p class="text-muted mb-0 ms-4" style="font-size: 0.8rem;">Toplam Araç</p>
                            <h4 class="mb-0 text-dark fw-bold ms-4"><?php echo $toplam_arac; ?> <span
                                    style="font-size: 0.9rem; font-weight: normal; color: #6c757d;">adet</span></h4>
                        </div>
                        <a href="index.php?p=arac-takip/list" class="text-primary fw-semibold small text-decoration-none"
                            style="font-size: 0.8rem;">Araç Listesine Git</a>
                    </div>

                    <div class="progress mb-4" style="height: 20px; border-radius: 4px;">
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $saha_a_yuzde; ?>%; background-color: #198754;"
                            title="Sahada: <?php echo $sahadaki_arac; ?>"></div>
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $servis_a_yuzde; ?>%; background-color: #dc3545;"
                            title="Serviste: <?php echo $servisteki_arac; ?>"></div>
                        <div class="progress-bar" role="progressbar"
                            style="width: <?php echo $bosta_a_yuzde; ?>%; background-color: #ffc107;"
                            title="Boşta: <?php echo $bosta_arac; ?>"></div>
                    </div>

                    <div class="row mt-auto">
                        <div class="col-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #198754; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">Saha Aracı</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $sahadaki_arac; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #dc3545; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">Servis / Pasif</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $servisteki_arac; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div style="width: 3px; height: 32px; background-color: #ffc107; margin-right: 12px;"></div>
                                <div>
                                    <p class="text-muted small mb-0 font-size-11">Boşta</p>
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo $bosta_arac; ?> Adet</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-arac-ozet'] = ob_get_clean();
    }

    if (\App\Service\Gate::allows("talepler")) {
        ob_start(); ?>
        <div class="col-md-2 widget-item" id="widget-bekleyen-talepler">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
                style="--card-color: #f6c23e; border-bottom: 3px solid var(--card-color) !important; --delay: 0.6s">
                <div class="card-body p-3 pb-2">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(246, 194, 62, 0.1);">
                            <i class="bx bx-time-five fs-4" style="color: #f6c23e;"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TALEP</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BEKLEYEN TALEPLER</p>
                    <h4 class="mb-0 fw-bold bordro-text-heading">
                        <?php echo $personel_talep_sayisi ?? 0; ?>
                        <span class="trend-badge <?php echo $personel_talep_sayisi > 0 ? 'down' : 'up'; ?> ms-1">
                            <?php echo $personel_talep_sayisi > 0 ? 'Dikkat' : 'Stabil'; ?>
                        </span>
                    </h4>
                    <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Onay bekleyen işlemler</div>
                    <div class="card-footer-actions mt-2 d-flex justify-content-end">
                        <a href="index.php?p=talepler/list" class="btn btn-xs btn-soft-warning rounded-pill">
                            <i class="bx bx-right-arrow-alt"></i> Git
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-bekleyen-talepler'] = ob_get_clean();
    }

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-gec-kalanlar">
        <a href="index.php?p=personel-takip/list&tab=tabGecKalanlar" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
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
                </div>
            </div>
        </a>
    </div>
    <?php $widgets['widget-gec-kalanlar'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-nobetciler">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important; --delay: 0.75s">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                        <i class="bx bx-calendar-star fs-4" style="color: #556ee6;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NÖBET</span>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BUGÜNKÜ NÖBETÇİLER</p>

                <div class="grid-content-area">
                    <?php if (empty($nobetciler)): ?>
                        <div class="text-center py-2">
                            <p class="text-muted mb-0 small">Kayıt yok</p>
                        </div>
                    <?php else: ?>
                        <div class="nobetci-list" style="max-height: 120px; overflow-y: auto;">
                            <?php foreach (array_slice($nobetciler, 0, 5) as $nobet): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?php echo !empty($nobet->resim_yolu) ? $nobet->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>"
                                        class="rounded-circle avatar-xs me-2" style="width: 28px; height: 28px;">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <h6 class="mb-0 font-size-12 text-truncate"><?php echo $nobet->adi_soyadi; ?></h6>
                                        <small class="text-muted font-size-11"><?php echo $nobet->cep_telefonu; ?></small>
                                    </div>
                                    <?php if ($nobet->cep_telefonu): ?>
                                        <a href="tel:<?php echo $nobet->cep_telefonu; ?>" class="text-success ms-1 bx-no-drag">
                                            <i class="bx bx-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="javascript:void(0);" class="text-primary ms-1 btn-send-nobet-reminder bx-no-drag"
                                        data-id="<?php echo Security::encrypt($nobet->personel_id); ?>"
                                        data-name="<?php echo $nobet->adi_soyadi; ?>" title="Bildirim Gönder">
                                        <i class="bx bx-bell"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer-actions mt-2">
                    <div class="sub-text m-0" style="font-size: 10px; color: #858796;">
                        <?php echo count($nobetciler); ?> personel nöbetçi
                    </div>
                    <a href="index.php?p=nobet/list" class="btn btn-xs btn-soft-primary rounded-pill">
                        <i class="bx bx-calendar"></i> Takvim
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-nobetciler'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-12 mt-4 mb-3">
        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom border-light">
            <h5 class="mb-0 text-secondary fw-bold" style="font-family: 'Outfit', sans-serif; opacity: 0.8;">
                <i class="bx bx-stats me-1"></i> Operasyonel İstatistikler
            </h5>
        </div>
    </div>
    <?php $widgets['widget-row-break'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-gunluk-muhurleme">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative"
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
                    data-daily="<?php echo $dailyWorkStats->muhurleme ?? 0; ?>"
                    data-monthly="<?php echo $monthlyWorkStats->muhurleme ?? 0; ?>" data-label-daily="GÜNLÜK MÜHÜRLEME"
                    data-label-monthly="AYLIK MÜHÜRLEME" data-sub-daily="Bugün yapılan mühürleme"
                    data-sub-monthly="Bu ay yapılan mühürleme">
                    <?php echo $dailyWorkStats->muhurleme ?? 0; ?>
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
                        class="fw-bold"><?php echo $last_update_isler ? date('d.m.Y H:i', strtotime($last_update_isler)) : '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-muhurleme'] = ob_get_clean();



    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-gunluk-kesme-acma">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative"
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
                    data-daily="<?php echo $dailyWorkStats->kesme_acma ?? 0; ?>"
                    data-monthly="<?php echo $monthlyWorkStats->kesme_acma ?? 0; ?>" data-label-daily="GÜNLÜK KESME AÇMA"
                    data-label-monthly="AYLIK KESME AÇMA" data-sub-daily="Bugün yapılan kesme/açma"
                    data-sub-monthly="Bu ay yapılan kesme/açma">
                    <?php echo $dailyWorkStats->kesme_acma ?? 0; ?>
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
                        class="fw-bold"><?php echo $last_update_isler ? date('d.m.Y H:i', strtotime($last_update_isler)) : '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-kesme-acma'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-gunluk-endeks-okuma">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative"
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
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value" data-daily="<?php echo $dailyReadingTotal ?? 0; ?>"
                    data-monthly="<?php echo $monthlyReadingTotal ?? 0; ?>" data-label-daily="GÜNLÜK ENDEKS OKUMA"
                    data-label-monthly="AYLIK ENDEKS OKUMA" data-sub-daily="Bugün okunan endeksler"
                    data-sub-monthly="Bu ay okunan endeksler">
                    <?php echo $dailyReadingTotal ?? 0; ?>
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
                        class="fw-bold"><?php echo $last_update_endeks ? date('d.m.Y H:i', strtotime($last_update_endeks)) : '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-endeks-okuma'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-gunluk-sayac-degisimi">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative"
            style="--card-color: #1cc88a; border-bottom: 3px solid var(--card-color) !important; --delay: 1.1s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container d-flex justify-content-between align-items-start">
                    <div class="icon-box" style="background: rgba(28, 200, 138, 0.1);">
                        <i class="bx bx-refresh fs-4" style="color: #1cc88a;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <a href="javascript:void(0);" class="btn-api-sync text-muted" data-action="online-puantaj-sorgula"
                            data-active-tab="sokme_takma" data-bs-toggle="tooltip" title="Online sorgula(API)">
                            <i class="bx bx-refresh fs-5"></i>
                        </a>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞ</span>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                    SAYAÇ DEĞİŞİMİ</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                    data-daily="<?php echo $dailyWorkStats->sayac_degisimi ?? 0; ?>"
                    data-monthly="<?php echo $monthlyWorkStats->sayac_degisimi ?? 0; ?>"
                    data-label-daily="GÜNLÜK SAYAÇ DEĞİŞİMİ" data-label-monthly="AYLIK SAYAÇ DEĞİŞİMİ"
                    data-sub-daily="Bugün yapılan sayaç değişimi" data-sub-monthly="Bu ay yapılan sayaç değişimi">
                    <?php echo $dailyWorkStats->sayac_degisimi ?? 0; ?>
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
                        class="fw-bold"><?php echo $last_update_isler ? date('d.m.Y H:i', strtotime($last_update_isler)) : '-'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-sayac-degisimi'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-kacak-sayisi">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card position-relative"
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
                    data-daily="<?php echo $kacakDailyTotal->toplam ?? 0; ?>"
                    data-monthly="<?php echo $kacakMonthlyTotal->toplam ?? 0; ?>" data-label-daily="GÜNLÜK KAÇAK"
                    data-label-monthly="AYLIK KAÇAK" data-sub-daily="Bugün tespit edilen/girilen"
                    data-sub-monthly="Bu ay tespit edilen/girilen">
                    <?php echo $kacakDailyTotal->toplam ?? 0; ?>
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
                    <a href="index.php?p=puantaj/veri-yukleme&tab=kacak_kontrol"
                        class="btn btn-xs btn-soft-danger rounded-pill">
                        <i class="bx bx-right-arrow-alt"></i> Git
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-kacak-sayisi'] = ob_get_clean();

    ob_start(); ?>
    <div class="<?php echo getWidgetWidth('widget-endeks-karsilastirma', 'col-12'); ?> widget-item"
        id="widget-endeks-karsilastirma">
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
    <?php $widgets['widget-endeks-karsilastirma'] = ob_get_clean();

    if (\App\Service\Gate::allows("gorevler")) {
        ob_start(); ?>
    <div class="<?php echo getWidgetWidth('widget-yaklasan-gorevler', 'col-md-6'); ?> widget-item"
        id="widget-yaklasan-gorevler">
        <div class="card summary-card border-0 shadow-sm"
            style="border-radius: 16px; background: #fff;">
            <div class="card-header bg-transparent align-items-center d-flex justify-content-between px-4 py-3"
                style="border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex align-items-center gap-2">
                    <i class='bx bx-grid-vertical drag-handle text-muted cursor-move'></i>
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2" style="font-family: 'Inter', sans-serif; font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                        Yaklaşan Görevler
                    </h5>
                    <span class="badge rounded-pill bg-light text-muted fw-bold ms-1" style="font-size: 0.8rem;"><?php echo count($yaklasan_gorevler); ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border: none; background: #f8fafc;" title="Yenile" onclick="window.location.reload();">
                        <i class="bx bx-refresh fs-5 text-muted"></i>
                    </button>
                    <a href="index.php?p=gorevler/list"
                        class="btn btn-sm btn-soft-primary rounded-pill fw-semibold border-0"
                        style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                        Tümü <i class="bx bx-right-arrow-alt ms-1"></i>
                    </a>
                    <?php echo getWidthControl(); ?>
                </div>
            </div>
            <div class="card-body p-4"
                style="min-height: <?php echo getWidgetHeight('widget-yaklasan-gorevler', 'auto'); ?>; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                <?php if (empty($yaklasan_gorevler)): ?>
                    <div class="text-center py-5 d-flex flex-column align-items-center justify-content-center">
                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 64px; height: 64px;">
                            <i class="bx bx-check" style="font-size: 32px; color: #10b981;"></i>
                        </div>
                        <h6 class="text-slate-800 fw-semibold mb-1" style="color: #1e293b;">Görev Yok</h6>
                        <p class="text-slate-500 mb-0" style="font-size: 0.875rem; color: #64748b;">Yaklaşan herhangi bir göreviniz bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <div class="yaklasan-gorev-list d-flex flex-column gap-2" style="margin-bottom:0">
                        <?php foreach ($yaklasan_gorevler as $gorev): ?>
                            <?php
                            $renk = $gorev->liste_renk ?: '#4f46e5';
                            list($r, $g, $b) = sscanf($renk, "#%02x%02x%02x") ?: [79, 70, 229];
                            $rgba_bg = "rgba($r, $g, $b, 0.1)";

                            $tarihVar = !empty($gorev->tarih);
                            $isGecikti = false;
                            $isBugun = false;

                            if ($tarihVar) {
                                $isGecikti = (strtotime($gorev->tarih . ' ' . ($gorev->saat ?? '23:59:59')) < time());
                                $isBugun = ($gorev->tarih == date('Y-m-d'));
                            }

                            if ($isGecikti) {
                                $durum_ikon = '<i class="bx bxs-error-circle text-danger" style="font-size: 0.85rem;"></i>';
                            } elseif ($isBugun) {
                                $durum_ikon = '<i class="bx bxs-circle text-warning" style="font-size: 8px;"></i>';
                            } elseif ($tarihVar) {
                                $durum_ikon = '<i class="bx bxs-circle text-success" style="font-size: 8px;"></i>';
                            } else {
                                $durum_ikon = '<i class="bx bxs-circle text-muted" style="font-size: 8px;"></i>';
                            }
                            ?>
                            <div class="card border-0 task-item-hover shadow-none mb-0"
                                style="border-radius: 12px; background: #fff; border: 1px solid #f1f5f9 !important; transition: all 0.2s; cursor: pointer;"
                                onclick="window.location.href='index.php?p=gorevler/list&task_id=<?php echo $gorev->id; ?>'">
                                
                                <div class="card-body p-2 d-flex align-items-center gap-2">
                                    <!-- Colored Icon Box (Compact) -->
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" 
                                        style="width: 32px; height: 32px; background-color: <?php echo $rgba_bg; ?>; color: <?php echo $renk; ?>;">
                                        <i class="bx bx-task font-size-14"></i>
                                    </div>
                                    
                                    <!-- Content Area -->
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #1e293b !important;">
                                                <?php echo htmlspecialchars($gorev->baslik); ?>
                                            </h6>
                                            <div class="flex-shrink-0 d-flex align-items-center gap-1">
                                                <span class="text-muted" style="font-size: 0.7rem;">
                                                    <?php echo $tarihVar ? date('d M', strtotime($gorev->tarih)) : 'Tarih Yok'; ?>
                                                </span>
                                                <?php echo $durum_ikon; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-0">
                                            <span class="fw-medium text-muted text-truncate" style="font-size: 0.7rem; opacity: 0.8;">
                                                <i class="bx bx-folder-open me-1" style="color: <?php echo $renk; ?>;"></i>
                                                <?php echo htmlspecialchars($gorev->liste_adi); ?>
                                            </span>
                                            <?php $gorevPersonel = isset($personel_map[$gorev->olusturan_id]) ? $personel_map[$gorev->olusturan_id] : null; ?>
                                            <?php if ($gorevPersonel): ?>
                                            <span class="text-muted" style="font-size: 0.7rem;">• <?php echo explode(' ', trim($gorevPersonel->adi_soyadi))[0]; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Minimal Detail Arrow -->
                                    <div class="flex-shrink-0 ps-1">
                                        <i class="bx bx-chevron-right text-muted opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <style>
                            .task-item-hover:hover {
                                background: #f8fafc !important;
                                border-color: #e2e8f0 !important;
                                transform: translateX(2px);
                            }
                        </style>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
        <?php $widgets['widget-yaklasan-gorevler'] = ob_get_clean();
    }

    if (\App\Service\Gate::allows("gorev_bildirim_log_kayitlari")) {
        // Giriş kayıtları sorgusu
        $personelLogs = [];
        $kullaniciLogs = [];

        try {
            $personelStmt = $db->prepare("
                SELECT p.adi_soyadi, pg.giris_tarihi as tarih, pg.ip_adresi, pg.tarayici
                FROM personel_giris_loglari pg JOIN personel p ON p.id = pg.personel_id
                WHERE p.firma_id = :firma_id
                ORDER BY pg.giris_tarihi DESC LIMIT 10
            ");
            $personelStmt->execute(['firma_id' => $_SESSION['firma_id']]);
            $personelLogs = $personelStmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Hata durumunda boş dizi
        }

        try {
            $kullaniciStmt = $db->prepare("
                SELECT u.adi_soyadi, sl.created_at as tarih, SUBSTR(sl.description, LOCATE('IP:', sl.description) + 4) as ip_adresi, 'Sistem' as tarayici
                FROM system_logs sl JOIN users u ON u.id = sl.user_id
                WHERE sl.action_type = 'Başarılı Giriş' AND FIND_IN_SET(:firma_id, u.firma_ids) ORDER BY sl.created_at DESC LIMIT 10
            ");
            $kullaniciStmt->execute(['firma_id' => $_SESSION['firma_id']]);
            $kullaniciLogs = $kullaniciStmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Hata durumunda boş dizi
        }
        ob_start(); ?>
        <div class="<?php echo getWidgetWidth('widget-bildirimler', 'col-12'); ?> widget-item" id="widget-bildirimler">
            <div class="card summary-card"
                style="background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(248,250,252,0.99)); border: 1px solid rgba(226,232,240,0.8); border-radius: 12px; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.05), 0 2px 5px -2px rgba(0,0,0,0.02);">
                <div class="card-header align-items-center d-flex flex-wrap gap-2">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class='bx bx-grid-vertical drag-handle'></i>
                    </h5>
                    <div class="flex-shrink-0 flex-grow-1" style="align-self: flex-end;">
                        <ul class="nav nav-tabs card-header-tabs m-0" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#gorev-tab" role="tab">
                                    Görev ve Bildirimler
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#personel-giris-tab" role="tab">
                                    Personel Girişleri
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#kullanici-giris-tab" role="tab">
                                    Yönetici Girişleri
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <a href="index.php?p=gorev-bildirimler" class="btn btn-sm btn-soft-primary rounded-pill">
                            <i class="bx bx-list-ul me-1"></i> Tümünü Gör
                        </a>
                        <?php echo getWidthControl(); ?>
                    </div>
                </div>
                <div class="card-body"
                    style="padding: 0; min-height: <?php echo getWidgetHeight('widget-bildirimler', 'auto'); ?>;">
                    <div class="tab-content"
                        style="height: <?php echo getWidgetHeight('widget-bildirimler', 'auto'); ?>; overflow-y: auto;">
                        <div class="tab-pane active" id="gorev-tab" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-centered table-nowrap table-hover mb-0 align-middle">
                                    <thead style="background: rgba(248,250,252,0.8); position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Seviye</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                İşlem Tipi</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                İçerik</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Tarih</th>
                                            <th class="text-center"
                                                style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_logs)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4" style="color: #64748b;">
                                                    <div class="avatar-sm mx-auto mb-2">
                                                        <div class="avatar-title rounded-circle bg-light text-muted"><i
                                                                class="bx bx-x"></i></div>
                                                    </div>
                                                    Kayıt bulunmamaktadır.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_logs as $log): ?>
                                                <?php
                                                $logLevel = $log->level ?? 0;
                                                if ($logLevel >= 2) {
                                                    $levelBadge = '<span class="badge bg-soft-danger text-danger px-2 py-1 border border-danger" style="border-radius: 4px;"><i class="bx bx-error-circle me-1"></i>Kritik</span>';
                                                    $levelIcon = 'bx bx-error-circle text-muted';
                                                } elseif ($logLevel >= 1) {
                                                    $levelBadge = '<span class="badge bg-soft-warning text-warning px-2 py-1 border border-warning" style="border-radius: 4px;"><i class="bx bx-error me-1"></i>Önemli</span>';
                                                    $levelIcon = 'bx bx-error text-muted';
                                                } else {
                                                    $levelBadge = '<span class="badge bg-soft-info text-info px-2 py-1 border border-info" style="border-radius: 4px;"><i class="bx bx-info-circle me-1"></i>Bilgi</span>';
                                                    $levelIcon = 'bx bx-info-circle text-muted';
                                                }
                                                ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;">
                                                    <td style="padding: 0.75rem 1rem;"><?php echo $levelBadge; ?></td>
                                                    <td style="padding: 0.75rem 1rem; color:#475569; font-weight:500;">
                                                        <i class="<?php echo $levelIcon; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($log->action_type); ?>
                                                    </td>
                                                    <td style="padding: 0.75rem 1rem; color:#64748b;">
                                                        <?php
                                                        $user_name = $log->adi_soyadi ?? 'Sistem';
                                                        $full_desc = htmlspecialchars($log->description);
                                                        $short_desc = mb_strimwidth($full_desc, 0, 80, "...");
                                                        echo $short_desc . " <small class='text-muted' style='opacity:0.7'>($user_name tarafından)</small>";
                                                        ?>
                                                    </td>
                                                    <td
                                                        style="padding: 0.75rem 1rem; color:#475569; font-weight:500; font-size:0.85rem;">
                                                        <?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>
                                                    </td>
                                                    <td class="text-center" style="padding: 0.75rem 1rem;">
                                                        <button type="button" class="btn btn-sm btn-light btn-log-detay"
                                                            style="border-radius: 6px; font-weight:500; color:#475569; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                                                            data-title="<?php echo htmlspecialchars($log->action_type); ?>"
                                                            data-user="<?php echo htmlspecialchars($user_name); ?>"
                                                            data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>"
                                                            data-content="<?php echo htmlspecialchars($log->description); ?>">
                                                            <i class="bx bx-show me-1 text-primary"></i> Detay
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- end tab pane -->

                        <div class="tab-pane" id="personel-giris-tab" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-borderless table-nowrap align-middle mb-0">
                                    <thead style="background: rgba(248,250,252,0.8); position: sticky; top: 0; z-index: 10;">
                                        <tr style="border-bottom: 2px solid #f1f5f9;">
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Ad Soyad</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Tarih</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Tarayıcı</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($personelLogs)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4" style="color: #64748b;">
                                                    <div class="avatar-sm mx-auto mb-2">
                                                        <div class="avatar-title rounded-circle bg-light text-muted"><i
                                                                class="bx bx-x"></i></div>
                                                    </div>
                                                    Kayıt bulunamadı.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($personelLogs as $ll): ?>
                                                <tr style="border-bottom: 1px solid #f8fafc; transition: all 0.2s;"
                                                    onmouseover="this.style.background='#f8fafc'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <td style="padding: 0.75rem 1rem;">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm me-3">
                                                                <span class="avatar-title rounded-circle"
                                                                    style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #4f46e5; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                                                    <?php echo mb_substr($ll->adi_soyadi, 0, 1, 'UTF-8'); ?>
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <h5 class="font-size-14 mb-0" style="color: #334155; font-weight: 600;">
                                                                    <?php echo htmlspecialchars($ll->adi_soyadi ?? ''); ?>
                                                                </h5>
                                                                <span class="badge bg-soft-info text-info font-size-11"
                                                                    style="border-radius: 4px;">Personel</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem 1rem;">
                                                        <div class="d-flex flex-column">
                                                            <span
                                                                style="color: #475569; font-weight: 500;"><?php echo date('d.m.Y', strtotime($ll->tarih)); ?></span>
                                                            <span style="color: #94a3b8; font-size: 0.75rem;"><i
                                                                    class="bx bx-time-five me-1"></i><?php echo date('H:i', strtotime($ll->tarih)); ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem 1rem;">
                                                        <div
                                                            style="background: rgba(241,245,249,0.8); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; color: #475569; border: 1px solid #e2e8f0;">
                                                            <?php echo htmlspecialchars($ll->tarayici ?? '-'); ?>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem 1rem;"><span
                                                            style="font-family: monospace; color: #64748b; font-size: 0.85rem; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo htmlspecialchars($ll->ip_adresi ?? '-'); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- end tab pane -->

                        <div class="tab-pane" id="kullanici-giris-tab" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-borderless table-nowrap align-middle mb-0">
                                    <thead style="background: rgba(248,250,252,0.8); position: sticky; top: 0; z-index: 10;">
                                        <tr style="border-bottom: 2px solid #f1f5f9;">
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Ad Soyad</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                Tarih</th>
                                            <th
                                                style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">
                                                IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($kullaniciLogs)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4" style="color: #64748b;">
                                                    <div class="avatar-sm mx-auto mb-2">
                                                        <div class="avatar-title rounded-circle bg-light text-muted"><i
                                                                class="bx bx-x"></i></div>
                                                    </div>
                                                    Kayıt bulunamadı.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($kullaniciLogs as $ll): ?>
                                                <tr style="border-bottom: 1px solid #f8fafc; transition: all 0.2s;"
                                                    onmouseover="this.style.background='#f8fafc'"
                                                    onmouseout="this.style.background='transparent'">
                                                    <td style="padding: 0.75rem 1rem;">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm me-3">
                                                                <span class="avatar-title rounded-circle"
                                                                    style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                                                    <?php echo mb_substr($ll->adi_soyadi, 0, 1, 'UTF-8'); ?>
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <h5 class="font-size-14 mb-0" style="color: #334155; font-weight: 600;">
                                                                    <?php echo htmlspecialchars($ll->adi_soyadi ?? ''); ?>
                                                                </h5>
                                                                <span class="badge bg-soft-success text-success font-size-11"
                                                                    style="border-radius: 4px;">Kullanıcı</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem 1rem;">
                                                        <div class="d-flex flex-column">
                                                            <span
                                                                style="color: #475569; font-weight: 500;"><?php echo date('d.m.Y', strtotime($ll->tarih)); ?></span>
                                                            <span style="color: #94a3b8; font-size: 0.75rem;"><i
                                                                    class="bx bx-time-five me-1"></i><?php echo date('H:i', strtotime($ll->tarih)); ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem 1rem;"><span
                                                            style="font-family: monospace; color: #64748b; font-size: 0.85rem; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo htmlspecialchars($ll->ip_adresi ?? '-'); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- end tab pane -->
                    </div><!-- end tab content -->
                </div>

            </div>
        </div>
        <?php $widgets['widget-bildirimler'] = ob_get_clean();
    }

    if (\App\Service\Gate::allows("talepler")) {
        ob_start(); ?>
        <div class="<?php echo getWidgetWidth('widget-talepler', 'col-md-6'); ?> widget-item" id="widget-talepler">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> Arıza/İzin/Avans Talepleri</h5>
                    <?php echo getWidthControl(); ?>
                </div>
                <div class="card-body"
                    style="height: <?php echo getWidgetHeight('widget-talepler', 'auto'); ?>; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel</th>
                                    <th>Talep Tipi</th>
                                    <th>Detay</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_requests)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Bekleyen talep bulunmamaktadır.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_requests as $req):
                                        $personel = $personel_map[$req->personel_id] ?? null;
                                        $badgeClass = 'badge-warning';
                                        if ($req->tip == 'Avans')
                                            $badgeClass = 'badge-success';
                                        if ($req->tip == 'İzin')
                                            $badgeClass = 'badge-primary';
                                        if ($req->tip == 'Talep')
                                            $badgeClass = 'badge-info';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($personel): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <img src="<?php echo !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>"
                                                                alt="" class="avatar-xs rounded-circle">
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="font-size-14 mb-1"><?php echo $personel->adi_soyadi; ?></h5>
                                                            <p class="text-muted mb-0 font-size-12">
                                                                <?php echo $personel->departman; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    Personel #<?php echo $req->personel_id; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><span
                                                    class="badge <?php echo $badgeClass; ?> font-size-12"><?php echo $req->tip; ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                if ($req->tip == 'Avans')
                                                    echo number_format($req->detay, 2) . ' ₺';
                                                elseif ($req->tip == 'İzin')
                                                    echo htmlspecialchars($req->detay);
                                                else
                                                    echo $req->detay;
                                                ?>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($req->tarih)); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-info btn-sm btn-home-detay"
                                                        data-id="<?php echo $req->id; ?>" data-tip="<?php echo $req->tip; ?>"
                                                        data-personel="<?php echo htmlspecialchars($personel ? $personel->adi_soyadi : 'Personel #' . $req->personel_id); ?>"
                                                        data-detay="<?php echo htmlspecialchars($req->tip == 'Avans' ? number_format($req->detay, 2) . ' ₺' : $req->detay); ?>"
                                                        data-tarih="<?php echo date('d.m.Y', strtotime($req->tarih)); ?>" title="Detay">
                                                        <i class='bx bx-show'></i>
                                                    </button>

                                                    <?php if ($req->tip == 'Avans'): ?>
                                                        <button type="button" class="btn btn-success btn-sm btn-avans-onayla"
                                                            data-id="<?php echo $req->id; ?>"
                                                            data-personel="<?php echo htmlspecialchars($personel ? $personel->adi_soyadi : 'Personel #' . $req->personel_id); ?>"
                                                            data-tutar="<?php echo $req->detay; ?>" title="Onayla">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-avans-reddet"
                                                            data-id="<?php echo $req->id; ?>"
                                                            data-personel="<?php echo htmlspecialchars($personel ? $personel->adi_soyadi : 'Personel #' . $req->personel_id); ?>"
                                                            title="Reddet">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                    <?php elseif ($req->tip == 'İzin'): ?>
                                                        <button type="button" class="btn btn-success btn-sm btn-izin-onayla"
                                                            data-id="<?php echo $req->id; ?>"
                                                            data-personel="<?php echo htmlspecialchars($personel ? $personel->adi_soyadi : 'Personel #' . $req->personel_id); ?>"
                                                            data-tur="<?php echo htmlspecialchars($req->detay); ?>"
                                                            data-gun="<?php echo $req->toplam_gun ?? 0; ?>" title="Onayla">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-izin-reddet"
                                                            data-id="<?php echo $req->id; ?>"
                                                            data-personel="<?php echo htmlspecialchars($personel ? $personel->adi_soyadi : 'Personel #' . $req->personel_id); ?>"
                                                            title="Reddet">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                    <?php elseif ($req->tip == 'Talep'): ?>
                                                        <button type="button" class="btn btn-success btn-sm btn-talep-cozuldu"
                                                            data-id="<?php echo $req->id; ?>"
                                                            data-personel="<?php echo htmlspecialchars($personel ? $personel->adi_soyadi : 'Personel #' . $req->personel_id); ?>"
                                                            data-baslik="<?php echo htmlspecialchars($req->detay); ?>" title="Çözüldü">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php
                                                    $tabParam = 'avans';
                                                    if ($req->tip == 'Avans')
                                                        $tabParam = 'avans';
                                                    elseif ($req->tip == 'İzin')
                                                        $tabParam = 'izin';
                                                    else
                                                        $tabParam = 'talep';
                                                    ?>
                                                    <a href="index.php?p=talepler/list&tab=<?php echo $tabParam; ?>"
                                                        class="btn btn-primary btn-sm" title="Talepler Sayfasına Git">
                                                        <i class='bx bx-right-arrow-alt'></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php $widgets['widget-talepler'] = ob_get_clean();
    }

    ob_start(); ?>
    <div class="<?php echo getWidgetWidth('widget-izindekiler', 'col-md-6'); ?> widget-item" id="widget-izindekiler">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> Şu Anda İzinde Olan Personeller</h5>
                <?php echo getWidthControl(); ?>
            </div>
            <div class="card-body"
                style="height: <?php echo getWidgetHeight('widget-izindekiler', 'auto'); ?>; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-centered table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Personel</th>
                                <th>İzin Tipi</th>
                                <th>Bitiş Tarihi</th>
                                <th>Kalan Süre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_leaves)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">Şu anda izinde olan personel bulunmamaktadır.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_leaves as $leave):
                                    $bitis = new DateTime($leave->bitis_tarihi);
                                    $bugun = new DateTime();
                                    $kalan = $bugun->diff($bitis)->days;

                                    $badgeClass = 'badge-primary';
                                    if ($leave->izin_tipi_adi == 'hastalik')
                                        $badgeClass = 'badge-danger';
                                    if ($leave->izin_tipi_adi == 'mazeret')
                                        $badgeClass = 'badge-warning';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <img src="<?php echo !empty($leave->resim_yolu) ? $leave->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>"
                                                        alt="" class="avatar-xs rounded-circle">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="font-size-14 mb-1"><?php echo $leave->adi_soyadi; ?></h5>
                                                    <p class="text-muted mb-0 font-size-12"><?php echo $leave->departman; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?> font-size-12">
                                                <?php echo htmlspecialchars($leave->izin_tipi_adi ?? $leave->izin_tipi ?? 'İzin'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($leave->bitis_tarihi)); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $kalan; ?> Gün Kaldı</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-izindekiler'] = ob_get_clean();

    ob_start(); ?>
    <div class="<?php echo getWidgetWidth('widget-is-turu-istatistikleri', 'col-md-6'); ?> widget-item"
        id="widget-is-turu-istatistikleri">
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
    <div class="<?php echo getWidgetWidth('widget-is-emri-sonucu-istatistikleri', 'col-md-6'); ?> widget-item"
        id="widget-is-emri-sonucu-istatistikleri">
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
    
        // Sıralamayı Çerezden Oku
        $saved_order = isset($_COOKIE['dashboard_order']) ? json_decode($_COOKIE['dashboard_order'], true) : null;
        $render_order = $saved_order ?: array_keys($widgets);

        // Slider her zaman üstte olmalı
        if (!in_array('widget-ana-slider', $render_order) && isset($widgets['widget-ana-slider'])) {
            array_unshift($render_order, 'widget-ana-slider');
        }
        ?>

    <div class="container-fluid">

        <style id="dashboard-skeleton-critical">
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
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false" style="font-weight: 500;">
                            <i class="bx bx-show me-1"></i> Kart Görünürlüğü
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0"
                            style="min-width: 280px; z-index: 1060;">
                            <li>
                                <h6 class="dropdown-header fw-bold">Gösterilecek Kartları Seçin</h6>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-ana-slider" checked>
                                    <strong>Haberler ve Duyurular</strong>
                                </label>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-personel-ozet" checked>
                                    Personel Durumu
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-arac-ozet" checked>
                                    Araç Durumu
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-bekleyen-talepler" checked>
                                    Bekleyen Talepler
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-gec-kalanlar" checked>
                                    Geç Kalanlar
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-nobetciler" checked>
                                    Bugünkü Nöbetçiler
                                </label>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-bildirimler" checked>
                                    Görev ve Bildirimler
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-talepler" checked>
                                    Arıza/İzin/Avans Talepleri
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-izindekiler" checked>
                                    İzinde Olan Personeller
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-is-turu-istatistikleri" checked>
                                    İş Türü İstatistikleri
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-is-emri-sonucu-istatistikleri" checked>
                                    İş Emri Sonuç İstatistikleri
                                </label>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-gunluk-kesme-acma" checked>
                                    Günlük Kesme Açma
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-gunluk-endeks-okuma" checked>
                                    Günlük Endeks Okuma
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-gunluk-sayac-degisimi" checked>
                                    Günlük Sayaç Değişimi
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-gunluk-muhurleme" checked>
                                    Günlük Mühürleme
                                </label>
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-kacak-sayisi" checked>
                                    Kaçak Kontrol
                                </label>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="form-check-input widget-toggle me-2"
                                        data-widget="widget-endeks-karsilastirma" checked>
                                    <strong>Endeks Karşılaştırma</strong>
                                </label>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reset-dashboard"
                        style="font-weight: 500;">
                        <i class="bx bx-reset me-1"></i> Varsayılan Yerleşime Dön
                    </button>
                </div>
            </div>

            <div class="row" id="dashboard-widgets">
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

            .bordro-summary-card .card-body:not(.no-mobile-grid) {
                display: grid !important;
                grid-template-columns: 36px 1fr auto;
                align-items: center;
                gap: 0 12px;
                padding: 12px !important;
            }

            .icon-label-container {
                display: contents !important;
            }

            .icon-box {
                width: 36px !important;
                height: 36px !important;
                grid-column: 1 !important;
                grid-row: 1 / span 2 !important;
                margin: 0 !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
            }

            .icon-box i {
                font-size: 1.1rem !important;
            }

            /* Target the tag/sync container on the right */
            .icon-label-container>span,
            .icon-label-container>div:not(.icon-box) {
                grid-column: 3 !important;
                grid-row: 1 / span 2 !important;
                justify-self: end;
            }

            .bordro-summary-card .card-body>p {
                grid-column: 2 !important;
                grid-row: 1 !important;
                margin: 0 !important;
                line-height: 1.2;
                font-size: 0.65rem !important;
                text-align: left !important;
            }

            .bordro-summary-card .card-body>h4 {
                grid-column: 2 !important;
                grid-row: 2 !important;
                margin: 0 !important;
                line-height: 1.2;
                font-size: 1.1rem !important;
                text-align: left !important;
            }

            .sub-text {
                grid-column: 1 / span 3 !important;
                margin-top: 8px !important;
                padding-top: 6px !important;
                border-top: 1px dashed rgba(0, 0, 0, 0.1);
                font-size: 9px !important;
            }

            .card-footer-actions {
                grid-column: 1 / span 3 !important;
                padding-top: 8px !important;
                margin-top: 6px !important;
            }

            .grid-content-area {
                grid-column: 1 / span 3 !important;
            }

            .btn-xs {
                padding: 2px 8px !important;
                font-size: 9px !important;
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

                if ('requestIdleCallback' in window) {
                    requestIdleCallback(() => revealPhase2Widgets(), { timeout: 500 });
                } else {
                    setTimeout(revealPhase2Widgets, 350);
                }
            };

            prepareStagedWidgets();

            window.addEventListener('load', () => {
                setTimeout(revealDashboardPage, 120);
            }, { once: true });

            setTimeout(() => {
                if (document.readyState === 'complete') {
                    revealDashboardPage();
                }
            }, 1800);

            const API_URL = 'views/talepler/api.php';

            // Load widget visibility from localStorage
            function loadWidgetVisibility() {
                const visibility = localStorage.getItem('dashboard_widget_visibility');
                if (visibility) {
                    const visibleWidgets = JSON.parse(visibility);
                    $('#dashboard-widgets .widget-item').each(function () {
                        const id = $(this).attr('id');
                        // Eğer localStorage'da yoksa varsayılan olarak göster (true)
                        const isVisible = visibleWidgets[id] !== false;
                        if (!isVisible) {
                            $(this).hide();
                        } else {
                            $(this).show();
                        }
                        $(`input[data-widget="${id}"]`).prop('checked', isVisible);
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
                $(`#${widgetId}`).fadeToggle(300);
                saveWidgetVisibility();
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
                                submitBtn.innerHTML = originalText;
                            }
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
            // Dashboard Config Persistence
            const dashboard = $("#dashboard-widgets");

            function saveDashboardConfig() {
                const order = dashboard.sortable("toArray");
                const settings = {};
                $("#dashboard-widgets .widget-item").each(function () {
                    const id = $(this).attr('id');

                    // Width
                    const classes = $(this).attr('class').split(' ');
                    const widthClass = classes.find(c => c.startsWith('col-'));

                    // Height
                    const height = $(this).find('.card-body').css('height');

                    if (id) {
                        settings[id] = {
                            width: widthClass,
                            height: height
                        };
                    }
                });

                const cookieOptions = "; path=/; max-age=" + (60 * 60 * 24 * 30);
                document.cookie = "dashboard_order=" + JSON.stringify(order) + cookieOptions;
                document.cookie = "dashboard_settings=" + JSON.stringify(settings) + cookieOptions;
            }

            // Dashboard Sortable Logic
            dashboard.sortable({
                handle: ".card-header, .card-header-flex, .bordro-summary-card, .drag-handle",
                cancel: ".btn-api-sync, .stats-local-btn, .btn, .bx-no-drag, .carousel-control-prev, .carousel-control-next, .carousel-indicators",
                placeholder: "ui-sortable-placeholder",
                start: function (e, ui) {
                    const classes = ui.item.attr('class');
                    ui.placeholder.attr('class', 'ui-sortable-placeholder ' + classes);
                },
                update: function (event, ui) {
                    saveDashboardConfig();
                }
            });

            // Card Resize Logic (Width)
            $(document).on('click', '.btn-resize-width', function (e) {
                e.preventDefault();
                const newWidth = $(this).data('width');
                const widget = $(this).closest('.widget-item');

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
                const cardBody = widget.find('.card-body');

                cardBody.css('height', newHeight);
                saveDashboardConfig();

                // Trigger window resize to let charts adjust
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 100);
            });

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
                        document.cookie = "dashboard_order=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
                        document.cookie = "dashboard_settings=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
                        localStorage.removeItem('dashboard_widget_visibility');
                        location.reload();
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

            // Online API Sync Logic
            $(document).on('click', '.btn-api-sync', function (e) {
                e.preventDefault();
                const $btn = $(this);
                const $icon = $btn.find('i');
                const action = $btn.data('action');
                const today = '<?php echo date('Y-m-d'); ?>';
                const firmaKodu = '<?php echo $_SESSION['firma_kodu'] ?? 17; ?>';

                if ($btn.hasClass('syncing')) return;

                $btn.addClass('syncing');
                $icon.addClass('bx-spin text-primary');

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
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sorgulama Başarılı',
                                    html: msg,
                                    timer: res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0 ? 5000 : 2000,
                                    showConfirmButton: res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Hata', res.message || 'Sorgulama sırasında bir hata oluştu.', 'error');
                            }
                        } catch (err) {
                            console.error("API Response Error:", err);
                            console.log("Raw Response:", response);
                            Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                        }
                    },
                    error: function () {
                        $btn.removeClass('syncing');
                        $icon.removeClass('bx-spin text-primary');
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

                function renderBolgeView(bolgeData, periods) {
                    const periodLabels = periods.map(p => p.label);
                    let html = '';

                    html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                    html += '<table class="table table-hover table-nowrap align-middle mb-0" style="font-size: 13px;">';

                    // Header
                    html += '<thead style="background: #f8fafc; position: sticky; top: 0; z-index: 5;">';
                    html += '<tr>';
                    html += '<th style="padding: 10px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; min-width: 160px;">Bölge</th>';
                    periodLabels.forEach((label, idx) => {
                        const isCurrent = periods[idx].is_current;
                        const bgColor = isCurrent ? 'rgba(99,102,241,0.08)' : 'transparent';
                        html += `<th class="text-center" style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: ${isCurrent ? '#6366f1' : '#64748b'}; font-weight: 700; background: ${bgColor}; min-width: 120px;">${label}</th>`;
                    });
                    html += '<th class="text-center" style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; min-width: 100px;">Değişim</th>';
                    html += '<th style="width: 40px;"></th>';
                    html += '</tr>';
                    html += '</thead>';

                    html += '<tbody>';
                    const bolgeKeys = Object.keys(bolgeData).sort();

                    // Firma toplamları
                    let firmaToplam = {};
                    periodLabels.forEach(label => { firmaToplam[label] = 0; });

                    bolgeKeys.forEach((bolge, bIdx) => {
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
                        const hasPersonel = bData.personeller && Object.keys(bData.personeller).length > 0;

                        html += `<tr class="bolge-row cursor-pointer" data-bolge="${bIdx}" style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;" ${hasPersonel ? `onclick="toggleBolgeDetail(${bIdx})"` : ''}>`;
                        html += `<td style="padding: 10px 16px;">`;
                        html += `<div class="d-flex align-items-center gap-2">`;
                        if (hasPersonel) {
                            html += `<i class="bx bx-chevron-right bolge-chevron-${bIdx} text-muted" style="font-size: 14px; transition: transform 0.2s;"></i>`;
                        } else {
                            html += `<span style="width: 14px;"></span>`;
                        }
                        html += `<div>`;
                        html += `<span class="fw-semibold" style="color: #334155;">${bolge}</span>`;
                        const pSayisi = bData.periods[periodLabels[periodLabels.length - 1]]?.personel_sayisi || 0;
                        if (pSayisi > 0) {
                            html += `<br><span class="text-muted" style="font-size: 10px;">${pSayisi} personel</span>`;
                        }
                        html += `</div></div></td>`;

                        periodValues.forEach((val, pIdx) => {
                            const isCurrent = periods[pIdx].is_current;
                            const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                            html += `<td class="text-center" style="padding: 10px 12px; background: ${bgColor};">`;
                            html += `<span class="fw-bold" style="color: #1e293b; font-size: 14px;">${formatNumber(val)}</span>`;
                            html += `</td>`;
                        });

                        // Trend
                        html += `<td class="text-center" style="padding: 10px 12px;">`;
                        if (trend.icon) {
                            html += `<span class="${trend.class} fw-bold" style="font-size: 13px;"><i class="bx ${trend.icon} me-1"></i>${trend.text}</span>`;
                        } else {
                            html += `<span class="text-muted">-</span>`;
                        }
                        html += `</td>`;
                        html += `<td style="padding: 10px 8px;">`;
                        if (hasPersonel) html += `<i class="bx bx-expand-vertical text-muted" style="font-size: 12px; opacity: 0.5;"></i>`;
                        html += `</td>`;
                        html += `</tr>`;

                        // Personel detay satırları (gizli)
                        if (hasPersonel) {
                            const personeller = bData.personeller;
                            Object.keys(personeller).forEach(pKey => {
                                const p = personeller[pKey];
                                const pPeriods = [];
                                periodLabels.forEach(l => pPeriods.push(p.periods[l]?.toplam || 0));
                                const pLastVal = pPeriods[pPeriods.length - 1];
                                const pPrevVal = pPeriods.length > 1 ? pPeriods[pPeriods.length - 2] : 0;
                                const pTrend = getTrendInfo(pLastVal, pPrevVal);

                                html += `<tr class="bolge-detail-${bIdx}" style="display: none; background: #fafbfc; border-bottom: 1px solid #f1f5f9; animation: fadeInDown 0.2s;">`;
                                html += `<td style="padding: 8px 16px 8px 48px;">`;
                                html += `<div>`;
                                html += `<span style="color: #475569; font-size: 12px;">${p.personel_adi}</span>`;
                                html += `<br><span class="text-muted" style="font-size: 10px;">${p.ekip_adi}</span>`;
                                html += `</div></td>`;

                                pPeriods.forEach((val, ppIdx) => {
                                    const isCurrent = periods[ppIdx].is_current;
                                    const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                    html += `<td class="text-center" style="padding: 8px 12px; background: ${bgColor}; font-size: 12px;">`;
                                    html += `<span class="fw-semibold" style="color: #475569;">${formatNumber(val)}</span>`;
                                    html += `</td>`;
                                });

                                html += `<td class="text-center" style="padding: 8px 12px; font-size: 11px;">`;
                                if (pTrend.icon) {
                                    html += `<span class="${pTrend.class}"><i class="bx ${pTrend.icon} me-1"></i>${pTrend.text}</span>`;
                                } else {
                                    html += `<span class="text-muted">-</span>`;
                                }
                                html += `</td>`;
                                html += `<td></td>`;
                                html += `</tr>`;
                            });
                        }
                    });

                    // Firma toplam footer
                    html += '<tr style="background: linear-gradient(135deg, #f0f1ff 0%, #e8eaff 100%); border-top: 2px solid #c7d2fe; font-weight: 700;">';
                    html += '<td style="padding: 12px 16px; color: #4338ca; font-size: 13px;"><i class="bx bx-buildings me-2"></i>GENEL TOPLAM</td>';
                    periodLabels.forEach((label, idx) => {
                        const isCurrent = periods[idx].is_current;
                        const bgColor = isCurrent ? 'rgba(99,102,241,0.12)' : 'transparent';
                        html += `<td class="text-center" style="padding: 12px 12px; color: #312e81; font-size: 15px; background: ${bgColor};">${formatNumber(firmaToplam[label])}</td>`;
                    });
                    const fLastVal = firmaToplam[periodLabels[periodLabels.length - 1]];
                    const fPrevVal = periodLabels.length > 1 ? firmaToplam[periodLabels[periodLabels.length - 2]] : 0;
                    const fTrend = getTrendInfo(fLastVal, fPrevVal);
                    html += `<td class="text-center" style="padding: 12px 12px;">`;
                    if (fTrend.icon) {
                        html += `<span class="${fTrend.class} fw-bold" style="font-size: 14px;"><i class="bx ${fTrend.icon} me-1"></i>${fTrend.text}</span>`;
                    }
                    html += `</td><td></td></tr>`;

                    html += '</tbody></table></div>';

                    // Küçük açıklama 
                    html += '<div class="px-3 py-2 d-flex align-items-center gap-3" style="border-top: 1px solid #f1f5f9; background: #fafbfc;">';
                    html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Her ayın 1\'i ile ' + endeksCompData.gun + '\'ı arası abone okuma sayıları karşılaştırılmaktadır.</span>';
                    html += '<a href="index.php?p=puantaj/raporlar&tab=karsilastirma" class="ms-auto btn btn-sm btn-outline-primary" style="font-size: 11px; border-radius: 6px;"><i class="bx bx-right-arrow-alt me-1"></i>Detaylı Rapor</a>';
                    html += '</div>';

                    $('#endeksCompBolge').html(html);
                }

                function renderPersonelView(personelData, periods) {
                    const periodLabels = periods.map(p => p.label);
                    let html = '';

                    html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                    html += '<table class="table table-hover table-nowrap align-middle mb-0" style="font-size: 13px;">';

                    html += '<thead style="background: #f8fafc; position: sticky; top: 0; z-index: 5;">';
                    html += '<tr>';
                    html += '<th style="padding: 10px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; min-width: 180px;">Personel</th>';
                    html += '<th style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; min-width: 100px;">Bölge</th>';
                    periodLabels.forEach((label, idx) => {
                        const isCurrent = periods[idx].is_current;
                        const bgColor = isCurrent ? 'rgba(99,102,241,0.08)' : 'transparent';
                        html += `<th class="text-center" style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: ${isCurrent ? '#6366f1' : '#64748b'}; font-weight: 700; background: ${bgColor}; min-width: 110px;">${label}</th>`;
                    });
                    html += '<th class="text-center" style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; min-width: 90px;">Değişim</th>';
                    html += '</tr>';
                    html += '</thead>';

                    html += '<tbody>';

                    // Personel listesini sırala (toplam azalan)
                    const sortedKeys = Object.keys(personelData).sort((a, b) => {
                        const lastLabel = periodLabels[periodLabels.length - 1];
                        const aVal = personelData[a].periods[lastLabel]?.toplam || 0;
                        const bVal = personelData[b].periods[lastLabel]?.toplam || 0;
                        return bVal - aVal;
                    });

                    sortedKeys.forEach(pKey => {
                        const p = personelData[pKey];
                        const pPeriods = [];
                        periodLabels.forEach(l => pPeriods.push(p.periods[l]?.toplam || 0));
                        const pLastVal = pPeriods[pPeriods.length - 1];
                        const pPrevVal = pPeriods.length > 1 ? pPeriods[pPeriods.length - 2] : 0;
                        const pTrend = getTrendInfo(pLastVal, pPrevVal);

                        html += `<tr style="border-bottom: 1px solid #f1f5f9;">`;
                        html += `<td style="padding: 10px 16px;">`;
                        html += `<span class="fw-semibold" style="color: #334155; font-size: 12px;">${p.personel_adi}</span>`;
                        html += `<br><span class="text-muted" style="font-size: 10px;">${p.ekip_adi}</span>`;
                        html += `</td>`;
                        html += `<td style="padding: 10px 12px;"><span class="badge bg-light text-dark" style="font-size: 10px; font-weight: 500;">${p.bolge}</span></td>`;

                        pPeriods.forEach((val, ppIdx) => {
                            const isCurrent = periods[ppIdx].is_current;
                            const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                            html += `<td class="text-center" style="padding: 10px 12px; background: ${bgColor};">`;
                            html += `<span class="fw-bold" style="color: #1e293b; font-size: 13px;">${formatNumber(val)}</span>`;
                            html += `</td>`;
                        });

                        html += `<td class="text-center" style="padding: 10px 12px;">`;
                        if (pTrend.icon) {
                            html += `<span class="${pTrend.class} fw-bold" style="font-size: 12px;"><i class="bx ${pTrend.icon} me-1"></i>${pTrend.text}</span>`;
                        } else {
                            html += `<span class="text-muted">-</span>`;
                        }
                        html += `</td>`;
                        html += `</tr>`;
                    });

                    html += '</tbody></table></div>';

                    html += '<div class="px-3 py-2" style="border-top: 1px solid #f1f5f9; background: #fafbfc;">';
                    html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Personeller mevcut ay okuma sayısına göre sıralanmıştır.</span>';
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

                // Sayfa yüklendiğinde veri çek
                loadEndeksComparison();
            })();
            // ========== /ENDEKS KARŞILAŞTIRMA KART LOGIC ==========

        });
    </script>
    <?php
} else {
    //Alert::danger("Bu sayfaya erişim yetkiniz yok!");
    /**Personelin yetkili olduğu ilk sayfaya yönlendir */
    $permissionModel = new PermissionsModel();
    $permissionModel->redirectFirstPersmissionPage();
}