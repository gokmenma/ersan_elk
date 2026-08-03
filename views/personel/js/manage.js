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
      "departman[]": {
        required: true,
      },
      gorev: {
        required: true,
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
      "departman[]": {
        required: "Lütfen Departman seçiniz.",
      },
      gorev: {
        required: "Lütfen Görev / Unvan seçiniz.",
      },
      ekip_no: {
        required: "Lütfen Ekip Numarası giriniz.",
      },
    },
    ignore: ":hidden:not(select)",
    errorElement: "span",
    errorPlacement: function (error, element) {
      error.addClass("invalid-feedback");
      if (
        element.hasClass("select2") &&
        element.next(".select2-container").length
      ) {
        error.insertAfter(element.next(".select2-container"));
      } else if (element.parent(".form-floating").length) {
        error.insertAfter(element.parent(".form-floating"));
      } else {
        error.insertAfter(element);
      }
    },
    highlight: function (element) {
      $(element).addClass("is-invalid");
      if ($(element).hasClass("select2")) {
        $(element)
          .next(".select2-container")
          .find(".select2-selection")
          .addClass("border-danger");
      }
    },
    unhighlight: function (element) {
      $(element).removeClass("is-invalid");
      if ($(element).hasClass("select2")) {
        $(element)
          .next(".select2-container")
          .find(".select2-selection")
          .removeClass("border-danger");
      }
    },
  });

  function optimizePersonnelImage(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (event) => {
        const image = new Image();
        image.onload = () => {
          const maxDimension = 800;
          const scale = Math.min(1, maxDimension / Math.max(image.width, image.height));
          const width = Math.max(1, Math.round(image.width * scale));
          const height = Math.max(1, Math.round(image.height * scale));
          const canvas = document.createElement("canvas");
          canvas.width = width;
          canvas.height = height;
          const context = canvas.getContext("2d");
          context.fillStyle = "#fff";
          context.fillRect(0, 0, width, height);
          context.drawImage(image, 0, 0, width, height);
          canvas.toBlob((blob) => {
            if (!blob) {
              reject(new Error("Resim sıkıştırılamadı."));
              return;
            }
            resolve(new File([blob], "personel.jpg", {
              type: "image/jpeg",
              lastModified: Date.now(),
            }));
          }, "image/jpeg", 0.82);
        };
        image.onerror = () => reject(new Error("Bu resim formatı okunamadı."));
        image.src = event.target.result;
      };
      reader.onerror = () => reject(new Error("Resim okunamadı."));
      reader.readAsDataURL(file);
    });
  }

  // Kaydet Butonu Tıklama Olayı
  $("#saveButton").click(async function () {
    let form = $("#personelForm");

    // Validasyon kontrolü
    if (!form.valid()) {
      Swal.fire({
        title: "Hata",
        html: "Lütfen formu doldurunuz.",
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
      try {
        const optimizedImage = await optimizePersonnelImage(fileInput.files[0]);
        formData.append("resim_yolu", optimizedImage);
      } catch (error) {
        Swal.fire("Resim Hatası", error.message || "Resim hazırlanamadı.", "error");
        return;
      }
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
            title: "İşlem Başarılı",
            html: res.message,
            icon: "success",
            confirmButtonText: "Tamam",
          }).then((result) => {
            window.location.href = "index?p=personel/list";
          });
        } else {
          Swal.fire({
            title: "Hata",
            html: res.message,
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
          html: "Bir sunucu hatası oluştu.",
          icon: "error",
        });
      },
      complete: function () {
        $btn.prop("disabled", false).html(originalText);
      },
    });
  });

  $("#personelForm").on("change", ".select2", function () {
    var form = this.form;
    if (form && form.id === "personelForm") {
      var validator = $.data(form, "validator");
      if (validator) {
        try {
          $(this).valid();
        } catch (e) {
          console.warn("Validation error ignored:", e);
        }
      }
    }
  });
});
