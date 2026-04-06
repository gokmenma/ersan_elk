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
          const detailTableId = `detailsTable_${pId}_${date.replace(/\./g, '_')}`;
          let html = `
            <div class="ms-5 me-2 mb-3 bg-white border border-info border-start-0 border-end-0 border-bottom-0 border-top-4 rounded-bottom shadow-sm overflow-hidden animate__animated animate__fadeInDown">
                <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-info"><i class="bx bx-list-ul me-1"></i> GÜNLÜK HAREKET DETAYLARI (${data.personel_adi} - ${data.tarih})</span>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-success border-0 fw-bold px-2 export-excel-btn" data-target="${detailTableId}" data-filename="Sayac_Hareket_Detay_${data.personel_adi}_${data.tarih}">
                            <i class="bx bxs-file-export me-1"></i> Excel'e Aktar
                        </button>
                        <span class="badge bg-soft-info text-info">${res.data.length} işlem</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="${detailTableId}" class="table table-sm table-hover mb-0">
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

  // Excel Dışa Aktar Butonu Handleri
  $(document).on('click', '.export-excel-btn', function(e) {
    e.stopPropagation();
    const tableId = $(this).data('target');
    const filename = $(this).data('filename');
    const table = document.getElementById(tableId);
    
    if (!table) return;

    let excelContent = '<table>';
    excelContent += table.innerHTML;
    excelContent += '</table>';

    // UTF-8 BOM ekle (Türkçe karakterler için)
    const blob = new Blob(['\ufeff', excelContent], {
        type: 'application/vnd.ms-excel;charset=utf-8'
    });
    
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.xls';
    a.click();
    URL.revokeObjectURL(url);
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

  // Sayaçları Tümünü Seç
  let isAllFilteredSelected = false;

  function updateSelectionInfo() {
    const selectedOnPage = $(".sayac-select:checked").length;
    const totalFiltered = (typeof sayacTable !== "undefined") ? sayacTable.page.info().recordsDisplay : 0;

    if (selectedOnPage > 0 || isAllFilteredSelected) {
      $("#sayacSelectedCount").text(isAllFilteredSelected ? totalFiltered : selectedOnPage);
      $("#sayacTotalFilteredCount").text(totalFiltered);
      $("#sayacSelectAllInfo").removeClass("d-none").addClass("d-flex");
      
      const infoArea = $("#sayacSelectionMessage");

      if (isAllFilteredSelected) {
        infoArea.html('Filtrelenmiş <span class="fw-bold">' + totalFiltered + '</span> kaydın tümü seçildi. ' + 
            (totalFiltered > $(".sayac-select").length ? '<a href="javascript:void(0);" class="text-primary ms-2" id="btnSelectOnlyVisible">Sadece bu sayfayı seç</a>' : '') +
            '<a href="javascript:void(0);" class="text-danger ms-2" id="btnClearSelectionLink">Seçimi Temizle</a>');
      } else {
        infoArea.html('<span id="sayacSelectedCount">' + selectedOnPage + '</span> kayıt seçildi. ' + 
            (totalFiltered > selectedOnPage ? '<a href="javascript:void(0);" class="fw-bold text-decoration-underline ms-1" id="btnSelectAllFiltered">Filtrelenmiş ' + totalFiltered + ' kaydın tümünü seç</a>' : '') +
            '<a href="javascript:void(0);" class="text-danger ms-2" id="btnClearSelectionLink">Seçimi Temizle</a>');
      }
    } else {
      isAllFilteredSelected = false;
      $("#sayacSelectAllInfo").addClass("d-none").removeClass("d-flex");
    }
  }

  function resetSelectAllLink() {
    $("#btnSelectAllFiltered").parent().html('Filtrelenmiş <span id="sayacTotalFilteredCount">0</span> kaydın tümünü seç');
  }

  $(document).on("change", "#selectAllSayac", function () {
    const isChecked = $(this).prop("checked");
    const totalFiltered = (typeof sayacTable !== "undefined") ? sayacTable.page.info().recordsDisplay : 0;
    
    $(".sayac-select").prop("checked", isChecked);
    
    if (isChecked && totalFiltered > 0) {
      isAllFilteredSelected = true;
    } else {
      isAllFilteredSelected = false;
    }
    
    updateSelectionInfo();
  });

  $(document).on("click", "#btnSelectOnlyVisible", function (e) {
    e.preventDefault();
    isAllFilteredSelected = false;
    updateSelectionInfo();
  });

  $(document).on("click", "#btnSelectAllFiltered", function (e) {
    e.preventDefault();
    isAllFilteredSelected = true;
    updateSelectionInfo();
  });

  $(document).on("click", "#btnClearSelection, #btnClearSelectionLink", function (e) {
    e.preventDefault();
    isAllFilteredSelected = false;
    $(".sayac-select, #selectAllSayac").prop("checked", false);
    updateSelectionInfo();
    resetSelectAllLink();
  });

  $(document).on("change", ".sayac-select", function () {
    if (!$(this).prop("checked")) {
      $("#selectAllSayac").prop("checked", false);
      isAllFilteredSelected = false;
    } else {
      if ($(".sayac-select:checked").length === $(".sayac-select").length && $(".sayac-select").length > 0) {
        $("#selectAllSayac").prop("checked", true);
      }
    }
    updateSelectionInfo();
  });

  // Toplu Silme İşlemi
  $(document).on("click", "#btnTopluSilSayac", function (e) {
    e.preventDefault();
    const selected = [];
    $(".sayac-select:checked").each(function () {
      selected.push($(this).val());
    });

    const totalFiltered = (typeof sayacTable !== "undefined") ? sayacTable.page.info().recordsDisplay : 0;
    
    if (selected.length === 0 && !isAllFilteredSelected) {
      Swal.fire("Uyarı", "Lütfen silmek istediğiniz sayaçları seçin.", "warning");
      return;
    }

    const countToDel = isAllFilteredSelected ? totalFiltered : selected.length;

    Swal.fire({
      title: "Emin misiniz?",
      html: `Seçili <b>${countToDel}</b> adet sayacı silmek istediğinizden emin misiniz? <br><small class="text-danger">Zimmet geçmişi olan kayıtlar silinemeyecektir.</small>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Evet, Sil!",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: "Siliniyor...",
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading(),
        });

        // Backend'e gönderilecek parametreler
        const postData = { 
          action: "bulk-demirbas-sil", 
          ids: selected,
          all_filtered: isAllFilteredSelected ? 1 : 0
        };

        // Eğer tümü seçiliyse filtreleri de gönder
        if (isAllFilteredSelected) {
          postData.tab = "sayac";
          postData.status_filter = $('input[name="sayac-status-filter"]:checked').val() || "";
          
          // Gelişmiş filtreleri/arama parametrelerini de ekle (DataTable'dan alabiliriz)
          if (typeof sayacTable !== "undefined") {
            postData.search_val = sayacTable.search();
            // Kolon bazlı filtreleri topla
            const colSearches = {};
            sayacTable.columns().every(function(idx) {
               const s = this.search();
               if(s) colSearches[idx] = s;
            });
            postData.column_searches = colSearches;
          }
        }

        $.post(apiUrl, postData, function (res) {
          if (res.status === "success") {
            Swal.fire("Başarılı", res.message, "success");
            if (typeof sayacTable !== "undefined") sayacTable.ajax.reload(null, false);
            $("#selectAllSayac, .sayac-select").prop("checked", false);
            isAllFilteredSelected = false;
            updateSelectionInfo();
            
            if (typeof window.loadStats === "function") window.loadStats();
          } else {
            Swal.fire("Hata", res.message, "error");
          }
        }, 'json').fail(function() {
          Swal.fire("Hata", "Sistem hatası oluştu.", "error");
        });
      }
    });
  });

  // Tablo yenilendiğinde seçimi temizle (veya koru, ama genellikle temizlenmesi istenir)
  if (typeof sayacTable !== "undefined") {
    sayacTable.on('draw', function() {
       if(!isAllFilteredSelected) {
           $("#selectAllSayac, .sayac-select").prop("checked", false);
           updateSelectionInfo();
       } else {
           // Tüm filtrelenmiş seçiliyken yeni gelen satırları da işaretle
           $(".sayac-select").prop("checked", true);
       }
    });
  }
});

