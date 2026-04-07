<?php
use App\Helper\Form;
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="modal fade" id="sayacZimmetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.06), rgba(245, 158, 11, 0.02));">
                <div class="d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bx bx-user-check text-warning fs-4"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Sayaç Zimmet Ver</h6>
                        <small class="text-muted" style="font-size: 0.7rem;">Personele sayaç zimmetleyin.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sayacZimmetForm">
                <input type="hidden" name="zimmet_id" value="0">
                <div class="modal-body p-4">
                    <div class="alert alert-soft-warning border-0 d-flex align-items-center mb-4">
                        <i class="bx bx-info-circle me-3 text-warning fs-5"></i>
                        <div class="text-dark small">Personele sayaç zimmetlemek için formu doldurun.</div>
                    </div>

                    <!-- Çoklu Zimmet Modu -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sayacKoliModuToggle">
                                <label class="form-check-label fw-bold" for="sayacKoliModuToggle">
                                    <i class="bx bx-list-ol me-1"></i>Çoklu / Seri Zimmet
                                </label>
                            </div>
                            <div id="sayacKoliTipiSecimi" class="d-none">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="sayac_koli_tipi" id="sayacKoliTipTen" value="10" checked>
                                    <label class="form-check-label small fw-bold text-muted" for="sayacKoliTipTen">10'lu Koli</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="sayac_koli_tipi" id="sayacKoliTipOne" value="1">
                                    <label class="form-check-label small fw-bold text-muted" for="sayacKoliTipOne">Tekil Seri</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Standart Seçim (Tekil) -->
                    <div id="sayacTekliSecim" class="mb-3">
                        <?php echo Form::FormSelect2('demirbas_id', [], null, 'Sayaç Seçin *', 'package', '', '', 'form-select select2'); ?>
                    </div>

                    <!-- Tablodan Toplu Seçim Bilgisi (Gizli, JS ile kontrol edilir) -->
                    <div id="sayacTopluTabloSecim" class="d-none mb-3">
                        <div class="alert alert-info py-2 mb-0 d-flex align-items-center">
                            <i class="bx bx-check-double fs-4 me-2"></i>
                            <div>
                                <strong id="sayacTopluTabloAdetText">0</strong> sayaç tablodan seçildi ve zimmetlenecek.
                            </div>
                        </div>
                        <input type="hidden" name="is_toplu_secim" id="sayac_is_toplu_secim" value="0">
                        <input type="hidden" name="secilen_ids" id="sayac_secilen_ids" value="">
                    </div>

                    <!-- Koli Seçimi -->
                    <div id="sayacKoliSecim" class="d-none mb-3">
                        <div class="input-group mb-2">
                            <div class="form-floating form-floating-custom flex-grow-1">
                                <input type="text" class="form-control" id="sayac_koli_baslangic_seri" placeholder="Başlangıç Seri No">
                                <label for="sayac_koli_baslangic_seri">Başlangıç Seri No</label>
                                <div class="form-floating-icon"><i data-feather="menu"></i></div>
                            </div>
                            <button class="btn btn-primary" type="button" id="btnSayacKoliEkle"><i class="bx bx-plus"></i> Ekle</button>
                            <label class="btn btn-success mb-0 d-flex align-items-center" for="sayacKoliExcelFile" style="cursor:pointer;">
                                <i class="bx bx-upload me-1"></i> Excel Yükle
                                <input type="file" id="sayacKoliExcelFile" class="d-none" accept=".xlsx,.xls">
                            </label>
                        </div>
                        <div class="form-text mb-2">Birden fazla eklemek için virgülle ayırabilirsiniz.</div>

                        <div id="sayacEklenenKoliler" class="list-group list-group-flush border rounded mb-2 d-none" style="max-height:250px;overflow-y:auto;"></div>
                        <div id="sayacToplamKoliBilgisi" class="alert alert-info py-2 d-none">
                            <div class="d-flex justify-content-between">
                                <span>Toplam Koli: <strong id="sayacLblKoli">0</strong></span>
                                <span>Toplam Sayaç: <strong id="sayacLblSayac">0</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormSelect2('personel_id', [], null, 'Personel Seçin *', 'users'); ?>
                        </div>
                    </div>

                    <div class="row" id="sayacTeslimMiktarRow">
                        <div class="col-md-6 mb-3">
                            <div class="input-group">
                                <?php echo Form::FormFloatInput('number', 'teslim_miktar', '1', null, 'Teslim Edilecek Miktar *', 'hash', 'form-control', false, null, 'on', false, 'min="1"'); ?>
                                <span class="input-group-text">Kalan: <span id="sayacKalanMiktar" class="ms-1 fw-bold">-</span></span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('text', 'teslim_tarihi', date('d.m.Y'), null, 'Teslim Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatTextarea('aciklama', null, 'Zimmet ile ilgili notlar...', 'Açıklama', 'file-text'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="sayacZimmetKaydet" class="btn btn-warning">
                        <i class="bx bx-check-square me-1"></i>Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
