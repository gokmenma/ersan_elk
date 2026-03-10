$(document).ready(function () {
  initHakedisTable();

  $("#yeniHakedisForm").on("submit", function (e) {
    e.preventDefault();
    saveHakedis(this);
  });
});

let hakedisTable;

function initHakedisTable() {
  let options =
    typeof getDatatableOptions === "function"
      ? getDatatableOptions()
      : {
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json",
          },
          processing: true,
          serverSide: true,
        };

  options.processing = true;
  options.serverSide = true;
  options.ajax = {
    url: "views/hakedisler/online-api.php?type=getHakedisler",
    type: "POST",
    data: function (d) {
      d.sozlesme_id = currentSozlesmeId;
    },
  };
  ((options.columns = [
    {
      data: "hakedis_no",
      render: function (data) {
        return `<strong>#${data}</strong>`;
      },
    },
    {
      data: null,
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
        return `${aylar[row.hakedis_tarihi_ay]} ${row.hakedis_tarihi_yil}`;
      },
    },
    {
      data: null,
      render: function (data, type, row) {
        let temel = row.temel_endeks_ayi || "-";
        let guncel = row.guncel_endeks_ayi || "-";
        return `<span class="badge bg-info">${temel}</span> <i class="bx bx-right-arrow-alt"></i> <span class="badge bg-warning">${guncel}</span>`;
      },
    },
    {
      data: "tutanak_tasdik_tarihi",
      render: function (data) {
        if (!data || data === '0000-00-00') return '<span class="text-muted">-</span>';
        const parts = data.split('-');
        if (parts.length !== 3) return data;
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
      },
    },
    {
      data: "imalat_donem",
      render: function (data, type, row) {
        let manufacture = parseFloat(data || 0).toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
        let ff = parseFloat(row.fiyat_farki || 0).toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
        
        return `<div><strong>${manufacture}</strong></div>
                <div class="text-success" style="font-size: 11px;">
                    <i class="bx bx-plus-circle me-1"></i>FF: ${ff}
                </div>`;
      },
    },
    {
      data: "durum",
      render: function (data) {
        const durumMap = {
          taslak: { badge: "bg-secondary", label: "Taslak" },
          hazirlandi: { badge: "bg-info", label: "Hazırlandı" },
          tamamlandi: { badge: "bg-success", label: "Tamamlandı" },
          onaylandi: { badge: "bg-primary", label: "Onaylandı" },
        };
        let d = durumMap[data] || { badge: "bg-secondary", label: data };
        return `<span class="badge ${d.badge}">${d.label}</span>`;
      },
    },
    {
      data: "id",
      orderable: false,
      render: function (data, type, row) {
        let deleteBtn = row.durum === 'tamamlandi' ? '' : `
            <button class="btn btn-sm btn-danger" onclick="deleteHakedis(${data})" title="Sil">
                <i class="bx bx-trash"></i>
            </button>`;
        
        return `
                        <div class="d-flex gap-2">
                            <a href="?p=hakedisler/hakedis-detay&id=${data}" class="btn btn-sm btn-primary" title="Miktarlar ve Fiyat Farkı">
                                <i class="bx bx-list-ol"></i> İçerik
                            </a>
                            <button class="btn btn-sm btn-warning" onclick="editHakedis(${data})" title="Düzenle">
                                <i class="bx bx-edit"></i>
                            </button>
                            ${deleteBtn}
                        </div>
                    `;
      },
    },
  ]),
    (options.order = [[0, "asc"]]));
  hakedisTable = $("#hakedisTable").DataTable(options);
}

function saveHakedis(form) {
  // Endeks label'larını hidden inputlara yaz
  if (typeof updateEndeksLabels === "function") {
    updateEndeksLabels();
  }

  const formData = $(form).serializeArray();
  formData.push({ name: "type", value: "saveHakedis" });

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
        Swal.fire("Başarılı!", "Hakediş kaydedildi.", "success").then(() => {
          $("#yeniHakedisModal").modal("hide");
          hakedisTable.ajax.reload();
          // window.location.href = "?p=hakedisler/hakedis-detay&id=" + response.hakedis_id;
        });
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

        $form
          .find('[name="durum"]')
          .val(data.durum || "taslak")
          .trigger("change");

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

  // Reset durum to taslak
  $form.find('[name="durum"]').val("taslak").trigger("change");

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
