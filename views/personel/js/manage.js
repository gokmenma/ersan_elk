// views/personel/js/manage.js

$(document).ready(function () {
  // Fotoğraf Değiştirme Butonu
  $("#changePhotoButton").click(function () {
    $("#avatarInput").click();
  });

  // Dosya Seçilince Önizleme
  $("#avatarInput").change(function () {
    if (this.files && this.files[0]) {
      let reader = new FileReader();
      reader.onload = function (e) {
        $("#personelImage").attr("src", e.target.result);
      };
      reader.readAsDataURL(this.files[0]);
    }
  });

  // Form Validasyonu
  $("#personelForm").validate({
    rules: {
      tc_kimlik_no: {
        required: true,
        minlength: 11,
        maxlength: 11,
        digits: true,
      },
      adi_soyadi: {
        required: true,
      },
      ise_giris_tarihi: {
        required: true,
      },
      dogum_tarihi: {
        required: true,
        /**15 yaşından küçük olamaz */
        minAge: 15,
      },
      cep_telefonu: {
        required: true,
        minlength: 10,
        maxlength: 15,
        digits: true,
      },
      ekip_no: {
        required: function () {
          return $("#departman").val() !== "BÜRO";
        },
      },
    },
    messages: {
      tc_kimlik_no: {
        required: "Lütfen TC Kimlik No giriniz.",
        minlength: "TC Kimlik No 11 haneli olmalıdır.",
        maxlength: "TC Kimlik No 11 haneli olmalıdır.",
        digits: "Lütfen sadece rakam giriniz.",
      },
      adi_soyadi: {
        required: "Lütfen Ad Soyad giriniz.",
      },
      ise_giris_tarihi: {
        required: "Lütfen İşe Giriş Tarihi giriniz.",
      },
      dogum_tarihi: {
        required: "Lütfen Doğum Tarihi giriniz.",
      },
      cep_telefonu: {
        required: "Lütfen Cep Telefonu giriniz.",
        minlength: "Cep Telefonu en az 10 haneli olmalıdır.",
        maxlength: "Cep Telefonu en fazla 15 haneli olmalıdır.",
        digits: "Lütfen sadece rakam giriniz.",
      },
      ekip_no: {
        required: "Lütfen Ekip Numarası giriniz.",
      },
    },
    ignore: "hidden",
  });

  // Kaydet Butonu Tıklama Olayı
  $("#saveButton").click(function () {
    let form = $("#personelForm");

    // Validasyon kontrolü
    if (!form.valid()) {
      swal.fire({
        title: "Hata",
        text: "Lütfen formu doldurunuz.",
        icon: "error",
        confirmButtonText: "Tamam",
      });
      return;
    }

    let personel_id = $("#personel_id").val();

    let formData = new FormData(form[0]);

    // Profil resmi inputu formun dışında olduğu için manuel ekliyoruz
    let fileInput = $("#avatarInput")[0];
    if (fileInput.files && fileInput.files[0]) {
      formData.append("resim_yolu", fileInput.files[0]);
    }

    formData.append("action", "personel-kaydet");

    // Butonu pasif yap ve yükleniyor göster
    var $btn = $(this);
    var originalText = $btn.html();
    // Ripple (dalga) efektini temizle, aksi takdirde originalText içinde birikerek butonu beyazlatır
    if ($btn.find(".waves-ripple").length > 0) {
      $btn.find(".waves-ripple").remove();
      originalText = $btn.html();
    }
    $btn
      .prop("disabled", true)
      .html(
        '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...',
      );

    $.ajax({
      url: "views/personel/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        let res = JSON.parse(response);
        console.log(res);
        if (res.status === "success") {
          Swal.fire({
            title: "Başarılı",
            text: res.message,
            icon: "success",
            confirmButtonText: "Tamam",
          }).then((result) => {
            if (personel_id == 0 || personel_id == "") {
              $("#personel_id").val(res.id);
              $btn.prop("disabled", false).html(originalText);
            } else {
              location.reload();
            }
          });
        } else {
          Swal.fire({
            title: "Hata",
            text: res.message,
            icon: "error",
            confirmButtonText: "Tamam",
          });
          /**Butonu eski haline getir */
          $btn.prop("disabled", false).html(originalText);
        }
      },
      error: function (xhr, status, error) {
        console.error(error);
        Swal.fire({
          title: "Hata",
          text: "Bir sunucu hatası oluştu.",
          icon: "error",
        });
      },
      complete: function () {
        $btn.prop("disabled", false).html(originalText);
      },
    });
  });

  $(".select2").on("change", function () {
    $(this).valid();
  });
});
