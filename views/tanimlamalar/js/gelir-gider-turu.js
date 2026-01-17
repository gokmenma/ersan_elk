let url = "views/tanimlamalar/api.php";

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      gelir_gider_turu: {
        required: true
      }
    },
    messages: {
      gelir_gider_turu: {
        required: "Gelir/Gider Türü boş bırakılamaz"
      }
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    }
  });

  if (!form.valid()) {
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "gelir-gider-turu-kaydet");

  fetch(url, {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      console.log(data);
      title = data.status == "success" ? "Başarılı" : "Hata";

      var table = $("#actionTable").DataTable();
      table.row.add($(data.son_kayit)).draw(false);
      swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam"
      });
    });
});
