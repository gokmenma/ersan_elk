<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Model\KacakSicilEksikModel;
use App\Model\PersonelModel;
use App\Service\Gate;

$Kacak = new KacakKontrolModel();
$Personel = new PersonelModel();

$bugun = date('Y-m-d');
$haftaBasi = date('Y-m-d', strtotime('monday this week'));
$son30Gun = date('Y-m-d', strtotime('-29 days'));

$personelOptions = $Personel->optionList('puantaj', $bugun);

$ilceler = KacakKontrolModel::ILCELER;
$bekleyenSayisi = $Kacak->getPendingCount();
$bildirimKacakId = 0;
if (($_GET['tab'] ?? '') === 'onay' && !empty($_GET['kacak_token'])) {
    $bildirimKacakId = max(0, (int) Security::decrypt((string) $_GET['kacak_token']));
}

$ilceOptions = ['' => 'Tüm İlçeler'];
foreach ($ilceler as $ilce) {
    $ilceOptions[$ilce] = $ilce;
}

$turOptions = ['' => 'Tüm Türler'];
foreach (KacakKontrolModel::TURLER as $tur) {
    $turOptions[$tur] = $tur;
}

// Modal içindeki satır şablonu için (boş seçenek metinleri farklı)
$ilceSatirOptions = ['' => 'İlçe Seçiniz'];
foreach ($ilceler as $ilce) {
    $ilceSatirOptions[$ilce] = $ilce;
}

$turSatirOptions = [];
foreach (KacakKontrolModel::TURLER as $tur) {
    $turSatirOptions[$tur] = $tur;
}

$yilOptions = [];
for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--) {
    $yilOptions[$y] = $y;
}

$ayAdlari = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
    7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
];

$yetkiDuzenle = Gate::allows('kacak_duzenle') || Gate::isSuperAdmin();
$yetkiOnay = Gate::allows('kacak_onay') || Gate::isSuperAdmin();
$yetkiIptal = Gate::allows('kacak_iptal') || Gate::isSuperAdmin();
$yetkiIptalEkle = Gate::allows('kacak_iptal_ekle') || Gate::isSuperAdmin();
$yetkiArsiv = Gate::allows('kacak_arsiv') || Gate::isSuperAdmin();

$yetkiSicilBildir = Gate::allows('kacak_sicil_bildir') || Gate::isSuperAdmin();
$yetkiSicilYanitla = Gate::allows('kacak_sicil_yanitla') || Gate::isSuperAdmin();
$yetkiSicil = $yetkiSicilBildir || $yetkiSicilYanitla;

$sicilNedenOptions = KacakSicilEksikModel::NEDENLER;
$sicilNedenFiltreOptions = ['' => 'Tüm Nedenler'] + KacakSicilEksikModel::NEDENLER;
?>

<link rel="stylesheet" href="assets/libs/glightbox/css/glightbox.min.css">

<style>
    .kacak-ozet-serit {
        border-radius: 14px;
    }

    .kacak-ozet-ikon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .kacak-ozet-etiket {
        font-size: .68rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #94a3b8;
        white-space: nowrap;
    }

    .kacak-ozet-sayi {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .kacak-ozet-alt {
        font-size: .68rem;
        color: #94a3b8;
        white-space: nowrap;
    }

    .kacak-ozet-cip {
        font-size: .72rem;
        font-weight: 600;
        padding: .22rem .6rem;
        border-radius: 100px;
        border: 1px solid;
        white-space: nowrap;
    }

    .kacak-ozet-cip b {
        font-weight: 700;
        margin-left: .15rem;
    }

    /* Ayraçlar sadece tek satıra sığdığı geniş ekranlarda görünür */
    @media (min-width: 992px) {
        .kacak-ozet-ayrac {
            border-left: 1px solid var(--bs-border-color);
        }
    }

    .kacak-tabs .nav-link {
        border: none !important;
        border-radius: 100px !important;
        font-size: .78rem;
        font-weight: 600;
        padding: 8px 18px;
        color: #64748b;
    }

    .kacak-tabs .nav-link.active {
        background: #556ee6 !important;
        color: #fff !important;
        box-shadow: 0 4px 10px -2px rgba(85, 110, 230, .5);
    }

    .kacak-dashboard { --kd-kacak:#f1b44c; --kd-abonesiz:#f46a6a; --kd-usulsuz:#50a5f1; }
    .kacak-dashboard .kd-filter { background:var(--bs-tertiary-bg); border:1px solid var(--bs-border-color); border-radius:12px; padding:12px; }
    .kd-periods { display:flex; gap:6px; flex-wrap:wrap; }
    .kd-period { border:1px solid var(--bs-border-color); background:var(--bs-body-bg); color:var(--bs-secondary-color); border-radius:100px; padding:.38rem .8rem; font-size:.76rem; }
    .kd-period.active { background:var(--bs-primary); border-color:var(--bs-primary); color:#fff; }
    .kd-card { border:1px solid var(--bs-border-color); border-radius:12px; background:var(--bs-body-bg); height:100%; }
    .kd-kpi { padding:1rem 1.1rem; }
    .kd-label { color:var(--bs-secondary-color); font-size:.69rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .kd-value { font-size:2rem; line-height:1.15; font-weight:700; font-variant-numeric:tabular-nums; }
    .kd-segbar,.kd-waitbar { display:flex; overflow:hidden; background:var(--bs-tertiary-bg); border-radius:4px; }
    .kd-segbar { height:5px; margin-top:.65rem; } .kd-waitbar { height:4px; margin-top:.55rem; }
    .kd-segbar i,.kd-waitbar i { display:block; height:100%; }
    .kd-legend { display:flex; gap:.9rem; flex-wrap:wrap; margin-top:.5rem; font-size:.72rem; color:var(--bs-secondary-color); }
    .kd-dot { display:inline-block; width:7px; height:7px; border-radius:2px; margin-right:4px; }
    .kd-quiet { border-top:1px solid var(--bs-border-color); margin-top:.85rem; padding-top:.75rem; }
    .kd-actions { padding:.4rem .55rem .55rem; }
    .kd-action { display:flex; align-items:center; gap:1rem; padding:.7rem .75rem; color:inherit; border-radius:8px; cursor:pointer; }
    .kd-action + .kd-action { border-top:1px solid var(--bs-border-color); }
    .kd-action:hover { background:var(--bs-tertiary-bg); }
    .kd-action-num { width:46px; text-align:center; font-size:1.3rem; font-weight:700; font-variant-numeric:tabular-nums; }
    .kd-action-text { flex:1; } .kd-action-text small { display:block; color:var(--bs-secondary-color); }
    .kd-panel-head { display:flex; justify-content:space-between; gap:1rem; padding:1rem 1rem 0; }
    .kd-panel-body { padding:.5rem 1rem 1rem; }
    .kd-stack { display:flex; height:8px; overflow:hidden; border-radius:3px; min-width:90px; }
    .kd-verim { min-width:110px; } .kd-verim .progress { height:6px; }
    .kacak-dashboard-degisim { font-size:.7rem; font-weight:700; }
    @media (max-width:767.98px) { .kd-action-go,.kd-stack-col { display:none; } }

    .kacak-rapor-metin {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .85rem;
        white-space: pre-wrap;
        line-height: 1.7;
        margin: 0;
    }

    .kacak-foto-thumb {
        width: 92px;
        height: 92px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid var(--bs-border-color);
    }

    .kacak-foto-item {
        position: relative;
    }

    .kacak-foto-item .btn-foto-sil {
        position: absolute;
        top: -6px;
        right: -6px;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        padding: 0;
        line-height: 1;
    }

    .kacak-foto-item .btn-foto-indir {
        position: absolute;
        top: -6px;
        left: -6px;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        padding: 0;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
    }

    .kacak-upload-btn-card {
        width: 92px;
        min-height: 120px;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
        cursor: pointer;
        padding: 8px 6px;
        transition: all 0.2s ease;
        user-select: none;
    }
    [data-bs-theme="dark"] .kacak-upload-btn-card {
        background: #1e293b;
        border-color: #475569;
    }
    .kacak-upload-btn-card:hover {
        border-color: #3b82f6;
        background: #eff6ff;
        transform: translateY(-2px);
    }
    [data-bs-theme="dark"] .kacak-upload-btn-card:hover {
        background: #1e3a8a33;
        border-color: #60a5fa;
    }
    .kacak-upload-btn-card.video-card:hover {
        border-color: #ef4444;
        background: #fef2f2;
    }
    [data-bs-theme="dark"] .kacak-upload-btn-card.video-card:hover {
        background: #7f1d1d33;
        border-color: #f87171;
    }
    .kacak-upload-btn-card.tutanak-card:hover {
        border-color: #f59e0b;
        background: #fffbeb;
    }
    [data-bs-theme="dark"] .kacak-upload-btn-card.tutanak-card:hover {
        background: #78350f33;
        border-color: #fbbf24;
    }

    /* Çekim ile yükleme arasında uzun boşluk olan fotoğrafları öne çıkarır. */
    @keyframes kacakGecikmeNabzi {
        0%   { box-shadow: 0 0 0 0 rgba(255, 193, 7, .85); }
        70%  { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
    }

    .kacak-gecikme-pulse {
        animation: kacakGecikmeNabzi 1.6s infinite;
        border: 2px solid var(--bs-warning) !important;
        position: relative;
        z-index: 1;
    }

    .btn-foto.kacak-gecikme-pulse {
        color: var(--bs-warning) !important;
    }

    .kacak-foto-thumb.kacak-gecikme-pulse {
        border-width: 3px !important;
    }

    @media (prefers-reduced-motion: reduce) {
        .kacak-gecikme-pulse {
            animation: none;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, .45);
        }
    }

    .ai-field-uncertain {
        background-color: #fff8e1 !important;
        border-color: #ffc107 !important;
    }

    .ai-field-confident {
        background-color: #f1fbf5 !important;
        border-color: #34c38f !important;
    }

    .ai-conf-badge {
        font-size: .62rem;
        font-weight: 700;
        padding: .12em .4em;
    }

    [data-bs-theme="dark"] .ai-field-uncertain {
        background-color: rgba(255, 193, 7, .12) !important;
    }

    [data-bs-theme="dark"] .ai-field-confident {
        background-color: rgba(52, 195, 143, .12) !important;
    }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "İş Takip";
    $title = "Kaçak İşlemleri";
    include 'layouts/breadcrumb.php';
    ?>

    <!-- Özet şeridi: tek satır, üç grup -->
    <div class="card border-0 shadow-sm mb-3 kacak-ozet-serit">
        <div class="card-body py-3">
            <div class="row g-0 align-items-center">

                <!-- Aktif tutanaklar + tür kırılımı -->
                <div class="col-12 col-lg-6 d-flex align-items-center gap-3 px-3">
                    <span class="kacak-ozet-ikon bg-primary-subtle text-primary"><i class="bx bx-list-ul"></i></span>
                    <div>
                        <div class="kacak-ozet-etiket">Aktif Tutanak</div>
                        <div class="kacak-ozet-sayi" id="ozetAktif">0</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 ms-2">
                        <span class="kacak-ozet-cip text-danger border-danger-subtle">
                            Kaçak <b id="ozetKacak">0</b>
                        </span>
                        <span class="kacak-ozet-cip text-warning border-warning-subtle">
                            Abonesiz <b id="ozetAbonesiz">0</b>
                        </span>
                        <span class="kacak-ozet-cip text-info border-info-subtle">
                            Usülsüz <b id="ozetUsulsuz">0</b>
                        </span>
                    </div>
                </div>

                <!-- İptaller -->
                <div class="col-6 col-lg-3 d-flex align-items-center gap-3 px-3 kacak-ozet-ayrac">
                    <span class="kacak-ozet-ikon bg-secondary-subtle text-secondary"><i class="bx bx-x-circle"></i></span>
                    <div>
                        <div class="kacak-ozet-etiket">İptal</div>
                        <div class="kacak-ozet-sayi" id="ozetIptal">0</div>
                        <div class="kacak-ozet-alt">
                            hakedişten düşen: <b id="ozetIptalDusulen">0</b>
                        </div>
                    </div>
                </div>

                <!-- Bekleyen bildirimler (tıklayınca onay sekmesine gider) -->
                <div class="col-6 col-lg-3 d-flex align-items-center gap-3 px-3 kacak-ozet-ayrac">
                    <span class="kacak-ozet-ikon bg-info-subtle text-info"><i class="bx bx-time-five"></i></span>
                    <div>
                        <div class="kacak-ozet-etiket">Bekleyen Bildirim</div>
                        <div class="kacak-ozet-sayi" id="ozetBekleyen"><?= (int) $bekleyenSayisi ?></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-soft-info ms-auto" id="btnOnaylaraGit"
                        title="Bekleyen onaylara git">
                        <i class="bx bx-right-arrow-alt"></i>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Sekmeler -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2">
            <ul class="nav nav-pills kacak-tabs" id="kacakTabs" role="tablist">
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-ekip-ozet"
                        type="button"><i class="bx bx-table me-1"></i> Ekip Özeti</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-dashboard"
                        type="button"><i class="bx bx-grid-alt me-1"></i> Dashboard</button></li>
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-kayitlar"
                        type="button"><i class="bx bx-list-ul me-1"></i> Kayıtlar</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-onaylar"
                        type="button"><i class="bx bx-check-shield me-1"></i> Bekleyen Onaylar
                        <span class="badge bg-danger ms-1" id="bekleyenBadge"
                            <?= $bekleyenSayisi > 0 ? '' : 'style="display:none"' ?>><?= (int) $bekleyenSayisi ?></span>
                    </button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-iptaller"
                        type="button"><i class="bx bx-x-circle me-1"></i> İptaller</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-gunluk"
                        type="button"><i class="bx bx-clipboard me-1"></i> Günlük Rapor</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-haftalik"
                        type="button"><i class="bx bx-bar-chart-alt-2 me-1"></i> Haftalık Rapor</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-teslim"
                        type="button"><i class="bx bx-printer me-1"></i> Teslim Alma Listesi</button></li>
                <?php if ($yetkiSicil): ?>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-sicil"
                            type="button" id="tabSicil"><i class="bx bx-user-x me-1"></i> Sicil Oluşmayanlar
                            <span class="badge bg-danger ms-1" id="sicilBadge" style="display:none">0</span>
                        </button></li>
                <?php endif; ?>
                <?php if ($yetkiArsiv): ?>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-arsiv"
                            type="button"><i class="bx bx-archive me-1"></i> Fotoğraf Arşivi</button></li>
                <?php endif; ?>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-bildirim-personelleri"
                        type="button" id="tabBildirimPersonelleri"><i class="bx bx-user-check me-1"></i> Bildirim Personelleri</button></li>
            </ul>
        </div>
    </div>

    <div class="tab-content">

        <!-- ============ DASHBOARD ============ -->
        <div class="tab-pane fade" id="pane-dashboard">
            <div class="kacak-dashboard">
                <div class="kd-filter mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-xl-auto"><div class="kd-periods">
                            <button type="button" class="kd-period" data-days="0">Bugün</button><button type="button" class="kd-period" data-days="week">Bu hafta</button>
                            <button type="button" class="kd-period" data-days="month">Bu ay</button><button type="button" class="kd-period active" data-days="29">Son 30 gün</button>
                            <button type="button" class="kd-period" data-days="previous-month">Geçen ay</button>
                        </div></div>
                        <div class="col-md-3 col-xl-2"><?= Form::FormDate('dashboard_baslangic', Date::dmY($son30Gun), 'Başlangıç') ?></div>
                        <div class="col-md-3 col-xl-2"><?= Form::FormDate('dashboard_bitis', Date::dmY($bugun), 'Bitiş') ?></div>
                        <div class="col-md-auto ms-xl-auto"><button type="button" class="btn btn-primary px-4" id="btnDashboardFiltrele"><i class="bx bx-refresh me-1"></i>Güncelle</button></div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-xl-8"><div class="kd-card kd-kpi">
                        <div class="kd-label">Aktif tutanak <span class="fw-normal text-lowercase">· onayı tamamlanmış</span></div><div class="kd-value" id="dashboardAktif">0</div>
                        <div class="kd-segbar"><i id="dashboardSegAbonesiz" style="background:var(--kd-abonesiz)"></i><i id="dashboardSegKacak" style="background:var(--kd-kacak)"></i><i id="dashboardSegUsulsuz" style="background:var(--kd-usulsuz)"></i></div>
                        <div class="kd-legend"><span><i class="kd-dot" style="background:var(--kd-abonesiz)"></i>Abonesiz <b id="dashboardAbonesiz">0</b></span><span><i class="kd-dot" style="background:var(--kd-kacak)"></i>Kaçak <b id="dashboardKacak">0</b></span><span><i class="kd-dot" style="background:var(--kd-usulsuz)"></i>Usülsüz <b id="dashboardUsulsuz">0</b></span></div>
                        <div class="kd-quiet"><div class="d-flex justify-content-between gap-2 small text-muted"><span>Ayrıca <b id="dashboardBekleyen">0</b> tutanak onay bekliyor · onaylanınca <b id="dashboardPotansiyel">0</b></span><a href="#" id="dashboardOnayaGit">Listeye git →</a></div>
                            <div class="kd-waitbar"><i id="wait02" class="bg-secondary"></i><i id="wait37" style="background:#6b7280"></i><i id="wait814" class="bg-warning"></i><i id="wait15" class="bg-danger"></i></div>
                            <div class="kd-legend"><span>0–2 gün <b id="wait02n">0</b></span><span>3–7 gün <b id="wait37n">0</b></span><span>8–14 gün <b id="wait814n">0</b></span><span>15 gün + <b id="wait15n">0</b></span></div>
                        </div>
                    </div>
                    </div>
                    <div class="col-xl-4"><div class="kd-card kd-kpi"><div class="kd-label">Günlük üretim</div><div class="kd-value" id="dashboardGunlukOrtalama">0</div><div class="text-muted small"><span id="dashboardAktifGun">0</span> çalışılan günün ortalaması</div><div class="text-muted small mt-1"><span id="dashboardEkipSayisi">0</span> ekip · ekip başı <b id="dashboardEkipOrtalama">0</b> tutanak/gün</div></div></div>
                </div>
                <div class="kd-card mb-3"><div class="kd-panel-head"><h6 class="mb-0">Bekleyen işler</h6><small class="text-muted">Satıra tıklayınca ilgili sekme açılır</small></div><div class="kd-actions" id="dashboardAksiyonlar"></div></div>
                <div class="row g-3 mb-3">
                    <div class="col-xl-7"><div class="kd-card"><div class="kd-panel-head"><h6 class="mb-0">Günlük Tutanak</h6><small class="text-muted">Onaylanmış aktif tutanaklar</small></div><div class="kd-panel-body"><div id="kacakTrendChart"></div></div></div></div>
                    <div class="col-xl-5"><div class="kd-card"><div class="kd-panel-head"><h6 class="mb-0">İlçe × tür dağılımı</h6><small class="text-muted">Aktif + onay bekleyen</small></div><div class="kd-panel-body table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>İlçe</th><th class="kd-stack-col">Dağılım</th><th class="text-end">Kaçak</th><th class="text-end">Abonesiz</th><th class="text-end">Usülsüz</th><th class="text-end">Aktif</th><th class="text-end">+ Bekleyen</th></tr></thead><tbody id="dashboardIlceTablo"></tbody></table></div></div></div>
                </div>
                <div class="kd-card"><div class="kd-panel-head"><h6 class="mb-0">Ekip performansı</h6><small class="text-muted">Günlük verime göre karşılaştırma</small></div><div class="kd-panel-body table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Ekip</th><th class="text-end">Aktif</th><th class="text-end">Çalışılan gün</th><th>Günlük verim</th><th class="text-end">+ Bekleyen</th><th class="text-end">Son işlem</th></tr></thead><tbody id="dashboardEkipTablo"></tbody></table></div></div>
            </div>
        </div>

        <!-- ============ KAYITLAR ============ -->
        <div class="tab-pane fade show active" id="pane-kayitlar">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-2">
                            <?= Form::FormDate('filtre_baslangic', Date::dmY($son30Gun), 'Başlangıç Tarihi') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormDate('filtre_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormSelect2('filtre_ilce', $ilceOptions, '', 'İlçe', 'map-pin') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormSelect2('filtre_tur', $turOptions, '', 'Tür', 'tag') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormFloatInput('text', 'filtre_arama', '', 'Tutanak no, abone, sayaç...', 'Ara', 'search') ?>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1" id="btnFiltrele"><i
                                    class="bx bx-search me-1"></i>Filtrele</button>
                            <button class="btn btn-outline-success" id="btnKayitlarExcel" title="Excel İndir"><i
                                    class="bx bx-download"></i></button>
                            <?php if ($yetkiDuzenle): ?>
                                <button class="btn btn-outline-primary" id="btnKacakExcelYukle" title="Excel'den Yükle"
                                        data-bs-toggle="modal" data-bs-target="#kacakExcelModal"><i
                                        class="bx bx-upload"></i></button>
                                <button class="btn btn-success" id="btnYeniKacak" title="Yeni Kayıt"><i
                                        class="bx bx-plus"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="kacakTable">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Tutanak No</th>
                                    <th data-filter="string">Abone Adı</th>
                                    <th data-filter="select">İlçe</th>
                                    <th data-filter="select">Tür</th>
                                    <th data-filter="string">Sayaç No</th>
                                    <th class="text-center" data-filter="number">Sayı</th>
                                    <th data-filter="select">Ekip</th>
                                    <th data-filter="select">Kaynak</th>
                                    <th class="text-center" data-filter="none">Foto</th>
                                    <th class="text-center" data-filter="select">Durum</th>
                                    <th class="text-center" data-filter="none">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ BEKLEYEN ONAYLAR ============ -->
        <div class="tab-pane fade" id="pane-onaylar">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($bildirimKacakId > 0): ?>
                    <div class="alert alert-info d-flex align-items-center justify-content-between gap-3">
                        <span><i class="bx bx-filter-alt me-1"></i>Bildirimdeki kaçak kaydı gösteriliyor.</span>
                        <a href="index.php?p=kacak/list&amp;tab=onay"
                            class="btn btn-sm btn-outline-info flex-shrink-0">
                            <i class="bx bx-list-ul me-1"></i>Tüm Bekleyen Onayları Göster
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bx bx-info-circle fs-4 me-2"></i>
                        <div>Personel mobil uygulamasından gelen kaçak bildirimleri burada listelenir. Onaylanmayan
                            kayıtlar hakediş ve prim hesabına dahil edilmez.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="onayTable">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Bildiren</th>
                                    <th data-filter="select">Ekip</th>
                                    <th data-filter="string">Tutanak No</th>
                                    <th data-filter="string">Abone Adı</th>
                                    <th data-filter="select">İlçe</th>
                                    <th data-filter="select">Tür</th>
                                    <th class="text-center" data-filter="none">Foto</th>
                                    <th class="text-center" data-filter="none">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ İPTALLER ============ -->
        <div class="tab-pane fade" id="pane-iptaller">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-2"><?= Form::FormDate('iptal_baslangic', Date::dmY($son30Gun), 'Başlangıç') ?>
                        </div>
                        <div class="col-md-2"><?= Form::FormDate('iptal_bitis', Date::dmY($bugun), 'Bitiş') ?></div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" id="btnIptalFiltrele"><i
                                    class="bx bx-search me-1"></i>Filtrele</button>
                        </div>
                        <?php if ($yetkiIptalEkle): ?>
                            <div class="col-md-2 ms-auto">
                                <button type="button" class="btn btn-danger w-100" id="btnYeniIptal"><i class="bx bx-plus me-1"></i>Yeni İptal</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="iptalTable">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Tutanak No</th>
                                    <th data-filter="string">Abone Adı</th>
                                    <th data-filter="select">İlçe</th>
                                    <th data-filter="select">Tür</th>
                                    <th class="text-center" data-filter="number">Sayı</th>
                                    <th data-filter="string">İptal Açıklaması</th>
                                    <th class="text-center" data-filter="select">Hakedişten Düş</th>
                                    <th data-filter="string">İptal Eden</th>
                                    <th class="text-center" data-filter="none">Foto</th>
                                    <th class="text-center" data-filter="none">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ EKİP ÖZETİ (Ekip x Gün pivot) ============ -->
        <div class="tab-pane fade" id="pane-ekip-ozet">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-table me-1 text-primary"></i> Ekip Bazlı Aylık Özet</h5>
                        <p class="text-muted small mb-0 mt-1">Seçilen ay için ekip × gün kırılımında tutanak sayıları. Gün kutucuğuna <strong>çift tıklayarak</strong> o tarih ve ekip için hızlıca yeni kayıt oluşturabilirsiniz.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        <div style="min-width: 150px; max-width: 175px;">
                            <?= Form::FormDate('ozet_donem_picker', date('Y-m'), 'Dönem', 'calendar', 'form-control flatpickr-month') ?>
                        </div>
                        <button type="button" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1" id="btnEkipOzetBolgeTopl">
                            <i class="bx bx-list-check me-1"></i> Bölge Topl. Göster
                        </button>
                        <?php if (\App\Service\Gate::allows('is_takip_ayarlar') || \App\Service\Gate::isSuperAdmin()): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-tab-settings d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; padding: 0;" data-tab="kacakkontrol" data-tab-name="Kaçak Kontrol" title="Kaçak Kontrol Ayarları">
                                <i class="bx bx-cog fs-5"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-success btn-sm d-flex align-items-center justify-content-center" id="btnEkipOzetExcel" style="width: 34px; height: 34px; padding: 0;" title="Excel İndir">
                            <i class="bx bx-download fs-5"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div id="ekipOzetIcerik">
                        <div class="text-center text-muted p-5">Raporu görmek için dönem seçin.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ GÜNLÜK RAPOR ============ -->
        <div class="tab-pane fade" id="pane-gunluk">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1"><i class="bx bx-clipboard me-1"></i> Günlük Rapor</h5>
                    <p class="text-muted small">Seçtiğiniz güne ait Kaçak, Sayaçlı Abonesiz ve Usülsüz tutanak sayılarını ilçe
                        bazında, panoya kopyalanabilir metin olarak üretir.</p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3"><?= Form::FormDate('gunluk_tarih', Date::dmY($bugun), 'Rapor Tarihi') ?>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="btnGunlukRapor"><i
                                    class="bx bx-refresh me-1"></i>Raporu Oluştur</button>
                        </div>
                    </div>

                    <div class="card bg-light border">
                        <div class="card-body position-relative">
                            <button class="btn btn-sm btn-soft-primary position-absolute top-0 end-0 m-2"
                                id="btnGunlukKopyala" title="Panoya kopyala"><i class="bx bx-copy"></i></button>
                            <pre class="kacak-rapor-metin" id="gunlukRaporMetin">Raporu oluşturmak için tarih seçip "Raporu Oluştur" butonuna basın.</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ HAFTALIK RAPOR ============ -->
        <div class="tab-pane fade" id="pane-haftalik">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1"><i class="bx bx-bar-chart-alt-2 me-1"></i> Haftalık Rapor</h5>
                    <p class="text-muted small">Seçtiğiniz tarih aralığı için "Bölge (İlçe) Bazlı Abonesiz / Kaçak / Usülsüz
                        Özeti" tablosunu görüntüler ve Excel olarak indirir.</p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <?= Form::FormDate('haftalik_baslangic', Date::dmY($haftaBasi), 'Başlangıç Tarihi') ?></div>
                        <div class="col-md-3"><?= Form::FormDate('haftalik_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="btnHaftalikRapor"><i
                                    class="bx bx-refresh me-1"></i>Raporu Getir</button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success w-100" id="btnHaftalikExcel"><i
                                    class="bx bx-download me-1"></i>Excel İndir</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle w-100" id="haftalikTable">
                            <thead class="table-light">
                                <tr>
                                    <th>İLÇE</th>
                                    <th class="text-end">ABONESİZ</th>
                                    <th class="text-end">KAÇAK</th>
                                    <th class="text-end">USÜLSÜZ</th>
                                    <th class="text-end">TOPLAM</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>GENEL TOPLAM</td>
                                    <td class="text-end" id="haftalikToplamAbonesiz">0</td>
                                    <td class="text-end" id="haftalikToplamKacak">0</td>
                                    <td class="text-end" id="haftalikToplamUsulsuz">0</td>
                                    <td class="text-end" id="haftalikToplamGenel">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TESLİM ALMA LİSTESİ ============ -->
        <div class="tab-pane fade" id="pane-teslim">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <h5 class="mb-1"><i class="bx bx-printer me-1 text-primary"></i> Haftalık Teslim Alma Listesi</h5>
                            <p class="small text-muted mb-0"><strong>Fiziki teslim alma:</strong> Onikişubat/Dulkadiroğlu'nda tüm tutanaklar + diğer ilçelerdeki Kaçak ve Usülsüz evraklar. <strong>Foto çıktısı:</strong> Yalnızca Onikişubat/Dulkadiroğlu'ndaki Kaçak kayıtlar.</p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 bg-light rounded-3 border mb-3">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div style="width: 140px;">
                                <?= Form::FormDate('teslim_baslangic', Date::dmY($haftaBasi), 'Başlangıç') ?>
                            </div>
                            <div style="width: 140px;">
                                <?= Form::FormDate('teslim_bitis', Date::dmY($bugun), 'Bitiş') ?>
                            </div>
                            <button class="btn btn-primary btn-sm px-3" id="btnTeslimListesi" style="height: 48px;" title="Listeyi Getir">
                                <i class="bx bx-refresh me-1"></i>Listele
                            </button>
                            <div class="vr mx-1 d-none d-md-block" style="height: 32px; opacity: 0.2;"></div>
                            <span class="badge bg-white text-secondary border py-2 px-2.5" id="teslimSecimBilgi">0 kayıt seçildi</span>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm px-3 dropdown-toggle fw-medium" type="button" id="btnTeslimIslemler" data-bs-toggle="dropdown" aria-expanded="false" disabled style="height: 48px;">
                                <i class="bx bx-cog me-1"></i>İşlemler
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="btnTeslimIslemler">
                                <li>
                                    <a class="dropdown-item py-2" href="javascript:void(0)" id="btnTeslimAlindi">
                                        <i class="bx bx-check-double text-info me-2 fs-5 align-middle"></i>Teslim Alındı İşaretle
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item py-2" href="javascript:void(0)" id="btnTeslimFotoPdf">
                                        <i class="bx bxs-file-pdf text-danger me-2 fs-5 align-middle"></i>Fotoğraf Çıktısı (PDF)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="javascript:void(0)" id="btnTeslimZip">
                                        <i class="bx bxs-file-archive text-warning me-2 fs-5 align-middle"></i>Toplu Evrak İndir (ZIP)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="javascript:void(0)" id="btnTeslimExcel">
                                        <i class="bx bx-download text-success me-2 fs-5 align-middle"></i>Excel İndir
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="teslimTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" data-filter="none"><input type="checkbox" class="form-check-input" id="teslimTumunuSec" title="Tümünü seç"></th>
                                    <th data-filter="date">TARİH</th>
                                    <th data-filter="string">TUTANAK NO</th>
                                    <th data-filter="string">MÜKELLEF ADI</th>
                                    <th data-filter="select">İLÇE</th>
                                    <th data-filter="select">TÜR</th>
                                    <th data-filter="select">EKİP</th>
                                    <th data-filter="string">SEBEP</th>
                                    <th class="text-center" data-filter="none">FOTO ÇIKTISI</th>
                                    <th data-filter="select">TESLİM DURUMU</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($yetkiSicil): ?>
            <!-- ============ SİCİL OLUŞMAYANLAR ============ -->
            <div class="tab-pane fade" id="pane-sicil">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start">
                            <i class="bx bx-info-circle fs-4 me-2"></i>
                            <div>Kurumun ceza işlemi için sicil oluşturamadığı tutanaklar burada takip edilir. Bildirim
                                açıldığında tutanağı tutan ekibe mobil bildirim düşer; ekip aboneye ulaşıp doğru bilgiyi
                                girdiğinde kayıt <strong>Yanıtlandı</strong> sekmesine geçer ve bildirimi açan kullanıcıya
                                haber verilir. Bu akış tutanağın hakediş durumunu <strong>etkilemez</strong>.
                                <div class="small mt-2"><i class="bx bx-mouse me-1"></i>Satıra <strong>sağ
                                        tıklayarak</strong> da işlem yapabilirsiniz.</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <ul class="nav nav-pills kacak-tabs" id="sicilAltTabs" role="tablist">
                                <li class="nav-item"><button class="nav-link active" type="button"
                                        data-sicil-durum="beklemede"><i class="bx bx-time-five me-1"></i> Bekleyen
                                        <span class="badge bg-danger ms-1" id="sicilSayiBeklemede">0</span></button></li>
                                <li class="nav-item"><button class="nav-link" type="button"
                                        data-sicil-durum="yanitlandi"><i class="bx bx-check-double me-1"></i> Yanıtlandı
                                        <span class="badge bg-success ms-1" id="sicilSayiYanitlandi">0</span></button></li>
                                <li class="nav-item"><button class="nav-link" type="button" data-sicil-durum="arsiv"><i
                                            class="bx bx-archive me-1"></i> Çözüldü / Arşiv</button></li>
                            </ul>
                            <?php if ($yetkiSicilBildir): ?>
                                <button class="btn btn-success" id="btnYeniSicilEksik" data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Tutanak numarasını arayarak yeni sicil oluşmadı bildirimi aç"><i
                                        class="bx bx-plus me-1"></i> Yeni Bildirim</button>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-2">
                                <?= Form::FormDate('sicil_baslangic', '', 'Başlangıç Tarihi') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormDate('sicil_bitis', '', 'Bitiş Tarihi') ?>
                            </div>
                            <div class="col-md-3">
                                <?= Form::FormSelect2('sicil_filtre_neden', $sicilNedenFiltreOptions, '', 'Neden', 'alert-circle') ?>
                            </div>
                            <div class="col-md-3">
                                <?= Form::FormFloatInput('text', 'sicil_arama', '', 'Tutanak no, abone, ekip...', 'Ara', 'search') ?>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" id="btnSicilFiltrele"><i
                                        class="bx bx-search me-1"></i>Filtrele</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle nowrap w-100" id="sicilTable">
                                <thead class="table-light">
                                    <tr>
                                        <th data-filter="string">Tutanak No</th>
                                        <th data-filter="date">Tutanak Tarihi</th>
                                        <th data-filter="string">Abone</th>
                                        <th data-filter="select">Ekip</th>
                                        <th data-filter="select">Neden</th>
                                        <th data-filter="string">Açıklama</th>
                                        <th data-filter="string">Bildiren</th>
                                        <th class="text-center" data-filter="string">Bekleme</th>
                                        <th class="text-center" data-filter="select">Tur</th>
                                        <th class="text-center" data-filter="select">Durum</th>
                                        <th class="text-center" data-filter="none">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($yetkiArsiv): ?>
            <!-- ============ FOTOĞRAF ARŞİVİ ============ -->
            <div class="tab-pane fade" id="pane-arsiv">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-1"><i class="bx bx-archive me-1"></i> Fotoğraf Arşivleme</h5>
                        <div class="alert alert-warning d-flex align-items-start mt-3">
                            <i class="bx bx-error-circle fs-4 me-2"></i>
                            <div>Belirttiğiniz dönemdeki kaçak fotoğrafları ilçe ve tür bazında klasörlenerek ZIP olarak
                                indirilecek ve <strong>sunucudan kalıcı olarak silinecektir</strong>. İndirilen dosyayı
                                güvenli bir yerde saklayın.</div>
                        </div>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <?= Form::FormDate('arsiv_baslangic', Date::dmY($son30Gun), 'Başlangıç Tarihi') ?></div>
                            <div class="col-md-3"><?= Form::FormDate('arsiv_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary w-100" id="btnArsivKontrol"><i
                                        class="bx bx-search me-1"></i>Dosyaları Kontrol Et</button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-danger w-100" id="btnArsivle" disabled><i
                                        class="bx bx-archive-in me-1"></i>Arşivle ve Sunucudan Sil</button>
                            </div>
                        </div>

                        <div class="alert alert-secondary mt-3 mb-0 d-none" id="arsivSonuc"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ BİLDİRİM PERSONELLERİ (KASKI) ============ -->
        <div class="tab-pane fade" id="pane-bildirim-personelleri">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1 text-dark fw-bold"><i class="bx bx-user-check text-primary me-1"></i> Bildirim Personelleri (KASKI)</h5>
                            <p class="text-muted small mb-0">Bu personeller yalnızca Personel PWA mobil uygulaması üzerinden Kaçak Bildirimi yapmak üzere yetkilidir; bordro, puantaj ve genel personel listelerinde yer almazlar.</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm px-3" onclick="openNewBildirimPersonelModal()">
                                <i class="bx bx-plus me-1"></i> Yeni Bildirim Personeli Ekle
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="bildirimPersonelTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Adı Soyadı</th>
                                    <th>T.C. Kimlik No</th>
                                    <th>Cep Telefonu</th>
                                    <th>Ekip / Bölge</th>
                                    <th>E-Posta</th>
                                    <th>Durum</th>
                                    <th>Kayıt Tarihi</th>
                                    <th class="text-end">İşlemler</th>
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

<!-- ============ KAYIT MODALI ============ -->
<div class="modal fade" id="kacakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form id="kacakForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="kacakModalTitle">Kaçak Kontrol Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="kacak_id" value="0">
            <div class="modal-body">

                    <div class="alert alert-info d-flex align-items-center d-none" id="bekleyenBildirimUyarisi">
                        <i class="bx bx-mobile-alt fs-4 me-2"></i>
                        <div>
                            Bu kayıt <strong id="bekleyenBildirenAdi"></strong> tarafından mobil uygulamadan
                            bildirildi ve <strong>onay bekliyor</strong>. Hatalı alanları düzeltip
                            "Kaydet ve Onayla" ile onaylayabilirsiniz.
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-3">
                        <h6 class="fw-bold text-primary mb-2"><i class="bx bx-bot me-1"></i> Yapay Zeka ile Tutanak Oku
                        </h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-9">
                                <?= Form::FormFileInput(
                                    name: 'tutanak_file',
                                    label: 'Tutanak Belgesi (Görsel, PDF veya Excel)',
                                    icon: 'upload',
                                    class: 'form-control'
                                ) ?>
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="btnAnalizEt"
                                    class="btn btn-warning w-100 fw-bold d-flex align-items-center justify-content-center"
                                    style="height:38px">
                                    <i class="bx bx-play me-1 fs-5"></i> Analiz Et
                                </button>
                            </div>
                        </div>
                        <div class="form-text text-muted">Dosyayı seçtikten sonra "Analiz Et" butonuna basarak verileri
                            otomatik çıkartabilirsiniz. Seçilen dosya kayıtla birlikte tutanak belgesi olarak da
                            saklanır.</div>
                        <div id="analizSpinner" class="text-center p-2 mt-3 d-none">
                            <div class="spinner-border text-warning"></div>
                            <p class="mt-2 text-warning fw-bold mb-0">Tutanak analiz ediliyor, lütfen bekleyin...</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?= Form::FormDate('tarih', Date::today(), 'Tarih') ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormMultipleSelect2(
                                name: 'kacak_personel_ids',
                                options: $personelOptions,
                                selectedValues: [],
                                label: 'Personel Seçimi (En Fazla 2)',
                                icon: 'users',
                                valueField: 'key',
                                textField: '',
                                class: 'form-select select2-kacak-personel',
                                required: true,
                                id: 'kacak_personel_ids'
                            ) ?>
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-bold text-primary"><i class="bx bx-list-plus me-1"></i> Giriş Detayları
                            </h6>
                            <button type="button" class="btn btn-sm btn-soft-success" id="btnSatirEkle"><i
                                    class="bx bx-plus me-1"></i> Satır Ekle</button>
                        </div>
                        <div id="kacakSatirlar"></div>

                        <!-- Satır şablonu: JS bu şablonu klonlar (alanlar App\Helper\Form ile üretilir) -->
                        <template id="kacakSatirTemplate">
                            <div class="border p-2 rounded mb-2 kacak-satir bg-white">
                                <div class="row g-2">
                                    <div class="col-md-2">
                                        <?= Form::FormSelect2('ilce[]', $ilceSatirOptions, '', 'İlçe', 'map-pin', 'key', '', 'form-select satir-ilce', true, 'width:100%', '', 'tpl_ilce') ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormSelect2('tur[]', $turSatirOptions, 'Kaçak', 'Tür', 'tag', 'key', '', 'form-select satir-tur', true, 'width:100%', '', 'tpl_tur') ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('text', 'tutanak_no[]', '', 'Tutanak No', 'Tutanak No', 'hash', 'form-control satir-tutanak_no', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?= Form::FormFloatInput('text', 'abone_adi[]', '', 'Abone Adı Soyadı', 'Abone Adı', 'user', 'form-control satir-abone_adi', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('text', 'sayac_no[]', '', 'Sayaç No', 'Sayaç No', 'cpu', 'form-control satir-sayac_no', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-1 d-flex align-items-center justify-content-end">
                                        <button type="button" class="btn btn-soft-danger btnSatirSil" title="Satırı sil">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>

                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('text', 'endeks[]', '', 'Endeks', 'Endeks', 'activity', 'form-control satir-endeks', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('number', 'sayi[]', '1', 'Sayı', 'Sayı', 'plus-circle', 'form-control satir-sayi', true, null, 'off', false, 'min="1"', false) ?>
                                    </div>
                                    <div class="col-md-8">
                                        <?= Form::FormFloatInput('text', 'aciklama[]', '', 'Açıklama', 'Açıklama', 'message-square', 'form-control satir-aciklama', false, null, 'off', false, '', false) ?>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Gizli dosya inputları -->
                    <input type="file" id="tutanak_foto" name="tutanak_foto[]" multiple accept="image/*,application/pdf" class="d-none">
                    <input type="file" id="saha_fotolari" name="saha_fotolari[]" multiple accept="image/*" class="d-none">
                    <input type="file" id="kacak_videolar" name="videolar[]" multiple accept="video/*" class="d-none">

                    <div id="mevcutFotolarBolumu" class="accordion mt-3">
                        <div class="accordion-item border rounded-3 overflow-hidden">
                            <h2 class="accordion-header" id="headingFotolar">
                                <button class="accordion-button py-2 px-3 bg-light fw-semibold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFotolar" aria-expanded="true" aria-controls="collapseFotolar">
                                    <i class="bx bx-images me-2 fs-5"></i>
                                    <span>Yüklü Belgeler ve Görseller</span>
                                    <span class="badge bg-primary rounded-pill ms-2" id="fotoSayisiBadge">0</span>
                                </button>
                            </h2>
                            <div id="collapseFotolar" class="accordion-collapse collapse show" aria-labelledby="headingFotolar">
                                <div class="accordion-body p-3">
                                    <div class="d-flex flex-wrap gap-3 align-items-start" id="mevcutFotolar">
                                        <div id="mevcutFotolarListesi" class="d-flex flex-wrap gap-3"></div>
                                        <div id="yeniTutanaklarPreview" class="d-flex flex-wrap gap-3"></div>
                                        <div id="yeniFotolarPreview" class="d-flex flex-wrap gap-3"></div>
                                        <div id="yeniVideolarPreview" class="d-flex flex-wrap gap-3"></div>

                                        <!-- Tutanak Ekle Kartı -->
                                        <div id="btnTutanakSecCard" class="kacak-upload-btn-card tutanak-card text-center d-flex flex-column align-items-center justify-content-center" role="button" title="Tutanak Belgesi / Fotoğrafı Seç (Görsel veya PDF)">
                                            <i class="bx bx-file-blank fs-2 text-warning"></i>
                                            <span class="fw-bold text-warning mt-1" style="font-size:0.72rem;">Tutanak Ekle</span>
                                            <span class="text-muted" style="font-size:0.62rem;">Görsel / PDF</span>
                                        </div>

                                        <!-- Fotoğraf Ekle Kartı -->
                                        <div id="btnFotoSecCard" class="kacak-upload-btn-card text-center d-flex flex-column align-items-center justify-content-center" role="button" title="Saha Fotoğrafı Seç (en fazla <?= KacakKontrolModel::MAX_SAHA_FOTO ?> adet)">
                                            <i class="bx bx-camera-plus fs-2 text-primary"></i>
                                            <span class="fw-bold text-primary mt-1" style="font-size:0.72rem;">Fotoğraf Ekle</span>
                                            <span class="text-muted" style="font-size:0.62rem;">Max <?= KacakKontrolModel::MAX_SAHA_FOTO ?> adet</span>
                                        </div>

                                        <!-- Video Ekle Kartı -->
                                        <div id="btnVideoSecCard" class="kacak-upload-btn-card video-card text-center d-flex flex-column align-items-center justify-content-center" role="button" title="Video Seç (en fazla <?= KacakKontrolModel::MAX_VIDEO ?> adet, <?= KacakKontrolModel::VIDEO_MAX_SURE ?> sn)">
                                            <i class="bx bx-video-plus fs-2 text-danger"></i>
                                            <span class="fw-bold text-danger mt-1" style="font-size:0.72rem;">Video Ekle</span>
                                            <span class="text-muted" style="font-size:0.62rem;">Max <?= KacakKontrolModel::MAX_VIDEO ?> adet, <?= KacakKontrolModel::VIDEO_MAX_SURE ?>sn</span>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2" style="font-size:0.75rem;">
                                        <i class="bx bx-info-circle me-1"></i>Tutanak belgesi (PDF veya JPEG/PNG), saha fotoğrafları (en fazla <?= KacakKontrolModel::MAX_SAHA_FOTO ?> adet), videolar ise en fazla <?= KacakKontrolModel::VIDEO_MAX_SURE ?> saniye ve <?= round(KacakKontrolModel::videoYuklemeSiniri() / 1048576) ?> MB olabilir.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                    <button type="button" class="btn btn-success d-none" id="btnKaydetVeOnayla">
                        <i class="bx bx-check me-1"></i>Kaydet ve Onayla
                    </button>
                </div>
        </form>
    </div>
</div>

<!-- ============ İPTAL MODALI ============ -->
<div class="modal fade" id="iptalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tutanak İptali</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="iptalForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" id="iptal_id" value="0">
                <input type="hidden" name="cancel_token" id="iptal_token" value="">
                <div class="modal-body">
                    <div class="mb-3 d-none" id="iptalTutanakSecimAlani">
                        <?= Form::FormSelect2(
                            name: 'iptal_tutanak_sec',
                            options: ['' => 'Tutanak seçiniz'],
                            selectedValue: '',
                            label: 'İptal Edilecek Tutanak',
                            icon: 'file-text',
                            required: true
                        ) ?>
                    </div>
                    <div class="alert alert-light border" id="iptalKayitBilgi"></div>

                    <div class="mb-3">
                        <?= Form::FormFloatTextarea(
                            name: 'iptal_aciklama',
                            value: '',
                            placeholder: 'Tutanağın neden iptal edildiğini yazın...',
                            label: 'İptal Açıklaması',
                            icon: 'edit-3',
                            required: true,
                            minHeight: '90px',
                            rows: 3
                        ) ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFileInput(
                            name: 'iptal_foto',
                            label: 'İptal Belgesi / Fotoğrafı',
                            icon: 'image',
                            class: 'form-control',
                            attributes: 'accept="image/*,application/pdf"'
                        ) ?>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="hakedisten_dus" name="hakedisten_dus"
                            value="1">
                        <label class="form-check-label fw-semibold" for="hakedisten_dus">Hakedişten düşülsün</label>
                        <div class="form-text">İşaretlenirse bu tutanak toplam sayıdan ve prim hesabından çıkarılır.
                            İşaretlenmezse kayıt iptal görünür ama hakedişte sayılmaya devam eder.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-danger">İptal Et</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($yetkiSicilBildir): ?>
    <!-- ============ SİCİL EKSİK BİLDİRİM MODALI ============ -->
    <div class="modal fade" id="sicilEksikModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-user-x me-1 text-danger"></i> Sicil Oluşmadı Bildirimi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="sicilEksikForm">
                    <input type="hidden" name="action" value="sicil-create">
                    <input type="hidden" name="kacak_id" id="sicil_kacak_id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <?= Form::FormSelect2(
                                name: 'tutanak_no',
                                options: [],
                                selectedValue: '',
                                label: 'Tutanak No',
                                icon: 'file-text',
                                required: true,
                                id: 'sicil_tutanak_no'
                            ) ?>
                            <div class="form-text">Tutanak numarasını yazmaya başlayın; sistemdeki kayıtlar listelenir.</div>
                        </div>

                        <div class="alert alert-light border d-none" id="sicilTutanakBilgi"></div>

                        <div class="mb-3">
                            <?= Form::FormSelect2(
                                name: 'neden',
                                options: $sicilNedenOptions,
                                selectedValue: 'dogum_tarihi_hatali',
                                label: 'Sicil Oluşmama Nedeni',
                                icon: 'alert-circle',
                                required: true,
                                id: 'sicil_neden'
                            ) ?>
                        </div>

                        <div class="mb-0">
                            <?= Form::FormFloatTextarea(
                                name: 'aciklama',
                                value: '',
                                placeholder: 'Örn: Nüfus kaydındaki doğum tarihiyle uyuşmuyor...',
                                label: 'Açıklama',
                                icon: 'edit-3',
                                minHeight: '90px',
                                rows: 3
                            ) ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-danger"><i class="bx bx-send me-1"></i>Ekibe Bildir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($yetkiSicilYanitla): ?>
    <!-- ============ SİCİL DÜZELTME MODALI ============ -->
    <div class="modal fade" id="sicilYanitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-edit me-1 text-primary"></i> Düzeltilmiş Bilgi Girişi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="sicilYanitForm">
                    <input type="hidden" name="action" value="sicil-yanitla">
                    <input type="hidden" name="id" id="sicil_yanit_id" value="0">
                    <div class="modal-body">
                        <div class="alert alert-warning" id="sicilYanitTalep"></div>

                        <p class="text-muted small mb-3">Yalnızca düzelttiğiniz alanları doldurun. Boş bırakılan alanlar
                            değiştirilmez.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'abone_tc', '', '11 haneli TC kimlik no', 'TC Kimlik No', 'credit-card', maxlength: 11, autocomplete: 'off') ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormDate('abone_dogum_tarihi', '', 'Doğum Tarihi') ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'abone_adi', '', 'Abone adı soyadı', 'Ad Soyad', 'user', autocomplete: 'off') ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'sayac_no', '', 'Sayaç seri no', 'Sayaç No', 'hash', autocomplete: 'off') ?>
                            </div>
                            <div class="col-12">
                                <?= Form::FormFloatInput('text', 'abone_adres', '', 'Abone adresi', 'Adres', 'map-pin', autocomplete: 'off') ?>
                            </div>
                            <div class="col-12">
                                <?= Form::FormFloatTextarea(
                                    name: 'yanit_aciklama',
                                    value: '',
                                    placeholder: 'Aboneyle görüşme notu, ulaşılamadıysa nedeni...',
                                    label: 'Açıklama',
                                    icon: 'message-square',
                                    minHeight: '80px',
                                    rows: 3
                                ) ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-primary"><i class="bx bx-send me-1"></i>Kuruma Gönder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($yetkiSicil): ?>
    <!-- ============ SİCİL DETAY MODALI ============ -->
    <div class="modal fade" id="sicilDetayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-history me-1"></i> Sicil Eksik Kaydı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="sicilDetayGovde"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($yetkiDuzenle): ?>
<!-- ============ EXCEL'DEN YÜKLEME MODALI ============ -->
<div class="modal fade" id="kacakExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="bx bx-upload me-1"></i>Excel'den Kayıt Yükle</h5>
                    <small class="text-muted">Tutanak numarasına göre mükerrer kontrolü yapılır</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="kacakExcelForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <?= Form::FormFileInput('excelFile', 'Excel Dosyası (.xlsx, .xls, .csv)', 'bx bx-spreadsheet', 'form-control', true, 'accept=".xlsx,.xls,.csv"', 'kacakExcelFile') ?>
                    </div>
                </form>

                <div class="alert alert-info py-2 px-3 small mb-3">
                    <i class="bx bx-info-circle me-1"></i>
                    Sütunlar başlık adına göre eşlenir, sıralamanın önemi yoktur. Beklenen başlıklar:
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <?php foreach (['TARİH', 'TUTANAK NO', 'İSİM SOYİSİM', 'SAYAÇ NO', 'TÜR', 'ENDEKS', 'İŞLEM YAPAN MEMUR', 'İLÇE', 'TUTAR', 'KONTROL EDİLDİ', 'USULSÜZ', 'TESLİM DURUMU'] as $baslik): ?>
                            <span class="badge bg-primary bg-opacity-75"><?= htmlspecialchars($baslik, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2">
                        <a href="views/kacak/excel-sablon.php" class="alert-link">
                            <i class="bx bx-download me-1"></i>Örnek şablonu indir
                        </a>
                    </div>
                </div>

                <div class="alert alert-warning py-2 px-3 small mb-3">
                    <i class="bx bx-error-circle me-1"></i>
                    <strong>Satır atlanma nedenleri:</strong> tutanak numarası boş ya da sistemde kayıtlı,
                    tarih okunamıyor, ilçe geçersiz, <strong>işlem yapan memur</strong> personel kaydıyla eşleşmiyor.
                    Atlanan satırlar yükleme sonunda tek tek listelenir.
                    <div class="mt-1">İki personel için virgül kullanın: <code>BÜNYAMİN ATEŞ,SAMED ARSLAN</code></div>
                </div>

                <div class="card bg-light border-0 shadow-none mb-0">
                    <div class="card-body py-2 px-3">
                        <p class="mb-1 small fw-bold text-muted">
                            <i class="bx bx-map-pin me-1"></i>Geçerli İlçeler
                            <span class="badge bg-secondary ms-1"><?= count($ilceler) ?></span>
                        </p>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($ilceler as $ilce): ?>
                                <span class="badge bg-secondary bg-opacity-50"><?= htmlspecialchars($ilce, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-primary" id="btnKacakExcelGonder">
                    <i class="bx bx-upload me-1"></i>Yükle
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============ FOTOĞRAF MODALI ============ -->
<div class="modal fade" id="fotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kayıt Belgeleri</h5>
                <a href="#" id="btnFotoModalZip" class="btn btn-sm btn-success ms-auto me-2" title="Tüm Belgeleri ZIP İndir">
                    <i class="bx bx-download me-1"></i> ZIP İndir
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-3" id="fotoModalIcerik"></div>
            </div>
        </div>
    </div>
</div>

<!-- ============ KAÇAK KONTROL RAPOR AYARLARI MODALI ============ -->
<div class="modal fade" id="reportSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kaçak Kontrol Rapor Ayarları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reportSettingsForm">
                <input type="hidden" name="action" value="report-settings-kaydet">
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bx bx-info-circle me-1"></i> Kaçak kontrol ekibi aralığını belirleryin.
                        <br><small>Birden fazla aralık için virgül kullanın (Örn: 51-60, 101-110)</small>
                    </div>
                    <div class="mb-3">
                        <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_kacak_kontrol', '51-60', '51-60', 'Kaçak Kontrol Ekip Aralığı', 'bx bx-search-alt') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveReportSettings">Ayarları Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ BİLDİRİM PERSONELİ EKLE / DÜZENLE MODALI ============ -->
<div class="modal fade" id="modalBildirimPersonel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <form id="formBildirimPersonel" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBildirimPersonelTitle">Yeni Bildirim Personeli</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <input type="hidden" name="token" id="bp_token" value="">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Adı Soyadı</label>
                    <input type="text" name="adi_soyadi" id="bp_adi_soyadi" class="form-control" required placeholder="Örn: Ahmet Yılmaz">
                </div>
                <div class="mb-3">
                    <label class="form-label required">T.C. Kimlik Numarası</label>
                    <input type="text" name="tc_kimlik_no" id="bp_tc_kimlik_no" class="form-control" maxlength="11" required placeholder="11 haneli T.C. No">
                </div>
                <div class="mb-3">
                    <label class="form-label required">Cep Telefonu</label>
                    <input type="tel" name="cep_telefonu" id="bp_cep_telefonu" class="form-control" maxlength="11" required placeholder="05XXXXXXXXX">
                </div>
                <div class="mb-3" id="bp_sifre_wrapper">
                    <label class="form-label">Giriş Şifresi <small class="text-muted" id="bp_sifre_hint">(Boş bırakılırsa otomatik üretilir)</small></label>
                    <div class="input-group">
                        <input type="text" name="sifre" id="bp_sifre" class="form-control" placeholder="En az 6 karakter">
                        <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPassword('bp_sifre')"><i class="bx bx-dice-5"></i> Üret</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bölge / Ekip Notu</label>
                    <input type="text" name="ekip_bolge" id="bp_ekip_bolge" class="form-control" placeholder="Örn: Onikişubat - KASKI Denetim">
                </div>
                <div class="mb-3">
                    <label class="form-label">E-Posta (Opsiyonel)</label>
                    <input type="email" name="email_adresi" id="bp_email_adresi" class="form-control" placeholder="ornek@kaski.gov.tr">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="submit" class="btn btn-primary" id="btnSaveBildirimPersonel"><i class="bx bx-save me-1"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ ŞİFRE BELİRLE MODALI ============ -->
<div class="modal fade" id="modalSifreBelirle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form id="formSifreBelirle" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Şifre Belirle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <input type="hidden" name="token" id="sifre_token" value="">
            <div class="modal-body">
                <p class="text-muted small mb-2"><strong id="sifre_personel_adi"></strong> personeli için yeni giriş şifresi belirleyin:</p>
                <div class="input-group mb-2">
                    <input type="text" name="sifre" id="yeni_sifre" class="form-control" required placeholder="Yeni şifre">
                    <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPassword('yeni_sifre')"><i class="bx bx-dice-5"></i></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-warning btn-sm" id="btnSaveSifre"><i class="bx bx-key me-1"></i> Güncelle</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/libs/glightbox/js/glightbox.min.js"></script>
<script>
    (function () {
        const API = 'views/kacak/api.php';
        const ILCELER = <?= json_encode(KacakKontrolModel::ILCELER, JSON_UNESCAPED_UNICODE) ?>;
        const MAX_SAHA_FOTO = <?= KacakKontrolModel::MAX_SAHA_FOTO ?>;
        const MAX_VIDEO = <?= KacakKontrolModel::MAX_VIDEO ?>;
        const VIDEO_MAX_SURE = <?= KacakKontrolModel::VIDEO_MAX_SURE ?>;
        const VIDEO_MAX_BYTE = <?= KacakKontrolModel::videoYuklemeSiniri() ?>;
        const CEKIM_GECIKME_DK = <?= KacakKontrolModel::CEKIM_GECIKME_DK ?>;
        const BILDIRIM_KACAK_ID = <?= json_encode($bildirimKacakId) ?>;
        let kacakSeciliTutanaklar = [];
        let kacakSeciliFotolar = [];
        let kacakSeciliVideolar = [];
        const YETKI = {
            duzenle: <?= $yetkiDuzenle ? 'true' : 'false' ?>,
            onay: <?= $yetkiOnay ? 'true' : 'false' ?>,
            iptal: <?= $yetkiIptal ? 'true' : 'false' ?>,
            iptalEkle: <?= $yetkiIptalEkle ? 'true' : 'false' ?>,
            arsiv: <?= $yetkiArsiv ? 'true' : 'false' ?>,
            sicilBildir: <?= $yetkiSicilBildir ? 'true' : 'false' ?>,
            sicilYanitla: <?= $yetkiSicilYanitla ? 'true' : 'false' ?>
        };
        const SICIL_NEDENLER = <?= json_encode(KacakSicilEksikModel::NEDENLER, JSON_UNESCAPED_UNICODE) ?>;
        const SICIL_UYARI_GUN = <?= KacakSicilEksikModel::UYARI_GUN ?>;
        const SICIL_KRITIK_GUN = <?= KacakSicilEksikModel::KRITIK_GUN ?>;
        let kacakFotoLightbox = null;

        let kacakTable, onayTable, iptalTable, teslimTable, sicilTable;
        let dashboardYuklendi = false;
        const dashboardCharts = {};
        const teslimSecilenler = new Set();
        let sicilAktifDurum = 'beklemede';
        let kacakKayitlari = [];
        let sicilKayitlari = [];
        let onayKayitlari = [];

        function esc(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/[&<>"']/g, m => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[m]));
        }

        function toIsoDate(str) {
            if (!str) return '';
            const p = String(str).trim().split(/[.\/-]/);
            if (p.length !== 3) return str;
            if (p[0].length === 4) return `${p[0]}-${p[1]}-${p[2]}`;
            return `${p[2]}-${p[1]}-${p[0]}`;
        }

        function apiGet(params) {
            return $.getJSON(API, params);
        }

        function hataGoster(res) {
            Swal.fire('Hata', (res && res.message) || 'İşlem tamamlanamadı.', 'error');
        }

        // DataTables çizimde satırları yeniden ürettiği için tooltip'ler her
        // seferinde yeniden bağlanır. container:'body' ile table-responsive
        // taşma kırpmasından kurtulur; artık kalan balonlar temizlenir.
        function tooltipleriTazele(kapsayici) {
            $('.tooltip').remove();
            $(kapsayici).find('[data-bs-toggle="tooltip"]').each(function () {
                const mevcut = bootstrap.Tooltip.getInstance(this);
                if (mevcut) mevcut.dispose();
                new bootstrap.Tooltip(this, { container: 'body', trigger: 'hover' });
            });
        }

        // ---------- Ortak DataTable ayarları ----------
        // Dil ve görünüm ayarları projenin merkezi helper'ından gelir (assets/js/datatables.init.js).
        function dtSecenekleri(ek) {
            var temel = (typeof getDatatableOptions === 'function') ? getDatatableOptions() : {};
            var secenekler = $.extend(true, {}, temel, ek || {});
            if (typeof applyLengthStateSave === 'function') {
                secenekler = applyLengthStateSave(secenekler);
            }
            return secenekler;
        }

        function turBadge(tur) {
            const renkler = { 'Kaçak': 'bg-danger', 'Abonesiz': 'bg-warning text-dark', 'Usülsüz': 'bg-info' };
            return `<span class="badge ${renkler[tur] || 'bg-secondary'}">${esc(tur)}</span>`;
        }

        function tarihHucresi(k) {
            const tutanakTarihi = esc(k.tarih_formatted || '-');
            const bildirimTarihi = esc(k.olusturma_tarihi_formatted || '-');
            return `<div>
                <span class="fw-semibold">${tutanakTarihi}</span>
                ${bildirimTarihi !== '-' ? `<small class="text-muted d-block mt-0.5" style="font-size:11px;" title="Bildirim Tarihi & Saati"><i class="bx bx-time-five me-1"></i>${bildirimTarihi}</small>` : ''}
            </div>`;
        }

        function durumBadge(k) {
            if (k.onay_durumu === 'beklemede') return '<span class="badge bg-info">Onay Bekliyor</span>';
            if (k.onay_durumu === 'reddedildi') return '<span class="badge bg-secondary">Reddedildi</span>';
            if (k.durum === 'iptal') {
                return k.hakedisten_dus == 1
                    ? '<span class="badge bg-dark">İptal (Düşüldü)</span>'
                    : '<span class="badge bg-secondary">İptal</span>';
            }
            return '<span class="badge bg-success">Aktif</span>';
        }

        // Tutanağın kurum tarafındaki sicil akışı - hakediş durumundan bağımsızdır.
        function sicilIsareti(k) {
            const map = {
                eksik: ['bx-user-x text-danger', 'Sicil oluşmadı, ekip yanıtı bekleniyor'],
                yanitlandi: ['bx-user-check text-success', 'Düzeltme girildi, kurum kontrolünde'],
                cozuldu: ['bx-check-shield text-muted', 'Sicil oluşturuldu']
            };
            const durum = map[k.sicil_durumu];
            if (!durum) return '';
            return ` <i class="bx ${durum[0]}" title="${durum[1]}"></i>`;
        }

        function kaynakBadge(kaynak) {
            const map = { pwa: ['bg-primary', 'Mobil'], masaustu: ['bg-light text-dark', 'Masaüstü'], excel: ['bg-light text-dark', 'Excel'] };
            const [cls, label] = map[kaynak] || ['bg-light text-dark', kaynak || '-'];
            return `<span class="badge ${cls}">${esc(label)}</span>`;
        }

        function fotoButonu(k) {
            const adet = parseInt(k.foto_sayisi || 0, 10);
            const beklenen = parseInt(k.beklenen_foto_sayisi || 0, 10);
            const eksik = Math.max(0, beklenen - adet);
            if (adet === 0 && eksik === 0) return '<span class="text-muted">-</span>';
            if (adet === 0) {
                return `<span class="badge bg-warning-subtle text-warning" title="Fotoğraf yüklemesi sürüyor">
                    <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>0/${beklenen}
                </span>`;
            }
            const sayac = eksik > 0 ? `${adet}/${beklenen}` : adet;
            const gecikmeli = parseInt(k.gecikmeli_foto_sayisi || 0, 10);
            const baslik = gecikmeli > 0
                ? `${gecikmeli} fotoğraf çekildikten ${CEKIM_GECIKME_DK} dakikadan geç yüklenmiş`
                : (eksik > 0
                    ? `Beklenen ${beklenen} belgeden ${adet} tanesi geldi`
                    : `${adet} belge — Fotoğrafları Görüntüle`);
            const bekleme = eksik > 0
                ? `<span class="badge bg-warning-subtle text-warning" title="${eksik} fotoğrafın yüklenmesi bekleniyor"><i class="bx bx-time-five me-1"></i>${eksik} bekleniyor</span>`
                : '';
            const gecikmeSinifi = gecikmeli > 0 ? ' kacak-gecikme-pulse' : '';
            return `<div class="d-flex align-items-center justify-content-center gap-1">
                <button class="btn btn-sm btn-soft-info btn-foto${gecikmeSinifi}" data-id="${k.id}" data-mevcut="${adet}" data-beklenen="${beklenen}" title="${baslik}"><i class="bx bx-image me-1"></i>${sayac}</button>
                ${bekleme}
                <a href="${API}?action=download-zip&id=${k.id}" class="btn btn-sm btn-soft-success btn-foto-zip" title="Fotoğrafları ZIP Olarak İndir"><i class="bx bx-download"></i></a>
            </div>`;
        }

        // ---------- KAYITLAR ----------
        function kayitFiltreleri() {
            return {
                action: 'list',
                start_date: toIsoDate($('#filtre_baslangic').val()),
                end_date: toIsoDate($('#filtre_bitis').val()),
                ilce: $('#filtre_ilce').val(),
                tur: $('#filtre_tur').val(),
                arama: $('#filtre_arama').val(),
                durum: 'aktif',
                onay_durumu: 'onaylandi'
            };
        }

        function ozetGuncelle(ozet) {
            if (!ozet) return;
            $('#ozetAktif').text(ozet.aktif || 0);
            $('#ozetKacak').text(ozet.kacak || 0);
            $('#ozetAbonesiz').text(ozet.abonesiz || 0);
            $('#ozetUsulsuz').text(ozet.usulsuz || 0);
            $('#ozetIptal').text(ozet.iptal || 0);
            $('#ozetIptalDusulen').text(ozet.iptal_dusulen || 0);
            $('#ozetBekleyen').text(ozet.bekleyen || 0);
            const badge = $('#bekleyenBadge');
            if ((ozet.bekleyen || 0) > 0) badge.text(ozet.bekleyen).show(); else badge.hide();
        }

        function dashboardGrafikCiz(anahtar, selector, options) {
            if (typeof ApexCharts === 'undefined') return;
            if (dashboardCharts[anahtar]) dashboardCharts[anahtar].destroy();
            dashboardCharts[anahtar] = new ApexCharts(document.querySelector(selector), options);
            dashboardCharts[anahtar].render();
        }

        function dashboardYukle() {
            apiGet({
                action: 'dashboard',
                start_date: toIsoDate($('#dashboard_baslangic').val()),
                end_date: toIsoDate($('#dashboard_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const d = res.data || {}, o = d.ozet || {};
                $('#dashboardAktif').text(o.aktif || 0);
                $('#dashboardBekleyen').text(o.bekleyen || 0);
                $('#dashboardPotansiyel').text(parseInt(o.aktif || 0, 10) + parseInt(o.bekleyen || 0, 10));
                $('#dashboardKacak').text(o.kacak || 0); $('#dashboardAbonesiz').text(o.abonesiz || 0); $('#dashboardUsulsuz').text(o.usulsuz || 0);
                const aktifToplam = Math.max(1, parseInt(o.aktif || 0, 10));
                $('#dashboardSegKacak').css('width', (parseInt(o.kacak || 0, 10) * 100 / aktifToplam) + '%');
                $('#dashboardSegAbonesiz').css('width', (parseInt(o.abonesiz || 0, 10) * 100 / aktifToplam) + '%');
                $('#dashboardSegUsulsuz').css('width', (parseInt(o.usulsuz || 0, 10) * 100 / aktifToplam) + '%');
                const i = d.istatistik || {};
                $('#dashboardGunlukOrtalama').text(i.gunluk_ortalama || 0);
                $('#dashboardEkipSayisi').text(i.ekip_sayisi || 0);
                $('#dashboardAktifGun').text(i.aktif_gun || 0);
                const ekipBoleni = Math.max(1, parseInt(i.ekip_sayisi || 0, 10) * parseInt(i.aktif_gun || 0, 10));
                $('#dashboardEkipOrtalama').text((parseInt(o.aktif || 0, 10) / ekipBoleni).toFixed(1).replace('.', ','));
                const by = d.bekleme_yasi || {}, bekToplam = Math.max(1, parseInt(o.bekleyen || 0, 10));
                [['02','gun_0_2'],['37','gun_3_7'],['814','gun_8_14'],['15','gun_15_plus']].forEach(x => {
                    const n = parseInt(by[x[1]] || 0, 10); $('#wait' + x[0] + 'n').text(n); $('#wait' + x[0]).css('width', (n * 100 / bekToplam) + '%');
                });

                const aks = d.aksiyonlar || {}, uzunBekleyen = parseInt(by.gun_8_14 || 0, 10) + parseInt(by.gun_15_plus || 0, 10);
                const aksiyonlar = [
                    {n:uzunBekleyen, cls:uzunBekleyen ? 'text-warning' : 'text-muted', text:'onay 7 günden uzun bekliyor', sub:uzunBekleyen ? 'Öncelikli kontrol edilmeli' : 'Bu dönemde geciken onay yok', tab:'#pane-onaylar', go:'Bekleyen Onaylar →'},
                    {n:parseInt(aks.foto_eksik || 0,10), cls:aks.foto_eksik ? 'text-warning' : 'text-muted', text:'tutanakta fotoğraf eksik', sub:'Beklenen fotoğraf sayısı tamamlanmamış', tab:'#pane-kayitlar', go:'Kayıtlar →'},
                    {n:parseInt(o.iptal || 0,10), cls:o.iptal ? 'text-danger' : 'text-muted', text:'iptal edilmiş tutanak', sub:(o.iptal_dusulen || 0) + ' adedi hakedişten düşülmüş', tab:'#pane-iptaller', go:'İptaller →'}
                ];
                $('#dashboardAksiyonlar').html(aksiyonlar.map(a => `<div class="kd-action" data-tab="${a.tab}"><div class="kd-action-num ${a.cls}">${a.n}</div><div class="kd-action-text">${a.text}<small>${a.sub}</small></div><div class="kd-action-go text-muted">${a.go}</div></div>`).join(''));

                const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                const ortak = {
                    chart: { toolbar: { show: false }, height: 280, fontFamily: 'inherit' },
                    dataLabels: { enabled: true, offsetY: -5, style: { fontSize: '10px' }, background: { enabled: false } }, noData: { text: 'Veri yok' },
                    grid: { borderColor: dark ? 'rgba(148,163,184,.15)' : '#eef2f7', strokeDashArray: 4 },
                    tooltip: { theme: dark ? 'dark' : 'light' }
                };
                dashboardGrafikCiz('trend', '#kacakTrendChart', $.extend(true, {}, ortak, {
                    chart: { type: 'bar' }, colors: ['#6366f1'], plotOptions:{bar:{borderRadius:3,columnWidth:'65%',dataLabels:{position:'top'}}},
                    series: [{ name: 'Tutanak', data: (d.trend || []).map(x => parseInt(x.aktif || 0, 10)) }],
                    xaxis: { categories: (d.trend || []).map(x => x.tarih), tickAmount: 8 }, yaxis: { min: 0, forceNiceScale: true, labels:{show:false} }, legend:{show:false}
                }));
                const ilceler = d.ilceler || [], ilceMax = Math.max(1, ...ilceler.map(x => parseInt(x.toplam || 0,10)));
                $('#dashboardIlceTablo').html(ilceler.length ? ilceler.map(x => { const t=parseInt(x.toplam||0,10)||1; return `<tr><td>${esc(x.ilce||'-')}</td><td class="kd-stack-col"><div class="kd-stack" style="width:${t*100/ilceMax}%"><i style="width:${parseInt(x.abonesiz||0,10)*100/t}%;background:var(--kd-abonesiz)"></i><i style="width:${parseInt(x.kacak||0,10)*100/t}%;background:var(--kd-kacak)"></i><i style="width:${parseInt(x.usulsuz||0,10)*100/t}%;background:var(--kd-usulsuz)"></i></div></td><td class="text-end">${parseInt(x.kacak||0,10)}</td><td class="text-end">${parseInt(x.abonesiz||0,10)}</td><td class="text-end">${parseInt(x.usulsuz||0,10)}</td><td class="text-end fw-bold">${parseInt(x.toplam||0,10)}</td><td class="text-end text-muted">${parseInt(x.bekleyen||0,10)}</td></tr>`; }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Bu dönem için ilçe verisi yok.</td></tr>');
                const ekipler = d.ekipler || [];
                const enYuksek = Math.max(1, ...ekipler.map(x => parseInt(x.aktif || 0, 10) / Math.max(1,parseInt(x.calisilan_gun||0,10))));
                $('#dashboardEkipTablo').html(ekipler.length ? ekipler.map(e => {
                    const aktif=parseInt(e.aktif||0,10), gun=Math.max(1,parseInt(e.calisilan_gun||0,10)), verim=aktif/gun;
                    return `<tr><td class="fw-semibold">${esc(e.ekip)}</td><td class="text-end fw-bold">${aktif}</td><td class="text-end text-muted">${gun}</td><td class="kd-verim"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar" style="width:${verim*100/enYuksek}%"></div></div><span>${verim.toFixed(1).replace('.',',')}</span></div></td><td class="text-end text-muted">${parseInt(e.bekleyen||0,10)}</td><td class="text-end text-muted small">${esc((e.son_islem||'-').slice(0,16))}</td></tr>`;
                }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Bu dönem için ekip verisi yok.</td></tr>');
                dashboardYuklendi = true;
            });
        }

        function kayitlariYukle() {
            if (kacakTable) {
                kacakTable.ajax.reload(null, true);
                return;
            }

            const options = dtSecenekleri({
                processing: true,
                serverSide: true,
                pageLength: 25,
                order: [[0, 'desc']],
                ajax: {
                    url: API,
                    type: 'GET',
                    data: function (d) {
                        return $.extend(d, kayitFiltreleri());
                    },
                    dataSrc: function (res) {
                        if (res.status !== 'success') {
                            hataGoster(res);
                            return [];
                        }
                        ozetGuncelle(res.ozet);
                        kacakKayitlari = res.data || [];
                        return res.data || [];
                    },
                    error: function () {
                        Swal.fire('Hata', 'Kayıtlar yüklenemedi.', 'error');
                    }
                },
                columns: [
                    { data: 'tarih_formatted', render: function (data, type, row) { return tarihHucresi(row); } },
                    { data: 'tutanak_no', render: function (data, type, row) { return esc(data) + sicilIsareti(row); } },
                    { data: 'abone_adi', render: $.fn.dataTable.render.text() },
                    { data: 'ilce', render: $.fn.dataTable.render.text() },
                    { data: 'tur', render: function (data) { return turBadge(data); } },
                    { data: 'sayac_no', render: $.fn.dataTable.render.text() },
                    { data: 'sayi', render: $.fn.dataTable.render.text() },
                    { data: 'ekip_adi', render: $.fn.dataTable.render.text() },
                    { data: 'kaynak', render: function (data) { return kaynakBadge(data); } },
                    { data: null, orderable: false, searchable: false, render: function (data, type, row) { return fotoButonu(row); } },
                    { data: 'durum', render: function (data, type, row) { return durumBadge(row); } },
                    { data: null, orderable: false, searchable: false, render: function (data, type, row) { return kayitIslemButonlari(row); } }
                ],
                drawCallback: function () { tooltipleriTazele('#kacakTable'); }
            });
            kacakTable = $('#kacakTable').DataTable(
                typeof applyLengthStateSave === 'function' ? applyLengthStateSave(options) : options
            );
        }

        // Tooltip'li işlem butonu üretir.
        function islemButonu(cls, id, ikon, ipucu, etiket) {
            return `<button type="button" class="btn btn-sm ${cls}" data-id="${id}"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="${esc(ipucu)}">
                        <i class="bx ${ikon}"></i>${etiket ? ' ' + esc(etiket) : ''}</button>`;
        }

        // İşlem listesinden hem buton şeridi hem sağ tık menüsü üretilir;
        // ikisi tek kaynaktan geldiği için asla ayrışmaz.
        function islemButonlariCiz(islemler) {
            if (!islemler.length) return '<span class="text-muted">-</span>';
            let html = '<div class="d-flex gap-1 justify-content-center">';
            islemler.forEach(i => {
                html += islemButonu(i.cls + ' ' + i.sinif, i.id, i.ikon, i.ipucu, i.butonEtiketi);
            });
            return html + '</div>';
        }

        // Kurum kullanıcısı listeden doğrudan sicil eksik bildirimi açabilsin.
        function sicilBildirIslemi(k) {
            if (!YETKI.sicilBildir || !k.tutanak_no) return null;

            if (k.sicil_durumu === 'eksik' || k.sicil_durumu === 'yanitlandi') {
                return {
                    id: k.id, sinif: 'btn-sicil-git', etiket: 'Sicil Kaydına Git', ikon: 'bx-user-x',
                    ipucu: 'Bu tutanak için açık sicil kaydı var — Sicil Oluşmayanlar sekmesine git',
                    cls: 'btn-soft-secondary', renk: 'text-secondary'
                };
            }
            return {
                id: k.id, sinif: 'btn-sicil-bildir', etiket: 'Sicil Oluşmadı Bildir', ikon: 'bx-user-x',
                ipucu: 'Sicil oluşmadı bildirimi aç — tutanağı tutan ekibe düşer',
                cls: 'btn-soft-danger', renk: 'text-danger'
            };
        }

        // Belge butonu ayrı kolonda duruyor, sağ tık menüsünde de erişilebilsin.
        function fotoIslemi(k) {
            const adet = parseInt(k.foto_sayisi || 0, 10);
            if (adet === 0) return null;

            const beklenen = parseInt(k.beklenen_foto_sayisi || 0, 10);
            return {
                id: k.id, sinif: 'btn-foto', etiket: 'Belgeleri Görüntüle', ikon: 'bx-image',
                ipucu: adet + ' belge yüklü', renk: 'text-info', menuOnly: true,
                veri: { mevcut: adet, beklenen: beklenen }
            };
        }

        function kayitIslemleri(k) {
            const islemler = [];

            if (YETKI.duzenle) {
                islemler.push({
                    id: k.id, sinif: 'btn-duzenle', etiket: 'Düzenle', ikon: 'bx-edit',
                    ipucu: 'Kaydı düzenle', cls: 'btn-soft-primary', renk: 'text-primary'
                });
            }
            if (YETKI.iptalEkle && k.durum !== 'iptal') {
                islemler.push({
                    id: k.id, sinif: 'btn-iptal', etiket: 'İptal Et', ikon: 'bx-x-circle',
                    ipucu: 'Tutanağı iptal et — hakedişten düşme seçeneğiyle',
                    cls: 'btn-soft-warning', renk: 'text-warning'
                });
            }
            if (YETKI.duzenle) {
                islemler.push({
                    id: k.id, sinif: 'btn-sil', etiket: 'Sil', ikon: 'bx-trash',
                    ipucu: 'Kaydı sil', cls: 'btn-soft-danger', renk: 'text-danger'
                });
            }

            const sicil = sicilBildirIslemi(k);
            if (sicil) islemler.push(sicil);

            const foto = fotoIslemi(k);
            if (foto) islemler.push(foto);

            return islemler;
        }

        function kayitIslemButonlari(k) {
            return islemButonlariCiz(kayitIslemleri(k).filter(i => !i.menuOnly));
        }

        // ---------- BEKLEYEN ONAYLAR ----------
        // Sahadan hatalı veri gelebildiği için yönetici onaylamadan önce kaydı düzeltebilir.
        function onayIslemleri(k) {
            const islemler = [];

            if (YETKI.duzenle) {
                islemler.push({
                    id: k.id, sinif: 'btn-duzenle', etiket: 'Düzelt', ikon: 'bx-edit',
                    ipucu: 'Onaydan önce kaydı düzelt', cls: 'btn-soft-primary', renk: 'text-primary'
                });
            }
            if (YETKI.onay) {
                islemler.push({
                    id: k.id, sinif: 'btn-onayla', etiket: 'Onayla', ikon: 'bx-check',
                    ipucu: 'Bildirimi onayla — hakedişe dahil olur',
                    cls: 'btn-success', renk: 'text-success', butonEtiketi: 'Onayla'
                });
                islemler.push({
                    id: k.id, sinif: 'btn-reddet', etiket: 'Reddet', ikon: 'bx-x',
                    ipucu: 'Bildirimi reddet — personele bildirim gider',
                    cls: 'btn-danger', renk: 'text-danger', butonEtiketi: 'Reddet'
                });
            }

            const sicil = sicilBildirIslemi(k);
            if (sicil) islemler.push(sicil);

            const foto = fotoIslemi(k);
            if (foto) islemler.push(foto);

            return islemler;
        }

        function onayIslemButonlari(k) {
            return islemButonlariCiz(onayIslemleri(k).filter(i => !i.menuOnly));
        }

        function onaylariYukle() {
            apiGet({ action: 'list', start_date: '2000-01-01', end_date: '2099-12-31', onay_durumu: 'beklemede' })
                .done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);

                    // Sağ tık menüsü satır verisine ihtiyaç duyar; tablo satırları
                    // dizi olduğu için kayıtları ayrıca saklıyoruz.
                    onayKayitlari = res.data || [];
                    if (BILDIRIM_KACAK_ID > 0) {
                        onayKayitlari = onayKayitlari.filter(k => Number(k.id) === BILDIRIM_KACAK_ID);
                    }

                    const rows = onayKayitlari.map(k => [
                        tarihHucresi(k),
                        esc(k.bildiren_adi || '-'),
                        esc(k.ekip_adi),
                        esc(k.tutanak_no),
                        esc(k.abone_adi),
                        esc(k.ilce),
                        turBadge(k.tur),
                        fotoButonu(k),
                        onayIslemButonlari(k)
                    ]);

                    if (onayTable) {
                        onayTable.clear().rows.add(rows).draw();
                    } else {
                        onayTable = $('#onayTable').DataTable(dtSecenekleri({
                            data: rows, pageLength: 25, order: [[0, 'desc']],
                            columnDefs: [{ targets: [8], orderable: false }],
                            drawCallback: function () { tooltipleriTazele('#onayTable'); }
                        }));
                    }
                });
        }

        // ---------- İPTALLER ----------
        function iptalleriYukle() {
            apiGet({
                action: 'list',
                start_date: toIsoDate($('#iptal_baslangic').val()),
                end_date: toIsoDate($('#iptal_bitis').val()),
                durum: 'iptal'
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                const rows = res.data.map(k => [
                    esc(k.tarih_formatted),
                    esc(k.tutanak_no),
                    esc(k.abone_adi),
                    esc(k.ilce),
                    turBadge(k.tur),
                    esc(k.sayi),
                    esc(k.iptal_aciklama),
                    k.hakedisten_dus == 1
                        ? '<span class="badge bg-dark">Düşüldü</span>'
                        : '<span class="badge bg-light text-dark">Düşülmedi</span>',
                    esc(k.iptal_eden_adi || '-'),
                    fotoButonu(k),
                    YETKI.iptal
                        ? `<button class="btn btn-sm btn-soft-success btn-iptal-geri" data-id="${k.id}"><i class="bx bx-undo"></i> Geri Al</button>`
                        : '<span class="text-muted">-</span>'
                ]);

                if (iptalTable) {
                    iptalTable.clear().rows.add(rows).draw();
                } else {
                    iptalTable = $('#iptalTable').DataTable(dtSecenekleri({
                        data: rows, pageLength: 25, order: [[0, 'desc']],
                        columnDefs: [{ targets: [10], orderable: false }]
                    }));
                }
            });
        }

        // ---------- MODAL: SATIR ----------
        function guvenSinifi(guven, alan) {
            if (!guven || guven[alan] === undefined) return '';
            return parseInt(guven[alan], 10) < 70 ? 'ai-field-uncertain' : 'ai-field-confident';
        }

        function guvenRozeti(guven, alan) {
            if (!guven || guven[alan] === undefined) return '';
            const v = parseInt(guven[alan], 10);
            const cls = v < 70 ? 'bg-warning text-dark' : 'bg-success';
            return `<span class="badge ai-conf-badge ${cls}">%${v}</span>`;
        }

        function satirEkle(data) {
            data = data || {};
            const guven = data.guven || null;
            const rowId = 'satir_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

            const sablon = document.getElementById('kacakSatirTemplate');
            const $satir = $(sablon.content.cloneNode(true).firstElementChild);
            $satir.attr('id', rowId);

            // Şablondaki sabit id'ler klonlanınca tekilleştirilir (label[for] eşlemesiyle birlikte)
            $satir.find('[id]').each(function () {
                const eskiId = this.id;
                const yeniId = rowId + '_' + eskiId;
                $satir.find('label[for="' + eskiId + '"]').attr('for', yeniId);
                this.id = yeniId;
            });

            const alanlar = ['ilce', 'tur', 'tutanak_no', 'abone_adi', 'sayac_no', 'endeks', 'sayi', 'aciklama'];
            alanlar.forEach(function (alan) {
                const $el = $satir.find('.satir-' + alan);
                if (!$el.length) return;

                const deger = data[alan];
                if (deger !== undefined && deger !== null && deger !== '') {
                    $el.val(deger);
                }

                const sinif = guvenSinifi(guven, alan);
                if (sinif) $el.addClass(sinif);

                const rozet = guvenRozeti(guven, alan);
                if (rozet) {
                    $satir.find('label[for="' + $el.attr('id') + '"]').append(' ' + rozet);
                }
            });

            if (!$satir.find('.satir-sayi').val()) {
                $satir.find('.satir-sayi').val(1);
            }

            $('#kacakSatirlar').append($satir);

            $satir.find('.satir-ilce, .satir-tur').select2({
                dropdownParent: $('#kacakModal'),
                width: '100%'
            });

            if (typeof feather !== 'undefined') {
                try { feather.replace(); } catch (e) { console.warn('feather.replace error:', e); }
            }
        }

        $(document).on('input change', '#kacakSatirlar .ai-field-uncertain', function () {
            $(this).removeClass('ai-field-uncertain').addClass('ai-field-confident');
        });

        $('#btnSatirEkle').on('click', () => satirEkle());

        $(document).on('click', '.btnSatirSil', function () {
            if ($('.kacak-satir').length > 1) {
                $(this).closest('.kacak-satir').remove();
            } else {
                Swal.fire('Uyarı', 'En az bir satır bulunmalıdır.', 'warning');
            }
        });

        function badgeGuncelle() {
            const mevcutYuklu = $('#mevcutFotolarListesi .kacak-foto-item').length;
            const yeniTutanak = kacakSeciliTutanaklar.length;
            const yeniFoto = kacakSeciliFotolar.length;
            const yeniVideo = kacakSeciliVideolar.length;
            const toplam = mevcutYuklu + yeniTutanak + yeniFoto + yeniVideo;
            $('#fotoSayisiBadge').text(toplam);
        }

        // ---------- MODAL: AÇ / KAYDET ----------
        function modalSifirla() {
            $('#kacakForm')[0].reset();
            $('#kacak_id').val(0);
            $('#kacakSatirlar').empty();
            $('#mevcutFotolarBolumu').removeClass('d-none');
            $('#collapseFotolar').addClass('show');
            $('#headingFotolar .accordion-button').removeClass('collapsed').attr('aria-expanded', 'true');
            $('#mevcutFotolarListesi').empty().removeData('fotolar').removeData('kacak-id').removeData('loaded');
            $('#yeniTutanaklarPreview').empty();
            $('#yeniFotolarPreview').empty();
            $('#yeniVideolarPreview').empty();
            kacakSeciliTutanaklar = [];
            kacakSeciliFotolar = [];
            kacakSeciliVideolar = [];
            badgeGuncelle();
            $('#kacak_personel_ids').val(null).trigger('change');

            // Video ve dosya alanlarını tam temizle
            $('#tutanak_foto').val('');
            $('#saha_fotolari').val('');
            $('#kacak_videolar').val('');
            $('#tutanak_file').val('');
        }

        $('#kacakModal').on('hidden.bs.modal', function () {
            modalSifirla();
        });

        $('#btnYeniKacak').on('click', function () {
            modalSifirla();
            $('#kacakModalTitle').text('Yeni Kaçak Kontrol Kaydı');
            satirEkle();
            $('#kacakModal').modal('show');
            if (typeof feather !== 'undefined') {
                try { feather.replace(); } catch (e) {}
            }
        });

        $(document).on('click', '.btn-duzenle', function () {
            const id = $(this).data('id');
            apiGet({ action: 'get-record', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const k = res.data;

                modalSifirla();
                $('#kacak_id').val(k.id);
                $('#kacakForm input[name="tarih"]').val(k.tarih_formatted);
                $('#kacak_personel_ids').val(k.personel_ids_array).trigger('change');
                satirEkle(k);
                const fotolar = k.fotograflar || [];
                const $target = $('#mevcutFotolarListesi');
                $target.empty()
                    .data('fotolar', fotolar)
                    .data('kacak-id', k.id)
                    .data('loaded', true);
                if (fotolar.length > 0) {
                    fotolariBas($target, fotolar, k.id);
                }
                badgeGuncelle();

                // Onay bekleyen (mobil) bildirimlerde düzelt-ve-onayla akışı
                const onayBekliyor = k.onay_durumu === 'beklemede';
                $('#kacakModalTitle').text(onayBekliyor
                    ? 'Bildirimi Düzelt ve Onayla'
                    : 'Kaçak Kontrol Kaydını Düzenle');

                $('#bekleyenBildirimUyarisi').toggleClass('d-none', !onayBekliyor)
                    .find('#bekleyenBildirenAdi').text(k.bildiren_adi || 'Bilinmiyor');

                $('#btnKaydetVeOnayla').toggleClass('d-none', !(onayBekliyor && YETKI.onay));

                $('#kacakModal').modal('show');
                if (typeof feather !== 'undefined') {
                    try { feather.replace(); } catch (e) {}
                }
            });
        });

        // EXIF okuyucu yüklenemezse en azından cihazdaki dosya tarihi gönderilir.
        async function cekimOku(dosya) {
            if (window.ExifCekim) {
                try {
                    const deger = await window.ExifCekim.oku(dosya);
                    if (deger) return deger;
                } catch (hata) {
                    console.error('Çekim tarihi okunamadı:', hata);
                }
            }
            return window.ExifCekim ? window.ExifCekim.dosyaTarihi(dosya) : '';
        }

        async function kacakFormGonder(onaylaSonrasinda) {
            const secili = $('#kacak_personel_ids').val() || [];
            if (secili.length === 0) {
                Swal.fire('Uyarı', 'En az bir personel seçmelisiniz.', 'warning');
                return;
            }
            if (secili.length > 2) {
                Swal.fire('Uyarı', 'En fazla 2 personel seçebilirsiniz.', 'warning');
                return;
            }

            const form = document.getElementById('kacakForm');
            if (!form.reportValidity()) return;

            const mevcutYuklu = $('#mevcutFotolarListesi .kacak-foto-item').length;
            if (mevcutYuklu + kacakSeciliFotolar.length > MAX_SAHA_FOTO) {
                Swal.fire('Uyarı', `En fazla ${MAX_SAHA_FOTO} saha fotoğrafı yükleyebilirsiniz.`, 'warning');
                return;
            }

            const kayitId = parseInt($('#kacak_id').val(), 10) || 0;
            const fd = new FormData(form);
            fd.set('tarih', toIsoDate($('#kacakForm input[name="tarih"]').val()));

            const $butonlar = $('#kacakForm button[type="submit"], #btnKaydetVeOnayla');
            $butonlar.prop('disabled', true);

            // Tutanak dosyaları
            fd.delete('tutanak_foto[]');
            if (kacakSeciliTutanaklar && kacakSeciliTutanaklar.length > 0) {
                if (window.ResimSikistir) {
                    try {
                        const cekimler = await Promise.all(kacakSeciliTutanaklar.map(cekimOku));
                        const kucukler = await window.ResimSikistir.listeyiKucult(kacakSeciliTutanaklar, 2200, 0.82);
                        kucukler.forEach((dosya, i) => {
                            fd.append('tutanak_foto[]', dosya, dosya.name);
                            fd.append('tutanak_foto_cekim[]', cekimler[i] || '');
                        });
                    } catch (hata) {
                        console.error('Tutanak küçültme başarısız, orijinal dosyalar gönderiliyor:', hata);
                        kacakSeciliTutanaklar.forEach(f => fd.append('tutanak_foto[]', f, f.name));
                    }
                } else {
                    kacakSeciliTutanaklar.forEach(f => fd.append('tutanak_foto[]', f, f.name));
                }
            }

            // Saha Fotoğrafları
            fd.delete('saha_fotolari[]');
            if (kacakSeciliFotolar && kacakSeciliFotolar.length > 0) {
                if (window.ResimSikistir) {
                    try {
                        const cekimler = await Promise.all(kacakSeciliFotolar.map(cekimOku));
                        const kucukler = await window.ResimSikistir.listeyiKucult(kacakSeciliFotolar, 1600, 0.75);
                        kucukler.forEach((dosya, i) => {
                            fd.append('saha_fotolari[]', dosya, dosya.name);
                            fd.append('saha_fotolari_cekim[]', cekimler[i] || '');
                        });
                    } catch (hata) {
                        console.error('Fotoğraf küçültme başarısız, orijinal dosyalar gönderiliyor:', hata);
                        kacakSeciliFotolar.forEach(f => fd.append('saha_fotolari[]', f, f.name));
                    }
                } else {
                    kacakSeciliFotolar.forEach(f => fd.append('saha_fotolari[]', f, f.name));
                }
            }

            // Videolar seçim anında doğrulanmıştı; eğer istemcide işlendiyse süre ve kapak karesi ile gönderilir
            fd.delete('videolar[]');
            if (kacakSeciliVideolar && kacakSeciliVideolar.length > 0) {
                kacakSeciliVideolar.forEach(v => {
                    fd.append('videolar[]', v.dosya, v.dosya.name);
                    fd.append('video_cekimleri[]', v.cekim || '');
                    fd.append('video_sureleri[]', v.sure);
                    fd.append('video_kapaklari[]', v.kapak || '');
                });
            }

            $.ajax({
                url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                // Video/fotoğraf sunucuya ulaşmadıysa kayıt yine de kaydedilir;
                // sessiz kalmamak için uyarı olarak gösterilir.
                const medyaUyarilari = res.medya_uyarilari || [];
                const uyariVar = medyaUyarilari.length > 0;

                // Düzeltme sonrası doğrudan onaylama istendiyse aynı akışta onayı da geç
                if (onaylaSonrasinda && kayitId > 0) {
                    $.post(API, { action: 'approve', id: kayitId }, null, 'json').done(function (onay) {
                        if (onay.status !== 'success') return hataGoster(onay);
                        $('#kacakModal').modal('hide');
                        Swal.fire(
                            uyariVar ? 'Onaylandı, ancak dikkat' : 'Onaylandı',
                            uyariVar
                                ? 'Kayıt düzeltildi ve onaylandı. Ancak bazı dosyalar yüklenemedi:<br>' + medyaUyarilari.map(esc).join('<br>')
                                : 'Kayıt düzeltildi ve onaylandı.',
                            uyariVar ? 'warning' : 'success'
                        );
                        listeleriTazele();
                    }).fail(() => Swal.fire('Hata', 'Onaylama sırasında bir hata oluştu.', 'error'))
                        .always(() => $butonlar.prop('disabled', false));
                    return;
                }

                $('#kacakModal').modal('hide');
                Swal.fire(uyariVar ? 'Kaydedildi, ancak dikkat' : 'Başarılı', res.message, uyariVar ? 'warning' : 'success');
                listeleriTazele();
                $butonlar.prop('disabled', false);
            }).fail(function () {
                Swal.fire('Hata', 'Kayıt sırasında bir hata oluştu.', 'error');
                $butonlar.prop('disabled', false);
            });
        }

        function listeleriTazele() {
            kayitlariYukle();
            if (onayTable) onaylariYukle();
            if (ekipOzetYuklendi) ekipOzetiYukle();
        }

        // Tutanak Ekle kartına tıklama
        $('#btnTutanakSecCard').on('click', function () {
            $('#tutanak_foto').trigger('click');
        });

        // Fotoğraf Ekle kartına tıklama
        $('#btnFotoSecCard').on('click', function () {
            $('#saha_fotolari').trigger('click');
        });

        // Video Ekle kartına tıklama
        $('#btnVideoSecCard').on('click', function () {
            $('#kacak_videolar').trigger('click');
        });

        // Tutanak seçimi
        $('#tutanak_foto').on('change', function () {
            const files = Array.from(this.files || []);
            if (!files.length) return;

            for (const f of files) {
                kacakSeciliTutanaklar.push(f);
            }
            this.value = '';
            yeniTutanaklariCiz();
        });

        function yeniTutanaklariCiz() {
            const $container = $('#yeniTutanaklarPreview');
            $container.empty();
            kacakSeciliTutanaklar.forEach((file, idx) => {
                const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                const thumb = isPdf
                    ? `<div class="kacak-foto-thumb d-flex align-items-center justify-content-center bg-light"><i class="bx bxs-file-pdf fs-1 text-danger"></i></div>`
                    : `<img src="${URL.createObjectURL(file)}" class="kacak-foto-thumb" loading="lazy" alt="${esc(file.name)}">`;

                $container.append(`
                    <div class="kacak-foto-item text-center">
                        ${thumb}
                        <button type="button" class="btn btn-danger btn-sm btn-yeni-tutanak-sil" data-idx="${idx}" title="Kaldır">
                            <i class="bx bx-x"></i>
                        </button>
                        <div class="small text-muted mt-1 text-truncate" style="max-width:92px;" title="${esc(file.name)}">${esc(file.name)}</div>
                        <span class="badge bg-warning text-dark" style="font-size:.6rem">Yeni Tutanak</span>
                    </div>
                `);
            });
            badgeGuncelle();
        }

        $(document).on('click', '.btn-yeni-tutanak-sil', function () {
            const idx = parseInt($(this).data('idx'), 10);
            if (!isNaN(idx) && idx >= 0 && idx < kacakSeciliTutanaklar.length) {
                kacakSeciliTutanaklar.splice(idx, 1);
                yeniTutanaklariCiz();
            }
        });

        // Fotoğraf seçimi
        $('#saha_fotolari').on('change', function () {
            const files = Array.from(this.files || []);
            if (!files.length) return;

            const mevcutYuklu = $('#mevcutFotolarListesi .kacak-foto-item').length;
            if (mevcutYuklu + kacakSeciliFotolar.length + files.length > MAX_SAHA_FOTO) {
                Swal.fire('Uyarı', `En fazla ${MAX_SAHA_FOTO} saha fotoğrafı ekleyebilirsiniz. (Mevcut: ${mevcutYuklu + kacakSeciliFotolar.length}, Eklenmek istenen: ${files.length})`, 'warning');
            }

            for (const f of files) {
                if (mevcutYuklu + kacakSeciliFotolar.length >= MAX_SAHA_FOTO) break;
                kacakSeciliFotolar.push(f);
            }
            this.value = '';
            yeniFotolariCiz();
        });

        function yeniFotolariCiz() {
            const $container = $('#yeniFotolarPreview');
            $container.empty();
            kacakSeciliFotolar.forEach((file, idx) => {
                const objUrl = URL.createObjectURL(file);
                $container.append(`
                    <div class="kacak-foto-item text-center">
                        <img src="${objUrl}" class="kacak-foto-thumb" loading="lazy" alt="${esc(file.name)}">
                        <button type="button" class="btn btn-danger btn-sm btn-yeni-foto-sil" data-idx="${idx}" title="Kaldır">
                            <i class="bx bx-x"></i>
                        </button>
                        <div class="small text-muted mt-1 text-truncate" style="max-width:92px;" title="${esc(file.name)}">${esc(file.name)}</div>
                        <span class="badge bg-success" style="font-size:.6rem">Yeni Saha</span>
                    </div>
                `);
            });
            badgeGuncelle();
        }

        $(document).on('click', '.btn-yeni-foto-sil', function () {
            const idx = parseInt($(this).data('idx'), 10);
            if (!isNaN(idx) && idx >= 0 && idx < kacakSeciliFotolar.length) {
                kacakSeciliFotolar.splice(idx, 1);
                yeniFotolariCiz();
            }
        });

        // Video seçimi
        $('#kacak_videolar').on('change', async function () {
            const files = Array.from(this.files || []);
            if (!files.length) return;

            const mevcutVideoSayisi = $('#mevcutFotolarListesi .kacak-foto-item a[title="Videoyu oynat"]').length;
            if (mevcutVideoSayisi + kacakSeciliVideolar.length + files.length > MAX_VIDEO) {
                Swal.fire('Uyarı', `En fazla ${MAX_VIDEO} video ekleyebilirsiniz.`, 'warning');
            }

            const kabulEdilen = [];
            const hatalar = [];

            for (const dosya of files) {
                if (mevcutVideoSayisi + kacakSeciliVideolar.length + kabulEdilen.length >= MAX_VIDEO) {
                    break;
                }
                try {
                    kabulEdilen.push(await VideoKontrol.incele(dosya, VIDEO_MAX_SURE, VIDEO_MAX_BYTE));
                } catch (hata) {
                    hatalar.push(`${esc(dosya.name)}: ${esc(hata.message)}`);
                }
            }

            this.value = '';
            kacakSeciliVideolar = kacakSeciliVideolar.concat(kabulEdilen);
            yeniVideolariCiz();

            if (hatalar.length) {
                Swal.fire('Bazı videolar eklenemedi', hatalar.join('<br>'), 'warning');
            }
        });

        function yeniVideolariCiz() {
            const $container = $('#yeniVideolarPreview');
            $container.empty();
            kacakSeciliVideolar.forEach((v, idx) => {
                const kapak = v.kapak
                    ? `<img src="${v.kapak}" class="kacak-foto-thumb" loading="lazy" alt="Video">`
                    : `<div class="kacak-foto-thumb d-flex align-items-center justify-content-center bg-light"><i class="bx bx-video fs-1 text-muted"></i></div>`;
                const sureRozeti = v.sure
                    ? `<span class="badge bg-dark position-absolute bottom-0 end-0 m-1" style="font-size:.65rem">${VideoKontrol.sureBicimle(v.sure)}</span>`
                    : '';
                $container.append(`
                    <div class="kacak-foto-item text-center">
                        <div class="position-relative d-inline-block">
                            ${kapak}
                            <span class="position-absolute top-50 start-50 translate-middle badge rounded-circle bg-dark bg-opacity-75 p-2"><i class="bx bx-play fs-5"></i></span>
                            ${sureRozeti}
                        </div>
                        <button type="button" class="btn btn-danger btn-sm btn-yeni-video-sil" data-idx="${idx}" title="Kaldır">
                            <i class="bx bx-x"></i>
                        </button>
                        <div class="small text-muted mt-1 text-truncate" style="max-width:92px;">Video (${(v.dosya.size / 1048576).toFixed(1)} MB)</div>
                        <span class="badge bg-success" style="font-size:.6rem">Yeni Video</span>
                    </div>
                `);
            });
            badgeGuncelle();
        }

        $(document).on('click', '.btn-yeni-video-sil', function () {
            const idx = parseInt($(this).data('idx'), 10);
            if (!isNaN(idx) && idx >= 0 && idx < kacakSeciliVideolar.length) {
                kacakSeciliVideolar.splice(idx, 1);
                yeniVideolariCiz();
            }
        });

        $('#kacakForm').on('submit', function (e) {
            e.preventDefault();
            kacakFormGonder(false);
        });

        $('#btnKaydetVeOnayla').on('click', function () {
            kacakFormGonder(true);
        });

        // ---------- YAPAY ZEKA ANALİZİ ----------
        $('#btnAnalizEt').on('click', function () {
            const input = document.getElementById('tutanak_file');
            if (!input || input.files.length === 0) {
                return Swal.fire('Uyarı', 'Lütfen önce analiz edilecek tutanak dosyasını seçin.', 'warning');
            }

            const fd = new FormData();
            fd.append('action', 'analyze');
            fd.append('tutanak_file', input.files[0]);
            fd.append('tarih', toIsoDate($('#kacakForm input[name="tarih"]').val()));

            $('#analizSpinner').removeClass('d-none');
            $(this).prop('disabled', true);

            $.ajax({
                url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                $('#kacakSatirlar').empty();
                const satirlar = res.data || [];
                if (satirlar.length === 0) {
                    satirEkle();
                    return Swal.fire('Bilgi', 'Tutanaktan veri çıkartılamadı, lütfen elle doldurun.', 'info');
                }

                satirlar.forEach(s => satirEkle(s));

                if (satirlar[0].tarih) {
                    const p = satirlar[0].tarih.split('-');
                    $('#kacakForm input[name="tarih"]').val(`${p[2]}.${p[1]}.${p[0]}`);
                }

                const pIds = satirlar[0].personel_ids || [];
                if (pIds.length > 0) {
                    $('#kacak_personel_ids').val(pIds.map(String)).trigger('change');
                }

                Swal.fire('Analiz Tamamlandı', res.message, 'success');
            }).fail(() => Swal.fire('Hata', 'Analiz sırasında bir hata oluştu.', 'error'))
                .always(() => {
                    $('#analizSpinner').addClass('d-none');
                    $('#btnAnalizEt').prop('disabled', false);
                });
        });

        // ---------- ONAY / RED ----------
        $(document).on('click', '.btn-onayla', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Bildirimi onayla?',
                text: 'Onaylanan kayıt hakediş ve prim hesabına dahil edilir.',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Onayla', cancelButtonText: 'Vazgeç'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'approve', id: id }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Onaylandı', res.message, 'success');
                    onaylariYukle();
                    kayitlariYukle();
                });
            });
        });

        $(document).on('click', '.btn-reddet', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Bildirimi reddet',
                input: 'textarea',
                inputLabel: 'Red nedeni',
                inputPlaceholder: 'Reddetme nedenini yazın...',
                showCancelButton: true,
                confirmButtonText: 'Reddet', cancelButtonText: 'Vazgeç',
                inputValidator: v => (!v || !v.trim()) ? 'Red nedeni zorunludur.' : undefined
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'reject', id: id, red_nedeni: r.value }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Reddedildi', res.message, 'success');
                    onaylariYukle();
                });
            });
        });

        // ---------- İPTAL ----------
        $(document).on('click', '.btn-iptal', function () {
            const id = $(this).data('id');
            const listeKaydi = kacakKayitlari.find(k => String(k.id) === String(id));
            apiGet({ action: 'get-record', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const k = res.data;

                $('#iptalForm')[0].reset();
                $('#iptal_id').val(k.id);
                $('#iptal_token').val((listeKaydi && listeKaydi.iptal_token) || '');
                $('#iptalTutanakSecimAlani').addClass('d-none');
                $('#iptal_tutanak_sec').prop('disabled', true);
                $('#iptalKayitBilgi').html(
                    `<strong>${esc(k.tarih_formatted)}</strong> &middot; Tutanak No: <strong>${esc(k.tutanak_no || '-')}</strong><br>
                     ${esc(k.abone_adi || '-')} &middot; ${esc(k.ilce || '-')} &middot; ${esc(k.tur)}`
                );
                $('#iptalModal').modal('show');
            });
        });

        if (YETKI.iptalEkle) {
            $('#iptal_tutanak_sec').select2({
                dropdownParent: $('#iptalModal'), width: '100%',
                placeholder: 'Tutanak no, abone veya tarih ile arayın'
            }).on('select2:select', function (e) {
                $('#iptal_token').val(e.params.data.id);
                $('#iptal_id').val('0');
                $('#iptalKayitBilgi').html('<strong>' + esc(e.params.data.text) + '</strong>');
            });

            function iptalAdaylariniYukle() {
                const $secim = $('#iptal_tutanak_sec');
                $secim.prop('disabled', true).empty().append(new Option('Tutanaklar yükleniyor...', '', true, true)).trigger('change');
                return apiGet({ action: 'cancel-candidates', q: '' }).done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    $secim.empty().append(new Option('Tutanak seçiniz', '', true, true));
                    (res.results || []).forEach(k => $secim.append(new Option(k.text, k.id, false, false)));
                    $secim.prop('disabled', false).val('').trigger('change');
                    if (!(res.results || []).length) {
                        $('#iptalKayitBilgi').html('<span class="text-warning">İptale uygun aktif ve onaylanmış tutanak bulunamadı.</span>');
                    }
                }).fail(function (xhr) {
                    let mesaj = 'Tutanaklar yüklenemedi.';
                    try { mesaj = JSON.parse(xhr.responseText).message || mesaj; } catch (e) {}
                    $secim.empty().append(new Option('Tutanaklar yüklenemedi', '', true, true)).prop('disabled', true).trigger('change');
                    Swal.fire('Hata', mesaj, 'error');
                });
            }

            $('#btnYeniIptal').on('click', function () {
                $('#iptalForm')[0].reset();
                $('#iptal_id').val('0');
                $('#iptal_token').val('');
                $('#iptal_tutanak_sec').val(null).trigger('change');
                $('#iptal_tutanak_sec').prop('disabled', false);
                $('#iptalKayitBilgi').html('<span class="text-muted">İptal edilecek tutanağı aşağıdan seçin.</span>');
                $('#iptalTutanakSecimAlani').removeClass('d-none');
                $('#iptalModal').modal('show');
                iptalAdaylariniYukle();
            });
        }

        $('#iptalForm').on('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            if (!$('#hakedisten_dus').is(':checked')) fd.set('hakedisten_dus', '0');

            const btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true);

            $.ajax({ url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    $('#iptalModal').modal('hide');
                    Swal.fire('İptal Edildi', res.message, 'success');
                    kayitlariYukle();
                    iptalleriYukle();
                }).fail(() => Swal.fire('Hata', 'İptal işlemi başarısız.', 'error'))
                .always(() => btn.prop('disabled', false));
        });

        $(document).on('click', '.btn-iptal-geri', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'İptali geri al?',
                text: 'Kayıt yeniden aktif hâle gelecek ve hakedişte sayılacak.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Geri Al', cancelButtonText: 'Vazgeç'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'revert-cancel', id: id }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Başarılı', res.message, 'success');
                    iptalleriYukle();
                    kayitlariYukle();
                });
            });
        });

        // ---------- SİLME ----------
        $(document).on('click', '.btn-sil', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Kayıt silinsin mi?',
                text: 'Bu işlem geri alınamaz.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#f46a6a'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'delete', id: id }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Silindi', res.message, 'success');
                    kayitlariYukle();
                });
            });
        });

        // ---------- FOTOĞRAFLAR ----------
        function fotolariBas($hedef, fotolar, kacakId) {
            $hedef.empty();
            if (!fotolar || fotolar.length === 0) {
                if (kacakFotoLightbox) {
                    kacakFotoLightbox.destroy();
                    kacakFotoLightbox = null;
                }
                $hedef.html('<p class="text-muted mb-0">Bu kayıt için yüklü belge bulunmuyor.</p>');
                return;
            }

            const turEtiket = { tutanak: 'Tutanak', saha: 'Saha', iptal: 'İptal Belgesi' };

            function zamanMetni(deger) {
                if (!deger) return '';
                const t = new Date(String(deger).replace(' ', 'T'));
                if (isNaN(t.getTime())) return '';
                const iki = s => String(s).padStart(2, '0');
                return `${iki(t.getDate())}.${iki(t.getMonth() + 1)}.${t.getFullYear()} ${iki(t.getHours())}:${iki(t.getMinutes())}`;
            }

            function gecikmeDakika(f) {
                if (!f || !f.cekim_tarihi || !f.olusturma_tarihi) return null;
                const bas = new Date(String(f.cekim_tarihi).replace(' ', 'T'));
                const son = new Date(String(f.olusturma_tarihi).replace(' ', 'T'));
                if (isNaN(bas.getTime()) || isNaN(son.getTime())) return null;
                return Math.round((son - bas) / 60000);
            }

            function farkMetni(dakika) {
                if (dakika === null) return null;
                if (dakika < 0) return { metin: 'çekimden önce yüklenmiş', renk: 'bg-danger' };
                if (dakika <= CEKIM_GECIKME_DK) return { metin: dakika + ' dk sonra', renk: 'bg-success' };
                if (dakika < 60) return { metin: dakika + ' dk sonra', renk: 'bg-warning text-dark' };

                const saat = Math.floor(dakika / 60);
                if (saat < 24) return { metin: saat + ' sa ' + (dakika % 60) + ' dk sonra', renk: saat < 8 ? 'bg-warning text-dark' : 'bg-danger' };
                return { metin: Math.floor(saat / 24) + ' gün ' + (saat % 24) + ' sa sonra', renk: 'bg-danger' };
            }

            // Personelin fotoğrafı çektiği an ile sisteme yüklediği an arasındaki gecikme.
            function cekimSatiri(f) {
                const yukleme = zamanMetni(f.olusturma_tarihi);
                if (!f.cekim_tarihi) {
                    return `<div class="small text-muted" style="font-size:.7rem" title="Fotoğrafın çekim bilgisi bulunamadı">
                        <i class="bx bx-time"></i> Yükleme ${esc(yukleme)}<br><span class="text-muted">Çekim bilgisi yok</span></div>`;
                }

                const fark = farkMetni(gecikmeDakika(f));
                const kaynak = f.cekim_kaynak === 'exif' ? 'Fotoğraf meta verisi' : 'Cihazdaki dosya tarihi';
                const rozet = fark
                    ? `<span class="badge ${fark.renk}" style="font-size:.6rem">${esc(fark.metin)}</span>`
                    : '';

                return `<div class="small text-muted" style="font-size:.7rem" title="${esc(kaynak)}">
                    <i class="bx bx-camera"></i> Çekim ${esc(zamanMetni(f.cekim_tarihi))}<br>
                    <i class="bx bx-upload"></i> Yükleme ${esc(yukleme)}<br>${rozet}</div>`;
            }

            fotolar.forEach(f => {
                const url = 'views/kacak/foto-goruntule.php?id=' + f.id;
                const kucukUrl = url + '&boyut=kucuk';
                const pdfMi = /\.pdf$/i.test(f.dosya_yolu);
                const videoMu = f.medya_tipi === 'video';

                const gecikme = gecikmeDakika(f);
                const gecikmeSinifi = (gecikme !== null && gecikme > CEKIM_GECIKME_DK) ? ' kacak-gecikme-pulse' : '';

                if (videoMu) {
                    const kapak = f.kucuk_yol
                        ? `<img src="${kucukUrl}" class="kacak-foto-thumb${gecikmeSinifi}" loading="lazy" alt="Video">`
                        : `<div class="kacak-foto-thumb${gecikmeSinifi} d-flex align-items-center justify-content-center bg-light"><i class="bx bx-video fs-1 text-muted"></i></div>`;
                    const sureRozeti = f.sure_saniye
                        ? `<span class="badge bg-dark position-absolute bottom-0 end-0 m-1" style="font-size:.65rem">${VideoKontrol.sureBicimle(f.sure_saniye)}</span>`
                        : '';
                    const silVideoBtn = YETKI.arsiv
                        ? `<button type="button" class="btn btn-danger btn-sm btn-foto-sil" data-foto-id="${f.id}" data-kacak-id="${kacakId}" title="Sil"><i class="bx bx-x"></i></button>`
                        : '';

                    $hedef.append(`
                        <div class="kacak-foto-item text-center">
                            <a href="${url}" target="_blank" rel="noopener" class="position-relative d-inline-block" title="Videoyu oynat">
                                ${kapak}
                                <span class="position-absolute top-50 start-50 translate-middle badge rounded-circle bg-dark bg-opacity-75 p-2"><i class="bx bx-play fs-5"></i></span>
                                ${sureRozeti}
                            </a>
                            ${silVideoBtn}
                            <div class="small text-muted mt-1">Video</div>
                            ${cekimSatiri(f)}
                        </div>`);
                    return;
                }

                const onizleme = pdfMi
                    ? `<div class="kacak-foto-thumb${gecikmeSinifi} d-flex align-items-center justify-content-center bg-light"><i class="bx bxs-file-pdf fs-1 text-danger"></i></div>`
                    : `<img src="${kucukUrl}" class="kacak-foto-thumb${gecikmeSinifi}" loading="lazy" alt="${esc(turEtiket[f.tur] || f.tur)}">`;

                const silBtn = YETKI.arsiv
                    ? `<button type="button" class="btn btn-danger btn-sm btn-foto-sil" data-foto-id="${f.id}" data-kacak-id="${kacakId}" title="Sil"><i class="bx bx-x"></i></button>`
                    : '';

                const indirBtn = `<a href="${url}&indir=1" class="btn btn-secondary btn-sm btn-foto-indir" title="${pdfMi ? 'Belgeyi İndir' : 'Fotoğrafı İndir (JPEG)'}" download><i class="bx bx-download"></i></a>`;

                $hedef.append(`
                    <div class="kacak-foto-item text-center">
                        <a href="${url}" ${pdfMi
                            ? 'target="_blank" rel="noopener"'
                            : `class="kacak-foto-lightbox" data-gallery="kacak-${kacakId}" data-type="image" data-title="${esc(turEtiket[f.tur] || f.tur)}"`}>${onizleme}</a>
                        ${indirBtn}
                        ${silBtn}
                        <div class="small text-muted mt-1">${esc(turEtiket[f.tur] || f.tur)}</div>
                        ${cekimSatiri(f)}
                    </div>`);
            });

            if (kacakFotoLightbox) kacakFotoLightbox.destroy();
            kacakFotoLightbox = GLightbox({
                selector: '.kacak-foto-lightbox',
                loop: true,
                touchNavigation: true
            });
        }

        $(document).on('show.bs.collapse', '#collapseFotolar', function () {
            const $target = $('#mevcutFotolarListesi');
            if (!$target.data('loaded')) {
                const fotolar = $target.data('fotolar') || [];
                const kacakId = $target.data('kacak-id') || 0;
                if (fotolar.length > 0) {
                    fotolariBas($target, fotolar, kacakId);
                }
                $target.data('loaded', true);
            }
        });

        $(document).on('click', '.btn-foto', function () {
            const id = $(this).data('id');
            const mevcut = parseInt($(this).attr('data-mevcut') || '0', 10);
            const beklenen = parseInt($(this).attr('data-beklenen') || '0', 10);
            if (beklenen > mevcut) {
                Swal.fire({
                    icon: 'info',
                    title: 'Fotoğraf yüklemesi sürüyor',
                    text: `${beklenen - mevcut} fotoğrafın daha yüklenmesi bekleniyor. Şu anda gelen ${mevcut} fotoğraf gösterilecek.`,
                    timer: 2800,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
            $('#btnFotoModalZip').attr('href', API + '?action=download-zip&id=' + id);
            apiGet({ action: 'get-photos', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                fotolariBas($('#fotoModalIcerik'), res.data, id);
                if ($('#fotoModalIcerik .kacak-foto-lightbox').length && kacakFotoLightbox) {
                    kacakFotoLightbox.open();
                } else {
                    // Yalnızca PDF belge varsa indirme/silme işlemleri için belge modalını göster.
                    $('#fotoModal').modal('show');
                }
            });
        });

        $(document).on('click', '.btn-foto-sil', function () {
            const fotoId = $(this).data('foto-id');
            const kacakId = $(this).data('kacak-id');
            const $item = $(this).closest('.kacak-foto-item');

            Swal.fire({
                title: 'Fotoğraf silinsin mi?',
                text: 'Dosya sunucudan kalıcı olarak silinecek.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#f46a6a'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'delete-photo', foto_id: fotoId }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    $item.remove();
                    badgeGuncelle();
                    if (kacakFotoLightbox) kacakFotoLightbox.reload();
                    kayitlariYukle();
                });
            });
        });

        // ---------- GÜNLÜK RAPOR ----------
        $('#btnGunlukRapor').on('click', function () {
            apiGet({ action: 'gunluk-rapor', tarih: toIsoDate($('#gunluk_tarih').val()) }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                $('#gunlukRaporMetin').text(res.metin);
            });
        });

        $('#btnGunlukKopyala').on('click', function () {
            const metin = $('#gunlukRaporMetin').text();
            navigator.clipboard.writeText(metin).then(
                () => Swal.fire({ icon: 'success', title: 'Kopyalandı', timer: 1200, showConfirmButton: false }),
                () => Swal.fire('Hata', 'Panoya kopyalanamadı.', 'error')
            );
        });

        // ---------- HAFTALIK RAPOR ----------
        $('#btnHaftalikRapor').on('click', function () {
            apiGet({
                action: 'haftalik-rapor',
                start_date: toIsoDate($('#haftalik_baslangic').val()),
                end_date: toIsoDate($('#haftalik_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                const tbody = $('#haftalikTable tbody').empty();
                let tA = 0, tK = 0, tU = 0;

                (res.data || []).forEach(r => {
                    tA += parseInt(r.abonesiz, 10);
                    tK += parseInt(r.kacak, 10);
                    tU += parseInt(r.usulsuz, 10);
                    tbody.append(`<tr>
                        <td class="fw-semibold">${esc((r.ilce || 'Belirtilmemiş').toLocaleUpperCase('tr-TR'))}</td>
                        <td class="text-end">${esc(r.abonesiz)}</td>
                        <td class="text-end">${esc(r.kacak)}</td>
                        <td class="text-end">${esc(r.usulsuz)}</td>
                        <td class="text-end">${esc(r.toplam)}</td>
                    </tr>`);
                });

                if ((res.data || []).length === 0) {
                    tbody.append('<tr><td colspan="5" class="text-center text-muted">Kayıt bulunamadı.</td></tr>');
                }

                $('#haftalikToplamAbonesiz').text(tA);
                $('#haftalikToplamKacak').text(tK);
                $('#haftalikToplamUsulsuz').text(tU);
                $('#haftalikToplamGenel').text(tA + tK + tU);
            });
        });

        $('#btnHaftalikExcel').on('click', function () {
            const q = $.param({
                tip: 'ozet',
                start_date: toIsoDate($('#haftalik_baslangic').val()),
                end_date: toIsoDate($('#haftalik_bitis').val())
            });
            window.location.href = 'views/kacak/export-haftalik.php?' + q;
        });

        // ---------- TESLİM ALMA LİSTESİ ----------
        let teslimTumTokens = [];

        function syncTeslimSecimCheckboxes() {
            $('#teslimTable .teslim-secim').each(function () {
                $(this).prop('checked', teslimSecilenler.has(this.value));
            });
            const allSelected = teslimTumTokens.length > 0 && teslimTumTokens.every(t => teslimSecilenler.has(t));
            $('#teslimTumunuSec').prop('checked', allSelected);
        }

        function teslimSecimGuncelle() {
            $('#teslimSecimBilgi').text(teslimSecilenler.size + ' kayıt seçildi');
            $('#btnTeslimIslemler').prop('disabled', teslimSecilenler.size === 0);
        }

        $('#btnTeslimListesi').on('click', function () {
            apiGet({
                action: 'teslim-alma-listesi',
                start_date: toIsoDate($('#teslim_baslangic').val()),
                end_date: toIsoDate($('#teslim_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                teslimSecilenler.clear();
                teslimTumTokens = (res.data || []).map(r => r.token);
                $('#teslimTumunuSec').prop('checked', false);
                const rows = (res.data || []).map(r => [
                    '<input type="checkbox" class="form-check-input teslim-secim" value="' + esc(r.token) + '">',
                    esc(r.tarih_formatted),
                    esc(r.tutanak_no),
                    esc(r.abone_adi),
                    esc((r.ilce || '').toLocaleUpperCase('tr-TR')),
                    turBadge(r.tur),
                    esc(r.ekip_adi),
                    esc(r.sebep),
                    r.foto_cikti_gerekli == 1
                        ? '<div class="d-inline-flex align-items-center gap-1"><span class="badge bg-primary">GEREKLİ</span> <button type="button" class="btn btn-xs btn-outline-danger btn-teslim-foto-tekil-pdf" data-token="' + esc(r.token) + '" title="Fotoğraf Çıktısı İndir (PDF)"><i class="bx bxs-file-pdf"></i></button></div>'
                        : '<span class="text-muted">-</span>',
                    r.teslim_alindi == 1
                        ? '<span class="badge bg-success">TESLİM ALINDI</span>'
                        : '<span class="badge bg-warning text-dark">TESLİM ALINMADI</span>'
                ]);

                if (teslimTable) {
                    teslimTable.clear().rows.add(rows).draw();
                } else {
                    teslimTable = $('#teslimTable').DataTable(dtSecenekleri({
                        data: rows, pageLength: 50, order: [[4, 'asc'], [1, 'asc']],
                        columnDefs: [{ targets: 0, orderable: false, searchable: false, className: 'text-center' }]
                    }));
                    $('#teslimTable').on('draw.dt', function () {
                        syncTeslimSecimCheckboxes();
                    });
                }
                syncTeslimSecimCheckboxes();
                teslimSecimGuncelle();
            });
        });

        $('#teslimTable').on('change', '.teslim-secim', function () {
            this.checked ? teslimSecilenler.add(this.value) : teslimSecilenler.delete(this.value);
            syncTeslimSecimCheckboxes();
            teslimSecimGuncelle();
        });

        $('#teslimTumunuSec').on('change', function () {
            const sec = this.checked;
            if (sec) {
                teslimTumTokens.forEach(t => teslimSecilenler.add(t));
            } else {
                teslimSecilenler.clear();
            }
            syncTeslimSecimCheckboxes();
            teslimSecimGuncelle();
        });

        $('#btnTeslimAlindi').on('click', function (e) {
            e.preventDefault();
            if (teslimSecilenler.size === 0) return;
            Swal.fire({title: 'Teslim alındı işaretlensin mi?', text: teslimSecilenler.size + ' kayıt güncellenecek.', icon: 'question', showCancelButton: true, confirmButtonText: 'Evet', cancelButtonText: 'Vazgeç'}).then(result => {
                if (!result.isConfirmed) return;
                $.post(API, {action: 'teslim-alindi-isaretle', tokens: Array.from(teslimSecilenler)}, null, 'json').done(res => {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Tamamlandı', res.message, 'success');
                    $('#btnTeslimListesi').trigger('click');
                }).fail(() => hataGoster());
            });
        });

        $('#btnTeslimFotoPdf').on('click', function (e) {
            e.preventDefault();
            if (teslimSecilenler.size === 0) return;
            const $mainBtn = $('#btnTeslimIslemler');
            const origHtml = $mainBtn.html();
            $mainBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Hazırlanıyor...');

            const formData = new FormData();
            formData.append('tip', 'teslim_foto_pdf');
            formData.append('start_date', toIsoDate($('#teslim_baslangic').val()));
            formData.append('end_date', toIsoDate($('#teslim_bitis').val()));
            teslimSecilenler.forEach(t => formData.append('tokens[]', t));

            fetch('views/kacak/export-haftalik.php', {method: 'POST', body: formData})
                .then(async response => {
                    if (!response.ok) {
                        const errText = await response.text();
                        throw new Error(errText || 'Fotoğraf çıktısı PDF dosyası indirilemedi.');
                    }
                    const disposition = response.headers.get('Content-Disposition');
                    let filename = 'Kacak_Foto_Ciktilari.pdf';
                    if (disposition) {
                        const matches = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"/i.exec(disposition);
                        if (matches) {
                            filename = decodeURIComponent(matches[1] || matches[2]);
                        }
                    }
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(err => {
                    Swal.fire('Bilgi', err.message || 'PDF oluşturulurken bir sorun oluştu.', 'warning');
                })
                .finally(() => {
                    $mainBtn.prop('disabled', teslimSecilenler.size === 0).html(origHtml);
                });
        });

        $('#teslimTable').on('click', '.btn-teslim-foto-tekil-pdf', function (e) {
            e.stopPropagation();
            const token = $(this).data('token');
            if (!token) return;
            const $btn = $(this);
            const origHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" style="width:12px;height:12px;" role="status"></span>');

            const formData = new FormData();
            formData.append('tip', 'teslim_foto_pdf');
            formData.append('start_date', toIsoDate($('#teslim_baslangic').val()));
            formData.append('end_date', toIsoDate($('#teslim_bitis').val()));
            formData.append('tokens[]', token);

            fetch('views/kacak/export-haftalik.php', {method: 'POST', body: formData})
                .then(async response => {
                    if (!response.ok) {
                        const errText = await response.text();
                        throw new Error(errText || 'Fotoğraf çıktısı PDF dosyası indirilemedi.');
                    }
                    const disposition = response.headers.get('Content-Disposition');
                    let filename = 'Kacak_Foto_Ciktisi.pdf';
                    if (disposition) {
                        const matches = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"/i.exec(disposition);
                        if (matches) {
                            filename = decodeURIComponent(matches[1] || matches[2]);
                        }
                    }
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(err => {
                    Swal.fire('Bilgi', err.message || 'PDF oluşturulamadı.', 'warning');
                })
                .finally(() => {
                    $btn.prop('disabled', false).html(origHtml);
                });
        });

        $('#btnTeslimExcel').on('click', function (e) {
            e.preventDefault();
            if (teslimSecilenler.size === 0) return;
            const form = $('<form>', {method: 'POST', action: 'views/kacak/export-haftalik.php'}).appendTo('body');
            $('<input>', {type:'hidden', name:'tip', value:'teslim'}).appendTo(form);
            $('<input>', {type:'hidden', name:'start_date', value:toIsoDate($('#teslim_baslangic').val())}).appendTo(form);
            $('<input>', {type:'hidden', name:'end_date', value:toIsoDate($('#teslim_bitis').val())}).appendTo(form);
            teslimSecilenler.forEach(t => $('<input>', {type:'hidden', name:'tokens[]', value:t}).appendTo(form));
            form.trigger('submit').remove();
        });

        $('#btnTeslimZip').on('click', function (e) {
            e.preventDefault();
            if (teslimSecilenler.size === 0) return;
            const $mainBtn = $('#btnTeslimIslemler');
            const origHtml = $mainBtn.html();
            $mainBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Hazırlanıyor...');

            const formData = new FormData();
            formData.append('tip', 'teslim_zip');
            formData.append('start_date', toIsoDate($('#teslim_baslangic').val()));
            formData.append('end_date', toIsoDate($('#teslim_bitis').val()));
            teslimSecilenler.forEach(t => formData.append('tokens[]', t));
            fetch('views/kacak/export-haftalik.php', {method: 'POST', body: formData})
                .then(async response => {
                    if (!response.ok) {
                        const errText = await response.text();
                        throw new Error(errText || 'ZIP dosyası indirilemedi.');
                    }
                    const disposition = response.headers.get('Content-Disposition');
                    let filename = 'Teslim_Alma_Listesi.zip';
                    if (disposition) {
                        const matches = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"/i.exec(disposition);
                        if (matches) {
                            filename = decodeURIComponent(matches[1] || matches[2]);
                        }
                    }
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(err => {
                    Swal.fire('Hata', err.message || 'İndirme sırasında bir sorun oluştu.', 'error');
                })
                .finally(() => {
                    $mainBtn.prop('disabled', teslimSecilenler.size === 0).html(origHtml);
                });
        });

        // ---------- EKİP ÖZETİ (Özet Raporlar'dan taşındı) ----------
        let ekipOzetYuklendi = false;
        let currentOzetYear = new Date().getFullYear();
        let currentOzetMonth = new Date().getMonth() + 1;

        function ekipOzetiYukle(year, month) {
            const y = year || currentOzetYear;
            const m = month || currentOzetMonth;

            currentOzetYear = y;
            currentOzetMonth = m;

            const $kutu = $('#ekipOzetIcerik');
            $kutu.html('<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Rapor hazırlanıyor...</p></div>');

            $.get('views/puantaj/api.php', {
                action: 'get-report-table',
                tab: 'kacakkontrol',
                year: y,
                month: m,
                filter_type: 'period'
            }).done(function (html) {
                $kutu.html(html);
                ekipOzetYuklendi = true;

                // İç taraftaki varsayılan ikon/legend çubuğunu gizle
                $kutu.find('#workTypeLegend').addClass('d-none');

                // Bölge Toplamı buton durumunu sıfırla
                $('#btnEkipOzetBolgeTopl').removeClass('active btn-warning').addClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Göster');

                if (typeof feather !== 'undefined') {
                    try { feather.replace(); } catch (e) { console.warn('feather.replace error:', e); }
                }
            }).fail(function () {
                $kutu.html('<div class="alert alert-danger mb-0">Rapor yüklenirken bir hata oluştu.</div>');
            });
        }

        // Flatpickr Ay Seçici İlklendirme
        function initOzetDonemPicker() {
            const el = document.getElementById('ozet_donem_picker');
            if (!el) return;

            if (el._flatpickr) {
                el._flatpickr.destroy();
            }

            const pluginFunc = window.monthSelectPlugin || (typeof monthSelectPlugin !== 'undefined' ? monthSelectPlugin : null);

            flatpickr(el, {
                locale: 'tr',
                dateFormat: 'Y-m',
                altInput: true,
                altFormat: 'F Y',
                defaultDate: `${currentOzetYear}-${String(currentOzetMonth).padStart(2, '0')}`,
                plugins: pluginFunc ? [new pluginFunc({ shorthand: false, dateFormat: "Y-m", altFormat: "F Y", theme: "light" })] : [],
                onChange: function (selectedDates, dateStr) {
                    if (dateStr) {
                        const parts = dateStr.split('-');
                        if (parts.length === 2) {
                            currentOzetYear = parseInt(parts[0], 10);
                            currentOzetMonth = parseInt(parts[1], 10);
                            ekipOzetiYukle(currentOzetYear, currentOzetMonth);
                        }
                    }
                }
            });
        }

        $('#btnEkipOzetExcel').on('click', function () {
            const q = $.param({
                tab: 'kacakkontrol',
                year: currentOzetYear,
                month: currentOzetMonth,
                filter_type: 'period'
            });
            window.location.href = 'views/puantaj/rapor-excel.php?' + q;
        });

        // Bölge Toplamları Göster/Gizle Butonu
        $(document).on('click', '#btnEkipOzetBolgeTopl', function () {
            const $reportBtn = $('#btnToggleRegionTotals');
            if ($reportBtn.length) {
                $reportBtn.trigger('click');
                const isHidden = $reportBtn.hasClass('active');
                if (isHidden) {
                    $(this).addClass('active btn-warning').removeClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Gizle');
                } else {
                    $(this).removeClass('active btn-warning').addClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Göster');
                }
            } else {
                const $btn = $(this);
                const $table = $('#raporTable');
                const rows = $table.find('tbody tr[data-region-id]');
                if (rows.length > 0) {
                    const isVisible = rows.first().is(':visible');
                    if (isVisible) {
                        rows.addClass('d-none');
                        $btn.removeClass('active btn-warning').addClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Göster');
                    } else {
                        rows.removeClass('d-none');
                        $btn.addClass('active btn-warning').removeClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Gizle');
                    }
                }
            }
        });

        // Ayarlar Butonu ve Formu
        $(document).on('click', '.btn-tab-settings', function () {
            $('#reportSettingsModal').modal('show');
        });

        $('#reportSettingsForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btnSaveReportSettings');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...');

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            }).done(function (res) {
                if (res.status === 'success') {
                    $('#reportSettingsModal').modal('hide');
                    Swal.fire('Başarılı', res.message, 'success');
                    if (ekipOzetYuklendi) ekipOzetiYukle();
                } else {
                    hataGoster(res);
                }
            }).fail(() => Swal.fire('Hata', 'Ayarlar kaydedilemedi.', 'error'))
                .always(() => btn.prop('disabled', false).text('Ayarları Kaydet'));
        });

        // Gün kutucuğuna çift tıklayınca o tarih + ekip için yeni kayıt modalı
        $(document).on('dblclick', '#ekipOzetIcerik .kacak-quick-cell', function () {
            if (!YETKI.duzenle) return;

            const $h = $(this);
            const tarih = $h.data('date');
            const personelIds = String($h.data('personel-ids') || '')
                .split(',').map(s => s.trim()).filter(Boolean);

            modalSifirla();
            $('#kacakModalTitle').text('Hızlı Kaçak Kontrol Kaydı');

            if (tarih) {
                const p = String(tarih).split('-');
                $('#kacakForm input[name="tarih"]').val(p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : tarih);
            }
            if (personelIds.length) {
                $('#kacak_personel_ids').val(personelIds).trigger('change');
            }

            satirEkle();
            $('#kacakModal').modal('show');
        });

        // ---------- ARŞİV ----------
        $('#btnArsivKontrol').on('click', function () {
            apiGet({
                action: 'archive-preview',
                start_date: toIsoDate($('#arsiv_baslangic').val()),
                end_date: toIsoDate($('#arsiv_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                $('#arsivSonuc').removeClass('d-none')
                    .html(`Seçilen tarih aralığında <b>${res.count}</b> adet dosya bulundu.`);
                $('#btnArsivle').prop('disabled', res.count === 0);
            });
        });

        $('#btnArsivle').on('click', function () {
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu işlem belirtilen tarih aralığındaki tüm fotoğrafları arşivleyip sunucudan kalıcı olarak silecektir!',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Evet, arşivle ve sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#f46a6a'
            }).then(r => {
                if (!r.isConfirmed) return;
                const q = $.param({
                    action: 'archive-download',
                    start_date: toIsoDate($('#arsiv_baslangic').val()),
                    end_date: toIsoDate($('#arsiv_bitis').val())
                });
                window.location.href = API + '?' + q;
                $('#btnArsivle').prop('disabled', true);
                $('#arsivSonuc').html('Arşiv indiriliyor... İndirme tamamlandığında dosyalar sunucudan silinmiş olacaktır.');
            });
        });

        // ---------- SİCİL OLUŞMAYANLAR ----------
        function sicilDurumBadge(s) {
            const map = {
                beklemede: ['bg-danger', 'Ekip Yanıtı Bekleniyor'],
                yanitlandi: ['bg-success', 'Yanıtlandı'],
                cozuldu: ['bg-secondary', 'Çözüldü'],
                iptal: ['bg-dark', 'İptal']
            };
            const [cls, label] = map[s.durum] || ['bg-light text-dark', s.durum || '-'];
            return `<span class="badge ${cls}">${esc(label)}</span>`;
        }

        function sicilBeklemeHucresi(s) {
            const gun = parseInt(s.bekleme_gun || 0, 10);
            if (s.durum !== 'beklemede') {
                return '<span class="text-muted">-</span>';
            }
            let cls = 'bg-light text-dark';
            if (gun >= SICIL_KRITIK_GUN) cls = 'bg-danger';
            else if (gun >= SICIL_UYARI_GUN) cls = 'bg-warning text-dark';
            return `<span class="badge ${cls}">${gun} gün</span>`;
        }

        // Satır durumuna göre yapılabilecek işlemler — hem butonlar hem sağ tık
        // menüsü bu tek listeden üretilir ki ikisi asla ayrışmasın.
        function sicilIslemleri(s) {
            const islemler = [{
                id: s.id, sinif: 'btn-sicil-detay', etiket: 'Detay ve Geçmiş', ikon: 'bx-history',
                ipucu: 'Tüm turların geçmişini, girilen düzeltmeleri ve kapanış notlarını gör',
                cls: 'btn-soft-secondary', renk: 'text-secondary'
            }];

            if (YETKI.sicilYanitla && s.durum === 'beklemede') {
                islemler.push({
                    id: s.id, sinif: 'btn-sicil-yanitla', etiket: 'Düzelt', ikon: 'bx-edit',
                    ipucu: 'Aboneden öğrenilen doğru bilgiyi gir ve kuruma gönder',
                    cls: 'btn-primary', renk: 'text-primary', butonEtiketi: 'Düzelt'
                });
            }
            if (YETKI.sicilBildir && s.durum === 'yanitlandi') {
                islemler.push({
                    id: s.id, sinif: 'btn-sicil-cozuldu', etiket: 'Çözüldü', ikon: 'bx-check',
                    ipucu: 'Sicil oluşturuldu — kaydı kapat, ekibe bilgi gitsin',
                    cls: 'btn-success', renk: 'text-success', butonEtiketi: 'Çözüldü'
                });
                islemler.push({
                    id: s.id, sinif: 'btn-sicil-tekrar', etiket: 'Yeni Tur Aç', ikon: 'bx-revision',
                    ipucu: 'Girilen bilgi de hatalı — bu kaydı kapatıp yeni düzeltme turu başlat',
                    cls: 'btn-soft-danger', renk: 'text-danger'
                });
            }
            if (YETKI.sicilBildir && (s.durum === 'beklemede' || s.durum === 'yanitlandi')) {
                islemler.push({
                    id: s.id, sinif: 'btn-sicil-iptal', etiket: 'Bildirimi İptal Et', ikon: 'bx-x',
                    ipucu: 'Hatalı açılmış bildirimi iptal et — ekipten yanıt beklenmez',
                    cls: 'btn-soft-dark', renk: 'text-dark'
                });
            }

            return islemler;
        }

        function sicilIslemButonlari(s) {
            return islemButonlariCiz(sicilIslemleri(s).filter(i => !i.menuOnly));
        }

        function sicilTutanakHucresi(s) {
            let html = `<span class="fw-semibold">${esc(s.tutanak_no)}</span>`;
            if (!s.kacak_id) {
                html += ` <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Bu tutanak numarası sistemde bulunamadı, kayıtla eşleştirilemedi">Eşleşmedi</span>`;
            }
            return html;
        }

        function siciliYukle() {
            const params = {
                action: 'sicil-list',
                durum: sicilAktifDurum,
                neden: $('#sicil_filtre_neden').val() || '',
                arama: $('#sicil_arama').val() || ''
            };
            const bas = toIsoDate($('#sicil_baslangic').val());
            const bit = toIsoDate($('#sicil_bitis').val());
            if (bas) params.start_date = bas;
            if (bit) params.end_date = bit;

            apiGet(params).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                // Sağ tık menüsü satır verisine ihtiyaç duyar; tablo satırları dizi olduğu
                // için kayıtları ayrıca saklıyoruz.
                sicilKayitlari = res.data || [];

                const rows = (res.data || []).map(s => [
                    sicilTutanakHucresi(s),
                    esc(s.tutanak_tarihi_formatted || '-'),
                    esc(s.abone_adi || '-'),
                    esc(s.ekip_adi || '-'),
                    esc(s.neden_metin || '-'),
                    esc(s.aciklama || '-'),
                    esc(s.bildiren_adi || '-'),
                    sicilBeklemeHucresi(s),
                    `<span class="badge bg-light text-dark">${parseInt(s.tur_sira || 1, 10)}. tur</span>`,
                    sicilDurumBadge(s),
                    sicilIslemButonlari(s)
                ]);

                if (sicilTable) {
                    sicilTable.clear().rows.add(rows).draw();
                } else {
                    sicilTable = $('#sicilTable').DataTable(dtSecenekleri({
                        data: rows, pageLength: 25, order: [[1, 'desc']],
                        columnDefs: [{ targets: [7, 8, 9, 10], orderable: false }],
                        drawCallback: function () { tooltipleriTazele('#sicilTable'); }
                    }));
                }
            });
        }

        function sicilSayaclariYukle() {
            apiGet({ action: 'sicil-counts' }).done(function (res) {
                if (res.status !== 'success') return;
                const c = res.counts || {};
                $('#sicilSayiBeklemede').text(c.beklemede || 0);
                $('#sicilSayiYanitlandi').text(c.yanitlandi || 0);

                // Rozet role duyarlı: kurum kullanıcısı kendi aksiyonunu bekleyeni,
                // ekip/ofis ise yanıtlaması gerekeni görür.
                const bekleyen = YETKI.sicilBildir
                    ? parseInt(c.yanitlandi || 0, 10)
                    : parseInt(c.beklemede || 0, 10);

                $('#sicilBadge').text(bekleyen).toggle(bekleyen > 0);
            });
        }

        $('#sicilAltTabs button').on('click', function () {
            $('#sicilAltTabs button').removeClass('active');
            $(this).addClass('active');
            sicilAktifDurum = $(this).data('sicil-durum');
            siciliYukle();
        });

        $('#btnSicilFiltrele').on('click', siciliYukle);
        $('#sicil_arama').on('keypress', e => { if (e.which === 13) siciliYukle(); });

        // --- Yeni bildirim (kurum kullanıcısı) ---
        function sicilTutanakOzeti(kayit) {
            return `<div class="row g-2 small">
                <div class="col-md-4"><span class="text-muted">Tarih:</span> <strong>${esc(kayit.tarih_formatted || '-')}</strong></div>
                <div class="col-md-4"><span class="text-muted">Abone:</span> <strong>${esc(kayit.abone_adi || '-')}</strong></div>
                <div class="col-md-4"><span class="text-muted">İlçe:</span> <strong>${esc(kayit.ilce || '-')}</strong></div>
                <div class="col-md-4"><span class="text-muted">Ekip:</span> <strong>${esc(kayit.ekip_adi || '-')}</strong></div>
                <div class="col-md-4"><span class="text-muted">Sayaç:</span> <strong>${esc(kayit.sayac_no || '-')}</strong></div>
                <div class="col-md-4"><span class="text-muted">TC:</span> <strong>${esc(kayit.abone_tc || '-')}</strong></div>
            </div>`;
        }

        // kayit verilirse tutanak alanı kilitli ve dolu açılır, verilmezse boş.
        function sicilEksikModalAc(kayit) {
            if (!YETKI.sicilBildir) return;

            $('#sicilEksikForm')[0].reset();
            $('#sicil_neden').val('dogum_tarihi_hatali').trigger('change');
            $('#sicilTutanakBilgi').addClass('d-none').removeClass('alert-warning').addClass('alert-light').empty();

            if (kayit) {
                $('#sicil_kacak_id').val(kayit.id);
                $('#sicil_tutanak_no').empty()
                    .append(new Option(kayit.tutanak_no, kayit.tutanak_no, true, true))
                    .trigger('change');
                $('#sicilTutanakBilgi').removeClass('d-none').html(sicilTutanakOzeti(kayit));
            } else {
                $('#sicil_kacak_id').val('');
                $('#sicil_tutanak_no').val(null).trigger('change');
            }

            new bootstrap.Modal('#sicilEksikModal').show();
        }

        // Kayıtlar / Bekleyen Onaylar sekmesinden gelen bildirim başlatma.
        function sicilEksikBildirimBaslat(kacakId) {
            if (!YETKI.sicilBildir) return;

            apiGet({ action: 'get-record', id: kacakId }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const k = res.data;

                if (!k.tutanak_no) {
                    return Swal.fire('Tutanak Numarası Yok',
                        'Bu kaydın tutanak numarası girilmemiş. Sicil bildirimi tutanak numarası üzerinden yapılır.', 'warning');
                }
                if (k.sicil_durumu === 'eksik' || k.sicil_durumu === 'yanitlandi') {
                    return Swal.fire('Açık Kayıt Var',
                        'Bu tutanak için halihazırda açık bir sicil eksik bildirimi var. Sicil Oluşmayanlar sekmesinden takip edin.', 'info');
                }

                sicilEksikModalAc(k);
            });
        }

        $('#btnYeniSicilEksik').on('click', () => sicilEksikModalAc(null));

        $(document).on('click', '.btn-sicil-bildir', function () {
            sicilEksikBildirimBaslat($(this).data('id'));
        });

        $(document).on('click', '.btn-sicil-git', function () {
            $('#sicilAltTabs button').removeClass('active');
            $('#sicilAltTabs button[data-sicil-durum="beklemede"]').addClass('active');
            sicilAktifDurum = 'beklemede';
            $('#kacakTabs button[data-bs-target="#pane-sicil"]').tab('show');
            siciliYukle();
        });

        // --- Sağ tık menüsü ---
        let sagTikMenu = null;

        function sagTikKapat() {
            if (sagTikMenu) {
                sagTikMenu.remove();
                sagTikMenu = null;
            }
        }

        /**
         * ogeler: [{ etiket, ikon, renk, aciklama, calistir }]
         */
        function sagTikMenuAc(e, baslik, ogeler) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            sagTikKapat();
            if (!ogeler || !ogeler.length) return;

            const menu = $('<div class="dropdown-menu show shadow border" style="z-index:1080; max-width:280px;"></div>');
            menu.append(`<h6 class="dropdown-header">${esc(baslik)}</h6>`);

            ogeler.forEach(o => {
                const $btn = $(`<button class="dropdown-item d-flex align-items-start gap-2" type="button">
                        <i class="bx ${o.ikon} ${o.renk || ''}" style="margin-top:.15rem"></i>
                        <span>
                            <span class="d-block">${esc(o.etiket)}</span>
                            ${o.aciklama ? `<small class="text-muted" style="white-space:normal">${esc(o.aciklama)}</small>` : ''}
                        </span>
                    </button>`);
                $btn.on('click', function () {
                    sagTikKapat();
                    o.calistir();
                });
                menu.append($btn);
            });

            menu.appendTo('body');

            // Tema CSS'i ".dropdown-menu{top:100%!important}" tanımlıyor; position:fixed
            // ile bu, menüyü ekranın tam altına (viewport yüksekliği kadar aşağı) itip
            // görünmez yapıyor. Bu yüzden konum satır içi !important ile veriliyor.
            const stil = menu[0].style;
            const sol = Math.max(4, Math.min(e.clientX, $(window).width() - menu.outerWidth() - 8));
            const ust = Math.max(4, Math.min(e.clientY, $(window).height() - menu.outerHeight() - 8));

            stil.setProperty('position', 'fixed', 'important');
            stil.setProperty('left', sol + 'px', 'important');
            stil.setProperty('top', ust + 'px', 'important');
            stil.setProperty('right', 'auto', 'important');
            stil.setProperty('bottom', 'auto', 'important');

            sagTikMenu = menu;
        }

        /**
         * İşlemi çalıştırır. Menüden tetiklenirken satırdaki butona güvenilemez:
         * DataTables responsive modda dar ekranda son kolonu (işlem kolonu) gizleyip
         * alt satıra taşıyor, buton DOM'da olmuyor. Bu yüzden butonun aynısını geçici
         * olarak body'ye ekleyip tetikliyoruz; delege click handler'ları yakalıyor.
         */
        function islemiCalistir(islem) {
            const $gecici = $('<button type="button"></button>')
                .addClass(islem.sinif)
                .attr('data-id', islem.id)
                .css({ position: 'fixed', left: '-9999px', top: '-9999px' });

            $.each(islem.veri || {}, function (ad, deger) {
                $gecici.attr('data-' + ad, deger);
            });

            $gecici.appendTo('body').trigger('click');
            $gecici.remove();
        }

        function islemleriMenuyeCevir(islemler) {
            return islemler.map(i => ({
                etiket: i.etiket,
                ikon: i.ikon,
                renk: i.renk,
                aciklama: i.ipucu,
                calistir: () => islemiCalistir(i)
            }));
        }

        /**
         * Sağ tıklanan satırın kaydını verir. Satır sırası kaynak dizinin sırasıyla
         * birebir aynı olduğu için DataTables satır indeksi üzerinden buluyoruz;
         * bu yol gizlenmiş kolonlardan etkilenmez.
         */
        function satirKaydi(tablo, $satir, kayitlar) {
            if (!tablo) return null;

            let el = ($satir && $satir.jquery) ? $satir.get(0) : $satir;
            if (!el) return null;

            // Responsive alt satırına tıklandıysa asıl satıra çık.
            if (el.classList && el.classList.contains('child')) {
                const prev = el.previousElementSibling;
                if (prev) el = prev;
            }

            try {
                const satir = tablo.row(el);
                if (satir && satir.any()) {
                    const veri = satir.data();

                    // Nesne tabanlı tablolarda (ör. kacakTable)
                    if (veri && typeof veri === 'object' && !Array.isArray(veri)) {
                        return veri;
                    }

                    // Dizi tabanlı tablolarda (ör. onayTable, sicilTable)
                    const indeks = satir.index();
                    if (kayitlar && indeks !== undefined && indeks !== null && kayitlar[indeks]) {
                        return kayitlar[indeks];
                    }
                }
            } catch (err) {
                console.warn('satirKaydi hatası:', err);
            }

            // Fallback: Orijinal diziden rowIndex - 1 ile al
            if (kayitlar && el.rowIndex !== undefined && el.rowIndex > 0) {
                const idx = el.rowIndex - 1;
                if (kayitlar[idx]) return kayitlar[idx];
            }

            return null;
        }

        // Kayıtlar
        $(document).on('contextmenu', '#kacakTable tbody tr', function (e) {
            e.preventDefault();
            const k = satirKaydi(kacakTable, this, kacakKayitlari);
            if (!k) return;

            sagTikMenuAc(e, (k.tutanak_no || 'Tutanak') + ' — ' + (k.abone_adi || '-'),
                islemleriMenuyeCevir(kayitIslemleri(k)));
        });

        // Bekleyen Onaylar
        $(document).on('contextmenu', '#onayTable tbody tr', function (e) {
            e.preventDefault();
            const k = satirKaydi(onayTable, this, onayKayitlari);
            if (!k) return;

            sagTikMenuAc(e, (k.tutanak_no || 'Tutanak') + ' — ' + (k.bildiren_adi || '-'),
                islemleriMenuyeCevir(onayIslemleri(k)));
        });

        // Sicil Oluşmayanlar
        $(document).on('contextmenu', '#sicilTable tbody tr', function (e) {
            e.preventDefault();
            const s = satirKaydi(sicilTable, this, sicilKayitlari);
            if (!s) return;

            sagTikMenuAc(e, (s.tutanak_no || 'Tutanak') + ' — ' + (s.durum_metin || '-'),
                islemleriMenuyeCevir(sicilIslemleri(s)));
        });

        $(document).on('click', sagTikKapat);
        $(window).on('scroll resize', sagTikKapat);
        $(document).on('keydown', e => { if (e.key === 'Escape') sagTikKapat(); });

        function sicilTutanakSecimiBaslat() {
            if (!$('#sicil_tutanak_no').length) return;

            $('#sicil_tutanak_no').select2({
                dropdownParent: $('#sicilEksikModal'),
                placeholder: 'Tutanak numarası yazın...',
                width: '100%',
                minimumInputLength: 2,
                language: {
                    inputTooShort: () => 'En az 2 karakter girin',
                    searching: () => 'Aranıyor...',
                    noResults: () => 'Tutanak bulunamadı'
                },
                ajax: {
                    url: API,
                    dataType: 'json',
                    delay: 300,
                    data: params => ({ action: 'sicil-tutanak-ara', q: params.term }),
                    processResults: function (res) {
                        return {
                            results: (res.data || []).map(t => ({
                                id: t.tutanak_no,
                                text: t.tutanak_no + ' — ' + (t.abone_adi || 'Abone yok') + ' (' + t.tarih_formatted + ')',
                                kayit: t
                            }))
                        };
                    }
                },
                tags: true,
                createTag: function (params) {
                    const term = $.trim(params.term);
                    if (!term) return null;
                    return { id: term, text: term + ' (sistemde yok, yine de bildir)', yeni: true };
                },
                // Aranan numara sistemde zaten bulunduysa "sistemde yok" seçeneğini gösterme.
                insertTag: function (data, tag) {
                    const varOlan = data.some(d => String(d.id) === String(tag.id));
                    if (!varOlan) {
                        data.push(tag);
                    }
                }
            });

            $('#sicil_tutanak_no').on('select2:select', function (e) {
                const kayit = e.params.data.kayit;
                if (!kayit) {
                    $('#sicil_kacak_id').val('');
                    $('#sicilTutanakBilgi')
                        .removeClass('d-none alert-light')
                        .addClass('alert-warning')
                        .html('<i class="bx bx-error me-1"></i> Bu tutanak numarası sistemde bulunamadı. Bildirim yine de kaydedilir, ofis sonradan eşleştirebilir.');
                    return;
                }

                $('#sicil_kacak_id').val(kayit.id);
                $('#sicilTutanakBilgi')
                    .removeClass('d-none alert-warning')
                    .addClass('alert-light')
                    .html(sicilTutanakOzeti(kayit));
            });
        }

        $('#sicilEksikForm').on('submit', function (e) {
            e.preventDefault();
            const $btn = $(this).find('button[type=submit]');
            $btn.prop('disabled', true);

            $.post(API, $(this).serialize(), null, 'json')
                .done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    bootstrap.Modal.getInstance(document.getElementById('sicilEksikModal')).hide();
                    Swal.fire('Bildirildi', res.message, 'success');
                    siciliYukle();
                    sicilSayaclariYukle();
                    // Kayıtlar/onaylar sekmesindeki sicil işareti güncellensin.
                    if (kacakTable) kacakTable.ajax.reload(null, false);
                    if (onayTable) onaylariYukle();
                })
                .always(() => $btn.prop('disabled', false));
        });

        // --- Düzeltme girişi (ekip / ofis) ---
        $(document).on('click', '.btn-sicil-yanitla', function () {
            const id = $(this).data('id');
            apiGet({ action: 'sicil-detay', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const s = res.data;

                $('#sicilYanitForm')[0].reset();
                $('#sicil_yanit_id').val(s.id);
                $('#sicilYanitTalep').html(`
                    <div class="fw-semibold mb-1"><i class="bx bx-error-circle me-1"></i>${esc(s.tutanak_no)} nolu tutanak — ${esc(s.neden_metin)}</div>
                    ${s.aciklama ? `<div class="small mb-2">${esc(s.aciklama)}</div>` : ''}
                    <div class="small text-muted">
                        Abone: <strong>${esc(s.abone_adi || '-')}</strong> ·
                        TC: <strong>${esc(s.abone_tc || '-')}</strong> ·
                        Doğum: <strong>${esc(s.abone_dogum_tarihi || '-')}</strong> ·
                        Sayaç: <strong>${esc(s.sayac_no || '-')}</strong>
                    </div>`);

                new bootstrap.Modal('#sicilYanitModal').show();
            });
        });

        $('#sicilYanitForm').on('submit', function (e) {
            e.preventDefault();
            const $btn = $(this).find('button[type=submit]');
            $btn.prop('disabled', true);

            $.post(API, $(this).serialize(), null, 'json')
                .done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    bootstrap.Modal.getInstance(document.getElementById('sicilYanitModal')).hide();
                    Swal.fire('Gönderildi', res.message, 'success');
                    siciliYukle();
                    sicilSayaclariYukle();
                })
                .always(() => $btn.prop('disabled', false));
        });

        // --- Kapatma işlemleri (kurum kullanıcısı) ---
        function sicilKapat(id, sonuc, baslik, metin, renk, aciklamaZorunlu) {
            Swal.fire({
                title: baslik,
                text: metin,
                input: 'textarea',
                inputPlaceholder: aciklamaZorunlu ? 'Açıklama (zorunlu)' : 'Açıklama (isteğe bağlı)',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Onayla',
                cancelButtonText: 'Vazgeç',
                confirmButtonColor: renk,
                inputValidator: value => (aciklamaZorunlu && !String(value || '').trim()) ? 'Açıklama zorunludur.' : null
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'sicil-kapat', id: id, sonuc: sonuc, aciklama: r.value || '' }, null, 'json')
                    .done(function (res) {
                        if (res.status !== 'success') return hataGoster(res);
                        Swal.fire('Tamam', res.message, 'success');
                        siciliYukle();
                        sicilSayaclariYukle();
                    });
            });
        }

        $(document).on('click', '.btn-sicil-cozuldu', function () {
            sicilKapat($(this).data('id'), 'cozuldu', 'Sicil oluşturuldu mu?',
                'Kayıt çözüldü olarak kapatılacak ve ekibe bilgi verilecek.', '#34c38f', false);
        });

        $(document).on('click', '.btn-sicil-iptal', function () {
            sicilKapat($(this).data('id'), 'iptal', 'Bildirimi iptal et',
                'Bu bildirim iptal edilecek. Nedenini yazın.', '#f46a6a', true);
        });

        // Düzeltme yine hatalıysa: mevcut kayıt çözüldü kapanır, yeni tur açılır.
        $(document).on('click', '.btn-sicil-tekrar', function () {
            const id = $(this).data('id');
            apiGet({ action: 'sicil-detay', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const s = res.data;

                Swal.fire({
                    title: 'Bilgi yine hatalı mı?',
                    text: 'Mevcut kayıt kapatılıp ' + s.tutanak_no + ' için yeni bir düzeltme turu açılacak.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yeni tur aç',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#f1b44c'
                }).then(r => {
                    if (!r.isConfirmed) return;
                    $.post(API, {
                        action: 'sicil-kapat', id: id, sonuc: 'iptal',
                        aciklama: 'Girilen düzeltme kabul edilmedi, yeni tur açıldı.'
                    }, null, 'json').done(function (kapatRes) {
                        if (kapatRes.status !== 'success') return hataGoster(kapatRes);

                        $('#sicilEksikForm')[0].reset();
                        $('#sicil_kacak_id').val(s.kacak_id || '');
                        $('#sicil_tutanak_no').empty()
                            .append(new Option(s.tutanak_no, s.tutanak_no, true, true))
                            .trigger('change');
                        $('#sicil_neden').val(s.neden).trigger('change');
                        $('#sicilTutanakBilgi').removeClass('d-none').html(
                            `<i class="bx bx-revision me-1"></i> ${esc(s.tutanak_no)} için yeni tur açılıyor.`);
                        new bootstrap.Modal('#sicilEksikModal').show();
                    });
                });
            });
        });

        // --- Detay ve geçmiş ---
        $(document).on('click', '.btn-sicil-detay', function () {
            apiGet({ action: 'sicil-detay', id: $(this).data('id') }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const s = res.data;

                function turKarti(t, aktif) {
                    const d = t.duzeltilen_veri_dizi || {};
                    const alanlar = Object.keys(d).map(k =>
                        `<div><span class="text-muted">${esc(k)}:</span> <strong>${esc(d[k])}</strong></div>`).join('');

                    return `<div class="border rounded p-3 mb-2 ${aktif ? 'border-primary' : ''}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold">${parseInt(t.tur_sira || 1, 10)}. tur — ${esc(t.neden_metin)}</span>
                            ${sicilDurumBadge(t)}
                        </div>
                        ${t.aciklama ? `<div class="small mb-2">${esc(t.aciklama)}</div>` : ''}
                        <div class="small text-muted">Bildiren: ${esc(t.bildiren_adi || '-')}</div>
                        ${t.yanit_veren_adi ? `<div class="small text-muted">Yanıtlayan: ${esc(t.yanit_veren_adi)}</div>` : ''}
                        ${t.yanit_aciklama ? `<div class="small mt-1">Yanıt: ${esc(t.yanit_aciklama)}</div>` : ''}
                        ${alanlar ? `<div class="small mt-2 p-2 bg-light rounded">${alanlar}</div>` : ''}
                        ${t.kapatma_aciklama ? `<div class="small mt-1 text-muted">Kapanış: ${esc(t.kapatma_aciklama)}</div>` : ''}
                    </div>`;
                }

                const gecmis = (s.gecmis || []).map(t => turKarti(t, false)).join('');

                $('#sicilDetayGovde').html(`
                    <div class="mb-3">
                        <h6 class="fw-bold mb-1">${esc(s.tutanak_no)}</h6>
                        <div class="small text-muted">
                            ${esc(s.abone_adi || 'Abone bilgisi yok')} ·
                            ${esc(s.ilce || '-')} · Ekip: ${esc(s.ekip_adi || '-')}
                        </div>
                    </div>
                    ${turKarti(s, true)}
                    ${gecmis ? `<h6 class="fw-bold mt-3 mb-2">Önceki Turlar</h6>${gecmis}` : ''}
                `);

                new bootstrap.Modal('#sicilDetayModal').show();
            });
        });

        // ---------- BAŞLANGIÇ ----------
        $('#btnOnaylaraGit').on('click', function () {
            $('#kacakTabs button[data-bs-target="#pane-onaylar"]').tab('show');
        });

        function tarihAlaniAyarla(alanId, isoTarih) {
            const el = document.getElementById(alanId);
            if (!el || !isoTarih) return;

            if (el._flatpickr) {
                el._flatpickr.setDate(isoTarih, false);
                return;
            }
            const p = String(isoTarih).split('-');
            if (p.length === 3) el.value = p[2] + '.' + p[1] + '.' + p[0];
        }

        // Excel ile eklenen kayıtlar mevcut tarih filtresinin dışında kalırsa
        // liste boş görünmesin diye aralık genişletilir.
        function kayitFiltresiniGenislet(ilkTarih, sonTarih) {
            let degisti = false;

            if (ilkTarih && (!$('#filtre_baslangic').val() || ilkTarih < toIsoDate($('#filtre_baslangic').val()))) {
                tarihAlaniAyarla('filtre_baslangic', ilkTarih);
                degisti = true;
            }
            if (sonTarih && (!$('#filtre_bitis').val() || sonTarih > toIsoDate($('#filtre_bitis').val()))) {
                tarihAlaniAyarla('filtre_bitis', sonTarih);
                degisti = true;
            }

            return degisti;
        }

        // Toplu yükleme sonrası açılmış tüm sekmelerin verisini tazeler.
        function tumSekmeleriTazele() {
            kayitlariYukle();
            if (dashboardYuklendi) dashboardYukle();
            if (onayTable) onaylariYukle();
            if (iptalTable) iptalleriYukle();
            if (ekipOzetYuklendi) ekipOzetiYukle();
            if (sicilTable) siciliYukle();
            if (teslimTable) $('#btnTeslimListesi').trigger('click');
        }

        function excelSonucHtml(res) {
            const atlanan = res.atlanan || [];
            let html = '<div class="text-start">'
                + '<div class="d-flex gap-2 mb-3 justify-content-center flex-wrap">'
                + '<span class="badge bg-success px-3 py-2 fs-6"><i class="bx bx-check-circle me-1"></i>'
                + (res.basarili || 0) + ' Yüklendi</span>';

            if (atlanan.length) {
                html += '<span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="bx bx-skip-next-circle me-1"></i>'
                    + atlanan.length + ' Atlandı</span>';
            }
            if (res.teslimIsaretlenen) {
                html += '<span class="badge bg-info px-3 py-2 fs-6"><i class="bx bx-package me-1"></i>'
                    + res.teslimIsaretlenen + ' Teslim Alındı</span>';
            }
            html += '</div>';

            if (atlanan.length) {
                html += '<div class="alert alert-warning py-2 px-3 mb-2 small">'
                    + '<i class="bx bx-info-circle me-1"></i><strong>Yüklenmeyen satırlar</strong> — düzeltip tekrar yükleyebilirsiniz.'
                    + '</div>'
                    + '<div class="table-responsive" style="max-height:300px;overflow-y:auto;">'
                    + '<table class="table table-sm table-bordered table-striped mb-0">'
                    + '<thead class="table-light" style="position:sticky;top:0;">'
                    + '<tr><th style="width:12%">Satır</th><th style="width:28%">Tutanak No</th><th>Neden</th></tr>'
                    + '</thead><tbody>';

                atlanan.forEach(function (a) {
                    html += '<tr><td class="text-center fw-bold">' + esc(a.satir)
                        + '</td><td>' + esc(a.tutanak_no)
                        + '</td><td class="small">' + esc(a.neden) + '</td></tr>';
                });

                html += '</tbody></table></div>'
                    + '<div class="text-end mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="btnAtlananIndir">'
                    + '<i class="bx bx-download me-1"></i>Atlanan satırları CSV indir</button></div>';
            }

            return html + '</div>';
        }

        function atlananCsvIndir(atlanan) {
            const satirlar = [['Satır', 'Tutanak No', 'Neden']].concat(atlanan.map(function (a) {
                return [a.satir, a.tutanak_no, a.neden];
            }));
            const csv = satirlar.map(function (r) {
                return r.map(function (h) {
                    return '"' + String(h === null || h === undefined ? '' : h).replace(/"/g, '""') + '"';
                }).join(';');
            }).join('\r\n');

            const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
            const baglanti = document.createElement('a');
            baglanti.href = URL.createObjectURL(blob);
            baglanti.download = 'kacak_yuklenmeyen_satirlar.csv';
            document.body.appendChild(baglanti);
            baglanti.click();
            document.body.removeChild(baglanti);
            URL.revokeObjectURL(baglanti.href);
        }

        $('#btnKacakExcelGonder').on('click', function () {
            const dosyaAlani = document.getElementById('kacakExcelFile');
            if (!dosyaAlani || !dosyaAlani.files.length) {
                Swal.fire('Uyarı', 'Lütfen bir Excel dosyası seçin.', 'warning');
                return;
            }

            const veri = new FormData(document.getElementById('kacakExcelForm'));
            veri.append('action', 'excel-yukle');

            const $dugme = $(this).prop('disabled', true);
            Swal.fire({
                title: 'Yükleniyor...',
                text: 'Excel dosyası işleniyor, lütfen bekleyin.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: API,
                type: 'POST',
                data: veri,
                contentType: false,
                processData: false,
                dataType: 'json'
            }).done(function (res) {
                const atlanan = (res && res.atlanan) || [];

                if (res && res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('kacakExcelModal'))?.hide();
                    document.getElementById('kacakExcelForm').reset();

                    Swal.fire({
                        icon: atlanan.length ? 'warning' : 'success',
                        title: atlanan.length ? 'Yükleme kısmen tamamlandı' : 'Yükleme tamamlandı',
                        html: excelSonucHtml(res),
                        width: atlanan.length ? '46rem' : '32rem',
                        confirmButtonText: 'Tamam',
                        didOpen: () => {
                            const $indir = $('#btnAtlananIndir');
                            if ($indir.length) {
                                $indir.on('click', () => atlananCsvIndir(atlanan));
                            }
                        }
                    });

                    kayitFiltresiniGenislet(res.ilkTarih, res.sonTarih);
                    tumSekmeleriTazele();
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Yükleme yapılamadı',
                    html: atlanan.length
                        ? '<p class="mb-2">' + esc((res && res.message) || 'Bilinmeyen hata.') + '</p>' + excelSonucHtml(res)
                        : esc((res && res.message) || 'Bilinmeyen hata.'),
                    width: atlanan.length ? '46rem' : '32rem',
                    didOpen: () => {
                        const $indir = $('#btnAtlananIndir');
                        if ($indir.length) {
                            $indir.on('click', () => atlananCsvIndir(atlanan));
                        }
                    }
                });
            }).fail(function () {
                Swal.fire('Hata', 'Sunucuya ulaşılamadı ya da dosya sunucu sınırlarını aşıyor.', 'error');
            }).always(function () {
                $dugme.prop('disabled', false);
            });
        });

        $('#btnFiltrele').on('click', kayitlariYukle);
        $('#btnKayitlarExcel').on('click', function () {
            const params = kayitFiltreleri();
            params.tip = 'kayitlar';
            delete params.action;
            window.location.href = 'views/kacak/export-haftalik.php?' + $.param(params);
        });
        $('#btnIptalFiltrele').on('click', iptalleriYukle);
        $('#btnDashboardFiltrele').on('click', dashboardYukle);
        $('.kd-period').on('click', function () {
            const tur = String($(this).data('days')), bugun = new Date(), bas = new Date(bugun), bit = new Date(bugun);
            if (tur === 'week') bas.setDate(bugun.getDate() - ((bugun.getDay() + 6) % 7));
            else if (tur === 'month') bas.setDate(1);
            else if (tur === 'previous-month') { bas.setMonth(bugun.getMonth() - 1, 1); bit.setDate(0); }
            else bas.setDate(bugun.getDate() - parseInt(tur || '0', 10));
            const yaz = d => String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0') + '.' + d.getFullYear();
            $('#dashboard_baslangic').val(yaz(bas)); $('#dashboard_bitis').val(yaz(bit));
            $('.kd-period').removeClass('active'); $(this).addClass('active'); dashboardYukle();
        });
        $(document).on('click', '.kd-action', function () { $('#kacakTabs button[data-bs-target="' + $(this).data('tab') + '"]').tab('show'); });
        $('#dashboardOnayaGit').on('click', function (e) { e.preventDefault(); $('#kacakTabs button[data-bs-target="#pane-onaylar"]').tab('show'); });
        $('#filtre_arama').on('keypress', e => { if (e.which === 13) kayitlariYukle(); });

        $('#kacakTabs button').on('shown.bs.tab', function () {
            const hedef = $(this).data('bs-target');
            if (hedef === '#pane-kayitlar' && !kacakTable) kayitlariYukle();
            if (hedef === '#pane-dashboard' && !dashboardYuklendi) dashboardYukle();
            if (hedef === '#pane-onaylar' && !onayTable) onaylariYukle();
            if (hedef === '#pane-iptaller' && !iptalTable) iptalleriYukle();
            if (hedef === '#pane-ekip-ozet' && !ekipOzetYuklendi) ekipOzetiYukle();
            if (hedef === '#pane-sicil' && !sicilTable) siciliYukle();
            if (hedef === '#pane-bildirim-personelleri' && !bildirimPersonelTable) loadBildirimPersonelleri();
            if (typeof $.fn.dataTable !== 'undefined') {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
            }
        });

        // =====================================================
        // BİLDİRİM PERSONELLERİ (KASKI) YÖNETİMİ JS
        // =====================================================
        let bildirimPersonelTable = null;

        function loadBildirimPersonelleri() {
            if (bildirimPersonelTable) {
                bildirimPersonelTable.ajax.reload(null, false);
                return;
            }

            bildirimPersonelTable = $('#bildirimPersonelTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: API,
                    type: 'POST',
                    data: { action: 'get_bildirim_personelleri' },
                    dataSrc: function (json) {
                        if (json.status === 'error') {
                            Swal.fire('Hata', json.message || 'Veriler yüklenemedi.', 'error');
                            return [];
                        }
                        return json.data || [];
                    }
                },
                columns: [
                    { data: null, render: (data, type, row, meta) => meta.row + 1 },
                    { data: 'adi_soyadi', className: 'fw-bold text-dark' },
                    { data: 'tc_kimlik_no' },
                    { data: 'cep_telefonu' },
                    { data: 'ekip_bolge' },
                    { data: 'email_adresi' },
                    { data: 'durum_html' },
                    { data: 'tarih' },
                    { data: 'actions', className: 'text-end', orderable: false }
                ],
                order: [[1, 'asc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
                },
                pageLength: 25,
                responsive: true
            });
        }

        window.openNewBildirimPersonelModal = function () {
            $('#formBildirimPersonel')[0].reset();
            $('#bp_token').val('');
            $('#modalBildirimPersonelTitle').text('Yeni Bildirim Personeli');
            $('#bp_sifre_wrapper').show();
            $('#bp_sifre_hint').text('(Boş bırakılırsa 6 haneli rastgele şifre üretilir)');
            window.generateRandomPassword('bp_sifre');
            $('#modalBildirimPersonel').modal('show');
        };

        window.editBildirimPersonel = function (token) {
            $.post(API, { action: 'get_bildirim_personeli', token: token }, function (res) {
                if (res.status === 'success') {
                    const d = res.data;
                    $('#bp_token').val(d.token);
                    $('#bp_adi_soyadi').val(d.adi_soyadi);
                    $('#bp_tc_kimlik_no').val(d.tc_kimlik_no);
                    $('#bp_cep_telefonu').val(d.cep_telefonu);
                    $('#bp_ekip_bolge').val(d.ekip_bolge || '');
                    $('#bp_email_adresi').val(d.email_adresi || '');
                    $('#bp_sifre').val('');
                    $('#bp_sifre_hint').text('(Değiştirmek istemiyorsanız boş bırakın)');
                    $('#modalBildirimPersonelTitle').text('Bildirim Personelini Düzenle');
                    $('#modalBildirimPersonel').modal('show');
                } else {
                    Swal.fire('Hata', res.message || 'Bilgiler alınamadı.', 'error');
                }
            }, 'json');
        };

        window.openSifreModal = function (token, adiSoyadi) {
            $('#formSifreBelirle')[0].reset();
            $('#sifre_token').val(token);
            $('#sifre_personel_adi').text(adiSoyadi);
            window.generateRandomPassword('yeni_sifre');
            $('#modalSifreBelirle').modal('show');
        };

        window.toggleBildirimPersonelStatus = function (token, targetStatus) {
            const isPasifYap = targetStatus === 'pasif';
            const actionText = isPasifYap ? 'pasife almak' : 'aktif etmek';
            Swal.fire({
                title: 'Emin misiniz?',
                text: `Bu personeli ${actionText} istediğinize emin misiniz?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Devam Et',
                cancelButtonText: 'İptal'
            }).then(result => {
                if (result.isConfirmed) {
                    $.post(API, { action: 'toggle_bildirim_personel_status', token: token }, function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Başarılı', res.message, 'success');
                            if (bildirimPersonelTable) bildirimPersonelTable.ajax.reload(null, false);
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        };

        window.deleteBildirimPersonel = function (token, adiSoyadi) {
            Swal.fire({
                title: 'Personeli Sil?',
                text: `${adiSoyadi} isimli bildirim personelini sistemden silmek istediğinize emin misiniz?`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#d33'
            }).then(result => {
                if (result.isConfirmed) {
                    $.post(API, { action: 'delete_bildirim_personel', token: token }, function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Silindi', res.message, 'success');
                            if (bildirimPersonelTable) bildirimPersonelTable.ajax.reload(null, false);
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        };

        window.generateRandomPassword = function (targetId) {
            const rand = Math.floor(100000 + Math.random() * 900000);
            $('#' + targetId).val(rand);
        };

        $('#formBildirimPersonel').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#btnSaveBildirimPersonel');
            $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');
            const formData = $(this).serialize() + '&action=save_bildirim_personel';

            $.post(API, formData, function (res) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res.status === 'success') {
                    $('#modalBildirimPersonel').modal('hide');
                    if (res.generated_password) {
                        Swal.fire({
                            title: 'Personel Oluşturuldu!',
                            html: `<p>Giriş Şifresi: <strong class="fs-5 text-primary">${res.generated_password}</strong></p><p class="small text-muted">Personel bu şifre ile PWA üzerinden giriş yapabilir.</p>`,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire('Başarılı', res.message, 'success');
                    }
                    if (bildirimPersonelTable) bildirimPersonelTable.ajax.reload(null, false);
                } else {
                    Swal.fire('Hata', res.message || 'Kayıt sırasında hata oluştu.', 'error');
                }
            }, 'json').fail(function () {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                Swal.fire('Hata', 'Sunucu ile iletişim kurulamadı.', 'error');
            });
        });

        $('#formSifreBelirle').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#btnSaveSifre');
            $btn.prop('disabled', true);
            const formData = $(this).serialize() + '&action=reset_bildirim_personel_sifre';

            $.post(API, formData, function (res) {
                $btn.prop('disabled', false);
                if (res.status === 'success') {
                    $('#modalSifreBelirle').modal('hide');
                    Swal.fire('Başarılı', res.message, 'success');
                } else {
                    Swal.fire('Hata', res.message || 'Şifre güncellenemedi.', 'error');
                }
            }, 'json').fail(function () {
                $btn.prop('disabled', false);
                Swal.fire('Hata', 'Sunucu ile iletişim kurulamadı.', 'error');
            });
        });

        $(document).on('shown.bs.modal', '.modal', function () {
            if (typeof feather !== 'undefined') {
                try { feather.replace(); } catch (e) { console.warn('feather.replace error:', e); }
            }
        });

        $(function () {
            $('.select2-kacak-personel').select2({
                dropdownParent: $('#kacakModal'),
                placeholder: 'Personel seçin (en fazla 2)',
                maximumSelectionLength: 2,
                width: '100%'
            });

            initOzetDonemPicker();

            kayitlariYukle();

            // Yeni kaçak bildiriminden gelindiyse ilgili onay sekmesini aç.
            const baslangicParametreleri = new URLSearchParams(window.location.search);
            if (baslangicParametreleri.get('tab') === 'onay') {
                $('#kacakTabs button[data-bs-target="#pane-onaylar"]').tab('show');
            } else if (baslangicParametreleri.get('tab') === 'bildirim-personelleri') {
                $('#kacakTabs button[data-bs-target="#pane-bildirim-personelleri"]').tab('show');
            }

            if (YETKI.sicilBildir || YETKI.sicilYanitla) {
                $('#sicil_filtre_neden').select2({ width: '100%' });
                sicilTutanakSecimiBaslat();
                sicilSayaclariYukle();

                // Bildirim linkinden gelindiyse doğrudan sekmeyi aç.
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('tab') === 'sicil') {
                    if (urlParams.get('sicil_id')) sicilAktifDurum = 'yanitlandi';
                    $('#sicilAltTabs button').removeClass('active');
                    $('#sicilAltTabs button[data-sicil-durum="' + sicilAktifDurum + '"]').addClass('active');
                    $('#kacakTabs button[data-bs-target="#pane-sicil"]').tab('show');
                }
            }

            // Tablo dışındaki sabit butonların tooltip'leri.
            tooltipleriTazele(document);

            if (typeof feather !== 'undefined') {
                try { feather.replace(); } catch (e) { console.warn('feather.replace error:', e); }
            }

        });
    })();
</script>
