<style>
    /* Toplu Aparat Zimmet Modal Styles */
    .aparat-zimmet-list {
        max-height: 340px;
        overflow-y: auto;
        border-radius: 0.5rem;
    }

    .aparat-zimmet-list::-webkit-scrollbar {
        width: 5px;
    }

    .aparat-zimmet-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .aparat-zimmet-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background-color 0.15s ease;
    }

    .aparat-zimmet-item:last-child {
        border-bottom: none;
    }

    .aparat-zimmet-item:hover {
        background-color: #f8fafc;
    }

    .aparat-zimmet-item .aparat-info {
        flex: 1;
        min-width: 0;
    }

    .aparat-zimmet-item .aparat-name {
        font-weight: 600;
        font-size: 0.875rem;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .aparat-zimmet-item .aparat-meta {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 1px;
    }

    .aparat-zimmet-item .aparat-qty-group {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex-shrink: 0;
    }

    .aparat-zimmet-item .qty-input {
        width: 65px;
        text-align: center;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.3rem 0.4rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.375rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .aparat-zimmet-item .qty-input:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
        outline: none;
    }

    .aparat-zimmet-item .qty-input.is-invalid {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .aparat-zimmet-item .stock-badge {
        font-size: 0.7rem;
        padding: 0.15rem 0.45rem;
        border-radius: 0.25rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .aparat-zimmet-item .remove-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.375rem;
        border: none;
        background: #fef2f2;
        color: #ef4444;
        cursor: pointer;
        transition: all 0.15s ease;
        flex-shrink: 0;
    }

    .aparat-zimmet-item .remove-btn:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .toplu-aparat-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0.85rem;
        background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%);
        border-radius: 0.5rem;
        border: 1px solid #fde68a;
        font-size: 0.8rem;
    }

    .toplu-aparat-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        color: #94a3b8;
        text-align: center;
    }

    .toplu-aparat-empty i {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }
</style>

<div class="modal fade" id="topluAparatZimmetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-warning">
                    <i class="bx bx-transfer-alt me-2"></i>Toplu Aparat Zimmet Ver
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="topluAparatZimmetForm">
                <div class="modal-body p-4">
                    <div class="alert alert-soft-warning border-0 d-flex align-items-center mb-3 py-2">
                        <i class="bx bx-info-circle me-2 text-warning fs-5"></i>
                        <div class="text-dark small">
                            Seçilen aparatlar aşağıda listelenmiştir. Her aparat için teslim edilecek miktarı giriniz.
                        </div>
                    </div>

                    <!-- Aparat Listesi -->
                    <div class="aparat-zimmet-list border rounded mb-3" id="topluAparatListesi">
                        <div class="toplu-aparat-empty">
                            <i class="bx bx-package"></i>
                            <span>Aparat seçilmemiş</span>
                        </div>
                    </div>

                    <!-- Özet -->
                    <div class="toplu-aparat-summary mb-3" id="topluAparatOzet" style="display:none;">
                        <div>
                            <i class="bx bx-package me-1"></i>
                            <strong id="topluAparatCesit">0</strong> çeşit aparat
                        </div>
                        <div>
                            Toplam: <strong id="topluAparatToplam">0</strong> adet
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Personel Seçimi -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <select class="form-select" id="toplu_aparat_personel_id" name="personel_id"
                                    style="width:100%">
                                    <!-- AJAX ile dolacak -->
                                </select>
                                <label for="toplu_aparat_personel_id">Personel Seçin *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="users"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarih ve Açıklama -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'toplu_aparat_teslim_tarihi', date('d.m.Y'), null, 'Teslim Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="toplu-aparat-summary w-100" style="display: flex;">
                                <div class="d-flex align-items-center gap-2 w-100 justify-content-center">
                                    <i class="bx bx-check-circle text-success"></i>
                                    <span class="small fw-medium" id="topluAparatValidasyonText">Tüm miktarlar
                                        uygun</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <textarea class="form-control" name="toplu_aparat_aciklama" id="toplu_aparat_aciklama"
                                    placeholder="Zimmet ile ilgili notlar..." style="height: 60px;"></textarea>
                                <label for="toplu_aparat_aciklama">Açıklama</label>
                                <div class="form-floating-icon">
                                    <i data-feather="file-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="topluAparatZimmetKaydet" class="btn btn-warning" disabled>
                        <i class="bx bx-transfer-alt me-1"></i>Toplu Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>