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
    const colCount = table.find("thead tr:first th").length || 9;
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
    if (!kmId && eVal <= 0) {
      // Kayıt yok ve bitiş de girilmemiş - sadece görsel güncelleme, atla
      this.kmKaydetSequential(rows);
      return;
    }

    // Eğer bVal ve eVal ikisi de 0 ise (ve ID varsa) bu da gereksiz bir update olabilir,
    // ancak kullanıcı belki temizlemek istemiştir. Yine de bitis 0 ise yapılan 0 gidecek.

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
            currentBaslangic = lastBitis;

            // Sadece mevcut kaydı olanları veya kullanıcının bitiş girdiği yeni kayıtları işaretle
            const kmId = tr.data("id");
            if (kmId || currentBitis > 0) {
              tr.attr("data-needs-update", "true");
            }
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
      html: `<b>${plaka}</b> plakalı aracı silmek istediğinize emin misiniz?<br><br>
             <textarea id="swal-input-aciklama" class="form-control" placeholder="Lütfen silme nedeni giriniz..." rows="3"></textarea>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
      preConfirm: () => {
        const aciklama = document.getElementById('swal-input-aciklama').value.trim();
        if (!aciklama) {
          Swal.showValidationMessage('Silme işlemi için açıklama girmek zorunludur!');
          return false;
        }
        return aciklama;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          { action: "arac-sil", id: id, aciklama: result.value },
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
    $("#iade_zimmet_id").val(zimmetId);
    $("#iade-arac-plaka").empty().append(`<span class="fw-bold text-dark">${plaka}</span> plakalı aracın iadesi`);
    $("#zimmetIadeForm")[0].reset();

    const today = new Date().toLocaleDateString("tr-TR");
    $("#iade_tarihi").val(today);

    // Initialize or re-init flatpickr
    if ($("#iade_tarihi").length > 0 && !$("#iade_tarihi")[0]._flatpickr) {
      $("#iade_tarihi").flatpickr({
        locale: "tr",
        dateFormat: "d.m.Y",
        allowInput: true,
      });
    }

    if ($("#iade_tarihi")[0] && $("#iade_tarihi")[0]._flatpickr) {
      $("#iade_tarihi")[0]._flatpickr.setDate(today);
    }

    $("#zimmetIadeModal").modal("show");
  },

  zimmetIadeKaydet: function () {
    const iadeKm = $("#iade_km").val();
    const iadeTarihi = $("#iade_tarihi").val();

    if (!iadeKm || iadeKm <= 0) {
      Swal.fire("Uyarı", "Lütfen geçerli bir iade KM giriniz.", "warning");
      return;
    }
    if (!iadeTarihi) {
      Swal.fire("Uyarı", "Lütfen iade tarihini giriniz.", "warning");
      return;
    }

    const formData = new FormData($("#zimmetIadeForm")[0]);
    formData.append("action", "zimmet-iade");

    const btn = $("#btnZimmetIadeKaydet");
    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> İşleniyor...')
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
            $("#zimmetIadeModal").modal("hide");
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

  zimmetGecmisi: function(aracId, plaka) {
    console.log("Arac Takip: Zimmet geçmişi isteniyor.", {aracId, plaka});
    $("#gecmisAracPlaka").text(plaka);
    $("#btnZimmetGecmisiExcel").attr("data-arac-id", aracId);
    const tbody = $("#zimmetGecmisiTableBody");
    tbody.html('<tr><td colspan="8" class="text-center p-4 text-muted"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Yükleniyor...</td></tr>');
    
    const modalEl = document.getElementById('zimmetGecmisiModal');
    if (modalEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            m.show();
        } else {
            $(modalEl).modal("show");
        }
    } else {
        console.error("Zimmet Geçmişi Modalı bulunamadı!");
        return;
    }

    $.post(this.apiUrl, { action: "zimmet-gecmisi", arac_id: aracId }, (response) => {
        if (response.status === "success") {
            let html = "";
            if (response.data && response.data.length > 0) {
                response.data.forEach((z, index) => {
                    const durumBadge = z.durum === "aktif" 
                        ? '<span class="badge bg-success">Aktif</span>' 
                        : '<span class="badge bg-secondary">İade Edildi</span>';
                    
                    html += `<tr>
                        <td class="text-center">${index + 1}</td>
                        <td><strong>${z.personel_adi || 'Bilinmiyor'}</strong></td>
                        <td class="text-center">${z.zimmet_tarihi_fmt || '-'}</td>
                        <td class="text-center">${z.iade_tarihi_fmt || '-'}</td>
                        <td class="text-center fw-bold">${z.teslim_km ? this.formatNumber(z.teslim_km) + ' km' : '-'}</td>
                        <td class="text-center fw-bold">${z.iade_km ? this.formatNumber(z.iade_km) + ' km' : '-'}</td>
                        <td class="text-center">
                            <small class="d-block fw-bold">${z.olusturan_kullanici_adi || '-'}</small>
                            <small class="text-muted" style="font-size: 0.7rem;">${z.olusturma_tarihi_fmt || '-'}</small>
                        </td>
                        <td class="text-center">${durumBadge}</td>
                    </tr>`;
                });
            } else {
                html = '<tr><td colspan="8" class="text-center text-muted p-4">Bu araca ait zimmet geçmişi bulunmamaktadır.</td></tr>';
            }
            tbody.html(html);
        } else {
            tbody.html(`<tr><td colspan="8" class="text-center text-danger p-4">Hata: ${response.message}</td></tr>`);
        }
    }).fail((xhr) => {
        console.error("API Hatası:", xhr);
        tbody.html('<tr><td colspan="8" class="text-center text-danger p-4">Sunucu hatası oluştu.</td></tr>');
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
                                <div class="dropdown">
                                    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        ${z.durum === "aktif" ? `
                                            <li>
                                                <a class="dropdown-item text-warning zimmet-iade" href="javascript:void(0);" data-id="${z.id}" data-plaka="${z.plaka}">
                                                    <i class="bx bx-undo me-2"></i> İade Al
                                                </a>
                                            </li>
                                        ` : ''}
                                        <li>
                                            <a class="dropdown-item text-info arac-zimmet-gecmisi" href="javascript:void(0);" data-id="${z.arac_id}" data-plaka="${z.plaka}">
                                                <i class="bx bx-history me-2"></i> Zimmet Geçmişi
                                            </a>
                                        </li>
                                    </ul>
                                </div>
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
      html: `Bu yakıt kaydını silmek istediğinize emin misiniz?<br><br>
             <textarea id="swal-input-aciklama" class="form-control" placeholder="Lütfen silme nedeni giriniz..." rows="3"></textarea>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
      preConfirm: () => {
        const aciklama = document.getElementById('swal-input-aciklama').value.trim();
        if (!aciklama) {
          Swal.showValidationMessage('Silme işlemi için açıklama girmek zorunludur!');
          return false;
        }
        return aciklama;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          { action: "yakit-sil", id: id, aciklama: result.value },
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
  yakitListesiYukle: function (aracId = null, baslangic = null, bitis = null, departman = null) {
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
    if (departman) data.departman = departman;

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
                            <td class="text-end">${self.formatMoney(y.brut_tutar)}</td>
                            <td class="text-end">${self.formatNumber(y.iskonto)}%</td>
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

  kmSil: function (id, fromPuantaj = false) {
    Swal.fire({
      title: "Emin misiniz?",
      html: `Bu KM kaydını silmek istediğinize emin misiniz?<br><br>
             <textarea id="swal-input-aciklama" class="form-control" placeholder="Lütfen silme nedeni giriniz..." rows="3"></textarea>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
      preConfirm: () => {
        const aciklama = document.getElementById('swal-input-aciklama').value.trim();
        if (!aciklama) {
          Swal.showValidationMessage('Silme işlemi için açıklama girmek zorunludur!');
          return false;
        }
        return aciklama;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(this.apiUrl, { action: "km-sil", id: id, aciklama: result.value }, function (response) {
          if (response.status === "success") {
            Swal.fire({
              icon: "success",
              title: "Silindi",
              text: response.message,
              timer: 1500,
              showConfirmButton: false,
            }).then(() => {
              if (fromPuantaj && typeof window.loadReport === 'function') {
                window.loadReport();
              } else {
                location.reload();
              }
            });
          } else {
            Swal.fire("Hata", response.message, "error");
          }
        });
      }
    });
  },

  kmListesiYukle: function (aracId = null, baslangic = null, bitis = null, departman = null) {
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
    if (departman) data.departman = departman;

    $.post(this.apiUrl, data, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data && response.data.length > 0) {
          response.data.forEach(function (k, index) {
            let yapilanKm = parseFloat(k.yapilan_km) || 0;
            if (yapilanKm < 0) yapilanKm = 0;

            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${k.plaka}</strong></td>
                            <td>${self.formatDate(k.tarih)}</td>
                            <td class="text-end">
                                <div class="d-flex flex-column align-items-end">
                                    <a href="arac-puantaj?arac_id=${k.arac_id}" class="text-primary fw-bold" title="Puantajda Görüntüle">${self.formatNumber(k.baslangic_km)} km</a>
                                    ${k.sabah_personel ? `<span class="badge bg-light text-dark border p-1 mt-1" style="font-size: 0.65rem;"><i class='bx bx-sun me-1'></i>${k.sabah_personel}</span>` : ''}
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-column align-items-end">
                                    <a href="arac-puantaj?arac_id=${k.arac_id}" class="text-primary fw-bold" title="Puantajda Görüntüle">${self.formatNumber(k.bitis_km)} km</a>
                                    ${k.aksam_personel ? `<span class="badge bg-light text-dark border p-1 mt-1" style="font-size: 0.65rem;"><i class='bx bx-moon me-1'></i>${k.aksam_personel}</span>` : ''}
                                </div>
                            </td>
                            <td class="text-end fw-bold ${yapilanKm > 0 ? 'text-success' : 'text-muted'}">${self.formatNumber(yapilanKm)} km</td>
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
      }
    );
  },

  initListContextMenu: function () {
    const contextMenu = $("#arac-context-menu");
    if (!contextMenu.length) return;

    $(document).on("contextmenu", "#aracTable tbody tr, #zimmetTable tbody tr", function (e) {
      // Input veya textarea üzerinde sağ tıklandığında engelleme
      if ($(e.target).is("input, textarea, select")) return;

      e.preventDefault();
      
      const tr = $(this);
      const dropdownMenu = tr.find(".dropdown-menu");
      if (dropdownMenu.length === 0) return;

      // Aktif satır vurgusu
      $(".context-menu-active").removeClass("context-menu-active");
      tr.addClass("context-menu-active");

      const x = e.clientX;
      const y = e.clientY;
      const menuItems = $("#context-menu-items");
      
      // Dropdown içindeki menü elemanlarını kopyala
      menuItems.empty();
      
      // Plaka bilgisini al
      let plaka = tr.find("strong").first().text();
      if (!plaka) {
          plaka = tr.find("td:nth-child(2) strong").text();
      }
      $("#context-arac-plaka").text(plaka || "Seçili Araç");

      dropdownMenu.find("li").each(function() {
          const li = $(this);
          const originalA = li.find("a");
          
          if (originalA.length > 0) {
              const clonedA = originalA.clone();
              clonedA.addClass("context-link");
              menuItems.append(clonedA);
          } else if (li.find("hr").length > 0) {
              menuItems.append('<div class="dropdown-divider"></div>');
          }
      });

      // Menü konumlandırma ve gösterim
      contextMenu.show();
      
      const menuWidth = contextMenu.outerWidth();
      const menuHeight = contextMenu.outerHeight();
      const windowWidth = $(window).width();
      const windowHeight = $(window).height();

      let left = x;
      let top = y;

      if (x + menuWidth > windowWidth) left = x - menuWidth;
      if (y + menuHeight > windowHeight) top = y - menuHeight;

      contextMenu.css({
          left: left + "px",
          top: top + "px"
      });
    });

    // Menü dışına tıklandığında kapat
    $(document).on("mousedown", function (e) {
      if (!$(e.target).closest("#arac-context-menu").length) {
          contextMenu.hide();
          $(".context-menu-active").removeClass("context-menu-active");
      }
    });

    // Context link tıklandığında menüyü kapat
    $(document).on("click", ".context-link", function() {
      contextMenu.hide();
      $(".context-menu-active").removeClass("context-menu-active");
    });
  },

  // =============================================
  // PUANTAJ CONTEXT MENU
  // =============================================
  initPuantajContextMenu: function () {
    const menu = $("#kmContextMenu");
    if (!menu.length) return;

    // Body'ye taşı (Konumlandırma sorunlarını önlemek için)
    menu.appendTo("body");

    $(document).on("contextmenu", ".km-ctx-target", function (e) {
      const td = $(this);
      const kmId = td.attr("data-km-id");
      const aracId = td.attr("data-arac-id");

      if (!kmId) return;

      e.preventDefault();
      e.stopPropagation();

      $(".km-cell-active").removeClass("km-cell-active");
      td.addClass("km-cell-active");

      menu.data("km-id", kmId);
      menu.data("arac-id", aracId);

      let posX = e.clientX;
      let posY = e.clientY;

      const menuWidth = menu.outerWidth();
      const menuHeight = menu.outerHeight();
      const winWidth = $(window).width();
      const winHeight = $(window).height();

      if (posX + menuWidth > winWidth) posX -= menuWidth;
      if (posY + menuHeight > winHeight) posY -= menuHeight;

      menu.css({
        display: "block",
        left: posX,
        top: posY,
      });

      // Dışarı tıklayınca kapat
      $(document).one("mousedown", function (de) {
        if (!$(de.target).closest("#kmContextMenu").length) {
          menu.hide();
          td.removeClass("km-cell-active");
        }
      });
    });

    // Düzenle
    $("#ctxKmDuzenle").on("click", function () {
      const aracId = menu.data("arac-id");
      if (!aracId) return;

      const year = $('select[name="year"]').val();
      const month = $('select[name="month"]').val();

      $("#aracOzelPuantajContent").html(
        '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Puantaj detayları yükleniyor...</p></div>',
      );
      $("#aracOzelPuantajModal").modal("show");

      $.get(
        AracTakip.apiUrl,
        {
          action: "get-arac-ozel-puantaj",
          id: aracId,
          month: month,
          year: year,
        },
        function (html) {
          $("#aracOzelPuantajContent").html(html);
        },
      );
      menu.hide();
    });

    // Sil
    $("#ctxKmSil").on("click", function () {
      const kmId = menu.data("km-id");
      if (kmId) {
        AracTakip.kmSil(kmId, true);
      }
      menu.hide();
    });
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

          if (response.updatedDetails && response.updatedDetails.length > 0) {
            msg += `<br><br><details><summary class="text-primary fw-bold" style="cursor:pointer;">Güncellenen Kayıtlar (${response.updatedDetails.length})</summary>
            <ul class="text-start small mt-2 mb-0" style="max-height: 150px; overflow-y: auto;">`;
            response.updatedDetails.forEach(d => msg += `<li>${d}</li>`);
            msg += `</ul></details>`;
          }

          if (response.addedDetails && response.addedDetails.length > 0) {
            msg += `<br><details><summary class="text-success fw-bold" style="cursor:pointer;">Yeni Eklenen Kayıtlar (${response.addedDetails.length})</summary>
            <ul class="text-start small mt-2 mb-0" style="max-height: 150px; overflow-y: auto;">`;
            response.addedDetails.forEach(d => msg += `<li>${d}</li>`);
            msg += `</ul></details>`;
          }

          if (response.errors && response.errors.length > 0) {
            msg += "<br><br><strong>Hatalar:</strong><ul class='text-start small text-danger' style='max-height: 150px; overflow-y: auto;'>";
            response.errors.forEach(function (e) {
              msg += "<li>" + e + "</li>";
            });
            msg += "</ul>";
          }

          Swal.fire({
            icon: "success",
            title: "İşlem Tamamlandı",
            html: msg,
            width: '600px',
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
              console.log(data[key]);
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
    const iadeTarihi = $("#servisForm [name='iade_tarihi']").val();
    const ikamePlaka = $("#servisForm [name='ikame_plaka']").val();
    const ikameIadeKm = $("#servisForm [name='ikame_iade_km']").val();

    if (iadeTarihi && ikamePlaka && !ikameIadeKm) {
      Swal.fire({
        icon: "warning",
        title: "Eksik Bilgi",
        text: "İkame araç iade KM bilgisini girmelisiniz.",
      }).then(() => {
        $("#servis-ikame-tab").tab("show");
        setTimeout(() => {
          $("#servisForm [name='ikame_iade_km']").focus();
        }, 200);
      });
      return;
    }

    const confirmed = $("#servisForm").data("ikame-confirmed");

    if (!ikamePlaka && !confirmed) {
      Swal.fire({
        title: "İkame Araç",
        text: "İkame araç kaydı yapılacak mı?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Evet",
        cancelButtonText: "Hayır",
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#6c757d",
      }).then((result) => {
        if (result.isConfirmed) {
          $("#servis-ikame-tab").tab("show");
          setTimeout(() => {
            $("#servisForm [name='ikame_plaka']").focus();
          }, 200);
        } else {
          $("#servisForm").data("ikame-confirmed", true);
          this.servisKaydet();
        }
      });
      return;
    }

    const ikameId = $("#servisForm [name='ikame_arac_id']").val();
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

  servisDuzenle: function (id, showCikisTab = false) {
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
          if (showCikisTab) {
            setTimeout(() => {
              $('#servis-cikis-tab').tab('show');
            }, 100);
          } else {
            setTimeout(() => {
              $('#servis-giris-tab').tab('show');
            }, 100);
          }
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
    );
  },

  servisSil: function (id) {
    Swal.fire({
      title: "Emin misiniz?",
      html: `Bu servis kaydını silmek istediğinize emin misiniz?<br><br>
             <textarea id="swal-input-aciklama" class="form-control" placeholder="Lütfen silme nedeni giriniz..." rows="3"></textarea>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
      preConfirm: () => {
        const aciklama = document.getElementById('swal-input-aciklama').value.trim();
        if (!aciklama) {
          Swal.showValidationMessage('Silme işlemi için açıklama girmek zorunludur!');
          return false;
        }
        return aciklama;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          this.apiUrl,
          { action: "servis-sil", id: id, aciklama: result.value },
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
            const sJson = JSON.stringify(s).replace(/"/g, '&quot;');
            
            let ikameBadgeClass = "bg-warning text-dark";
            let ikameTitle = "İkame Detayları";
            if (s.ikame_iade_tarihi) {
                ikameBadgeClass = "bg-success-subtle text-success";
                ikameTitle = "İkame İade Edildi (Detaylar için tıkla)";
            }
            
            const ikameInfo = s.ikame_plaka ? `<span class="badge ${ikameBadgeClass} ikame-detay-btn" style="cursor:pointer;" data-json="${sJson}" title="${ikameTitle}"><i class="bx bx-transfer me-1"></i>${s.ikame_plaka}</span>` : '<span class="text-muted">-</span>';

            const actionButtons = `
                <div class="dropdown">
                    <a href="javascript:void(0);" class="text-muted" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 0.5rem; display: inline-block; line-height: 1;">
                        <i class="bx bx-dots-vertical-rounded" style="font-size: 1.4rem;"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width: 160px; border-radius: 8px;">
                        ${!s.iade_tarihi ? `<li><a class="dropdown-item servis-duzenle-cikis text-success fw-bold py-2" href="#" data-id="${s.id}"><i class="bx bx-log-out-circle me-1" style="font-size: 1.1rem; vertical-align: middle;"></i> Servis Çıkış Kaydı</a></li><li><hr class="dropdown-divider my-1"></li>` : ''}
                        <li><a class="dropdown-item servis-duzenle text-warning py-2" href="#" data-id="${s.id}"><i class="bx bx-edit me-1" style="font-size: 1.1rem; vertical-align: middle;"></i> Düzenle</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item servis-sil text-danger py-2" href="#" data-id="${s.id}"><i class="bx bx-trash me-1" style="font-size: 1.1rem; vertical-align: middle;"></i> Sil</a></li>
                    </ul>
                </div>
            `;

            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${s.plaka || "-"}</strong><br><small>${s.marka || ""} ${s.model || ""}</small></td>
                            <td>${self.formatDate(s.servis_tarihi)}</td>
                            <td>${s.iade_tarihi ? self.formatDate(s.iade_tarihi) : "-"}</td>
                            <td class="text-end">${self.formatNumber(s.giris_km)} km</td>
                            <td class="text-end">${self.formatNumber(s.cikis_km)} km</td>
                            <td class="text-truncate" style="max-width: 200px;" title="${s.servis_nedeni}">${s.servis_nedeni || "-"}</td>
                            <td class="text-center">${ikameInfo}</td>
                            <td class="text-center">
                                ${actionButtons}
                            </td>
                        </tr>`;
          });
        } else {
          const colCount = $("#servisTable").find("thead tr:first th").length || 9;
          let tds =
            '<td class="text-center py-4 text-muted">-</td><td class="py-4 text-muted">Kayıt bulunamadı.</td>';
          for (let i = 2; i < colCount; i++) tds += "<td></td>";
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
  // KM ONAY İŞLEMLERİ
  // =============================================
  kmOnayla: function (id, aracId, km, plaka, tur, tarih) {
    const now = new Date();
    const currentHour = now.getHours();
    const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

    if (tarih === todayStr && tur === 'aksam' && currentHour < 13) {
        Swal.fire({
            icon: 'warning',
            title: 'Onay Engellendi',
            text: 'Bugün için henüz akşam KM onayı yapılamaz. Lütfen saat 13:00\'dan sonra tekrar deneyiniz.',
            confirmButtonText: 'Tamam'
        });
        return;
    }

    const turText = tur === 'sabah' ? '<span class="text-warning">SABAH (Başlangıç)</span>' : '<span class="text-info">AKŞAM (Bitiş)</span>';
    Swal.fire({
      title: "Kilometre Onayı",
      html: `<b>${plaka}</b> plakalı aracın bildirilen ${turText} <b>${km} KM</b> değerini onaylıyor musunuz?<br><br><small class="text-muted">Bu işlem aracın güncel KM değerini güncelleyecektir.</small>`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#34c38f",
      cancelButtonColor: "#f46a6a",
      confirmButtonText: "Evet, Onayla",
      cancelButtonText: "Vazgeç",
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: "İşlem Yapılıyor",
          text: "Lütfen bekleyiniz...",
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        $.post(
          this.apiUrl,
          { action: "km-onay-ver", id: id },
          (response) => {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Onaylandı",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              }).then(() => location.reload());
            } else {
              Swal.fire("Hata", response.message, "error").then(() => {
                  if (response.message && response.message.indexOf("Sayfa yenileniyor") !== -1) {
                      location.reload();
                  }
              });
            }
          },
        ).fail(() => {
          Swal.fire("Hata", "Sunucu hatası oluştu. Lütfen tekrar deneyiniz.", "error");
        });
      }
    });
  },

  kmTopluOnayla: function (ids) {
    if (!ids || ids.length === 0) return;

    Swal.fire({
      title: "Toplu KM Onayı",
      html: `Seçilen <b>${ids.length}</b> adet KM bildirimini onaylamak istediğinize emin misiniz?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#34c38f",
      cancelButtonColor: "#f46a6a",
      confirmButtonText: "Evet, Hepsini Onayla",
      cancelButtonText: "Vazgeç",
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: "İşlem Yapılıyor",
          text: "Lütfen bekleyiniz...",
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        $.post(
          this.apiUrl,
          { action: "km-onay-toplu-onayla", ids: ids },
          (response) => {
            if (response.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Başarılı",
                text: response.message,
                timer: 2000,
                showConfirmButton: false,
              }).then(() => location.reload());
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
        ).fail(() => {
          Swal.fire("Hata", "Sunucu hatası oluştu. Lütfen tekrar deneyiniz.", "error");
        });
      }
    });
  },

  kmReddet: function (id) {
    $("#kmRedForm")[0].reset();
    $("#red_bildirim_id").val(id);
    $("#kmRedModal").modal("show");
  },

  kmRedKaydet: function () {
    const formData = new FormData($("#kmRedForm")[0]);
    const btn = $("#btnKmReddetSubmit");
    const originalText = btn.html();

    btn.html('<i class="bx bx-loader-alt bx-spin me-1"></i> Reddediliyor...')
       .prop("disabled", true);

    Swal.fire({
      title: "İşlem Yapılıyor",
      text: "Lütfen bekleyiniz...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Reddedildi",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            $("#kmRedModal").modal("hide");
            location.reload();
          });
        } else {
          Swal.fire("Hata", response.message, "error").then(() => {
              if (response.message && response.message.indexOf("Sayfa yenileniyor") !== -1) {
                  location.reload();
              }
          });
        }
      },
      error: () => {
        Swal.fire("Hata", "Sunucu hatası oluştu. Lütfen tekrar deneyiniz.", "error");
      },
      complete: () => {
        btn.html(originalText).prop("disabled", false);
      }
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
    $("#servisForm #arac_id").val(null).trigger("change");
    $("#servisForm #ikame_arac_id").val("");
    
    // Flatpickr alanlarını temizle
    $("#servisForm .flatpickr").each(function() {
      if (this._flatpickr) this._flatpickr.clear();
      $(this).val("");
    });

    $("#servisForm").data("ikame-confirmed", false);

    $("#ikameAracBilgiCard").hide();
    $("#servisModal")
      .find(".modal-title")
      .html('<i class="bx bx-wrench me-2"></i>Yeni Servis Kaydı');
    $('#servis-giris-tab').tab('show');
  },

  // KM Excel Yükleme İşlemleri
  initKmExcelUpload: function () {
    const self = this;
    const kmFileInput = document.getElementById("kmExcelFile");
    const kmUploadZone = document.getElementById("kmUploadZone");

    if (!kmUploadZone || !kmFileInput) return;

    $("#kmExcelYukleModal").on("show.bs.modal", function () {
      // Reset state
      $("#kmUploadDefault").removeClass("d-none");
      $("#kmUploadSelected").addClass("d-none");
      $("#kmUploadProgress").addClass("d-none");
      $("#kmUploadResult").addClass("d-none").html("");
      $("#btnKmExcelYukleSubmit").prop("disabled", true);
      $("#kmExcelFile").val("");
    });

    kmFileInput.addEventListener("change", function () {
      if (this.files.length > 0) {
        onKmFileSelected(this.files[0]);
      }
    });

    // Drag-and-drop
    kmUploadZone.addEventListener("dragover", function (e) {
      e.preventDefault();
      $(this).addClass("dragover");
    });
    kmUploadZone.addEventListener("dragleave", function () {
      $(this).removeClass("dragover");
    });
    kmUploadZone.addEventListener("drop", function (e) {
      e.preventDefault();
      $(this).removeClass("dragover");
      const file = e.dataTransfer.files[0];
      if (file) {
        kmFileInput.files = e.dataTransfer.files;
        onKmFileSelected(file);
      }
    });

    function onKmFileSelected(file) {
      const allowed = ["xlsx", "xls"];
      const ext = file.name.split(".").pop().toLowerCase();
      if (!allowed.includes(ext)) {
        showKmResult(
          "danger",
          '<i class="mdi mdi-alert-circle me-1"></i>Sadece .xlsx veya .xls dosyası kabul edilir.',
        );
        return;
      }
      $("#kmUploadDefault").addClass("d-none");
      $("#kmUploadSelected").removeClass("d-none");
      $("#kmUploadFileName").text(
        file.name + " (" + (file.size / 1024).toFixed(1) + " KB)",
      );
      $("#btnKmExcelYukleSubmit").prop("disabled", false);
    }

    $("#btnKmExcelYukleSubmit").on("click", function () {
      const file = kmFileInput.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append("action", "km-excel-yukle");
      formData.append("excel_file", file);

      $("#kmUploadProgress").removeClass("d-none");
      $("#kmUploadResult").addClass("d-none").html("");
      $(this).prop("disabled", true);

      $.ajax({
        url: self.apiUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          $("#kmUploadProgress").addClass("d-none");

          if (response.status === "success") {
            let html = `<div class="alert alert-success py-2 mb-2">
                                <i class="mdi mdi-check-circle me-1"></i>
                                <strong>${response.success} kayıt</strong> başarıyla kaydedildi.
                                ${response.skip > 0 ? `<span class="text-muted"> (${response.skip} satır atlandı)</span>` : ""}
                            </div>`;

            if (
              response.unmatchedPlates &&
              response.unmatchedPlates.length > 0
            ) {
              html += `<div class="alert alert-warning py-2 mb-2">
                                    <strong><i class="mdi mdi-car-off me-1"></i>Eşleşmeyen Plakalar (${response.unmatchedPlates.length}):</strong>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        ${response.unmatchedPlates.map((p) => `<span class="badge bg-warning text-dark">${p}</span>`).join("")}
                                    </div>
                                    <small class="text-muted d-block mt-1">Bu plakalar sistemde bulunamadı veya pasif durumda.</small>
                                </div>`;
            }

            if (response.errors && response.errors.length > 0) {
              html += `<div class="alert alert-danger py-2 mb-0">
                                    <strong><i class="mdi mdi-alert me-1"></i>Hatalar (${response.errors.length}):</strong>
                                    <ul class="mb-0 mt-1 ps-3 small">
                                        ${response.errors.map((e) => `<li>${e}</li>`).join("")}
                                    </ul>
                                </div>`;
            }

            if (response.debugDate) {
              html += `<div class="alert alert-info py-1 mb-2 small">
                                    <i class="mdi mdi-information-outline me-1"></i>
                                    <strong>Tarih Kontrolü:</strong> ${response.debugDate}
                                    <br><small>Eğer bu tarih yanlışsa, puantajda doğru ayı göremiyor olabilirsiniz.</small>
                                </div>`;
            }

            if (response.success > 0) {
              html += `<div class="mt-2 text-end">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnKmYukleSonrasiYenile">
                                        <i class="mdi mdi-refresh me-1"></i> Tabloyu Yenile
                                    </button>
                                </div>`;
            }

            showKmResult(null, html);
          } else {
            showKmResult(
              "danger",
              '<i class="mdi mdi-alert-circle me-1"></i>' +
                (response.message || "Bilinmeyen hata."),
            );
          }
          $("#btnKmExcelYukleSubmit").prop("disabled", false);
        },
        error: function (xhr) {
          $("#kmUploadProgress").addClass("d-none");
          showKmResult(
            "danger",
            '<i class="mdi mdi-alert-circle me-1"></i>Sunucu hatası: ' +
              xhr.responseText.substring(0, 200),
          );
          $("#btnKmExcelYukleSubmit").prop("disabled", false);
        },
      });
    });

    $(document).on("click", "#btnKmYukleSonrasiYenile", function () {
      $("#kmExcelYukleModal").modal("hide");
      if (typeof loadReport === "function") {
        loadReport();
      } else if (self.activeTab === "km" || $("#kmTable").length > 0) {
        self.kmListesiYukle(
          $("#km-filtre-arac").val(),
          $("#km-filtre-baslangic").val(),
          $("#km-filtre-bitis").val(),
        );
      } else {
        location.reload();
      }
    });

    function showKmResult(type, html) {
      const el = $("#kmUploadResult");
      el.removeClass("d-none");
      if (type) {
        el.html(`<div class="alert alert-${type} py-2 mb-0">${html}</div>`);
      } else {
        el.html(html);
      }
    }
  },

  aylikRaporYukle: function () {
    const baslangic = $("#rapor-filtre-baslangic").val();
    const bitis = $("#rapor-filtre-bitis").val();
    const aracId = $("#rapor-filtre-arac").val();
    const btn = $("#btnRaporYukle");
    const container = $("#raporIcerik");

    const originalText = btn.html();
    btn
      .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...')
      .prop("disabled", true);
    container.html(
      '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Rapor hazırlanıyor...</p></div>',
    );

    $.post(
      this.apiUrl,
      {
        action: "aylik-rapor",
        baslangic: baslangic,
        bitis: bitis,
        arac_id: aracId,
      },
      function (response) {
        if (response.status === "success") {
          const yakitOzet = response.data.yakit_ozet || [];
          const kmOzet = response.data.km_ozet || [];

          if (yakitOzet.length === 0 && kmOzet.length === 0) {
            container.html(
              '<div class="alert alert-info">Seçilen kriterlere uygun veri bulunamadı.</div>',
            );
            return;
          }

          // Merge arrays based on arac_id
          const map = {};

          yakitOzet.forEach((y) => {
            map[y.arac_id] = { ...y };
          });

          kmOzet.forEach((k) => {
            if (!map[k.arac_id]) {
              map[k.arac_id] = {
                arac_id: k.arac_id,
                plaka: k.plaka,
                marka: k.marka,
                model: k.model,
                toplam_litre: 0,
                toplam_tutar: 0,
              };
            }
            map[k.arac_id].toplam_km = k.toplam_km;
          });

          const merged = Object.values(map);

          let maxKm = 0;
          let maxKmPlaka = "-";
          let maxLitre = 0;
          let maxLitrePlaka = "-";
          let maxTutar = 0;
          let maxTutarPlaka = "-";

          merged.forEach((m) => {
            const km = parseFloat(m.toplam_km) || 0;
            const litre = parseFloat(m.toplam_litre) || 0;
            const tutar = parseFloat(m.toplam_tutar) || 0;

            if (km > maxKm) {
              maxKm = km;
              maxKmPlaka = m.plaka;
            }
            if (litre > maxLitre) {
              maxLitre = litre;
              maxLitrePlaka = m.plaka;
            }
            if (tutar > maxTutar) {
              maxTutar = tutar;
              maxTutarPlaka = m.plaka;
            }
          });

          let html = `
          <div class="row g-3 mb-4">
              <div class="col-xl col-md-4">
                  <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                      <div class="card-body p-3">
                          <div class="icon-label-container">
                              <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                  <i class="bx bx-tachometer fs-4" style="color: #0ea5e9;"></i>
                              </div>
                              <span class="text-muted small fw-bold" style="font-size: 0.65rem;">EN ÇOK YAPAN</span>
                          </div>
                          <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">EN ÇOK KM YAPAN ARAÇ</p>
                          <h4 class="mb-1 fw-bold bordro-text-heading">${maxKmPlaka}</h4>
                          <span class="text-primary fw-bold" style="font-size: 0.85rem;">${AracTakip.formatNumber(maxKm)} km</span>
                      </div>
                  </div>
              </div>
              <div class="col-xl col-md-4">
                  <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                      <div class="card-body p-3">
                          <div class="icon-label-container">
                              <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                  <i class="bx bx-gas-pump fs-4" style="color: #2a9d8f;"></i>
                              </div>
                              <span class="text-muted small fw-bold" style="font-size: 0.65rem;">LİTRE BAZINDA</span>
                          </div>
                          <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">EN ÇOK YAKIT TÜKETEN</p>
                          <h4 class="mb-1 fw-bold bordro-text-heading">${maxLitrePlaka}</h4>
                          <span class="text-success fw-bold" style="font-size: 0.85rem;">${AracTakip.formatNumber(maxLitre)} L</span>
                      </div>
                  </div>
              </div>
              <div class="col-xl col-md-4">
                  <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #E76F51; border-bottom: 3px solid var(--card-color) !important;">
                      <div class="card-body p-3">
                          <div class="icon-label-container">
                              <div class="icon-box" style="background: rgba(231, 111, 81, 0.1);">
                                  <i class="bx bx-money fs-4" style="color: #E76F51;"></i>
                              </div>
                              <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TUTAR BAZINDA</span>
                          </div>
                          <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">EN YÜKSEK MALİYET</p>
                          <h4 class="mb-1 fw-bold bordro-text-heading">${maxTutarPlaka}</h4>
                          <span class="text-danger fw-bold" style="font-size: 0.85rem;">${AracTakip.formatMoney(maxTutar)} ₺</span>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="table-responsive">
              <table class="table table-hover table-bordered nowrap w-100 report-dataTable">
                  <thead class="table-light">
                      <tr>
                          <th class="text-center" style="width:5%">Sıra</th>
                          <th>Plaka</th>
                          <th>Araç Bilgisi</th>
                          <th class="text-end">Yapılan KM</th>
                          <th class="text-end">Tüketim (Litre)</th>
                          <th class="text-end">Maliyet</th>
                          <th class="text-end">Ort. Tüketim (L/100km)</th>
                      </tr>
                  </thead>
                  <tbody>
        `;

          merged.forEach((item, index) => {
            const km = parseFloat(item.toplam_km) || 0;
            const litre = parseFloat(item.toplam_litre) || 0;
            const tutar = parseFloat(item.toplam_tutar) || 0;
            const ort = km > 0 ? (litre / km) * 100 : 0;

            html += `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td class="fw-bold">${item.plaka}</td>
                    <td><span class="small text-muted">${item.marka || ""} ${item.model || ""}</span></td>
                    <td class="text-end text-primary fw-bold">${AracTakip.formatNumber(km)} km</td>
                    <td class="text-end text-success fw-bold">${AracTakip.formatNumber(litre)} L</td>
                    <td class="text-end text-danger fw-bold">${AracTakip.formatMoney(tutar)}</td>
                    <td class="text-end">${AracTakip.formatNumber(ort.toFixed(2))} L</td>
                </tr>
            `;
          });

          html += `
                  </tbody>
              </table>
          </div>
        `;

          container.html(html);

          if (typeof destroyAndInitDataTable === "function") {
            destroyAndInitDataTable(".report-dataTable", {
              pageLength: 25,
              order: []
            });
          } else if ($.fn.DataTable) {
            $(".report-dataTable").DataTable();
          }
        } else {
          container.html(
            '<div class="alert alert-danger">' +
              (response.message || "Hata oluştu.") +
              "</div>",
          );
        }
      },
    )
      .fail(function () {
        container.html(
          '<div class="alert alert-danger">Sunucu hatası oluştu.</div>',
        );
      })
      .always(function () {
        btn.html(originalText).prop("disabled", false);
      });
  },
};

// =============================================
// EVENT LISTENERS
// =============================================
$(document).ready(function () {
  // DataTable başlat
  AracTakip.initDataTable("#aracTable");
  AracTakip.initKmExcelUpload();

  // Sekme bazlı UI güncellemeleri
  function updateAracTakipUI() {
    const activeTabBtn = $("#aracTab .nav-link.active");
    if (activeTabBtn.length === 0) return;

    const target =
      activeTabBtn.attr("data-bs-target") || activeTabBtn.attr("href");
    if (!target) return;

    const tabName = target.replace("#", "").replace("Content", "");

    // Excel menüsü görünürlüğü
    if (tabName === "yakit") {
      $("#liExcelYakitYukle").show();
      $("#liExcelAracYukle").hide();
      $("#liExcelKmYukle").hide();
    } else if (tabName === "arac") {
      $("#liExcelYakitYukle").hide();
      $("#liExcelAracYukle").show();
      $("#liExcelKmYukle").hide();
    } else if (tabName === "km") {
      $("#liExcelYakitYukle").hide();
      $("#liExcelAracYukle").hide();
      $("#liExcelKmYukle").show();
    } else {
      $("#liExcelYakitYukle").hide();
      $("#liExcelAracYukle").hide();
      $("#liExcelKmYukle").hide();
    }

    // Yeni Ekle butonu görünürlüğü (Rapor ve KM Onay sekmesinde gizle)
    if (tabName === "rapor" || tabName === "kmOnay") {
      $("#btnYeniEkle").attr("style", "display: none !important");
    } else {
      $("#btnYeniEkle").attr("style", "display: block !important");
    }
  }

  const TURKISH_MONTH_NAMES = [
    "Ocak",
    "Şubat",
    "Mart",
    "Nisan",
    "Mayıs",
    "Haziran",
    "Temmuz",
    "Ağustos",
    "Eylül",
    "Ekim",
    "Kasım",
    "Aralık",
  ];

  function formatPeriodLabel(month, year) {
    return `${TURKISH_MONTH_NAMES[month - 1]} ${year}`;
  }

  function parsePeriodValue(periodRaw) {
    const normalizedValue = (periodRaw || "").trim();
    if (!normalizedValue) return null;

    const dotMatch = normalizedValue.match(/^(0?[1-9]|1[0-2])[.\/-](\d{4})$/);
    if (dotMatch) {
      return {
        month: parseInt(dotMatch[1], 10),
        year: parseInt(dotMatch[2], 10),
      };
    }

    const isoMatch = normalizedValue.match(/^(\d{4})-(0?[1-9]|1[0-2])$/);
    if (isoMatch) {
      return {
        month: parseInt(isoMatch[2], 10),
        year: parseInt(isoMatch[1], 10),
      };
    }

    const textMatch = normalizedValue.match(/^([A-Za-zCĞIİOÖSŞUÜcğıiösşuü]+)\s+(\d{4})$/i);
    if (textMatch) {
      const monthName = textMatch[1]
        .replace(/İ/g, "i")
        .replace(/I/g, "i")
        .replace(/ı/g, "i")
        .replace(/Ğ/g, "g")
        .replace(/ğ/g, "g")
        .replace(/Ü/g, "u")
        .replace(/ü/g, "u")
        .replace(/Ş/g, "s")
        .replace(/ş/g, "s")
        .replace(/Ö/g, "o")
        .replace(/ö/g, "o")
        .replace(/Ç/g, "c")
        .replace(/ç/g, "c")
        .toLowerCase();
      const monthIndex = [
        "ocak",
        "subat",
        "mart",
        "nisan",
        "mayis",
        "haziran",
        "temmuz",
        "agustos",
        "eylul",
        "ekim",
        "kasim",
        "aralik",
      ].indexOf(monthName);

      if (monthIndex !== -1) {
        return {
          month: monthIndex + 1,
          year: parseInt(textMatch[2], 10),
        };
      }
    }

    return null;
  }

  function getDateRangeForType(type) {
    const mode = $(`input[name="${type}DateMode"]:checked`).val() || "range";

    if (mode === "period") {
      const periodRaw = ($(`#${type}-filtre-donem`).val() || "").trim();
      const parsedPeriod = parsePeriodValue(periodRaw);

      let month;
      let year;

      if (parsedPeriod) {
        month = parsedPeriod.month;
        year = parsedPeriod.year;
      } else {
        const now = new Date();
        month = now.getMonth() + 1;
        year = now.getFullYear();
      }

      const paddedMonth = String(month).padStart(2, "0");
      const lastDay = new Date(year, month, 0).getDate();
      const baslangic = `01.${paddedMonth}.${year}`;
      const bitis = `${String(lastDay).padStart(2, "0")}.${paddedMonth}.${year}`;

      $(`#${type}-filtre-baslangic`).val(baslangic);
      $(`#${type}-filtre-bitis`).val(bitis);
      if (!periodRaw || !parsedPeriod) {
        $(`#${type}-filtre-donem`).val(formatPeriodLabel(month, year));
      }

      return { baslangic, bitis };
    }

    return {
      baslangic: $(`#${type}-filtre-baslangic`).val(),
      bitis: $(`#${type}-filtre-bitis`).val(),
    };
  }

  function syncDateModeUI(type) {
    const mode = $(`input[name="${type}DateMode"]:checked`).val() || "range";
    const isPeriod = mode === "period";
    $(`.${type}-range-field`).toggleClass("d-none", isPeriod);
    $(`.${type}-period-field`).toggleClass("d-none", !isPeriod);
    if (isPeriod) {
      getDateRangeForType(type);
    }
  }

  function syncAllDateModeUI() {
    syncDateModeUI("yakit");
    syncDateModeUI("km");
    syncDateModeUI("servis");
  }

  function initMonthPickers() {
    if (typeof flatpickr === "undefined") return;

    $(".month-picker").each(function () {
      if (this._flatpickr) return;

      const input = this;
      const initialPeriod = parsePeriodValue(input.value);
      const config = {
        locale: "tr",
        allowInput: true,
        dateFormat: "F Y",
        defaultDate: initialPeriod
          ? new Date(initialPeriod.year, initialPeriod.month - 1, 1)
          : new Date(),
        onChange: function () {
          const type = (input.id || "").split("-")[0];
          if (type) {
            getDateRangeForType(type);
          }
        },
        onClose: function () {
          const type = (input.id || "").split("-")[0];
          if (type) {
            getDateRangeForType(type);
          }
        },
      };

      if (typeof monthSelectPlugin !== "undefined") {
        config.plugins = [
          new monthSelectPlugin({
            shorthand: false,
            dateFormat: "F Y",
            altFormat: "F Y",
            theme: "light",
          }),
        ];
      }

      flatpickr(input, config);
    });
  }

  function loadTabList(type) {
    const dateRange = getDateRangeForType(type);

    if (type === "yakit") {
      AracTakip.yakitListesiYukle(
        $("#yakit-filtre-arac").val(),
        dateRange.baslangic,
        dateRange.bitis,
        $("#yakit-filtre-departman").val()
      );
    } else if (type === "km") {
      AracTakip.kmListesiYukle(
        $("#km-filtre-arac").val(),
        dateRange.baslangic,
        dateRange.bitis,
        $("#km-filtre-departman").val()
      );
    } else if (type === "servis") {
      AracTakip.servisListesiYukle(
        $("#servis-filtre-arac").val(),
        dateRange.baslangic,
        dateRange.bitis,
      );
    }
  }

  initMonthPickers();
  syncAllDateModeUI();
  setTimeout(syncAllDateModeUI, 0);

  $(document).on(
    "change",
    'input[name="yakitDateMode"], input[name="kmDateMode"], input[name="servisDateMode"]',
    function () {
      const match = (this.name || "").match(/^([a-z]+)DateMode$/);
      if (!match) return;
      syncDateModeUI(match[1]);
    },
  );

  $(document).on(
    "change",
    "#yakit-filtre-donem, #km-filtre-donem, #servis-filtre-donem",
    function () {
      const type = (this.id || "").split("-")[0];
      if (type) {
        getDateRangeForType(type);
      }
    },
  );

  // Excele Aktar Butonu
  $(document).on("click", "#btnExceleAktar", function (e) {
    e.preventDefault();
    const activeTabObj = $(".tab-pane.active");
    const activeTabId = activeTabObj.attr("id");
    
    // Araçlar sekmesiyse özel exportu kullan
    if (activeTabId === "aracContent") {
      const urlParams = new URLSearchParams(window.location.search);
      const filter = urlParams.get("filter") || "";
      window.location.href = AracTakip.apiUrl + "?action=arac-excel-aktar&filter=" + filter;
      return;
    }

    const table = activeTabObj.find("table");
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
  $(document).on("click", ".arac-sil", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const plaka = $(this).data("plaka");
    if (id) AracTakip.aracSil(id, plaka);
  });
  $(document).on("click", ".arac-zimmet-gecmisi", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const plaka = $(this).data("plaka");
    if (id) AracTakip.zimmetGecmisi(id, plaka);
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
  $(document).on("click", "#btnZimmetIadeKaydet", (e) => {
    e.preventDefault();
    AracTakip.zimmetIadeKaydet();
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
  $(document).on("click", ".servis-duzenle-cikis", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    if (id) AracTakip.servisDuzenle(id, true);
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

  // KM Onay İşlemleri
  $(document).on("click", ".btn-km-onayla", function (e) {
    e.preventDefault();
    const btn = $(this);
    AracTakip.kmOnayla(
      btn.data("id"),
      btn.data("arac-id"),
      btn.data("km"),
      btn.data("plaka"),
      btn.data("tur"),
      btn.data("tarih")
    );
  });

  $(document).on("click", ".btn-km-reddet", function (e) {
    e.preventDefault();
    AracTakip.kmReddet($(this).data("id"));
  });
  $(document).on("submit", "#kmRedForm", function (e) {
    e.preventDefault();
    AracTakip.kmRedKaydet();
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
    loadTabList("yakit");
  });
  $(document).on("click", "#btnKmFiltrele", (e) => {
    e.preventDefault();
    loadTabList("km");
  });
  $(document).on("click", "#btnServisFiltrele", (e) => {
    e.preventDefault();
    loadTabList("servis");
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
    else if (target === "#yakitContent") loadTabList("yakit");
    else if (target === "#kmContent") loadTabList("km");
    else if (target === "#servisContent") loadTabList("servis");
    else if (target === "#raporContent") AracTakip.aylikRaporYukle();
    else if (
      target === "#aracContent" &&
      !$.fn.DataTable.isDataTable("#aracTable")
    )
      AracTakip.initDataTable("#aracTable");

    if (tabName === "yakit" || tabName === "km" || tabName === "servis") {
      syncDateModeUI(tabName);
    }

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
    else if (activeTarget === "#yakitContent") loadTabList("yakit");
    else if (activeTarget === "#kmContent") loadTabList("km");
    else if (activeTarget === "#servisContent") loadTabList("servis");
    else if (activeTarget === "#raporContent") AracTakip.aylikRaporYukle();
    updateAracTakipUI();
  }

  // İkame Detay Modal Listener
  $(document).on("click", ".ikame-detay-btn", function () {
    const s = $(this).data("json");
    $("#ikame-detay-plaka").text(s.ikame_plaka || "-");
    $("#ikame-detay-marka-model").text(`${s.ikame_marka || ""} ${s.ikame_model || ""}`.trim() || "-");
    $("#ikame-detay-alis-tarih").text(AracTakip.formatDate(s.ikame_alis_tarihi));
    $("#ikame-detay-iade-tarih").text(AracTakip.formatDate(s.ikame_iade_tarihi));
    $("#ikame-detay-teslim-km").text(s.ikame_teslim_km ? AracTakip.formatNumber(s.ikame_teslim_km) + " km" : "-");
    $("#ikame-detay-iade-km").text(s.ikame_iade_km ? AracTakip.formatNumber(s.ikame_iade_km) + " km" : "-");
    $("#ikameDetayModal").modal("show");
  });

  // Zimmet Geçmişi Excel Aktar
  $(document).on("click", "#btnZimmetGecmisiExcel", function() {
    const aracId = $(this).attr("data-arac-id");
    if (aracId) {
        window.location.href = AracTakip.apiUrl + "?action=zimmet-gecmisi-excel&arac_id=" + aracId;
    }
  });

  // Context Menüleri Başlat
  AracTakip.initPuantajContextMenu();
  AracTakip.initListContextMenu();
});
