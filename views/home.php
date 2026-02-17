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

$personelModel = new PersonelModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$talepModel = new TalepModel();
$systemLogModel = new SystemLogModel();
$puantajModel = new PuantajModel();
$endeksOkumaModel = new EndeksOkumaModel();
$nobetModel = new NobetModel();
$hareketModel = new PersonelHareketleriModel();

if (Gate::allows("ana_sayfa")) {

    $bugun = date('Y-m-d');
    $nobetciler = $nobetModel->getNobetlerByTarih($bugun);
    $gec_kalan_sayisi = $hareketModel->getGecKalanlarCount($_SESSION['firma_id'] ?? null);

    // Dashboard Ayarlarını Çerezden Oku
    $extraStats = $personelModel->getAdvancedDashboardStats();
    $dailyWorkStats = $puantajModel->getDailyStats();
    $dailyReadingTotal = $endeksOkumaModel->getDailyStats();
    $monthlyWorkStats = $puantajModel->getMonthlyStats();
    $monthlyReadingTotal = $endeksOkumaModel->getMonthlyStats();
    $extraStatsMonthly = $personelModel->getMonthlyAdvancedDashboardStats();
    $saved_settings = isset($_COOKIE['dashboard_settings']) ? json_decode($_COOKIE['dashboard_settings'], true) : [];

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
    $stmt = $db->prepare("SELECT count(*) as count FROM personel_talepleri WHERE durum != 'cozuldu' AND deleted_at IS NULL");
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
    $stmt = $db->prepare("SELECT 'Talep' as tip, id, personel_id, olusturma_tarihi as tarih, durum, baslik as detay FROM personel_talepleri WHERE durum != 'cozuldu' AND deleted_at IS NULL LIMIT 5");
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

    // Widget İçeriklerini Tanımla
    $widgets = [];

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-toplam-personel">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #4e73df; border-bottom: 3px solid var(--card-color) !important; --delay: 0.1s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(78, 115, 223, 0.1);">
                        <i class="bx bx-group fs-4" style="color: #4e73df;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">PERSONEL</span>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM PERSONEL</p>
                <h4 class="mb-0 fw-bold bordro-text-heading">
                    <?php echo $istatistik->toplam_personel ?? 0; ?>
                    <span class="trend-badge up ms-1">+5.4%</span>
                </h4>
                <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Tüm zamanların toplamı</div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-toplam-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-aktif-personel">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #1cc88a; border-bottom: 3px solid var(--card-color) !important; --delay: 0.2s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(28, 200, 138, 0.1);">
                        <i class="bx bx-user-check fs-4" style="color: #1cc88a;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">AKTİF</span>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">AKTİF PERSONEL</p>
                <h4 class="mb-0 fw-bold bordro-text-heading">
                    <?php echo $istatistik->aktif_personel ?? 0; ?>
                    <span class="trend-badge up ms-1">+2.5%</span>
                </h4>
                <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Sistemde aktif çalışıyor</div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-aktif-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-pasif-personel">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #858796; border-bottom: 3px solid var(--card-color) !important; --delay: 0.3s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(133, 135, 150, 0.1);">
                        <i class="bx bx-user-x fs-4" style="color: #858796;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">PASİF</span>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">PASİF PERSONEL</p>
                <h4 class="mb-0 fw-bold bordro-text-heading">
                    <?php echo $istatistik->pasif_personel ?? 0; ?>
                    <span class="trend-badge down ms-1">-1.2%</span>
                </h4>
                <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">İşten ayrılan/pasif</div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-pasif-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-sahadaki-personel">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #4e73df; border-bottom: 3px solid var(--card-color) !important; --delay: 0.4s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(78, 115, 223, 0.1);">
                        <i class="bx bx-user-voice fs-4" style="color: #4e73df;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SAHA</span>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK SAHA
                    PERSONELİ</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                    data-daily="<?php echo $extraStats->sahadaki_personel ?? 0; ?>"
                    data-monthly="<?php echo $extraStatsMonthly->sahadaki_personel ?? 0; ?>"
                    data-label-daily="GÜNLÜK SAHA PERSONELİ" data-label-monthly="AYLIK SAHA PERSONELİ"
                    data-sub-daily="Bugün sahada olan/aktif" data-sub-monthly="Bu ay sahada görev alanlar">
                    <?php echo $extraStats->sahadaki_personel ?? 0; ?>
                </h4>
                <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün sahada olan/aktif
                </div>
                <div class="card-footer-actions mt-2">
                    <div class="btn-group btn-group-sm stats-local-toggle-group">
                        <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                            data-mode="daily">Gün</button>
                        <button type="button" class="btn btn-outline-secondary stats-local-btn"
                            data-mode="monthly">Ay</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-sahadaki-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-izinli-personel">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #f6c23e; border-bottom: 3px solid var(--card-color) !important; --delay: 0.5s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(246, 194, 62, 0.1);">
                        <i class="bx bx-calendar-minus fs-4" style="color: #f6c23e;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İZİN</span>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK
                    İZİNLİ PERSONEL</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                    data-daily="<?php echo $extraStats->izinli_personel ?? 0; ?>"
                    data-monthly="<?php echo $extraStatsMonthly->izinli_personel ?? 0; ?>"
                    data-label-daily="GÜNLÜK İZİNLİ PERSONEL" data-label-monthly="AYLIK İZİNLİ PERSONEL"
                    data-sub-daily="Bugün izinli olanlar" data-sub-monthly="Bu ay izin kullananlar">
                    <?php echo $extraStats->izinli_personel ?? 0; ?>
                </h4>
                <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Bugün izinli olanlar</div>
                <div class="card-footer-actions mt-2">
                    <div class="btn-group btn-group-sm stats-local-toggle-group">
                        <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                            data-mode="daily">Gün</button>
                        <button type="button" class="btn btn-outline-secondary stats-local-btn"
                            data-mode="monthly">Ay</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-izinli-personel'] = ob_get_clean();

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
            </div>
        </div>
    </div>
    <?php $widgets['widget-bekleyen-talepler'] = ob_get_clean();

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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                            <i class="bx bx-calendar-star fs-4" style="color: #556ee6;"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NÖBET</span>
                    </div>
                    <a href="index.php?p=nobet/list" class="btn btn-sm btn-soft-primary rounded-pill">
                        <i class="bx bx-calendar"></i> Takvim
                    </a>
                </div>
                <p class="text-muted mb-2 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BUGÜNKÜ NÖBETÇİLER</p>

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

                <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">
                    <?php echo count($nobetciler); ?> personel nöbetçi
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-nobetciler'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded shadow-sm">
            <h5 class="mb-0 text-primary fw-bold"><i class="bx bx-stats me-2"></i> Operasyonel İstatistikler</h5>
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
                            data-bs-toggle="tooltip" title="Online sorgula(API)">
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
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-muhurleme'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-sahadaki-arac">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #5a5c69; border-bottom: 3px solid var(--card-color) !important; --delay: 0.8s">
            <div class="card-body p-3 pb-2">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(90, 92, 105, 0.1);">
                        <i class="bx bx-car fs-4" style="color: #5a5c69;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">ARAÇ</span>
                </div>
                <p class="text-muted mb-1 small fw-bold stat-label" style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK SAHA
                    ARACI</p>
                <h4 class="mb-0 fw-bold bordro-text-heading stat-value"
                    data-daily="<?php echo $extraStats->sahadaki_arac ?? 0; ?>"
                    data-monthly="<?php echo $extraStats->sahadaki_arac ?? 0; ?>" data-label-daily="GÜNLÜK SAHA ARACI"
                    data-label-monthly="AYLIK SAHA ARACI" data-sub-daily="Aktif kullanılan araçlar"
                    data-sub-monthly="Aktif kullanılan araçlar">
                    <?php echo $extraStats->sahadaki_arac ?? 0; ?>
                </h4>
                <div class="sub-text mt-2 stat-subtext" style="font-size: 10px; color: #858796;">Aktif kullanılan araçlar
                </div>
                <div class="card-footer-actions mt-2">
                    <div class="btn-group btn-group-sm stats-local-toggle-group">
                        <button type="button" class="btn btn-outline-secondary stats-local-btn active"
                            data-mode="daily">Gün</button>
                        <button type="button" class="btn btn-outline-secondary stats-local-btn"
                            data-mode="monthly">Ay</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-sahadaki-arac'] = ob_get_clean();

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
                            data-bs-toggle="tooltip" title="Online sorgula(API)">
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
                            data-bs-toggle="tooltip" title="Online sorgula(API)">
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
            </div>
        </div>
    </div>
    <?php $widgets['widget-gunluk-sayac-degisimi'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-2 widget-item" id="widget-servisteki-arac">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card"
            style="--card-color: #b7b9cc; border-bottom: 3px solid var(--card-color) !important; --delay: 1.2s">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(183, 185, 204, 0.1);">
                        <i class="bx bx-wrench fs-4" style="color: #b7b9cc;"></i>
                    </div>
                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">ARAÇ</span>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">SERVİSTEKİ ARAÇ</p>
                <h4 class="mb-0 fw-bold bordro-text-heading">
                    <?php echo $extraStats->servisteki_arac ?? 0; ?>
                </h4>
                <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Serviste/Pasif araçlar</div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-servisteki-arac'] = ob_get_clean();

    ob_start(); ?>
    <div class="<?php echo getWidgetWidth('widget-bildirimler', 'col-12'); ?> widget-item" id="widget-bildirimler">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> Görev ve Bildirimler</h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?p=gorev-bildirimler" class="btn btn-sm btn-soft-primary rounded-pill">
                        <i class="bx bx-list-ul me-1"></i> Tümünü Gör
                    </a>
                    <?php echo getWidthControl(); ?>
                </div>
            </div>
            <div class="card-body"
                style="height: <?php echo getWidgetHeight('widget-bildirimler', 'auto'); ?>; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-centered table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Bildirim Tipi</th>
                                <th>Başlık</th>
                                <th>İçerik</th>
                                <th>Tarih</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Kayıt bulunmamaktadır.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td>
                                            <i class="bx bx-info-circle me-1"></i>
                                            <?php echo htmlspecialchars($log->action_type); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log->action_type); ?></td>
                                        <td>
                                            <?php
                                            $user_name = $log->adi_soyadi ?? 'Sistem';
                                            $full_desc = htmlspecialchars($log->description);
                                            $short_desc = mb_strimwidth($full_desc, 0, 80, "...");
                                            echo $short_desc . " <small class='text-muted'>($user_name tarafından)</small>";
                                            ?>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-soft-primary btn-log-detay"
                                                data-title="<?php echo htmlspecialchars($log->action_type); ?>"
                                                data-user="<?php echo htmlspecialchars($user_name); ?>"
                                                data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>"
                                                data-content="<?php echo htmlspecialchars($log->description); ?>">
                                                <i class="bx bx-show me-1"></i> Detay
                                            </button>
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
    <?php $widgets['widget-bildirimler'] = ob_get_clean();

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
                <div id="work-type-stats-chart" style="min-height: 400px; height: 100%;"></div>
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
                <div id="work-result-stats-chart" style="min-height: 400px; height: 100%;"></div>
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
        ?>

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <?php
                $maintitle = 'Ana Sayfa';
                $title = '';
                ?>
            </div>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false" style="font-weight: 500;">
                        <i class="bx bx-show me-1"></i> Kart Görünürlüğü
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="min-width: 280px; z-index: 1060;">
                        <li>
                            <h6 class="dropdown-header fw-bold">Gösterilecek Kartları Seçin</h6>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                <input type="checkbox" class="form-check-input widget-toggle me-2"
                                    data-widget="widget-aktif-personel" checked>
                                Aktif Personel
                            </label>
                        </li>
                        <li>
                            <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                <input type="checkbox" class="form-check-input widget-toggle me-2"
                                    data-widget="widget-pasif-personel" checked>
                                Pasif Personel
                            </label>
                        </li>
                        <li>
                            <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                <input type="checkbox" class="form-check-input widget-toggle me-2"
                                    data-widget="widget-toplam-personel" checked>
                                Toplam Personel
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
                                    data-widget="widget-sahadaki-personel" checked>
                                Sahadaki Personel
                            </label>
                        </li>
                        <li>
                            <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                <input type="checkbox" class="form-check-input widget-toggle me-2"
                                    data-widget="widget-izinli-personel" checked>
                                İzinli Personel
                            </label>
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
                                    data-widget="widget-sahadaki-arac" checked>
                                Sahadaki Araç
                            </label>
                        </li>
                        <li>
                            <label class="dropdown-item cursor-pointer mb-0" style="cursor: pointer;">
                                <input type="checkbox" class="form-check-input widget-toggle me-2"
                                    data-widget="widget-servisteki-arac" checked>
                                Servisteki Araç
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

    <div class="modal fade" id="modalLogDetay" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bx bx-info-circle me-2"></i>Bildirim Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small mb-1">Bildirim Tipi</label>
                            <h6 id="logDetayTitle" class="fw-bold">-</h6>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <label class="text-muted small mb-1">Tarih</label>
                            <h6 id="logDetayDate" class="fw-bold">-</h6>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small mb-1">İşlemi Yapan</label>
                        <h6 id="logDetayUser" class="fw-bold">-</h6>
                    </div>
                    <hr>
                    <div class="mb-0">
                        <label class="text-muted small mb-1">İçerik Detayı</label>
                        <div id="logDetayContent" class="p-3 bg-light rounded border"
                            style="white-space: pre-wrap; line-height: 1.6;">
                            -
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
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

            .bordro-summary-card .card-body {
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
    <script>
        // Number Cou            nt                                 er F           unction
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
        function loadWorkTypeStats(year) {
            if (typeof ApexCharts === 'undefined') {
                console.log('ApexCharts henüz yüklenmedi, 500ms sonra tekrar denenecek...');
                setTimeout(() => loadWorkTypeStats(year), 500);
                return;
            }

            const chartElement = document.querySelector("#work-type-stats-chart");
            if (!chartElement) return;

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

                        chartElement.innerHTML = ''; // Temizle
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
            const API_URL = 'views/talepler/api.php';

            // Load widget visibility from localStorage
            function loadWidgetVisibility() {
                const visibility = localStorage.getItem('dashboard_widget_visibility');
                if (visibility) {
                    const visibleWidgets = JSON.parse(visibility);
                    $('#dashboard-widgets .widget-item').each(function () {
                        const id = $(this).attr('id');
                        const isVisible = visibleWidgets[id] !== false;
                        $(this).toggle(isVisible);
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

                    if (content.includes('{') && content.includes('}')) {
                        try {
                            let parts = content.split(' (Güncellenen veriler: { ');
                            let mainText = parts[0];
                            let changesPart = parts[1].replace(' })', '');
                            let changes = changesPart.split(', ');
                            let formattedContent = `<div class="mb-2 fw-bold text-primary">${mainText}</div>`;
                            formattedContent += `<table class="table table-sm table-bordered mt-2 mb-0">
                            <thead class="table-light">
                                <tr><th>Alan</th><th>Değişim</th></tr>
                            </thead>
                            <tbody>`;
                            changes.forEach(change => {
                                if (change.includes(': ')) {
                                    let [key, val] = change.split(': ');
                                    formattedContent += `<tr><td class="fw-bold" style="width: 30%;">${key}</td><td>${val}</td></tr>`;
                                } else {
                                    formattedContent += `<tr><td colspan="2" class="text-center text-muted italic">${change}</td></tr>`;
                                }
                            });
                            formattedContent += `</tbody></table>`;
                            document.getElementById('logDetayContent').innerHTML = formattedContent;
                        } catch (e) {
                            document.getElementById('logDetayContent').textContent = content;
                        }
                    } else {
                        document.getElementById('logDetayContent').textContent = content;
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
                handle: ".card-header, .card-header-flex, .bordro-summary-card",
                cancel: ".btn-api-sync, .stats-local-btn, .btn, .bx-no-drag",
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
                const firmaId = '<?php echo $_SESSION['firma_id'] ?? 17; ?>';

                if ($btn.hasClass('syncing')) return;

                $btn.addClass('syncing');
                $icon.addClass('bx-spin text-primary');

                $.ajax({
                    url: 'views/puantaj/api.php',
                    type: 'POST',
                    data: {
                        action: action,
                        baslangic_tarihi: today,
                        bitis_tarihi: today,
                        ilk_firma: firmaId,
                        son_firma: firmaId
                    },
                    success: function (response) {
                        $btn.removeClass('syncing');
                        $icon.removeClass('bx-spin text-primary');

                        try {
                            const res = typeof response === 'object' ? response : JSON.parse(response);
                            if (res.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sorgulama Başarılı',
                                    text: (res.yeni_kayit || 0) + ' adet yeni kayıt eklendi.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Hata', res.message || 'Sorgulama sırasında bir hata oluştu.', 'error');
                            }
                        } catch (err) {
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
        });
    </script>
    <?php
} else {
    //Alert::danger("Bu sayfaya erişim yetkiniz yok!");
    /**Personelin yetkili olduğu ilk sayfaya yönlendir */
    $permissionModel = new PermissionsModel();
    $permissionModel->redirectFirstPersmissionPage();
}