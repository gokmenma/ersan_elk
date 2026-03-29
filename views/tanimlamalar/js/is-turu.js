let url = "views/tanimlamalar/api.php";
let canManageUcretGecmisi = false;
let shouldReloadAfterUcretGecmisClose = false;

function getTodayTr() {
  const d = new Date();
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const year = d.getFullYear();
  return `${day}.${month}.${year}`;
}

function ymdToDmy(value) {
  if (!value || value === "0000-00-00") {
    return "";
  }
  const parts = value.split("-");
  if (parts.length !== 3) {
    return value;
  }
  return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function dmyToYmd(value) {
  if (!value) {
    return "";
  }
  const clean = String(value).trim();
  const parts = clean.split(".");
  if (parts.length !== 3) {
    return clean;
  }
  const day = parts[0].padStart(2, "0");
  const month = parts[1].padStart(2, "0");
  const year = parts[2];
  return `${year}-${month}-${day}`;
}

function setFlatpickrValue(selector, value) {
  const el = $(selector)[0];
  if (!el) {
    return;
  }

  if (el._flatpickr) {
    if (value) {
      el._flatpickr.setDate(value, true, "d.m.Y");
    } else {
      el._flatpickr.clear();
    }
  } else {
    $(selector).val(value || "");
  }
}

function toInputDate(value) {
  return ymdToDmy(value);
}

function formatDateToTr(value) {
  if (!value || value === "0000-00-00") {
    return "-";
  }

  const parts = value.split("-");
  if (parts.length !== 3) {
    return value;
  }

  return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function resetUcretGecmisiForm() {
  $("#gecmis_id").val("0");
  setFlatpickrValue("#gecmis_baslangic", getTodayTr());
  setFlatpickrValue("#gecmis_bitis", "");
  $("#gecmis_ucret").val("");
  $("#gecmis_aracli_ucret").val("");
}

function initUcretFlatpickr() {
  if (typeof flatpickr === "undefined") {
    return;
  }

  const targets = [
    "#ucret_gecerlilik_baslangic",
    "#gecmis_baslangic",
    "#gecmis_bitis",
  ];

  targets.forEach((selector) => {
    const el = document.querySelector(selector);
    if (!el || el._flatpickr) {
      return;
    }

    flatpickr(el, {
      dateFormat: "d.m.Y",
      allowInput: true,
      disableMobile: true,
      clickOpens: true,
    });
  });
}

function loadUcretGecmisi(isTuruId) {
  const formData = new FormData();
  formData.append("action", "is-turu-ucret-gecmisi-getir");
  formData.append("id", isTuruId);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((res) => {
      const tbody = $("#ucretGecmisTable tbody");
      tbody.empty();

      if (res.status !== "success") {
        swal.fire("Hata", res.message || "Ücret geçmişi alınamadı.", "error");
        return;
      }

      if (!res.data || res.data.length === 0) {
        const colspan = canManageUcretGecmisi ? 5 : 4;
        tbody.append(
          `<tr><td colspan="${colspan}" class="text-center text-muted">Kayıt bulunamadı</td></tr>`,
        );
        return;
      }

      res.data.forEach((item) => {
        const islemBtn = canManageUcretGecmisi
          ? `<td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1 gecmis-duzenle"
                    data-id="${item.encrypted_id}"
                    data-baslangic="${item.gecerlilik_baslangic || ""}"
                    data-bitis="${item.gecerlilik_bitis || ""}"
                    data-ucret="${item.ucret || 0}"
                    data-aracli-ucret="${item.aracli_ucret || 0}">
                  <i data-feather="edit-2" style="width:14px;height:14px;"></i> Düzenle
                </button>
             </td>`
          : "";

        tbody.append(`
          <tr>
            <td>${formatDateToTr(item.gecerlilik_baslangic)}</td>
            <td>${item.gecerlilik_bitis ? formatDateToTr(item.gecerlilik_bitis) : "Aktif"}</td>
            <td>${item.ucret || 0}</td>
            <td>${item.aracli_ucret || 0}</td>
            ${islemBtn}
          </tr>
        `);
      });

      if (typeof feather !== "undefined" && typeof feather.replace === "function") {
        feather.replace();
      }
    })
    .catch((error) => {
      swal.fire("Hata", "Ücret geçmişi yüklenirken hata: " + error.message, "error");
    });
}

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#is_turu").val("").trigger("change");
  $("#is_turu_id").val(0);
  $("#is_emri_sonucu").val("");
  $("#is_turu_ucret").val("");
  $("#aracli_personel_is_turu_ucret").val("");
  setFlatpickrValue("#ucret_gecerlilik_baslangic", getTodayTr());
  $("#rapor_sekmesi").val("").trigger("change");
  $("#actionModalLabel").text("İş Türü Ekle");
});

$(document).ready(function () {
  canManageUcretGecmisi = $("#ucretGecmisYetki").val() === "1";

  $("#is_turu").select2({
    dropdownParent: $("#actionModal"),
    tags: true,
    width: "100%",
  });

  $("#rapor_sekmesi").select2({
    dropdownParent: $("#actionModal"),
    width: "100%",
    placeholder: "Rapor Sekmesi Seçiniz",
  });

  initUcretFlatpickr();
  setFlatpickrValue("#ucret_gecerlilik_baslangic", getTodayTr());

  if (canManageUcretGecmisi) {
    resetUcretGecmisiForm();
  }

  if (typeof feather !== "undefined" && typeof feather.replace === "function") {
    feather.replace();
  }
});

$(document).on("click", ".ucret-gecmisi", function (e) {
  e.preventDefault();
  const id = $(this).data("id");
  const name = $(this).data("name") || "";

  $("#gecmis_is_turu_id").val(id);
  $("#ucretGecmisModalLabel").text(`${name} - Ücret Geçmişi`);

  if (canManageUcretGecmisi) {
    resetUcretGecmisiForm();
  }

  loadUcretGecmisi(id);
  $("#ucretGecmisModal").modal("show");
});

$(document).on("click", "#ucretGecmisTemizle", function () {
  resetUcretGecmisiForm();
});

$(document).on("click", ".gecmis-duzenle", function () {
  $("#gecmis_id").val($(this).data("id"));
  setFlatpickrValue("#gecmis_baslangic", toInputDate($(this).data("baslangic")));
  setFlatpickrValue("#gecmis_bitis", toInputDate($(this).data("bitis")));
  $("#gecmis_ucret").val($(this).data("ucret"));
  $("#gecmis_aracli_ucret").val($(this).data("aracli-ucret"));
});

$(document).on("click", "#ucretGecmisKaydet", function () {
  const isTuruId = $("#gecmis_is_turu_id").val();
  const baslangic = $("#gecmis_baslangic").val();

  if (!isTuruId) {
    swal.fire("Hata", "İş türü bilgisi bulunamadı.", "error");
    return;
  }

  if (!baslangic) {
    swal.fire("Hata", "Geçerlilik başlangıç tarihi zorunludur.", "error");
    return;
  }

  const formData = new FormData();
  formData.append("action", "is-turu-ucret-gecmisi-kaydet");
  formData.append("is_turu_id", isTuruId);
  formData.append("gecmis_id", $("#gecmis_id").val() || "0");
  formData.append("gecerlilik_baslangic", dmyToYmd(baslangic));
  formData.append("gecerlilik_bitis", dmyToYmd($("#gecmis_bitis").val()));
  formData.append("ucret", $("#gecmis_ucret").val() || "0");
  formData.append("aracli_ucret", $("#gecmis_aracli_ucret").val() || "0");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((res) => {
      const title = res.status === "success" ? "Başarılı" : "Hata";
      swal.fire(title, res.message, res.status).then(() => {
        if (res.status === "success") {
          shouldReloadAfterUcretGecmisClose = true;
          $("#ucretGecmisModal").modal("hide");
        }
      });
    })
    .catch((error) => {
      swal.fire("Hata", "Kayıt sırasında hata: " + error.message, "error");
    });
});

function resetUcretGecmisiFormSafely() {
  if (canManageUcretGecmisi) {
    resetUcretGecmisiForm();
  }
}

$(document).on("hidden.bs.modal", "#ucretGecmisModal", function () {
  if (shouldReloadAfterUcretGecmisClose) {
    shouldReloadAfterUcretGecmisClose = false;
    location.reload();
  }

  resetUcretGecmisiFormSafely();
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      is_turu: {
        required: true,
      },
    },
    messages: {
      is_turu: {
        required: "İş Türü boş bırakılamaz",
      },
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
  });

  if (!form.valid()) {
    return;
  }

  var formData = new FormData(form[0]);
  const anaBaslangic = $("#ucret_gecerlilik_baslangic").val();
  formData.set("ucret_gecerlilik_baslangic", dmyToYmd(anaBaslangic));
  formData.append("action", "is-turu-kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";

      if (data.status == "success") {
        var table = $("#actionTable").DataTable();
        // If update, remove old row first
        if (data.is_update) {
          table
            .row($("#row_" + data.id))
            .remove()
            .draw(false);
        }
      }

      swal
        .fire({
          title: title,
          text: data.message,
          icon: data.status,
          confirmButtonText: "Tamam",
        })
        .then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
    });
});

$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  var formData = new FormData();
  formData.append("action", "is-turu-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        // We need to set the hidden input to the ENCRYPTED id so save works
        $("#is_turu_id").val(id);
        $("#is_turu").val(data.data.is_turu).trigger("change");

        $("#is_emri_sonucu").val(data.data.is_emri_sonucu);
        $("#is_turu_ucret").val(data.data.is_turu_ucret);
        $("#aracli_personel_is_turu_ucret").val(
          data.data.aracli_personel_is_turu_ucret,
        );
        setFlatpickrValue("#ucret_gecerlilik_baslangic", getTodayTr());
        $("#rapor_sekmesi").val(data.data.rapor_sekmesi).trigger("change");
        $("#aciklama").val(data.data.aciklama);
        $("#actionModalLabel").text("İş Türü Düzenle");
        $("#actionModal").modal("show");
      } else {
        swal.fire("Hata", data.message, "error");
      }
    });
});

$(document).on("click", ".sil", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  swal
    .fire({
      title: "Emin misiniz?",
      text: "Bu kaydı silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "İptal",
    })
    .then((result) => {
      if (result.isConfirmed) {
        var formData = new FormData();
        formData.append("action", "is-turu-sil");
        formData.append("id", id);

        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status == "success") {
              var table = $("#actionTable").DataTable();
              table
                .row($("#row_" + data.deleted_id))
                .remove()
                .draw(false);

              swal.fire("Silindi!", data.message, "success");
            } else {
              swal.fire("Hata", data.message, "error");
            }
          });
      }
    });
});

// Excel Yükle Form Submit
$(document).on("submit", "#formExcelYukle", function (e) {
  e.preventDefault();

  var form = $(this);
  var formData = new FormData(form[0]);
  formData.append("action", "is-turu-excel-yukle");

  // Yükleniyor göster
  Swal.fire({
    title: "Yükleniyor...",
    text: "Excel dosyası işleniyor, lütfen bekleyin.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      Swal.close();

      if (data.status === "success") {
        Swal.fire({
          title: "Başarılı",
          html: `
            <div class="text-center">
              <p>${data.message}</p>
              ${data.insertCount > 0 ? `<div class="badge bg-success me-1">${data.insertCount} yeni kayıt</div>` : ""}
              ${data.updateCount > 0 ? `<div class="badge bg-info">${data.updateCount} güncelleme</div>` : ""}
            </div>
          `,
          icon: "success",
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
      } else if (data.status === "warning") {
        Swal.fire({
          title: "Uyarı",
          text: data.message,
          icon: "warning",
          confirmButtonText: "Tamam",
        });
      } else {
        Swal.fire({
          title: "Hata",
          text: data.message,
          icon: "error",
          confirmButtonText: "Tamam",
        });
      }

      // Modal'ı kapat ve formu sıfırla
      $("#excelModal").modal("hide");
      form[0].reset();
    })
    .catch((error) => {
      Swal.close();
      Swal.fire({
        title: "Hata",
        text: "Bir hata oluştu: " + error.message,
        icon: "error",
        confirmButtonText: "Tamam",
      });
    });
});

$(document).on("input", "#is_turu_ucret", function () {
  if ($("#is_turu_id").val() == 0) {
    $("#aracli_personel_is_turu_ucret").val($(this).val()).trigger("input");
  }
});
