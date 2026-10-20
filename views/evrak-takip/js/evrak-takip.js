$(document).ready(function () {
  const api_url = "views/evrak-takip/api.php";

  function escapeEvrakHtml(value) {
    return $("<div>").text(String(value ?? "")).html();
  }

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
  let gelenDosyalar = [];
  let silinenGelenDosyaIds = [];
  let gelenDosyaSirasi = 1;

  function formatGelenDosyaBoyutu(bytes) {
    if (!bytes) return "Boyut bilgisi yok";
    return bytes >= 1024 * 1024
      ? (bytes / (1024 * 1024)).toFixed(2) + " MB"
      : (bytes / 1024).toFixed(2) + " KB";
  }

  function gelenDosyaAlanlariniGuncelle() {
    const order = gelenDosyalar.filter(item => item.type !== "primary").map(item => item.type === "existing"
      ? { type: "existing", id: item.id }
      : { type: "new", key: item.key });
    $("#gelen_ek_duzen_json").val(JSON.stringify(order));
    $("#gelen_silinen_ek_ids_json").val(JSON.stringify(silinenGelenDosyaIds));
  }

  function gelenDosyalariGoster() {
    const list = $("#gelenEvrakDosyaListesi").empty();
    gelenDosyalar.forEach((item, index) => {
      const card = $("<div>").addClass("border rounded-3 p-2 bg-light d-flex align-items-center gap-2");
      const icon = $("<span>").addClass("badge bg-info-subtle text-info p-2 rounded-circle")
        .append($("<i>").addClass("bx bx-file font-size-16"));
      const info = $("<div>").addClass("flex-grow-1 min-w-0");
      $("<div>").addClass("fw-bold small text-truncate").text(item.name).appendTo(info);
      const detail = item.type === "existing" || item.type === "primary"
        ? [item.date ? new Date(item.date.replace(" ", "T")).toLocaleString("tr-TR") : "", item.user || "Bilinmeyen Kullanıcı"]
        : ["Henüz kaydedilmedi", "Ekleyen: siz"];
      $("<div>").addClass("text-muted").css("font-size", "11px")
        .text(detail.filter(Boolean).join(" • ") + " • " + formatGelenDosyaBoyutu(item.size)).appendTo(info);
      const actions = $("<div>").addClass("d-flex gap-1 flex-shrink-0");
      if ((item.type === "existing" || item.type === "primary") && item.path) {
        $("<a>").addClass("btn btn-sm btn-subtle-info").attr({href: item.path, target: "_blank", title: "Görüntüle"})
          .append($("<i>").addClass("bx bx-show font-size-14")).appendTo(actions);
      }
      const removeButton = $("<button type='button'>").addClass("btn btn-sm btn-subtle-danger").attr("title", "Listeden kaldır")
        .append($("<i>").addClass("bx bx-trash font-size-14"))
        .on("click", function () {
          if (item.type === "existing") silinenGelenDosyaIds.push(item.id);
          gelenDosyalar.splice(index, 1);
          gelenDosyalariGoster();
        });
      if (item.type !== "primary") removeButton.appendTo(actions);
      card.append(icon, info, actions).appendTo(list);
    });
    gelenDosyaAlanlariniGuncelle();
  }

  $("#gelenEkDosyalari").on("change", function () {
    Array.from(this.files || []).forEach(file => {
      if (file.size > 10 * 1024 * 1024) {
        Swal.fire("Dosya Çok Büyük", file.name + " 10 MB sınırını aşıyor.", "warning");
        return;
      }
      gelenDosyalar.push({type: "new", key: "g" + gelenDosyaSirasi++, name: file.name, size: file.size, file: file});
    });
    this.value = "";
    gelenDosyalariGoster();
  });

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

                    return $(
                        '<div class="d-flex align-items-center justify-content-between w-100 py-0" style="font-size: 12.5px; line-height: 1.3;">' +
                            '<span>' + safeText + '</span>' +
                        '</div>'
                    ).append($deleteBtn);
                }
            });
        }
    });

    removeDeletedOptions();
  }

  // Handle Select2 in Modals
  $('#evrakModal').on('shown.bs.modal', function () {
    initEvrakSummernote();
    initEvrakSelect2();
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
          $("#plakaFeedback").html(`<span class="text-success"><i class="bx bx-check-circle me-1"></i>✓ Bu tarihte plakaya zimmetli personel otomatik seçildi: <strong>${response.personel_adi}</strong></span>`);
          
          const selectContainer = $("#ilgili_personel_id").next('.select2-container');
          selectContainer.addClass('border border-success rounded-3');
          setTimeout(() => {
            selectContainer.removeClass('border border-success');
          }, 2000);
        } else {
          $("#plakaFeedback").html(`<span class="text-warning"><i class="bx bx-error me-1"></i>⚠ Bu tarihte zimmetli personel bulunamadı.</span>`);
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

  // Yeni Evrak Butonu
  $("#btnYeniEvrak").on("click", function () {
    $("#evrakModalLabel").text('Yeni Gelen Evrak Kaydı');
    $("#evrakForm")[0].reset();
    $("#evrak_id").val("");
    setEvrakContent("");
    $("#mevcutDosya").hide();
    gelenDosyalar = [];
    silinenGelenDosyaIds = [];
    gelenDosyalariGoster();
    
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

  // Evrak Form Submit
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
    gelenDosyaAlanlariniGuncelle();
    const formData = new FormData(this);
    gelenDosyalar.forEach(item => {
      if (item.type === "new") formData.append("ek_dosyalari[" + item.key + "]", item.file, item.name);
    });
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

  // Evrak Düzenle
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
        if (data.son_tarih && data.son_tarih !== '0000-00-00') {
          const d_son = data.son_tarih.split("-");
          $('input[name="son_tarih"]').val(d_son[2] + "." + d_son[1] + "." + d_son[0]);
        } else {
          $('input[name="son_tarih"]').val('');
        }
        $('input[name="evrak_no"]').val(data.evrak_no);
        $('input[name="kurum_adi"]').val(data.kurum_adi);

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
        gelenDosyalar = (data.dosyalar || []).map(item => ({...item, type: item.type || "existing"}));
        silinenGelenDosyaIds = [];
        gelenDosyalariGoster();
        if (data.dosya_yolu && gelenDosyalar.length === 0) $("#mevcutDosya").show().find("a").attr("href", data.dosya_yolu);
        else $("#mevcutDosya").hide();

        ensureModalInBody();
        $("#evrakModal").modal("show");
      }
    });
  });

  // Manuel Bildirim Gönder
  $(document).on("click", ".evrak-bildir-manuel", function () {
    const id = $(this).data("id");
    const personId = $(this).data("personel-id");
    const type = $(this).data("type") || "ilgili";
    const lastNotified = $(this).data("last-notified");
    const btn = $(this);
    const originalIcon = btn.html();

    let text = "Seçili personele evrak bilgileri bildirim ve mail olarak gönderilecektir.";
    if (lastNotified && lastNotified !== "" && lastNotified !== "0000-00-00 00:00:00" && lastNotified !== "null") {
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

  // Evrak Sil
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

  function updateModalPadesProgress(percent, text, detail, state) {
    const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
    const $bar = $("#modalPadesProgressBar");
    $("#modalPadesProgress").removeClass("d-none");
    $("#modalPadesProgressText").text(text || "İşlem sürüyor");
    $("#modalPadesProgressPercent").text(safePercent + "%");
    $("#modalPadesProgressDetail").text(detail || "");
    $bar.css("width", safePercent + "%").attr("aria-valuenow", safePercent)
      .toggleClass("progress-bar-animated progress-bar-striped", state !== "success" && state !== "error")
      .toggleClass("bg-success", state !== "error").toggleClass("bg-danger", state === "error");
  }

  function modalPadesOtomatikKep(completed, evrakId, pin) {
    const autoKep = completed?.data?.auto_kep;
    if (!completed?.data?.completed || !autoKep) return Promise.resolve(null);
    if (!autoKep.enabled) {
      const error = new Error(autoKep.error || "Otomatik KEP gönderim bilgileri eksik.");
      error.code = "PADES_OK_KEP_FAILED";
      throw error;
    }
    updateModalPadesProgress(75, "KEP paketi hazırlanıyor", "Son imza tamamlandı. KEP gönderim paketi oluşturuluyor.");
    return $.post(api_url, {
      action: "kep-mime-hazirla", evrak_id: evrakId,
      alici_kep: autoKep.alici_kep, paket_turu: autoKep.paket_turu || "Standart"
    }).then(mime => {
      if (mime.status !== "success" || !mime.eHash || !mime.temp_id) throw new Error(mime.message || "KEP paketi hazırlanamadı.");
      updateModalPadesProgress(84, "KEP paketi imzalanıyor", "Paket aynı akıllı kartla CAdES olarak imzalanıyor.");
      return fetch("http://127.0.0.1:9191/sign", {
        method: "POST", mode: "cors", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ hash: mime.eHash, pin: pin })
      }).then(response => response.json()).then(signature => ({ mime, signature }));
    }).then(({ mime, signature }) => {
      if (!signature || signature.status !== "success" || !signature.signature) throw new Error(signature?.message || "KEP CAdES imzası üretilemedi.");
      updateModalPadesProgress(93, "PTT KEP sistemine gönderiliyor", "İmzalı ileti güvenli kanaldan sevk ediliyor.");
      return $.post(api_url, { action: "kep-smime-sevk", temp_id: mime.temp_id, mesajid: mime.mesajid, signature: signature.signature });
    }).then(sent => {
      if (sent.status !== "success") throw new Error(sent.message || "PTT KEP gönderimi tamamlanamadı.");
      return sent;
    }).catch(error => {
      error.code = "PADES_OK_KEP_FAILED";
      throw error;
    });
  }

  function startModalPadesSignature(id, button) {
    const pin = String($("#modal_eimza_pin").val() || "").trim();
    if (!pin) {
      $("#modal_eimza_pin").trigger("focus").addClass("is-invalid");
      updateModalPadesProgress(0, "PIN kodu gerekli", "İmzalama işlemine başlamak için E-İmza PIN kodunuzu giriniz.", "error");
      return;
    }
    $("#modal_eimza_pin").removeClass("is-invalid").prop("disabled", true);
    $("#btnModalPadesImzala, #btnModalImzaIade").prop("disabled", true);
    button.prop("disabled", true);
    updateModalPadesProgress(8, "E-İmza uygulaması kontrol ediliyor", "Akıllı kart ve yerel imza uygulaması aranıyor.");
    fetch("http://127.0.0.1:9191/status", { method: "GET", mode: "cors" })
      .then(response => response.json())
      .then(agent => {
        if (!agent || agent.status !== "success") throw new Error((agent && agent.message) || "E-imza uygulaması çalışmıyor.");
        if (!String(agent.agent_version || "").startsWith("1.2.")) {
          const error = new Error("PAdES için Ersan E-İmza v1.2.0 gereklidir.");
          error.code = "EIMZA_AGENT_UPDATE_REQUIRED";
          throw error;
        }
        updateModalPadesProgress(20, "Akıllı kart hazır", (agent.owner_name || "Sertifika sahibi") + " adına imzalama başlatılıyor.");
        updateModalPadesProgress(30, "İmzalanacak PDF hazırlanıyor", "Sunucu değişmez evrak sürümünü hazırlıyor.");
        return $.post(api_url, { action: "evrak-pades-hazirla", id: id }).then(prepared => {
          if (prepared.status !== "success" || !prepared.data?.pdf || !prepared.data?.temp_id) throw new Error(prepared.message || "PDF hazırlanamadı.");
          updateModalPadesProgress(48, "PAdES imzası oluşturuluyor", "PDF akıllı kart ile güvenli elektronik olarak imzalanıyor.");
          return fetch("http://127.0.0.1:9191/sign-pdf", {
            method: "POST", mode: "cors", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ pdf: prepared.data.pdf, pin: pin, signer_name: prepared.data.signer_name, reason: prepared.data.reason, location: prepared.data.location })
          }).then(response => response.json()).then(signed => ({ signed, tempId: prepared.data.temp_id, pin }));
        });
      }).then(payload => {
        if (!payload.signed || payload.signed.status !== "success" || !payload.signed.signed_pdf) throw new Error(payload.signed?.message || "Kart PAdES imzası üretemedi.");
        updateModalPadesProgress(65, "İmza doğrulanıyor", "PAdES imzası ve sertifika bilgileri sunucuda kontrol ediliyor.");
        return $.post(api_url, { action: "evrak-pades-tamamla", id: id, temp_id: payload.tempId, signed_pdf: payload.signed.signed_pdf })
          .then(completed => {
            if (completed.status !== "success") throw new Error(completed.message || "PAdES imzası kabul edilmedi.");
            return modalPadesOtomatikKep(completed, id, payload.pin).then(sent => ({ completed, sent }));
          });
      }).then(result => {
        const detail = result.sent ? "İmza doğrulandı. PTT Takip Kodu: " + (result.sent.kep_id || "-") : result.completed.message;
        updateModalPadesProgress(100, result.sent ? "İmzalandı ve KEP ile gönderildi" : "E-İmza tamamlandı", detail, "success");
        $("#modal_eimza_pin").val("");
        setTimeout(() => location.reload(), 1800);
      }).catch(error => {
        const signatureAlreadyCompleted = error?.code === "PADES_OK_KEP_FAILED";
        if (!signatureAlreadyCompleted) {
          $("#modal_eimza_pin, #btnModalPadesImzala, #btnModalImzaIade").prop("disabled", false);
          $("#modal_eimza_pin").val("").trigger("focus");
        }
        if (error?.code === "EIMZA_AGENT_UPDATE_REQUIRED") {
          updateModalPadesProgress(0, "E-İmza uygulamasını güncelleyin", "PAdES için Ersan E-İmza v1.2.0 gereklidir.", "error");
        } else if (error?.code === "PADES_OK_KEP_FAILED") {
          updateModalPadesProgress(100, "İmza tamamlandı, KEP gönderilemedi", error.message + " Evrak imzalı olarak kaydedildi.", "error");
          setTimeout(() => location.reload(), 2500);
        } else {
          updateModalPadesProgress(0, "E-İmza işlemi tamamlanamadı", error.message || "Bilinmeyen imza hatası.", "error");
        }
      });
  }

  $(document).on("click", ".evrak-e-imza-onayla, #btnModalPadesImzala", function (event) {
    event.preventDefault();
    event.stopPropagation();
    if (this.id !== "btnModalPadesImzala") {
      const $previewBadge = $(this).closest("tr").find(".evrak-imza-onizle").first();
      if ($previewBadge.length) $previewBadge.trigger("click");
      return;
    }
    startModalPadesSignature($(this).data("id"), $(this));
  });

  // E-İmza Geri Al
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

  // E-İmza İade Et
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
        inputValidator: value => (!value || value.trim().length < 5) ? 'Gerekçeyi en az 5 karakter olacak şekilde yazınız.' : undefined,
        didOpen: () => $(".swal2-container").css("z-index", 20000)
    }).then((result) => {
        if (!result.isConfirmed) return;
        $.post(api_url, { action: "evrak-e-imza-iade", id: button.data("id"), gerekce: result.value }, function (response) {
            if (response.status === "success") {
                Swal.fire({icon: 'success', title: 'İade Edildi', text: response.message, didOpen: () => $(".swal2-container").css("z-index", 20000)}).then(() => location.reload());
            } else {
                Swal.fire('Hata!', response.message, 'error');
            }
        }).fail(() => Swal.fire('Hata!', "Sunucu ile iletişim kurulamadı.", 'error'));
    });
  });

  // Popover E-İmza Akışı
  document.querySelectorAll("#evrakTable [data-onay-akis]").forEach(function (rozet) {
    new bootstrap.Popover(rozet, {
      container: "body",
      html: true,
      trigger: "hover focus",
      placement: "left",
      customClass: "onay-akis-popover",
      title: "E-İmza Onay Akışı",
      content: rozet.getAttribute("data-onay-akis")
    });
  });

  // DataTables Özel Filtreleri
  let imzaFiltresiAktif = false;
  let activeQuickFilter = "all";

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (settings.nTable.id !== "evrakTable") return true;
    if (settings.oFeatures && settings.oFeatures.bServerSide) return true;
    
    const row = $(settings.aoData[dataIndex].nTr);

    // İmzamı bekleyen filtresi
    if (imzaFiltresiAktif && row.attr("data-imza-bekliyor") !== "1") {
      return false;
    }

    // KPI Hızlı Filtreleri
    if (activeQuickFilter === "gelen") {
      return row.attr("data-tip") === "gelen";
    }
    if (activeQuickFilter === "giden") {
      return row.attr("data-tip") === "giden";
    }
    if (activeQuickFilter === "cevap_bekleyen") {
      return row.attr("data-tip") === "gelen" && row.attr("data-cevap") === "BEKLEMEDE";
    }

    return true;
  });

  // DataTable Başlatma
  let evrakDataTable = null;
  if ($("#evrakTable").length > 0) {
    const baseOptions = typeof getDatatableOptions === "function" ? getDatatableOptions() : {};
    const dtOptions = typeof applyLengthStateSave === "function" ? applyLengthStateSave({
      ...baseOptions,
      processing: true,
      serverSide: true,
      ajax: {
        url: api_url,
        type: "POST",
        data: function (data) {
          data.action = "evrak-listesi";
          data.quick_filter = activeQuickFilter;
          data.signature_only = imzaFiltresiAktif ? 1 : 0;
        }
      },
      order: [[2, "desc"]],
      drawCallback: function () {
        document.querySelectorAll("#evrakTable [data-onay-akis]").forEach(function (element) {
          if (!bootstrap.Popover.getInstance(element)) new bootstrap.Popover(element, {container: "body", html: true, trigger: "hover focus", placement: "left", customClass: "onay-akis-popover", title: "E-İmza Onay Akışı", content: element.getAttribute("data-onay-akis")});
        });
      },
      columnDefs: [
        { targets: 2, type: "date" },
        { targets: [0, 1, 8, 9, 10, 11], orderable: false }
      ]
    }) : {
      processing: true,
      serverSide: true,
      ajax: { url: api_url, type: "POST", data: function (data) { data.action = "evrak-listesi"; data.quick_filter = activeQuickFilter; data.signature_only = imzaFiltresiAktif ? 1 : 0; } },
      order: [[2, "desc"]],
      columnDefs: [
        { targets: 2, type: "date" },
        { targets: [0, 1, 8, 9, 10, 11], orderable: false }
      ],
      language: { url: "assets/libs/datatables.net/js/tr.json" }
    };

    evrakDataTable = $("#evrakTable").DataTable(dtOptions);
  }

  // KPI Hızlı Filtre Butonları
  $(".status-quick-filter").on("click", function () {
    $(".status-quick-filter").removeClass("active");
    $(this).addClass("active");
    activeQuickFilter = $(this).data("filter-tip") || "all";
    if (evrakDataTable) {
      evrakDataTable.draw();
    }
  });

  // İmza Filtresi Butonu
  $("#btnImzaFiltre").on("click", function () {
    imzaFiltresiAktif = !imzaFiltresiAktif;
    $(this)
      .toggleClass("btn-warning btn-outline-dark")
      .html(imzaFiltresiAktif
        ? '<i class="bx bx-x me-1"></i> Filtreyi Kaldır'
        : '<i class="bx bx-filter-alt me-1"></i> Sadece Bunları Göster');
    if (evrakDataTable) {
      evrakDataTable.draw();
    }
  });

  // Excel ve Yazdır Butonları
  $("#btnHeaderExportExcel, #btnDropdownExportExcel").on("click", function () {
    // Basit ve güvenli HTML tablosundan Excel indirme
    const table = document.getElementById("evrakTable");
    if (!table) return;
    
    // Geçici klon oluşturarak işlem sütununu çıkart
    const clone = table.cloneNode(true);
    $(clone).find("th:last-child, td:last-child").remove();
    
    const html = clone.outerHTML;
    const blob = new Blob(["\ufeff", html], { type: "application/vnd.ms-excel" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "evrak_listesi_" + new Date().toISOString().slice(0, 10) + ".xls";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });

  $("#btnHeaderPrint").on("click", function () {
    window.print();
  });

  // PDF Önizleme İşlemleri
  function formatImzaTarihi(value) {
    if (!value) return "";
    const parsed = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString("tr-TR");
  }

  function renderImzaPanel(evrak) {
    const signatures = Array.isArray(evrak.imza_akisi) ? evrak.imza_akisi : [];
    const signedCount = signatures.filter(item => item.durum === "onaylandi").length;
    $("#evrakImzaBelgeBaslik").text(evrak.konu || "Resmî yazı");
    $("#evrakImzaBelgeMeta").text("Evrak No: " + (evrak.evrak_no || "-") + " • " + signedCount + "/" + signatures.length + " imza");

    const signerHtml = signatures.map((item, index) => {
      const signed = item.durum === "onaylandi";
      const isCurrent = !signed && signatures.slice(0, index).every(previous => previous.durum === "onaylandi");
      const iconBoxClass = signed ? "bg-success text-white" : (isCurrent ? "bg-warning text-dark" : "bg-secondary-subtle text-secondary");
      const iconClass = signed ? "bx-check" : (isCurrent ? "bx-time-five" : "bx-user");
      const state = signed ? "İmzalandı" : (isCurrent && evrak.onay_durumu === "onay_bekliyor" ? "İmza bekleniyor" : "Sırasını bekliyor");
      const certificate = signed && item.sertifika_ozne
        ? '<div class="text-muted mt-1 text-break" style="font-size:10px" title="' + escapeEvrakHtml(item.sertifika_ozne) + '">Sertifika doğrulandı · ' + escapeEvrakHtml(item.imza_turu || "PAdES") + '</div>'
        : "";
      return '<div class="d-flex gap-2 mb-3"><span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 ' + iconBoxClass + '" style="width:30px;height:30px"><i class="bx ' + iconClass + '"></i></span>'
        + '<div class="min-w-0"><div class="fw-semibold text-dark font-size-12">' + escapeEvrakHtml(item.sira + ". " + item.adi_soyadi) + '</div>'
        + (item.unvan ? '<div class="text-muted font-size-11">' + escapeEvrakHtml(item.unvan) + '</div>' : '')
        + '<div class="' + (signed ? 'text-success' : (isCurrent ? 'text-warning' : 'text-muted')) + ' font-size-11">' + state
        + (item.onay_tarihi ? ' · ' + escapeEvrakHtml(formatImzaTarihi(item.onay_tarihi)) : '') + '</div>' + certificate + '</div></div>';
    }).join("") || '<div class="text-muted font-size-12">İmzacı kaydı bulunamadı.</div>';

    const events = [];
    if (evrak.olusturma_tarihi) events.push({label: "Evrak oluşturuldu", date: evrak.olusturma_tarihi, color: "primary"});
    if (signatures[0] && signatures[0].akisa_eklenme_tarihi) events.push({label: "E-imza onayına sunuldu", date: signatures[0].akisa_eklenme_tarihi, color: "info"});
    signatures.filter(item => item.onay_tarihi).forEach(item => events.push({label: item.sira + ". imza tamamlandı — " + item.adi_soyadi, date: item.onay_tarihi, color: "success"}));
    if (evrak.e_imza_iade_tarihi) events.push({label: "Evrak düzeltme için iade edildi" + (evrak.e_imza_iade_gerekcesi ? ": " + evrak.e_imza_iade_gerekcesi : ""), date: evrak.e_imza_iade_tarihi, color: "danger"});
    if (evrak.e_imza_onay_tarihi) events.push({label: "Tüm elektronik imzalar tamamlandı", date: evrak.e_imza_onay_tarihi, color: "success"});
    events.sort((a, b) => new Date(String(a.date).replace(" ", "T")) - new Date(String(b.date).replace(" ", "T")));
    const timelineHtml = events.map(event => '<div class="d-flex gap-2 mb-3"><span class="rounded-circle bg-' + event.color + ' flex-shrink-0 mt-1" style="width:9px;height:9px"></span><div><div class="text-dark font-size-11">' + escapeEvrakHtml(event.label) + '</div><div class="text-muted" style="font-size:10px">' + escapeEvrakHtml(formatImzaTarihi(event.date)) + '</div></div></div>').join("");
    $("#evrakImzaSignerList").html(signerHtml);
    $("#evrakImzaTimeline").html(timelineHtml || '<div class="text-muted font-size-12">İşlem tarihçesi bulunamadı.</div>');
    $("#evrakImzaOnayAction").toggleClass("d-none", !evrak.imza_sirasi_bende);
    $("#modal_eimza_pin").val("").removeClass("is-invalid").prop("disabled", false);
    $("#modalPadesProgress").addClass("d-none");
    updateModalPadesProgress(0, "İşlem hazırlanıyor", "");
    $("#modalPadesProgress").addClass("d-none");
    $("#btnModalPadesImzala").data("id", evrak.id || "").attr("data-id", evrak.id || "").prop("disabled", false);
    $("#btnModalImzaIade").data("id", evrak.id || "").attr("data-id", evrak.id || "").prop("disabled", false);
    $("#evrakImzaPanelLoader").addClass("d-none");
    $("#evrakImzaPanelContent").removeClass("d-none");
  }

  function showPdfFromRequest(request, withSignaturePanel) {
    clearPdfObjectUrl();
    $("#evrakImzaPanel").toggleClass("d-none", !withSignaturePanel).toggleClass("evrak-imza-panel-visible", !!withSignaturePanel);
    $("#evrakImzaPanelLoader").toggleClass("d-none", !withSignaturePanel);
    $("#evrakImzaPanelContent").addClass("d-none");
    if (withSignaturePanel) {
      $("#evrakImzaBelgeBaslik").text("İmza bilgileri yükleniyor");
      $("#evrakImzaBelgeMeta").empty();
    }
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

  $(document).on("click", ".evrak-pdf-goruntule", function () {
    showPdfFromRequest({ type: "GET", data: { id: $(this).data("id") } }, false);
  });

  $(document).on("click", ".evrak-imza-onizle", function (event) {
    event.preventDefault();
    event.stopPropagation();
    const id = $(this).data("id");
    const popover = bootstrap.Popover.getInstance(this);
    if (popover) popover.hide();
    showPdfFromRequest({ type: "GET", data: { id: id } }, true);
    $.post(api_url, { action: "evrak-detay", id: id }, function (response) {
      if (response.status === "success") renderImzaPanel(response.data || {});
      else throw new Error(response.message || "İmza bilgileri alınamadı.");
    }, "json").fail(function () {
      $("#evrakImzaPanelLoader").addClass("d-none");
      $("#evrakImzaSignerList").html('<div class="alert alert-danger font-size-12 mb-0">İmza bilgileri yüklenemedi.</div>');
      $("#evrakImzaTimeline").empty();
      $("#evrakImzaPanelContent").removeClass("d-none");
    });
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
    $("#evrakImzaPanel").addClass("d-none").removeClass("evrak-imza-panel-visible");
    $("#evrakImzaPanelContent").addClass("d-none");
    $("#evrakImzaOnayAction").addClass("d-none");
    $("#btnModalPadesImzala").data("id", "").attr("data-id", "").prop("disabled", false);
    $("#btnModalImzaIade").data("id", "").attr("data-id", "").prop("disabled", false);
    $("#modal_eimza_pin").val("").removeClass("is-invalid").prop("disabled", false);
    $("#modalPadesProgress").addClass("d-none");
  }

  $(document).on("hidden.bs.modal", "#evrakPdfModal", resetPdfModalState);

  // ==========================================
  // UETS / KEP İŞLEMLERİ
  // ==========================================

  function updateUetsSelectionSummary() {
    const selectedCount = $(".uets-incoming-check:checked").length;
    const availableCount = $(".uets-incoming-check:not(:disabled)").length;
    $("#uetsSelectionSummary").text(selectedCount + " / " + availableCount + " uygun evrak seçildi");
    $("#btnUetsSelectedImport").prop("disabled", selectedCount === 0);
    $("#uetsSelectAll").prop("checked", availableCount > 0 && selectedCount === availableCount);
  }

  function renderUetsIncomingRows(rows) {
    const $body = $("#uetsGelenSecimBody").empty();
    if (!Array.isArray(rows) || rows.length === 0) {
      $body.append($("<tr>").append($("<td>", {
        colspan: 5,
        class: "text-center text-muted py-5",
        text: "UETS'te aktarılabilecek gelen evrak bulunamadı.",
      })));
      updateUetsSelectionSummary();
      return;
    }

    rows.forEach(function (row) {
      const disabled = Boolean(row.aktarildi);
      const $check = $("<input>", {
        type: "checkbox",
        class: "form-check-input uets-incoming-check",
        value: row.selection_id || "",
        disabled: disabled,
        "aria-label": (row.konu || "UETS evrakı") + " seç",
      });
      const $status = $("<div>");
      if (row.aktarildi) {
        $status.append($("<span>", { class: "badge bg-success-subtle text-success border border-success-subtle", text: "Zaten aktarıldı" }));
      } else if (row.olasi_mukerrer) {
        const matchNo = row.eslesen_evrak && row.eslesen_evrak.evrak_no ? " (No: " + row.eslesen_evrak.evrak_no + ")" : "";
        $status.append($("<span>", { class: "badge bg-warning-subtle text-warning border border-warning-subtle", text: "Manuel kayıt eşleşmesi" }));
        $status.append($("<small>", { class: "d-block text-muted mt-1", text: "Aktarmadan önce kontrol edin" + matchNo }));
      } else {
        $status.append($("<span>", { class: "badge bg-primary-subtle text-primary border border-primary-subtle", text: row.durum || "Yeni" }));
      }

      const $sender = $("<div>").append($("<span>", { class: "d-block fw-semibold", text: row.gonderen || "-" }));
      if (row.gonderen_uets) {
        $sender.append($("<small>", { class: "text-muted font-monospace", text: row.gonderen_uets }));
      }
      const $tr = $("<tr>").toggleClass("table-warning", Boolean(row.olasi_mukerrer) && !disabled);
      $tr.append($("<td>", { class: "text-center" }).append($check));
      $tr.append($("<td>", { text: row.tarih || "-" }));
      $tr.append($("<td>").append($("<span>", { class: "fw-semibold", text: row.konu || "Konusuz Tebligat" })));
      $tr.append($("<td>").append($sender));
      $tr.append($("<td>").append($status));
      $body.append($tr);
    });
    updateUetsSelectionSummary();
  }

  $(document).on("change", ".uets-incoming-check", updateUetsSelectionSummary);
  $("#uetsSelectAll").on("change", function () {
    $(".uets-incoming-check:not(:disabled)").prop("checked", this.checked);
    updateUetsSelectionSummary();
  });

  // 1. UETS Senkronize Et: önce canlı listeyi getir, kullanıcı seçimini bekle
  $("#btnUetsSync").on("click", function () {
    Swal.fire({ title: "UETS Evrakları Alınıyor...", text: "Gelen evrak listesi kontrol ediliyor.", allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "uets-senkronize-et" },
      dataType: "json",
    }).done(function (res) {
      if (res.status !== "success") {
        Swal.fire("Hata", res.message || "UETS evrakları alınamadı.", "error");
        return;
      }
      const fetchErrors = Array.isArray(res.hatalar) ? res.hatalar.filter(Boolean) : [];
      if ((!Array.isArray(res.data) || res.data.length === 0) && fetchErrors.length) {
        Swal.fire("UETS Evrakları Alınamadı", fetchErrors.join("\n"), "error");
        return;
      }
      Swal.close();
      $("#uetsSelectAll").prop("checked", false);
      $("#uetsFetchWarning")
        .toggleClass("d-none", fetchErrors.length === 0)
        .text(fetchErrors.length ? "Bazı UETS durumları alınamadı: " + fetchErrors.join(" • ") : "");
      renderUetsIncomingRows(res.data || []);
      $("#modalUetsGelenSecim").modal("show");
    }).fail(function (xhr) {
      const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "UETS senkronizasyon servisine bağlanılamadı.";
      Swal.fire("Hata", message, "error");
    });
  });

  $("#btnUetsSelectedImport").on("click", function () {
    const selectionIds = $(".uets-incoming-check:checked").map(function () { return this.value; }).get();
    if (!selectionIds.length) return;
    const $button = $(this).prop("disabled", true);
    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "uets-secilenleri-aktar", selection_ids: selectionIds },
      dataType: "json",
    }).done(function (res) {
      if (res.status !== "success") {
        Swal.fire("Hata", res.message || "Seçilen evraklar aktarılamadı.", "error");
        return;
      }
      $("#modalUetsGelenSecim").modal("hide");
      Swal.fire("Aktarım Tamamlandı", res.message || "Seçilen UETS evrakları aktarıldı.", "success").then(() => location.reload());
    }).fail(function (xhr) {
      const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Aktarım sırasında sunucu hatası oluştu.";
      Swal.fire("Hata", message, "error");
    }).always(function () {
      $button.prop("disabled", false);
      updateUetsSelectionSummary();
    });
  });

  function pttKepFormData(action) {
    return {
      action: action,
      kep_adresi: $("#ptt_kep_adresi").val() || "",
      yetkili_tcno: $("#ptt_yetkili_tcno").val() || "",
      parola: $("#ptt_parola").val() || "",
      sifre: $("#ptt_sifre").val() || "",
      wsdl_url: $("#ptt_wsdl_url").val() || "",
      ortam: $("#ptt_ortam").val() || "prod",
    };
  }

  function loadPttKepSettings() {
    if (!$("#pttKepSettingsPane").length) return;
    $("#pttKepTestResult").addClass("d-none").empty();
    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "kep-ayarlari-getir" },
      dataType: "json",
    }).done(function (res) {
      if (res.status !== "success" || !res.data) return;
      const d = res.data;
      $("#ptt_kep_adresi").val(d.kep_adresi || "");
      // Maskeli TCKN hiçbir zaman yeniden sunucuya gönderilmez.
      $("#ptt_yetkili_tcno, #ptt_parola, #ptt_sifre").val("");
      $("#ptt_wsdl_url").val(d.wsdl_url || "https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl");
      $("#ptt_ortam").val(d.ortam || "prod").trigger("change");
      $("#pttKepTcHelpText").text(d.yetkili_tcno ? "Bu kullanıcı için kayıtlı TCKN: " + d.yetkili_tcno + " — değiştirmeyecekseniz boş bırakın." : "Bu kullanıcı için henüz yetkili TCKN kaydedilmemiş.");
      $("#pttKepParolaHelpText").text(d.has_secrets ? "Bu kullanıcıya ait parola kayıtlı; değiştirmeyecekseniz boş bırakın." : "Bu kullanıcı için henüz parola kaydedilmemiş.");
      $("#pttKepSifreHelpText").text(d.has_secrets ? "Bu kullanıcıya ait web servis şifresi kayıtlı; değiştirmeyecekseniz boş bırakın." : "Bu kullanıcı için henüz web servis şifresi kaydedilmemiş.");
      $("#pttKepBakiyeText").text(d.bakiye_kontor !== null && d.bakiye_kontor !== undefined ? "Kayıtlı Bakiye: " + d.bakiye_kontor + " " + (d.bakiye_cins || "Kontör") : "Kayıtlı Kontör: -");
      $("#pttKepSonSorguText").text(d.son_bakiye_sorgu ? "(Son: " + d.son_bakiye_sorgu + ")" : "");
    });
  }

  // 2. UETS Ayarları Modalı Açma
  $("#btnUetsSettings").on("click", function () {
    $("#uetsTestResult").addClass("d-none").html("");
    loadPttKepSettings();
    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "uets-ayarlari-getir" },
      dataType: "json",
      success: function (res) {
        if (res.status === "success" && res.data) {
          $("#uets_adresi").val(res.data.uets_adresi || "");
          $("#api_environment").val(res.data.api_environment || "prod").trigger("change");
          $("#uets_otomatik_sync").prop("checked", res.data.otomatik_senkronizasyon == 1);
          $("#api_key, #uets_api_key").val("");
          if (res.data.has_api_key) {
            $("#apiKeyHelpText").text("Mevcut anahtar kayıtlı: " + res.data.api_key_masked + " (Değiştirmeyecekseniz boş bırakınız)");
          } else {
            $("#apiKeyHelpText").text("Henüz API anahtarı tanımlanmamış.");
          }
          if (res.data.son_gelen_sync_zamani) {
            $("#uetsSonSyncText").text("Son Senkronizasyon: " + res.data.son_gelen_sync_zamani);
          }
          if (res.data.bakiye !== null && res.data.bakiye !== undefined) {
            $("#uetsBakiyeText").html("• <strong>Bakiye:</strong> " + res.data.bakiye + " Kredi");
          }
        }
        $("#modalUetsAyarlar").modal("show");
      },
    });
  });

  // Modal gösterildiğinde Select2 başlat
  $("#modalUetsAyarlar").on("shown.bs.modal", function () {
    if ($("#api_environment").length && !$("#api_environment").hasClass("select2-hidden-accessible")) {
      $("#api_environment").select2({
        dropdownParent: $("#modalUetsAyarlar"),
        width: "100%",
        minimumResultsForSearch: -1,
      });
    }
    if ($("#ptt_ortam").length && !$("#ptt_ortam").hasClass("select2-hidden-accessible")) {
      $("#ptt_ortam").select2({ dropdownParent: $("#modalUetsAyarlar"), width: "100%", minimumResultsForSearch: -1 });
    }
  });

  // Modal kapandığında şifre alanını gizli yap
  $("#modalUetsAyarlar").on("hidden.bs.modal", function () {
    const $input = $("#api_key").length ? $("#api_key") : $("#uets_api_key");
    $input.attr("type", "password");
    $("#btnToggleApiKeyVisibility").find("i").attr("class", "bx bx-show font-size-18");
    $("[data-ptt-password-target]").each(function () {
      $("#" + $(this).data("ptt-password-target")).attr("type", "password");
      $(this).find("i").attr("class", "bx bx-show font-size-18");
    });
  });

  $(document).on("click", "[data-ptt-password-target]", function () {
    const $input = $("#" + $(this).data("ptt-password-target"));
    const reveal = $input.attr("type") === "password";
    $input.attr("type", reveal ? "text" : "password");
    $(this).find("i").attr("class", reveal ? "bx bx-hide font-size-18" : "bx bx-show font-size-18");
  });

  $("#btnPttKepTestConnection").on("click", function () {
    const $btn = $(this), original = $btn.html(), $result = $("#pttKepTestResult");
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Test ediliyor...');
    $result.removeClass("d-none alert-success alert-danger").addClass("alert alert-info").text("PTT KEP servisine bağlanılıyor...");
    $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data: pttKepFormData("kep-baglanti-test"), dataType: "json" })
      .done(function (res) {
        const ok = res.status === "success";
        $result.removeClass("alert-info alert-success alert-danger").addClass("alert " + (ok ? "alert-success" : "alert-danger")).text(ok ? (res.message || "PTT KEP bağlantısı başarılı.") : (res.message || "Giriş bilgileri doğrulanamadı."));
      }).fail(function (xhr) {
        $result.removeClass("alert-info alert-success").addClass("alert alert-danger").text(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "PTT KEP servisine erişilemedi.");
      }).always(function () { $btn.prop("disabled", false).html(original); });
  });

  $("#btnPttKepCheckBalance").on("click", function () {
    const $btn = $(this), original = $btn.html();
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>');
    $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data: { action: "kep-bakiye-sorgula" }, dataType: "json" })
      .done(function (res) {
        if (res.status === "success") {
          $("#pttKepBakiyeText").text("Kayıtlı Bakiye: " + res.miktar + " " + (res.cins || "Kontör"));
          Swal.fire("KEP Bakiyesi", "Güncel bakiye: " + res.miktar + " " + (res.cins || "Kontör"), "info");
        } else Swal.fire("Bakiye Sorgulama Hatası", res.message || "Bakiye sorgulanamadı.", "warning");
      }).fail(function (xhr) {
        Swal.fire("Hata", xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Bakiye servisine erişilemedi.", "error");
      }).always(function () { $btn.prop("disabled", false).html(original); });
  });

  $("#formPttKepAyarlar").on("submit", function (e) {
    e.preventDefault();
    const $btn = $("#btnSavePttKepSettings");
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');
    $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data: pttKepFormData("kep-ayarlari-kaydet"), dataType: "json" })
      .done(function (res) {
        if (res.status === "success") {
          loadPttKepSettings();
          Swal.fire({ icon: "success", title: "Başarılı", text: res.message || "KEP ayarları kaydedildi.", timer: 1800, showConfirmButton: false });
        } else Swal.fire("Hata", res.message || "KEP ayarları kaydedilemedi.", "error");
      }).fail(function (xhr) {
        Swal.fire("Hata", xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "KEP ayarları kaydedilirken hata oluştu.", "error");
      }).always(function () { $btn.prop("disabled", false).html('<i class="bx bx-save me-1"></i> KEP Ayarlarını Kaydet'); });
  });

  // UETS Adresi 15 haneli girildiğinde tire ile formatla
  $("#uets_adresi").on("blur", function () {
    const raw = $(this).val().replace(/[^0-9]/g, "");
    if (raw.length === 15 && $(this).val().indexOf("-") === -1) {
      $(this).val(raw.substr(0, 5) + "-" + raw.substr(5, 5) + "-" + raw.substr(10, 5));
    }
  });

  // 3. API Anahtarı Göster / Gizle
  $("#btnToggleApiKeyVisibility").on("click", function (e) {
    e.preventDefault();
    const $input = $("#api_key").length ? $("#api_key") : $("#uets_api_key");
    const isPassword = $input.attr("type") === "password";
    $input.attr("type", isPassword ? "text" : "password");
    $(this).find("i").attr("class", isPassword ? "bx bx-hide font-size-18" : "bx bx-show font-size-18");
  });

  // 4. UETS Ayarları Kaydetme
  $("#formUetsAyarlar").on("submit", function (e) {
    e.preventDefault();
    const formData = $(this).serialize() + "&action=uets-ayarlari-kaydet";
    const $btn = $("#btnSaveUetsSettings");
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html('<i class="bx bx-save me-1"></i> Ayarları Kaydet');
        if (res.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: res.message || "UETS ayarları kaydedildi.",
            timer: 2000,
            showConfirmButton: false,
          });
          $("#modalUetsAyarlar").modal("hide");
        } else {
          Swal.fire({ icon: "error", title: "Hata", text: res.message });
        }
      },
      error: function () {
        $btn.prop("disabled", false).html('<i class="bx bx-save me-1"></i> Ayarları Kaydet');
        Swal.fire({ icon: "error", title: "Hata", text: "Ayarlar kaydedilirken hata oluştu." });
      },
    });
  });

  // 5. UETS Bağlantı Testi
  $("#btnUetsTestConnection").on("click", function () {
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Test ediliyor...');
    $("#uetsTestResult").removeClass("d-none alert-success alert-danger").addClass("alert alert-info").text("Bağlantı kuruluyor...");

    const apiKeyVal = ($("#api_key").length ? $("#api_key") : $("#uets_api_key")).val();
    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: {
        action: "uets-baglanti-test",
        api_key: apiKeyVal,
        api_environment: $("#api_environment").val(),
      },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html(originalText);
        if (res.status === "success" && res.data && res.data.success) {
          $("#uetsTestResult")
            .removeClass("alert-info alert-danger")
            .addClass("alert alert-success")
            .html(`<strong><i class="bx bx-check-circle me-1"></i> Başarılı:</strong> PTT UETS API bağlantısı sağlandı. Toplam Tebligat: ${res.data.total_count}, Okunmamış: ${res.data.unread_count}`);
        } else {
          $("#uetsTestResult")
            .removeClass("alert-info alert-success")
            .addClass("alert alert-danger")
            .html(`<strong><i class="bx bx-x-circle me-1"></i> Bağlantı Hatası:</strong> ${res.message || "Bağlantı kurulamadı."}`);
        }
      },
      error: function () {
        $btn.prop("disabled", false).html(originalText);
        $("#uetsTestResult")
          .removeClass("alert-info alert-success")
          .addClass("alert alert-danger")
          .text("Sunucuya ulaşılamadı.");
      },
    });
  });

  // 6. UETS Bakiye Sorgula
  $("#btnUetsCheckBalance").on("click", function () {
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Sorgulanıyor...');

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "uets-bakiye-sorgula" },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html(originalText);
        if (res.status === "success" && res.data) {
          const bal = res.data.balance !== undefined ? res.data.balance : "Bilinmiyor";
          $("#uetsBakiyeText").html(`• <strong>Bakiye:</strong> ${bal} Kredi`);
          Swal.fire({
            icon: "info",
            title: "UETS Bakiye Bilgisi",
            html: `<div class="font-size-14">Mevcut Bakiye: <strong>${bal}</strong> Kredi<br>${res.data.credit_limit ? "Kredi Limiti: " + res.data.credit_limit : ""}</div>`,
          });
        } else {
          Swal.fire({ icon: "error", title: "Hata", text: res.message || "Bakiye alınamadı." });
        }
      },
      error: function () {
        $btn.prop("disabled", false).html(originalText);
        Swal.fire({ icon: "error", title: "Hata", text: "Bakiye sorgulanamadı." });
      },
    });
  });

  // 7. Tebligat Delilleri Modalı Açma
  $(document).on("click", ".btn-uets-deliller", function () {
    const uetsId = $(this).data("uets-id");
    if (!uetsId) return;

    $("#delilModalUetsId").text(uetsId);
    $("#delilModalBarcode").text("-");
    $("#delilModalSender").text("-");
    $("#delilModalRecipient").text("-");
    $("#delilModalSubject").text("Yükleniyor...");
    $("#delilTimelineContainer").html('<div class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin fs-4"></i> Deliller yükleniyor...</div>');
    $("#btnDownloadEypPackage").attr("href", `views/evrak-takip/pdf.php?type=uets_eyp&uets_id=${encodeURIComponent(uetsId)}`);
    $("#modalUetsDeliller").modal("show");

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "uets-delil-listesi", uets_message_id: uetsId },
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          const t = res.tebligat || {};
          $("#delilModalBarcode").text(t.barcode || t.reference_id || "-");
          $("#delilModalSender").text(t.sender_name || t.sender_uets || "-");
          $("#delilModalRecipient").text(t.recipient_name || t.recipient_uets || "-");
          $("#delilModalSubject").text(t.subject || "-");

          const deliller = res.data || [];
          if (deliller.length === 0) {
            $("#delilTimelineContainer").html('<div class="alert alert-light border text-center text-muted py-3">Bu tebligata ait henüz delil kaydı oluşmamış.</div>');
            return;
          }

          let html = '<ul class="list-group list-group-flush border rounded-3">';
          deliller.forEach(function (d) {
            const dateStr = d.olay_zamani ? new Date(d.olay_zamani).toLocaleString("tr-TR") : "-";
            let eventBadge = '<span class="badge bg-secondary">Bilinmeyen</span>';
            let icon = "bx-info-circle";

            if (d.olay === "Acceptance") {
              eventBadge = '<span class="badge bg-primary-subtle text-primary">Sisteme Kabul Edildi</span>';
              icon = "bx-check";
            } else if (d.olay === "Delivery") {
              eventBadge = '<span class="badge bg-success-subtle text-success">Muhataba Teslim Edildi</span>';
              icon = "bx-down-arrow-circle";
            } else if (d.olay === "Retrieval") {
              eventBadge = '<span class="badge bg-info-subtle text-info">Muhatap Tarafından Açıldı / Okundu</span>';
              icon = "bx-envelope-open";
            } else if (d.olay === "5Days") {
              eventBadge = '<span class="badge bg-danger-subtle text-danger">5 Günlük Yasal Süre Doldu</span>';
              icon = "bx-time-five";
            }

            html += `
              <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                <div>
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bx ${icon} text-primary fs-5"></i>
                    <strong>${d.olay}</strong>
                    ${eventBadge}
                  </div>
                  <div class="font-size-12 text-muted">
                    <span>Olay Zamanı: ${dateStr}</span>
                    <span class="ms-2 font-monospace">ID: ${d.evidence_id}</span>
                  </div>
                </div>
                <div>
                  <a href="views/evrak-takip/pdf.php?type=uets_delil&evidence_id=${encodeURIComponent(d.evidence_id)}" 
                     class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" target="_blank">
                    <i class="bx bxs-file-pdf font-size-14"></i> Delil PDF İndir
                  </a>
                </div>
              </li>
            `;
          });
          html += "</ul>";
          $("#delilTimelineContainer").html(html);
        } else {
          $("#delilTimelineContainer").html(`<div class="alert alert-danger py-2">${res.message || "Deliller yüklenemedi."}</div>`);
        }
      },
      error: function () {
        $("#delilTimelineContainer").html('<div class="alert alert-danger py-2">Sunucu hatası: Deliller yüklenemedi.</div>');
      },
    });
  });

  // ---------- Özet kartlarını gizle/göster (Standardized) ----------
  const kepModal = $("#modalKepGonderisi");
  const LOCAL_EIMZA_AGENT_URL = "http://127.0.0.1:9191";
  let localEimzaAgentState = {
    available: false,
    cardDetected: false,
    data: null,
    timestamp: 0
  };

  function checkLocalEimzaAgent(onComplete) {
    if (localEimzaAgentState.available && localEimzaAgentState.cardDetected && (Date.now() - (localEimzaAgentState.timestamp || 0) < 8000)) {
      if (typeof onComplete === "function") onComplete(localEimzaAgentState);
      return;
    }
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 6000);

    fetch(LOCAL_EIMZA_AGENT_URL + "/status", { method: "GET", mode: "cors", signal: controller.signal })
      .then(res => res.json())
      .then(data => {
        clearTimeout(timeoutId);
        if (data && data.status === "success") {
          localEimzaAgentState = { available: true, cardDetected: true, data: data, timestamp: Date.now() };
        } else {
          localEimzaAgentState = { available: true, cardDetected: false, data: data, timestamp: Date.now() };
        }
        if (typeof onComplete === "function") onComplete(localEimzaAgentState);
      })
      .catch(() => {
        clearTimeout(timeoutId);
        localEimzaAgentState = { available: false, cardDetected: false, data: null, timestamp: Date.now() };
        if (typeof onComplete === "function") onComplete(localEimzaAgentState);
      });
  }

  function syncAgentFooter(status, text, detailsHtml, isConnected) {
    const $badge = $("#modalKepAgentBadge");
    const $details = $("#modalKepAgentDetails");

    $badge.removeClass("bg-success-subtle text-success bg-warning-subtle text-warning bg-secondary-subtle text-secondary border-success-subtle border-warning-subtle");
    if (isConnected) {
      $badge.addClass("bg-success-subtle text-success border-success-subtle").html('<i class="bx bx-check-circle me-1"></i> ' + escapeEvrakHtml(status));
    } else {
      $badge.addClass("bg-secondary-subtle text-secondary border").html('<i class="bx bx-unlink me-1"></i> ' + escapeEvrakHtml(status));
    }
    if (detailsHtml) $details.html(detailsHtml);
  }

  function updateKepAgentUI() {
    const $badge = $("#modalKepAgentBadge");
    const $details = $("#modalKepAgentDetails");

    $badge.removeClass("bg-success-subtle text-success bg-warning-subtle text-warning bg-secondary-subtle text-secondary border-success-subtle border-warning-subtle")
      .addClass("bg-secondary-subtle text-secondary border")
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> E-İmza Kontrol Ediliyor...');

    // 1. Önce yerel ajana (127.0.0.1:9191) bak
    checkLocalEimzaAgent(state => {
      if (state.available && state.cardDetected) {
        syncAgentFooter(
          "E-İmza Aktif",
          null,
          `<strong>${escapeEvrakHtml(state.data.owner_name || '')}</strong> <span class="text-muted font-size-11">(${escapeEvrakHtml(state.data.device_type || 'Akıllı Kart')})</span>`,
          true
        );
        return;
      }

      // 2. Yerel ajanda yoksa sunucudaki takılı donanımı kontrol et
      $.ajax({ url: api_url, type: "POST", data: { action: "eimza-kart-bilgisi" }, dataType: "json" })
        .done(function (card) {
          if (card && card.status === "success") {
            syncAgentFooter(
              "E-İmza Hazır",
              null,
              `<strong>${escapeEvrakHtml(card.owner_name || '')}</strong> <span class="text-muted font-size-11">(${escapeEvrakHtml(card.device_type || 'Akıllı Kart')})</span>`,
              true
            );
          } else {
            syncAgentFooter(
              "E-İmza Bekleniyor",
              null,
              state.available ? (state.data?.message || 'USB kartınızı takınız.') : 'Uygulama veya USB kart bekleniyor.',
              false
            );
          }
        })
        .fail(function () {
          syncAgentFooter(
            "E-İmza Bekleniyor",
            null,
            'Bilgisayarınızda yerel uygulama bekleniyor.',
            false
          );
        });
    });
  }

  $("#btnAgentHelpModal, #btnCheckAgentAgain").on("click", function (e) {
    e.preventDefault();
    if ($(this).attr("id") === "btnCheckAgentAgain") {
      updateKepAgentUI();
      $(this).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Kontrol Ediliyor...');
      setTimeout(() => {
        $(this).html('<i class="bx bx-refresh me-1"></i>Bağlantıyı Yeniden Kontrol Et');
        if (localEimzaAgentState.available && localEimzaAgentState.cardDetected) {
          $("#modalEimzaAgentHelp").modal("hide");
        }
      }, 1000);
    } else {
      $("#modalEimzaAgentHelp").modal("show");
    }
  });

  function scanKepEimzaCard($badge, $sub) {
    $badge.removeClass("bg-success-subtle text-success border-success-subtle bg-warning-subtle text-warning border-warning-subtle")
      .addClass("bg-info-subtle text-info border-info-subtle")
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> E-İmza Taranıyor...');

    // 1. Önce yerel ajanı kontrol et (Seçenek B)
    checkLocalEimzaAgent(state => {
      if (state.available && state.cardDetected) {
        $badge.removeClass("bg-info-subtle text-info border-info-subtle bg-warning-subtle text-warning border-warning-subtle")
          .addClass("bg-success-subtle text-success border-success-subtle")
          .html(`<i class="bx bx-chip me-1"></i> ${escapeEvrakHtml(state.data.device_type || 'Akıllı Kart')} (Yerel)`);
        $sub.html(`<strong>${escapeEvrakHtml(state.data.owner_name || '')}</strong> <span class="text-muted font-monospace font-size-11">(TC: ${escapeEvrakHtml(state.data.tcno || '')})</span> adına ait sertifika ile imzalanır.`);
        syncAgentFooter(
          "E-İmza Aktif",
          null,
          `<strong>${escapeEvrakHtml(state.data.owner_name || '')}</strong> <span class="text-muted font-size-11">(${escapeEvrakHtml(state.data.device_type || 'Akıllı Kart')})</span>`,
          true
        );
        return;
      }

      // 2. Yerel ajanda kart yoksa veya ajan kapalıysa sunucuyu sorgula
      $.ajax({ url: api_url, type: "POST", data: { action: "eimza-kart-bilgisi" }, dataType: "json" }).done(function (card) {
        if (card && card.status === "success") {
          $badge.removeClass("bg-info-subtle text-info border-info-subtle")
            .addClass("bg-success-subtle text-success border-success-subtle")
            .html(`<i class="bx bx-chip me-1"></i> ${escapeEvrakHtml(card.device_type || 'Akıllı Kart')} Takılı`);
          $sub.html(`<strong>${escapeEvrakHtml(card.owner_name || '')}</strong> <span class="text-muted font-monospace font-size-11">(TC: ${escapeEvrakHtml(card.tcno || '')})</span> adına ait sertifika ile imzalanır.`);
          syncAgentFooter(
            "E-İmza Hazır",
            null,
            `<strong>${escapeEvrakHtml(card.owner_name || '')}</strong> <span class="text-muted font-size-11">(${escapeEvrakHtml(card.device_type || 'Akıllı Kart')})</span>`,
            true
          );
        } else {
          $badge.removeClass("bg-info-subtle text-info border-info-subtle")
            .addClass("bg-warning-subtle text-warning border-warning-subtle")
            .html('<i class="bx bx-error-circle me-1"></i> Cihaz Bulunamadı');
          if (state.available) {
            $sub.text(state.data?.message || 'Lütfen USB E-İmza cihazınızı bilgisayarınıza takınız.');
          } else {
            $sub.html('E-İmza cihazı bulunamadı. <a href="downloads/ErsanEImza.jar" download class="text-primary fw-semibold"><i class="bx bxs-download me-1"></i>Uygulamayı İndir (.jar)</a> veya <a href="javascript:void(0)" id="linkOpenAgentHelpInSwal" class="text-primary fw-semibold">Nasıl başlatılır?</a>');
            $("#linkOpenAgentHelpInSwal").on("click", function () {
              $("#modalEimzaAgentHelp").modal("show");
            });
          }
          syncAgentFooter("E-İmza Bekleniyor", null, "USB kartınızı takınız.", false);
        }
      }).fail(function (xhr) {
        $badge.removeClass("bg-info-subtle text-info border-info-subtle")
          .addClass("bg-warning-subtle text-warning border-warning-subtle")
          .html('<i class="bx bx-error-circle me-1"></i> Cihaz Kontrol Edilemedi');
        $sub.text(xhr.responseJSON?.message || "E-imza cihazı sorgulanamadı.");
      });
    });
  }

  function parseModalKepRecipients() {
    return [...new Set(String($("#modal_kep_alicilar").val() || "").split(/[;,\n\r]+/).map(item => item.trim()).filter(Boolean))];
  }

  function formatKepBytes(bytes) {
    if (!bytes) return "0 KB";
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    return (bytes / 1024).toFixed(2) + " KB";
  }

  function loadKepAddressBook() {
    const $select = $("#modal_kep_adres_defteri");
    if (!$select.length) return;
    $.ajax({ url: api_url, type: "POST", data: { action: "kep-adres-defteri-listele" }, dataType: "json" }).done(function (res) {
      const current = $select.val();
      $select.empty().append(new Option("Kayıtlı KEP adresi seçin", "", true, false));
      if (res.status === "success") {
        (res.data || []).forEach(item => $select.append(new Option(item.kep_adresi, item.kep_adresi, false, item.kep_adresi === current)));
      }
      $select.trigger("change.select2");
    });
  }

  let approvedKepEvrakId = "";

  $(document).on("click", ".btn-approved-kep-send", function (event) {
    event.preventDefault();
    event.stopPropagation();
    approvedKepEvrakId = String($(this).data("id") || "");
    if (!approvedKepEvrakId) return;
    $("#btnYeniKepGonderisi").trigger("click");
    const subject = String($(this).data("subject") || "Resmî Yazı");
    $("#modalKepGonderisiLabel").text("Onaylı Evrakı KEP ile Gönder");
    $("#modalKepGonderisiLabel").next("div").text("Onaylı üst yazı PDF’i ve kayıtlı ekler değişmeden gönderilir");
    $("#modal_kep_konu").val(subject).prop("readonly", true);
    $("#modal_kep_ileti_detayi").val("Onaylı giden evrak").prop("readonly", true);
    if ($.fn.summernote && $("#modal_kep_icerik").next(".note-editor").length) {
      $("#modal_kep_icerik").summernote("code", "İlgili yazı ektedir.");
      $("#modal_kep_icerik").summernote("disable");
    } else {
      $("#modal_kep_icerik").val("İlgili yazı ektedir.").prop("readonly", true);
    }
    $("#modal_kep_ekler").closest(".border").addClass("d-none");
    $("#modalKepEkOzeti").text("Onaylı evrak dosyaları");
  });

  $("#btnYeniKepGonderisi").on("click", function () {
    const form = $("#formKepGonderisi")[0];
    if (!form) return;
    form.reset();
    if (!approvedKepEvrakId) {
      $("#modalKepGonderisiLabel").text("KEP İletisi Oluştur");
      $("#modalKepGonderisiLabel").next("div").text("PTT KEP üzerinden doğrudan güvenli ileti gönderin");
      $("#modal_kep_konu, #modal_kep_ileti_detayi, #modal_kep_icerik").prop("readonly", false);
      $("#modal_kep_ekler").closest(".border").removeClass("d-none");
    }
    $("#modalKepDogrulamaSonuc").empty();
    $("#modalKepEkOzeti").text("0 dosya · 0 KB");
    $("#modal_kep_ileti_tipi").val("standart");
    $("#kepTipStandart").prop("checked", true);
    loadKepAddressBook();
    if ($.fn.summernote) {
      if (!$("#modal_kep_icerik").next(".note-editor").length) {
        $("#modal_kep_icerik").summernote({ height: 180, lang: "tr-TR", placeholder: "İleti metnini yazınız...", toolbar: [["style", ["bold", "italic", "underline", "clear"]], ["para", ["ul", "ol", "paragraph"]], ["insert", ["link"]], ["view", ["codeview"]]] });
      } else {
        $("#modal_kep_icerik").summernote("code", "");
      }
    }
    kepModal.modal("show");
    updateKepAgentUI();
    const balanceBadge = $("#modalKepBalanceBadge");
    const sendButton = $("#btnModalKepGonder").prop("disabled", true).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
    if (sendButton.data("settings-ready") === 1 || String(sendButton.data("settings-ready")) === "1") {
      balanceBadge.removeClass("bg-success-subtle text-success bg-danger-subtle text-danger bg-secondary-subtle text-secondary").addClass("bg-info-subtle text-info").html('<i class="bx bx-loader-alt bx-spin me-1"></i>Bağlantı kontrol ediliyor');
      $.post(api_url, { action: "kep-bakiye-sorgula" }, function (response) {
        if (response.status === "success") {
          balanceBadge.removeClass("bg-info-subtle text-info bg-danger-subtle text-danger").addClass("bg-success-subtle text-success").text(response.miktar + " " + (response.cins || "Kontör")).attr("title", "Canlı PTT KEP sorgusu başarılı");
          sendButton.prop("disabled", false);
        } else {
          balanceBadge.removeClass("bg-info-subtle text-info bg-success-subtle text-success").addClass("bg-danger-subtle text-danger").text("Bağlantı hatası").attr("title", response.message || "PTT KEP kimlik doğrulaması başarısız");
          $("#modalKepDogrulamaSonuc").html('<div class="alert alert-danger py-2 mb-0"><i class="bx bx-lock-alt me-1"></i>' + $("<div>").text(response.message || "PTT KEP kimlik bilgileri geçersiz.").html() + ' Ayarlardan parola ve şifreyi yeniden kaydedin.</div>');
        }
      }, "json").fail(function (xhr) {
        balanceBadge.removeClass("bg-info-subtle text-info").addClass("bg-danger-subtle text-danger").text("Bağlantı hatası");
        $("#modalKepDogrulamaSonuc").html('<div class="alert alert-danger py-2 mb-0">' + $("<div>").text(xhr.responseJSON?.message || "PTT KEP servisine bağlanılamadı.").html() + '</div>');
      });
    }
  });

  kepModal.on("shown.bs.modal", function () {
    const addressBook = $("#modal_kep_adres_defteri");
    if (addressBook.length && !addressBook.data("select2")) addressBook.select2({ width: "100%", dropdownParent: kepModal, placeholder: "Kayıtlı KEP adresi seçin", allowClear: true });
  });

  $("input[name='modal_kep_ileti_tipi_secim']").on("change", function () {
    $("#modal_kep_ileti_tipi").val(this.value);
  });

  $("#modal_kep_adres_defteri").on("change", function () {
    const selected = String($(this).val() || "").trim();
    if (!selected) return;
    const addresses = parseModalKepRecipients();
    if (!addresses.includes(selected)) addresses.push(selected);
    $("#modal_kep_alicilar").val(addresses.join("; "));
    $(this).val("").trigger("change.select2");
  });

  $("#modal_kep_ekler").on("change", function () {
    const files = Array.from(this.files || []);
    const total = files.reduce((sum, file) => sum + file.size, 0);
    $("#modalKepEkOzeti").text(files.length + " dosya · " + formatKepBytes(total));
  });

  $("#btnModalKepAliciDogrula").on("click", function () {
    const addresses = parseModalKepRecipients();
    const result = $("#modalKepDogrulamaSonuc").empty();
    if (!addresses.length) return result.html('<span class="text-danger">En az bir KEP adresi giriniz.</span>');
    const button = $(this).prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin"></i> Doğrulanıyor');
    const requests = addresses.map(address => new Promise(resolve => {
      $.ajax({
        url: api_url,
        type: "POST",
        data: { action: "kep-alici-dogrula", alici_kep: address },
        dataType: "json",
      }).done(response => resolve({ address, response }))
        .fail(xhr => resolve({
          address,
          response: {
            status: "error",
            message: xhr.responseJSON?.message || "PTT KEP rehber servisine ulaşılamadı.",
          },
        }));
    }));
    Promise.all(requests).then(function (items) {
      items.forEach(item => {
        const ok = item.response?.status === "success" && item.response?.exist && item.response?.suitable;
        const row = $("<div>").addClass(ok ? "text-success" : "text-warning");
        $("<i>").addClass("bx me-1 " + (ok ? "bx-check-circle" : "bx-error-circle")).appendTo(row);
        $("<span>").addClass("font-monospace").text(item.address).appendTo(row);
        row.append(document.createTextNode(" — " + (ok ? "Doğrulandı" : (item.response?.message || "Rehberde bulunamadı"))));
        result.append(row);
      });
      if (items.some(item => item.response?.adres_defterine_kaydedildi)) loadKepAddressBook();
      button.prop("disabled", false).html('<i class="bx bx-search"></i> Doğrula');
    }).catch(function () {
      result.html('<span class="text-danger">PTT KEP rehber sorgusu tamamlanamadı.</span>');
      button.prop("disabled", false).html('<i class="bx bx-search"></i> Doğrula');
    });
  });

  $("#formKepGonderisi").on("submit", function (event) {
    event.preventDefault();
    const addresses = parseModalKepRecipients();
    const subject = $("#modal_kep_konu").val().trim();
    const content = $.fn.summernote && $("#modal_kep_icerik").next(".note-editor").length ? $("#modal_kep_icerik").summernote("code") : $("#modal_kep_icerik").val();
    if (!addresses.length || addresses.some(address => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address))) return Swal.fire("Eksik Bilgi", "Geçerli bir alıcı KEP adresi giriniz.", "warning");
    if (!subject) return Swal.fire("Eksik Bilgi", "İleti konusunu giriniz.", "warning");
    if (!String(content).replace(/<[^>]*>/g, "").trim()) return Swal.fire("Eksik Bilgi", "İleti metnini giriniz.", "warning");

    Swal.fire({
      target: document.getElementById("modalKepGonderisi"),
      title: "E-İmza ile Gönder",
      html: '<div class="text-start">' +
              '<div class="small text-muted mb-2">Alıcı: <span class="font-monospace">' + escapeEvrakHtml(addresses.join(", ")) + '</span></div>' +
              '<div class="d-flex align-items-center justify-content-between gap-2 p-2 rounded bg-light mb-3">' +
                '<span id="kepSignCardText" class="small text-muted">E-imza cihazı taranıyor...</span>' +
                '<span id="kepSignCardBadge" class="badge bg-info-subtle text-info"><i class="bx bx-loader-alt bx-spin me-1"></i> Taranıyor</span>' +
              '</div>' +
              '<label for="kepSignPin" class="form-label fw-semibold">E-İmza Kart PIN Kodu</label>' +
              '<input type="password" id="kepSignPin" class="form-control" maxlength="20" autocomplete="off" placeholder="PIN kodunuzu giriniz">' +
              '<div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">' +
                '<span class="small text-muted"><i class="bx bx-shield-check text-success me-1"></i>PIN kaydedilmez.</span>' +
                '<a href="downloads/ErsanEImza.jar" download class="text-primary font-size-11 fw-semibold text-decoration-none" title="Java E-İmza Uygulamasını İndir">' +
                  '<i class="bx bxs-download me-1"></i>Uygulamayı İndir (.jar)' +
                '</a>' +
              '</div>' +
            '</div>',
      showCancelButton: true,
      confirmButtonText: '<i class="bx bx-shield-quarter me-1"></i> PIN ile İmzala ve Gönder',
      cancelButtonText: "Vazgeç",
      confirmButtonColor: "#34c38f",
      focusConfirm: false,
      didOpen: function () {
        scanKepEimzaCard($("#kepSignCardBadge"), $("#kepSignCardText"));
        $("#kepSignPin").trigger("focus");
      },
      preConfirm: function () {
        const pin = String($("#kepSignPin").val() || "").trim();
        if (!pin) { Swal.showValidationMessage("E-imza PIN kodunu giriniz."); return false; }
        return pin;
      },
    }).then(answer => {
      if (!answer.isConfirmed) return;
      const pin = answer.value || "";
      const sendButton = $("#btnModalKepGonder").prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>Gönderiliyor...');

      // Seçenek B: Yerel E-İmza Ajanı üzerinden imzalama
      if (localEimzaAgentState.available && localEimzaAgentState.cardDetected) {
        Swal.fire({
          target: document.getElementById("modalKepGonderisi"),
          title: "PTT KEP İletisi Gönderiliyor",
          html: `<div class="p-3 text-center">
                   <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                   <div id="kepAgentFlowStep" class="fw-semibold font-size-14 mb-1">1/3: PTT KEP MIME paketi hazırlanıyor...</div>
                   <div class="small text-muted" id="kepAgentFlowSub">İleti içeriği ve ekler PTT sunucusuna aktarılıyor</div>
                 </div>`,
          allowOutsideClick: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading()
        });

        const mimeData = new FormData();
        mimeData.append("action", "kep-mime-hazirla");
        if (approvedKepEvrakId) {
          mimeData.append("evrak_id", approvedKepEvrakId);
          mimeData.append("alici_kep", addresses.join(";"));
          mimeData.append("paket_turu", "Standart");
        } else {
          mimeData.append("kep_alicilar", addresses.join(";"));
        }
        mimeData.append("kep_konu", subject);
        mimeData.append("kep_icerik", content);
        mimeData.append("kep_ileti_tipi", $("#modal_kep_ileti_tipi").val() || "standart");
        mimeData.append("kep_ileti_detayi", $("#modal_kep_ileti_detayi").val() || "");
        mimeData.append("kep_paket_turu", "Standart");
        if (!approvedKepEvrakId) Array.from($("#modal_kep_ekler")[0].files || []).forEach(file => mimeData.append("kep_ekler[]", file, file.name));

        // 1. Adım: MIME paketi hazırla
        $.ajax({
          url: api_url,
          type: "POST",
          data: mimeData,
          contentType: false,
          processData: false,
          dataType: "json"
        }).done(mimeRes => {
          if (mimeRes.status !== "success" || !mimeRes.eHash || !mimeRes.temp_id) {
            Swal.fire({
              target: document.getElementById("modalKepGonderisi"),
              icon: "error",
              title: "MIME Paketi Hatası",
              text: mimeRes.message || "PTT KEP paketi hazırlanamadı."
            });
            sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
            return;
          }

          // 2. Adım: Yerel USB Token'dan CAdES-BES imzası al
          $("#kepAgentFlowStep").text("2/3: Akıllı kart üzerinde CAdES-BES imzası üretiliyor...");
          $("#kepAgentFlowSub").text("SafeNet / Akis / E-Tuğra cihazınız PIN ile imzayı üretiyor...");

          fetch(LOCAL_EIMZA_AGENT_URL + "/sign", {
            method: "POST",
            mode: "cors",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ hash: mimeRes.eHash, pin: pin })
          })
          .then(res => res.json())
          .then(signRes => {
            if (signRes.status !== "success" || !signRes.signature) {
              Swal.fire({
                target: document.getElementById("modalKepGonderisi"),
                icon: "error",
                title: "E-İmza Hatası",
                text: signRes.message || "Akıllı kart imzalamayı reddetti."
              });
              sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
              return;
            }

            // 3. Adım: İmzalı paketi PTT KEP'e sevk et
            $("#kepAgentFlowStep").text("3/3: İmzalı paket PTT KEP ağına sevk ediliyor...");
            $("#kepAgentFlowSub").text("PTT alıcı sunucusuna teslimat yapılıyor...");

            $.ajax({
              url: api_url,
              type: "POST",
              data: {
                action: "kep-smime-sevk",
                temp_id: mimeRes.temp_id,
                mesajid: mimeRes.mesajid,
                signature: signRes.signature
              },
              dataType: "json"
            }).done(sendRes => {
              if (sendRes.status !== "success") {
                Swal.fire({
                  target: document.getElementById("modalKepGonderisi"),
                  icon: "error",
                  title: "PTT KEP Gönderim Hatası",
                  text: sendRes.message || "İleti PTT KEP servisi tarafından kabul edilmedi."
                });
                sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
                return;
              }

              const trackingCode = String(sendRes.kep_id || sendRes.takip_kodu || "").trim();
              kepModal.modal("hide");
              Swal.fire({
                title: "KEP İletisi Başarıyla Gönderildi!",
                icon: "success",
                html: trackingCode
                  ? '<div class="text-muted mb-2">PTT Takip Kodu (KepId)</div><code class="d-block p-2 bg-light rounded text-primary text-break font-monospace">' + escapeEvrakHtml(trackingCode) + '</code><div class="mt-2 text-success small"><i class="bx bx-check-double me-1"></i> Resmi CAdES-BES imzası doğrulandı, PTT tarafından kabul edildi.</div>'
                  : '<div class="text-warning">Gönderim tamamlandı; takip kodu kayda işlendi.</div>',
                confirmButtonText: "Tamam"
              }).then(() => location.reload());
            }).fail(xhr => {
              Swal.fire({
                target: document.getElementById("modalKepGonderisi"),
                icon: "error",
                title: "Gönderim Hatası",
                text: xhr.responseJSON?.message || "PTT KEP servisine bağlanılamadı."
              });
              sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
            });
          })
          .catch(err => {
            Swal.fire({
              target: document.getElementById("modalKepGonderisi"),
              icon: "error",
              title: "Yerel Ajan Hatası",
              text: "Yerel E-İmza Ajanı ile iletişim kurulamadı: " + err.message
            });
            sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
          });
        }).fail(xhr => {
          Swal.fire({
            target: document.getElementById("modalKepGonderisi"),
            icon: "error",
            title: "MIME Hazırlama Hatası",
            text: xhr.responseJSON?.message || "PTT KEP servisine bağlanılamadı."
          });
          sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder');
        });

      } else {
        // Fallback: Yerel ajan bağlı değilse sunucu üzerinden klasik gönderim
        const data = new FormData();
        data.append("action", approvedKepEvrakId ? "kep-gonder" : "kep-dogrudan-gonder");
        if (approvedKepEvrakId) { data.append("evrak_id", approvedKepEvrakId); data.append("alici_kep", addresses.join(";")); data.append("paket_turu", "Standart"); }
        else data.append("kep_alicilar", addresses.join(";"));
        data.append("kep_konu", subject); data.append("kep_icerik", content);
        data.append("kep_ileti_tipi", $("#modal_kep_ileti_tipi").val() || "standart"); data.append("kep_ileti_detayi", $("#modal_kep_ileti_detayi").val() || ""); data.append("kep_paket_turu", "Standart");
        data.append("eimza_pin", pin);
        if (!approvedKepEvrakId) Array.from($("#modal_kep_ekler")[0].files || []).forEach(file => data.append("kep_ekler[]", file, file.name));
        $.ajax({ url: api_url, type: "POST", data, contentType: false, processData: false, dataType: "json" })
          .done(response => {
            if (response.status !== "success") return Swal.fire("Gönderim Hatası", response.message || "KEP gönderilemedi.", "error");
            const trackingCode = String(response.kep_id || response.takip_kodu || "").trim();
            kepModal.modal("hide");
            Swal.fire({
              title: "Gönderildi",
              icon: "success",
              html: trackingCode
                ? '<div class="text-muted mb-2">PTT Takip Kodu</div><code class="d-block p-2 bg-light rounded text-primary text-break">' + escapeEvrakHtml(trackingCode) + "</code>"
                : '<div class="text-warning">Gönderim tamamlandı; takip kodu kayda işlendi.</div>',
              confirmButtonText: "Tamam",
            }).then(() => location.reload());
          })
          .fail(xhr => Swal.fire("Gönderim Hatası", xhr.responseJSON?.message || "PTT KEP servisine bağlanılamadı.", "error"))
          .always(() => sendButton.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder'));
      }
    });
  });

  kepModal.on("hidden.bs.modal", function () {
    approvedKepEvrakId = "";
    $("#modal_kep_konu, #modal_kep_ileti_detayi, #modal_kep_icerik").prop("readonly", false);
    if ($.fn.summernote && $("#modal_kep_icerik").next(".note-editor").length) $("#modal_kep_icerik").summernote("enable");
    $("#modal_kep_ekler").closest(".border").removeClass("d-none");
  });

  const SUMMARY_STATE_KEY = "evrak_summary_cards_state";

  function setSummaryCardsVisibility(visible) {
    document.documentElement.classList.toggle("evrak-summary-hidden", !visible);
    $("#btnToggleSummaryCards")
      .attr("aria-expanded", visible ? "true" : "false")
      .attr("title", visible ? "Özet Kartları Gizle" : "Özet Kartları Göster")
      .find("i")
      .attr("class", visible ? "bx bx-chevron-up" : "bx bx-chevron-down");
  }

  $("#btnToggleSummaryCards").on("click", function (e) {
    e.preventDefault();
    const isCurrentlyHidden = document.documentElement.classList.contains("evrak-summary-hidden");
    const shouldShow = isCurrentlyHidden;
    setSummaryCardsVisibility(shouldShow);
    try {
      localStorage.setItem(SUMMARY_STATE_KEY, shouldShow ? "visible" : "hidden");
    } catch (e) {}
  });

  // İlk yüklemedeki buton ikonu senkronizasyonu
  (function initSummaryCardsState() {
    const isHidden = localStorage.getItem(SUMMARY_STATE_KEY) === "hidden";
    setSummaryCardsVisibility(!isHidden);
  })();

  // --- KEP İletisi ve Delil Önizleme Modalı ---
  function ensureKepPreviewModalInBody() {
    const modalEl = $("#modalKepOnizleme");
    if (modalEl.length > 0 && modalEl.parent().prop("tagName") !== "BODY") {
      modalEl.appendTo("body");
    }
  }
  ensureKepPreviewModalInBody();

  let activeKepPreviewEvrakId = null;
  let activeKepPreviewKepId = null;

  function formatFileSize(bytes) {
    if (!bytes || isNaN(bytes)) return "";
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    return (bytes / 1024).toFixed(1) + " KB";
  }

  function renderKepPreview(data) {
    activeKepPreviewEvrakId = data.evrak_id || null;
    activeKepPreviewKepId = data.kep_id || null;

    // Üst Başlık & Rozet
    const badge = data.durum_rozeti || { ad: data.durum || "Gönderildi", badge: "bg-primary-subtle text-primary border-primary-subtle" };
    $("#kepPreviewDurumBadge").attr("class", "badge px-3 py-1 font-size-12 rounded-pill fw-semibold border " + badge.badge).text(badge.ad);

    // Üst Bilgiler
    $("#kepPreviewGonderen").text(data.gonderen_kep || "-");

    // Alıcılar
    const alicilarEl = $("#kepPreviewAlicilar").empty();
    if (Array.isArray(data.alici_listesi) && data.alici_listesi.length > 0) {
      data.alici_listesi.forEach(addr => {
        alicilarEl.append($("<span>").addClass("badge bg-info-subtle text-info border border-info-subtle font-monospace font-size-11").text(addr));
      });
    } else if (data.alici_kep) {
      alicilarEl.append($("<span>").addClass("badge bg-info-subtle text-info border border-info-subtle font-monospace font-size-11").text(data.alici_kep));
    } else {
      alicilarEl.text("-");
    }

    $("#kepPreviewKepId").text(data.kep_id || "-").attr("title", data.kep_id || "");
    $("#kepPreviewTarih").text(data.tarih || "-");
    $("#kepPreviewPaketTuru").text(data.paket_turu || "Standart");

    // İleti Sekmesi
    $("#kepPreviewKonu").text(data.konu || "-");
    const icerikHtml = data.icerik ? String(data.icerik) : "<div class='text-muted fst-italic'>İleti metni bulunmuyor.</div>";
    $("#kepPreviewIcerik").html(icerikHtml);

    // Ekler
    const eklerWrapper = $("#kepPreviewEklerWrapper");
    const eklerListesi = $("#kepPreviewEklerListesi").empty();
    if (Array.isArray(data.ekler) && data.ekler.length > 0) {
      $("#kepPreviewEkSayisi").text(data.ekler.length);
      data.ekler.forEach(file => {
        const sizeStr = file.boyut ? formatFileSize(file.boyut) : "";
        const col = $("<div>").addClass("col-md-6 col-12");
        const card = $("<div>").addClass("border rounded-3 p-2 bg-light d-flex align-items-center justify-content-between gap-2");
        const fileInfo = $("<div>").addClass("d-flex align-items-center gap-2 min-w-0");
        fileInfo.append($("<span>").addClass("badge bg-primary-subtle text-primary p-2 rounded-circle flex-shrink-0").append($("<i>").addClass("bx bx-file font-size-16")));
        const textWrapper = $("<div>").addClass("min-w-0");
        textWrapper.append($("<div>").addClass("fw-bold font-size-12 text-truncate").text(file.dosya_adi));
        if (sizeStr) textWrapper.append($("<div>").addClass("text-muted font-size-11").text(sizeStr));
        fileInfo.append(textWrapper);
        const actionBtn = $("<a>").addClass("btn btn-sm btn-subtle-primary flex-shrink-0")
          .attr({ href: file.dosya_yolu, target: "_blank", title: "Dosyayı İndir" })
          .append($("<i>").addClass("bx bx-download"));
        card.append(fileInfo, actionBtn).appendTo(col);
        eklerListesi.append(col);
      });
      eklerWrapper.removeClass("d-none");
    } else {
      eklerWrapper.addClass("d-none");
    }

    // Deliller Sekmesi
    const deliller = Array.isArray(data.deliller) ? data.deliller : [];
    $("#kepPreviewDelilSayisiBadge").text(deliller.length);
    const delilListEl = $("#kepPreviewDelillerListesi").empty();

    if (deliller.length === 0) {
      delilListEl.html('<div class="text-center py-4 text-muted font-size-13"><i class="bx bx-info-circle me-1"></i> Henüz kayıtlı delil bulunmuyor. Yukarıdaki "PTT\'den Delilleri Güncelle" butonuna tıklayarak PTT sistemini sorgulayabilirsiniz.</div>');
    } else {
      deliller.forEach(delil => {
        const itemCard = $("<div>").addClass("card border rounded-3 p-3 shadow-none bg-white mb-2");
        const headerRow = $("<div>").addClass("d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2");

        const titleGroup = $("<div>").addClass("d-flex align-items-center gap-2");
        titleGroup.append($("<span>").addClass("badge rounded-circle p-2 " + (delil.badge || "bg-primary-subtle text-primary")).append($("<i>").addClass((delil.icon || "bx bx-check") + " font-size-16")));
        titleGroup.append($("<div>").append($("<strong>").addClass("font-size-13 text-dark").text(delil.tur_adi || "PTT Delili"), $("<div>").addClass("text-muted font-size-11").text("Sunucu: " + (delil.kephs || "PTT KEPHS") + " • Zaman: " + (delil.olay_zamani || "-"))));

        const actionGroup = $("<div>").addClass("d-flex align-items-center gap-1");
        const downloadXmlUrl = "views/evrak-takip/api.php?action=kep-delil-indir&delil_id=" + encodeURIComponent(delil.delil_id) + "&download=1";
        actionGroup.append($("<a>").addClass("btn btn-sm btn-outline-primary font-size-11 px-2 py-1").attr({ href: downloadXmlUrl, target: "_blank", title: "ETSI REM Resmi Delil XML Dosyasını İndir" }).append($("<i>").addClass("bx bx-download me-1"), "XML Delili İndir"));

        headerRow.append(titleGroup, actionGroup);
        itemCard.append(headerRow);

        if (delil.aciklama) {
          itemCard.append($("<div>").addClass("font-size-12 text-muted bg-light p-2 rounded border").append($("<span>").addClass("fw-semibold text-dark").text("Açıklama: "), $("<span>").text(delil.aciklama)));
        }

        itemCard.append($("<div>").addClass("font-size-11 text-muted font-monospace mt-2 text-truncate").text("Delil ID: " + delil.delil_id));
        delilListEl.append(itemCard);
      });
    }

    // Footer
    if (data.evrak_no && data.evrak_no !== "-") {
      $("#kepPreviewFooterEvrakNo").text("Resmi Evrak Kayıt No: #" + data.evrak_no);
    } else {
      $("#kepPreviewFooterEvrakNo").text("");
    }

    if (data.encrypted_id) {
      $("#btnKepPreviewPdf").removeClass("d-none").data("id", data.encrypted_id);
    } else {
      $("#btnKepPreviewPdf").addClass("d-none");
    }

    $("#kepPreviewLoader").addClass("d-none");
    $("#kepPreviewContent").removeClass("d-none");
  }

  function openKepPreviewModal(evrakId, kepId) {
    ensureKepPreviewModalInBody();
    const modal = $("#modalKepOnizleme");
    $("#kepPreviewLoader").removeClass("d-none");
    $("#kepPreviewContent").addClass("d-none");
    $("#tabKepIleti-tab").tab("show");
    modal.modal("show");

    $.ajax({
      url: api_url,
      type: "POST",
      data: { action: "kep-detay-getir", id: evrakId, kep_id: kepId },
      dataType: "json"
    }).done(function (res) {
      if (res.status !== "success" || !res.data) {
        modal.modal("hide");
        return Swal.fire("Hata", res.message || "KEP iletisi detayları yüklenemedi.", "error");
      }
      renderKepPreview(res.data);
    }).fail(function (xhr) {
      modal.modal("hide");
      Swal.fire("Hata", xhr.responseJSON?.message || "Sunucu ile iletişim kurulamadı.", "error");
    });
  }

  // KEP Önizleme Butonu Tıklaması
  $(document).on("click", ".btn-kep-onizle", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const id = $(this).data("id");
    const kepId = $(this).data("kep-id") || "";
    openKepPreviewModal(id, kepId);
  });

  // PTT'den Delilleri Canlı Yenileme
  $(document).on("click", "#btnKepDelilYenile", function () {
    const btn = $(this);
    const originalHtml = btn.html();
    btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> PTT Sorgulanıyor...');

    $.ajax({
      url: api_url,
      type: "POST",
      data: { action: "kep-detay-getir", id: activeKepPreviewEvrakId, kep_id: activeKepPreviewKepId },
      dataType: "json"
    }).done(function (res) {
      if (res.status === "success" && res.data) {
        renderKepPreview(res.data);
        $("#tabKepDeliller-tab").tab("show");
        Swal.fire({ toast: true, position: "top-end", icon: "success", title: "PTT delil kayıtları güncellendi", showConfirmButton: false, timer: 2000 });
      } else {
        Swal.fire("Uyarı", res.message || "Delil güncellemesi yapılamadı.", "warning");
      }
    }).fail(function () {
      Swal.fire("Hata", "PTT KEP servisine ulaşılamadı.", "error");
    }).always(function () {
      btn.prop("disabled", false).html(originalHtml);
    });
  });

  // Kep Takip Kodu Kopyalama
  $(document).on("click", "#btnCopyKepId", function () {
    const text = $("#kepPreviewKepId").text().trim();
    if (text && text !== "-") {
      navigator.clipboard.writeText(text).then(function () {
        Swal.fire({ toast: true, position: "top-end", icon: "success", title: "PTT KEP Takip Kodu kopyalandı", showConfirmButton: false, timer: 1500 });
      });
    }
  });

  // KEP Modalından Resmi Evrak PDF'ini Açma
  $(document).on("click", "#btnKepPreviewPdf", function () {
    const id = $(this).data("id");
    if (id) {
      showPdfFromRequest({ type: "GET", data: { id: id } });
    }
  });

  // Tablo Satırı Tıklaması KEP Koruma
  $("#evrakTable tbody").on("click", "tr", function (e) {
    if ($(e.target).closest("a, button, input, .dropdown, .popover, .action-btn-group, .evrak-imza-onizle, .e-imza-rozet").length) return;
    const kepBtn = $(this).find(".btn-kep-onizle");
    if (kepBtn.length) {
      e.preventDefault();
      kepBtn.first().trigger("click");
    }
  });
});
