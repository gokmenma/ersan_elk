function escapeHtml(text) {
  if (text == null) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

$(document).ready(function () {
  initHakedisTable();

  // Satır Tıklama (Detaya Git) - İşlem Sütunu Hariç
  $('#hakedisTable tbody').on('click', 'tr td:not(:last-child)', function (e) {
    if ($(e.target).closest('a, button, .dropdown-menu, input, select').length > 0) return;
    const href = $(this).closest('tr').find('a.hakedis-detay').attr('href');
    if (href) window.location.href = href;
  });

  $("#btnHakedisSave").on("click", function (e) {
    e.preventDefault();
    handleHakedisSubmit("save");
  });

  $("#btnHakedisSaveAndGo").on("click", function (e) {
    e.preventDefault();
    handleHakedisSubmit("saveAndGo");
  });

  $("#yeniHakedisForm").on("submit", function (e) {
    e.preventDefault();
    handleHakedisSubmit("saveAndGo");
  });
});

let hakedisTable;

function initHakedisTable() {
  const options = applyLengthStateSave({
    ...getDatatableOptions(),
    processing: true,
    serverSide: true,
    ajax: {
      url: "views/hakedisler/online-api.php?type=getHakedisler",
      type: "POST",
      data: function (d) {
        d.sozlesme_id = currentSozlesmeId;
      },
    },
    columns: [
      {
        data: "hakedis_no",
        className: "text-center align-middle",
        width: "90px",
        render: function (data) {
          return `<span class="fw-bold text-dark font-size-13">#${escapeHtml(data)}</span>`;
        },
      },
      {
        data: null,
        className: "align-middle",
        width: "140px",
        render: function (data, type, row) {
          const aylar = {
            1: "Ocak",
            2: "Şubat",
            3: "Mart",
            4: "Nisan",
            5: "Mayıs",
            6: "Haziran",
            7: "Temmuz",
            8: "Ağustos",
            9: "Eylül",
            10: "Ekim",
            11: "Kasım",
            12: "Aralık",
          };
          const ayStr = aylar[row.hakedis_tarihi_ay] || '';
          return `<span class="fw-semibold text-dark font-size-13">${escapeHtml(ayStr)} ${escapeHtml(row.hakedis_tarihi_yil)}</span>`;
        },
      },
      {
        data: null,
        className: "align-middle",
        render: function (data, type, row) {
          let temel = row.temel_endeks_ayi || "-";
          let guncel = row.guncel_endeks_ayi || "-";
          return `<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${escapeHtml(temel)}</span>` +
            ` <i class="bx bx-right-arrow-alt text-muted mx-1"></i> ` +
            `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${escapeHtml(guncel)}</span>`;
        },
      },
      {
        data: "tutanak_tasdik_tarihi",
        className: "text-center align-middle",
        width: "140px",
        render: function (data) {
          if (!data || data === "0000-00-00") return '<span class="text-muted font-size-12">-</span>';
          const parts = data.split("-");
          if (parts.length !== 3) return `<span class="font-size-12">${escapeHtml(data)}</span>`;
          return `<span class="fw-medium text-dark font-size-12">${parts[2]}.${parts[1]}.${parts[0]}</span>`;
        },
      },
      {
        data: "imalat_donem",
        className: "text-end align-middle",
        width: "160px",
        render: function (data, type, row) {
          let manufacture = parseFloat(data || 0).toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ₺";
          let ff = parseFloat(row.fiyat_farki || 0).toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ₺";

          return `<div><strong class="font-size-13 text-dark">${manufacture}</strong></div>
                  <div class="text-success font-size-11" style="line-height: 1.2;">
                      <i class="bx bx-plus-circle me-1"></i>FF: ${ff}
                  </div>`;
        },
      },
      {
        data: "durum",
        className: "text-center align-middle",
        width: "120px",
        render: function (data) {
          const durumMap = {
            taslak: { badge: "bg-secondary-subtle text-secondary border-secondary-subtle", label: "Taslak" },
            hazirlandi: { badge: "bg-info-subtle text-info border-info-subtle", label: "Hazırlandı" },
            tamamlandi: { badge: "bg-success-subtle text-success border-success-subtle", label: "Tamamlandı" },
            onaylandi: { badge: "bg-primary-subtle text-primary border-primary-subtle", label: "Onaylandı" },
          };
          let d = durumMap[data] || { badge: "bg-secondary-subtle text-secondary border-secondary-subtle", label: data || "Taslak" };
          return `<span class="badge ${d.badge} border rounded-pill px-2 py-1 font-size-11 fw-semibold">${escapeHtml(d.label)}</span>`;
        },
      },
      {
        data: "id",
        className: "text-center align-middle",
        width: "110px",
        orderable: false,
        searchable: false,
        render: function (data, type, row) {
          let deleteBtn = row.durum === "tamamlandi" ? "" : `
              <button type="button" class="btn btn-subtle-danger table-action-btn" onclick="deleteHakedis(${data})" title="Sil">
                  <i class="bx bx-trash font-size-14"></i>
              </button>`;

          return `
              <div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">
                  <a href="?p=hakedisler/hakedis-detay&id=${data}" class="btn btn-subtle-primary table-action-btn hakedis-detay" title="İçerik ve Miktarlar">
                      <i class="bx bx-list-ol font-size-14"></i>
                  </a>
                  <button type="button" class="btn btn-subtle-warning table-action-btn" onclick="editHakedis(${data})" title="Düzenle">
                      <i class="bx bx-edit-alt font-size-14"></i>
                  </button>
                  ${deleteBtn}
              </div>
          `;
        },
      },
    ],
    order: [[0, "asc"]],
    drawCallback: function (settings) {
      let api = this.api();
      let json = api.ajax.json();

      if (json && json.data) {
        let totalImalat = 0;
        let totalFf = 0;

        json.data.forEach(function (row) {
          totalImalat += parseFloat(row.imalat_donem || 0);
          totalFf += parseFloat(row.fiyat_farki || 0);
        });

        let tImalatFmt = totalImalat.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ₺";
        let tFfFmt = totalFf.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ₺";

        let html = `<div><strong class="font-size-13 text-dark">${tImalatFmt}</strong></div>
            <div class="text-success font-size-11" style="line-height: 1.2;">
                <i class="bx bx-plus-circle me-1"></i>FF: ${tFfFmt}
            </div>`;
        $("#tableSayfaToplam").html(html);
      }
    }
  });

  hakedisTable = $("#hakedisTable").DataTable(options);
}

function handleHakedisSubmit(actionType) {
  const form = document.getElementById("yeniHakedisForm");
  const $form = $(form);
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const durum = $form.find('[name="durum"]').val();
  const hakedisNo = $form.find('[name="hakedis_no"]').val() || "";
  const hasDraftInvoice = $form.data("fatura-id") && $form.data("fatura-durum-kodu") === "TASLAK";

  if (durum === "tamamlandi") {
    let swalTitle = "E-Fatura Kesmek İstiyor Musunuz?";
    let swalHtml = `Hakediş durumu <b>'Tamamlandı'</b> olarak kaydedilecek.<br><br><b>#${hakedisNo} nolu hakediş</b> için otomatik e-fatura taslağı oluşturulsun mu?`;
    let confirmBtn = '<i class="bx bx-file me-1"></i> Evet, Fatura Taslağı Oluştur';

    if (hasDraftInvoice) {
      swalTitle = "Fatura Taslağı Güncellensin mi?";
      swalHtml = `Hakediş durumu <b>'Tamamlandı'</b> olarak kaydedilecek.<br><br>Bu hakedişe ait mevcut <b>e-fatura taslağı</b> güncel tutarlarla güncellensin mi?`;
      confirmBtn = '<i class="bx bx-refresh me-1"></i> Evet, Taslağı Güncelle';
    }

    Swal.fire({
      title: swalTitle,
      html: swalHtml,
      icon: "question",
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: confirmBtn,
      denyButtonText: '<i class="bx bx-check me-1"></i> Hayır, Sadece Kaydet',
      cancelButtonText: "Vazgeç",
      confirmButtonColor: "#34c38f",
      denyButtonColor: "#556ee6",
      cancelButtonColor: "#74788d",
      allowOutsideClick: false,
    }).then((result) => {
      if (result.isConfirmed) {
        executeSaveHakedis(form, actionType, 1);
      } else if (result.isDenied) {
        executeSaveHakedis(form, actionType, 0);
      }
    });
  } else {
    executeSaveHakedis(form, actionType, 0);
  }
}

function executeSaveHakedis(form, actionType, createInvoice) {
  // Endeks label'larını hidden inputlara yaz
  if (typeof updateEndeksLabels === "function") {
    updateEndeksLabels();
  }

  const formData = $(form).serializeArray();
  formData.push({ name: "type", value: "saveHakedis" });
  formData.push({ name: "create_invoice_draft", value: createInvoice });

  // If durum select is disabled, serializeArray won't include it, so manually append if missing
  if (!formData.some(item => item.name === "durum")) {
    formData.push({ name: "durum", value: $(form).find('[name="durum"]').val() });
  }

  Swal.fire({
    title: "Kaydediliyor...",
    allowEscapeKey: false,
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.post(
    "views/hakedisler/online-api.php",
    formData,
    function (response) {
      if (response.status === "success") {
        if ((response.invoice_created || response.invoice_updated) && response.invoice_id) {
          const invoiceLink = `index.php?p=efatura/olustur&id=${response.invoice_id}`;
          const isUpdated = response.invoice_updated;
          const successMsg = isUpdated
            ? "Hakediş kaydedildi ve <b>fatura taslağı güncellendi</b>."
            : "Hakediş kaydedildi ve <b>fatura taslağı oluşturuldu</b>.";

          Swal.fire({
            title: "Başarılı!",
            html: `${successMsg}<br><br>
                   <a href="${invoiceLink}" class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                       <i class="bx bx-edit me-1"></i> Fatura Taslağını Görüntüle
                   </a>`,
            icon: "success",
            confirmButtonText: actionType === "saveAndGo" ? "Hakediş Detayına Git" : "Tamam",
          }).then(() => {
            $("#yeniHakedisModal").modal("hide");
            hakedisTable.ajax.reload();
            if (actionType === "saveAndGo") {
              window.location.href = "?p=hakedisler/hakedis-detay&id=" + response.hakedis_id;
            }
          });
        } else {
          Swal.fire("Başarılı!", "Hakediş kaydedildi.", "success").then(() => {
            $("#yeniHakedisModal").modal("hide");
            hakedisTable.ajax.reload();
            if (actionType === "saveAndGo") {
              window.location.href = "?p=hakedisler/hakedis-detay&id=" + response.hakedis_id;
            }
          });
        }
      } else {
        Swal.fire("Hata!", response.message || "Bir hata oluştu.", "error");
      }
    },
    "json",
  ).fail(function () {
    Swal.fire("Hata!", "Sunucu bağlantısında sorun oluştu.", "error");
  });
}

function editHakedis(id) {
  Swal.fire({
    title: "Yükleniyor...",
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.post(
    "views/hakedisler/online-api.php",
    { type: "getHakedis", id: id },
    function (res) {
      if (res.status === "success") {
        Swal.close();
        const data = res.data;
        const $form = $("#yeniHakedisForm");

        $("#hakedis_id").val(data.id);
        $form.data("fatura-id", data.fatura_id || null);
        $form.data("fatura-durum-kodu", data.fatura_durum_kodu || null);

        $form.find('[name="hakedis_no"]').val(data.hakedis_no);

        // Hakediş Ayı ve Yılı - trigger change for Select2 and labels
        $form
          .find('[name="hakedis_tarihi_ay"]')
          .val(data.hakedis_tarihi_ay)
          .trigger("change");
        $form
          .find('[name="hakedis_tarihi_yil"]')
          .val(data.hakedis_tarihi_yil)
          .trigger("change");

        const $dateInput = $form.find('[name="is_yapilan_ayin_son_gunu"]');
        let dtVal = data.is_yapilan_ayin_son_gunu;
        if (dtVal && typeof dtVal === 'string' && dtVal.match(/^\d{4}-\d{2}-\d{2}$/)) {
            const parts = dtVal.split('-');
            dtVal = `${parts[2]}.${parts[1]}.${parts[0]}`;
        }
        if ($dateInput[0] && $dateInput[0]._flatpickr) {
          $dateInput[0]._flatpickr.setDate(dtVal);
        } else {
          $dateInput.val(dtVal);
        }

        const $tutanakInput = $form.find('[name="tutanak_tasdik_tarihi"]');
        let tutanakVal = data.tutanak_tasdik_tarihi;
        if (tutanakVal && typeof tutanakVal === 'string' && tutanakVal.match(/^\d{4}-\d{2}-\d{2}$/)) {
            const parts = tutanakVal.split('-');
            tutanakVal = `${parts[2]}.${parts[1]}.${parts[0]}`;
        }
        if ($tutanakInput[0] && $tutanakInput[0]._flatpickr) {
            $tutanakInput[0]._flatpickr.setDate(tutanakVal);
        } else {
            $tutanakInput.val(tutanakVal);
        }

        // Fatura durumuna göre kilit veya bilgilendirme kontrolü
        $("#faturaDurumInfo").remove();
        const $durumSelect = $form.find('[name="durum"]');

        if (data.fatura_durum_kodu && data.fatura_durum_kodu !== "TASLAK") {
          $durumSelect.prop("disabled", true).val(data.durum || "tamamlandi").trigger("change");
          $durumSelect.closest(".col-md-6").prepend(`
            <div id="faturaDurumInfo" class="alert alert-warning py-1 px-2 mb-2 small">
                <i class="bx bx-lock-alt me-1"></i> Fatura GİB'e iletilmiştir (<b>${data.fatura_durum_kodu}</b>${data.fatura_fatura_no ? ' - ' + data.fatura_fatura_no : ''}). Durum değiştirilemez.
            </div>
          `);
        } else {
          $durumSelect.prop("disabled", false).val(data.durum || "taslak").trigger("change");
          if (data.fatura_durum_kodu === "TASLAK") {
            $durumSelect.closest(".col-md-6").prepend(`
              <div id="faturaDurumInfo" class="alert alert-info py-1 px-2 mb-2 small">
                  <i class="bx bx-info-circle me-1"></i> Bu hakedişe bağlı <b>TASLAK</b> fatura mevcuttur.
              </div>
            `);
          }
        }

        $form.find('[name="onceki_hakedis_tutari"]').val(data.onceki_hakedis_tutari || 0);

        // Update hidden fields
        $("#temel_endeks_ayi_hidden").val(data.temel_endeks_ayi || "");
        $("#guncel_endeks_ayi_hidden").val(data.guncel_endeks_ayi || "");

        // Update labels
        updateEndeksLabels();

        $("#yeniHakedisModal").modal("show");

        // Select2 clipping fix
        setTimeout(() => {
          $form.find(".select2").each(function () {
            $(this).select2({
              dropdownParent: $("#yeniHakedisModal"),
              language: "tr",
            });
          });
        }, 300);

        if (typeof feather !== "undefined") {
          setTimeout(() => {
            feather.replace();
          }, 100);
        }
      } else {
        Swal.fire("Hata", res.message, "error");
      }
    },
    "json",
  );
}

$(document).on("click", '[data-bs-target="#yeniHakedisModal"]', function () {
  const $form = $("#yeniHakedisForm");
  $form[0].reset();
  $("#hakedis_id").val("");
  $form.removeData("fatura-id").removeData("fatura-durum-kodu");
  $("#faturaDurumInfo").remove();

  // Reset durum to taslak and enable it
  $form.find('[name="durum"]').prop("disabled", false).val("taslak").trigger("change");

  // Reset date to current month's last day
  const now = new Date();
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  const y = lastDay.getFullYear();
  const m = String(lastDay.getMonth() + 1).padStart(2, "0");
  const d = String(lastDay.getDate()).padStart(2, "0");
  const lastDayStr = `${d}.${m}.${y}`;
  
  const $dateInput = $form.find('[name="is_yapilan_ayin_son_gunu"]');
  if ($dateInput[0] && $dateInput[0]._flatpickr) {
    $dateInput[0]._flatpickr.setDate(lastDayStr);
  } else {
    $dateInput.val(lastDayStr);
  }

  // Clear tutanak tasdik tarihi
  const $tutanakInput = $form.find('[name="tutanak_tasdik_tarihi"]');
  if ($tutanakInput[0] && $tutanakInput[0]._flatpickr) {
    $tutanakInput[0]._flatpickr.clear();
  } else {
    $tutanakInput.val('');
  }

  // Initialize Select2 with dropdownParent to prevent clipping
  setTimeout(() => {
    $form.find(".select2").each(function () {
      $(this).select2({
        dropdownParent: $("#yeniHakedisModal"),
        language: "tr",
      });
    });
  }, 300);

  // Reset endeks labels
  if (typeof updateEndeksLabels === "function") {
    setTimeout(() => {
      updateEndeksLabels();
    }, 50);
  }

  if (typeof feather !== "undefined") {
    setTimeout(() => {
      feather.replace();
    }, 100);
  }
});

// Ay/Yıl değiştiğinde tarihi otomatik güncelle
$(document).on(
  "change",
  "#hakedis_tarihi_ay, #hakedis_tarihi_yil",
  function (e) {
    const ay = parseInt($("#hakedis_tarihi_ay").val());
    const yil = parseInt($("#hakedis_tarihi_yil").val());
    if (ay && yil) {
      // Ayın son gününü bul
      // JS Date'te ay 0-indexed, ama biz 1-indexed veriyoruz.
      // new Date(yil, ay, 0) -> ay'ıncaya kadarki ayın (bir sonraki ayın) 0. günü = istenen ayın son günü
      const lastDayDate = new Date(yil, ay, 0);
      const y = lastDayDate.getFullYear();
      const m = String(lastDayDate.getMonth() + 1).padStart(2, "0"); 
      const d = String(lastDayDate.getDate()).padStart(2, "0");
      const lastDayStr = `${d}.${m}.${y}`; // dd.mm.yyyy formatı

      const $dateInput = $("#yeniHakedisForm").find(
        '[name="is_yapilan_ayin_son_gunu"]',
      );

      // Flatpickr varsa onun üzerinden güncelle, yoksa normal val
      if ($dateInput[0] && $dateInput[0]._flatpickr) {
        $dateInput[0]._flatpickr.setDate(lastDayStr);
      } else {
        $dateInput.val(lastDayStr);
      }

      updateEndeksLabels();
    }
  },
);

// Tarih değiştiğinde ay/yıl selectlerini güncelle
$(document).on("change", '[name="is_yapilan_ayin_son_gunu"]', function (e) {
  // Hem manuel hem de flatpickr kaynaklı değişimleri yakala
  if (e.originalEvent || e.isTrigger) {
    const dateVal = $(this).val();
    if (dateVal && dateVal.includes(".")) {
      const parts = dateVal.split(".");
      if (parts.length === 3) {
        const ay = parseInt(parts[1]);
        const yil = parseInt(parts[2]);

        if (ay && yil) {
          $("#hakedis_tarihi_ay").val(ay).trigger("change.select2");
          $("#hakedis_tarihi_yil").val(yil);
          updateEndeksLabels();
        }
      }
    }
  }
});

function deleteHakedis(id) {
  // Check if it's completed from the table data first (for immediate feedback)
  const rowData = hakedisTable.rows().data().toArray().find(r => r.id == id);
  if (rowData && rowData.durum === 'tamamlandi') {
    Swal.fire("Uyarı", "Tamamlanmış hakedişler silinemez. Lütfen önce durumu 'Taslak' veya 'Hazırlandı' olarak değiştirin.", "warning");
    return;
  }

  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu hakedişin tüm detay verileri ve miktar girişleri silinecektir. Geri alınamaz!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "views/hakedisler/online-api.php",
        { type: "deleteHakedis", id: id },
        function (res) {
          if (res.status == "success") {
            hakedisTable.ajax.reload();
            Swal.fire("Silindi!", "Hakediş başarıyla silindi.", "success");
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        },
        "json",
      );
    }
  });
}
