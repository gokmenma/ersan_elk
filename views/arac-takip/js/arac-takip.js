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
          const input = $('[name="' + key + '"]');
          if (input.length) {
            if (input.is("select")) {
              input.val(data[key]);
              // Sadece Select2 ise change tetikle
              if (input.hasClass("select2")) {
                //input.trigger("change");
              }
            } else {
              input.val(data[key]);
            }
          }
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
            if (input.hasClass("flatpickr")) {
              // Flatpickr tarih formatı
              const date = new Date(data[key]);
              input[0]._flatpickr.setDate(date);
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
            if (input.hasClass("flatpickr")) {
              const date = new Date(data[key]);
              input[0]._flatpickr.setDate(date);
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
                // dd.mm.yyyy formatını ayrıştır
                const parts = dateVal.split(".");
                if (parts.length === 3) {
                  const date = new Date(parts[2], parts[1] - 1, parts[0]);
                  input[0]._flatpickr.setDate(date);
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
        modalId = "#aracModal";
        break;
      case "zimmet-tab":
        modalId = "#zimmetModal";
        break;
      case "yakit-tab":
        modalId = "#yakitModal";
        break;
      case "km-tab":
        modalId = "#kmModal";
        break;
      case "servis-tab":
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
