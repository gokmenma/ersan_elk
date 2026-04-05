<?php

use App\Helper\Form;

?>
<style>
    .mail-sender-card {
        background-color: #fff;
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }

    .mail-sender-card .card-header {
        background-color: #fff;
        border-bottom: 1px solid #e9ecef;
        padding: 1.25rem 1.5rem;
    }

    .mail-sender-card .card-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }

    .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        transition: all 0.2s ease-in-out;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
    }

    /* Email Önizleme Stilleri */
    .mail-preview-container {
        border: 1px solid #e9ecef;
        border-radius: 12px;
        background-color: #fff;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .mail-preview-header {
        padding: 15px;
        border-bottom: 1px solid #eee;
        background-color: #f8f9fa;
        border-radius: 12px 12px 0 0;
    }

    .mail-preview-body {
        padding: 20px;
        flex-grow: 1;
        overflow-y: auto;
        min-height: 400px;
    }

    .mail-preview-subject {
        font-weight: bold;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .mail-preview-info {
        font-size: 0.85rem;
        color: #666;
    }

    /* Tag Sistemi */
    .tag-input-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        cursor: text;
        min-height: 45px;
    }

    .tag {
        display: inline-flex;
        align-items: center;
        background-color: #111;
        /* Dark background like the button */
        color: #fff;
        padding: 6px 12px;
        border-radius: 8px;
        /* Rounded corners like the button */
        font-size: 0.85rem;
        font-weight: 500;
        animation: slideDownFadeIn 0.3s ease-out;
        margin-bottom: 4px;
    }

    .tag .close-tag {
        cursor: pointer;
        margin-left: 8px;
        font-weight: bold;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    .tag .close-tag:hover {
        opacity: 1;
    }

    @keyframes slideDownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .attachment-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 5px;
        border: 1px solid #eee;
    }

    .attachment-item i {
        margin-right: 10px;
        color: #6c757d;
    }

    .attachment-item .remove-attachment {
        margin-left: auto;
        cursor: pointer;
        color: #dc3545;
    }
</style>

<div class="mail-sender-card shadow-lg">
    <div class="card-header">
        <h4 class="mb-0 d-flex align-items-center">
            <i class="fas fa-envelope-open-text me-2 text-primary"></i>
            Yeni E-Posta Gönder
        </h4>
    </div>

    <div class="card-body p-4">
        <div class="row g-4">
            <!-- Sol Taraf: Form -->
            <div class="col-lg-7">
                <form id="mailForm" enctype="multipart/form-data">
                    <!-- Gönderen Seçimi -->
                    <div class="mb-4">
                        <?php
                        echo Form::FormSelect2(
                            name: "senderAccount",
                            label: "Gönderen Hesap",
                            options: [
                                "1" => "noreply@ersantr.com"
                            ],
                            selectedValue: 1,
                            icon: "send"
                        );
                        ?>
                    </div>

                    <!-- Alıcılar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Alıcılar</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" id="clear-recipients"
                                    style="border-radius: 6px;">
                                    <i class="fas fa-trash-alt me-1"></i> Temizle
                                </button>
                            </div>
                        </div>
                        <div class="tag-input-container form-control" id="recipients-container">
                            <div class="tag-input-wrapper flex-grow-1">
                                <input type="text" id="recipients" class="border-0 w-100 outline-none"
                                    style="outline: none;" placeholder="E-posta yazıp Enter'a basın...">
                            </div>
                        </div>
                        <div class="form-text">Birden fazla e-posta adresi ekleyebilirsiniz.</div>
                    </div>

                    <!-- Konu -->
                    <div class="mb-4">
                        <?php
                        echo Form::FormFloatInput(
                            type: "text",
                            name: "subject",
                            value: "",
                            placeholder: "E-posta Konusu",
                            label: "Konu",
                            icon: "file-text"
                        );
                        ?>
                    </div>

                    <!-- Mesaj -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Mesaj İçeriği</label>
                            <a href="#" class="text-decoration-none small sablon-kullan">
                                <i class="fas fa-copy me-1"></i> Şablon Kullan
                            </a>
                        </div>
                        <textarea id="mailMessage" name="message"></textarea>
                    </div>

                    <!-- Dosya Ekleri -->
                    <div class="mb-4">
                        <label class="form-label">Dosya Ekleri</label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="mailAttachments" name="attachments[]" multiple>
                            <label class="input-group-text" for="mailAttachments"><i
                                    class="fas fa-paperclip"></i></label>
                        </div>
                        <div id="attachment-list" class="mt-2"></div>
                    </div>
                </form>
            </div>

            <!-- Sağ Taraf: Önizleme -->
            <div class="col-lg-5">
                <div class="mail-preview-container">
                    <div class="mail-preview-header">
                        <div class="mail-preview-subject" id="preview-subject">Konu burada görünecek...</div>
                        <div class="mail-preview-info">
                            <div><strong>Kimden:</strong> <span id="preview-from">noreply@ersantr.com</span></div>
                            <div><strong>Kime:</strong> <span id="preview-to">Alıcılar...</span></div>
                        </div>
                    </div>
                    <div class="mail-preview-body" id="preview-body">
                        <div class="text-muted italic">Mesaj içeriği burada önizlenecek...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer p-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <button type="button" class="btn btn-outline-secondary" id="save-as-template">
                    <i class="fas fa-save me-1"></i> Şablon Olarak Kaydet
                </button>
            </div>
            <div class="d-flex align-items-center">
                <div class="collapse collapse-horizontal me-3" id="scheduleCollapse">
                    <div class="d-flex" style="width: 350px;">
                        <div class="me-2">
                            <?php
                            echo Form::FormFloatInput(
                                type: "text",
                                name: "scheduleDate",
                                value: "",
                                placeholder: "Tarih",
                                label: "Tarih",
                                icon: "calendar",
                                class: "form-control flatpickr"
                            );
                            ?>
                        </div>
                        <div>
                            <?php
                            echo Form::FormFloatInput(
                                type: "time",
                                name: "scheduleTime",
                                value: "",
                                placeholder: "Saat",
                                label: "Saat",
                                icon: "clock",
                                class: "form-control"
                            );
                            ?>
                        </div>
                    </div>
                </div>
                <button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="collapse"
                    data-bs-target="#scheduleCollapse">
                    <i class="fas fa-clock me-1"></i> Zamanla
                </button>
                <button type="submit" form="mailForm" class="btn btn-primary px-5">
                    <i class="fas fa-paper-plane me-2"></i> Gönder
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modallar (SMS sayfasındakilerle benzer) -->


<div class="modal fade" id="sablondanSecModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content sablondanSecModalContent"></div>
    </div>
</div>

<div class="modal fade" id="sablonKaydetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Şablon Olarak Kaydet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sablonKaydetForm">
                    <div class="mb-3">
                        <?php
                        echo Form::FormFloatInput(
                            type: "text",
                            name: "sablonAdi",
                            value: "",
                            placeholder: "Şablon adını giriniz",
                            label: "Şablon Adı",
                            icon: "file"
                        );
                        ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" id="sablonKaydetButton" class="btn btn-primary">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Summernote Init
        $('#mailMessage').summernote({
            placeholder: 'Mesajınızı buraya yazın...',
            tabsize: 2,
            height: 300,
            lang: 'tr-TR',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            callbacks: {
                onChange: function (contents, $editable) {
                    $('#preview-body').html(contents || '<div class="text-muted italic">Mesaj içeriği burada önizlenecek...</div>');
                }
            }
        });

        // Diğer modal işlemleri sms-gonder.php ile benzer olacak, JS dosyasına taşınacak
    });
</script>