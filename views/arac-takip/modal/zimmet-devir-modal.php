<div class="modal fade" id="geciciTeslimModal" tabindex="-1" aria-labelledby="geciciTeslimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header premium-modal-header modal-header-info">
                <div class="modal-title-section">
                    <div class="modal-icon-box">
                        <i class="bx bx-transfer-alt"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title" id="geciciTeslimModalLabel">Geçici Teslim</h5>
                        <p class="modal-subtitle" id="gecici-teslim-plaka">Araç bilgisi</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light bg-opacity-50">
                <form id="geciciTeslimForm">
                    <input type="hidden" name="gecici_zimmet_id" id="gecici_teslim_zimmet_id">

                    <div class="row g-3">
                        <div class="col-12 d-none" id="gecici-zimmet-sec-wrap">
                            <?php echo \App\Helper\Form::FormSelect2('gecici_zimmet_sec', [], '', 'Zimmetli Araç *', 'truck', 'id', 'text', 'form-select select2', false); ?>
                        </div>

                        <div class="col-12" id="gecici-teslim-bilgi">
                            <div class="alert alert-soft-info d-flex align-items-start mb-0" role="alert">
                                <i class="bx bx-info-circle fs-4 me-2"></i>
                                <div class="small">
                                    <div>Asıl zimmet sahibi: <strong id="gecici-teslim-asil">-</strong></div>
                                    <div>Şu an kullanan: <strong id="gecici-teslim-veren">-</strong></div>
                                    <div class="text-muted mt-1">Geçici teslim asıl zimmet kaydını değiştirmez; yalnızca personeller arası teslim hareketi kaydedilir.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <?php echo \App\Helper\Form::FormSelect2('alan_personel_id', $personeller, '', 'Teslim Alacak Personel *', 'user', 'id', 'adi_soyadi', 'form-select select2', true, 'width:100%', '', 'gecici_teslim_alan_personel_id'); ?>
                        </div>

                        <div class="col-md-4">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'gecici_teslim_tarihi', date('d.m.Y H:i'), '', 'Teslim Tarihi / Saati *', 'calendar', 'form-control', true, null, 'off'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'gecici_planlanan_iade', '', '', 'Planlanan Geri Alma', 'calendar', 'form-control', false, null, 'off'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo \App\Helper\Form::FormFloatInput('number', 'gecici_teslim_km', '', 'Teslim anındaki KM', 'Teslim KM', 'activity', 'form-control', false, null, 'off'); ?>
                        </div>

                        <div class="col-12">
                            <?php echo \App\Helper\Form::FormFloatTextarea('gecici_aciklama', '', 'Teslim nedeni, araç durumu vb.', 'Açıklama', 'edit-3', 'form-control', false, '80px', 3); ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer bg-white p-3 border-top">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-info text-white px-4 shadow-sm" id="btnGeciciTeslimKaydet">
                    <i class="bx bx-check me-1"></i> Teslim Et
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="geciciTeslimIadeModal" tabindex="-1" aria-labelledby="geciciTeslimIadeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header premium-modal-header modal-header-warning">
                <div class="modal-title-section">
                    <div class="modal-icon-box">
                        <i class="bx bx-undo"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title" id="geciciTeslimIadeModalLabel">Geçici Teslimi Geri Al</h5>
                        <p class="modal-subtitle" id="gecici-iade-bilgi">Araç bilgisi</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light bg-opacity-50">
                <form id="geciciTeslimIadeForm">
                    <input type="hidden" name="gecici_devir_id" id="gecici_iade_devir_id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'gecici_iade_tarihi', date('d.m.Y H:i'), '', 'Geri Alma Tarihi *', 'calendar', 'form-control', true, null, 'off'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('number', 'gecici_iade_km', '', 'Güncel KM', 'Geri Alma KM', 'activity', 'form-control', false, null, 'off'); ?>
                        </div>
                        <div class="col-12">
                            <?php echo \App\Helper\Form::FormFloatTextarea('gecici_iade_notu', '', 'Araç durumu, eksik/hasar vb.', 'Not', 'edit-3', 'form-control', false, '80px', 3); ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer bg-white p-3 border-top">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-warning px-4 shadow-sm" id="btnGeciciTeslimIadeKaydet">
                    <i class="bx bx-check me-1"></i> Geri Al
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="zimmetDevirGecmisiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header premium-modal-header modal-header-primary">
                <div class="modal-title-section">
                    <div class="modal-icon-box">
                        <i class="bx bx-list-ul"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title">Teslim Hareketleri <span id="devirGecmisiPlaka" class="text-primary"></span></h5>
                        <p class="modal-subtitle" id="devirGecmisiAsil"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Teslim Eden</th>
                                <th>Teslim Alan</th>
                                <th class="text-center">Teslim</th>
                                <th class="text-center">Planlanan</th>
                                <th class="text-center">Geri Alma</th>
                                <th class="text-center">Teslim KM</th>
                                <th class="text-center">Geri KM</th>
                                <th>Açıklama / Not</th>
                                <th class="text-center">İşlem Yapan</th>
                                <th class="text-center">Durum</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetDevirGecmisiBody">
                            <tr>
                                <td colspan="11" class="text-center p-4 text-muted">Yükleniyor...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
