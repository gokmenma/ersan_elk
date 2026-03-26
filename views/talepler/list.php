<?php

use App\Model\TalepModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Form;
use App\Service\Gate;

// Yetki değişikliği yapıldıysa önbelleği temizle (Opsiyonel ama hata olasılığını azaltır)
if (isset($_SESSION['user_id'])) {
    unset($_SESSION['permission_cache'][$_SESSION['user_id']]);
}

$canAvans = Gate::allows('avans_talepleri');
$canIzin = Gate::allows('izin_talepleri');
$canAriza = Gate::allows('ariza_talepleri');
$canTalepler = Gate::allows('talepler');

// Eğer hiçbir yetki yoksa (güvenlik için)
if (!$canAvans && !$canIzin && !$canAriza && !$canTalepler) {
    Gate::authorizeOrDie('talepler'); 
}

// Hangi tabın aktif olacağını yetkilere göre zorunlu belirle
$currentTab = $_GET['tab'] ?? '';
if (empty($currentTab) || 
    ($currentTab == 'avans' && !$canAvans) || 
    ($currentTab == 'izin' && !$canIzin) || 
    ($currentTab == 'talepler' && !$canAriza)) {
    
    if ($canAvans) $currentTab = 'avans';
    elseif ($canIzin) $currentTab = 'izin';
    elseif ($canAriza) $currentTab = 'talepler';
}

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
$toplamCount = 0;
if ($canAvans) $toplamCount += $avansCount;
if ($canIzin) $toplamCount += $izinCount;
if ($canAriza) $toplamCount += $talepCount;

// Talepleri getir - bekleyen veya işlem yapılmış
if ($showApproved) {
    $avanslar = $avansModel->getIslenmisAvanslar(50);
    try {
        $izinler = $izinModel->getIslenmisIzinler(50);
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
<link rel="stylesheet" href="views/talepler/assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">

<div class="container-fluid">
    <?php
    $maintitle = "Talepler";
    $title = "Talep Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Özet Kartları -->
    <div class="row mb-4 g-3">
        <?php if ($canAvans): ?>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card"
                style="--card-color: #10b981; --card-rgb: 16, 185, 129; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body">
                    <div class="icon-label-container">
                        <div class="icon-box">
                            <i class='bx bx-money fs-4' style="color: var(--card-color);"></i>
                        </div>
                        <div class="stat-trend neutral">
                            <i class='bx bx-hourglass'></i> Beklemede
                        </div>
                    </div>
                    <p class="stat-label-main">AVANS TALEPLERİ</p>
                    <h4 class="stat-value"><?= $avansCount ?></h4>
                    <p class="stat-sub">Bekleyen finansal talepler</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canIzin): ?>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card"
                style="--card-color: #3b82f6; --card-rgb: 59, 130, 246; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body">
                    <div class="icon-label-container">
                        <div class="icon-box">
                            <i class='bx bx-calendar-check fs-4' style="color: var(--card-color);"></i>
                        </div>
                        <div class="stat-trend neutral">
                            <i class='bx bx-time'></i> Planlı
                        </div>
                    </div>
                    <p class="stat-label-main">İZİN TALEPLERİ</p>
                    <h4 class="stat-value"><?= $izinCount ?></h4>
                    <p class="stat-sub">Bekleyen izin onayları</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canAriza): ?>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card"
                style="--card-color: #06b6d4; --card-rgb: 6, 182, 212; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body">
                    <div class="icon-label-container">
                        <div class="icon-box">
                            <i class='bx bx-message-square-detail fs-4' style="color: var(--card-color);"></i>
                        </div>
                        <div class="stat-trend neutral">
                            <i class='bx bx-info-circle'></i> Genel
                        </div>
                    </div>
                    <p class="stat-label-main">GENEL TALEPLER</p>
                    <h4 class="stat-value"><?= $talepCount ?></h4>
                    <p class="stat-sub">Diğer destek talepleri</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card"
                style="--card-color: #f59e0b; --card-rgb: 245, 158, 11; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body">
                    <div class="icon-label-container">
                        <div class="icon-box">
                            <i class='bx bx-bell fs-4' style="color: var(--card-color);"></i>
                        </div>
                        <div class="stat-trend down">
                            <i class='bx bx-error-circle'></i> Acil
                        </div>
                    </div>
                    <p class="stat-label-main">TOPLAM BEKLEYEN</p>
                    <h4 class="stat-value"><?= $toplamCount ?></h4>
                    <p class="stat-sub">İşlem bekleyen tüm talepler</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div
            class="card-header bg-transparent border-bottom-0 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-tabs nav-tabs-custom" id="talepTabs" role="tablist">
                <?php if ($canAvans): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentTab == 'avans' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabAvans" role="tab">
                        <i class="bx bx-money"></i> Avans
                        <?php if (!$showApproved && $avansCount > 0): ?>
                            <span class="badge bg-success"><?= $avansCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canIzin): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentTab == 'izin' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabIzin" role="tab">
                        <i class="bx bx-calendar-check"></i> İzin
                        <?php if (!$showApproved && $izinCount > 0): ?>
                            <span class="badge bg-primary"><?= $izinCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canAriza): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentTab == 'talepler' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabTalepler" role="tab">
                        <i class="bx bx-message-square-detail"></i> Talepler
                        <?php if (!$showApproved && $talepCount > 0): ?>
                            <span class="badge bg-info"><?= $talepCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <?php
            $currentTabForUrl = $currentTab;
            $pendingUrl = "index?p=talepler/list&tab=" . $currentTabForUrl;
            $approvedUrl = "index?p=talepler/list&show=approved&tab=" . $currentTabForUrl;
            ?>
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 mb-2">
                <a href="<?= $pendingUrl ?>" id="btnShowPending"
                    class="btn btn-sm px-3 rounded-pill <?= !$showApproved ? 'btn-warning text-dark shadow-sm fw-bold' : 'btn-link text-muted text-decoration-none' ?>">
                    <i class="bx bx-time me-1"></i>Bekleyenler
                </a>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                <a href="<?= $approvedUrl ?>" id="btnShowApproved"
                    class="btn btn-sm px-3 rounded-pill <?= $showApproved ? 'btn-success text-white shadow-sm fw-bold' : 'btn-link text-muted text-decoration-none' ?>">
                    <i class="bx bx-check-circle me-1"></i>İşlem Yapılanlar
                </a>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                <button type="button" class="btn btn-sm btn-outline-success px-3 rounded-pill" id="btnExportExcel">
                    <i class="bx bx-file me-1"></i>Excel e Aktar
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($showApproved): ?>
                <div class="alert alert-success mb-3">
                    <i class="bx bx-check-circle me-1"></i>
                    <strong>İşlem Yapılmış Talepler</strong> (Onaylanan & Reddedilen) görüntüleniyor. Son 50 kayıt
                    listelenmektedir.
                </div>
            <?php endif; ?>

            <div class="tab-content">
                <!-- Avans Talepleri Tab -->
                <?php if ($canAvans): ?>
                <div class="tab-pane fade <?= $currentTab == 'avans' ? 'show active' : '' ?>" id="tabAvans" role="tabpanel">
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table datatables table-hover table-bordered nowrap align-middle w-100 datatable"
                            id="avansTable" data-order="[]">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="string">Personel</th>
                                    <th data-filter="select">Talep Türü</th>
                                    <th data-filter="number">Tutar</th>
                                    <th data-filter="date">Talep Tarihi</th>
                                    <th data-filter="select">Durum</th>
                                    <th>Açıklama</th>
                                    <?php if ($showApproved): ?>
                                        <th>İşlem Yapan</th>
                                        <th>Sonuç Açıklaması</th>
                                    <?php endif; ?>
                                    <?php if (true): ?>
                                        <th class="text-center" style="width:200px">İşlemler</th>
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
                                                        <?= htmlspecialchars($avans->requester_name ?? '') ?>
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
                                            $durumType = 'warning';
                                            $durumText = ucfirst($avans->durum);
                                            if ($avans->durum == 'onaylandi') {
                                                $durumType = 'success';
                                                $durumText = 'Onaylandı';
                                            }
                                            if ($avans->durum == 'reddedildi') {
                                                $durumType = 'danger';
                                                $durumText = 'Reddedildi';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $durumType ?>">
                                                <i
                                                    class="bx bx-<?= $durumType == 'success' ? 'check-circle' : ($durumType == 'danger' ? 'x-circle' : 'time-five') ?>"></i>
                                                <?= $durumText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($avans->aciklama ?? '-') ?>
                                        </td>
                                        <?php if ($showApproved): ?>
                                            <td>
                                                <?= htmlspecialchars($avans->solver_name ?? '-') ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($avans->onay_aciklama ?? '-') ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <?php if ($avans->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-success btn-avans-onayla" type="button"
                                                        data-id="<?= $avans->id ?>"
                                                        data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>"
                                                        data-tutar="<?= $avans->tutar ?>" title="Onayla">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($avans->durum != 'reddedildi'): ?>
                                                    <button class="btn btn-sm btn-soft-danger btn-avans-reddet" type="button"
                                                        data-id="<?= $avans->id ?>"
                                                        data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>"
                                                        title="Reddet">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-soft-info btn-avans-detay" type="button"
                                                    data-id="<?= $avans->id ?>" title="Detay">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if ($avans->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-danger btn-sil" type="button"
                                                        data-id="<?= $avans->id ?>" data-tip="avans" title="Sil">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobil Görünüm (Avans) -->
                    <div class="d-block d-lg-none mt-3">
                        <div class="row g-3">
                            <?php foreach ($avanslar as $avans): ?>
                                <div class="col-12" data-id="<?= $avans->id ?>" data-tip="avans">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($avans->resim_yolu) ? $avans->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" class="rounded-circle avatar-sm" alt="">
                                                    <div>
                                                        <h6 class="mb-0 fs-14"><?= htmlspecialchars($avans->requester_name ?? '') ?></h6>
                                                        <small class="text-muted"><?= date('d.m.Y H:i', strtotime($avans->talep_tarihi)) ?></small>
                                                    </div>
                                                </div>
                                                <span class="badge bg-success"><i class="bx bx-money me-1"></i>Avans</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">Tutar:</span>
                                                <span class="text-success fw-bold"><?= number_format($avans->tutar, 2, ',', '.') ?> ₺</span>
                                            </div>
                                            <?php
                                            $durumType = 'warning';
                                            $durumText = ucfirst($avans->durum);
                                            if ($avans->durum == 'onaylandi') { $durumType = 'success'; $durumText = 'Onaylandı'; }
                                            if ($avans->durum == 'reddedildi') { $durumType = 'danger'; $durumText = 'Reddedildi'; }
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="text-muted small">Durum:</span>
                                                <span class="badge bg-<?= $durumType ?>"><i class="bx bx-<?= $durumType == 'success' ? 'check-circle' : ($durumType == 'danger' ? 'x-circle' : 'time-five') ?>"></i> <?= $durumText ?></span>
                                            </div>
                                            
                                            <?php if ($showApproved): ?>
                                            <div class="mb-3 bg-light p-2 rounded small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted"><i class="bx bx-user me-1"></i>İşlem Yapan:</span>
                                                    <span class="fw-medium"><?= htmlspecialchars($avans->solver_name ?? '-') ?></span>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="text-muted"><i class="bx bx-comment-detail me-1"></i>Açıklama:</span>
                                                    <span class="text-break mt-1"><?= htmlspecialchars($avans->onay_aciklama ?? '-') ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex gap-2 mt-2 pt-2 border-top">
                                                <?php if ($avans->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-success flex-fill btn-avans-onayla" type="button" data-id="<?= $avans->id ?>" data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>" data-tutar="<?= $avans->tutar ?>"><i class="bx bx-check"></i> Onayla</button>
                                                <?php endif; ?>
                                                <?php if ($avans->durum != 'reddedildi'): ?>
                                                    <button class="btn btn-sm btn-soft-danger flex-fill btn-avans-reddet" type="button" data-id="<?= $avans->id ?>" data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>"><i class="bx bx-x"></i> Red</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-soft-info flex-fill btn-avans-detay" type="button" data-id="<?= $avans->id ?>"><i class="bx bx-show"></i> Detay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($avanslar)): ?>
                                <div class="col-12"><div class="alert alert-info text-center mb-0">Gösterilecek kayıt bulunamadı.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- İzin Talepleri Tab -->
                <?php if ($canIzin): ?>
                <div class="tab-pane fade <?= $currentTab == 'izin' ? 'show active' : '' ?>" id="tabIzin" role="tabpanel">
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table datatables table-hover table-bordered nowrap align-middle w-100 datatable"
                            id="izinTable" data-order="[]">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel</th>
                                    <th>Talep Türü</th>
                                    <th>İzin Türü</th>
                                    <th>Tarih Aralığı</th>
                                    <th>Gün Sayısı</th>
                                    <th>Durum</th>
                                    <th>Açıklama</th>
                                    <?php if ($showApproved): ?>
                                        <th>İşlem Yapan</th>
                                        <th>Sonuç Açıklaması</th>
                                    <?php endif; ?>
                                    <?php if (true): ?>
                                        <th class="text-center" style="width:200px">İşlemler</th>
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
                                                        <?= htmlspecialchars($izin->requester_name ?? '') ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($izin->departman ?? '') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><i class="bx bx-calendar-check me-1"></i>İzin</span>
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
                                            $durumType = 'warning';
                                            $durumText = ucfirst($izin->onay_durumu);
                                            if ($izin->onay_durumu == 'Onaylandı') {
                                                $durumType = 'success';
                                            }
                                            if ($izin->onay_durumu == 'Reddedildi') {
                                                $durumType = 'danger';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $durumType ?>">
                                                <i
                                                    class="bx bx-<?= $durumType == 'success' ? 'check-circle' : ($durumType == 'danger' ? 'x-circle' : 'time-five') ?>"></i>
                                                <?= $durumText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($izin->aciklama ?? '-') ?>
                                        </td>
                                        <?php if ($showApproved): ?>
                                            <td>
                                                <?= htmlspecialchars($izin->solver_name ?? '-') ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($izin->onay_aciklama ?? '-') ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <?php if ($izin->onay_durumu != 'Onaylandı'): ?>
                                                    <button class="btn btn-sm btn-soft-success btn-izin-onayla" type="button"
                                                        data-id="<?= $izin->id ?>"
                                                        data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"
                                                        data-tur="<?= $izinTuruLabel ?>" data-gun="<?= $gunSayisi ?>"
                                                        title="Onayla">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($izin->onay_durumu != 'Reddedildi'): ?>
                                                    <button class="btn btn-sm btn-soft-danger btn-izin-reddet" type="button"
                                                        data-id="<?= $izin->id ?>"
                                                        data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"
                                                        title="Reddet">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-soft-info btn-izin-detay" type="button"
                                                    data-id="<?= $izin->id ?>" title="Detay">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if ($izin->onay_durumu != 'Onaylandı'): ?>
                                                    <button class="btn btn-sm btn-soft-danger btn-sil" type="button"
                                                        data-id="<?= $izin->id ?>" data-tip="izin" title="Sil">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobil Görünüm (İzin) -->
                    <div class="d-block d-lg-none mt-3">
                        <div class="row g-3">
                            <?php foreach ($izinler as $izin): 
                                $gunSayisi = $izinModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);
                                $izinTuruLabel = $izin->izin_tipi_adi ?? $izin->izin_tipi ?? 'Belirtilmemiş';
                            ?>
                                <div class="col-12" data-id="<?= $izin->id ?>" data-tip="izin">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($izin->resim_yolu) ? $izin->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" class="rounded-circle avatar-sm" alt="">
                                                    <div>
                                                        <h6 class="mb-0 fs-14"><?= htmlspecialchars($izin->requester_name ?? '') ?></h6>
                                                        <small class="text-muted"><span class="badge bg-info"><?= $izinTuruLabel ?></span></small>
                                                    </div>
                                                </div>
                                                <span class="badge bg-secondary"><?= $gunSayisi ?> Gün</span>
                                            </div>
                                            <div class="mb-2 text-muted small">
                                                <i class="bx bx-calendar"></i> <?= date('d.m.Y', strtotime($izin->baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($izin->bitis_tarihi)) ?>
                                            </div>
                                            <?php
                                            $durumType = 'warning';
                                            $durumText = ucfirst($izin->onay_durumu);
                                            if ($izin->onay_durumu == 'Onaylandı') { $durumType = 'success'; }
                                            if ($izin->onay_durumu == 'Reddedildi') { $durumType = 'danger'; }
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="text-muted small">Durum:</span>
                                                <span class="badge bg-<?= $durumType ?>"><i class="bx bx-<?= $durumType == 'success' ? 'check-circle' : ($durumType == 'danger' ? 'x-circle' : 'time-five') ?>"></i> <?= $durumText ?></span>
                                            </div>
                                            
                                            <?php if ($showApproved): ?>
                                            <div class="mb-3 bg-light p-2 rounded small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted"><i class="bx bx-user me-1"></i>İşlem Yapan:</span>
                                                    <span class="fw-medium"><?= htmlspecialchars($izin->solver_name ?? '-') ?></span>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="text-muted"><i class="bx bx-comment-detail me-1"></i>Açıklama:</span>
                                                    <span class="text-break mt-1"><?= htmlspecialchars($izin->onay_aciklama ?? '-') ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex gap-2 mt-2 pt-2 border-top">
                                                <?php if ($izin->onay_durumu != 'Onaylandı'): ?>
                                                    <button class="btn btn-sm btn-soft-success flex-fill btn-izin-onayla" type="button" data-id="<?= $izin->id ?>" data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>" data-tur="<?= $izinTuruLabel ?>" data-gun="<?= $gunSayisi ?>"><i class="bx bx-check"></i> Onayla</button>
                                                <?php endif; ?>
                                                <?php if ($izin->onay_durumu != 'Reddedildi'): ?>
                                                    <button class="btn btn-sm btn-soft-danger flex-fill btn-izin-reddet" type="button" data-id="<?= $izin->id ?>" data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"><i class="bx bx-x"></i> Red</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-soft-info flex-fill btn-izin-detay" type="button" data-id="<?= $izin->id ?>"><i class="bx bx-show"></i> Detay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($izinler)): ?>
                                <div class="col-12"><div class="alert alert-info text-center mb-0">Gösterilecek kayıt bulunamadı.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Genel Talepler Tab -->
                <?php if ($canAriza): ?>
                <div class="tab-pane fade <?= $currentTab == 'talepler' ? 'show active' : '' ?>" id="tabTalepler" role="tabpanel">
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table datatables table-hover table-bordered nowrap align-middle w-100 datatable"
                            id="taleplerTable" data-order="[]">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel</th>
                                    <th>Talep Türü</th>
                                    <th>Başlık</th>
                                    <th>Durum</th>
                                    <th>Öncelik</th>
                                    <th>Tarih</th>
                                    <th>Açıklama</th>
                                    <th>Sonuç</th>
                                    <th class="text-center" style="width:200px">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($talepler as $talep):
                                    $durumType = 'warning';
                                    if ($talep->durum == 'islemde')
                                        $durumType = 'info';
                                    if ($talep->durum == 'cozuldu' || $talep->durum == 'onaylandi')
                                        $durumType = 'success';
                                    if ($talep->durum == 'reddedildi' || $talep->durum == 'iptal_edildi')
                                        $durumType = 'danger';

                                    $oncelikType = 'secondary';
                                    if (isset($talep->oncelik)) {
                                        if ($talep->oncelik == 'yuksek')
                                            $oncelikType = 'danger';
                                        if ($talep->oncelik == 'orta')
                                            $oncelikType = 'warning';
                                        if ($talep->oncelik == 'dusuk')
                                            $oncelikType = 'success';
                                    }
                                    ?>
                                    <tr data-id="<?= $talep->id ?>" data-tip="talep">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($talep->resim_yolu) ? $talep->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                    alt="" class="rounded-circle avatar-sm me-2">
                                                <div>
                                                    <h6 class="mb-0">
                                                        <?= htmlspecialchars($talep->requester_name ?? '') ?>
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
                                            <?php
                                            $durumText = ucfirst($talep->durum);
                                            if ($talep->durum == 'cozuldu')
                                                $durumText = 'Çözüldü';
                                            if ($talep->durum == 'onaylandi')
                                                $durumText = 'Onaylandı';
                                            if ($talep->durum == 'reddedildi')
                                                $durumText = 'Reddedildi';
                                            if ($talep->durum == 'iptal_edildi')
                                                $durumText = 'İptal Edildi';
                                            if ($talep->durum == 'islemde')
                                                $durumText = 'İşlemde';
                                            if ($talep->durum == 'beklemede')
                                                $durumText = 'Beklemede';
                                            ?>
                                            <span class="badge bg-<?= $durumType ?>">
                                                <i
                                                    class="bx bx-<?= $durumType == 'success' ? 'check-circle' : ($durumType == 'info' ? 'play-circle' : ($durumType == 'danger' ? 'x-circle' : 'time-five')) ?>"></i>
                                                <?= $durumText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $oncelikType ?>">
                                                <i
                                                    class="bx bx-<?= $oncelikType == 'danger' ? 'error' : ($oncelikType == 'warning' ? 'error-circle' : ($oncelikType == 'success' ? 'check-double' : 'info-circle')) ?>"></i>
                                                <?= ucfirst($talep->oncelik ?? 'Normal') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('d.m.Y H:i', strtotime($talep->olusturma_tarihi)) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($talep->aciklama ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($talep->cozum_aciklama ?? '-') ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <?php if ($talep->durum != 'islemde' && $talep->durum != 'cozuldu' && $talep->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-warning btn-talep-isleme" type="button"
                                                        data-id="<?= $talep->id ?>" title="İşleme Al">
                                                        <i class="bx bx-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($talep->durum != 'cozuldu' && $talep->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-success btn-talep-cozuldu" type="button"
                                                        data-id="<?= $talep->id ?>"
                                                        data-personel="<?= htmlspecialchars($talep->requester_name ?? '') ?>"
                                                        data-baslik="<?= htmlspecialchars($talep->baslik ?? '') ?>"
                                                        title="Çözüldü">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-soft-info btn-talep-detay" type="button"
                                                    data-id="<?= $talep->id ?>" title="Detay">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if ($talep->durum == 'beklemede' || $talep->durum == 'reddedildi' || $talep->durum == 'iptal_edildi'): ?>
                                                    <button class="btn btn-sm btn-soft-danger btn-sil" type="button"
                                                        data-id="<?= $talep->id ?>" data-tip="talep" title="Sil">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobil Görünüm (Talepler) -->
                    <div class="d-block d-lg-none mt-3">
                        <div class="row g-3">
                            <?php foreach ($talepler as $talep): 
                                $durumType = 'warning';
                                if ($talep->durum == 'islemde') $durumType = 'info';
                                if ($talep->durum == 'cozuldu' || $talep->durum == 'onaylandi') $durumType = 'success';
                                if ($talep->durum == 'reddedildi' || $talep->durum == 'iptal_edildi') $durumType = 'danger';

                                $oncelikType = 'secondary';
                                if (isset($talep->oncelik)) {
                                    if ($talep->oncelik == 'yuksek') $oncelikType = 'danger';
                                    if ($talep->oncelik == 'orta') $oncelikType = 'warning';
                                    if ($talep->oncelik == 'dusuk') $oncelikType = 'success';
                                }
                            ?>
                                <div class="col-12" data-id="<?= $talep->id ?>" data-tip="talep">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($talep->resim_yolu) ? $talep->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" class="rounded-circle avatar-sm" alt="">
                                                    <div>
                                                        <h6 class="mb-0 fs-14"><?= htmlspecialchars($talep->requester_name ?? '') ?></h6>
                                                        <small class="text-muted"><span class="badge bg-<?= $oncelikType ?>"><?= ucfirst($talep->oncelik ?? 'Normal') ?></span></small>
                                                    </div>
                                                </div>
                                                <span class="text-muted small"><?= date('d.m.Y H:i', strtotime($talep->olusturma_tarihi)) ?></span>
                                            </div>
                                            <div class="mb-2">
                                                <h6 class="mb-1"><?= htmlspecialchars($talep->baslik ?? '-') ?></h6>
                                                <p class="text-muted small mb-0 text-truncate"><?= htmlspecialchars($talep->aciklama ?? '-') ?></p>
                                            </div>
                                            <?php
                                            $durumText = ucfirst($talep->durum);
                                            if ($talep->durum == 'cozuldu') $durumText = 'Çözüldü';
                                            if ($talep->durum == 'onaylandi') $durumText = 'Onaylandı';
                                            if ($talep->durum == 'reddedildi') $durumText = 'Reddedildi';
                                            if ($talep->durum == 'iptal_edildi') $durumText = 'İptal Edildi';
                                            if ($talep->durum == 'islemde') $durumText = 'İşlemde';
                                            if ($talep->durum == 'beklemede') $durumText = 'Beklemede';
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="text-muted small">Durum:</span>
                                                <span class="badge bg-<?= $durumType ?>"><i class="bx bx-<?= $durumType == 'success' ? 'check-circle' : ($durumType == 'info' ? 'play-circle' : ($durumType == 'danger' ? 'x-circle' : 'time-five')) ?>"></i> <?= $durumText ?></span>
                                            </div>
                                            
                                            <div class="d-flex gap-2 mt-2 pt-2 border-top">
                                                <?php if ($talep->durum != 'islemde' && $talep->durum != 'cozuldu' && $talep->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-warning flex-fill btn-talep-isleme" type="button" data-id="<?= $talep->id ?>"><i class="bx bx-play"></i> İşleme Al</button>
                                                <?php endif; ?>
                                                <?php if ($talep->durum != 'cozuldu' && $talep->durum != 'onaylandi'): ?>
                                                    <button class="btn btn-sm btn-soft-success flex-fill btn-talep-cozuldu" type="button" data-id="<?= $talep->id ?>" data-personel="<?= htmlspecialchars($talep->requester_name ?? '') ?>" data-baslik="<?= htmlspecialchars($talep->baslik ?? '') ?>"><i class="bx bx-check"></i> Çözüldü</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-soft-info flex-fill btn-talep-detay" type="button" data-id="<?= $talep->id ?>"><i class="bx bx-show"></i> Detay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($talepler)): ?>
                                <div class="col-12"><div class="alert alert-info text-center mb-0">Gösterilecek kayıt bulunamadı.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
                            >
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

<!-- Silme Modal -->
<div class="modal fade" id="modalSil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bx bx-trash me-2"></i>Kayıt Silme</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSil">
                <input type="hidden" name="id" id="sil_id">
                <input type="hidden" name="action" id="sil_action">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Bu kaydı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Silme Gerekçesi <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="aciklama" rows="3" placeholder="Silme sebebini açıklayınız..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger"><i class="bx bx-trash me-1"></i>Sil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 32px;
        height: 32px;
        object-fit: cover;
    }

    /* Soft button styles for inline action buttons */
    .btn-soft-success {
        color: #0ab39c;
        background-color: rgba(10, 179, 156, 0.1);
        border: 1px solid rgba(10, 179, 156, 0.2);
    }

    .btn-soft-success:hover {
        color: #fff;
        background-color: #0ab39c;
        border-color: #0ab39c;
    }

    .btn-soft-danger {
        color: #f06548;
        background-color: rgba(240, 101, 72, 0.1);
        border: 1px solid rgba(240, 101, 72, 0.2);
    }

    .btn-soft-danger:hover {
        color: #fff;
        background-color: #f06548;
        border-color: #f06548;
    }

    .btn-soft-info {
        color: #299cdb;
        background-color: rgba(41, 156, 219, 0.1);
        border: 1px solid rgba(41, 156, 219, 0.2);
    }

    .btn-soft-info:hover {
        color: #fff;
        background-color: #299cdb;
        border-color: #299cdb;
    }

    .btn-soft-warning {
        color: #f7b84b;
        background-color: rgba(247, 184, 75, 0.1);
        border: 1px solid rgba(247, 184, 75, 0.2);
    }

    .btn-soft-warning:hover {
        color: #fff;
        background-color: #f7b84b;
        border-color: #f7b84b;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const API_URL = 'views/talepler/api.php';

        // Tab değiştiğinde Datatable'ı yenile (responsive düzeltmesi için)
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
        });

        // Excel Export
        document.getElementById('btnExportExcel')?.addEventListener('click', function () {
            // Aktif tabı bul
            const activeTabId = document.querySelector('.nav-tabs .nav-link.active').getAttribute('href').replace('#', '');
            let tableId = '';
            let fileName = '';

            if (activeTabId === 'tabAvans') {
                tableId = 'avansTable';
                fileName = 'Avans_Talepleri';
            } else if (activeTabId === 'tabIzin') {
                tableId = 'izinTable';
                fileName = 'Izin_Talepleri';
            } else if (activeTabId === 'tabTalepler') {
                tableId = 'taleplerTable';
                fileName = 'Genel_Talepler';
            }

            if (tableId) {
                const table = $('#' + tableId).DataTable();
                table.button('.buttons-excel').trigger();
            }
        });

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

        // Sil Modal Aç
        document.querySelectorAll('.btn-sil').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const tip = this.dataset.tip; // 'avans', 'izin', 'talep'

                document.getElementById('sil_id').value = id;
                document.getElementById('sil_action').value = tip + '-sil';
                document.getElementById('formSil').reset();

                new bootstrap.Modal(document.getElementById('modalSil')).show();
            });
        });

        // Sil Form Gönder
        document.getElementById('formSil')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            submitFormAction(formData, 'modalSil');
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
            html += '<h5>' + (data.requester_name || 'Bilinmeyen') + '</h5>';
            html += '<p class="text-muted mb-1">' + (data.departman || '') + '</p>';
            html += '<p class="text-muted mb-0"><small>' + (data.gorev || '') + '</small></p>';
            html += '</div>';

            // Talep Detayları
            html += '<div class="col-md-8">';
            html += '<table class="table table-sm">';

            if (tip === 'avans') {
                html += '<tr><td class="text-muted" width="40%">Talep Tarihi:</td><td>' + formatDate(data.talep_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Tutar:</td><td class="fs-5 fw-bold text-success">' + formatMoney(data.tutar) + '</td></tr>';
                var aDurum = data.durum == 'onaylandi' ? 'success' : (data.durum == 'reddedildi' ? 'danger' : 'warning');
                var aIcon = aDurum == 'success' ? 'check-circle' : (aDurum == 'danger' ? 'x-circle' : 'time-five');
                html += '<tr><td class="text-muted">Durum:</td><td><span class="badge bg-' + aDurum + '"><i class="bx bx-' + aIcon + '"></i> ' + ucfirst(data.durum) + '</span></td></tr>';
                if (data.aciklama) {
                    html += '<tr><td class="text-muted">Açıklama:</td><td>' + data.aciklama + '</td></tr>';
                }
            } else if (tip === 'izin') {
                html += '<tr><td class="text-muted" width="40%">Talep Tarihi:</td><td>' + formatDate(data.talep_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">İzin Türü:</td><td><span class="badge bg-info"><i class="bx bx-calendar"></i> ' + ucfirst(data.izin_tipi) + '</span></td></tr>';
                html += '<tr><td class="text-muted">Başlangıç:</td><td>' + formatDateOnly(data.baslangic_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Bitiş:</td><td>' + formatDateOnly(data.bitis_tarihi) + '</td></tr>';
                var iDurum = data.onay_durumu == 'Onaylandı' ? 'success' : (data.onay_durumu == 'Reddedildi' ? 'danger' : 'warning');
                var iIcon = iDurum == 'success' ? 'check-circle' : (iDurum == 'danger' ? 'x-circle' : 'time-five');
                html += '<tr><td class="text-muted">Durum:</td><td><span class="badge bg-' + iDurum + '"><i class="bx bx-' + iIcon + '"></i> ' + data.onay_durumu + '</span></td></tr>';
                if (data.aciklama) {
                    html += '<tr><td class="text-muted">Açıklama:</td><td>' + data.aciklama + '</td></tr>';
                }
            } else if (tip === 'talep') {
                html += '<tr><td class="text-muted" width="40%">Oluşturma Tarihi:</td><td>' + formatDate(data.olusturma_tarihi) + '</td></tr>';
                html += '<tr><td class="text-muted">Başlık:</td><td><strong>' + data.baslik + '</strong></td></tr>';
                var tDurum = data.durum == 'cozuldu' ? 'success' : (data.durum == 'islemde' ? 'info' : 'warning');
                var tIcon = tDurum == 'success' ? 'check-circle' : (tDurum == 'info' ? 'play-circle' : 'time-five');
                html += '<tr><td class="text-muted">Durum:</td><td><span class="badge bg-' + tDurum + '"><i class="bx bx-' + tIcon + '"></i> ' + ucfirst(data.durum) + '</span></td></tr>';
                if (data.aciklama) {
                    html += '<tr><td class="text-muted">Açıklama:</td><td>' + data.aciklama + '</td></tr>';
                }
                if (data.cozum_aciklama) {
                    html += '<tr><td class="text-muted text-success">Sonuç Açıklaması:</td><td><strong class="text-success">' + data.cozum_aciklama + '</strong></td></tr>';
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

        // Tab kliklendiğinde buton linklerini güncelle
        $('.nav-tabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href"); // #tabAvans, #tabIzin, #tabTalepler
            var tabName = '';
            if (target === '#tabAvans') tabName = 'avans';
            else if (target === '#tabIzin') tabName = 'izin';
            else if (target === '#tabTalepler') tabName = 'talep';

            if (tabName) {
                $('#btnShowPending').attr('href', 'index?p=talepler/list&tab=' + tabName);
                $('#btnShowApproved').attr('href', 'index?p=talepler/list&show=approved&tab=' + tabName);

                // URL'yi de sessizce güncelle (opsiyonel ama iyi olur)
                var newUrl = new URL(window.location.href);
                newUrl.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', newUrl);
            }
        });
    });
</script>