<?php

use App\Helper\Form;

?>
<style>
    .sms-sender-card {
        background-color: #fff;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        /* Köşeleri yuvarlatmak için */
    }

    .sms-sender-card .card-header {
        background-color: #fff;
        border-bottom: 1px solid #e9ecef;
        padding: 1.25rem 1.5rem;
    }

    .sms-sender-card .card-footer {
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

    /* Telefon Önizleme Stilleri */
    .phone-preview {
        position: relative;
        width: 280px;
        height: 550px;
        background-color: #1c1c1e;
        border-radius: 40px;
        padding: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .phone-notch {
        position: absolute;
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        width: 120px;
        height: 25px;
        background-color: #1c1c1e;
        border-radius: 0 0 15px 15px;
        z-index: 2;
    }

    .phone-screen {
        width: 100%;
        height: 100%;
        background-color: #f0f2f5;
        /* Telefon içi arkaplanı */
        border-radius: 25px;
        padding: 35px 15px 15px;
        overflow-y: auto;
    }

    .sender-id-preview {
        text-align: center;
        color: #888;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .message-bubble {
        background-color: #e9e9eb;
        color: #000;
        padding: 10px 15px;
        border-radius: 20px;
        max-width: 85%;
        word-wrap: break-word;
        line-height: 1.4;
    }

    /* Etiket (Tag) Giriş Alanı */
    .tag-input-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        cursor: text;
    }

    .tag-input-container input {
        border: none;
        outline: none;
        flex-grow: 1;
        padding: 0;
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
        transition: opacity 0.3s ease, transform 0.3s ease;
        animation: slideDownFadeIn 0.4s ease-out;
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

    .form-floating {
        width: 100% !important;
    }

    .toastr {
        border-radius: 6px;
        padding: 12px;
    }

    /* Saat input'unun ve label'ının bulunduğu sarmalayıcı div'i göreceli yapıyoruz. */
    #collapseWidthExample .d-inline-block:has(#scheduleTime) {
        position: relative;
    }

    /* 
   Varsayılan saat ikonunu gizle. Bu, input'un kendisi için geçerli.
*/
    #scheduleTime::-webkit-calendar-picker-indicator {
        display: none;
        -webkit-appearance: none;
    }


    @keyframes slideDownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-15px);
            /* Yukarıdan başla */
        }

        to {
            opacity: 1;
            transform: translateY(0);
            /* Normal pozisyonuna in */
        }
    }
</style>


<div class="sms-sender-card shadow-lg">
    <!-- KART BAŞLIĞI -->
    <div class="card-header">
        <h4 class="mb-0 d-flex align-items-center">
            <i class="fas fa-paper-plane me-2"></i>
            Yeni SMS Gönder
        </h4>
    </div>

    <!-- KART GÖVDESİ -->
    <div class="card-body p-4">
        <div class="row g-5">
            <!-- Sol Taraf: Form Alanları -->
            <div class="col-lg-7">
                <form id="smsForm">
                    <!-- Gönderen Adı (Alfanümerik) -->
                    <div class="mb-4">

                        <?php
                        echo Form::FormSelect2(
                            name: "senderId",
                            label: "Gönderen Adı",
                            options: [
                                "1" => "ERSANELKTRK"
                            ],
                            selectedValue: 1,
                            icon: "send"
                        );

                        ?>

                    </div>

                    <!-- Alıcılar (Tag Sistemi) -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Alıcılar</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-dark btn-sm kisilerden-sec"
                                    style="border-radius: 6px;">
                                    <i class="fas fa-address-book me-1"></i> Kişilerden Seç
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="clear-recipients"
                                    style="border-radius: 6px;">
                                    <i class="fas fa-trash-alt me-1"></i> Temizle
                                </button>
                            </div>
                        </div>
                        <!-- JS ile oluşturulan etiketler buraya gelecek -->

                        <?php
                        echo Form::FormFloatInput(
                            type: "text",
                            name: "recipients",
                            value: "",
                            placeholder: "Numara yazıp Enter'a basın...",
                            label: "Kime",
                            icon: "user",

                        );

                        ?>
                        <div class="tag-input-container form-control mt-1" id="recipients-container">
                        </div>

                        <div class="form-text">Birden fazla numara ekleyebilirsiniz.(Numara yazıp Enter'a basın...)
                        </div>
                    </div>

                    <!-- Mesaj Alanı -->
                    <div class="mb-2">

                        <div class="row ">

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <a href="#" class="text-decoration-none small sablon-kullan">
                                        <i class="fas fa-address-book me-1"></i> Şablon Kullan
                                    </a>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        id="save-as-template">
                                        <i class="fas fa-save"></i> Şablon Olarak Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>



                        <?php
                        echo Form::FormFloatTextarea(
                            name: "message",
                            value: "",
                            label: "Mesajınız",
                            placeholder: "Mesajınız",
                            icon: "list",
                            minHeight: "250px"
                        );
                        ?>
                    </div>

                    <!-- Karakter Sayacı -->
                    <div class="d-flex justify-content-end text-muted small" id="char-counter">
                        <span>0 / 160 (1 SMS)</span>
                    </div>
                </form>
            </div>

            <!-- Sağ Taraf: Telefon Önizlemesi -->
            <div class="col-lg-5 d-none d-lg-flex justify-content-center align-items-center">
                <div class="phone-preview">
                    <div class="phone-notch"></div>
                    <div class="phone-screen">
                        <div class="sender-id-preview">
                            FIRMAUNVANI
                        </div>
                        <div class="message-bubble">
                            <p id="message-preview">Mesajınız burada görünecek...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer text-end p-3">
        <div class="d-flex justify-content-end align-items-end">

            <!-- Bootstrap Collapse Mekanizması -->
            <div class="collapse-horizontal collapse" id="scheduleCollapse">
                <div class="d-flex" style="width: 350px;">
                    <!-- Sabit bir genişlik vererek ani zıplamayı önle -->
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
                data-bs-target="#scheduleCollapse" aria-expanded="false" aria-controls="scheduleCollapse">
                <i class="fas fa-clock me-1"></i> Zamanla
            </button>

            <button type="submit" form="smsForm" class="btn btn-primary px-5">
                <i class="fas fa-paper-plane me-2"></i> Gönder
            </button>
        </div>
    </div>
</div>


<!-- Modallar -->
<div class="modal fade" id="kisilerdenSecModal" tabindex="-1" aria-labelledby="kisilerdenSecModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content kisilerdenSecModalContent">
            <!-- İçerik AJAX ile yüklenecek -->
        </div>
    </div>
</div>

<div class="modal fade" id="sablondanSecModal" tabindex="-1" aria-labelledby="sablondanSecModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content sablondanSecModalContent">
            <!-- İçerik AJAX ile yüklenecek -->
        </div>
    </div>
</div>

<div class="modal fade" id="sablonKaydetModal" tabindex="-1" aria-labelledby="sablonKaydetModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sablonKaydetModalLabel">Şablon Olarak Kaydet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
        //kisilerden seç linkine tıklanınca modal açma
        $(".kisilerden-sec").on("click", function (e) {
            e.preventDefault();
            $.get('/views/mail-sms/modal/kisi_sec_modal.php?type=sms', function (data) {

                $(".kisilerdenSecModalContent").html(data);
            });

            $("#kisilerdenSecModal").modal("show");
        });

        //şablondan seç linkine tıklanınca modal açma
        $(".sablon-kullan").on("click", function (e) {
            e.preventDefault();
            $.get('/views/mail-sms/modal/sablondan_sec_modal.php', function (data) {

                $(".sablondanSecModalContent").html(data);
            });

            $("#sablondanSecModal").modal("show");
        });
    });
</script>