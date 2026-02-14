let url = "views/tanimlamalar/api.php";

// Yeni Ekle butonuna tıklayınca formu sıfırla
$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#unvan_departman").val("").trigger("change");
  $("#unvan_ucret_id").val(0);
  $("#actionModalLabel").text("Unvan / Ücret Ekle");
});

$(document).ready(function () {
  // Select2 init
  $("#unvan_departman").select2({
    dropdownParent: $("#actionModal"),
    width: "100%",
    placeholder: "Departman Seçiniz",
  });

  // Departman filter
  $("#filterDepartman").on("change", function () {
    var selectedDepartman = $(this).val();
    var table = $("#actionTable").DataTable();

    if (selectedDepartman) {
      table.column(1).search(selectedDepartman).draw();
    } else {
      table.column(1).search("").draw();
    }
  });
});

// Kaydet butonu
$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      unvan_departman: { required: true },
      unvan_adi: { required: true },
      unvan_ucret: { required: true },
    },
    messages: {
      unvan_departman: { required: "Departman seçiniz" },
      unvan_adi: { required: "Unvan / Görev adı boş bırakılamaz" },
      unvan_ucret: { required: "Ücret tutarı boş bırakılamaz" },
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
  formData.append("action", "unvan-ucret-kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        if (typeof showToast === "function") {
          showToast(data.message, "success");
        } else {
          swal.fire("Başarılı", data.message, "success");
        }
        setTimeout(function () {
          location.reload();
        }, 1000);
      } else {
        if (typeof showToast === "function") {
          showToast(data.message, "error");
        } else {
          swal.fire("Hata", data.message, "error");
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      if (typeof showToast === "function") {
        showToast("Bir hata oluştu", "error");
      }
    });
});

// Düzenle butonu
$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  var formData = new FormData();
  formData.append("action", "unvan-ucret-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        $("#unvan_ucret_id").val(id);
        $("#unvan_departman").val(data.data.unvan_departman).trigger("change");
        $("#unvan_adi").val(data.data.tur_adi);
        $("#unvan_ucret").val(data.data.unvan_ucret);
        $("#aciklama").val(data.data.aciklama);
        $("#actionModalLabel").text("Unvan / Ücret Düzenle");
        $("#actionModal").modal("show");
      } else {
        swal.fire("Hata", data.message, "error");
      }
    });
});

// Sil butonu
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
        formData.append("action", "unvan-ucret-sil");
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

              if (typeof showToast === "function") {
                showToast(data.message, "success");
              } else {
                swal.fire("Silindi!", data.message, "success");
              }
            } else {
              swal.fire("Hata", data.message, "error");
            }
          });
      }
    });
});
