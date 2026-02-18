let table;
$(document).ready(function () {
  table = $(".datatable").DataTable(getDatatableOptions());
});

function getDatatableOptions() {
  return {
    stateSave: false,
    responsive: true,
    scrollX: false,
    pageLength: 10,
    dom: 't<"row"<"col-sm-12 col-md-6 d-flex align-items-center justify-content-start"i<"ms-3 text-nowrap"l>><"col-sm-12 col-md-6 d-flex justify-content-end"p>>',
    language: {
      url: "assets/js/tr.json",
      emptyTable:
        '<div class="text-center py-5"><div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 100px; height: 100px; background: linear-gradient(135deg, rgba(85,110,230,0.15) 0%, rgba(85,110,230,0.05) 100%); border: 2px dashed rgba(85,110,230,0.3);"><i class="bx bx-folder-open text-primary" style="font-size: 48px;"></i></div><h5 class="text-dark fw-semibold mb-2">Veri Bulunamadı</h5><p class="text-muted mb-0" style="max-width: 280px; margin: 0 auto;">Bu tabloda henüz gösterilecek kayıt bulunmuyor.</p></div>',
      zeroRecords:
        '<div class="text-center py-5"><div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 100px; height: 100px; background: linear-gradient(135deg, rgba(241,180,76,0.15) 0%, rgba(241,180,76,0.05) 100%); border: 2px dashed rgba(241,180,76,0.3);"><i class="bx bx-search-alt text-warning" style="font-size: 48px;"></i></div><h5 class="text-dark fw-semibold mb-2">Sonuç Bulunamadı</h5><p class="text-muted mb-0" style="max-width: 280px; margin: 0 auto;">Arama kriterlerinize uygun kayıt bulunamadı.</p></div>',
    },
    buttons: ["excel"],

    ...getTableSpecificOptions(),

    initComplete: function (settings, json) {
      var api = this.api();
      var tableId = settings.sTableId;
      var $nTable = $(settings.nTable);
      var $thead = $nTable.find("thead");

      // Gelişmiş filtre var mı kontrol et (Daha sağlam kontrol)
      var hasAnyAdvancedFilter = $thead.find("th[data-filter]").length > 0;

      // PageLength select kutusunun düzgün görünmesi için
      $(settings.nTableWrapper)
        .find(".dataTables_length label")
        .addClass("d-flex align-items-center");
      $(settings.nTableWrapper)
        .find(".dataTables_length select")
        .addClass("mx-2");

      if (hasAnyAdvancedFilter) {
        // Gelişmiş filtre varsa, eski basit filtre satırını HİÇ oluşturma.
        // initAdvancedFilters fonksiyonu aşağıda (satır 145) toplu olarak çağrılıyor.
      } else {
        // Sadece eski tip filtreler varsa eski mantığı çalıştır
        if ($thead.find(".search-input-row").length > 0) return;
        var $searchRow = $('<tr class="search-input-row"></tr>');
        $thead.append($searchRow);

        api.columns().every(function () {
          let column = this;
          let title = column.header().textContent;

          if (
            title != "İşlem" &&
            title != "Seç" &&
            title != "#" &&
            $(column.header()).find('input[type="checkbox"]').length === 0
          ) {
            let input = document.createElement("input");
            input.placeholder = title;
            input.classList.add("form-control", "form-control-sm");
            input.setAttribute("autocomplete", "off");
            $(input).attr("data-col-idx", column.index());

            const th = $('<th class="search">').append(input);
            $searchRow.append(th);

            // Eski tip Tarih sütunu desteği
            if (title === "Tarih") {
              $(input).addClass("flatpickr-datatable");
              $(input).flatpickr({
                locale: "tr",
                dateFormat: "d.m.Y",
                allowInput: true,
                onChange: function (selectedDates, dateStr) {
                  let colIdx = $(input).attr("data-col-idx");
                  let table = $(input).closest("table").DataTable();
                  if (table.settings()[0].oFeatures.bServerSide) {
                    table.column(colIdx).search(dateStr).draw();
                  } else {
                    table.draw();
                  }
                },
              });
            }

            let searchTimeout;
            $(input).on("input change", function (event) {
              let val = $(this).val();
              let colIdx = $(this).attr("data-col-idx");
              let table = $(this).closest("table").DataTable();
              if (
                $(this).hasClass("flatpickr-datatable") &&
                event.type === "input"
              )
                return;

              clearTimeout(searchTimeout);
              searchTimeout = setTimeout(function () {
                if (table.settings()[0].oFeatures.bServerSide) {
                  table.column(colIdx).search(val).draw();
                } else {
                  table.draw();
                }
              }, 300);
            });

            if (!column.visible()) th.hide();
          } else {
            $searchRow.append("<th></th>");
          }
        });

        // Responsive olayını dinle
        api.on("responsive-resize", function (e, datatable, columns) {
          $searchRow.find("th").each(function (i) {
            columns[i] ? $(this).show() : $(this).hide();
          });
        });

        // State'den değerleri geri yükle
        var state = api.state.loaded();
        if (state) {
          $searchRow.find("input").each(function () {
            var colIdx = $(this).attr("data-col-idx");
            if (colIdx && state.columns[colIdx]) {
              var val = state.columns[colIdx].search.search;
              if (val) $(this).val(val);
            }
          });
        }
      }

      if (typeof feather !== "undefined") {
        feather.replace();
      }

      // Gelişmiş kolon filtreleri başlat (Sadece bir kez, initComplete sonunda)
      if (typeof initAdvancedFilters === "function") {
        initAdvancedFilters(api, settings);
      }
    },
  };
}

/**
 * DataTable seçeneklerine sadece sayfa uzunluğunu kaydedecek stateSave ayarlarını uygular.
 * @param {Object} options DataTable seçenekleri
 * @returns {Object} Güncellenmiş seçenekler
 */
function applyLengthStateSave(options) {
  options.stateSave = true;
  options.stateSaveParams = function (settings, data) {
    // Sadece sayfa uzunluğunu (length) sakla, diğer her şeyi sıfırla
    data.start = 0;
    data.search.search = "";
    data.order = [];
    if (data.columns) {
      data.columns.forEach((col) => {
        col.search.search = "";
      });
    }
  };
  return options;
}

$("#exportExcel").on("click", function () {
  table.button(".buttons-excel").trigger();
});

function getTableSpecificOptions() {
  return {
    ordering: document.getElementById("gelirGiderTable") ? false : true,
  };
}

// DataTables Türkçe karakter arama desteği
(function () {
  // Türkçe karakterleri normalize eden fonksiyon
  // ÖNEMLİ: Önce büyük Türkçe harfler dönüştürülmeli, sonra toLowerCase uygulanmalı
  function normalizeTR(data) {
    if (!data) return "";

    return (
      data
        .toString()
        // Önce büyük Türkçe harfleri küçüğe çevir (toLowerCase'dan önce!)
        .replace(/İ/gi, "i")
        .replace(/I/g, "ı") // Noktasız büyük I -> ı
        .replace(/Ş/gi, "s")
        .replace(/Ğ/gi, "g")
        .replace(/Ü/gi, "u")
        .replace(/Ö/gi, "o")
        .replace(/Ç/gi, "c")
        // Sonra standart toLowerCase
        .toLowerCase()
        // Küçük Türkçe harfleri de ASCII'ye çevir
        .replace(/ı/g, "i")
        .replace(/ş/g, "s")
        .replace(/ğ/g, "g")
        .replace(/ü/g, "u")
        .replace(/ö/g, "o")
        .replace(/ç/g, "c")
        .replace(/â/g, "a")
        .replace(/î/g, "i")
        .replace(/û/g, "u")
    );
  }

  // Global search override
  $.fn.dataTable.ext.type.search.string = function (data) {
    return normalizeTR(data);
  };

  // Sütun bazlı arama için özel filter - Input değerlerini direkt DOM'dan oku
  $.fn.dataTable.ext.search.push(
    function (settings, searchData, dataIndex, rowData, counter) {
      var tableId = settings.sTableId;
      var dominated = false;

      // Bu tablodaki tüm arama inputlarını bul
      $("#" + tableId + " .search-input-row input").each(function () {
        var searchValue = $(this).val();
        if (searchValue && searchValue.length > 0) {
          var colIdx = parseInt($(this).attr("data-col-idx"));
          if (!isNaN(colIdx)) {
            var cellValue = searchData[colIdx] || "";
            var normalizedCell = normalizeTR(cellValue);
            var normalizedSearch = normalizeTR(searchValue);

            if (normalizedCell.indexOf(normalizedSearch) === -1) {
              dominated = true;
              return false; // break
            }
          }
        }
      });

      return !dominated;
    },
  );
})();
