/**
 * Araç Takip Modülü - JavaScript
 * Araç CRUD, Zimmet, Yakıt ve KM işlemleri
 */

const AracTakip = {
  apiUrl: "views/arac-takip/api.php",

  // =============================================
  // YARDIMCI FONKSİYONLAR
  // =============================================
  formatMoney: function (amount) {
    return new Intl.NumberFormat("tr-TR", {
      style: "currency",
      currency: "TRY",
      minimumFractionDigits: 2,
    }).format(amount || 0);
  },

  formatNumber: function (num) {
    return new Intl.NumberFormat("tr-TR").format(num || 0);
  },

  formatDate: function (dateStr) {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return date.toLocaleDateString("tr-TR");
  },

  showLoading: function (selector) {
    const table = $(selector).closest("table");
    const colCount = table.find("thead tr:first th").length || 8;
    let tds = "";
    for (let i = 0; i < colCount; i++) {
      if (i === 0)
        tds += `<td class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></td>`;
      else if (i === 1) tds += `<td class="text-muted py-4">Yükleniyor...</td>`;
      else tds += `<td></td>`;
    }
    $(selector).html(`<tr>${tds}</tr>`);
  },

  initDataTable: function (selector) {
    if ($.fn.DataTable) {
      // Önce mevcut tabloyu yok et (varsa)
      if ($.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().clear().destroy();
      }
      // Tüm ekstra header satırlarını temizle (DataTable eklentileri tarafından eklenmiş olabilir)
      $(selector).find("thead tr:not(:first)").remove();
      // data-filter sütunlarının header sınıflarını temizle (gelişmiş filtreleme için)
      $(selector).find("thead th").removeClass("dt-header-with-filter");

      let options = {};
      if (typeof getDatatableOptions === "function") {
        options = getDatatableOptions();
      } else {
        options = {
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json",
          },
          pageLength: 10,
        };
      }

      // Sadece araç tablosu için sayfa uzunluğunu hatırla
      if (selector === "#aracTable") {
        options = applyLengthStateSave(options);
      } else {
        options.stateSave = false;
      }

      // Excel butonu için DOM düzenlemesi (B ekle)
      if (options.dom && options.dom.indexOf("B") === -1) {
        options.dom = "B" + options.dom;
      } else if (!options.dom) {
        options.dom = "Bfrtip";
      }

      // Excel butonu konfigürasyonu
      options.buttons = [
        {
          extend: "excelHtml5",
          className: "d-none", // Butonu gizle
          text: "Excel",
          exportOptions: {
            columns: ":visible:not(:last-child)", // Son sütun (İşlemler) hariç
          },
        },
      ];

      $(selector).DataTable(options);
    }
  },

  recalculatePuantajTable: function () {
    const table = $("#puantajTable");
    if (!table.length) return;

    let grandTotal = 0;
    const dayTotals = {
      yapilan: {},
      baslangic: {},
      bitis: {},
    };

    // Satır bazlı (Araç bazlı) toplamları hesapla
    table.find("tbody tr").each(function () {
      const tr = $(this);
      const aracId = tr.find("td[data-arac-id]").first().data("arac-id");
      if (!aracId) return;

      let rowTotal = 0;

      // Her günü işle
      tr.find("td[data-day]").each(function () {
        const cell = $(this);
        const day = cell.data("day");
        const type = cell.data("type");
        const val = parseInt(cell.text().replace(/\./g, "")) || 0;

        if (type === "yapilan") {
          rowTotal += val;
          dayTotals.yapilan[day] = (dayTotals.yapilan[day] || 0) + val;
        } else if (type === "baslangic") {
          dayTotals.baslangic[day] = (dayTotals.baslangic[day] || 0) + val;
        } else if (type === "bitis") {
          dayTotals.bitis[day] = (dayTotals.bitis[day] || 0) + val;
        }
      });

      // Satır toplamını yaz
      tr.find('td[data-type="row-total"]').text(
        AracTakip.formatNumber(rowTotal),
      );
      grandTotal += rowTotal;
    });

    // Günlük (Sütun bazlı) toplamları güncelle
    Object.keys(dayTotals.yapilan).forEach((day) => {
      table
        .find(`tfoot td[data-day="${day}"][data-type="col-yapilan"]`)
        .text(AracTakip.formatNumber(dayTotals.yapilan[day]) || "-");
      table
        .find(`tfoot td[data-day="${day}"][data-type="col-baslangic"]`)
        .text(AracTakip.formatNumber(dayTotals.baslangic[day]) || "-");
      table
        .find(`tfoot td[data-day="${day}"][data-type="col-bitis"]`)
        .text(AracTakip.formatNumber(dayTotals.bitis[day]) || "-");
    });

    // Genel toplamı güncelle
    $("#puantajGrandTotal").text(AracTakip.formatNumber(grandTotal));
  },

  kmKaydetChain: function (startRow) {
    const nextRows = startRow.nextAll(
      'tr.km-quick-row[data-needs-update="true"]',
    );
    if (nextRows.length > 0) {
      this.kmKaydetSequential(nextRows.toArray());
    }
  },

  kmKaydetSequential: function (rows) {
    if (rows.length === 0) return;

    const tr = $(rows.shift());
    const aracId = tr.data("arac-id");
    const date = tr.data("date");
    const kmId = tr.data("id");
    const baslangic = tr
      .find('.km-editable[data-type="baslangic"]')
      .text()
      .trim()
      .replace(/\D/g, "");
    const bitis = tr
      .find('.km-editable[data-type="bitis"]')
      .text()
      .trim()
      .replace(/\D/g, "");

    const bVal = parseInt(baslangic) || 0;
    const eVal = parseInt(bitis) || 0;

    tr.removeAttr("data-needs-update");
    tr.find('.km-editable[data-type="baslangic"]').removeClass("text-info");

    // Sadece mevcut kaydı olan satırları güncelle (yeni boş kayıt yaratma)
    if (!kmId && eVal === 0) {
      // Kayıt yok ve bitiş de girilmemiş - sadece görsel güncelleme, atla
      this.kmKaydetSequential(rows);
      return;
    }

    $.post(
      AracTakip.apiUrl,
      {
        action: "km-kaydet-inline",
        id: kmId || "",
        arac_id: aracId,
        tarih: date,
        baslangic_km: bVal,
        bitis_km: eVal,
      },
      (response) => {
        if (response.status === "success") {
          if (response.id)
            tr.attr("data-id", response.id).data("id", response.id);
          // Sıradakini kaydet
          this.kmKaydetSequential(rows);
        }
      },
    );
  },

  recalculateModalChain: function (startRow) {
    const tbody = startRow.closest("tbody");
    let lastBitis = 0;

    // Başlangıç değerini bul: Ya bu satırın başlangıcı ya da bir önceki satırın bitişi
    const prevTr = startRow.prev("tr.km-quick-row");
    if (prevTr.length) {
      lastBitis =
        parseInt(
          prevTr
            .find('.km-editable[data-type="bitis"]')
            .text()
            .replace(/\D/g, ""),
        ) || 0;
      if (lastBitis === 0) {
        lastBitis =
          parseInt(
            prevTr
              .find('.km-editable[data-type="baslangic"]')
              .text()
              .replace(/\D/g, ""),
          ) || 0;
      }
    }

    // Seçilen satırdan sonuna kadar tüm günleri tara
    let rowsToProcess = startRow.nextAll("tr.km-quick-row").addBack();

    rowsToProcess.each(function () {
      const tr = $(this);
      const baslangicTd = tr.find('.km-editable[data-type="baslangic"]');
      const bitisTd = tr.find('.km-editable[data-type="bitis"]');
      const yapilanTd = tr.find(".yapilan-km");

      let currentBaslangic =
        parseInt(baslangicTd.text().replace(/\D/g, "")) || 0;
      let currentBitis = parseInt(bitisTd.text().replace(/\D/g, "")) || 0;

      // Eğer bu satırın başlangıcı boşsa veya bir önceki bitişten farklıysa
      if (lastBitis > 0) {
        if (!tr.is(startRow)) {
          const oldBaslangic = baslangicTd.text().trim().replace(/\D/g, "");
          if (oldBaslangic != lastBitis) {
            baslangicTd
              .text(AracTakip.formatNumber(lastBitis))
              .addClass("text-info");
            tr.attr("data-needs-update", "true");
            currentBaslangic = lastBitis;
          }
        }
      }

      // Arka plan tablosunu (arka plandakini) anlık güncelle
      const aracEncrypt = tr.data("arac-encrypt");
      const day = tr.data("day");
      if (aracEncrypt && day) {
        $(
          `#puantajTable td[data-arac-id="${aracEncrypt}"][data-day="${day}"][data-type="baslangic"]`,
        ).text(baslangicTd.text());
        $(
          `#puantajTable td[data-arac-id="${aracEncrypt}"][data-day="${day}"][data-type="bitis"]`,
        ).text(bitisTd.text());
        // Yapılan KM hesaplanacak ve aşağıda basılacak
      }

      const yapilan =
        currentBitis > 0
          ? currentBitis >= currentBaslangic
            ? currentBitis - currentBaslangic
            : "Hata"
          : 0;

      const formattedYapilan =
        yapilan === "Hata"
          ? "Hata"
          : yapilan > 0
            ? AracTakip.formatNumber(yapilan)
            : "-";

      if (yapilan === "Hata") {
        yapilanTd
          .text("Hata")
          .addClass("text-danger")
          .removeClass("text-primary");
      } else if (yapilan > 0) {
        yapilanTd
          .text(formattedYapilan)
          .addClass("text-primary")
          .removeClass("text-danger");
      } else {
        yapilanTd.text("-").removeClass("text-primary text-danger");
      }

      if (aracEncrypt && day) {
        $(
          `#puantajTable td[data-arac-id="${aracEncrypt}"][data-day="${day}"][data-type="yapilan"]`,
        )
          .text(formattedYapilan)
          .toggleClass("text-primary", yapilan > 0)
          .toggleClass("text-danger", yapilan === "Hata");
      }

      // Bir sonraki satır için bu satırın bitişini ayarla
      if (currentBitis > 0) {
        lastBitis = currentBitis;
      } else {
        // Eğer bitiş yoksa, başlangıç bir sonraki gün için referans olabilir
        lastBitis = currentBaslangic;
      }
    });

    // Genel toplamı güncelle
    let genelToplam = 0;
    tbody.find(".yapilan-km").each(function () {
      const val = parseInt($(this).text().replace(/\./g, "")) || 0;
      genelToplam += val;
    });
    tbody
      .closest("table")
      .find("tfoot .text-primary")
      .text(AracTakip.formatNumber(genelToplam) + " KM");
  },

  // =============================================
  // ARAÇ İŞLEMLERİ
  // =============================================
  aracKaydet: function () {
    const form = $("#aracForm");
    const formData = new FormData(form[0]);
    formData.append("action", "arac-kaydet");

    const btn = $("#btnAracKaydet");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            $("#aracModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  aracDuzenle: function (id) {
    const self = this;
    $.post(this.apiUrl, { action: "arac-detay", id: id }, function (response) {
      if (response.status === "success") {
        const data = response.data;
        $("#aracModal")
          .find(".modal-title")
          .html('<i class="bx bx-edit me-2"></i>Araç Düzenle');

        // Form alanlarını doldur
        Object.keys(data).forEach(function (key) {
          const val = data[key];
          const inputs = $("#aracModal").find('[name="' + key + '"]');

          inputs.each(function () {
            const input = $(this);
            if (input.hasClass("flatpickr") && val) {
              let formattedDate = val;
              // Eğer veri yyyy-mm-dd formatındaysa dd.mm.yyyy'ye çevir
              if (val.includes("-") && !val.includes(".")) {
                const parts = val.split(" ")[0].split("-");
                if (parts.length === 3) {
                  formattedDate = `${parts[2]}.${parts[1]}.${parts[0]}`;
                }
              }

              // Önce input değerini ayarla (mask ve görüntü için)
              input.val(formattedDate);

              // Sonra varsa Flatpickr'ı güncelle
              if (this._flatpickr) {
                this._flatpickr.setDate(formattedDate, false);
              }
            } else if (input.is("select")) {
              input.val(val);
              if (input.hasClass("select2")) {
                input.trigger("change");
              }
            } else {
              input.val(val);
            }
          });
        });

        $("#aracModal").modal("show");
      } else {
        Swal.fire("Hata", response.message, "error");
      }
    });
  },

  aracSil: function (id, plaka) {
    Swal.fire({
      title: "Emin misiniz?",
      html: `<b>${plaka}</b> plakalı aracı silmek istediğinize emin misiniz?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          { action: "arac-sil", id: id },
          function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              }).then(() => location.reload());
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
        );
      }
    });
  },

  // =============================================
  // ZİMMET İŞLEMLERİ
  // =============================================
  zimmetVer: function () {
    const formData = new FormData($("#zimmetForm")[0]);
    formData.append("action", "zimmet-ver");

    const btn = $("#btnZimmetKaydet");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            $("#zimmetModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  zimmetIade: function (zimmetId, plaka) {
    Swal.fire({
      title: "Araç İadesi",
      html: `<b>${plaka}</b> plakalı aracın iadesini yapmak istiyor musunuz?<br><br>
                <input type="number" id="iadeKm" class="form-control mb-2" placeholder="İade KM">
                <textarea id="iadeNot" class="form-control" placeholder="Not (opsiyonel)" rows="2"></textarea>`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#28a745",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "İade Et",
      cancelButtonText: "İptal",
      preConfirm: () => {
        const iadeKm = document.getElementById("iadeKm").value;
        if (!iadeKm || iadeKm <= 0) {
          Swal.showValidationMessage(`Lütfen geçerli bir iade KM giriniz.`);
          return false;
        }
        return {
          iade_km: iadeKm,
          notlar: document.getElementById("iadeNot").value,
        };
      },
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          {
            action: "zimmet-iade",
            zimmet_id: zimmetId,
            iade_km: result.value.iade_km,
            notlar: result.value.notlar,
          },
          function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Başarılı",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              }).then(() => location.reload());
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
        );
      }
    });
  },

  zimmetListesiYukle: function () {
    const self = this;
    if ($.fn.DataTable.isDataTable("#zimmetTable")) {
      $("#zimmetTable").DataTable().clear().destroy();
    }
    const tbody = $("#zimmetTableBody");
    self.showLoading(tbody);

    $.post(this.apiUrl, { action: "zimmet-listesi" }, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data && response.data.length > 0) {
          response.data.forEach(function (z, index) {
            const durumBadge =
              z.durum === "aktif"
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">İade Edildi</span>';

            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${z.plaka}</strong><br><small class="text-muted">${z.marka || ""} ${z.model || ""}</small></td>
                            <td>${z.personel_adi}</td>
                            <td>${self.formatDate(z.zimmet_tarihi)}</td>
                            <td>${z.iade_tarihi ? self.formatDate(z.iade_tarihi) : "-"}</td>
                            <td class="text-center">${durumBadge}</td>
                            <td class="text-center">
                                ${z.durum === "aktif" ? `<button class="btn btn-sm btn-warning zimmet-iade" data-id="${z.id}" data-plaka="${z.plaka}" title="İade Al"><i class="bx bx-undo"></i></button>` : ""}
                            </td>
                        </tr>`;
          });
        }
        tbody.html(html);
        self.initDataTable("#zimmetTable");
      } else {
        const colCount =
          $("#zimmetTable").find("thead tr:first th").length || 7;
        let tds = `<td>-</td><td>${response.message || "Veri yükleniyor..."}</td>`;
        for (let i = 2; i < colCount; i++) tds += "<td></td>";
        tbody.html(`<tr>${tds}</tr>`);
      }
    }).fail(function (xhr) {
      const colCount = $("#zimmetTable").find("thead tr:first th").length || 7;
      let tds = `<td>-</td><td>Hata: ${xhr.statusText}</td>`;
      for (let i = 2; i < colCount; i++) tds += "<td></td>";
      tbody.html(`<tr>${tds}</tr>`);
    });
  },

  // =============================================
  // YAKIT KAYDI İŞLEMLERİ
  // =============================================
  yakitKaydet: function () {
    const formData = new FormData($("#yakitForm")[0]);
    formData.append("action", "yakit-kaydet");

    const btn = $("#btnYakitKaydet");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            $("#yakitModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  yakitSil: function (id) {
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu yakıt kaydını silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          { action: "yakit-sil", id: id },
          function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              }).then(() => location.reload());
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
        );
      }
    });
  },

  yakitListesiYukle: function (aracId = null, baslangic = null, bitis = null) {
    const self = this;
    if ($.fn.DataTable.isDataTable("#yakitTable")) {
      $("#yakitTable").DataTable().clear().destroy();
    }
    const tbody = $("#yakitTableBody");
    self.showLoading(tbody);

    const data = { action: "yakit-listesi" };
    if (aracId) data.arac_id = aracId;
    if (baslangic) data.baslangic = baslangic;
    if (bitis) data.bitis = bitis;

    $.post(this.apiUrl, data, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data && response.data.length > 0) {
          response.data.forEach(function (y, index) {
            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${y.plaka}</strong></td>
                            <td>${self.formatDate(y.tarih)}</td>
                            <td class="text-end"><a href="arac-puantaj?arac_id=${y.arac_id}" class="text-primary fw-bold" title="Puantajda Görüntüle">${self.formatNumber(y.km)} km</a></td>
                            <td class="text-end">${self.formatNumber(y.yakit_miktari)} L</td>
                            <td class="text-end">${self.formatMoney(y.birim_fiyat)}</td>
                            <td class="text-end">${self.formatMoney(y.toplam_tutar)}</td>
                            <td>${y.istasyon || "-"}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-primary yakit-duzenle" data-id="${y.id}" title="Düzenle"><i class="bx bx-edit"></i></button>
                                    <button class="btn btn-danger yakit-sil" data-id="${y.id}" title="Sil"><i class="bx bx-trash"></i></button>
                                </div>
                            </td>
                        </tr>`;
          });
        }
        tbody.html(html);
        self.initDataTable("#yakitTable");

        // İstatistikleri güncelle
        if (response.stats) {
          $("#yakit-toplam-litre").text(
            self.formatNumber(response.stats.toplam_litre) + " L",
          );
          $("#yakit-toplam-maliyet").text(
            self.formatMoney(response.stats.toplam_tutar),
          );
          $("#yakit-ortalama-fiyat").text(
            self.formatMoney(response.stats.ortalama_birim_fiyat),
          );
          $("#yakit-kayit-sayisi").text(response.stats.toplam_kayit);
        }
      } else {
        const colCount = $("#yakitTable").find("thead tr:first th").length || 9;
        let tds = `<td>-</td><td>${response.message || "Veri yükleniyor..."}</td>`;
        for (let i = 2; i < colCount; i++) tds += "<td></td>";
        tbody.html(`<tr>${tds}</tr>`);
      }
    }).fail(function (xhr) {
      const colCount = $("#yakitTable").find("thead tr:first th").length || 9;
      let tds = `<td>-</td><td>Hata: ${xhr.statusText}</td>`;
      for (let i = 2; i < colCount; i++) tds += "<td></td>";
      tbody.html(`<tr>${tds}</tr>`);
    });
  },

  // =============================================
  // KM KAYDI İŞLEMLERİ
  // =============================================
  kmKaydet: function () {
    const baslangic = parseInt($("#kmForm #baslangic_km").val()) || 0;
    const bitis = parseInt($("#kmForm #bitis_km").val()) || 0;

    if (bitis < baslangic) {
      Swal.fire({
        icon: "warning",
        title: "Hata",
        text: "Bitiş KM, başlangıç KM'den küçük olamaz.",
      });
      return;
    }

    const formData = new FormData($("#kmForm")[0]);
    formData.append("action", "km-kaydet");

    const btn = $("#btnKmKaydet");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            $("#kmModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  kmSil: function (id) {
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu KM kaydını silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(this.apiUrl, { action: "km-sil", id: id }, function (response) {
          if (response.status === "success") {
            Swal.fire({
              icon: "success",
              title: "Silindi",
              text: response.message,
              timer: 1500,
              showConfirmButton: false,
            }).then(() => location.reload());
          } else {
            Swal.fire("Hata", response.message, "error");
          }
        });
      }
    });
  },

  kmListesiYukle: function (aracId = null, baslangic = null, bitis = null) {
    const self = this;
    if ($.fn.DataTable.isDataTable("#kmTable")) {
      $("#kmTable").DataTable().clear().destroy();
    }
    const tbody = $("#kmTableBody");
    self.showLoading(tbody);

    const data = { action: "km-listesi" };
    if (aracId) data.arac_id = aracId;
    if (baslangic) data.baslangic = baslangic;
    if (bitis) data.bitis = bitis;

    $.post(this.apiUrl, data, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data && response.data.length > 0) {
          response.data.forEach(function (k, index) {
            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${k.plaka}</strong></td>
                            <td>${self.formatDate(k.tarih)}</td>
                            <td class="text-end"><a href="arac-puantaj?arac_id=${k.arac_id}" class="text-primary fw-bold" title="Puantajda Görüntüle">${self.formatNumber(k.baslangic_km)} km</a></td>
                            <td class="text-end"><a href="arac-puantaj?arac_id=${k.arac_id}" class="text-primary fw-bold" title="Puantajda Görüntüle">${self.formatNumber(k.bitis_km)} km</a></td>
                            <td class="text-end">${self.formatNumber(k.yapilan_km)} km</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-primary km-duzenle" data-id="${k.id}" title="Düzenle"><i class="bx bx-edit"></i></button>
                                    <button class="btn btn-danger km-sil" data-id="${k.id}" title="Sil"><i class="bx bx-trash"></i></button>
                                </div>
                            </td>
                        </tr>`;
          });
        }
        tbody.html(html);
        self.initDataTable("#kmTable");

        // İstatistikleri güncelle
        if (response.stats) {
          $("#km-toplam-yol").text(
            self.formatNumber(response.stats.toplam_km) + " km",
          );
          $("#km-ortalama-yol").text(
            self.formatNumber(
              parseFloat(response.stats.ortalama_gunluk_km).toFixed(1),
            ) + " km",
          );
          $("#km-kayit-sayisi").text(response.stats.toplam_kayit);
        }
      } else {
        const colCount = $("#kmTable").find("thead tr:first th").length || 6;
        let tds = `<td>-</td><td>${response.message || "Veri yükleniyor..."}</td>`;
        for (let i = 2; i < colCount; i++) tds += "<td></td>";
        tbody.html(`<tr>${tds}</tr>`);
      }
    }).fail(function (xhr) {
      const colCount = $("#kmTable").find("thead tr:first th").length || 6;
      let tds = `<td>-</td><td>Hata: ${xhr.statusText}</td>`;
      for (let i = 2; i < colCount; i++) tds += "<td></td>";
      tbody.html(`<tr>${tds}</tr>`);
    });
  },

  openKmModalWithData: function (aracId, date) {
    this.resetKmModal();

    // Tarih formatını dd.mm.yyyy -> yyyy-mm-dd dönüştür (eğer nokta içeriyorsa)
    let dbDate = date;
    if (date.includes(".")) {
      const parts = date.split(".");
      if (parts.length === 3) {
        dbDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
      }
    }

    $("#kmModal #arac_id").val(aracId).trigger("change");
    $("#kmModal #tarih").val(dbDate);

    $("#kmModal").modal("show");

    // Focus on bitis_km after modal is shown
    $("#kmModal").one("shown.bs.modal", function () {
      setTimeout(() => {
        $("#kmModal #bitis_km").focus();
      }, 300);
    });
  },

  // =============================================
  // RAPORLAR
  // =============================================
  aylikRaporYukle: function () {
    const self = this;
    const yil = $("#raporYil").val() || new Date().getFullYear();
    const ay = $("#raporAy").val() || new Date().getMonth() + 1;
    const aracId = $("#raporArac").val() || "";

    const container = $("#raporContent");
    self.showLoading(container);

    $.post(
      this.apiUrl,
      {
        action: "aylik-rapor",
        yil: yil,
        ay: ay,
        arac_id: aracId,
      },
      function (response) {
        if (response.status === "success") {
          const data = response.data;
          let html = "";

          // Genel Özet Kartları
          html += `<div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1">${self.formatNumber(data.genel_km?.toplam_km || 0)} km</h4>
                                <small>Toplam KM</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1">${self.formatNumber(data.genel_yakit?.toplam_litre || 0)} L</h4>
                                <small>Toplam Yakıt</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1">${self.formatMoney(data.genel_yakit?.toplam_tutar || 0)}</h4>
                                <small>Toplam Maliyet</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1">${self.formatMoney(data.genel_yakit?.ortalama_birim_fiyat || 0)}</h4>
                                <small>Ort. Birim Fiyat</small>
                            </div>
                        </div>
                    </div>
                </div>`;

          // Araç Bazlı Tablo
          html += `<div class="table-responsive">
                    <table id="raporTable" class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Plaka</th>
                                <th>Marka/Model</th>
                                <th class="text-end">Toplam KM</th>
                                <th class="text-end">Toplam Yakıt (L)</th>
                                <th class="text-end">Toplam Maliyet</th>
                                <th class="text-end">Ort. Tüketim (L/100km)</th>
                            </tr>
                        </thead>
                        <tbody>`;

          if (data.yakit_ozet && data.yakit_ozet.length > 0) {
            data.yakit_ozet.forEach(function (item) {
              html += `<tr>
                            <td><strong>${item.plaka}</strong></td>
                            <td>${item.marka || ""} ${item.model || ""}</td>
                            <td class="text-end">${self.formatNumber(item.toplam_km)} km</td>
                            <td class="text-end">${self.formatNumber(item.toplam_litre)} L</td>
                            <td class="text-end">${self.formatMoney(item.toplam_tutar)}</td>
                            <td class="text-end">${item.ortalama_tuketim || 0} L/100km</td>
                        </tr>`;
            });
          }

          html += "</tbody></table></div>";
          container.html(html);
          self.initDataTable("#raporTable");
        }
      },
    );
  },

  // =============================================
  // EXCEL İŞLEMLERİ
  // =============================================
  yakitExcelYukle: function () {
    const formData = new FormData($("#excelUploadForm")[0]);
    formData.append("action", "yakit-excel-yukle");

    const btn = $("#btnExcelYukle");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          let msg = response.message;
          if (response.errors && response.errors.length > 0) {
            msg += "<br><br><strong>Hatalar:</strong><ul>";
            response.errors.forEach(function (e) {
              msg += "<li>" + e + "</li>";
            });
            msg += "</ul>";
          }
          Swal.fire({
            icon: "success",
            title: "İşlem Tamamlandı",
            html: msg,
            confirmButtonText: "Tamam",
          }).then(() => {
            $("#excelModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  yakitDuzenle: function (id) {
    const self = this;
    $.post(this.apiUrl, { action: "yakit-detay", id: id }, function (response) {
      if (response.status === "success") {
        const data = response.data;
        $("#yakitModal")
          .find(".modal-title")
          .html('<i class="bx bx-edit me-2"></i>Yakıt Kaydı Düzenle');

        // Form alanlarını doldur
        Object.keys(data).forEach(function (key) {
          const input = $("#yakitModal").find('[name="' + key + '"]');
          if (input.length) {
            if (input.hasClass("flatpickr") && data[key]) {
              // Flatpickr tarih formatı
              if (input[0]._flatpickr) {
                input[0]._flatpickr.setDate(data[key]);
              } else {
                input.val(self.formatDate(data[key]));
              }
            } else if (input.is("select")) {
              input.val(data[key]).trigger("change");
            } else {
              input.val(data[key]);
            }
          }
        });

        $("#yakitModal").modal("show");
      } else {
        Swal.fire("Hata", response.message, "error");
      }
    });
  },

  kmDuzenle: function (id) {
    const self = this;
    $.post(this.apiUrl, { action: "km-detay", id: id }, function (response) {
      if (response.status === "success") {
        const data = response.data;
        $("#kmModal")
          .find(".modal-title")
          .html('<i class="bx bx-edit me-2"></i>KM Kaydı Düzenle');

        // Form alanlarını doldur
        Object.keys(data).forEach(function (key) {
          const input = $("#kmModal").find('[name="' + key + '"]');
          if (input.length) {
            if (input.hasClass("flatpickr") && data[key]) {
              // Flatpickr tarih formatı
              if (input[0]._flatpickr) {
                input[0]._flatpickr.setDate(data[key]);
              } else {
                input.val(self.formatDate(data[key]));
              }
            } else if (input.is("select")) {
              input.val(data[key]).trigger("change");
            } else {
              input.val(data[key]);
            }
          }
        });

        $("#kmModal").modal("show");
      } else {
        Swal.fire("Hata", response.message, "error");
      }
    });
  },

  // =============================================
  // ARAÇ EXCEL YÜKLEME
  // =============================================
  aracExcelYukle: function () {
    const formData = new FormData($("#aracExcelUploadForm")[0]);
    formData.append("action", "arac-excel-yukle");

    const btn = $("#btnAracExcelYukle");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          let msg = response.message;
          if (response.errors && response.errors.length > 0) {
            msg += "<br><br><strong>Hatalar:</strong><ul>";
            response.errors.forEach(function (e) {
              msg += "<li>" + e + "</li>";
            });
            msg += "</ul>";
          }
          Swal.fire({
            icon: "success",
            title: "İşlem Tamamlandı",
            html: msg,
            confirmButtonText: "Tamam",
          }).then(() => {
            $("#aracExcelModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  // =============================================
  // SERVİS İŞLEMLERİ
  // =============================================
  servisKaydet: function () {
    const form = $("#servisForm");
    const formData = new FormData(form[0]);
    formData.append("action", "servis-kaydet");

    const btn = $("#btnServisKaydet");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...')
      .prop("disabled", true);

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            $("#servisModal").modal("hide");
            AracTakip.servisListesiYukle();
          });
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
      complete: function () {
        btn.html(originalText).prop("disabled", false);
      },
    });
  },

  servisDuzenle: function (id) {
    const self = this;
    $.post(
      this.apiUrl,
      { action: "servis-detay", id: id },
      function (response) {
        if (response.status === "success") {
          const data = response.data;
          $("#servisModal")
            .find(".modal-title")
            .html('<i class="bx bx-edit me-2"></i>Servis Kaydı Düzenle');

          // Form alanlarını doldur
          Object.keys(data).forEach(function (key) {
            const input = $("#servisModal").find('[name="' + key + '"]');
            if (input.length) {
              if (input.hasClass("flatpickr") && data[key]) {
                const dateVal = data[key];
                if (input[0]._flatpickr) {
                  // Eğer tarih dmY (dd.mm.yyyy) ise parçala, değilse direkt setDate
                  if (dateVal.includes(".")) {
                    const parts = dateVal.split(".");
                    if (parts.length === 3) {
                      const date = new Date(parts[2], parts[1] - 1, parts[0]);
                      input[0]._flatpickr.setDate(date);
                    }
                  } else {
                    input[0]._flatpickr.setDate(dateVal);
                  }
                } else {
                  input.val(dateVal);
                }
              } else if (input.is("select")) {
                input.val(data[key]).trigger("change");
              } else {
                input.val(data[key]);
              }
            }
          });

          $("#servisModal").modal("show");
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
    );
  },

  servisSil: function (id) {
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu servis kaydını silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          { action: "servis-sil", id: id },
          function (response) {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Silindi",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              }).then(() => AracTakip.servisListesiYukle());
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
        );
      }
    });
  },

  servisListesiYukle: function (aracId = null, baslangic = null, bitis = null) {
    const self = this;
    if ($.fn.DataTable.isDataTable("#servisTable")) {
      $("#servisTable").DataTable().clear().destroy();
    }
    const tbody = $("#servisTableBody");
    self.showLoading(tbody);

    const data = { action: "servis-listesi" };
    if (aracId) data.arac_id = aracId;
    if (baslangic) data.baslangic = baslangic;
    if (bitis) data.bitis = bitis;

    $.post(this.apiUrl, data, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data && response.data.length > 0) {
          response.data.forEach(function (s, index) {
            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${s.plaka || "-"}</strong><br><small>${s.marka || ""} ${s.model || ""}</small></td>
                            <td>${self.formatDate(s.servis_tarihi)}</td>
                            <td>${s.iade_tarihi ? self.formatDate(s.iade_tarihi) : "-"}</td>
                            <td class="text-end">${self.formatNumber(s.giris_km)} km</td>
                            <td class="text-end">${self.formatNumber(s.cikis_km)} km</td>
                            <td class="text-truncate" style="max-width: 200px;" title="${s.servis_nedeni}">${s.servis_nedeni || "-"}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-warning servis-duzenle" data-id="${s.id}" title="Düzenle"><i class="bx bx-edit"></i></button>
                                    <button class="btn btn-danger servis-sil" data-id="${s.id}" title="Sil"><i class="bx bx-trash"></i></button>
                                </div>
                            </td>
                        </tr>`;
          });
        } else {
          let tds =
            '<td class="text-center py-4 text-muted">-</td><td class="py-4 text-muted">Kayıt bulunamadı.</td>';
          for (let i = 2; i < 8; i++) tds += "<td></td>";
          html = `<tr>${tds}</tr>`;
        }
        tbody.html(html);
        self.initDataTable("#servisTable");

        // İstatistikleri güncelle
        if (response.stats) {
          $("#servis-toplam-kayit").text(response.stats.toplam_kayit || 0);
          $("#servis-servisteki-arac").text(
            response.stats.servisteki_arac_sayisi || 0,
          );
          $("#badge-servisteki-arac").html(
            `<i class="bx bx-wrench me-1"></i> Servisteki: ${response.stats.servisteki_arac_sayisi || 0}`,
          );
          $("#servis-toplam-maliyet").html(
            `${self.formatMoney(response.stats.toplam_maliyet || 0)}`,
          );
        }
      } else {
        const colCount =
          $("#servisTable").find("thead tr:first th").length || 8;
        let tds = `<td>-</td><td>${response.message || "Veri yükleniyor..."}</td>`;
        for (let i = 2; i < colCount; i++) tds += "<td></td>";
        tbody.html(`<tr>${tds}</tr>`);
      }
    }).fail(function (xhr) {
      const colCount = $("#servisTable").find("thead tr:first th").length || 8;
      let tds = `<td>-</td><td>Hata: ${xhr.statusText}</td>`;
      for (let i = 2; i < colCount; i++) tds += "<td></td>";
      tbody.html(`<tr>${tds}</tr>`);
    });
  },

  // =============================================
  // MODAL RESETLEME
  // =============================================
  resetAracModal: function () {
    $("#aracForm")[0].reset();
    $('#aracForm input[name="id"]').val("");
    $("#aracModal")
      .find(".modal-title")
      .html('<i class="bx bx-car me-2"></i>Yeni Araç Ekle');
  },

  resetZimmetModal: function () {
    $("#zimmetForm")[0].reset();
    $("#zimmetModal #arac_id").val(null).trigger("change");
    $("#zimmetModal #personel_id").val(null).trigger("change");
  },

  resetYakitModal: function () {
    $("#yakitForm")[0].reset();
    $('#yakitForm input[name="id"]').val("");
    $("#yakitModal #arac_id").val(null).trigger("change");
    $("#yakitModal")
      .find(".modal-title")
      .html('<i class="bx bx-gas-pump me-2"></i>Yakıt Kaydı Ekle');
  },

  resetKmModal: function () {
    $("#kmForm")[0].reset();
    $('#kmForm input[name="id"]').val("");
    $("#kmModal #arac_id").val(null).trigger("change");
  },

  resetServisModal: function () {
    $("#servisForm")[0].reset();
    $('#servisForm input[name="id"]').val("");
    $("#servisModal #arac_id").val(null).trigger("change");
    $("#servisModal")
      .find(".modal-title")
      .html('<i class="bx bx-wrench me-2"></i>Yeni Servis Kaydı');
  },
};

// =============================================
// EVENT LISTENERS
// =============================================
$(document).ready(function () {
  // DataTable başlat
  AracTakip.initDataTable("#aracTable");

  // Sekme bazlı UI güncellemeleri
  function updateAracTakipUI() {
    const activeTabBtn = $("#aracTab .nav-link.active");
    if (activeTabBtn.length === 0) return;

    const target =
      activeTabBtn.attr("data-bs-target") || activeTabBtn.attr("href");
    if (!target) return;

    const tabName = target.replace("#", "").replace("Content", "");

    // Excel menüsü görünürlüğü (Sadece yakıt sekmesinde)
    if (tabName === "yakit") {
      $("#liExcelYakitYukle").attr("style", "display: block !important");
    } else {
      $("#liExcelYakitYukle").attr("style", "display: none !important");
    }

    // Yeni Ekle butonu görünürlüğü (Rapor sekmesinde gizle)
    if (tabName === "rapor") {
      $("#btnYeniEkle").attr("style", "display: none !important");
    } else {
      $("#btnYeniEkle").attr("style", "display: block !important");
    }
  }

  // Excele Aktar Butonu
  $(document).on("click", "#btnExceleAktar", function (e) {
    e.preventDefault();
    const activeTab = $(".tab-pane.active");
    const table = activeTab.find("table");
    if (table.length && $.fn.DataTable.isDataTable(table)) {
      table.DataTable().button(".buttons-excel").trigger();
    } else {
      Swal.fire({
        icon: "warning",
        title: "Uyarı",
        text: "Aktif sekmede dışa aktarılacak veri bulunamadı.",
        confirmButtonText: "Tamam",
      });
    }
  });

  // Excel'den Araç Yükle
  $(document).on("click", "#btnAracExcelYukle", (e) => {
    e.preventDefault();
    AracTakip.aracExcelYukle();
  });

  // Yeni Ekle Butonu
  $(document).on("click", "#btnYeniEkle", function (e) {
    e.preventDefault();
    const activeTabBtn = $("#aracTab .nav-link.active");
    const activeTabId = activeTabBtn.attr("id");
    let modalId = "";

    switch (activeTabId) {
      case "arac-tab":
        AracTakip.resetAracModal();
        modalId = "#aracModal";
        break;
      case "zimmet-tab":
        AracTakip.resetZimmetModal();
        modalId = "#zimmetModal";
        break;
      case "yakit-tab":
        AracTakip.resetYakitModal();
        modalId = "#yakitModal";
        break;
      case "km-tab":
        AracTakip.resetKmModal();
        modalId = "#kmModal";
        break;
      case "servis-tab":
        AracTakip.resetServisModal();
        modalId = "#servisModal";
        break;
    }

    if (modalId) {
      $(modalId).modal("show");
    }
  });

  // Modal sıfırlama işlemleri
  $("#aracModal").on("hidden.bs.modal", () => AracTakip.resetAracModal());
  $("#zimmetModal").on("hidden.bs.modal", () => AracTakip.resetZimmetModal());
  $("#yakitModal").on("hidden.bs.modal", () => AracTakip.resetYakitModal());
  $("#kmModal").on("hidden.bs.modal", () => AracTakip.resetKmModal());
  $("#servisModal").on("hidden.bs.modal", () => AracTakip.resetServisModal());

  // Kaydetme ve Silme İşlemleri
  $(document).on("click", "#btnAracKaydet", (e) => {
    e.preventDefault();
    AracTakip.aracKaydet();
  });
  $(document).on("click", ".arac-duzenle", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    if (id) AracTakip.aracDuzenle(id);
  });
  $(document).on("click", ".yakit-duzenle", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    if (id) AracTakip.yakitDuzenle(id);
  });
  $(document).on("click", ".km-duzenle", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    if (id) AracTakip.kmDuzenle(id);
  });
  $(document).on("click", "#btnZimmetKaydet", (e) => {
    e.preventDefault();
    AracTakip.zimmetVer();
  });
  $(document).on("click", ".zimmet-iade", function (e) {
    e.preventDefault();
    AracTakip.zimmetIade($(this).data("id"), $(this).data("plaka"));
  });
  $(document).on("click", ".zimmet-hizli", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const plaka = $(this).data("plaka");
    const km = $(this).data("km");

    AracTakip.resetZimmetModal();
    $("#zimmetModal").modal("show");

    // Modal içindeki select2'nin yüklendiğinden emin olmak için hafif gecikme
    setTimeout(() => {
      $("#zimmetModal #arac_id").val(id).trigger("change");
      if (km) {
        $("#zimmetModal #teslim_km").val(km);
      }
    }, 200);
  });
  $(document).on("click", "#btnYakitKaydet", (e) => {
    e.preventDefault();
    AracTakip.yakitKaydet();
  });
  $(document).on("click", ".yakit-sil", function (e) {
    e.preventDefault();
    AracTakip.yakitSil($(this).data("id"));
  });
  $(document).on("click", "#btnKmKaydet", (e) => {
    e.preventDefault();
    AracTakip.kmKaydet();
  });
  $(document).on("click", ".km-sil", function (e) {
    e.preventDefault();
    AracTakip.kmSil($(this).data("id"));
  });
  $(document).on("click", "#btnServisKaydet", (e) => {
    e.preventDefault();
    AracTakip.servisKaydet();
  });
  $(document).on("click", ".servis-duzenle", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    if (id) AracTakip.servisDuzenle(id);
  });
  $(document).on("click", ".servis-sil", function (e) {
    e.preventDefault();
    AracTakip.servisSil($(this).data("id"));
  });
  $(document).on("click", "#btnExcelYukle", (e) => {
    e.preventDefault();
    AracTakip.yakitExcelYukle();
  });
  $(document).on("click", "#btnRaporYukle", (e) => {
    e.preventDefault();
    AracTakip.aylikRaporYukle();
  });
  $(document).on("click", "#btnYakitFiltrele", (e) => {
    e.preventDefault();
    AracTakip.yakitListesiYukle(
      $("#yakit-filtre-arac").val(),
      $("#yakit-filtre-baslangic").val(),
      $("#yakit-filtre-bitis").val(),
    );
  });
  $(document).on("click", "#btnKmFiltrele", (e) => {
    e.preventDefault();
    AracTakip.kmListesiYukle(
      $("#km-filtre-arac").val(),
      $("#km-filtre-baslangic").val(),
      $("#km-filtre-bitis").val(),
    );
  });
  $(document).on("click", "#btnServisFiltrele", (e) => {
    e.preventDefault();
    AracTakip.servisListesiYukle(
      $("#servis-filtre-arac").val(),
      $("#servis-filtre-baslangic").val(),
      $("#servis-filtre-bitis").val(),
    );
  });

  // KM Inline Düzenleme (Excel-like)
  $(document).on("focus", ".km-editable", function () {
    const td = $(this);
    td.data("old-val", td.text().trim().replace(/\D/g, ""));

    const range = document.createRange();
    range.selectNodeContents(this);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  });

  $(document).on("keydown", ".km-editable", function (e) {
    const currentTd = $(this);
    const currentTr = currentTd.closest("tr");
    const type = currentTd.data("type");

    if (e.key === "Enter" || e.key === "ArrowDown") {
      e.preventDefault();
      const nextTr = currentTr.next("tr");
      if (nextTr.length) {
        nextTr.find(`.km-editable[data-type="${type}"]`).focus();
      } else {
        $(this).blur();
      }
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      const prevTr = currentTr.prev("tr");
      if (prevTr.length) {
        prevTr.find(`.km-editable[data-type="${type}"]`).focus();
      }
    } else if (e.key === "ArrowRight" && type === "baslangic") {
      // Check if cursor is at the end of text? Or just jump? Jump is easier for "excel-like"
      if (window.getSelection().anchorOffset === $(this).text().length) {
        e.preventDefault();
        currentTr.find('.km-editable[data-type="bitis"]').focus();
      }
    } else if (e.key === "ArrowLeft" && type === "bitis") {
      if (window.getSelection().anchorOffset === 0) {
        e.preventDefault();
        currentTr.find('.km-editable[data-type="baslangic"]').focus();
      }
    }
  });

  $(document).on("input", ".km-editable", function () {
    const tr = $(this).closest("tr");
    const table = tr.closest("table");

    // Modal içindeki geniş tabloda ise zincirleme hesapla
    if (table.hasClass("report-table")) {
      AracTakip.recalculateModalChain(tr);
    } else {
      // Ana puantaj tablosundaki basit satır hesaplaması
      const baslangic =
        parseInt(
          tr
            .find('.km-editable[data-type="baslangic"]')
            .text()
            .replace(/\D/g, ""),
        ) || 0;
      const bitis =
        parseInt(
          tr.find('.km-editable[data-type="bitis"]').text().replace(/\D/g, ""),
        ) || 0;
      const yapilan = bitis - baslangic;

      const yapilanTd = tr.find(".yapilan-km");
      if (bitis > 0 && bitis < baslangic) {
        yapilanTd
          .text("Hata")
          .addClass("text-danger")
          .removeClass("text-primary");
      } else if (yapilan >= 0) {
        yapilanTd
          .text(AracTakip.formatNumber(yapilan))
          .addClass("text-primary")
          .removeClass("text-danger");
      } else {
        yapilanTd.text("-").removeClass("text-primary text-danger");
      }
    }
  });

  $(document).on("blur", ".km-editable", function () {
    const td = $(this);
    const tr = td.closest("tr");
    const aracId = tr.data("arac-id");
    const date = tr.data("date");
    const kmId = tr.data("id");
    const currentValRaw = td.text().trim().replace(/\D/g, "");

    // Değişiklik yoksa çık
    if (td.data("old-val") === currentValRaw) return;

    const baslangic = tr
      .find('.km-editable[data-type="baslangic"]')
      .text()
      .trim()
      .replace(/\D/g, "");
    const bitis = tr
      .find('.km-editable[data-type="bitis"]')
      .text()
      .trim()
      .replace(/\D/g, "");

    const bVal = baslangic === "" ? 0 : parseInt(baslangic);
    const eVal = bitis === "" ? 0 : parseInt(bitis);

    if (isNaN(bVal) || isNaN(eVal)) return;

    if (eVal > 0 && eVal < bVal) {
      td.addClass("bg-soft-danger");
      setTimeout(() => td.removeClass("bg-soft-danger"), 2000);
      return;
    }

    // Görsel geri bildirim (Kaydediliyor...)
    td.addClass("bg-soft-warning");

    $.post(
      AracTakip.apiUrl,
      {
        action: "km-kaydet-inline",
        id: kmId,
        arac_id: aracId,
        tarih: date,
        baslangic_km: bVal,
        bitis_km: eVal,
      },
      function (response) {
        td.removeClass("bg-soft-warning");
        if (response.status === "success") {
          td.addClass("bg-soft-success");
          setTimeout(() => td.removeClass("bg-soft-success"), 1000);
          td.data("old-val", currentValRaw);

          // Modal kapandığında tabloyu yenilemek için flag
          window._kmModalChanged = true;

          // ID'yi güncelle
          if (response.id) {
            tr.attr("data-id", response.id).data("id", response.id);
          }

          // Ana puantaj tablosunu (arka plandakini) güncelle
          const aracEncrypt = tr.data("arac-encrypt");
          const day = tr.data("day");
          const type = td.data("type");
          const formattedVal = AracTakip.formatNumber(parseInt(currentValRaw));

          if (aracEncrypt && day) {
            const backgroundCell = $(
              `#puantajTable td[data-arac-id="${aracEncrypt}"][data-day="${day}"][data-type="${type}"]`,
            );
            if (backgroundCell.length) {
              backgroundCell.text(formattedVal).addClass("bg-soft-success");
              setTimeout(
                () => backgroundCell.removeClass("bg-soft-success"),
                1000,
              );
            }
          }

          // Backend'den gelen yapılan KM'yi her iki tabloda da güncelle
          if (response.yapilan !== undefined) {
            const yapilanTd = tr.find(".yapilan-km");
            const formattedYapilan =
              response.yapilan > 0
                ? AracTakip.formatNumber(response.yapilan)
                : "-";

            yapilanTd
              .text(formattedYapilan)
              .toggleClass("text-primary", response.yapilan > 0);

            if (aracEncrypt && day) {
              const backgroundYapilanCell = $(
                `#puantajTable td[data-arac-id="${aracEncrypt}"][data-day="${day}"][data-type="yapilan"]`,
              );
              if (backgroundYapilanCell.length) {
                backgroundYapilanCell
                  .text(formattedYapilan)
                  .toggleClass("text-primary", response.yapilan > 0)
                  .addClass("bg-soft-success");
                setTimeout(
                  () => backgroundYapilanCell.removeClass("bg-soft-success"),
                  1000,
                );
              }
            }
          }

          // Otomatik Başlangıç KM taşıma (Eğer Bitiş girildiyse sonraki günün başlangıcına yaz)
          if (type === "bitis" && currentValRaw > 0) {
            const nextTr = tr.next("tr.km-quick-row");
            if (nextTr.length) {
              const nextBaslangicTd = nextTr.find(
                '.km-editable[data-type="baslangic"]',
              );
              const nextBaslangicVal = nextBaslangicTd
                .text()
                .trim()
                .replace(/\D/g, "");
              // Eğer sonraki günün başlangıcı boşsa veya güncellenebilir durumdaysa otomatik doldur
              if (nextBaslangicVal === "" || nextBaslangicVal == 0) {
                nextBaslangicTd.text(
                  AracTakip.formatNumber(parseInt(currentValRaw)),
                );
                // Not: Blur tetiklemiyoruz çünkü kullanıcı henüz oraya dokunmadı, sadece görsel kolaylık.
              }
            }
          }

          // Tablo genel toplamlarını yeniden hesapla
          AracTakip.recalculatePuantajTable();

          // Zincirleme kaydet (Diğer etkilenen günleri de veritabanına yaz)
          AracTakip.kmKaydetChain(tr);
        } else {
          td.addClass("bg-soft-danger");
          console.error("KM Kaydetme Hatası:", response.message);
          // Toast hata mesajı (eğer toast kütüphanesi varsa)
          if (window.toastr) {
            toastr.error(response.message);
          } else if (window.Swal) {
            Swal.fire({
              icon: "error",
              title: "Hata",
              text: response.message,
              toast: true,
              position: "top-end",
              showConfirmButton: false,
              timer: 3000,
            });
          }
        }
      },
    ).fail(function () {
      td.removeClass("bg-soft-warning").addClass("bg-soft-danger");
    });
  });

  // İstatistik Modal
  $(document).on(
    "click",
    "#btnYakitIstatistik, #btnKmIstatistik",
    function (e) {
      const type = $(this).data("type");
      $("#istatistikModalBody").html(
        '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2">Yükleniyor...</p></div>',
      );
      $.get(
        `views/arac-takip/modal_arac_${type}_istatistik.php`,
        {
          baslangic: $(`#${type}-filtre-baslangic`).val(),
          bitis: $(`#${type}-filtre-bitis`).val(),
          arac_id: $(`#${type}-filtre-arac`).val(),
        },
        (html) => $("#istatistikModalBody").html(html),
      ).fail(() =>
        $("#istatistikModalBody").html(
          '<div class="alert alert-danger">Hata oluştu.</div>',
        ),
      );
    },
  );

  // Select2
  if ($.fn.select2) {
    $(".select2").select2({
      dropdownParent: $(".modal.show").length ? $(".modal.show") : $("body"),
    });
    $(".modal").on("shown.bs.modal", function () {
      $(this)
        .find(".select2")
        .each(function () {
          $(this).select2({ dropdownParent: $(this).closest(".modal") });
        });
      if (typeof feather !== "undefined") feather.replace();
    });
  }

  // Tab Değişiklikleri
  $('#aracTab button[data-bs-toggle="tab"]').on("shown.bs.tab", function (e) {
    const target = $(e.target).attr("data-bs-target");
    if (!target) return;
    const tabName = target.replace("#", "").replace("Content", "");
    const url = new URL(window.location);
    url.searchParams.set("tab", tabName);
    window.history.replaceState({}, "", url);

    if (target === "#zimmetContent") AracTakip.zimmetListesiYukle();
    else if (target === "#yakitContent")
      AracTakip.yakitListesiYukle(
        $("#yakit-filtre-arac").val(),
        $("#yakit-filtre-baslangic").val(),
        $("#yakit-filtre-bitis").val(),
      );
    else if (target === "#kmContent")
      AracTakip.kmListesiYukle(
        $("#km-filtre-arac").val(),
        $("#km-filtre-baslangic").val(),
        $("#km-filtre-bitis").val(),
      );
    else if (target === "#servisContent")
      AracTakip.servisListesiYukle(
        $("#servis-filtre-arac").val(),
        $("#servis-filtre-baslangic").val(),
        $("#servis-filtre-bitis").val(),
      );
    else if (target === "#raporContent") AracTakip.aylikRaporYukle();
    else if (
      target === "#aracContent" &&
      !$.fn.DataTable.isDataTable("#aracTable")
    )
      AracTakip.initDataTable("#aracTable");

    // Excel Yükleme Butonu Görünürlüğü
    if (tabName === "yakit") {
      $("#liExcelYakitYukle").show();
      $("#liExcelAracYukle").hide();
    } else if (tabName === "arac") {
      $("#liExcelYakitYukle").hide();
      $("#liExcelAracYukle").show();
    } else {
      $("#liExcelYakitYukle").hide();
      $("#liExcelAracYukle").hide();
    }

    updateAracTakipUI();
  });

  // Başlangıç Yüklemesi
  const activeTabBtn = $("#aracTab .nav-link.active");
  if (activeTabBtn.length > 0) {
    const activeTarget = activeTabBtn.attr("data-bs-target");
    if (activeTarget === "#zimmetContent") AracTakip.zimmetListesiYukle();
    else if (activeTarget === "#yakitContent")
      AracTakip.yakitListesiYukle(
        $("#yakit-filtre-arac").val(),
        $("#yakit-filtre-baslangic").val(),
        $("#yakit-filtre-bitis").val(),
      );
    else if (activeTarget === "#kmContent")
      AracTakip.kmListesiYukle(
        $("#km-filtre-arac").val(),
        $("#km-filtre-baslangic").val(),
        $("#km-filtre-bitis").val(),
      );
    else if (activeTarget === "#servisContent")
      AracTakip.servisListesiYukle(
        $("#servis-filtre-arac").val(),
        $("#servis-filtre-baslangic").val(),
        $("#servis-filtre-bitis").val(),
      );
    else if (activeTarget === "#raporContent") AracTakip.aylikRaporYukle();
    updateAracTakipUI();
  }
});
