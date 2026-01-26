<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\PersonelModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Model\TalepModel;
use App\Model\SystemLogModel;


$personelModel = new PersonelModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$talepModel = new TalepModel();
$systemLogModel = new SystemLogModel();

// Sistem Logları
$recent_logs = $systemLogModel->getRecentLogs(10);

// Personel Sayıları
$personel_sayisi = count($personelModel->where('aktif_mi', 1));
$pasif_personel_sayisi = count($personelModel->where('aktif_mi', 0));
$toplam_personel_sayisi = count($personelModel->all());

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

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = 'Ana Sayfa';
    $title = '';
    ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-md-3">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-success mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bx-user'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Aktif Personel Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $personel_sayisi ?? 0; ?></h3>
                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-danger mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bx-user'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Pasif Personel Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $pasif_personel_sayisi ?? 0; ?></h3>
                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-primary mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bxs-user-account'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Toplam Personel Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $toplam_personel_sayisi ?? 0; ?></h3>
                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-warning mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bx-user'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Bekleyen Personel Talep Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $personel_talep_sayisi ?? 0; ?></h3>
                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

 

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Arıza/İzin/Avans Talepleri</h5>
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
                                                        data-tarih="<?php echo date('d.m.Y', strtotime($req->tarih)); ?>"
                                                        title="Detay">
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
                                                            data-baslik="<?php echo htmlspecialchars($req->detay); ?>"
                                                            title="Çözüldü">
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

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Şu Anda İzinde Olan Personeller</h5>
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
    </div>
       <!-- BİLDİRİMLER -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Görev ve Bildirimler</h5>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_logs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Kayıt bulunmamaktadır.</td>
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
                                                echo htmlspecialchars($log->description) . " <small class='text-muted'>($user_name tarafından)</small>";
                                                ?>
                                            </td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>İş İstatistikleri</h5>
            <span>Aylar bazında iş istatistikleri</span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div id="chart"></div>
                </div>
                <div class="col-md-4">
                    <div id="chart2"></div>
                </div>
                <div class="col-md-4">
                    <div id="chart3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Talep Detay Modal -->
<div class="modal fade" id="modalHomeDetay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-detay-header" id="modalHeader">
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal">
                    <i class="bx bx-x"></i>
                </button>
                <div class="icon-wrapper">
                    <i class="bx bx-file" id="modalHeaderIcon"></i>
                </div>
                <span class="badge-tip" id="modalTalepTipi">Avans</span>
                <h5>Talep Detayı</h5>
            </div>
            <div class="modal-body">
                <div class="modal-detay-card" id="cardPersonel">
                    <div class="label"><i class="bx bx-user"></i> Personel</div>
                    <div class="value" id="modalPersonel">-</div>
                </div>
                <div class="modal-detay-card" id="cardDetay">
                    <div class="label"><i class="bx bx-info-circle"></i> Detay Bilgisi</div>
                    <div class="value" id="modalDetay">-</div>
                </div>
                <div class="modal-detay-card">
                    <div class="label"><i class="bx bx-calendar"></i> Talep Tarihi</div>
                    <div class="value" id="modalTarih">-</div>
                </div>
                <div class="modal-detay-card">
                    <div class="label"><i class="bx bx-loader-circle"></i> Durum</div>
                    <div class="value">
                        <span class="badge bg-warning text-dark px-3 py-2">
                            <i class="bx bx-time me-1"></i>Beklemede
                        </span>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i>Kapat
                </button>
                <a href="#" id="modalGitBtn" class="btn btn-primary px-4">
                    <i class="bx bx-right-arrow-alt me-1"></i>Talep Sayfasına Git
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Avans Onay Modal -->
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

<!-- Avans Red Modal -->
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

<!-- İzin Onay Modal -->
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

<!-- İzin Red Modal -->
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

<!-- Talep Çözüldü Modal -->
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
        color: #6c757d;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.35rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
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
</style>

<script src="assets/libs/apexcharts/apexcharts.min.js"></script>
<script>
    var months = <?php echo json_encode($months); ?>;
    var totals = <?php echo json_encode($totals); ?>;

    var options = {
        chart: { type: 'line', height: 350 },
        series: [{ name: 'Üye Sayısı', data: totals }],
        xaxis: { categories: months },
        colors: ['#556ee6']
    }
    new ApexCharts(document.querySelector("#chart"), options).render();

    var options2 = {
        series: [{ name: 'Gelir', data: [44, 55, 57, 56, 61, 58, 63, 60, 66, 85, 96, 85] },
        { name: 'Gider', data: [76, 85, 101, 98, 87, 105, 91, 114, 94, 78, 77, 25] }],
        chart: { type: 'bar', height: 350 },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
        xaxis: { categories: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'] },
        colors: ['#34c38f', '#f46a6a']
    };
    new ApexCharts(document.querySelector("#chart2"), options2).render();

    var options3 = {
        series: [<?php echo $toplam_gelir; ?>, <?php echo $toplam_gider; ?>, <?php echo $toplam_bakiye; ?>],
        chart: { type: 'polarArea', height: 350 },
        labels: ['Gelir', 'Gider', 'Kasa'],
        colors: ['#34c38f', '#f46a6a', '#556ee6']
    };
    new ApexCharts(document.querySelector("#chart3"), options3).render();

    document.addEventListener('DOMContentLoaded', function () {
        const API_URL = 'views/talepler/api.php';

        // Detay Modal
        document.querySelectorAll('.btn-home-detay').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tip = this.dataset.tip;
                var personel = this.dataset.personel;
                var detay = this.dataset.detay;
                var tarih = this.dataset.tarih;

                var headerClass = tip === 'Avans' ? 'tip-avans' : (tip === 'İzin' ? 'tip-izin' : 'tip-talep');
                var headerIcon = tip === 'Avans' ? 'bx-money' : (tip === 'İzin' ? 'bx-calendar-check' : 'bx-message-square-detail');

                document.getElementById('modalHeader').className = 'modal-detay-header ' + headerClass;
                document.getElementById('modalTalepTipi').textContent = tip;
                document.getElementById('modalHeaderIcon').className = 'bx ' + headerIcon;
                document.getElementById('modalPersonel').textContent = personel;
                document.getElementById('modalDetay').textContent = detay;
                document.getElementById('modalTarih').textContent = tarih;

                var tabParam = tip === 'Avans' ? 'avans' : (tip === 'İzin' ? 'izin' : 'talep');
                document.getElementById('modalGitBtn').href = 'index.php?p=talepler/list&tab=' + tabParam;

                new bootstrap.Modal(document.getElementById('modalHomeDetay')).show();
            });
        });

        // Avans Onayla
        document.querySelectorAll('.btn-avans-onayla').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('avans_onay_id').value = this.dataset.id;
                document.getElementById('avans_onay_personel').textContent = this.dataset.personel;
                document.getElementById('avans_onay_tutar').textContent = parseFloat(this.dataset.tutar).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
                new bootstrap.Modal(document.getElementById('modalAvansOnay')).show();
            });
        });

        // Avans Reddet
        document.querySelectorAll('.btn-avans-reddet').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('avans_red_id').value = this.dataset.id;
                document.getElementById('avans_red_personel').textContent = this.dataset.personel;
                new bootstrap.Modal(document.getElementById('modalAvansRed')).show();
            });
        });

        // İzin Onayla
        document.querySelectorAll('.btn-izin-onayla').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('izin_onay_id').value = this.dataset.id;
                document.getElementById('izin_onay_personel').textContent = this.dataset.personel;
                document.getElementById('izin_onay_tur').textContent = this.dataset.tur;
                document.getElementById('izin_onay_gun').textContent = this.dataset.gun;
                new bootstrap.Modal(document.getElementById('modalIzinOnay')).show();
            });
        });

        // İzin Reddet
        document.querySelectorAll('.btn-izin-reddet').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('izin_red_id').value = this.dataset.id;
                document.getElementById('izin_red_personel').textContent = this.dataset.personel;
                new bootstrap.Modal(document.getElementById('modalIzinRed')).show();
            });
        });

        // Talep Çözüldü
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
    });
</script>