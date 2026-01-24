<?php

use App\Model\TalepModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Form;

$talepModel = new TalepModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();

// URL parametresi ile görünüm tipi
$showApproved = isset($_GET['show']) && $_GET['show'] === 'approved';

// Bekleyen talep sayıları
$avansCount = $avansModel->getBekleyenAvansSayisi();

try {
    $izinCount = $izinModel->getBekleyenIzinSayisi();
} catch (\Exception $e) {
    $izinCount = 0;
}

$talepCount = $talepModel->getBekleyenTalepSayisi();
$toplamCount = $avansCount + $izinCount + $talepCount;

// Talepleri getir - bekleyen veya onaylanmış
if ($showApproved) {
    $avanslar = $avansModel->getOnaylanmisAvanslar(50);
    try {
        $izinler = $izinModel->getOnaylanmisIzinler(50);
    } catch (\Exception $e) {
        $izinler = [];
    }
    $talepler = $talepModel->getCozulmusTalepler(50);
} else {
    $avanslar = $avansModel->getButunBekleyenAvanslar();
    try {
        $izinler = $izinModel->getButunBekleyenIzinler();
    } catch (\Exception $e) {
        $izinler = [];
    }
    $talepler = $talepModel->getButunBekleyenTalepler();
}

// İzin türleri etiketleri
$izinTurleri = [
    'yillik' => 'Yıllık İzin',
    'hastalik' => 'Hastalık',
    'mazeret' => 'Mazeret',
    'dogum' => 'Doğum',
    'evlilik' => 'Evlilik',
    'olum' => 'Vefat',
    'diger' => 'Diğer'
];
?>

<div class="container-fluid">
    <?php
    $maintitle = "Talepler";
    $title = "Talep Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Özet Kartları -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card widget-flat border-start border-success border-3">
                <div class="card-body">
                    <div class="float-end">
                        <span class="badge badge-success font-size-24 opacity-75">
                            <i class='bx bx-money'></i>
                        </span>
                    </div>
                    <h6 class="text-muted mt-0">Avans Talepleri</h6>
                    <h3 class="mt-2 mb-0">
                        <?= $avansCount ?>
                    </h3>
                    <p class="text-muted mb-0 small">Bekleyen</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card widget-flat border-start border-primary border-3">
                <div class="card-body">
                    <div class="float-end">
                        <span class="badge badge-primary font-size-24 opacity-75">
                            <i class='bx bx-calendar-check'></i>
                        </span>
                    </div>
                    <h6 class="text-muted mt-0">İzin Talepleri</h6>
                    <h3 class="mt-2 mb-0">
                        <?= $izinCount ?>
                    </h3>
                    <p class="text-muted mb-0 small">Bekleyen</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card widget-flat border-start border-info border-3">
                <div class="card-body">
                    <div class="float-end">
                        <span class="badge badge-info font-size-24 opacity-75">
                            <i class='bx bx-message-square-detail'></i>
                        </span>
                    </div>
                    <h6 class="text-muted mt-0">Genel Talepler</h6>
                    <h3 class="mt-2 mb-0">
                        <?= $talepCount ?>
                    </h3>
                    <p class="text-muted mb-0 small">Bekleyen</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card widget-flat border-start border-warning border-3">
                <div class="card-body">
                    <div class="float-end">
                        <span class="badge badge-warning font-size-24 opacity-75">
                            <i class='bx bx-bell'></i>
                        </span>
                    </div>
                    <h6 class="text-muted mt-0">Toplam Bekleyen</h6>
                    <h3 class="mt-2 mb-0">
                        <?= $toplamCount ?>
                    </h3>
                    <p class="text-muted mb-0 small">Tüm talepler</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tabAvans" role="tab">
                        <i class="bx bx-money me-1"></i> Avans
                        <?php if (!$showApproved && $avansCount > 0): ?>
                            <span class="badge bg-success ms-1">
                                <?= $avansCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabIzin" role="tab">
                        <i class="bx bx-calendar-check me-1"></i> İzin
                        <?php if (!$showApproved && $izinCount > 0): ?>
                            <span class="badge bg-primary ms-1">
                                <?= $izinCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabTalepler" role="tab">
                        <i class="bx bx-message-square-detail me-1"></i> Talepler
                        <?php if (!$showApproved && $talepCount > 0): ?>
                            <span class="badge bg-info ms-1">
                                <?= $talepCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <div class="btn-group">
                <a href="index?p=talepler/list" class="btn btn-<?= $showApproved ? 'outline-' : '' ?>warning btn-sm">
                    <i class="bx bx-time me-1"></i>Bekleyenler
                </a>
                <a href="index?p=talepler/list&show=approved"
                    class="btn btn-<?= $showApproved ? '' : 'outline-' ?>success btn-sm">
                    <i class="bx bx-check-circle me-1"></i>Onaylananlar
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if ($showApproved): ?>
                <div class="alert alert-success mb-3">
                    <i class="bx bx-check-circle me-1"></i>
                    <strong>Onaylanmış Talepler</strong> görüntüleniyor. Son 50 kayıt listelenmektedir.
                </div>
            <?php endif; ?>

            <div class="tab-content">
                <!-- Avans Talepleri Tab -->
                <div class="tab-pane fade show active" id="tabAvans" role="tabpanel">
                    <?php if (empty($avanslar)): ?>
                        <div class="text-center py-5">
                            <i
                                class="bx bx-<?= $showApproved ? 'folder-open' : 'check-circle' ?> display-1 text-success opacity-50"></i>
                            <h5 class="mt-3 text-muted">
                                <?= $showApproved ? 'Onaylanmış avans talebi bulunmuyor' : 'Bekleyen avans talebi bulunmuyor' ?>
                            </h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered datatable" id="avansTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Personel</th>
                                        <th>Talep Türü</th>
                                        <th>Tutar</th>
                                        <th>Talep Tarihi</th>
                                        <th>Durum</th>
                                        <th>Açıklama</th>
                                        <?php if (!$showApproved): ?>
                                            <th class="text-center" style="width:150px">İşlemler</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($avanslar as $avans): ?>
                                        <tr data-id="<?= $avans->id ?>" data-tip="avans">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($avans->resim_yolu) ? $avans->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                        alt="" class="rounded-circle avatar-sm me-2">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <?= htmlspecialchars($avans->adi_soyadi) ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($avans->departman ?? '') ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><i class="bx bx-money me-1"></i>Avans</span>
                                            </td>
                                            <td>
                                                <span class="text-success">
                                                    <?= number_format($avans->tutar, 2, ',', '.') ?>
                                                    ₺
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y H:i', strtotime($avans->talep_tarihi)) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $durumClass = 'bg-warning';
                                                $durumText = ucfirst($avans->durum);
                                                if ($avans->durum == 'onaylandi') {
                                                    $durumClass = 'bg-success';
                                                    $durumText = 'Onaylandı';
                                                }
                                                if ($avans->durum == 'reddedildi') {
                                                    $durumClass = 'bg-danger';
                                                    $durumText = 'Reddedildi';
                                                }
                                                ?>
                                                <span class="badge <?= $durumClass ?>">
                                                    <?= $durumText ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($avans->aciklama ?? '-') ?>
                                            </td>
                                            <?php if (!$showApproved): ?>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-success btn-sm btn-avans-onayla"
                                                            data-id="<?= $avans->id ?>"
                                                            data-personel="<?= htmlspecialchars($avans->adi_soyadi) ?>"
                                                            data-tutar="<?= $avans->tutar ?>" title="Onayla">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-avans-reddet"
                                                            data-id="<?= $avans->id ?>"
                                                            data-personel="<?= htmlspecialchars($avans->adi_soyadi) ?>"
                                                            title="Reddet">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-sm btn-avans-detay"
                                                            data-id="<?= $avans->id ?>" title="Detay">
                                                            <i class="bx bx-show"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- İzin Talepleri Tab -->
                <div class="tab-pane fade" id="tabIzin" role="tabpanel">
                    <?php if (empty($izinler)): ?>
                        <div class="text-center py-5">
                            <i
                                class="bx bx-<?= $showApproved ? 'folder-open' : 'check-circle' ?> display-1 text-primary opacity-50"></i>
                            <h5 class="mt-3 text-muted">
                                <?= $showApproved ? 'Onaylanmış izin talebi bulunmuyor' : 'Bekleyen izin talebi bulunmuyor' ?>
                            </h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered datatable" id="izinTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Personel</th>
                                        <th>Talep Türü</th>
                                        <th>İzin Türü</th>
                                        <th>Tarih Aralığı</th>
                                        <th>Gün Sayısı</th>
                                        <th>Durum</th>
                                        <?php if (!$showApproved): ?>
                                            <th class="text-center" style="width:150px">İşlemler</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($izinler as $izin):
                                        $gunSayisi = $izinModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);
                                        $izinTuruLabel = $izin->izin_tipi_adi ?? $izin->izin_tipi ?? 'Belirtilmemiş';
                                        ?>
                                        <tr data-id="<?= $izin->id ?>" data-tip="izin">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($izin->resim_yolu) ? $izin->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                        alt="" class="rounded-circle avatar-sm me-2">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <?= htmlspecialchars($izin->adi_soyadi) ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($izin->departman ?? '') ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><i
                                                        class="bx bx-calendar-check me-1"></i>İzin</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $izinTuruLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y', strtotime($izin->baslangic_tarihi)) ?> -
                                                <?= date('d.m.Y', strtotime($izin->bitis_tarihi)) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= $gunSayisi ?> Gün
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $durumClass = 'bg-warning';
                                                $durumText = ucfirst($izin->onay_durumu);
                                                if ($izin->onay_durumu == 'Onaylandı') {
                                                    $durumClass = 'bg-success';
                                                }
                                                if ($izin->onay_durumu == 'Reddedildi') {
                                                    $durumClass = 'bg-danger';
                                                }
                                                ?>
                                                <span class="badge <?= $durumClass ?>">
                                                    <?= $durumText ?>
                                                </span>
                                            </td>
                                            <?php if (!$showApproved): ?>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-success btn-sm btn-izin-onayla"
                                                            data-id="<?= $izin->id ?>"
                                                            data-personel="<?= htmlspecialchars($izin->adi_soyadi) ?>"
                                                            data-tur="<?= $izinTuruLabel ?>" data-gun="<?= $gunSayisi ?>"
                                                            title="Onayla">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-izin-reddet"
                                                            data-id="<?= $izin->id ?>"
                                                            data-personel="<?= htmlspecialchars($izin->adi_soyadi) ?>"
                                                            title="Reddet">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-sm btn-izin-detay"
                                                            data-id="<?= $izin->id ?>" title="Detay">
                                                            <i class="bx bx-show"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Genel Talepler Tab -->
                <div class="tab-pane fade" id="tabTalepler" role="tabpanel">
                    <?php if (empty($talepler)): ?>
                        <div class="text-center py-5">
                            <i
                                class="bx bx-<?= $showApproved ? 'folder-open' : 'check-circle' ?> display-1 text-info opacity-50"></i>
                            <h5 class="mt-3 text-muted">
                                <?= $showApproved ? 'Çözülmüş talep bulunmuyor' : 'Bekleyen genel talep bulunmuyor' ?>
                            </h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered datatable" id="taleplerTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Personel</th>
                                        <th>Talep Türü</th>
                                        <th>Başlık</th>
                                        <th>Durum</th>
                                        <th>Öncelik</th>
                                        <th>Tarih</th>
                                        <?php if (!$showApproved): ?>
                                            <th class="text-center" style="width:150px">İşlemler</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($talepler as $talep):
                                        $durumBadge = 'bg-warning';
                                        if ($talep->durum == 'islemde')
                                            $durumBadge = 'bg-info';
                                        if ($talep->durum == 'cozuldu')
                                            $durumBadge = 'bg-success';

                                        $oncelikBadge = 'bg-secondary';
                                        if (isset($talep->oncelik)) {
                                            if ($talep->oncelik == 'yuksek')
                                                $oncelikBadge = 'bg-danger';
                                            if ($talep->oncelik == 'orta')
                                                $oncelikBadge = 'bg-warning';
                                            if ($talep->oncelik == 'dusuk')
                                                $oncelikBadge = 'bg-success';
                                        }
                                        ?>
                                        <tr data-id="<?= $talep->id ?>" data-tip="talep">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($talep->resim_yolu) ? $talep->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                        alt="" class="rounded-circle avatar-sm me-2">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <?= htmlspecialchars($talep->adi_soyadi) ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($talep->departman ?? '') ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark"><i
                                                        class="bx bx-message-square-detail me-1"></i>Talep</span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($talep->baslik ?? '-') ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $durumBadge ?>">
                                                    <?= ucfirst($talep->durum) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $oncelikBadge ?>">
                                                    <?= ucfirst($talep->oncelik ?? 'Normal') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y H:i', strtotime($talep->olusturma_tarihi)) ?>
                                            </td>
                                            <?php if (!$showApproved): ?>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <?php if ($talep->durum != 'islemde'): ?>
                                                            <button type="button" class="btn btn-warning btn-sm btn-talep-isleme"
                                                                data-id="<?= $talep->id ?>" title="İşleme Al">
                                                                <i class="bx bx-play"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-success btn-sm btn-talep-cozuldu"
                                                            data-id="<?= $talep->id ?>"
                                                            data-personel="<?= htmlspecialchars($talep->adi_soyadi) ?>"
                                                            data-baslik="<?= htmlspecialchars($talep->baslik ?? '') ?>"
                                                            title="Çözüldü">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-sm btn-talep-detay"
                                                            data-id="<?= $talep->id ?>" title="Detay">
                                                            <i class="bx bx-show"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
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

<!-- Detay Modal -->
<div class="modal fade" id="modalDetay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-detail me-2"></i>Talep Detayı</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detayContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 40px;
        height: 40px;
        object-fit: cover;
    }

    .nav-tabs .nav-link {
        color: #6c757d;
        border: none;
        padding: 0.75rem 1.5rem;
    }

    .nav-tabs .nav-link.active {
        color: #556ee6;
        background: transparent;
        border-bottom: 2px solid #556ee6;
    }

    .nav-tabs .nav-link:hover:not(.active) {
        border-color: transparent;
        color: #556ee6;
    }

    .widget-flat {
        transition: transform 0.2s ease;
    }

    .widget-flat:hover {
        transform: translateY(-2px);
    }

    .btn-group .btn {
        padding: 0.25rem 0.5rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const API_URL = 'views/talepler/api.php';

        // Avans Onayla
        document.querySelectorAll('.btn-avans-onayla').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const personel = this.dataset.personel;
                const tutar = parseFloat(this.dataset.tutar).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';

                document.getElementById('avans_onay_id').value = id;
                document.getElementById('avans_onay_personel').textContent = personel;
                document.getElementById('avans_onay_tutar').textContent = tutar;

                new bootstrap.Modal(document.getElementById('modalAvansOnay')).show();
            });
        });

        // Avans Reddet
        document.querySelectorAll('.btn-avans-reddet').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const personel = this.dataset.personel;

                document.getElementById('avans_red_id').value = id;
                document.getElementById('avans_red_personel').textContent = personel;

                new bootstrap.Modal(document.getElementById('modalAvansRed')).show();
            });
        });

        // Avans Detay
        document.querySelectorAll('.btn-avans-detay').forEach(btn => {
            btn.addEventListener('click', function () {
                loadDetay('avans', this.dataset.id);
            });
        });

        // İzin Onayla
        document.querySelectorAll('.btn-izin-onayla').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const personel = this.dataset.personel;
                const tur = this.dataset.tur;
                const gun = this.dataset.gun;

                document.getElementById('izin_onay_id').value = id;
                document.getElementById('izin_onay_personel').textContent = personel;
                document.getElementById('izin_onay_tur').textContent = tur;
                document.getElementById('izin_onay_gun').textContent = gun;

                new bootstrap.Modal(document.getElementById('modalIzinOnay')).show();
            });
        });

        // İzin Reddet
        document.querySelectorAll('.btn-izin-reddet').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const personel = this.dataset.personel;

                document.getElementById('izin_red_id').value = id;
                document.getElementById('izin_red_personel').textContent = personel;

                new bootstrap.Modal(document.getElementById('modalIzinRed')).show();
            });
        });

        // İzin Detay
        document.querySelectorAll('.btn-izin-detay').forEach(btn => {
            btn.addEventListener('click', function () {
                loadDetay('izin', this.dataset.id);
            });
        });

        // Talep İşleme Al
        document.querySelectorAll('.btn-talep-isleme').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'İşleme Al',
                        text: 'Bu talebi işleme almak istediğinize emin misiniz?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Evet, İşleme Al',
                        cancelButtonText: 'İptal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitAction('talep-isleme-al', { id: id });
                        }
                    });
                } else {
                    if (confirm('Bu talebi işleme almak istediğinize emin misiniz?')) {
                        submitAction('talep-isleme-al', { id: id });
                    }
                }
            });
        });

        // Talep Çözüldü
        document.querySelectorAll('.btn-talep-cozuldu').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const baslik = this.dataset.baslik;

                document.getElementById('talep_cozuldu_id').value = id;
                document.getElementById('talep_cozuldu_baslik').textContent = baslik;

                new bootstrap.Modal(document.getElementById('modalTalepCozuldu')).show();
            });
        });

        // Talep Detay
        document.querySelectorAll('.btn-talep-detay').forEach(btn => {
            btn.addEventListener('click', function () {
                loadDetay('talep', this.dataset.id);
            });
        });

        // Form Submits
        document.getElementById('formAvansOnay')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'avans-onayla');
            submitFormAction(formData, 'modalAvansOnay');
        });

        document.getElementById('formAvansRed')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'avans-reddet');
            submitFormAction(formData, 'modalAvansRed');
        });

        document.getElementById('formIzinOnay')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'izin-onayla');
            submitFormAction(formData, 'modalIzinOnay');
        });

        document.getElementById('formIzinRed')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'izin-reddet');
            submitFormAction(formData, 'modalIzinRed');
        });

        document.getElementById('formTalepCozuldu')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'talep-cozuldu');
            submitFormAction(formData, 'modalTalepCozuldu');
        });

        // Helper Functions
        function submitAction(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata',
                                text: data.message
                            });
                        } else {
                            alert('Hata: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error.message);
                });
        }

        function submitFormAction(formData, modalId) {
            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata',
                                text: data.message
                            });
                        } else {
                            alert('Hata: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error.message);
                });
        }

        function loadDetay(tip, id) {
            const detayContent = document.getElementById('detayContent');
            detayContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>';

            new bootstrap.Modal(document.getElementById('modalDetay')).show();

            const formData = new FormData();
            formData.append('action', 'get-' + tip + '-detay');
            formData.append('id', id);

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        detayContent.innerHTML = renderDetay(tip, data.data);
                    } else {
                        detayContent.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    detayContent.innerHTML = '<div class="alert alert-danger">Bir hata oluştu: ' + error.message + '</div>';
                });
        }

        function renderDetay(tip, data) {
            let html = '<div class="row">';

            // Personel Bilgileri
            html += '<div class="col-md-4 text-center mb-4">';
            html += '<img src="' + (data.resim_yolu || 'assets/images/users/user-dummy-img.jpg') + '" class="rounded-circle mb-3" style="width:100px;height:100px;object-fit:cover;" onerror="this.src=\'assets/images/users/user-dummy-img.jpg\'">';
            html += '<h5>' + data.adi_soyadi + '</h5>';
            html += '<p class="text-muted mb-1">' + (data.departman || '') + '</p>';
            html += '<p class="text-muted mb-0"><small>' + (data.gorev || '') + '</small></p>';
            html += '</div>';

            // Talep Detayları
            html += '<div class="col-md-8">';
            html += '<table class="table table-sm">';

            if (tip === 'avans') {
                html += '<tr><td class="text-muted" width="40%">Talep Tarihi:</td><td>' + formatDate(data.talep_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Tutar:</td><td class="fs-5 fw-bold text-success">' + formatMoney(data.tutar) + '</td></tr>';
                html += '<tr><td class="text-muted">Durum:</td><td><span class="badge bg-' + (data.durum == 'onaylandi' ? 'success' : (data.durum == 'reddedildi' ? 'danger' : 'warning')) + '">' + ucfirst(data.durum) + '</span></td></tr>';
            } else if (tip === 'izin') {
                html += '<tr><td class="text-muted" width="40%">Talep Tarihi:</td><td>' + formatDate(data.talep_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">İzin Türü:</td><td><span class="badge bg-primary">' + ucfirst(data.izin_tipi) + '</span></td></tr>';
                html += '<tr><td class="text-muted">Başlangıç:</td><td>' + formatDateOnly(data.baslangic_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Bitiş:</td><td>' + formatDateOnly(data.bitis_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Durum:</td><td><span class="badge bg-' + (data.onay_durumu == 'Onaylandı' ? 'success' : (data.onay_durumu == 'Reddedildi' ? 'danger' : 'warning')) + '">' + data.onay_durumu + '</span></td></tr>';
            } else if (tip === 'talep') {
                html += '<tr><td class="text-muted" width="40%">Oluşturma Tarihi:</td><td>' + formatDate(data.olusturma_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Başlık:</td><td><strong>' + data.baslik + '</strong></td></tr>';
                html += '<tr><td class="text-muted">Durum:</td><td><span class="badge bg-' + (data.durum == 'cozuldu' ? 'success' : (data.durum == 'islemde' ? 'info' : 'warning')) + '">' + ucfirst(data.durum) + '</span></td></tr>';
                if (data.aciklama) {
                    html += '<tr><td class="text-muted">Açıklama:</td><td>' + data.aciklama + '</td></tr>';
                }
                if (data.foto) {
                    html += '<tr><td class="text-muted">Fotoğraf:</td><td><img src="' + data.foto + '" class="img-fluid rounded mt-2" style="max-height:200px;cursor:pointer;" onclick="window.open(\'' + data.foto + '\', \'_blank\')" onerror="this.style.display=\'none\'"></td></tr>';
                }
            }

            html += '</table>';
            html += '</div>';
            html += '</div>';

            return html;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        }

        function formatDateOnly(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('tr-TR');
        }

        function formatMoney(amount) {
            return parseFloat(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
        }

        function ucfirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // URL'deki tab parametresini oku ve ilgili tab'ı aç
        var urlParams = new URLSearchParams(window.location.search);
        var tabParam = urlParams.get('tab');
        var idParam = urlParams.get('id');

        if (tabParam) {
            var tabId = '';
            if (tabParam === 'avans') tabId = 'tabAvans';
            else if (tabParam === 'izin') tabId = 'tabIzin';
            else if (tabParam === 'talep') tabId = 'tabTalepler';

            if (tabId) {
                var targetLink = document.querySelector('.nav-tabs a[href="#' + tabId + '"]');
                if (targetLink) {
                    var tabTrigger = new bootstrap.Tab(targetLink);
                    tabTrigger.show();
                }
            }
        }

        // ID varsa satırı vurgula ve odaklan
        if (idParam) {
            setTimeout(function () {
                var targetRow = document.querySelector('tr[data-id="' + idParam + '"]');
                if (targetRow) {
                    targetRow.style.backgroundColor = 'rgba(85, 110, 230, 0.2)';
                    targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Yanıp sönme efekti
                    var count = 0;
                    var interval = setInterval(function () {
                        targetRow.style.backgroundColor = count % 2 === 0 ? 'rgba(85, 110, 230, 0.4)' : 'rgba(85, 110, 230, 0.2)';
                        count++;
                        if (count > 5) {
                            clearInterval(interval);
                            targetRow.style.backgroundColor = 'rgba(85, 110, 230, 0.1)';
                        }
                    }, 500);
                }
            }, 1000);
        }

    });
</script>