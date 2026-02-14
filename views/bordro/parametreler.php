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
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-list-ul me-1"></i> Gelir ve Kesinti Türleri
                        </h5>

                        <div class="d-flex align-items-center gap-3">
                            <!-- Dönem Filtresi Toggle -->
                            <div class="btn-group" role="group">
                                <a href="?p=bordro/parametreler"
                                    class="btn btn-sm <?= !$showAll ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <i class="bx bx-calendar-check me-1"></i> Bugün Geçerli
                                </a>
                                <a href="?p=bordro/parametreler&tum_donemler=1"
                                    class="btn btn-sm <?= $showAll ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <i class="bx bx-calendar me-1"></i> Tüm Dönemler
                                </a>
                            </div>

                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#modalParametreEkle">
                                <i class="bx bx-plus me-1"></i> Yeni Parametre
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($showAll): ?>
                        <div class="alert alert-info mb-3">
                            <i class="bx bx-info-circle me-1"></i>
                            <strong>Tüm dönemler</strong> görüntüleniyor. Aktif dönemler
                            <span class="badge bg-success">yeşil</span> ile işaretlenmiştir.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Gelir Türleri -->
                        <div class="col-lg-6">
                            <div class="card border-success mb-3">
                                <div class="card-header bg-success text-white">
                                    <i class="bx bx-plus-circle me-1"></i> Gelir Türleri (Ek ödemeler)
                                    <span class="badge bg-light text-success float-end">
                                        <?= count($gelirGruplu) ?> tür
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($gelirGruplu)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bx bx-folder-open fs-1 d-block mb-2"></i>
                                            Henüz gelir türü tanımlanmamış.
                                        </div>
                                    <?php else: ?>
                                        <div class="accordion accordion-flush" id="accordionGelirler">
                                            <?php foreach ($gelirGruplu as $kod => $parametreler): ?>
                                                <?php
                                                // Bugün geçerli olanı bul
                                                $aktifParam = null;
                                                foreach ($parametreler as $p) {
                                                    if (bugunGecerliMi($p, $bugun)) {
                                                        $aktifParam = $p;
                                                        break;
                                                    }
                                                }
                                                $ilkParam = $parametreler[0];
                                                $donemSayisi = count($parametreler);

                                                // Tüm dönemler modunda değilse ve aktif yoksa atla
                                                if (!$showAll && !$aktifParam)
                                                    continue;
                                                ?>
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button
                                                            class="accordion-button <?= $donemSayisi <= 1 && !$showAll ? 'collapsed' : '' ?>"
                                                            type="button" data-bs-toggle="collapse"
                                                            data-bs-target="#collapse_gelir_<?= $kod ?>">
                                                            <div
                                                                class="d-flex align-items-center justify-content-between w-100 me-3">
                                                                <div>
                                                                    <strong><?= htmlspecialchars($ilkParam->etiket) ?></strong>
                                                                    <br>
                                                                    <code class="me-2"><?= htmlspecialchars($kod) ?></code>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php
                                                                    $badge = match ($ilkParam->hesaplama_tipi) {
                                                                        'brut', 'gunluk_brut', 'aylik_gun_brut' => 'bg-primary',
                                                                        'net', 'gunluk_net', 'aylik_gun_net' => 'bg-success',
                                                                        'kismi_muaf', 'gunluk_kismi_muaf' => 'bg-warning text-dark',
                                                                        default => 'bg-secondary'
                                                                    };
                                                                    ?>
                                                                    <span class="badge <?= $badge ?>">
                                                                        <?= $hesaplamaTipleri[$ilkParam->hesaplama_tipi] ?? ($ilkParam->hesaplama_tipi ?: 'Tanımsız') ?>
                                                                    </span>
                                                                    <?php if ($donemSayisi > 1): ?>
                                                                        <span class="badge bg-info"><?= $donemSayisi ?> dönem</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </h2>
                                                    <div id="collapse_gelir_<?= $kod ?>"
                                                        class="accordion-collapse collapse <?= $showAll || $donemSayisi > 1 ? 'show' : '' ?>">
                                                        <div class="accordion-body p-0">
                                                            <table class="table table-sm mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Geçerlilik Dönemi</th>
                                                                        <th>Muaf Limit</th>
                                                                        <th>SGK</th>
                                                                        <th>G.V.</th>
                                                                        <th class="text-center">İşlem</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($parametreler as $param): ?>
                                                                        <?php
                                                                        $aktifMi = bugunGecerliMi($param, $bugun);
                                                                        if (!$showAll && !$aktifMi)
                                                                            continue;
                                                                        ?>
                                                                        <tr class="<?= $aktifMi ? 'table-success' : '' ?>">
                                                                            <td>
                                                                                <?php if ($aktifMi): ?>
                                                                                    <span class="badge bg-success me-1">Aktif</span>
                                                                                <?php endif; ?>
                                                                                <?php if ($param->gecerlilik_baslangic): ?>
                                                                                    <?= date('d.m.Y', strtotime($param->gecerlilik_baslangic)) ?>
                                                                                    <?= $param->gecerlilik_bitis ? ' - ' . date('d.m.Y', strtotime($param->gecerlilik_bitis)) : ' - Süresiz' ?>
                                                                                <?php else: ?>
                                                                                    <span class="text-muted">Tüm dönemler</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php if ($param->hesaplama_tipi === 'kismi_muaf' && $param->gunluk_muaf_limit > 0): ?>
                                                                                    <strong class="text-primary">
                                                                                        <?= number_format($param->gunluk_muaf_limit, 2, ',', '.') ?>
                                                                                        ?/gün
                                                                                    </strong>
                                                                                <?php else: ?>
                                                                                    <span class="text-muted">-</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?= $param->sgk_matrahi_dahil ? '<i class="bx bx-check text-success fs-5"></i>' : '<i class="bx bx-x text-danger fs-5"></i>' ?>
                                                                            </td>
                                                                            <td>
                                                                                <?= $param->gelir_vergisi_dahil ? '<i class="bx bx-check text-success fs-5"></i>' : '<i class="bx bx-x text-danger fs-5"></i>' ?>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <button
                                                                                    class="btn btn-sm btn-outline-primary btn-edit-param"
                                                                                    data-id="<?= $param->id ?>"
                                                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'>
                                                                                    <i class="bx bx-edit-alt"></i>
                                                                                </button>
                                                                                <button
                                                                                    class="btn btn-sm btn-outline-success btn-copy-param"
                                                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'
                                                                                    title="Yeni dönem olarak kopyala">
                                                                                    <i class="bx bx-copy"></i>
                                                                                </button>
                                                                                <button
                                                                                    class="btn btn-sm btn-outline-danger btn-delete-param"
                                                                                    data-id="<?= $param->id ?>"
                                                                                    data-etiket="<?= htmlspecialchars($param->etiket) ?>"
                                                                                    title="Sil">
                                                                                    <i class="bx bx-trash"></i>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Kesinti Türleri -->
                        <div class="col-lg-6">
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger text-white">
                                    <i class="bx bx-minus-circle me-1"></i> Kesinti Türleri
                                    <span class="badge bg-light text-danger float-end">
                                        <?= count($kesintiGruplu) ?> tür
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($kesintiGruplu)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bx bx-folder-open fs-1 d-block mb-2"></i>
                                            Henüz kesinti türü tanımlanmamış.
                                        </div>
                                    <?php else: ?>
                                        <div class="accordion accordion-flush" id="accordionKesintiler">
                                            <?php foreach ($kesintiGruplu as $kod => $parametreler): ?>
                                                <?php
                                                $aktifParam = null;
                                                foreach ($parametreler as $p) {
                                                    if (bugunGecerliMi($p, $bugun)) {
                                                        $aktifParam = $p;
                                                        break;
                                                    }
                                                }
                                                $ilkParam = $parametreler[0];
                                                $donemSayisi = count($parametreler);

                                                if (!$showAll && !$aktifParam)
                                                    continue;
                                                ?>
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button
                                                            class="accordion-button <?= $donemSayisi <= 1 && !$showAll ? 'collapsed' : '' ?>"
                                                            type="button" data-bs-toggle="collapse"
                                                            data-bs-target="#collapse_kesinti_<?= $kod ?>">
                                                            <div
                                                                class="d-flex align-items-center justify-content-between w-100 me-3">
                                                                <div>
                                                                    <strong><?= htmlspecialchars($ilkParam->etiket) ?></strong>
                                                                    <br>
                                                                    <code class="me-2"><?= htmlspecialchars($kod) ?></code>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php
                                                                    $badge = match ($ilkParam->hesaplama_tipi) {
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
                                                                        <?= $hesaplamaTipleri[$ilkParam->hesaplama_tipi] ?? ($ilkParam->hesaplama_tipi ?: 'Tanımsız') ?>
                                                                    </span>
                                                                    <?php if ($donemSayisi > 1): ?>
                                                                        <span class="badge bg-info"><?= $donemSayisi ?> dönem</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </h2>
                                                    <div id="collapse_kesinti_<?= $kod ?>"
                                                        class="accordion-collapse collapse <?= $showAll || $donemSayisi > 1 ? 'show' : '' ?>">
                                                        <div class="accordion-body p-0">
                                                            <table class="table table-sm mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Geçerlilik Dönemi</th>
                                                                        <th>Açıklama</th>
                                                                        <th class="text-center">İşlem</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($parametreler as $param): ?>
                                                                        <?php
                                                                        $aktifMi = bugunGecerliMi($param, $bugun);
                                                                        if (!$showAll && !$aktifMi)
                                                                            continue;
                                                                        ?>
                                                                        <tr class="<?= $aktifMi ? 'table-success' : '' ?>">
                                                                            <td>
                                                                                <?php if ($aktifMi): ?>
                                                                                    <span class="badge bg-success me-1">Aktif</span>
                                                                                <?php endif; ?>
                                                                                <?php if ($param->gecerlilik_baslangic): ?>
                                                                                    <?= date('d.m.Y', strtotime($param->gecerlilik_baslangic)) ?>
                                                                                    <?= $param->gecerlilik_bitis ? ' - ' . date('d.m.Y', strtotime($param->gecerlilik_bitis)) : ' - Süresiz' ?>
                                                                                <?php else: ?>
                                                                                    <span class="text-muted">Tüm dönemler</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-muted small">
                                                                                <?= htmlspecialchars($param->aciklama ?? '-') ?>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <button
                                                                                    class="btn btn-sm btn-outline-primary btn-edit-param"
                                                                                    data-id="<?= $param->id ?>"
                                                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'>
                                                                                    <i class="bx bx-edit-alt"></i>
                                                                                </button>
                                                                                <button
                                                                                    class="btn btn-sm btn-outline-success btn-copy-param"
                                                                                    data-param='<?= htmlspecialchars(json_encode($param), ENT_QUOTES, 'UTF-8') ?>'
                                                                                    title="Yeni dönem olarak kopyala">
                                                                                    <i class="bx bx-copy"></i>
                                                                                </button>
                                                                                <button
                                                                                    class="btn btn-sm btn-outline-danger btn-delete-param"
                                                                                    data-id="<?= $param->id ?>"
                                                                                    data-etiket="<?= htmlspecialchars($param->etiket) ?>"
                                                                                    title="Sil">
                                                                                    <i class="bx bx-trash"></i>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
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
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($donemler)): ?>
                                <select id="donemSecimi" class="form-select" style="width: 200px;">
                                    <?php foreach ($donemler as $donem): ?>
                                        <option value="<?= $donem ?>" <?= $seciliDonem === $donem ? 'selected' : '' ?>>
                                            <?= getDonemLabel($donem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal"
                                data-bs-target="#modalYeniDonem">
                                <i class="bx bx-copy me-1"></i> Yeni Dönem Oluştur
                            </button>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#modalGenelAyarEkle">
                                <i class="bx bx-plus me-1"></i> Yeni Ayar
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
                            <table class="table table-hover table-bordered">
                                <thead class="table-primary">
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
                                        <tr class="<?= !$ayar->aktif ? 'table-secondary text-muted' : '' ?>">
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
                        <div class="d-flex align-items-center gap-2">
                            <select id="vergiYiliSecimi" class="form-select" style="width: 120px;">
                                <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $seciliVergiYili == $y ? 'selected' : '' ?>><?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#modalVergiDilimiEkle">
                                <i class="bx bx-plus me-1"></i> Dilim Ekle
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tblVergiDilimleri">
                            <thead class="table-primary">
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
                                <?php if (empty($vergiDilimleri)): ?>
                                    <tr class="vergi-dilim-empty">
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <?= $seciliVergiYili ?> yılı için vergi dilimi tanımlanmamış.
                                        </td>
                                    </tr>
                                <?php else: ?>
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
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-plus-circle me-2"></i>Yeni Parametre Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formParametre">
                <input type="hidden" name="id" id="param_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("text", "kod", "", "Örn: yemek_yardimi", "Kod", "bx bx-hash", "form-control", true) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("text", "etiket", "", "Örn: Yemek Yardımı", "Etiket", "bx bx-label", "form-control", true) ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?= Form::FormSelect2(
                                name: "kategori",
                                options: $kategoriOptions,
                                selectedValue: 'gelir',
                                label: "Kategori",
                                icon: "bx bx-category",
                                required: true
                            ) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormSelect2(
                                name: "hesaplama_tipi",
                                options: $hesaplamaTipleri,
                                selectedValue: 'net',
                                label: "Hesaplama Tipi",
                                icon: "bx bx-calculator",
                                required: true
                            ) ?>
                        </div>
                    </div>

                    <div id="muafiyetAyarlari" class="row" style="display: none;">
                        <div class="col-md-4 mb-3">
                            <?= Form::FormSelect2(
                                name: "muaf_limit_tipi",
                                options: $muafLimitTipleri,
                                selectedValue: 'yok',
                                label: "Muafiyet Tipi",
                                icon: "bx bx-shield"
                            ) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?= Form::FormFloatInput("number", "gunluk_muaf_limit", "0", "0.00", "Günlük Muaf Limit", "bx bx-money", "form-control", false, null, "off", false, 'step="0.01"') ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?= Form::FormFloatInput("number", "aylik_muaf_limit", "0", "0.00", "Aylık Muaf Limit", "bx bx-money", "form-control", false, null, "off", false, 'step="0.01"') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Vergi/SGK Dahil Mi?</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sgk_matrahi_dahil"
                                        name="sgk_matrahi_dahil" value="1">
                                    <label class="form-check-label" for="sgk_matrahi_dahil" id="sgk_matrah_label">SGK
                                        Matrahına Dahil</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="gelir_vergisi_dahil"
                                        name="gelir_vergisi_dahil" value="1" checked>
                                    <label class="form-check-label" for="gelir_vergisi_dahil"
                                        id="gelir_vergisi_label">Gelir Vergisine
                                        Dahil</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="damga_vergisi_dahil"
                                        name="damga_vergisi_dahil" value="1">
                                    <label class="form-check-label" for="damga_vergisi_dahil"
                                        id="damga_vergisi_label">Damga Vergisine
                                        Dahil</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("date", "gecerlilik_baslangic", "", "", "Geçerlilik Başlangıç", "bx bx-calendar", "form-control") ?>
                            <small class="text-muted">Boş bırakılırsa tüm dönemler için geçerli</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("date", "gecerlilik_bitis", "", "", "Geçerlilik Bitiş", "bx bx-calendar", "form-control") ?>
                            <small class="text-muted">Boş bırakılırsa süresiz geçerli</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3" id="divTutar">
                            <?= Form::FormFloatInput("text", "varsayilan_tutar", "0", "0.00", "Varsayılan Tutar", "dollar-sign", "form-control money", false, null, "off", false) ?>
                        </div>
                        <div class="col-md-6 mb-3" id="divGunlukTutar" style="display: none;">
                            <?= Form::FormFloatInput("text", "gunluk_tutar", "0", "0.00", "Günlük Tutar", "bx bx-calendar-check", "form-control money", false, null, "off", false) ?>
                        </div>
                        <div class="col-md-6 mb-3" id="divOran" style="display: none;">
                            <?= Form::FormFloatInput("number", "oran", "0", "0", "Oran (%)", "bx bx-percentage", "form-control", false, null, "off", false, 'step="0.01"') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("number", "sira", "0", "0", "Sıralama", "bx bx-sort-amount-up", "form-control") ?>
                        </div>
                    </div>

                    <!-- Günlük Hesaplama Ayarları -->
                    <div id="gunlukAyarlar" class="row" style="display: none;">
                        <div class="col-12 mb-3">
                            <div class="alert alert-info mb-0">
                                <i class="bx bx-info-circle me-1"></i>
                                <strong>Günlük Bazlı Hesaplama:</strong> Tutar = Günlük Tutar × Hesaplanan Gün Sayısı
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gün Sayısı Hesaplama</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="gun_otomatik"
                                        name="gun_sayisi_otomatik" value="1">
                                    <label class="form-check-label" for="gun_otomatik">
                                        <i class="bx bx-git-branch text-success"></i> Otomatik (Puantajdan)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="gun_manuel"
                                        name="gun_sayisi_otomatik" value="0" checked>
                                    <label class="form-check-label" for="gun_manuel">
                                        <i class="bx bx-edit text-primary"></i> Manuel/Sabit
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="divVarsayilanGun">
                            <?= Form::FormFloatInput("number", "varsayilan_gun_sayisi", "26", "26", "Varsayılan Gün Sayısı", "bx bx-calendar", "form-control") ?>
                            <small class="text-muted">Manuel hesaplama için kullanılır</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama...", "Açıklama", "bx bx-message-detail", "form-control") ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bx bx-save me-1"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Yeni Dönem Oluştur Modal -->
<div class="modal fade" id="modalYeniDonem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-copy me-2"></i>Yeni Dönem Oluştur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formYeniDonem">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-1"></i>
                        Mevcut ayarları yeni bir döneme kopyalayabilirsiniz. Değişen değerleri güncelleyin.
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Kaynak Dönem</label>
                            <select name="kaynak_donem" id="kaynakDonemSecimi" class="form-select" required>
                                <?php foreach ($donemler as $donem): ?>
                                    <option value="<?= $donem ?>" <?= $seciliDonem === $donem ? 'selected' : '' ?>>
                                        <?= getDonemLabel($donem) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Yeni Geçerlilik Başlangıç Tarihi</label>
                            <input type="date" name="yeni_gecerlilik" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Açıklama</label>
                            <input type="text" name="donem_aciklama" class="form-control"
                                placeholder="Örn: 2026 Temmuz güncellemesi">
                        </div>
                    </div>

                    <hr>

                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 30px;">
                                        <input type="checkbox" id="selectAllAyar" checked>
                                    </th>
                                    <th>Ayar Adı</th>
                                    <th>Mevcut Değer</th>
                                    <th>Yeni Değer</th>
                                </tr>
                            </thead>
                            <tbody id="donemAyarListesi">
                                <!-- JavaScript ile doldurulacak -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bx bx-copy me-1"></i>Seçilenleri Kopyala
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Genel Ayar Ekle Modal -->
<div class="modal fade" id="modalGenelAyarEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-slider me-2"></i>Genel Ayar Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formGenelAyar">
                <input type="hidden" name="id" id="ayar_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "parametre_kodu", "", "Örn: asgari_ucret_brut", "Parametre Kodu", "hash", "form-control", true) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "parametre_adi", "", "Örn: Asgari Ücret (Brüt)", "Parametre Adı", "tag", "form-control", true) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "deger", "", "0.00", "Değer", "dollar-sign", "form-control money", true, null, "off", false) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput("date", "ayar_gecerlilik_baslangic", date('Y-m-d'), "", "Geçerlilik Başlangıç", "calendar", "form-control", true) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "ayar_aciklama", "", "Açıklama...", "Açıklama", "file-text", "form-control") ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vergi Dilimi Ekle/Düzenle Modal -->
<div class="modal fade" id="modalVergiDilimiEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-chart me-2"></i>Vergi Dilimi Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVergiDilimi">
                <input type="hidden" name="id" id="dilim_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Yıl</label>
                            <select name="dilim_yili" id="dilim_yili" class="form-select" required>
                                <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $seciliVergiYili == $y ? 'selected' : '' ?>><?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dilim No</label>
                            <input type="number" name="dilim_no" class="form-control" required min="1" max="10"
                                placeholder="1-10">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Alt Limit (₺)</label>
                            <input type="text" name="alt_limit" class="form-control money" required placeholder="0,00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Üst Limit (₺)</label>
                            <input type="text" name="ust_limit" class="form-control money" placeholder="Boş = Sınırsız">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Vergi Oranı (%)</label>
                            <input type="number" name="vergi_orani" class="form-control" required min="0" max="100"
                                step="0.01" placeholder="15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Açıklama</label>
                            <input type="text" name="dilim_aciklama" class="form-control" placeholder="Opsiyonel">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bx bx-save me-1"></i>Kaydet</button>
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
                modalHeader.removeClass('bg-success').addClass('bg-danger');
            } else {
                modalHeader.removeClass('bg-danger').addClass('bg-success');
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

            /**Kesinti Modalda Lalbellar değiştir */
            if (param.kategori === 'gelir') {
                $("#sgk_matrah_label").html("SGK Matrahına Dahil");
                $("#gelir_vergisi_label").html("Gelir Vergisine Dahil");
                $("#damga_vergisi_label").html("Damga Vergisine Dahil");
            } else {

                $("#sgk_matrah_label").html("SGK Matrahından Düşülür");
                $("#gelir_vergisi_label").html("Gelir Vergisinden Düşülür");
                $("#damga_vergisi_label").html("Damga Vergisinden Düşülür");
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
</script>