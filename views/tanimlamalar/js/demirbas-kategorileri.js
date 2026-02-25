let url = "views/tanimlamalar/api.php";

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#kategori_id").val(0);
  $("#actionModalLabel").html(
    '<i class="bx bx-list-ul me-2"></i>Kategori Ekle',
  );
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      kategori_adi: {
        required: true,
      },
    },
    messages: {
      kategori_adi: {
        required: "Kategori adı boş bırakılamaz",
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
  formData.append("action", "demirbas-kategorisi-kaydet");

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
  formData.append("action", "demirbas-kategorisi-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        $("#kategori_id").val(id);
        $("#kategori_adi").val(data.data.tur_adi);
        $("#aciklama").val(data.data.aciklama);

        $("#actionModalLabel").html(
          '<i class="bx bx-edit me-2"></i>Kategori Düzenle',
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
      text: "Bu kategoriyi silmek istediğinize emin misiniz?",
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
        formData.append("action", "demirbas-kategorisi-sil");
        formData.append("id", id);

        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status == "success") {
              swal.fire("Silindi!", data.message, "success").then(() => {
                location.reload();
              });
            } else {
              swal.fire("Hata", data.message, "error");
            }
          });
      }
    });
});
