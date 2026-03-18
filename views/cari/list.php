<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;
$maintitle = 'Ana Sayfa';
$title = 'Cari Yönetimi';
?>

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst Bilgi Çubuğu (Bordro Stili) -->
    <div class="card border-0 shadow-sm mb-4 bordro-info-bar"
        style="border-radius: 20px; background: rgba(19, 91, 236, 0.03); border: 1px solid rgba(19, 91, 236, 0.1) !important;">
        <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="bg-white rounded-3 shadow-sm p-2 me-3 d-flex align-items-center justify-content-center"
                    style="width: 45px; height: 45px;">
                    <i class="bx bx-group fs-3 text-primary"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold bordro-text-heading">Cari Hesaplar</h5>
                    <small class="text-muted fw-medium">Firmaya kayıtlı tüm cari hesapların listesi ve bakiye durumu.</small>
                </div>
            </div>
            
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto d-none d-md-flex">
                <button type="button" class="btn btn-link btn-sm text-success text-decoration-none px-2" id="btnExportExcel" title="Excel'e Aktar">
                    <i data-feather="printer"></i>
                </button>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-3 d-flex align-items-center shadow-sm" id="btnYeniCari">
                    <i data-feather="plus" class="me-1" style="width: 16px; height: 16px;"></i> <span class="d-none d-sm-inline">Yeni Cari Ekle</span>
                </button>
            </div>
            
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto d-md-none">
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-3 d-flex align-items-center shadow-sm" id="btnYeniCariMobileTop">
                    <i data-feather="plus" class="me-1" style="width: 16px; height: 16px;"></i> Ekle
                </button>
            </div>
        </div>
    </div>

    <!-- Özet Kartları (Minimal Mobil ve Desktop) -->
    <div class="row g-2 mb-4 summary-cards-container">
        <div class="col-4 col-md-4">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card minimal-card"
                style="--card-color: #f43f5e; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 text-center text-md-start">
                    <div class="icon-label-container d-none d-md-flex">
                        <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                            <i class="bx bx-trending-up fs-4 text-danger"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold d-none d-md-block" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM BORÇ</p>
                    <p class="text-danger mb-0 small fw-bold d-md-none" style="font-size: 10px;">TOPLAM BORÇ</p>
                    <h5 class="mb-0 fw-bold bordro-text-heading mt-md-0 mt-1">
                        <span id="toplam_borc" style="font-size: 0.9rem;">0,00</span> <span style="font-size: 0.7rem; font-weight: 600;">₺</span>
                    </h5>
                </div>
            </div>
        </div>

        <div class="col-4 col-md-4">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card minimal-card"
                style="--card-color: #2a9d8f; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 text-center text-md-start">
                    <div class="icon-label-container d-none d-md-flex">
                        <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                            <i class="bx bx-trending-down fs-4" style="color: #2a9d8f;"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold d-none d-md-block" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM ALACAK</p>
                    <p class="text-success mb-0 small fw-bold d-md-none" style="font-size: 10px;">TOPLAM ALACAK</p>
                    <h5 class="mb-0 fw-bold bordro-text-heading mt-md-0 mt-1">
                        <span id="toplam_alacak" style="font-size: 0.9rem;">0,00</span> <span style="font-size: 0.7rem; font-weight: 600;">₺</span>
                    </h5>
                </div>
            </div>
        </div>

        <div class="col-4 col-md-4">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card minimal-card"
                style="--card-color: #135bec; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 text-center text-md-start">
                    <div class="icon-label-container d-none d-md-flex">
                        <div class="icon-box" style="background: rgba(19, 91, 236, 0.1);">
                            <i class="bx bx-briefcase fs-4 text-primary"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold d-none d-md-block" style="letter-spacing: 0.5px; opacity: 0.7;">BAKİYE</p>
                    <p class="text-primary mb-0 small fw-bold d-md-none" id="mobile_bakiye_title" style="font-size: 10px;">GÜNCEL BAKİYE</p>
                    <h5 class="mb-0 fw-bold bordro-text-heading mt-md-0 mt-1" id="bakiye_label_container">
                        <span id="genel_bakiye" style="font-size: 0.9rem;">0,00</span> <span style="font-size: 0.7rem; font-weight: 600;">₺</span>
                        <small id="bakiye_bilgi" style="font-size: 0.6rem; display: block;"></small>
                    </h5>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Mobil Tasarım İyileştirmeleri - Dashboard Uyumluluğu */
        @media (max-width: 767.98px) {
            .bordro-info-bar { border-radius: 15px !important; margin-bottom: 1rem !important; }
            .summary-cards-container { margin-bottom: 1rem !important; }
            
            .minimal-card { 
                padding: 10px 5px !important; 
                min-height: 70px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .minimal-card .card-body { padding: 5px !important; }
            .minimal-card h5 { font-size: 0.85rem !important; margin-top: 2px !important; }
            .minimal-card p.small { font-size: 8px !important; margin-bottom: 0 !important; }

            .datatable-deferred_wrapper .top { display: none; } 
            
            .cari-mobile-list { display: block !important; padding: 0 5px; }
            .cari-desktop-table { display: none !important; }
            
            .mobile-card {
                background: #fff;
                border-radius: 12px;
                padding: 12px 15px;
                margin-bottom: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                display: flex;
                align-items: center;
                border: 1px solid #f1f3f5;
                transition: all 0.2s ease;
                position: relative;
                overflow: hidden;
            }
            .mobile-card:active { background-color: #f8f9fa; transform: scale(0.99); }
            
            .mobile-card::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 3px;
                background: #135bec; /* Accent color */
                opacity: 0.1;
            }

            .btn-soft-success {
                background-color: rgba(25, 135, 84, 0.1);
                color: #198754;
                border: none;
                border-radius: 8px;
                transition: all 0.2s;
                height: 32px;
                width: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .btn-soft-success:active { background-color: rgba(25, 135, 84, 0.2); transform: scale(0.9); }
            
            .mobile-quick-add { margin-left: auto; }
            
            .mobile-card-icon {
                width: 40px;
                height: 40px;
                background: #f8fbff;
                color: #135bec;
                border: 1px solid rgba(19, 91, 236, 0.1);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                font-weight: 700;
                margin-right: 12px;
                flex-shrink: 0;
            }
            .mobile-card-content { flex-grow: 1; padding-right: 10px; }
            .mobile-card-title { font-weight: 600; font-size: 14px; color: #1a1a1a; margin-bottom: 1px; }
            .mobile-card-subtitle { font-size: 11px; color: #6c757d; font-weight: 500; display: flex; align-items: center; }
            .mobile-card-subtitle i { font-size: 12px; margin-right: 3px; }
            
            .mobile-card-right { display: flex; align-items: center; gap: 8px; }
            .mobile-card-value { text-align: right; }
            .mobile-card-amt { font-weight: 700; font-size: 13px; display: block; }
            .mobile-card-type { font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
            
            .mobile-card-chevron { color: #dee2e6; flex-shrink: 0; }
            
            /* Modern FAB */
            .fab-button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background-color: #135bec;
                color: white;
                border-radius: 50px;
                padding: 12px 20px;
                box-shadow: 0 4px 12px rgba(19, 91, 236, 0.4);
                display: flex;
                align-items: center;
                gap: 8px;
                border: none;
                z-index: 1000;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            .fab-button:active { transform: scale(0.95); }

            /* Flatpickr Time input fix for Modal */
            .flatpickr-time input {
                -webkit-appearance: none;
                -moz-appearance: textfield;
                appearance: none;
            }
            .flatpickr-time input::-webkit-outer-spin-button,
            .flatpickr-time input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                appearance: none;
                margin: 0;
            }
            
            .mobile-search-bar {
                background: #fff;
                border-radius: 12px;
                padding: 12px 15px;
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                border: 1px solid #e9ecef;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            }
            .mobile-search-bar i { color: #135bec; margin-right: 12px; font-size: 20px; }
            .mobile-search-bar input {
                border: none;
                background: transparent;
                width: 100%;
                outline: none;
                font-size: 14px;
                font-weight: 600;
                color: #333;
            }
            .mobile-search-bar input::placeholder { color: #adb5bd; }
        }
        @media (min-width: 768px) {
            .cari-mobile-list { display: none !important; }
            .fab-button { display: none !important; }
        }
    </style>

    <div class="row cari-desktop-table">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="cariTable" class="table table-hover table-bordered nowrap w-100 datatable-deferred">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Cari Adı</th>
                                <th>Firma</th>
                                <th>Telefon</th>
                                <th>Email</th>
                                <th>Adres</th>
                                <th class="text-end">Bakiye</th>
                                <th class="text-center" style="width: 80px;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobil Liste Görünümü -->
    <div class="cari-mobile-list">
        <div class="mobile-search-container">
            <div class="mobile-search-bar">
                <i class="bx bx-search"></i>
                <input type="text" id="mobileSearch" placeholder="Cari Ara...">
            </div>
        </div>
        <div id="cariMobileContainer">
            <!-- Kartlar JS ile buraya gelecek -->
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div>
                Yükleniyor...
            </div>
        </div>
        <div style="height: 80px;"></div> <!-- FAB için boşluk -->
    </div>

    <!-- FAB -->
    <button class="fab-button" id="btnYeniCariMobile">
        <i class="bx bx-user-plus fs-4"></i>
        <span>Cari Ekle</span>
    </button>
</div>

<div class="modal fade" id="cariModal" tabindex="-1" aria-labelledby="cariModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background-color: #d1fae5;">
                        <i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="cariModalLabel" style="color: #1a1a1a;">Yeni Cari Ekle</h5>
                        <p class="text-muted small mb-0">Yeni kayıt oluşturmak için bilgileri doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="cariForm">
                <input type="hidden" name="action" value="cari-kaydet">
                <input type="hidden" name="cari_id" id="cari_id" value="">
                
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput("text", "CariAdi", "", "Cari Adı", "Cari Adı", "bx bx-user", "form-control", true); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput("text", "firma", "", "Firma", "Firma / Ünvan", "bx bx-building", "form-control", false); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput("text", "Telefon", "", "Telefon", "Telefon", "bx bx-phone", "form-control"); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput("email", "Email", "", "Email", "Email", "bx bx-envelope", "form-control"); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea("Adres", "", "Adres", "Adres", "bx bx-map-pin", "form-control", false, "100px"); ?>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="hizliIslemModal" tabindex="-1" aria-labelledby="hizliIslemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-3" id="hizliIslemIconBg" style="width: 48px; height: 48px;">
                        <i class="bx bx-transfer font-size-24 text-primary" id="hizliIslemIcon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title fw-bold mb-1" id="hizliIslemModalLabel" style="color: #1a1a1a;">Yeni İşlem Ekle</h5>
                        <p class="text-muted small mb-0" id="hizliIslemModalDesc">İşlem türünü seçin ve bilgileri girin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="hizliIslemForm">
                <input type="hidden" name="action" value="hizli-hareket-kaydet">
                <input type="hidden" name="cari_id" id="hizli_islem_cari_id" value="">
                
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="mb-4 d-flex justify-content-center gap-2">
                        <input type="radio" class="btn-check" name="type" id="type_aldim" value="aldim" autocomplete="off" checked>
                        <label class="btn btn-outline-success flex-grow-1 fw-bold" for="type_aldim"><i class="bx bx-minus-circle me-1"></i>Aldım</label>

                        <input type="radio" class="btn-check" name="type" id="type_verdim" value="verdim" autocomplete="off">
                        <label class="btn btn-outline-danger flex-grow-1 fw-bold" for="type_verdim"><i class="bx bx-plus-circle me-1"></i>Verdim</label>
                    </div>

                    <div class="mb-3">
                        <?php 
                        echo Form::FormFloatInput("text", "islem_tarihi", date('Y-m-d H:i'), "Tarih", "Tarih", "calendar", "form-control flatpickr-time-input", true, null, "off", false); 
                        ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Tutar</label>
                        <?php echo Form::FormFloatInput("text", "tutar", "", "0.00", "İşlem Tutarı", "dollar-sign", "form-control money", true, null, "off", false, 'step="0.01" min="0.01"'); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput("text", "belge_no", "", "Belge No", "Belge No", "hash", "form-control"); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea("aciklama", "", "Açıklama giriniz...", "Açıklama", "list", "form-control", false, "80px"); ?>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/cari/js/cari.js?v=<?php echo time(); ?>"></script>
