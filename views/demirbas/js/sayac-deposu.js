$(function () {
  const apiUrl = "views/demirbas/api.php";

  // sayacTable is already initialized by demirbas.js (shares #sayacTable element)

  const sayacZimmetTable = $("#sayacZimmetTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "zimmet-listesi";
        d.filter_type = "sayac";
        d.personel_id = "all";
        d.status_filter = $('input[name="zimmet-status-filter"]:checked').val() || "";
        d.sayac_kat_ids = typeof sayacKatIds !== "undefined" ? sayacKatIds : [];
        d.aparat_kat_ids = [];
      },
    },
    columns: [
      { data: "checkbox", className: "text-center", orderable: false, searchable: false },
      { data: "id", className: "text-center" },
      { data: "kategori_adi" },
      { data: "demirbas_adi" },
      { data: "marka_model" },
      { data: "personel_adi" },
      { data: "teslim_miktar", className: "text-center" },
      { data: "teslim_tarihi" },
      { data: "durum", className: "text-center" },
      { data: "islemler", className: "text-center", orderable: false },
    ],
    order: [[1, "desc"]],
    initComplete: function (settings, json) {
      // Hareketler tabı yüklendiğinde de preloader'dan emin olalım
      $("#personel-loader").fadeOut(300);

      // Projenin standart gelişmiş filtrelerini başlat
      if (typeof initAdvancedFilters === "function") {
        initAdvancedFilters(this.api(), settings);
      }
    },
  });

  // Sayaç Personel Özeti Tablosu
  const sayacPersonelTable = $("#sayacPersonelTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "sayac-personel-list";
      },
    },
    order: [[2, "desc"]], // Tarihe göre azalan
    columns: [
      { data: "expand_icon", orderable: false, searchable: false, className: "text-center" },
      { data: "sira", className: "text-center" },
      { data: "tarih" },
      { data: "personel_adi" },
      { data: "bizden_toplam_aldigi", className: "text-center" },
      { data: "toplam_taktigi", className: "text-center" },
      { data: "teslim_edilen_hurda", className: "text-center" },
      { data: "toplam_hurda", className: "text-center" },
      {
        data: "elinde_kalan_yeni",
        className: "text-center fw-bold"
      }
    ],
    createdRow: function (row, data, dataIndex) {
      $(row).attr("data-personel-id", data.personel_id);
      $(row).attr("data-tarih", data.tarih_raw);
      $(row).css("cursor", "pointer");
      $(row).addClass('personel-day-row');
    }
  });

  // Accordion Detay Gösterimi
  $('#sayacPersonelTable tbody').on('click', 'tr.personel-day-row', function () {
    const tr = $(this);
    const row = sayacPersonelTable.row(tr);
    const icon = tr.find('.expand-icon-btn');

    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass('shown');
      icon.removeClass('rotate-90');
    } else {
      const data = row.data();
      const pId = data.personel_id;
      const date = data.tarih_raw;

      // Detayları API'dan çek
      $.post(apiUrl, { action: 'sayac-personel-daily-details', personel_id: pId, date: date }, function (res) {
        if (res.status === 'success') {
          let html = `
            <div class="ms-5 me-2 mb-3 bg-white border border-info border-start-0 border-end-0 border-bottom-0 border-top-4 rounded-bottom shadow-sm overflow-hidden">
                <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-info"><i class="bx bx-list-ul me-1"></i> GÜNLÜK HAREKET DETAYLARI</span>
                    <span class="badge bg-soft-info text-info">${res.data.length} işlem</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-white">
                            <tr class="small text-muted border-top-0">
                                <th class="ps-3">Saat</th>
                                <th>İşlem</th>
                                <th>Sayaç / Demirbaş</th>
                                <th>Marka/Model</th>
                                <th>Seri No</th>
                                <th class="text-center">Miktar</th>
                                <th>Durum</th>
                                <th>Açıklama</th>
                            </tr>
                        </thead>
                        <tbody>`;
          
          if(res.data.length === 0) {
              html += `<tr><td colspan="8" class="text-center py-4 text-muted">Kayıt bulunamadı.</td></tr>`;
          }

          res.data.forEach(item => {
            html += `
                <tr>
                    <td class="ps-3 fw-medium text-muted small">${item.tarih}</td>
                    <td>${item.tip}</td>
                    <td class="fw-medium">${item.demirbas}</td>
                    <td class="small">${item.marka_model}</td>
                    <td><code class="text-dark bg-light px-1 rounded">${item.seri_no}</code></td>
                    <td class="text-center fw-bold text-primary">${item.miktar}</td>
                    <td>${item.durum_badge}</td>
                    <td class="small text-muted">${item.aciklama}</td>
                </tr>`;
          });

          html += `</tbody></table></div></div>`;
          row.child(html).show();
          tr.addClass('shown');
          icon.addClass('rotate-90');
        }
      }, 'json');
    }
  });

  // Zimmet filtre butonları
  $(document).on("change", 'input[name="zimmet-status-filter"]', function () {
    $(this).closest(".status-filter-group").find("label").removeClass("active");
    $(this).next("label").addClass("active");
    sayacZimmetTable.draw();
  });

  $('button[data-bs-target="#sayacHareketPane"]').on("shown.bs.tab", function () {
    sayacZimmetTable.columns.adjust().draw(false);
  });

  $('button[data-bs-target="#sayacPersonelPane"]').on("shown.bs.tab", function () {
    sayacPersonelTable.columns.adjust().draw(false);
  });

  $('button[data-bs-target="#sayaclarPane"]').on("shown.bs.tab", function () {
    if (typeof sayacTable !== "undefined" && sayacTable) {
      sayacTable.columns.adjust().draw(false);
    }
  });

  // Excel dışa aktar — aktif sekmeye göre (demirbas.js'in handlerını geçersiz kılar)
  $(document).off("click.sayacDepo", "#exportExcel").on("click.sayacDepo", "#exportExcel", function (e) {
    e.preventDefault();
    const activeTarget = $("#sayacDepoTab button.active").data("bs-target");
    let tbl = null;
    if (activeTarget === "#sayacHareketPane") {
        tbl = sayacZimmetTable;
    } else if (activeTarget === "#sayacPersonelPane") {
        tbl = sayacPersonelTable;
    } else {
        tbl = typeof sayacTable !== "undefined" ? sayacTable : null;
    }
    if (tbl) {
      tbl.button(".buttons-excel").trigger();
    }
  });

  // Toplu Kaskiye Teslim
  $(document).on("click", "#btnTopluKaskiyeTeslim", function () {
    const selected = [];
    $(".sayac-select:checked").each(function () {
      selected.push($(this).val());
    });

    if (selected.length === 0) {
      Swal.fire("Uyarı", "Lütfen en az bir sayaç seçin.", "warning");
      return;
    }

    // Modal'ı hazırla
    $("#kasiye_is_toplu").val("1");
    $("#kasiye_toplu_ids").val(JSON.stringify(selected));
    $("#kasiye_demirbas_id").val("");
    
    $("#kasiyeTopluAdetV2").text(selected.length);
    $("#kasiyeOnayMesaji").html('Seçili <span class="fw-bold text-dark">' + selected.length + '</span> adet sayacı Kaskiye teslim etmek istiyor musunuz?');

    $("#kasiyeTeslimModal").modal("show");
  });

  // Tekli Kaskiye Teslim (Tablodan)
  $(document).on("click", ".sayac-kasiye-teslim", function (e) {
    e.preventDefault();
    const id = $(this).data("id");
    const name = $(this).data("name");

    $("#kasiye_is_toplu").val("0");
    $("#kasiye_toplu_ids").val("");
    $("#kasiye_demirbas_id").val(id);

    $("#kasiyeTopluAdetV2").text("1");
    $("#kasiyeOnayMesaji").html('<span class="fw-bold text-dark">' + name + '</span> isimli sayacı Kaskiye teslim etmek istiyor musunuz?');

    $("#kasiyeTeslimModal").modal("show");
  });

  // Kaskiye Teslim Form Kaydet
  $(document).on("submit", "#kasiyeTeslimForm", function (e) {
    e.preventDefault();
    const isToplu = $("#kasiye_is_toplu").val() === "1";
    const formData = $(this).serialize();
    const action = isToplu ? "toplu-kasiye-teslim" : "kasiye-teslim";

    $("#btnKasiyeKaydet").prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> İşleniyor...');

    $.ajax({
      url: apiUrl,
      type: "POST",
      data: formData + "&action=" + action,
      dataType: "json",
      success: function (res) {
        $("#btnKasiyeKaydet").prop("disabled", false).html("Evet, Teslim Et");
        if (res.status === "success") {
          $("#kasiyeTeslimModal").modal("hide");
          Swal.fire("Başarılı", res.message, "success");
          
          // Tabloları yenile
          if (typeof sayacTable !== "undefined") sayacTable.ajax.reload(null, false);
          if (typeof zimmetTable !== "undefined") zimmetTable.ajax.reload(null, false);
          if (typeof sayacZimmetTable !== "undefined") sayacZimmetTable.draw(false);
          
          // İstatistikleri güncelle (sayfada varsa)
          if (typeof window.loadStats === "function") window.loadStats();
        } else {
          Swal.fire("Hata", res.message, "error");
        }
      },
      error: function () {
        $("#btnKasiyeKaydet").prop("disabled", false).html("Evet, Teslim Et");
        Swal.fire("Hata", "Sistem hatası oluştu.", "error");
      },
    });
  });

  // Modal kapandığında formu sıfırla
  $("#kasiyeTeslimModal").on("hidden.bs.modal", function() {
    $("#kasiyeTeslimForm")[0].reset();
    $("#kasiye_is_toplu").val("0");
    $("#kasiye_toplu_ids").val("");
    $("#kasiye_demirbas_id").val("");
    $("#kasiyeOnayMesaji").html('Seçili <span class="fw-bold text-dark" id="kasiyeTopluAdetV2">1</span> adet sayacı Kaskiye teslim etmek istiyor musunuz?');
  });
});

