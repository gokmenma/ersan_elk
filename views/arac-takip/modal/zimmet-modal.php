<!-- Zimmet Modal -->
<div class="modal fade" id="zimmetModal" tabindex="-1" aria-labelledby="zimmetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 12px;">
            <!-- Üst Header - Tam Genişlik -->
            <div class="modal-header bg-white p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i data-feather="repeat" class="text-warning"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-dark" id="zimmetModalLabel">Araç Zimmetle</h5>
                        <small class="text-muted">Aracı bir personelin üzerine zimmetleyin</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="row g-0 p-3 flex-grow-1 bg-light bg-opacity-50">
                <!-- Sol Resim Alanı -->
                <div class="col-md-4 d-none d-md-block">
                    <div class="modal-image-panel h-100 rounded-3 overflow-hidden position-relative v-zimmet" style="min-height: 450px;">
                        <div class="modal-image-bg" style="background-image: url('assets/images/modals/zimmet_modal.png'); opacity: 0.7;"></div>
                        <div class="modal-image-overlay"></div>
                        <div class="modal-image-content d-flex flex-column justify-content-center align-items-center h-100 position-relative p-1" style="z-index: 3;">
                             <div class="p-4 shadow-lg text-dark text-center d-flex flex-column justify-content-center align-items-center" style="border-radius: 12px; width: 98%; height: 98%; background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px);">
                                 <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                     <i data-feather="repeat" class="text-warning" style="width: 40px; height: 40px;"></i>
                                 </div>
                                 <h3 class="fw-bold mb-3">Araç Zimmet</h3>
                                 <p class="text-muted px-3 mb-0" style="font-size: 1.1rem;">Araçları personellere zimmetleyerek sorumluluk takibini ve operasyonel verimliliği artırın.</p>
                             </div>
                             <div class="position-absolute bottom-0 w-100 text-center pb-4">
                                 <span class="text-white fs-6 fw-light opacity-50">Ersan Elektrik Filo</span>
                             </div>
                        </div>
                    </div>
                </div>
                <!-- Sağ Form Alanı -->
                <div class="col-md-8 ps-3">
                    <div class="d-flex flex-column shadow-sm bg-white rounded-3 overflow-hidden h-100">
                        <div class="modal-body p-4 pt-4">
                            <form id="zimmetForm" class="pe-5">
                                <?php
                                // Boştaki araçları hazırla
                                $bostaAraclar = [];
                                $aracKmMap = [];
                                foreach ($araclar as $arac) {
                                    if (empty($arac->zimmetli_personel_id)) {
                                        $arac->display_name = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                        $bostaAraclar[] = $arac;
                                        $aracKmMap[$arac->id] = $arac->guncel_km;
                                    }
                                }
                                ?>

                                <div class="p-4 border rounded bg-light bg-opacity-50 mb-4">
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <?php echo \App\Helper\Form::FormSelect2('arac_id', $bostaAraclar, '', 'Eşleşecek Araç *', 'truck', 'id', 'display_name', 'form-select select2', true); ?>
                                        </div>
                                        <div class="col-12">
                                            <?php echo \App\Helper\Form::FormSelect2('personel_id', $personeller, '', 'Zimmetlenecek Personel *', 'user', 'id', 'adi_soyadi', 'form-select select2', true); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('text', 'zimmet_tarihi', date('d.m.Y'), '', 'Zimmet Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('number', 'teslim_km', '', 'Aracın mevcut KM\'si', 'Teslim KM *', 'activity', 'form-control', true); ?>
                                    </div>
                                    <div class="col-12">
                                        <?php echo \App\Helper\Form::FormFloatTextarea('notlar', '', 'Aksesuar durumu, hasar kaydı vb.', 'Teslim Notları', 'edit-3', 'form-control', false, '100px'); ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-white p-3 border-top">
                            <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="button" class="btn btn-dark px-4 shadow-sm" id="btnZimmetKaydet">
                                <i class="bx bx-check me-1"></i> Zimmet Ver
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var aracKmMap = <?php echo json_encode($aracKmMap); ?>;

    // Araç seçildiğinde KM'yi otomatik doldur
    $(document).off('change', '#zimmetModal #arac_id').on('change', '#zimmetModal #arac_id', function () {
        const aracId = $(this).val();
        if (aracId && aracKmMap[aracId]) {
            $('#zimmetModal #teslim_km').val(aracKmMap[aracId]);
        }
    });
</script>

<style>
    /* Premium Image Panel Styling */
    .modal-image-panel {
        background-color: #1a1d21;
        overflow: hidden;
    }

    .v-zimmet .modal-image-overlay {
        background: linear-gradient(180deg, rgba(241, 180, 76, 0.2) 0%, rgba(20, 20, 30, 0.8) 100%);
    }

    .modal-image-bg {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-size: cover;
        background-position: center;
        transition: transform 1.2s cubic-bezier(0.25, 1, 0.5, 1), filter 1.2s ease;
        filter: blur(8px) brightness(0.6);
        transform: scale(1.15);
        z-index: 1;
    }

    .modal.show .modal-image-bg {
        filter: blur(0px) brightness(1);
        transform: scale(1);
    }

    .modal-image-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 2;
        opacity: 0.4;
        transition: opacity 1s ease;
    }

    .modal.show .modal-image-overlay {
        opacity: 0.25;
    }

    .modal-image-content {
        transition: all 1s ease;
        transform: translateY(20px);
        opacity: 0;
    }

    .modal.show .modal-image-content {
        transform: translateY(0);
        opacity: 1;
    }

    .modal-header .btn-close {
        background-color: rgba(0, 0, 0, 0.05);
        padding: 0.5rem;
        border-radius: 50%;
        opacity: 0.5;
    }
</style>