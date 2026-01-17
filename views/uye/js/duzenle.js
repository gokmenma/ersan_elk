import { tableRowAddOrUpdate } from "../../../App/utils/tableUtils.js";
import { sweatalert } from "../../../App/utils/alertUtils.js";

let url= "views/uye/api.php";
//*********************ÜYE KAYDET*************
$(document).on("click", "#saveButton", function () {
  let form = $("#uyeForm");
  let uye_id = $("#uye_id").val();

  form.validate({
    rules: {
      uye_no: "required",
      adi_soyadi: "required",
      telefon: "required",
      tc_kimlik: {
        required: true,
        maxlength: 11
      }
    },
    messages: {
      uye_no: "Üye No alanı boş bırakılamaz",
      adi_soyadi: "Adı Soyadı alanı boş bırakılamaz",
      telefon: "Telefon alanı boş bırakılamaz",
      tc_kimlik: {
        required: "TC Kimlik No alanı boş bırakılamaz",
        maxlength: "TC Kimlik No 11 haneli olmalıdır"
      }
    },

  });
  if (!form.valid()) {
    return false;
  }

  let formData = new FormData(form[0]);

  formData.append("action", "uye-kaydet");

  // for (var pair of formData.entries()) {
  //   console.log(pair[0] + ", " + pair[1]);
  // }


  //Preloader
  Pace.restart();

  let $this = $(this);
  $this.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...');
  
  fetch("views/uye/api.php", {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      //console.log(data);
      title = data.status == "success" ? "Başarılı" : "Hata";

      //Eğer işlem başarılı ise uyelik bilgileri tablosuna ekleme yapılır
      if (data.status == "success") {
        if (uye_id == 0) {
          let table = $("#uyeIslemTable").DataTable();
          table.row.add($(data.uyelik_bilgi)).draw(false);
        }
      }

      $("#uye_id").val(data.id);
      Swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam"
      });
    })
    .catch((error) => {
      console.error("Error:", error);
    })
    .finally(() => {
      $this.prop("disabled", false).html('<i class="bx bx-save label-icon me-1"></i> Kaydet');
    });
});
//*********************ÜYE KAYDET*************

//------------------------------------------------------

//*********************YENİ ÜYELİK İŞLEMİ*************
$(document).on('click', '#uyeIslemEkle', function() {
     //Temsilcilik kaydedildi mi kontrol et.
     var uye_id = $("#uye_id").val();
     if (uye_id == 0) {
       swal.fire({
         title: "Hata",
         text: "Önce Üye Bilgilerini kaydetmeniz gerekir",
         icon: "error",
       });
       return;
     }
   
     $("#uyeIslemForm").trigger("reset");
      $("#uye_islem_id").val(0);
     //Modali aç
     $("#uyeIslemModal").modal("show");
});


//*********************ÜYE İŞLEMLERİ KAYDET*************
$(document).on("click", "#uyeIslemKaydet", function () {
  let form = $("#uyeIslemForm");
  let uye_id = $("#uye_id").val();

  //Üye bilgileri kaydedilmemişse hata ver
  if (uye_id == "" || uye_id == 0) {
    Swal.fire({
      title: "Hata",
      text: "Üye bilgileri kaydedilmemiş",
      icon: "error",
      confirmButtonText: "Tamam"
    });
    return false;
  }

  //İstifa tarihi üyelik tarihinden büyük olmalıdır
  $.validator.addMethod(
    "greaterThan",
    function (value, element, params) {
      // Convert dates to a consistent format (YYYY-MM-DD)
      let dateValue = value.split(".").reverse().join("-");
      let dateParam = $(params).val().split(".").reverse().join("-");

      // Parse the dates using the consistent format
      let dateValueParsed = new Date(dateValue);
      let dateParamParsed = new Date(dateParam);

      // Only validate if both dates are valid
      if (
        !isNaN(dateValueParsed.getTime()) &&
        !isNaN(dateParamParsed.getTime())
      ) {
        return dateValueParsed > dateParamParsed;
      }

      return false;
    },
    "İstifa tarihi {0}'den büyük olmak zorunda."
  );

  //Form Doğrulaması yap
  form.validate({
    rules: {
      uyelik_tarihi: "required",
      istifa_tarihi: {
        required: false,
        greaterThan: {
          param: "#uyelik_tarihi",
          depends: function () {
            return $(this).val() !== ""; // Only validate if istifa_tarihi has a value
          }
        }
      }
    },
    messages: {
      uyelik_tarihi: "Üyelik tarihi alanı boş bırakılamaz",
      istifa_tarihi: {
        greaterThan: "İstifa tarihi üyelik tarihinden büyük olmalıdır"
      }
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element) {
      $(element).removeClass("is-invalid");
    }
  });

  //Form doğrulaması başarısız ise işlemi durdur
  if (!form.valid()) {
    return false;
  }

  let formData = new FormData(form[0]);

  //İşlemin id'sini al,eğer yoksa 0 ata
  let islem_id = $("#islem_id").val();

  formData.append("action", "uye-islem-kaydet");
  formData.append("uye_id", uye_id);
  formData.append("islem_id", islem_id);

  // for (var pair of formData.entries()) {
  //   console.log(pair[0] + ", " + pair[1]);
  // }

  fetch(url, {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      //console.log(data);
      let table = $("#uyeIslemTable").DataTable();
      // Tabloya yeni satır eklemek için
      if (data.status == "success" && islem_id == 0) {
        //Eğer işlem başarılı ve yeni kayıt ise tabloya son eklenen kaydın bilgileri ekle ekle
        table.row.add($(data.son_kayit)).draw(false);
      } else if (data.status == "success" && islem_id != 0) {
        //Eğer işlem başarılı ve güncelleme ise tablodaki veriyi güncelle
        let rowNode = table.$(`tr[data-id="${islem_id}"]`)[0];
        //console.log(rowNode);
        if (rowNode) {
          table.row(rowNode).remove().draw();
          table.row.add($(data.son_kayit)).draw(false);
        }

        //Modali temizle
        $("#islem_id").val(0);
        $("#uyelik_tarihi").val("");
        $("#istifa_tarihi").val("");
        $("#aciklama").val("");

        //Modali kapat
        $("#uyeIslemModal").modal("hide");
      }

      title = data.status == "success" ? "Başarılı" : "Hata";
      Swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam"
      });
    });
});
//*********************ÜYE İŞLEMLERİ KAYDET*************

//------------------------------------------------------

//*********************ÜYE İŞLEMLERİ DÜZENLE*************
$(document).on("click", ".uye-islem-duzenle", function () {
  let islem_id = $(this).data("id");

//console.log(islem_id);

  //bilgileri veritabanından çek
  var formData = new FormData();
  formData.append("action", "uye-islem-bilgi");
  formData.append("islem_id", islem_id);

  fetch("views/uye/api.php", {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      //console.log(data);
      if (data.status == "success") {
        $("#islem_id").val(islem_id);
        $("#uyelik_tarihi").val(data.uyelik_tarihi);
        $("#istifa_tarihi").val(data.istifa_tarihi);
        $("#karar_tarihi_no").val(data.karar_tarihi_no);
        $("#giden_evrak").val(data.giden_evrak);
        $("#birim_evrak").val(data.birim_evrak);
        $("#aciklama").val(data.aciklama);
        $("#uyeIslemModal").modal("show");
      }
    });
});
//*********************ÜYE İŞLEMLERİ DÜZENLE*************

//------------------------------------------------------

//*********************ÜYE İŞLEMLERİ SİL*************
$(document).on("click", ".uye-islem-sil", function () {
  let islem_id = $(this).data("id");
  let buttonElement = $(this); // Store reference to the clicked button

  Swal.fire({
    title: "Emin misiniz?",
    html: `Üye işlemi silmek istediğinize emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Evet",
    cancelButtonText: "Hayır"
  }).then((result) => {
    if (result.isConfirmed) {
      var formData = new FormData();
      formData.append("action", "uye-islem-sil");
      formData.append("islem_id", islem_id);

      fetch("views/uye/api.php", {
        method: "POST",
        body: formData
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status == "success") {
            let table = $("#uyeIslemTable").DataTable();
            table.row(buttonElement.closest("tr")).remove().draw(false);
            Swal.fire("Başarılı!", `Üye işlemi başarıyla silindi.`, "success");
          }
        });
    }
  });
});
//*********************ÜYE İŞLEMLERİ SİL*************

//------------------------------------------------------





