$(document).ready(function () {
  const api_url = "views/evrak-takip/api.php";

  // Modalı body'ye taşı (z-index ve stacking context/backdrop sorununu çözmek için)
  function ensureModalInBody() {
    const modalEl = $("#evrakModal");
    if (modalEl.length > 0 && modalEl.parent().prop("tagName") !== "BODY") {
      modalEl.appendTo("body");
    }
  }
  ensureModalInBody();
  $("#dosya").attr("accept", ".pdf,.jpg,.jpeg,.png");

  let evrakPdfObjectUrl = null;

  function initEvrakSummernote() {
    const editor = $("#evrak_aciklama");
    if (!editor.length || typeof $.fn.summernote === "undefined" || editor.data("summernote")) return;
    editor.summernote({
      height: 230,
      lang: "tr-TR",
      placeholder: "Evrak içeriğini buraya yazın...",
      dialogsInBody: true,
      toolbar: [
        ["style", ["style", "bold", "italic", "underline", "clear"]],
        ["font", ["fontsize", "color"]],
        ["para", ["ul", "ol", "paragraph"]],
        ["insert", ["link", "table", "hr"]],
        ["view", ["fullscreen", "codeview"]]
      ]
    });
    updateEditorFont();
  }

  function updateEditorFont() {
    const isArial = $("#yazi_tipi").val() === "arial";
    $("#evrakModal .note-editable").css({
      "font-family": isArial ? "Arial, sans-serif" : '"Times New Roman", Times, serif',
      "font-size": isArial ? "11pt" : "12pt"
    });
  }

  function setEvrakContent(content) {
    initEvrakSummernote();
    if ($("#evrak_aciklama").data("summernote")) {
      $("#evrak_aciklama").summernote("code", content || "");
    } else {
      $("#evrak_aciklama").val(content || "");
    }
  }

  function syncEvrakContent() {
    if ($("#evrak_aciklama").data("summernote")) {
      $("#evrak_aciklama").val($("#evrak_aciklama").summernote("code"));
    }
  }

  function clearPdfObjectUrl() {
    if (evrakPdfObjectUrl) URL.revokeObjectURL(evrakPdfObjectUrl);
    evrakPdfObjectUrl = null;
    $("#evrakPdfFrame").attr("src", "about:blank").hide();
    $("#evrakPdfYeniSekme").addClass("d-none").attr("href", "#");
  }

  // Feather Icons
  if (typeof feather !== 'undefined') feather.replace();

  // Deleted konular helpers
  function getDeletedKonular() {
    try {
      return JSON.parse(localStorage.getItem("evrak_deleted_konular") || "[]");
    } catch (e) {
      return [];
    }
  }

  function removeDeletedOptions() {
    const deleted = getDeletedKonular();
    if (deleted.length > 0) {
      $("#konu option").each(function () {
        const val = $(this).val();
        if (val && deleted.includes(val)) {
          $(this).remove();
        }
      });
      if ($("#konu").data('select2')) {
        $("#konu").trigger('change.select2');
      }
    }
  }

  let isDeletingKonu = false;

  function handleDeleteKonu(konuVal) {
    if (!konuVal || isDeletingKonu) return;
    isDeletingKonu = true;

    const previousVal = $("#konu").val();
    if ($("#konu").data('select2')) {
      try { $("#konu").select2("close"); } catch (err) {}
    }

    let deletedKonular = getDeletedKonular();
    if (!deletedKonular.includes(konuVal)) {
      deletedKonular.push(konuVal);
      localStorage.setItem("evrak_deleted_konular", JSON.stringify(deletedKonular));
    }

    $("#konu option").each(function () {
      if ($(this).val() === konuVal) {
        $(this).remove();
      }
    });

    if (previousVal === konuVal) {
      $("#konu").val("").trigger("change");
    } else {
      $("#konu").val(previousVal).trigger("change.select2");
    }

    setTimeout(() => { isDeletingKonu = false; }, 100);
  }

  $(document).on("select2:selecting", "#konu", function (e) {
    if (isDeletingKonu) {
      e.preventDefault();
      return false;
    }
  });

  // Select2 Initialization Function
  function initEvrakSelect2() {
    console.log("Initializing Select2...");
    
    // Normal Select2 (evrak-select2)
    $("#evrakModal .evrak-select2").each(function() {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({
                dropdownParent: $("#evrakModal"),
                width: "100%",
                placeholder: "Seçiniz..."
            });
        }
    });

    $("#evrakModal .evrak-select2-multiple").each(function() {
      if (!$(this).hasClass("select2-hidden-accessible")) {
        $(this).select2({
          dropdownParent: $("#evrakModal"),
          width: "100%",
          placeholder: "İmza atacak kullanıcıları seçiniz...",
          maximumSelectionLength: 3
        });
      }
    });

    // Tags Select2 (evrak-select2-tags)
    $("#evrakModal .evrak-select2-tags").each(function() {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({
                tags: true,
                dropdownParent: $("#evrakModal"),
                width: "100%",
                placeholder: "Seçiniz veya Yazınız...",
                templateResult: function (data) {
                    if (!data.id || data.id === '') {
                        return data.text;
                    }
                    var safeText = $('<div>').text(data.text).html();
                    var safeId = $('<div>').text(data.id).html();
                    
                    var $deleteBtn = $(
                        '<span class="btn-delete-konu ms-2 rounded-circle d-flex align-items-center justify-content-center" data-konu="' + safeId + '" title="Sil" style="cursor: pointer; width: 20px; height: 20px; color: #94a3b8; transition: all 0.15s ease;">' +
                            '<i class="bx bx-x" style="font-size: 15px;"></i>' +
                        '</span>'
                    );

                    $deleteBtn.on("mousedown mouseup click touchstart touchend", function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (e.stopImmediatePropagation) {
                            e.stopImmediatePropagation();
                        }
                        if (e.type === "click" || e.type === "touchend") {
                            handleDeleteKonu(data.id);
                        }
                        return false;
                    });

                    var $el = $(
                        '<div class="d-flex align-items-center justify-content-between w-100 py-0" style="font-size: 12.5px; line-height: 1.3;">' +
                            '<span>' + safeText + '</span>' +
                        '</div>'
                    ).append($deleteBtn);

                    return $el;
                }
            });
        }
    });

    removeDeletedOptions();
  }

  // Handle Select2 in Modals (Bootstrap 5 fix)
  $('#evrakModal').on('shown.bs.modal', function () {
    initEvrakSummernote();
    initEvrakSelect2();
    if (typeof feather !== 'undefined') feather.replace();
    checkSectionVisibility();
  });

  // Flatpickr
  function initFlatpickr() {
      if (typeof $.fn.flatpickr !== 'undefined' || typeof flatpickr !== 'undefined') {
        $(".flatpickr").flatpickr({
          dateFormat: "d.m.Y",
          locale: "tr",
          static: false,
          disableMobile: true
        });
      }
  }
  initFlatpickr();

  // Validation
  const validator = $("#evrakForm").validate({
    rules: {
      tarih: { required: true },
      konu: { required: true },
      kurum_adi: { required: true }
    },
    errorElement: "span",
    errorPlacement: function (error, element) {
      error.addClass("invalid-feedback");
      element.closest(".mb-3").append(error);
    },
    highlight: function (element) { $(element).addClass("is-invalid"); },
    unhighlight: function (element) { $(element).removeClass("is-invalid"); }
  });

  // Visibility Controls
  function checkSectionVisibility() {
    const tip = $('input[name="evrak_tipi"]').val() || "gelen";
    if (tip === "gelen") {
        $("#gelenCevapSection").removeClass("d-none");
        $("#gidenIliskiSection").addClass("d-none");
        $("#ilgili_evrak_id").val("").trigger("change");
    } else {
        $("#gelenCevapSection").addClass("d-none");
        $("#gidenIliskiSection").removeClass("d-none");
        $("#cevap_verildi").prop("checked", false);
        $("#cevapTarihiContainer").addClass("d-none");
    }
    checkBildirimVisibility();
    checkCevapVisibility();
    checkTrafficFineVisibility();
  }

  function checkTrafficFineVisibility() {
    const konu = ($("#konu").val() || '').toLowerCase();
    if (konu.includes('trafik')) {
      const section = $("#trafficFineSection");
      section.stop(true, true);
      if (section.hasClass("d-none")) {
        section.removeClass("d-none").hide().slideDown(300);
      } else {
        section.show();
      }
    } else {
      const section = $("#trafficFineSection");
      if (section.hasClass("d-none")) return;
      section.stop(true, true).slideUp(300, function() {
        $(this).addClass("d-none");
        $("#plaka").val("");
        $("#ceza_personel_id").val("").trigger("change");
        $("#tutar").val("");
        $("#plakaFeedback").hide().html("");
      });
    }
  }

  function checkTrafficFineTarget() {
    const target = $('input[name="ceza_hedef_tipi"]:checked').val() || "arac";
    if (target === "personel") {
      $("#cezaAracContainer").addClass("d-none");
      $("#cezaPersonelContainer").removeClass("d-none");
      $("#plaka").val("").trigger("change");
      $("#plakaFeedback").hide().html("");
    } else {
      $("#cezaPersonelContainer").addClass("d-none");
      $("#cezaAracContainer").removeClass("d-none");
      $("#ceza_personel_id").val("").trigger("change");
    }
  }

  function queryAracZimmet() {
    if (($('input[name="ceza_hedef_tipi"]:checked').val() || "arac") !== "arac") return;
    const plaka = $("#plaka").val() || '';
    const tarih = $("input[name='tarih']").val() || '';
    
    if (plaka.length >= 5 && tarih !== '') {
      $("#plakaFeedback").show().html('<span class="text-muted"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Sorgulanıyor...</span>');
      
      $.post(api_url, {
        action: "arac-zimmet-sorgula",
        plaka: plaka,
        tarih: tarih
      }, function(response) {
        if (response.status === "success" && response.personel_id) {
          $("#ilgili_personel_id").val(response.personel_id).trigger("change");
          $("#plakaFeedback").html(`<span class="text-success"><i data-feather="check-circle" style="width:12px; height:12px;" class="me-1"></i>✓ Bu tarihte plakaya zimmetli personel otomatik seçildi: <strong>${response.personel_adi}</strong></span>`);
          if (typeof feather !== 'undefined') feather.replace();
          
          const selectContainer = $("#ilgili_personel_id").next('.select2-container');
          selectContainer.addClass('border border-success rounded-3');
          setTimeout(() => {
            selectContainer.removeClass('border border-success');
          }, 2000);
        } else {
          $("#plakaFeedback").html(`<span class="text-warning"><i data-feather="alert-triangle" style="width:12px; height:12px;" class="me-1"></i>⚠ Bu tarihte zimmetli personel bulunamadı.</span>`);
          if (typeof feather !== 'undefined') feather.replace();
        }
      }, "json").fail(function() {
        $("#plakaFeedback").html(`<span class="text-danger">Sorgulama başarısız oldu.</span>`);
      });
    } else {
      $("#plakaFeedback").hide().html('');
    }
  }

  function checkBildirimVisibility() {
    const personel = $("#personel_id").val();
    const ilgili = $("#ilgili_personel_id").val();
    if ((personel && personel !== "" && personel != "0") || (ilgili && ilgili !== "" && ilgili != "0")) {
      $("#bildirimContainer").removeClass("d-none").addClass("d-flex");
    } else {
      $("#bildirimContainer").addClass("d-none").removeClass("d-flex");
      $("#personel_bildir").prop("checked", false);
    }
  }

  function checkCevapVisibility() {
    const checked = $("#cevap_verildi").is(":checked");
    if (checked) {
      $("#cevapTarihiContainer").removeClass("d-none");
    } else {
      $("#cevapTarihiContainer").addClass("d-none");
    }
  }

  // Events
  $(document).on("change", "#ilgili_personel_id, #personel_id", checkBildirimVisibility);
  $(document).on("change", "#cevap_verildi", checkCevapVisibility);
  $(document).on("keyup change", "#plaka", queryAracZimmet);
  $(document).on("change", "input[name='tarih']", queryAracZimmet);
  $(document).on("change", "#konu", checkTrafficFineVisibility);
  $(document).on("change", 'input[name="ceza_hedef_tipi"]', checkTrafficFineTarget);
  $(document).on("keyup change", "#ceza_tutari", function() {
    const val = parseFloat($(this).val()) || 0;
    if (val > 0) {
      const discounted = (val * 0.75).toFixed(2);
      $("#tutar").val(discounted);
    } else {
      $("#tutar").val("");
    }
  });
  $(document).on("change", 'input[name="evrak_tipi"]', function () {
    const tip = $(this).val();
    if (tip === "gelen") getNextEvrakNo("gelen");
    else $("#evrak_no").val("");
    checkSectionVisibility();
  });

  $(document).on("change", "#yazi_tipi", updateEditorFont);

  $(document).on("change", "#firma_logo", function () {
    const file = this.files && this.files[0];
    if (!file) return;
    if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2 * 1024 * 1024) {
      this.value = '';
      Swal.fire('Geçersiz Logo', 'Logo PNG veya JPG formatında ve en fazla 2 MB olmalıdır.', 'warning');
      return;
    }
    const reader = new FileReader();
    reader.onload = e => {
      $("#mevcutFirmaLogo").attr("src", e.target.result).show();
      $("#firmaLogoYok").addClass("d-none");
    };
    reader.readAsDataURL(file);
  });

  // Handle option deletion from Evrak Konusu (prevent Select2 option selection on mousedown/mouseup/click)
  $(document).on("mousedown mouseup click touchstart touchend", ".btn-delete-konu", function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) {
      e.stopImmediatePropagation();
    }

    if (e.type === "click" || e.type === "touchend") {
      const konuVal = $(this).attr("data-konu") || $(this).data("konu");
      if (konuVal) {
        handleDeleteKonu(konuVal);
      }
    }
    return false;
  });

  // API Functions
  function loadKonular() {
    removeDeletedOptions();
    $.post(api_url, { action: "get-konular" }, function (response) {
      if (response.status === "success") {
        const select = $("#konu");
        const deleted = getDeletedKonular();
        const existingValues = [];
        select.find("option").each(function () {
          if ($(this).val()) existingValues.push($(this).val());
        });
        response.data.forEach((konu) => {
          if (konu && !existingValues.includes(konu) && !deleted.includes(konu)) {
            select.append(new Option(konu, konu));
          }
        });
        removeDeletedOptions();
        if (select.data('select2')) select.trigger('change.select2');
      }
    });
  }

  function getNextEvrakNo(tip) {
    if ($("#evrak_id").val() !== "") return;
    $.post(api_url, { action: "get-next-evrak-no", tip: tip }, function (response) {
      if (response.status === "success") {
        $("#evrak_no").val(response.next_no);
      }
    });
  }

  // Buttons
  $("#btnYeniEvrak").on("click", function () {
    $("#evrakModalLabel").text('Yeni Gelen Evrak Kaydı');
    $("#evrakForm")[0].reset();
    $("#evrak_id").val("");
    setEvrakContent("");
    $("#mevcutDosya").hide();
    
    // Select2 Reset
    $(".evrak-select2, .evrak-select2-tags, .evrak-select2-multiple").val("").trigger("change");
    $("#yazi_tipi").val("times_new_roman").trigger("change");
    
    $("#personel_bildir, #cevap_verildi").prop("checked", false);
    $("#cezaHedefArac").prop("checked", true);
    checkTrafficFineTarget();
    $("#bildirimContainer, #cevapTarihiContainer, #gidenIliskiSection").addClass("d-none");
    $("#gelenCevapSection").removeClass("d-none");
    
    validator.resetForm();
    $(".is-invalid").removeClass("is-invalid");
    
    getNextEvrakNo("gelen");
    loadKonular();
    ensureModalInBody();
    $("#evrakModal").modal("show");
  });

  $("#evrakForm").on("submit", function (e) {
    e.preventDefault();
    syncEvrakContent();
    if (!$(this).valid()) return false;
    if (!$("#trafficFineSection").hasClass("d-none")) {
      const target = $('input[name="ceza_hedef_tipi"]:checked').val() || "arac";
      if (target === "personel" && !$("#ceza_personel_id").val()) {
        Swal.fire('Eksik Bilgi', 'Lütfen cezanın yazıldığı personeli seçiniz.', 'warning');
        return false;
      }
      if (target === "arac" && !$("#plaka").val()) {
        Swal.fire('Eksik Bilgi', 'Lütfen cezanın yazıldığı aracı seçiniz.', 'warning');
        return false;
      }
    }
    const formData = new FormData(this);
    const btn = $("#btnEvrakKaydet");
    btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
      url: api_url, type: "POST", data: formData, contentType: false, processData: false,
      success: function (response) {
        if (response.status === "success") {
          $("#evrakModal").modal("hide");
          Swal.fire('Başarılı!', response.message, 'success').then(() => location.reload());
        } else {
          Swal.fire('Hata!', response.message, 'error');
          btn.prop("disabled", false).text('Bilgileri Kaydet');
        }
      },
      error: function () {
        btn.prop("disabled", false).text('Bilgileri Kaydet');
        Swal.fire('Hata!', "Sunucu ile iletişim kurulurken bir hata oluştu.", 'error');
      }
    });
  });

  $(document).on("click", ".evrak-duzenle", function () {
    const id = $(this).data("id");
    $.post(api_url, { action: "evrak-detay", id: id }, function (response) {
      if (response.status === "success") {
        const data = response.data;
        $("#evrakModalLabel").text('Evrak Düzenle');
        $("#evrak_id").val(data.id);
        
        if (data.evrak_tipi === "gelen") $("#tipGelen").prop("checked", true);
        else $("#tipGiden").prop("checked", true);

        if (data.tarih) {
          const d = data.tarih.split("-");
          $('input[name="tarih"]').val(d[2] + "." + d[1] + "." + d[0]);
        }
        $('input[name="evrak_no"]').val(data.evrak_no);
        $('input[name="kurum_adi"]').val(data.kurum_adi);
        
        // Tags check
        if (data.konu && $("#konu option[value='" + data.konu + "']").length === 0) {
            $("#konu").append(new Option(data.konu, data.konu));
        }
        $("#konu").val(data.konu).trigger("change");
        
        $("#personel_id").val(data.personel_id).trigger("change");
        $("#ilgili_personel_id").val(data.ilgili_personel_id).trigger("change");
        $("#ilgili_evrak_id").val(data.ilgili_evrak_id).trigger("change");
        $("#yazi_tipi").val(data.yazi_tipi || "times_new_roman").trigger("change");
        $("#imza_kullanici_ids").val(data.imza_kullanici_ids || []).trigger("change");
        $("#muhatap_alt_birim").val(data.muhatap_alt_birim || "");
        $("#muhatap_adres").val(data.muhatap_adres || "");
        $("#ilgiler").val(data.ilgiler || "");
        $("#ekler").val(data.ekler || "");
        
        $("#personel_bildir").prop("checked", data.personel_bildirim_durumu == 1);
        $("#cevap_verildi").prop("checked", data.cevap_verildi_mi == 1);
        
        if (data.cevap_tarihi && data.cevap_tarihi !== '0000-00-00') {
            const d2 = data.cevap_tarihi.split("-");
            $('input[name="cevap_tarihi"]').val(d2[2] + "." + d2[1] + "." + d2[0]);
        }

        const cezaHedefTipi = data.ceza_hedef_tipi || (data.ceza_personel_id ? "personel" : "arac");
        $(`input[name="ceza_hedef_tipi"][value="${cezaHedefTipi}"]`).prop("checked", true);
        checkTrafficFineTarget();
        $("#plaka").val(data.plaka || "").trigger("change");
        $("#ceza_personel_id").val(data.ceza_personel_id || "").trigger("change");
        $("#ceza_tutari").val(data.ceza_tutari || "");
        $("#tutar").val(data.tutar || "");

        checkSectionVisibility();
        
        setEvrakContent(data.aciklama);
        if (data.dosya_yolu) $("#mevcutDosya").show().find("a").attr("href", data.dosya_yolu);
        else $("#mevcutDosya").hide();

        ensureModalInBody();
        $("#evrakModal").modal("show");
      }
    });
  });

  $(document).on("click", ".evrak-bildir-manuel", function () {
    const id = $(this).data("id");
    const personId = $(this).data("personel-id");
    const type = $(this).data("type") || "ilgili";
    const lastNotified = $(this).data("last-notified");
    const btn = $(this);
    const originalIcon = btn.html();

    let text = "Seçili personele evrak bilgileri bildirim ve mail olarak gönderilecektir.";
    if (lastNotified && lastNotified !== "" && lastNotified !== "0000-00-00 00:00:00" && lastNotified !== "null") {
        // Tarihi daha şık formatla (Y-m-d H:i:s -> d.m.Y H:i)
        let formattedDate = lastNotified;
        try {
            const dateParts = lastNotified.split(' ');
            const d = dateParts[0].split('-');
            const t = dateParts[1].split(':');
            formattedDate = d[2] + "." + d[1] + "." + d[0] + " " + t[0] + ":" + t[1];
        } catch (e) {}
        
        text = `En son <b>${formattedDate}</b> tarihinde bildirim yapıldı.<br>Tekrar bildirim yapmak istiyor musunuz?`;
    }

    Swal.fire({
        title: 'Emin misiniz?',
        html: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#f43f5e',
        confirmButtonText: 'Evet, Gönder',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (result.isConfirmed) {
            btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.post(api_url, { 
                action: "evrak-bildir", 
                id: id, 
                personel_id: personId,
                type: type 
            }, function (response) {
                btn.prop("disabled", false).html(originalIcon);
                if (response.status === "success") {
                    Swal.fire('Başarılı!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Hata!', response.message, 'error');
                }
            }).fail(function() {
                btn.prop("disabled", false).html(originalIcon);
                Swal.fire('Hata!', "Sunucu ile iletişim kurulurken bir hata oluştu.", 'error');
            });
        }
    });
  });

  $(document).on("click", ".evrak-sil", function () {
    const id = $(this).data("id");
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu kaydı sildiğinizde geri alamazsınız!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Evet, Sil!',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(api_url, { action: "evrak-sil", id: id }, function (response) {
                if (response.status === "success") {
                    Swal.fire('Silindi!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Hata!', response.message, 'error');
                }
            });
        }
    });
  });

  $(document).on("click", ".evrak-e-imza-onayla", function () {
    const id = $(this).data("id");
    Swal.fire({
        title: 'E-İmza ile Onayla',
        html: "Evrakı elektronik imza ile onaylıyorsunuz.<br><b>Tüm imzacılar onayladığında evrak elektronik imzalı hâle gelir ve içeriği bir daha değiştirilemez.</b>",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Evet, Onayla',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(api_url, { action: "evrak-e-imza-onayla", id: id }, function (response) {
                if (response.status === "success") {
                    Swal.fire('Onaylandı!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Hata!', response.message, 'error');
                }
            }).fail(() => Swal.fire('Hata!', "Sunucu ile iletişim kurulamadı.", 'error'));
        }
    });
  });

  $(document).on("click", ".evrak-e-imza-geri-al", function () {
    const id = $(this).data("id");
    Swal.fire({
        title: 'Evrakı Üzerime Geri Al',
        html: "Elektronik imza süreci iptal edilecek ve evrak <b>taslak</b> durumuna dönecek.<br>Alınmış tüm imzalar sıfırlanır ve işlem kayıt altına alınır.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Evet, Geri Al',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(api_url, { action: "evrak-e-imza-geri-al", id: id }, function (response) {
                if (response.status === "success") {
                    Swal.fire('Geri Alındı!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Hata!', response.message, 'error');
                }
            }).fail(() => Swal.fire('Hata!', "Sunucu ile iletişim kurulamadı.", 'error'));
        }
    });
  });

  $(document).on("click", ".evrak-e-imza-iade", function () {
    const button = $(this);
    Swal.fire({
        title: 'Düzeltilmek Üzere İade Et',
        html: "Evrak imzalanmadan <b>taslak</b> durumuna döndürülecek ve gerekçe evrakı hazırlayan kullanıcıya bildirilecek.",
        input: 'textarea',
        inputLabel: 'İade gerekçesi',
        inputPlaceholder: 'Örnek: İlgi bölümündeki esas numarası hatalı, düzeltilip yeniden gönderilmeli.',
        inputAttributes: { maxlength: 2000, rows: 4 },
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'İade Et',
        cancelButtonText: 'Vazgeç',
        inputValidator: value => (!value || value.trim().length < 5) ? 'Gerekçeyi en az 5 karakter olacak şekilde yazınız.' : undefined
    }).then((result) => {
        if (!result.isConfirmed) return;
        $.post(api_url, { action: "evrak-e-imza-iade", id: button.data("id"), gerekce: result.value }, function (response) {
            if (response.status === "success") {
                Swal.fire('İade Edildi', response.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Hata!', response.message, 'error');
            }
        }).fail(() => Swal.fire('Hata!', "Sunucu ile iletişim kurulamadı.", 'error'));
    });
  });

  let imzaFiltresiAktif = false;
  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (!imzaFiltresiAktif || settings.nTable.id !== "evrakTable") return true;
    return $(settings.aoData[dataIndex].nTr).attr("data-imza-bekliyor") === "1";
  });

  $("#btnImzaFiltre").on("click", function () {
    imzaFiltresiAktif = !imzaFiltresiAktif;
    $(this)
      .toggleClass("btn-warning btn-outline-dark")
      .html(imzaFiltresiAktif
        ? '<i data-feather="x" class="icon-xs me-1"></i> Filtreyi Kaldır'
        : '<i data-feather="filter" class="icon-xs me-1"></i> Sadece Bunları Göster');
    if ($.fn.DataTable.isDataTable("#evrakTable")) {
      $("#evrakTable").DataTable().draw();
    }
    if (typeof feather !== "undefined") feather.replace();
  });

  function showPdfFromRequest(request) {
    clearPdfObjectUrl();
    $("#evrakPdfLoader").removeClass("d-none");
    if ($("body > #evrakPdfModal").length > 1) {
      $("body > #evrakPdfModal").not(":last").remove();
    }
    $("#evrakPdfModal").appendTo("body").modal("show");

    $.ajax({
      ...request,
      url: "views/evrak-takip/pdf.php",
      xhrFields: { responseType: "blob" },
      success: function (blob) {
        if (blob.type !== "application/pdf") {
          blob.text().then(message => Swal.fire("Hata!", message || "PDF oluşturulamadı.", "error"));
          $("#evrakPdfModal").modal("hide");
          return;
        }
        evrakPdfObjectUrl = URL.createObjectURL(blob);
        $("#evrakPdfFrame").attr("src", evrakPdfObjectUrl).show();
        $("#evrakPdfYeniSekme").attr("href", evrakPdfObjectUrl).removeClass("d-none");
        $("#evrakPdfLoader").addClass("d-none");
      },
      error: function (xhr) {
        $("#evrakPdfModal").modal("hide");
        const response = xhr.response;
        if (response instanceof Blob) {
          response.text().then(message => Swal.fire("Hata!", message || "PDF oluşturulamadı.", "error"));
        } else {
          Swal.fire("Hata!", "PDF oluşturulamadı.", "error");
        }
      }
    });
  }

  $("#btnPdfOnizle").on("click", function () {
    syncEvrakContent();
    showPdfFromRequest({ type: "POST", data: new FormData($("#evrakForm")[0]), contentType: false, processData: false });
  });

  $(document).on("click", ".evrak-pdf-goruntule", function () {
    showPdfFromRequest({ type: "GET", data: { id: $(this).data("id") } });
  });

  $(document).on("click", "#btnPdfTamEkran", function () {
    const $dialog = $("#evrakPdfModal .modal-dialog");
    const $iframe = $("#evrakPdfFrame");
    const isFullscreen = $dialog.hasClass("modal-fullscreen");

    if (isFullscreen) {
      $dialog.removeClass("modal-fullscreen").addClass("modal-xl modal-dialog-centered");
      $iframe.css("height", "72vh");
      $(this).html('<i class="bx bx-fullscreen"></i> <span>Tam Ekran</span>');
    } else {
      $dialog.removeClass("modal-xl modal-dialog-centered").addClass("modal-fullscreen");
      $iframe.css("height", "calc(100vh - 60px)");
      $(this).html('<i class="bx bx-exit-fullscreen"></i> <span>Küçült</span>');
    }
  });

  function resetPdfModalState() {
    clearPdfObjectUrl();
    const $dialog = $("#evrakPdfModal .modal-dialog");
    $dialog.removeClass("modal-fullscreen").addClass("modal-xl modal-dialog-centered");
    $("#evrakPdfFrame").css("height", "72vh");
    $("#btnPdfTamEkran").html('<i class="bx bx-fullscreen"></i> <span>Tam Ekran</span>');
  }

  $(document).on("hidden.bs.modal", "#evrakPdfModal", resetPdfModalState);

  $("#btnRefresh").on("click", function () { location.reload(); });
});
