<?php

use App\Model\PersonelModel;
use App\Model\MesajLogModel;
use App\Helper\Form;

$maintitle = "Bildirimler";
$title = "Push Bildirim Gönder";

$personelModel = new PersonelModel();
$mesajLogModel = new MesajLogModel();
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

$personeller = $personelModel->all(true);
$personelSecenekleri = [];
$aboneIds = [];
foreach ($personeller as $personel) {
    $abone = (int) ($personel->bildirim_abonesi ?? 0) === 1;
    if ($abone) {
        $aboneIds[] = (int) $personel->id;
    }
    $personelSecenekleri[$personel->id] = $personel->adi_soyadi . ($abone ? ' (Abone)' : ' (Abonelik Yok)');
}
$toplamPersonel = count($personeller);
$aboneSayisi = count($aboneIds);
$stats = $mesajLogModel->pushStats($firmaId);
$hedefSayfalar = [
    '' => 'Ana Sayfa',
    'index.php?page=puantaj' => 'İş Takibi / Puantaj',
    'index.php?page=izin' => 'İzinler',
    'index.php?page=bordro' => 'Bordrolar',
    'index.php?page=avans' => 'Avans İşlemleri',
    'index.php?page=talep' => 'Talepler',
    'index.php?page=zimmetler' => 'Zimmetler',
    'index.php?page=icralar' => 'İcralarım',
    'index.php?page=km-bildirimleri' => 'KM Bildirimleri',
    'index.php?page=etkinlikler' => 'Etkinlikler',
    'index.php?page=yardim' => 'Yardım & Destek',
    'index.php?page=ihbar' => 'İhbar Yönetimi',
    'index.php?page=kacak' => 'Kaçak İşlemleri (yetkili personel)',
    'index.php?page=aparat' => 'Aparat Takip (kesme-açma personeli)',
    'index.php?page=nobet' => 'Nöbet Takibi (kesme-açma personeli)',
    'index.php?page=ekip-takibi' => 'Ekip Takibi (ekip şefi)',
    'index.php?page=profil' => 'Profil',
];
$sonGonderim = $stats->son ? date('d.m.Y H:i', strtotime($stats->son)) : 'Henüz gönderim yok';
?>
<script>try { document.documentElement.classList.toggle('bildirim-summary-hidden', localStorage.getItem('bildirim_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.bildirim-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

.step-badge { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: var(--bs-primary-bg-subtle, #e7ebfb); color: var(--bs-primary, #556ee6); flex-shrink: 0; }
.recipient-option { cursor: pointer; border: 1.5px solid var(--bs-border-color, #e5e7eb); border-radius: 10px; padding: .7rem .9rem; transition: border-color .15s, background-color .15s; height: 100%; }
.recipient-option:hover { border-color: var(--bs-primary, #556ee6); }
.btn-check:checked + .recipient-option { border-color: var(--bs-primary, #556ee6); background: var(--bs-primary-bg-subtle, #eef1fd); }
.template-chip { cursor: pointer; border: 1px dashed var(--bs-border-color, #cbd5e1); border-radius: 999px; padding: .25rem .75rem; font-size: 12px; background: transparent; color: inherit; }
.template-chip:hover { border-color: var(--bs-primary, #556ee6); color: var(--bs-primary, #556ee6); }
.char-counter { font-size: 11px; color: #8c9097; text-align: right; margin-top: 2px; }
.char-counter.limit { color: #ea5455; }
.image-drop { border: 1.5px dashed var(--bs-border-color, #cbd5e1); border-radius: 10px; padding: .9rem; text-align: center; cursor: pointer; transition: border-color .15s, background-color .15s; }
.image-drop:hover, .image-drop.dragover { border-color: var(--bs-primary, #556ee6); background: var(--bs-primary-bg-subtle, #eef1fd); }
.image-preview-wrap { position: relative; display: none; }
.image-preview-wrap img { width: 100%; max-height: 150px; object-fit: cover; border-radius: 10px; }
.image-preview-wrap .btn-remove-image { position: absolute; top: 8px; right: 8px; }
.preview-sticky { position: sticky; top: 90px; }
.phone-preview { background: #e6eaef; border-radius: 22px; padding: 1.1rem; }
.notif-mock { background: #fff; border-radius: 16px; padding: .75rem .9rem; box-shadow: 0 4px 14px rgba(0,0,0,.08); color: #1f2937; }
.notif-mock .notif-app { font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: .4rem; margin-bottom: .35rem; }
.notif-mock .notif-title { font-weight: 700; font-size: 14px; word-break: break-word; }
.notif-mock .notif-body { font-size: 13px; color: #4b5563; word-break: break-word; white-space: pre-line; }
.notif-mock .notif-img { width: 100%; max-height: 120px; object-fit: cover; border-radius: 10px; margin-top: .55rem; display: none; }
#gecmisTable tbody tr { cursor: default; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-bell fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Push Bildirim Gönder</h4>
                <p class="text-muted mb-0 font-size-12">Personelin mobil uygulamasına anlık bildirim gönderin ve gönderim geçmişini izleyin</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <a href="#gecmisCard" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-history font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Gönderim Geçmişi</span>
            </a>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF PERSONEL</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-group"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1"><?= $toplamPersonel ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">Bildirim gönderilebilecek havuz</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 summary-pill-btn d-flex align-items-center gap-1" id="btnPickAll">
                            <i class="bx bx-select-multiple"></i> Tümünü Seç
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ABONE PERSONEL</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-circle"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success"><?= $aboneSayisi ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Bildirim alabilen</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 summary-pill-btn d-flex align-items-center gap-1" id="btnPickSubscribers">
                            <i class="bx bx-check"></i> Aboneleri Seç
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ABONE OLMAYAN</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-bell-off"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger"><?= $toplamPersonel - $aboneSayisi ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold">Uygulamada izin vermemiş</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BU AY GÖNDERİLEN</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-send"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_bu_ay"><?= (int) $stats->bu_ay ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_son">Son: <?= htmlspecialchars($sonGonderim, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Toplam: <span id="kpi_toplam"><?= (int) $stats->toplam ?></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="formBildirimGonder" enctype="multipart/form-data" autocomplete="off">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <span class="step-badge">1</span>
                        <div>
                            <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Kime gönderilsin?</h5>
                            <p class="text-muted font-size-11 mb-0">Belirli personelleri seçin ya da tüm abonelere gönderin</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="alici_tipi" id="aliciTekli" value="tekli" checked>
                                <label class="recipient-option d-flex align-items-center gap-2 w-100" for="aliciTekli">
                                    <i class="bx bx-user-check fs-4 text-primary"></i>
                                    <span><span class="fw-semibold d-block font-size-13">Seçili Personeller</span><small class="text-muted">İstediğiniz kişileri seçin</small></span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="alici_tipi" id="aliciToplu" value="toplu">
                                <label class="recipient-option d-flex align-items-center gap-2 w-100" for="aliciToplu">
                                    <i class="bx bx-broadcast fs-4 text-primary"></i>
                                    <span><span class="fw-semibold d-block font-size-13">Tüm Aboneler</span><small class="text-muted"><?= $aboneSayisi ?> abone personel</small></span>
                                </label>
                            </div>
                        </div>

                        <div id="personelSecimContainer">
                            <?= Form::FormMultipleSelect2(
                                'personel_ids',
                                $personelSecenekleri,
                                [],
                                'Personel Seç',
                                'users',
                                'key',
                                '',
                                'form-select select2',
                                false
                            ) ?>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                                <small class="text-muted" id="secimOzeti">Henüz personel seçilmedi</small>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-subtle-success" id="btnPickSubscribers2"><i class="bx bx-check me-1"></i>Aboneleri seç</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary bg-white" id="btnClearPick"><i class="bx bx-x me-1"></i>Temizle</button>
                                </div>
                            </div>
                            <div class="alert alert-warning py-2 px-3 font-size-12 mt-2 mb-0 d-none" id="aboneUyari"></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <span class="step-badge">2</span>
                        <div>
                            <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Ne yazsın?</h5>
                            <p class="text-muted font-size-11 mb-0">Başlık ve mesajı yazın; sağdaki önizlemede nasıl görüneceğini izleyin</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <small class="text-muted me-1">Hızlı şablon:</small>
                            <button type="button" class="template-chip" data-baslik="Bordronuz yayınlandı" data-mesaj="Bordronuz yayınlandı. Detayları görmek için dokunun." data-hedef="index.php?page=bordro">Bordro yayında</button>
                            <button type="button" class="template-chip" data-baslik="İzin talebiniz sonuçlandı" data-mesaj="İzin talebiniz değerlendirildi. Sonucu görmek için dokunun." data-hedef="index.php?page=izin">İzin sonucu</button>
                            <button type="button" class="template-chip" data-baslik="Duyuru" data-mesaj="" data-hedef="">Genel duyuru</button>
                        </div>

                        <div class="mb-1">
                            <?= Form::FormFloatInput('text', 'baslik', '', 'Bildirim başlığı...', 'Başlık', 'bx bx-heading', 'form-control', true, 50) ?>
                            <div class="char-counter" id="baslikSayac">0 / 50</div>
                        </div>

                        <div class="mb-1 mt-2">
                            <?= Form::FormFloatTextarea('mesaj', '', 'Bildirim içeriğini yazın...', 'Mesaj', 'bx bx-message-detail', 'form-control', true, '100px', 3) ?>
                            <div class="char-counter" id="mesajSayac">0 / 178</div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <?= Form::FormSelect2(
                                    'hedef_sayfa',
                                    $hedefSayfalar,
                                    '',
                                    'Tıklandığında Açılacak Sayfa',
                                    'home',
                                    'key',
                                    '',
                                    'form-select select2',
                                    false
                                ) ?>
                            </div>
                            <div class="col-md-6">
                                <input type="file" class="d-none" id="resim" name="resim" accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="image-drop" id="imageDrop" role="button" tabindex="0">
                                    <i class="bx bx-image-add fs-3 text-primary d-block"></i>
                                    <small class="text-muted">Görsel ekle (isteğe bağlı)<br>Önerilen 512x256 · en fazla 2 MB</small>
                                </div>
                                <div class="image-preview-wrap" id="imagePreviewWrap">
                                    <img src="" alt="Seçilen görsel önizlemesi" id="imagePreview">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove-image" id="btnRemoveImage" aria-label="Görseli kaldır"><i class="bx bx-x"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <small class="text-muted" id="gonderOzeti"></small>
                        <button type="submit" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnGonder">
                            <i class="bx bx-send font-size-16"></i> Bildirimi Gönder
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="preview-sticky">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                            <span class="step-badge"><i class="bx bx-show"></i></span>
                            <div>
                                <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Canlı Önizleme</h5>
                                <p class="text-muted font-size-11 mb-0">Telefonda yaklaşık böyle görünür</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="phone-preview">
                                <div class="notif-mock">
                                    <div class="notif-app"><i class="bx bx-bell"></i> Personel Uygulaması · şimdi</div>
                                    <div class="notif-title" id="prevBaslik">Bildirim başlığı</div>
                                    <div class="notif-body" id="prevMesaj">Mesajınız burada görünecek.</div>
                                    <img class="notif-img" id="prevResim" alt="Bildirim görseli">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body py-3">
                            <h6 class="font-size-13 fw-bold mb-2"><i class="bx bx-info-circle text-info me-1"></i>Bilmeniz gerekenler</h6>
                            <ul class="font-size-12 text-muted ps-3 mb-0">
                                <li>Yalnızca uygulamada bildirim izni vermiş (abone) personel bildirimi alır.</li>
                                <li>Bir personel birden fazla cihazdan abone olabilir; hepsine iletilir.</li>
                                <li>Kısa ve net başlıklar telefonda kesilmeden görünür.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0 mt-3" id="gecmisCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bx bx-history font-size-18"></i>
                </div>
                <div>
                    <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Gönderim Geçmişi</h5>
                    <p class="text-muted font-size-11 mb-0">Daha önce gönderilen push bildirimleri</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                <i class="bx bx-printer font-size-14 me-1"></i> Yazdır
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="gecmisTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 60px;" data-filter="none">#</th>
                            <th data-filter="none">Kanal</th>
                            <th data-filter="string">Gönderen</th>
                            <th data-filter="string">Alıcılar</th>
                            <th data-filter="string">Başlık / Mesaj</th>
                            <th class="text-center" style="width: 110px;" data-filter="none">Durum</th>
                            <th class="text-center" style="width: 150px;" data-filter="none">Tarih</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    window.BILDIRIM_CFG = {
        aboneIds: <?= json_encode(array_map('strval', $aboneIds)) ?>,
        aboneSayisi: <?= (int) $aboneSayisi ?>
    };
</script>
<script src="views/bildirim/js/gonder.js?v=<?= time() ?>"></script>
