const url = "views/personel/api/APIyillik-izin-hakedisleri.php";
const SUMMARY_KEY = "yillikizin_summary_cards_state";
const CAN_EDIT = document.getElementById("btnManuelEkle") !== null;

function gunMetni(v) {
  return Number(v).toLocaleString("tr-TR", { maximumFractionDigits: 1 });
}

function escapeHtml(v) {
  return $("<div>").text(v == null ? "" : String(v)).html();
}

$(document).ready(function () {
  let statusFilter = "all";

  const dtTable = $("#yillikIzinTable").DataTable(
    applyLengthStateSave({
      ...getDatatableOptions(),
      processing: true,
      serverSide: true,
      ajax: {
        url: url,
        type: "POST",
        data: function (d) {
          d.action = "yillik-izin-hakedis-liste";
          d.status_filter = statusFilter;
          d.pasif_dahil = $("#pasifDahil").is(":checked") ? 1 : 0;
        },
      },
      columns: [
        { data: "sira", orderable: false, searchable: false },
        { data: "adi_soyadi" },
        { data: "departman" },
        { data: "gorev" },
        { data: "ise_giris_tarihi" },
        { data: "kidem_yil" },
        { data: "toplam" },
        { data: "kullanilan" },
        { data: "kalan" },
        { data: "durum" },
        { data: "sonraki" },
        { data: "actions", orderable: false, searchable: false },
      ],
      order: [[1, "asc"]],
      pageLength: 25,
    }),
  );

  const $container = $("#summaryCardsContainer");
  const $toggle = $("#btnToggleSummaryCards");

  function setSummaryVisible(visible) {
    document.documentElement.classList.toggle("yillikizin-summary-hidden", !visible);
    try {
      localStorage.setItem(SUMMARY_KEY, visible ? "visible" : "hidden");
    } catch (e) {}
    $toggle
      .attr("aria-expanded", visible ? "true" : "false")
      .attr("aria-label", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
      .attr("title", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
      .html(visible ? '<i class="bx bx-chevron-up"></i>' : '<i class="bx bx-chevron-down"></i>');
  }

  if ($container.length && $toggle.length) {
    const hidden = document.documentElement.classList.contains("yillikizin-summary-hidden");
    $toggle.attr("aria-expanded", hidden ? "false" : "true");
    $toggle.html(hidden ? '<i class="bx bx-chevron-down"></i>' : '<i class="bx bx-chevron-up"></i>');
    $toggle.on("click", function () {
      setSummaryVisible(document.documentElement.classList.contains("yillikizin-summary-hidden"));
    });
  }

  function refreshSummary() {
    $.post(url, { action: "yillik-izin-hakedis-ozet", pasif_dahil: $("#pasifDahil").is(":checked") ? 1 : 0 }, function (res) {
      if (res && res.status === "success" && res.summary) {
        const s = res.summary;
        $("#kpi_toplam").text(s.toplam_personel);
        $("#kpi_sub_toplam").text("Hakedişi olan: " + s.hakedisi_olan + " | Yok: " + s.hakedis_yok);
        $("#kpi_kullanilabilir").text(s.kullanilabilir);
        $("#kpi_tukendi").text(s.tukendi);
        $("#kpi_sub_yok").text("Hakedişi olmayan: " + s.hakedis_yok);
        $("#kpi_kalan").text(gunMetni(s.toplam_kalan));
        $("#kpi_sub_kalan").text("Hakediş: " + gunMetni(s.toplam_hakedis) + " | Kullanılan: " + gunMetni(s.toplam_kullanilan));
      }
    }, "json");
  }

  $("#pasifDahil").on("change", function () {
    dtTable.ajax.reload();
    refreshSummary();
  });

  $(".status-quick-filter").on("click", function () {
    $(".status-quick-filter").removeClass("active");
    $(this).addClass("active");
    statusFilter = $(this).data("status");
    dtTable.ajax.reload();
  });

  $("#btnHeaderPrint").on("click", function () {
    window.print();
  });

  function openDetay(encId) {
    $.post(url, { action: "yillik-izin-hakedis-detay", id: encId }, function (res) {
      if (!res || res.status !== "success") {
        Swal.fire("Hata", (res && res.message) || "Detay alınamadı.", "error");
        return;
      }
      const d = res.detay;
      let html =
        '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">' +
        '<div><h5 class="mb-0 fw-bold">' + escapeHtml(d.adi_soyadi) + "</h5>" +
        '<small class="text-muted">İşe giriş: ' + escapeHtml(d.ise_giris_tarihi) + "</small></div>" +
        '<div class="d-flex gap-2">' +
        '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">Hakediş: ' + gunMetni(d.toplam) + " gün</span>" +
        '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2">Kullanılan: ' + gunMetni(d.kullanilan) + " gün</span>" +
        '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">Kalan: ' + gunMetni(d.kalan) + " gün</span>" +
        "</div></div>";

      html += '<h6 class="fw-bold font-size-13">Hizmet Yılına Göre Hakediş</h6>';
      if (d.yillar.length) {
        html += '<table class="table table-sm table-bordered align-middle mb-4"><thead class="table-light"><tr><th>Hizmet Yılı</th><th>Hakediş Tarihi</th><th class="text-center">Gün</th></tr></thead><tbody>';
        d.yillar.forEach(function (y) {
          html += "<tr><td>" + y.yil + ". yıl</td><td>" + escapeHtml(y.tarih) + '</td><td class="text-center fw-semibold">' + y.gun + "</td></tr>";
        });
        html += "</tbody></table>";
      } else {
        html += '<p class="text-muted font-size-12 mb-4">Henüz bir yıllık hizmet süresi dolmadığı için hakediş oluşmamıştır.</p>';
      }

      if (d.manuel && d.manuel.length) {
        html += '<h6 class="fw-bold font-size-13">Manuel Hakedişler</h6>';
        html += '<table class="table table-sm table-bordered align-middle mb-4"><thead class="table-light"><tr><th>Tarih</th><th class="text-center">Gün</th><th>Açıklama</th><th class="text-center">Kaynak</th><th class="text-center" style="width:60px"></th></tr></thead><tbody>';
        d.manuel.forEach(function (m) {
          html += '<tr><td>' + escapeHtml(m.tarih) + '</td><td class="text-center fw-semibold">' + gunMetni(m.gun) + '</td><td>' + escapeHtml(m.aciklama) + '</td><td class="text-center">' + (m.kaynak === 'excel' ? 'Excel' : 'Manuel') + '</td><td class="text-center">' +
            (CAN_EDIT ? '<button type="button" class="btn btn-sm btn-subtle-danger table-action-btn manuel-sil" data-id="' + escapeHtml(m.id) + '" title="Sil"><i class="bx bx-trash"></i></button>' : '') + '</td></tr>';
        });
        html += '</tbody></table>';
      }

      html += '<h6 class="fw-bold font-size-13">Kullanılan Yıllık İzinler</h6>';
      if (d.izinler.length) {
        html += '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Başlangıç</th><th>Bitiş</th><th class="text-center">Gün</th><th class="text-center">Bakiyeden Düşer</th></tr></thead><tbody>';
        d.izinler.forEach(function (i) {
          html += "<tr><td>" + escapeHtml(i.baslangic) + "</td><td>" + escapeHtml(i.bitis) + '</td><td class="text-center">' + gunMetni(i.gun) + '</td><td class="text-center">' +
            (i.dusuyor ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Evet</span>' : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Hayır</span>') + "</td></tr>";
        });
        html += "</tbody></table>";
      } else {
        html += '<p class="text-muted font-size-12 mb-0">Onaylanmış yıllık izin kaydı bulunmuyor.</p>';
      }

      $("#detayModalBody").html(html);
      bootstrap.Modal.getOrCreateInstance(document.getElementById("detayModal")).show();
    }, "json").fail(function () {
      Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
    });
  }

  $("#yillikIzinTable tbody").on("click", "tr", function (e) {
    if ($(e.target).closest(".action-btn-group").length) {
      return;
    }
    const row = dtTable.row(this).data();
    if (row && row.enc_id) {
      openDetay(row.enc_id);
    }
  });

  $("#yillikIzinTable tbody").on("click", ".detay", function (e) {
    e.stopPropagation();
    openDetay($(this).data("id"));
  });
  function yenile() {
    dtTable.ajax.reload(null, false);
    refreshSummary();
  }

  if (CAN_EDIT) {
    $("#personel_id").select2({ width: "100%", dropdownParent: $("#manuelModal") });

    $("#manuelModal").on("show.bs.modal", function () {
      $("#personel_id").val("").trigger("change");
      $("#gun").val("");
      $("#aciklama").val("");
    });

    $("#btnManuelKaydet").on("click", function () {
      const $btn = $(this).prop("disabled", true);
      $.post(url, {
        action: "manuel-hakedis-kaydet",
        personel_id: $("#personel_id").val(),
        hakedis_tarihi: $("#hakedis_tarihi").val(),
        gun: $("#gun").val(),
        aciklama: $("#aciklama").val(),
      }, function (res) {
        $btn.prop("disabled", false);
        if (res && res.status === "success") {
          bootstrap.Modal.getInstance(document.getElementById("manuelModal")).hide();
          Swal.fire({ icon: "success", title: "Kaydedildi", text: res.message, timer: 1500, showConfirmButton: false });
          yenile();
        } else {
          Swal.fire("Hata", (res && res.message) || "İşlem başarısız.", "error");
        }
      }, "json").fail(function () {
        $btn.prop("disabled", false);
        Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
      });
    });

    $("#excelModal").on("show.bs.modal", function () {
      $("#excelForm")[0].reset();
      $("#excelSonuc").empty();
    });

    $("#btnExcelYukle").on("click", function () {
      const dosya = $("#excel_file")[0].files[0];
      if (!dosya) {
        Swal.fire("Uyarı", "Lütfen bir Excel dosyası seçiniz.", "warning");
        return;
      }
      const fd = new FormData();
      fd.append("action", "manuel-hakedis-excel-yukle");
      fd.append("excel_file", dosya);
      const $btn = $(this).prop("disabled", true);
      $.ajax({ url: url, type: "POST", data: fd, processData: false, contentType: false, dataType: "json" })
        .done(function (res) {
          $btn.prop("disabled", false);
          if (res && res.status === "success") {
            let html = '<div class="alert alert-success py-2 mb-2">' + escapeHtml(res.message) + "</div>";
            if (res.hatalar && res.hatalar.length) {
              html += '<ul class="text-danger ps-3 mb-0">' + res.hatalar.map(function (h) { return "<li>" + escapeHtml(h) + "</li>"; }).join("") + "</ul>";
            }
            $("#excelSonuc").html(html);
            yenile();
          } else {
            $("#excelSonuc").html('<div class="alert alert-danger py-2 mb-0">' + escapeHtml((res && res.message) || "Yükleme başarısız.") + "</div>");
          }
        })
        .fail(function () {
          $btn.prop("disabled", false);
          $("#excelSonuc").html('<div class="alert alert-danger py-2 mb-0">Sunucu ile iletişim kurulamadı.</div>');
        });
    });

    $(document).on("click", ".manuel-sil", function () {
      const id = $(this).data("id");
      Swal.fire({
        title: "Emin misiniz?",
        text: "Manuel hakediş kaydı silinecek.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ea5455",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Evet, sil!",
        cancelButtonText: "İptal",
      }).then(function (r) {
        if (!r.isConfirmed) {
          return;
        }
        $.post(url, { action: "manuel-hakedis-sil", id: id }, function (res) {
          if (res && res.status === "success") {
            bootstrap.Modal.getInstance(document.getElementById("detayModal")).hide();
            Swal.fire({ icon: "success", title: "Silindi", text: res.message, timer: 1500, showConfirmButton: false });
            yenile();
          } else {
            Swal.fire("Hata", (res && res.message) || "Silme başarısız.", "error");
          }
        }, "json");
      });
    });
  }
});
