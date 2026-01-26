<!-- KM Kaydı Modal -->
<div class="modal fade" id="kmModal" tabindex="-1" aria-labelledby="kmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info bg-gradient text-white">
                <h5 class="modal-title" id="kmModalLabel"><i data-feather="activity" class="me-2"></i>KM Kaydı Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="kmForm">
                    <input type="hidden" name="id" value="">
                    <?php
                    // Araç haritası oluştur (JS için)
                    $kmAracMap = [];
                    foreach ($araclar as $arac) {
                        $arac->display_name = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                        $kmAracMap[$arac->id] = $arac->guncel_km;
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
                                'km_baslangic_km',
                                '',
                                'Gün başı KM',
                                'Başlangıç KM *',
                                'chevrons-right',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'min="0"'
                            ); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'number',
                                'km_bitis_km',
                                '',
                                'Gün sonu KM',
                                'Bitiş KM *',
                                'chevrons-left',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'min="0"'
                            ); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatInput(
                            'text',
                            'yapilan_km',
                            '',
                            'Otomatik hesaplanır',
                            'Yapılan KM',
                            'activity',
                            'form-control',
                            false,
                            null,
                            'on',
                            true,
                            'disabled'
                        ); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatTextarea(
                            'notlar',
                            '',
                            'Gün içi rotalar, notlar...',
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
                <button type="button" class="btn btn-info text-white" id="btnKmKaydet">
                    <i data-feather="save" class="me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var kmAracMap = <?php echo json_encode($kmAracMap); ?>;

    // Araç seçildiğinde KM'yi otomatik doldur
    $(document).on('change', '#kmModal #arac_id', function () {
        const aracId = $(this).val();
        if (aracId && kmAracMap[aracId]) {
            $('#kmModal #km_baslangic_km').val(kmAracMap[aracId]);
        }
    });

    // Yapılan KM otomatik hesaplama
    $(document).on('input', '#kmModal #km_baslangic_km, #kmModal #km_bitis_km', function () {
        const baslangic = parseInt($('#kmModal #km_baslangic_km').val()) || 0;
        const bitis = parseInt($('#kmModal #km_bitis_km').val()) || 0;
        const yapilan = bitis - baslangic;
        console.log(baslangic);
        $('#yapilan_km').val(yapilan > 0 ? yapilan + ' km' : '0 km');
    });
</script>