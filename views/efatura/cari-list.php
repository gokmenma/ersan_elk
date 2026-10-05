<?php
\App\Service\Gate::authorizeOrDie('efatura/cari-list');

use App\Helper\Form;
use App\Helper\Security;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Cari Listesi';

// İl, İlçe ve Ülke verilerini yükle
$edmJsonPath = __DIR__ . '/data/edm_kod_listesi.json';
$edmKodListesi = file_exists($edmJsonPath) ? json_decode(file_get_contents($edmJsonPath), true) : [];
$sehirler = $edmKodListesi['cities'] ?? [];
$ilceler = $edmKodListesi['districts'] ?? [];
$ulkeler = $edmKodListesi['countries'] ?? [];

$ilOptions = ['' => 'İl Seçiniz...'];
foreach ($sehirler as $sehir) {
    $ilOptions[$sehir] = $sehir;
}

$ulkeOptions = ['Türkiye' => 'Türkiye'];
foreach ($ulkeler as $u) {
    if (($u['name'] ?? '') !== 'Türkiye' && !empty($u['name'])) {
        $ulkeOptions[$u['name']] = $u['name'];
    }
}

$aliciTuruOptions = [
    'KURUMSAL' => 'Kurumsal Firma (Tüzel Kişi / VKN)',
    'BIREYSEL' => 'Bireysel Şahıs (Gerçek Kişi / TCKN)'
];

$belgeTuruOptions = [
    'OTOMATIK' => 'Otomatik Tespit (GİB Sorgusuna Göre)',
    'EFATURA'  => 'e-Fatura (GİB Kayıtlı)',
    'EARSIV'   => 'e-Arşiv Fatura'
];
?>
<script>try { document.documentElement.classList.toggle('efatura-cari-summary-hidden', localStorage.getItem('efatura_cari_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.efatura-cari-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#efaturaCariTable tbody tr { cursor: pointer; }
#efaturaCariTable { border-bottom: 1px solid #e2e8f0 !important; }
#efaturaCariTable tbody tr:last-child td { border-bottom: 1px solid #e2e8f0 !important; }
.table-responsive { border-bottom: 1px solid #e2e8f0 !important; }
</style>
<script>
    const EDM_DISTRICTS = <?= json_encode($ilceler, JSON_UNESCAPED_UNICODE) ?>;
</script>
<meta name="csrf-token" content="<?= htmlspecialchars(Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-group fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">E-Fatura Cari Listesi</h4>
                <p class="text-muted mb-0 font-size-12">E-Fatura ve E-Arşiv alıcı/tedarikçi cari hesapları ve mükellefiyet yönetimi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Cari Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniCari">
                <i class="bx bx-plus font-size-16"></i> Yeni Cari Ekle
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/olustur">
                        <i class="bx bx-receipt me-2 text-primary font-size-16"></i> Yeni Fatura Kes
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/mal-hizmet-list">
                        <i class="bx bx-package me-2 text-info font-size-16"></i> Mal/Hizmet Tanımları
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/giden-list">
                        <i class="bx bx-cloud-upload me-2 text-primary font-size-16"></i> Giden Faturalara Git
                    </a>
                </div>
            </div>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM CARİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM CARİ</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-group"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_cari">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_kurumsal_bireysel">Kurumsal: 0 | Bireysel: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: KURUMSAL CARİLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KURUMSAL FİRMALAR</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-buildings"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_kurumsal_sayisi">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Tüzel Kişi / VKN</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="kurumsal">
                            <i class="bx bx-check-circle"></i> Kurumsal
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: BİREYSEL ŞAHISLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BİREYSEL ŞAHISLAR</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-user"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_bireysel_sayisi">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold">Gerçek Kişi / TCKN</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="bireysel">
                            <i class="bx bx-user-check"></i> Bireysel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: E-FATURA MÜKELLEFİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">E-FATURA MÜKELLEFİ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-cloud"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_efatura_mukellefi">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold" id="stat_earsiv_mukellefi">E-Arşiv: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="efatura">
                            <i class="bx bx-file"></i> e-Fatura
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. DataTables Liste Kartı -->
    <div class="card shadow-sm border-0 mb-4" id="efaturaCariListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bx bx-list-ul font-size-16"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold">E-Fatura Cari Listesi</h5>
                    <span class="text-muted font-size-11">Sistemdeki tüm kayıtlı alıcı ve tedarikçiler</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                    <i class="bx bx-printer font-size-14"></i> Yazdır
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive p-3">
                <table id="efaturaCariTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string" style="width: 110px;">Cari Kodu</th>
                            <th data-filter="string">Ünvan / Ad Soyad</th>
                            <th data-filter="string" style="width: 120px;">VKN / TCKN</th>
                            <th data-filter="select" style="width: 110px;">Alıcı Türü</th>
                            <th data-filter="select" style="width: 120px;">Belge Türü</th>
                            <th data-filter="string" style="width: 120px;">Telefon</th>
                            <th data-filter="string" style="width: 140px;">İl / İlçe</th>
                            <th data-filter="string">E-Posta</th>
                            <th data-filter="none" class="text-center" style="width: 110px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- CARİ EKLE / DÜZENLE MODALI                                    -->
<!-- ============================================================== -->
<div class="modal fade" id="modalCari" tabindex="-1" aria-labelledby="modalCariLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 44px; height: 44px; background-color: #d1fae5; color: #10b981;">
                        <i class="bx bx-user-plus fs-3 text-success" id="modalCariHeaderIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="modalCariLabel">Yeni E-Fatura Carisi Ekle</h5>
                        <p class="text-muted small mb-0">Yeni alıcı/tedarikçi kaydı oluşturmak için bilgileri doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            
            <form id="formCari" autocomplete="off">
                <div class="modal-body px-4 pt-3 pb-2">
                    <input type="hidden" name="enc_id" id="cari_enc_id" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Sekmeler (Nav Pills) -->
                    <ul class="nav nav-pills nav-justified mb-3 p-1 rounded-3" id="cariModalTabs" role="tablist" style="background: #f1f5f9;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-cari-genel-btn" data-bs-toggle="pill" data-bs-target="#tab-cari-genel" type="button" role="tab" aria-selected="true" style="border-radius: 8px;">
                                <i class="bx bx-receipt font-size-16"></i> <span>Fatura & Vergi (GİB)</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-cari-iletisim-btn" data-bs-toggle="pill" data-bs-target="#tab-cari-iletisim" type="button" role="tab" aria-selected="false" style="border-radius: 8px;">
                                <i class="bx bx-map-pin font-size-16"></i> <span>İletişim & Adres</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-cari-diger-btn" data-bs-toggle="pill" data-bs-target="#tab-cari-diger" type="button" role="tab" aria-selected="false" style="border-radius: 8px;">
                                <i class="bx bx-notepad font-size-16"></i> <span>Diğer & Notlar</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="cariModalTabsContent">
                        <!-- 1. SEKME: FATURA & VERGİ BİLGİLERİ (GİB) -->
                        <div class="tab-pane fade show active" id="tab-cari-genel" role="tabpanel">
                            <div class="row g-3">
                                <!-- VKN/TCKN ve GİB Sorgulama -->
                                <div class="col-md-7 col-12">
                                    <?= Form::FormFloatInput('text', 'vkn_tckn', '', '10 Haneli VKN veya 11 Haneli TCKN', 'VKN / TCKN *', 'bx bx-id-card', 'form-control fw-bold', true, 11, 'off') ?>
                                </div>
                                <div class="col-md-5 col-12">
                                    <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-1 shadow-sm" id="btnGibSorgula" style="height: 52px; border-radius: 8px;">
                                        <i class="bx bx-search fs-5"></i> <span class="fw-semibold font-size-13">GİB'den Sorgula</span>
                                    </button>
                                </div>
                                <div class="col-12 mt-1">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-1 border-top">
                                        <small class="text-muted" id="gib_sorgu_sonuc">10 haneli VKN veya 11 haneli TCKN girerek sorgulayabilirsiniz.</small>
                                        <div id="cariMukellefBadge">
                                            <span class="badge bg-light text-muted border px-3 py-1.5 rounded-pill font-size-11">
                                                <i class="bx bx-info-circle me-1"></i> VKN sorgulayabilirsiniz
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cari Ünvan / Ad Soyad -->
                                <div class="col-12">
                                    <?= Form::FormFloatInput('text', 'unvan', '', 'Cari resmî ünvanı veya tam adı soyadı', 'Cari Ünvan / Ad Soyad *', 'bx bx-building', 'form-control fw-semibold', true) ?>
                                </div>

                                <!-- Kısa Ad / Rumuz -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'kisa_ad', '', 'Kısa ad veya rumuz', 'Kısa Ad / Rumuz', 'bx bx-user') ?>
                                </div>

                                <!-- Cari Kodu -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'cari_kodu', '', 'Örn: CARI-001', 'Cari Kodu', 'bx bx-barcode') ?>
                                </div>

                                <!-- Alıcı Türü -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('alici_turu', $aliciTuruOptions, 'KURUMSAL', 'Alıcı Türü *', 'bx bx-user-check') ?>
                                </div>

                                <!-- Belge Türü -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('belge_turu', $belgeTuruOptions, 'OTOMATIK', 'Belge Türü *', 'bx bx-file') ?>
                                </div>

                                <!-- Vergi Dairesi -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'vergi_dairesi', '', 'Vergi dairesi adı', 'Vergi Dairesi', 'bx bx-briefcase-alt') ?>
                                </div>

                                <!-- Posta Kutusu (GB Alias) -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'posta_kutusu', '', 'urn:mail:defaultgb@...', 'E-Fatura Posta Kutusu (GB Alias)', 'bx bx-envelope', 'form-control font-monospace') ?>
                                </div>
                            </div>
                        </div>

                        <!-- 2. SEKME: İLETİŞİM & ADRES -->
                        <div class="tab-pane fade" id="tab-cari-iletisim" role="tabpanel">
                            <div class="row g-3">
                                <!-- Telefon -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'telefon', '', 'Telefon numarası', 'Telefon Numarası', 'bx bx-phone') ?>
                                </div>

                                <!-- E-Posta -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('email', 'eposta', '', 'E-Posta adresi', 'E-Posta Adresi', 'bx bx-at') ?>
                                </div>

                                <!-- Web Sitesi -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'web_sitesi', '', 'www.site.com', 'Web Sitesi', 'bx bx-globe') ?>
                                </div>

                                <!-- Ülke -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('ulke', $ulkeOptions, 'Türkiye', 'Ülke', 'bx bx-globe') ?>
                                </div>

                                <!-- İl -->
                                <div class="col-md-4 col-12">
                                    <?= Form::FormSelect2('il', $ilOptions, '', 'İl', 'bx bx-map') ?>
                                </div>

                                <!-- İlçe -->
                                <div class="col-md-4 col-12">
                                    <?= Form::FormSelect2('ilce', ['' => 'İlçe Seçiniz...'], '', 'İlçe', 'bx bx-map-pin') ?>
                                </div>

                                <!-- Posta Kodu -->
                                <div class="col-md-4 col-12">
                                    <?= Form::FormFloatInput('text', 'posta_kodu', '', 'Posta Kodu', 'Posta Kodu', 'bx bx-archive') ?>
                                </div>

                                <!-- Açık Adres -->
                                <div class="col-12">
                                    <?= Form::FormFloatTextarea('adres', '', 'Cadde, sokak, bina ve kapı no...', 'Açık Fatura Adresi', 'bx bx-map-pin', 'form-control', false, '80px', 2) ?>
                                </div>
                            </div>
                        </div>

                        <!-- 3. SEKME: DİĞER & NOTLAR -->
                        <div class="tab-pane fade" id="tab-cari-diger" role="tabpanel">
                            <div class="row g-3">
                                <!-- Ticaret Sicil No -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'ticaret_sicil_no', '', 'Ticaret Sicil No', 'Ticaret Sicil No', 'bx bx-credit-card') ?>
                                </div>

                                <!-- Mersis No -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'mersis_no', '', 'Mersis No', 'Mersis No', 'bx bx-tag') ?>
                                </div>

                                <!-- Notlar -->
                                <div class="col-12">
                                    <?= Form::FormFloatTextarea('notlar', '', 'Cari ile ilgili dahili notlar veya özel şartlar...', 'Dahili Notlar / Açıklamalar', 'bx bx-notepad', 'form-control', false, '80px', 2) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="background:#6c757d; border-radius: 8px; border:none;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4 fw-semibold shadow-sm" id="btnSaveCari" style="background:#1e293b; border-radius: 8px; border:none;">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    let currentStatusFilter = 'all';

    // 1. Özet Kartları Aç/Kapa
    $('#btnToggleSummaryCards').on('click', function() {
        const isHidden = document.documentElement.classList.toggle('efatura-cari-summary-hidden');
        localStorage.setItem('efatura_cari_summary_cards_state', isHidden ? 'hidden' : 'visible');
        $(this).find('i').toggleClass('bx-chevron-up bx-chevron-down');
        $(this).attr('aria-expanded', !isHidden);
    });

    if (localStorage.getItem('efatura_cari_summary_cards_state') === 'hidden') {
        $('#btnToggleSummaryCards i').removeClass('bx-chevron-up').addClass('bx-chevron-down');
        $('#btnToggleSummaryCards').attr('aria-expanded', 'false');
    }

    // Modal içi Select2 başlatma fonksiyonu (Çift eleman oluşmasını önler)
    function initCariModalSelect2() {
        $('#modalCari select.select2').each(function() {
            const $select = $(this);
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.siblings('.select2-container').remove();
            $select.select2({
                dropdownParent: $('#modalCari'),
                width: '100%'
            });
        });
    }

    // Modal açıldığında Select2 başlat
    $('#modalCari').on('shown.bs.modal', function() {
        initCariModalSelect2();
    });

    // Sekmeler arasında geçiş yapıldığında aktif sekmedeki Select2'leri tazele
    $('#cariModalTabs button[data-bs-toggle="pill"]').on('shown.bs.tab', function() {
        initCariModalSelect2();
    });

    // İl seçildiğinde ilçeleri dinamik doldurma
    $('#il').on('change', function() {
        const selectedCity = $(this).val();
        const $ilce = $('#ilce');
        $ilce.empty().append('<option value="">İlçe Seçiniz...</option>');

        if (selectedCity && EDM_DISTRICTS[selectedCity]) {
            EDM_DISTRICTS[selectedCity].forEach(function(district) {
                $ilce.append(new Option(district, district));
            });
        }
        $ilce.trigger('change.select2');
    });

    // 2. DataTables Başlatma
    const table = $('#efaturaCariTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/efatura-cari-api.php',
            type: 'GET',
            data: function(d) {
                d.action = 'list';
                d.status_filter = currentStatusFilter;
            },
            error: function(xhr) {
                console.error('DataTables Load Error:', xhr);
            }
        },
        columns: [
            {
                data: 'cari_kodu',
                render: function(data) {
                    return data ? '<span class="fw-semibold text-dark">' + escapeHtml(data) + '</span>' : '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'unvan',
                render: function(data, type, row) {
                    let sub = row.kisa_ad ? '<div class="text-muted font-size-11">' + escapeHtml(row.kisa_ad) + '</div>' : '';
                    return '<div class="fw-bold text-dark font-size-13">' + escapeHtml(data) + '</div>' + sub;
                }
            },
            {
                data: 'vkn_tckn',
                render: function(data) {
                    return '<span class="badge bg-light text-secondary border font-size-11"><i class="bx bx-id-card me-1"></i>' + escapeHtml(data) + '</span>';
                }
            },
            {
                data: 'alici_turu',
                render: function(data) {
                    if (data === 'KURUMSAL') {
                        return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-buildings me-1"></i>Kurumsal</span>';
                    }
                    return '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-user me-1"></i>Bireysel</span>';
                }
            },
            {
                data: 'belge_turu',
                render: function(data) {
                    if (data === 'EFATURA') {
                        return '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-cloud-check me-1"></i>e-Fatura</span>';
                    } else if (data === 'EARSIV') {
                        return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-file me-1"></i>e-Arşiv</span>';
                    }
                    return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Otomatik</span>';
                }
            },
            {
                data: 'telefon',
                render: function(data) {
                    return data ? '<span class="font-size-12"><i class="bx bx-phone me-1 text-muted"></i>' + escapeHtml(data) + '</span>' : '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'il',
                render: function(data, type, row) {
                    let loc = [];
                    if (row.il) loc.push(row.il);
                    if (row.ilce) loc.push(row.ilce);
                    return loc.length > 0 ? '<span class="font-size-12 text-muted"><i class="bx bx-map-pin me-1"></i>' + escapeHtml(loc.join(' / ')) + '</span>' : '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'eposta',
                render: function(data) {
                    return data ? '<span class="font-size-12"><i class="bx bx-envelope me-1 text-muted"></i>' + escapeHtml(data) + '</span>' : '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'enc_id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    return `
                        <div class="action-btn-group d-flex align-items-center justify-content-center gap-1">
                            <button type="button" class="btn btn-sm btn-subtle-warning table-action-btn btn-edit-cari" data-id="${data}" title="Düzenle">
                                <i class="bx bx-edit font-size-14"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-delete-cari" data-id="${data}" data-name="${escapeHtml(row.unvan)}" title="Sil">
                                <i class="bx bx-trash font-size-14"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[1, 'asc']]
    }));

    // 3. KPI İstatistiklerini Yükle
    function loadSummaryStats() {
        $.getJSON('api/efatura-cari-api.php?action=summary', function(res) {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                $('#stat_toplam_cari').text(d.toplam_cari || 0);
                $('#stat_sub_kurumsal_bireysel').text('Kurumsal: ' + (d.kurumsal_sayisi || 0) + ' | Bireysel: ' + (d.bireysel_sayisi || 0));
                $('#stat_kurumsal_sayisi').text(d.kurumsal_sayisi || 0);
                $('#stat_bireysel_sayisi').text(d.bireysel_sayisi || 0);
                $('#stat_efatura_mukellefi').text(d.efatura_mukellefi || 0);
                $('#stat_earsiv_mukellefi').text('e-Arşiv: ' + (d.earsiv_mukellefi || 0));
            }
        });
    }
    loadSummaryStats();

    // 4. Hızlı Durum Filtre Butonları
    $('.status-quick-filter').on('click', function() {
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        currentStatusFilter = $(this).data('status');
        table.ajax.reload();
    });

    // 5. Yeni Cari Ekle Butonu
    $('#btnYeniCari').on('click', function() {
        $('#formCari')[0].reset();
        $('#cari_enc_id').val('');
        $('#modalCariLabel').text('Yeni E-Fatura Carisi Ekle');
        $('#modalCariHeaderIcon').attr('class', 'bx bx-user-plus fs-3 text-success');
        $('#tab-cari-genel-btn').tab('show');
        initCariModalSelect2();
        $('#alici_turu').val('KURUMSAL').trigger('change.select2');
        $('#belge_turu').val('OTOMATIK').trigger('change.select2');
        $('#ulke').val('Türkiye').trigger('change.select2');
        $('#il').val('').trigger('change.select2');
        $('#ilce').empty().append('<option value="">İlçe Seçiniz...</option>').trigger('change.select2');
        $('#gib_sorgu_sonuc').html('10 haneli VKN veya 11 haneli TCKN girerek sorgulayabilirsiniz.').attr('class', 'text-muted font-size-11 mt-1');
        $('#cariMukellefBadge').html(`
            <span class="badge bg-light text-muted border px-3 py-1.5 rounded-pill font-size-11">
                <i class="bx bx-info-circle me-1"></i> VKN sorgulayabilirsiniz
            </span>
        `);
        $('#modalCari').modal('show');
        if (typeof feather !== 'undefined') feather.replace();
    });

    // 6. Satıra Tıklandığında veya Düzenle Butonunda Modalı Aç
    $('#efaturaCariTable tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('.action-btn-group, button, a').length) return;
        const data = table.row(this).data();
        if (data && data.enc_id) {
            openEditModal(data.enc_id);
        }
    });

    $(document).on('click', '.btn-edit-cari', function(e) {
        e.stopPropagation();
        const encId = $(this).data('id');
        openEditModal(encId);
    });

    function openEditModal(encId) {
        Swal.fire({
            title: 'Yükleniyor...',
            text: 'Cari bilgileri getiriliyor.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.getJSON('api/efatura-cari-api.php?action=get&id=' + encodeURIComponent(encId), function(res) {
            Swal.close();
            if (res.status === 'success' && res.data) {
                const d = res.data;
                $('#formCari')[0].reset();
                $('#cari_enc_id').val(d.enc_id);
                $('#modalCariLabel').text('Cariyi Düzenle: ' + d.unvan);
                $('#modalCariHeaderIcon').attr('class', 'bx bx-edit fs-3 text-primary');
                $('#tab-cari-genel-btn').tab('show');
                initCariModalSelect2();

                $('#vkn_tckn').val(d.vkn_tckn || '');
                $('#cari_kodu').val(d.cari_kodu || '');
                $('#unvan').val(d.unvan || '');
                $('#kisa_ad').val(d.kisa_ad || '');
                $('#vergi_dairesi').val(d.vergi_dairesi || '');
                $('#posta_kutusu').val(d.posta_kutusu || '');
                $('#telefon').val(d.telefon || '');
                $('#eposta').val(d.eposta || '');
                $('#web_sitesi').val(d.web_sitesi || '');
                $('#ticaret_sicil_no').val(d.ticaret_sicil_no || '');
                $('#mersis_no').val(d.mersis_no || '');
                $('#adres').val(d.adres || '');
                $('#notlar').val(d.notlar || '');
                $('#posta_kodu').val(d.posta_kodu || '');

                $('#alici_turu').val(d.alici_turu || 'KURUMSAL').trigger('change.select2');
                $('#belge_turu').val(d.belge_turu || 'OTOMATIK').trigger('change.select2');
                $('#ulke').val(d.ulke || 'Türkiye').trigger('change.select2');
                
                $('#il').val(d.il || '').trigger('change.select2');
                if (d.il && EDM_DISTRICTS[d.il]) {
                    const $ilce = $('#ilce');
                    $ilce.empty().append('<option value="">İlçe Seçiniz...</option>');
                    EDM_DISTRICTS[d.il].forEach(function(dist) {
                        $ilce.append(new Option(dist, dist, false, dist === d.ilce));
                    });
                    $ilce.trigger('change.select2');
                }

                if (d.belge_turu === 'EFATURA') {
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-check-circle"></i> E-Fatura Mükellefi
                        </span>
                    `);
                } else if (d.belge_turu === 'EARSIV') {
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-info-circle"></i> E-Arşiv Mükellefi
                        </span>
                    `);
                } else {
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-light text-muted border px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-info-circle"></i> Otomatik Tespit
                        </span>
                    `);
                }

                $('#gib_sorgu_sonuc').html('Kayıtlı cari bilgileri yüklendi.').attr('class', 'text-muted font-size-11 mt-1');
                $('#modalCari').modal('show');
            } else {
                Swal.fire('Hata', res.message || 'Cari bulunamadı.', 'error');
            }
        }).fail(function(xhr) {
            Swal.close();
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sunucuyla bağlantı kurulamadı.';
            Swal.fire('Hata', msg, 'error');
        });
    }

    // 7. GİB Mükellefiyet Sorgulama
    $('#btnGibSorgula').on('click', function() {
        const vkn = $('#vkn_tckn').val().replace(/\D/g, '');
        if (vkn.length !== 10 && vkn.length !== 11) {
            Swal.fire('Uyarı', 'Lütfen 10 haneli VKN veya 11 haneli TCKN giriniz.', 'warning');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Sorgulanıyor...');
        $('#gib_sorgu_sonuc').html('<span class="text-primary"><i class="bx bx-loader-alt bx-spin"></i> GİB / EDM sistemi sorgulanıyor...</span>');

        $.getJSON('api/efatura-cari-api.php?action=check_taxpayer&vkn=' + encodeURIComponent(vkn), function(res) {
            $btn.prop('disabled', false).html('<i class="bx bx-search font-size-16 me-1"></i> <span class="font-size-12 fw-semibold">Sorgula</span>');
            if (res.status === 'success' && res.data) {
                const d = res.data;
                if (d.is_taxpayer) {
                    $('#belge_turu').val('EFATURA').trigger('change');
                    $('#alici_turu').val(vkn.length === 10 ? 'KURUMSAL' : 'BIREYSEL').trigger('change');
                    if (d.title && !$('#unvan').val()) {
                        $('#unvan').val(d.title);
                    }
                    if (d.alias) {
                        $('#posta_kutusu').val(d.alias);
                    }
                    $('#gib_sorgu_sonuc').html('<span class="text-success fw-semibold"><i class="bx bx-check-circle"></i> E-Fatura Mükellefi Tespit Edildi (' + (d.alias || 'Posta Kutusu Mevcut') + ')</span>');
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-check-circle"></i> E-Fatura Mükellefi
                        </span>
                    `);
                } else {
                    $('#belge_turu').val('EARSIV').trigger('change');
                    $('#gib_sorgu_sonuc').html('<span class="text-warning fw-semibold"><i class="bx bx-info-circle"></i> E-Fatura Mükellefi Değil (E-Arşiv Fatura)</span>');
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-info-circle"></i> E-Arşiv Mükellefi
                        </span>
                    `);
                }
            } else {
                $('#gib_sorgu_sonuc').html('<span class="text-danger">' + (res.message || 'Sorgulama başarısız.') + '</span>');
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false).html('<i class="bx bx-search font-size-16 me-1"></i> <span class="font-size-12 fw-semibold">Sorgula</span>');
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sorgu sırasında sunucu hatası oluştu.';
            $('#gib_sorgu_sonuc').html('<span class="text-danger">' + msg + '</span>');
        });
    });

    // 8. Cari Formunu Kaydet
    $('#formCari').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#btnSaveCari');
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Kaydediliyor...');

        const formData = $(this).serialize();
        const effectiveCsrf = $('input[name="csrf_token"]').val() || csrfToken;

        $.ajax({
            url: 'api/efatura-cari-api.php?action=save',
            type: 'POST',
            data: formData,
            headers: { 'X-CSRF-Token': effectiveCsrf },
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res.status === 'success') {
                    $('#modalCari').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: res.message || 'Cari başarıyla kaydedildi.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    table.ajax.reload(null, false);
                    loadSummaryStats();
                } else {
                    Swal.fire('Hata', res.message || 'Kayıt sırasında hata oluştu.', 'error');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sunucuyla bağlantı hatası oluştu.';
                Swal.fire('Hata', msg, 'error');
            }
        });
    });

    // 9. Cari Silme
    $(document).on('click', '.btn-delete-cari', function(e) {
        e.stopPropagation();
        const encId = $(this).data('id');
        const name = $(this).data('name') || 'Bu cari';
        const effectiveCsrf = $('input[name="csrf_token"]').val() || csrfToken;

        Swal.fire({
            title: 'Emin misiniz?',
            text: `"${name}" cari kaydı sistemden silinecektir.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/efatura-cari-api.php?action=delete',
                    type: 'POST',
                    data: { id: encId, csrf_token: effectiveCsrf },
                    headers: { 'X-CSRF-Token': effectiveCsrf },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
                                text: res.message || 'Cari kaydı silindi.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                            loadSummaryStats();
                        } else {
                            Swal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sunucu hatası oluştu.';
                        Swal.fire('Hata', msg, 'error');
                    }
                });
            }
        });
    });

    // 10. Yazdır ve Excel
    $('#btnHeaderPrint').on('click', function() {
        window.print();
    });

    $('#btnDropdownExportExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
