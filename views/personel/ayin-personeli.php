<?php
/**
 * Ayın Personeli Yönetim Sayfası
 */
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\Gate;
use App\Model\AyinPersoneliModel;
use App\Helper\Helper;

if (!Gate::allows("personel_listesi")) {
    die("Yetkisiz erişim!");
}

$model = new AyinPersoneliModel();
$donem = date('Y-m');
$hediyeler = $model->getHediyeler();
$hallOfFame = $model->getHallOfFame($_SESSION['firma_id'] ?? 0);
$winner = $model->getWinnerForMonth($donem, $_SESSION['firma_id'] ?? 0);

?>

<div class="container-fluid">
    <?php
    $maintitle = "Personel";
    $subtitle = "Etkinlikler";
    $title = "Ayın Personeli";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row mb-4">
        <div class="col-xl-8">
            <!-- Üst Bilgi Kartı -->
            <div class="card border-0 shadow-sm mb-4 overflow-hidden" 
                 style="border-radius: 15px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff;">
                <div class="card-body p-4 position-relative">
                    <div style="position: absolute; top: -20px; right: -20px; opacity: 0.1;">
                        <i class="bx bxs-award" style="font-size: 200px; color: #f1b44c;"></i>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h2 class="fw-bold mb-2" style="font-family: 'Outfit', sans-serif;">Ayın Yıldızını Seçelim! 🌟</h2>
                            <p class="text-white-50 mb-4">Personel performans verilerine göre bu ayın en başarılı isimlerini aşağıda görebilir, kazananı belirleyerek onu ödüllendirebilirsin.</p>
                            <div class="d-flex gap-3">
                                <div class="bg-dark bg-opacity-25 p-3 rounded-3 border border-white border-opacity-10">
                                    <div class="small text-white-50">Dönem</div>
                                    <div class="fw-bold"><?php echo date('F Y'); ?></div>
                                </div>
                                <div class="bg-dark bg-opacity-25 p-3 rounded-3 border border-white border-opacity-10">
                                    <div class="small text-white-50">Kupon / Ödül</div>
                                    <div class="fw-bold"><?php echo count($hediyeler); ?> Aktif Seçenek</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 d-none d-md-block text-center">
                            <?php if ($winner): ?>
                                <div class="winner-preview p-3 rounded-4" style="background: rgba(237, 177, 68, 0.1); border: 2px dashed #edb144;">
                                    <img src="<?php echo !empty($winner->resim_yolu) ? $winner->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>" 
                                         class="rounded-circle mb-2" style="width: 80px; height: 80px; border: 3px solid #edb144;">
                                    <h6 class="mb-0 text-warning"><?php echo $winner->adi_soyadi; ?></h6>
                                    <small class="text-white-50">Bu ayın kazananı!</small>
                                </div>
                            <?php else: ?>
                                <div class="p-4 rounded-4" style="background: rgba(255,255,255,0.05); border: 2px dashed rgba(255,255,255,0.1);">
                                    <i class="bx bx-help-circle fs-1 mb-2 text-white-50"></i>
                                    <p class="small mb-0 text-white-50">Henüz seçim yapılmadı.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adaylar Listesi -->
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bx bx-crown text-warning me-2"></i> En Güçlü Adaylar</h5>
                    <p class="text-muted small">Sistem tarafından hesaplanan en yüksek performans skorlu personeller.</p>
                </div>
                <div class="card-body p-4">
                    <div id="candidates-container">
                        <!-- AJAX ile yüklenecek -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Adaylar hesaplanıyor...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <!-- Hediye Seçimi -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bx bx-gift text-danger me-2"></i> Hediye Belirle</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <?php foreach($hediyeler as $h): ?>
                            <div class="col-6">
                                <label class="gift-option-card w-100 mb-0">
                                    <input type="radio" name="selected_gift" value="<?php echo $h->id; ?>" class="d-none">
                                    <div class="p-3 text-center rounded-3 border h-100 transition-all cursor-pointer">
                                        <div class="icon-circle mb-2 mx-auto" style="background: <?php echo $h->renk; ?>20; color: <?php echo $h->renk; ?>;">
                                            <i class="bx <?php echo $h->icon; ?> fs-4"></i>
                                        </div>
                                        <h6 class="small fw-bold mb-1"><?php echo $h->baslik; ?></h6>
                                        <p class="x-small text-muted mb-0" style="font-size: 0.65rem;"><?php echo $h->aciklama; ?></p>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <label class="form-label small fw-bold">Tebrik Mesajı</label>
                        <textarea id="winner_message" class="form-control form-control-sm" rows="3" placeholder="Harika bir iş çıkardın! Başarılarının devamını dileriz."></textarea>
                    </div>

                    <button id="btn-save-winner" class="btn btn-primary w-100 mt-4 py-2 fw-bold" disabled>
                        <i class="bx bx-party me-2"></i> Yıldızı Taçlandır!
                    </button>
                </div>
            </div>

            <!-- Geçmiş Kazananlar -->
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bx bx-history text-muted me-2"></i> Onur Köşesi</h5>
                </div>
                <div class="card-body p-4">
                    <div class="hall-of-fame-list">
                        <?php if (empty($hallOfFame)): ?>
                            <p class="text-muted small text-center">Henüz geçmiş kayıt bulunmuyor.</p>
                        <?php else: ?>
                            <?php foreach($hallOfFame as $hof): ?>
                                <div class="d-flex align-items-center mb-3 p-2 rounded-3 bg-light bg-opacity-50">
                                    <img src="<?php echo !empty($hof->resim_yolu) ? $hof->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>" 
                                         class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <h6 class="small fw-bold mb-0 text-truncate"><?php echo $hof->adi_soyadi; ?></h6>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('F Y', strtotime($hof->donem . '-01')); ?></small>
                                    </div>
                                    <span class="badge bg-soft-warning text-warning"><i class="bx bxs-star"></i></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .candidate-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent !important;
        cursor: pointer;
    }
    .candidate-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important;
        border-color: rgba(85, 110, 230, 0.2) !important;
    }
    .candidate-card.selected {
        border-color: #556ee6 !important;
        background: rgba(85, 110, 230, 0.02);
    }
    .candidate-card.selected .selection-check {
        display: flex !important;
    }
    
    .gift-option-card input:checked + div {
        border-color: #f46a6a !important;
        background: rgba(244, 106, 106, 0.05);
        box-shadow: 0 5px 15px rgba(244, 106, 106, 0.1);
    }
    
    .icon-circle {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .transition-all { transition: all 0.2s ease; }
    .cursor-pointer { cursor: pointer; }
    .x-small { font-size: 0.75rem; }
</style>

<script>
$(document).ready(function() {
    let selectedPersonelId = null;
    let selectedGiftId = null;

    // Adayları yükle
    function loadCandidates() {
        $('#candidates-container').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
        
        $.ajax({
            url: 'views/personel/api/ayin-personeli-api.php',
            type: 'GET',
            data: { action: 'get-candidates', donem: '<?php echo $donem; ?>' },
            success: function(res) {
                if (res.status === 'success') {
                    let html = '<div class="row g-4">';
                    res.data.forEach((p, idx) => {
                        let rankClass = idx === 0 ? 'border-warning shadow-sm' : '';
                        let scorePercent = Math.min(100, (p.bilet_skoru * 10 + p.gorev_skoru * 5 + p.devam_skoru));
                        
                        html += `
                            <div class="col-md-6">
                                <div class="card candidate-card h-100 border shadow-none" data-id="${p.id}">
                                    <div class="card-body p-3">
                                        <div class="selection-check position-absolute" style="top:-10px; right: -10px; display: none; background: #556ee6; color: #fff; width: 25px; height: 25px; border-radius: 50%; align-items: center; justify-content: center; border: 3px solid #fff;">
                                            <i class="bx bx-check"></i>
                                        </div>
                                        <div class="d-flex align-items-center mb-3">
                                            <img src="${p.resim_yolu || 'assets/images/users/user-dummy-img.jpg'}" 
                                                 class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                            <div class="overflow-hidden">
                                                <h6 class="fw-bold mb-0 text-truncate">${p.adi_soyadi}</h6>
                                                <small class="text-muted">${p.departman || 'Departman Belirtilmedi'}</small>
                                            </div>
                                        </div>
                                        <div class="stats-grid d-flex gap-2 mb-3">
                                            <div class="flex-grow-1 bg-light p-2 rounded text-center">
                                                <div class="x-small text-muted">Bilet</div>
                                                <div class="fw-bold">${p.bilet_skoru}</div>
                                            </div>
                                            <div class="flex-grow-1 bg-light p-2 rounded text-center">
                                                <div class="x-small text-muted">Görev</div>
                                                <div class="fw-bold">${p.gorev_skoru}</div>
                                            </div>
                                            <div class="flex-grow-1 bg-light p-2 rounded text-center">
                                                <div class="x-small text-muted">Devam</div>
                                                <div class="fw-bold">${p.devam_skoru}</div>
                                            </div>
                                        </div>
                                        <div class="mb-0">
                                            <div class="d-flex justify-content-between x-small mb-1">
                                                <span>Skor</span>
                                                <span class="fw-bold">%${Math.round(scorePercent)}</span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: ${scorePercent}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#candidates-container').html(html);
                }
            }
        });
    }

    loadCandidates();

    // Personel seçimi
    $(document).on('click', '.candidate-card', function() {
        $('.candidate-card').removeClass('selected');
        $(this).addClass('selected');
        selectedPersonelId = $(this).data('id');
        checkForm();
    });

    // Hediye seçimi
    $('input[name="selected_gift"]').on('change', function() {
        selectedGiftId = $(this).val();
        checkForm();
    });

    function checkForm() {
        if (selectedPersonelId && selectedGiftId) {
            $('#btn-save-winner').prop('disabled', false);
        } else {
            $('#btn-save-winner').prop('disabled', true);
        }
    }

    // Seçimi kaydet
    $('#btn-save-winner').on('click', function() {
        const btn = $(this);
        const originalText = btn.html();
        
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Seçtiğiniz personeli ayın yıldızı olarak ilan edeceksiniz!",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, İlan Et!',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#556ee6'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.html('<i class="bx bx-loader bx-spin me-2"></i> İlan ediliyor...').prop('disabled', true);
                
                $.ajax({
                    url: 'views/personel/api/ayin-personeli-api.php',
                    type: 'POST',
                    data: {
                        action: 'save-winner',
                        personel_id: selectedPersonelId,
                        hediye_id: selectedGiftId,
                        mesaj: $('#winner_message').val(),
                        donem: '<?php echo $donem; ?>'
                    },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'Tebrikler! 🎉',
                                text: 'Ayın Yıldızı başarıyla seçildi ve duyuruldu.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                            btn.html(originalText).prop('disabled', false);
                        }
                    }
                });
            }
        });
    });
});
</script>
