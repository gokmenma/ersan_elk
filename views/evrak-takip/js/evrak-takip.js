$(document).ready(function () {
  const api_url = "views/evrak-takip/api.php";

  // Feather Icons
  if (typeof feather !== 'undefined') feather.replace();

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

    // Tags Select2 (evrak-select2-tags)
    $("#evrakModal .evrak-select2-tags").each(function() {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({
                tags: true,
                dropdownParent: $("#evrakModal"),
                width: "100%",
                placeholder: "Seçiniz veya Yazınız..."
            });
        }
    });
  }

  // Handle Select2 in Modals (Bootstrap 5 fix)
  $('#evrakModal').on('shown.bs.modal', function () {
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
    const tip = $('input[name="evrak_tipi"]:checked').val();
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
  }

  function checkBildirimVisibility() {
    const val = $("#ilgili_personel_id").val();
    if (val && val !== "" && val != "0") {
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
  $(document).on("change", "#ilgili_personel_id", checkBildirimVisibility);
  $(document).on("change", "#cevap_verildi", checkCevapVisibility);
  $(document).on("change", 'input[name="evrak_tipi"]', function () {
    const tip = $(this).val();
    if (tip === "gelen") getNextEvrakNo("gelen");
    else $("#evrak_no").val("");
    checkSectionVisibility();
  });

  // API Functions
  function loadKonular() {
    $.post(api_url, { action: "get-konular" }, function (response) {
      if (response.status === "success") {
        const select = $("#konu");
        const existingValues = [];
        select.find("option").each(function () {
          if ($(this).val()) existingValues.push($(this).val());
        });
        response.data.forEach((konu) => {
          if (konu && !existingValues.includes(konu)) {
            select.append(new Option(konu, konu));
          }
        });
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
    $("#evrakModalLabel").text('Yeni Evrak Kaydı');
    $("#evrakForm")[0].reset();
    $("#evrak_id").val("");
    $("#mevcutDosya").hide();
    
    // Select2 Reset
    $(".evrak-select2, .evrak-select2-tags").val("").trigger("change");
    
    $("#personel_bildir, #cevap_verildi").prop("checked", false);
    $("#bildirimContainer, #cevapTarihiContainer, #gidenIliskiSection").addClass("d-none");
    $("#gelenCevapSection").removeClass("d-none");
    
    validator.resetForm();
    $(".is-invalid").removeClass("is-invalid");
    
    if ($("#tipGelen").is(":checked")) getNextEvrakNo("gelen");
    loadKonular();
    $("#evrakModal").modal("show");
  });

  $("#evrakForm").on("submit", function (e) {
    e.preventDefault();
    if (!$(this).valid()) return false;
    const formData = new FormData(this);
    const btn = $("#btnEvrakKaydet");
    btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
      url: api_url, type: "POST", data: formData, contentType: false, processData: false,
      success: function (response) {
        if (response.status === "success") {
          $("#evrakModal").modal("hide");
          showToast(response.message, "success");
          setTimeout(() => location.reload(), 800);
        } else {
          showToast(response.message, "error");
          btn.prop("disabled", false).text('Bilgileri Kaydet');
        }
      },
      error: function () {
        btn.prop("disabled", false).text('Bilgileri Kaydet');
        showToast("Hata oluştu", "error");
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
        
        $("#personel_bildir").prop("checked", data.personel_bildirim_durumu == 1);
        $("#cevap_verildi").prop("checked", data.cevap_verildi_mi == 1);
        
        if (data.cevap_tarihi && data.cevap_tarihi !== '0000-00-00') {
            const d2 = data.cevap_tarihi.split("-");
            $('input[name="cevap_tarihi"]').val(d2[2] + "." + d2[1] + "." + d2[0]);
        }

        checkSectionVisibility();
        
        $('textarea[name="aciklama"]').val(data.aciklama);
        if (data.dosya_yolu) $("#mevcutDosya").show().find("a").attr("href", data.dosya_yolu);
        else $("#mevcutDosya").hide();

        $("#evrakModal").modal("show");
      }
    });
  });

  $(document).on("click", ".evrak-sil", function () {
    const id = $(this).data("id");
    if(confirm("Kaydı silmek istediğinize emin misiniz?")) {
        $.post(api_url, { action: "evrak-sil", id: id }, function (response) {
            if (response.status === "success") location.reload();
        });
    }
  });

  $("#btnRefresh").on("click", function () { location.reload(); });
});
