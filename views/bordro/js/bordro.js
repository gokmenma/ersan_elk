$(document).ready(function () {
  // Yıl değiştiğinde sayfayı yenile
  $("#yilSelect").on("change", function () {
    const yil = $(this).val();
    if (yil) {
      window.location.href = "index.php?p=bordro/list&yil=" + yil;
    }
  });

  // Dönem değiştiğinde sayfayı yenile
  $("#donemSelect").on("change", function () {
    const donemId = $(this).val();
    const yil = $("#yilSelect").val();
    if (donemId) {
      window.location.href =
        "index.php?p=bordro/list&yil=" + yil + "&donem=" + donemId;
    }
  });

  // Dönem Sil butonu
  $("#donemSil").on("click", function () {
    const donemId = $("#donemSelect").val();
    const donemAdi = $("#donemSelect option:selected").text();

    if (!donemId) {
      Swal.fire({
        icon: "warning",
        title: "Uyarı",
        text: "Silinecek bir dönem seçili değil.",
      });
      return;
    }

    Swal.fire({
      title: "Dönemi Silmek İstediğinize Emin misiniz?",
      html: `<strong>${donemAdi}</strong> dönemi ve bu döneme ait tüm bordro kayıtları silinecek!<br><br><span class="text-danger"><i class="bx bx-error-circle"></i> Bu işlem geri alınamaz.</span>`,
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
          data: {
            action: "donem-sil",
            donem_id: donemId,
          },
          dataType: "json",
          beforeSend: function () {
            Swal.fire({
              title: "Siliniyor...",
              text: "Dönem silme işlemi gerçekleştiriliyor.",
              allowOutsideClick: false,
              didOpen: () => {
                Swal.showLoading();
              },
            });
          },
          success: function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Başarılı!",
                text: response.message,
                confirmButtonText: "Tamam",
              }).then(() => {
                // Yılı koruyarak sayfayı yenile
                const yil = $("#yilSelect").val();
                window.location.href = "index.php?p=bordro/list&yil=" + yil;
              });
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

  // Yeni Dönem Formu - Form submit'i engelle
  $("#formYeniDonem").on("submit", function (e) {
    e.preventDefault();
    e.stopPropagation();
    console.log("Form submit triggered");

    const formData = new FormData(this);
    formData.append("action", "donem-ekle");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      beforeSend: function () {
        console.log("AJAX request starting...");
        $('#formYeniDonem button[type="submit"]')
          .prop("disabled", true)
          .html('<i class="bx bx-loader bx-spin me-1"></i>Oluşturuluyor...');
      },
      success: function (response) {
        console.log("Response:", response);
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => {
            window.location.href =
              "index.php?p=bordro/list&donem=" + response.donem_id;
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata!",
            text: response.message,
          });
        }
      },
      error: function (xhr, status, error) {
        console.log("AJAX Error:", status, error);
        console.log("Response:", xhr.responseText);
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: "Bir hata oluştu: " + error,
        });
      },
      complete: function () {
        $('#formYeniDonem button[type="submit"]')
          .prop("disabled", false)
          .html('<i class="bx bx-check me-1"></i>Dönem Oluştur');
      },
    });

    return false;
  });

  // Personelleri Güncelle
  $("#btnRefreshPersonel").on("click", function () {
    const donemId = $("#donemSelect").val();
    if (!donemId) return;

    Swal.fire({
      title: "Personeller Güncelleniyor",
      text: "Dönem tarihlerine uygun personeller ekleniyor...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: "personel-guncelle",
        donem_id: donemId,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => {
            location.reload();
          });
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
  });

  // Checkbox işlemleri
  $("#selectAll").on("change", function () {
    $(".personel-check").prop("checked", $(this).prop("checked"));
    updateButtonStates();
  });

  $(document).on("change", ".personel-check", function () {
    updateButtonStates();
    const allChecked =
      $(".personel-check:checked").length === $(".personel-check").length;
    $("#selectAll").prop("checked", allChecked);
  });

  // Maaş Hesapla
  $("#btnHesapla").on("click", function () {
    let selectedIds = getSelectedIds();

    // Eğer hiç personel seçilmemişse tümünü seç
    if (selectedIds.length === 0) {
      $(".personel-check").each(function () {
        selectedIds.push($(this).val());
      });
    }

    if (selectedIds.length === 0) {
      Swal.fire({
        icon: "warning",
        title: "Uyarı",
        text: "Hesaplanacak personel bulunamadı.",
      });
      return;
    }

    Swal.fire({
      title: "Maaş Hesaplanıyor",
      text: selectedIds.length + " personelin maaşı hesaplanıyor...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: "maas-hesapla",
        donem_id: $("#donemSelect").val(),
        personel_ids: selectedIds,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => {
            location.reload();
          });
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
  });

  // Personeli Dönemden Çıkar
  $(document).on("click", ".btn-remove", function () {
    const id = $(this).data("id");
    const row = $(this).closest("tr");
    const personelAdi = row.find("td:eq(1)").text().trim();

    Swal.fire({
      title: "Emin misiniz?",
      text: personelAdi + " dönemden çıkarılacak!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Çıkar",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: {
            action: "personel-cikar",
            id: id,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              row.fadeOut(300, function () {
                $(this).remove();
              });
              Swal.fire({
                icon: "success",
                title: "Başarılı!",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              });
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

  // Detay Görüntüle
  $(document).on("click", ".btn-detail", function () {
    const id = $(this).data("id");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: "get-detail",
        id: id,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          $("#bordroDetailContent").html(response.html);
          $("#bordroDetailModal").modal("show");
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata!",
            text: response.message,
          });
        }
      },
    });
  });

  // Dönem Durum Switch'i (Açık/Kapalı)
  $("#switchDonemDurum").on("change", function () {
    const isChecked = $(this).prop("checked");
    const action = isChecked ? "donem-kapat" : "donem-ac";
    const title = isChecked
      ? "Dönemi Kapatmak İstediğinize Emin misiniz?"
      : "Dönemi Açmak İstediğinize Emin misiniz?";
    const text = isChecked
      ? "Dönem kapatıldığında hesaplama ve personel değişikliği yapılamaz!"
      : "Dönem açıldığında hesaplama ve personel değişikliği yapılabilir.";
    const icon = isChecked ? "warning" : "question";
    const confirmColor = isChecked ? "#d33" : "#28a745";
    const confirmText = isChecked ? "Evet, Kapat" : "Evet, Aç";

    Swal.fire({
      title: title,
      text: text,
      icon: icon,
      showCancelButton: true,
      confirmButtonColor: confirmColor,
      cancelButtonColor: "#6c757d",
      confirmButtonText: confirmText,
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: {
            action: action,
            donem_id: $("#donemSelect").val(),
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Başarılı!",
                text: response.message,
                confirmButtonText: "Tamam",
              }).then(() => {
                location.reload();
              });
            } else {
              Swal.fire({
                icon: "error",
                title: "Hata!",
                text: response.message,
              });
              // Hata durumunda switch'i önceki haline döndür
              $("#switchDonemDurum").prop("checked", !isChecked);
            }
          },
          error: function () {
            Swal.fire({
              icon: "error",
              title: "Hata!",
              text: "Bir hata oluştu.",
            });
            // Hata durumunda switch'i önceki haline döndür
            $("#switchDonemDurum").prop("checked", !isChecked);
          },
        });
      } else {
        // İptal edilirse switch'i önceki haline döndür
        $(this).prop("checked", !isChecked);
      }
    });
  });

  // Gelir Ekle Form
  $("#formGelirEkle").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "gelir-ekle");

    uploadExcelFile(formData, "Gelirler Ekleniyor", "#gelirEkleModal");
  });

  // Kesinti Ekle Form
  $("#formKesintiEkle").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "kesinti-ekle");

    uploadExcelFile(formData, "Kesintiler Ekleniyor", "#kesintiEkleModal");
  });

  // Excel Export
  $("#btnExportExcel").on("click", function () {
    const donemId = $("#donemSelect").val();
    if (donemId) {
      window.location.href =
        "views/bordro/api.php?action=export-excel&donem_id=" + donemId;
    }
  });

  // Excel Export (Banka Formatı)
  $("#btnExportExcelBanka").on("click", function () {
    const donemId = $("#donemSelect").val();
    if (donemId) {
      window.location.href =
        "views/bordro/excel-banka-export.php?donem_id=" + donemId;
    }
  });

  // Ödeme Dağıt Butonu
  $(document).on("click", ".btn-odeme", function () {
    const id = $(this).data("id");
    const ad = $(this).data("ad");
    const net = parseFloat($(this).data("net")) || 0;
    const banka = parseFloat($(this).data("banka")) || 0;
    const sodexo = parseFloat($(this).data("sodexo")) || 0;
    const diger = parseFloat($(this).data("diger")) || 0;

    $("#odeme_bordro_id").val(id);
    $("#odeme_personel_ad").text(ad);
    $("#odeme_net_maas").text(formatMoney(net) + " ₺");
    $("#banka_odemesi").val(banka);
    $("#sodexo_odemesi").val(sodexo);
    $("#diger_odeme").val(diger);

    hesaplaEldenOdeme();
    $("#odemeDagitModal").modal("show");
  });

  // Ödeme inputları değiştiğinde elden ödemeyi hesapla
  $("#banka_odemesi, #sodexo_odemesi, #diger_odeme").on("input", function () {
    hesaplaEldenOdeme();
  });

  // Ödeme Dağıt Form Submit
  $("#formOdemeDagit").on("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append("action", "odeme-dagit");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          $("#odemeDagitModal").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata!",
            text: response.message,
          });
        }
      },
    });
  });
  // Personel Gelir Ekle Butonu
  $(document).on("click", ".btn-gelir-ekle, .btn-detail-ekodeme", function () {
    const id = $(this).data("id");
    const ad = $(this).data("ad");
    const donemId = $("#donemSelect").val();

    $("#gelir_personel_id").val(id);
    $("#gelir_personel_ad").text(ad);
    $("#gelir_edit_id").val(0); // Reset edit ID
    $("#formPersonelGelirEkle")[0].reset();
    $("#formPersonelGelirEkle button[type='submit']").html(
      '<i class="bx bx-save me-1"></i>Kaydet',
    ); // Reset button

    // Kart vurgusunu kaldır
    $(".card.border-primary").removeClass(
      "border-primary bg-primary bg-opacity-10",
    );

    // Listeyi getir
    loadGelirListesi(id, donemId);

    // Accordion'ı aç (ekle butonuysa) veya kapat (detay butonuysa)
    if ($(this).hasClass("btn-detail-ekodeme")) {
      $("#collapseGelir").removeClass("show");
    } else {
      $("#collapseGelir").addClass("show");
    }

    $("#modalPersonelGelirEkle").modal("show");
  });

  // Personel Kesinti Ekle Butonu
  $(document).on(
    "click",
    ".btn-kesinti-ekle, .btn-detail-kesinti",
    function () {
      const id = $(this).data("id");
      const ad = $(this).data("ad");
      const donemId = $("#donemSelect").val();

      $("#kesinti_personel_id").val(id);
      $("#kesinti_personel_ad").text(ad);
      $("#kesinti_edit_id").val(0); // Reset edit ID
      $("#formPersonelKesintiEkle")[0].reset();
      $("#formPersonelKesintiEkle button[type='submit']").html(
        '<i class="bx bx-save me-1"></i>Kaydet',
      ); // Reset button

      // Kart vurgusunu kaldır
      $(".card.border-danger").removeClass(
        "border-danger bg-danger bg-opacity-10",
      );

      // Listeyi getir
      loadKesintiListesi(id, donemId);

      // Accordion'ı aç (ekle butonuysa) veya kapat (detay butonuysa)
      if ($(this).hasClass("btn-detail-kesinti")) {
        $("#collapseKesinti").removeClass("show");
      } else {
        $("#collapseKesinti").addClass("show");
      }

      $("#modalPersonelKesintiEkle").modal("show");
    },
  );

  // Gelir Düzenle Butonu
  $(document).on("click", ".btn-edit-gelir", function () {
    const id = $(this).data("id");
    const tur = $(this).data("tur");
    const tutar = $(this).data("tutar");
    const aciklama = $(this).data("aciklama");

    // Önceki aktif karttan class'ı kaldır
    $(".card.border-primary").removeClass(
      "border-primary bg-primary bg-opacity-10",
    );

    // Tıklanan butona ait kartı bul ve aktif class'ı ekle
    $(this)
      .closest(".card")
      .addClass("border-primary bg-primary bg-opacity-10");

    $("#gelir_edit_id").val(id);
    $("#formPersonelGelirEkle select[name='ek_odeme_tur']")
      .val(tur)
      .trigger("change");
    $("#gelir_tutar").val(tutar);
    $("#gelir_aciklama").val(aciklama);

    $("#formPersonelGelirEkle button[type='submit']").html(
      '<i class="bx bx-check-circle me-1"></i>Güncelle',
    );

    // Accordion'ı aç
    $("#collapseGelir").addClass("show");
  });

  // Kesinti Düzenle Butonu
  $(document).on("click", ".btn-edit-kesinti", function () {
    const id = $(this).data("id");
    const tur = $(this).data("tur");
    const tutar = $(this).data("tutar");
    const aciklama = $(this).data("aciklama");

    // Önceki aktif karttan class'ı kaldır
    $(".card.border-danger").removeClass(
      "border-danger bg-danger bg-opacity-10",
    );

    // Tıklanan butona ait kartı bul ve aktif class'ı ekle
    $(this).closest(".card").addClass("border-danger bg-danger bg-opacity-10");

    $("#kesinti_edit_id").val(id);
    $("#formPersonelKesintiEkle select[name='kesinti_tur']")
      .val(tur)
      .trigger("change");
    $("#kesinti_tutar").val(tutar);
    $("#kesinti_aciklama").val(aciklama);

    $("#formPersonelKesintiEkle button[type='submit']").html(
      '<i class="bx bx-check-circle me-1"></i>Güncelle',
    );

    // Accordion'ı aç
    $("#collapseKesinti").addClass("show");
  });

  // Gelir Silme Butonu
  $(document).on("click", ".btn-delete-gelir", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const personelId = $("#gelir_personel_id").val();
    const donemId = $("#donemSelect").val();

    console.log("Gelir silme isteği:", { id, personelId, donemId });

    Swal.fire({
      title: "Silmek istediğinize emin misiniz?",
      text: "Bu işlem geri alınamaz!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: {
            action: "personel-ek-odeme-sil",
            id: id,
            personel_id: personelId,
            donem_id: donemId,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi!",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              });

              loadGelirListesi(personelId, donemId);

              // Edit modundaysa ve silinen kayıt editlenen kayıt ise formu resetle
              if ($("#gelir_edit_id").val() == id) {
                $("#gelir_edit_id").val(0);
                $("#formPersonelGelirEkle")[0].reset();
                $("#formPersonelGelirEkle button[type='submit']").html(
                  '<i class="bx bx-save me-1"></i>Kaydet',
                );
                $("#collapseGelir").removeClass("show");
              }
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
          error: function (xhr, status, error) {
            console.error("Delete Error:", error);
            Swal.fire("Hata", "Bir hata oluştu: " + error, "error");
          },
        });
      }
    });
  });

  // Kesinti Silme Butonu
  $(document).on("click", ".btn-delete-kesinti", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const personelId = $("#kesinti_personel_id").val();
    const donemId = $("#donemSelect").val();

    console.log("Kesinti silme isteği:", { id, personelId, donemId });

    Swal.fire({
      title: "Silmek istediğinize emin misiniz?",
      text: "Bu işlem geri alınamaz!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: {
            action: "personel-kesinti-sil",
            id: id,
            personel_id: personelId,
            donem_id: donemId,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi!",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              });

              loadKesintiListesi(personelId, donemId);

              // Edit modundaysa ve silinen kayıt editlenen kayıt ise formu resetle
              if ($("#kesinti_edit_id").val() == id) {
                $("#kesinti_edit_id").val(0);
                $("#formPersonelKesintiEkle")[0].reset();
                $("#formPersonelKesintiEkle button[type='submit']").html(
                  '<i class="bx bx-save me-1"></i>Kaydet',
                );
                $("#collapseKesinti").removeClass("show");
              }
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
          error: function (xhr, status, error) {
            console.error("Delete Error:", error);
            Swal.fire("Hata", "Bir hata oluştu: " + error, "error");
          },
        });
      }
    });
  });

  // Personel Gelir Ekle Form Submit
  $("#formPersonelGelirEkle").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "personel-gelir-ekle");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          $("#modalPersonelGelirEkle").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => {
            location.reload();
          });
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
  });

  // Personel Kesinti Ekle Form Submit
  $("#formPersonelKesintiEkle").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "personel-kesinti-ekle");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          $("#modalPersonelKesintiEkle").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: response.message,
            confirmButtonText: "Tamam",
          }).then(() => {
            location.reload();
          });
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
  });
});

// function initDataTable() {
//   if ($.fn.DataTable && $.fn.DataTable.isDataTable("#bordroTable")) {
//     return;
//   }

//   if ($.fn.DataTable) {
//     $("#bordroTable").DataTable({
//       responsive: true,
//       language: {
//         url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json",
//       },
//       columnDefs: [{ orderable: false, targets: [0, 10] }],
//       order: [[1, "asc"]],
//       pageLength: 25,
//       dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
//     });
//   }
// }

function getSelectedIds() {
  const ids = [];
  $(".personel-check:checked").each(function () {
    ids.push($(this).val());
  });
  return ids;
}

function updateButtonStates() {
  const selectedCount = $(".personel-check:checked").length;
  $("#btnHesapla, #btnExportExcel").prop("disabled", selectedCount === 0);
}

function uploadExcelFile(formData, title, modalId) {
  Swal.fire({
    title: title,
    text: "Excel dosyası işleniyor...",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.ajax({
    url: "views/bordro/api.php",
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        $(modalId).modal("hide");
        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: response.message,
          confirmButtonText: "Tamam",
        }).then(() => {
          location.reload();
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: response.message,
        });
      }
    },
    error: function (xhr, status, error) {
      Swal.fire({
        icon: "error",
        title: "Hata!",
        text: "Dosya yüklenirken bir hata oluştu: " + error,
      });
    },
  });
}

function hesaplaEldenOdeme() {
  const net =
    parseFloat(
      $("#odeme_net_maas")
        .text()
        .replace(/[^\d,]/g, "")
        .replace(",", "."),
    ) || 0;
  const banka = parseFloat($("#banka_odemesi").val()) || 0;
  const sodexo = parseFloat($("#sodexo_odemesi").val()) || 0;
  const diger = parseFloat($("#diger_odeme").val()) || 0;

  const elden = net - banka - sodexo - diger;
  $("#elden_odeme_goster").text(formatMoney(elden >= 0 ? elden : 0) + " ₺");

  if (elden < 0) {
    $("#elden_odeme_goster")
      .addClass("text-danger")
      .removeClass("text-warning");
  } else {
    $("#elden_odeme_goster")
      .removeClass("text-danger")
      .addClass("text-warning");
  }
}

function formatMoney(amount) {
  return new Intl.NumberFormat("tr-TR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

function loadGelirListesi(personelId, donemId) {
  $("#listPersonelGelirler").html(
    '<div class="text-center py-3"><div class="spinner-border text-success" role="status"></div></div>',
  );

  $.ajax({
    url: "views/bordro/api.php",
    type: "POST",
    data: {
      action: "get-personel-ek-odeme-listesi",
      personel_id: personelId,
      donem_id: donemId,
    },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        let html = "";

        if (response.data.length === 0) {
          html =
            '<div class="text-center text-muted py-3"><i class="bx bx-info-circle fs-1 mb-2"></i><br>Kayıtlı gelir bulunamadı.</div>';
        } else {
          response.data.forEach((item) => {
            html += `
                        <div class="card mb-2 border shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="avatar-sm">
                                            <span class="avatar-title bg-success bg-opacity-10 text-success rounded-3">
                                                <i class="bx bx-plus-circle fs-3"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-uppercase text-success fw-bold">${item.tur}</h6>
                                        <p class="text-muted mb-0 small">${item.aciklama || "Açıklama yok"}</p>
                                    </div>
                                    <div class="flex-shrink-0 text-end mx-3">
                                        <h5 class="mb-1 text-success fw-bold">+${formatMoney(item.tutar)} ₺</h5>
                                        <small class="text-muted" style="font-size: 11px;">${new Date(item.created_at).toLocaleDateString("tr-TR")}</small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="d-flex flex-column gap-1">
                                            <button type="button" class="btn btn-sm btn-soft-primary btn-edit-gelir" 
                                                data-id="${item.id}" 
                                                data-tur="${item.tur}" 
                                                data-tutar="${item.tutar}" 
                                                data-aciklama="${item.aciklama || ""}">
                                                <i class="bx bx-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-soft-danger btn-delete-gelir" data-id="${item.id}">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
          });
        }
        $("#listPersonelGelirler").html(html);
      }
    },
  });
}

function loadKesintiListesi(personelId, donemId) {
  $("#listPersonelKesintiler").html(
    '<div class="text-center py-3"><div class="spinner-border text-danger" role="status"></div></div>',
  );

  $.ajax({
    url: "views/bordro/api.php",
    type: "POST",
    data: {
      action: "get-personel-kesinti-listesi",
      personel_id: personelId,
      donem_id: donemId,
    },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        let html = "";

        if (response.data.length === 0) {
          html =
            '<div class="text-center text-muted py-3"><i class="bx bx-info-circle fs-1 mb-2"></i><br>Kayıtlı kesinti bulunamadı.</div>';
        } else {
          response.data.forEach((item) => {
            html += `
                        <div class="card mb-2 border shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="avatar-sm">
                                            <span class="avatar-title bg-danger bg-opacity-10 text-danger rounded-3">
                                                <i class="bx bx-minus-circle fs-3"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-uppercase text-danger fw-bold">${item.tur}</h6>
                                        <p class="text-muted mb-0 small">${item.aciklama || "Açıklama yok"}</p>
                                    </div>
                                    <div class="flex-shrink-0 text-end mx-3">
                                        <h5 class="mb-1 text-danger fw-bold">-${formatMoney(item.tutar)} ₺</h5>
                                        <small class="text-muted" style="font-size: 11px;">${new Date(item.created_at).toLocaleDateString("tr-TR")}</small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="d-flex flex-column gap-1">
                                            <button type="button" class="btn btn-sm btn-soft-primary btn-edit-kesinti" 
                                                data-id="${item.id}" 
                                                data-tur="${item.tur}" 
                                                data-tutar="${item.tutar}" 
                                                data-aciklama="${item.aciklama || ""}">
                                                <i class="bx bx-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-kesinti" data-id="${item.id}">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
          });
        }
        $("#listPersonelKesintiler").html(html);
      }
    },
  });
}

// Bordro Detay Yazdır/PDF
$(document).on("click", "#btnPrintBordro", function () {
  const printContent = document.getElementById("bordroDetailContent");
  if (!printContent) return;

  const printWindow = window.open("", "_blank", "width=900,height=700");
  if (!printWindow) {
    Swal.fire({
      icon: "error",
      title: "Hata!",
      text: "Popup penceresi engellenmiş olabilir. Lütfen popup engelleyiciyi devre dışı bırakın.",
    });
    return;
  }

  const printStyles = `
    <style>
      @page {
        size: A4;
        margin: 15mm;
      }
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 12px;
        line-height: 1.4;
        color: #333;
        background: white;
        padding: 20px;
      }
      .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px;
      }
      .col-md-3, .col-md-4, .col-md-6, .col-12 {
        padding: 0 10px;
        margin-bottom: 15px;
      }
      .col-md-3 { width: 25%; }
      .col-md-4 { width: 33.33%; }
      .col-md-6 { width: 50%; }
      .col-12 { width: 100%; }
      .card {
        border: 1px solid #ddd;
        border-radius: 6px;
        margin-bottom: 15px;
        overflow: hidden;
      }
      .card-header {
        padding: 8px 12px;
        font-weight: bold;
        font-size: 11px;
        border-bottom: 1px solid #ddd;
      }
      .card-body {
        padding: 10px 12px;
      }
      .bg-danger { background-color: #dc3545 !important; color: white !important; }
      .bg-warning { background-color: #ffc107 !important; color: #333 !important; }
      .bg-success { background-color: #28a745 !important; color: white !important; }
      .bg-primary { background-color: #0d6efd !important; color: white !important; }
      .border-danger { border-color: #dc3545 !important; }
      .border-warning { border-color: #ffc107 !important; }
      .border-success { border-color: #28a745 !important; }
      .text-danger { color: #dc3545 !important; }
      .text-warning { color: #856404 !important; }
      .text-success { color: #28a745 !important; }
      .text-primary { color: #0d6efd !important; }
      .text-muted { color: #6c757d !important; }
      .text-end { text-align: right; }
      .text-center { text-align: center; }
      .fw-bold { font-weight: bold; }
      .fw-medium { font-weight: 500; }
      .fs-5 { font-size: 14px; }
      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
      }
      table td, table th {
        padding: 5px 8px;
        border-bottom: 1px solid #eee;
      }
      table.table-sm td, table.table-sm th {
        padding: 4px 6px;
      }
      .table-light { background-color: #f8f9fa; }
      .table-success { background-color: #d4edda !important; }
      .table-warning { background-color: #fff3cd !important; }
      h6 {
        font-size: 13px;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #0d6efd;
      }
      .border {
        border: 1px solid #ddd !important;
      }
      .rounded {
        border-radius: 6px;
      }
      .p-3 {
        padding: 10px;
      }
      .mb-0 { margin-bottom: 0; }
      .mb-3 { margin-bottom: 15px; }
      .mt-3 { margin-top: 15px; }
      .mt-4 { margin-top: 20px; }
      .me-1, .me-2 { margin-right: 5px; }
      .ps-3 { padding-left: 10px; }
      .pe-3 { padding-right: 10px; }
      .py-2 { padding-top: 6px; padding-bottom: 6px; }
      small { font-size: 10px; }
      .d-block { display: block; }
      .h-100 { height: 100%; }
      .print-header {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #0d6efd;
      }
      .print-header h3 {
        color: #0d6efd;
        margin: 0;
        font-size: 18px;
      }
      .print-header small {
        color: #666;
        font-size: 11px;
      }
      .print-footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
        text-align: center;
        font-size: 10px;
        color: #666;
      }
      @media print {
        body { padding: 0; }
        .card { page-break-inside: avoid; }
      }
      i.bx { display: none; }
      .bg-opacity-10 { opacity: 0.9; }
    </style>
  `;

  const personelAdi =
    printContent.querySelector(".fw-bold")?.textContent || "Bordro";

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Bordro Detayı - ${personelAdi}</title>
      ${printStyles}
    </head>
    <body>
      <div class="print-header">
        <h3>BORDRO DETAY RAPORU</h3>
        <small>Oluşturulma Tarihi: ${new Date().toLocaleString("tr-TR")}</small>
      </div>
      ${printContent.innerHTML}
      <div class="print-footer">
        Bu belge bordro sisteminden otomatik olarak oluşturulmuştur.
      </div>
    </body>
    </html>
  `);

  printWindow.document.close();

  // Sayfa yüklendikten sonra yazdır
  printWindow.onload = function () {
    printWindow.focus();
    printWindow.print();
  };
});
