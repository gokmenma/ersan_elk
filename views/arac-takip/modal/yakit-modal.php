<!-- Yakıt Kaydı Modal -->
<div class="modal fade" id="yakitModal" tabindex="-1" aria-labelledby="yakitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success bg-gradient text-white">
                <h5 class="modal-title" id="yakitModalLabel"><i data-feather="droplet" class="me-2"></i>Yakıt Kaydı Ekle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="yakitForm">
                    <input type="hidden" name="id" value="">
                    <?php
                    // Araç haritası oluştur (JS için)
                    $aracMap = [];
                    foreach ($araclar as $arac) {
                        $arac->display_name = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                        $aracMap[$arac->id] = [
                            'km' => $arac->guncel_km,
                            'yakit_tipi' => $arac->yakit_tipi
                        ];
                    }
                    ?>

                    <div class="row">
                        <div class="col-8 mb-3">
                            <?php echo \App\Helper\Form::FormSelect2(
                                'arac_id',
                                $araclar,
                                '',
                                'Araç Seçin *',
                                'truck',
                                'id',
                                'display_name',
                                'form-select select2',
                                true
                            ); ?>
                        </div>
                        <div class="col-4 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'date',
                                'tarih',
                                date('Y-m-d'),
                                '',
                                'Tarih *',
                                'calendar',
                                'form-control',
                                true
                            ); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'number',
                                'km',
                                '',
                                'Güncel kilometre',
                                'Güncel KM *',
                                'activity',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'min="0"'
                            ); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <?php
                            $yakitTipleri = [
                                'dizel' => 'Dizel',
                                'benzin' => 'Benzin',
                                'lpg' => 'LPG',
                                'elektrik' => 'Elektrik'
                            ];
                            echo \App\Helper\Form::FormSelect2(
                                'yakit_tipi',
                                $yakitTipleri,
                                '',
                                'Yakıt Tipi',
                                'droplet'
                            );
                            ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-4 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'number',
                                'yakit_miktari',
                                '',
                                '0.00',
                                'Miktar (L) *',
                                'droplet',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'step="0.01" min="0"'
                            ); ?>
                        </div>
                        <div class="col-4 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'number',
                                'birim_fiyat',
                                '',
                                '0.00',
                                'Birim Fiyat (₺)',
                                'dollar-sign',
                                'form-control',
                                false,
                                null,
                                'on',
                                false,
                                'step="0.01" min="0"'
                            ); ?>
                        </div>
                        <div class="col-4 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'number',
                                'toplam_tutar',
                                '',
                                '0.00',
                                'Toplam Tutar (₺) *',
                                'credit-card',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'step="0.01" min="0"'
                            ); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'text',
                                'istasyon',
                                '',
                                'Akaryakıt istasyonu adı',
                                'İstasyon',
                                'map-pin'
                            ); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'text',
                                'fatura_no',
                                '',
                                'Fiş numarası',
                                'Fiş/Fatura No',
                                'file-text'
                            ); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="tam_depo_mu" value="1" id="tamDepo">
                            <label class="form-check-label" for="tamDepo">Tam Depo</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatTextarea(
                            'notlar',
                            '',
                            'Ek notlar...',
                            'Notlar',
                            'edit-3',
                            'form-control',
                            false,
                            '100px',
                            2
                        ); ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" id="btnYakitKaydet">
                    <i data-feather="save" class="me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var yakitAracMap = <?php echo json_encode($aracMap); ?>;

    // Araç seçildiğinde KM ve yakıt tipini otomatik doldur
    $(document).on('change', '#yakitModal #arac_id', function () {
        const aracId = $(this).val();
        if (aracId && yakitAracMap[aracId]) {
            const data = yakitAracMap[aracId];
            if (data.km) $('#yakitModal #km').val(data.km);
            if (data.yakit_tipi) $('#yakitModal #yakit_tipi').val(data.yakit_tipi).trigger('change');
        }
    });

    // Yakıt hesaplama mantığı
    $(document).on("input", "#yakit_miktari, #birim_fiyat, #toplam_tutar", function (e) {
        const targetId = e.target.id;
        const miktar = parseFloat($("#yakit_miktari").val()) || 0;
        const birimFiyat = parseFloat($("#birim_fiyat").val()) || 0;
        const toplamTutar = parseFloat($("#toplam_tutar").val()) || 0;

        if (targetId === "yakit_miktari" || targetId === "birim_fiyat") {
            // Miktar veya Birim Fiyat değişince Toplam'ı hesapla
            if (miktar > 0 && birimFiyat > 0) {
                $("#toplam_tutar").val((miktar * birimFiyat).toFixed(2));
            }
        } else if (targetId === "toplam_tutar") {
            // Toplam değişince Birim Fiyat'ı hesapla
            if (miktar > 0 && toplamTutar > 0) {
                $("#birim_fiyat").val((toplamTutar / miktar).toFixed(2));
            }
        }
    });
</script>