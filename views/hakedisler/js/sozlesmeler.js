$(document).ready(function () {
  initSozlesmelerTable();

  $("#yeniSozlesmeForm").on("submit", function (e) {
    e.preventDefault();
    saveSozlesme(this);
  });
});

let sozlesmeTable;

function initSozlesmelerTable() {
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
    url: "views/hakedisler/online-api.php?type=getSozlesmeler",
    type: "POST",
  };
  ((options.columns = [
    { data: "idare_adi" },
    {
      data: "isin_adi",
      render: function (data, type, row) {
        return data.length > 50 ? data.substr(0, 50) + "..." : data;
      },
    },
    {
      data: "sozlesme_tarihi",
      render: function (data) {
        return data ? moment(data).format("DD.MM.YYYY") : "-";
      },
    },
    {
      data: "isin_bitecegi_tarih",
      render: function (data) {
        return data ? moment(data).format("DD.MM.YYYY") : "-";
      },
    },
    {
      data: "sozlesme_bedeli",
      render: function (data) {
        return data
          ? parseFloat(data).toLocaleString("tr-TR", {
              style: "currency",
              currency: "TRY",
            })
          : "-";
      },
    },
    {
      data: "durum",
      render: function (data) {
        let badge = "bg-primary";
        if (data == "tamamlandi") badge = "bg-success";
        if (data == "pasif") badge = "bg-danger";
        return `<span class="badge ${badge}">${data.toUpperCase()}</span>`;
      },
    },
    {
      data: "id",
      orderable: false,
      render: function (data) {
        return `
                        <div class="d-flex gap-2">
                            <a href="?p=hakedisler/sozlesme-detay&id=${data}" class="btn btn-sm btn-info" title="Detaya Git">
                                <i class="bx bx-file-find"></i> Detay/Hakedişler
                            </a>
                            <button class="btn btn-sm btn-warning" onclick="editSozlesme(${data})" title="Düzenle">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSozlesme(${data})" title="Sil">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    `;
      },
    },
  ]),
    (options.order = [[2, "desc"]]));
  sozlesmeTable = $("#sozlesmeTable").DataTable(options);
}

function saveSozlesme(form) {
  const formData = $(form).serializeArray();

  // Tablodaki verileri de topla
  let kalemler = [];
  $("#birimFiyatBody tr").each(function (index) {
    const kalemId = $(this).find('input[name="kalem_id[]"]').val();
    const pNo = $(this).find('input[name="kalem_poz_no[]"]').val();
    const ad = $(this).find('input[name="kalem_adi[]"]').val();
    const birim = $(this).find('select[name="kalem_birim[]"]').val();
    const miktar = parseFloat(
      $(this).find('input[name="kalem_miktar[]"]').val() || 0,
    );
    const fiyat = parseFloat(
      $(this).find('input[name="kalem_teklif_fiyat[]"]').val() || 0,
    );

    if (ad && fiyat > 0) {
      kalemler.push({
        id: kalemId,
        poz_no: pNo,
        kalem_adi: (pNo ? pNo + " - " : "") + ad,
        birim: birim,
        miktari: miktar,
        teklif_edilen_birim_fiyat: fiyat,
      });
    }
  });

  formData.push({ name: "type", value: "saveSozlesme" });
  formData.push({ name: "kalem_verileri", value: JSON.stringify(kalemler) });

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
        Swal.fire("Başarılı!", "Sözleşme kaydedildi.", "success");
        $("#yeniSozlesmeModal").modal("hide");
        form.reset();
        $("#birimFiyatBody").empty();
        hesaplaGenelToplam();
        sozlesmeTable.ajax.reload();
      } else {
        Swal.fire("Hata!", response.message || "Bir hata oluştu.", "error");
      }
    },
    "json",
  ).fail(function () {
    Swal.fire("Hata!", "Sunucu bağlantısında sorun oluştu.", "error");
  });
}

function deleteSozlesme(id) {
  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu sözleşmeyi ve ilişkili tüm hakediş/kalem verilerini silmek üzeresiniz. Bu işlem geri alınamaz!",
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
        { type: "deleteSozlesme", id: id },
        function (res) {
          if (res.status == "success") {
            sozlesmeTable.ajax.reload();
            Swal.fire("Silindi!", "Sözleşme başarıyla silindi.", "success");
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        },
        "json",
      );
    }
  });
}

function editSozlesme(id) {
  Swal.fire({
    title: "Yükleniyor...",
    allowEscapeKey: false,
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.post(
    "views/hakedisler/online-api.php",
    { type: "getSozlesme", id: id },
    function (res) {
      if (res.status === "success") {
        Swal.close();
        const data = res.data;
        const form = $("#yeniSozlesmeForm")[0];
        form.reset();
        $("#birimFiyatBody").empty();

        // Populate form
        for (let key in data) {
          const el = $(form).find(`[name="${key}"]`);
          if (el.length) {
            el.val(data[key]);
          }
        }

        // Load kalemler if exists
        if (res.kalemler && res.kalemler.length > 0) {
          res.kalemler.forEach((k) => {
            let tr = satirEkle();

            // Deneyebilirsen poz_no çıkar
            let adi = k.kalem_adi;
            let pz = "";
            if (adi.includes(" - ")) {
              let parts = adi.split(" - ");
              pz = parts[0];
              parts.shift();
              adi = parts.join(" - ");
            }

            tr.find('input[name="kalem_id[]"]').val(k.id);
            tr.find('input[name="kalem_poz_no[]"]').val(pz);
            tr.find('input[name="kalem_adi[]"]').val(adi);
            tr.find('select[name="kalem_birim[]"]').val(k.birim);
            tr.find('input[name="kalem_miktar[]"]').val(k.miktari);
            tr.find('input[name="kalem_teklif_fiyat[]"]').val(
              parseFloat(k.teklif_edilen_birim_fiyat).toFixed(2),
            );
            hesaplaSatirTutar(tr.find('input[name="kalem_miktar[]"]')[0]);
          });
        }

        // Reset tab
        $('.nav-tabs a[href="#sozlesme-bilgileri-tab"]').tab("show");
        $("#yeniSozlesmeModal").modal("show");
      } else {
        Swal.fire("Hata", res.message, "error");
      }
    },
    "json",
  );
}

// Yeni Sözleşme Butonu Eventi
$(document).on("click", '[data-bs-target="#yeniSozlesmeModal"]', function () {
  $("#yeniSozlesmeForm")[0].reset();
  $("#sozlesme_id").val("");
  $("#birimFiyatBody").empty();
  hesaplaGenelToplam();
  $('.nav-tabs a[href="#sozlesme-bilgileri-tab"]').tab("show");
});

function satirEkle() {
  let sira = $("#birimFiyatBody tr").length + 1;
  let tr = $(`
        <tr>
            <td class="text-center fw-bold align-middle sira-no">${sira}</td>
            <td>
                <input type="hidden" name="kalem_id[]" value="">
                <input type="text" class="form-control form-control-sm" name="kalem_poz_no[]" placeholder="Örn: KASKİ-01">
            </td>
            <td><input type="text" class="form-control form-control-sm required-kalem" name="kalem_adi[]" placeholder="Örn: Sayaç Sökme" required></td>
            <td>
                <select class="form-select form-select-sm" name="kalem_birim[]">
                    <option value="Adet">Adet</option>
                    <option value="Metre">Metre</option>
                    <option value="Km">Km</option>
                    <option value="Gün">Gün</option>
                    <option value="Ay">Ay</option>
                    <option value="Saat">Saat</option>
                    <option value="Ton">Ton</option>
                    <option value="Litre">Litre</option>
                    <option value="m2">m2</option>
                    <option value="m3">m3</option>
                </select>
            </td>
            <td><input type="number" step="0.01" class="form-control form-control-sm" name="kalem_miktar[]" onkeyup="hesaplaSatirTutar(this)" onchange="hesaplaSatirTutar(this)" value="0"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm" name="kalem_teklif_fiyat[]" onkeyup="hesaplaSatirTutar(this)" onchange="hesaplaSatirTutar(this)" value="0"></td>
            <td class="text-end fw-bold align-middle satir-tutar">0,00 ₺</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="satirSil(this)"><i class="bx bx-trash"></i></button>
            </td>
        </tr>
    `);

  $("#birimFiyatBody").append(tr);
  return tr;
}

function satirSil(btn) {
  $(btn).closest("tr").remove();
  sirala();
  hesaplaGenelToplam();
}

function sirala() {
  $("#birimFiyatBody tr").each(function (index) {
    $(this)
      .find(".sira-no")
      .text(index + 1);
  });
}

function hesaplaSatirTutar(input) {
  let tr = $(input).closest("tr");
  let m = parseFloat(tr.find('input[name="kalem_miktar[]"]').val() || 0);
  let f = parseFloat(tr.find('input[name="kalem_teklif_fiyat[]"]').val() || 0);
  let t = m * f;
  tr.find(".satir-tutar").text(
    t.toLocaleString("tr-TR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + " ₺",
  );
  hesaplaGenelToplam();
}

function hesaplaGenelToplam() {
  let g = 0;
  $("#birimFiyatBody tr").each(function () {
    let m = parseFloat($(this).find('input[name="kalem_miktar[]"]').val() || 0);
    let f = parseFloat(
      $(this).find('input[name="kalem_teklif_fiyat[]"]').val() || 0,
    );
    g += m * f;
  });
  $("#genelToplamTutar").text(
    g.toLocaleString("tr-TR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + " ₺",
  );

  // Opsiyonel: Sözleşme bedeline yazalım mı?
  // $('input[name="sozlesme_bedeli"]').val(g.toFixed(2));
}
