<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracModel;
use App\Model\AracZimmetModel;
use App\Model\AracYakitModel;
use App\Model\AracKmModel;
use App\Model\PersonelModel;

$Arac = new AracModel();
$Zimmet = new AracZimmetModel();
$Yakit = new AracYakitModel();
$Km = new AracKmModel();
$Personel = new PersonelModel();

$araclar = $Arac->all();
$personeller = $Personel->all();
$aracStats = $Arac->getStats();
$zimmetStats = $Zimmet->getStats();
$zimmetliSayi = $Arac->getZimmetliAracSayisi();

// Aylık istatistikler (mevcut ay)
$yakitStats = $Yakit->getStats(date('Y'), date('m'));
$kmStats = $Km->getStats(date('Y'), date('m'));

$activeTab = $_GET['tab'] ?? 'arac';
?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Araç Takip";
    $title = "Araç Takip & Yakıt Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-pills" id="aracTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'arac' ? 'active' : ''; ?>" id="arac-tab" data-bs-toggle="tab"
                                    data-bs-target="#aracContent" type="button" role="tab">
                                    <i class="bx bx-car me-1"></i> Araçlar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'zimmet' ? 'active' : ''; ?>" id="zimmet-tab" data-bs-toggle="tab"
                                    data-bs-target="#zimmetContent" type="button" role="tab">
                                    <i class="bx bx-transfer me-1"></i> Zimmetler
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'yakit' ? 'active' : ''; ?>" id="yakit-tab" data-bs-toggle="tab"
                                    data-bs-target="#yakitContent" type="button" role="tab">
                                    <i class="bx bx-gas-pump me-1"></i> Yakıt Kayıtları
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'km' ? 'active' : ''; ?>" id="km-tab" data-bs-toggle="tab"
                                    data-bs-target="#kmContent" type="button" role="tab">
                                    <i class="bx bx-tachometer me-1"></i> KM Kayıtları
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'rapor' ? 'active' : ''; ?>" id="rapor-tab" data-bs-toggle="tab"
                                    data-bs-target="#raporContent" type="button" role="tab">
                                    <i class="bx bx-bar-chart-alt-2 me-1"></i> Raporlar
                                </button>
                            </li>
                        </ul>

                        <div class="vr mx-2 d-none d-md-block"></div>

                        <!-- İstatistikler -->
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-primary-subtle text-primary fs-6">
                                <i class="bx bx-car me-1"></i> Araç:
                                <?php echo $aracStats->toplam_arac ?? 0; ?>
                            </span>
                            <span class="badge bg-success-subtle text-success fs-6">
                                <i class="bx bx-check-circle me-1"></i> Aktif:
                                <?php echo $aracStats->aktif_arac ?? 0; ?>
                            </span>
                            <span class="badge bg-warning-subtle text-warning fs-6">
                                <i class="bx bx-transfer me-1"></i> Zimmetli:
                                <?php echo $zimmetliSayi; ?>
                            </span>
                        </div>

                        <!-- Butonlar -->
                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <button class="btn btn-primary" type="button" id="btnYeniEkle">
                                <i class="bx bx-plus me-1"></i> Yeni Ekle
                            </button>

                            <div class="dropdown">
                                <button type="button" class="btn btn-secondary dropdown-toggle waves-effect waves-light"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bx bx-menu me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" id="btnExceleAktar">
                                            <i class="bx bx-export me-2"></i> Excele Aktar
                                        </a></li>
                                    <li id="liExcelYakitYukle" style="display: none;"><a class="dropdown-item" href="#" data-bs-toggle="modal"
                                            data-bs-target="#excelModal">
                                            <i class="bx bx-upload me-2"></i> Excel'den Yakıt Yükle
                                        </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="aracTabContent">

                        <!-- =============================================
                             ARAÇLAR TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'arac' ? 'show active' : ''; ?>" id="aracContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="aracTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:12%">Plaka</th>
                                            <th style="width:15%">Marka/Model</th>
                                            <th style="width:8%" class="text-center">Tip</th>
                                            <th style="width:8%" class="text-center">Yakıt</th>
                                            <th style="width:10%" class="text-end">Güncel KM</th>
                                            <th style="width:15%">Zimmetli</th>
                                            <th style="width:8%" class="text-center">Durum</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 0;
                                        foreach ($araclar as $arac):
                                            $i++; ?>
                                                            <?php
                                                            $durumBadge = $arac->aktif_mi ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>';

                                                            $tipLabels = [
                                                                'binek' => 'Binek',
                                                                'kamyonet' => 'Kamyonet',
                                                                'kamyon' => 'Kamyon',
                                                                'minibus' => 'Minibüs',
                                                                'otobus' => 'Otobüs',
                                                                'motosiklet' => 'Motosiklet',
                                                                'diger' => 'Diğer'
                                                            ];

                                                            $yakitLabels = [
                                                                'benzin' => '<span class="badge bg-danger">Benzin</span>',
                                                                'dizel' => '<span class="badge bg-dark">Dizel</span>',
                                                                'lpg' => '<span class="badge bg-info">LPG</span>',
                                                                'elektrik' => '<span class="badge bg-success">Elektrik</span>',
                                                                'hibrit' => '<span class="badge bg-warning text-dark">Hibrit</span>'
                                                            ];
                                                            ?>
                                                            <tr>
                                                                <td class="text-center">
                                                                    <?php echo $i; ?>
                                                                </td>
                                                                <td>
                                                                    <a href="javascript:void(0)" class="fw-bold text-primary arac-duzenle"
                                                                        data-id="<?php echo $arac->id; ?>">
                                                                        <?php echo $arac->plaka; ?>
                                                                    </a>
                                                                </td>
                                                                <td>
                                                                    <?php echo ($arac->marka ?? '-') . ' ' . ($arac->model ?? ''); ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php echo $tipLabels[$arac->arac_tipi] ?? '-'; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php echo $yakitLabels[$arac->yakit_tipi] ?? '-'; ?>
                                                                </td>
                                                                <td class="text-end">
                                                                    <?php echo number_format($arac->guncel_km ?? 0, 0, ',', '.'); ?> km
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($arac->zimmetli_personel_adi)): ?>
                                                                                        <span class="badge bg-warning-subtle text-warning">
                                                                                            <i class="bx bx-user me-1"></i>
                                                                                            <?php echo $arac->zimmetli_personel_adi; ?>
                                                                                        </span>
                                                                    <?php else: ?>
                                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php echo $durumBadge; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <div class="btn-group btn-group-sm">
                                                                        <?php if (empty($arac->zimmetli_personel_id)): ?>
                                                                                            <button type="button" class="btn btn-warning zimmet-hizli"
                                                                                                data-id="<?php echo $arac->id; ?>"
                                                                                                data-plaka="<?php echo $arac->plaka; ?>"
                                                                                                data-km="<?php echo $arac->guncel_km; ?>" title="Zimmet Ver">
                                                                                                <i class="bx bx-transfer"></i>
                                                                                            </button>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-primary arac-duzenle"
                                                                            data-id="<?php echo $arac->id; ?>" title="Düzenle">
                                                                            <i class="bx bx-edit"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-danger arac-sil"
                                                                            data-id="<?php echo $arac->id; ?>"
                                                                            data-plaka="<?php echo $arac->plaka; ?>" title="Sil">
                                                                            <i class="bx bx-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             ZİMMETLER TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'zimmet' ? 'show active' : ''; ?>" id="zimmetContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="zimmetTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:18%">Araç</th>
                                            <th style="width:18%">Personel</th>
                                            <th style="width:12%">Zimmet Tarihi</th>
                                            <th style="width:12%">İade Tarihi</th>
                                            <th style="width:10%" class="text-center">Durum</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="zimmetTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             YAKIT KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'yakit' ? 'show active' : ''; ?>" id="yakitContent" role="tabpanel">
                            <!-- Aylık Özet Kartları -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-success bg-gradient text-white">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="yakit-toplam-litre">
                                                <?php echo number_format($yakitStats->toplam_litre ?? 0, 0, ',', '.'); ?>
                                                L
                                            </h4>
                                            <small>Bu Ay Toplam Yakıt</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning bg-gradient text-dark">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="yakit-toplam-maliyet">
                                                <?php echo number_format($yakitStats->toplam_tutar ?? 0, 2, ',', '.'); ?>
                                                ₺
                                            </h4>
                                            <small>Bu Ay Toplam Maliyet</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info bg-gradient text-white">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="yakit-ortalama-fiyat">
                                                <?php echo number_format($yakitStats->ortalama_birim_fiyat ?? 0, 2, ',', '.'); ?>
                                                ₺
                                            </h4>
                                            <small>Ortalama Birim Fiyat</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-primary bg-gradient text-white">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="yakit-kayit-sayisi">
                                                <?php echo $yakitStats->toplam_kayit ?? 0; ?>
                                            </h4>
                                            <small>Bu Ay Kayıt Sayısı</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-4">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php
                                            $aracOptions = ['' => 'Tüm Araçlar'];
                                            foreach ($araclar as $arac) {
                                                $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                            }
                                            echo App\Helper\Form::FormSelect2('yakit-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>







                                        </div>
                                        <div class="col-md-3 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-primary w-100" id="btnYakitFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                            <button type="button" class="btn btn-info w-100" id="btnYakitIstatistik" data-bs-toggle="modal" data-bs-target="#istatistikModal" data-type="yakit">
                                                <i class="bx bx-stats me-1"></i> İstatistikler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="yakitTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:12%">Plaka</th>
                                            <th style="width:10%">Tarih</th>
                                            <th style="width:10%" class="text-end">KM</th>
                                            <th style="width:10%" class="text-end">Miktar (L)</th>
                                            <th style="width:10%" class="text-end">Birim Fiyat</th>
                                            <th style="width:12%" class="text-end">Toplam Tutar</th>
                                            <th style="width:15%">İstasyon</th>
                                            <th style="width:8%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yakitTableBody">
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             KM KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'km' ? 'show active' : ''; ?>" id="kmContent" role="tabpanel">
                            <!-- Aylık Özet Kartları -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card bg-primary bg-gradient text-white">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="km-toplam-yol">
                                                <?php echo number_format($kmStats->toplam_km ?? 0, 0, ',', '.'); ?> km
                                            </h4>
                                            <small>Bu Ay Toplam Yol</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-info bg-gradient text-white">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="km-ortalama-yol">
                                                <?php echo number_format($kmStats->ortalama_gunluk_km ?? 0, 1, ',', '.'); ?> km
                                            </h4>
                                            <small>Ortalama Günlük Yol</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-success bg-gradient text-white">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="km-kayit-sayisi">
                                                <?php echo $kmStats->toplam_kayit ?? 0; ?>
                                            </h4>
                                            <small>Bu Ay Kayıt Sayısı</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-4">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php
                                            echo App\Helper\Form::FormSelect2('km-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>







                                        </div>
                                        <div class="col-md-3 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-primary w-100" id="btnKmFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                            <button type="button" class="btn btn-info w-100" id="btnKmIstatistik" data-bs-toggle="modal" data-bs-target="#istatistikModal" data-type="km">
                                                <i class="bx bx-stats me-1"></i> İstatistikler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="kmTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%">Plaka</th>
                                            <th style="width:15%">Tarih</th>
                                            <th style="width:15%" class="text-end">Başlangıç KM</th>
                                            <th style="width:15%" class="text-end">Bitiş KM</th>
                                            <th style="width:15%" class="text-end">Yapılan KM</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="kmTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             RAPORLAR TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'rapor' ? 'show active' : ''; ?>" id="raporContent" role="tabpanel">
                            <!-- Filtre -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Yıl</label>
                                    <select class="form-select" id="raporYil">
                                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                                            <option value="<?php echo $y; ?>">
                                                                <?php echo $y; ?>
                                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Ay</label>
                                    <select class="form-select" id="raporAy">
                                        <?php
                                        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                                        for ($m = 1; $m <= 12; $m++):
                                            ?>
                                                            <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                                                <?php echo $aylar[$m - 1]; ?>
                                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Araç (Opsiyonel)</label>
                                    <select class="form-select" id="raporArac">
                                        <option value="">Tüm Araçlar</option>
                                        <?php foreach ($araclar as $arac): ?>
                                                            <option value="<?php echo $arac->id; ?>">
                                                                <?php echo $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? ''); ?>
                                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary w-100" id="btnRaporYukle">
                                        <i class="bx bx-search me-1"></i> Rapor Getir
                                    </button>
                                </div>
                            </div>

                            <!-- Rapor İçeriği -->
                            <div id="raporIcerik">
                                <div class="text-center py-5 text-muted">
                                    <i class="bx bx-bar-chart-alt-2 display-1"></i>
                                    <p class="mt-3">Rapor görüntülemek için yukarıdan filtre seçin ve "Rapor Getir"
                                        butonuna tıklayın.</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #aracTable tbody tr,
    #zimmetTable tbody tr,
    #yakitTable tbody tr,
    #kmTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #aracTable tbody tr:hover,
    #zimmetTable tbody tr:hover,
    #yakitTable tbody tr:hover,
    #kmTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.1);
    }

    .nav-pills .nav-link.active {
        background-color: #556ee6;
    }

    .nav-pills .nav-link {
        color: #495057;
    }

    .card.bg-gradient {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
</style>

<!-- Modaller -->
<?php include_once "modal/arac-modal.php"; ?>
<?php include_once "modal/zimmet-modal.php"; ?>
<?php include_once "modal/yakit-modal.php"; ?>
<?php include_once "modal/km-modal.php"; ?>
<?php include_once "modal/excel-modal.php"; ?>

<!-- İstatistik Modal -->
<div class="modal fade" id="istatistikModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-stats me-2"></i>İstatistik Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="istatistikModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="views/arac-takip/js/arac-takip.js"></script>