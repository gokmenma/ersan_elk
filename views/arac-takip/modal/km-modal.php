<!-- KM Kaydı Modal -->
<div class="modal fade" id="kmModal" tabindex="-1" aria-labelledby="kmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 12px;">
            <!-- Üst Header - Tam Genişlik -->
            <div class="modal-header bg-white p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i data-feather="activity" class="text-info"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-dark" id="kmModalLabel">Kilometre Kaydı</h5>
                        <small class="text-muted">Aracın günlük kilometre takibini yapın</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="row g-0 p-3 flex-grow-1 bg-light bg-opacity-50">
                <!-- Sol Resim Alanı -->
                <div class="col-md-4 d-none d-md-block">
                    <div class="modal-image-panel h-100 rounded-3 overflow-hidden position-relative v-km" style="min-height: 450px;">
                        <div class="modal-image-bg" style="background-image: url('assets/images/modals/km_modal.png'); opacity: 0.7;"></div>
                        <div class="modal-image-overlay"></div>
                        <div class="modal-image-content d-flex flex-column justify-content-center align-items-center h-100 position-relative p-1" style="z-index: 3;">
                             <div class="p-4 shadow-lg text-dark text-center d-flex flex-column justify-content-center align-items-center" style="border-radius: 12px; width: 98%; height: 98%; background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px);">
                                 <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                     <i data-feather="activity" class="text-info" style="width: 40px; height: 40px;"></i>
                                 </div>
                                 <h3 class="fw-bold mb-3">KM Takibi</h3>
                                 <p class="text-muted px-3 mb-0" style="font-size: 1.1rem;">Araç kullanım mesafelerini, rotaları ve periyodik verimlilik verilerini hassasiyetle izleyin.</p>
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
                        <div class="modal-body p-4 pt-4 flex-grow-1">
                            <form id="kmForm" class="pe-5">
                                <input type="hidden" name="id" value="">
                                <?php
                                // Araç haritası oluştur (JS için)
                                $maxKmList = $Km->getAllMaxBitisKm();
                                $kmAracMap = [];
                                foreach ($araclar as $arac) {
                                    $arac->display_name = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                    $kmAracMap[$arac->id] = isset($maxKmList[$arac->id]) ? $maxKmList[$arac->id] : ($arac->baslangic_km ?? 0);
                                }
                                ?>

                                <div class="row g-3">
                                    <div class="col-md-8 mb-2">
                                        <?php echo \App\Helper\Form::FormSelect2('arac_id', $araclar, '', 'Araç Seçin *', 'truck', 'id', 'display_name', 'form-select select2', true); ?>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <?php echo \App\Helper\Form::FormFloatInput('text', 'tarih', date('d.m.Y'), '', 'Tarih *', 'calendar', 'form-control flatpickr', true); ?>
                                    </div>

                                    <div class="col-md-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('number', 'baslangic_km', '', 'Gün başı KM', 'Başlangıç KM *', 'bx bx-chevrons-right', 'form-control', true, null, 'on', false, 'min="0"'); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('number', 'bitis_km', '', 'Gün sonu KM', 'Bitiş KM *', 'bx bx-chevrons-left', 'form-control', true, null, 'on', false, 'min="0"'); ?>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-3 border border-info border-opacity-25 rounded bg-info bg-opacity-10">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-info fw-bold"><i class="bx bx-run me-1"></i>Kalkış - Varış Mesafesi</div>
                                                <h4 class="mb-0 text-info fw-bold" id="yapilan_km_badge">0 km</h4>
                                                <input type="hidden" id="yapilan_km" value="">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-2">
                                        <?php echo \App\Helper\Form::FormFloatTextarea('notlar', '', 'Gün içi rotalar, notlar...', 'Gidilen Rota & Notlar', 'edit-3', 'form-control', false, '100px'); ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-white p-3 border-top">
                            <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="button" class="btn btn-dark text-white px-4 shadow-sm" id="btnKmKaydet">
                                <i class="bx bx-save me-1"></i> Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var kmAracMap = <?php echo json_encode($kmAracMap); ?>;

    function updateBaslangicKm() {
        // Eğer düzenleme modundaysak (id varsa) otomatik doldurma
        if ($('#kmForm input[name="id"]').val() !== "") return;

        const aracId = $('#kmModal #arac_id').val();
        if (aracId && kmAracMap.hasOwnProperty(aracId)) {
            $('#kmForm #baslangic_km').val(kmAracMap[aracId]);
            // Yapılan KM hesaplamasını tetikle
            $('#kmForm #baslangic_km').trigger('input');
        }
    }

    // Araç seçildiğinde KM'yi otomatik doldur
    $(document).off('change', '#kmModal #arac_id').on('change', '#kmModal #arac_id', function () {
        updateBaslangicKm();
    });

    // Modal açıldığında (tek araç varsa veya seçim yapılmışsa) KM'yi doldur
    $('#kmModal').off('shown.bs.modal').on('shown.bs.modal', function () {
        if ($('#kmForm input[name="id"]').val() === "") {
            updateBaslangicKm();
        }
    });

    // Yapılan KM otomatik hesaplama
    $(document).off('input', '#kmForm #baslangic_km, #kmForm #bitis_km').on('input', '#kmForm #baslangic_km, #kmForm #bitis_km', function () {
        const baslangic = parseInt($('#kmForm #baslangic_km').val()) || 0;
        const bitis = parseInt($('#kmForm #bitis_km').val()) || 0;
        const yapilan = bitis - baslangic;

        const yapilanBadge = $('#yapilan_km_badge');
        const bitisInput = $('#kmForm #bitis_km');
        const saveBtn = $('#btnKmKaydet');

        if (bitis > 0 && bitis < baslangic) {
            yapilanBadge.text('Hata!').addClass('text-danger').removeClass('text-info');
            bitisInput.addClass('is-invalid');
            saveBtn.prop('disabled', true);
        } else {
            yapilanBadge.text((yapilan >= 0 ? yapilan : 0) + ' km').removeClass('text-danger').addClass('text-info');
            bitisInput.removeClass('is-invalid');
            saveBtn.prop('disabled', false);
        }
        $('#yapilan_km').val(yapilan >= 0 ? yapilan : 0);
    });
</script>

<style>
    /* Modal Image Animation Effects */
    .modal-image-panel {
        background-color: #1a1d21;
    }

    .modal-image-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-size: cover;
        background-position: center;
        transition: transform 0.8s cubic-bezier(0.25, 0.8, 0.25, 1), filter 0.8s ease-out;
        filter: blur(10px) brightness(0.5);
        transform: scale(1.15);
    }

    .modal.show .modal-image-bg {
        filter: blur(0px) brightness(0.95);
        transform: scale(1);
    }

    .modal-image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        transition: opacity 0.8s ease;
        opacity: 0.8;
        background: linear-gradient(180deg, rgba(80, 165, 241, 0.1) 0%, rgba(80, 165, 241, 0.4) 100%);
    }

    .modal.show .modal-image-overlay {
        opacity: 0.3;
    }
</style>