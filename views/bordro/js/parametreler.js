$(document).ready(function () {
  // Dönem seçimi
  $("#donemSecimi").select2({
    minimumResultsForSearch: Infinity,
  });

  $("#donemSecimi").on("change", function () {
    const target = $(this).val();
    $(".donem-content").removeClass("active show");
    $("#" + target).addClass("active show");
  });

  // Kategori değişince hesaplama tiplerini güncelle
  $('select[name="kategori"]').on("change", function () {
    const kategori = $(this).val();

    // Header rengini güncelle
    const modalHeader = $("#modalParametreEkle .modal-header");
    if (kategori === "kesinti") {
      modalHeader.removeClass("bg-success").addClass("bg-danger");
    } else {
      modalHeader.removeClass("bg-danger").addClass("bg-success");
    }

    const $hesaplamaTipi = $('select[name="hesaplama_tipi"]');
    const currentVal = $hesaplamaTipi.val();

    $hesaplamaTipi.empty();

    let options =
      kategori === "gelir" ? hesaplamaTipleriGelir : hesaplamaTipleriKesinti;

    $.each(options, function (key, value) {
      $hesaplamaTipi.append(new Option(value, key));
    });

    // Eğer mevcut değer yeni listede varsa koru, yoksa ilkini seç
    if (options[currentVal]) {
      $hesaplamaTipi.val(currentVal);
    } else {
      $hesaplamaTipi.val(Object.keys(options)[0]);
    }

    $hesaplamaTipi.trigger("change");
  });

  $('select[name="hesaplama_tipi"]').on("change", function () {
    const val = $(this).val();
    if (!val) return;
    const isGunluk = val.startsWith("gunluk_");
    const isAylikGun = val.startsWith("aylik_gun_");

    // Kısmi Muaf kontrolü
    if (val === "kismi_muaf" || val === "gunluk_kismi_muaf") {
      $("#muafiyetAyarlari").slideDown();
    } else {
      $("#muafiyetAyarlari").slideUp();
    }

    // Günlük veya Aylık (Çalışılan Güne Göre) mi?
    if (isGunluk || isAylikGun) {
      $("#gunlukAyarlar").slideDown();
      if (isGunluk) {
        $("#divGunlukTutar").slideDown();
        $("#divTutar").hide();
      } else {
        $("#divGunlukTutar").hide();
        $("#divTutar").slideDown(); // Aylık tutar girilecek
      }
      $("#divOran").hide();
    } else {
      $("#gunlukAyarlar").slideUp();
      $("#divGunlukTutar").hide();

      // Oran Bazlı kontrolü
      if (
        ["oran_bazli_vergi", "oran_bazli_sgk", "oran_bazli_net"].includes(val)
      ) {
        $("#divOran").slideDown();
        $("#divTutar").hide();
      } else {
        $("#divOran").slideUp();
        $("#divTutar").show();
      }
    }
  });

  // Gün sayısı radioları değişikliğinde
  $('input[name="gun_sayisi_otomatik"]').on("change", function () {
    if ($(this).val() === "0") {
      $("#divVarsayilanGun").slideDown();
    } else {
      $("#divVarsayilanGun").slideUp();
    }
  });

  // Parametre düzenleme
  $(document).on("click", ".btn-edit-param", function () {
    const param = $(this).data("param");

    $("#param_id").val(param.id);
    $('input[name="kod"]').val(param.kod);
    $('input[name="etiket"]').val(param.etiket);
    $('select[name="kategori"]').val(param.kategori).trigger("change");
    $('select[name="hesaplama_tipi"]')
      .val(param.hesaplama_tipi)
      .trigger("change");
    $('select[name="muaf_limit_tipi"]')
      .val(param.muaf_limit_tipi)
      .trigger("change");
    $('input[name="gunluk_muaf_limit"]').val(param.gunluk_muaf_limit);
    $('input[name="aylik_muaf_limit"]').val(param.aylik_muaf_limit);
    $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
    $('input[name="oran"]').val(param.oran);
    $('input[name="sira"]').val(param.sira);
    $('input[name="aciklama"]').val(param.aciklama);
    $('input[name="gecerlilik_baslangic"]').val(param.gecerlilik_baslangic);
    $('input[name="gecerlilik_bitis"]').val(param.gecerlilik_bitis);

    $("#sgk_matrahi_dahil").prop("checked", param.sgk_matrahi_dahil == 1);
    $("#gelir_vergisi_dahil").prop("checked", param.gelir_vergisi_dahil == 1);
    $("#damga_vergisi_dahil").prop("checked", param.damga_vergisi_dahil == 1);

    $('input[name="gunluk_tutar"]').val(param.gunluk_tutar || 0);
    $('input[name="varsayilan_gun_sayisi"]').val(
      param.varsayilan_gun_sayisi || 26,
    );
    $(
      'input[name="gun_sayisi_otomatik"][value="' +
        (param.gun_sayisi_otomatik || 0) +
        '"]',
    ).prop("checked", true);

    $("#modalParametreEkle .modal-title").html(
      '<i class="bx bx-edit me-2"></i>Parametre Düzenle',
    );

    if (param.kategori === "gelir") {
      $("#sgk_matrah_label").html("SGK Matrahına Dahil");
      $("#gelir_vergisi_label").html("Gelir Vergisine Dahil");
      $("#damga_vergisi_label").html("Damga Vergisine Dahil");
    } else {
      $("#sgk_matrah_label").html("SGK Matrahından Düşülür");
      $("#gelir_vergisi_label").html("Gelir Vergisinden Düşülür");
      $("#damga_vergisi_label").html("Damga Vergisinden Düşülür");
    }

    $("#modalParametreEkle").modal("show");
  });

  // Parametre kopyalama (yeni dönem için)
  $(document).on("click", ".btn-copy-param", function () {
    const param = $(this).data("param");

    $("#param_id").val(""); // Yeni kayıt olacak
    $('input[name="kod"]').val(param.kod);
    $('input[name="etiket"]').val(param.etiket);
    $('select[name="kategori"]').val(param.kategori).trigger("change");
    $('select[name="hesaplama_tipi"]')
      .val(param.hesaplama_tipi)
      .trigger("change");
    $('select[name="muaf_limit_tipi"]')
      .val(param.muaf_limit_tipi)
      .trigger("change");
    $('input[name="gunluk_muaf_limit"]').val(param.gunluk_muaf_limit);
    $('input[name="aylik_muaf_limit"]').val(param.aylik_muaf_limit);
    $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
    $('input[name="oran"]').val(param.oran);
    $('input[name="sira"]').val(param.sira);
    $('input[name="aciklama"]').val(param.aciklama);

    $('input[name="gecerlilik_baslangic"]').val("");
    $('input[name="gecerlilik_bitis"]').val("");

    $("#sgk_matrahi_dahil").prop("checked", param.sgk_matrahi_dahil == 1);
    $("#gelir_vergisi_dahil").prop("checked", param.gelir_vergisi_dahil == 1);
    $("#damga_vergisi_dahil").prop("checked", param.damga_vergisi_dahil == 1);

    $('input[name="gunluk_tutar"]').val(param.gunluk_tutar || 0);
    $('input[name="varsayilan_gun_sayisi"]').val(
      param.varsayilan_gun_sayisi || 26,
    );
    $(
      'input[name="gun_sayisi_otomatik"][value="' +
        (param.gun_sayisi_otomatik || 0) +
        '"]',
    ).prop("checked", true);

    $("#modalParametreEkle .modal-title").html(
      '<i class="bx bx-copy me-2"></i>Yeni Dönem Ekle (Kopyala)',
    );
    $("#modalParametreEkle").modal("show");
  });

  // Parametre formu submit
  $("#formParametre").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append(
      "action",
      $("#param_id").val() ? "update-parametre" : "add-parametre",
    );

    formData.set(
      "sgk_matrahi_dahil",
      $("#sgk_matrahi_dahil").is(":checked") ? 1 : 0,
    );
    formData.set(
      "gelir_vergisi_dahil",
      $("#gelir_vergisi_dahil").is(":checked") ? 1 : 0,
    );
    formData.set(
      "damga_vergisi_dahil",
      $("#damga_vergisi_dahil").is(":checked") ? 1 : 0,
    );

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => location.reload());
        } else {
          Swal.fire({ icon: "error", title: "Hata!", text: response.message });
        }
      },
      error: function () {
        Swal.fire({ icon: "error", title: "Hata!", text: "Bir hata oluştu." });
      },
    });
  });

  // Genel Ayar formu submit
  $("#formGenelAyar").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append(
      "action",
      $("#ayar_id").val() ? "update-genel-ayar" : "add-genel-ayar",
    );

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => location.reload());
        } else {
          Swal.fire({ icon: "error", title: "Hata!", text: response.message });
        }
      },
    });
  });

  // Ayar düzenleme
  $(document).on("click", ".btn-edit-ayar", function () {
    const ayar = $(this).data("ayar");
    $("#ayar_id").val(ayar.id);
    $('input[name="parametre_kodu"]').val(ayar.parametre_kodu);
    $('input[name="parametre_adi"]').val(ayar.parametre_adi);
    $('input[name="deger"]').val(ayar.deger);
    $('input[name="ayar_gecerlilik_baslangic"]').val(ayar.gecerlilik_baslangic);
    $('input[name="ayar_gecerlilik_bitis"]').val(ayar.gecerlilik_bitis);
    $("#ayar_aktif").prop("checked", ayar.aktif == 1);
    $('input[name="ayar_aciklama"]').val(ayar.aciklama);

    $("#modalGenelAyarEkle .modal-title").html(
      '<i class="bx bx-edit me-2"></i>Ayar Düzenle',
    );
    $("#modalGenelAyarEkle").modal("show");
  });

  // Ayar durum değiştirme
  $(document).on("change", ".switch-ayar-status", function () {
    const id = $(this).data("id");
    const aktif = $(this).is(":checked") ? 1 : 0;

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: { action: "toggle-genel-ayar-status", id: id, aktif: aktif },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          const Toast = Swal.mixin({
            toast: true,
            position: "top-end",
            showConfirmButton: false,
            timer: 3000,
          });
          Toast.fire({
            icon: "success",
            title: "Durum güncellendi",
          });
          const row = $('.switch-ayar-status[data-id="' + id + '"]').closest(
            "tr",
          );
          if (aktif) {
            row.removeClass("table-secondary text-muted");
          } else {
            row.addClass("table-secondary text-muted");
          }
        } else {
          Swal.fire({ icon: "error", title: "Hata!", text: response.message });
          $(this).prop("checked", !aktif);
        }
      },
      error: function () {
        Swal.fire({ icon: "error", title: "Hata!", text: "Bir hata oluştu." });
      },
    });
  });

  // Ayar silme
  $(document).on("click", ".btn-delete-ayar", function () {
    const id = $(this).data("id");
    const adi = $(this).data("adi");

    Swal.fire({
      title: "Silmek istediğinize emin misiniz?",
      html: "<strong>" + adi + "</strong> ayarı silinecek.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: { action: "delete-genel-ayar", id: id },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi!",
                text: response.message,
                confirmButtonText: "Tamam",
              }).then(() => location.reload());
            } else {
              Swal.fire({
                icon: "error",
                title: "Hata!",
                text: response.message,
              });
            }
          },
        });
      }
    });
  });

  // Yeni Dönem modalı açıldığında mevcut ayarları listele
  $("#modalYeniDonem").on("show.bs.modal", function () {
    let html = "";
    genelAyarlar.forEach(function (ayar) {
      const isOran = ayar.parametre_kodu.includes("orani");
      const degerStr = isOran
        ? "%" + parseFloat(ayar.deger).toFixed(2)
        : parseFloat(ayar.deger).toLocaleString("tr-TR") + " ?";

      html += "<tr>";
      html +=
        '<td><input type="checkbox" name="ayar_sec[]" value="' +
        ayar.id +
        '" checked class="ayar-checkbox"></td>';
      html +=
        "<td>" +
        ayar.parametre_adi +
        '<br><code class="small">' +
        ayar.parametre_kodu +
        "</code></td>";
      html += "<td>" + degerStr + "</td>";
      html +=
        '<td><input type="number" step="0.01" class="form-control form-control-sm" name="yeni_deger[' +
        ayar.id +
        ']" value="' +
        ayar.deger +
        '"></td>';
      html += "</tr>";
    });
    $("#donemAyarListesi").html(html);
  });

  // Tümünü seç/kaldır
  $("#selectAllAyar").on("change", function () {
    $(".ayar-checkbox").prop("checked", $(this).is(":checked"));
  });

  // Yeni Dönem formu submit
  $("#formYeniDonem").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "copy-genel-ayarlar");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => location.reload());
        } else {
          Swal.fire({ icon: "error", title: "Hata!", text: response.message });
        }
      },
      error: function () {
        Swal.fire({ icon: "error", title: "Hata!", text: "Bir hata oluştu." });
      },
    });
  });

  // Modal kapandığında formu sıfırla
  $("#modalParametreEkle").on("hidden.bs.modal", function () {
    $("#formParametre")[0].reset();
    $("#param_id").val("");
    $("#modalParametreEkle .modal-title").html(
      '<i class="bx bx-plus-circle me-2"></i>Yeni Parametre Ekle',
    );
    $("#modalParametreEkle .modal-header")
      .removeClass("bg-danger")
      .addClass("bg-success");
    $("#muafiyetAyarlari").hide();
    setTimeout(function () {
      $('select[name="kategori"]').trigger("change");
    }, 50);
  });

  // Parametre silme
  $(document).on("click", ".btn-delete-param", function () {
    const id = $(this).data("id");
    const etiket = $(this).data("etiket");

    Swal.fire({
      title: "Silmek istediğinize emin misiniz?",
      html: "<strong>" + etiket + "</strong> parametresi silinecek.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: { action: "delete-parametre", id: id },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi!",
                text: response.message,
                confirmButtonText: "Tamam",
              }).then(() => location.reload());
            } else {
              Swal.fire({
                icon: "error",
                title: "Hata!",
                text: response.message,
              });
            }
          },
          error: function () {
            Swal.fire({
              icon: "error",
              title: "Hata!",
              text: "Bir hata oluştu.",
            });
          },
        });
      }
    });
  });

  // Sayfa yüklendiğinde listeleri güncelle
  $('select[name="kategori"]').trigger("change");
});
