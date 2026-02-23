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
    'aylik_gun_net' => 'Aylık (Çalışılan Gün) - Net'
];

$hesaplamaTipleriKesinti = [
    'netten' => 'Netten Kesinti',
    'brutten' => 'Brütten Kesinti',
    'sgk_matrahindan' => 'SGK Matrahından',
    'oran_bazli_vergi' => 'Oran (Vergi Matrahı)',
    'oran_bazli_sgk' => 'Oran (SGK Matrahı)',
    'oran_bazli_net' => 'Oran (Net)',
    'gunluk_kesinti' => 'Günlük Kesinti',
    'aylik_gun_kesinti' => 'Aylık (Çalışılan Gün) Kesinti'
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

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $title = "Bordro Parametreleri";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs nav-pills mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabParametreler" role="tab">
                <i class="bx bx-cog me-1"></i> Gelir/Kesinti Parametreleri
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabGenelAyarlar" role="tab">
                <i class="bx bx-slider me-1"></i> Genel Ayarlar
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabVergiDilimleri" role="tab">
                <i class="bx bx-chart me-1"></i> Gelir Vergisi Dilimleri
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Gelir/Kesinti Parametreleri Tab -->
        <div class="tab-pane active" id="tabParametreler" role="tabpanel">
            <div class="card">
                <div class="card-header border-bottom-0 pb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-list-ul me-1"></i> Tüm Gelir ve Kesinti Türleri
                        </h5>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button" id="exportExcelParametreler"
                                class="btn btn-link text-success text-decoration-none px-2 d-flex align-items-center"
                                title="Excel'e Aktar">
                                <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel'e Aktar
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-dark text-white shadow-sm text-decoration-none px-3 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#modalParametreEkle">
                                <i class="bx bx-plus me-1 text-white fs-5"></i> Yeni Parametre
                            </button>
                        </div>
                    </div>
                    <!-- Filtre Alanları -->
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <?= Form::FormSelect2(
                                name: "filtreDonem",
                                options: [
                                    "aktif" => "Sadece Aktif Dönemler (Bugün Geçerli)",
                                    "tumu" => "Tüm Dönemler (Geçmiş ve Gelecek Dahil)"
                                ],
                                selectedValue: ($showAll ? "tumu" : "aktif"),
                                label: "Dönem Filtresi",
                                icon: "calendar"
                            ) ?>
                        </div>
                        <div class="col-md-3">
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
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered w-100 align-middle" id="dtParametreler">
                            <thead class="table-light">
                                <tr>
                                    <th>Kategori</th>
                                    <th>Tür Kodu</th>
                                    <th>Etiket (Adı)</th>
                                    <th>Hesaplama Tipi</th>
                                    <th>Başlangıç</th>
                                    <th>Bitiş</th>
                                    <th class="text-center">Durum</th>
                                    <th class="text-center" style="width: 120px;">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tumParametreler as $param): ?>
                                    <?php
                                    $aktifMi = bugunGecerliMi($param, $bugun);
                                    ?>
                                    <tr data-aktif="<?= $aktifMi ? '1' : '0' ?>"
                                        data-kategori="<?= htmlspecialchars($param->kategori === 'gelir' ? 'Gelir' : 'Kesinti') ?>">
                                        <td>
                                            <?php if ($param->kategori === 'gelir'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success"><i
                                                        class="bx bx-plus-circle me-1"></i> Gelir</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger"><i
                                                        class="bx bx-minus-circle me-1"></i> Kesinti</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($param->kod) ?></code></td>
                                        <td>
                                            <strong><?= htmlspecialchars($param->etiket) ?></strong>
                                            <?php if (!empty($param->aciklama)): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($param->aciklama) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge = match ($param->hesaplama_tipi) {
                                                'brut', 'gunluk_brut', 'aylik_gun_brut' => 'bg-primary',
                                                'net', 'gunluk_net', 'aylik_gun_net' => 'bg-success',
                                                'kismi_muaf', 'gunluk_kismi_muaf' => 'bg-warning text-dark',
                                                'netten' => 'bg-secondary',
                                                'brutten', 'gunluk_kesinti', 'aylik_gun_kesinti' => 'bg-danger',
                                                'sgk_matrahindan' => 'bg-warning text-dark',
                                                'oran_bazli_vergi' => 'bg-info',
                                                'oran_bazli_sgk' => 'bg-primary',
                                                'oran_bazli_net' => 'bg-dark',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badge ?>">
                                                <?= $hesaplamaTipleri[$param->hesaplama_tipi] ?? ($param->hesaplama_tipi ?: 'Tanımsız') ?>
                                            </span>
                                            <?php if ($param->hesaplama_tipi === 'kismi_muaf' && $param->gunluk_muaf_limit > 0): ?>
                                                <br><small class="text-primary mt-1 d-inline-block">Limit:
                                                    <?= number_format($param->gunluk_muaf_limit, 2, ',', '.') ?> ₺/gün</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $param->gecerlilik_baslangic ? date('d.m.Y', strtotime($param->gecerlilik_baslangic)) : '<span class="text-muted">Tüm Dönemler</span>' ?>
                                        </td>
                                        <td>
                                            <?= $param->gecerlilik_bitis ? date('d.m.Y', strtotime($param->gecerlilik_bitis)) : '<span class="text-muted">Süresiz</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($aktifMi): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button class="btn btn-sm btn-outline-primary btn-edit-param"
                                                    data-id="<?= $param->id ?>"
                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'
                                                    title="Düzenle">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success btn-copy-param"
                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'
                                                    title="Yeni dönem olarak kopyala">
                                                    <i class="bx bx-copy"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete-param"
                                                    data-id="<?= $param->id ?>"
                                                    data-etiket="<?= htmlspecialchars($param->etiket) ?>" title="Sil">
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

        <!-- Genel Ayarlar Tab -->
        <div class="tab-pane" id="tabGenelAyarlar" role="tabpanel">
            <?php
            // Seçili döneme göre filtrele
            $filtreliAyarlar = [];
            if ($seciliDonem && !empty($genelAyarlar)) {
                foreach ($genelAyarlar as $ayar) {
                    if (!empty($ayar->gecerlilik_baslangic)) {
                        $donemKey = getDonemKey($ayar->gecerlilik_baslangic);
                        if ($donemKey === $seciliDonem) {
                            $filtreliAyarlar[] = $ayar;
                        }
                    }
                }
            }
            ?>
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-slider me-1"></i> Genel Bordro Ayarları
                        </h5>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <?php if (!empty($donemler)): ?>
                                <div style="min-width: 200px;">
                                    <select id="donemSecimi" class="form-select" style="width: 100%;">
                                        <?php foreach ($donemler as $donem): ?>
                                            <option value="<?= $donem ?>" <?= $seciliDonem === $donem ? 'selected' : '' ?>>
                                                <?= getDonemLabel($donem) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <?php endif; ?>

                            <button type="button" id="exportExcelGenelAyar"
                                class="btn btn-link text-success text-decoration-none px-2 d-flex align-items-center"
                                title="Excel'e Aktar">
                                <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel'e Aktar
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-link text-info text-decoration-none px-2 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#modalYeniDonem">
                                <i class="bx bx-copy me-1 fs-5"></i> Yeni Dönem Oluştur
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-dark text-white shadow-sm text-decoration-none px-3 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#modalGenelAyarEkle">
                                <i class="bx bx-plus me-1 fs-5 text-white"></i> Yeni Ayar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($filtreliAyarlar)): ?>
                        <div class="alert alert-warning text-center">
                            <i class="bx bx-info-circle me-1"></i>
                            <?php if (empty($donemler)): ?>
                                Henüz genel ayar tanımlanmamış.
                            <?php else: ?>
                                Bu dönem için ayar bulunamadı.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered w-100 align-middle" id="dtGenelAyarlar">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ayar Adı</th>
                                        <th>Kod</th>
                                        <th class="text-end">Değer</th>
                                        <th>Geçerlilik</th>
                                        <th class="text-center">Durum</th>
                                        <th class="text-center" style="width: 100px;">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtreliAyarlar as $ayar): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($ayar->parametre_adi) ?></strong>
                                            </td>
                                            <td><code><?= htmlspecialchars($ayar->parametre_kodu) ?></code></td>
                                            <td class="text-end">
                                                <?php if (strpos($ayar->parametre_kodu, 'orani') !== false): ?>
                                                    <span
                                                        class="badge bg-info fs-6">%<?= number_format($ayar->deger, 2, ',', '.') ?></span>
                                                <?php else: ?>
                                                    <span
                                                        class="badge bg-success fs-6"><?= number_format($ayar->deger, 2, ',', '.') ?>
                                                        ₺</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y', strtotime($ayar->gecerlilik_baslangic)) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?= $ayar->gecerlilik_bitis ? date('d.m.Y', strtotime($ayar->gecerlilik_bitis)) : 'Süresiz' ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input switch-ayar-status" type="checkbox"
                                                        data-id="<?= $ayar->id ?>" <?= $ayar->aktif ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary btn-edit-ayar"
                                                    data-id="<?= $ayar->id ?>" data-ayar='<?= json_encode($ayar) ?>'>
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete-ayar"
                                                    data-id="<?= $ayar->id ?>"
                                                    data-adi="<?= htmlspecialchars($ayar->parametre_adi) ?>">
                                                    <i class="bx bx-trash"></i>
                                                </button>
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

        <!-- Gelir Vergisi Dilimleri Tab -->
        <div class="tab-pane" id="tabVergiDilimleri" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-chart me-1"></i>
                            <span id="vergiDilimiBaslik"><?= $seciliVergiYili ?></span> Yılı Gelir Vergisi Dilimleri
                        </h5>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <div style="min-width: 120px;">
                                <select id="vergiYiliSecimi" class="form-select" style="width: 100%;">
                                    <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                        <option value="<?= $y ?>" <?= $seciliVergiYili == $y ? 'selected' : '' ?>><?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button" id="exportExcelVergiDilimleri"
                                class="btn btn-link text-success text-decoration-none px-2 d-flex align-items-center"
                                title="Excel'e Aktar">
                                <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel'e Aktar
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-dark text-white shadow-sm text-decoration-none px-3 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#modalVergiDilimiEkle">
                                <i class="bx bx-plus me-1 text-white fs-5"></i> Dilim Ekle
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered w-100 align-middle" id="dtVergiDilimleri">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th>Dilim</th>
                                    <th>Alt Limit</th>
                                    <th>Üst Limit</th>
                                    <th>Vergi Oranı</th>
                                    <th>Açıklama</th>
                                    <th style="width: 100px;">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($vergiDilimleri)): ?>
                                    <?php foreach ($vergiDilimleri as $dilim): ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?= $dilim->dilim_no ?>. Dilim</td>
                                            <td class="text-end"><?= number_format($dilim->alt_limit, 2, ',', '.') ?> ₺</td>
                                            <td class="text-end">
                                                <?= $dilim->ust_limit ? number_format($dilim->ust_limit, 2, ',', '.') . ' ₺' : '<span class="text-muted">Sınırsız</span>' ?>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-primary fs-6">%<?= number_format($dilim->vergi_orani, 0) ?></span>
                                            </td>
                                            <td class="text-muted small"><?= htmlspecialchars($dilim->aciklama ?? '') ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary btn-edit-dilim"
                                                    data-dilim='<?= json_encode($dilim) ?>'>
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete-dilim"
                                                    data-id="<?= $dilim->id ?>" data-dilim="<?= $dilim->dilim_no ?>">
                                                    <i class="bx bx-trash"></i>
                                                </button>
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
                            <div class="col-md-6">
                                <?= Form::FormSelect2(
                                    name: "hesaplama_tipi",
                                    options: $hesaplamaTipleri,
                                    selectedValue: 'net',
                                    label: "Hesaplama Tipi",
                                    icon: "sliders",
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

            $('input[name="gunluk_tutar"]').val(param.gunluk_tutar || 0);
            $('input[name="varsayilan_gun_sayisi"]').val(param.varsayilan_gun_sayisi || 26);
            $('input[name="gun_sayisi_otomatik"][value="' + (param.gun_sayisi_otomatik || 0) + '"]').prop('checked', true);

            $('#modalParametreEkle .modal-title').html('<i class="bx bx-copy me-2"></i>Yeni Dönem Ekle (Kopyala)');
            $('#modalParametreEkle').modal('show');
        });

        // Para formatını sayısal değere çeviren yardımcı fonksiyon
        function parseMoney(value) {
            if (!value) return 0;
            // ₺ sembolünü kaldır, binlik ayracı (.) kaldır, ondalık ayracı (,) noktaya çevir
            return parseFloat(value.toString().replace(/[₺\s]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
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
            $('input[name="alt_limit"]').val(dilim.alt_limit);
            $('input[name="ust_limit"]').val(dilim.ust_limit || '');
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
    });

    // PHP'den JS'e veri aktarımı
    const hesaplamaTipleriGelir = <?= json_encode($hesaplamaTipleriGelir) ?>;
    const hesaplamaTipleriKesinti = <?= json_encode($hesaplamaTipleriKesinti) ?>;
    const genelAyarlar = <?= json_encode($genelAyarlar) ?>;
</script>