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
                            <th data-filter="string" style="width: 110px;">DURUM</th>
                            <th data-filter="none" class="text-center" style="width: 100px;">BAŞVURU</th>
                            <th data-filter="none" class="text-center" style="width: 110px;">AI OLUMLU</th>
                            <th data-filter="none" style="width: 130px;">OLUŞTURMA</th>
                            <th data-filter="none" style="width: 150px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ikilanModal" tabindex="-1" aria-labelledby="ikilanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                        <i class="bx bx-briefcase-alt-2 fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="ikilanModalLabel">Yeni İlan</h5>
                        <p class="text-muted small mb-0">İlan bilgilerini ve yapay zekâ değerlendirme kriterlerini girin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="ikilanForm">
                <input type="hidden" name="action" value="ilan-kaydet">
                <input type="hidden" name="id" id="ilan_id" value="">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="row g-3">
                        <div class="col-md-8"><?= Form::FormFloatInput("text", "baslik", "", "İlan Başlığı", "İlan Başlığı *", "briefcase", "form-control fw-semibold", true, 255) ?></div>
                        <div class="col-md-4"><?= Form::FormSelect2("durum", $durumOptions, "taslak", "Durum", "activity") ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("text", "departman", "", "Departman", "Departman", "layers", "form-control", false, 150) ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("text", "gorev", "", "Görev / Unvan", "Görev / Unvan", "user", "form-control", false, 150) ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("text", "calisma_yeri", "", "Çalışma Yeri", "Çalışma Yeri", "map-pin", "form-control", false, 150) ?></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("date", "gecerlilik_tarihi", "", "Son Başvuru Tarihi", "Son Başvuru Tarihi", "calendar", "form-control") ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("aciklama", "", "İş tanımı, çalışma koşulları...", "İş Tanımı (adaya gösterilir)", "file-text", "form-control", false, "90px") ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("aranan_nitelikler", "", "Eğitim, deneyim, belge...", "Aranan Nitelikler (adaya gösterilir)", "check-square", "form-control", false, "90px") ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("ai_kriterleri", "", "Örn: Zorunlu: C sınıfı ehliyet, 2 yıl saha deneyimi. Artı: AG/OG tecrübesi", "Yapay Zekâ Değerlendirme Kriterleri (adaya gösterilmez)", "cpu", "form-control", false, "90px") ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("sorular", "", "Her satıra bir soru yazın (en fazla 8)", "Pozisyona Özel Sorular (her satıra bir soru)", "help-circle", "form-control", false, "90px") ?></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary text-white" id="btnIlanKaydet"><i class="bx bx-save me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="ikilanLinkModal" tabindex="-1" aria-labelledby="ikilanLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="ikilanLinkModalLabel"><i class="bx bxl-whatsapp text-success me-1"></i> Başvuru Bağlantısını Paylaş</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body px-4">
                <div class="alert alert-warning py-2 font-size-12 d-none" id="linkYayindaDegil">Bu ilan yayında değil; bağlantı yayınlanana kadar adaylara "geçersiz" görünür.</div>
                <label class="form-label font-size-12 text-muted mb-1" for="linkMesaj">WhatsApp mesajı</label>
                <textarea class="form-control mb-2" id="linkMesaj" rows="7"></textarea>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="linkUrl" readonly aria-label="Başvuru bağlantısı">
                    <button type="button" class="btn btn-outline-secondary" id="btnLinkKopyala"><i class="bx bx-copy"></i> Kopyala</button>
                </div>
                <div class="text-center mb-2"><img id="linkQr" alt="QR kod" class="img-fluid border rounded-3 p-1" style="max-width: 160px;"></div>
            </div>
            <div class="modal-footer border-top-0 px-4 pb-4">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-success text-white" id="btnWhatsappPaylas"><i class="bx bxl-whatsapp me-1"></i> WhatsApp'ta Paylaş</button>
            </div>
        </div>
    </div>
</div>

<script src="views/ik-ise-alim/js/ilanlar.js?v=<?php echo time(); ?>"></script>
