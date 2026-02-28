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

  // Görev select2 init
  $("#main_gorev").select2({
    width: "100%",
    placeholder: "Görev Seçiniz",
    allowClear: true,
  });

  // Görev unvanlarını departmana göre yükle
  function loadGorevOptions(selectedDepartmanlar, callback) {
    if (!selectedDepartmanlar || selectedDepartmanlar.length === 0) {
      var $gorev = $("#main_gorev");
      $gorev.find("option").not(":first").remove();
      $gorev.val("").trigger("change.select2");
      if (callback) callback();
      return;
    }

    var allOptions = [];
    var promises = [];

    selectedDepartmanlar.forEach(function (departman) {
      var formData = new FormData();
      formData.append("action", "unvan-ucretleri-getir");
      formData.append("departman", departman);

      var promise = fetch("views/tanimlamalar/api.php", {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data.status === "success" && data.data) {
            data.data.forEach(function (item) {
              allOptions.push({
                id: item.tur_adi,
                text: item.tur_adi,
                ucret: item.unvan_ucret,
              });
            });
          }
        });

      promises.push(promise);
    });

    Promise.all(promises).then(function () {
      var $gorev = $("#main_gorev");
      $gorev.find("option").not(":first").remove();

      // Yeni seçenekleri ekle (duplicates yok)
      var addedValues = [];
      allOptions.forEach(function (opt) {
        if (addedValues.indexOf(opt.id) === -1) {
          var option = new Option(opt.text, opt.id, false, false);
          $(option).data("ucret", opt.ucret);
          $gorev.append(option);
          addedValues.push(opt.id);
        }
      });

      $gorev.trigger("change.select2");
      if (callback) callback();
    });
  }

  // Departman değişince görev/unvan listesini güncelle
  $("#main_departman").on("change", function () {
    var selectedDepartmanlar = $(this).val();
    loadGorevOptions(selectedDepartmanlar);

    // Departman temizlendiğinde ücreti de temizle
    if (!selectedDepartmanlar || selectedDepartmanlar.length === 0) {
      var $maasTutari = $("#maas_tutari");
      if ($maasTutari.length) {
        $maasTutari.val("₺0,00");
      }
    }
  });

  // Görev seçilince ilgili ücreti maaş tutarına aktar
  $("#main_gorev").on("change", function () {
    var selectedVal = $(this).val();
    var ucret = 0;

    if (selectedVal) {
      var selectedOption = $(this).find('option[value="' + selectedVal + '"]');
      ucret = selectedOption.data("ucret") || 0;
    }

    var numericUcret = parseFloat(ucret) || 0;
    var formattedUcret = numericUcret
      .toFixed(2)
      .replace(".", ",")
      .replace(/\B(?=(\d{3})+(?!\d))/g, ".");

    var $maasTutari = $("#maas_tutari");
    if ($maasTutari.length) {
      $maasTutari.val("₺" + formattedUcret);

      if (selectedVal && numericUcret > 0) {
        if (typeof showToast === "function") {
          showToast(
            selectedVal +
              " unvanı için ücret tutarı maaş alanına aktarıldı: ₺" +
              formattedUcret,
            "info",
          );
        }
      }
    }
  });

  // Sayfa yüklendiğinde mevcut departmana göre görevleri yükle
  var $gorevSelect = $("#main_gorev");
  var currentGorev = $gorevSelect.data("current-gorev") || "";
  var $departman = $("#main_departman");
  var currentDepartmanlar = $departman.val();

  if (currentDepartmanlar && currentDepartmanlar.length > 0) {
    loadGorevOptions(currentDepartmanlar, function () {
      // Mevcut görev tanımlı unvanlar arasında varsa seç
      if (currentGorev) {
        var matchingOption = $gorevSelect.find(
          'option[value="' + currentGorev + '"]',
        );
        if (matchingOption.length > 0) {
          $gorevSelect.val(currentGorev).trigger("change.select2");
        }
      }
    });
  }
});
