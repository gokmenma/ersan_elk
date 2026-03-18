// Turkish toUpper Helper
function turkishToUpper(str) {
  if (!str) return "";
  var letters = {
    i: "İ",
    ş: "Ş",
    ğ: "Ğ",
    ü: "Ü",
    ö: "Ö",
    ç: "Ç",
    ı: "I",
  };
  return str
    .replace(/(([iışğüöçı]))/g, function (letter) {
      return letters[letter];
    })
    .toUpperCase();
}

const monthMap = {
  OCAK: 1,
  ŞUBAT: 2,
  MART: 3,
  NİSAN: 4,
  MAYIS: 5,
  HAZİRAN: 6,
  TEMMUZ: 7,
  AĞUSTOS: 8,
  EYLÜL: 9,
  EKİM: 10,
  KASIM: 11,
  ARALIK: 12,
};

$(document).ready(function () {
  // Bordro Tablosunu Başlat
  var bordroOpts = getDatatableOptions();
  var originalInitComplete = bordroOpts.initComplete;
  bordroOpts.columnDefs = [{ orderable: false, targets: [0, 10] }];
  bordroOpts.order = [[1, "asc"]];
  bordroOpts.pageLength = 25;
  bordroOpts.initComplete = function (settings, json) {
    // Önce orijinal initComplete'i çalıştır (filtreler, arama kutuları vb.)
    if (typeof originalInitComplete === "function") {
      originalInitComplete.call(this, settings, json);
    }
    // Tablo hazır - satırları göster ve preloader'ı kapat
    $("#bordroTable").addClass("dt-ready");
    $("#bordro-loader").fadeOut(300);

   
  };
  $("#bordroTable").DataTable(applyLengthStateSave(bordroOpts));

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
              showToast(response.message, "success");
              setTimeout(() => {
                // Yılı koruyarak sayfayı yenile
                const yil = $("#yilSelect").val();
                window.location.href = "index.php?p=bordro/list&yil=" + yil;
              }, 1000);
            } else {
              showToast(response.message, "error");
            }
          },
          error: function () {
            showToast("Bir hata oluştu.", "error");
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
          showToast(response.message, "success");
          setTimeout(() => {
            window.location.href =
              "index.php?p=bordro/list&donem=" + response.donem_id;
          }, 1000);
        } else {
          showToast(response.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.log("AJAX Error:", status, error);
        showToast("Bir hata oluştu: " + error, "error");
      },
      complete: function () {
        $('#formYeniDonem button[type="submit"]')
          .prop("disabled", false)
          .html('<i class="bx bx-check me-1"></i>Dönem Oluştur');
      },
    });

    return false;
  });

  // Dönem Ay/Yıl Değiştiğinde Bilgileri Güncelle
  function updateDonemBilgileri() {
    const ayVal = $("#donem_ay").val();
    const yilVal = $("#donem_yil").val();

    if (ayVal && yilVal) {
      const ayAd = $("#donem_ay option:selected").text();
      const donemAdi = turkishToUpper(ayAd) + " " + yilVal;
      $("#donem_adi_hidden").val(donemAdi);

      // Başlangıç ve Bitiş tarihlerini hesapla
      const month = parseInt(ayVal) - 1;
      const year = parseInt(yilVal);

      const firstDayDate = new Date(year, month, 1);
      const lastDayDate = new Date(year, month + 1, 0);

      const fDay = String(firstDayDate.getDate()).padStart(2, "0");
      const fMonth = String(firstDayDate.getMonth() + 1).padStart(2, "0");
      const fYear = firstDayDate.getFullYear();
      const firstDay = `${fDay}.${fMonth}.${fYear}`;

      const lDay = String(lastDayDate.getDate()).padStart(2, "0");
      const lMonth = String(lastDayDate.getMonth() + 1).padStart(2, "0");
      const lYear = lastDayDate.getFullYear();
      const lastDay = `${lDay}.${lMonth}.${lYear}`;

      $("#baslangic_tarihi").val(firstDay);
      $("#bitis_tarihi").val(lastDay);
    }
  }

  $(document).on("change", "#donem_ay, #donem_yil", function () {
    updateDonemBilgileri();
  });

  // Modal açıldığında ilk hesaplamayı yap
  $("#yeniDonemModal").on("shown.bs.modal", function () {
    updateDonemBilgileri();
  });

  // Düzenleme Modalı İçin Bilgi Güncelleme
  function updateEditDonemBilgileri() {
    const ayVal = $("#edit_donem_ay").val();
    const yilVal = $("#edit_donem_yil").val();
    if (ayVal && yilVal) {
      const ayAd = $("#edit_donem_ay option:selected").text();
      const donemAdi = turkishToUpper(ayAd) + " " + yilVal;
      $("#edit_donem_adi_hidden").val(donemAdi);
    }
  }

  $(document).on("change", "#edit_donem_ay, #edit_donem_yil", function () {
    updateEditDonemBilgileri();
  });

  // Dönem Adı Düzenle Butonu
  $(document).on("click", "#btnEditDonemAdi, #btnHeaderEditDonem", function () {
    const currentName = $("#displayDonemAdi").text().trim();
    const parts = currentName.split(" ");

    if (parts.length >= 2) {
      const year = parts.pop();
      const monthName = turkishToUpper(parts.join(" "));
      const monthIndex = monthMap[monthName] || "";

      $("#edit_donem_ay").val(monthIndex).trigger("change.select2");
      $("#edit_donem_yil").val(year).trigger("change.select2");
      $("#edit_donem_adi_hidden").val(currentName);
    }
    showModal("modalDonemGuncelle");
  });

  // Dönem Güncelle Formu
  $("#formDonemGuncelle").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "donem-guncelle");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      beforeSend: function () {
        $('#formDonemGuncelle button[type="submit"]')
          .prop("disabled", true)
          .html('<i class="bx bx-loader bx-spin me-1"></i>Güncelleniyor...');
      },
      success: function (response) {
        if (response.status === "success") {
          hideModal("modalDonemGuncelle");
          $("#displayDonemAdi").text(response.donem_adi);
          // Dönem selectbox'ındaki metni de güncelle
          const donemId = formData.get("donem_id");
          $(`#donemSelect option[value="${donemId}"]`).text(response.donem_adi);
          $("#donemSelect").trigger("change.select2");

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
      error: function () {
        showToast("Bir hata oluştu.", "error");
      },
      complete: function () {
        $('#formDonemGuncelle button[type="submit"]')
          .prop("disabled", false)
          .html('<i class="bx bx-save me-1"></i>Güncelle');
      },
    });
  });

  $(document).on("change", "#baslangic_tarihi", function () {
    let baslangic = $(this).val();
    let bitis = ayinSonGununuGetir(baslangic);

    if (bitis) {
      $("#bitis_tarihi").val(bitis);
    }
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
          showToast(response.message, "success");
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          showToast(response.message, "error");
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
    const isChecked = $(this).prop("checked");
    const table = $("#bordroTable").DataTable();
    // Tüm sayfalardaki checkboxları seç/kaldır
    table.$(".personel-check").prop("checked", isChecked);
    updateButtonStates();
  });

  $(document).on("change", ".personel-check", function () {
    updateButtonStates();
    const table = $("#bordroTable").DataTable();
    const allCheckboxes = table.$(".personel-check");
    const checkedCheckboxes = table.$(".personel-check:checked");
    const allChecked =
      allCheckboxes.length > 0 &&
      checkedCheckboxes.length === allCheckboxes.length;
    $("#selectAll").prop("checked", allChecked);
  });

  // Maaş Hesapla
  $("#btnHesapla").on("click", function () {
    let selectedIds = getSelectedIds();

    // Eğer hiç personel seçilmemişse tümünü seç (tüm sayfalardan)
    if (selectedIds.length === 0) {
      const table = $("#bordroTable").DataTable();
      table.$(".personel-check").each(function () {
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
          // Onay bekleyen kesinti veya icra uyarısı varsa göster
          if (response.warning) {
            var detailsHtml = response.warning_details
              ? '<div class="mt-3 mb-2 text-start px-2"><strong>Uyarı Detayları:</strong></div>' +
                '<div class="border rounded p-3 bg-light text-start shadow-sm mx-2">' +
                response.warning_details +
                "</div>"
              : "";

            Swal.fire({
              icon: "warning",
              title: "Maaş Hesaplandı - Uyarılar",
              html:
                '<p class="mb-3">' +
                response.message +
                "</p>" +
                '<div class="alert alert-warning text-start mb-2 mx-1">' +
                '<p class="mb-0 small">' +
                response.warning +
                "</p>" +
                "</div>" +
                detailsHtml +
                '<p class="text-muted small mt-3">Detaylar için ilgili personelin yönetim sayfasını ziyaret edebilirsiniz.</p>',
              confirmButtonText: "Sistemi Yenile",
              width: "650px",
              customClass: {
                htmlContainer: "px-0",
              },
              didOpen: () => {
                if (typeof feather !== "undefined") {
                  feather.replace();
                }
              },
            }).then(() => {
              location.reload();
            });
          } else {
            Swal.fire({
              icon: "success",
              title: "Başarılı!",
              text: response.message,
              confirmButtonText: "Tamam",
            }).then(() => {
              location.reload();
            });
          }
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
    const personelAdi =
      $(this).data("ad") || row.find("td:eq(4)").find("a").text().trim();

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
              showToast(response.message, "success");
            } else {
              showToast(response.message, "error");
            }
          },
        });
      }
    });
  });

  // Detay Görüntüle
  $(document).on("click", ".btn-detail", function () {
    const id = $(this).data("id");
    console.log("Bordro Detay tıklandı, ID:", id);

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
          showModal("bordroDetailModal");
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata!",
            text: response.message,
          });
        }
      },
      error: function (xhr, status, error) {
        console.error("Detay getirme hatası:", error);
      },
    });
  });

  // İcra Detay Görüntüle
  $(document).on("click", ".btn-icra-detail", function () {
    const id = $(this).data("id");

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: "get-icra-detail",
        id: id,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          $("#icra_detay_personel_ad").text(response.personel_ad);
          $("#icra_detay_content").html(response.html);
          showModal("modalIcraDetay");
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata!",
            text: response.message,
          });
        }
      },
      error: function (xhr, status, error) {
        console.error("İcra detay getirme hatası:", error);
        Swal.fire({
          icon: "error",
          title: "Sistem Hatası",
          text: "Detaylar yüklenirken bir hata oluştu.",
        });
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
        donemDurumGuncelle(action, isChecked, false);
      } else {
        // İptal edilirse switch'i önceki haline döndür
        $(this).prop("checked", !isChecked);
      }
    });
  });

  // Dönem durumu güncelleme fonksiyonu
  function donemDurumGuncelle(action, isChecked, forceClose) {
    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: action,
        donem_id: $("#donemSelect").val(),
        force_close: forceClose ? "1" : "0",
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
        } else if (response.status === "warning") {
          // Bekleyen avans/izin uyarısı
          let warningHtml =
            '<div class="text-start"><p class="mb-2">' +
            response.message +
            "</p><ul class='mb-0 ps-3'>";
          response.warnings.forEach(function (w) {
            warningHtml +=
              '<li class="text-warning"><i class="bx bx-error me-1"></i>' +
              w +
              "</li>";
          });
          warningHtml += "</ul></div>";

          Swal.fire({
            icon: "warning",
            title: "Bekleyen Talepler Var!",
            html: warningHtml,
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonColor: "#d33",
            denyButtonColor: "#6c757d",
            cancelButtonColor: "#28a745",
            confirmButtonText: '<i class="bx bx-lock me-1"></i>Yine de Kapat',
            denyButtonText: '<i class="bx bx-x me-1"></i>İptal',
            cancelButtonText:
              '<i class="bx bx-check-circle me-1"></i>Talepleri İncele',
          }).then((result) => {
            if (result.isConfirmed) {
              // Zorla kapat
              donemDurumGuncelle(action, isChecked, true);
            } else {
              // İptal veya Talepleri incele - switch'i geri al
              $("#switchDonemDurum").prop("checked", false);
            }
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
  }

  // Personel Görsün Switch'i
  $("#switchPersonelGorsun").on("change", function () {
    const isChecked = $(this).prop("checked");
    const donemId = $("#donemSelect").val();

    if (!donemId) return;

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: "donem-personel-gorsun-guncelle",
        donem_id: donemId,
        personel_gorsun: isChecked ? 1 : 0,
      },
      dataType: "json",
      beforeSend: function () {
        Swal.fire({
          title: "Güncelleniyor...",
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          },
        });
      },
      success: function (response) {
        Swal.close();
        if (response.status === "success") {
          showToast(response.message, "success");
          // Label metnini ve rengini de güncelleyebilirsiniz (opsiyonel ama şık olur)
          const label = $("label[for='switchPersonelGorsun']");
          if (isChecked) {
            label.removeClass("text-danger").addClass("text-success");
          } else {
            label.removeClass("text-success").addClass("text-danger");
          }
        } else {
          showToast(response.message, "error");
          // Hata durumunda switch'i geri al
          $("#switchPersonelGorsun").prop("checked", !isChecked);
        }
      },
      error: function () {
        Swal.close();
        showToast("Bir hata oluştu.", "error");
        $("#switchPersonelGorsun").prop("checked", !isChecked);
      },
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

  // Ödeme Dağıt (Excel) Form
  $("#formOdemeEkle").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("action", "odeme-dagit-excel");

    uploadExcelFile(formData, "Ödemeler Dağıtılıyor", "#odemeEkleModal");
  });

  // Personel Resimi Tooltip Preview
  $(document).on("mouseenter", ".personel-img-zoom", function () {
    $(this).siblings(".img-preview-tooltip").stop().fadeIn(200);
  }).on("mouseleave", ".personel-img-zoom", function () {
    $(this).siblings(".img-preview-tooltip").stop().fadeOut(150);
  });

  // Filtrelenmiş ID'leri al
  function getFilteredIds() {
    const table = $("#bordroTable").DataTable();
    const info = table.page.info();

    // Eğer filtre uygulanmışsa (toplam kayıt ile filtrelenmiş kayıt sayısı farklıysa)
    if (info.recordsDisplay < info.recordsTotal) {
      const rows = table.rows({ filter: "applied" }).nodes();
      const ids = [];
      $(rows).each(function () {
        const id = $(this).data("id");
        if (id) ids.push(id);
      });
      return ids;
    }
    return [];
  }

  // Excel Export
  $("#btnExportExcel").on("click", function () {
    const donemId = $("#donemSelect").val();
    if (donemId) {
      let url = "views/bordro/export-excel.php?donem_id=" + donemId;
      const ids = getFilteredIds();
      if (ids.length > 0) {
        url += "&ids=" + ids.join(",");
      }
      window.location.href = url;
    }
  });

  // Excel Export (Banka Formatı)
  $("#btnExportExcelBanka").on("click", function () {
    const donemId = $("#donemSelect").val();
    if (donemId) {
      let url = "views/bordro/excel-banka-export.php?donem_id=" + donemId;
      const ids = getFilteredIds();
      if (ids.length > 0) {
        url += "&ids=" + ids.join(",");
      }
      window.location.href = url;
    }
  });

  // Excel Export (Sodexo Formatı)
  $("#btnExportExcelSodexo").on("click", function () {
    const donemId = $("#donemSelect").val();
    if (donemId) {
      let url = "views/bordro/excel-sodexo-export.php?donem_id=" + donemId;
      const ids = getFilteredIds();
      if (ids.length > 0) {
        url += "&ids=" + ids.join(",");
      }
      window.location.href = url;
    }
  });

  $(document).on("click", ".btn-odeme", function () {
    const id = $(this).data("id");
    const ad = $(this).data("ad");
    const net = parseFloat($(this).data("net")) || 0;
    const banka = parseFloat($(this).data("banka")) || 0;
    const sodexo = parseFloat($(this).data("sodexo")) || 0;
    const icra = parseFloat($(this).data("icra")) || 0;
    const diger = parseFloat($(this).data("diger")) || 0;

    $("#odeme_bordro_id").val(id);
    $("#odeme_personel_ad").text(ad);
    $("#odeme_net_maas").text(formatMoney(net) + " ₺ (Net Alacağı)");
    $("#odeme_icra_tutari").val(icra); // Hidden input if added or just for calc
    $("#banka_odemesi").val(banka);
    $("#sodexo_odemesi").val(sodexo);
    $("#diger_odeme").val(diger);

    // Toplam alacak değerini sakla (Sodexo limit kontrolü için)
    $("#formOdemeDagit").data("toplam_alacak", $(this).data("toplam_alacak"));

    hesaplaEldenOdeme();
    showModal("odemeDagitModal");
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
          hideModal("odemeDagitModal");
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

  // Ödeme Dağıtımı Varsayılana Dön
  $("#btnOdemeReset").on("click", function () {
    const id = $("#odeme_bordro_id").val();
    if (!id) return;

    Swal.fire({
      title: "Emin misiniz?",
      text: "Manuel yaptığınız tüm ödeme dağılımları silinecek ve varsayılan sistem hesaplamasına dönülecektir.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#f1b44c",
      confirmButtonText: "Evet, Varsayılana Dön",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/bordro/api.php",
          type: "POST",
          data: {
            action: "odeme-reset",
            id: id,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              hideModal("odemeDagitModal");
              Swal.fire({
                icon: "success",
                title: "Başarılı!",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              }).then(() => {
                location.reload();
              });
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
          error: function () {
            Swal.fire("Hata", "Bir hata oluştu.", "error");
          },
        });
      }
    });
  });

  // Satırda Sodexo Düzenleme İkonu
  $(document).on("click", ".btn-edit-sodexo-inline", function () {
    const parent = $(this).closest(".sodexo-wrapper");
    const span = parent.find(".sodexo-value");
    const input = parent.find(".update-sodexo");
    const icon = $(this);

    span.addClass("d-none");
    icon.addClass("d-none");
    input.removeClass("d-none").focus();
  });

  // Input odağını kaybettiğinde eski haline dön
  $(document).on("blur", ".update-sodexo", function () {
    const input = $(this);
    const parent = input.closest(".sodexo-wrapper");
    const span = parent.find(".sodexo-value");
    const icon = parent.find(".btn-edit-sodexo-inline");

    // Eğer değer değişmediyse veya değişim işlemi devam ediyorsa/bittiyse gizle
    // Not: change event'i blur'dan önce tetiklenir
    setTimeout(() => {
      input.addClass("d-none");
      span.removeClass("d-none");
      icon.removeClass("d-none");
    }, 200);
  });

  // Satırda Sodexo Güncelleme
  $(document).on("change", ".update-sodexo", function () {
    const input = $(this);
    const id = input.data("id");
    const toplam_alacak = parseFloat(input.data("toplam_alacak")) || 0;
    const net = parseFloat(input.data("net")) || 0;
    const banka = parseFloat(input.data("banka")) || 0;
    const diger = parseFloat(input.data("diger")) || 0;
    const icra = parseFloat(input.data("icra")) || 0;
    const parent = input.closest(".sodexo-wrapper");
    const span = parent.find(".sodexo-value");
    const oldSodexo = parseFloat(input.attr("data-current-val")) || 0;

    // IMask değerinden sayıya çevir
    let val = input.val();
    val = val.replace("₺", "").replace(/\./g, "").replace(",", ".");
    const sodexo = parseFloat(val) || 0;

    // Üst sınır kontrolü (%20)
    const maxSodexo = toplam_alacak * 0.20;
    if (sodexo > maxSodexo) {
      Swal.fire({
        icon: "warning",
        title: "Üst Sınır Uyarısı",
        text: `Sodexo tutarı toplam alacağın %20'ini (${formatMoney(maxSodexo)} ₺) geçemez!`,
      });
      // Değeri geri al
      span.text(formatMoney(oldSodexo) + " ₺");
      input.val(formatMoney(oldSodexo));
      return;
    }

    const elden = Math.max(0, net - banka - sodexo - icra - diger);
    const diff = sodexo - oldSodexo;

    $.ajax({
      url: "views/bordro/api.php",
      type: "POST",
      data: {
        action: "odeme-dagit",
        id: id,
        banka_odemesi: banka,
        sodexo_odemesi: sodexo,
        diger_odeme: diger,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          // Toastify ile bildirim ver
          Toastify({
            text: "Sodexo tutarı güncellendi. Bordroyu tekrar hesaplamanız gerekmektedir.",
            duration: 3000, // Mesaj biraz daha uzun olduğu için süreyi azıcık artırdım
            gravity: "top",
            position: "center",
            style: {
              background: "#000",
              borderRadius: "6px",
            },
          }).showToast();

          // Span ve Input değerlerini güncelle
          span.text(formatMoney(sodexo) + " ₺");
          input.attr("data-current-val", sodexo);

          // Elden hücresini güncelle
          const eldenTd = input.closest("tr").find(".td-elden");
          eldenTd.text(formatMoney(elden) + " ₺");

          // Özet kartlarını güncelle (Sodexo artarsa Elden aynı oranda azalır)
          const totalSodexoElem = $("#total-sodexo");
          const totalEldenElem = $("#total-elden");

          if (totalSodexoElem.length && totalEldenElem.length) {
            let currentTotalSodexo =
              parseFloat(
                totalSodexoElem.text().replace(/\./g, "").replace(",", "."),
              ) || 0;
            let currentTotalElden =
              parseFloat(
                totalEldenElem.text().replace(/\./g, "").replace(",", "."),
              ) || 0;

            const newTotalSodexo = currentTotalSodexo + diff;
            const newTotalElden = currentTotalElden - diff; // Sodexo artışı eldeni azaltır

            totalSodexoElem.text(formatMoney(newTotalSodexo));
            totalEldenElem.text(formatMoney(Math.max(0, newTotalElden)));
          }

          // Inputu gizle, span'ı göster
          input.addClass("d-none");
          span.removeClass("d-none");
          parent.find(".btn-edit-sodexo-inline").removeClass("d-none");
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

  // Enter tuşuna basıldığında inputu kapat
  $(document).on("keypress", ".update-sodexo", function (e) {
    if (e.which == 13) {
      $(this).blur();
    }
  });

  // Personel Gelir Ekle Butonu
  $(document).on("click", ".btn-gelir-ekle, .btn-detail-ekodeme", function () {
    console.log("Gelir Ekle/Detay tıklandı");
    const id = $(this).data("id");
    const ad = $(this).data("ad");
    const donemId = $("#donemSelect").val();

    console.log("Personel ID:", id, "Ad:", ad, "Dönem:", donemId);

    $("#gelir_personel_id").val(id);
    $("#gelir_personel_ad").text(ad);
    $("#gelir_edit_id").val(0); // Reset edit ID

    const form = $("#formPersonelGelirEkle");
    if (form.length > 0) {
      form.trigger("reset");
      // Bugünün tarihini varsayılan yap
      const today = new Date().toISOString().split("T")[0];
      form.find("input[name='tarih']").val(today);
      form
        .find("button[type='submit']")
        .html('<i class="bx bx-save me-1"></i>Kaydet');
    } else {
      console.error("Form #formPersonelGelirEkle bulunamadı!");
    }

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

    showModal("modalPersonelGelirEkle");
  });

  // Personel Kesinti Ekle Butonu
  $(document).on(
    "click",
    ".btn-kesinti-ekle, .btn-detail-kesinti",
    function () {
      console.log("Kesinti Ekle/Detay tıklandı");
      const id = $(this).data("id");
      const ad = $(this).data("ad");
      const donemId = $("#donemSelect").val();
      const maas = $(this).data("maas");
      const maasDurumu = $(this).data("maas-durumu");

      console.log(
        "Personel ID:",
        id,
        "Ad:",
        ad,
        "Dönem:",
        donemId,
        "Maaş:",
        maas,
        "Durum:",
        maasDurumu,
      );

      $("#kesinti_personel_id").val(id);
      $("#formPersonelKesintiEkle").attr("data-maas", maas);
      $("#formPersonelKesintiEkle").attr("data-maas-durumu", maasDurumu);
      $("#kesinti_personel_ad").text(ad);
      $("#kesinti_edit_id").val(0); // Reset edit ID

      const form = $("#formPersonelKesintiEkle");
      if (form.length > 0) {
        form.trigger("reset");
        // Bugünün tarihini varsayılan yap
        const today = new Date().toISOString().split("T")[0];
        form.find("input[name='tarih']").val(today);
        form
          .find("button[type='submit']")
          .html('<i class="bx bx-save me-1"></i>Kaydet');

        // UI Reset
        $("#div_ucretsiz_izin_secenek").addClass("d-none");
        $("#div_kesinti_gun").addClass("d-none");
        $("#div_kesinti_tutar").removeClass("d-none");
        $("#kesinti_tip_tutar").prop("checked", true);
      } else {
        console.error("Form #formPersonelKesintiEkle bulunamadı!");
      }

      // Kart vurgusunu kaldır
      $(".card.border-danger").removeClass(
        "border-danger bg-danger bg-opacity-10",
      );

      // Listeyi getir
      loadKesintiListesi(id, donemId);

      // Accordion'ı kapalı getir
      $("#collapseKesinti").removeClass("show");

      showModal("modalPersonelKesintiEkle");
    },
  );

  // Gelir Düzenle Butonu
  $(document).on("click", ".btn-edit-gelir", function () {
    const id = $(this).data("id");
    const tur = $(this).data("tur");
    const tutar = $(this).data("tutar");
    const aciklama = $(this).data("aciklama");
    const tarih = $(this).data("tarih");

    // Önceki aktif karttan class'ı kaldır
    $(".card.border-primary").removeClass(
      "border-primary bg-primary bg-opacity-10",
    );

    // Tıklanan butona ait kartı bul ve aktif class'ı ekle
    $(this)
      .closest(".card")
      .addClass("border-primary bg-primary bg-opacity-10");

    $("#formPersonelGelirEkle input[name='id']").val(id);
    $("#formPersonelGelirEkle select[name='ek_odeme_tur']")
      .val(tur)
      .trigger("change");
    $("#formPersonelGelirEkle input[name='tutar']").val(tutar);
    $("#formPersonelGelirEkle input[name='aciklama']").val(aciklama);
    $("#formPersonelGelirEkle input[name='tarih']").val(tarih || "");

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
    const tarih = $(this).data("tarih");

    // Önceki aktif karttan class'ı kaldır
    $(".card.border-danger").removeClass(
      "border-danger bg-danger bg-opacity-10",
    );

    // Tıklanan butona ait kartı bul ve aktif class'ı ekle
    $(this).closest(".card").addClass("border-danger bg-danger bg-opacity-10");

    $("#formPersonelKesintiEkle input[name='id']").val(id);
    $("#formPersonelKesintiEkle select[name='kesinti_tur']")
      .val(tur)
      .trigger("change");
    $("#formPersonelKesintiEkle input[name='tutar']").val(tutar);
    $("#formPersonelKesintiEkle input[name='aciklama']").val(aciklama);
    $("#formPersonelKesintiEkle input[name='tarih']").val(tarih || "");

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
          hideModal("modalPersonelGelirEkle");
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
          hideModal("modalPersonelKesintiEkle");
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

  // Kesinti Türü Değişince
  $(document).on("change", "select[name='kesinti_tur']", function () {
    const tur = $(this).val();
    const form = $("#formPersonelKesintiEkle");
    const maasDurumu = form.attr("data-maas-durumu");

    if (tur === "izin_kesinti") {
      $("#div_ucretsiz_izin_secenek").removeClass("d-none");

      // Eğer prim usulü ise gün seçeneğini gizle
      if (maasDurumu === "Prim Usulü") {
        $("#kesinti_tip_gun").prop("disabled", true);
        $("label[for='kesinti_tip_gun']").addClass("d-none");
        $("#kesinti_tip_tutar").prop("checked", true).trigger("change");
      } else {
        $("#kesinti_tip_gun").prop("disabled", false);
        $("label[for='kesinti_tip_gun']").removeClass("d-none");
      }
    } else {
      $("#div_ucretsiz_izin_secenek").addClass("d-none");
      $("#div_kesinti_gun").addClass("d-none");
      $("#div_kesinti_tutar").removeClass("d-none");
    }
  });

  // Kesinti Tipi (Tutar/Gün) Değişince
  $(document).on("change", "input[name='rad_kesinti_tip']", function () {
    const tip = $(this).val();
    if (tip === "gun") {
      $("#div_kesinti_gun").removeClass("d-none");
      $("#div_kesinti_tutar").addClass("d-none");
      $("#kesinti_tutar").prop("required", false);
      $("#kesinti_gun_sayisi").prop("required", true).focus();
    } else {
      $("#div_kesinti_gun").addClass("d-none");
      $("#div_kesinti_tutar").removeClass("d-none");
      $("#kesinti_tutar").prop("required", true);
      $("#kesinti_gun_sayisi").prop("required", false);
    }
  });

  // Gün Sayısı Değişince Tutar Hesapla
  $(document).on("input", "#kesinti_gun_sayisi", function () {
    const gun = parseFloat($(this).val()) || 0;
    const form = $("#formPersonelKesintiEkle");
    const maas = parseFloat(form.attr("data-maas")) || 0;

    if (gun > 0 && maas > 0) {
      const gunluk = maas / 30;
      const toplam = (gunluk * gun).toFixed(2);
      $("#kesinti_tutar").val(toplam);
    } else {
      $("#kesinti_tutar").val(0);
    }
  });
});

function getSelectedIds() {
  const ids = [];
  const table = $("#bordroTable").DataTable();
  table.$(".personel-check:checked").each(function () {
    ids.push($(this).val());
  });
  return ids;
}

function updateButtonStates() {
  const table = $("#bordroTable").DataTable();
  const selectedCount = table.$(".personel-check:checked").length;
  // Maaş hesapla butonu her zaman aktif kalsın (seçim yoksa hepsini hesaplar)
  // Sadece export butonu seçim varsa aktif olsun (o da hepsi için çalışabilir ama mevcut yapıyı koruyalım)
  $("#btnExportExcel").prop("disabled", selectedCount === 0);
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
        hideModal(modalId.replace("#", ""));
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
        .split("(")[0]
        .replace(/[^\d,]/g, "")
        .replace(",", "."),
    ) || 0;
  const icra =
    parseFloat(
      $(".btn-odeme[data-id='" + $("#odeme_bordro_id").val() + "']").data(
        "icra",
      ),
    ) || 0;
  const banka = parseFloat($("#banka_odemesi").val()) || 0;
  const sodexo = parseFloat($("#sodexo_odemesi").val()) || 0;
  const diger = parseFloat($("#diger_odeme").val()) || 0;
  const toplam_alacak =
    parseFloat($("#formOdemeDagit").data("toplam_alacak")) || 0;

  // Sodexo limit kontrolü (%20)
  const maxSodexo = toplam_alacak * 0.20;
  if (sodexo > maxSodexo + 0.01) {
    $("#sodexo_odemesi").addClass("is-invalid");
    if (!$("#sodexo_limit_warning").length) {
      $("#sodexo_odemesi").after(
        `<div id="sodexo_limit_warning" class="text-danger small mt-1">Sodexo limiti: ${formatMoney(
          maxSodexo
        )} ₺ (%20)</div>`
      );
    } else {
      $("#sodexo_limit_warning").text(
        `Sodexo limiti: ${formatMoney(maxSodexo)} ₺ (%20)`
      );
    }
  } else {
    $("#sodexo_odemesi").removeClass("is-invalid");
    $("#sodexo_limit_warning").remove();
  }

  const elden = net - banka - sodexo - diger - icra;
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
        console.log(response.data);

        if (response.data.length === 0) {
          html =
            '<div class="text-center text-muted py-3"><i class="bx bx-info-circle fs-1 mb-2"></i><br>Kayıtlı gelir bulunamadı.</div>';
        } else {
          html = `
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Tarih</th>
                    <th>Tür</th>
                    <th>Açıklama</th>
                    <th class="text-end">Tutar</th>
                    <th class="text-center">İşlem</th>
                  </tr>
                </thead>
                <tbody>`;
          response.data.forEach((item) => {
            const fullDate = item.tarih
              ? item.tarih
              : item.created_at
                ? item.created_at
                : "";
            const dateStr = fullDate.split(" ")[0];
            const formattedDate = dateStr
              ? new Date(dateStr).toLocaleDateString("tr-TR")
              : "-";
            html += `
                  <tr>
                    <td>${formattedDate}</td>
                    <td><span class="badge bg-success bg-opacity-10 text-success">${item.etiket}</span></td>
                    <td class="small">${item.aciklama || "-"}</td>
                    <td class="text-end fw-bold text-success">+${formatMoney(item.tutar)} ₺</td>
                    <td class="text-center">
                      <div class="d-flex justify-content-center gap-1">
                        <button type="button" class="btn btn-sm btn-soft-success btn-edit-gelir" 
                            data-id="${item.id}" 
                            data-tur="${item.tur}" 
                            data-tutar="${item.tutar}" 
                            data-tarih="${item.tarih || ""}" 
                            data-aciklama="${item.aciklama || ""}">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-gelir" data-id="${item.id}">
                            <i class="bx bx-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>`;
          });
          html += `</tbody></table></div>`;
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
          const kesintiMap = {
            İZİN_KESİNTİ: "Ücretsiz İzin",
            DİĞER_KESİNTİ: "Diğer Kesinti",
            ÖZEL_KESİNTİ: "Özel Kesinti",
            AVANS: "Avans",
            İCRA: "İcra",
            NAFAKA: "Nafaka",
            izin_kesinti: "Ücretsiz İzin",
            diger: "Diğer Kesinti",
            ozel_kesinti: "Özel Kesinti",
            icra: "İcra",
            avans: "Avans",
            nafaka: "Nafaka",
          };

          html = `
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Tarih</th>
                    <th>Tür</th>
                    <th>Açıklama</th>
                    <th class="text-end">Tutar</th>
                    <th class="text-center">İşlem</th>
                  </tr>
                </thead>
                <tbody>`;

          response.data.forEach((item) => {
            const turLabel =
              kesintiMap[item.tur] || item.tur.replace(/_/g, " ");
            const fullDate = item.tarih
              ? item.tarih
              : item.olusturma_tarihi
                ? item.olusturma_tarihi
                : "";
            const dateStr = fullDate.split(" ")[0];
            const formattedDate = dateStr
              ? new Date(dateStr).toLocaleDateString("tr-TR")
              : "-";

            html += `
                  <tr>
                    <td>${formattedDate}</td>
                    <td><span class="badge bg-danger bg-opacity-10 text-danger">${turLabel}</span></td>
                    <td class="small">${item.aciklama || "-"}</td>
                    <td class="text-end fw-bold text-danger">-${formatMoney(item.tutar)} ₺</td>
                    <td class="text-center">
                      <div class="d-flex justify-content-center gap-1">
                        <button type="button" class="btn btn-sm btn-soft-primary btn-edit-kesinti" 
                            data-id="${item.id}" 
                            data-tur="${item.tur}" 
                            data-tutar="${item.tutar}" 
                            data-tarih="${item.tarih || ""}" 
                            data-aciklama="${item.aciklama || ""}">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-kesinti" data-id="${item.id}">
                            <i class="bx bx-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>`;
          });
          html += `</tbody></table></div>`;
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

/**
 * Bootstrap Modal'ı güvenli bir şekilde açar
 * @param {string} modalId - Modal elementinin ID'si (başında # olmadan)
 */
function showModal(modalId) {
  console.log("showModal çağrıldı:", modalId);
  const el = document.getElementById(modalId);
  if (!el) {
    console.error("Modal element bulunamadı:", modalId);
    return;
  }

  try {
    if (window.bootstrap && window.bootstrap.Modal) {
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      modal.show();
      console.log("Bootstrap 5 Modal açıldı");
    } else if ($.fn.modal) {
      $(el).modal("show");
      console.log("jQuery Bootstrap Modal açıldı");
    } else {
      console.error("Bootstrap Modal kütüphanesi bulunamadı!");
      // Fallback: Manuel olarak class ekle (yetersiz ama denenebilir)
      $(el).addClass("show").css("display", "block");
      $("body")
        .addClass("modal-open")
        .append('<div class="modal-backdrop fade show"></div>');
    }
  } catch (e) {
    console.error("Modal açılırken hata oluştu:", e);
  }
}

/**
 * Bootstrap Modal'ı güvenli bir şekilde kapatır
 * @param {string} modalId - Modal elementinin ID'si (başında # olmadan)
 */
function hideModal(modalId) {
  console.log("hideModal çağrıldı:", modalId);
  const el = document.getElementById(modalId);
  if (!el) return;

  try {
    if (window.bootstrap && window.bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(el);
      if (modal) modal.hide();
    } else if ($.fn.modal) {
      $(el).modal("hide");
    } else {
      $(el).removeClass("show").css("display", "none");
      $(".modal-backdrop").remove();
      $("body").removeClass("modal-open");
    }
  } catch (e) {
    console.error("Modal kapatılırken hata oluştu:", e);
  }
}


