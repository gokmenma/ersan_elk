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

$superAdmin = Gate::isSuperAdmin();
$yetkiDashboard = Gate::allows('aparat-takip/dashboard') || $superAdmin;
$yetkiGelismis = Gate::allows('aparat_gelismis') || $superAdmin;
$yetkiDepo = Gate::allows('aparat_depo') || $superAdmin;
$yetkiIptal = $yetkiGelismis && (Gate::allows('aparat_iptal') || $superAdmin);
$yetkiSayim = $yetkiGelismis && (Gate::allows('aparat_sayim') || $superAdmin);
$yetkiTanim = $yetkiGelismis && (Gate::allows('aparat_tanim') || $superAdmin);
$yetkiTransfer = $yetkiGelismis && (Gate::allows('aparat_transfer_yonet') || $superAdmin);

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


$renkOptions = [];
foreach (AparatTipiModel::RENKLER as $renk) {
    $renkOptions[$renk] = ucfirst($renk);
}
?>

<script>try { document.documentElement.classList.toggle('aparat-summary-hidden', localStorage.getItem('aparat_summary_cards_state') === 'hidden'); } catch (e) {}</script>

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

    .apf { border: 1px solid #e2e8f0; border-radius: 14px; background: #f8fafc; overflow: hidden; }
    .apf-sahne { position: relative; height: 250px; margin: 6px 8px 0; }
    .apf-yollar { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .apf-yollar line { stroke: #cbd5e1; stroke-width: 2; stroke-dasharray: 4 5; vector-effect: non-scaling-stroke; transition: stroke .3s ease; }
    .apf-yollar line.aktif { stroke: var(--apf-renk, #475569); stroke-width: 3; animation: apf-akis .6s linear infinite; }
    @keyframes apf-akis { to { stroke-dashoffset: -18; } }
    .apf-dugum { position: absolute; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; gap: 3px; width: 96px; text-align: center; z-index: 1; }
    .apf-ikon { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, .07); font-size: 22px; color: #475569; transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease; }
    .apf-dugum--depo .apf-ikon { color: #2563eb; }
    .apf-dugum--ekip .apf-ikon { color: #059669; }
    .apf-dugum--abone .apf-ikon { color: #d97706; }
    .apf-dugum--tedarik .apf-ikon { color: #64748b; background: #f1f5f9; }
    .apf-dugum.vurgu .apf-ikon { transform: scale(1.08); border-color: var(--apf-renk, #475569); box-shadow: 0 0 0 4px rgba(71, 85, 105, .12); }
    .apf-ad { font-size: 12px; font-weight: 700; line-height: 1.15; color: #1e293b; }
    .apf-ad small { display: block; font-size: 10.5px; font-weight: 500; color: #64748b; }
    .apf-sayi { position: absolute; top: -6px; right: 18px; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: #1e293b; color: #fff; font-size: 11.5px; font-weight: 700; line-height: 22px; font-variant-numeric: tabular-nums; }
    .apf-sayi.artti { animation: apf-art .5s ease; background: #059669; }
    .apf-sayi.azaldi { animation: apf-art .5s ease; background: #d97706; }
    @keyframes apf-art { 0% { transform: scale(1); } 40% { transform: scale(1.35); } 100% { transform: scale(1); } }
    .apf-aparat { position: absolute; z-index: 2; display: grid; place-items: center; width: 30px; height: 30px; margin: -15px 0 0 -15px; border-radius: 9px; background: var(--apf-renk, #475569); color: #fff; font-size: 17px; box-shadow: 0 6px 14px rgba(15, 23, 42, .25); transition: left 1.2s cubic-bezier(.45, .05, .25, 1), top 1.2s cubic-bezier(.45, .05, .25, 1), opacity .25s ease; }
    .apf-aparat.gizli { opacity: 0; }
    .apf-anlatim { padding: 6px 18px 4px; text-align: center; min-height: 92px; }
    .apf-kim { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .3px; background: #e2e8f0; color: #334155; }
    .apf-kim.siz { background: #dbeafe; color: #1d4ed8; }
    .apf-kim.sistem { background: #d1fae5; color: #047857; }
    .apf-baslik { margin: 6px 0 2px; font-size: 16px; font-weight: 700; color: #0f172a; }
    .apf-metin { margin: 0 auto; max-width: 520px; font-size: 13px; color: #475569; }
    .apf-kontrol { display: flex; align-items: center; gap: 6px; padding: 10px 14px 12px; border-top: 1px solid #e2e8f0; background: #fff; }
    .apf-kontrol .btn { width: 34px; height: 34px; padding: 0; display: inline-grid; place-items: center; font-size: 18px; }
    .apf-adimlar { display: flex; flex: 1; gap: 6px; margin-left: 6px; }
    .apf-adimlar button { flex: 1; height: 8px; border: 0; border-radius: 999px; background: #e2e8f0; padding: 0; position: relative; overflow: hidden; cursor: pointer; }
    .apf-adimlar button.bitti { background: #94a3b8; }
    .apf-adimlar button.aktif::after { content: ""; position: absolute; inset: 0; width: 0; background: var(--apf-renk, #475569); animation: apf-dolum var(--apf-sure, 3.6s) linear forwards; }
    .apf.durdu .apf-adimlar button.aktif::after { animation: none; width: 100%; }
    @keyframes apf-dolum { to { width: 100%; } }
    .apf-ozet { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 12px 0 4px; }
    .apf-ozet__kutu { padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #1e293b; }
    .apf-ozet__kutu small { display: block; margin-top: 3px; color: #64748b; font-size: 12px; }
    .apf-ozet__etiket { display: inline-block; margin-bottom: 4px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
    .apf-ozet__etiket--siz { background: #dbeafe; color: #1d4ed8; }
    .apf-ozet__etiket--sistem { background: #d1fae5; color: #047857; }
    @media (max-width: 575.98px) {
        .apf-sahne { height: 230px; }
        .apf-dugum { width: 74px; }
        .apf-ikon { width: 40px; height: 40px; font-size: 19px; }
        .apf-ad { font-size: 11px; }
        .apf-sayi { right: 6px; }
        .apf-ozet { grid-template-columns: 1fr; }
    }
    [data-bs-theme="dark"] .apf { background: #2a3042; border-color: #32394e; }
    [data-bs-theme="dark"] .apf-ikon { background: #32394e; border-color: #3b4560; }
    [data-bs-theme="dark"] .apf-ad, [data-bs-theme="dark"] .apf-baslik, [data-bs-theme="dark"] .apf-ozet__kutu { color: #e2e8f0; }
    [data-bs-theme="dark"] .apf-metin, [data-bs-theme="dark"] .apf-ad small { color: #a6b0cf; }
    [data-bs-theme="dark"] .apf-kontrol { background: #2a3042; border-color: #32394e; }
    [data-bs-theme="dark"] .apf-ozet__kutu { border-color: #32394e; }
    @media (prefers-reduced-motion: reduce) {
        .apf-aparat, .apf-ikon { transition: none; }
        .apf-yollar line.aktif, .apf-sayi.artti, .apf-sayi.azaldi { animation: none; }
    }

    .aparat-matris td.adet-kritik {
        color: #b45309;
        background-color: rgba(245, 158, 11, .14);
    }

    .aparat-kritik-kutu {
        border: 1px solid #fde68a;
        background: #fffbeb;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 12.5px;
        height: 100%;
    }

    #summaryCardsContainer {
        overflow: hidden;
        max-height: 1100px;
        opacity: 1;
        transition: max-height .3s ease, opacity .3s ease, margin .3s ease;
    }

    .aparat-summary-hidden #summaryCardsContainer {
        max-height: 0 !important;
        opacity: 0;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        pointer-events: none;
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

    <div class="row align-items-center mb-3">
        <div class="col-lg-5 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-package fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Aparat Takip</h4>
                <p class="text-muted mb-0 font-size-12">Depoya al, ekibe ver, iade al. Kesme, açma ve ekip değişimi otomatik işlenir.</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-lg-7 col-12 d-flex flex-wrap align-items-center justify-content-lg-end gap-2 mt-2 mt-lg-0">
            <?php if ($yetkiDepo): ?>
                <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white btn-depo-hareket" data-tur="depo_giris">
                    <i class="bx bx-plus font-size-16"></i> Depoya Giriş
                </button>
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm btn-depo-hareket" data-tur="depo_cikis">
                    <i class="bx bx-export font-size-16 text-primary"></i> Ekibe Ver
                </button>
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm btn-depo-hareket" data-tur="depo_iade">
                    <i class="bx bx-import font-size-16 text-success"></i> İade Al
                </button>
            <?php endif; ?>
            <?php if ($yetkiGelismis && $yetkiDepo): ?>
                <div class="dropdown d-inline-block">
                    <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-cog font-size-16 text-primary"></i> Diğer
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                        <button type="button" class="dropdown-item d-flex align-items-center btn-depo-hareket" data-tur="acilis">
                            <i class="bx bx-box me-2 font-size-16 text-primary"></i> Açılış Stoğu
                        </button>
                        <button type="button" class="dropdown-item d-flex align-items-center" id="btnManuelIslem">
                            <i class="bx bx-edit me-2 font-size-16 text-primary"></i> Manuel Saha Kaydı
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item d-flex align-items-center btn-depo-hareket" data-tur="hurda">
                            <i class="bx bx-trash me-2 font-size-16 text-warning"></i> Hurdaya Ayır
                        </button>
                        <button type="button" class="dropdown-item d-flex align-items-center btn-depo-hareket" data-tur="kayip">
                            <i class="bx bx-error me-2 font-size-16 text-danger"></i> Kayıp Kaydı
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($yetkiDashboard): ?>
                <a href="index.php?p=aparat-takip/dashboard" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                    <i class="bx bx-pie-chart-alt-2 font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Dashboard</span>
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNasilCalisir" title="Nasıl Çalışır?" aria-label="Nasıl Çalışır?">
                <i class="bx bx-help-circle"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Gizle" aria-label="Özet Kartları Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">DEPODA</span><h3 class="summary-kpi-value my-1" id="ozetDepo">-</h3></div>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-buildings"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-1">
                        <span class="summary-kpi-subtext">Dağıtılmayı bekleyen</span>
                        <span class="summary-kpi-subtext"><i class="bx bx-trash"></i> <b id="ozetHurda">0</b> · <i class="bx bx-error"></i> <b id="ozetKayip">0</b></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">EKİPLERDE</span><h3 class="summary-kpi-value my-1 text-success" id="ozetEkip">-</h3></div>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-group"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-1">
                        <span class="summary-kpi-subtext" id="ozetEkipAlt">Personel zimmetinde</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">ABONEDE TAKILI</span><h3 class="summary-kpi-value my-1 text-info" id="ozetSaha">-</h3></div>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-map-pin"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-1">
                        <span class="summary-kpi-subtext">Kesmede takılan, açmada geri gelir</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">KRİTİK STOK</span><h3 class="summary-kpi-value my-1 text-warning" id="ozetKritik">-</h3></div>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-error"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-1">
                        <span class="summary-kpi-subtext" id="ozetKritikAlt">Depo: 0 · Ekip: 0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="aparatKritikUyari" class="card summary-kpi-card border-warning-subtle mb-3 d-none">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bx bx-error text-warning fs-5"></i>
                <span class="fw-semibold text-dark">Azalan aparatlar</span>
            </div>
            <div class="row g-2" id="aparatKritikListe" style="max-height: 260px; overflow-y: auto;"></div>
        </div>
    </div>

    <?php if ($yetkiGelismis): ?>
        <div id="aparatKirmaBekleyen" class="alert alert-warning d-none align-items-center gap-2" role="alert">
            <i class="bx bx-time-five fs-4"></i>
            <div class="flex-grow-1">
                Puantajdan gelen <b id="kirmaBekleyenSayi">0</b> adet aparat hareketi (online kesme / açma / kırma), aparat tipi belli olmadığı için işlenmeyi bekliyor.
                Tanımlar sekmesinde bir aparat tipini <b>“Tipi bilinmeyen online işlemlerde bu tip kullanılsın”</b> olarak işaretleyin.
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
        </ul>
    <?php endif; ?>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="pane-stok">
            <div class="card summary-kpi-card mb-3" id="aparatListCard">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="bx bx-list-ul fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-size-14 fw-bold">Kimde Kaç Aparat Var?</h5>
                            <p class="text-muted font-size-12 mb-0">Turuncu: azaldı · Kırmızı: bitti/eksi. Alt satırlarda depo ve abonede takılı olanlar.</p>
                        </div>
                    </div>
                    <a class="btn btn-outline-success btn-sm" id="btnStokExcel"
                        href="views/aparat-takip/export-excel.php?tip=stok" title="Excel İndir">
                        <i class="bx bx-download"></i> Excel
                    </a>
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
                                            <?php elseif ((int) $t['ekip_kritik_stok'] > 0): ?>
                                                <small class="d-block fw-normal text-muted">sınır <?= (int) $t['ekip_kritik_stok'] ?></small>
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

            <div class="card summary-kpi-card mb-0">
                <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
                    <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-history fs-5"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 font-size-14 fw-bold">Son Depo Hareketleri</h5>
                        <p class="text-muted font-size-12 mb-0">Depoya giren, ekibe verilen ve iade alınan son 10 kayıt.</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="tabloSonDepo">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Hareket</th>
                                    <th>Aparat</th>
                                    <th class="text-center">Adet</th>
                                    <th>Ekip / Personel</th>
                                    <th>Kaydeden</th>
                                    <th>Açıklama</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="7" class="text-center text-muted py-3">Yükleniyor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ SAHA İŞLEMLERİ ============ -->
        <?php if ($yetkiGelismis): ?>
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
                                    <th data-filter="select">Nereden</th>
                                    <th data-filter="select">Nereye</th>
                                    <th data-filter="select">Aparat</th>
                                    <th class="text-center" data-filter="number">Adet</th>
                                    <th data-filter="string">İşi Yapan</th>
                                    <th data-filter="select">Kaydeden</th>
                                    <th data-filter="string">Açıklama</th>
                                    <th class="text-center">İşlem</th>
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

        <?php endif; ?>
        <!-- ============ TANIMLAR ============ -->
        <?php if ($yetkiTanim): ?>
            <div class="tab-pane fade" id="pane-tanimlar">
                <div class="card summary-kpi-card mb-0">
                    <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="bx bx-cog fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 font-size-14 fw-bold">Aparat Tipleri</h5>
                                <p class="text-muted font-size-12 mb-0">Kritik stok sınırları buradan girilir. Stok hareketi görmüş tipler silinmez, pasife alınır.</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnTipEkle"><i class="bx bx-plus font-size-16"></i> Yeni Tip</button>
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
                                        <th class="text-center" data-filter="number">Depo Kritik</th>
                                        <th class="text-center" data-filter="number">Ekip Kritik</th>
                                        <th data-filter="string">Açıklama</th>
                                        <th data-filter="select">Durum</th>
                                        <th class="text-center" style="width: 90px;">İşlem</th>
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
<div class="modal fade" id="modalNasilCalisir" tabindex="-1" data-modal-icon="bx bx-help-circle" data-modal-subtitle="Aparatın depodan aboneye yolculuğu, adım adım">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-help-circle me-1 text-info"></i> Aparat Takip Nasıl Çalışır?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="apf" id="aparatAkis">
                    <div class="apf-sahne" aria-hidden="true">
                        <svg class="apf-yollar" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <line data-yol="tedarik-depo" x1="9" y1="50" x2="29" y2="50"></line>
                            <line data-yol="depo-ekipA" x1="29" y1="50" x2="58" y2="24"></line>
                            <line data-yol="ekipA-abone" x1="58" y1="24" x2="88" y2="24"></line>
                            <line data-yol="abone-ekipB" x1="88" y1="24" x2="58" y2="78"></line>
                            <line data-yol="ekipB-ekipA" x1="58" y1="78" x2="58" y2="24"></line>
                            <line data-yol="ekipA-depo" x1="58" y1="24" x2="29" y2="50"></line>
                        </svg>

                        <div class="apf-dugum apf-dugum--tedarik" data-dugum="tedarik" style="left:9%;top:50%">
                            <span class="apf-ikon"><i class="bx bx-cart"></i></span>
                            <span class="apf-ad">Satın alma</span>
                        </div>
                        <div class="apf-dugum apf-dugum--depo" data-dugum="depo" style="left:29%;top:50%">
                            <span class="apf-ikon"><i class="bx bx-buildings"></i></span>
                            <span class="apf-ad">Depo</span>
                            <span class="apf-sayi" data-sayi="depo">3</span>
                        </div>
                        <div class="apf-dugum apf-dugum--ekip" data-dugum="ekipA" style="left:58%;top:24%">
                            <span class="apf-ikon"><i class="bx bx-user"></i></span>
                            <span class="apf-ad">Ahmet <small>Ekip 3</small></span>
                            <span class="apf-sayi" data-sayi="ekipA">0</span>
                        </div>
                        <div class="apf-dugum apf-dugum--ekip" data-dugum="ekipB" style="left:58%;top:78%">
                            <span class="apf-ikon"><i class="bx bx-user"></i></span>
                            <span class="apf-ad">Mehmet <small>Ekip 5</small></span>
                            <span class="apf-sayi" data-sayi="ekipB">0</span>
                        </div>
                        <div class="apf-dugum apf-dugum--abone" data-dugum="abone" style="left:88%;top:24%">
                            <span class="apf-ikon"><i class="bx bx-home-alt"></i></span>
                            <span class="apf-ad">Abone <small>sayaçta takılı</small></span>
                            <span class="apf-sayi" data-sayi="abone">0</span>
                        </div>

                        <div class="apf-aparat" id="apfAparat" style="left:9%;top:50%"><i class="bx bx-package"></i></div>
                    </div>

                    <div class="apf-anlatim">
                        <span class="apf-kim" id="apfKim">Siz yaparsınız</span>
                        <h6 class="apf-baslik" id="apfBaslik">Depoya Giriş</h6>
                        <p class="apf-metin" id="apfMetin">Aparat satın alındığında depoya eklenir.</p>
                    </div>

                    <div class="apf-kontrol">
                        <button type="button" class="btn btn-sm btn-outline-secondary bg-white" id="apfGeri" title="Önceki adım" aria-label="Önceki adım"><i class="bx bx-chevron-left"></i></button>
                        <button type="button" class="btn btn-sm btn-primary" id="apfOynat" aria-label="Duraklat"><i class="bx bx-pause"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary bg-white" id="apfIleri" title="Sonraki adım" aria-label="Sonraki adım"><i class="bx bx-chevron-right"></i></button>
                        <div class="apf-adimlar" id="apfAdimlar" role="tablist" aria-label="Adımlar"></div>
                    </div>
                </div>

                <div class="apf-ozet">
                    <div class="apf-ozet__kutu">
                        <span class="apf-ozet__etiket apf-ozet__etiket--siz">Siz yaparsınız</span>
                        <div><b>Depoya Giriş</b> · <b>Ekibe Ver</b> · <b>İade Al</b></div>
                        <small>Sayfanın üstündeki üç buton. Başka bir şey girmenize gerek yok.</small>
                    </div>
                    <div class="apf-ozet__kutu">
                        <span class="apf-ozet__etiket apf-ozet__etiket--sistem">Sistem yapar</span>
                        <div><b>Kesme</b> · <b>Açma</b> · <b>Ekip değişimi</b> · <b>Kritik stok uyarısı</b></div>
                        <small>Personel telefondan kesme/açma girince zimmet kendiliğinden düşer veya geri gelir.</small>
                    </div>
                </div>

                <?php if ($yetkiGelismis): ?>
                <hr>
                <h6 class="fw-bold text-muted">Ayrıntılı işleyiş</h6>
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
                                <li><b>Hasarlı geldi</b> → açan ekibin stoğuna +1; ekip bozuk aparatı depoya iade eder, depo <b>İade Al → Bozuk</b> ile hurdaya ayırır</li>
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
                <p class="text-muted small mb-2">Sayfanın üstündeki <b>Depoya Giriş / Ekibe Ver / İade Al</b> butonlarından, hurda/kayıp/açılış ise <b>Diğer</b> menüsünden yapılır.</p>
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

                <?php endif; ?>
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
                    <h5 class="modal-title" id="havuzModalBaslik">Depoya Giriş</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <input type="hidden" id="havuz_tur" value="depo_giris">
                        <div class="col-12"><p class="text-muted small mb-0" id="havuzModalAciklama"></p></div>
                        <div class="col-12" id="havuzEkipAlan"><?= Form::FormSelect2('havuz_ekip', $ekipSecimOptions, '', 'Ekip', 'bx bx-group') ?></div>
                        <div class="col-7"><?= Form::FormSelect2('havuz_aparat', $tipSecimOptions, '', 'Aparat Tipi', 'bx bx-package', 'key', '', 'form-select select2', true) ?></div>
                        <div class="col-5"><?= Form::FormFloatInput('number', 'havuz_adet', '1', '1', 'Adet', 'bx bx-hash', 'form-control', true) ?></div>
                        <div class="col-12 d-none" id="havuzBakiyeBilgi"><span class="badge bg-light text-dark border px-2 py-1 font-size-12" id="havuzBakiyeMetin"></span></div>
                        <div class="col-12 d-none" id="havuzBozukAlan">
                            <div class="form-check form-switch p-2 ps-5 border rounded-3 bg-danger-subtle border-danger-subtle mb-0">
                                <input class="form-check-input" type="checkbox" id="havuz_bozuk">
                                <label class="form-check-label fw-semibold text-danger" for="havuz_bozuk">Aparat bozuk / hasarlı — hurdaya ayır</label>
                                <div class="small text-muted">İşaretlenirse aparat depoya girmez, ekibin zimmetinden düşüp hurdaya geçer.</div>
                            </div>
                        </div>
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
<?php if ($yetkiGelismis): ?>
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

<?php endif; ?>
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
                            <div class="col-6"><?= Form::FormFloatInput('number', 'tip_depo_kritik', '0', '0', 'Depo kritik stok', 'bx bx-buildings') ?></div>
                            <div class="col-6"><?= Form::FormFloatInput('number', 'tip_ekip_kritik', '0', '0', 'Ekip kritik stok', 'bx bx-group') ?></div>
                            <div class="col-12"><p class="text-muted small mb-0">Stok bu sayıya düştüğünde ana sayfada ve personelin telefonunda uyarı çıkar. 0 = uyarı yok.</p></div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tip_varsayilan_kirma">
                                    <label class="form-check-label" for="tip_varsayilan_kirma">Tipi bilinmeyen online işlemlerde bu tip kullanılsın</label>
                                    <div class="small text-muted">Puantajdan gelen online kesme, açma ve aparat kırma kayıtları bu tipten işlenir. Yalnız bir tip işaretlenebilir.</div>
                                </div>
                            </div>
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
        transfer: <?= $yetkiTransfer ? 'true' : 'false' ?>,
        gelismis: <?= $yetkiGelismis ? 'true' : 'false' ?>
    };
    window.aparatTipleri = <?= json_encode(array_map(fn($t) => [
        'id' => (int) $t['id'],
        'ad' => $t['ad'],
        'kod' => $t['kod'],
        'renk' => $t['renk'],
        'aktif' => (int) $t['is_active'],
        'depo_kritik' => (int) $t['depo_kritik_stok'],
        'ekip_kritik' => (int) $t['ekip_kritik_stok'],
    ], $tipler), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="views/aparat-takip/js/list.js?v=<?= filemtime(__DIR__ . '/js/list.js') ?>"></script>
