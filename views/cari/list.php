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

    <!-- Özet Kartları (Bordro Stili) -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-4">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                            <i class="bx bx-trending-up fs-4 text-danger"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">BORÇ</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM BORÇ</p>
                    <h4 class="mb-0 fw-bold bordro-text-heading">
                        <span id="toplam_borc">0,00</span> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-4">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                            <i class="bx bx-trending-down fs-4" style="color: #2a9d8f;"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">ALACAK</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM ALACAK</p>
                    <h4 class="mb-0 fw-bold bordro-text-heading">
                        <span id="toplam_alacak">0,00</span> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-4">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #135bec; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(19, 91, 236, 0.1);">
                            <i class="bx bx-wallet fs-4 text-primary"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">BAKİYE</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">GENEL BAKİYE</p>
                    <h4 class="mb-0 fw-bold bordro-text-heading">
                        <span id="genel_bakiye">0,00</span> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Mobil Tasarım İyileştirmeleri - Dashboard Uyumluluğu */
        @media (max-width: 767.98px) {
            .bordro-info-bar { border-radius: 15px !important; margin-bottom: 1.5rem !important; }
            .bordro-summary-card { margin-bottom: 0.75rem !important; }
            .datatable-deferred_wrapper .top { display: none; } 
            
            .cari-mobile-list { display: block !important; padding: 0 5px; }
            .cari-desktop-table { display: none !important; }
            
            .mobile-card {
                background: #fff;
                border-radius: 15px;
                padding: 15px;
                margin-bottom: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.02);
                display: flex;
                align-items: center;
                justify-content: space-between;
                border: 1px solid rgba(0,0,0,0.05);
                transition: all 0.2s ease;
            }
            .mobile-card:active { background-color: #f8f9fa; transform: scale(0.98); }
            
            .mobile-card-icon {
                width: 42px;
                height: 42px;
                background: rgba(19, 91, 236, 0.05);
                color: #135bec;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                font-weight: 700;
                margin-right: 15px;
                flex-shrink: 0;
            }
            .mobile-card-content { flex-grow: 1; }
            .mobile-card-title { font-weight: 700; font-size: 15px; color: #1a1a1a; margin-bottom: 2px; }
            .mobile-card-subtitle { font-size: 12px; color: #6c757d; font-weight: 500; display: flex; align-items: center; }
            .mobile-card-subtitle i { font-size: 14px; margin-right: 4px; }
            
            .mobile-card-value { text-align: right; }
            .mobile-card-amt { font-weight: 800; font-size: 14px; display: block; }
            .mobile-card-type { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
            
            /* Modern FAB */
            .fab-button {
                position: fixed;
                bottom: 30px;
                right: 25px;
                width: auto;
                min-width: 140px;
                height: 52px;
                background: #135bec;
                color: white;
                border-radius: 26px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8px 16px rgba(19, 91, 236, 0.3);
                z-index: 1000;
                border: none;
                font-weight: 700;
                gap: 10px;
                padding: 0 25px;
            }
            .fab-button:active { transform: translateY(2px); box-shadow: 0 4px 8px rgba(19, 91, 236, 0.3); }
            
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

<script src="views/cari/js/cari.js?v=<?php echo time(); ?>"></script>
