<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\AparatHareketModel;
use App\Model\AparatStokModel;
use App\Model\AparatTipiModel;
use App\Model\AparatTransferModel;
use App\Model\KesmeAcmaIslemModel;
use App\Service\Gate;

$Tip = new AparatTipiModel();
$Stok = new AparatStokModel();

$bugun = date('Y-m-d');
$ayBasi = date('Y-m-01');

$tipler = $Tip->listele(false);
$ekipler = $Stok->ekipler();

$yetkiDepo = Gate::allows('aparat_depo') || Gate::isSuperAdmin();
$yetkiIptal = Gate::allows('aparat_iptal') || Gate::isSuperAdmin();
$yetkiSayim = Gate::allows('aparat_sayim') || Gate::isSuperAdmin();
$yetkiTanim = Gate::allows('aparat_tanim') || Gate::isSuperAdmin();
$yetkiTransfer = Gate::allows('aparat_transfer_yonet') || Gate::isSuperAdmin();

// Ekipler listelerde personel adıyla gösterilir; ekip kodu parantez içinde kalır.
$ekipUyeleri = $Stok->ekipUyeHaritasi();

$ekipEtiketi = function (array $ekip) use ($ekipUyeleri): string {
    $uyeler = $ekipUyeleri[(int) $ekip['id']] ?? '';
    return $uyeler !== ''
        ? $uyeler . ' (' . $ekip['tur_adi'] . ')'
        : $ekip['tur_adi'];
};

$ekipOptions = ['' => 'Tüm Ekipler'];
$ekipSecimOptions = ['' => 'Ekip Seçiniz'];
foreach ($ekipler as $ekip) {
    $etiket = $ekipEtiketi($ekip);
    $ekipOptions[$ekip['id']] = $etiket;
    $ekipSecimOptions[$ekip['id']] = $etiket;
}

$tipOptions = ['' => 'Tüm Aparatlar'];
$tipSecimOptions = ['' => 'Aparat Seçiniz'];
foreach ($tipler as $t) {
    if ((int) $t['is_active'] === 1) {
        $tipOptions[$t['id']] = $t['ad'];
        $tipSecimOptions[$t['id']] = $t['ad'];
    }
}

$hareketTipiOptions = ['' => 'Tüm Hareketler'] + AparatHareketModel::HAREKET_TIPLERI;
$havuzOptions = ['' => 'Tüm Havuzlar'] + AparatHareketModel::HAVUZLAR;

$havuzHareketOptions = [
    'depo_giris' => 'Depo Girişi (satın alma)',
    'depo_cikis' => 'Depodan Ekibe Çıkış',
    'depo_iade' => 'Ekipten Depoya İade',
    'hurda' => 'Hurdaya Ayır',
    'kayip' => 'Kayıp Kaydı',
    'acilis' => 'Açılış Stoğu (ilk kurulum)',
];

$renkOptions = [];
foreach (AparatTipiModel::RENKLER as $renk) {
    $renkOptions[$renk] = ucfirst($renk);
}
?>

<style>
    .aparat-matris th,
    .aparat-matris td {
        vertical-align: middle;
        white-space: nowrap;
    }

    .aparat-matris tbody td.adet-hucre {
        text-align: center;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .aparat-matris tbody td.adet-sifir {
        color: #adb5bd;
        font-weight: 400;
    }

    .aparat-matris tbody td.adet-negatif {
        color: #f46a6a;
        background-color: rgba(244, 106, 106, .10);
    }

    .aparat-matris tbody tr.havuz-satiri {
        background-color: rgba(85, 110, 230, .05);
    }

    .aparat-matris tfoot td {
        font-weight: 700;
        border-top: 2px solid #dee2e6;
    }

    .aparat-ozet-kart {
        border-left: 3px solid transparent;
    }

    .aparat-ozet-sayi {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .aparat-ozet-etiket {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #74788d;
    }

    .aparat-foto-kutu img {
        width: 100%;
        border-radius: .5rem;
        object-fit: cover;
        max-height: 320px;
    }

    .sayim-adet-input {
        max-width: 110px;
    }

    .status-filter-group {
        background: #f8f9fa;
        padding: 4px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .status-filter-group .btn-check + .btn {
        margin-bottom: 0 !important;
        border: none !important;
        border-radius: 50px !important;
        font-size: .75rem;
        font-weight: 600;
        padding: 6px 16px;
        color: #64748b;
        transition: all .2s ease;
        background: transparent !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-filter-group .btn-check:checked + .btn {
        background: #fff !important;
        color: #0ea5e9;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
    }

    .dataTables_wrapper,
    table.dataTable {
        width: 100% !important;
    }

    .nc-adim {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #556ee6;
        color: #fff;
        font-size: .72rem;
        margin-right: 6px;
    }

    .nc-havuz {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 50px;
        background: #f8f9fa;
        border: 1px solid #e2e8f0;
        font-size: .78rem;
        font-weight: 600;
        color: #495057;
    }

    .nc-formul {
        background: #f8f9fa;
        border-left: 3px solid #556ee6;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: .8rem;
        font-weight: 600;
        color: #343a40;
    }

    .nc-kutu {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px;
        font-size: .82rem;
        height: 100%;
    }

    [data-bs-theme="dark"] .nc-havuz,
    [data-bs-theme="dark"] .nc-formul {
        background: #2a3042;
        border-color: #32394e;
        color: #c3cbe4;
    }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "İş Takip";
    $title = "Aparat Takip";
    include 'layouts/breadcrumb.php';
    ?>

    <div class="row g-3 mb-3" id="aparatOzetSerit">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 3px solid #556ee6 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size: 0.68rem; letter-spacing: 0.5px;">Toplam Aparat</div>
                        <h4 class="mb-0 fw-bold text-dark" id="ozetToplam">-</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title rounded-3 fs-4" style="background: rgba(85, 110, 230, 0.1); color: #556ee6;">
                            <i class="bx bx-package"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 3px solid #34c38f !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size: 0.68rem; letter-spacing: 0.5px;">Sahada Takılı</div>
                        <h4 class="mb-0 fw-bold text-dark" id="ozetSaha">-</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title rounded-3 fs-4" style="background: rgba(52, 195, 143, 0.1); color: #34c38f;">
                            <i class="bx bx-map-pin"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 3px solid #f1b44c !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size: 0.68rem; letter-spacing: 0.5px;">Bekleyen Transfer</div>
                        <h4 class="mb-0 fw-bold text-dark" id="ozetTransfer">-</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title rounded-3 fs-4" style="background: rgba(241, 180, 76, 0.1); color: #f1b44c;">
                            <i class="bx bx-transfer-alt"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 3px solid #f46a6a !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size: 0.68rem; letter-spacing: 0.5px;">Negatif Stoklu Kayıt</div>
                        <h4 class="mb-0 fw-bold text-danger" id="ozetNegatif">-</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title rounded-3 fs-4" style="background: rgba(244, 106, 106, 0.1); color: #f46a6a;">
                            <i class="bx bx-error-circle"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="aparatTutarsizlikUyari" class="alert alert-danger d-none align-items-center gap-2" role="alert">
        <i class="bx bx-error-circle fs-4"></i>
        <div class="flex-grow-1">
            Ana defter ile bakiye tablosu arasında <b id="tutarsizSayi">0</b> satırda fark var.
            <?php if ($yetkiDepo): ?>
                Bakiyeyi ana defterden yeniden hesaplayabilirsiniz.
            <?php endif; ?>
        </div>
        <?php if ($yetkiDepo): ?>
            <button type="button" class="btn btn-sm btn-danger" id="btnBakiyeOnar">Bakiyeyi Onar</button>
        <?php endif; ?>
    </div>

    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pane-stok" role="tab">
                <i class="bx bx-table me-1"></i> Stok Durumu</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-islemler" role="tab">
                <i class="bx bx-list-ul me-1"></i> Saha İşlemleri</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-hareketler" role="tab">
                <i class="bx bx-transfer-alt me-1"></i> Hareket Dökümü</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-transferler" role="tab">
                <i class="bx bx-git-compare me-1"></i> Transferler</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-sayim" role="tab">
                <i class="bx bx-check-square me-1"></i> Sayım</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-raporlar" role="tab">
                <i class="bx bx-bar-chart-alt-2 me-1"></i> Raporlar</a></li>
        <?php if ($yetkiTanim): ?>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-tanimlar" role="tab">
                    <i class="bx bx-cog me-1"></i> Tanımlar</a></li>
        <?php endif; ?>

        <li class="nav-item ms-auto d-flex align-items-center">
            <button type="button" class="btn btn-sm btn-soft-info d-flex align-items-center gap-1"
                data-bs-toggle="modal" data-bs-target="#modalNasilCalisir">
                <i class="bx bx-help-circle fs-5"></i> Nasıl Çalışır?
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ============ STOK MATRİSİ ============ -->
        <div class="tab-pane fade show active" id="pane-stok">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-package me-1 text-primary"></i> Ekip × Aparat Tipi Stok Tablosu</h5>
                        <p class="text-muted small mb-0 mt-1">Depo, ekipler, sahada takılı, hurda ve kayıp havuzlarının anlık dökümü.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        <?php if ($yetkiDepo): ?>
                            <button type="button" class="btn btn-primary btn-sm" id="btnHavuzHareketi">
                                <i class="bx bx-plus me-1"></i> Depo / Havuz Hareketi
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnManuelIslem">
                                <i class="bx bx-edit me-1"></i> Manuel Saha Kaydı
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnStokYenile" title="Yenile">
                            <i class="bx bx-refresh"></i>
                        </button>
                        <a class="btn btn-outline-success btn-sm" id="btnStokExcel"
                            href="views/aparat-takip/export-excel.php?tip=stok" title="Excel İndir">
                            <i class="bx bx-download"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle aparat-matris mb-0 w-100" id="tabloStokMatris" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="string">Ekip / Personel</th>
                                    <?php foreach ($tipler as $t): ?>
                                        <th class="text-center<?= (int) $t['is_active'] === 0 ? ' text-muted' : '' ?>" data-filter="number">
                                            <?= htmlspecialchars($t['ad'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ((int) $t['is_active'] === 0): ?>
                                                <small class="d-block fw-normal">(pasif)</small>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center" data-filter="number">Toplam</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot class="table-light"></tfoot>
                        </table>
                    </div>
                    <?php if (empty($tipler)): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            Henüz aparat tipi tanımlanmamış.
                            <?= $yetkiTanim ? '“Tanımlar” sekmesinden ekleyebilirsiniz.' : 'Yöneticinizden tanımlamasını isteyin.' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============ SAHA İŞLEMLERİ ============ -->
        <div class="tab-pane fade" id="pane-islemler">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-2"><?= Form::FormDate('islem_bas', Date::dmY($ayBasi), 'Başlangıç') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormDate('islem_bit', Date::dmY($bugun), 'Bitiş') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormSelect2('islem_ekip', $ekipOptions, '', 'Ekip', 'bx bx-group') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormSelect2('islem_tip_filtre', ['' => 'Kesme + Açma', 'kesme' => 'Kesme', 'acma' => 'Açma'], '', 'İşlem', 'bx bx-filter') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormSelect2('islem_aparat', $tipOptions, '', 'Aparat', 'bx bx-package') ?></div>
                        <div class="col-6 col-md-2 d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1" id="btnIslemListele"><i class="bx bx-search"></i></button>
                            <a class="btn btn-outline-success" id="btnIslemExcel" href="#" title="Excel"><i class="bx bx-download"></i></a>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <div class="status-filter-group shadow-sm" id="islemDurumFiltre">
                            <input type="radio" class="btn-check" name="islem-durum" id="is_durum_aktif" value="aktif" checked>
                            <label class="btn px-3" for="is_durum_aktif"><i class="bx bx-check-circle"></i> Aktif</label>

                            <input type="radio" class="btn-check" name="islem-durum" id="is_durum_tum" value="">
                            <label class="btn px-3" for="is_durum_tum"><i class="bx bx-check-double"></i> İptaller Dahil</label>

                            <input type="radio" class="btn-check" name="islem-durum" id="is_durum_negatif" value="negatif">
                            <label class="btn px-3" for="is_durum_negatif"><i class="bx bx-error"></i> Negatif Stoklu</label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 w-100" id="tabloIslemler" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="select">İşlem</th>
                                    <th data-filter="select">Ekip</th>
                                    <th data-filter="string">Personel</th>
                                    <th data-filter="string">Abone No</th>
                                    <th data-filter="string">Sayaç No</th>
                                    <th data-filter="select">Aparat</th>
                                    <th class="text-center" data-filter="number">Adet</th>
                                    <th data-filter="select">Durum</th>
                                    <th class="text-center">Foto</th>
                                    <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ HAREKET DÖKÜMÜ ============ -->
        <div class="tab-pane fade" id="pane-hareketler">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-2"><?= Form::FormDate('hrk_bas', Date::dmY($ayBasi), 'Başlangıç') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormDate('hrk_bit', Date::dmY($bugun), 'Bitiş') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormSelect2('hrk_ekip', $ekipOptions, '', 'Ekip', 'bx bx-group') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormSelect2('hrk_tip', $hareketTipiOptions, '', 'Hareket Tipi', 'bx bx-transfer') ?></div>
                        <div class="col-6 col-md-2"><?= Form::FormSelect2('hrk_havuz', $havuzOptions, '', 'Havuz', 'bx bx-box') ?></div>
                        <div class="col-6 col-md-2 d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1" id="btnHareketListele"><i class="bx bx-search"></i></button>
                            <a class="btn btn-outline-success" id="btnHareketExcel" href="#" title="Excel"><i class="bx bx-download"></i></a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 w-100" id="tabloHareketler" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="select">Hareket</th>
                                    <th data-filter="select">Havuz</th>
                                    <th data-filter="select">Ekip</th>
                                    <th data-filter="select">Aparat</th>
                                    <th class="text-center" data-filter="number">Adet</th>
                                    <th data-filter="string">Personel / Kullanıcı</th>
                                    <th data-filter="string">Açıklama</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TRANSFERLER ============ -->
        <div class="tab-pane fade" id="pane-transferler">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center gap-2">
                    <div class="status-filter-group shadow-sm" id="transferDurumFiltre">
                        <input type="radio" class="btn-check" name="transfer-durum" id="tr_durum_all" value="" checked>
                        <label class="btn px-3" for="tr_durum_all"><i class="bx bx-check-double"></i> Tümü</label>

                        <input type="radio" class="btn-check" name="transfer-durum" id="tr_durum_bekleyen" value="beklemede">
                        <label class="btn px-3" for="tr_durum_bekleyen"><i class="bx bx-time-five"></i> Beklemede</label>

                        <input type="radio" class="btn-check" name="transfer-durum" id="tr_durum_onayli" value="onaylandi">
                        <label class="btn px-3" for="tr_durum_onayli"><i class="bx bx-check-circle"></i> Onaylandı</label>

                        <input type="radio" class="btn-check" name="transfer-durum" id="tr_durum_red" value="reddedildi">
                        <label class="btn px-3" for="tr_durum_red"><i class="bx bx-x-circle"></i> Reddedildi</label>
                    </div>
                    <div class="ms-auto text-muted small">
                        <i class="bx bx-info-circle"></i> Transferler alan ekibin onayıyla stoğa işlenir.
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 w-100" id="tabloTransferler" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="select">Veren Ekip</th>
                                    <th data-filter="select">Alan Ekip</th>
                                    <th data-filter="select">Aparat</th>
                                    <th class="text-center" data-filter="number">Adet</th>
                                    <th data-filter="select">Durum</th>
                                    <th data-filter="string">Oluşturan</th>
                                    <th data-filter="string">Onaylayan</th>
                                    <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ SAYIM ============ -->
        <div class="tab-pane fade" id="pane-sayim">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 fw-bold">Sayımlar</h6>
                            <?php if ($yetkiSayim): ?>
                                <button class="btn btn-sm btn-primary" id="btnSayimBaslat"><i class="bx bx-plus"></i> Başlat</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush" id="sayimListesi">
                                <div class="list-group-item text-muted small">Yükleniyor...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center gap-2">
                            <h6 class="mb-0 fw-bold" id="sayimBaslik">Sayım seçilmedi</h6>
                            <div class="ms-auto d-flex gap-2" id="sayimAksiyonlar"></div>
                        </div>
                        <div class="card-body">
                            <div id="sayimDetayIcerik" class="text-muted small">
                                Soldaki listeden bir sayım seçin.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ RAPORLAR ============ -->
        <div class="tab-pane fade" id="pane-raporlar">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center gap-2">
                            <h6 class="mb-0 fw-bold"><i class="bx bx-map-pin me-1 text-danger"></i> Sahada Takılı Aparatlar</h6>
                            <div class="ms-auto d-flex gap-2 align-items-center">
                                <div style="min-width:150px"><?= Form::FormSelect2('rapor_aparat', $tipOptions, '', 'Aparat', 'bx bx-package') ?></div>
                                <div style="width:120px"><?= Form::FormFloatInput('number', 'rapor_min_gun', '', '0', 'Min. Gün', 'bx bx-calendar') ?></div>
                                <button class="btn btn-primary" id="btnSahadaTakili"><i class="bx bx-search"></i></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 w-100" id="tabloSahada" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th data-filter="date">Kesme Tarihi</th>
                                            <th class="text-center" data-filter="number">Gün</th>
                                            <th data-filter="string">Abone No</th>
                                            <th data-filter="string">Sayaç No</th>
                                            <th data-filter="select">İlçe / Mahalle</th>
                                            <th data-filter="select">Aparat</th>
                                            <th class="text-center" data-filter="number">Adet</th>
                                            <th data-filter="select">Kesen Ekip</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center gap-2">
                            <h6 class="mb-0 fw-bold"><i class="bx bx-pie-chart-alt me-1 text-primary"></i> Dönemsel Özet</h6>
                            <div class="ms-auto d-flex gap-2 align-items-center">
                                <div style="width:140px"><?= Form::FormDate('ozet_bas', Date::dmY($ayBasi), 'Başlangıç') ?></div>
                                <div style="width:140px"><?= Form::FormDate('ozet_bit', Date::dmY($bugun), 'Bitiş') ?></div>
                                <button class="btn btn-primary" id="btnDonemselOzet"><i class="bx bx-search"></i></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 w-100" id="tabloDonemsel" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th data-filter="select">İşlem</th>
                                            <th data-filter="select">Aparat</th>
                                            <th class="text-center" data-filter="number">Kayıt</th>
                                            <th class="text-center" data-filter="number">Aparat Adedi</th>
                                            <th class="text-center" data-filter="number">Hasarlı</th>
                                            <th class="text-center" data-filter="number">Kayıp</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center gap-2">
                            <div>
                                <h6 class="mb-0 fw-bold"><i class="bx bx-git-compare me-1 text-warning"></i> KASKİ API Karşılaştırması</h6>
                                <small class="text-muted">API'deki aparatlı kesim adedi ile panele girilen kayıtlar</small>
                            </div>
                            <button class="btn btn-primary ms-auto" id="btnApiKarsilastir"><i class="bx bx-search"></i></button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height:420px;overflow:auto">
                                <table class="table table-sm align-middle mb-0 w-100" id="tabloApiKarsilastirma" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th data-filter="date">Tarih</th>
                                            <th data-filter="select">Ekip</th>
                                            <th class="text-center" data-filter="number">API</th>
                                            <th class="text-center" data-filter="number">Panel</th>
                                            <th class="text-center" data-filter="number">Fark</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TANIMLAR ============ -->
        <?php if ($yetkiTanim): ?>
            <div class="tab-pane fade" id="pane-tanimlar">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-cog me-1 text-primary"></i> Aparat Tipleri</h5>
                            <p class="text-muted small mb-0 mt-1">Stok hareketi görmüş tipler silinmez, pasife alınır.</p>
                        </div>
                        <button class="btn btn-primary btn-sm" id="btnTipEkle"><i class="bx bx-plus me-1"></i> Yeni Tip</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 w-100" id="tabloTipler" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" data-filter="number">Sıra</th>
                                        <th class="text-center" style="width:60px">Görsel</th>
                                        <th data-filter="string">Ad</th>
                                        <th data-filter="string">Kod</th>
                                        <th data-filter="select">Renk</th>
                                        <th data-filter="string">Açıklama</th>
                                        <th data-filter="select">Durum</th>
                                        <th class="text-end">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ MODAL: NASIL ÇALIŞIR ============ -->
<div class="modal fade" id="modalNasilCalisir" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-help-circle me-1 text-info"></i> Aparat Takip Nasıl Çalışır?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <p class="text-muted">
                    Aparatlar <b>ekibe</b> zimmetlidir, personele değil. Ekip üyeleri gün gün
                    değişebildiği için aparat araçta/ekipte kalır; ekranlarda ekip kodu yerine
                    o ekipte <b>bugün çalışan personelin adı</b> gösterilir.
                </p>

                <h6 class="fw-bold mt-4"><span class="nc-adim">1</span> Beş havuz</h6>
                <p class="text-muted small mb-2">Her aparat daima bu beş havuzdan birindedir. Toplam adet asla kendiliğinden değişmez.</p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="nc-havuz"><i class="bx bx-buildings"></i> Depo</span>
                    <span class="nc-havuz"><i class="bx bx-group"></i> Ekip Stoğu</span>
                    <span class="nc-havuz"><i class="bx bx-map-pin"></i> Sahada Takılı</span>
                    <span class="nc-havuz"><i class="bx bx-trash"></i> Hurda</span>
                    <span class="nc-havuz"><i class="bx bx-error"></i> Kayıp</span>
                </div>
                <div class="nc-formul">Depo + Σ(ekipler) + Sahada takılı + Hurda + Kayıp = Toplam alınan aparat</div>
                <p class="text-muted small mt-2 mb-0">
                    Bu eşitlik her aparat tipi için ayrı tutar. <b>Stok Durumu</b> sekmesindeki tablonun
                    en alt satırı bu toplamı gösterir.
                </p>

                <h6 class="fw-bold mt-4"><span class="nc-adim">2</span> Saha işlemi: kesme ve açma</h6>
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="nc-kutu border-danger-subtle">
                            <div class="fw-bold text-danger mb-1"><i class="bx bx-water"></i> Kesme</div>
                            Ekip stoğundan <b>−1</b> düşer, aparat <b>Sahada Takılı</b> havuzuna geçer.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nc-kutu border-success-subtle">
                            <div class="fw-bold text-success mb-1"><i class="bx bx-droplet"></i> Açma</div>
                            Sahada Takılı havuzundan <b>−1</b> düşer; aparatın durumuna göre:
                            <ul class="mb-0 ps-3 mt-1">
                                <li><b>Alındı</b> → açan ekibin stoğuna +1</li>
                                <li><b>Hasarlı geldi</b> → Hurda havuzuna +1</li>
                                <li><b>Bulunamadı</b> → Kayıp havuzuna +1</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info py-2 small mt-2 mb-0">
                    <b>Modülün çözdüğü asıl problem:</b> Ekip-3'ün kestiği aboneyi günler sonra Ekip-5 açarsa,
                    aparat kendiliğinden Ekip-5'in stoğuna geçer. Kimin taktığı ile kimin söktüğünün
                    farklı olması sorun değil, sistemin normal işleyişidir.
                </div>

                <h6 class="fw-bold mt-4"><span class="nc-adim">3</span> Depo ve havuz hareketleri</h6>
                <p class="text-muted small mb-2"><b>Stok Durumu → Depo / Havuz Hareketi</b> butonundan yapılır (şef yetkisi ister).</p>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="fw-semibold" style="width:38%">Depo Girişi</td><td>Satın alınan aparatlar depoya eklenir. Toplamı artıran tek hareket.</td></tr>
                        <tr><td class="fw-semibold">Depodan Ekibe Çıkış</td><td>Depo −, ekip + . <b>Ekibin zimmeti budur.</b></td></tr>
                        <tr><td class="fw-semibold">Ekipten Depoya İade</td><td>Ekip −, depo +</td></tr>
                        <tr><td class="fw-semibold">Hurdaya Ayır / Kayıp</td><td>Ekip −, hurda veya kayıp + (gerekçe zorunlu)</td></tr>
                        <tr><td class="fw-semibold">Açılış Stoğu</td><td>Modül devreye girerken ekiplerin elindeki mevcut aparatlar. Bir kereye mahsus.</td></tr>
                    </tbody>
                </table>

                <h6 class="fw-bold mt-4"><span class="nc-adim">4</span> Ekipler arası transfer</h6>
                <p class="text-muted small mb-0">
                    Veren ekip telefondan gönderir → kayıt <b>Beklemede</b> durumuna düşer →
                    <b>alan ekip onaylayana kadar stok değişmez</b>. Alan ekip adedi düzelterek de onaylayabilir.
                    Şef bekleyen bir transferi yalnızca <u>iptal</u> edebilir, onaylayamaz — çift onay
                    "ben verdim / bana gelmedi" tartışmasında sistemin hakem olabilmesi için vardır.
                </p>

                <h6 class="fw-bold mt-4"><span class="nc-adim">5</span> Sayım (mutabakat)</h6>
                <p class="text-muted small mb-0">
                    Şef sayım başlatır, her ekip için tip bazında satır açılır. Sayılan adet girilir,
                    sistem farkı hesaplar. <b>Farkları İşle</b> dendiğinde eksik çıkanlar Kayıp havuzuna yazılır,
                    fazla çıkanlar Kayıp havuzundan düşülür — böylece toplam adet korunur.
                    Farklı çıkan her satır için açıklama zorunludur.
                </p>

                <h6 class="fw-bold mt-4"><span class="nc-adim">6</span> Bilinmesi gereken kurallar</h6>
                <ul class="text-muted small mb-0 ps-3">
                    <li><b>Ana defter esastır.</b> Stok tablosu yalnızca hızlı okuma içindir ve her hareketle aynı anda güncellenir. Aralarında fark oluşursa sayfanın üstünde kırmızı uyarı çıkar, "Bakiyeyi Onar" ile defterden yeniden hesaplanır.</li>
                    <li><b>Kayıt silinmez, iptal edilir.</b> İptalde ters hareket yazılır, iz kaybolmaz.</li>
                    <li><b>Negatif stok engellenmez.</b> Ekipte o tipten aparat yokken kesme girilirse kayıt yine alınır, kırmızı işaretlenir ve şefe raporlanır — saha kilitlenmesin diye.</li>
                    <li><b>Fotoğraf zorunlu.</b> Her saha kaydında sayaç ve aparat fotoğrafı istenir.</li>
                    <li><b>Çevrimdışı çalışır.</b> Kapsama dışında girilen kayıt telefonda kuyruğa alınır, bağlantı gelince gönderilir. Hareket, kaydın gönderildiği güne değil <b>işlemin yapıldığı güne</b> ve o gün geçerli olan ekibe yazılır.</li>
                    <li><b>Mükerrer uyarısı:</b> Aynı abonede aynı gün aynı işlem ikinci kez girilirse kayıt işaretlenir.</li>
                </ul>

                <h6 class="fw-bold mt-4"><span class="nc-adim">7</span> İlk kurulum sırası</h6>
                <ol class="text-muted small mb-0 ps-3">
                    <li><b>Tanımlar</b> sekmesinden aparat tipleri girilir.</li>
                    <li><b>Depo Girişi</b> ile mevcut aparat mevcudu depoya işlenir.</li>
                    <li>Her ekip için <b>Açılış Stoğu</b> ya da <b>Depodan Ekibe Çıkış</b> yapılır.</li>
                    <li>Saha personeli telefonda <b>Hızlı İşlemler → Aparat Takip</b> ekranından kayda başlar.</li>
                </ol>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ MODAL: HAVUZ HAREKETİ ============ -->
<div class="modal fade" id="modalHavuz" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formHavuz">
                <div class="modal-header">
                    <h5 class="modal-title">Depo / Havuz Hareketi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><?= Form::FormSelect2('havuz_tur', $havuzHareketOptions, 'depo_giris', 'Hareket Türü', 'bx bx-transfer', 'key', '', 'form-select select2', true) ?></div>
                        <div class="col-12" id="havuzEkipAlan"><?= Form::FormSelect2('havuz_ekip', $ekipSecimOptions, '', 'Ekip', 'bx bx-group') ?></div>
                        <div class="col-7"><?= Form::FormSelect2('havuz_aparat', $tipSecimOptions, '', 'Aparat Tipi', 'bx bx-package', 'key', '', 'form-select select2', true) ?></div>
                        <div class="col-5"><?= Form::FormFloatInput('number', 'havuz_adet', '1', '1', 'Adet', 'bx bx-hash', 'form-control', true) ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea('havuz_aciklama', '', 'Açıklama / irsaliye no', 'Açıklama', 'bx bx-note') ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODAL: MANUEL SAHA KAYDI ============ -->
<div class="modal fade" id="modalIslem" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formIslem">
                <div class="modal-header">
                    <h5 class="modal-title">Manuel Saha Kaydı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><?= Form::FormSelect2('mi_islem_tipi', ['kesme' => 'Kesme', 'acma' => 'Açma'], 'kesme', 'İşlem Tipi', 'bx bx-transfer', 'key', '', 'form-select select2', true) ?></div>
                        <div class="col-md-4"><?= Form::FormSelect2('mi_ekip', $ekipSecimOptions, '', 'Ekip', 'bx bx-group', 'key', '', 'form-select select2', true) ?></div>
                        <div class="col-md-4"><?= Form::FormDate('mi_tarih', Date::dmY($bugun), 'Tarih') ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput('text', 'mi_abone_no', '', 'Abone No', 'Abone No', 'bx bx-user') ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput('text', 'mi_sayac_no', '', 'Sayaç No', 'Sayaç No', 'bx bx-tachometer') ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput('text', 'mi_ilce', '', 'İlçe', 'İlçe', 'bx bx-map') ?></div>
                        <div class="col-md-5"><?= Form::FormSelect2('mi_aparat', $tipSecimOptions, '', 'Aparat Tipi', 'bx bx-package') ?></div>
                        <div class="col-md-3"><?= Form::FormFloatInput('number', 'mi_adet', '1', '1', 'Adet', 'bx bx-hash') ?></div>
                        <div class="col-md-4" id="miDurumAlan" style="display:none">
                            <?= Form::FormSelect2('mi_aparat_durumu', KesmeAcmaIslemModel::APARAT_DURUMLARI, 'alindi', 'Aparat Durumu', 'bx bx-check-shield') ?>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mi_aparatsiz">
                                <label class="form-check-label" for="mi_aparatsiz">Bu işlemde aparat kullanılmadı</label>
                            </div>
                        </div>
                        <div class="col-12"><?= Form::FormFloatTextarea('mi_aciklama', '', 'Açıklama', 'Açıklama', 'bx bx-note') ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODAL: İŞLEM DETAY ============ -->
<div class="modal fade" id="modalIslemDetay" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Saha İşlem Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="islemDetayIcerik"></div>
            <div class="modal-footer">
                <?php if ($yetkiIptal): ?>
                    <button type="button" class="btn btn-outline-danger me-auto" id="btnIslemIptal">
                        <i class="bx bx-x-circle me-1"></i> Kaydı İptal Et
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ MODAL: APARAT TİPİ ============ -->
<?php if ($yetkiTanim): ?>
    <div class="modal fade" id="modalTip" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formTip" enctype="multipart/form-data">
                    <input type="hidden" id="tip_id" value="0">
                    <input type="hidden" id="tip_resim_sil" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tipModalBaslik">Yeni Aparat Tipi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-8"><?= Form::FormFloatInput('text', 'tip_ad', '', 'Küresel Aparat', 'Aparat Adı', 'bx bx-package', 'form-control', true, 100) ?></div>
                            <div class="col-4"><?= Form::FormFloatInput('text', 'tip_kod', '', 'KUR', 'Kod', 'bx bx-hash', 'form-control', true, 20) ?></div>
                            <div class="col-6"><?= Form::FormSelect2('tip_renk', $renkOptions, 'primary', 'Renk', 'bx bx-palette') ?></div>
                            <div class="col-6"><?= Form::FormFloatInput('number', 'tip_sira', '1', '1', 'Sıra', 'bx bx-sort') ?></div>
                            <div class="col-12"><?= Form::FormFloatTextarea('tip_aciklama', '', 'Açıklama', 'Açıklama', 'bx bx-note') ?></div>
                            <div class="col-12">
                                <label for="tip_resim" class="form-label fw-semibold small text-muted mb-1"><i class="bx bx-image me-1"></i> Aparat Görseli (İsteğe Bağlı)</label>
                                <input class="form-control" type="file" id="tip_resim" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                                <div class="form-text text-muted small">Max 5MB (JPG, PNG, WEBP, GIF, SVG). Görsel şifrelenerek saklanır.</div>
                                <div id="tip_resim_onizleme_kutu" class="d-none mt-2 text-center border rounded p-2 bg-light position-relative">
                                    <img id="tip_resim_img" src="" class="img-thumbnail" style="max-height: 120px; object-fit: contain;" alt="Aparat Önizleme">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnTipResimSil"><i class="bx bx-trash me-1"></i> Resmi Kaldır</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="tip_aktif" checked>
                                    <label class="form-check-label" for="tip_aktif">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============ MODAL: TIP RESİM BÜYÜK ÖNİZLEME ============ -->
<div class="modal fade" id="modalTipResimOnizleme" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTipResimBaslik">Aparat Görseli</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img id="modalTipResimImg" src="" class="img-fluid rounded shadow-sm" style="max-height: 500px; width: auto;" alt="Aparat Görseli">
            </div>
        </div>
    </div>
</div>

<!-- ============ MODAL: SAYIM BAŞLAT ============ -->
<?php if ($yetkiSayim): ?>
    <div class="modal fade" id="modalSayim" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formSayim">
                    <div class="modal-header">
                        <h5 class="modal-title">Sayım Başlat</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12"><?= Form::FormFloatInput('text', 'sayim_baslik', 'Aparat Sayımı ' . date('d.m.Y'), '', 'Sayım Başlığı', 'bx bx-clipboard', 'form-control', true, 100) ?></div>
                            <div class="col-12">
                                <label class="form-label small text-muted">Sayıma dahil ekipler (boş bırakılırsa tümü)</label>
                                <select class="form-select select2" id="sayim_ekipler" multiple style="width:100%">
                                    <?php foreach ($ekipler as $ekip): ?>
                                        <option value="<?= (int) $ekip['id'] ?>"><?= htmlspecialchars($ekipEtiketi($ekip), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><?= Form::FormFloatTextarea('sayim_aciklama', '', 'Açıklama', 'Açıklama', 'bx bx-note') ?></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-primary">Sayımı Başlat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    window.aparatYetki = {
        depo: <?= $yetkiDepo ? 'true' : 'false' ?>,
        iptal: <?= $yetkiIptal ? 'true' : 'false' ?>,
        sayim: <?= $yetkiSayim ? 'true' : 'false' ?>,
        tanim: <?= $yetkiTanim ? 'true' : 'false' ?>,
        transfer: <?= $yetkiTransfer ? 'true' : 'false' ?>
    };
    window.aparatTipleri = <?= json_encode(array_map(fn($t) => [
        'id' => (int) $t['id'],
        'ad' => $t['ad'],
        'kod' => $t['kod'],
        'renk' => $t['renk'],
        'aktif' => (int) $t['is_active'],
    ], $tipler), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="views/aparat-takip/js/list.js?v=<?= filemtime(__DIR__ . '/js/list.js') ?>"></script>
