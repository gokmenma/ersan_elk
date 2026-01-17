import { tableRowAddOrUpdate } from "../../../App/utils/tableUtils.js";
import { sweatalert } from "../../../App/utils/alertUtils.js";
var url = "views/rehber/api.php";


//Yeni ekle butonuna tıklandığında
$(document).on("click", "#yeniEkle", function () {
  $("#rehberForm").trigger("reset");
  $("#id").val(0);
});

$(document).on("click", "#rehberKaydet", function () {
  var form = $("#rehberForm");
  var id = $("#id").val();

  form.validate({
    rules: {
      adi_soyadi: {
        required: true,
        minlength: 2,
      },
      telefon: {
        required: true,
        minlength: 10,
      },
    },
    messages: {
      adi_soyadi: {
        required: "Adı ve Soyadı alanı zorunludur.",
        minlength: "Adı ve Soyadı en az 2 karakter olmalıdır.",
      },
      telefon: {
        required: "Telefon alanı zorunludur.",
        minlength: "Telefon numarası en az 10 karakter olmalıdır.",
      },
    },
  });   
  if (!form.valid()) {
    return false;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      //console.log(data);

      tableRowAddOrUpdate("rehberTable", data, id);
      sweatalert(data);
    })
    .catch((error) => {
      console.error("Error:", error);
    });
});

$(document).on("click", ".kayit-sil", function () {
  var id = $(this).data("id");
  var formData = new FormData();
  formData.append("id", id);
  formData.append("action", "kayitSil");

  swal
    .fire({
      title: "Uyarı!",
      text: "Silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet",
      cancelButtonText: "Hayır",
    })
    .then((result) => {
      if (result.isConfirmed) {
        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            var table = $("#rehberTable").DataTable();
            table.row($(this).closest("tr")).remove().draw(false);

            var title = data.status == "success" ? "Başarılı!" : "Hata!";
            swal.fire({
              title: title,
              text: data.message,
              icon: data.status,
              confirmButtonText: "Tamam",
            });
          })
          .catch((error) => {
            console.error("Error:", error);
          });
      }
    });
});

//Güncelle butonuna tıklandığında
$(document).on("click", ".kayit-duzenle", function () {
  var id = $(this).data("id");
  var formData = new FormData();
  formData.append("id", id);
  formData.append("action", "kayitGetir");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      //console.log(data);
      $("#id").val(id);
      $("#adi_soyadi").val(data.adi_soyadi);
      $("#kurum_adi").val(data.kurum_adi);
      $("#telefon").val(data.telefon);
      $("#telefon2").val(data.telefon2);
      $("#email").val(data.email);
      $("#adres").val(data.adres);
      $("#aciklama").val(data.aciklama);

      //Modal açılır
      $("#rehberModal").modal("show");
    })
    .catch((error) => {
      console.error("Error:", error);
    });
});
