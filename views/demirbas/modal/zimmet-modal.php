<?php
use App\Helper\Form;
use App\Model\PersonelModel;
use App\Model\DemirbasModel;

// $Personel = new PersonelModel();
// $Demirbas = new DemirbasModel();

// $personeller = $Personel->all();
// $demirbaslar = $Demirbas->getInStock();

// Personel seçeneklerini hazırla
$personelOptions = [];
// foreach ($personeller as $p) {
//     $personelOptions[$p->id] = $p->adi_soyadi . ' - ' . ($p->cep_telefonu ?? '');
// }
?>

<style>
    /* Modern Segmented Control */
    .segmented-control {
        display: flex;
        width: 100%;
        background-color: #f1f5f9;
        border-radius: 0.5rem;
        padding: 0.25rem;
        position: relative;
        border: 1px solid #e2e8f0;
    }

    .segmented-control input[type="radio"] {
        display: none;
    }

    .segmented-control label {
        flex: 1;
        text-align: center;
        padding: 0.5rem 1rem;
        cursor: pointer;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        color: #64748b;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        user-select: none;
        margin-bottom: 0;
        line-height: 1.2;
    }

    .segmented-control input[type="radio"]:checked+label {
        background-color: #ffffff;
        color: #2563eb; /* Primary Blue */
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        font-weight: 600;
    }

    .segmented-control label:hover:not(:active) {
        color: #1e293b;
    }
</style>

<div class="modal fade" id="zimmetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-primary"><i data-feather="repeat" class="me-2"></i>Demirbaş Zimmet Ver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="zimmetForm">
                <input type="hidden" name="zimmet_id" id="zimmet_id" value="0">
                <div class="modal-body p-4">
                    <div class="alert alert-soft-primary border-0 d-flex align-items-center mb-4">
                        <i data-feather="info" class="me-3 text-primary"></i>
                        <div class="text-primary small">
                            Personele demirbaş zimmetlemek için formu doldurun. Seçtiğiniz türe göre liste güncellenecektir.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label d-block fw-bold text-muted small text-uppercase mb-2">Zimmet Türü</label>
                            <div class="segmented-control">
                                <input type="radio" name="zimmet_turu" id="zimmetTurDemirbas" value="demirbas" checked>
                                <label for="zimmetTurDemirbas">
                                    <i data-feather="box" width="16" height="16"></i> Demirbaş
                                </label>

                                <input type="radio" name="zimmet_turu" id="zimmetTurSayac" value="sayac">
                                <label for="zimmetTurSayac">
                                    <i data-feather="clock" width="16" height="16"></i> Sayaç
                                </label>

                                <input type="radio" name="zimmet_turu" id="zimmetTurAparat" value="aparat">
                                <label for="zimmetTurAparat">
                                    <i data-feather="tool" width="16" height="16"></i> Aparat
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <!-- Koli Modu Checkbox (Sadece Sayaç için) -->
                            <div id="koliModuWrapper" class="form-check form-switch mb-2 d-none">
                                <input class="form-check-input" type="checkbox" id="koliModuToggle">
                                <label class="form-check-label fw-medium" for="koliModuToggle">
                                    <i class="bx bx-package me-1"></i>10'lu Koli Zimmeti
                                </label>
                            </div>

                            <!-- Standart Seçim -->
                            <div id="tekliSecimAlani" class="form-floating form-floating-custom">
                                <select class="form-select select2" id="demirbas_id_zimmet" name="demirbas_id"
                                    style="width:100%">
                                    <!-- AJAX ile dolacak -->
                                </select>
                                <label for="demirbas_id_zimmet">Demirbaş Seçin *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="package"></i>
                                </div>
                            </div>

                            <!-- Koli Seçimi -->
                            <div id="koliSecimAlani" class="d-none">
                                <div class="input-group mb-2">
                                    <div class="form-floating form-floating-custom flex-grow-1">
                                        <input type="text" class="form-control" id="koli_baslangic_seri" placeholder="Başlangıç Seri No">
                                        <label for="koli_baslangic_seri">Başlangıç Seri No</label>
                                        <div class="form-floating-icon">
                                            <i data-feather="menu"></i>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary" type="button" id="btnKoliEkle"><i class="bx bx-plus"></i> Ekle</button>
                                </div>
                                <div class="form-text mb-2">Birden fazla eklemek için virgülle ayırabilirsiniz.</div>

                                <div id="eklenenKolilerListesi" class="list-group list-group-flush border rounded mb-2 d-none" style="max-height: 250px; overflow-y: auto;">
                                    <!-- Javascript ile dolacak -->
                                </div>
                                
                                <div id="toplamKoliBilgisi" class="alert alert-info py-2 d-none">
                                    <div class="d-flex justify-content-between">
                                        <span>Toplam Koli: <strong id="lblToplamKoli">0</strong></span>
                                        <span>Toplam Sayaç: <strong id="lblToplamSayac">0</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div id="personelTuruWrapper" class="d-none">
                                <label class="form-label d-block fw-bold text-muted small text-uppercase mb-2">Personel Seçimi</label>
                                <div class="segmented-control mb-2">
                                    <input type="radio" name="personel_turu" id="personelTuruTum" value="all" checked>
                                    <label for="personelTuruTum">
                                        <i data-feather="users" width="16" height="16"></i> Tüm Personeller
                                    </label>
    
                                    <input type="radio" name="personel_turu" id="personelTuruKesmeAcma" value="kesme_acma">
                                    <label for="personelTuruKesmeAcma">
                                        <i data-feather="scissors" width="16" height="16"></i> Kesme Açma
                                    </label>
                                </div>
                            </div>
                            <?php echo Form::FormSelect2('personel_id', [], null, 'Personel Seçin *', 'users'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="input-group">
                                <?php echo Form::FormFloatInput('number', 'teslim_miktar', '1', null, 'Teslim Edilecek Miktar *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                                <span class="input-group-text">
                                    Kalan: <span id="kalanMiktarText" class="ms-1 fw-bold">-</span>
                                </span>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="zimmetKaydet" class="btn btn-warning">
                        <i data-feather="check-square" class="me-1"></i>Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>