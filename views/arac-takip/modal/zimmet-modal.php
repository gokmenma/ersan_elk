<!-- Zimmet Modal -->
<div class="modal fade" id="zimmetModal" tabindex="-1" aria-labelledby="zimmetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning bg-gradient text-dark">
                <h5 class="modal-title" id="zimmetModalLabel"><i data-feather="repeat" class="me-2"></i>Araç Zimmet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="zimmetForm">
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

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormSelect2(
                            'arac_id',
                            $bostaAraclar,
                            '',
                            'Araç Seçin *',
                            'truck',
                            'id',
                            'display_name',
                            'form-select select2',
                            true
                        ); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormSelect2(
                            'personel_id',
                            $personeller,
                            '',
                            'Personel Seçin *',
                            'user',
                            'id',
                            'adi_soyadi',
                            'form-select select2',
                            true
                        ); ?>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'date',
                                'zimmet_tarihi',
                                date('Y-m-d'),
                                '',
                                'Zimmet Tarihi *',
                                'calendar',
                                'form-control',
                                true
                            ); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput(
                                'number',
                                'teslim_km',
                                '',
                                '0',
                                'Teslim KM',
                                'activity',
                                'form-control',
                                false,
                                null,
                                'on',
                                false,
                                'min="0"'
                            ); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatTextarea(
                            'notlar',
                            '',
                            'Zimmet notları...',
                            'Notlar',
                            'file-text',
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
                <button type="button" class="btn btn-warning" id="btnZimmetKaydet">
                    <i data-feather="repeat" class="me-1"></i> Zimmet Ver
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var aracKmMap = <?php echo json_encode($aracKmMap); ?>;

    // Araç seçildiğinde KM'yi otomatik doldur
    $(document).on('change', '#zimmetModal #arac_id', function () {
        const aracId = $(this).val();
        if (aracId && aracKmMap[aracId]) {
            $('#zimmetModal #teslim_km').val(aracKmMap[aracId]);
        }
    });
</script>