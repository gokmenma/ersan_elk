$(document).ready(function () {
  // Load existing items/quantities
  loadKalemler();

  // Form submission for Top-Level parameters (Endeksler)
  $("#hakedisParametreForm").on("submit", function (e) {
    e.preventDefault();
    saveParametreler(this);
  });

  // Make table inputs editable on blur or enter key
  $("#kalemlerBody").on("change", "input.miktar-input", function () {
    const kalemId = $(this).data("kalem-id");
    const miktarValue = $(this).val();
    updateMiktar(kalemId, miktarValue);
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
          html = `<tr><td colspan="9" class="text-center text-muted py-4"><i class="bx bx-info-circle mb-2" style="font-size:30px;"></i><br>Henüz bu sözleşmeye ait kalem eklenmemiş. Lütfen sağ üstteki butondan yeni kalem ekleyin.</td></tr>`;
        } else {
          res.data.forEach((kalem, index) => {
            const oncekiMiktar = parseFloat(kalem.onceki_miktar || 0);
            const buAyMiktar = parseFloat(kalem.bu_ay_miktar || 0);
            const toplamMiktar = oncekiMiktar + buAyMiktar;
            const birimFiyat = parseFloat(kalem.teklif_edilen_birim_fiyat || 0);
            const rowTotal = toplamMiktar * birimFiyat;

            totalImalat += rowTotal;

            html += `
                        <tr id="kalem_row_${kalem.id}">
                            <td class="text-center fw-bold">${index + 1}</td>
                            <td><div class="text-wrap" style="width: 250px;">${kalem.kalem_adi}</div></td>
                            <td>${kalem.birim}</td>
                            <td class="text-end">${birimFiyat.toLocaleString("tr-TR", { minimumFractionDigits: 2 })} ₺</td>
                            <td class="text-center" title="Geçmiş hakedişlerden gelen toplan">${oncekiMiktar.toLocaleString("tr-TR")}</td>
                            <td style="width:120px;">
                                <input type="number" step="0.01" class="form-control form-control-sm miktar-input" data-kalem-id="${kalem.id}" value="${buAyMiktar}" placeholder="0">
                            </td>
                            <td class="text-center table-warning fw-bold">${toplamMiktar.toLocaleString("tr-TR")}</td>
                            <td class="text-end fw-bold text-success">${rowTotal.toLocaleString("tr-TR", { minimumFractionDigits: 2 })} ₺</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteKalem(${kalem.id})" title="Kaldır"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>
                    `;
          });
        }

        $("#kalemlerBody").html(html);
        $("#toplamImalatTutar").text(
          totalImalat.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) +
            " ₺",
        );

        // Fiyat farkı calculation (Formula mockup from excel logic)
        // (Pn - 1) * RowTotal vs -> Need backend calculation response to be precise.
        // For now, doing a basic update from backend if we fetch stats:
        if (res.fiyat_farki) {
          $("#hesaplananFiyatFarki").text(
            res.fiyat_farki.toLocaleString("tr-TR", {
              minimumFractionDigits: 2,
            }) + " ₺",
          );
          let genel = totalImalat + res.fiyat_farki;
          // Assuming kdv comes from db or form, here a quick mock:
          const kdvRate = parseFloat($('input[name="kdv_orani"]').val() || 20);
          genel = genel + (genel * kdvRate) / 100;
          $("#kdvDahilToplam").text(
            genel.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺",
          );
        }
      } else {
        $("#kalemlerBody").html(
          `<tr><td colspan="9" class="text-center text-danger">${res.message}</td></tr>`,
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

function saveInlineKalem(btn) {
  const tr = $(btn).closest("tr");
  const kalemAdi = tr.find("#inline_kalem_adi").val();
  const birim = tr.find("#inline_birim").val();
  const teklifFiyat = tr.find("#inline_teklif_fiyat").val();

  if (!kalemAdi || !teklifFiyat) {
    Swal.fire("Uyarı", "Kalem Adı ve Birim Fiyat zorunludur.", "warning");
    return;
  }

  $.post(
    "views/hakedisler/online-api.php",
    {
      type: "saveKalem",
      sozlesme_id: currentSozlesmeId,
      kalem_adi: kalemAdi,
      birim: birim,
      teklif_edilen_birim_fiyat: teklifFiyat,
      hedef_miktari: 0,
    },
    function (res) {
      if (res.status == "success") {
        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 3000,
          icon: "success",
          title: "Yeni kalem satırı eklendi",
        });
        loadKalemler();
      } else {
        Swal.fire("Hata", res.message, "error");
      }
    },
    "json",
  );
}

function addNewKalemRow() {
  if ($("#yeni_kalem_satiri").length > 0) return; // Zaten açıksa ekleme

  // Eğer listede hiç eleman yoksa ve uyarı mesajı varsa onu temizle
  if (
    $("#kalemlerBody tr td").length === 1 &&
    $("#kalemlerBody tr td").hasClass("text-muted")
  ) {
    $("#kalemlerBody").empty();
  }

  const tr = `
    <tr id="yeni_kalem_satiri">
        <td class="text-center fw-bold text-muted">+</td>
        <td><input type="text" class="form-control form-control-sm" id="inline_kalem_adi" placeholder="Örn: Sayaç Sökme"></td>
        <td>
            <select class="form-select form-select-sm" id="inline_birim">
                <option value="Adet">Adet</option>
                <option value="Metre">Metre</option>
                <option value="Km">Km</option>
                <option value="Gün">Gün</option>
                <option value="Ay">Ay</option>
                <option value="Saat">Saat</option>
                <option value="Ton">Ton</option>
                <option value="Litre">Litre</option>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm text-end" id="inline_teklif_fiyat" placeholder="0"></td>
        <td class="text-center text-muted">-</td>
        <td class="text-center text-muted">-</td>
        <td class="text-center text-muted">-</td>
        <td class="text-center text-muted">-</td>
        <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
                <button class="btn btn-sm btn-success" onclick="saveInlineKalem(this)" title="Kaydet"><i class="bx bx-check"></i></button>
                <button class="btn btn-sm btn-secondary" onclick="$(this).closest('tr').remove()" title="İptal"><i class="bx bx-x"></i></button>
            </div>
        </td>
    </tr>
  `;
  $("#kalemlerBody").append(tr);
  $("#inline_kalem_adi").focus();
}

function deleteKalem(id) {
  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu kalem (ve girilmişse bu aydaki miktarları) silinecektir.",
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
        {
          type: "deleteKalem",
          id: id,
        },
        function (res) {
          if (res.status == "success") {
            Swal.fire("Silindi!", "Kalem başarıyla silindi.", "success");
            loadKalemler();
          } else {
            Swal.fire("Hata", res.message, "error");
          }
        },
        "json",
      );
    }
  });
}

function updateMiktar(kalemId, miktar) {
  // Save directly to db on change
  $.post(
    "views/hakedisler/online-api.php",
    {
      type: "updateMiktar",
      hakedis_id: currentHakedisId,
      kalem_id: kalemId,
      miktar: miktar,
    },
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
    title: "Bilgi",
    text: "Orijinal Excel şablonuna yazdırma özelliği bir sonraki aşamada (PhpSpreadsheet entegrasyonu ile) devreye alınacaktır.",
    icon: "info",
  });
}
