<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Security;
use App\Model\CariModel;

$maintitle = 'Cari Yönetimi';
$title = 'Hesap Hareketleri';

$cari_id_enc = $_GET['id'] ?? '';
$cari_id = Security::decrypt($cari_id_enc);

$Cari = new CariModel();
$cariData = $Cari->find($cari_id);

if (!$cariData) {
    echo '<div class="alert alert-danger">Cari bulunamadı!</div>';
    exit;
}

// Cari Özet Bilgileri
// Cari Özet Bilgileri
$stmt = $Cari->getDb()->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
$stmt->execute(['cari_id' => $cari_id]);
$ozet = $stmt->fetch(PDO::FETCH_OBJ);
$toplam_borc = $ozet->toplam_borc ?? 0;
$toplam_alacak = $ozet->toplam_alacak ?? 0;
$bakiye = $ozet->bakiye ?? 0;
?>

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Cari Bilgi Çubuğu (Bordro Stili) -->
    <div class="card border-0 shadow-sm mb-4 bordro-info-bar"
        style="border-radius: 20px; background: rgba(19, 91, 236, 0.03); border: 1px solid rgba(19, 91, 236, 0.1) !important;">
        <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="bg-white rounded-3 shadow-sm p-2 me-3 d-flex align-items-center justify-content-center"
                    style="width: 45px; height: 45px;">
                    <i data-feather="user" class="text-primary"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold bordro-text-heading"><?php echo htmlspecialchars($cariData->CariAdi); ?></h5>
                    <small class="text-muted fw-medium">
                        <i data-feather="phone" class="me-1" style="width: 14px; height: 14px;"></i><?php echo htmlspecialchars($cariData->Telefon ?: 'Belirtilmemiş'); ?> 
                        <span class="mx-2">|</span> 
                        <i data-feather="mail" class="me-1" style="width: 14px; height: 14px;"></i><?php echo htmlspecialchars($cariData->Email ?: 'Belirtilmemiş'); ?>
                    </small>
                </div>
            </div>
            
        
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 d-none d-md-flex">
             <a href="index.php?p=cari/list" class="btn btn-link btn-sm text-secondary text-decoration-none px-2 d-flex align-items-center">
                    <i data-feather="arrow-left" class="me-1" style="width: 18px; height: 18px;"></i> <span class="d-none d-sm-inline">Listeye Dön</span>
                </a>   
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

            <button type="button" id="exportExcel" class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center">
                    <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel
                </button>
               
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

                <a href="views/cari/export-ekstre-pdf.php?id=<?= $cari_id_enc ?>" target="_blank" class="btn btn-link btn-sm text-danger text-decoration-none px-2 d-flex align-items-center">
                    <i class="mdi mdi-file-pdf-box fs-5 me-1"></i> PDF Ekstre
                </a>

                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

                <button type="button" onclick="editCariNoteDesktop()" class="btn btn-link btn-sm text-warning text-decoration-none px-2 d-flex align-items-center">
                    <i class="mdi mdi-note-edit-outline fs-5 me-1"></i> Cari Notu
                </button>
               
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                 <button type="button" class="btn  btn-outline-success btn-sm fw-semibold px-3 d-flex align-items-center" id="btnAldimDesktop">
                    <i data-feather="plus-circle" class="me-1" style="width: 16px; height: 16px;"></i> Tahsilat
                </button>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

                <button type="button" class="btn btn-outline-danger btn-sm fw-semibold px-3 d-flex align-items-center" id="btnVerdimDesktop">
                    <i data-feather="minus-circle" class="me-1" style="width: 16px; height: 16px;"></i> Ödeme
                </button>
            </div>
            
            <!-- Mobile actions wrapper (only visible on mobile to keep structure tidy) -->
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto d-md-none">
                <button type="button" class="btn btn-link btn-sm text-success text-decoration-none px-2" id="btnExportExcelMobileTop" title="Excel'e Aktar">
                    <i data-feather="printer"></i>
                </button>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                <a href="index.php?p=cari/list" class="btn btn-link btn-sm text-secondary text-decoration-none px-3 d-flex align-items-center">
                    <i data-feather="arrow-left" class="me-1" style="width: 18px; height: 18px;"></i>
                </a>
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
                            <i data-feather="trending-up" class="text-danger"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold d-none d-md-block" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM BORÇ</p>
                    <p class="text-danger mb-0 small fw-bold d-md-none" style="font-size: 10px;">TOPLAM BORÇ</p>
                    <h5 class="mb-0 fw-bold bordro-text-heading mt-md-0 mt-1">
                        <span id="toplam_borc_kart" style="font-size: 0.9rem;"><?php echo number_format($toplam_borc, 2, ',', '.'); ?></span> <span style="font-size: 0.7rem; font-weight: 600;">₺</span>
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
                            <i data-feather="trending-down" style="color: #2a9d8f;"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold d-none d-md-block" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM ALACAK</p>
                    <p class="text-success mb-0 small fw-bold d-md-none" style="font-size: 10px;">TOPLAM ALACAK</p>
                    <h5 class="mb-0 fw-bold bordro-text-heading mt-md-0 mt-1">
                        <span id="toplam_alacak_kart" style="font-size: 0.9rem;"><?php echo number_format($toplam_alacak, 2, ',', '.'); ?></span> <span style="font-size: 0.7rem; font-weight: 600;">₺</span>
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
                            <i data-feather="briefcase" id="bakiye_icon_color" class="<?php echo $bakiye < 0 ? 'text-danger' : 'text-success'; ?>"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-1 small fw-bold d-none d-md-block" style="letter-spacing: 0.5px; opacity: 0.7;">BAKİYE</p>
                    <p class="text-primary mb-0 small fw-bold d-md-none" id="mobile_bakiye_title" style="font-size: 10px; color: <?php echo $bakiye < 0 ? '#f43f5e' : '#2a9d8f'; ?> !important;"><?php echo $bakiye < 0 ? 'GÜNCEL BORÇ' : 'GÜNCEL ALACAK'; ?></p>
                    <h5 class="mb-0 fw-bold bordro-text-heading mt-md-0 mt-1 <?php echo $bakiye < 0 ? 'text-danger' : 'text-success'; ?>" id="bakiye_label_container">
                        <span id="genel_bakiye_kart" style="font-size: 0.9rem;"><?php echo number_format(abs($bakiye), 2, ',', '.'); ?></span> <span style="font-size: 0.7rem; font-weight: 600;">₺</span>
                        <small id="bakiye_status_text" style="font-size: 0.6rem; display: block;"><?php echo $bakiye < 0 ? '(Borç)' : ($bakiye > 0 ? '(Alacak)' : ''); ?></small>
                    </h5>
                </div>
            </div>
        </div>
    </div>

    <?php if($cariData->notlar): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 15px; background: #fffbeb; border: 1px solid #fef3c7 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <i data-feather="sticky-note" class="text-warning me-2" style="width: 18px;"></i>
                            <h6 class="mb-0 fw-bold text-warning-emphasis">Cari Notu</h6>
                        </div>
                        <button type="button" onclick="editCariNoteDesktop()" class="btn btn-sm btn-light-warning">
                            <i data-feather="edit-2" style="width: 14px;"></i> Düzenle
                        </button>
                    </div>
                    <p class="mb-0 text-muted small italic"><?= nl2br(htmlspecialchars($cariData->notlar)) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <style>
        /* Mobil Tasarım İyileştirmeleri - Desktop Uyumluluğu */
        @media (max-width: 767.98px) {
            /* Mobilde uygulamanın main footer'ını gizle */
            footer, .footer, #footer { display: none !important; }
            body { padding-bottom: 70px; } /* Alt butonlar için alan */
            
            .bordro-info-bar { border-radius: 10px !important; margin-bottom: 1rem !important; border: 1px solid #dee2e6 !important; background: #fff !important; }
            
            .hareket-desktop-table { display: none !important; }
            .hareket-mobile-list { display: block !important; padding: 0 5px; }
            
            /* Genel Bakiye Kartı - Desktop ile aynı minimal tarz */
            /* Genel Bakiye Kartı - Desktop ile aynı minimal tarz */
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
            
            .mobile-balance-card {
                background: #fff;
                border: 1px solid #e9ecef;
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                text-align: center;
            }
            .mobile-balance-card .label { color: #495057; font-weight: 600; font-size: 12px; margin-bottom: 5px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
            .mobile-balance-card .amount { font-size: 22px; font-weight: 700; color: #343a40; }
            
            /* Hızlı Aksiyonlar - Minimal Butonlar */
            .mobile-quick-actions { 
                display: flex; 
                justify-content: space-around; 
                margin-bottom: 25px; 
            }
            .quick-action-btn { 
                display: flex; 
                flex-direction: column; 
                align-items: center; 
                text-decoration: none; 
                color: #495057; 
                font-size: 11px; 
                font-weight: 500; 
                padding: 8px;
                background: transparent;
                border-radius: 8px;
                transition: background-color 0.2s;
            }
            .quick-action-btn:active { background: #f8f9fa; }
            .quick-action-icon { width: 35px; height: 35px; border-radius: 8px; background: rgba(19, 91, 236, 0.05); color: #135bec; display: flex; align-items: center; justify-content: center; margin-bottom: 5px; }

            /* Operasyon Listesi */
            .op-header { font-weight: 600; color: #343a40; font-size: 14px; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #e9ecef; }
            
            .op-card {
                background: #fff;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 8px 12px;
                margin-bottom: 6px;
                display: flex;
                align-items: center;
            }
            .op-icon {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 10px;
                flex-shrink: 0;
            }
            .op-icon i { font-size: 14px; }
            .op-icon.up { color: #dc3545; background: rgba(220, 53, 69, 0.1); }
            .op-icon.down { color: #198754; background: rgba(25, 135, 84, 0.1); }
            
            .op-info { flex-grow: 1; }
            .op-date { font-weight: 600; font-size: 11px; color: #495057; margin-bottom: 1px; }
            .op-desc { font-size: 10px; color: #6c757d; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 150px; }
            
            .op-value { text-align: right; }
            .op-amt { font-weight: 700; font-size: 12px; display: block; color: #212529; }
            .op-type { font-size: 8px; font-weight: 600; text-transform: uppercase; color: #adb5bd; }

            .btn-light-primary { background: #e0e7ff; color: #135bec; border: none; }
            .btn-light-danger { background: #fee2e2; color: #ef4444; border: none; }

            /* Sabit Alt Butonlar - Bootstrap Outline Buton Stili (Desktop-like) */
            .bottom-actions {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background: #fff;
                padding: 10px 15px;
                display: flex;
                gap: 10px;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
                z-index: 1000;
                border-top: 1px solid #e9ecef;
            }
            .btn-aldim { background: transparent; color: #198754; flex: 1; border: 1px solid #198754; font-weight: 600; height: 40px; border-radius: 6px; font-size: 12px; display: flex; align-items: center; justify-content: center; }
            .btn-aldim:active { background: #198754; color: #fff; }
            
            .btn-verdim { background: transparent; color: #dc3545; flex: 1; border: 1px solid #dc3545; font-weight: 600; height: 40px; border-radius: 6px; font-size: 12px; display: flex; align-items: center; justify-content: center; }
            .btn-verdim:active { background: #dc3545; color: #fff; }

            /* Flatpickr Time input fix */
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
        }
        @media (min-width: 768px) {
            .hareket-mobile-list { display: none !important; }
            .bottom-actions { display: none !important; }
            .container-fluid { padding-bottom: 20px; }
        }
    </style>

    <div class="row hareket-desktop-table">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="hareketTable" class="table table-hover table-bordered nowrap w-100 datatable-deferred">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="text-center" style="width: 100px;">Tarih</th>
                                <th style="width: 120px;">Belge No</th>
                                <th>Açıklama</th>
                                <th class="text-end" style="width: 150px;">Borç</th>
                                <th class="text-end" style="width: 150px;">Alacak</th>
                                <th class="text-end" style="width: 150px;">Yürüyen Bakiye</th>
                                <th class="text-center" style="width: 80px;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobil Hareket Görünümü -->
    <div class="hareket-mobile-list">

        <div class="mobile-quick-actions">
            <a href="#" class="quick-action-btn"><div class="quick-action-icon"><i data-feather="edit-2" style="width: 18px; height: 18px;"></i></div><span>Not</span></a>
            <a href="tel:<?php echo $cariData->Telefon; ?>" class="quick-action-btn"><div class="quick-action-icon"><i data-feather="phone" style="width: 18px; height: 18px;"></i></div><span>Ara</span></a>
            <a href="#" class="quick-action-btn"><div class="quick-action-icon"><i data-feather="share-2" style="width: 18px; height: 18px;"></i></div><span>Paylaş</span></a>
            <a href="#" class="quick-action-btn" id="btnExportExcelMobile"><div class="quick-action-icon"><i data-feather="file-text" style="width: 18px; height: 18px;"></i></div><span>Raporlar</span></a>
        </div>

        <div class="op-header">
            Kayıtlı İşlemler 
            <span class="float-end text-muted small fw-normal" id="op_count" style="margin-top: 2px;">(0)</span>
        </div>

        <div id="hareketMobileContainer">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm me-2 text-primary"></div>
                Veriler yükleniyor...
            </div>
        </div>
    </div>

    <!-- Sabit Alt Butonlar -->
    <div class="bottom-actions">
        <button class="btn-aldim" id="btnAldimMobile"><i data-feather="plus-circle" class="me-2" style="width: 16px;"></i>Tahsilat</button>
        <button class="btn-verdim" id="btnVerdimMobile"><i data-feather="minus-circle" class="me-2" style="width: 16px;"></i>Ödeme</button>
    </div>
</div>

<script>
    const global_cari_id = '<?php echo $cari_id_enc; ?>';

    async function editCariNoteDesktop() {
        const { value: text } = await Swal.fire({
            title: 'Cari Notu Düzenle',
            input: 'textarea',
            inputValue: <?= json_encode($cariData->notlar ?: '') ?>,
            showCancelButton: true,
            confirmButtonText: 'Kaydet',
            cancelButtonText: 'İptal',
        });

        if (text !== undefined) {
            $.post('views/cari/api.php', {
                action: 'cari-not-kaydet',
                cari_id: global_cari_id,
                notlar: text
            }, function(res) {
                if(res.status === 'success') {
                    location.reload();
                } else {
                    Swal.fire('Hata', res.message, 'error');
                }
            }, 'json');
        }
    }
</script>
<script src="views/cari/js/hareketler.js?v=<?php echo time(); ?>"></script>

<!-- Hızlı İşlem Modalı -->
<div class="modal fade" id="hizliIslemModal" tabindex="-1" aria-labelledby="hizliIslemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background-color: #e0e7ff;">
                        <!-- Icon will be injected by JS depending on type (aldi/verdi) -->
                        <div id="hizliIslemModalIcon"><i data-feather="plus-circle" style="width: 24px; height: 24px; color: #135bec;"></i></div>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="hizliIslemModalLabel" style="color: #1a1a1a;">Yeni İşlem</h5>
                        <p class="text-muted small mb-0" id="hizliIslemModalDesc">İşlem bilgilerini doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="hizliIslemForm">
                <input type="hidden" name="action" value="hizli-hareket-kaydet">
                <input type="hidden" name="cari_id" value="<?php echo $cari_id_enc; ?>">
                <input type="hidden" name="type" id="hizli_islem_type" value="">
                
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="mb-3">
                        <?php 
                        use App\Helper\Form;
                        echo Form::FormFloatInput("text", "islem_tarihi", "", "Tarih", "Tarih", "calendar", "form-control flatpickr-time-input", true, null, "off", false); 
                        ?>
                    </div>
                    <div class="mb-3">
                        <label id="hizli_islem_amt_label" class="form-label small fw-bold text-muted mb-1">Tutar</label>
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
