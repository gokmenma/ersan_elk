let url = "views/tanimlamalar/api.php";

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#is_turu").val("").trigger("change");
  $("#is_turu_id").val(0);
  $("#is_emri_sonucu").val("");
  $("#is_turu_ucret").val("");
  $("#aracli_personel_is_turu_ucret").val("");
  $("#rapor_sekmesi").val("").trigger("change");
  $("#actionModalLabel").text("İş Türü Ekle");
});

$(document).ready(function () {
  $("#is_turu").select2({
    dropdownParent: $("#actionModal"),
    tags: true,
    width: "100%",
  });

  $("#rapor_sekmesi").select2({
    dropdownParent: $("#actionModal"),
    width: "100%",
    placeholder: "Rapor Sekmesi Seçiniz",
  });
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      is_turu: {
        required: true,
      },
    },
    messages: {
      is_turu: {
        required: "İş Türü boş bırakılamaz",
      },
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
  });

  if (!form.valid()) {
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "is-turu-kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";

      if (data.status == "success") {
        var table = $("#actionTable").DataTable();
        // If update, remove old row first
        if (data.is_update) {
          table
            .row($("#row_" + data.id))
            .remove()
            .draw(false);
        }
      }

      swal
        .fire({
          title: title,
          text: data.message,
          icon: data.status,
          confirmButtonText: "Tamam",
        })
        .then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
    });
});

$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  var formData = new FormData();
  formData.append("action", "is-turu-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        // We need to set the hidden input to the ENCRYPTED id so save works
        $("#is_turu_id").val(id);
        $("#is_turu").val(data.data.is_turu).trigger("change");

        $("#is_emri_sonucu").val(data.data.is_emri_sonucu);
        $("#is_turu_ucret").val(data.data.is_turu_ucret);
        $("#aracli_personel_is_turu_ucret").val(
          data.data.aracli_personel_is_turu_ucret,
        );
        $("#rapor_sekmesi").val(data.data.rapor_sekmesi).trigger("change");
        $("#aciklama").val(data.data.aciklama);
        $("#actionModalLabel").text("İş Türü Düzenle");
        $("#actionModal").modal("show");
      } else {
        swal.fire("Hata", data.message, "error");
      }
    });
});

$(document).on("click", ".sil", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  swal
    .fire({
      title: "Emin misiniz?",
      text: "Bu kaydı silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "İptal",
    })
    .then((result) => {
      if (result.isConfirmed) {
        var formData = new FormData();
        formData.append("action", "is-turu-sil");
        formData.append("id", id);

        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status == "success") {
              var table = $("#actionTable").DataTable();
              table
                .row($("#row_" + data.deleted_id))
                .remove()
                .draw(false);

              swal.fire("Silindi!", data.message, "success");
            } else {
              swal.fire("Hata", data.message, "error");
            }
          });
      }
    });
});

// Excel Yükle Form Submit
$(document).on("submit", "#formExcelYukle", function (e) {
  e.preventDefault();

  var form = $(this);
  var formData = new FormData(form[0]);
  formData.append("action", "is-turu-excel-yukle");

  // Yükleniyor göster
  Swal.fire({
    title: "Yükleniyor...",
    text: "Excel dosyası işleniyor, lütfen bekleyin.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      Swal.close();

      if (data.status === "success") {
        Swal.fire({
          title: "Başarılı",
          html: `
            <div class="text-center">
              <p>${data.message}</p>
              ${data.insertCount > 0 ? `<div class="badge bg-success me-1">${data.insertCount} yeni kayıt</div>` : ""}
              ${data.updateCount > 0 ? `<div class="badge bg-info">${data.updateCount} güncelleme</div>` : ""}
            </div>
          `,
          icon: "success",
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
      } else if (data.status === "warning") {
        Swal.fire({
          title: "Uyarı",
          text: data.message,
          icon: "warning",
          confirmButtonText: "Tamam",
        });
      } else {
        Swal.fire({
          title: "Hata",
          text: data.message,
          icon: "error",
          confirmButtonText: "Tamam",
        });
      }

      // Modal'ı kapat ve formu sıfırla
      $("#excelModal").modal("hide");
      form[0].reset();
    })
    .catch((error) => {
      Swal.close();
      Swal.fire({
        title: "Hata",
        text: "Bir hata oluştu: " + error.message,
        icon: "error",
        confirmButtonText: "Tamam",
      });
    });
});

$(document).on("input", "#is_turu_ucret", function () {
  if ($("#is_turu_id").val() == 0) {
    $("#aracli_personel_is_turu_ucret").val($(this).val()).trigger("input");
  }
});
