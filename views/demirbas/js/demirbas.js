let zimmetUrl = "views/demirbas/api.php";
let zimmetTable;

// ============== SAYFA YÜKLENDİĞİNDE ==============
$(document).ready(function () {
  // DataTable başlat
  let demirbasOptions = getDatatableOptions();
  demirbasOptions.columnDefs = [{ orderable: false, targets: -1 }];
  demirbasOptions.order = [[0, "asc"]];
  // Özelleştirilmiş boş tablo mesajı
  demirbasOptions.language.emptyTable =
    '<div class="text-center text-muted py-4"><i class="bx bx-package display-4 d-block mb-2"></i>Henüz demirbaş eklenmemiş.<br><small>"Yeni Demirbaş" butonuna tıklayarak ekleyebilirsiniz.</small></div>';

  table = $("#demirbasTable").DataTable(demirbasOptions);

  // Zimmet tablosu DataTable
  zimmetTable = $("#zimmetTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: zimmetUrl,
      type: "POST",
      data: function (d) {
        d.action = "zimmet-listesi";
      },
    },
    columns: [
      { data: "id", className: "text-center" },
      { data: "kategori_adi" },
      { data: "demirbas_adi" },
      { data: "marka_model" },
      { data: "personel_adi" },
      { data: "teslim_miktar", className: "text-center" },
      { data: "teslim_tarihi" },
      { data: "durum", className: "text-center" },
      { data: "islemler", className: "text-center", orderable: false },
    ],
    order: [[0, "desc"]],
    createdRow: function (row, data, dataIndex) {
      $(row).attr("data-id", data.enc_id);
    },
    language: {
      ...getDatatableOptions().language,
      emptyTable:
        '<div class="text-center text-muted py-4"><i class="bx bx-transfer display-4 d-block mb-2"></i>Henüz zimmet kaydı bulunmamaktadır.</div>',
    },
  });

  // Select2 başlat
  initSelect2();

  // Feather icons
  if (typeof feather !== "undefined") {
    feather.replace();
  }

  // Başlangıçta buton görünürlüğünü ayarla
  updateButtonVisibility();

  // Eğer sayfa yüklendiğinde zimmet tabı aktifse listeyi yükle
  if ($("#zimmet-tab").hasClass("active")) {
    loadZimmetList();
  }
});

function updateButtonVisibility() {
  let activeTabBtn = $("#demirbasTab button.active");
  if (activeTabBtn.length === 0) return;

  let activeTab = activeTabBtn.attr("id");
  if (activeTab === "demirbas-tab") {
    $("#btnYeniDemirbas").show();
    $("#btnZimmetVer").hide();
    $("#importExcelLi").show();
  } else if (activeTab === "zimmet-tab") {
    $("#btnYeniDemirbas").hide();
    $("#btnZimmetVer").show();
    $("#importExcelLi").hide();
  } else {
    // zimmet-ayarlari-tab
    $("#btnYeniDemirbas").hide();
    $("#btnZimmetVer").hide();
    $("#importExcelLi").hide();
  }
}

// ============== SELECT2 BAŞLAT ==============
function initSelect2() {
  // Zimmet Modalı Select2'leri
  if ($("#demirbas_id_zimmet").length) {
    $("#demirbas_id_zimmet").select2({
      dropdownParent: $("#zimmetModal"),
      placeholder: "Demirbaş arayın...",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#personel_id").length) {
    $("#personel_id").select2({
      dropdownParent: $("#zimmetModal"),
      placeholder: "Personel arayın...",
      allowClear: true,
      width: "100%",
    });
  }

  // Genel Demirbaş Modalı Select2'leri
  if ($("#kategori_id").length) {
    $("#kategori_id").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Kategori seçin...",
      width: "100%",
    });
  }

  if ($("#durum").length) {
    $("#durum").select2({
      dropdownParent: $("#demirbasModal"),
      minimumResultsForSearch: Infinity,
      width: "100%",
    });
  }

  // Otomatik Zimmet Ayarları Select2'leri
  if ($("#otomatik_zimmet_is_emri").length) {
    $("#otomatik_zimmet_is_emri").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#otomatik_iade_is_emri").length) {
    $("#otomatik_iade_is_emri").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }
}

// ============== İŞ EMRİ SONUÇLARINI GETİR ==============
function fetchIsEmriSonuclari(callback) {
  fetch(zimmetUrl + "?action=is-emri-sonuclari")
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        const options = data.data;
        const selects = ["#otomatik_zimmet_is_emri", "#otomatik_iade_is_emri"];

        selects.forEach((selector) => {
          const $select = $(selector);
          if ($select.length) {
            // Mevcut seçili değeri sakla
            const currentVal = $select.val();

            // Seçenekleri temizle ve yeniden doldur
            $select.empty();
            options.forEach((opt) => {
              const selected = opt.id === currentVal ? "selected" : "";
              $select.append(
                new Option(opt.text, opt.id, false, opt.id === currentVal),
              );
            });

            // Tekrar tetikle
            if (currentVal) {
              $select.val(currentVal).trigger("change.select2");
            }
          }
        });

        if (typeof callback === "function") callback();
      }
    })
    .catch((err) => console.error("İş emri sonuçları yüklenemedi:", err));
}

// ============== TAB DEĞİŞİKLİĞİNDE ==============
// Sadece ana sayfadaki bu iki butona tıklandığında URL parametresini değiştir
$(document).on("click", "#demirbas-tab, #zimmet-tab", function () {
  const tabName = this.id === "zimmet-tab" ? "zimmet" : "demirbas";

  // URL'i güncelle
  const url = new URL(window.location);
  url.searchParams.set("tab", tabName);
  window.history.replaceState({}, "", url);

  // Buton görünürlüğünü güncelle
  updateButtonVisibility();

  if (this.id === "zimmet-tab") {
    loadZimmetList();
  }
});

// ============== ZİMMET LİSTESİ YÜKLE ==============
function loadZimmetList() {
  zimmetTable.ajax.reload(null, false);
}

// ============== DEMİRBAŞ İŞLEMLERİ ==============

// Demirbaş Kaydet
$(document).on("click", "#demirbasKaydet", function () {
  var form = $("#demirbasForm");
  var demirbas_id = $("#demirbas_id").val();

  form.validate({
    rules: {
      demirbas_adi: { required: true },
      kategori_id: { required: true },
      miktar: { required: true, min: 1 },
    },
    messages: {
      demirbas_adi: { required: "Demirbaş adı zorunludur" },
      kategori_id: { required: "Kategori seçimi zorunludur" },
      miktar: {
        required: "Miktar zorunludur",
        min: "Miktar en az 1 olmalıdır",
      },
    },
  });

  if (!form.valid()) return;

  var formData = new FormData(form[0]);
  formData.append("action", "demirbas-kaydet");

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        if (demirbas_id == 0) {
          table.row.add($(data.son_kayit)).draw(false);
        } else {
          let rowNode = table.$(`tr[data-id="${demirbas_id}"]`)[0];
          if (rowNode) {
            table.row(rowNode).remove().draw();
            table.row.add($(data.son_kayit)).draw(false);
          }
        }
        $("#demirbasModal").modal("hide");
        resetDemirbasForm();

        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.reload();
          }
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam",
        });
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire({
        icon: "error",
        title: "Hata!",
        text: "İşlem sırasında bir hata oluştu.",
        confirmButtonText: "Tamam",
      });
    });
});

// Demirbaş Düzenle
$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  $("#demirbas_id").val(id);

  // Modal içindeki tab'ı ilk sekmeye sıfırla
  $("#demirbasModalTabs a:first").tab("show");

  var formData = new FormData();
  formData.append("action", "demirbas-getir");
  formData.append("demirbas_id", id);

  // Seçenekleri önce yükle, sonra verileri bas
  fetchIsEmriSonuclari(() => {
    fetch(zimmetUrl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          var d = data.data;
          for (var key in d) {
            if ($("#" + key).length) {
              if (key === "edinme_tutari" && d[key]) {
                // Para formatı
                $("#" + key).val(
                  parseFloat(d[key]).toLocaleString("tr-TR", {
                    minimumFractionDigits: 2,
                  }),
                );
              } else if (
                key === "kategori_id" ||
                key === "durum" ||
                key === "otomatik_zimmet_is_emri" ||
                key === "otomatik_iade_is_emri"
              ) {
                // Select2 alanları için
                $("#" + key)
                  .val(d[key])
                  .trigger("change");
              } else {
                $("#" + key).val(d[key]);
              }
            }
          }
          $("#demirbasModal").modal("show");
        }
      });
  });
});

// Demirbaş Sil
$(document).on("click", ".demirbas-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let name = $(this).data("name");
  let row = $(this).closest("tr");

  Swal.fire({
    title: "Emin misiniz?",
    html: `<strong>${name}</strong> adlı demirbaşı silmek istediğinizden emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(zimmetUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=demirbas-sil&id=${id}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            table.row(row).remove().draw();
            Swal.fire("Silindi!", data.message, "success");
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        });
    }
  });
});

// Form Reset
function resetDemirbasForm() {
  $("#demirbasForm")[0].reset();
  $("#demirbas_id").val(0);
  $("#kategori_id").val("").trigger("change");
  $("#durum").val("aktif").trigger("change");
  $("#miktar").val(1);
  // Otomatik zimmet ayarları
  $("#otomatik_zimmet_is_emri").val("").trigger("change");
  $("#otomatik_iade_is_emri").val("").trigger("change");
  // Modal içindeki tab'ı ilk sekmeye sıfırla
  $("#demirbasModalTabs a:first").tab("show");
}

// Modal kapatıldığında formu sıfırla
$("#demirbasModal").on("hidden.bs.modal", function () {
  resetDemirbasForm();
});

// Modallar açıldığında Feather ikonlarını yenile
$(".modal").on("shown.bs.modal", function () {
  if (typeof feather !== "undefined") {
    feather.replace();
  }
  initSelect2();

  // Eğer demirbasModal ise seçenekleri bir kez daha tazele (opsiyonel ama garanti)
  if ($(this).attr("id") === "demirbasModal") {
    fetchIsEmriSonuclari();
  }
});

// ============== ZİMMET İŞLEMLERİ ==============

// Demirbaş listesinden zimmet ver
$(document).on("click", ".zimmet-ver", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let name = $(this).data("name");
  let kalan = $(this).data("kalan");

  // Demirbaş seçimini yap
  // Not: Select2'de değer seçmek için ID'yi decrypt etmemiz gerekiyor
  // Şimdilik modal'ı aç, kullanıcı manuel seçim yapsın

  $("#zimmetModal").modal("show");
  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan);
});

// Demirbaş seçildiğinde kalan miktarı göster
$(document).on("change", "#demirbas_id_zimmet", function () {
  let kalan = $(this).find(":selected").data("kalan") || 0;
  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan).val(1);
});

// Zimmet Kaydet
$(document).on("click", "#zimmetKaydet", function () {
  var form = $("#zimmetForm");

  form.validate({
    rules: {
      demirbas_id: { required: true },
      personel_id: { required: true },
      teslim_miktar: { required: true, min: 1 },
      teslim_tarihi: { required: true },
    },
    messages: {
      demirbas_id: { required: "Demirbaş seçimi zorunludur" },
      personel_id: { required: "Personel seçimi zorunludur" },
      teslim_miktar: { required: "Miktar zorunludur" },
      teslim_tarihi: { required: "Teslim tarihi zorunludur" },
    },
  });

  if (!form.valid()) return;

  // Miktar kontrolü
  let kalan = parseInt(
    $("#demirbas_id_zimmet").find(":selected").data("kalan") || 0,
  );
  let teslimMiktar = parseInt($("#teslim_miktar").val());

  if (teslimMiktar > kalan) {
    Swal.fire({
      icon: "error",
      title: "Yetersiz Stok!",
      text: `Stokta sadece ${kalan} adet bulunmaktadır.`,
      confirmButtonText: "Tamam",
    });
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "zimmet-kaydet");

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#zimmetModal").modal("hide");
        resetZimmetForm();

        // Zimmet tablosunu yenile
        loadZimmetList();

        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam",
        });
      }
    });
});

// Zimmet İade Modal Aç
$(document).on("click", ".zimmet-iade", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let demirbas = $(this).data("demirbas");
  let personel = $(this).data("personel");
  let miktar = $(this).data("miktar");

  $("#iade_zimmet_id").val(id);
  $("#iade_demirbas_adi").text(demirbas);
  $("#iade_personel_adi").text(personel);
  $("#iade_teslim_miktar").text(miktar);
  $("#iade_miktar").val(miktar).attr("max", miktar);

  $("#iadeModal").modal("show");
});

// İade Kaydet
$(document).on("click", "#iadeKaydet", function () {
  var form = $("#iadeForm");

  let iadeMiktar = parseInt($("#iade_miktar").val());
  let teslimMiktar = parseInt($("#iade_teslim_miktar").text());

  if (iadeMiktar > teslimMiktar) {
    Swal.fire({
      icon: "error",
      title: "Hata!",
      text: `İade miktarı teslim edilen miktardan (${teslimMiktar}) fazla olamaz.`,
      confirmButtonText: "Tamam",
    });
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "zimmet-iade");
  formData.append("zimmet_id", $("#iade_zimmet_id").val());

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#iadeModal").modal("hide");

        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then(() => {
          location.reload();
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam",
        });
      }
    });
});

// Zimmet Sil
$(document).on("click", ".zimmet-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let row = $(this).closest("tr");

  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu zimmet kaydını silmek istediğinizden emin misiniz? Stok miktarı güncellenecektir.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(zimmetUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=zimmet-sil&id=${id}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            zimmetTable.row(row).remove().draw();
            Swal.fire("Silindi!", data.message, "success").then(() => {
              location.reload();
            });
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        });
    }
  });
});

// Form Reset
function resetZimmetForm() {
  $("#zimmetForm")[0].reset();
  $("#zimmet_id").val(0);
  $("#demirbas_id_zimmet").val("").trigger("change");
  $("#personel_id").val("").trigger("change");
  $("#kalanMiktarText").text("-");
  $(".flatpickr").flatpickr({
    dateFormat: "d.m.Y",
    locale: "tr",
    defaultDate: new Date(),
  });
}

// Modal kapatıldığında formu sıfırla
$("#zimmetModal").on("hidden.bs.modal", function () {
  resetZimmetForm();
});

// Zimmet Detay
$(document).on("click", ".zimmet-detay", function (e) {
  e.preventDefault();
  let id = $(this).data("id");

  // Loading göster
  Pace.start();

  var formData = new FormData();
  formData.append("action", "zimmet-detay");
  formData.append("id", id);

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        let d = data.data;
        let gecmis = data.gecmis;
        let hareketler = data.hareketler;

        // Üst bilgi kartını doldur
        $("#detay_demirbas_adi").text(d.demirbas_detay.demirbas_adi || "-");
        $("#detay_marka_model").text(
          (d.demirbas_detay.marka || "") + " " + (d.demirbas_detay.model || ""),
        );
        $("#detay_seri_no").text(d.demirbas_detay.seri_no || "-");
        $("#detay_durum_badge").html(d.durum_badge);
        $("#detay_personel").text(d.personel_detay.adi_soyadi || "-");

        // 1. HAREKET DETAYLARI TABLOSUNU DOLDUR
        let hBody = $("#zimmetHareketBody");
        hBody.empty();
        if (hareketler && hareketler.length > 0) {
          hareketler.forEach((h) => {
            let row = `
              <tr>
                <td>${h.hareket_badge}</td>
                <td class="text-center fw-bold">${h.miktar}</td>
                <td>${h.tarih_format}</td>
                <td class="small">${h.aciklama || ""}</td>
                <td>${h.kaynak_badge}</td>
              </tr>
            `;
            hBody.append(row);
          });
        } else {
          hBody.append(
            '<tr><td colspan="5" class="text-center text-muted">Hareket kaydı bulunamadı.</td></tr>',
          );
        }

        // 2. GEÇMİŞ TABLOSUNU DOLDUR
        let tbody = $("#zimmetGecmisBody");
        tbody.empty();
        if (gecmis && gecmis.length > 0) {
          gecmis.forEach((item) => {
            let row = `
              <tr>
                <td>
                  <div class="fw-medium">${item.personel_adi || "-"}</div>
                  <div class="small text-muted">${item.personel_telefon || ""}</div>
                </td>
                <td class="text-center">${item.teslim_miktar}</td>
                <td>${item.teslim_tarihi_format}</td>
                <td>${item.iade_tarihi_format}</td>
                <td class="text-center">${item.durum_badge}</td>
                <td class="small text-muted">${item.aciklama || "-"}</td>
              </tr>
            `;
            tbody.append(row);
          });
        } else {
          tbody.append(
            '<tr><td colspan="6" class="text-center text-muted py-3">Geçmiş kaydı bulunamadı.</td></tr>',
          );
        }

        $("#zimmetDetayModal").modal("show");
      } else {
        Swal.fire("Hata!", data.message, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.close();
      Swal.fire("Hata!", "Bir hata oluştu.", "error");
    });
});

// ============== EXCEL İŞLEMLERİ ==============

// Excel Import Modal Aç
$(document).on("click", "#importExcel", function () {
  $("#importExcelModal").modal("show");
});

// Excel Upload
$(document).on("click", "#btnUploadExcel", function () {
  var formData = new FormData($("#importExcelForm")[0]);
  formData.append("action", "excel-upload");

  if ($("#excelFile").val() == "") {
    Swal.fire("Uyarı", "Lütfen bir dosya seçiniz.", "warning");
    return;
  }

  Swal.fire({
    title: "Yükleniyor...",
    text: "Demirbaş listesi işleniyor, lütfen bekleyin.",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.ajax({
    url: zimmetUrl,
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (response) {
      try {
        let res = JSON.parse(response);
        if (res.status === "success") {
          Swal.fire({
            title: "Başarılı",
            text: res.message,
            icon: "success",
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire("Hata", res.message, "error");
        }
      } catch (e) {
        Swal.fire("Hata", "Sunucudan geçersiz yanıt alındı.", "error");
      }
    },
    error: function () {
      Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
    },
  });
});

// Excel Export
$(document).on("click", "#exportExcel", function () {
  let activeTab = $("#demirbasTab button.active").attr("id");
  let tabName = activeTab === "zimmet-tab" ? "zimmet" : "demirbas";
  let currentTable = tabName === "zimmet" ? zimmetTable : table;

  let searchTerm = currentTable.search();
  let url = "views/demirbas/export-excel.php";
  let params = new URLSearchParams();

  params.append("tab", tabName);
  if (searchTerm) {
    params.append("search", searchTerm);
  }

  // Sütun bazlı aramaları ekle (eğer varsa)
  let colSearches = {};
  $(
    `#${tabName === "zimmet" ? "zimmetTable" : "demirbasTable"} .search-input-row input`,
  ).each(function () {
    let val = $(this).val();
    let colIdx = $(this).attr("data-col-idx");
    if (val && colIdx) {
      colSearches[colIdx] = val;
    }
  });

  if (Object.keys(colSearches).length > 0) {
    params.append("col_search", JSON.stringify(colSearches));
  }

  window.location.href = url + "?" + params.toString();
});
