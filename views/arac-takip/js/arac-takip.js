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
    $(selector).html(
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Yükleniyor...</p></div>',
    );
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
              input.val(data[key]).trigger("change");
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
        return {
          iade_km: document.getElementById("iadeKm").value,
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
    const tbody = $("#zimmetTableBody");
    self.showLoading(tbody);

    $.post(this.apiUrl, { action: "zimmet-listesi" }, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data.length === 0) {
          html =
            '<tr><td colspan="7" class="text-center text-muted py-4">Zimmet kaydı bulunmamaktadır.</td></tr>';
        } else {
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
      }
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

  yakitListesiYukle: function (aracId = null) {
    const self = this;
    const tbody = $("#yakitTableBody");
    self.showLoading(tbody);

    const data = { action: "yakit-listesi" };
    if (aracId) data.arac_id = aracId;

    $.post(this.apiUrl, data, function (response) {
      if (response.status === "success") {
        let html = "";
        if (response.data.length === 0) {
          html =
            '<tr><td colspan="9" class="text-center text-muted py-4">Yakıt kaydı bulunmamaktadır.</td></tr>';
        } else {
          response.data.forEach(function (y, index) {
            html += `<tr>
                            <td class="text-center">${index + 1}</td>
                            <td><strong>${y.plaka}</strong></td>
                            <td>${self.formatDate(y.tarih)}</td>
                            <td class="text-end">${self.formatNumber(y.km)} km</td>
                            <td class="text-end">${self.formatNumber(y.yakit_miktari)} L</td>
                            <td class="text-end">${self.formatMoney(y.birim_fiyat)}</td>
                            <td class="text-end">${self.formatMoney(y.toplam_tutar)}</td>
                            <td>${y.istasyon || "-"}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-danger yakit-sil" data-id="${y.id}" title="Sil"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>`;
          });
        }
        tbody.html(html);
      }
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
                    <table class="table table-bordered table-hover">
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
          } else {
            html +=
              '<tr><td colspan="6" class="text-center text-muted">Bu dönem için veri bulunmamaktadır.</td></tr>';
          }

          html += "</tbody></table></div>";
          container.html(html);
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
    $("#zimmetSelect").val(null).trigger("change");
    $("#personelSelect").val(null).trigger("change");
  },

  resetYakitModal: function () {
    $("#yakitForm")[0].reset();
    $('#yakitForm input[name="id"]').val("");
    $("#yakitAracSelect").val(null).trigger("change");
    $("#yakitModal")
      .find(".modal-title")
      .html('<i class="bx bx-gas-pump me-2"></i>Yakıt Kaydı Ekle');
  },

  resetKmModal: function () {
    $("#kmForm")[0].reset();
    $('#kmForm input[name="id"]').val("");
    $("#kmAracSelect").val(null).trigger("change");
  },
};

// =============================================
// EVENT LISTENERS
// =============================================
$(document).ready(function () {
  // DataTable başlat
  if ($("#aracTable").length && $.fn.DataTable) {
    $("#aracTable").DataTable({
      language: {
        url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json",
      },
      order: [[1, "asc"]],
      pageLength: 25,
    });
  }

  // Araç Modal sıfırlama
  $("#aracModal").on("hidden.bs.modal", function () {
    AracTakip.resetAracModal();
  });

  // Zimmet Modal sıfırlama
  $("#zimmetModal").on("hidden.bs.modal", function () {
    AracTakip.resetZimmetModal();
  });

  // Yakıt Modal sıfırlama
  $("#yakitModal").on("hidden.bs.modal", function () {
    AracTakip.resetYakitModal();
  });

  // KM Modal sıfırlama
  $("#kmModal").on("hidden.bs.modal", function () {
    AracTakip.resetKmModal();
  });

  // Araç Kaydet
  $(document).on("click", "#btnAracKaydet", function (e) {
    e.preventDefault();
    AracTakip.aracKaydet();
  });

  // Araç Düzenle
  $(document).on("click", ".arac-duzenle", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    AracTakip.aracDuzenle(id);
  });

  // Araç Sil
  $(document).on("click", ".arac-sil", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const plaka = $(this).data("plaka");
    AracTakip.aracSil(id, plaka);
  });

  // Zimmet Kaydet
  $(document).on("click", "#btnZimmetKaydet", function (e) {
    e.preventDefault();
    AracTakip.zimmetVer();
  });

  // Zimmet İade
  $(document).on("click", ".zimmet-iade", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const plaka = $(this).data("plaka");
    AracTakip.zimmetIade(id, plaka);
  });

  // Yakıt Kaydet
  $(document).on("click", "#btnYakitKaydet", function (e) {
    e.preventDefault();
    AracTakip.yakitKaydet();
  });

  // Yakıt Sil
  $(document).on("click", ".yakit-sil", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    AracTakip.yakitSil(id);
  });

  // KM Kaydet
  $(document).on("click", "#btnKmKaydet", function (e) {
    e.preventDefault();
    AracTakip.kmKaydet();
  });

  // Excel Yükle
  $(document).on("click", "#btnExcelYukle", function (e) {
    e.preventDefault();
    AracTakip.yakitExcelYukle();
  });

  // Rapor Yükle
  $(document).on("click", "#btnRaporYukle", function (e) {
    e.preventDefault();
    AracTakip.aylikRaporYukle();
  });

  // Tab değişikliklerinde listeleri yükle
  $('button[data-bs-toggle="tab"]').on("shown.bs.tab", function (e) {
    const target = $(e.target).data("bs-target");
    if (target === "#zimmetContent") {
      AracTakip.zimmetListesiYukle();
    } else if (target === "#yakitContent") {
      AracTakip.yakitListesiYukle();
    } else if (target === "#raporContent") {
      AracTakip.aylikRaporYukle();
    }
  });

  // Birim fiyat otomatik hesaplama
  $(document).on("input", "#yakitMiktari, #yakitTutar", function () {
    const miktar = parseFloat($("#yakitMiktari").val()) || 0;
    const tutar = parseFloat($("#yakitTutar").val()) || 0;
    if (miktar > 0 && tutar > 0) {
      const birimFiyat = (tutar / miktar).toFixed(2);
      $("#yakitBirimFiyat").val(birimFiyat);
    }
  });

  // Select2 başlat
  if ($.fn.select2) {
    $(".select2").select2({
      theme: "bootstrap-5",
      width: "100%",
      dropdownParent: $(".modal.show").length ? $(".modal.show") : $("body"),
    });

    // Modal açıldığında Select2 yeniden başlat
    $(".modal").on("shown.bs.modal", function () {
      $(this)
        .find(".select2")
        .each(function () {
          $(this).select2({
            theme: "bootstrap-5",
            width: "100%",
            dropdownParent: $(this).closest(".modal"),
          });
        });
    });
  }
});
