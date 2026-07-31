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
        kalem_adi: ad,
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
            let val = data[key];
            if (val === '0000-00-00') {
              val = '';
            }
            if (val && typeof val === 'string' && val.match(/^\d{4}-\d{2}-\d{2}$/)) {
                // YYYY-MM-DD formatını DD.MM.YYYY yapalım
                const parts = val.split('-');
                val = `${parts[2]}.${parts[1]}.${parts[0]}`;
            }

            if (el[0]._flatpickr) {
              el[0]._flatpickr.setDate(val);
            } else {
              el.val(val);
            }
            if (el.hasClass("select2")) {
              el.trigger("change");
            }
          }
        }

        // Load kalemler if exists
        if (res.kalemler && res.kalemler.length > 0) {
          res.kalemler.forEach((k) => {
            let tr = satirEkle();

            // Deneyebilirsen poz_no çıkar
            let adi = k.kalem_adi;
            let pz = k.poz_no || "";
            if (!pz && adi.includes(" - ")) {
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
            if (parseInt(res.revizyon_sayisi || 0, 10) > 0) {
              tr.find('input[name="kalem_miktar[]"]').prop("readonly", true)
                .attr("title", "Miktar, İş Artış/Azalış İşlemleri sekmesinden güncellenir.");
            }
            tr.find('input[name="kalem_teklif_fiyat[]"]').val(
              parseFloat(k.teklif_edilen_birim_fiyat).toFixed(2),
            );
            hesaplaSatirTutar(tr.find('input[name="kalem_miktar[]"]')[0]);
          });
        }

        const sureUzatimiVar = parseInt(res.sure_uzatim_sayisi || 0, 10) > 0;
        $('#yeniSozlesmeForm [name="isin_bitecegi_tarih"], #yeniSozlesmeForm [name="isin_suresi"]')
          .prop("readonly", sureUzatimiVar)
          .attr("title", sureUzatimiVar ? "Bu alan süre uzatımları sekmesinden güncellenir." : "");

        revizyonSekmesiniHazirla(data.id, res.kalemler || [], data.sozlesme_bedeli, res.revizyon_sayisi);
        sureUzatimSekmesiniHazirla(data.id, data.isin_bitecegi_tarih, res.sure_uzatim_sayisi);

        // Reset tab
        $('.nav-tabs a[href="#sozlesme-bilgileri-tab"]').tab("show");
        $("#yeniSozlesmeModal").modal("show");

        // Select2 clipping fix
        setTimeout(() => {
          $("#yeniSozlesmeForm")
            .find(".select2")
            .each(function () {
              $(this).select2({
                dropdownParent: $("#yeniSozlesmeModal"),
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

// Yeni Sözleşme Butonu Eventi
$(document).on("click", '[data-bs-target="#yeniSozlesmeModal"]', function () {
  $("#yeniSozlesmeForm")[0].reset();
  $("#sozlesme_id").val("");
  $('#yeniSozlesmeForm [name="isin_bitecegi_tarih"], #yeniSozlesmeForm [name="isin_suresi"]')
    .prop("readonly", false).attr("title", "");
  $("#birimFiyatBody").empty();
  revizyonSekmesiniSifirla();
  sureUzatimSekmesiniSifirla();
  hesaplaGenelToplam();
  $('.nav-tabs a[href="#sozlesme-bilgileri-tab"]').tab("show");

  // Initialize Select2 with dropdownParent to prevent clipping
  setTimeout(() => {
    $("#yeniSozlesmeForm")
      .find(".select2")
      .each(function () {
        $(this).select2({
          dropdownParent: $("#yeniSozlesmeModal"),
          language: "tr",
        });
      });
  }, 300);

  if (typeof feather !== "undefined") {
    setTimeout(() => {
      feather.replace();
    }, 100);
  }
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

function revizyonSekmesiniSifirla() {
  $("#revizyonYeniSozlesmeUyarisi").removeClass("d-none");
  $("#revizyonAlani").addClass("d-none");
  $("#revizyonKalemBody, #revizyonGecmisi").empty();
  $("#revizyonTutarFarki").text("0,00 ₺").removeClass("text-success text-danger");
  $("#revizyonToplamOrani").text("%0,00").removeClass("text-success text-danger");
  $("#revizyonYeniFormu").show();
  $("#revizyonYeniBtn").addClass("d-none");
  $("#revizyonFormKapatBtn").addClass("d-none");
}

function revizyonSekmesiniHazirla(sozlesmeId, kalemler, sozlesmeBedeli, revizyonSayisi) {
  $("#revizyonYeniSozlesmeUyarisi").addClass("d-none");
  $("#revizyonAlani").removeClass("d-none")
    .attr("data-sozlesme-id", sozlesmeId)
    .attr("data-sozlesme-bedeli", parseFloat(sozlesmeBedeli || 0));
  $("#revizyonKalemBody").empty();

  kalemler.forEach(function (k) {
    const mevcut = parseFloat(k.miktari || 0);
    const fiyat = parseFloat(k.teklif_edilen_birim_fiyat || 0);
    $("#revizyonKalemBody").append(`
      <tr data-kalem-id="${k.id}" data-mevcut="${mevcut}" data-fiyat="${fiyat}">
        <td>${escapeHtml(k.poz_no || "-")}</td>
        <td>${escapeHtml(k.kalem_adi || "")}</td>
        <td>${escapeHtml(k.birim || "")}</td>
        <td class="text-end">${formatMiktar(mevcut)}</td>
        <td><input type="number" step="0.0001" value="0"
          class="form-control form-control-sm revizyon-degisim" oninput="revizyonHesapla()"></td>
        <td class="text-end revizyon-yeni">${formatMiktar(mevcut)}</td>
        <td class="text-end revizyon-fark">0,00 ₺</td>
      </tr>`);
  });
  $("#revizyon_tarihi").val(moment().format("DD.MM.YYYY"));
  $("#revizyon_karar_no, #revizyon_aciklama").val("");
  const revizyonVar = parseInt(revizyonSayisi || 0, 10) > 0;
  $("#revizyonYeniFormu").toggle(!revizyonVar);
  $("#revizyonYeniBtn").addClass("d-none");
  $("#revizyonFormKapatBtn").addClass("d-none");
  revizyonHesapla();
  revizyonGecmisiniYukle(sozlesmeId);
}

function revizyonHesapla() {
  let toplam = 0;
  let mevcutToplam = 0;
  $("#revizyonKalemBody tr").each(function () {
    const mevcut = parseFloat($(this).data("mevcut")) || 0;
    const fiyat = parseFloat($(this).data("fiyat")) || 0;
    const degisim = parseFloat($(this).find(".revizyon-degisim").val()) || 0;
    const yeni = mevcut + degisim;
    const fark = degisim * fiyat;
    mevcutToplam += mevcut * fiyat;
    toplam += fark;
    $(this).find(".revizyon-yeni").text(formatMiktar(yeni))
      .toggleClass("text-danger", yeni < 0);
    $(this).find(".revizyon-fark").text(formatPara(fark));
  });
  $("#revizyonTutarFarki").text(formatPara(toplam))
    .toggleClass("text-success", toplam > 0)
    .toggleClass("text-danger", toplam < 0);
  const sozlesmeBedeli = parseFloat($("#revizyonAlani").attr("data-sozlesme-bedeli")) || 0;
  const yeniToplam = mevcutToplam + toplam;
  const oran = sozlesmeBedeli > 0 ? ((yeniToplam - sozlesmeBedeli) / sozlesmeBedeli) * 100 : 0;
  $("#revizyonToplamOrani").text(formatYuzde(oran))
    .toggleClass("text-success", oran > 0)
    .toggleClass("text-danger", oran < 0);
}

function revizyonKaydet() {
  const sozlesmeId = parseInt($("#revizyonAlani").attr("data-sozlesme-id"), 10);
  const kalemler = [];
  let gecersiz = false;
  $("#revizyonKalemBody tr").each(function () {
    const mevcut = parseFloat($(this).data("mevcut")) || 0;
    const degisim = parseFloat($(this).find(".revizyon-degisim").val()) || 0;
    if (mevcut + degisim < 0) gecersiz = true;
    if (Math.abs(degisim) > 0.0000001) {
      kalemler.push({ kalem_id: parseInt($(this).data("kalem-id"), 10), degisim_miktari: degisim });
    }
  });
  if (!$("#revizyon_tarihi").val()) {
    Swal.fire("Uyarı", "Revizyon tarihini giriniz.", "warning");
    return;
  }
  if (!kalemler.length) {
    Swal.fire("Uyarı", "En az bir kalemde artış veya azalış miktarı giriniz.", "warning");
    return;
  }
  if (gecersiz) {
    Swal.fire("Uyarı", "Yeni miktar sıfırın altında olamaz.", "warning");
    return;
  }

  Swal.fire({
    title: "İşlemi kaydetmek istiyor musunuz?",
    text: "Kalem miktarları güncellenecek ve işlem revizyon geçmişine eklenecek.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Evet, Kaydet",
    cancelButtonText: "İptal"
  }).then(function (result) {
    if (!result.isConfirmed) return;
    $("#revizyonKaydetBtn").prop("disabled", true);
    $.post("views/hakedisler/online-api.php", {
      type: "saveIsRevizyonu",
      sozlesme_id: sozlesmeId,
      revizyon_tarihi: $("#revizyon_tarihi").val(),
      karar_no: $("#revizyon_karar_no").val(),
      aciklama: $("#revizyon_aciklama").val(),
      kalemler: JSON.stringify(kalemler)
    }, function (res) {
      if (res.status !== "success") {
        Swal.fire("Hata", res.message || "İşlem kaydedilemedi.", "error");
        return;
      }
      Swal.fire("Başarılı", `${res.revizyon_no}. iş artış/azalış işlemi kaydedildi.`, "success");
      editSozlesme(sozlesmeId);
    }, "json").fail(function () {
      Swal.fire("Hata", "Sunucu bağlantısında sorun oluştu.", "error");
    }).always(function () {
      $("#revizyonKaydetBtn").prop("disabled", false);
    });
  });
}

function revizyonGecmisiniYukle(sozlesmeId) {
  $("#revizyonGecmisi").html('<div class="text-muted">Yükleniyor...</div>');
  $.post("views/hakedisler/online-api.php", {
    type: "getIsRevizyonlari", sozlesme_id: sozlesmeId
  }, function (res) {
    if (res.status !== "success" || !res.data.length) {
      $("#revizyonYeniFormu").show();
      $("#revizyonYeniBtn").addClass("d-none");
      $("#revizyonFormKapatBtn").addClass("d-none");
      $("#revizyonGecmisi").html('<div class="alert alert-light border">Henüz iş artış/azalış işlemi bulunmuyor.</div>');
      return;
    }
    $("#revizyonYeniFormu").hide();
    $("#revizyonYeniBtn").removeClass("d-none");
    $("#revizyonFormKapatBtn").addClass("d-none");
    let html = '<div class="accordion" id="revizyonAccordion">';
    res.data.forEach(function (r, i) {
      const toplam = parseFloat(r.toplam_tutar_farki || 0);
      const toplamOran = parseFloat(r.toplam_artis_orani || 0);
      html += `<div class="accordion-item">
        <div class="accordion-header d-flex align-items-stretch">
          <button class="accordion-button ${i ? "collapsed" : ""}" type="button" data-bs-toggle="collapse"
            data-bs-target="#revizyon-${r.id}">
            <span class="fw-bold me-2">${r.revizyon_no}. Revizyon</span>
            <span class="text-muted me-2">${moment(r.revizyon_tarihi).format("DD.MM.YYYY")}</span>
            ${r.karar_no ? `<span class="badge bg-light text-dark me-2">${escapeHtml(r.karar_no)}</span>` : ""}
            <span class="ms-auto me-3 ${toplamOran < 0 ? "text-danger" : "text-success"}">
              ${formatPara(toplam)} <small class="ms-1">(${formatYuzde(toplamOran)})</small>
            </span>
          </button>
          <button type="button" class="btn btn-outline-danger rounded-0 px-3" title="Revizyonu sil"
            onclick="revizyonSil(${r.id}, ${sozlesmeId})"><i class="bx bx-trash"></i></button>
        </div>
        <div id="revizyon-${r.id}" class="accordion-collapse collapse ${i ? "" : "show"}"
          data-bs-parent="#revizyonAccordion"><div class="accordion-body">
          ${r.aciklama ? `<p>${escapeHtml(r.aciklama)}</p>` : ""}
          <div class="table-responsive"><table class="table table-sm mb-0">
          <thead><tr><th>Poz / Kalem</th><th class="text-end">Önceki</th><th class="text-end">Değişim</th><th class="text-end">Yeni</th><th class="text-end">Tutar Farkı</th></tr></thead><tbody>`;
      r.kalemler.forEach(function (k) {
        const d = parseFloat(k.degisim_miktari);
        html += `<tr><td>${escapeHtml((k.poz_no ? k.poz_no + " - " : "") + k.kalem_adi)}</td>
          <td class="text-end">${formatMiktar(k.onceki_miktar)}</td>
          <td class="text-end ${d < 0 ? "text-danger" : "text-success"}">${d > 0 ? "+" : ""}${formatMiktar(d)}</td>
          <td class="text-end">${formatMiktar(k.yeni_miktar)}</td>
          <td class="text-end">${formatPara(d * parseFloat(k.birim_fiyat))}</td></tr>`;
      });
      html += '</tbody></table></div></div></div></div>';
    });
    $("#revizyonGecmisi").html(html + "</div>");
  }, "json");
}

function yeniRevizyonFormunuAc() {
  $("#revizyonYeniFormu").stop(true, true).slideDown(250);
  $("#revizyonYeniBtn").addClass("d-none");
  $("#revizyonFormKapatBtn").removeClass("d-none");
  $("#revizyon_tarihi").trigger("focus");
}

function revizyonFormunuKapat() {
  $("#revizyonYeniFormu").stop(true, true).slideUp(200);
  $("#revizyonYeniBtn").removeClass("d-none");
  $("#revizyonFormKapatBtn").addClass("d-none");
}

function revizyonSil(revizyonId, sozlesmeId) {
  Swal.fire({
    title: "Revizyon silinsin mi?",
    text: "Kalem miktarları bu revizyon geri alınarak yeniden hesaplanacak.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Evet, Sil",
    cancelButtonText: "Vazgeç",
    confirmButtonColor: "#dc3545"
  }).then(function (result) {
    if (!result.isConfirmed) return;
    $.post("views/hakedisler/online-api.php", {
      type: "deleteIsRevizyonu", revizyon_id: revizyonId, sozlesme_id: sozlesmeId
    }, function (res) {
      if (res.status !== "success") {
        Swal.fire("Hata", res.message || "Revizyon silinemedi.", "error");
        return;
      }
      Swal.fire("Silindi", "Revizyon ve miktar etkisi geri alındı.", "success");
      editSozlesme(sozlesmeId);
    }, "json").fail(function () {
      Swal.fire("Hata", "Sunucu bağlantısında sorun oluştu.", "error");
    });
  });
}

function formatMiktar(value) {
  return Number(value || 0).toLocaleString("tr-TR", { minimumFractionDigits: 0, maximumFractionDigits: 4 });
}

function formatPara(value) {
  return Number(value || 0).toLocaleString("tr-TR", { style: "currency", currency: "TRY" });
}

function formatYuzde(value) {
  const sayi = Number(value || 0);
  return `%${sayi.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function sureUzatimSekmesiniSifirla() {
  $("#sureUzatimYeniSozlesmeUyarisi").removeClass("d-none");
  $("#sureUzatimAlani").addClass("d-none").removeAttr("data-sozlesme-id data-mevcut-bitis");
  $("#sure_uzatim_tarihi, #sure_uzatim_karar_no, #sure_uzatim_gun, #sure_uzatim_aciklama").val("");
  $("#sureUzatimMevcutBitis, #sureUzatimYeniBitis").text("-");
  $("#sureUzatimGecmisi").empty();
  $("#sureUzatimYeniFormu").show();
  $("#sureUzatimYeniBtn, #sureUzatimFormKapatBtn").addClass("d-none");
}

function sureUzatimSekmesiniHazirla(sozlesmeId, bitisTarihi, uzatimSayisi) {
  $("#sureUzatimYeniSozlesmeUyarisi").addClass("d-none");
  $("#sureUzatimAlani").removeClass("d-none")
    .attr("data-sozlesme-id", sozlesmeId)
    .attr("data-mevcut-bitis", bitisTarihi || "");
  $("#sure_uzatim_tarihi").val(moment().format("DD.MM.YYYY"));
  $("#sure_uzatim_karar_no, #sure_uzatim_gun, #sure_uzatim_aciklama").val("");
  $("#sureUzatimMevcutBitis").text(formatTarih(bitisTarihi));
  const uzatimVar = parseInt(uzatimSayisi || 0, 10) > 0;
  $("#sureUzatimYeniFormu").toggle(!uzatimVar);
  $("#sureUzatimYeniBtn, #sureUzatimFormKapatBtn").addClass("d-none");
  sureUzatimHesapla();
  sureUzatimGecmisiniYukle(sozlesmeId);
}

function sureUzatimHesapla() {
  const mevcut = $("#sureUzatimAlani").attr("data-mevcut-bitis");
  const gun = parseInt($("#sure_uzatim_gun").val(), 10) || 0;
  if (!mevcut || gun <= 0) {
    $("#sureUzatimYeniBitis").text("-");
    return;
  }
  $("#sureUzatimYeniBitis").text(moment(mevcut, "YYYY-MM-DD").add(gun, "days").format("DD.MM.YYYY"));
}

function sureUzatimKaydet() {
  const sozlesmeId = parseInt($("#sureUzatimAlani").attr("data-sozlesme-id"), 10);
  const gun = parseInt($("#sure_uzatim_gun").val(), 10) || 0;
  if (!$("#sure_uzatim_tarihi").val() || gun <= 0) {
    Swal.fire("Uyarı", "Onay tarihi ve uzatım gününü giriniz.", "warning");
    return;
  }
  Swal.fire({
    title: "Süre uzatımı kaydedilsin mi?",
    text: `Sözleşme bitiş tarihi ${gun} gün uzatılacak.`,
    icon: "question", showCancelButton: true,
    confirmButtonText: "Evet, Kaydet", cancelButtonText: "Vazgeç"
  }).then(function (result) {
    if (!result.isConfirmed) return;
    $("#sureUzatimKaydetBtn").prop("disabled", true);
    $.post("views/hakedisler/online-api.php", {
      type: "saveSureUzatimi", sozlesme_id: sozlesmeId,
      uzatim_tarihi: $("#sure_uzatim_tarihi").val(),
      karar_no: $("#sure_uzatim_karar_no").val(),
      uzatim_gun: gun, aciklama: $("#sure_uzatim_aciklama").val()
    }, function (res) {
      if (res.status !== "success") {
        Swal.fire("Hata", res.message || "Süre uzatımı kaydedilemedi.", "error");
        return;
      }
      Swal.fire("Başarılı", `${res.uzatim_no}. süre uzatımı kaydedildi.`, "success");
      editSozlesme(sozlesmeId);
    }, "json").fail(function () {
      Swal.fire("Hata", "Sunucu bağlantısında sorun oluştu.", "error");
    }).always(function () {
      $("#sureUzatimKaydetBtn").prop("disabled", false);
    });
  });
}

function sureUzatimGecmisiniYukle(sozlesmeId) {
  $("#sureUzatimGecmisi").html('<div class="text-muted">Yükleniyor...</div>');
  $.post("views/hakedisler/online-api.php", {
    type: "getSureUzatimlari", sozlesme_id: sozlesmeId
  }, function (res) {
    if (res.status !== "success" || !res.data.length) {
      $("#sureUzatimYeniFormu").show();
      $("#sureUzatimYeniBtn, #sureUzatimFormKapatBtn").addClass("d-none");
      $("#sureUzatimGecmisi").html('<div class="alert alert-light border">Henüz süre uzatımı bulunmuyor.</div>');
      return;
    }
    $("#sureUzatimYeniFormu").hide();
    $("#sureUzatimYeniBtn").removeClass("d-none");
    $("#sureUzatimFormKapatBtn").addClass("d-none");
    let html = '<div class="accordion" id="sureUzatimAccordion">';
    res.data.forEach(function (u, i) {
      html += `<div class="accordion-item">
        <div class="accordion-header d-flex align-items-stretch">
          <button class="accordion-button ${i ? "collapsed" : ""}" type="button" data-bs-toggle="collapse" data-bs-target="#sure-uzatim-${u.id}">
            <span class="fw-bold me-2">${u.uzatim_no}. Süre Uzatımı</span>
            <span class="text-muted me-2">${formatTarih(u.uzatim_tarihi)}</span>
            ${u.karar_no ? `<span class="badge bg-light text-dark me-2">${escapeHtml(u.karar_no)}</span>` : ""}
            <span class="ms-auto me-3 text-success">+${u.uzatim_gun} gün</span>
          </button>
          <button type="button" class="btn btn-outline-danger rounded-0 px-3" title="Süre uzatımını sil"
            onclick="sureUzatimSil(${u.id}, ${sozlesmeId})"><i class="bx bx-trash"></i></button>
        </div>
        <div id="sure-uzatim-${u.id}" class="accordion-collapse collapse ${i ? "" : "show"}" data-bs-parent="#sureUzatimAccordion">
          <div class="accordion-body">
            ${u.aciklama ? `<p>${escapeHtml(u.aciklama)}</p>` : ""}
            <div class="row"><div class="col-md-6"><small class="text-muted">Önceki Bitiş</small><div class="fw-bold">${formatTarih(u.onceki_bitis_tarihi)}</div></div>
            <div class="col-md-6"><small class="text-muted">Yeni Bitiş</small><div class="fw-bold text-success">${formatTarih(u.yeni_bitis_tarihi)}</div></div></div>
          </div>
        </div></div>`;
    });
    $("#sureUzatimGecmisi").html(html + "</div>");
  }, "json");
}

function yeniSureUzatimFormunuAc() {
  $("#sureUzatimYeniFormu").stop(true, true).slideDown(250);
  $("#sureUzatimYeniBtn").addClass("d-none");
  $("#sureUzatimFormKapatBtn").removeClass("d-none");
  $("#sure_uzatim_tarihi").trigger("focus");
}

function sureUzatimFormunuKapat() {
  $("#sureUzatimYeniFormu").stop(true, true).slideUp(200);
  $("#sureUzatimYeniBtn").removeClass("d-none");
  $("#sureUzatimFormKapatBtn").addClass("d-none");
}

function sureUzatimSil(uzatimId, sozlesmeId) {
  Swal.fire({
    title: "Süre uzatımı silinsin mi?",
    text: "Uzatım günü sözleşme süresi ve bitiş tarihinden geri alınacak.",
    icon: "warning", showCancelButton: true,
    confirmButtonText: "Evet, Sil", cancelButtonText: "Vazgeç", confirmButtonColor: "#dc3545"
  }).then(function (result) {
    if (!result.isConfirmed) return;
    $.post("views/hakedisler/online-api.php", {
      type: "deleteSureUzatimi", uzatim_id: uzatimId, sozlesme_id: sozlesmeId
    }, function (res) {
      if (res.status !== "success") {
        Swal.fire("Hata", res.message || "Süre uzatımı silinemedi.", "error");
        return;
      }
      Swal.fire("Silindi", "Süre uzatımı geri alındı.", "success");
      editSozlesme(sozlesmeId);
    }, "json");
  });
}

function formatTarih(value) {
  return value ? moment(value, "YYYY-MM-DD").format("DD.MM.YYYY") : "-";
}

function escapeHtml(value) {
  return $("<div>").text(value == null ? "" : String(value)).html();
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
