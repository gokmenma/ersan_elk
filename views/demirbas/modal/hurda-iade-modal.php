<?php
use App\Helper\Form;

// Personel listelerini hazırla
$aktifPersoneller = [];
$tumPersoneller = [];

foreach ($personeller as $p) {
    $item = ['id' => $p->id, 'text' => htmlspecialchars($p->adi_soyadi)];
    $tumPersoneller[] = $item;
    if (empty($p->isten_cikis_tarihi) || $p->isten_cikis_tarihi == '0000-00-00') {
        $aktifPersoneller[] = $item;
    }
}
?>

<script>
    var hurdaAktifPersoneller = <?php echo json_encode($aktifPersoneller); ?>;
    var hurdaTumPersoneller = <?php echo json_encode($tumPersoneller); ?>;
</script>

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
        padding: 0.35rem 0.8rem;
        cursor: pointer;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.825rem;
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
        color: #ef4444;
        /* Hurda konseptine uygun kırmızı */
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        font-weight: 600;
    }

    .segmented-control label:hover:not(:active) {
        color: #1e293b;
    }
</style>

<div class="modal fade" id="hurdaIadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-bottom bg-light bg-opacity-75 p-4">
                <div class="d-flex align-items-center">
                    <div class="btn btn-icon btn-soft-danger rounded-circle me-3"
                        style="pointer-events: none; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                        <i data-feather="layers" class="text-danger" style="width: 24px; height: 24px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-1">Hurda Sayaç İade Al</h5>
                        <p class="text-muted small mb-0">Lütfen formu eksiksiz doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="hurdaIadeForm">
                <div class="modal-body p-4 pt-4">
                    <!-- Personel Filtresi -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label d-block fw-bold text-muted small text-uppercase mb-2">Personel
                                Filtresi</label>
                            <div class="segmented-control" style="max-width: 450px;">
                                <input type="radio" name="hurdaPersonelFilter" id="hurdaPersonelAktif" value="aktif"
                                    checked>
                                <label for="hurdaPersonelAktif">
                                    <i data-feather="user-check" width="16" height="16"></i> Aktif Personeller
                                </label>

                                <input type="radio" name="hurdaPersonelFilter" id="hurdaPersonelTum" value="all">
                                <label for="hurdaPersonelTum">
                                    <i data-feather="users" width="16" height="16"></i> Tüm Personeller
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-soft-warning border-0 d-flex align-items-center mb-4 p-3"
                        style="border-left: 4px solid #f1b44c !important; border-radius: 8px;">
                        <i data-feather="info" class="me-3 text-warning flex-shrink-0"
                            style="width: 24px; height: 24px;"></i>
                        <div class="small text-dark">
                            Personelin zimmetindeki hurda sayaçları depoya iade almak için bu formu kullanın.
                            Hurda sayaçlar depoya girdikten sonra <strong>Kaskiye Teslim</strong> işlemi yapılabilir.
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Personel Seçimi -->
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Personel *</label>
                            <?php echo Form::FormSelect2('hurda_personel_id', [], null, null, 'user'); ?>
                        </div>

                        <!-- Tarih -->
                        <div class="col-md-7">
                            <?php echo Form::FormFloatInput('text', 'hurda_iade_tarihi', date('d.m.Y'), null, 'İade Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>

                        <!-- Adet -->
                        <div class="col-md-5">
                            <?php echo Form::FormFloatInput('number', 'hurda_iade_adet', '1', null, 'Adet *', 'hash', 'form-control', true, null, 'off', false, 'min="1"'); ?>
                        </div>

                        <!-- Sayaç Adı -->
                        <div class="col-md-7">
                            <?php echo Form::FormFloatInput('text', 'hurda_sayac_adi', '', 'Opsiyonel (Boş bırakılırsa otomatik oluşturulur)', 'Hurda Sayaç Adı', 'package', 'form-control', false); ?>
                        </div>

                        <!-- Açıklama -->
                        <div class="col-12">
                            <?php echo Form::FormFloatTextarea('hurda_aciklama', null, 'İade ile ilgili notlar...', 'Açıklama', 'message-square'); ?>
                        </div>
                    </div>

                    <!-- Personel zimmetindeki hurda sayaçlar listesi (dinamik yüklenecek) -->
                    <div id="hurdaZimmetListesi" class="mt-4 pt-2 border-top d-none">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <label
                                class="form-label d-block fw-bold text-muted small text-uppercase mb-0 d-flex align-items-center">
                                <i data-feather="list" class="me-1 text-danger"
                                    style="width: 14px; height: 14px; margin-bottom: 2px;"></i>
                                Personelin Zimmetindeki Hurda Sayaçlar
                            </label>
                        </div>
                        <div class="table-responsive border rounded" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead style="background-color: #f8f9fa; position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th class="text-center align-middle" style="width: 40px; padding: 10px;">
                                            <div class="form-check d-flex justify-content-center m-0">
                                                <input class="form-check-input" type="checkbox" id="hurdaCheckAll">
                                            </div>
                                        </th>
                                        <th class="align-middle fw-semibold text-muted" style="padding: 10px;">Sayaç Adı
                                        </th>
                                        <th class="text-center align-middle fw-semibold text-muted"
                                            style="width: 80px; padding: 10px;">Adet</th>
                                        <th class="align-middle fw-semibold text-muted"
                                            style="width: 140px; padding: 10px;">Teslim Tarihi</th>
                                    </tr>
                                </thead>
                                <tbody id="hurdaZimmetBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <div class="d-flex flex-column align-items-center justify-content-center">
                                                <i data-feather="info" class="text-muted mb-2 opacity-50"
                                                    style="width: 20px; height: 20px;"></i>
                                                <span class="small">Personel seçildiğinde listelenecektir.</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top-0 rounded-bottom"
                    style="border-radius: 0 0 12px 12px;">
                    <button type="button"
                        class="btn btn-outline-secondary fw-medium px-4 text-uppercase d-flex align-items-center"
                        data-bs-dismiss="modal">
                        <i data-feather="x" class="me-1" style="width: 16px; height: 16px;"></i> İptal
                    </button>
                    <button type="button" id="btnHurdaIadeKaydet"
                        class="btn btn-danger fw-bold px-4 text-uppercase d-flex align-items-center shadow-sm">
                        <i data-feather="check-circle" class="me-1" style="width: 16px; height: 16px;"></i> Hurda İade
                        Al
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>