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

$personelModel = new PersonelModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$talepModel = new TalepModel();
$systemLogModel = new SystemLogModel();

if (Gate::allows("ana_sayfa")) {

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
    <div class="col-md-3 widget-item" id="widget-aktif-personel">
        <div class="summary-card animate-card" style="--delay: 0.1s">
            <div class="card-header-flex">
                <h5 class="card-title"><i class='bx bx-grid-vertical drag-handle me-1'></i> Aktif Personel</h5>
                <div class="trend-badge up">
                    <i class='bx bx-trending-up'></i> +2.5%
                </div>
            </div>
            <div class="main-value"><?php echo $istatistik->aktif_personel ?? 0; ?></div>
            <div class="trend-description">
                Sistemde aktif çalışıyor <i class='bx bx-trending-up'></i>
            </div>
            <div class="sub-text">Son 30 gün verilerine göre</div>
        </div>
    </div>
    <?php $widgets['widget-aktif-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-3 widget-item" id="widget-pasif-personel">
        <div class="summary-card animate-card" style="--delay: 0.2s">
            <div class="card-header-flex">
                <h5 class="card-title"><i class='bx bx-grid-vertical drag-handle me-1'></i> Pasif Personel</h5>
                <div class="trend-badge down">
                    <i class='bx bx-trending-down'></i> -1.2%
                </div>
            </div>
            <div class="main-value"><?php echo $istatistik->pasif_personel ?? 0; ?></div>
            <div class="trend-description">
                İşten ayrılan/pasif <i class='bx bx-trending-down'></i>
            </div>
            <div class="sub-text">Toplam pasif kayıt sayısı</div>
        </div>
    </div>
    <?php $widgets['widget-pasif-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-3 widget-item" id="widget-toplam-personel">
        <div class="summary-card animate-card" style="--delay: 0.3s">
            <div class="card-header-flex">
                <h5 class="card-title"><i class='bx bx-grid-vertical drag-handle me-1'></i> Toplam Personel</h5>
                <div class="trend-badge up">
                    <i class='bx bx-trending-up'></i> +5.4%
                </div>
            </div>
            <div class="main-value"><?php echo $istatistik->toplam_personel ?? 0; ?></div>
            <div class="trend-description">
                Genel personel havuzu <i class='bx bx-trending-up'></i>
            </div>
            <div class="sub-text">Tüm zamanların toplamı</div>
        </div>
    </div>
    <?php $widgets['widget-toplam-personel'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-md-3 widget-item" id="widget-bekleyen-talepler">
        <div class="summary-card animate-card" style="--delay: 0.4s">
            <div class="card-header-flex">
                <h5 class="card-title"><i class='bx bx-grid-vertical drag-handle me-1'></i> Bekleyen Talepler</h5>
                <div class="trend-badge <?php echo $personel_talep_sayisi > 10 ? 'down' : 'up'; ?>">
                    <i class='bx <?php echo $personel_talep_sayisi > 10 ? 'bx-trending-up' : 'bx-trending-down'; ?>'></i>
                    <?php echo $personel_talep_sayisi > 0 ? 'Dikkat' : 'Stabil'; ?>
                </div>
            </div>
            <div class="main-value"><?php echo $personel_talep_sayisi ?? 0; ?></div>
            <div class="trend-description">
                Onay bekleyen işlemler <i class='bx bx-time-five'></i>
            </div>
            <div class="sub-text">İzin, avans ve diğer talepler</div>
        </div>
    </div>
    <?php $widgets['widget-bekleyen-talepler'] = ob_get_clean();

    ob_start(); ?>
    <div class="col-12 widget-item" id="widget-bildirimler">
        <div class="card">
            <div class="card-header">
                <h5><i class='bx bx-grid-vertical drag-handle me-1'></i> Görev ve Bildirimler</h5>
            </div>
            <div class="card-body">
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
    <div class="col-md-6 widget-item" id="widget-talepler">
        <div class="card">
            <div class="card-header">
                <h5><i class='bx bx-grid-vertical drag-handle me-1'></i> Arıza/İzin/Avans Talepleri</h5>
            </div>
            <div class="card-body">
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
    <div class="col-md-6 widget-item" id="widget-izindekiler">
        <div class="card">
            <div class="card-header">
                <h5><i class='bx bx-grid-vertical drag-handle me-1'></i> Şu Anda İzinde Olan Personeller</h5>
            </div>
            <div class="card-body">
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
    <div class="col-md-12 widget-item" id="widget-is-turu-istatistikleri">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> İş Türü İstatistikleri</h5>
                <div class="flex-shrink-0" style="width: 100px;">
                    <select class="form-select form-select-sm" id="stats-year-filter">
                        <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
                            echo "<option value='$y'>$y</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div id="work-type-stats-chart" style="min-height: 350px;"></div>
            </div>
        </div>
    </div>
    <?php $widgets['widget-is-turu-istatistikleri'] = ob_get_clean();

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

        <!-- start page title -->
        <?php
        $maintitle = 'Ana Sayfa';
        $title = '';
        ?>
        <!-- end page title -->

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

        /* Summary Cards Shadcn Style */
        .summary-card {
            background: linear-gradient(to top, rgba(var(--bs-primary-rgb), 0.04) 0%, rgba(var(--bs-primary-rgb), 0.1) 100%);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(var(--bs-primary-rgb), 0.09);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: "";
            position: absolute;
            top: -20%;
            right: -10%;
            width: 140px;
            height: 140px;
            background: rgba(var(--bs-primary-rgb), 0.03);
            border-radius: 50%;
            z-index: 0;
        }

        .animate-card {
            opacity: 0;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            animation-delay: var(--delay);
        }

        @keyframes slideUpFade {
            0% {
                opacity: 0;
                transform: translateY(12px) scale(0.99);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-color: rgba(var(--bs-primary-rgb), 0.3);
        }

        .summary-card .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .summary-card .card-title {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            z-index: 1;
        }

        .summary-card .trend-badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(var(--bs-primary-rgb), 0.1);
            border: 1px solid rgba(var(--bs-primary-rgb), 0.05);
            z-index: 1;
        }

        .summary-card .trend-badge.up {
            color: #10b981;
        }

        .summary-card .trend-badge.down {
            color: #ef4444;
        }

        .summary-card .main-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f172a;
            margin: 10px 0 5px 0;
            letter-spacing: -0.02em;
            z-index: 1;
        }

        .summary-card .trend-description {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 1;
        }

        .summary-card .sub-text {
            font-size: 0.75rem;
            color: #94a3b8;
            z-index: 1;
        }

        /* Sortable Styles */
        .widget-item {
            margin-bottom: 24px;
        }

        .card-header,
        .card-header-flex {
            cursor: grab;
        }

        .card-header:active,
        .card-header-flex:active {
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

        /* Dark Mode Adjustments */
        [data-bs-theme="dark"] .summary-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-color: #334155;
        }

        [data-bs-theme="dark"] .summary-card .card-title {
            color: #f8fafc;
        }

        [data-bs-theme="dark"] .summary-card .main-value {
            color: #f8fafc;
        }

        [data-bs-theme="dark"] .summary-card .trend-badge {
            background: #1e293b;
            border-color: #334155;
        }

        [data-bs-theme="dark"] .summary-card .trend-description {
            color: #94a3b8;
        }

        [data-bs-theme="dark"] .summary-card .sub-text {
            color: #64748b;
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
    </style>

    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
    <script>
        // Number Count            er F            unction
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
                                height: 350,
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


        document.addEventListener('DOMContentLoaded', function () {
            const API_URL = 'views/talepler/api.php';

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

            // İş Türü İstatistikleri
            const yearFilter = document.getElementById('stats-year-filter');
            if (yearFilter) {
                yearFilter.addEventListener('change', function () {
                    loadWorkTypeStats(this.value);
                });
                loadWorkTypeStats(new Date().getFullYear());
            }

            // Dashboard Sortable Logic
            const dashboard = $("#dashboard-widgets");
            dashboard.sortable({
                handle: ".card-header, .card-header-flex",
                placeholder: "ui-sortable-placeholder",
                start: function (e, ui) {
                    const classes = ui.item.attr('class');
                    ui.placeholder.attr('class', 'ui-sortable-placeholder ' + classes);
                },
                update: function (event, ui) {
                    const order = dashboard.sortable("toArray");
                    // Save to Cookie (for PHP to read on next load)
                    document.cookie = "dashboard_order=" + JSON.stringify(order) + "; path=/; max-age=" + (60 * 60 * 24 * 30);
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