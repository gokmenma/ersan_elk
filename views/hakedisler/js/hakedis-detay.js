$(document).ready(function () {
  // Load existing items/quantities
  loadKalemler();

  // Form submission for Top-Level parameters (Endeksler)
  $("#hakedisParametreForm").on("submit", function (e) {
    e.preventDefault();
    saveParametreler(this);
  });

  // Make table inputs editable on change
  $("#kalemlerBody").on("change", "input.miktar-input", function () {
    const kalemId = $(this).data("kalem-id");
    const miktarValue = $(this).val();
    updateMiktar(kalemId, miktarValue, "miktar");
  });

  $("#kalemlerBody").on("change", "input.onceki-miktar-input", function () {
    const kalemId = $(this).data("kalem-id");
    const miktarValue = $(this).val();
    updateMiktar(kalemId, miktarValue, "onceki_miktar");
  });

  // Auto-save and recalculate when the asgari farkı dahil checkbox is toggled
  $("#asgari_farki_dahil").on("change", function () {
    $("#hakedisParametreForm").submit();
  });
});

function loadKalemler() {
  $.post(
    "views/hakedisler/online-api.php",
    {
      type: "getHakedisKalemler",
      hakedis_id: currentHakedisId,
      sozlesme_id: currentSozlesmeId,
    },
    function (res) {
      if (res.status == "success") {
        let html = "";
        let totalImalat = 0;
        if (res.data.length === 0) {
          html = `<tr><td colspan="10" class="text-center text-muted py-4"><i class="bx bx-info-circle mb-2" style="font-size:30px;"></i><br>Henüz bu sözleşmeye ait kalem eklenmemiş. Lütfen sağ üstteki butondan yeni kalem ekleyin.</td></tr>`;
        } else {
          res.data.forEach((kalem, index) => {
            const oncekiMiktar = parseFloat(kalem.onceki_miktar || 0);
            const buAyMiktar = parseFloat(kalem.bu_ay_miktar || 0);
            const toplamMiktar = oncekiMiktar + buAyMiktar;
            const sozlesmeMiktari = parseFloat(kalem.miktari || 0);
            const yuzde = sozlesmeMiktari > 0 ? (toplamMiktar / sozlesmeMiktari) * 100 : 0;
            const birimFiyat = parseFloat(kalem.teklif_edilen_birim_fiyat || 0);
            const rowTotal = toplamMiktar * birimFiyat;
            const donemTutari = buAyMiktar * birimFiyat;

            totalImalat += rowTotal;

            html += `
                        <tr id="kalem_row_${kalem.id}">
                            <td class="text-center fw-bold">${index + 1}</td>
                            <td class="td-kalem-adi">
                                <span class="badge bg-secondary mb-1 poz-no-badge">${kalem.poz_no || ""}</span>
                                <div class="text-wrap" style="width: 200px;">${kalem.kalem_adi}</div>
                            </td>
                            <td class="text-center td-birim" data-birim="${kalem.birim}" data-miktar="${sozlesmeMiktari}">
                                <span class="fw-bold text-primary">${sozlesmeMiktari.toLocaleString("tr-TR")}</span> / <span class="text-muted">${kalem.birim}</span>
                            </td>
                            <td class="text-end td-fiyat">${birimFiyat.toLocaleString("tr-TR", { minimumFractionDigits: 2 })} ₺</td>
                            <td class="text-center" style="width:100px;">
                                <input type="number" step="0.01" class="form-control form-control-sm onceki-miktar-input text-center px-1" data-kalem-id="${kalem.id}" value="${oncekiMiktar}" placeholder="0">
                            </td>
                            <td class="text-center" style="width:100px;">
                                <input type="number" step="0.01" class="form-control form-control-sm miktar-input text-center px-1" data-kalem-id="${kalem.id}" value="${buAyMiktar}" placeholder="0">
                            </td>
                            <td class="text-center table-warning fw-bold">${toplamMiktar.toLocaleString("tr-TR")}</td>
                            <td class="text-center" style="width: 100px;">
                                <div class="fw-bold mb-1 ${yuzde > 100 ? 'text-danger' : 'text-primary'}" style="font-size: 11px;">%${yuzde.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                                <div class="progress progress-sm" style="height: 6px; background-color: #e9ecef;">
                                    <div class="progress-bar ${yuzde > 100 ? 'bg-danger' : 'bg-success'}" role="progressbar" style="width: ${Math.min(yuzde, 100)}%" aria-valuenow="${yuzde}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td class="text-end fw-bold text-success">${donemTutari.toLocaleString("tr-TR", { minimumFractionDigits: 2 })} ₺</td>
                            <td class="text-center actions-container">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-sm btn-info" onclick="editKalemRow(this, ${kalem.id})" title="Düzenle"><i class="bx bx-edit"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
          });
        }

        $("#kalemlerBody").html(html);

        // Toplam Gerçekleşme Yüzdesi (Kümülatif İmalat / Sözleşme Bedeli)
        const genelYuzde = sozlesmeBedeli > 0 ? (totalImalat / sozlesmeBedeli) * 100 : 0;
        $("#toplamGerceklesmeYuzdesi").text("%" + genelYuzde.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        if (genelYuzde > 100) {
            $("#toplamGerceklesmeYuzdesi").removeClass("bg-soft-success text-success").addClass("bg-soft-danger text-danger");
        } else {
            $("#toplamGerceklesmeYuzdesi").removeClass("bg-soft-danger text-danger").addClass("bg-soft-success text-success");
        }

        $("#toplamImalatTutar").text(
          totalImalat.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) +
            " ₺",
        );

        // Fiyat farkı calculation (Formula mockup from excel logic)
        if (typeof res.fiyat_farki !== "undefined") {
          const ffValue = parseFloat(res.fiyat_farki);

          $("#hesaplananFiyatFarki").text(
            parseFloat(ffValue).toLocaleString("tr-TR", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            }) + " ₺"
          );

          // Bu ayın toplamı (İmalat + Fiyat Farkı)
          const donemImalat = parseFloat(res.imalat_donem || 0);
          const donemAraToplam = donemImalat + ffValue;
          const kdvRate = parseFloat(res.kdv_orani || 20);
          const donemKdvDahil = donemAraToplam * (1 + kdvRate / 100);

          $("#toplamImalatTutar").text(
            donemImalat.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺"
          );

          $("#fiyatFarkiDahilToplam").text(
            donemAraToplam.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ₺"
          );

          $("#kdvDahilToplam").text(
            donemKdvDahil.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2, }) + " ₺"
          );
        }
      } else {
        $("#kalemlerBody").html(
          `<tr><td colspan="10" class="text-center text-danger">${res.message}</td></tr>`,
        );
      }
    },
    "json",
  );
}

function saveParametreler(form) {
  const data = $(form).serializeArray();
  data.push({ name: "type", value: "updateHakedisParametreler" });

  $.post(
    "views/hakedisler/online-api.php",
    data,
    function (res) {
      if (res.status === "success") {
        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 3000,
          icon: "success",
          title: "Hakediş endeks ve katsayıları güncellendi.",
        });
        loadKalemler(); // Recalculate everything
      } else {
        Swal.fire("Hata", res.message, "error");
      }
    },
    "json",
  );
}

function editKalemRow(btn, id) {
  const tr = $(btn).closest("tr");
  const kalemAdi = tr.find(".td-kalem-adi .text-wrap").text().trim();
  const pozNo = tr.find(".td-kalem-adi .poz-no-badge").text().trim();
  const birim = tr.find(".td-birim").data("birim");
  const miktar = tr.find(".td-birim").data("miktar");
  const rawFiyat = tr
    .find(".td-fiyat")
    .text()
    .replace(/[^0-9,-]+/g, "")
    .replace(",", ".");
  const floatFiyat = parseFloat(rawFiyat) || 0;

  let unitOptions = [
    "Adet",
    "Metre",
    "Km",
    "Gün",
    "Ay",
    "Saat",
    "Ton",
    "Litre",
  ];
  let selectHtml = `<select class="form-select form-select-sm edit-birim mt-1">`;
  unitOptions.forEach((op) => {
    let sel = op === birim ? "selected" : "";
    selectHtml += `<option value="${op}" ${sel}>${op}</option>`;
  });
  selectHtml += `</select>`;

  tr.find(".td-kalem-adi").html(
    `<input type="text" class="form-control form-control-sm edit-poz-no mb-1" value="${pozNo}" placeholder="Poz No">
     <input type="text" class="form-control form-control-sm edit-kalem-adi" value="${kalemAdi}">`
  );
  tr.find(".td-birim").html(
    `<input type="number" step="0.01" class="form-control form-control-sm edit-miktar" value="${miktar}" placeholder="Miktar">
     ${selectHtml}`
  );
  tr.find(".td-fiyat").html(
    `<input type="number" step="0.01" class="form-control form-control-sm text-end edit-fiyat" value="${floatFiyat}">`,
  );

  tr.find(".actions-container").html(`
      <div class="d-flex gap-1 justify-content-center">
          <button class="btn btn-sm btn-success" onclick="saveEditedKalem(this, ${id})" title="Kaydet"><i class="bx bx-check"></i></button>
          <button class="btn btn-sm btn-secondary" onclick="loadKalemler()" title="İptal"><i class="bx bx-x"></i></button>
      </div>
  `);
}

function saveEditedKalem(btn, id) {
  const tr = $(btn).closest("tr");
  const kalemAdi = tr.find(".edit-kalem-adi").val();
  const pozNo = tr.find(".edit-poz-no").val();
  const birim = tr.find(".edit-birim").val();
  const miktar = tr.find(".edit-miktar").val();
  const teklifFiyat = tr.find(".edit-fiyat").val();

  if (!kalemAdi || !teklifFiyat) {
    Swal.fire("Uyarı", "Kalem Adı ve Birim Fiyat zorunludur.", "warning");
    return;
  }

  $.post(
    "views/hakedisler/online-api.php",
    {
      type: "updateKalem",
      kalem_id: id,
      poz_no: pozNo,
      kalem_adi: kalemAdi,
      birim: birim,
      miktari: miktar,
      teklif_edilen_birim_fiyat: teklifFiyat,
    },
    function (res) {
      if (res.status == "success") {
        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 3000,
          icon: "success",
          title: "Kalem güncellendi",
        });
        loadKalemler();
      } else {
        Swal.fire("Hata", res.message, "error");
      }
    },
    "json",
  );
}

function updateMiktar(kalemId, miktar, field = "miktar") {
  const data = {
    type: "updateMiktar",
    hakedis_id: currentHakedisId,
    kalem_id: kalemId,
  };
  data[field] = miktar;

  // Save directly to db on change
  $.post(
    "views/hakedisler/online-api.php",
    data,
    function (res) {
      if (res.status == "success") {
        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 1500,
          icon: "success",
          title: "Miktar kaydedildi.",
        });
        loadKalemler(); // refresh sums
      } else {
        Swal.fire("Hata", "Miktar güncellenemedi: " + res.message, "error");
      }
    },
    "json",
  );
}

function exportHakedisToExcel(id) {
  Swal.fire({
    title: 'Excel Hazırlanıyor',
    text: 'Dosya oluşturuluyor, lütfen bekleyin...',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  // Start download
  window.location.href = "views/hakedisler/export-excel.php?id=" + id;

  // Close loader after a few seconds (browser starts download usually within this time)
  setTimeout(() => {
    Swal.close();
  }, 3000);
}

function uploadHakedisTemplate(input) {
    if (!input.files || !input.files[0]) return;
    
    let file = input.files[0];
    let fileName = file.name;
    
    // Check extension
    if (!fileName.toLowerCase().endsWith('.xlsx')) {
        Swal.fire('Hata', 'Lütfen sadece .xlsx uzantılı Excel dosyası yükleyin.', 'error');
        $(input).val('');
        return;
    }

    Swal.fire({
        title: 'Şablon Yükleniyor',
        text: 'Lütfen bekleyin...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let formData = new FormData();
    formData.append('type', 'uploadHakedisTemplate');
    formData.append('templateFile', file);

    $.ajax({
        url: 'views/hakedisler/online-api.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire('Başarılı', 'Excel şablon dosyası başarıyla güncellendi.', 'success');
            } else {
                Swal.fire('Hata', res.message || 'Dosya yüklenirken bir hata oluştu.', 'error');
            }
            $(input).val('');
        },
        error: function() {
            Swal.fire('Hata', 'Sunucu ile iletişim kurulamadı.', 'error');
            $(input).val('');
        }
    });
}

function addEndeksRow(containerId, type) {
  let bgClass =
    type === "temel"
      ? "bg-light text-muted"
      : "bg-soft-warning text-warning border-warning";
  let borderClass = type === "temel" ? "" : "border-warning";

  const html = `
    <div class="input-group mt-1 ek-param-row ${type === "guncel" ? "border-warning" : ""}">
        <input type="text" class="form-control ${bgClass} fw-bold" style="max-width: 120px;" placeholder="Adı..." onkeyup="$(this).next('input[type=number]').attr('name', 'ekstra_${type}[' + this.value.trim() + ']')">
        <input type="number" step="any" class="form-control ${borderClass}" placeholder="Değer giriniz...">
        <button type="button" class="btn btn-outline-danger btn-sm ${borderClass}" onclick="$(this).closest('.input-group').remove()"><i class="bx bx-trash"></i></button>
    </div>
  `;

  $(`#${containerId}`).append(html);
  $(`#${containerId} .ek-param-row`).last().find('input[type="text"]').focus();
}

function fetchGuncelEndeksler() {
    Swal.fire({
        title: 'Veriler Çekiliyor...',
        text: 'TÜİK ve EPDK sayfalarından güncel endeksler alınıyor, lütfen bekleyin.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.post("views/hakedisler/online-api.php", {
        type: "fetchEndeksForHakedis",
        hakedis_tarihi_ay: currentHakedisAy,
        hakedis_tarihi_yil: currentHakedisYil
    }, function (res) {
        if (res.status === "success") {
            const d = res.data;
            let updated = false;

            if (d.asgari_ucret_guncel !== null) {
                $('input[name="asgari_ucret_guncel"]').val(d.asgari_ucret_guncel);
                updated = true;
            }
            if (d.motorin_guncel !== null) {
                $('input[name="motorin_guncel"]').val(d.motorin_guncel);
                updated = true;
            }
            if (d.ufe_genel_guncel !== null) {
                $('input[name="ufe_genel_guncel"]').val(d.ufe_genel_guncel);
                updated = true;
            }
            if (d.makine_ekipman_guncel !== null) {
                $('input[name="makine_ekipman_guncel"]').val(d.makine_ekipman_guncel);
                updated = true;
            }

            if (updated) {
                if (d.message) {
                    Swal.fire('Kısmi Veri', 'Veriler çekildi ancak bazı endeksler bu ay için henüz açıklanmamış. <br><br><small>' + d.message + '</small>', 'info');
                } else {
                    Swal.fire('Başarılı', 'Güncel endeks verileri başarıyla çekildi. Değişiklikleri kaydetmeyi unutmayın.', 'success');
                }
            } else {
                Swal.fire('Bilgi', 'Bu ay için henüz hiçbir endeks verisi açıklanmamış. Lütfen kurumların verileri açıklamasını bekleyip daha sonra tekrar deneyin.', 'warning');
            }
        } else {
            Swal.fire("Hata", res.message || "Veriler çekilirken bir hata oluştu.", "error");
        }
    }, "json").fail(function() {
        Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
    });
}
