<?php

use App\Model\IhbarModel;
use App\Model\SettingsModel;
use App\Service\Gate;
use App\Helper\Form;
use App\Helper\Security;

Gate::authorizeOrDie('ihbar/list');

$IhbarModel = new IhbarModel();
$ihbarlar = $IhbarModel->getAllForDashboard();
$yonlendirilecekPersoneller = $IhbarModel->getYonlendirilecekPersonelListesi();
$ihbarSettings = (new SettingsModel())->getAllSettingsAsKeyValue((int) ($_SESSION['firma_id'] ?? 1));
$ihbarPersonelLimit = max(1, (int) ($ihbarSettings['ihbar_personel_eszamanli_limit'] ?? 5));
$ihbarAyniBolgeOnceligi = ($ihbarSettings['ihbar_ayni_bolge_onceligi'] ?? '1') === '1';

$ihbarOzet = [
    'toplam' => count($ihbarlar),
    'yeni' => 0,
    'devam_eden' => 0,
    'olumlu' => 0,
    'olumsuz' => 0,
];

foreach ($ihbarlar as $ihbar) {
    if (isset($ihbarOzet[$ihbar->durum])) {
        $ihbarOzet[$ihbar->durum]++;
    }

    if (in_array($ihbar->durum, ['yonlendirildi', 'islemde'], true)) {
        $ihbarOzet['devam_eden']++;
    }
}

$durumDagilimi = [
    'Yeni' => 0,
    'Yönlendirildi' => 0,
    'İşlemde' => 0,
    'Olumlu' => 0,
    'Olumsuz' => 0,
];
$durumEtiketleri = [
    'yeni' => 'Yeni',
    'yonlendirildi' => 'Yönlendirildi',
    'islemde' => 'İşlemde',
    'olumlu' => 'Olumlu',
    'olumsuz' => 'Olumsuz',
];
$aylikTrend = [];
$ilceDagilimi = [];

for ($i = 5; $i >= 0; $i--) {
    $ayAnahtari = date('Y-m', strtotime("-{$i} month"));
    $aylikTrend[$ayAnahtari] = [
        'etiket' => date('m/Y', strtotime($ayAnahtari . '-01')),
        'toplam' => 0,
        'sonuclanan' => 0,
    ];
}

foreach ($ihbarlar as $ihbar) {
    if (isset($durumEtiketleri[$ihbar->durum])) {
        $durumDagilimi[$durumEtiketleri[$ihbar->durum]]++;
    }

    $ayAnahtari = date('Y-m', strtotime($ihbar->created_at));
    if (isset($aylikTrend[$ayAnahtari])) {
        $aylikTrend[$ayAnahtari]['toplam']++;
        if (in_array($ihbar->durum, ['olumlu', 'olumsuz'], true)) {
            $aylikTrend[$ayAnahtari]['sonuclanan']++;
        }
    }

    $ilce = trim((string) ($ihbar->ilce ?? '')) ?: 'Belirtilmemiş';
    $ilceDagilimi[$ilce] = ($ilceDagilimi[$ilce] ?? 0) + 1;
}

arsort($ilceDagilimi);
$ilceDagilimi = array_slice($ilceDagilimi, 0, 6, true);
$sonuclananToplam = $ihbarOzet['olumlu'] + $ihbarOzet['olumsuz'];
$sonuclanmaOrani = $ihbarOzet['toplam'] > 0
    ? round(($sonuclananToplam / $ihbarOzet['toplam']) * 100)
    : 0;
$olumluOrani = $sonuclananToplam > 0
    ? round(($ihbarOzet['olumlu'] / $sonuclananToplam) * 100)
    : 0;
$buAyToplam = end($aylikTrend)['toplam'] ?? 0;
$kahramanmarasIlceleri = [
    '' => 'İlçe seçin...',
    'Afşin' => 'Afşin',
    'Andırın' => 'Andırın',
    'Çağlayancerit' => 'Çağlayancerit',
    'Dulkadiroğlu' => 'Dulkadiroğlu',
    'Ekinözü' => 'Ekinözü',
    'Elbistan' => 'Elbistan',
    'Göksun' => 'Göksun',
    'Nurhak' => 'Nurhak',
    'Onikişubat' => 'Onikişubat',
    'Pazarcık' => 'Pazarcık',
    'Türkoğlu' => 'Türkoğlu',
];

$ihbarEkipSelectHtml = Form::FormMultipleSelect2(
    'ihbarEkipSelect',
    $yonlendirilecekPersoneller,
    [],
    'Görevlendirilecek personeller',
    'bx bx-group',
    'id',
    'adi_soyadi',
    'form-select select2',
    false,
    'ihbarEkipSelect'
);
$ihbarTopluPersoneller = array_map(static fn($p) => [
    'token' => Security::encrypt((int) $p->id),
    'adi_soyadi' => $p->adi_soyadi,
], $yonlendirilecekPersoneller);
$ihbarTopluPersonelSelectHtml = Form::FormSelect2(
    'toplu_personel', $ihbarTopluPersoneller, '', 'Yönlendirilecek personel', 'bx bx-user',
    'token', 'adi_soyadi', 'form-select select2', true, 'width:100%', '', 'topluPersonelTemplate'
);
$ihbarNotInputHtml = Form::FormFloatInput(
    'text',
    'ihbarNotInput',
    '',
    'Yapılan işlem veya görüşme notunu yazın',
    'İşlem notu',
    'bx bx-note',
    'form-control',
    false,
    null,
    'off',
    false,
    'onkeydown="if(event.key === \'Enter\'){ event.preventDefault(); ihbarNotEkle(); }"'
);
$ihbarSonucSelectHtml = Form::FormSelect2(
    'ihbarSonucDurum',
    ['' => 'Sonuç seçin...', 'olumlu' => 'Olumlu', 'olumsuz' => 'Olumsuz'],
    '',
    'Sonuç durumu',
    'bx bx-check-shield',
    'key',
    '',
    'form-select',
    false,
    'width:100%',
    'onchange="ihbarSonucAlanGuncelle()"'
);
$ihbarTutanakInputHtml = Form::FormFloatInput(
    'text',
    'ihbarTutanakNo',
    '',
    'Tutanak numarasını girin',
    'Tutanak numarası',
    'bx bx-file',
    'form-control',
    false,
    null,
    'off'
);
$ihbarOlumsuzSebepHtml = Form::FormFloatTextarea(
    'ihbarOlumsuzSebep',
    '',
    'Olumsuz sonuç sebebini açıklayın',
    'Olumsuz sonuç sebebi',
    'bx bx-message-square-error',
    'form-control',
    false,
    '90px',
    3
);

function ihbarDurumBadge($durum)
{
    $map = [
        'yeni' => ['secondary', 'Yeni'],
        'yonlendirildi' => ['info', 'Yönlendirildi'],
        'islemde' => ['warning', 'İşlemde'],
        'olumlu' => ['success', 'Olumlu'],
        'olumsuz' => ['danger', 'Olumsuz'],
    ];
    [$class, $text] = $map[$durum] ?? ['secondary', $durum];
    return '<span class="badge bg-' . $class . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<link rel="stylesheet" href="assets/libs/glightbox/css/glightbox.min.css">

<div class="container-fluid">
    <?php
    $maintitle = "İş Takip";
    $title = "İhbar Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="card border-0 shadow-sm mb-4 ihbar-tabs-card">
        <div class="card-body p-2">
            <ul class="nav nav-pills ihbar-tabs" id="ihbarYonetimTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ihbar-dashboard-tab" data-bs-toggle="tab"
                        data-bs-target="#ihbar-dashboard-pane" type="button" role="tab">
                        <i class="bx bx-grid-alt me-2"></i>Dashboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ihbar-list-tab" data-bs-toggle="tab"
                        data-bs-target="#ihbar-list-pane" type="button" role="tab">
                        <i class="bx bx-list-ul me-2"></i>Gelen İhbarlar
                        <span class="badge rounded-pill ms-2"><?= $ihbarOzet['toplam'] ?></span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="ihbarYonetimTabContent">
        <div class="tab-pane fade show active" id="ihbar-dashboard-pane" role="tabpanel">
    <div class="row g-3 mb-4 ihbar-dashboard">
        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm h-100 ihbar-stat-card ihbar-stat-total">
                <div class="card-body d-flex align-items-center">
                    <span class="ihbar-stat-icon"><i class="bx bx-list-ul"></i></span>
                    <div>
                        <span class="ihbar-stat-label">Toplam İhbar</span>
                        <h3 class="ihbar-stat-value mb-0"><?= $ihbarOzet['toplam'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm h-100 ihbar-stat-card ihbar-stat-new">
                <div class="card-body d-flex align-items-center">
                    <span class="ihbar-stat-icon"><i class="bx bx-bell"></i></span>
                    <div>
                        <span class="ihbar-stat-label">Yeni</span>
                        <h3 class="ihbar-stat-value mb-0"><?= $ihbarOzet['yeni'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm h-100 ihbar-stat-card ihbar-stat-progress">
                <div class="card-body d-flex align-items-center">
                    <span class="ihbar-stat-icon"><i class="bx bx-loader-circle"></i></span>
                    <div>
                        <span class="ihbar-stat-label">Devam Eden</span>
                        <h3 class="ihbar-stat-value mb-0"><?= $ihbarOzet['devam_eden'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm h-100 ihbar-stat-card ihbar-stat-positive">
                <div class="card-body d-flex align-items-center">
                    <span class="ihbar-stat-icon"><i class="bx bx-check-circle"></i></span>
                    <div>
                        <span class="ihbar-stat-label">Olumlu</span>
                        <h3 class="ihbar-stat-value mb-0"><?= $ihbarOzet['olumlu'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm h-100 ihbar-stat-card ihbar-stat-negative">
                <div class="card-body d-flex align-items-center">
                    <span class="ihbar-stat-icon"><i class="bx bx-x-circle"></i></span>
                    <div>
                        <span class="ihbar-stat-label">Olumsuz</span>
                        <h3 class="ihbar-stat-value mb-0"><?= $ihbarOzet['olumsuz'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100 ihbar-chart-card">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">İhbar Trendi</h5>
                        <p class="text-muted small mb-0">Son 6 ayda gelen ve sonuçlanan ihbarlar</p>
                    </div>
                    <span class="ihbar-chart-badge"><i class="bx bx-calendar me-1"></i>6 Ay</span>
                </div>
                <div class="card-body pt-0">
                    <div id="ihbarTrendChart" class="ihbar-chart"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100 ihbar-chart-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-1">Durum Dağılımı</h5>
                    <p class="text-muted small mb-0">Tüm ihbarların güncel durumu</p>
                </div>
                <div class="card-body pt-0">
                    <div id="ihbarDurumChart" class="ihbar-chart"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100 ihbar-chart-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-1">İlçe Yoğunluğu</h5>
                    <p class="text-muted small mb-0">En fazla ihbar gelen ilk 6 ilçe</p>
                </div>
                <div class="card-body pt-0">
                    <div id="ihbarIlceChart" class="ihbar-chart ihbar-chart-sm"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100 ihbar-chart-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-1">Performans Özeti</h5>
                    <p class="text-muted small mb-0">Operasyonel sonuç göstergeleri</p>
                </div>
                <div class="card-body">
                    <div class="ihbar-performance-item">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sonuçlandırma oranı</span><strong><?= $sonuclanmaOrani ?>%</strong>
                        </div>
                        <div class="progress"><div class="progress-bar bg-primary" style="width:<?= $sonuclanmaOrani ?>%"></div></div>
                    </div>
                    <div class="ihbar-performance-item">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Olumlu sonuç oranı</span><strong><?= $olumluOrani ?>%</strong>
                        </div>
                        <div class="progress"><div class="progress-bar bg-success" style="width:<?= $olumluOrani ?>%"></div></div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <div class="ihbar-mini-metric">
                                <i class="bx bx-calendar-check text-primary"></i>
                                <span>Bu Ay Gelen</span>
                                <strong><?= $buAyToplam ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="ihbar-mini-metric">
                                <i class="bx bx-check-double text-success"></i>
                                <span>Sonuçlanan</span>
                                <strong><?= $sonuclananToplam ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php if ($ihbarOzet['yeni'] > 0): ?>
                        <div class="alert alert-warning d-flex align-items-center mt-3 mb-0 py-2">
                            <i class="bx bx-error-circle fs-4 me-2"></i>
                            <small><strong><?= $ihbarOzet['yeni'] ?> yeni ihbar</strong> henüz ekibe yönlendirilmedi.</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success d-flex align-items-center mt-3 mb-0 py-2">
                            <i class="bx bx-check-shield fs-4 me-2"></i>
                            <small>Yönlendirme bekleyen yeni ihbar bulunmuyor.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
        </div>

        <div class="tab-pane fade" id="ihbar-list-pane" role="tabpanel">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bx bx-error-circle me-2 text-danger"></i>Gelen İhbarlar</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success px-3 rounded-pill" id="ihbarExportExcel">
                    <i class="bx bx-file me-1"></i>Excel'e Aktar
                </button>
                <button type="button" class="btn btn-sm btn-danger px-3 rounded-pill" onclick="ihbarYeniAc()">
                    <i class="bx bx-plus me-1"></i>Yeni İhbar Ekle
                </button>
                <button type="button" class="btn btn-sm btn-warning px-3 rounded-pill" onclick="ihbarYenidenYonlendirAc()">
                    <i class="bx bx-transfer-alt me-1"></i>Yeniden Yönlendir
                </button>
                <?php if (Gate::allows('is_takip_ayarlar') || Gate::isSuperAdmin()): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalIhbarAyarlar" title="İhbar yönlendirme ayarları">
                    <i class="bx bx-cog"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table datatables table-hover table-bordered nowrap align-middle w-100"
                    id="ihbarTable" data-order="[]">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="date">Tarih</th>
                            <th data-filter="string">İlçe</th>
                            <th data-filter="string">Mahalle</th>
                            <th data-filter="string">Telefon</th>
                            <th data-filter="string">Bildiren</th>
                            <th data-filter="string">Atanan Ekip</th>
                            <th data-filter="select">Durum</th>
                            <th class="text-center" style="width:210px">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ihbarlar as $ihbar): ?>
                            <tr data-id="<?= (int) $ihbar->id ?>">
                                <td><?= date('d.m.Y H:i', strtotime($ihbar->created_at)) ?></td>
                                <td><?= htmlspecialchars($ihbar->ilce ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ihbar->mahalle ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ihbar->telefon ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ihbar->bildiren_personel_adi ?? $ihbar->olusturan_user_adi ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ihbar->atanan_ekip_adi ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= ihbarDurumBadge($ihbar->durum) ?></td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-info text-white" onclick="ihbarDetay(<?= (int) $ihbar->id ?>)" title="Detay">
                                            <i class="bx bx-detail"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="ihbarDuzenle(<?= (int) $ihbar->id ?>)" title="Düzenle">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="ihbarSil(<?= (int) $ihbar->id ?>)" title="Sil">
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
    </div>
</div>

<div class="modal fade" id="modalTopluYonlendir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title">İhbarları Yeniden Yönlendir</h5>
                    <small class="text-muted">Yeni ve yönlendirilmiş ihbarların önerilen personelini kontrol edin.</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="topluYonlendirIcerik"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-warning" id="btnTopluYonlendirKaydet" onclick="ihbarTopluYonlendir()">
                    <i class="bx bx-send me-1"></i>Seçilenleri Yönlendir
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalIhbarAyarlar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="formIhbarAyarlar">
            <div class="modal-header"><div><h5 class="modal-title">İhbar Yönlendirme Ayarları</h5>
                <small class="text-muted">Otomatik ve toplu yönlendirme kuralları</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><?= Form::FormFloatInput('number', 'ihbar_personel_eszamanli_limit', $ihbarPersonelLimit, 'Örn. 5', 'Personel başına açık ihbar limiti', 'bx bx-list-check', 'form-control', true, 3, 'off', false, 'min="1" max="100"') ?></div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="ihbarAyniBolgeOnceligi" name="ihbar_ayni_bolge_onceligi" value="1" <?= $ihbarAyniBolgeOnceligi ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ihbarAyniBolgeOnceligi">Aynı ilçe ve mahalledeki ihbarları aynı personele önceliklendir</label>
                </div>
                <div class="alert alert-info small mt-3 mb-0">Personelin limiti dolduğunda sistem sıradaki en yakın görevdeki Kaçak personeline geçer.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-primary" id="btnIhbarAyarKaydet">Ayarları Kaydet</button></div>
        </form>
    </div></div>
</div>

<!-- Yeni İhbar Ekle Modal -->
<div class="modal fade" id="modalYeniIhbar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bx bx-error-circle me-2"></i>Yeni İhbar Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formYeniIhbar">
                <input type="hidden" name="id" id="ihbarFormId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormSelect2('ilce', $kahramanmarasIlceleri, '', 'İlçe', 'bx bx-map', 'key', '', 'form-select select2', false, 'width:100%', '', 'ihbarIlce') ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput('text', 'mahalle', '', 'Mahalle', 'Mahalle', 'bx bx-map-pin', 'form-control', false, 150, 'off') ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput('tel', 'telefon', '', '05XX XXX XX XX', 'Komşu / Abone Telefonu', 'bx bx-phone', 'form-control', false, 20, 'tel') ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput('text', 'komsu_abone_no', '', 'Abone numarası', 'Komşu Abone No', 'bx bx-id-card', 'form-control', false, 50, 'off') ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput('url', 'konum_link', '', 'https://maps.google.com/...', 'Konum Linki', 'bx bx-link-alt', 'form-control', false, 1000, 'url') ?>
                        <small class="text-muted">Google Maps veya başka bir harita uygulamasından alınan konum bağlantısını yapıştırabilirsiniz.</small>
                    </div>
                    <div class="mb-3">
                        <input type="hidden" name="konum_lat" id="konum_lat">
                        <input type="hidden" name="konum_lng" id="konum_lng">
                        <input type="hidden" name="konum_dogruluk" id="konum_dogruluk">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="ihbarYoneticiKonumAl()">
                            <i class="bx bx-current-location me-1"></i><span id="yoneticiKonumText">Konumumu Ekle</span>
                        </button>
                        <small class="text-muted d-block mt-1" id="yoneticiKonumDurum"></small>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatTextarea('aciklama', '', 'İhbar detaylarını yazınız...', 'Açıklama', 'bx bx-align-left', 'form-control', true, '120px', 4) ?>
                    </div>
                    <div class="mb-3" id="ihbarFotoWrapper">
                        <?= Form::FormFileInput('fotograflar[]', 'Fotoğraf (en fazla ' . IhbarModel::MAX_FOTO . ' adet)', 'bx bx-image-add', 'form-control', false, 'accept="image/*" multiple', 'ihbarFotoInput') ?>
                        <div id="ihbarFotoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                    <div class="mb-3" id="ihbarVideoWrapper">
                        <?= Form::FormFileInput('videolar[]', 'Video (en fazla ' . IhbarModel::MAX_VIDEO . ' adet, ' . IhbarModel::VIDEO_MAX_SURE . ' saniye)', 'bx bx-video-plus', 'form-control', false, 'accept="video/*" multiple', 'ihbarVideoInput') ?>
                        <small class="text-muted d-block mt-1">Her video en fazla <?= IhbarModel::VIDEO_MAX_SURE ?> saniye ve <?= round(IhbarModel::VIDEO_MAX_BYTE / 1048576) ?> MB olabilir.</small>
                        <div id="ihbarVideoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger" id="btnIhbarKaydet"><i class="bx bx-send me-1"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İhbar Detay Modal -->
<div class="modal fade" id="modalIhbarDetay" tabindex="-1" aria-hidden="true"
    data-modal-icon="bx bx-error-circle" data-modal-subtitle="İhbar detaylarını inceleyin ve işlem yapın.">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg ihbar-detail-modal">
            <div class="modal-header">
                <h5 class="modal-title">İhbar Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="ihbarDetayContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .ihbar-tabs-card { border-radius: 14px; }
    .ihbar-tabs { gap: .35rem; }
    .ihbar-tabs .nav-link {
        border-radius: 10px;
        padding: .7rem 1.1rem;
        color: #64748b;
        font-weight: 600;
    }
    .ihbar-tabs .nav-link.active {
        background: #6366f1;
        box-shadow: 0 5px 14px rgba(99, 102, 241, .24);
        color: #fff;
    }
    .ihbar-tabs .nav-link:not(.active) .badge {
        background: #eef2ff;
        color: #6366f1;
    }
    .ihbar-tabs .nav-link.active .badge {
        background: rgba(255, 255, 255, .2);
        color: #fff;
    }
    .ihbar-chart-card { border-radius: 14px; }
    .ihbar-chart-card .card-header { padding: 1.25rem 1.25rem .5rem; }
    .ihbar-chart-card .card-header h5 { font-size: 1rem; }
    .ihbar-chart { min-height: 315px; }
    .ihbar-chart-sm { min-height: 270px; }
    .ihbar-chart-badge {
        padding: .35rem .7rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #6366f1;
        font-size: .72rem;
        font-weight: 700;
    }
    .ihbar-performance-item { margin-bottom: 1.35rem; }
    .ihbar-performance-item span { color: #64748b; font-size: .82rem; }
    .ihbar-performance-item strong { color: #334155; font-size: .82rem; }
    .ihbar-performance-item .progress {
        height: 7px;
        background: #eef2f7;
        border-radius: 999px;
    }
    .ihbar-mini-metric {
        min-height: 95px;
        padding: .85rem;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        background: #f8fafc;
        display: grid;
        grid-template-columns: auto 1fr;
        align-items: center;
        gap: .15rem .55rem;
    }
    .ihbar-mini-metric i { font-size: 1.35rem; grid-row: span 2; }
    .ihbar-mini-metric span { color: #64748b; font-size: .7rem; font-weight: 600; }
    .ihbar-mini-metric strong { color: #1e293b; font-size: 1.3rem; line-height: 1; }
    .ihbar-stat-card {
        overflow: hidden;
        position: relative;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .ihbar-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(15, 23, 42, .09) !important;
    }
    .ihbar-stat-card::after {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--ihbar-stat-color);
    }
    .ihbar-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        margin-right: .9rem;
        color: var(--ihbar-stat-color);
        background: var(--ihbar-stat-bg);
    }
    .ihbar-stat-icon i { font-size: 1.5rem; }
    .ihbar-stat-label {
        display: block;
        color: #64748b;
        font-size: .76rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .ihbar-stat-value {
        color: #1e293b;
        font-size: 1.55rem;
        line-height: 1.25;
        font-weight: 700;
    }
    .ihbar-stat-total { --ihbar-stat-color: #6366f1; --ihbar-stat-bg: rgba(99, 102, 241, .12); }
    .ihbar-stat-new { --ihbar-stat-color: #64748b; --ihbar-stat-bg: rgba(100, 116, 139, .12); }
    .ihbar-stat-progress { --ihbar-stat-color: #f59e0b; --ihbar-stat-bg: rgba(245, 158, 11, .12); }
    .ihbar-stat-positive { --ihbar-stat-color: #10b981; --ihbar-stat-bg: rgba(16, 185, 129, .12); }
    .ihbar-stat-negative { --ihbar-stat-color: #ef4444; --ihbar-stat-bg: rgba(239, 68, 68, .12); }
    #ihbarTable_wrapper > .row:last-child .dataTables_paginate {
        display: flex;
        justify-content: flex-end;
    }
    [data-bs-theme="dark"] .ihbar-stat-label { color: #94a3b8; }
    [data-bs-theme="dark"] .ihbar-stat-value { color: #e2e8f0; }
    [data-bs-theme="dark"] .ihbar-tabs .nav-link { color: #94a3b8; }
    [data-bs-theme="dark"] .ihbar-mini-metric {
        background: rgba(15, 23, 42, .35);
        border-color: rgba(148, 163, 184, .15);
    }
    [data-bs-theme="dark"] .ihbar-mini-metric strong,
    [data-bs-theme="dark"] .ihbar-performance-item strong { color: #e2e8f0; }

    .ihbar-detail-modal {
        border-radius: 18px;
        overflow: hidden;
    }
    #modalIhbarDetay .modal-header {
        padding: 1rem 1.35rem;
        border-bottom-color: #eef2f7;
    }
    #ihbarDetayContent .ihbar-detail-summary {
        padding: 1.15rem 1.4rem;
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border-bottom: 1px solid #e8edf5;
    }
    #ihbarDetayContent .ihbar-detail-id {
        display: inline-flex;
        align-items: center;
        padding: .3rem .6rem;
        border-radius: 8px;
        background: rgba(99, 102, 241, .1);
        color: #6366f1;
        font-size: .72rem;
        font-weight: 700;
    }
    #ihbarDetayContent .ihbar-detail-title {
        color: #1e293b;
        font-size: 1.05rem;
        font-weight: 700;
    }
    #ihbarDetayContent .ihbar-detail-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem 1.15rem;
        color: #64748b;
        font-size: .76rem;
    }
    #ihbarDetayContent .ihbar-detail-meta span {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }
    #ihbarDetayContent .ihbar-main-column {
        padding: 1.4rem;
        max-height: 65vh;
        overflow-y: auto;
    }
    #ihbarDetayContent .ihbar-actions-column {
        padding: 1.25rem;
        background: #f8fafc;
        border-left: 1px solid #eef2f7;
        max-height: 65vh;
        overflow-y: auto;
    }
    #ihbarDetayContent .ihbar-content-card {
        padding: 1rem;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        background: #fff;
    }
    #ihbarDetayContent .ihbar-description {
        color: #334155;
        font-size: .88rem;
        line-height: 1.65;
        white-space: pre-wrap;
        margin: 0;
    }
    #ihbarDetayContent .ihbar-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .65rem;
    }
    #ihbarDetayContent .ihbar-info-item {
        min-width: 0;
        padding: .75rem;
        border: 1px solid #e9eef5;
        border-radius: 11px;
        background: #fff;
    }
    #ihbarDetayContent .ihbar-info-item i {
        color: #6366f1;
        font-size: 1rem;
        margin-right: .25rem;
    }
    #ihbarDetayContent .ihbar-info-item strong {
        display: block;
        overflow: hidden;
        color: #334155;
        font-size: .8rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #ihbarDetayContent .ihbar-action-panel {
        padding: 1rem;
        border: 1px solid #e7ebf2;
        border-radius: 14px;
        background: #fff;
        margin-top: .85rem;
    }
    #ihbarDetayContent .ihbar-action-panel-title {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        margin-bottom: .85rem;
    }
    #ihbarDetayContent .ihbar-action-panel-title > i {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 10px;
        background: #eef2ff;
        color: #6366f1;
        font-size: 1.05rem;
    }
    #ihbarDetayContent .ihbar-action-panel-title strong {
        display: block;
        color: #334155;
        font-size: .82rem;
    }
    #ihbarDetayContent .ihbar-action-panel-title small {
        display: block;
        color: #94a3b8;
        font-size: .68rem;
        line-height: 1.35;
    }
    #ihbarDetayContent .ihbar-note-composer {
        display: flex;
        gap: .55rem;
        padding: .55rem;
        border: 1px solid #e7ebf2;
        border-radius: 12px;
        background: #f8fafc;
    }
    #ihbarDetayContent .ihbar-note-composer .form-control {
        border: 0;
        background: transparent;
        box-shadow: none;
    }
    #ihbarDetayContent .ihbar-empty-photos {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .85rem;
        border: 1px dashed #dbe3ee;
        border-radius: 12px;
        color: #94a3b8;
        font-size: .78rem;
    }
    #ihbarDetayContent .ihbar-closed-message {
        padding: .8rem;
        border-radius: 11px;
        background: #ecfdf5;
        color: #047857;
        font-size: .78rem;
        font-weight: 600;
    }
    #ihbarDetayContent .select2-container .select2-selection--multiple {
        min-height: 38px;
        border-color: #dfe5ec;
        border-radius: 9px;
    }
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-detail-summary {
        background: linear-gradient(135deg, #202532 0%, #252941 100%);
        border-bottom-color: rgba(148, 163, 184, .15);
    }
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-detail-title,
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-description,
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-info-item strong,
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-action-panel-title strong { color: #e2e8f0; }
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-actions-column {
        background: rgba(15, 23, 42, .28);
        border-color: rgba(148, 163, 184, .14);
    }
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-content-card,
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-info-item,
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-action-panel {
        background: #262b38;
        border-color: rgba(148, 163, 184, .15);
    }
    [data-bs-theme="dark"] #ihbarDetayContent .ihbar-note-composer {
        background: rgba(15, 23, 42, .3);
        border-color: rgba(148, 163, 184, .15);
    }
    @media (max-width: 991.98px) {
        #modalIhbarDetay .modal-dialog { margin: .75rem; }
        #ihbarDetayContent .ihbar-main-column,
        #ihbarDetayContent .ihbar-actions-column { max-height: none; }
        #ihbarDetayContent .ihbar-actions-column {
            border-top: 1px solid #eef2f7;
            border-left: 0;
        }
    }
    @media (max-width: 575.98px) {
        #ihbarDetayContent .ihbar-info-grid { grid-template-columns: 1fr; }
        #ihbarDetayContent .ihbar-note-composer { flex-direction: column; }
    }

    #ihbarDetayContent .ihbar-side-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #94a3b8;
        display: block;
        margin-bottom: 2px;
    }
    #ihbarDetayContent .ihbar-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #94a3b8;
        margin-bottom: .75rem;
    }
    #ihbarDetayContent .ihbar-foto-thumb {
        width: 92px;
        height: 92px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: transform .15s ease;
    }
    #ihbarDetayContent .ihbar-foto-thumb:hover { transform: scale(1.04); }
    #ihbarDetayContent .ihbar-action-card {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    #ihbarDetayContent .ihbar-action-card h6 {
        font-size: 0.8rem;
        font-weight: 700;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: .75rem;
    }
    /* Tarihçe Timeline */
    #ihbarDetayContent .timeline-container {
        position: relative;
        padding-left: 2.5rem;
    }
    #ihbarDetayContent .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
    }
    #ihbarDetayContent .timeline-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: -1.75rem;
        top: 1.6rem;
        bottom: -0.75rem;
        width: 2px;
        background: #e2e8f0;
    }
    #ihbarDetayContent .timeline-dot {
        position: absolute;
        left: -2.4rem;
        top: 0.15rem;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 0 4px #fff;
        z-index: 1;
    }
    #ihbarDetayContent .timeline-dot i { font-size: .7rem; color: #fff; }
    #ihbarDetayContent .timeline-content {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: .75rem 1rem;
    }
    #ihbarDetayContent .timeline-desc {
        font-size: .82rem;
        color: #334155;
        margin-bottom: .25rem;
    }
    #ihbarDetayContent .timeline-time {
        font-size: .7rem;
        color: #94a3b8;
        font-weight: 600;
    }
</style>

<script src="assets/libs/glightbox/js/glightbox.min.js"></script>
<script>
    const IHBAR_API_URL = 'views/ihbar/api.php';
    const IHBAR_PERSONEL_LISTESI = <?= json_encode(array_map(fn($p) => ['id' => (int) $p->id, 'adi_soyadi' => $p->adi_soyadi], $yonlendirilecekPersoneller), JSON_UNESCAPED_UNICODE) ?>;
    const IHBAR_FORM_HTML = <?= json_encode([
        'ekip' => $ihbarEkipSelectHtml,
        'not' => $ihbarNotInputHtml,
        'sonuc' => $ihbarSonucSelectHtml,
        'tutanak' => $ihbarTutanakInputHtml,
        'olumsuzSebep' => $ihbarOlumsuzSebepHtml,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const IHBAR_TOPLU_PERSONEL_HTML = <?= json_encode($ihbarTopluPersonelSelectHtml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const IHBAR_TOPLU_PERSONEL_TOKENS = <?= json_encode(array_column($ihbarTopluPersoneller, 'token'), JSON_UNESCAPED_SLASHES) ?>;
    const IHBAR_DASHBOARD_DATA = <?= json_encode([
        'durum' => [
            'labels' => array_keys($durumDagilimi),
            'values' => array_values($durumDagilimi),
        ],
        'trend' => [
            'labels' => array_column($aylikTrend, 'etiket'),
            'toplam' => array_column($aylikTrend, 'toplam'),
            'sonuclanan' => array_column($aylikTrend, 'sonuclanan'),
        ],
        'ilce' => [
            'labels' => array_keys($ilceDagilimi),
            'values' => array_values($ilceDagilimi),
        ],
    ], JSON_UNESCAPED_UNICODE) ?>;
    const IHBAR_MAX_FOTO = <?= IhbarModel::MAX_FOTO ?>;
    const IHBAR_MAX_VIDEO = <?= IhbarModel::MAX_VIDEO ?>;
    const IHBAR_VIDEO_MAX_SURE = <?= IhbarModel::VIDEO_MAX_SURE ?>;
    const IHBAR_VIDEO_MAX_BYTE = <?= IhbarModel::VIDEO_MAX_BYTE ?>;
    let ihbarSeciliFotolar = [];
    let ihbarSeciliVideolar = [];
    let ihbarAktifId = null;
    let ihbarAktifDetay = null;
    let ihbarFotoLightbox = null;
    const ihbarEscapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);

    document.addEventListener('DOMContentLoaded', function () {
        $('#ihbarIlce').select2({
            dropdownParent: $('#modalYeniIhbar'),
            width: '100%',
            placeholder: 'İlçe seçin...'
        });
        document.getElementById('formIhbarAyarlar')?.addEventListener('submit', function (event) {
            event.preventDefault();
            const data = new FormData(this); data.append('action', 'saveSettings');
            if (!document.getElementById('ihbarAyniBolgeOnceligi').checked) data.set('ihbar_ayni_bolge_onceligi', '0');
            const button = document.getElementById('btnIhbarAyarKaydet'); button.disabled = true;
            fetch(IHBAR_API_URL, { method: 'POST', body: data }).then(r => r.json()).then(res => {
                button.disabled = false;
                if (res.success) Swal.fire('Başarılı', res.message, 'success').then(() => location.reload());
                else Swal.fire('Hata', res.message || 'Ayarlar kaydedilemedi.', 'error');
            }).catch(() => { button.disabled = false; Swal.fire('Hata', 'Sunucuya ulaşılamadı.', 'error'); });
        });

        const ihbarTable = $('#ihbarTable').DataTable($.extend(true, {}, getDatatableOptions(), {
            dom: 'rt' +
                 '<"row mt-3 align-items-center g-2"' +
                    '<"col-12 col-md-7 d-flex flex-wrap align-items-center gap-3"i l>' +
                    '<"col-12 col-md-5 d-flex justify-content-md-end"p>' +
                 '>'
        }));

        renderIhbarDashboardCharts();

        document.getElementById('ihbar-list-tab')?.addEventListener('shown.bs.tab', function () {
            ihbarTable.columns.adjust().responsive.recalc();
        });

        document.getElementById('ihbarExportExcel')?.addEventListener('click', function () {
            ihbarTable.button('.buttons-excel').trigger();
        });

        document.getElementById('ihbarVideoInput')?.addEventListener('change', async function () {
            const onizleme = document.getElementById('ihbarVideoPreview');
            onizleme.innerHTML = '';
            ihbarSeciliVideolar = [];

            if (this.files.length > IHBAR_MAX_VIDEO) {
                Swal.fire('Uyarı', `En fazla ${IHBAR_MAX_VIDEO} video seçebilirsiniz.`, 'warning');
                this.value = '';
                return;
            }

            onizleme.innerHTML = '<span class="text-muted small">Videolar kontrol ediliyor...</span>';
            const kabulEdilen = [];
            const hatalar = [];

            for (const dosya of Array.from(this.files)) {
                try {
                    kabulEdilen.push(await VideoKontrol.incele(dosya, IHBAR_VIDEO_MAX_SURE, IHBAR_VIDEO_MAX_BYTE));
                } catch (hata) {
                    hatalar.push(`${dosya.name}: ${hata.message}`);
                }
            }

            ihbarSeciliVideolar = kabulEdilen;
            onizleme.innerHTML = '';

            kabulEdilen.forEach(v => {
                const kutu = document.createElement('div');
                kutu.className = 'position-relative';
                kutu.innerHTML = v.kapak
                    ? `<img src="${v.kapak}" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6">`
                    : `<div style="width:70px;height:70px;border-radius:8px;border:1px solid #dee2e6" class="d-flex align-items-center justify-content-center bg-light"><i class="bx bx-video fs-3 text-muted"></i></div>`;
                kutu.innerHTML += `<span class="badge bg-dark position-absolute bottom-0 end-0 m-1" style="font-size:.65rem">${VideoKontrol.sureBicimle(v.sure)}</span>`;
                onizleme.appendChild(kutu);
            });

            if (hatalar.length) {
                Swal.fire('Bazı videolar eklenemedi', hatalar.join('<br>'), 'warning');
                if (kabulEdilen.length === 0) this.value = '';
            }
        });

        document.getElementById('ihbarFotoInput')?.addEventListener('change', function (e) {
            if (this.files.length > IHBAR_MAX_FOTO) {
                Swal.fire('Uyarı', `En fazla ${IHBAR_MAX_FOTO} fotoğraf seçebilirsiniz.`, 'warning');
                this.value = '';
                document.getElementById('ihbarFotoPreview').innerHTML = '';
                return;
            }
            const preview = document.getElementById('ihbarFotoPreview');
            preview.innerHTML = '';
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });

        document.getElementById('formYeniIhbar').addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const editId = document.getElementById('ihbarFormId').value;
            formData.append('action', editId ? 'update' : 'create');

            const btn = document.getElementById('btnIhbarKaydet');
            btn.disabled = true;

            // Ham fotoğraflar sunucuya gitmeden önce küçültülür; sıkıştırıcı yüklenemezse
            // dosyalar olduğu gibi gider ve sunucu tarafındaki optimizasyon devreye girer.
            const fotoInput = document.getElementById('ihbarFotoInput');
            if (window.ResimSikistir && fotoInput && fotoInput.files.length) {
                try {
                    const kucukler = await window.ResimSikistir.listeyiKucult(fotoInput.files, 1600, 0.75);
                    formData.delete('fotograflar[]');
                    kucukler.forEach(dosya => formData.append('fotograflar[]', dosya, dosya.name));
                } catch (hata) {
                    console.error('Fotoğraf küçültme başarısız, orijinal dosyalar gönderiliyor:', hata);
                }
            }

            // Videolar seçim anında doğrulanmıştı; süre ve kapak karesi yanlarında gider.
            formData.delete('videolar[]');
            ihbarSeciliVideolar.forEach(v => {
                formData.append('videolar[]', v.dosya, v.dosya.name);
                formData.append('video_sureleri[]', v.sure);
                formData.append('video_kapaklari[]', v.kapak || '');
            });

            fetch(IHBAR_API_URL, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    if (res.success) {
                        Swal.fire('Başarılı', res.message || 'İşlem tamamlandı.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Hata', res.message || 'Bir hata oluştu.', 'error');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    Swal.fire('Hata', 'Sunucuya ulaşılamadı.', 'error');
                });
        });
    });

    function ihbarYeniAc() {
        document.getElementById('formYeniIhbar').reset();
        $('#ihbarIlce').val('').trigger('change');
        document.getElementById('ihbarFormId').value = '';
        document.querySelector('#modalYeniIhbar .modal-title').innerHTML = '<i class="bx bx-error-circle me-2"></i>Yeni İhbar Ekle';
        document.getElementById('btnIhbarKaydet').innerHTML = '<i class="bx bx-send me-1"></i>Kaydet';
        document.getElementById('ihbarFotoWrapper').classList.remove('d-none');
        document.getElementById('yoneticiKonumText').textContent = 'Konumumu Ekle';
        document.getElementById('yoneticiKonumDurum').textContent = '';
        ihbarSeciliFotolar = [];
        ihbarSeciliVideolar = [];
        document.getElementById('ihbarFotoPreview').innerHTML = '';
        document.getElementById('ihbarVideoPreview').innerHTML = '';
        new bootstrap.Modal(document.getElementById('modalYeniIhbar')).show();
    }

    async function ihbarYenidenYonlendirAc() {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTopluYonlendir'));
        const content = document.getElementById('topluYonlendirIcerik');
        content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-warning"></div></div>';
        modal.show();
        try {
            const requestResponse = await fetch(IHBAR_API_URL, {
                method: 'POST', body: new URLSearchParams({ action: 'requestFreshLocations' })
            }).then(r => r.json());
            let bekleyen = Number(requestResponse.data?.bekleyen || 0);
            const baslangic = Date.now();
            while (bekleyen > 0 && Date.now() - baslangic < 30000) {
                const kalan = Math.max(0, Math.ceil((30000 - (Date.now() - baslangic)) / 1000));
                content.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-warning mb-3"></div>
                    <h6>Görevdeki personellerin güncel konumları alınıyor</h6>
                    <div class="text-muted small">${bekleyen} personel bekleniyor · en fazla ${kalan} saniye</div></div>`;
                await new Promise(resolve => setTimeout(resolve, 3000));
                const status = await fetch(IHBAR_API_URL, {
                    method: 'POST', body: new URLSearchParams({ action: 'freshLocationStatus' })
                }).then(r => r.json());
                bekleyen = Number(status.data?.bekleyen || 0);
            }
        } catch (error) {
            console.warn('Güncel konum bekleme işlemi tamamlanamadı:', error);
        }
        fetch(IHBAR_API_URL, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'reassignPreview',
                personel_tokens: JSON.stringify(IHBAR_TOPLU_PERSONEL_TOKENS)
            })
        })
            .then(r => r.json()).then(res => {
                if (!res.success) throw new Error(res.message || 'Liste alınamadı.');
                if (!res.data.length) {
                    content.innerHTML = '<div class="alert alert-info mb-0">Yeniden yönlendirilebilecek ihbar bulunmuyor.</div>';
                    return;
                }
                content.innerHTML = `<div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th style="width:45px"></th><th>İhbar</th><th>Mevcut ekip</th><th style="min-width:300px">Yeni personel</th></tr></thead>
                    <tbody>${res.data.map((row, index) => `<tr>
                        <td><input class="form-check-input toplu-ihbar-sec" type="checkbox" checked data-index="${index}" data-token="${row.token}"></td>
                        <td><strong>#${Number(row.id)}</strong><div>${ihbarEscapeHtml(row.ilce || '-')} / ${ihbarEscapeHtml(row.mahalle || '-')}</div></td>
                        <td>${ihbarEscapeHtml(row.mevcut_ekip || 'Atanmamış')}</td>
                        <td><div class="toplu-personel-holder" data-index="${index}">${IHBAR_TOPLU_PERSONEL_HTML}</div></td>
                    </tr>`).join('')}</tbody></table></div>`;
                content.querySelectorAll('.toplu-personel-holder').forEach((holder, index) => {
                    const select = holder.querySelector('select');
                    const row = res.data[index];
                    select.id = `topluPersonel_${index}`;
                    select.name = `toplu_personel_${index}`;
                    $(select).val(row.onerilen_personel_token || '').select2({
                        dropdownParent: $('#modalTopluYonlendir'), width: '100%', placeholder: 'Personel seçin...'
                    });
                });
            }).catch(err => { content.innerHTML = `<div class="alert alert-danger mb-0">${err.message}</div>`; });
    }

    function ihbarTopluYonlendir() {
        const assignments = [];
        document.querySelectorAll('.toplu-ihbar-sec:checked').forEach(check => {
            const value = document.getElementById(`topluPersonel_${check.dataset.index}`)?.value;
            if (value) assignments.push({ ihbar_token: check.dataset.token, personel_token: value });
        });
        if (!assignments.length) {
            Swal.fire('Uyarı', 'En az bir ihbar ve personel seçmelisiniz.', 'warning'); return;
        }
        const data = new FormData(); data.append('action', 'bulkReassign');
        data.append('assignments', JSON.stringify(assignments));
        const button = document.getElementById('btnTopluYonlendirKaydet'); button.disabled = true;
        fetch(IHBAR_API_URL, { method: 'POST', body: data }).then(r => r.json()).then(res => {
            button.disabled = false;
            if (res.success) Swal.fire('Başarılı', res.message, 'success').then(() => location.reload());
            else Swal.fire('Hata', res.message || 'Yönlendirme yapılamadı.', 'error');
        }).catch(() => { button.disabled = false; Swal.fire('Hata', 'Sunucuya ulaşılamadı.', 'error'); });
    }

    function ihbarDuzenleFormAc(detay) {
        const form = document.getElementById('formYeniIhbar');
        form.reset();
        document.getElementById('ihbarFormId').value = detay.id;
        $('#ihbarIlce').val(detay.ilce || '').trigger('change');
        form.querySelector('[name=mahalle]').value = detay.mahalle || '';
        form.querySelector('[name=telefon]').value = detay.telefon || '';
        form.querySelector('[name=komsu_abone_no]').value = detay.komsu_abone_no || '';
        form.querySelector('[name=aciklama]').value = detay.aciklama || '';
        form.querySelector('[name=konum_link]').value = detay.konum_link || '';
        form.querySelector('[name=konum_lat]').value = detay.konum_lat || '';
        form.querySelector('[name=konum_lng]').value = detay.konum_lng || '';
        form.querySelector('[name=konum_dogruluk]').value = detay.konum_dogruluk || '';
        document.getElementById('yoneticiKonumText').textContent =
            detay.konum_lat && detay.konum_lng ? 'Konumu Güncelle' : 'Konumumu Ekle';
        document.getElementById('yoneticiKonumDurum').textContent =
            detay.konum_lat && detay.konum_lng ? `${detay.konum_lat}, ${detay.konum_lng}` : '';

        document.querySelector('#modalYeniIhbar .modal-title').innerHTML = '<i class="bx bx-edit me-2"></i>İhbarı Düzenle';
        document.getElementById('btnIhbarKaydet').innerHTML = '<i class="bx bx-save me-1"></i>Güncelle';
        document.getElementById('ihbarFotoWrapper').classList.add('d-none');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIhbarDetay')).hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalYeniIhbar')).show();
    }

    function ihbarDuzenle(id) {
        fetch(IHBAR_API_URL, {
            method: 'POST',
            body: new URLSearchParams({ action: 'detay', id: id })
        }).then(r => r.json()).then(res => {
            if (!res.success) {
                Swal.fire('Hata', res.message || 'Kayıt bulunamadı.', 'error');
                return;
            }
            ihbarAktifDetay = res.data;
            ihbarDuzenleFormAc(res.data);
        }).catch(() => Swal.fire('Hata', 'Kayıt bilgileri alınamadı.', 'error'));
    }

    function ihbarSil(id) {
        Swal.fire({
            title: 'İhbar silinsin mi?',
            text: 'Bu kayıt listelerden kaldırılacaktır.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'Vazgeç',
            reverseButtons: true
        }).then(result => {
            if (!result.isConfirmed) return;

            fetch(IHBAR_API_URL, {
                method: 'POST',
                body: new URLSearchParams({ action: 'delete', id: id })
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Silindi', res.message || 'İhbar silindi.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Hata', res.message || 'İhbar silinemedi.', 'error');
                }
            }).catch(() => Swal.fire('Hata', 'Sunucuya ulaşılamadı.', 'error'));
        });
    }

    function ihbarYoneticiKonumAl() {
        if (!navigator.geolocation) {
            Swal.fire('Hata', 'Tarayıcınız konum paylaşımını desteklemiyor.', 'error');
            return;
        }

        const text = document.getElementById('yoneticiKonumText');
        const durum = document.getElementById('yoneticiKonumDurum');
        text.textContent = 'Konum alınıyor...';

        navigator.geolocation.getCurrentPosition(position => {
            document.getElementById('konum_lat').value = position.coords.latitude.toFixed(7);
            document.getElementById('konum_lng').value = position.coords.longitude.toFixed(7);
            document.getElementById('konum_dogruluk').value = position.coords.accuracy.toFixed(2);
            text.textContent = 'Konum Eklendi';
            durum.textContent = `${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)} · ${Math.round(position.coords.accuracy)} m`;
        }, () => {
            text.textContent = 'Konumumu Ekle';
            Swal.fire('Konum Alınamadı', 'Konum iznini kontrol edip tekrar deneyiniz.', 'warning');
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
    }

    function renderIhbarDashboardCharts() {
        if (typeof ApexCharts === 'undefined') {
            setTimeout(renderIhbarDashboardCharts, 250);
            return;
        }

        const darkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const textColor = darkMode ? '#94a3b8' : '#64748b';
        const gridColor = darkMode ? 'rgba(148, 163, 184, .14)' : '#eef2f7';
        const sharedChart = {
            fontFamily: 'inherit',
            foreColor: textColor,
            toolbar: { show: false },
            animations: { enabled: true, speed: 550 }
        };

        new ApexCharts(document.querySelector('#ihbarTrendChart'), {
            chart: { ...sharedChart, type: 'area', height: 315 },
            series: [
                { name: 'Gelen İhbar', data: IHBAR_DASHBOARD_DATA.trend.toplam },
                { name: 'Sonuçlanan', data: IHBAR_DASHBOARD_DATA.trend.sonuclanan }
            ],
            colors: ['#6366f1', '#10b981'],
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: .28, opacityTo: .03, stops: [0, 95] }
            },
            markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
            dataLabels: { enabled: false },
            xaxis: { categories: IHBAR_DASHBOARD_DATA.trend.labels, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value) } },
            grid: { borderColor: gridColor, strokeDashArray: 4 },
            legend: { position: 'top', horizontalAlign: 'right' },
            tooltip: { theme: darkMode ? 'dark' : 'light' }
        }).render();

        new ApexCharts(document.querySelector('#ihbarDurumChart'), {
            chart: { ...sharedChart, type: 'donut', height: 315 },
            series: IHBAR_DASHBOARD_DATA.durum.values,
            labels: IHBAR_DASHBOARD_DATA.durum.labels,
            colors: ['#64748b', '#3b82f6', '#f59e0b', '#10b981', '#ef4444'],
            stroke: { width: 3, colors: [darkMode ? '#262b38' : '#fff'] },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '12px' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Toplam',
                                formatter: () => IHBAR_DASHBOARD_DATA.durum.values.reduce((a, b) => a + b, 0)
                            }
                        }
                    }
                }
            },
            noData: { text: 'Henüz ihbar bulunmuyor' },
            tooltip: { theme: darkMode ? 'dark' : 'light' }
        }).render();

        new ApexCharts(document.querySelector('#ihbarIlceChart'), {
            chart: { ...sharedChart, type: 'bar', height: 270 },
            series: [{ name: 'İhbar', data: IHBAR_DASHBOARD_DATA.ilce.values }],
            colors: ['#6366f1'],
            plotOptions: {
                bar: { horizontal: true, borderRadius: 5, barHeight: '52%', distributed: false }
            },
            dataLabels: { enabled: true, textAnchor: 'start', offsetX: 5, style: { colors: ['#fff'] } },
            xaxis: { categories: IHBAR_DASHBOARD_DATA.ilce.labels, min: 0, labels: { formatter: value => Math.round(value) } },
            grid: { borderColor: gridColor, strokeDashArray: 4 },
            tooltip: { theme: darkMode ? 'dark' : 'light' },
            noData: { text: 'İlçe verisi bulunmuyor' }
        }).render();
    }

    function ihbarDurumBadgeJs(durum) {
        const map = {
            yeni: ['secondary', 'Yeni'], yonlendirildi: ['info', 'Yönlendirildi'],
            islemde: ['warning', 'İşlemde'], olumlu: ['success', 'Olumlu'], olumsuz: ['danger', 'Olumsuz']
        };
        const [cls, text] = map[durum] || ['secondary', durum];
        return `<span class="badge bg-${cls}">${text}</span>`;
    }

    function ihbarDetay(id) {
        ihbarAktifId = id;
        const content = document.getElementById('ihbarDetayContent');
        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        new bootstrap.Modal(document.getElementById('modalIhbarDetay')).show();

        fetch(IHBAR_API_URL, {
            method: 'POST',
            body: new URLSearchParams({ action: 'detay', id: id })
        }).then(r => r.json()).then(res => {
            if (!res.success) {
                content.innerHTML = '<div class="alert alert-danger">' + (res.message || 'Kayıt bulunamadı') + '</div>';
                return;
            }
            ihbarAktifDetay = res.data;
            content.innerHTML = renderIhbarDetay(res.data);
            if (ihbarFotoLightbox) ihbarFotoLightbox.destroy();
            ihbarFotoLightbox = GLightbox({
                selector: '.ihbar-foto-lightbox',
                loop: true,
                touchNavigation: true
            });
            ihbarDetaySecimleriHazirla((res.data.atanan_ekip || []).map(a => String(a.personel_id)));
            if (res.data.durum === 'olumlu' || res.data.durum === 'olumsuz') {
                const sonucSelect = document.getElementById('ihbarSonucDurum');
                if (sonucSelect) {
                    sonucSelect.value = res.data.durum;
                }
                const tutanakInput = document.getElementById('ihbarTutanakNo');
                if (tutanakInput) {
                    tutanakInput.value = res.data.tutanak_no || '';
                }
                const sebepInput = document.getElementById('ihbarOlumsuzSebep');
                if (sebepInput) {
                    sebepInput.value = res.data.olumsuz_sebep || '';
                }
                ihbarSonucAlanGuncelle();
            }
        }).catch(() => {
            content.innerHTML = '<div class="alert alert-danger">Bir hata oluştu.</div>';
        });
    }

    function ihbarTarihceGorsel(tip, aciklama) {
        if (tip === 'yonlendirildi') return { icon: 'bx bx-transfer-alt', bg: '#3b82f6' };
        if (tip === 'not') return { icon: 'bx bx-note', bg: '#f59e0b' };
        if (tip === 'durum_degisti') {
            return aciklama.includes('olumsuz')
                ? { icon: 'bx bx-x-circle', bg: '#ef4444' }
                : { icon: 'bx bx-check-circle', bg: '#22c55e' };
        }
        return { icon: 'bx bx-plus-circle', bg: '#64748b' };
    }

    function renderIhbarDetay(d) {
        const atananIds = (d.atanan_ekip || []).map(a => a.personel_id);
        const ekipAdi = (d.atanan_ekip || []).map(a => a.adi_soyadi).join(', ') || 'Henüz atanmadı';
        const bildirenAdi = d.bildiren_personel_adi || d.olusturan_user_adi || 'Belirtilmemiş';
        const tarih = d.created_at
            ? new Date(d.created_at.replace(' ', 'T')).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
            : '-';
        let konumUrl = '';
        if (d.konum_link) {
            try {
                const parsedUrl = new URL(d.konum_link);
                if (['http:', 'https:'].includes(parsedUrl.protocol)) konumUrl = parsedUrl.href;
            } catch (e) {}
        }
        if (!konumUrl && d.konum_lat && d.konum_lng) {
            konumUrl = `https://www.google.com/maps?q=${encodeURIComponent(d.konum_lat)},${encodeURIComponent(d.konum_lng)}`;
        }

        const medyalar = d.fotograflar || [];
        const fotoHtml = medyalar.map(f => {
            if (f.medya_tipi === 'video') {
                const sureRozeti = f.sure_saniye
                    ? `<span class="badge bg-dark position-absolute bottom-0 end-0 m-1" style="font-size:.65rem">${VideoKontrol.sureBicimle(f.sure_saniye)}</span>`
                    : '';
                const kapak = f.kucuk_var
                    ? `<img src="${IHBAR_API_URL}?action=foto&boyut=kucuk&foto_id=${f.id}" class="ihbar-foto-thumb" loading="lazy" alt="İhbar videosu">`
                    : `<div class="ihbar-foto-thumb d-flex align-items-center justify-content-center bg-light"><i class="bx bx-video fs-3 text-muted"></i></div>`;
                return `<a href="${IHBAR_API_URL}?action=foto&foto_id=${f.id}" target="_blank" rel="noopener"
                            class="position-relative d-inline-block" title="Videoyu oynat">
                            ${kapak}
                            <span class="position-absolute top-50 start-50 translate-middle badge rounded-circle bg-dark bg-opacity-75 p-2"><i class="bx bx-play fs-5"></i></span>
                            ${sureRozeti}
                        </a>`;
            }
            return `<a href="${IHBAR_API_URL}?action=foto&foto_id=${f.id}" class="ihbar-foto-lightbox"
                data-gallery="ihbar-${d.id}" data-type="image" data-title="İhbar #${d.id}" title="Fotoğrafı büyüt">
                <img src="${IHBAR_API_URL}?action=foto&boyut=kucuk&foto_id=${f.id}" class="ihbar-foto-thumb" loading="lazy" alt="İhbar fotoğrafı">
            </a>`;
        }).join('') || `
            <div class="ihbar-empty-photos w-100">
                <i class="bx bx-image-alt fs-4"></i>
                <span>Bu ihbara ait fotoğraf eklenmemiş.</span>
            </div>`;

        const tarihceHtml = (d.tarihce || []).map(t => {
            const g = ihbarTarihceGorsel(t.tip, t.aciklama);
            return `
            <div class="timeline-item">
                <div class="timeline-dot" style="background:${g.bg}"><i class="${g.icon}"></i></div>
                <div class="timeline-content">
                    <p class="timeline-desc mb-1">${t.aciklama}</p>
                    <span class="timeline-time">${t.ekleyen_adi || '-'} • ${new Date(t.created_at).toLocaleString('tr-TR')}</span>
                </div>
            </div>`;
        }).join('');

        const kapaliMi = (d.durum === 'olumlu' || d.durum === 'olumsuz');

        return `
        <div class="ihbar-detail-summary">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="ihbar-detail-id">#${d.id}</span>
                        ${ihbarDurumBadgeJs(d.durum)}
                    </div>
                    <div class="ihbar-detail-title">${d.ilce || 'İlçe belirtilmemiş'} / ${d.mahalle || 'Mahalle belirtilmemiş'}</div>
                    <div class="ihbar-detail-meta mt-2">
                        <span><i class="bx bx-calendar"></i>${tarih}</span>
                        <span><i class="bx bx-user"></i>${bildirenAdi}</span>
                        <span><i class="bx bx-group"></i>${ekipAdi}</span>
                        <span><i class="bx bx-phone"></i>${d.telefon || 'Telefon belirtilmemiş'}</span>
                        ${d.komsu_abone_no ? `<span><i class="bx bx-id-card"></i>Abone No: ${d.komsu_abone_no}</span>` : ''}
                        ${konumUrl ? `
                            <a href="${konumUrl}" target="_blank" rel="noopener noreferrer"
                                class="text-primary fw-semibold">
                                <i class="bx bx-map"></i>Konumu Haritada Aç
                            </a>` : ''}
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-0">
            <div class="col-lg-8 ihbar-main-column">
                <section class="mb-4">
                    <h6 class="ihbar-section-title"><i class="bx bx-align-left me-1"></i>İhbar Açıklaması</h6>
                    <div class="ihbar-content-card">
                        <p class="ihbar-description">${d.aciklama || 'Açıklama girilmemiş.'}</p>
                    </div>
                </section>

                <section class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="ihbar-section-title mb-0"><i class="bx bx-images me-1"></i>Fotoğraflar</h6>
                        ${medyalar.length ? `<span class="text-muted small">${medyalar.filter(m => m.medya_tipi !== 'video').length} fotoğraf${medyalar.some(m => m.medya_tipi === 'video') ? `, ${medyalar.filter(m => m.medya_tipi === 'video').length} video` : ''}</span>` : ''}
                    </div>
                    <div class="d-flex flex-wrap gap-2">${fotoHtml}</div>
                </section>

                <section class="mb-4">
                    <h6 class="ihbar-section-title"><i class="bx bx-message-square-add me-1"></i>Yeni Not</h6>
                    <div class="ihbar-note-composer">
                        <div class="flex-grow-1">${IHBAR_FORM_HTML.not}</div>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 flex-shrink-0" onclick="ihbarNotEkle()">
                            <i class="bx bx-plus me-1"></i>Not Ekle
                        </button>
                    </div>
                </section>

                <section>
                    <h6 class="ihbar-section-title"><i class="bx bx-history me-1"></i>İşlem Geçmişi</h6>
                    <div class="timeline-container">
                        ${tarihceHtml || '<div class="text-muted small py-3">Henüz işlem kaydı bulunmuyor.</div>'}
                    </div>
                </section>
            </div>

            <aside class="col-lg-4 ihbar-actions-column">
                ${d.durum === 'olumlu' ? `
                    <div class="ihbar-closed-message">
                        <i class="bx bx-check-circle me-1"></i>Olumlu sonuçlandı
                        <div class="mt-1 fw-normal">Tutanak No: <strong>${d.tutanak_no || '-'}</strong></div>
                    </div>` : ''}
                ${d.durum === 'olumsuz' ? `
                    <div class="alert alert-danger mb-0 py-2 small">
                        <i class="bx bx-x-circle me-1"></i><strong>Olumsuz sonuçlandı</strong>
                        <div class="mt-1">${d.olumsuz_sebep || '-'}</div>
                    </div>` : ''}

                <div class="ihbar-action-panel ${kapaliMi ? 'mt-3' : 'mt-0'}">
                    <div class="ihbar-action-panel-title">
                        <i class="bx bx-transfer-alt"></i>
                        <div>
                            <strong>Ekibe Yönlendir</strong>
                            <small>Görevlendirilecek bir veya daha fazla personeli seçin.</small>
                        </div>
                    </div>
                    ${IHBAR_FORM_HTML.ekip}
                    <button type="button" class="btn btn-primary btn-sm w-100 rounded-pill mt-3" onclick="ihbarYonlendir()">
                        <i class="bx bx-send me-1"></i>Seçilen Ekibe Yönlendir
                    </button>
                </div>

                <div class="ihbar-action-panel">
                    <div class="ihbar-action-panel-title">
                        <i class="bx bx-check-shield" style="background:#ecfdf5;color:#10b981"></i>
                        <div>
                            <strong>${kapaliMi ? 'Sonucu Yeniden Düzenle' : 'İhbarı Sonuçlandır'}</strong>
                            <small>${kapaliMi ? 'Hatalı sonuçlandıysa sonucu ve bilgileri güncelleyebilirsiniz.' : 'İşlem tamamlandıysa sonucu ve gerekli bilgileri girin.'}</small>
                        </div>
                    </div>
                    ${IHBAR_FORM_HTML.sonuc}
                    <div class="d-none mt-2" id="ihbarTutanakNoWrapper">${IHBAR_FORM_HTML.tutanak}</div>
                    <div class="d-none mt-2" id="ihbarOlumsuzSebepWrapper">${IHBAR_FORM_HTML.olumsuzSebep}</div>
                    <button type="button" class="btn btn-success btn-sm w-100 rounded-pill mt-1" onclick="ihbarSonuclandir()">
                        <i class="bx bx-check me-1"></i>${kapaliMi ? 'Sonucu Güncelle' : 'Sonucu Kaydet'}
                    </button>
                    ${kapaliMi ? `
                    <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill mt-2" onclick="ihbarSonucIptal()">
                        <i class="bx bx-undo me-1"></i>Sonuçlanma Durumunu İptal Et
                    </button>` : ''}
                </div>
            </aside>
        </div>
        `;
    }

    function ihbarDetaySecimleriHazirla(atananIds = []) {
        const ekipSelect = $('#ihbarEkipSelect');
        if (!ekipSelect.length) return;

        ekipSelect.val(atananIds);
        ekipSelect.select2({
            dropdownParent: $('#modalIhbarDetay'),
            width: '100%',
            placeholder: 'Personel seçin...',
            closeOnSelect: false
        });
    }

    function ihbarSonucAlanGuncelle() {
        const val = document.getElementById('ihbarSonucDurum').value;
        document.getElementById('ihbarTutanakNoWrapper').classList.toggle('d-none', val !== 'olumlu');
        document.getElementById('ihbarOlumsuzSebepWrapper').classList.toggle('d-none', val !== 'olumsuz');
    }

    function ihbarYonlendir() {
        const ids = $('#ihbarEkipSelect').val();
        if (!ids || ids.length === 0) {
            Swal.fire('Uyarı', 'En az bir personel seçmelisiniz.', 'warning');
            return;
        }

        const params = new URLSearchParams();
        params.append('action', 'assign');
        params.append('id', ihbarAktifId);
        ids.forEach(i => params.append('personel_ids[]', i));

        fetch(IHBAR_API_URL, {
            method: 'POST',
            body: params
        }).then(r => r.json()).then(res => {
            if (res.success) {
                Swal.fire('Başarılı', res.message || 'Yönlendirildi.', 'success').then(() => { ihbarDetay(ihbarAktifId); location.reload(); });
            } else {
                Swal.fire('Hata', res.message || 'Bir hata oluştu.', 'error');
            }
        });
    }

    function ihbarNotEkle() {
        const not = document.getElementById('ihbarNotInput').value.trim();
        if (!not) {
            Swal.fire('Uyarı', 'Lütfen bir not yazın.', 'warning');
            return;
        }
        fetch(IHBAR_API_URL, {
            method: 'POST',
            body: new URLSearchParams({ action: 'addNote', id: ihbarAktifId, aciklama: not })
        }).then(r => r.json()).then(res => {
            if (res.success) {
                document.getElementById('ihbarNotInput').value = '';
                ihbarDetay(ihbarAktifId);
            } else {
                Swal.fire('Hata', res.message || 'Bir hata oluştu.', 'error');
            }
        });
    }

    function ihbarSonuclandir() {
        const durum = document.getElementById('ihbarSonucDurum').value;
        if (!durum) {
            Swal.fire('Uyarı', 'Lütfen sonuç seçiniz.', 'warning');
            return;
        }
        const tutanakNo = document.getElementById('ihbarTutanakNo').value.trim();
        const sebep = document.getElementById('ihbarOlumsuzSebep').value.trim();

        if (durum === 'olumlu' && !tutanakNo) {
            Swal.fire('Uyarı', 'Tutanak numarası girilmelidir.', 'warning');
            return;
        }
        if (durum === 'olumsuz' && !sebep) {
            Swal.fire('Uyarı', 'Olumsuz sebebi girilmelidir.', 'warning');
            return;
        }

        fetch(IHBAR_API_URL, {
            method: 'POST',
            body: new URLSearchParams({ action: 'close', id: ihbarAktifId, durum: durum, tutanak_no: tutanakNo, sebep: sebep })
        }).then(r => r.json()).then(res => {
            if (res.success) {
                Swal.fire('Başarılı', res.message || 'İhbar sonuçlandırıldı.', 'success').then(() => location.reload());
            } else {
                Swal.fire('Hata', res.message || 'Bir hata oluştu.', 'error');
            }
        });
    }

    function ihbarSonucIptal() {
        Swal.fire({
            title: 'Sonuçlanma iptal edilsin mi?',
            text: 'Sonuç bilgileri temizlenecek ve ihbar yeniden işlem bekleyen duruma alınacaktır.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, İptal Et',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#ef4444'
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch(IHBAR_API_URL, {
                method: 'POST',
                body: new URLSearchParams({ action: 'cancelResult', id: ihbarAktifId })
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Başarılı', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Hata', res.message || 'Sonuç iptal edilemedi.', 'error');
                }
            }).catch(() => Swal.fire('Hata', 'Sunucuya ulaşılamadı.', 'error'));
        });
    }
</script>
