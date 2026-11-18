<?php

use App\Model\BordroParametreModel;
use App\Helper\Form;

$BordroParametre = new BordroParametreModel();

// Filtre modu (URL parametresinden)
$showAll = isset($_GET['tum_donemler']) && $_GET['tum_donemler'] == '1';

// Bugünün tarihi
$bugun = date('Y-m-d');

// Tüm parametreleri getir (tarih sınırlaması olmadan - admin için)
$tumParametreler = $BordroParametre->getAllParametreler();

// Parametreleri koda göre grupla
function grupla($parametreler)
{
    $gruplu = [];
    foreach ($parametreler as $param) {
        $gruplu[$param->kod][] = $param;
    }
    return $gruplu;
}

$gelirGruplu = grupla(array_filter($tumParametreler, fn($p) => $p->kategori === 'gelir'));
$kesintiGruplu = grupla(array_filter($tumParametreler, fn($p) => $p->kategori === 'kesinti'));

// Bugün geçerli olanları filtrele
function bugunGecerliMi($param, $bugun)
{
    $baslangic = $param->gecerlilik_baslangic;
    $bitis = $param->gecerlilik_bitis;

    if ($baslangic === null && $bitis === null)
        return true;
    if ($baslangic !== null && $baslangic > $bugun)
        return false;
    if ($bitis !== null && $bitis < $bugun)
        return false;
    return true;
}

// Vergi dilimleri için yıl seçimi
$seciliVergiYili = isset($_GET['vergi_yili']) ? intval($_GET['vergi_yili']) : date('Y');

// Genel ayarları dönemlere göre grupla (yarı-yıllık)
function getDonemKey($tarih)
{
    $ay = intval(date('m', strtotime($tarih)));
    $yil = date('Y', strtotime($tarih));
    return $ay <= 6 ? $yil . '_1' : $yil . '_2';
}

function getDonemLabel($key)
{
    list($yil, $yarimYil) = explode('_', $key);
    return $yarimYil == '1' ? "$yil Ocak - Haziran" : "$yil Temmuz - Aralık";
}

$genelAyarlar = $BordroParametre->getAllGenelAyarlarListesi();

// Dönemleri topla
$donemler = [];
if (!empty($genelAyarlar)) {
    foreach ($genelAyarlar as $ayar) {
        if (!empty($ayar->gecerlilik_baslangic)) {
            $donemKey = getDonemKey($ayar->gecerlilik_baslangic);
            if (!in_array($donemKey, $donemler)) {
                $donemler[] = $donemKey;
            }
        }
    }
    usort($donemler, function ($a, $b) {
        return strcmp((string) $b, (string) $a);
    });
}
$seciliDonem = isset($_GET['genel_donem']) ? $_GET['genel_donem'] : (!empty($donemler) ? $donemler[0] : null);
$vergiDilimleri = $BordroParametre->getVergiDilimleri($seciliVergiYili);

$hesaplamaTipleriGelir = [
    'brut' => 'Brüt',
    'net' => 'Net',
    'kismi_muaf' => 'Kısmi Muaf',
    'gunluk_brut' => 'Günlük Brüt',
    'gunluk_net' => 'Günlük Net',
    'gunluk_kismi_muaf' => 'Günlük Kısmi Muaf',
    'aylik_gun_brut' => 'Aylık (Çalışılan Gün) - Brüt',
    'aylik_gun_net' => 'Aylık (Çalışılan Gün) - Net',
    'aylik_fiili_gun_net' => 'Aylık (Fiili Çalışılan Gün) - Net'
];

$hesaplamaTipleriKesinti = [
    'netten' => 'Netten Kesinti',
    'brutten' => 'Brütten Kesinti',
    'sgk_matrahindan' => 'SGK Matrahından',
    'oran_bazli_vergi' => 'Oran (Vergi Matrahı)',
    'oran_bazli_sgk' => 'Oran (SGK Matrahı)',
    'oran_bazli_net' => 'Oran (Net)',
    'gunluk_kesinti' => 'Günlük Kesinti',
    'aylik_gun_kesinti' => 'Aylık (Çalışılan Gün) Kesinti',
    'elden_tutardan' => 'Elden Tutardan Kesinti'
];

$hesaplamaTipleri = array_merge($hesaplamaTipleriGelir, $hesaplamaTipleriKesinti);

$muafLimitTipleri = [
    'yok' => 'Yok',
    'gunluk' => 'Günlük',
    'aylik' => 'Aylık'
];

$kategoriOptions = [
    'gelir' => 'Gelir (Ek Ödeme)',
    'kesinti' => 'Kesinti'
];

$donemOptions = [];
if (!empty($donemler)) {
    foreach ($donemler as $donem) {
        $donemOptions[$donem] = getDonemLabel($donem);
    }
}

$vergiYillariOptions = [];
for ($y = date('Y') + 1; $y >= 2020; $y--) {
    $vergiYillariOptions[$y] = $y;
}
?>

<?php
    $toplamParametre = count($tumParametreler);
    $aktifParametre = 0;
    $gelirSayisi = 0;
    $kesintiSayisi = 0;
    foreach ($tumParametreler as $p) {
        if (bugunGecerliMi($p, $bugun)) {
            $aktifParametre++;
        }
        if ($p->kategori === 'gelir') {
            $gelirSayisi++;
        } else {
            $kesintiSayisi++;
        }
    }
    $filtreliAyarlar = [];
    if ($seciliDonem && !empty($genelAyarlar)) {
        foreach ($genelAyarlar as $ayar) {
            if (!empty($ayar->gecerlilik_baslangic) && getDonemKey($ayar->gecerlilik_baslangic) === $seciliDonem) {
                $filtreliAyarlar[] = $ayar;
            }
        }
    }
    $aktifAyarSayisi = count(array_filter($filtreliAyarlar, fn($a) => !empty($a->aktif)));
    $dilimSayisi = !empty($vergiDilimleri) ? count($vergiDilimleri) : 0;
    $enYuksekOran = 0;
    foreach (($vergiDilimleri ?: []) as $d) {
        $enYuksekOran = max($enYuksekOran, (float) $d->vergi_orani);
    }
    $secimDonemLabel = $seciliDonem ? getDonemLabel($seciliDonem) : '-';
?>
<script>try { document.documentElement.classList.toggle('bordro-param-summary-hidden', localStorage.getItem('bordro_param_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
    #summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
    .bordro-param-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
    @media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
    #paramTabs { gap: 6px; border-bottom: 0; }
    #paramTabs .nav-link { border-radius: 8px; font-size: 13px; font-weight: 600; color: #64748b; padding: 8px 14px; background: #fff; border: 1px solid #e2e8f0; }
    #paramTabs .nav-link.active { background-color: #475569 !important; color: #fff !important; border-color: #475569 !important; }
    .param-filter-bar .form-floating-select2, .param-filter-bar .form-floating { min-width: 220px; }
    html[data-theme-preset="macos-dark"] #paramTabs .nav-link { background: rgba(30, 31, 37, 0.94); border-color: rgba(255, 255, 255, 0.08); color: rgba(235, 238, 245, 0.75); }
    html[data-theme-preset="macos-dark"] #paramTabs .nav-link.active { background-color: #0a84ff; color: #fff; border-color: #0a84ff; }
</style>

<?php
$maintitle = "Bordro";
$title = "Bordro Parametreleri";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-slider-alt fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Bordro Parametreleri</h4>
                <p class="text-muted mb-0 font-size-12">Gelir/kesinti türleri, genel bordro ayarları ve gelir vergisi dilimleri</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnTopYeni" data-bs-toggle="modal" data-bs-target="#modalParametreEkle">
                <i class="bx bx-plus font-size-16"></i> <span id="btnTopYeniLabel">Yeni Parametre</span>
            </button>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">TOPLAM PARAMETRE</span><h3 class="summary-kpi-value my-1"><?= (int) $toplamParametre ?></h3></div>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary"><i class="bx bx-cog"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                        <span class="summary-kpi-subtext">Gelir: <?= (int) $gelirSayisi ?> | Kesinti: <?= (int) $kesintiSayisi ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary px-2 py-0 summary-pill-btn status-quick-filter active" data-kategori="" data-donem="tumu"><i class="bx bx-layer"></i> Tümü</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">BUGÜN GEÇERLİ</span><h3 class="summary-kpi-value my-1 text-success"><?= (int) $aktifParametre ?></h3></div>
                        <div class="summary-kpi-icon bg-success-subtle text-success"><i class="bx bx-check-circle"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                        <span class="summary-kpi-subtext">Pasif: <?= (int) ($toplamParametre - $aktifParametre) ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-success px-2 py-0 summary-pill-btn status-quick-filter" data-kategori="" data-donem="aktif"><i class="bx bx-check"></i> Aktif</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label">GENEL AYAR (SEÇİLİ DÖNEM)</span><h3 class="summary-kpi-value my-1"><?= count($filtreliAyarlar) ?></h3></div>
                        <div class="summary-kpi-icon bg-info-subtle text-info"><i class="bx bx-slider"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                        <span class="summary-kpi-subtext"><?= htmlspecialchars($secimDonemLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= (int) $aktifAyarSayisi ?> aktif</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label"><?= (int) $seciliVergiYili ?> VERGİ DİLİMİ</span><h3 class="summary-kpi-value my-1"><?= (int) $dilimSayisi ?></h3></div>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning"><i class="bx bx-chart"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                        <span class="summary-kpi-subtext">En yüksek oran</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">%<?= number_format($enYuksekOran, 0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3" id="paramTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabParametreler" role="tab" data-add-label="Yeni Parametre" data-add-target="#modalParametreEkle">
                <i class="bx bx-cog me-1"></i> Gelir/Kesinti Parametreleri
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabGenelAyarlar" role="tab" data-add-label="Yeni Ayar" data-add-target="#modalGenelAyarEkle">
                <i class="bx bx-slider me-1"></i> Genel Ayarlar
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabVergiDilimleri" role="tab" data-add-label="Dilim Ekle" data-add-target="#modalVergiDilimiEkle">
                <i class="bx bx-chart me-1"></i> Gelir Vergisi Dilimleri
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane active" id="tabParametreler" role="tabpanel">
            <div class="card summary-kpi-card mb-3" id="parametreListCard">
                <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="bx bx-list-ul font-size-20"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Tüm Gelir ve Kesinti Türleri</h5>
                            <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Parametre tanımları, hesaplama tipleri ve geçerlilik dönemleri</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap param-filter-bar">
                        <?= Form::FormSelect2(
                            name: "filtreDonem",
                            options: [
                                "aktif" => "Sadece Aktif (Bugün Geçerli)",
                                "tumu" => "Tüm Dönemler"
                            ],
                            selectedValue: ($showAll ? "tumu" : "aktif"),
                            label: "Dönem Filtresi",
                            icon: "calendar"
                        ) ?>
                        <?= Form::FormSelect2(
                            name: "filtreKategori",
                            options: [
                                "" => "Tümü",
                                "Gelir" => "Gelir (Ek Ödeme)",
                                "Kesinti" => "Kesinti"
                            ],
                            selectedValue: "",
                            label: "Kategori Filtresi",
                            icon: "filter"
                        ) ?>
                        <button type="button" id="exportExcelParametreler" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" title="Excel'e Aktar">
                            <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 pt-0">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="dtParametreler">
                            <thead>
                                <tr>
                                    <th data-filter="select">KATEGORİ</th>
                                    <th data-filter="string">TÜR KODU</th>
                                    <th data-filter="string">ETİKET (ADI)</th>
                                    <th data-filter="select">HESAPLAMA TİPİ</th>
                                    <th data-filter="string">BAŞLANGIÇ</th>
                                    <th data-filter="string">BİTİŞ</th>
                                    <th data-filter="select" class="text-center">DURUM</th>
                                    <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tumParametreler as $param): ?>
                                    <?php $aktifMi = bugunGecerliMi($param, $bugun); ?>
                                    <tr data-aktif="<?= $aktifMi ? '1' : '0' ?>"
                                        data-kategori="<?= $param->kategori === 'gelir' ? 'Gelir' : 'Kesinti' ?>">
                                        <td>
                                            <?php if ($param->kategori === 'gelir'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-plus-circle me-1"></i>Gelir</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-minus-circle me-1"></i>Kesinti</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge-group"><?= htmlspecialchars($param->kod, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($param->etiket, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if (!empty($param->aciklama)): ?>
                                                <div class="font-size-12 text-secondary"><?= htmlspecialchars($param->aciklama, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge = match ($param->hesaplama_tipi) {
                                                'brut', 'gunluk_brut', 'aylik_gun_brut', 'oran_bazli_sgk' => 'bg-primary-subtle text-primary border-primary-subtle',
                                                'net', 'gunluk_net', 'aylik_gun_net', 'aylik_fiili_gun_net' => 'bg-success-subtle text-success border-success-subtle',
                                                'kismi_muaf', 'gunluk_kismi_muaf', 'sgk_matrahindan', 'elden_tutardan' => 'bg-warning-subtle text-warning border-warning-subtle',
                                                'brutten', 'gunluk_kesinti', 'aylik_gun_kesinti' => 'bg-danger-subtle text-danger border-danger-subtle',
                                                'oran_bazli_vergi' => 'bg-info-subtle text-info border-info-subtle',
                                                default => 'bg-secondary-subtle text-secondary border-secondary-subtle'
                                            };
                                            ?>
                                            <span class="badge <?= $badge ?> border rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <?= htmlspecialchars($hesaplamaTipleri[$param->hesaplama_tipi] ?? ($param->hesaplama_tipi ?: 'Tanımsız'), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if ($param->hesaplama_tipi === 'kismi_muaf' && $param->gunluk_muaf_limit > 0): ?>
                                                <div class="font-size-12 text-primary text-nowrap">Limit: <?= number_format($param->gunluk_muaf_limit, 2, ',', '.') ?> ₺/gün</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?= $param->gecerlilik_baslangic ? date('d.m.Y', strtotime($param->gecerlilik_baslangic)) : '<span class="text-muted">Tüm Dönemler</span>' ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?= $param->gecerlilik_bitis ? date('d.m.Y', strtotime($param->gecerlilik_bitis)) : '<span class="text-muted">Süresiz</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($aktifMi): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-btn-group">
                                                <button class="btn btn-sm btn-subtle-warning table-action-btn btn-edit-param"
                                                    data-id="<?= (int) $param->id ?>"
                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'
                                                    title="Düzenle">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-subtle-primary table-action-btn btn-copy-param"
                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'
                                                    title="Yeni dönem olarak kopyala">
                                                    <i class="bx bx-copy"></i>
                                                </button>
                                                <button class="btn btn-sm btn-subtle-danger table-action-btn btn-delete-param"
                                                    data-id="<?= (int) $param->id ?>"
                                                    data-etiket="<?= htmlspecialchars($param->etiket, ENT_QUOTES, 'UTF-8') ?>" title="Sil">
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
            </div>
        </div>

        <div class="tab-pane" id="tabGenelAyarlar" role="tabpanel">
            <div class="card summary-kpi-card mb-3" id="genelAyarListCard">
                <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="bx bx-slider font-size-20"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Genel Bordro Ayarları</h5>
                            <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;"><?= htmlspecialchars($secimDonemLabel, ENT_QUOTES, 'UTF-8') ?> dönemine ait ayar değerleri</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap param-filter-bar">
                        <?php if (!empty($donemler)): ?>
                            <?= Form::FormSelect2(
                                name: "donemSecimi",
                                options: $donemOptions,
                                selectedValue: $seciliDonem,
                                label: "Dönem",
                                icon: "calendar"
                            ) ?>
                        <?php endif; ?>
                        <button type="button" id="exportExcelGenelAyar" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" title="Excel'e Aktar">
                            <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary bg-white px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalYeniDonem">
                            <i class="bx bx-copy font-size-15 text-primary"></i> <span class="d-none d-sm-inline font-size-12">Yeni Dönem Oluştur</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 pt-0">
                    <?php if (empty($filtreliAyarlar)): ?>
                        <div class="alert alert-warning text-center mb-0">
                            <i class="bx bx-info-circle me-1"></i>
                            <?= empty($donemler) ? 'Henüz genel ayar tanımlanmamış.' : 'Bu dönem için ayar bulunamadı.' ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="overflow-x: auto !important;">
                            <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="dtGenelAyarlar">
                                <thead>
                                    <tr>
                                        <th data-filter="string">AYAR ADI</th>
                                        <th data-filter="string">KOD</th>
                                        <th data-filter="string" class="text-end">DEĞER</th>
                                        <th data-filter="string">GEÇERLİLİK</th>
                                        <th data-filter="none" class="text-center">DURUM</th>
                                        <th data-filter="none" class="text-center" style="width: 100px;">İŞLEMLER</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtreliAyarlar as $ayar): ?>
                                        <tr>
                                            <td class="fw-medium"><?= htmlspecialchars($ayar->parametre_adi, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="badge-group"><?= htmlspecialchars($ayar->parametre_kodu, ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="text-end text-nowrap">
                                                <?php if (strpos($ayar->parametre_kodu, 'orani') !== false): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">%<?= number_format($ayar->deger, 2, ',', '.') ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= number_format($ayar->deger, 2, ',', '.') ?> ₺</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?= date('d.m.Y', strtotime($ayar->gecerlilik_baslangic)) ?>
                                                <div class="font-size-12 text-secondary"><?= $ayar->gecerlilik_bitis ? date('d.m.Y', strtotime($ayar->gecerlilik_bitis)) : 'Süresiz' ?></div>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input switch-ayar-status" type="checkbox"
                                                        data-id="<?= (int) $ayar->id ?>" <?= $ayar->aktif ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="action-btn-group">
                                                    <button class="btn btn-sm btn-subtle-warning table-action-btn btn-edit-ayar" title="Düzenle"
                                                        data-id="<?= (int) $ayar->id ?>" data-ayar='<?= htmlspecialchars(json_encode($ayar), ENT_QUOTES, 'UTF-8') ?>'>
                                                        <i class="bx bx-edit-alt"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-subtle-danger table-action-btn btn-delete-ayar" title="Sil"
                                                        data-id="<?= (int) $ayar->id ?>"
                                                        data-adi="<?= htmlspecialchars($ayar->parametre_adi, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane" id="tabVergiDilimleri" role="tabpanel">
            <div class="card summary-kpi-card mb-3" id="vergiDilimiListCard">
                <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="bx bx-chart font-size-20"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark"><span id="vergiDilimiBaslik"><?= (int) $seciliVergiYili ?></span> Yılı Gelir Vergisi Dilimleri</h5>
                            <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Kümülatif matrah dilimleri ve vergi oranları</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap param-filter-bar">
                        <?= Form::FormSelect2(
                            name: "vergiYiliSecimi",
                            options: $vergiYillariOptions,
                            selectedValue: $seciliVergiYili,
                            label: "Vergi Yılı",
                            icon: "calendar"
                        ) ?>
                        <button type="button" id="exportExcelVergiDilimleri" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" title="Excel'e Aktar">
                            <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 pt-0">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="dtVergiDilimleri">
                            <thead>
                                <tr>
                                    <th data-filter="string">DİLİM</th>
                                    <th data-filter="string" class="text-end">ALT LİMİT</th>
                                    <th data-filter="string" class="text-end">ÜST LİMİT</th>
                                    <th data-filter="string" class="text-center">VERGİ ORANI</th>
                                    <th data-filter="string">AÇIKLAMA</th>
                                    <th data-filter="none" class="text-center" style="width: 100px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($vergiDilimleri)): ?>
                                    <?php foreach ($vergiDilimleri as $dilim): ?>
                                        <tr>
                                            <td><span class="row-index-badge"><?= (int) $dilim->dilim_no ?>. Dilim</span></td>
                                            <td class="text-end text-nowrap"><?= number_format($dilim->alt_limit, 2, ',', '.') ?> ₺</td>
                                            <td class="text-end text-nowrap">
                                                <?= $dilim->ust_limit ? number_format($dilim->ust_limit, 2, ',', '.') . ' ₺' : '<span class="text-muted">Sınırsız</span>' ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">%<?= number_format($dilim->vergi_orani, 0) ?></span>
                                            </td>
                                            <td class="text-secondary font-size-12"><?= htmlspecialchars($dilim->aciklama ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center">
                                                <div class="action-btn-group">
                                                    <button class="btn btn-sm btn-subtle-warning table-action-btn btn-edit-dilim" title="Düzenle"
                                                        data-dilim='<?= htmlspecialchars(json_encode($dilim), ENT_QUOTES, 'UTF-8') ?>'>
                                                        <i class="bx bx-edit-alt"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-subtle-danger table-action-btn btn-delete-dilim" title="Sil"
                                                        data-id="<?= (int) $dilim->id ?>" data-dilim="<?= (int) $dilim->dilim_no ?>">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>Kümülatif Gelir Vergisi:</strong> Her personelin yılbaşından itibaren toplam gelir
                        vergisi matrahı takip edilir.
                        Aylık maaş bu dilimlerden geçtikçe ilgili dilimin oranı uygulanır.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Parametre Ekle/Düzenle Modal -->
<div class="modal fade" id="modalParametreEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-primary p-3">
                <div class="modal-icon-box">
                    <i class="bx bx-plus"></i>
                </div>
                <div class="modal-title-group">
                    <h5 class="modal-title">Yeni Parametre Ekle</h5>
                    <p class="modal-subtitle">Yeni kayıt oluşturmak için bilgileri doldurun.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formParametre">
                <input type="hidden" name="id" id="param_id">
                <div class="modal-body p-4">
                    <!-- Temel Bilgiler Section -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary-subtle text-primary rounded-circle p-2 me-2">
                                <i class="bx bx-info-circle fs-5"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Temel Bilgiler</h6>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <?= Form::FormFloatInput("text", "kod", "", "Örn: yemek_yardimi", "Kod", "hash", "form-control", true) ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput("text", "etiket", "", "Örn: Yemek Yardımı", "Etiket", "tag", "form-control", true) ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormSelect2(
                                    name: "kategori",
                                    options: $kategoriOptions,
                                    selectedValue: 'gelir',
                                    label: "Kategori",
                                    icon: "grid",
                                    required: true
                                ) ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormSelect2(
                                    name: "hesaplama_tipi",
                                    options: $hesaplamaTipleri,
                                    selectedValue: 'net',
                                    label: "Hesaplama Tipi",
                                    icon: "sliders",
                                    required: true
                                ) ?>
                            </div>
                            <div class="col-md-8">
                                <?= Form::FormSelect2(
                                    name: "odeme_yontemi",
                                    options: [
                                        'banka' => 'Banka Hesabına (Net Maaş Üzerine Eklenecek)',
                                        'elden' => 'Elden Ödeme (Nakit)',
                                        'sodexo' => 'Sodexo / Yemek Kartı',
                                        'diger' => 'Diğer'
                                    ],
                                    selectedValue: 'banka',
                                    label: "Ödeme Türü",
                                    icon: "credit-card",
                                    required: true
                                ) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Hesaplama Ayarları Section -->
                    <div class="mb-4 pt-3 border-top">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-success-subtle text-success rounded-circle p-2 me-2">
                                <i class="bx bx-calculator fs-5"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Hesaplama ve Vergi Ayarları</h6>
                        </div>

                        <div id="muafiyetAyarlari" class="row g-3 mb-3" style="display: none;">
                            <div class="col-md-4">
                                <?= Form::FormSelect2(
                                    name: "muaf_limit_tipi",
                                    options: $muafLimitTipleri,
                                    selectedValue: 'yok',
                                    label: "Muafiyet Tipi",
                                    icon: "shield"
                                ) ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormFloatInput("number", "gunluk_muaf_limit", "0", "0.00", "Günlük Muaf Limit", "dollar-sign", "form-control", false, null, "off", false, 'step="0.01"') ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormFloatInput("number", "aylik_muaf_limit", "0", "0.00", "Aylık Muaf Limit", "dollar-sign", "form-control", false, null, "off", false, 'step="0.01"') ?>
                            </div>
                        </div>

                        <div class="bg-light rounded p-3 mb-3 border border-dashed">
                            <label class="form-label d-block text-muted small fw-bold text-uppercase mb-2">Vergi/SGK
                                Dahil Mi?</label>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="sgk_matrahi_dahil"
                                        name="sgk_matrahi_dahil" value="1">
                                    <label class="form-check-label fw-medium" for="sgk_matrahi_dahil"
                                        id="sgk_matrah_label">SGK Matrahı</label>
                                </div>
                                <div class="form-check custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="gelir_vergisi_dahil"
                                        name="gelir_vergisi_dahil" value="1" checked>
                                    <label class="form-check-label fw-medium" for="gelir_vergisi_dahil"
                                        id="gelir_vergisi_label">Gelir Vergisi</label>
                                </div>
                                <div class="form-check custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="damga_vergisi_dahil"
                                        name="damga_vergisi_dahil" value="1">
                                    <label class="form-check-label fw-medium" for="damga_vergisi_dahil"
                                        id="damga_vergisi_label">Damga Vergisi</label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light rounded p-3 mb-3 border border-dashed" id="icraDahilAyarlari">
                            <label class="form-label d-block text-muted small fw-bold text-uppercase mb-2">İcra
                                Hesaplama</label>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="icra_pirim_dahil"
                                        name="icra_pirim_dahil" value="1">
                                    <label class="form-check-label fw-medium" for="icra_pirim_dahil">İcra Matrahına
                                        Dahil</label>
                                    <div class="text-muted" style="font-size: 11px;">İşaretlenirse bu gelir, icra
                                        kesintisi matrahına eklenir</div>
                                </div>
                                <div class="form-check custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="resmi_alacagina_dahil"
                                        name="resmi_alacagina_dahil" value="1">
                                    <label class="form-check-label fw-medium" for="resmi_alacagina_dahil">Resmi Alacağa Dahil</label>
                                    <div class="text-muted" style="font-size: 11px;">İşaretlenirse bu ek ödeme resmi alacağa eklenir ve yemek bedeli buna göre yeniden hesaplanır</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6" id="divTutar">
                                <?= Form::FormFloatInput("text", "varsayilan_tutar", "0", "0.00", "Varsayılan Tutar", "dollar-sign", "form-control money", false, null, "off", false) ?>
                            </div>
                            <div class="col-md-6" id="divGunlukTutar" style="display: none;">
                                <?= Form::FormFloatInput("text", "gunluk_tutar", "0", "0.00", "Günlük Tutar", "calendar", "form-control money", false, null, "off", false) ?>
                            </div>
                            <div class="col-md-6" id="divOran" style="display: none;">
                                <?= Form::FormFloatInput("number", "oran", "0", "0", "Oran (%)", "percent", "form-control", false, null, "off", false, 'step="0.01"') ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput("number", "sira", "0", "0", "Sıralama", "list", "form-control") ?>
                            </div>
                        </div>
                    </div>

                    <!-- Geçerlilik Ayarları Section -->
                    <div class="mb-4 pt-3 border-top">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-warning-subtle text-warning rounded-circle p-2 me-2">
                                <i class="bx bx-calendar fs-5"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Geçerlilik Tarihleri</h6>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <?= Form::FormFloatInput("date", "gecerlilik_baslangic", "", "", "Geçerlilik Başlangıç", "calendar", "form-control") ?>
                                <div class="text-muted mt-1" style="font-size: 11px;">Boş bırakılırsa tüm dönemler için
                                    geçerli</div>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput("date", "gecerlilik_bitis", "", "", "Geçerlilik Bitiş", "calendar", "form-control") ?>
                                <div class="text-muted mt-1" style="font-size: 11px;">Boş bırakılırsa süresiz geçerli
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Günluk Hesaplama Ayarları -->
                    <div id="gunlukAyarlar" class="mb-4 pt-3 border-top" style="display: none;">
                        <div class="alert alert-info border-0 shadow-sm d-flex mb-3">
                            <i class="bx bx-info-circle fs-4 me-2"></i>
                            <div>
                                <strong>Günlük Bazlı Hesaplama:</strong><br>
                                <span class="small">Tutar = Günlük Tutar × Hesaplanan Gün Sayısı</span>
                            </div>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">GÜN SAYISI HESAPLAMA</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check custom-radio">
                                        <input class="form-check-input" type="radio" id="gun_otomatik"
                                            name="gun_sayisi_otomatik" value="1">
                                        <label class="form-check-label" for="gun_otomatik">Otomatik</label>
                                    </div>
                                    <div class="form-check custom-radio">
                                        <input class="form-check-input" type="radio" id="gun_manuel"
                                            name="gun_sayisi_otomatik" value="0" checked>
                                        <label class="form-check-label" for="gun_manuel">Manuel</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="divVarsayilanGun">
                                <?= Form::FormFloatInput("number", "varsayilan_gun_sayisi", "26", "26", "Varsayılan Gün Sayısı", "calendar", "form-control") ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top">
                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama...", "Açıklama", "message-square", "form-control") ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 px-4 py-3">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none me-auto"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success px-4 py-2 fw-bold shadow-sm">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Yeni Dönem Oluştur Modal -->
<div class="modal fade" id="modalYeniDonem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-info p-3">
                <div class="modal-icon-box">
                    <i class="bx bx-copy"></i>
                </div>
                <div class="modal-title-group">
                    <h5 class="modal-title">Yeni Dönem Oluştur</h5>
                    <p class="modal-subtitle">Mevcut ayarları yeni bir döneme kopyalayarak hızlıca yeni dönem oluşturun.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formYeniDonem">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm d-flex mb-4">
                        <i class="bx bx-info-circle fs-4 me-2"></i>
                        <div class="small">Mevcut ayarları yeni bir döneme kopyalayabilirsiniz. Lütfen değişen değerleri
                            kontrol edip güncelleyin.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <?= Form::FormSelect2(
                                name: "kaynak_donem",
                                options: $donemOptions,
                                selectedValue: $seciliDonem,
                                label: "Kaynak Dönem",
                                icon: "copy",
                                required: true,
                                attributes: 'id="kaynakDonemSecimi"'
                            ) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("date", "yeni_gecerlilik", "", "", "Yeni Başlangıç Tarihi", "calendar", "form-control", true) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("text", "donem_aciklama", "", "Örn: 2026 Temmuz güncellemesi", "Açıklama", "message-square", "form-control") ?>
                        </div>
                    </div>

                    <div class="table-responsive rounded border shadow-sm" style="max-height: 400px;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3" style="width: 40px;">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAllAyar" checked>
                                        </div>
                                    </th>
                                    <th>Ayar Adı</th>
                                    <th>Mevcut Değer</th>
                                    <th style="width: 150px;">Yeni Değer</th>
                                </tr>
                            </thead>
                            <tbody id="donemAyarListesi">
                                <!-- JavaScript ile doldurulacak -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 px-4 py-3">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none me-auto"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info px-4 py-2 fw-bold shadow-sm text-white">
                        <i class="bx bx-copy me-1"></i> Dönemi Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Genel Ayar Ekle Modal -->
<div class="modal fade" id="modalGenelAyarEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-primary p-3">
                <div class="modal-icon-box">
                    <i class="bx bx-slider"></i>
                </div>
                <div class="modal-title-group">
                    <h5 class="modal-title">Genel Ayar Ekle</h5>
                    <p class="modal-subtitle">Yeni bir bordro hesaplama parametresi tanımlayın.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formGenelAyar">
                <input type="hidden" name="id" id="ayar_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <?= Form::FormFloatInput("text", "parametre_kodu", "", "Örn: asgari_ucret_brut", "Parametre Kodu", "hash", "form-control", true) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatInput("text", "parametre_adi", "", "Örn: Asgari Ücret (Brüt)", "Parametre Adı", "tag", "form-control", true) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatInput("text", "deger", "", "0.00", "Değer", "dollar-sign", "form-control money", true, null, "off", false) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatInput("date", "ayar_gecerlilik_baslangic", date('Y-m-d'), "", "Geçerlilik Başlangıç", "calendar", "form-control", true) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatInput("text", "ayar_aciklama", "", "Açıklama...", "Açıklama", "file-text", "form-control") ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 px-4 py-3">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none me-auto"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vergi Dilimi Ekle/Düzenle Modal -->
<div class="modal fade" id="modalVergiDilimiEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-success p-3">
                <div class="modal-icon-box">
                    <i class="bx bx-chart"></i>
                </div>
                <div class="modal-title-group">
                    <h5 class="modal-title">Vergi Dilimi Ekle</h5>
                    <p class="modal-subtitle">Gelir vergisi hesaplama dilimlerini güncelleyin.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVergiDilimi">
                <input type="hidden" name="id" id="dilim_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <?= Form::FormSelect2(
                                name: "dilim_yili",
                                options: $vergiYillariOptions,
                                selectedValue: $seciliVergiYili,
                                label: "Uygulama Yılı",
                                icon: "calendar",
                                required: true,
                                attributes: 'id="dilim_yili"'
                            ) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("number", "dilim_no", "", "Örn: 1", "Dilim Sırası", "hash", "form-control", true, null, "on", false, 'min="1" max="10"') ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "alt_limit", "", "0.00", "Alt Limit (₺)", "dollar-sign", "form-control money", true) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "ust_limit", "", "Boş = Sınırsız", "Üst Limit (₺)", "dollar-sign", "form-control money") ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("number", "vergi_orani", "", "15", "Vergi Oranı (%)", "percent", "form-control", true, null, "on", false, 'min="0" max="100" step="0.01"') ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "dilim_aciklama", "", "Opsiyonel", "Açıklama", "message-square", "form-control") ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 px-4 py-3">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none me-auto"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success px-4 py-2 fw-bold shadow-sm">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Dönem seçimi - sayfa yenile
        $('#donemSecimi').on('change', function () {
            const donem = $(this).val();
            const url = new URL(window.location.href);
            url.searchParams.set('genel_donem', donem);
            window.location.href = url.toString();
        });

        // Vergi yılı seçimi - sayfa yenile
        $('#vergiYiliSecimi').on('change', function () {
            const yil = $(this).val();
            const url = new URL(window.location.href);
            url.searchParams.set('vergi_yili', yil);
            window.location.href = url.toString();
        });
        const hesaplamaTipleriGelir = <?= json_encode($hesaplamaTipleriGelir) ?>;
        const hesaplamaTipleriKesinti = <?= json_encode($hesaplamaTipleriKesinti) ?>;

        // Kategori değişince hesaplama tiplerini güncelle
        $('select[name="kategori"]').on('change', function () {
            const kategori = $(this).val();

            // Header rengini güncelle
            const modalHeader = $('#modalParametreEkle .modal-header');
            if (kategori === 'kesinti') {
                modalHeader.removeClass('modal-header-primary modal-header-success').addClass('modal-header-danger');
            } else {
                modalHeader.removeClass('modal-header-danger modal-header-primary').addClass('modal-header-success');
            }

            const $hesaplamaTipi = $('select[name="hesaplama_tipi"]');
            const currentVal = $hesaplamaTipi.val();

            $hesaplamaTipi.empty();

            let options = kategori === 'gelir' ? hesaplamaTipleriGelir : hesaplamaTipleriKesinti;

            $.each(options, function (key, value) {
                $hesaplamaTipi.append(new Option(value, key));
            });

            // Eğer mevcut değer yeni listede varsa koru, yoksa ilkini seç
            if (options[currentVal]) {
                $hesaplamaTipi.val(currentVal);
            } else {
                $hesaplamaTipi.val(Object.keys(options)[0]);
            }

            $hesaplamaTipi.trigger('change');
        });

        // Hesaplama tipi değişince muafiyet ve oran alanlarını göster/gizle
        $('select[name="hesaplama_tipi"]').on('change', function () {
            const val = $(this).val();
            if (!val) return;
            const isGunluk = val.startsWith('gunluk_');
            const isAylikGun = val.startsWith('aylik_gun_');

            // Kısmi Muaf kontrolü
            if (val === 'kismi_muaf' || val === 'gunluk_kismi_muaf') {
                $('#muafiyetAyarlari').slideDown();
            } else {
                $('#muafiyetAyarlari').slideUp();
            }

            // Günlük veya Aylık (Çalışılan Güne Göre) mi?
            if (isGunluk || isAylikGun) {
                $('#gunlukAyarlar').slideDown();
                if (isGunluk) {
                    $('#divGunlukTutar').slideDown();
                    $('#divTutar').hide();
                } else {
                    $('#divGunlukTutar').hide();
                    $('#divTutar').slideDown(); // Aylık tutar girilecek
                }
                $('#divOran').hide();
            } else {
                $('#gunlukAyarlar').slideUp();
                $('#divGunlukTutar').hide();

                // Oran Bazlı kontrolü
                if (['oran_bazli_vergi', 'oran_bazli_sgk', 'oran_bazli_net'].includes(val)) {
                    $('#divOran').slideDown();
                    $('#divTutar').hide();
                } else {
                    $('#divOran').slideUp();
                    $('#divTutar').show();
                }
            }
        });

        // Gün sayısı radioları değişikliğinde
        $('input[name="gun_sayisi_otomatik"]').on('change', function () {
            if ($(this).val() === '0') {
                $('#divVarsayilanGun').slideDown();
            } else {
                $('#divVarsayilanGun').slideUp();
            }
        });



        // Parametre düzenleme
        $('.btn-edit-param').on('click', function () {
            const param = $(this).data('param');

            $('#param_id').val(param.id);
            $('input[name="kod"]').val(param.kod);
            $('input[name="etiket"]').val(param.etiket);
            $('select[name="kategori"]').val(param.kategori).trigger('change');
            $('select[name="hesaplama_tipi"]').val(param.hesaplama_tipi).trigger('change');
            $('select[name="odeme_yontemi"]').val(param.odeme_yontemi || 'banka').trigger('change');
            $('select[name="muaf_limit_tipi"]').val(param.muaf_limit_tipi).trigger('change');
            $('input[name="gunluk_muaf_limit"]').val(param.gunluk_muaf_limit);
            $('input[name="aylik_muaf_limit"]').val(param.aylik_muaf_limit);
            $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
            $('input[name="oran"]').val(param.oran);
            $('input[name="sira"]').val(param.sira);
            $('input[name="aciklama"]').val(param.aciklama);
            $('input[name="gecerlilik_baslangic"]').val(param.gecerlilik_baslangic);
            $('input[name="gecerlilik_bitis"]').val(param.gecerlilik_bitis);

            $('#sgk_matrahi_dahil').prop('checked', param.sgk_matrahi_dahil == 1);
            $('#gelir_vergisi_dahil').prop('checked', param.gelir_vergisi_dahil == 1);
            $('#damga_vergisi_dahil').prop('checked', param.damga_vergisi_dahil == 1);
            $('#icra_pirim_dahil').prop('checked', param.icra_pirim_dahil == 1);
            $('#resmi_alacagina_dahil').prop('checked', param.resmi_alacagina_dahil == 1);
            $('#icraDahilAyarlari').toggle(param.kategori === 'gelir');

            $('input[name="gunluk_tutar"]').val(param.gunluk_tutar || 0);
            $('input[name="varsayilan_gun_sayisi"]').val(param.varsayilan_gun_sayisi || 26);
            $('input[name="gun_sayisi_otomatik"][value="' + (param.gun_sayisi_otomatik || 0) + '"]').prop('checked', true);

            $('#modalParametreEkle .modal-title').html('<i class="bx bx-edit me-2"></i>Parametre Düzenle');

            /**Kesinti Modalda Labellar değiştir */
            if (param.kategori === 'gelir') {
                $("#sgk_matrah_label").html("SGK Matrahı (Dahil)");
                $("#gelir_vergisi_label").html("Gelir Vergisi (Dahil)");
                $("#damga_vergisi_label").html("Damga Vergisi (Dahil)");
            } else {
                $("#sgk_matrah_label").html("SGK Matrahı (Düşülür)");
                $("#gelir_vergisi_label").html("Gelir Vergisi (Düşülür)");
                $("#damga_vergisi_label").html("Damga Vergisi (Düşülür)");
            }

            $('#modalParametreEkle').modal('show');
        });

        // Parametre kopyalama (yeni dönem için)
        $('.btn-copy-param').on('click', function () {
            const param = $(this).data('param');

            $('#param_id').val(''); // Yeni kayıt olacak
            $('input[name="kod"]').val(param.kod);
            $('input[name="etiket"]').val(param.etiket);
            $('select[name="kategori"]').val(param.kategori).trigger('change');
            $('select[name="hesaplama_tipi"]').val(param.hesaplama_tipi).trigger('change');
            $('select[name="odeme_yontemi"]').val(param.odeme_yontemi || 'banka').trigger('change');
            $('select[name="muaf_limit_tipi"]').val(param.muaf_limit_tipi).trigger('change');
            $('input[name="gunluk_muaf_limit"]').val(param.gunluk_muaf_limit);
            $('input[name="aylik_muaf_limit"]').val(param.aylik_muaf_limit);
            $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
            $('input[name="oran"]').val(param.oran);
            $('input[name="sira"]').val(param.sira);
            $('input[name="aciklama"]').val(param.aciklama);

            // Tarihleri temizle (yeni dönem)
            $('input[name="gecerlilik_baslangic"]').val('');
            $('input[name="gecerlilik_bitis"]').val('');

            $('#sgk_matrahi_dahil').prop('checked', param.sgk_matrahi_dahil == 1);
            $('#gelir_vergisi_dahil').prop('checked', param.gelir_vergisi_dahil == 1);
            $('#damga_vergisi_dahil').prop('checked', param.damga_vergisi_dahil == 1);
            $('#icra_pirim_dahil').prop('checked', param.icra_pirim_dahil == 1);
            $('#resmi_alacagina_dahil').prop('checked', param.resmi_alacagina_dahil == 1);
            $('#icraDahilAyarlari').toggle(param.kategori === 'gelir');

            $('input[name="gunluk_tutar"]').val(param.gunluk_tutar || 0);
            $('input[name="varsayilan_gun_sayisi"]').val(param.varsayilan_gun_sayisi || 26);
            $('input[name="gun_sayisi_otomatik"][value="' + (param.gun_sayisi_otomatik || 0) + '"]').prop('checked', true);

            $('#modalParametreEkle .modal-title').html('<i class="bx bx-copy me-2"></i>Yeni Dönem Ekle (Kopyala)');
            $('#modalParametreEkle').modal('show');
        });

        // Para formatını sayısal değere çeviren yardımcı fonksiyon
        function parseMoney(value) {
            if (!value) return 0;
            // Sadece para birimi sembollerini ve boşlukları temizle, 
            // ayraçları (nokta/virgül) backend'deki Helper::formattedMoneyToNumber'a bırak
            return value.toString().replace(/[₺\s$€TLtryTRY]/g, '').trim();
        }

        // Parametre formu submit
        $('#formParametre').on('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', $('#param_id').val() ? 'update-parametre' : 'add-parametre');

            // Money alanlarını temizle ve sayısal değere çevir
            formData.set('varsayilan_tutar', parseMoney($('input[name="varsayilan_tutar"]').val()));
            formData.set('gunluk_tutar', parseMoney($('input[name="gunluk_tutar"]').val()));

            // Checkbox değerlerini düzelt
            formData.set('sgk_matrahi_dahil', $('#sgk_matrahi_dahil').is(':checked') ? 1 : 0);
            formData.set('gelir_vergisi_dahil', $('#gelir_vergisi_dahil').is(':checked') ? 1 : 0);
            formData.set('damga_vergisi_dahil', $('#damga_vergisi_dahil').is(':checked') ? 1 : 0);
            formData.set('icra_pirim_dahil', $('#icra_pirim_dahil').is(':checked') ? 1 : 0);

            $.ajax({
                url: 'views/bordro/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı!',
                            text: response.message,
                            confirmButtonText: 'Tamam'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Hata!', text: 'Bir hata oluştu.' });
                }
            });
        });

        // Genel Ayar formu submit
        $('#formGenelAyar').on('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', $('#ayar_id').val() ? 'update-genel-ayar' : 'add-genel-ayar');

            // Money alanını temizle ve sayısal değere çevir
            formData.set('deger', parseMoney($('input[name="deger"]').val()));

            $.ajax({
                url: 'views/bordro/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı!',
                            text: response.message,
                            confirmButtonText: 'Tamam'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                    }
                }
            });
        });

        // Ayar düzenleme
        $(document).on('click', '.btn-edit-ayar', function () {
            const ayar = $(this).data('ayar');
            $('#ayar_id').val(ayar.id);
            $('input[name="parametre_kodu"]').val(ayar.parametre_kodu);
            $('input[name="parametre_adi"]').val(ayar.parametre_adi);
            $('input[name="deger"]').val(ayar.deger);
            $('input[name="ayar_gecerlilik_baslangic"]').val(ayar.gecerlilik_baslangic);
            $('input[name="ayar_gecerlilik_bitis"]').val(ayar.gecerlilik_bitis);
            $('#ayar_aktif').prop('checked', ayar.aktif == 1);
            $('input[name="ayar_aciklama"]').val(ayar.aciklama);

            $('#modalGenelAyarEkle .modal-title').html('<i class="bx bx-edit me-2"></i>Ayar Düzenle');
            $('#modalGenelAyarEkle').modal('show');
        });

        // Ayar durum değiştirme
        $(document).on('change', '.switch-ayar-status', function () {
            const id = $(this).data('id');
            const aktif = $(this).is(':checked') ? 1 : 0;

            $.ajax({
                url: 'views/bordro/api.php',
                type: 'POST',
                data: { action: 'toggle-genel-ayar-status', id: id, aktif: aktif },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        Toast.fire({
                            icon: 'success',
                            title: 'Durum güncellendi'
                        });
                        // Satır stilini güncelle
                        const row = $('.switch-ayar-status[data-id="' + id + '"]').closest('tr');
                        if (aktif) {
                            row.removeClass('table-secondary text-muted');
                        } else {
                            row.addClass('table-secondary text-muted');
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                        // Geri al
                        $(this).prop('checked', !aktif);
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Hata!', text: 'Bir hata oluştu.' });
                }
            });
        });

        // Ayar silme
        $(document).on('click', '.btn-delete-ayar', function () {
            const id = $(this).data('id');
            const adi = $(this).data('adi');

            Swal.fire({
                title: 'Silmek istediğinize emin misiniz?',
                html: '<strong>' + adi + '</strong> ayarı silinecek.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'views/bordro/api.php',
                        type: 'POST',
                        data: { action: 'delete-genel-ayar', id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Silindi!',
                                    text: response.message,
                                    confirmButtonText: 'Tamam'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                            }
                        }
                    });
                }
            });
        });

        // Kaynak döneme göre ayarları listele - helper fonksiyon
        function getDonemKeyJs(tarih) {
            const parts = tarih.split('-');
            const ay = parseInt(parts[1]);
            const yil = parts[0];
            return ay <= 6 ? yil + '_1' : yil + '_2';
        }

        function listeleKaynakDonemAyarlari() {
            const genelAyarlar = <?= json_encode($genelAyarlar, JSON_UNESCAPED_UNICODE) ?>;
            const kaynakDonem = $('#kaynakDonemSecimi').val();
            let html = '';

            genelAyarlar.forEach(function (ayar) {
                if (!ayar.gecerlilik_baslangic) return;

                const ayarDonem = getDonemKeyJs(ayar.gecerlilik_baslangic);
                if (ayarDonem !== kaynakDonem) return;

                const isOran = ayar.parametre_kodu.includes('orani');
                const degerStr = isOran ? '%' + parseFloat(ayar.deger).toFixed(2) : parseFloat(ayar.deger).toLocaleString('tr-TR') + ' ₺';

                html += '<tr>';
                html += '<td><input type="checkbox" name="ayar_sec[]" value="' + ayar.id + '" checked class="ayar-checkbox"></td>';
                html += '<td>' + ayar.parametre_adi + '<br><code class="small">' + ayar.parametre_kodu + '</code></td>';
                html += '<td>' + degerStr + '</td>';
                html += '<td><input type="number" step="0.01" class="form-control form-control-sm" ';
                html += 'name="yeni_deger[' + ayar.id + ']" value="' + ayar.deger + '"></td>';
                html += '</tr>';
            });

            if (!html) {
                html = '<tr><td colspan="4" class="text-center text-muted py-3">Bu dönemde ayar bulunamadı.</td></tr>';
            }

            $('#donemAyarListesi').html(html);
        }

        // Yeni Dönem modalı açıldığında mevcut ayarları listele
        $('#modalYeniDonem').on('show.bs.modal', function () {
            listeleKaynakDonemAyarlari();
        });

        // Kaynak dönem değişince listeyi güncelle
        $('#kaynakDonemSecimi').on('change', function () {
            listeleKaynakDonemAyarlari();
        });

        // Tümünü seç/kaldır
        $('#selectAllAyar').on('change', function () {
            $('.ayar-checkbox').prop('checked', $(this).is(':checked'));
        });

        // Yeni Dönem formu submit
        $('#formYeniDonem').on('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'copy-genel-ayarlar');

            $.ajax({
                url: 'views/bordro/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı!',
                            text: response.message,
                            confirmButtonText: 'Tamam'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Hata!', text: 'Bir hata oluştu.' });
                }
            });
        });

        // Modal kapandığında formu sıfırla
        $('#modalParametreEkle').on('hidden.bs.modal', function () {
            $('#formParametre')[0].reset();
            $('#param_id').val('');
            $('#modalParametreEkle .modal-title').html('<i class="bx bx-plus-circle me-2"></i>Yeni Parametre Ekle');
            $('#modalParametreEkle .modal-header').removeClass('bg-danger').addClass('bg-success');
            $('#muafiyetAyarlari').hide();
        });

        // Parametre silme
        $(document).on('click', '.btn-delete-param', function () {
            const id = $(this).data('id');
            const etiket = $(this).data('etiket');

            Swal.fire({
                title: 'Silmek istediğinize emin misiniz?',
                html: '<strong>' + etiket + '</strong> parametresi silinecek.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'views/bordro/api.php',
                        type: 'POST',
                        data: { action: 'delete-parametre', id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Silindi!',
                                    text: response.message,
                                    confirmButtonText: 'Tamam'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                            }
                        },
                        error: function () {
                            Swal.fire({ icon: 'error', title: 'Hata!', text: 'Bir hata oluştu.' });
                        }
                    });
                }
            });
        });
        // Vergi Dilimi formu submit
        $('#formVergiDilimi').on('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', $('#dilim_id').val() ? 'update-vergi-dilimi' : 'add-vergi-dilimi');

            // Money alanlarını temizle ve sayısal değere çevir
            formData.set('alt_limit', parseMoney($('input[name="alt_limit"]').val()));
            formData.set('ust_limit', parseMoney($('input[name="ust_limit"]').val()));

            $.ajax({
                url: 'views/bordro/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı!',
                            text: response.message,
                            confirmButtonText: 'Tamam'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Hata!', text: 'Bir hata oluştu.' });
                }
            });
        });

        // Vergi dilimi düzenleme
        $(document).on('click', '.btn-edit-dilim', function () {
            const dilim = $(this).data('dilim');
            $('#dilim_id').val(dilim.id);
            $('select[name="dilim_yili"]').val(dilim.yil);
            $('input[name="dilim_no"]').val(dilim.dilim_no);
            
            // IMask ile uyumlu değer atama (typedValue float değer kabul eder)
            const altLimitInput = $('input[name="alt_limit"]')[0];
            if (altLimitInput && altLimitInput.imask) {
                altLimitInput.imask.typedValue = parseFloat(dilim.alt_limit || 0);
            } else {
                $('input[name="alt_limit"]').val(dilim.alt_limit);
            }

            const ustLimitInput = $('input[name="ust_limit"]')[0];
            if (ustLimitInput && ustLimitInput.imask) {
                if (dilim.ust_limit) {
                    ustLimitInput.imask.typedValue = parseFloat(dilim.ust_limit);
                } else {
                    ustLimitInput.imask.value = '';
                }
            } else {
                $('input[name="ust_limit"]').val(dilim.ust_limit || '');
            }

            $('input[name="vergi_orani"]').val(dilim.vergi_orani);
            $('input[name="dilim_aciklama"]').val(dilim.aciklama || '');
            $('#modalVergiDilimiEkle .modal-title').html('<i class="bx bx-edit me-2"></i>Vergi Dilimi Düzenle');
            $('#modalVergiDilimiEkle').modal('show');
        });

        // Vergi dilimi silme
        $(document).on('click', '.btn-delete-dilim', function () {
            const id = $(this).data('id');
            const dilimNo = $(this).data('dilim');
            Swal.fire({
                title: 'Silmek istediğinize emin misiniz?',
                html: '<strong>' + dilimNo + '. Dilim</strong> silinecek.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'views/bordro/api.php',
                        type: 'POST',
                        data: { action: 'delete-vergi-dilimi', id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Silindi!',
                                    text: response.message,
                                    confirmButtonText: 'Tamam'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata!', text: response.message });
                            }
                        }
                    });
                }
            });
        });

        // Vergi dilimi modal kapanınca sıfırla
        $('#modalVergiDilimiEkle').on('hidden.bs.modal', function () {
            $('#formVergiDilimi')[0].reset();
            $('#dilim_id').val('');
            $('#modalVergiDilimiEkle .modal-title').html('<i class="bx bx-chart me-2"></i>Vergi Dilimi Ekle');
        });

        // ========== DATATABLE BAŞLATMA ==========

        // Filtreler için Select2 Initializer
        $('#filtreDonem, #filtreKategori, #donemSecimi, #vergiYiliSecimi').select2({
            minimumResultsForSearch: Infinity
        });

        const toggleBtn = $('#btnToggleSummaryCards');
        const updateToggleState = () => {
            const isHidden = $('html').hasClass('bordro-param-summary-hidden');
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        };
        updateToggleState();
        toggleBtn.on('click', function () {
            const willHide = !$('html').hasClass('bordro-param-summary-hidden');
            $('html').toggleClass('bordro-param-summary-hidden', willHide);
            try { localStorage.setItem('bordro_param_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) { }
            updateToggleState();
        });

        $('#paramTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            $('#btnTopYeni').attr('data-bs-target', $(this).data('add-target'));
            $('#btnTopYeniLabel').text($(this).data('add-label'));
            history.replaceState(null, '', window.location.pathname + window.location.search + $(this).attr('href'));
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
        if (window.location.hash && $('#paramTabs a[href="' + window.location.hash + '"]').length) {
            bootstrap.Tab.getOrCreateInstance($('#paramTabs a[href="' + window.location.hash + '"]')[0]).show();
        }

        $('.status-quick-filter').on('click', function () {
            $('.status-quick-filter').removeClass('active');
            $(this).addClass('active');
            $('#filtreKategori').val($(this).data('kategori')).trigger('change');
            $('#filtreDonem').val($(this).data('donem')).trigger('change');
            bootstrap.Tab.getOrCreateInstance($('#paramTabs a[href="#tabParametreler"]')[0]).show();
        });

        // Parametreler Datatable Initializer
        let dtParametreler;
        if ($('#dtParametreler').length > 0) {
            dtParametreler = $('#dtParametreler').DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[1, 'asc']],
                pageLength: 25,
                columnDefs: [{ targets: -1, orderable: false, searchable: false }]
            }));

            // Filtreleme fonksiyonu
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'dtParametreler') return true;

                const filterKategori = $('#filtreKategori').val();
                const filterDonem = $('#filtreDonem').val();

                const row = $(settings.aoData[dataIndex].nTr);
                const rowKategori = row.data('kategori');
                const rowAktif = row.data('aktif') == '1';

                if (filterKategori !== '' && rowKategori !== filterKategori) {
                    return false;
                }

                if (filterDonem === 'aktif' && !rowAktif) {
                    return false;
                }

                return true;
            });

            // Filtre değiştiğinde tabloyu yeniden çiz
            $('#filtreKategori, #filtreDonem').on('change', function () {
                dtParametreler.draw();
            });

            dtParametreler.draw();
        }

        // Genel Ayarlar Datatable Initializer
        if ($('#dtGenelAyarlar').length > 0) {
            $('#dtGenelAyarlar').DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[0, 'asc']],
                pageLength: 25,
                columnDefs: [{ targets: [-1, -2], orderable: false, searchable: false }]
            }));
        }

        // Vergi Dilimleri Datatable Initializer
        if ($('#dtVergiDilimleri').length > 0) {
            $('#dtVergiDilimleri').DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[0, 'asc']],
                pageLength: 25,
                columnDefs: [{ targets: -1, orderable: false, searchable: false }]
            }));
        }

        // Excel Butonları
        $('#exportExcelParametreler').on('click', function () {
            if (dtParametreler) {
                dtParametreler.button('.buttons-excel').trigger();
            }
        });

        $('#exportExcelGenelAyar').on('click', function () {
            $('#dtGenelAyarlar').DataTable().button('.buttons-excel').trigger();
        });

        $('#exportExcelVergiDilimleri').on('click', function () {
            $('#dtVergiDilimleri').DataTable().button('.buttons-excel').trigger();
        });

        // Sayfa yüklendiğinde kategori change tetikle
        $('select[name="kategori"]').trigger('change');
    });

    // PHP'den JS'e veri aktarımı
    const hesaplamaTipleriGelir = <?= json_encode($hesaplamaTipleriGelir) ?>;
    const hesaplamaTipleriKesinti = <?= json_encode($hesaplamaTipleriKesinti) ?>;
    const genelAyarlar = <?= json_encode($genelAyarlar) ?>;
</script>