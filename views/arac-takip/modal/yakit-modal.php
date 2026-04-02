<!-- Yakıt Kaydı Modal -->
<div class="modal fade" id="yakitModal" tabindex="-1" aria-labelledby="yakitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 12px;">
            <!-- Üst Header - Tam Genişlik -->
            <div class="modal-header bg-white p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i data-feather="droplet" class="text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-dark" id="yakitModalLabel">Yakıt Kaydı</h5>
                        <small class="text-muted">Araca ait yakıt alım detaylarını girin</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="row g-0 p-3 flex-grow-1 bg-light bg-opacity-50">
                <!-- Sol Resim Alanı -->
                <div class="col-md-4 d-none d-md-block">
                    <div class="modal-image-panel h-100 rounded-3 overflow-hidden position-relative v-yakit" style="min-height: 500px;">
                        <div class="modal-image-bg" style="background-image: url('assets/images/modals/yakit_modal.png'); opacity: 0.7;"></div>
                        <div class="modal-image-overlay"></div>
                        <div class="modal-image-content d-flex flex-column justify-content-center align-items-center h-100 position-relative p-1" style="z-index: 3;">
                             <div class="p-4 shadow-lg text-dark text-center d-flex flex-column justify-content-center align-items-center" style="border-radius: 12px; width: 98%; height: 98%; background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px);">
                                 <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                     <i data-feather="droplet" class="text-success" style="width: 40px; height: 40px;"></i>
                                 </div>
                                 <h3 class="fw-bold mb-3">Yakıt Harcaması</h3>
                                 <p class="text-muted px-3 mb-0" style="font-size: 1.1rem;">Yakıt maliyetlerini ve verimlilik verilerini detaylı olarak kayıt altına alın.</p>
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
                            <form id="yakitForm" class="pe-5">
                                <input type="hidden" name="id" value="">
                                <?php
                                // Araç haritası oluştur (JS için)
                                $aracMap = [];
                                foreach ($araclar as $arac) {
                                    $arac->display_name = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                    $aracMap[$arac->id] = ['km' => $arac->guncel_km, 'yakit_tipi' => $arac->yakit_tipi];
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
                                        <?php echo \App\Helper\Form::FormFloatInput('number', 'km', '', '', 'Güncel KM *', 'activity', 'form-control', true, null, 'on', false, 'min="0"'); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php
                                        $yakitTipleri = ['dizel' => 'Dizel', 'benzin' => 'Benzin', 'lpg' => 'LPG', 'elektrik' => 'Elektrik'];
                                        echo \App\Helper\Form::FormSelect2('yakit_tipi', $yakitTipleri, '', 'Yakıt Tipi', 'droplet');
                                        ?>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-3 border rounded bg-light bg-opacity-50">
                                            <div class="text-muted small mb-3 fw-semibold"><i data-feather="trending-up" class="me-1" style="width: 14px; height: 14px;"></i>Maliyet Hesaplama</div>
                                            <div class="row g-2">
                                                <div class="col-md-3">
                                                    <?php echo \App\Helper\Form::FormFloatInput('number', 'yakit_miktari', '', '', 'Miktar (L) *', 'droplet', 'form-control', true, null, 'on', false, 'step="0.01" min="0"'); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <?php echo \App\Helper\Form::FormFloatInput('number', 'birim_fiyat', '', '', 'Birim Fiyat', 'dollar-sign', 'form-control', false, null, 'on', false, 'step="0.01" min="0"'); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <?php echo \App\Helper\Form::FormFloatInput('number', 'brut_tutar', '', '', 'Brüt Tutar', 'dollar-sign', 'form-control', false, null, 'on', false, 'step="0.01" min="0"'); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <?php echo \App\Helper\Form::FormFloatInput('number', 'iskonto', '0', '', 'İskonto %', 'percent', 'form-control', false, null, 'on', false, 'step="0.01" min="0" max="100"'); ?>
                                                </div>
                                                <div class="col-md-12 mt-2">
                                                    <?php echo \App\Helper\Form::FormFloatInput('number', 'toplam_tutar', '', '', 'Net Tutar *', 'shopping-cart', 'form-control', true, null, 'on', false, 'step="0.01" min="0"'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('text', 'istasyon', '', '', 'İstasyon', 'map-pin'); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('text', 'fatura_no', '', '', 'Fiş/Fatura No', 'file-text'); ?>
                                    </div>

                                    <div class="col-12 my-2">
                                        <div class="form-check form-switch form-switch-lg">
                                            <input class="form-check-input" type="checkbox" name="tam_depo_mu" value="1" id="tamDepo">
                                            <label class="form-check-label fw-bold text-success" for="tamDepo">Tam Depo Dolduruldu</label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <?php echo \App\Helper\Form::FormFloatTextarea('notlar', '', '', 'Notlar', 'edit-3', 'form-control', false, '100px'); ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-white p-3 border-top">
                            <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="button" class="btn btn-dark px-4 shadow-sm" id="btnYakitKaydet">
                                <i data-feather="save" class="me-1"></i> Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var yakitAracMap = <?php echo json_encode($aracMap); ?>;

    // Araç seçildiğinde KM ve yakıt tipini otomatik doldur
    $(document).off('change', '#yakitModal #arac_id').on('change', '#yakitModal #arac_id', function () {
        // Eğer düzenleme modundaysak (id varsa) otomatik doldurma yapma
        if ($('#yakitModal input[name="id"]').val() !== "") return;

        const aracId = $(this).val();
        if (aracId && yakitAracMap[aracId]) {
            const data = yakitAracMap[aracId];
            if (data.km) $('#yakitModal #km').val(data.km);
            if (data.yakit_tipi) $('#yakitModal #yakit_tipi').val(data.yakit_tipi).trigger('change');
        }
    });

    // Yakıt hesaplama mantığı
    $(document).off("input", "#yakit_miktari, #birim_fiyat, #iskonto, #brut_tutar, #toplam_tutar").on("input", "#yakit_miktari, #birim_fiyat, #iskonto, #brut_tutar, #toplam_tutar", function (e) {
        const targetId = e.target.id;
        const miktar = parseFloat($("#yakitModal #yakit_miktari").val()) || 0;
        const birimFiyat = parseFloat($("#yakitModal #birim_fiyat").val()) || 0;
        const iskontoYuzde = parseFloat($("#yakitModal #iskonto").val()) || 0;
        const brutTutar = parseFloat($("#yakitModal #brut_tutar").val()) || 0;
        const netTutar = parseFloat($("#yakitModal #toplam_tutar").val()) || 0;

        if (targetId === "yakit_miktari" || targetId === "birim_fiyat") {
            if (miktar > 0 && birimFiyat > 0) {
                const brut = miktar * birimFiyat;
                const net = brut * (1 - (iskontoYuzde / 100));
                $("#yakitModal #brut_tutar").val(brut.toFixed(2));
                $("#yakitModal #toplam_tutar").val(net.toFixed(2));
            }
        } else if (targetId === "brut_tutar") {
            if (brutTutar > 0) {
                if (miktar > 0) $("#yakitModal #birim_fiyat").val((brutTutar / miktar).toFixed(2));
                const net = brutTutar * (1 - (iskontoYuzde / 100));
                $("#yakitModal #toplam_tutar").val(net.toFixed(2));
            }
        } else if (targetId === "iskonto") {
            const brut = miktar * birimFiyat || brutTutar;
            if (brut > 0) {
                const net = brut * (1 - (iskontoYuzde / 100));
                $("#yakitModal #toplam_tutar").val(net.toFixed(2));
            }
        } else if (targetId === "toplam_tutar") {
            const brut = miktar * birimFiyat || brutTutar;
            if (brut > 0 && netTutar > 0) {
                const iskonto = ((brut - netTutar) / brut) * 100;
                $("#yakitModal #iskonto").val(iskonto.toFixed(2));
            }
        }
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
        background: linear-gradient(180deg, rgba(52, 195, 143, 0.1) 0%, rgba(52, 195, 143, 0.4) 100%);
    }

    .modal.show .modal-image-overlay {
        opacity: 0.3;
    }
</style>