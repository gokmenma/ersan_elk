<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Helper\Form;
use App\Model\FormlarModel;
use App\Model\PersonelModel;
use App\Model\AracModel;

$firma_id = (int) ($_SESSION['firma_id'] ?? 1);

// Modeller
$Formlar = new FormlarModel();
$Personel = new PersonelModel();
$Araclar = new AracModel();

// Veriler
$stats = $Formlar->getStats($firma_id);
$personeller = $Personel->all(false, 'personel'); 
$araclar = $Araclar->getAktifAraclar(); 

$maintitle = "İnsan Kaynakları";
$title = "Formlar ve Tutanaklar";
?>
<script>try { document.documentElement.classList.toggle('formlar-summary-hidden', localStorage.getItem('formlar_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.formlar-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Yeni Standart) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-file-blank fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Formlar ve Tutanaklar</h4>
                <p class="text-muted mb-0 font-size-12">Kurumsal form, tutanak ve otomatik doldurulabilir belge şablonları</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Şablon Yükle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" data-bs-toggle="modal" data-bs-target="#yeniFormModal">
                <i class="bx bx-plus font-size-16"></i> Yeni Şablon Yükle
            </button>

            <!-- 2. Değişkenler Rehberi Butonu -->
            <button type="button" class="btn btn-outline-info bg-white top-action-btn shadow-sm" data-bs-toggle="modal" data-bs-target="#degiskenlerModal">
                <i class="bx bx-info-circle font-size-16 text-info"></i> Değişken Rehberi
            </button>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM ŞABLON -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM ŞABLON</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-file-blank"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam"><?= $stats->toplam ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_toplam">Kayıtlı Tüm Şablonlar</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-filter-type="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: WORD ŞABLONLARI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">WORD ŞABLONLARI</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bxs-file-doc"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_word"><?= $stats->word ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold" id="stat_sub_word">Otomatik Doldurulabilir</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-type="word">
                            <i class="bx bxs-file-doc"></i> Word
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: EXCEL ŞABLONLARI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">EXCEL ŞABLONLARI</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bxs-file-table"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_excel"><?= $stats->excel ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_excel">Tablo & Hesaplamalar</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-type="excel">
                            <i class="bx bxs-file-table"></i> Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: PDF & DİĞER FORMLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">PDF & DİĞER FORMLAR</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bxs-file-pdf"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_pdf"><?= $stats->pdf ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_pdf">Yazdırılabilir Belgeler</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-type="pdf">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Formlar Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="formlarListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Şablon Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, canlı sütun filtreleme ve belge doldurma yönetimi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="formlarTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 45px;" data-filter="none">#</th>
                            <th data-filter="string">FORM / TUTANAK ADI</th>
                            <th class="text-center" style="width: 100px;" data-filter="select">FORMAT</th>
                            <th data-filter="string">EKLENEN DOSYA</th>
                            <th style="width: 140px;" data-filter="string">EKLEYEN</th>
                            <th style="width: 110px;" data-filter="date">TARİH</th>
                            <th class="text-center" style="min-width: 110px; width: 120px;" data-filter="none">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Değişkenler Rehberi Modalı -->
<div class="modal fade" id="degiskenlerModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary py-3 px-3 text-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-white bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bx bx-info-circle fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white fw-bold mb-0 font-size-15">Şablon Değişkenleri Rehberi</h5>
                        <p class="text-white-50 mb-0 font-size-12">Otomatik veri aktarımı için şablon etiketleri</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning border-0 shadow-sm mb-4 rounded-3 d-flex align-items-start gap-2">
                    <i class="bx bx-bulb fs-4 text-warning flex-shrink-0 mt-0.5"></i>
                    <div class="font-size-12 text-dark">
                        <strong>Önemli Kullanım Kuralı:</strong> Word (<code>.docx</code>) veya Excel (<code>.xlsx</code>) belgelerinde sistemin alanları doldurabilmesi için değişkenleri tam olarak aşağıdaki formatta ekleyiniz.<br>
                        <strong>Word Şablonları İçin:</strong> Dolar işareti ve süslü parantezleri kullanın (Örn: <code class="fw-bold text-danger">${PERSONEL_ADI}</code>). Tıklayarak kopyalayabilirsiniz.
                    </div>
                </div>
                
                <div class="row g-3">
                    <!-- Personel Etiketleri -->
                    <div class="col-md-6">
                        <div class="card border border-primary-subtle h-100 mb-0 rounded-3">
                            <div class="card-header bg-primary-subtle border-bottom border-primary-subtle py-2 px-3">
                                <h6 class="text-primary fw-bold mb-0 font-size-13 d-flex align-items-center gap-1.5">
                                    <i class="bx bx-user"></i> Personel Etiketleri
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0 font-size-12">
                                        <thead class="table-light">
                                            <tr><th style="width:50%">Etiket (Kopyala)</th><th>Açıklama</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><code>${PERSONEL_ADI}</code></td><td>Ad ve Soyad</td></tr>
                                            <tr><td><code>${TC_KIMLIK}</code></td><td>T.C. Kimlik No</td></tr>
                                            <tr><td><code>${TELEFON}</code></td><td>Telefon No</td></tr>
                                            <tr><td><code>${UNVAN}</code></td><td>Görevi / Mesleği</td></tr>
                                            <tr><td><code>${ISE_GIRIS}</code></td><td>İşe Giriş Tarihi</td></tr>
                                            <tr><td><code>${ADRES}</code></td><td>İkamet Adresi</td></tr>
                                            <tr><td><code>${TARIH}</code></td><td>Bugünün Tarihi</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Araç Etiketleri -->
                    <div class="col-md-6">
                        <div class="card border border-info-subtle h-100 mb-0 rounded-3">
                            <div class="card-header bg-info-subtle border-bottom border-info-subtle py-2 px-3">
                                <h6 class="text-info fw-bold mb-0 font-size-13 d-flex align-items-center gap-1.5">
                                    <i class="bx bx-car"></i> Araç Etiketleri
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0 font-size-12">
                                        <thead class="table-light">
                                            <tr><th style="width:50%">Etiket (Kopyala)</th><th>Açıklama</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><code>${PLAKA}</code></td><td>Araç Plakası</td></tr>
                                            <tr><td><code>${MARKA}</code></td><td>Araç Markası</td></tr>
                                            <tr><td><code>${MODEL}</code></td><td>Araç Modeli</td></tr>
                                            <tr><td><code>${BASLANGIC_KM}</code></td><td>Başlangıç KM</td></tr>
                                            <tr><td><code>${BITIS_KM}</code></td><td>Bitiş KM</td></tr>
                                            <tr><td><code>${ACIKLAMA}</code></td><td>Form Açıklaması</td></tr>
                                            <tr><td><code>${ARAC_BAKIM_KM}</code></td><td>Güncel KM</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zimmet Etiketleri -->
                    <div class="col-md-6">
                        <div class="card border border-warning-subtle h-100 mb-0 rounded-3">
                            <div class="card-header bg-warning-subtle border-bottom border-warning-subtle py-2 px-3">
                                <h6 class="text-warning-emphasis fw-bold mb-0 font-size-13 d-flex align-items-center gap-1.5">
                                    <i class="bx bx-briefcase"></i> Zimmet Etiketleri
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0 font-size-12">
                                        <thead class="table-light">
                                            <tr><th style="width:50%">Etiket (Kopyala)</th><th>Açıklama</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><code>${IMEI}</code></td><td>Cihaz IMEI Numarası</td></tr>
                                            <tr><td><code>${SERI_NO}</code></td><td>Cihaz Seri Numarası</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- İzin Etiketleri -->
                    <div class="col-md-6">
                        <div class="card border border-success-subtle h-100 mb-0 rounded-3">
                            <div class="card-header bg-success-subtle border-bottom border-success-subtle py-2 px-3">
                                <h6 class="text-success fw-bold mb-0 font-size-13 d-flex align-items-center gap-1.5">
                                    <i class="bx bx-calendar"></i> İzin Etiketleri
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0 font-size-12">
                                        <thead class="table-light">
                                            <tr><th style="width:50%">Etiket (Kopyala)</th><th>Açıklama</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><code>${IZIN_BASLANGIC}</code></td><td>İzin Başlangıç Tarihi</td></tr>
                                            <tr><td><code>${IZIN_BITIS}</code></td><td>İzin Bitiş Tarihi</td></tr>
                                            <tr><td><code>${IZIN_ISE_BASLAMA}</code></td><td>İşe Başlama Tarihi</td></tr>
                                            <tr><td><code>${IZIN_GUN}</code></td><td>İzin Gün Sayısı</td></tr>
                                            <tr><td><code>${IZIN_NEDENI}</code></td><td>İzin Talep Nedeni</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Şablon Yükleme Modalı -->
<div class="modal fade" id="yeniFormModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary py-3 px-3 text-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-white bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bx bx-cloud-upload fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white fw-bold mb-0 font-size-15">Yeni Şablon Yükle</h5>
                        <p class="text-white-50 mb-0 font-size-12">Word, Excel veya PDF formatında şablon yükleyin</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formEkleForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="baslik" class="form-label font-size-12 fw-bold text-dark">Şablon / Form Başlığı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="baslik" name="baslik" placeholder="Örn: Araç Teslim Tutanağı" required>
                    </div>
                    <div class="mb-3">
                        <label for="dosya" class="form-label font-size-12 fw-bold text-dark">Şablon Dosyası <span class="text-danger">*</span></label>
                        <input type="file" class="form-control rounded-3" id="dosya" name="dosya" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                        <div class="form-text font-size-11 text-muted mt-1">İzin verilen formatlar: .docx, .doc, .xlsx, .xls, .pdf</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill shadow-xs" id="btnKaydet">
                    <i class="bx bx-cloud-upload me-1"></i> Yükle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Şablon Doldurma Seçim Modalı -->
<div class="modal fade" id="personelSecModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info py-3 px-3 text-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-white bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bx bx-pencil fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white fw-bold mb-0 font-size-15">Şablonu Doldurarak İndir</h5>
                        <p class="text-white-50 mb-0 font-size-12">Belge içerisine yerleştirilecek bilgileri girin</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4 pb-2">
                <input type="hidden" id="indirmeFormId" value="">
                
                <p class="text-muted mb-3 font-size-12">
                    <i class="bx bx-info-circle text-info"></i> Belgenizi indirirken içine eklenecek bilgileri ilgili sekmelerden doldurabilirsiniz. Gerekmeyen alanları boş bırakabilirsiniz.
                </p>

                <ul class="nav nav-pills nav-justified mb-3 p-1 bg-light rounded-3" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active tab-manuel font-size-12 py-1.5" data-target="#tab-personel">
                            <i class="bx bx-user me-1"></i> Personel
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link tab-manuel font-size-12 py-1.5" data-target="#tab-arac">
                            <i class="bx bx-car me-1"></i> Araç
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link tab-manuel font-size-12 py-1.5" data-target="#tab-zimmet">
                            <i class="bx bx-briefcase me-1"></i> Zimmet
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link tab-manuel font-size-12 py-1.5" data-target="#tab-izin">
                            <i class="bx bx-calendar me-1"></i> İzin
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-1">
                    <!-- Personel Sekmesi -->
                    <div class="tab-pane active" id="tab-personel">
                        <div class="mb-3">
                            <?php
                            $personelOptions = ['' => 'Personel Seçiniz (İsteğe Bağlı)'];
                            foreach($personeller as $p) {
                                $personelOptions[Security::encrypt($p->id)] = $p->adi_soyadi;
                            }
                            echo Form::FormSelect2(
                                'indirmePersonelId',
                                $personelOptions,
                                '',
                                'Personel Seçimi',
                                'bx bx-user'
                            );
                            ?>
                        </div>
                    </div>

                    <!-- Araç Sekmesi -->
                    <div class="tab-pane" id="tab-arac">
                        <?php
                        $aracOptions = ['' => 'Araç Seçiniz (İsteğe Bağlı)'];
                        foreach($araclar as $a) {
                            $aracOptions[Security::encrypt($a->id)] = $a->plaka . ' - ' . $a->marka . ' ' . $a->model;
                        }
                        echo Form::FormSelect2(
                            'indirmeAracId',
                            $aracOptions,
                            '',
                            'Araç Seçimi',
                            'bx bx-car'
                        );
                        ?>
                        <div class="row mt-3">
                            <div class="col-6 mb-3">
                                <?php echo Form::FormFloatInput('number', 'indirmeAracBasKm', '', 'Örn: 154000', 'Başlangıç KM', 'bx bx-tachometer'); ?>
                            </div>
                            <div class="col-6 mb-3">
                                <?php echo Form::FormFloatInput('number', 'indirmeAracBitKm', '', 'Örn: 155000', 'Bitiş KM', 'bx bx-tachometer'); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <?php echo Form::FormFloatTextarea('indirmeAracAciklama', '', 'Sadece ihtiyaç varsa doldurun', 'Açıklama / Sefer Bilgisi', 'bx bx-detail', 'form-control', false, '80px', 2); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Zimmet Sekmesi -->
                    <div class="tab-pane" id="tab-zimmet">
                        <div class="row">
                            <div class="col-12 mb-3 mt-1">
                                <?php echo Form::FormFloatInput('text', 'indirmeZimmetImei', '', 'Örn: 123456789012345', 'IMEI Numarası (Varsa)', 'bx bx-barcode'); ?>
                            </div>
                            <div class="col-12 mb-2">
                                <?php echo Form::FormFloatInput('text', 'indirmeZimmetSeriNo', '', 'Örn: SN-987654', 'Seri Numarası (Varsa)', 'bx bx-hash'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- İzin Sekmesi -->
                    <div class="tab-pane" id="tab-izin">
                        <div class="row">
                            <div class="col-6 mb-3 mt-1">
                                <?php echo Form::FormFloatInput('text', 'indirmeIzinBaslangic', '', '', 'Başlangıç Tarihi', 'bx bx-calendar', 'form-control flatpickr-date'); ?>
                            </div>
                            <div class="col-6 mb-3 mt-1">
                                <?php echo Form::FormFloatInput('text', 'indirmeIzinBitis', '', '', 'Bitiş Tarihi', 'bx bx-calendar', 'form-control flatpickr-date'); ?>
                            </div>
                            <div class="col-12 mb-3">
                                <?php echo Form::FormFloatInput('number', 'indirmeIzinGun', '', 'Örn: 5', 'İzin Gün Sayısı', 'bx bx-time'); ?>
                            </div>
                            <div class="col-12 mb-2">
                                <?php echo Form::FormFloatInput('text', 'indirmeIzinNedeni', '', 'Örn: Yıllık İzin', 'İzin Nedeni', 'bx bx-info-circle'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-info btn-sm px-4 rounded-pill shadow-xs text-white" id="btnSablounuIndir">
                    <i class="bx bx-download me-1"></i> Şablonu İndir
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Tablo Tipografi ve Okunabilirlik İyileştirmeleri */
#formlarTable {
    font-size: 13px !important;
}
#formlarTable thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    vertical-align: middle !important;
}
#formlarTable tbody td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
    color: #0f172a !important;
}

/* Tablo Butonları Standartları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.copy-tag-text {
    cursor: pointer;
    transition: all 0.15s ease;
}
.copy-tag-text:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
    color: var(--bs-primary) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Select2
    if ($.fn.select2) {
        $('#indirmePersonelId').select2({
            dropdownParent: $('#personelSecModal'),
            width: '100%'
        });
        $('#indirmeAracId').select2({
            dropdownParent: $('#personelSecModal'),
            width: '100%'
        });
    }

    // Flatpickr
    if ($.fn.flatpickr) {
        $('.flatpickr-date').flatpickr({
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d.m.Y",
            locale: "tr"
        });
    }

    let activeFilterType = "all";

    // DataTables Custom Search Filter
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== "formlarTable") return true;
        if (activeFilterType === "all") return true;
        
        const row = settings.aoData[dataIndex]._aData;
        if (!row) return true;
        
        return row.format === activeFilterType;
    });

    // DataTables Başlatma
    const baseOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : { language: { url: "assets/libs/datatables.net/js/tr.json" } };
    const dtConfig = typeof applyLengthStateSave === 'function' ? applyLengthStateSave({
        ...baseOptions,
        ajax: {
            url: 'api/formlar/islem.php',
            type: 'POST',
            data: { action: 'list' },
            dataSrc: function (json) {
                if (json.stats) {
                    $('#stat_toplam').text((json.stats.toplam || 0) + ' Adet');
                    $('#stat_word').text((json.stats.word || 0) + ' Adet');
                    $('#stat_excel').text((json.stats.excel || 0) + ' Adet');
                    $('#stat_pdf').text((json.stats.pdf || 0) + ' Adet');
                }
                return json.data || [];
            }
        },
        columns: [
            { 
                data: 'id', 
                className: 'text-center',
                orderable: false,
                render: function(data, type, row, meta) { 
                    return '<span class="fw-bold text-muted">' + (meta.row + 1) + '</span>'; 
                } 
            },
            { 
                data: 'baslik', 
                render: function(data, type, row) { 
                    let iconClass = 'bx bx-file text-secondary';
                    let iconBg = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                    
                    if (row.format === 'word') {
                        iconClass = 'bx bxs-file-doc text-info';
                        iconBg = 'bg-info-subtle text-info border border-info-subtle';
                    } else if (row.format === 'excel') {
                        iconClass = 'bx bxs-file-table text-success';
                        iconBg = 'bg-success-subtle text-success border border-success-subtle';
                    } else if (row.format === 'pdf') {
                        iconClass = 'bx bxs-file-pdf text-danger';
                        iconBg = 'bg-danger-subtle text-danger border border-danger-subtle';
                    }

                    const iconBox = '<a href="' + row.dosya_yolu + '" target="_blank" class="p-2 ' + iconBg + ' rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 text-decoration-none shadow-xs" title="Boş Şablonu İndir" style="width: 34px; height: 34px;"><i class="' + iconClass + ' font-size-18"></i></a>';
                    const titleText = '<div class="min-w-0"><a href="' + row.dosya_yolu + '" target="_blank" class="fw-bold text-dark font-size-13 text-decoration-none d-block text-truncate" style="max-width: 320px;" title="' + data + '">' + data + '</a><span class="text-muted font-size-11 d-flex align-items-center gap-1"><i class="bx bx-paperclip"></i> ' + row.dosya_adi + '</span></div>';
                    
                    return '<div class="d-flex align-items-center gap-2.5">' + iconBox + titleText + '</div>';
                }
            },
            {
                data: 'format',
                className: 'text-center',
                render: function(data, type, row) {
                    const ext = (row.uzanti || data).toUpperCase();
                    if (data === 'word') {
                        return '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bxs-file-doc me-0.5"></i> ' + ext + '</span>';
                    } else if (data === 'excel') {
                        return '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bxs-file-table me-0.5"></i> ' + ext + '</span>';
                    } else if (data === 'pdf') {
                        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bxs-file-pdf me-0.5"></i> ' + ext + '</span>';
                    }
                    return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">' + ext + '</span>';
                }
            },
            {
                data: 'dosya_adi',
                render: function(data) {
                    return '<span class="font-size-12 text-dark fw-medium text-truncate d-block" style="max-width: 220px;" title="' + data + '">' + data + '</span>';
                }
            },
            { 
                data: 'ekleyen_adi',
                render: function(data) {
                    return '<div class="d-flex align-items-center gap-1.5"><div class="avatar-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 22px; height: 22px; font-size: 11px;"><i class="bx bx-user"></i></div><span class="font-size-12 fw-semibold text-dark text-truncate" style="max-width: 120px;">' + (data || '-') + '</span></div>';
                }
            },
            { 
                data: 'eklenme_tarihi',
                render: function(data) {
                    return '<span class="font-size-12 text-dark fw-medium">' + (data || '-') + '</span>';
                }
            },
            { 
                data: 'id', 
                orderable: false, 
                className: 'text-center',
                render: function(data, type, row) {
                    let btnDoldur = '';
                    if (['word', 'excel'].includes(row.format)) {
                        btnDoldur = '<button type="button" class="btn btn-subtle-info table-action-btn btn-personelli-indir" data-id="' + data + '" title="Şablonu Doldurarak İndir"><i class="bx bx-pencil"></i></button>';
                    }
                    
                    const btnIndir = '<a href="' + row.dosya_yolu + '" target="_blank" class="btn btn-subtle-secondary table-action-btn" title="Boş Şablonu İndir"><i class="bx bx-download"></i></a>';
                    const btnSil = '<button type="button" class="btn btn-subtle-danger table-action-btn btn-sil" data-id="' + data + '" title="Şablonu Sil"><i class="bx bx-trash"></i></button>';
                    
                    return '<div class="action-btn-group d-flex align-items-center justify-content-center gap-1">' + btnIndir + btnDoldur + btnSil + '</div>';
                }
            }
        ],
        order: [[0, 'asc']],
        columnDefs: [
            { targets: [0, 2, 6], orderable: false }
        ]
    }) : {
        order: [[0, 'asc']]
    };

    const table = $('#formlarTable').DataTable(dtConfig);

    // KPI Hızlı Filtre Butonları
    $('.status-quick-filter').on('click', function () {
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        activeFilterType = $(this).data('filter-type') || 'all';
        table.draw();
    });

    // Sekmeler
    $('.tab-manuel').click(function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $(this).closest('ul').find('.nav-link').removeClass('active');
        $(this).addClass('active');
        $(target).closest('.tab-content').find('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });

    // Şablon değişkenlerini kopyalama özelliği
    $('#degiskenlerModal table tbody tr td:first-child code').each(function() {
        var tag = $(this).text().trim();
        $(this).attr('title', 'Kopyalamak için tıklayın')
               .attr('data-tag', tag)
               .addClass('copy-tag-text');
        
        var $icon = $('<i class="bx bx-copy text-primary ms-1 cursor-pointer copy-tag-icon" title="Kopyalamak için tıklayın" style="font-size: 1.1em; vertical-align: middle;"></i>');
        $icon.attr('data-tag', tag);
        $(this).after($icon);
    });

    $(document).on('click', '.copy-tag-text, .copy-tag-icon', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var tag = $(this).attr('data-tag') || $(this).text().trim();
        if (!tag) return;

        function showSuccess(copiedTag) {
            if (typeof Toastify !== 'undefined') {
                Toastify({
                    text: "Panoya Kopyalandı: " + copiedTag,
                    duration: 2000,
                    close: true,
                    gravity: "top",
                    position: "center",
                    style: { background: "#1e293b", color: "#fff", borderRadius: "6px", boxShadow: "0 4px 12px rgba(0,0,0,0.3)" }
                }).showToast();
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: copiedTag + ' kopyalandı!', showConfirmButton: false, timer: 1500 });
            }
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(tag).then(function() {
                showSuccess(tag);
            }).catch(function() {
                fallbackCopy(tag, showSuccess);
            });
        } else {
            fallbackCopy(tag, showSuccess);
        }
    });

    function fallbackCopy(text, callback) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful && callback) callback(text);
        } catch (err) {
            console.error('Fallback copy fail:', err);
        }

        document.body.removeChild(textArea);
    }

    // Yeni Şablon Kaydet
    $('#btnKaydet').click(function() {
        var formData = new FormData($('#formEkleForm')[0]);
        formData.append('action', 'ekle');
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Yükleniyor...');
        
        $.ajax({
            url: 'api/formlar/islem.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                $('#yeniFormModal').modal('hide');
                $('#formEkleForm')[0].reset();
                table.ajax.reload();
                Swal.fire({ icon: 'success', title: 'Başarılı', text: res.message, timer: 2000, showConfirmButton: false });
            },
            error: function(xhr) {
                var res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Hata', text: res ? res.message : 'Bir hata oluştu' });
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="bx bx-cloud-upload me-1"></i> Yükle');
            }
        });
    });

    // Şablon Sil
    $(document).on('click', '.btn-sil', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu şablon tamamen silinecektir!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Evet, Sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api/formlar/islem.php', { action: 'sil', id: id }, function(res) {
                    Swal.fire({ icon: 'success', title: 'Silindi', text: res.message, timer: 2000, showConfirmButton: false });
                    table.ajax.reload();
                }).fail(function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Hata', text: xhr.responseJSON ? xhr.responseJSON.message : 'Silinemedi' });
                });
            }
        });
    });
    
    // Şablonu Doldurarak İndir Modalı
    $(document).on('click', '.btn-personelli-indir', function() {
        var id = $(this).data('id');
        $('#indirmeFormId').val(id);
        
        if ($.fn.select2) {
             $('#indirmePersonelId, #indirmeAracId').val(null).trigger('change');
        } else {
             $('#indirmePersonelId, #indirmeAracId').val('');
        }
        $('#indirmeAracBasKm, #indirmeAracBitKm, #indirmeAracAciklama').val('');
        $('#indirmeZimmetImei, #indirmeZimmetSeriNo').val('');
        $('#indirmeIzinBaslangic, #indirmeIzinBitis, #indirmeIzinGun, #indirmeIzinNedeni').val('');
        
        $('#personelSecModal').modal('show');
    });

    $('#btnSablounuIndir').click(function() {
        var formId = $('#indirmeFormId').val();
        var url = 'api/formlar/indir.php?id=' + encodeURIComponent(formId);
        
        var personelId = $('#indirmePersonelId').val();
        var aracId = $('#indirmeAracId').val();
        var imei = $('#indirmeZimmetImei').val();
        var seriNo = $('#indirmeZimmetSeriNo').val();
        var izinBaslangic = $('#indirmeIzinBaslangic').val();
        var izinBitis = $('#indirmeIzinBitis').val();
        var izinGun = $('#indirmeIzinGun').val();
        var izinNedeni = $('#indirmeIzinNedeni').val();
        
        if (!personelId && !aracId && !imei && !seriNo && !izinBaslangic && !izinBitis && !izinGun && !izinNedeni) {
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen indirme için en az bir bilgi doldurunuz.' });
            return;
        }

        if ((izinBaslangic || izinBitis || izinGun || izinNedeni) && !personelId) {
            Swal.fire({ icon: 'warning', title: 'Personel Seçimi', text: 'İzin bilgisi girebilmek için lütfen Personel sekmesinden formu dolduran personeli seçiniz.' });
            return;
        }
        
        if (personelId) url += '&personel_id=' + encodeURIComponent(personelId);
        if (aracId) {
            url += '&arac_id=' + encodeURIComponent(aracId);
            url += '&bas_km=' + encodeURIComponent($('#indirmeAracBasKm').val() || '');
            url += '&bit_km=' + encodeURIComponent($('#indirmeAracBitKm').val() || '');
            url += '&aciklama=' + encodeURIComponent($('#indirmeAracAciklama').val() || '');
        }
        if (imei) url += '&imei=' + encodeURIComponent(imei);
        if (seriNo) url += '&seri_no=' + encodeURIComponent(seriNo);
        if (izinBaslangic) url += '&izin_baslangic=' + encodeURIComponent(izinBaslangic);
        if (izinBitis) url += '&izin_bitis=' + encodeURIComponent(izinBitis);
        if (izinGun) url += '&izin_gun=' + encodeURIComponent(izinGun);
        if (izinNedeni) url += '&izin_nedeni=' + encodeURIComponent(izinNedeni);
        
        $('#personelSecModal').modal('hide');
        window.open(url, '_blank');
    });

    // Excel & Yazdır Butonları
    $('#btnHeaderExportExcel').on('click', function () {
        const tableEl = document.getElementById("formlarTable");
        if (!tableEl) return;
        
        const clone = tableEl.cloneNode(true);
        $(clone).find("th:last-child, td:last-child").remove();
        
        const html = clone.outerHTML;
        const blob = new Blob(["\ufeff", html], { type: "application/vnd.ms-excel" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "sablon_listesi_" + new Date().toISOString().slice(0, 10) + ".xls";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    $('#btnHeaderPrint').on('click', function () {
        window.print();
    });

    // ---------- Özet kartlarını gizle/göster ----------
    const SUMMARY_STATE_KEY = "formlar_summary_cards_state";

    function setSummaryCardsVisibility(visible) {
        document.documentElement.classList.toggle("formlar-summary-hidden", !visible);
        $("#btnToggleSummaryCards")
            .attr("aria-expanded", visible ? "true" : "false")
            .attr("title", visible ? "Özet Kartları Gizle" : "Özet Kartları Göster")
            .find("i")
            .attr("class", visible ? "bx bx-chevron-up" : "bx bx-chevron-down");
    }

    $("#btnToggleSummaryCards").on("click", function (e) {
        e.preventDefault();
        const isCurrentlyHidden = document.documentElement.classList.contains("formlar-summary-hidden");
        const shouldShow = isCurrentlyHidden;
        setSummaryCardsVisibility(shouldShow);
        try {
            localStorage.setItem(SUMMARY_STATE_KEY, shouldShow ? "visible" : "hidden");
        } catch (e) {}
    });

    (function initSummaryCardsState() {
        const isHidden = localStorage.getItem(SUMMARY_STATE_KEY) === "hidden";
        setSummaryCardsVisibility(!isHidden);
    })();
});
</script>
