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
      var $thead = $("#" + tableId + " thead");

      if ($thead.find(".search-input-row").length > 0) {
        return;
      }

      var $searchRow = $('<tr class="search-input-row"></tr>');
      $thead.append($searchRow);

      // PageLength select kutusunun düzgün görünmesi için
      $(settings.nTableWrapper)
        .find(".dataTables_length label")
        .addClass("d-flex align-items-center");
      $(settings.nTableWrapper)
        .find(".dataTables_length select")
        .addClass("mx-2");

      api.columns().every(function () {
        let column = this;
        let title = column.header().textContent;

        if (
          title != "İşlem" &&
          title != "Seç" &&
          title != "#" &&
          $(column.header()).find('input[type="checkbox"]').length === 0
        ) {
          // Create input element
          let input = document.createElement("input");
          input.placeholder = title;
          input.classList.add("form-control");
          input.classList.add("form-control-sm");
          input.setAttribute("autocomplete", "off");

          // Append input element to the new row
          const th = $('<th class="search">').append(input);
          $searchRow.append(th);

          // Türkçe arama için: column.search() yerine data attribute kullanıyoruz
          $(input).attr("data-col-idx", column.index());
          $(input).on("input", function () {
            let val = $(this).val();
            let colIdx = $(this).attr("data-col-idx");
            let table = $(this).closest("table").DataTable();

            // Eğer serverSide ise, DataTables'ın kendi arama mekanizmasını tetikle
            if (table.settings()[0].oFeatures.bServerSide) {
              table.column(colIdx).search(val).draw();
            } else {
              // Client-side ise sadece draw() yeterli (custom filter çalışır)
              table.draw();
            }
          });

          // Sütunun gerçekten görünür olup olmadığını kontrol et
          const isColumnVisible =
            column.visible() && !$(column.header()).hasClass("dtr-hidden");

          if (!isColumnVisible) {
            th.hide(); // Sütun gerçekten görünmüyorsa input'u da gizle
          }
        } else {
          // Eğer "İşlem" sütunuysa, boş bir th ekleyin
          $searchRow.append("<th></th>");
        }
      });

      // Responsive olayını dinle
      api.on("responsive-resize", function (e, datatable, columns) {
        // Sütun görünürlüğünü kontrol et ve inputları gizle/göster
        $searchRow.find("th").each(function (index) {
          if (columns[index]) {
            $(this).show(); // Sütun görünüyorsa inputu göster
          } else {
            $(this).hide(); // Sütun gizliyse inputu gizle
          }
        });
      });

      var state = api.state.loaded();
      if (state) {
        $searchRow.find("input").each(function (index) {
          var colIdx = $(this).attr("data-col-idx");
          if (colIdx && state.columns[colIdx]) {
            var searchValue = state.columns[colIdx].search.search;
            if (searchValue) {
              $(this).val(searchValue);
            }
          }
        });
      }
    },
  };
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
