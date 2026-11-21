<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\SozlesmeSablonModel;
use App\Service\Gate;
use App\Service\SozlesmeSablonService;

$maintitle = "Personel";
$title = "Sözleşme Şablonları";

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$Model = new SozlesmeSablonModel();
$hazir = $Model->htmlHazirMi();
$kayit = $hazir ? $Model->aktifHtml($firmaId) : null;
$duzenleyebilir = Gate::allows('personel/sozlesme-sablonlari-duzenle') && $hazir;
$icerik = SozlesmeSablonService::editorIcinHazirla(SozlesmeSablonService::htmlTemizle($kayit ? (string) $kayit->html_icerik : SozlesmeSablonService::varsayilanHtml()));
$cerceve = $kayit ? (int) $kayit->sayfa_cercevesi === 1 : true;
$alanlar = SozlesmeSablonService::alanTanimlari();
$gruplar = [
    'Personel Bilgileri' => ['AD_SOYAD' => 'Adı Soyadı', 'BABA_ADI' => 'Baba Adı', 'ANNE_ADI' => 'Anne Adı', 'TC_KIMLIK_NO' => 'T.C. Kimlik No', 'CEP_TELEFONU' => 'Cep Telefonu', 'ADRES' => 'İkamet Adresi'],
    'Doğum Bilgileri' => ['DOGUM_YERI' => 'Doğum Yeri', 'DOGUM_YILI' => 'Doğum Yılı', 'DOGUM_TARIHI' => 'Doğum Tarihi', 'DOGUM_YERI_YILI' => 'Doğum Yeri ve Yılı'],
    'Görev Bilgileri' => ['GOREV' => 'Görevi', 'DEPARTMAN' => 'Departman', 'ISE_BASLAMA_TARIHI' => 'İşe Başlama Tarihi', 'BUGUN' => 'Bugünün Tarihi'],
];
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$apiUrl = 'views/personel/api/APIsozlesme-sablonlari.php';
$ayarlar = [
    'api' => $apiUrl,
    'duzenleyebilir' => $duzenleyebilir,
    'icerik' => $icerik,
    'varsayilan' => SozlesmeSablonService::editorIcinHazirla(SozlesmeSablonService::varsayilanHtml()),
    'cerceve' => $cerceve,
];
?>
<link rel="stylesheet" href="assets/css/sozlesme-sablon-editor.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/sozlesme-sablon-editor.css') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-file fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Hizmet Sözleşmesi Şablonu</h4>
                <p class="text-muted mb-0 font-size-12">Times New Roman (10pt) formatında düzenleniyor</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <?php if ($duzenleyebilir): ?>
            <button type="button" class="btn btn-outline-danger bg-white top-action-btn shadow-sm" id="btnSablonSifirla">
                <i class="bx bx-reset font-size-16"></i> Varsayılana Sıfırla
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" id="btnSablonYazdir">
                <i class="bx bx-printer font-size-16"></i> Önizleme / Yazdır
            </button>
            <?php if ($duzenleyebilir): ?>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnSablonKaydet">
                <i class="bx bx-save font-size-16"></i> Şablonu Kaydet
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$hazir): ?>
    <div class="alert alert-warning">Şablon düzenleme için veritabanı betiği (sql/2026_11_21_sozlesme_sablon_web_editor.sql) çalıştırılmalıdır. O zamana kadar sistem varsayılan şablonu kullanılır ve şablon yalnızca görüntülenebilir.</div>
    <?php endif; ?>

    <div class="card sablon-editor-card mb-0" id="sablonEditorCard">
        <div class="sablon-editor-shell">
            <aside class="sablon-sidebar">
                <h6 class="sablon-sidebar-title"><i class="bx bx-code-curly"></i> Dinamik Alanlar</h6>

                <div class="sablon-ayar-kutusu">
                    <div class="sablon-grup-baslik">Sayfa Ayarları</div>
                    <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0 mb-0">
                        <label class="form-check-label font-size-12 fw-medium" for="sablonCerceve">Sayfa Çerçevesi</label>
                        <input class="form-check-input ms-2 mt-0" type="checkbox" role="switch" id="sablonCerceve" <?= $cerceve ? 'checked' : '' ?> <?= $duzenleyebilir ? '' : 'disabled' ?>>
                    </div>
                </div>

                <div class="sablon-akordeon" id="sablonAlanlar">
                    <?php $grupNo = 0; foreach ($gruplar as $grupAdi => $grupAlanlari): $grupNo++; ?>
                    <div class="sablon-grup">
                        <button type="button" class="sablon-grup-baslik <?= $grupNo === 1 ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#sablonGrup<?= $grupNo ?>" aria-expanded="<?= $grupNo === 1 ? 'true' : 'false' ?>" aria-controls="sablonGrup<?= $grupNo ?>">
                            <span><?= $e($grupAdi) ?></span>
                            <i class="bx bx-chevron-down"></i>
                        </button>
                        <div id="sablonGrup<?= $grupNo ?>" class="collapse <?= $grupNo === 1 ? 'show' : '' ?>" data-bs-parent="#sablonAlanlar">
                            <?php foreach ($grupAlanlari as $kod => $etiket): ?>
                            <button type="button" class="sablon-alan-btn" data-kod="{{<?= $e($kod) ?>}}" title="<?= $e($alanlar[$kod] ?? $etiket) ?>" <?= $duzenleyebilir ? '' : 'disabled' ?>>
                                <span><?= $e($etiket) ?></span>
                                <code>{{<?= $e($kod) ?>}}</code>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <section class="sablon-main">
                <div class="sablon-viewport" id="sablonViewport">
                    <textarea id="sablonEditor"></textarea>
                </div>
            </section>
        </div>
    </div>
</div>

<script>window.SOZLESME_SABLON = <?= json_encode($ayarlar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/sozlesme-belge.js?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/sozlesme-belge.js') ?>"></script>
<script src="views/personel/js/sozlesme-sablonlari.js?v=<?= (int) @filemtime(__DIR__ . '/js/sozlesme-sablonlari.js') ?>"></script>
