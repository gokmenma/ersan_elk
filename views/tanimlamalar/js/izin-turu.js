let url = "views/tanimlamalar/api.php";

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#izin_turu_id").val(0);
  $("#actionModalLabel").html(
    '<i class="bx bx-calendar-check me-2"></i>İzin Türü Ekle',
  );

  // Varsayılan değerler
  $("#ucretli_mi").prop("checked", true);
  $("#personel_gorebilir").prop("checked", true);
  $("#renk").val("bg-primary/10 text-primary");
  $("#ikon").val("event");
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      izin_turu: {
        required: true,
      },
    },
    messages: {
      izin_turu: {
        required: "İzin Türü boş bırakılamaz",
      },
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element) {
      $(element).removeClass("is-invalid");
    },
  });

  if (!form.valid()) {
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "izin-turu-kaydet");

  // Checkbox değerlerini manuel ekle (unchecked ise 0 gitmesi için)
  formData.set("ucretli_mi", $("#ucretli_mi").is(":checked") ? 1 : 0);
  formData.set(
    "personel_gorebilir",
    $("#personel_gorebilir").is(":checked") ? 1 : 0,
  );

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";

      if (data.status == "success") {
        var table = $("#actionTable").DataTable();
        // Güncelleme ise eski satırı sil
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
  formData.append("action", "izin-turu-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        $("#izin_turu_id").val(id);

        $("#izin_turu").val(data.data.tur_adi);
        $("#aciklama").val(data.data.aciklama);

        // Checkboxları ayarla
        $("#ucretli_mi").prop("checked", data.data.ucretli_mi == 1);
        $("#personel_gorebilir").prop(
          "checked",
          data.data.personel_gorebilir == 1,
        );

        // Renk ve İkon
        $("#renk").val(data.data.renk || "bg-primary/10 text-primary");
        $("#ikon").val(data.data.ikon || "event");

        $("#actionModalLabel").html(
          '<i class="bx bx-edit me-2"></i>İzin Türü Düzenle',
        );
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
      text: "Bu izin türünü silmek istediğinize emin misiniz?",
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
        formData.append("action", "izin-turu-sil");
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
