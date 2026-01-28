let url = "views/tanimlamalar/api.php";

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#ekip_id").val(0);
  $("#actionModalLabel").text("Ekip Kodu Ekle");
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      ekip_kodu: {
        required: true,
      },
    },
    messages: {
      ekip_kodu: {
        required: "Ekip Kodu boş bırakılamaz",
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
  formData.append("action", "ekip-kodu-kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";

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
  formData.append("action", "ekip-kodu-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        // We need to set the hidden input to the ENCRYPTED id so save works
        $("#ekip_id").val(id);
        $("#ekip_bolge").val(data.data.ekip_bolge);
        $("#ekip_kodu").val(data.data.ekip_kodu);
        $("#aciklama").val(data.data.aciklama);
        $("#actionModalLabel").text("Ekip Kodu Düzenle");
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
        formData.append("action", "ekip-kodu-sil");
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

              swal.fire("Silindi!", "Kayıt başarıyla silindi.", "success");
            } else {
              swal.fire("Hata", data.message, "error");
            }
          });
      }
    });
});
