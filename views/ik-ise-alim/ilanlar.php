<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;

$maintitle = 'İşe Alım';
$title = 'İş İlanları';

$durumOptions = ['taslak' => 'Taslak', 'yayinda' => 'Yayında', 'kapali' => 'Kapalı'];
?>
<script>try { document.documentElement.classList.toggle('ikilan-summary-hidden', localStorage.getItem('ikilan_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.ikilan-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-briefcase-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">İş İlanları</h4>
                <p class="text-muted mb-0 font-size-12">İlan oluşturun, başvuru bağlantısını WhatsApp ile paylaşın</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniIlan">
                <i class="bx bx-plus font-size-16"></i> Yeni İlan
            </button>
            <a href="index.php?p=ik-ise-alim/basvurular" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-user-check font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Başvurular</span>
            </a>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM İLAN</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-briefcase-alt-2"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext" id="stat_sub_toplam">Taslak: 0 | Kapalı: 0</span>
                    <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all"><i class="bx bx-layer"></i> Tümü</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">YAYINDAKİ İLAN</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-broadcast"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-success" id="stat_yayinda">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-success fw-semibold">Başvuru kabul ediyor</span>
                    <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="yayinda"><i class="bx bx-check-circle"></i> Yayında</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TASLAK İLAN</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-edit"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_taslak">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Henüz yayınlanmadı</span>
                    <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="taslak"><i class="bx bx-edit"></i> Taslak</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM BAŞVURU</span>
                    <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-user-plus"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_basvuru">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-muted">Tüm ilanlar</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Aday havuzu</span>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="ikilanListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">İlan Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Başvuru sayıları ve yapay zekâ olumlu aday adedi</p>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table id="ikilanTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th data-filter="string">İLAN BAŞLIĞI</th>
                            <th data-filter="string">DEPARTMAN</th>
                            <th data-filter="select" data-filter-options="Taslak|Yayında|Kapalı" style="width: 110px;">DURUM</th>
                            <th data-filter="number" class="text-center" style="width: 120px;">GÖRÜNTÜLEME</th>
                            <th data-filter="number" class="text-center" style="width: 100px;">BAŞVURU</th>
                            <th data-filter="number" class="text-center" style="width: 110px;">AI OLUMLU</th>
                            <th data-filter="date" style="width: 130px;">OLUŞTURMA</th>
                            <th data-filter="none" style="width: 150px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.ikm .modal-content { border-radius: 18px; overflow: hidden; }
.ikm .ikm-head { padding: 20px 24px 0; display: flex; gap: 14px; align-items: center; }
.ikm .ikm-ico { width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex: none; }
.ikm .ikm-steps { display: flex; gap: 8px; padding: 16px 24px 0; }
.ikm .ikm-step { flex: 1; border: 1px solid var(--bs-border-color, #e3e8ef); border-radius: 12px; padding: 8px 12px; background: transparent; text-align: left; display: flex; gap: 10px; align-items: center; color: inherit; transition: all .15s; }
.ikm .ikm-step .no { width: 24px; height: 24px; border-radius: 50%; background: var(--bs-secondary-bg, #eef1f5); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex: none; }
.ikm .ikm-step .tx { font-size: 13px; font-weight: 600; line-height: 1.2; }
.ikm .ikm-step .tx small { display: block; font-weight: 400; opacity: .65; font-size: 11px; }
.ikm .ikm-step.active { border-color: var(--bs-primary, #556ee6); background: var(--bs-primary-bg-subtle, #e8ecfd); }
.ikm .ikm-step.active .no { background: var(--bs-primary, #556ee6); color: #fff; }
@media (max-width: 575px) { .ikm .ikm-step .tx small { display: none; } .ikm .ikm-step { padding: 8px; } }
.ikm .ikm-hint { font-size: 12px; color: #74788d; margin: 6px 2px 0; }
.ikm .ikm-sec { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #74788d; margin: 4px 0 -4px; }
.ikm .soru-satir { display: flex; align-items: center; gap: 8px; border: 1px solid var(--bs-border-color, #e3e8ef); border-radius: 10px; padding: 7px 10px; margin-bottom: 8px; background: var(--bs-body-bg); }
.ikm .soru-satir .no { width: 22px; height: 22px; border-radius: 50%; background: var(--bs-primary-bg-subtle, #e8ecfd); color: var(--bs-primary, #556ee6); font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: none; }
.ikm .soru-satir .metin { flex: 1; font-size: 13.5px; word-break: break-word; }
.ikm .soru-satir .btn-ikon { width: 27px; height: 27px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
.ikm .soru-satir .soru-duzenle-input { flex: 1; }
.ikm .soru-satir.duzenleniyor { border-color: var(--bs-primary, #556ee6); box-shadow: 0 0 0 3px rgba(85,110,230,.12); }
.ikm .soru-bos { text-align: center; padding: 14px; border: 1px dashed var(--bs-border-color, #cfd6e0); border-radius: 10px; font-size: 13px; color: #74788d; margin-bottom: 8px; }
.ikm .kriter-chip { cursor: pointer; border-radius: 99px; padding: 3px 12px; font-size: 12px; border: 1px dashed var(--bs-border-color, #cfd6e0); background: transparent; color: inherit; }
.ikm .kriter-chip:hover { border-color: var(--bs-primary, #556ee6); color: var(--bs-primary, #556ee6); }
.ikm .wa-balon { background: #dcf8c6; color: #1c2530; border-radius: 4px 16px 16px 16px; padding: 12px 14px; font-size: 13.5px; white-space: pre-wrap; word-break: break-word; box-shadow: 0 1px 2px rgba(0,0,0,.12); }
html[data-bs-theme="dark"] .ikm .wa-balon, [data-theme-preset="macos-dark"] .ikm .wa-balon { background: #075e54; color: #e9f5f2; }
.ikm .wa-zemin { background: #efe7dd; border-radius: 14px; padding: 16px; }
html[data-bs-theme="dark"] .ikm .wa-zemin, [data-theme-preset="macos-dark"] .ikm .wa-zemin { background: #0b141a; }
.ikm .qr-kutu { width: 168px; height: 168px; border-radius: 14px; background: #fff; border: 1px solid #e3e8ef; display: flex; align-items: center; justify-content: center; padding: 8px; }
.ikm .qr-kutu img { width: 100%; height: 100%; image-rendering: pixelated; }
</style>

<div class="modal fade ikm no-upgrade" id="ikilanModal" tabindex="-1" aria-labelledby="ikilanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="ikm-head">
                <div class="ikm-ico bg-primary-subtle text-primary"><i class="bx bx-briefcase-alt-2"></i></div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fw-bold" id="ikilanModalLabel">Yeni İlan</h5>
                    <p class="text-muted font-size-12 mb-0">Üç kısa adımda ilanı hazırlayın; kaydedince başvuru bağlantısını paylaşabilirsiniz.</p>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="ikm-steps" role="tablist">
                <button type="button" class="ikm-step active" data-adim="1" role="tab"><span class="no">1</span><span class="tx">İlan Bilgileri<small>Başlık, yer, durum</small></span></button>
                <button type="button" class="ikm-step" data-adim="2" role="tab"><span class="no">2</span><span class="tx">Aday Ekranı<small>Adayın göreceği içerik</small></span></button>
                <button type="button" class="ikm-step" data-adim="3" role="tab"><span class="no">3</span><span class="tx">Yapay Zekâ<small>Kriter ve sorular</small></span></button>
            </div>
            <form id="ikilanForm" novalidate>
                <input type="hidden" name="action" value="ilan-kaydet">
                <input type="hidden" name="id" id="ilan_id" value="">
                <input type="hidden" name="sorular" id="sorular" value="">
                <div class="modal-body px-4 pt-4 pb-2" style="min-height: 330px;">
                    <div class="ikm-panel" data-adim="1">
                        <div class="row g-3">
                            <div class="col-md-8"><?= Form::FormFloatInput("text", "baslik", "", "Örn: Endeks Okuma Memuru", "İlan Başlığı *", "briefcase", "form-control fw-semibold", true, 255) ?></div>
                            <div class="col-md-4"><?= Form::FormSelect2("durum", $durumOptions, "taslak", "Durum", "activity") ?></div>
                            <div class="col-md-4"><?= Form::FormFloatInput("text", "departman", "", "Departman", "Departman", "layers", "form-control", false, 150) ?></div>
                            <div class="col-md-4"><?= Form::FormFloatInput("text", "gorev", "", "Görev / Unvan", "Görev / Unvan", "user", "form-control", false, 150) ?></div>
                            <div class="col-md-4"><?= Form::FormFloatInput("text", "calisma_yeri", "", "Çalışma Yeri", "Çalışma Yeri", "map-pin", "form-control", false, 150) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "gecerlilik_tarihi", "", "", "Son Başvuru Tarihi", "calendar", "form-control flatpickr", false, 10, "off") ?></div>
                            <div class="col-md-6 d-flex align-items-center"><p class="ikm-hint mb-0"><i class="bx bx-info-circle"></i> "Yayında" olmayan veya süresi geçen ilanın bağlantısı adaya "geçersiz" görünür. Boş bırakırsanız süresiz açık kalır.</p></div>
                        </div>
                    </div>
                    <div class="ikm-panel d-none" data-adim="2">
                        <div class="row g-3">
                            <div class="col-12"><?= Form::FormFloatTextarea("aciklama", "", "İş tanımı, çalışma koşulları, yan haklar...", "İş Tanımı", "file-text", "form-control", false, "130px") ?><p class="ikm-hint">Başvuru sayfasının üstünde adaya gösterilir.</p></div>
                            <div class="col-12"><?= Form::FormFloatTextarea("aranan_nitelikler", "", "Eğitim, deneyim, belge, ehliyet...", "Aranan Nitelikler", "check-square", "form-control", false, "130px") ?><p class="ikm-hint">Aday bu maddeleri okuyup kendini değerlendirir; satır satır yazmanız önerilir.</p></div>
                        </div>
                    </div>
                    <div class="ikm-panel d-none" data-adim="3">
                        <div class="ikm-sec mb-2">Yapay zekâ değerlendirme kriterleri <span class="text-muted text-lowercase fw-normal">(adaya gösterilmez)</span></div>
                        <?= Form::FormFloatTextarea("ai_kriterleri", "", "Zorunlu: C sınıfı ehliyet, 2 yıl saha deneyimi. Artı: AG/OG tecrübesi", "Değerlendirme kriterleri", "cpu", "form-control", false, "110px") ?>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="kriter-chip" data-ekle="Zorunlu: ">+ Zorunlu</button>
                            <button type="button" class="kriter-chip" data-ekle="Artı: ">+ Artı puan</button>
                            <button type="button" class="kriter-chip" data-ekle="Elenme sebebi: ">+ Elenme sebebi</button>
                            <button type="button" class="kriter-chip" data-ekle="Maaş aralığı: ">+ Maaş aralığı</button>
                        </div>
                        <p class="ikm-hint">Puanlama yalnızca buna ve ilan niteliklerine göre yapılır; sonuç sadece öneridir. Ehliyet sınıfları kapsayıcı değerlendirilir (E sınıfı C'yi kapsar).</p>
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                            <div class="ikm-sec m-0">Pozisyona özel sorular <span class="text-muted text-lowercase fw-normal">(adaya sorulur)</span></div>
                            <span class="badge bg-primary-subtle text-primary rounded-pill" id="soruSayac">0 / 8</span>
                        </div>
                        <div id="soruListe"></div>
                        <div class="d-flex flex-wrap gap-2 mb-2" id="soruOneriler">
                            <button type="button" class="kriter-chip soru-oneri" data-soru="Daha önce hangi firmalarda çalıştınız?">+ Önceki firmalar</button>
                            <button type="button" class="kriter-chip soru-oneri" data-soru="Hangi ehliyet sınıflarına sahipsiniz?">+ Ehliyet sınıfı</button>
                            <button type="button" class="kriter-chip soru-oneri" data-soru="Ne zaman işe başlayabilirsiniz?">+ İşe başlama</button>
                            <button type="button" class="kriter-chip soru-oneri" data-soru="Sahada vardiyalı / seyahatli çalışmaya uygun musunuz?">+ Saha uygunluğu</button>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="yeniSoru" maxlength="300" placeholder="Örn: Daha önce hangi firmalarda çalıştınız?" aria-label="Yeni soru">
                            <button type="button" class="btn btn-outline-primary" id="btnSoruEkle"><i class="bx bx-plus"></i> Soru ekle</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-light" id="btnGeri"><i class="bx bx-chevron-left"></i> Geri</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-subtle-primary" id="btnIleri">İleri <i class="bx bx-chevron-right"></i></button>
                        <button type="submit" class="btn btn-primary text-white" id="btnIlanKaydet"><i class="bx bx-save me-1"></i> Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade ikm no-upgrade" id="ikilanLinkModal" tabindex="-1" aria-labelledby="ikilanLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="ikm-head">
                <div class="ikm-ico bg-success-subtle text-success"><i class="bx bxl-whatsapp"></i></div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fw-bold" id="ikilanLinkModalLabel">Başvuru Bağlantısını Paylaş</h5>
                    <p class="text-muted font-size-12 mb-0" id="linkIlanAd">İlan</p>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body px-4 pt-3">
                <div class="alert alert-warning py-2 font-size-12 d-none" id="linkYayindaDegil"><i class="bx bx-error me-1"></i> Bu ilan şu an yayında değil; bağlantı yayınlanana kadar adaylara "geçersiz" görünür.</div>
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="ikm-sec mb-2">Mesaj önizleme (düzenleyebilirsiniz)</div>
                        <div class="wa-zemin">
                            <div class="wa-balon" id="linkBalon" contenteditable="true" role="textbox" aria-label="WhatsApp mesajı" spellcheck="false"></div>
                        </div>
                        <div class="ikm-sec mt-3 mb-2">Doğrudan bir numaraya gönder <span class="text-muted text-lowercase fw-normal">(isteğe bağlı)</span></div>
                        <input type="tel" class="form-control" id="linkTelefon" placeholder="05xx xxx xx xx — boş bırakırsanız kişiyi/grubu WhatsApp'ta seçersiniz" inputmode="tel" aria-label="Telefon numarası">
                    </div>
                    <div class="col-md-5 text-center">
                        <div class="ikm-sec mb-2 text-start">QR kod</div>
                        <div class="qr-kutu mx-auto"><img id="linkQr" alt="QR kod" class="d-none"><span id="linkQrYok" class="text-muted font-size-12">QR oluşturuluyor...</span></div>
                        <p class="ikm-hint">Telefonla okutarak veya ilan panosuna basarak paylaşın.</p>
                        <div class="ikm-sec mt-3 mb-2 text-start">Bağlantı</div>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="linkUrl" readonly aria-label="Başvuru bağlantısı">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLinkKopyala" title="Kopyala"><i class="bx bx-copy"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 px-4 pb-4">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-subtle-primary" id="btnMesajKopyala"><i class="bx bx-copy me-1"></i> Mesajı Kopyala</button>
                <button type="button" class="btn btn-success text-white" id="btnWhatsappPaylas"><i class="bx bxl-whatsapp me-1"></i> WhatsApp'ta Paylaş</button>
            </div>
        </div>
    </div>
</div>

<script src="views/ik-ise-alim/js/ilanlar.js?v=<?php echo time(); ?>"></script>
