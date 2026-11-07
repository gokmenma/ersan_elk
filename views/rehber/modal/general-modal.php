<?php
use App\Helper\Form;
?>

<div class="modal fade" id="rehberModal" tabindex="-1" aria-labelledby="rehberModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded-3 p-2 d-flex align-items-center justify-content-center me-3 border border-primary-subtle shadow-xs" style="width: 44px; height: 44px;">
                        <i class="bx bxs-contact fs-4 text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 font-size-15" id="rehberModalLabel">Yeni Rehber Kaydı</h5>
                        <p class="text-muted font-size-12 mb-0" id="rehberModalDesc">Kişi veya kurum iletişim bilgilerini giriniz.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <form id="rehberForm" novalidate>
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="id" id="id" value="0">

                    <div class="row g-3">
                        <div class="col-12">
                            <?= Form::FormFloatInput(
                                "text",
                                "adi_soyadi",
                                "",
                                "Adı Soyadı giriniz",
                                "Adı Soyadı *",
                                "user",
                                "form-control fw-semibold"
                            ); ?>
                        </div>

                        <div class="col-12">
                            <?= Form::FormFloatInput(
                                "text",
                                "kurum_adi",
                                "",
                                "Firma veya kurum adı",
                                "Kurum / Firma Adı",
                                "briefcase",
                                "form-control"
                            ); ?>
                        </div>

                        <div class="col-12">
                            <?= Form::FormFloatInput(
                                "tel",
                                "telefon",
                                "",
                                "05XX XXX XX XX",
                                "Telefon Numarası *",
                                "phone",
                                "form-control"
                            ); ?>
                        </div>

                        <div class="col-12">
                            <?= Form::FormFloatInput(
                                "email",
                                "email",
                                "",
                                "ornek@alanadi.com",
                                "E-Posta Adresi",
                                "at-sign",
                                "form-control"
                            ); ?>
                        </div>

                        <div class="col-12">
                            <?= Form::FormFloatInput(
                                "text",
                                "adres",
                                "",
                                "Şehir, ilçe veya tam adres",
                                "Adres Bilgisi",
                                "map-pin",
                                "form-control"
                            ); ?>
                        </div>

                        <div class="col-12">
                            <?= Form::FormFloatTextarea(
                                "aciklama",
                                "",
                                "Kişi/kurum hakkında ek notlar...",
                                "Açıklama / Notlar",
                                "file-text"
                            ); ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top-0 px-4 pb-4 pt-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary bg-white px-3 shadow-xs" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> İptal
                    </button>
                    <button type="button" id="rehberKaydet" class="btn btn-primary px-4 shadow-sm text-white">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
