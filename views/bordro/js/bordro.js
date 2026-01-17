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
  $(document).on("click", ".btn-gelir-ekle", function () {
    const id = $(this).data("id");
    const ad = $(this).data("ad");

    $("#gelir_personel_id").val(id);
    $("#gelir_personel_ad").text(ad);
    $("#formPersonelGelirEkle")[0].reset();
    $("#modalPersonelGelirEkle").modal("show");
  });

  // Personel Kesinti Ekle Butonu
  $(document).on("click", ".btn-kesinti-ekle", function () {
    const id = $(this).data("id");
    const ad = $(this).data("ad");

    $("#kesinti_personel_id").val(id);
    $("#kesinti_personel_ad").text(ad);
    $("#formPersonelKesintiEkle")[0].reset();
    $("#modalPersonelKesintiEkle").modal("show");
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
