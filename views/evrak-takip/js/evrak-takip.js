$(document).ready(function () {
  const api_url = "views/evrak-takip/api.php";

  // Feather Icons Başlat
  feather.replace();

  // jQuery Validation Ayarları
  const validator = $("#evrakForm").validate({
    rules: {
      tarih: { required: true },
      konu: { required: true },
      kurum_adi: { required: true },
    },
    messages: {
      tarih: { required: "Tarih seçimi zorunludur." },
      konu: { required: "Evrak konusu zorunludur." },
      kurum_adi: { required: "Kurum/Firma adı zorunludur." },
    },
    errorElement: "span",
    errorPlacement: function (error, element) {
      error.addClass("invalid-feedback");
      element.closest(".mb-3").append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass("is-invalid");
    },
  });

  // Yeni Evrak Ekle
  $("#btnYeniEvrak").on("click", function () {
    $("#evrakModalLabel").html(
      '<i data-feather="plus" class="me-2 icon-sm"></i>Yeni Evrak Kaydı',
    );
    $("#evrakForm")[0].reset();
    $("#evrak_id").val("");
    $("#mevcutDosya").hide();
    $("#personel_id").val("").trigger("change");
    validator.resetForm();
    $(".is-invalid").removeClass("is-invalid");
    $("#evrakModal").modal("show");
    feather.replace();
  });

  // Sayfayı Yenile
  $("#btnRefresh").on("click", function () {
    location.reload();
  });

  // Kaydet / Güncelle
  $("#btnEvrakKaydet").on("click", function () {
    if (!$("#evrakForm").valid()) {
      return false;
    }

    const form = $("#evrakForm")[0];
    const formData = new FormData(form);

    const btn = $(this);
    const originalHtml = btn.html();

    btn
      .prop("disabled", true)
      .html(
        '<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...',
      );

    $.ajax({
      url: api_url,
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        btn.prop("disabled", false).html(originalHtml);

        if (response.status === "success") {
          $("#evrakModal").modal("hide");
          showToast(response.message, "success");
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          showToast(response.message, "error");
        }
      },
      error: function () {
        btn.prop("disabled", false).html(originalHtml);
        showToast("Sistem hatası oluştu.", "error");
      },
    });
  });

  // Düzenle Butonu
  $(".evrak-duzenle").on("click", function () {
    const id = $(this).data("id");

    $.post(api_url, { action: "evrak-detay", id: id }, function (response) {
      if (response.status === "success") {
        const data = response.data;
        $("#evrakModalLabel").html(
          '<i data-feather="edit-2" class="me-2 icon-sm"></i>Evrak Düzenle',
        );
        $("#evrak_id").val(data.id);

        validator.resetForm();
        $(".is-invalid").removeClass("is-invalid");

        // Evrak Tipi
        if (data.evrak_tipi === "gelen") {
          $("#tipGelen").prop("checked", true);
        } else {
          $("#tipGiden").prop("checked", true);
        }

        // Tarih (Y-m-d formatını d.m.Y yap)
        if (data.tarih) {
          const dateParts = data.tarih.split("-");
          if (dateParts.length === 3) {
            $('input[name="tarih"]').val(
              dateParts[2] + "." + dateParts[1] + "." + dateParts[0],
            );
          }
        }

        $('input[name="evrak_no"]').val(data.evrak_no);
        $('input[name="konu"]').val(data.konu);
        $('input[name="kurum_adi"]').val(data.kurum_adi);
        $("#personel_id").val(data.personel_id).trigger("change");
        $('textarea[name="aciklama"]').val(data.aciklama);

        // Dosya linki
        if (data.dosya_yolu) {
          $("#mevcutDosya").show().find("a").attr("href", data.dosya_yolu);
        } else {
          $("#mevcutDosya").hide();
        }

        $("#evrakModal").modal("show");
        feather.replace();
      } else {
        showToast(response.message, "error");
      }
    });
  });

  // Sil Butonu
  $(".evrak-sil").on("click", function () {
    const id = $(this).data("id");

    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu evrak kaydı kalıcı olarak silinecektir!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Evet, Sil!",
      cancelButtonText: "Vazgeç",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(api_url, { action: "evrak-sil", id: id }, function (response) {
          if (response.status === "success") {
            showToast(response.message, "success");
            setTimeout(() => location.reload(), 1000);
          } else {
            showToast(response.message, "error");
          }
        });
      }
    });
  });

  // Select2 Başlat
  if ($(".select2").length > 0) {
    $(".select2").each(function () {
      $(this).select2({
        dropdownParent: $(this).closest(".modal").length
          ? $(this).closest(".modal")
          : null,
        width: "100%",
      });
    });
  }

  // Flatpickr Başlat
  if ($(".flatpickr").length > 0) {
    $(".flatpickr").flatpickr({
      dateFormat: "d.m.Y",
      locale: "tr",
      static: true,
    });
  }
});
